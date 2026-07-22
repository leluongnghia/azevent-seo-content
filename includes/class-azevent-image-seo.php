<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_Image_SEO
{
    const GENERATED_META_KEY = '_azevent_ai_generated_image';
    const ROLE_META_KEY = '_azevent_ai_image_role';

    public static function upload_base64(array $image_result, $post_id, array $arguments = array())
    {
        if (empty($image_result['base64'])) {
            return new WP_Error('azevent_image_empty', 'API không trả về dữ liệu ảnh.');
        }

        $image_data = base64_decode(preg_replace('/\s+/', '', $image_result['base64']), true);
        $detected = $image_data && function_exists('getimagesizefromstring') ? @getimagesizefromstring($image_data) : false;
        if (!$detected || empty($detected['mime'])) {
            return new WP_Error('azevent_image_invalid', 'API trả về dữ liệu không phải ảnh hợp lệ.');
        }

        $extensions = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        );
        $mime = sanitize_mime_type($detected['mime']);
        if (!isset($extensions[$mime])) {
            return new WP_Error('azevent_image_type_unsupported', 'Định dạng ảnh không được hỗ trợ: ' . $mime);
        }

        $role = sanitize_key($arguments['role'] ?? 'featured');
        $title = sanitize_text_field($arguments['title'] ?? 'AzEvent');
        $keyword = sanitize_text_field($arguments['keyword'] ?? '');
        $alt = self::normalize_alt($arguments['alt'] ?? '', $title, $keyword);
        $filename = self::build_filename($keyword, $title, $role, $extensions[$mime]);
        $upload = wp_upload_bits($filename, null, $image_data);
        if (!empty($upload['error'])) {
            return new WP_Error('azevent_image_upload', $upload['error']);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $optimized_file = self::optimize_uploaded_image($upload['file'], $mime, $role, $arguments);
        if (is_array($optimized_file) && !empty($optimized_file['file']) && file_exists($optimized_file['file'])) {
            $upload['file'] = $optimized_file['file'];
            $mime = sanitize_mime_type($optimized_file['mime'] ?? $mime);
        }
        $optimized = function_exists('wp_getimagesize') ? wp_getimagesize($upload['file']) : @getimagesize($upload['file']);
        if (is_array($optimized) && !empty($optimized['mime'])) {
            $mime = sanitize_mime_type($optimized['mime']);
        }

        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $mime,
            'post_title' => self::attachment_title($title, $keyword),
            'post_content' => $alt,
            'post_status' => 'inherit',
        ), $upload['file'], absint($post_id), true);
        if (is_wp_error($attachment_id)) {
            wp_delete_file($upload['file']);
            return $attachment_id;
        }

        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        update_post_meta($attachment_id, self::GENERATED_META_KEY, 1);
        update_post_meta($attachment_id, self::ROLE_META_KEY, $role);
        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        return $attachment_id;
    }

    public static function set_featured_image($post_id, $attachment_id)
    {
        $post_id = absint($post_id);
        $attachment_id = absint($attachment_id);
        $previous_id = absint(get_post_thumbnail_id($post_id));
        if (!$attachment_id || !set_post_thumbnail($post_id, $attachment_id)) {
            if ($attachment_id && absint(get_post_meta($attachment_id, self::GENERATED_META_KEY, true)) === 1) {
                wp_delete_attachment($attachment_id, true);
            }
            return new WP_Error('azevent_featured_image_failed', 'Không thể gắn ảnh đại diện vào bài viết.');
        }
        if (
            $previous_id
            && $previous_id !== $attachment_id
            && absint(get_post_meta($previous_id, self::GENERATED_META_KEY, true)) === 1
            && get_post_meta($previous_id, self::ROLE_META_KEY, true) === 'featured'
        ) {
            wp_delete_attachment($previous_id, true);
        }
        return true;
    }

    public static function attachment_data($attachment_id)
    {
        $attachment_id = absint($attachment_id);
        $source = wp_get_attachment_image_src($attachment_id, 'large');
        $file = get_attached_file($attachment_id);
        return array(
            'id' => $attachment_id,
            'url' => esc_url_raw(is_array($source) ? $source[0] : wp_get_attachment_image_url($attachment_id, 'large')),
            'full_url' => esc_url_raw(wp_get_attachment_image_url($attachment_id, 'full')),
            'alt' => sanitize_text_field(get_post_meta($attachment_id, '_wp_attachment_image_alt', true)),
            'width' => absint(is_array($source) ? $source[1] : 0),
            'height' => absint(is_array($source) ? $source[2] : 0),
            'mime' => sanitize_mime_type(get_post_mime_type($attachment_id)),
            'filename' => $file ? sanitize_file_name(wp_basename($file)) : '',
            'filesize' => $file && file_exists($file) ? absint(filesize($file)) : 0,
        );
    }

    public static function normalize_alt($alt, $title, $keyword = '')
    {
        $alt = sanitize_text_field(wp_strip_all_tags(html_entity_decode((string) $alt, ENT_QUOTES, 'UTF-8')));
        $alt = trim(preg_replace('/\s+/u', ' ', $alt), " \t\n\r\0\x0B\"'“”");
        $alt = preg_replace('/^(?:ảnh|hình ảnh|hình)\s+(?:minh họa\s+)?(?:cho|về)?\s*/iu', '', $alt);
        if ($alt === '') {
            $alt = sanitize_text_field($title);
        }
        if ($alt === '') {
            $alt = sanitize_text_field($keyword);
        }
        return self::truncate_at_word($alt, 125);
    }

    private static function optimize_uploaded_image($file, $mime, $role, array $arguments)
    {
        if ($mime === 'image/gif') {
            return array('file' => $file, 'mime' => $mime);
        }
        $editor = wp_get_image_editor($file);
        if (is_wp_error($editor)) {
            return array('file' => $file, 'mime' => $mime);
        }
        $default_width = $role === 'h2' ? 1600 : 1600;
        $default_height = $role === 'h2' ? 900 : 1600;
        $max_width = max(800, absint($arguments['max_width'] ?? apply_filters('azevent_' . $role . '_image_max_width', $default_width)));
        $max_height = max(450, absint($arguments['max_height'] ?? apply_filters('azevent_' . $role . '_image_max_height', $default_height)));
        $size = $editor->get_size();
        if (is_array($size) && (absint($size['width'] ?? 0) > $max_width || absint($size['height'] ?? 0) > $max_height)) {
            $editor->resize($max_width, $max_height, false);
        }
        $quality = min(92, max(65, absint($arguments['quality'] ?? apply_filters('azevent_' . $role . '_image_quality', 82))));
        $editor->set_quality($quality);
        $convert_to_webp = !isset($arguments['convert_webp']) || !empty($arguments['convert_webp']);
        $convert_to_webp = (bool) apply_filters('azevent_image_convert_webp', $convert_to_webp, $role, $mime);
        if (
            $convert_to_webp
            && in_array($mime, array('image/jpeg', 'image/png'), true)
            && function_exists('wp_image_editor_supports')
            && wp_image_editor_supports(array('mime_type' => 'image/webp'))
        ) {
            $webp_file = preg_replace('/\.[^.]+$/', '.webp', $file);
            $saved = $editor->save($webp_file, 'image/webp');
            if (!is_wp_error($saved) && !empty($saved['path']) && file_exists($saved['path'])) {
                if ($saved['path'] !== $file) {
                    wp_delete_file($file);
                }
                return array('file' => $saved['path'], 'mime' => 'image/webp');
            }
        }
        $saved = $editor->save($file);
        return array(
            'file' => !is_wp_error($saved) && !empty($saved['path']) ? $saved['path'] : $file,
            'mime' => $mime,
        );
    }

    private static function build_filename($keyword, $title, $role, $extension)
    {
        $normalized_title = self::normalize($title);
        $normalized_keyword = self::normalize($keyword);
        $source = $title;
        if ($keyword !== '' && ($normalized_keyword === '' || strpos($normalized_title, $normalized_keyword) === false)) {
            $source = $keyword . ' ' . $title;
        }
        $slug = sanitize_title($source);
        if ($slug === '') {
            $slug = 'azevent-image';
        }
        if (strlen($slug) > 120) {
            $slug = rtrim(substr($slug, 0, 120), '-');
        }
        return sanitize_file_name($slug . '-' . ($role === 'h2' ? 'h2' : 'featured') . '.' . sanitize_key($extension));
    }

    private static function attachment_title($title, $keyword)
    {
        if ($keyword === '' || strpos(self::normalize($title), self::normalize($keyword)) !== false) {
            return $title;
        }
        return $title . ' – ' . $keyword;
    }

    private static function normalize($value)
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower((string) $value) : strtolower((string) $value);
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value));
    }

    private static function truncate_at_word($value, $length)
    {
        $value = trim((string) $value);
        $current_length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($current_length <= $length) {
            return $value;
        }
        $short = function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
        $space = function_exists('mb_strrpos') ? mb_strrpos($short, ' ') : strrpos($short, ' ');
        if ($space !== false && $space > (int) ($length * 0.6)) {
            $short = function_exists('mb_substr') ? mb_substr($short, 0, $space) : substr($short, 0, $space);
        }
        return rtrim($short, " ,.;:-");
    }
}
