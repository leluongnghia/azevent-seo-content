<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_CKey_Client
{
    const API_BASE_URL = 'https://api.xah.io/v1';
    const MODEL_PREFIX = 'ckey::';

    private $api_key;
    private $model;
    private $last_text_metrics = array();

    public function __construct($api_key = '', $model = '')
    {
        $this->api_key = $api_key !== '' ? $api_key : get_option('azevent_seo_ckey_api_key', '');
        $this->model = $model !== '' ? $model : get_option('azevent_seo_ckey_model', '');
    }

    public static function is_configured()
    {
        return trim((string) get_option('azevent_seo_ckey_api_key', '')) !== '';
    }

    public function get_last_text_metrics()
    {
        return $this->last_text_metrics;
    }

    public static function model_reference($model)
    {
        $model = self::strip_model_prefix($model);
        return $model === '' ? '' : self::MODEL_PREFIX . $model;
    }

    public static function is_model_reference($model)
    {
        return strpos((string) $model, self::MODEL_PREFIX) === 0;
    }

    public static function strip_model_prefix($model)
    {
        $model = sanitize_text_field((string) $model);
        if (self::is_model_reference($model)) {
            $model = substr($model, strlen(self::MODEL_PREFIX));
        }
        return trim($model);
    }

    public static function uses_anthropic_format($api_format, $model)
    {
        $api_format = sanitize_key((string) $api_format);
        $model = self::strip_model_prefix($model);
        return $api_format === 'messages'
            || ($api_format === 'auto' && preg_match('/(?:^|[\/:_-])claude(?:[\/:_.-]|$)/i', $model));
    }

    public function generate_text($prompt, $system_prompt = '', array $options = array())
    {
        $started_at = microtime(true);
        $this->last_text_metrics = array();
        $prompt = trim((string) $prompt);
        if ($prompt === '') {
            return new WP_Error('azevent_ckey_empty_prompt', 'CKey prompt đang trống.');
        }
        if ($this->api_key === '') {
            return new WP_Error('azevent_ckey_missing_key', 'Thiếu CKey API Key.');
        }

        $model = self::strip_model_prefix($options['model'] ?? $this->model);
        if ($model === '') {
            return new WP_Error('azevent_ckey_missing_model', 'Chưa chọn CKey model.');
        }

        $api_format = sanitize_key($options['api_format'] ?? get_option('azevent_seo_ckey_api_format', 'messages'));
        if (!in_array($api_format, array('messages', 'auto', 'chat'), true)) {
            $api_format = 'messages';
        }
        $anthropic_format = self::uses_anthropic_format($api_format, $model);
        $max_tokens = max(1024, absint($options['max_tokens'] ?? 8192));
        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.7;
        $messages = array(array('role' => 'user', 'content' => $prompt));
        $body = array(
            'model' => $model,
            'max_tokens' => $max_tokens,
            'messages' => $messages,
            'temperature' => $temperature,
        );
        if ($anthropic_format) {
            if (trim((string) $system_prompt) !== '') {
                $body['system'] = (string) $system_prompt;
            }
            $path = '/messages';
        } else {
            if (trim((string) $system_prompt) !== '') {
                array_unshift($body['messages'], array('role' => 'system', 'content' => (string) $system_prompt));
            }
            $path = '/chat/completions';
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
            $completion = $this->request_completion($path, $body, $anthropic_format, $model);
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
                return new WP_Error('azevent_ckey_empty_response', 'CKey API không trả về nội dung.');
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
                    'azevent_ckey_text_truncated',
                    'CKey AI đã dừng vì hết giới hạn token trước khi hoàn tất nội dung. Hãy chọn model có output dài hơn hoặc rút gọn Outline.'
                );
            }

            $continuation_count++;
            $body['messages'][] = array('role' => 'assistant', 'content' => $segment);
            $body['messages'][] = array(
                'role' => 'user',
                'content' => 'Tiếp tục chính xác từ vị trí vừa dừng. Không lặp lại nội dung đã viết, không mở đầu lại và không giải thích. Chỉ trả về phần nội dung nối tiếp.',
            );
        }
    }

    private function request_completion($path, array $body, $anthropic_format, $model)
    {
        $result = $this->request($path, $body, 1200, $anthropic_format);
        if (is_wp_error($result)) {
            return $result;
        }
        if ($result['status'] < 200 || $result['status'] >= 300) {
            return new WP_Error(
                'azevent_ckey_api_error',
                'CKey API Text (' . $model . '): ' . $this->extract_error_message($result['data'], $result['status'], $result['raw_body'])
            );
        }

        if ($anthropic_format) {
            $content = '';
            foreach ((array) ($result['data']['content'] ?? array()) as $block) {
                if (is_array($block) && (!isset($block['type']) || $block['type'] === 'text') && isset($block['text'])) {
                    $content .= (string) $block['text'];
                }
            }
            return array(
                'content' => $content,
                'finish_reason' => sanitize_key((string) ($result['data']['stop_reason'] ?? '')),
                'usage' => $this->normalize_usage($result['data']['usage'] ?? array()),
                'attempts' => max(1, absint($result['attempts'] ?? 1)),
            );
        }

        $choice = isset($result['data']['choices'][0]) && is_array($result['data']['choices'][0])
            ? $result['data']['choices'][0]
            : array();
        return array(
            'content' => $this->normalize_text_content($choice['message']['content'] ?? ''),
            'finish_reason' => sanitize_key((string) ($choice['finish_reason'] ?? $choice['stop_reason'] ?? '')),
            'usage' => $this->normalize_usage($result['data']['usage'] ?? array()),
            'attempts' => max(1, absint($result['attempts'] ?? 1)),
        );
    }

    private function request($path, array $body, $timeout, $anthropic_format)
    {
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        );
        if ($anthropic_format) {
            $headers['x-api-key'] = $this->api_key;
            $headers['anthropic-version'] = '2023-06-01';
        } else {
            $headers['Authorization'] = 'Bearer ' . $this->api_key;
        }

        $attempt = 0;
        while (true) {
            $response = wp_remote_post(self::API_BASE_URL . $path, array(
                'timeout' => $timeout,
                'headers' => $headers,
                'body' => wp_json_encode($body),
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
            $delay = $retry_after > 0 ? $retry_after : ($rate_limited ? $attempt * 15 : 5);
            sleep(min(30, max(1, $delay)));
        }

        $raw_body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($raw_body, true);
        return array(
            'status' => (int) wp_remote_retrieve_response_code($response),
            'data' => is_array($data) ? $data : array(),
            'raw_body' => $raw_body,
            'attempts' => $attempt + 1,
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
            'provider' => 'CKey',
            'model' => sanitize_text_field($model),
            'duration_seconds' => round(max(0, microtime(true) - $started_at), 3),
            'requests' => absint($requests),
            'attempts' => absint($attempts),
            'status' => sanitize_key($status),
        ));
    }

    private function extract_error_message($data, $status, $raw_body = '')
    {
        if (is_array($data)) {
            if (!empty($data['error']['message'])) {
                return (string) $data['error']['message'] . ' (HTTP ' . absint($status) . ')';
            }
            if (!empty($data['error']) && is_string($data['error'])) {
                return $data['error'] . ' (HTTP ' . absint($status) . ')';
            }
            if (!empty($data['message'])) {
                return (string) $data['message'] . ' (HTTP ' . absint($status) . ')';
            }
        }
        $raw_body = trim(strip_tags((string) $raw_body));
        return ($raw_body !== '' ? substr($raw_body, 0, 300) . ' — ' : '') . 'HTTP ' . absint($status);
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
}
