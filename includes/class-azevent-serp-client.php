<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_SERP_Client
{
    const ENDPOINT = 'https://serpapi.com/search.json';

    public static function is_configured()
    {
        return trim((string) get_option('azevent_lab_serpapi_key', '')) !== '';
    }

    public function search($query)
    {
        $query = sanitize_text_field($query);
        if ($query === '') {
            return new WP_Error('azevent_serp_missing_query', 'Thiếu từ khóa để tìm kiếm SERP.');
        }
        $api_key = trim((string) get_option('azevent_lab_serpapi_key', ''));
        if ($api_key === '') {
            return new WP_Error('azevent_serp_not_configured', 'Để trống dữ liệu đối thủ yêu cầu SERP API. Hãy cấu hình SerpApi trong Settings → Workflow Lab Prompts hoặc nhập dữ liệu đối thủ thủ công.');
        }

        $location = sanitize_text_field(get_option('azevent_lab_serp_location', 'Vietnam'));
        $country = strtolower(sanitize_key(get_option('azevent_lab_serp_country', 'vn')));
        $language = strtolower(sanitize_key(get_option('azevent_lab_serp_language', 'vi')));
        $result_count = min(10, max(3, absint(get_option('azevent_lab_serp_result_count', 10))));
        $fetch_pages = min(5, max(0, absint(get_option('azevent_lab_serp_fetch_pages', 3))));
        $cache_key = 'azevent_serp_' . md5(wp_json_encode(array($query, $location, $country, $language, $result_count, $fetch_pages)));
        $cached = get_transient($cache_key);
        if (is_array($cached) && !empty($cached['organic_results'])) {
            $cached['cache_hit'] = true;
            return $cached;
        }

        $url = add_query_arg(array_filter(array(
            'engine' => 'google',
            'q' => $query,
            'api_key' => $api_key,
            'location' => $location,
            'gl' => $country ?: 'vn',
            'hl' => $language ?: 'vi',
            'num' => $result_count,
            'device' => 'desktop',
            'safe' => 'active',
            'output' => 'json',
        )), self::ENDPOINT);
        $response = wp_remote_get($url, array(
            'timeout' => 35,
            'headers' => array('Accept' => 'application/json'),
        ));
        if (is_wp_error($response)) {
            return new WP_Error('azevent_serp_request_failed', 'Không thể kết nối SerpApi: ' . $response->get_error_message());
        }
        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            $message = is_array($data) && !empty($data['error']) ? $data['error'] : 'Phản hồi SERP không hợp lệ.';
            return new WP_Error('azevent_serp_http_error', 'SerpApi HTTP ' . $status . ': ' . sanitize_text_field($message));
        }
        if (!empty($data['error'])) {
            return new WP_Error('azevent_serp_api_error', 'SerpApi: ' . sanitize_text_field($data['error']));
        }

        $site_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        $organic_results = array();
        $seen_urls = array();
        foreach ((array) ($data['organic_results'] ?? array()) as $item) {
            if (!is_array($item) || empty($item['link']) || empty($item['title'])) {
                continue;
            }
            $link = esc_url_raw($item['link']);
            $host = strtolower((string) wp_parse_url($link, PHP_URL_HOST));
            if ($link === '' || $host === '' || $this->same_host($host, $site_host) || isset($seen_urls[$link])) {
                continue;
            }
            $seen_urls[$link] = true;
            $organic_results[] = array(
                'position' => absint($item['position'] ?? count($organic_results) + 1),
                'title' => sanitize_text_field($item['title']),
                'url' => $link,
                'domain' => $host,
                'snippet' => sanitize_textarea_field($item['snippet'] ?? ''),
                'date' => sanitize_text_field($item['date'] ?? ''),
                'page_structure' => null,
            );
            if (count($organic_results) >= $result_count) {
                break;
            }
        }
        if (empty($organic_results)) {
            return new WP_Error('azevent_serp_empty', 'SerpApi không trả về kết quả organic của đối thủ sau khi loại website hiện tại.');
        }

        for ($index = 0; $index < min($fetch_pages, count($organic_results)); $index++) {
            $organic_results[$index]['page_structure'] = $this->fetch_page_structure($organic_results[$index]['url']);
        }

        $related_questions = array();
        foreach ((array) ($data['related_questions'] ?? array()) as $question) {
            if (!is_array($question) || empty($question['question'])) {
                continue;
            }
            $related_questions[] = array(
                'question' => sanitize_text_field($question['question']),
                'snippet' => sanitize_textarea_field($question['snippet'] ?? ''),
                'source' => sanitize_text_field($question['title'] ?? ''),
                'url' => esc_url_raw($question['link'] ?? ''),
            );
            if (count($related_questions) >= 8) {
                break;
            }
        }

        $snapshot = array(
            'provider' => 'SerpApi Google Search',
            'query' => $query,
            'location' => $location,
            'country' => $country ?: 'vn',
            'language' => $language ?: 'vi',
            'fetched_at' => time(),
            'cache_hit' => false,
            'organic_results' => $organic_results,
            'related_questions' => $related_questions,
        );
        set_transient($cache_key, $snapshot, 6 * HOUR_IN_SECONDS);
        return $snapshot;
    }

    private function fetch_page_structure($url)
    {
        if (!function_exists('wp_safe_remote_get') || !wp_http_validate_url($url)) {
            return array('status' => 'skipped', 'reason' => 'URL không hợp lệ hoặc WordPress không hỗ trợ safe request.');
        }
        $response = wp_safe_remote_get($url, array(
            'timeout' => 15,
            'redirection' => 3,
            'limit_response_size' => 600000,
            'user-agent' => 'AzEventSEOResearch/1.0; ' . home_url('/'),
            'headers' => array('Accept' => 'text/html,application/xhtml+xml'),
        ));
        if (is_wp_error($response)) {
            return array('status' => 'error', 'reason' => sanitize_text_field($response->get_error_message()));
        }
        $status = wp_remote_retrieve_response_code($response);
        $content_type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
        if ($status < 200 || $status >= 300 || strpos($content_type, 'text/html') === false) {
            return array('status' => 'skipped', 'reason' => 'HTTP ' . $status . ' hoặc không phải HTML.');
        }
        $html = wp_remote_retrieve_body($response);
        if ($html === '') {
            return array('status' => 'error', 'reason' => 'Trang không trả về HTML.');
        }

        $structure = array('status' => 'success', 'title' => '', 'meta_description' => '', 'headings' => array());
        if (class_exists('DOMDocument')) {
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if ($loaded) {
                $titles = $document->getElementsByTagName('title');
                if ($titles->length > 0) {
                    $structure['title'] = $this->limit_text($titles->item(0)->textContent, 240);
                }
                foreach ($document->getElementsByTagName('meta') as $meta) {
                    if (strtolower((string) $meta->getAttribute('name')) === 'description') {
                        $structure['meta_description'] = $this->limit_text($meta->getAttribute('content'), 320);
                        break;
                    }
                }
                foreach (array('h1', 'h2', 'h3') as $tag) {
                    foreach ($document->getElementsByTagName($tag) as $heading) {
                        $text = $this->limit_text($heading->textContent, 240);
                        if ($text !== '') {
                            $structure['headings'][] = array('level' => strtoupper($tag), 'text' => $text);
                        }
                        if (count($structure['headings']) >= 24) {
                            break 2;
                        }
                    }
                }
            }
        }
        if ($structure['title'] === '' && preg_match('/<title[^>]*>(.*?)<\/title>/isu', $html, $match)) {
            $structure['title'] = $this->limit_text(wp_strip_all_tags($match[1]), 240);
        }
        return $structure;
    }

    private function limit_text($value, $length)
    {
        $value = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $value)));
        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }

    private function same_host($left, $right)
    {
        $left = preg_replace('/^www\./i', '', (string) $left);
        $right = preg_replace('/^www\./i', '', (string) $right);
        return $left !== '' && $right !== '' && $left === $right;
    }
}
