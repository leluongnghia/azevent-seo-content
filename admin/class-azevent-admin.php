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
        add_action('admin_head', array($this, 'render_modal_frame_styles'));
        add_action('wp_ajax_azevent_fetch_legacy_models', array($this, 'fetch_legacy_models'));
        add_action('wp_ajax_azevent_test_ckey_connection', array($this, 'test_ckey_connection'));
    }

    /**
     * Add menu pages to the WordPress admin.
     */
    public function add_menu_pages()
    {
        add_menu_page(
            __('AzEvent SEO Queue', 'azevent-seo-content'),
            __('AzEvent SEO', 'azevent-seo-content'),
            'edit_posts',
            'azevent-seo-background-queue',
            array($this, 'render_background_queue_page'),
            'dashicons-art',
            80
        );

        add_submenu_page(
            null,
            __('Content Studio', 'azevent-seo-content'),
            __('Content Studio', 'azevent-seo-content'),
            'edit_posts',
            'azevent-seo-content-studio',
            array($this, 'render_content_studio_page')
        );

        add_submenu_page(
            null,
            __('SEO Workflow Lab', 'azevent-seo-content'),
            __('SEO Workflow Lab', 'azevent-seo-content'),
            'edit_posts',
            'azevent-seo-workflow-lab',
            array($this, 'render_workflow_lab_page')
        );

        add_submenu_page(
            null,
            __('AzEvent SEO Settings', 'azevent-seo-content'),
            __('Settings', 'azevent-seo-content'),
            'manage_options',
            'azevent-seo-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Strip the WordPress admin chrome when a plugin page is embedded in the
     * Queue modal. Each page keeps its normal capability check and assets.
     */
    public function render_modal_frame_styles()
    {
        if (absint($_GET['azevent_modal'] ?? 0) !== 1) {
            return;
        }

        $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));
        if (!in_array($page, array(
            'azevent-seo-settings',
            'azevent-seo-content-studio',
            'azevent-seo-workflow-lab',
        ), true)) {
            return;
        }
        ?>
        <style>
            html.wp-toolbar { padding-top: 0; }
            body { min-width: 0; background: #f8fafc; }
            #wpadminbar, #adminmenumain, #wpfooter, .update-nag, .notice:not(.settings-error) { display: none !important; }
            #wpcontent, #wpfooter { margin-left: 0; }
            #wpbody-content { min-height: 100vh; padding-bottom: 0; }
            .azevent-studio-admin-page { width: auto; max-width: none; margin: 0; }
            .azevent-studio-admin-page > h1,
            .azevent-studio-admin-page > .description,
            .azevent-studio-admin-page > .azevent-launch-card { display: none; }
            .azevent-studio-admin-page .azevent-modal { padding: 0; }
            .azevent-studio-admin-page .azevent-modal-dialog {
                width: 100%;
                max-width: none;
                height: 100%;
                max-height: none;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }
            .azevent-studio-admin-page .azevent-modal-header { display: none; }
            .azlab-page { width: auto; max-width: none !important; margin: 0 !important; padding: 18px; }
            .azlab-page > .azlab-hero { display: none; }
            .azlab-page > .azlab-layout { margin-top: 0; }
            @media (max-width: 782px) {
                .azlab-page { padding: 10px; }
                .azevent-studio-admin-page .azevent-modal-body { padding: 14px; }
            }
        </style>
        <script>
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && window.parent !== window) {
                    window.parent.postMessage('azevent-close-admin-modal', window.location.origin);
                }
            });
        </script>
        <?php
    }

    /**
     * Register plugin settings.
     */
    public function register_settings()
    {
        register_setting('azevent_seo_settings_group', 'aprg_cliproxy_api_key');
        register_setting('azevent_seo_settings_group', 'azevent_cliapi_api_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_remote_api_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('azevent_seo_settings_group', 'aprg_cliproxy_base_url', array(
            'sanitize_callback' => array('AzEvent_API_Client', 'get_base_url'),
            'default' => AzEvent_API_Client::DEFAULT_BASE_URL,
        ));
        register_setting('azevent_seo_settings_group', 'aprg_cliproxy_model');
        register_setting('azevent_seo_settings_group', 'azevent_seo_text_provider', array(
            'sanitize_callback' => array($this, 'sanitize_text_provider'),
        ));
        register_setting('azevent_seo_settings_group', 'azevent_seo_ckey_api_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_seo_ckey_model', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_seo_ckey_api_format', array(
            'sanitize_callback' => array($this, 'sanitize_ckey_api_format'),
        ));
        register_setting('azevent_seo_settings_group', 'azevent_seo_ckey_custom_models', array(
            'sanitize_callback' => array($this, 'sanitize_custom_models'),
        ));
        foreach (array('intent', 'outline', 'content', 'seo') as $step) {
            register_setting('azevent_seo_settings_group', "azevent_seo_{$step}_model", array(
                'sanitize_callback' => 'sanitize_text_field',
            ));
        }
        register_setting('azevent_seo_settings_group', 'aprg_cliproxy_custom_models', array(
            'sanitize_callback' => array($this, 'sanitize_custom_models'),
        ));
        register_setting('azevent_seo_settings_group', 'aprg_seo_default_cliproxy_image_model');
        register_setting('azevent_seo_settings_group', 'azevent_seo_default_language');
        register_setting('azevent_seo_settings_group', 'azevent_seo_browser_auto_advance', array(
            'sanitize_callback' => 'absint',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_seo_split_content_by_outline', array(
            'sanitize_callback' => 'absint',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_seo_generate_h2_images', array(
            'sanitize_callback' => 'absint',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_seo_h2_image_limit', array(
            'sanitize_callback' => array($this, 'sanitize_h2_image_limit'),
        ));
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
        register_setting('azevent_seo_settings_group', 'azevent_lab_serpapi_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_lab_serp_location', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_lab_serp_country', array(
            'sanitize_callback' => 'sanitize_key',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_lab_serp_language', array(
            'sanitize_callback' => 'sanitize_key',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_lab_serp_result_count', array(
            'sanitize_callback' => 'absint',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_lab_serp_fetch_pages', array(
            'sanitize_callback' => 'absint',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_lab_split_content_by_outline', array(
            'sanitize_callback' => 'absint',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_lab_validate_outline', array(
            'sanitize_callback' => 'absint',
        ));
        register_setting('azevent_seo_settings_group', 'azevent_lab_outline_validation_model', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // Prompts
        $prompts = array('intent', 'outline', 'content', 'seo');
        foreach ($prompts as $p) {
            register_setting('azevent_seo_settings_group', "azevent_seo_{$p}_system");
            register_setting('azevent_seo_settings_group', "azevent_seo_{$p}_user");
        }

        foreach (AzEvent_GEO_Prompts::get_defaults(AzEvent_GEO_Prompts::CONTENT_STUDIO) as $step => $prompt) {
            register_setting(
                'azevent_seo_settings_group',
                AzEvent_GEO_Prompts::option_name(AzEvent_GEO_Prompts::CONTENT_STUDIO, $step)
            );
        }
        register_setting('azevent_seo_settings_group', 'azevent_geo_content_studio_default_enabled', array(
            'sanitize_callback' => 'absint',
        ));

        foreach (array('research', 'brief', 'content', 'seo', 'quality') as $step) {
            register_setting('azevent_seo_settings_group', "azevent_lab_{$step}_system");
            register_setting('azevent_seo_settings_group', "azevent_lab_{$step}_user");
            register_setting('azevent_seo_settings_group', "azevent_lab_{$step}_model", array(
                'sanitize_callback' => 'sanitize_text_field',
            ));
        }

        foreach (AzEvent_GEO_Prompts::get_defaults(AzEvent_GEO_Prompts::WORKFLOW_LAB) as $step => $prompt) {
            register_setting(
                'azevent_seo_settings_group',
                AzEvent_GEO_Prompts::option_name(AzEvent_GEO_Prompts::WORKFLOW_LAB, $step)
            );
        }
        register_setting('azevent_seo_settings_group', 'azevent_geo_workflow_lab_default_enabled', array(
            'sanitize_callback' => 'absint',
        ));
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page()
    {
        require_once AZEVENT_SEO_PATH . 'admin/views/settings-page.php';
    }

    public function render_background_queue_page()
    {
        require_once AZEVENT_SEO_PATH . 'admin/views/background-queue-page.php';
    }

    public function render_content_studio_page()
    {
        AzEvent_Editor_Integration::render_standalone_page();
    }

    public function render_workflow_lab_page()
    {
        AzEvent_SEO_Workflow_Lab::render_page();
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

    public function sanitize_text_provider($value)
    {
        $value = sanitize_key($value);
        return in_array($value, array('azevent', 'ckey'), true) ? $value : 'azevent';
    }

    public function sanitize_ckey_api_format($value)
    {
        $value = sanitize_key($value);
        return in_array($value, array('messages', 'auto', 'chat'), true) ? $value : 'messages';
    }

    public function sanitize_h2_image_limit($value)
    {
        return min(10, max(1, absint($value)));
    }

    public function test_ckey_connection()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Bạn không có quyền thực hiện thao tác này.', 'azevent-seo-content')), 403);
        }

        check_ajax_referer('azevent_test_ckey_connection', 'nonce');

        $api_key = sanitize_text_field(wp_unslash($_POST['api_key'] ?? ''));
        $model = sanitize_text_field(wp_unslash($_POST['model'] ?? ''));
        $api_format = $this->sanitize_ckey_api_format(wp_unslash($_POST['api_format'] ?? 'messages'));
        if ($api_key === '' || $model === '') {
            wp_send_json_error(array('message' => __('Hãy nhập API Key và chọn model CKey trước khi kiểm tra.', 'azevent-seo-content')), 400);
        }

        $client = new AzEvent_CKey_Client($api_key, $model);
        $result = $client->generate_text('Chỉ trả lời chính xác: CKEY_OK', '', array(
            'model' => $model,
            'api_format' => $api_format,
            'max_tokens' => 1024,
            'temperature' => 0,
        ));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 502);
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('Kết nối CKey thành công qua %1$s với model %2$s.', 'azevent-seo-content'),
                AzEvent_CKey_Client::uses_anthropic_format($api_format, $model) ? 'Claude Messages' : 'OpenAI Chat',
                $model
            ),
        ));
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
