<?php
/**
 * AzEvent API client.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_API_Client
{
    const DEFAULT_BASE_URL = 'http://192.168.1.5:8317';
    const REMOTE_BASE_URL = 'https://api.azevent.vn';

    private $api_key;
    private $base_url;
    private $model;

    public function __construct($api_key = '', $base_url = '', $model = '')
    {
        $this->api_key = $api_key !== '' ? $api_key : get_option('aprg_cliproxy_api_key', '');
        $this->base_url = self::get_base_url($base_url);
        $this->model = $model !== '' ? $model : get_option('aprg_cliproxy_model', 'claude-sonnet-4-6');
    }

    public static function get_base_url($base_url = '')
    {
        if ($base_url === '') {
            $base_url = get_option('aprg_cliproxy_base_url', self::DEFAULT_BASE_URL);
        }

        $allowed_urls = array(self::DEFAULT_BASE_URL, self::REMOTE_BASE_URL);
        if (!in_array($base_url, $allowed_urls, true)) {
            $base_url = self::DEFAULT_BASE_URL;
        }

        return rtrim($base_url, '/');
    }

    public static function get_api_base_url($base_url = '')
    {
        return self::get_base_url($base_url) . '/v1';
    }

    public static function is_configured()
    {
        return (string) get_option('aprg_cliproxy_api_key', '') !== '';
    }

    public function generate_text($prompt, $system_prompt = '', array $options = array())
    {
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
            $body['max_completion_tokens'] = max($max_tokens, 16384);
        } else {
            $body['max_tokens'] = $max_tokens;
            $body['temperature'] = isset($options['temperature']) ? (float) $options['temperature'] : 0.7;
        }

        if (!empty($options['response_format']) && is_array($options['response_format'])) {
            $body['response_format'] = $options['response_format'];
        }

        $result = $this->request('/chat/completions', $body, 1200);
        if (is_wp_error($result)) {
            return $result;
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            return $this->api_error('AzEvent API Text', $result);
        }

        $content = isset($result['data']['choices'][0]['message']['content'])
            ? $result['data']['choices'][0]['message']['content']
            : '';

        if (is_array($content)) {
            $parts = array();
            foreach ($content as $part) {
                if (is_array($part) && isset($part['text'])) {
                    $parts[] = $part['text'];
                }
            }
            $content = implode("\n", $parts);
        }

        $content = trim((string) $content);
        if ($content === '') {
            return new WP_Error('azevent_empty_text_response', 'AzEvent API không trả về nội dung.');
        }

        return $content;
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

        return $this->api_error('AzEvent API Image', $result, $model);
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
            return $this->api_error('AzEvent API Gemini Image', $result, $model);
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

        while ($attempt <= 3) {
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
            if ($status !== 429 || $attempt >= 3) {
                break;
            }

            $attempt++;
            sleep($attempt * 15);
        }

        $raw_body = wp_remote_retrieve_body($response);
        $data = json_decode($raw_body, true);
        if (!is_array($data)) {
            $data = array('_raw' => substr((string) $raw_body, 0, 1000));
        }

        return array(
            'status' => (int) wp_remote_retrieve_response_code($response),
            'data' => $data,
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
