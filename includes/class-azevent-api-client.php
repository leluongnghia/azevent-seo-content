<?php
/**
 * AzEvent API client.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_API_Client
{
    const DEFAULT_BASE_URL = 'https://cliapi.azevent.vn';
    const REMOTE_BASE_URL = 'https://api.azevent.vn';
    const LEGACY_LOCAL_BASE_URL = 'http://192.168.1.5:8317';

    private $api_key;
    private $base_url;
    private $model;
    private $last_text_metrics = array();

    public function __construct($api_key = '', $base_url = '', $model = '')
    {
        $this->base_url = self::get_base_url($base_url);
        $this->api_key = $api_key !== '' ? $api_key : self::get_api_key($this->base_url);
        $this->model = $model !== '' ? $model : get_option('aprg_cliproxy_model', 'claude-sonnet-4-6');
    }

    public static function get_base_url($base_url = '')
    {
        if ($base_url === '') {
            $base_url = get_option('aprg_cliproxy_base_url', self::DEFAULT_BASE_URL);
        }

        $base_url = rtrim((string) $base_url, '/');
        if (substr($base_url, -3) === '/v1') {
            $base_url = substr($base_url, 0, -3);
        }

        $allowed_urls = array(self::DEFAULT_BASE_URL, self::REMOTE_BASE_URL, self::LEGACY_LOCAL_BASE_URL);
        if (!in_array($base_url, $allowed_urls, true)) {
            $base_url = self::DEFAULT_BASE_URL;
        }

        return rtrim($base_url, '/');
    }

    public static function get_api_base_url($base_url = '')
    {
        return self::get_base_url($base_url) . '/v1';
    }

    public static function get_provider_label($base_url = '')
    {
        $base_url = self::get_base_url($base_url);
        if ($base_url === self::DEFAULT_BASE_URL) {
            return 'AzEvent CLI API';
        }
        if ($base_url === self::REMOTE_BASE_URL) {
            return 'AzEvent API';
        }
        return 'AzEvent Local API';
    }

    public static function is_configured()
    {
        return self::get_api_key() !== '';
    }

    public static function get_api_key($base_url = '')
    {
        $base_url = self::get_base_url($base_url);
        $legacy_key = trim((string) get_option('aprg_cliproxy_api_key', ''));

        if ($base_url === self::DEFAULT_BASE_URL) {
            return trim((string) get_option('azevent_cliapi_api_key', $legacy_key));
        }

        if ($base_url === self::REMOTE_BASE_URL) {
            return trim((string) get_option('azevent_remote_api_key', $legacy_key));
        }

        return $legacy_key;
    }

    public function get_last_text_metrics()
    {
        return $this->last_text_metrics;
    }

    public function generate_text($prompt, $system_prompt = '', array $options = array())
    {
        $started_at = microtime(true);
        $this->last_text_metrics = array();
        if (trim((string) $prompt) === '') {
            return new WP_Error('azevent_empty_prompt', 'AzEvent API prompt đang trống.');
        }

        if ($this->api_key === '') {
            return new WP_Error('azevent_missing_api_key', 'Thiếu AzEvent API Key (CLIProxyAPI).');
        }

        $model = !empty($options['model']) ? sanitize_text_field($options['model']) : $this->model;
        $max_tokens = isset($options['max_tokens']) ? absint($options['max_tokens']) : 8192;
        $is_gpt5 = strpos($model, 'gpt-5') === 0;
        $is_reasoning = strpos($model, 'o1') === 0 || strpos($model, 'o3') === 0;

        $messages = array();
        if (trim((string) $system_prompt) !== '') {
            $messages[] = array(
                'role' => 'system',
                'content' => (string) $system_prompt,
            );
        }
        $messages[] = array(
            'role' => 'user',
            'content' => (string) $prompt,
        );

        $body = array(
            'model' => $model,
            'messages' => $messages,
        );

        if ($is_gpt5 || $is_reasoning) {
            $body['max_completion_tokens'] = max($max_tokens, 1024);
        } else {
            $body['max_tokens'] = $max_tokens;
            $body['temperature'] = isset($options['temperature']) ? (float) $options['temperature'] : 0.7;
        }

        if (!empty($options['response_format']) && is_array($options['response_format'])) {
            $body['response_format'] = $options['response_format'];
        }

        $use_stream = array_key_exists('stream', $options)
            ? (bool) $options['stream']
            : in_array($this->base_url, array(self::DEFAULT_BASE_URL, self::REMOTE_BASE_URL), true);
        if ($use_stream) {
            $body['stream'] = true;
            $body['stream_options'] = array('include_usage' => true);
        }

        $auto_continue = !empty($options['auto_continue']);
        $detect_incomplete_ending = !empty($options['detect_incomplete_ending']);
        $max_continuations = $auto_continue
            ? min(3, max(1, absint($options['max_continuations'] ?? 2)))
            : 0;
        $combined_content = '';
        $continuation_count = 0;
        $request_count = 0;
        $attempt_count = 0;
        $usage = array('input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0, 'reported' => false);

        while (true) {
            $completion = $this->request_text_completion($body, $use_stream, $model);
            if (is_wp_error($completion)) {
                $this->finish_text_metrics($started_at, $model, $usage, $request_count, $attempt_count, 'error');
                return $completion;
            }
            $request_count++;
            $attempt_count += max(1, absint($completion['attempts'] ?? 1));
            $usage = $this->merge_usage($usage, $completion['usage'] ?? array());

            $segment = (string) $completion['content'];
            if (trim($segment) === '') {
                $this->finish_text_metrics($started_at, $model, $usage, $request_count, $attempt_count, 'error');
                return new WP_Error('azevent_empty_text_response', 'AzEvent API không trả về nội dung.');
            }
            $combined_content = $this->append_text_segment($combined_content, $segment);

            $truncated = $this->is_truncated_finish_reason($completion['finish_reason']);
            if (!$truncated && $detect_incomplete_ending) {
                $truncated = $this->looks_like_incomplete_content($combined_content);
            }
            if (!$truncated) {
                $this->finish_text_metrics($started_at, $model, $usage, $request_count, $attempt_count, 'success');
                return trim($combined_content);
            }

            if (!$auto_continue || $continuation_count >= $max_continuations) {
                $this->finish_text_metrics($started_at, $model, $usage, $request_count, $attempt_count, 'error');
                return new WP_Error(
                    'azevent_text_truncated',
                    'AI đã dừng vì hết giới hạn token trước khi hoàn tất nội dung. Plugin không chuyển sang bước SEO để tránh lưu bài bị cắt. Hãy thử lại bằng model có output dài hơn hoặc rút gọn Outline.'
                );
            }

            $continuation_count++;
            $body['messages'][] = array('role' => 'assistant', 'content' => $segment);
            $body['messages'][] = array(
                'role' => 'user',
                'content' => 'Tiếp tục chính xác từ vị trí vừa dừng. Không lặp lại nội dung đã viết, không mở đầu lại và không giải thích. Hãy hoàn tất toàn bộ các mục còn lại theo yêu cầu ban đầu. Chỉ trả về phần nội dung nối tiếp.',
            );
        }
    }

    public function generate_image($prompt, $model = '', $aspect_ratio = '1:1')
    {
        $prompt = trim((string) $prompt);
        if ($prompt === '') {
            return new WP_Error('azevent_empty_image_prompt', 'AzEvent image prompt đang trống.');
        }

        if ($this->api_key === '') {
            return new WP_Error('azevent_missing_image_api_key', 'Thiếu AzEvent API Key (CLIProxyAPI).');
        }

        $model = $model !== '' ? sanitize_text_field($model) : get_option('aprg_seo_default_cliproxy_image_model', 'gpt-image-2');
        $body = array(
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'response_format' => 'b64_json',
        );

        if ($model === 'gpt-image-2') {
            $body['size'] = $this->openai_size_for_aspect_ratio($aspect_ratio);
            $body['output_format'] = 'png';
        } elseif ($model === 'grok-imagine-image') {
            $body['aspect_ratio'] = $this->normalize_aspect_ratio($aspect_ratio);
            $body['resolution'] = '1k';
        }

        $result = $this->request('/images/generations', $body, 300);
        if (is_wp_error($result)) {
            return $result;
        }

        if ($result['status'] >= 200 && $result['status'] < 300) {
            $image = $this->parse_images_response($result['data']);
            if (!is_wp_error($image)) {
                return array_merge($image, array('model' => $model, 'provider' => 'azevent'));
            }

            if ($model === 'gemini-3.1-flash-image') {
                $chat_image = $this->find_chat_image($result['data']);
                if (!is_wp_error($chat_image)) {
                    return array_merge($chat_image, array('model' => $model, 'provider' => 'azevent'));
                }
            }

            return $image;
        }

        $message = $this->extract_error_message($result['data'], $result['status']);
        if ($model === 'gemini-3.1-flash-image' && $this->is_unsupported_image_model_error($result['status'], $message)) {
            return $this->generate_gemini_via_chat($prompt, $model);
        }

        return $this->api_error(self::get_provider_label($this->base_url) . ' Image', $result, $model);
    }

    private function generate_gemini_via_chat($prompt, $model)
    {
        $body = array(
            'model' => $model,
            'messages' => array(
                array('role' => 'user', 'content' => $prompt),
            ),
            'modalities' => array('image', 'text'),
            'stream' => false,
        );

        $result = $this->request('/chat/completions', $body, 300);
        if (is_wp_error($result)) {
            return $result;
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            return $this->api_error(self::get_provider_label($this->base_url) . ' Gemini Image', $result, $model);
        }

        $image = $this->find_chat_image($result['data']);
        if (is_wp_error($image)) {
            return $image;
        }

        return array_merge($image, array('model' => $model, 'provider' => 'azevent'));
    }

    private function request($path, array $body, $timeout)
    {
        $attempt = 0;
        $response = null;

        while (true) {
            $response = wp_remote_post(self::get_api_base_url($this->base_url) . $path, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ),
                'body' => wp_json_encode($body),
                'timeout' => $timeout,
            ));

            if (is_wp_error($response)) {
                return $response;
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            $rate_limited = $status === 429;
            $temporary_error = in_array($status, array(500, 502, 503, 504, 520, 521, 522, 523), true);
            $max_retries = $rate_limited ? 3 : ($temporary_error ? 1 : 0);
            if ($attempt >= $max_retries) {
                break;
            }

            $attempt++;
            $retry_after = absint(wp_remote_retrieve_header($response, 'retry-after'));
            if ($retry_after <= 0) {
                $retry_data = json_decode(wp_remote_retrieve_body($response), true);
                $retry_after = is_array($retry_data) ? absint($retry_data['retry_after'] ?? 0) : 0;
            }
            $default_delay = $rate_limited ? $attempt * 15 : 5;
            sleep(min(30, max(1, $retry_after ?: $default_delay)));
        }

        $raw_body = wp_remote_retrieve_body($response);
        $data = json_decode($raw_body, true);
        if (!is_array($data)) {
            $data = array('_raw' => substr((string) $raw_body, 0, 1000));
        }

        return array(
            'status' => (int) wp_remote_retrieve_response_code($response),
            'data' => $data,
            'raw_body' => (string) $raw_body,
            'attempts' => $attempt + 1,
        );
    }

    private function request_text_completion(array &$body, &$use_stream, $model)
    {
        $result = $this->request('/chat/completions', $body, 1200);
        if (is_wp_error($result)) {
            return $result;
        }

        $attempts = max(1, absint($result['attempts'] ?? 1));
        if ($use_stream && $this->is_stream_unsupported_error($result)) {
            unset($body['stream']);
            unset($body['stream_options']);
            $use_stream = false;
            $result = $this->request('/chat/completions', $body, 1200);
            if (is_wp_error($result)) {
                return $result;
            }
            $attempts += max(1, absint($result['attempts'] ?? 1));
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            return $this->api_error(self::get_provider_label($this->base_url) . ' Text', $result, $model);
        }

        $completion = $use_stream
            ? $this->parse_streamed_text($result['raw_body'])
            : array('content' => '', 'finish_reason' => '', 'usage' => $this->normalize_usage($result['data']['usage'] ?? array()));
        if (trim((string) $completion['content']) === '') {
            $choice = isset($result['data']['choices'][0]) && is_array($result['data']['choices'][0])
                ? $result['data']['choices'][0]
                : array();
            $completion['content'] = isset($choice['message']['content'])
                ? $this->normalize_text_content($choice['message']['content'])
                : '';
            $completion['finish_reason'] = $this->extract_finish_reason($choice);
        }

        $completion['attempts'] = $attempts;

        return $completion;
    }

    private function parse_streamed_text($raw_body)
    {
        $parts = array();
        $finish_reason = '';
        $usage = array();
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw_body);

        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'data:') !== 0) {
                continue;
            }

            $payload = trim(substr($line, 5));
            if ($payload === '' || $payload === '[DONE]') {
                continue;
            }

            $chunk = json_decode($payload, true);
            if (!is_array($chunk)) {
                continue;
            }
            if (!empty($chunk['usage']) && is_array($chunk['usage'])) {
                $usage = $this->normalize_usage($chunk['usage']);
            }
            if (empty($chunk['choices'][0])) {
                continue;
            }

            $choice = $chunk['choices'][0];
            $chunk_finish_reason = $this->extract_finish_reason($choice);
            if ($chunk_finish_reason !== '') {
                $finish_reason = $chunk_finish_reason;
            }
            if (isset($choice['delta']['content'])) {
                $parts[] = $this->normalize_text_content($choice['delta']['content']);
            } elseif (isset($choice['message']['content'])) {
                $parts[] = $this->normalize_text_content($choice['message']['content']);
            } elseif (isset($choice['text'])) {
                $parts[] = (string) $choice['text'];
            }
        }

        return array(
            'content' => implode('', $parts),
            'finish_reason' => $finish_reason,
            'usage' => $usage,
        );
    }

    private function normalize_usage($usage)
    {
        if (!is_array($usage) || empty($usage)) {
            return array('input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0, 'reported' => false);
        }
        $input = absint($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0);
        $output = absint($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0);
        $total = absint($usage['total_tokens'] ?? ($input + $output));
        return array(
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => $total ?: ($input + $output),
            'reported' => $input > 0 || $output > 0 || $total > 0,
        );
    }

    private function merge_usage(array $total, $usage)
    {
        $usage = $this->normalize_usage($usage);
        $total['input_tokens'] = absint($total['input_tokens'] ?? 0) + $usage['input_tokens'];
        $total['output_tokens'] = absint($total['output_tokens'] ?? 0) + $usage['output_tokens'];
        $total['total_tokens'] = absint($total['total_tokens'] ?? 0) + $usage['total_tokens'];
        $total['reported'] = !empty($total['reported']) || !empty($usage['reported']);
        return $total;
    }

    private function finish_text_metrics($started_at, $model, array $usage, $requests, $attempts, $status)
    {
        $this->last_text_metrics = array_merge($usage, array(
            'provider' => self::get_provider_label($this->base_url),
            'model' => sanitize_text_field($model),
            'duration_seconds' => round(max(0, microtime(true) - $started_at), 3),
            'requests' => absint($requests),
            'attempts' => absint($attempts),
            'status' => sanitize_key($status),
        ));
    }

    private function extract_finish_reason(array $choice)
    {
        foreach (array('finish_reason', 'stop_reason', 'finishReason') as $key) {
            if (isset($choice[$key]) && $choice[$key] !== null) {
                return sanitize_key((string) $choice[$key]);
            }
        }
        return '';
    }

    private function is_truncated_finish_reason($finish_reason)
    {
        return in_array((string) $finish_reason, array('length', 'max_tokens', 'max_token', 'token_limit'), true);
    }

    private function looks_like_incomplete_content($content)
    {
        $content = trim((string) $content);
        if ($content === '') {
            return true;
        }
        if (preg_match('/<\/h[1-6]>\s*$/i', $content)) {
            return true;
        }
        if (preg_match('/(?:^|\n)#{1,6}\s+[^\n]+$/u', $content)) {
            return true;
        }
        return substr_count($content, '<') > substr_count($content, '>');
    }

    private function append_text_segment($content, $segment)
    {
        if ($content === '') {
            return $segment;
        }

        $max_overlap = min(1000, strlen($content), strlen($segment));
        for ($length = $max_overlap; $length >= 20; $length--) {
            if (substr($content, -$length) === substr($segment, 0, $length)) {
                return $content . substr($segment, $length);
            }
        }
        return $content . $segment;
    }

    private function normalize_text_content($content)
    {
        if (!is_array($content)) {
            return (string) $content;
        }

        $parts = array();
        foreach ($content as $part) {
            if (is_array($part) && isset($part['text'])) {
                $parts[] = (string) $part['text'];
            } elseif (is_string($part)) {
                $parts[] = $part;
            }
        }
        return implode("\n", $parts);
    }

    private function is_stream_unsupported_error(array $result)
    {
        if (!in_array((int) $result['status'], array(400, 404, 422), true)) {
            return false;
        }

        $message = strtolower($this->extract_error_message($result['data'], $result['status']));
        return strpos($message, 'stream') !== false && (
            strpos($message, 'not support') !== false
            || strpos($message, 'unsupported') !== false
            || strpos($message, 'invalid') !== false
            || strpos($message, 'unknown') !== false
        );
    }

    private function parse_images_response(array $data)
    {
        $item = isset($data['data'][0]) && is_array($data['data'][0]) ? $data['data'][0] : array();
        $mime = !empty($item['mime_type']) ? sanitize_mime_type($item['mime_type']) : 'image/png';

        if (!empty($item['b64_json'])) {
            return $this->parse_image_value($item['b64_json'], $mime);
        }

        if (!empty($item['url'])) {
            return $this->download_image($item['url'], $mime);
        }

        $value = $this->extract_image_value($item);
        return $value !== '' ? $this->parse_image_value($value, $mime) : new WP_Error(
            'azevent_empty_image_response',
            'AzEvent API không trả về dữ liệu ảnh.'
        );
    }

    private function find_chat_image(array $data)
    {
        $message = isset($data['choices'][0]['message']) && is_array($data['choices'][0]['message'])
            ? $data['choices'][0]['message']
            : array();

        if (!empty($message['images']) && is_array($message['images'])) {
            foreach ($message['images'] as $image) {
                $value = $this->extract_image_value($image);
                if ($value !== '') {
                    return $this->parse_image_value($value, 'image/png');
                }
            }
        }

        if (!empty($message['content']) && is_array($message['content'])) {
            foreach ($message['content'] as $part) {
                $value = $this->extract_image_value($part);
                if ($value !== '') {
                    return $this->parse_image_value($value, 'image/png');
                }
            }
        }

        if (!empty($message['content']) && is_string($message['content'])) {
            if (preg_match('#data:image/[a-z0-9.+-]+;base64,[a-z0-9+/=\s]+#i', $message['content'], $matches)) {
                return $this->parse_image_value($matches[0], 'image/png');
            }
        }

        return new WP_Error('azevent_empty_chat_image_response', 'AzEvent API không trả về ảnh.');
    }

    private function extract_image_value($item)
    {
        if (!is_array($item)) {
            return '';
        }

        if (!empty($item['b64_json'])) {
            return (string) $item['b64_json'];
        }
        if (!empty($item['inline_data']['data'])) {
            $mime = !empty($item['inline_data']['mime_type']) ? $item['inline_data']['mime_type'] : 'image/png';
            return 'data:' . $mime . ';base64,' . $item['inline_data']['data'];
        }
        if (!empty($item['inlineData']['data'])) {
            $mime = !empty($item['inlineData']['mimeType']) ? $item['inlineData']['mimeType'] : 'image/png';
            return 'data:' . $mime . ';base64,' . $item['inlineData']['data'];
        }

        if (isset($item['image_url'])) {
            if (is_array($item['image_url']) && !empty($item['image_url']['url'])) {
                return (string) $item['image_url']['url'];
            }
            if (is_string($item['image_url'])) {
                return $item['image_url'];
            }
        }
        return !empty($item['url']) ? (string) $item['url'] : '';
    }

    private function parse_image_value($value, $fallback_mime)
    {
        $value = trim((string) $value);
        if (preg_match('#^data:(image/[a-z0-9.+-]+);base64,(.+)$#is', $value, $matches)) {
            return array(
                'base64' => preg_replace('/\s+/', '', $matches[2]),
                'mime' => sanitize_mime_type(strtolower($matches[1])),
            );
        }

        if (preg_match('#^https?://#i', $value)) {
            return $this->download_image($value, $fallback_mime);
        }

        if ($value !== '') {
            return array(
                'base64' => preg_replace('/\s+/', '', $value),
                'mime' => sanitize_mime_type($fallback_mime) ?: 'image/png',
            );
        }

        return new WP_Error('azevent_invalid_image_data', 'AzEvent API trả về dữ liệu ảnh không hợp lệ.');
    }

    private function download_image($url, $fallback_mime)
    {
        $response = wp_safe_remote_get(esc_url_raw($url), array(
            'timeout' => 120,
            'redirection' => 3,
            'limit_response_size' => 20 * MB_IN_BYTES,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $binary = wp_remote_retrieve_body($response);
        if ($status < 200 || $status >= 300 || $binary === '') {
            return new WP_Error('azevent_image_download_failed', 'Không thể tải ảnh từ AzEvent API.');
        }

        $mime = wp_remote_retrieve_header($response, 'content-type');
        $mime = is_string($mime) ? sanitize_mime_type(trim(explode(';', $mime)[0])) : '';
        if (strpos((string) $mime, 'image/') !== 0) {
            $mime = sanitize_mime_type($fallback_mime) ?: 'image/png';
        }

        return array('base64' => base64_encode($binary), 'mime' => $mime);
    }

    private function api_error($prefix, array $result, $model = '')
    {
        $message = $this->extract_error_message($result['data'], $result['status']);
        if ((int) $result['status'] === 524) {
            $message = 'HTTP 524 — Cloudflare đã chờ 120 giây nhưng model chưa gửi dữ liệu.';
            $message .= ' Plugin không lặp lại cùng request để tránh chờ thêm 120 giây. Hãy mở Job trong Background Queue để tiếp tục bằng model nhanh hơn hoặc dùng endpoint API không qua proxy Cloudflare.';
        }
        $suffix = $model !== '' ? ' (' . $model . ')' : '';
        return new WP_Error(
            'azevent_api_error',
            $prefix . $suffix . ': ' . $message,
            array('status' => $result['status'], 'model' => $model)
        );
    }

    private function extract_error_message(array $data, $status)
    {
        if (!empty($data['error']['message'])) {
            return sanitize_text_field($data['error']['message']);
        }
        if (!empty($data['message'])) {
            return sanitize_text_field($data['message']);
        }
        if (!empty($data['_raw'])) {
            return sanitize_text_field($data['_raw']);
        }
        return sprintf('HTTP %d', (int) $status);
    }

    private function is_unsupported_image_model_error($status, $message)
    {
        if ((int) $status !== 400) {
            return false;
        }

        $message = strtolower((string) $message);
        return strpos($message, 'not supported') !== false
            || strpos($message, 'unsupported') !== false
            || strpos($message, 'unknown provider') !== false;
    }

    private function normalize_aspect_ratio($aspect_ratio)
    {
        $allowed = array('1:1', '16:9', '9:16', '4:3', '3:4', '3:2', '2:3');
        return in_array($aspect_ratio, $allowed, true) ? $aspect_ratio : '1:1';
    }

    private function openai_size_for_aspect_ratio($aspect_ratio)
    {
        if ($aspect_ratio === '1:1') {
            return '1024x1024';
        }
        if ($aspect_ratio === '9:16' || $aspect_ratio === '3:4' || $aspect_ratio === '2:3') {
            return '1024x1536';
        }
        return '1536x1024';
    }
}
