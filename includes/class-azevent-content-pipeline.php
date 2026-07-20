<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_Content_Pipeline
{
    public function process_step(array $arguments)
    {
        $keyword = sanitize_text_field($arguments['keyword'] ?? '');
        $language = sanitize_text_field($arguments['language'] ?? 'Vietnamese');
        $post_id = absint($arguments['post_id'] ?? 0);
        $step = sanitize_key($arguments['step'] ?? 'start');
        $mode = sanitize_key($arguments['mode'] ?? 'create');
        $regenerate_image = !empty($arguments['regenerate_image']);
        $author_id = absint($arguments['author_id'] ?? 0);
        $context = isset($arguments['context']) && is_array($arguments['context']) ? $arguments['context'] : array();

        if ($keyword === '') {
            return new WP_Error('azevent_missing_keyword', 'Vui lòng nhập từ khóa.');
        }

        if (!in_array($mode, array('create', 'rewrite'), true)) {
            $mode = 'create';
        }

        if ($mode === 'create' && $post_id <= 0 && $step === 'start') {
            $post_id = wp_insert_post(array(
                'post_type' => 'post',
                'post_status' => 'draft',
                'post_title' => $keyword,
                'post_author' => $author_id,
            ), true);
            if (is_wp_error($post_id)) {
                return new WP_Error('azevent_create_draft_failed', 'Không thể tạo Draft cho từ khóa: ' . $post_id->get_error_message());
            }
        }

        if ($post_id <= 0) {
            return new WP_Error('azevent_missing_post', 'Không xác định được bài viết đang xử lý.');
        }

        $context['post_id'] = $post_id;

        if ($mode === 'rewrite') {
            $existing_post = get_post($post_id);
            if (!$existing_post || $existing_post->post_type !== 'post') {
                return new WP_Error('azevent_missing_rewrite_post', 'Không tìm thấy bài viết cần viết lại.');
            }

            $browser_post = isset($context['existing_post']) && is_array($context['existing_post'])
                ? $context['existing_post']
                : array();
            $existing_content = !empty($browser_post['content'])
                ? (string) $browser_post['content']
                : (string) $existing_post->post_content;
            $existing_content = function_exists('mb_substr')
                ? mb_substr($existing_content, 0, 30000)
                : substr($existing_content, 0, 30000);

            $context['existing_post'] = array(
                'title' => !empty($browser_post['title'])
                    ? sanitize_text_field($browser_post['title'])
                    : (string) $existing_post->post_title,
                'content' => $existing_content,
                'excerpt' => (string) $existing_post->post_excerpt,
                'slug' => (string) $existing_post->post_name,
                'has_thumbnail' => has_post_thumbnail($post_id),
            );
        }

        $ai = new AzEvent_AI_Service();
        $brand_defaults = AzEvent_SEO_Content::get_default_brand_profile();
        $brand_name = get_option('azevent_seo_brand_name', $brand_defaults['azevent_seo_brand_name']);
        $brand_info = get_option('azevent_seo_brand_info', $brand_defaults['azevent_seo_brand_info']);
        $brand_solution = get_option('azevent_seo_brand_solution', $brand_defaults['azevent_seo_brand_solution']);

        $replace_placeholders = function ($text, $replacement_context) use ($keyword, $language, $brand_name, $brand_info, $brand_solution, $mode) {
            $placeholders = array(
                '{keyword}' => $keyword,
                '{secondary_keywords}' => $keyword,
                '{language}' => $language,
                '{outline_focus}' => isset($replacement_context['outline_focus']) ? $replacement_context['outline_focus'] : '',
                '{brand_name}' => $brand_name,
                '{brand_info}' => $brand_info,
                '{brand_solution}' => $brand_solution,
                '{search_intent}' => isset($replacement_context['search_intent']) ? $replacement_context['search_intent'] : '',
                '{outline}' => isset($replacement_context['outline']) ? $replacement_context['outline'] : '',
                '{content}' => isset($replacement_context['content']) ? $replacement_context['content'] : '',
                '{existing_title}' => isset($replacement_context['existing_post']['title']) ? $replacement_context['existing_post']['title'] : '',
                '{existing_content}' => isset($replacement_context['existing_post']['content']) ? $replacement_context['existing_post']['content'] : '',
                '{existing_excerpt}' => isset($replacement_context['existing_post']['excerpt']) ? $replacement_context['existing_post']['excerpt'] : '',
                '{existing_slug}' => isset($replacement_context['existing_post']['slug']) ? $replacement_context['existing_post']['slug'] : '',
                '{rewrite_goal}' => $mode === 'rewrite'
                    ? 'Viết lại bài hiện tại, giữ thông tin đúng và cải thiện chất lượng SEO.'
                    : 'Tạo nội dung mới từ đầu.',
            );

            return str_replace(array_keys($placeholders), array_values($placeholders), $text);
        };

        $prompt_defaults = AzEvent_Editor_Integration::get_default_prompts();
        $get_prompt = function ($option, $default) {
            $value = get_option($option, '');
            return trim((string) $value) === '' ? $default : $value;
        };
        $rewrite_instructions = array(
            'intent' => "\n\nChế độ viết lại. Hãy phân tích bài hiện tại và chỉ ra search intent, điểm yếu, thông tin thiếu hoặc lỗi thời. Bài hiện tại có tiêu đề '{existing_title}':\n{existing_content}",
            'outline' => "\n\nChế độ viết lại. Hãy tạo outline cải tiến dựa trên intent và bài hiện tại dưới đây. Giữ lại thông tin đúng, bổ sung phần còn thiếu, tránh lặp lại nội dung cũ không cần thiết.\n{existing_content}",
            'content' => "\n\nChế độ viết lại. Hãy viết lại bài hiện tại dưới đây theo outline mới. Giữ lại thông tin đúng, không tự bịa số liệu hoặc cam kết, cải thiện chiều sâu SEO và khả năng chuyển đổi.\n{existing_content}",
            'seo' => "\n\nĐây là chế độ viết lại. Hãy tạo metadata mới cho nội dung, nhưng giữ slug hiện tại '{existing_slug}' trừ khi có lý do SEO rõ ràng.",
        );
        $uses_azevent_api = AzEvent_API_Client::is_configured();
        $step_models = array(
            'intent' => $uses_azevent_api
                ? sanitize_text_field(get_option('azevent_seo_intent_model', ''))
                : 'claude-3-5-haiku-20241022',
            'outline' => $uses_azevent_api
                ? sanitize_text_field(get_option('azevent_seo_outline_model', ''))
                : 'claude-3-5-sonnet-20240620',
            'content' => $uses_azevent_api
                ? sanitize_text_field(get_option('azevent_seo_content_model', ''))
                : 'claude-3-5-sonnet-20240620',
            'seo' => $uses_azevent_api
                ? sanitize_text_field(get_option('azevent_seo_seo_model', ''))
                : '',
        );

        switch ($step) {
            case 'start':
            case 'search_intent':
                $system_prompt = $get_prompt('azevent_seo_intent_system', $prompt_defaults['intent']['system']);
                $user_prompt = $get_prompt('azevent_seo_intent_user', $prompt_defaults['intent']['user']);
                if ($mode === 'rewrite') {
                    $user_prompt .= $rewrite_instructions['intent'];
                }
                $result = $ai->call_anthropic(
                    $replace_placeholders($user_prompt, $context),
                    $replace_placeholders($system_prompt, $context),
                    $step_models['intent'],
                    4096
                );
                if (is_wp_error($result)) {
                    return $this->attach_error_context($result, $post_id, $context);
                }
                $context['search_intent'] = $result;
                return array(
                    'status' => 'processing',
                    'message' => 'Đã hoàn thành phân tích mục đích tìm kiếm. Đang xây dựng dàn ý bài viết...',
                    'next_step' => 'outline',
                    'post_id' => $post_id,
                    'context' => $context,
                );

            case 'outline':
                $system_prompt = $get_prompt('azevent_seo_outline_system', $prompt_defaults['outline']['system']);
                $user_prompt = $get_prompt('azevent_seo_outline_user', $prompt_defaults['outline']['user']);
                if ($mode === 'rewrite') {
                    $user_prompt .= $rewrite_instructions['outline'];
                }
                $result = $ai->call_anthropic(
                    $replace_placeholders($user_prompt, $context),
                    $replace_placeholders($system_prompt, $context),
                    $step_models['outline'],
                    6144,
                    array(
                        'auto_continue' => true,
                        'max_continuations' => 2,
                    )
                );
                if (is_wp_error($result)) {
                    return $this->attach_error_context($result, $post_id, $context);
                }
                $context['outline'] = $result;
                return array(
                    'status' => 'processing',
                    'message' => 'Đã xây dựng dàn ý chi tiết. Đang tiến hành viết nội dung bài viết...',
                    'next_step' => 'content',
                    'post_id' => $post_id,
                    'context' => $context,
                );

            case 'content':
                $system_prompt = $get_prompt('azevent_seo_content_system', $prompt_defaults['content']['system']);
                $user_prompt = $get_prompt('azevent_seo_content_user', $prompt_defaults['content']['user']);
                if ($mode === 'rewrite') {
                    $user_prompt .= $rewrite_instructions['content'];
                }
                $result = $ai->call_anthropic(
                    $replace_placeholders($user_prompt, $context),
                    $replace_placeholders($system_prompt, $context),
                    $step_models['content'],
                    8192,
                    array(
                        'auto_continue' => true,
                        'max_continuations' => 2,
                        'detect_incomplete_ending' => true,
                    )
                );
                if (is_wp_error($result)) {
                    return $this->attach_error_context($result, $post_id, $context);
                }
                $context['content'] = $result;
                return array(
                    'status' => 'processing',
                    'message' => 'Nội dung bài viết đã hoàn tất. Đang tạo dữ liệu SEO Meta...',
                    'next_step' => 'seo',
                    'post_id' => $post_id,
                    'context' => $context,
                );

            case 'seo':
                $system_prompt = $get_prompt('azevent_seo_seo_system', $prompt_defaults['seo']['system']);
                $user_prompt = $get_prompt('azevent_seo_seo_user', $prompt_defaults['seo']['user']);
                if ($mode === 'rewrite') {
                    $user_prompt .= $rewrite_instructions['seo'];
                }
                $seo_arguments = array(
                    'response_format' => array('type' => 'json_object'),
                    'max_tokens' => 2048,
                );
                if ($step_models['seo'] !== '') {
                    $seo_arguments['model'] = $step_models['seo'];
                }
                $result = $ai->call_openai(
                    $replace_placeholders($user_prompt, $context),
                    $replace_placeholders($system_prompt, $context),
                    'chat/completions',
                    $seo_arguments
                );
                if (is_wp_error($result)) {
                    return $this->attach_error_context($result, $post_id, $context);
                }
                $seo_data = json_decode($result, true);
                if (!is_array($seo_data) || empty($seo_data['title']) || empty($seo_data['meta']) || empty($seo_data['image_prompt'])) {
                    return new WP_Error('azevent_invalid_seo_response', 'AI trả về dữ liệu SEO không đầy đủ.');
                }
                $context['seo'] = $seo_data;
                $context['mode'] = $mode;
                $context['regenerate_image'] = $regenerate_image;
                $should_generate_image = $mode !== 'rewrite' || $regenerate_image || !has_post_thumbnail($post_id);
                return array(
                    'status' => 'processing',
                    'message' => $should_generate_image
                        ? 'Đã tối ưu SEO. Đang xử lý ảnh đại diện...'
                        : 'Đã tối ưu SEO. Đang lưu bản viết lại dưới dạng bản nháp...',
                    'next_step' => $should_generate_image ? 'image' : 'finalize',
                    'post_id' => $post_id,
                    'context' => $context,
                );

            case 'image':
                if (empty($context['seo']['image_prompt']) || empty($context['seo']['title'])) {
                    return new WP_Error('azevent_missing_image_context', 'Thiếu dữ liệu SEO để tạo ảnh.');
                }
                $image_prompt = $context['seo']['image_prompt'] . ' Cinematic style, professional photography, high resolution, no text, no watermark.';
                $image_result = $ai->generate_image($image_prompt, '', '1:1');
                if (is_wp_error($image_result)) {
                    return $image_result;
                }
                $attachment_id = $this->upload_image_from_result($image_result, $post_id, $context['seo']['title']);
                if (is_wp_error($attachment_id)) {
                    return new WP_Error('azevent_image_upload_failed', 'Lỗi tải ảnh: ' . $attachment_id->get_error_message());
                }
                set_post_thumbnail($post_id, $attachment_id);
                return $this->complete_generation($post_id, $context, $mode);

            case 'finalize':
                return $this->complete_generation($post_id, $context, $mode);
        }

        return new WP_Error('azevent_invalid_step', 'Bước xử lý không hợp lệ.');
    }

    private function complete_generation($post_id, array $context, $mode)
    {
        if (empty($context['seo']['title']) || empty($context['seo']['meta']) || empty($context['content'])) {
            return new WP_Error('azevent_incomplete_context', 'Thiếu dữ liệu để lưu bài viết.');
        }

        $updated_post = array(
            'ID' => $post_id,
            'post_title' => $context['seo']['title'],
            'post_content' => $context['content'],
            'post_excerpt' => $context['seo']['meta'],
            'post_status' => 'draft',
        );

        if ($mode !== 'rewrite' && !empty($context['seo']['slug'])) {
            $updated_post['post_name'] = $context['seo']['slug'];
        }

        $updated = wp_update_post($updated_post, true);
        if (is_wp_error($updated)) {
            return new WP_Error('azevent_save_failed', 'Không thể lưu bài viết: ' . $updated->get_error_message());
        }

        return array(
            'status' => 'completed',
            'post_id' => $post_id,
            'message' => $mode === 'rewrite'
                ? 'Đã viết lại bài và lưu thành bản nháp.'
                : 'Bài viết mới đã được tạo thành công dưới dạng bản nháp.',
            'title' => $context['seo']['title'],
            'content' => $context['content'],
        );
    }

    private function attach_error_context($error, $post_id, array $context)
    {
        $error->add_data(array(
            'post_id' => absint($post_id),
            'context' => $context,
        ));
        return $error;
    }

    private function upload_image_from_result($image_result, $post_id, $title)
    {
        if (empty($image_result['base64'])) {
            return new WP_Error('empty_image_data', 'AzEvent API không trả về dữ liệu ảnh.');
        }

        $image_data = base64_decode(preg_replace('/\s+/', '', $image_result['base64']), true);
        if ($image_data === false || $image_data === '') {
            return new WP_Error('invalid_image_data', 'Dữ liệu ảnh từ AzEvent API không hợp lệ.');
        }

        $detected = function_exists('getimagesizefromstring') ? @getimagesizefromstring($image_data) : false;
        if ($detected === false || empty($detected['mime']) || strpos($detected['mime'], 'image/') !== 0) {
            return new WP_Error('invalid_image_response', 'AzEvent API trả về dữ liệu không phải ảnh.');
        }

        $mime = sanitize_mime_type($detected['mime']);
        $extensions = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        );
        $extension = isset($extensions[$mime]) ? $extensions[$mime] : 'png';
        $file_name = sanitize_title($title) . '-azevent.' . $extension;
        $upload = wp_upload_bits($file_name, null, $image_data);
        if (!empty($upload['error'])) {
            return new WP_Error('image_upload_failed', $upload['error']);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment = array(
            'post_mime_type' => $mime,
            'post_title' => $title,
            'post_content' => 'AI-generated image for ' . $title,
            'post_status' => 'inherit',
            'guid' => $upload['url'],
        );
        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id, true);
        if (is_wp_error($attachment_id)) {
            wp_delete_file($upload['file']);
            return $attachment_id;
        }

        update_post_meta($attachment_id, '_wp_attachment_image_alt', $title);
        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        return $attachment_id;
    }
}
