<?php
/**
 * Editor Integration for AzEvent SEO Content Creator.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_Editor_Integration
{

    public static function get_default_prompts()
    {
        return require AZEVENT_SEO_PATH . 'includes/class-azevent-prompt-templates.php';
    }

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_seo_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_azevent_generate_content', array($this, 'ajax_generate_content'));
    }

    /**
     * Add Meta Box to the side of the post editor.
     */
    public function add_seo_meta_box()
    {
        add_meta_box(
            'azevent_seo_meta_box',
            __('AzEvent AI SEO Content', 'azevent-seo-content'),
            array($this, 'render_meta_box'),
            'post',
            'side',
            'high'
        );
    }

    /**
     * Render Meta Box content.
     */
    public function render_meta_box($post)
    {
        $default_mode = $post->ID > 0 ? 'rewrite' : 'create';
        $default_language = get_option('azevent_seo_default_language', 'Vietnamese');
        ?>
        <div id="azevent-seo-box">
            <p>
                <label for="azevent-mode"><strong><?php _e('Chế độ:', 'azevent-seo-content'); ?></strong></label><br>
                <select id="azevent-mode" style="width:100%;">
                    <option value="rewrite" <?php selected($default_mode, 'rewrite'); ?>><?php _e('Viết lại bài hiện tại', 'azevent-seo-content'); ?></option>
                    <option value="create" <?php selected($default_mode, 'create'); ?>><?php _e('Tạo nội dung mới', 'azevent-seo-content'); ?></option>
                </select>
            </p>
            <p>
                <label for="azevent_keyword"><strong>
                        <?php _e('Từ khóa chính:', 'azevent-seo-content'); ?>
                    </strong></label><br>
                <textarea id="azevent-keywords" name="azevent_keywords" rows="4" style="width:100%; resize:vertical;"
                    placeholder="<?php _e("Mỗi dòng một từ khóa. Ví dụ:\nTổ chức hội nghị doanh nghiệp\nDịch vụ gala dinner", 'azevent-seo-content'); ?>"><?php echo esc_textarea($default_mode === 'rewrite' ? $post->post_title : ''); ?></textarea>
                <span style="display:block; margin-top:4px; color:#777; font-size:11px;">
                    <?php _e('Tạo mới: mỗi dòng sẽ tạo một Draft riêng. Viết lại: chỉ dùng một từ khóa.', 'azevent-seo-content'); ?>
                </span>
            </p>
            <p>
                <label for="azevent_language"><strong>
                        <?php _e('Ngôn ngữ:', 'azevent-seo-content'); ?>
                    </strong></label><br>
                <select id="azevent_language" style="width:100%;">
                    <option value="Vietnamese" <?php selected($default_language, 'Vietnamese'); ?>>Tiếng Việt</option>
                    <option value="English" <?php selected($default_language, 'English'); ?>>English</option>
                </select>
            </p>
            <p>
                <label>
                    <input type="checkbox" id="azevent-regenerate-image" value="1">
                    <?php _e('Tạo lại ảnh đại diện', 'azevent-seo-content'); ?>
                </label>
            </p>
            <button type="button" id="azevent-generate-btn" class="button button-primary button-large" style="width:100%;">
                <?php _e('Bắt đầu tạo bài viết', 'azevent-seo-content'); ?>
            </button>

            <div id="azevent-progress" style="margin-top:15px; display:none;">
                <div class="azevent-spinner" style="display:inline-block; vertical-align:middle; margin-right:5px;"></div>
                <span id="azevent-status-text">
                    <?php _e('Đang chuẩn bị...', 'azevent-seo-content'); ?>
                </span>
            </div>

            <div id="azevent-log"
                style="margin-top:10px; font-size:11px; max-height:100px; overflow-y:auto; border-top:1px solid #ddd; padding-top:5px; display:none;">
            </div>
        </div>

        <style>
            .azevent-spinner {
                border: 3px solid #f3f3f3;
                border-top: 3px solid #3498db;
                border-radius: 50%;
                width: 15px;
                height: 15px;
                animation: azevent-spin 1s linear infinite;
            }

            @keyframes azevent-spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }
        </style>
        <?php
    }

    /**
     * Enqueue JS/CSS.
     */
    public function enqueue_assets($hook)
    {
        if ('post-new.php' !== $hook && 'post.php' !== $hook) {
            return;
        }

        wp_enqueue_script('azevent-seo-js', AZEVENT_SEO_URL . 'admin/js/editor.js', array('jquery'), AZEVENT_SEO_VERSION, true);
        wp_localize_script('azevent-seo-js', 'azevent_seo', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('azevent_seo_nonce'),
            'post_id' => get_the_ID(),
        ));
    }

    /**
     * AJAX handler for content generation.
     */
    public function ajax_generate_content()
    {
        check_ajax_referer('azevent_seo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Quyền truy cập bị từ chối.'));
        }

        $keyword = sanitize_text_field(wp_unslash($_POST['keyword'] ?? ''));
        $language = sanitize_text_field(wp_unslash($_POST['language'] ?? 'Vietnamese'));
        $post_id = absint($_POST['post_id'] ?? 0);
        $step = sanitize_text_field(wp_unslash($_POST['step'] ?? 'start'));
        $mode = sanitize_key(wp_unslash($_POST['mode'] ?? 'create'));
        $regenerate_image = sanitize_text_field(wp_unslash($_POST['regenerate_image'] ?? '0')) === '1';
        $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
        $context = is_array($context) ? $context : array();

        if (!in_array($mode, array('create', 'rewrite'), true)) {
            $mode = 'create';
        }

        if (empty($keyword)) {
            wp_send_json_error(array('message' => 'Vui lòng nhập từ khóa.'));
        }

        if ($mode === 'create' && $post_id <= 0 && $step === 'start') {
            $post_id = wp_insert_post(array(
                'post_type' => 'post',
                'post_status' => 'draft',
                'post_title' => $keyword,
                'post_author' => get_current_user_id(),
            ), true);
            if (is_wp_error($post_id)) {
                wp_send_json_error(array('message' => 'Không thể tạo Draft cho từ khóa: ' . $post_id->get_error_message()));
            }
        }

        if ($post_id > 0 && !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Bạn không có quyền chỉnh sửa bài viết này.'));
        }

        if ($post_id <= 0) {
            wp_send_json_error(array('message' => 'Không xác định được bài viết đang xử lý.'));
        }

        $context['post_id'] = $post_id;

        if ($mode === 'rewrite') {
            if ($post_id <= 0) {
                wp_send_json_error(array('message' => 'Cần lưu bài viết trước khi viết lại.'));
            }

            $existing_post = get_post($post_id);
            if (!$existing_post || $existing_post->post_type !== 'post') {
                wp_send_json_error(array('message' => 'Không tìm thấy bài viết cần viết lại.'));
            }

            if ($step === 'start' || empty($context['existing_post'])) {
                $existing_content = (string) $existing_post->post_content;
                if (function_exists('mb_substr')) {
                    $existing_content = mb_substr($existing_content, 0, 30000);
                } else {
                    $existing_content = substr($existing_content, 0, 30000);
                }

                $context['existing_post'] = array(
                    'title' => (string) $existing_post->post_title,
                    'content' => $existing_content,
                    'excerpt' => (string) $existing_post->post_excerpt,
                    'slug' => (string) $existing_post->post_name,
                    'has_thumbnail' => has_post_thumbnail($post_id),
                );
            }
        }

        $ai = new AzEvent_AI_Service();
        $brand_name = get_option('azevent_seo_brand_name');
        $brand_info = get_option('azevent_seo_brand_info');
        $brand_solution = get_option('azevent_seo_brand_solution');

        // Helper to replace placeholders
        $replace_placeholders = function ($text, $ctx) use ($keyword, $language, $brand_name, $brand_info, $brand_solution) {
            $placeholders = array(
                '{keyword}' => $keyword,
                '{secondary_keywords}' => $keyword,
                '{language}' => $language,
                '{outline_focus}' => isset($ctx['outline_focus']) ? $ctx['outline_focus'] : '',
                '{brand_name}' => $brand_name,
                '{brand_info}' => $brand_info,
                '{brand_solution}' => $brand_solution,
                '{search_intent}' => isset($ctx['search_intent']) ? $ctx['search_intent'] : '',
                '{outline}' => isset($ctx['outline']) ? $ctx['outline'] : '',
                '{content}' => isset($ctx['content']) ? $ctx['content'] : '',
                '{existing_title}' => isset($ctx['existing_post']['title']) ? $ctx['existing_post']['title'] : '',
                '{existing_content}' => isset($ctx['existing_post']['content']) ? $ctx['existing_post']['content'] : '',
                '{existing_excerpt}' => isset($ctx['existing_post']['excerpt']) ? $ctx['existing_post']['excerpt'] : '',
                '{existing_slug}' => isset($ctx['existing_post']['slug']) ? $ctx['existing_post']['slug'] : '',
                '{rewrite_goal}' => $mode === 'rewrite' ? 'Viết lại bài hiện tại, giữ thông tin đúng và cải thiện chất lượng SEO.' : 'Tạo nội dung mới từ đầu.',
            );
            return str_replace(array_keys($placeholders), array_values($placeholders), $text);
        };

        $prompt_defaults = self::get_default_prompts();
        $defaults = array(
            'intent_system' => $prompt_defaults['intent']['system'],
            'intent_user' => $prompt_defaults['intent']['user'],
            'outline_system' => $prompt_defaults['outline']['system'],
            'outline_user' => $prompt_defaults['outline']['user'],
            'content_system' => $prompt_defaults['content']['system'],
            'content_user' => $prompt_defaults['content']['user'],
            'seo_system' => $prompt_defaults['seo']['system'],
            'seo_user' => $prompt_defaults['seo']['user'],
        );
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

        switch ($step) {
            case 'start':
            case 'search_intent':
                $sys = $get_prompt('azevent_seo_intent_system', $defaults['intent_system']);
                $user = $get_prompt('azevent_seo_intent_user', $defaults['intent_user']);
                if ($mode === 'rewrite') {
                    $user .= $rewrite_instructions['intent'];
                }
                $result = $ai->call_anthropic($replace_placeholders($user, $context), $replace_placeholders($sys, $context), 'claude-3-5-haiku-20241022');
                if (is_wp_error($result))
                    wp_send_json_error(array('message' => $result->get_error_message()));

                $context['search_intent'] = $result;
                wp_send_json_success(array(
                    'status' => 'processing',
                    'message' => 'Đã hoàn thành phân tích mục đích tìm kiếm. Đang xây dựng dàn ý bài viết...',
                    'next_step' => 'outline',
                    'context' => $context
                ));
                break;

            case 'outline':
                $sys = $get_prompt('azevent_seo_outline_system', $defaults['outline_system']);
                $user = $get_prompt('azevent_seo_outline_user', $defaults['outline_user']);
                if ($mode === 'rewrite') {
                    $user .= $rewrite_instructions['outline'];
                }
                $result = $ai->call_anthropic($replace_placeholders($user, $context), $replace_placeholders($sys, $context), 'claude-3-5-sonnet-20240620');
                if (is_wp_error($result))
                    wp_send_json_error(array('message' => $result->get_error_message()));

                $context['outline'] = $result;
                wp_send_json_success(array(
                    'status' => 'processing',
                    'message' => 'Đã xây dựng dàn ý chi tiết. Đang tiến hành viết nội dung bài viết...',
                    'next_step' => 'content',
                    'context' => $context
                ));
                break;

            case 'content':
                $sys = $get_prompt('azevent_seo_content_system', $defaults['content_system']);
                $user = $get_prompt('azevent_seo_content_user', $defaults['content_user']);
                if ($mode === 'rewrite') {
                    $user .= $rewrite_instructions['content'];
                }
                $result = $ai->call_anthropic($replace_placeholders($user, $context), $replace_placeholders($sys, $context), 'claude-3-5-sonnet-20240620');
                if (is_wp_error($result))
                    wp_send_json_error(array('message' => $result->get_error_message()));

                $context['content'] = $result;
                wp_send_json_success(array(
                    'status' => 'processing',
                    'message' => 'Nội dung bài viết đã hoàn tất. Đang tạo dữ liệu SEO Meta (Title, Slug, Description)...',
                    'next_step' => 'seo',
                    'context' => $context
                ));
                break;

            case 'seo':
                $sys = $get_prompt('azevent_seo_seo_system', $defaults['seo_system']);
                $user = $get_prompt('azevent_seo_seo_user', $defaults['seo_user']);
                if ($mode === 'rewrite') {
                    $user .= $rewrite_instructions['seo'];
                }
                $result = $ai->call_openai($replace_placeholders($user, $context), $replace_placeholders($sys, $context), 'chat/completions', array('response_format' => array('type' => 'json_object')));
                if (is_wp_error($result))
                    wp_send_json_error(array('message' => $result->get_error_message()));

                $seo_data = json_decode($result, true);
                if (!is_array($seo_data) || empty($seo_data['title']) || empty($seo_data['meta']) || empty($seo_data['image_prompt'])) {
                    wp_send_json_error(array('message' => 'AI trả về dữ liệu SEO không đầy đủ.'));
                }
                $context['seo'] = $seo_data;
                $context['mode'] = $mode;
                $context['regenerate_image'] = $regenerate_image;

                $should_generate_image = $mode !== 'rewrite' || $regenerate_image || !has_post_thumbnail($post_id);
                $next_step = $should_generate_image ? 'image' : 'finalize';

                wp_send_json_success(array(
                    'status' => 'processing',
                    'message' => $should_generate_image ? 'Đã tối ưu SEO. Đang xử lý ảnh đại diện...' : 'Đã tối ưu SEO. Đang lưu bản viết lại dưới dạng bản nháp...',
                    'next_step' => $next_step,
                    'context' => $context
                ));
                break;

            case 'finalize':
                $this->complete_generation($post_id, $context, $mode);
                break;

            case 'image':
                $image_prompt = $context['seo']['image_prompt'] . " Cinematic style, professional photography, high resolution, no text, no watermark.";
                $image_result = $ai->generate_image($image_prompt, '', '1:1');
                if (is_wp_error($image_result))
                    wp_send_json_error(array('message' => $image_result->get_error_message()));

                $attachment_id = $this->upload_image_from_result($image_result, $post_id, $context['seo']['title']);
                if (is_wp_error($attachment_id))
                    wp_send_json_error(array('message' => 'Lỗi tải ảnh: ' . $attachment_id->get_error_message()));

                set_post_thumbnail($post_id, $attachment_id);

                $this->complete_generation($post_id, $context, $mode);
                break;

            default:
                wp_send_json_error(array('message' => 'Bước không hợp lệ.'));
                break;
        }
    }

    private function complete_generation($post_id, $context, $mode)
    {
        $updated_post = array(
            'ID' => $post_id,
            'post_title' => $context['seo']['title'],
            'post_content' => $context['content'],
            'post_excerpt' => $context['seo']['meta'],
            'post_status' => 'draft',
        );

        if ($mode !== 'rewrite') {
            $updated_post['post_name'] = $context['seo']['slug'];
        }

        $updated = wp_update_post($updated_post, true);
        if (is_wp_error($updated)) {
            wp_send_json_error(array('message' => 'Không thể lưu bài viết: ' . $updated->get_error_message()));
        }

        wp_send_json_success(array(
            'status' => 'completed',
            'post_id' => $post_id,
            'message' => $mode === 'rewrite'
                ? 'Đã viết lại bài và lưu thành bản nháp. Slug và ảnh đại diện cũ được giữ nguyên nếu bạn không chọn tạo lại ảnh.'
                : 'Tuyệt vời! Bài viết mới đã được tạo thành công dưới dạng bản nháp.',
            'title' => $context['seo']['title'],
            'content' => $context['content'],
        ));
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

        require_once(ABSPATH . 'wp-admin/includes/image.php');
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

    /**
     * Helper to upload image from URL to Media Library.
     */
    private function upload_image_from_url($url, $post_id, $title)
    {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $desc = "AI Generated Image for " . $title;
        $file_array = array();

        // Download file to temp location
        $temp_file = download_url($url);
        if (is_wp_error($temp_file))
            return $temp_file;

        $file_array['name'] = sanitize_title($title) . '.png';
        $file_array['tmp_name'] = $temp_file;

        // Do the real install
        $id = media_handle_sideload($file_array, $post_id, $desc);

        // If error, unlink
        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
        }

        return $id;
    }
}

new AzEvent_Editor_Integration();
