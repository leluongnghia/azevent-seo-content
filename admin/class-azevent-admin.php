<?php
/**
 * Admin logic for AzEvent SEO Content Creator.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_azevent_fetch_legacy_models', array($this, 'fetch_legacy_models'));
    }

    /**
     * Add menu pages to the WordPress admin.
     */
    public function add_menu_pages()
    {
        add_menu_page(
            __('AzEvent SEO Settings', 'azevent-seo-content'),
            __('AzEvent SEO', 'azevent-seo-content'),
            'manage_options',
            'azevent-seo-settings',
            array($this, 'render_settings_page'),
            'dashicons-art',
            80
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings()
    {
        register_setting('azevent_seo_settings_group', 'aprg_cliproxy_api_key');
        register_setting('azevent_seo_settings_group', 'aprg_cliproxy_base_url');
        register_setting('azevent_seo_settings_group', 'aprg_cliproxy_model');
        register_setting('azevent_seo_settings_group', 'aprg_cliproxy_custom_models', array(
            'sanitize_callback' => array($this, 'sanitize_custom_models'),
        ));
        register_setting('azevent_seo_settings_group', 'aprg_seo_default_cliproxy_image_model');
        register_setting('azevent_seo_settings_group', 'azevent_seo_default_language');
        register_setting('azevent_seo_settings_group', 'azevent_seo_openai_key');
        register_setting('azevent_seo_settings_group', 'azevent_seo_anthropic_key');
        register_setting('azevent_seo_settings_group', 'azevent_seo_openai_model', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_seo_anthropic_model', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_seo_legacy_openai_models', array(
            'sanitize_callback' => array($this, 'sanitize_custom_models'),
        ));
        register_setting('azevent_seo_settings_group', 'azevent_seo_legacy_anthropic_models', array(
            'sanitize_callback' => array($this, 'sanitize_custom_models'),
        ));
        register_setting('azevent_seo_settings_group', 'azevent_seo_brand_name');
        register_setting('azevent_seo_settings_group', 'azevent_seo_brand_info');
        register_setting('azevent_seo_settings_group', 'azevent_seo_brand_solution');

        // Prompts
        $prompts = array('intent', 'outline', 'content', 'seo');
        foreach ($prompts as $p) {
            register_setting('azevent_seo_settings_group', "azevent_seo_{$p}_system");
            register_setting('azevent_seo_settings_group', "azevent_seo_{$p}_user");
        }
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page()
    {
        require_once AZEVENT_SEO_PATH . 'admin/views/settings-page.php';
    }

    public function sanitize_custom_models($value)
    {
        $models = is_array($value) ? $value : json_decode(wp_unslash((string) $value), true);
        if (!is_array($models)) {
            return '[]';
        }

        $models = array_values(array_unique(array_filter(array_map('sanitize_text_field', $models))));
        return wp_json_encode($models);
    }

    public function fetch_legacy_models()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Bạn không có quyền thực hiện thao tác này.', 'azevent-seo-content')), 403);
        }

        check_ajax_referer('azevent_fetch_legacy_models', 'nonce');

        $providers = array(
            'openai' => array(
                'key' => get_option('azevent_seo_openai_key', ''),
                'url' => 'https://api.openai.com/v1/models',
                'headers' => array(
                    'Authorization' => 'Bearer ' . get_option('azevent_seo_openai_key', ''),
                    'Accept' => 'application/json',
                ),
                'option' => 'azevent_seo_legacy_openai_models',
            ),
            'anthropic' => array(
                'key' => get_option('azevent_seo_anthropic_key', ''),
                'url' => 'https://api.anthropic.com/v1/models',
                'headers' => array(
                    'x-api-key' => get_option('azevent_seo_anthropic_key', ''),
                    'anthropic-version' => '2023-06-01',
                    'Accept' => 'application/json',
                ),
                'option' => 'azevent_seo_legacy_anthropic_models',
            ),
        );
        $models = array();
        $errors = array();

        foreach ($providers as $provider => $config) {
            if (trim((string) $config['key']) === '') {
                continue;
            }

            $response = wp_remote_get($config['url'], array(
                'timeout' => 30,
                'headers' => $config['headers'],
            ));

            if (is_wp_error($response)) {
                $errors[$provider] = $response->get_error_message();
                continue;
            }

            $status = wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if ($status < 200 || $status >= 300 || !is_array($data)) {
                $message = is_array($data) && !empty($data['error']['message'])
                    ? $data['error']['message']
                    : __('API không trả về danh sách model hợp lệ.', 'azevent-seo-content');
                $errors[$provider] = $message . ' (' . $status . ')';
                continue;
            }

            $provider_models = array();
            foreach ((array) ($data['data'] ?? array()) as $model) {
                if (!is_array($model) || empty($model['id'])) {
                    continue;
                }
                $provider_models[] = sanitize_text_field($model['id']);
            }
            $provider_models = array_values(array_unique(array_filter($provider_models)));
            if (!empty($provider_models)) {
                sort($provider_models, SORT_NATURAL | SORT_FLAG_CASE);
                update_option($config['option'], wp_json_encode($provider_models));
                $models[$provider] = $provider_models;
            } else {
                $errors[$provider] = __('Không tìm thấy model khả dụng cho API key này.', 'azevent-seo-content');
            }
        }

        if (empty($models) && !empty($errors)) {
            wp_send_json_error(array(
                'message' => implode(' ', $errors),
                'errors' => $errors,
            ));
        }

        wp_send_json_success(array(
            'models' => $models,
            'errors' => $errors,
            'message' => empty($errors)
                ? __('Đã cập nhật danh sách model mới nhất.', 'azevent-seo-content')
                : __('Đã cập nhật một phần danh sách model. Kiểm tra thông báo theo từng API.', 'azevent-seo-content'),
        ));
    }
}

new AzEvent_Admin();
