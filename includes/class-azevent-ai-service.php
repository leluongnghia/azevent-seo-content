<?php
/**
 * AI Service for AzEvent SEO Content Creator.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_AI_Service
{

    private $openai_key;
    private $anthropic_key;
    private $openai_model;
    private $anthropic_model;
    private $azevent_api;
    private $ckey_api;

    public function __construct()
    {
        $this->openai_key = get_option('azevent_seo_openai_key');
        $this->anthropic_key = get_option('azevent_seo_anthropic_key');
        $this->openai_model = get_option('azevent_seo_openai_model', 'gpt-4o-mini');
        $this->anthropic_model = get_option('azevent_seo_anthropic_model', 'claude-3-5-sonnet-20240620');
        $this->azevent_api = new AzEvent_API_Client();
        $this->ckey_api = new AzEvent_CKey_Client();
    }

    /**
     * Call Anthropic API (Claude).
     */
    public function call_anthropic($user_prompt, $system_prompt = '', $model = '', $max_tokens = 8192, array $generation_options = array())
    {
        if (AzEvent_CKey_Client::is_model_reference($model)) {
            return $this->ckey_api->generate_text($user_prompt, $system_prompt, array_merge($generation_options, array(
                'model' => AzEvent_CKey_Client::strip_model_prefix($model),
                'max_tokens' => max(1024, absint($max_tokens)),
            )));
        }

        if (AzEvent_API_Client::is_configured()) {
            $options = array_merge($generation_options, array(
                'max_tokens' => max(1024, absint($max_tokens)),
            ));
            if ($model !== '') {
                $options['model'] = sanitize_text_field($model);
            }
            return $this->azevent_api->generate_text($user_prompt, $system_prompt, $options);
        }

        if (!$this->anthropic_key) {
            return new WP_Error('missing_key', 'Thiếu Anthropic API Key.');
        }

        $model = $this->anthropic_model !== '' ? $this->anthropic_model : ($model !== '' ? $model : 'claude-3-5-sonnet-20240620');

        $body = array(
            'model' => $model,
            'max_tokens' => 4000,
            'messages' => array(
                array('role' => 'user', 'content' => $user_prompt),
            ),
        );

        if (!empty($system_prompt)) {
            $body['system'] = $system_prompt;
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'timeout' => 120,
            'headers' => array(
                'x-api-key' => $this->anthropic_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ),
            'body' => json_encode($body),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['content'][0]['text'])) {
            return $body['content'][0]['text'];
        }

        return new WP_Error('api_error', 'Lỗi phản hồi từ Anthropic: ' . print_r($body, true));
    }

    /**
     * Call OpenAI API (GPT or DALL-E).
     */
    public function call_openai($user_prompt, $system_prompt = '', $endpoint = 'chat/completions', $args = array())
    {
        if ($endpoint !== 'images/generations' && AzEvent_CKey_Client::is_model_reference($args['model'] ?? '')) {
            return $this->ckey_api->generate_text($user_prompt, $system_prompt, array(
                'model' => AzEvent_CKey_Client::strip_model_prefix($args['model']),
                'max_tokens' => max(1024, absint($args['max_tokens'] ?? 8192)),
            ));
        }

        if (AzEvent_API_Client::is_configured()) {
            if ($endpoint === 'images/generations') {
                return $this->generate_image($user_prompt);
            }

            $options = array();
            if (isset($args['response_format']) && is_array($args['response_format'])) {
                $options['response_format'] = $args['response_format'];
            }
            if (!empty($args['model'])) {
                $options['model'] = sanitize_text_field($args['model']);
            }
            if (!empty($args['max_tokens'])) {
                $options['max_tokens'] = max(1024, absint($args['max_tokens']));
            }
            return $this->azevent_api->generate_text($user_prompt, $system_prompt, $options);
        }

        if (!$this->openai_key) {
            return new WP_Error('missing_key', 'Thiếu OpenAI API Key.');
        }

        $model = $this->openai_model !== '' ? $this->openai_model : 'gpt-4o-mini';

        $url = 'https://api.openai.com/v1/' . $endpoint;

        $messages = array();
        if (!empty($system_prompt)) {
            $messages[] = array('role' => 'system', 'content' => $system_prompt);
        }
        $messages[] = array('role' => 'user', 'content' => $user_prompt);

        if ($endpoint === 'images/generations') {
            $body = array(
                'model' => 'dall-e-3',
                'prompt' => $user_prompt,
                'n' => 1,
                'size' => '1024x1024',
            );
        } else {
            $body = array_merge(array(
                'model' => $model,
                'messages' => $messages,
            ), $args);
        }

        $response = wp_remote_post($url, array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->openai_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($endpoint === 'images/generations') {
            return isset($data['data'][0]['url']) ? $data['data'][0]['url'] : new WP_Error('api_error', 'Lỗi tạo ảnh: ' . print_r($data, true));
        }

        return isset($data['choices'][0]['message']['content']) ? $data['choices'][0]['message']['content'] : new WP_Error('api_error', 'Lỗi OpenAI: ' . print_r($data, true));
    }

    /**
     * Generate an image through AzEvent API.
     */
    public function generate_image($prompt, $model = '', $aspect_ratio = '1:1')
    {
        if (!AzEvent_API_Client::is_configured()) {
            return new WP_Error('missing_azevent_key', 'Thiếu AzEvent API Key (CLIProxyAPI).');
        }

        return $this->azevent_api->generate_image($prompt, $model, $aspect_ratio);
    }
}
