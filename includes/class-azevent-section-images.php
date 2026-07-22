<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_Section_Images
{
    const META_KEY = '_azevent_section_images';

    public static function is_enabled()
    {
        return absint(get_option('azevent_seo_generate_h2_images', 0)) === 1;
    }

    public static function get_limit()
    {
        return min(10, max(1, absint(get_option('azevent_seo_h2_image_limit', 6))));
    }

    public static function create_plan($content, $keyword, $language = 'Vietnamese')
    {
        $sections = self::extract_content_sections($content);
        $eligible = array_values(array_filter($sections, function ($section) {
            return !preg_match('/(?:faq|câu hỏi thường gặp|kết luận|liên hệ|đăng ký|cta|tóm tắt)/ui', $section['title']);
        }));
        $limit = self::get_limit();
        if (!$eligible) {
            return array(
                'enabled' => true,
                'completed' => true,
                'items' => array(),
                'current_index' => 0,
                'message' => 'Không có H2 phù hợp để tạo ảnh minh họa.',
            );
        }

        $brand = AzEvent_SEO_Content::get_brand_profile();
        $candidate_payload = array_map(function ($section) {
            return array(
                'key' => $section['key'],
                'title' => $section['title'],
                'summary' => self::truncate(wp_strip_all_tags($section['html']), 1400),
            );
        }, $eligible);
        $system = 'Bạn là art director và chuyên gia SEO hình ảnh. Chọn các section quan trọng, có giá trị minh họa cao. Trả về duy nhất JSON hợp lệ, không Markdown.';
        $user = "Lập kế hoạch ảnh minh họa cho bài SEO bằng {$language}.\n";
        $user .= 'Từ khóa: ' . sanitize_text_field($keyword) . "\n";
        $user .= 'Thương hiệu: ' . sanitize_text_field($brand['azevent_seo_brand_name']) . "\n";
        $user .= 'Thông tin thương hiệu: ' . sanitize_textarea_field($brand['azevent_seo_brand_info']) . "\n";
        $user .= 'Chọn tối đa ' . $limit . " H2. Không chọn FAQ, kết luận hoặc CTA. Mỗi ảnh phải khác ý tưởng và bám sát section.\n";
        $user .= 'Ảnh ngang 16:9, professional realistic event photography, natural lighting, no text, no logo, no watermark.\n';
        $user .= 'JSON: {"images":[{"key":"section key","prompt":"English image prompt","alt":"Vietnamese SEO alt text"}]}\n';
        $user .= 'Sections: ' . wp_json_encode($candidate_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $provider = sanitize_key(get_option('azevent_seo_text_provider', 'azevent'));
        $default_model = $provider === 'ckey'
            ? AzEvent_CKey_Client::model_reference(get_option('azevent_seo_ckey_model', ''))
            : sanitize_text_field(get_option('aprg_cliproxy_model', ''));
        $model = sanitize_text_field(get_option('azevent_seo_content_model', $default_model));
        if ($model === '') {
            $model = $default_model;
        }
        $service = new AzEvent_AI_Service();
        $result = $service->call_anthropic($user, $system, $model, 4096);
        $metrics = $service->get_last_text_metrics();
        $decoded = is_wp_error($result) ? null : self::decode_json($result);
        $requested = is_array($decoded) && isset($decoded['images']) && is_array($decoded['images'])
            ? $decoded['images']
            : array();
        $eligible_by_key = array();
        foreach ($eligible as $section) {
            $eligible_by_key[$section['key']] = $section;
        }
        $items = array();
        foreach ($requested as $image) {
            $key = sanitize_key($image['key'] ?? '');
            if (!isset($eligible_by_key[$key]) || count($items) >= $limit) {
                continue;
            }
            $section = $eligible_by_key[$key];
            $items[] = self::build_item(
                $section,
                sanitize_textarea_field($image['prompt'] ?? ''),
                sanitize_text_field($image['alt'] ?? '')
            );
            unset($eligible_by_key[$key]);
        }
        if (!$items) {
            foreach (array_slice($eligible, 0, $limit) as $section) {
                $items[] = self::build_item($section, '', 'Ảnh minh họa ' . $section['title']);
            }
        }

        return array(
            'enabled' => true,
            'completed' => false,
            'items' => $items,
            'current_index' => 0,
            'planned_at' => time(),
            'planning_error' => is_wp_error($result) ? $result->get_error_message() : '',
            'planning_metrics' => is_array($metrics) ? $metrics : array(),
        );
    }

    public static function process_next($post_id, $content, array $state)
    {
        $started_at = microtime(true);
        $items = array_values($state['items'] ?? array());
        $index = max(0, absint($state['current_index'] ?? 0));
        if (!isset($items[$index])) {
            $state['completed'] = true;
            return array('content' => $content, 'state' => $state, 'done' => true);
        }

        $item = $items[$index];
        $image_result = self::generate_with_retry($item['prompt']);
        $item['attempts'] = absint($image_result['attempts'] ?? 1);
        if (is_wp_error($image_result['result'])) {
            $item['status'] = 'skipped';
            $item['error'] = $image_result['result']->get_error_message();
        } else {
            $item['model'] = sanitize_text_field($image_result['result']['model'] ?? get_option('aprg_seo_default_cliproxy_image_model', ''));
            $item['provider'] = sanitize_text_field($image_result['result']['provider'] ?? AzEvent_API_Client::get_provider_label());
            $attachment_id = self::upload_image($image_result['result'], $post_id, $item['title'], $item['alt']);
            if (is_wp_error($attachment_id)) {
                $item['status'] = 'skipped';
                $item['error'] = $attachment_id->get_error_message();
            } else {
                $image = self::attachment_data($attachment_id, $item['alt']);
                $updated_content = self::insert_or_replace($content, $item, $image);
                if ($updated_content === $content) {
                    $item['status'] = 'skipped';
                    $item['error'] = 'Không tìm thấy vị trí H2 tương ứng trong nội dung.';
                } else {
                    $content = $updated_content;
                    $item['status'] = 'created';
                    $item['attachment'] = $image;
                    $item['error'] = '';
                }
            }
        }
        $item['completed_at'] = time();
        $item['duration_seconds'] = round(max(0, microtime(true) - $started_at), 3);
        $items[$index] = $item;
        $state['items'] = $items;
        $state['current_index'] = $index + 1;
        $state['completed'] = $state['current_index'] >= count($items);

        return array(
            'content' => $content,
            'state' => $state,
            'done' => $state['completed'],
            'item' => $item,
        );
    }

    public static function save_state($post_id, array $state)
    {
        update_post_meta(absint($post_id), self::META_KEY, $state);
    }

    public static function regenerate($post_id, $section_key)
    {
        $post = get_post($post_id);
        $state = get_post_meta($post_id, self::META_KEY, true);
        if (!$post || !is_array($state)) {
            return new WP_Error('azevent_section_image_missing_state', 'Không tìm thấy dữ liệu ảnh H2 của bài viết.');
        }
        foreach ((array) ($state['items'] ?? array()) as $index => $item) {
            if (($item['key'] ?? '') !== $section_key) {
                continue;
            }
            $generated = self::generate_with_retry($item['prompt']);
            if (is_wp_error($generated['result'])) {
                return $generated['result'];
            }
            $attachment_id = self::upload_image($generated['result'], $post_id, $item['title'], $item['alt']);
            if (is_wp_error($attachment_id)) {
                return $attachment_id;
            }
            $image = self::attachment_data($attachment_id, $item['alt']);
            $content = self::insert_or_replace($post->post_content, $item, $image);
            if ($content === $post->post_content) {
                return new WP_Error('azevent_section_image_replace_failed', 'Không tìm thấy ảnh H2 cần thay trong bài viết.');
            }
            $updated = wp_update_post(array('ID' => $post_id, 'post_content' => $content), true);
            if (is_wp_error($updated)) {
                return $updated;
            }
            $item['status'] = 'created';
            $item['model'] = sanitize_text_field($generated['result']['model'] ?? get_option('aprg_seo_default_cliproxy_image_model', ''));
            $item['provider'] = sanitize_text_field($generated['result']['provider'] ?? AzEvent_API_Client::get_provider_label());
            $item['attachment'] = $image;
            $item['attempts'] = absint($generated['attempts'] ?? 1);
            $item['error'] = '';
            $item['completed_at'] = time();
            $state['items'][$index] = $item;
            self::save_state($post_id, $state);
            return array('item' => $item, 'state' => $state);
        }
        return new WP_Error('azevent_section_image_not_found', 'Không tìm thấy H2 cần tạo lại ảnh.');
    }

    private static function build_item(array $section, $prompt, $alt)
    {
        if ($prompt === '') {
            $prompt = 'Professional realistic event photography illustrating "' . $section['title'] . '", relevant Vietnamese corporate event context, natural lighting, cinematic composition, landscape 16:9, high resolution, no text, no logo, no watermark.';
        }
        return array(
            'key' => $section['key'],
            'title' => $section['title'],
            'prompt' => $prompt,
            'alt' => $alt !== '' ? $alt : 'Ảnh minh họa ' . $section['title'],
            'status' => 'pending',
            'attempts' => 0,
            'error' => '',
        );
    }

    private static function extract_content_sections($content)
    {
        preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>(.*?)(?=<h2\b|$)/isu', (string) $content, $matches, PREG_SET_ORDER);
        $sections = array();
        foreach ($matches as $match) {
            $title = sanitize_text_field(wp_strip_all_tags($match[1]));
            if ($title === '') {
                continue;
            }
            $sections[] = array(
                'key' => 'h2-' . substr(md5(self::normalize($title)), 0, 12),
                'title' => $title,
                'html' => $match[2],
            );
        }
        return $sections;
    }

    private static function insert_or_replace($content, array $item, array $image)
    {
        $figure = '<figure class="azevent-h2-image" data-azevent-h2-key="' . esc_attr($item['key']) . '">'
            . '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($item['alt']) . '" loading="lazy" decoding="async" data-attachment-id="' . absint($image['id']) . '">'
            . '</figure>';
        $marker_pattern = '/<figure\b[^>]*data-azevent-h2-key=["\']' . preg_quote($item['key'], '/') . '["\'][^>]*>.*?<\/figure>/isu';
        if (preg_match($marker_pattern, $content)) {
            return preg_replace($marker_pattern, $figure, $content, 1);
        }
        $changed = false;
        $result = preg_replace_callback('/(<h2\b[^>]*>)(.*?)(<\/h2>)(.*?)(?=<h2\b|$)/isu', function ($match) use ($item, $figure, &$changed) {
            if ($changed || self::normalize(wp_strip_all_tags($match[2])) !== self::normalize($item['title'])) {
                return $match[0];
            }
            $body = $match[4];
            if (preg_match('/<p\b[^>]*>.*?<\/p>/isu', $body, $paragraph, PREG_OFFSET_CAPTURE)) {
                $position = $paragraph[0][1] + strlen($paragraph[0][0]);
                $body = substr($body, 0, $position) . "\n" . $figure . substr($body, $position);
            } else {
                $body = "\n" . $figure . $body;
            }
            $changed = true;
            return $match[1] . $match[2] . $match[3] . $body;
        }, (string) $content);
        return $changed ? $result : $content;
    }

    private static function generate_with_retry($prompt)
    {
        $last_error = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            if ($attempt > 1) {
                sleep($attempt === 2 ? 5 : 15);
            }
            $result = (new AzEvent_AI_Service())->generate_image($prompt, '', '16:9');
            if (!is_wp_error($result)) {
                return array('result' => $result, 'attempts' => $attempt);
            }
            $last_error = $result;
        }
        return array('result' => $last_error ?: new WP_Error('azevent_section_image_failed', 'Không thể tạo ảnh H2.'), 'attempts' => 3);
    }

    private static function upload_image($image_result, $post_id, $title, $alt)
    {
        if (empty($image_result['base64'])) {
            return new WP_Error('azevent_section_image_empty', 'API không trả về dữ liệu ảnh.');
        }
        $image_data = base64_decode(preg_replace('/\s+/', '', $image_result['base64']), true);
        $detected = $image_data && function_exists('getimagesizefromstring') ? @getimagesizefromstring($image_data) : false;
        if (!$detected || empty($detected['mime'])) {
            return new WP_Error('azevent_section_image_invalid', 'API trả về dữ liệu không phải ảnh.');
        }
        $mime = sanitize_mime_type($detected['mime']);
        $extensions = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
        $upload = wp_upload_bits(sanitize_title($title) . '-h2-' . time() . '.' . ($extensions[$mime] ?? 'png'), null, $image_data);
        if (!empty($upload['error'])) {
            return new WP_Error('azevent_section_image_upload', $upload['error']);
        }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $mime,
            'post_title' => $title,
            'post_status' => 'inherit',
        ), $upload['file'], $post_id, true);
        if (is_wp_error($attachment_id)) {
            wp_delete_file($upload['file']);
            return $attachment_id;
        }
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
        return $attachment_id;
    }

    private static function attachment_data($attachment_id, $alt)
    {
        return array(
            'id' => absint($attachment_id),
            'url' => esc_url_raw(wp_get_attachment_image_url($attachment_id, 'large')),
            'full_url' => esc_url_raw(wp_get_attachment_image_url($attachment_id, 'full')),
            'alt' => sanitize_text_field($alt),
        );
    }

    private static function decode_json($content)
    {
        $content = trim(preg_replace('/^\s*(?:```|~~~)(?:json)?\s*|\s*(?:```|~~~)\s*$/iu', '', (string) $content));
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        return $start !== false && $end > $start ? json_decode(substr($content, $start, $end - $start + 1), true) : null;
    }

    private static function normalize($value)
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower((string) $value) : strtolower((string) $value);
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value));
    }

    private static function truncate($value, $length)
    {
        return function_exists('mb_substr') ? mb_substr((string) $value, 0, $length) : substr((string) $value, 0, $length);
    }
}
