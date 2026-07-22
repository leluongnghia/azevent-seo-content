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
        $brand_profile = AzEvent_SEO_Content::get_brand_profile();
        $brand_name = $brand_profile['azevent_seo_brand_name'];
        $brand_info = $brand_profile['azevent_seo_brand_info'];
        $brand_solution = $brand_profile['azevent_seo_brand_solution'];

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
        $default_text_provider = sanitize_key(get_option('azevent_seo_text_provider', 'azevent'));
        $default_ckey_model = AzEvent_CKey_Client::model_reference(get_option('azevent_seo_ckey_model', ''));
        $default_step_model = $default_text_provider === 'ckey' ? $default_ckey_model : '';
        $uses_primary_api = AzEvent_API_Client::is_configured() || AzEvent_CKey_Client::is_configured();
        $step_models = array(
            'intent' => $uses_primary_api
                ? sanitize_text_field(get_option('azevent_seo_intent_model', $default_step_model))
                : 'claude-3-5-haiku-20241022',
            'outline' => $uses_primary_api
                ? sanitize_text_field(get_option('azevent_seo_outline_model', $default_step_model))
                : 'claude-3-5-sonnet-20240620',
            'content' => $uses_primary_api
                ? sanitize_text_field(get_option('azevent_seo_content_model', $default_step_model))
                : 'claude-3-5-sonnet-20240620',
            'seo' => $uses_primary_api
                ? sanitize_text_field(get_option('azevent_seo_seo_model', $default_step_model))
                : '',
        );
        foreach ($step_models as $step_key => $step_model) {
            if ($uses_primary_api && $step_model === '') {
                $step_models[$step_key] = $default_step_model;
            }
        }

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
                $outline_message = 'Đã xây dựng dàn ý chi tiết. Đang tiến hành viết nội dung bài viết...';
                if (absint(get_option('azevent_seo_split_content_by_outline', 0)) === 1) {
                    $sections = array_slice(AzEvent_Outline_Sections::extract($result), 0, 20);
                    $context['content_split'] = array(
                        'initialized' => true,
                        'enabled' => count($sections) >= 2,
                        'completed' => false,
                        'sections' => count($sections) >= 2 ? $sections : array(),
                        'current_index' => 0,
                        'parts' => array(),
                        'attempts' => array(),
                        'history' => array(),
                        'fallback_reason' => count($sections) >= 2 ? '' : 'Không nhận diện được ít nhất 2 H2 trong Outline.',
                    );
                    $outline_message = count($sections) >= 2
                        ? sprintf('Đã nhận diện %d H2. Sẵn sàng viết lần lượt từng phần.', count($sections))
                        : 'Không nhận diện được ít nhất 2 H2; Content sẽ viết toàn bài trong một request.';
                }
                return array(
                    'status' => 'processing',
                    'message' => $outline_message,
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
                $split_state = isset($context['content_split']) && is_array($context['content_split'])
                    ? $context['content_split']
                    : array();
                $split_in_progress = !empty($split_state['enabled']) && empty($split_state['completed']) && !empty($split_state['sections']);
                $split_enabled = $split_in_progress || absint(get_option('azevent_seo_split_content_by_outline', 0)) === 1;
                if ($split_enabled && empty($split_state['initialized'])) {
                    $sections = array_slice(AzEvent_Outline_Sections::extract($context['outline'] ?? ''), 0, 20);
                    $split_state = array(
                        'initialized' => true,
                        'enabled' => count($sections) >= 2,
                        'completed' => false,
                        'sections' => count($sections) >= 2 ? $sections : array(),
                        'current_index' => 0,
                        'parts' => array(),
                        'attempts' => array(),
                        'history' => array(),
                        'fallback_reason' => count($sections) >= 2 ? '' : 'Không nhận diện được ít nhất 2 H2 trong Outline.',
                    );
                    $context['content_split'] = $split_state;
                }

                if ($split_enabled && !empty($split_state['enabled']) && empty($split_state['completed'])) {
                    $sections = array_values($split_state['sections']);
                    $section_index = max(0, absint($split_state['current_index'] ?? 0));
                    if (!isset($sections[$section_index])) {
                        return $this->attach_error_context(
                            new WP_Error('azevent_invalid_content_split_checkpoint', 'Checkpoint H2 của Content Studio không hợp lệ.'),
                            $post_id,
                            $context
                        );
                    }

                    $split_state['attempts'][$section_index] = absint($split_state['attempts'][$section_index] ?? 0) + 1;
                    $split_state['last_error'] = '';
                    $context['content_split'] = $split_state;
                    $section_prompts = $this->build_content_section_prompts(
                        $replace_placeholders($system_prompt, $context),
                        $replace_placeholders($user_prompt, $context),
                        $context,
                        $split_state,
                        $section_index
                    );
                    $result = $ai->call_anthropic(
                        $section_prompts['user'],
                        $section_prompts['system'],
                        $step_models['content'],
                        6144,
                        array(
                            'auto_continue' => true,
                            'max_continuations' => 2,
                            'detect_incomplete_ending' => true,
                        )
                    );
                    if (is_wp_error($result)) {
                        $split_state['last_error'] = $result->get_error_message();
                        $context['content_split'] = $split_state;
                        return $this->attach_error_context($result, $post_id, $context);
                    }

                    $section_content = $this->clean_generated_html($result);
                    if ($section_content === '') {
                        $split_state['last_error'] = 'AI không trả về HTML hợp lệ cho H2 hiện tại.';
                        $context['content_split'] = $split_state;
                        return $this->attach_error_context(
                            new WP_Error('azevent_empty_content_section', $split_state['last_error']),
                            $post_id,
                            $context
                        );
                    }

                    $metrics = $ai->get_last_text_metrics();
                    $split_state['parts'][$section_index] = $section_content;
                    $split_state['history'][$section_index] = array(
                        'title' => sanitize_text_field($sections[$section_index]['title'] ?? ''),
                        'model' => sanitize_text_field($metrics['model'] ?? $step_models['content']),
                        'provider' => sanitize_text_field($metrics['provider'] ?? ''),
                        'input_tokens' => absint($metrics['input_tokens'] ?? 0),
                        'output_tokens' => absint($metrics['output_tokens'] ?? 0),
                        'duration_seconds' => (float) ($metrics['duration_seconds'] ?? 0),
                        'requests' => absint($metrics['requests'] ?? 1),
                        'attempts' => absint($metrics['attempts'] ?? 1),
                        'completed_at' => current_time('mysql'),
                    );
                    $metrics_summary = sprintf(
                        '%1$s%2$s · %3$s giây · %4$d input/%5$d output token',
                        sanitize_text_field($metrics['provider'] ?? 'AI'),
                        !empty($metrics['model']) ? ' (' . sanitize_text_field($metrics['model']) . ')' : '',
                        round((float) ($metrics['duration_seconds'] ?? 0), 1),
                        absint($metrics['input_tokens'] ?? 0),
                        absint($metrics['output_tokens'] ?? 0)
                    );
                    $split_state['current_index'] = $section_index + 1;

                    if ($split_state['current_index'] < count($sections)) {
                        $context['content_split'] = $split_state;
                        $next_section = $sections[$split_state['current_index']];
                        return array(
                            'status' => 'processing',
                            'message' => sprintf(
                                'Đã lưu H2 %1$d/%2$d · %4$s. Tiếp theo: %3$s.',
                                $section_index + 1,
                                count($sections),
                                sanitize_text_field($next_section['title'] ?? ''),
                                $metrics_summary
                            ),
                            'next_step' => 'content',
                            'post_id' => $post_id,
                            'context' => $context,
                        );
                    }

                    ksort($split_state['parts']);
                    $combined_content = trim(implode("\n\n", array_filter($split_state['parts'])));
                    if ($combined_content === '') {
                        return $this->attach_error_context(
                            new WP_Error('azevent_empty_split_content', 'Không thể ghép nội dung từ các H2 đã hoàn thành.'),
                            $post_id,
                            $context
                        );
                    }
                    $split_state['completed'] = true;
                    $split_state['completed_sections'] = count($sections);
                    $context['content_split'] = $split_state;
                    $context['content'] = $combined_content;
                    return array(
                        'status' => 'processing',
                        'message' => sprintf('Đã ghép đủ %1$d H2 · H2 cuối: %2$s. Đang tạo dữ liệu SEO Meta...', count($sections), $metrics_summary),
                        'next_step' => 'seo',
                        'post_id' => $post_id,
                        'context' => $context,
                    );
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
                $result = $this->clean_generated_html($result);
                if ($result === '') {
                    return new WP_Error('azevent_empty_content', 'AI không trả về nội dung bài viết hợp lệ.');
                }
                $context['content'] = $result;
                return array(
                    'status' => 'processing',
                    'message' => !empty($split_state['fallback_reason'])
                        ? $split_state['fallback_reason'] . ' Đã viết toàn bài và đang tạo dữ liệu SEO Meta...'
                        : 'Nội dung bài viết đã hoàn tất. Đang tạo dữ liệu SEO Meta...',
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
                $result = $this->clean_generated_json($result);
                $seo_data = json_decode($result, true);
                if (!is_array($seo_data) || empty($seo_data['title']) || empty($seo_data['meta']) || empty($seo_data['image_prompt'])) {
                    return new WP_Error('azevent_invalid_seo_response', 'AI trả về dữ liệu SEO không đầy đủ.');
                }
                $seo_data['image_alt'] = AzEvent_Image_SEO::normalize_alt(
                    $seo_data['image_alt'] ?? '',
                    $seo_data['title'],
                    $keyword
                );
                $context['seo'] = $seo_data;
                $context['mode'] = $mode;
                $context['regenerate_image'] = $regenerate_image;
                $should_generate_image = AzEvent_API_Client::is_configured()
                    && ($mode !== 'rewrite' || $regenerate_image || !has_post_thumbnail($post_id));
                $should_generate_section_images = AzEvent_Section_Images::is_enabled() && AzEvent_API_Client::is_configured();
                return array(
                    'status' => 'processing',
                    'message' => $should_generate_section_images
                        ? 'Đã tối ưu SEO. Sẵn sàng lập kế hoạch ảnh minh họa theo H2...'
                        : ($should_generate_image
                        ? 'Đã tối ưu SEO. Đang xử lý ảnh đại diện...'
                        : 'Đã tối ưu SEO. Đang lưu bản viết lại dưới dạng bản nháp...'),
                    'next_step' => $should_generate_section_images ? 'section_images' : ($should_generate_image ? 'image' : 'finalize'),
                    'post_id' => $post_id,
                    'context' => $context,
                );

            case 'section_images':
                if (empty($context['content']) || empty($context['seo'])) {
                    return $this->attach_error_context(
                        new WP_Error('azevent_missing_section_image_context', 'Thiếu Content hoặc SEO để tạo ảnh H2.'),
                        $post_id,
                        $context
                    );
                }
                if (!AzEvent_Section_Images::is_enabled() || !AzEvent_API_Client::is_configured()) {
                    $should_generate_image = AzEvent_API_Client::is_configured()
                        && ($mode !== 'rewrite' || $regenerate_image || !has_post_thumbnail($post_id));
                    return array(
                        'status' => 'processing',
                        'message' => 'Đã bỏ qua ảnh H2 theo cấu hình hiện tại.',
                        'next_step' => $should_generate_image ? 'image' : 'finalize',
                        'post_id' => $post_id,
                        'context' => $context,
                    );
                }
                if (empty($context['section_images']) || !is_array($context['section_images'])) {
                    $context['section_images'] = AzEvent_Section_Images::create_plan($context['content'], $keyword, $language);
                    AzEvent_Section_Images::save_state($post_id, $context['section_images']);
                    if (!empty($context['section_images']['completed'])) {
                        $should_generate_image = $mode !== 'rewrite' || $regenerate_image || !has_post_thumbnail($post_id);
                        return array(
                            'status' => 'processing',
                            'message' => $context['section_images']['message'] ?? 'Không có H2 phù hợp để tạo ảnh.',
                            'next_step' => $should_generate_image ? 'image' : 'finalize',
                            'post_id' => $post_id,
                            'context' => $context,
                        );
                    }
                    $planning_metrics = $context['section_images']['planning_metrics'] ?? array();
                    return array(
                        'status' => 'processing',
                        'message' => sprintf(
                            'Đã lập kế hoạch %1$d ảnh H2 · %2$s · %3$s giây · %4$d/%5$d token. Bắt đầu tạo ảnh thứ nhất...',
                            count($context['section_images']['items'] ?? array()),
                            sanitize_text_field($planning_metrics['model'] ?? $planning_metrics['provider'] ?? 'AI'),
                            round((float) ($planning_metrics['duration_seconds'] ?? 0), 1),
                            absint($planning_metrics['input_tokens'] ?? 0),
                            absint($planning_metrics['output_tokens'] ?? 0)
                        ),
                        'next_step' => 'section_images',
                        'post_id' => $post_id,
                        'context' => $context,
                    );
                }
                $processed = AzEvent_Section_Images::process_next($post_id, $context['content'], $context['section_images']);
                $context['content'] = $processed['content'];
                $context['section_images'] = $processed['state'];
                AzEvent_Section_Images::save_state($post_id, $context['section_images']);
                $item = $processed['item'] ?? array();
                $status_message = ($item['status'] ?? '') === 'created'
                    ? sprintf(
                        'Đã tạo và chèn ảnh cho H2: %1$s · %4$s · %2$s giây · %3$d lần gọi.',
                        sanitize_text_field($item['title'] ?? ''),
                        round((float) ($item['duration_seconds'] ?? 0), 1),
                        absint($item['attempts'] ?? 1),
                        sanitize_text_field($item['model'] ?? $item['provider'] ?? 'AI Image')
                    )
                    : 'Đã bỏ qua ảnh H2 ' . sanitize_text_field($item['title'] ?? '') . ': ' . sanitize_text_field($item['error'] ?? 'Không xác định.') . '.';
                if (empty($processed['done'])) {
                    $next_index = absint($context['section_images']['current_index'] ?? 0);
                    $total_images = count($context['section_images']['items'] ?? array());
                    return array(
                        'status' => 'processing',
                        'message' => $status_message . sprintf(' Tiếp tục ảnh %d/%d.', $next_index + 1, $total_images),
                        'next_step' => 'section_images',
                        'post_id' => $post_id,
                        'context' => $context,
                    );
                }
                $should_generate_image = $mode !== 'rewrite' || $regenerate_image || !has_post_thumbnail($post_id);
                return array(
                    'status' => 'processing',
                    'message' => $status_message . ' Đã hoàn tất ảnh H2.',
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
                    return $this->attach_error_context($image_result, $post_id, $context);
                }
                $attachment_id = AzEvent_Image_SEO::upload_base64($image_result, $post_id, array(
                    'role' => 'featured',
                    'title' => $context['seo']['title'],
                    'alt' => $context['seo']['image_alt'] ?? '',
                    'keyword' => $keyword,
                    'max_width' => 1600,
                    'max_height' => 1600,
                    'quality' => 82,
                ));
                if (is_wp_error($attachment_id)) {
                    return $this->attach_error_context(
                        new WP_Error('azevent_image_upload_failed', 'Lỗi tải ảnh: ' . $attachment_id->get_error_message()),
                        $post_id,
                        $context
                    );
                }
                $featured_result = AzEvent_Image_SEO::set_featured_image($post_id, $attachment_id);
                if (is_wp_error($featured_result)) {
                    return $this->attach_error_context($featured_result, $post_id, $context);
                }
                return $this->complete_generation($post_id, $context, $mode);

            case 'finalize':
                return $this->complete_generation($post_id, $context, $mode);
        }

        return new WP_Error('azevent_invalid_step', 'Bước xử lý không hợp lệ.');
    }

    private function complete_generation($post_id, array $context, $mode)
    {
        if (!empty($context['content'])) {
            $context['content'] = $this->clean_generated_html($context['content']);
        }

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
        if (!empty($context['section_images']) && is_array($context['section_images'])) {
            AzEvent_Section_Images::save_state($post_id, $context['section_images']);
        }

        return array(
            'status' => 'completed',
            'post_id' => $post_id,
            'message' => $mode === 'rewrite'
                ? 'Đã viết lại bài và lưu thành bản nháp.'
                : 'Bài viết mới đã được tạo thành công dưới dạng bản nháp.',
            'title' => $context['seo']['title'],
            'content' => $context['content'],
            'section_images' => isset($context['section_images']) && is_array($context['section_images']) ? $context['section_images'] : array(),
        );
    }

    private function clean_generated_html($content)
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', (string) $content);
        $content = trim($content);
        $content = preg_replace('/^\s*(?:```|~~~)(?:html|htm)?\s*/i', '', $content);
        $content = preg_replace('/\s*(?:```|~~~)\s*$/', '', $content);

        return trim($content);
    }

    private function clean_generated_json($content)
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', (string) $content);
        $content = trim($content);
        $content = preg_replace('/^\s*(?:```|~~~)(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*(?:```|~~~)\s*$/', '', $content);

        return trim($content);
    }

    private function build_content_section_prompts($system_prompt, $user_prompt, array $context, array $split_state, $section_index)
    {
        $sections = array_values($split_state['sections'] ?? array());
        $section = $sections[$section_index];
        $outline_plan = array();
        foreach ($sections as $index => $outline_section) {
            $outline_plan[] = sprintf('%d. %s', $index + 1, sanitize_text_field($outline_section['title'] ?? ''));
        }
        $previous_content = '';
        if ($section_index > 0 && isset($split_state['parts'][$section_index - 1])) {
            $previous_content = wp_strip_all_tags($split_state['parts'][$section_index - 1]);
            $previous_content = $this->tail_text($previous_content, 1800);
        }
        $position_instruction = $section_index === 0
            ? 'Viết mở bài ngắn trước H2 hiện tại, sau đó viết đầy đủ section đầu tiên.'
            : 'Bắt đầu trực tiếp bằng H2 hiện tại, nối mạch tự nhiên và không viết lại mở bài.';
        if ($section_index === count($sections) - 1) {
            $position_instruction .= ' Đây là section cuối; được phép thêm kết luận và CTA phù hợp sau nội dung H2.';
        } else {
            $position_instruction .= ' Không viết kết luận toàn bài hoặc CTA cuối bài.';
        }

        $user_prompt .= "\n\n## Chế độ bắt buộc: chỉ viết một phần H2\n";
        $user_prompt .= sprintf("- Phần hiện tại: %d/%d — %s\n", $section_index + 1, count($sections), sanitize_text_field($section['title'] ?? ''));
        $user_prompt .= "- Brief riêng của section:\n" . trim((string) ($section['outline'] ?? $section['title'] ?? '')) . "\n";
        $user_prompt .= "- Toàn bộ thứ tự H2:\n" . implode("\n", $outline_plan) . "\n";
        $user_prompt .= "- Đoạn cuối section trước để nối mạch và tránh lặp:\n" . ($previous_content !== '' ? $previous_content : 'Chưa có section trước.') . "\n";
        $user_prompt .= "- Chỉ dẫn vị trí: " . $position_instruction . "\n";
        $user_prompt .= '- Chỉ xuất HTML của section hiện tại. Không viết H2 khác, không lặp nội dung đã hoàn thành và không giải thích quy trình.';

        return array('system' => $system_prompt, 'user' => $user_prompt);
    }

    private function tail_text($value, $length)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, -$length) : substr($value, -$length);
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
        return AzEvent_Image_SEO::upload_base64($image_result, $post_id, array(
            'role' => 'featured',
            'title' => $title,
            'alt' => $title,
            'keyword' => $title,
            'max_width' => 1600,
            'max_height' => 1600,
            'quality' => 82,
        ));
    }
}
