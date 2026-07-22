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
        $keyword = sanitize_text_field($keyword);
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
        $user .= 'Từ khóa: ' . $keyword . "\n";
        $user .= 'Thương hiệu: ' . sanitize_text_field($brand['azevent_seo_brand_name']) . "\n";
        $user .= 'Thông tin thương hiệu: ' . sanitize_textarea_field($brand['azevent_seo_brand_info']) . "\n";
        $user .= 'Chọn tối đa ' . $limit . " H2. Không chọn FAQ, kết luận hoặc CTA. Mỗi ảnh phải khác ý tưởng và bám sát section.\n";
        $user .= 'Ảnh ngang 16:9, professional realistic event photography, natural lighting, no text, no logo, no watermark.\n';
        $user .= "ALT phải mô tả cảnh nhìn thấy một cách tự nhiên trong 6-18 từ, duy nhất cho từng ảnh; không mở đầu bằng \"ảnh/hình minh họa\", không nhồi từ khóa và không nhắc thương hiệu nếu thương hiệu không xuất hiện trong ảnh.\n";
        $user .= 'JSON: {"images":[{"key":"section key","prompt":"English image prompt","alt":"Vietnamese descriptive alt text"}]}\n';
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
        $used_alts = array();
        foreach ($requested as $image) {
            $key = sanitize_key($image['key'] ?? '');
            if (!isset($eligible_by_key[$key]) || count($items) >= $limit) {
                continue;
            }
            $section = $eligible_by_key[$key];
            $alt = self::normalize_alt($image['alt'] ?? '', $section['title'], $keyword);
            $alt_key = self::normalize($alt);
            if (isset($used_alts[$alt_key])) {
                $alt = self::normalize_alt($alt . ' - ' . $section['title'], $section['title'], $keyword);
                $alt_key = self::normalize($alt);
            }
            $used_alts[$alt_key] = true;
            $items[] = self::build_item(
                $section,
                sanitize_textarea_field($image['prompt'] ?? ''),
                $alt,
                $keyword
            );
            unset($eligible_by_key[$key]);
        }
        if (!$items) {
            foreach (array_slice($eligible, 0, $limit) as $section) {
                $items[] = self::build_item($section, '', '', $keyword);
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
        $item['keyword'] = sanitize_text_field($item['keyword'] ?? get_the_title($post_id));
        $item['alt'] = self::normalize_alt($item['alt'] ?? '', $item['title'] ?? '', $item['keyword']);
        $image_result = self::generate_with_retry($item['prompt']);
        $item['attempts'] = absint($image_result['attempts'] ?? 1);
        if (is_wp_error($image_result['result'])) {
            $item['status'] = 'skipped';
            $item['error'] = $image_result['result']->get_error_message();
        } else {
            $item['model'] = sanitize_text_field($image_result['result']['model'] ?? get_option('aprg_seo_default_cliproxy_image_model', ''));
            $item['provider'] = sanitize_text_field($image_result['result']['provider'] ?? AzEvent_API_Client::get_provider_label());
            $attachment_id = self::upload_image(
                $image_result['result'],
                $post_id,
                $item['title'],
                $item['alt'],
                $item['keyword'] ?? get_the_title($post_id)
            );
            if (is_wp_error($attachment_id)) {
                $item['status'] = 'skipped';
                $item['error'] = $attachment_id->get_error_message();
            } else {
                $image = self::attachment_data($attachment_id, $item['alt']);
                $updated_content = self::insert_or_replace($content, $item, $image);
                if ($updated_content === $content) {
                    wp_delete_attachment($attachment_id, true);
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
            $item['keyword'] = sanitize_text_field($item['keyword'] ?? get_the_title($post_id));
            $item['alt'] = self::normalize_alt($item['alt'] ?? '', $item['title'] ?? '', $item['keyword']);
            $generated = self::generate_with_retry($item['prompt']);
            if (is_wp_error($generated['result'])) {
                return $generated['result'];
            }
            $attachment_id = self::upload_image(
                $generated['result'],
                $post_id,
                $item['title'],
                $item['alt'],
                $item['keyword'] ?? get_the_title($post_id)
            );
            if (is_wp_error($attachment_id)) {
                return $attachment_id;
            }
            $image = self::attachment_data($attachment_id, $item['alt']);
            $content = self::insert_or_replace($post->post_content, $item, $image);
            if ($content === $post->post_content) {
                wp_delete_attachment($attachment_id, true);
                return new WP_Error('azevent_section_image_replace_failed', 'Không tìm thấy ảnh H2 cần thay trong bài viết.');
            }
            $updated = wp_update_post(array('ID' => $post_id, 'post_content' => $content), true);
            if (is_wp_error($updated)) {
                wp_delete_attachment($attachment_id, true);
                return $updated;
            }
            $previous_attachment_id = absint($item['attachment']['id'] ?? 0);
            if ($previous_attachment_id && $previous_attachment_id !== $attachment_id) {
                wp_delete_attachment($previous_attachment_id, true);
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

    private static function build_item(array $section, $prompt, $alt, $keyword = '')
    {
        if ($prompt === '') {
            $prompt = 'Professional realistic event photography illustrating "' . $section['title'] . '", relevant Vietnamese corporate event context, natural lighting, cinematic composition, landscape 16:9, high resolution, no text, no logo, no watermark.';
        }
        return array(
            'key' => $section['key'],
            'title' => $section['title'],
            'prompt' => $prompt,
            'alt' => self::normalize_alt($alt, $section['title'], $keyword),
            'keyword' => sanitize_text_field($keyword),
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
        $image_html = self::responsive_image_html($image, $item['alt']);
        $figure = '<figure class="wp-block-image size-large azevent-h2-image" data-azevent-h2-key="' . esc_attr($item['key']) . '">'
            . $image_html
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

    private static function upload_image($image_result, $post_id, $title, $alt, $keyword = '')
    {
        return AzEvent_Image_SEO::upload_base64($image_result, $post_id, array(
            'role' => 'h2',
            'title' => $title,
            'alt' => $alt,
            'keyword' => $keyword,
            'max_width' => 1600,
            'max_height' => 900,
            'quality' => 82,
        ));
    }

    private static function attachment_data($attachment_id, $alt)
    {
        $data = AzEvent_Image_SEO::attachment_data($attachment_id);
        if (empty($data['alt'])) {
            $data['alt'] = sanitize_text_field($alt);
        }
        return $data;
    }

    private static function responsive_image_html(array $image, $alt)
    {
        $attachment_id = absint($image['id'] ?? 0);
        $attributes = array(
            'class' => 'attachment-large size-large wp-image-' . $attachment_id . ' azevent-h2-image__img',
            'alt' => sanitize_text_field($alt),
            'loading' => 'lazy',
            'decoding' => 'async',
            'data-attachment-id' => $attachment_id,
        );
        $html = $attachment_id ? wp_get_attachment_image($attachment_id, 'large', false, $attributes) : '';
        if ($html !== '') {
            return $html;
        }
        $width = absint($image['width'] ?? 0);
        $height = absint($image['height'] ?? 0);
        return '<img src="' . esc_url($image['url'] ?? '') . '" alt="' . esc_attr($attributes['alt']) . '"'
            . ($width ? ' width="' . $width . '"' : '')
            . ($height ? ' height="' . $height . '"' : '')
            . ' loading="lazy" decoding="async" class="azevent-h2-image__img" data-attachment-id="' . $attachment_id . '">';
    }

    private static function normalize_alt($alt, $section_title, $keyword = '')
    {
        return AzEvent_Image_SEO::normalize_alt($alt, $section_title, $keyword);
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
