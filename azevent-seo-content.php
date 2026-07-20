<?php
/**
 * Plugin Name: AzEvent SEO Content Creator
 * Plugin URI:  https://azevent.vn/
 * Description: Tự động hóa việc tạo nội dung chuẩn SEO từ từ khóa sử dụng AI (Claude/GPT) và tạo ảnh đại diện bằng DALL-E. Tích hợp trực tiếp vào Classic Editor.
 * Version:     1.1.7
 * Author:      AzEvent Team
 * Author URI:  https://azevent.vn/
 * License:     GPL2
 * Text Domain: azevent-seo-content
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define constants
define('AZEVENT_SEO_VERSION', '1.1.7');
define('AZEVENT_SEO_PATH', plugin_dir_path(__FILE__));
define('AZEVENT_SEO_URL', plugin_dir_url(__FILE__));

/**
 * The main class of the plugin.
 */
class AzEvent_SEO_Content
{

    /**
     * Instance of this class.
     */
    private static $instance = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->includes();
        $this->maybe_upgrade_prompt_templates();
        $this->init_hooks();
    }

    private function maybe_upgrade_prompt_templates()
    {
        $template_version = get_option('azevent_seo_prompt_template_version', '');
        if ($template_version === 'source-v2') {
            return;
        }

        $prompts = AzEvent_Editor_Integration::get_default_prompts();

        if ($template_version === 'source-v1') {
            foreach ($prompts as $key => $prompt) {
                $system_option = "azevent_seo_{$key}_system";
                $user_option = "azevent_seo_{$key}_user";
                $system = (string) get_option($system_option, $prompt['system']);
                $user = (string) get_option($user_option, $prompt['user']);

                $system = str_replace(
                    array('### Output Language:*Vietnamese*', 'fluent in Vietnamese'),
                    array('### Output Language:*{language}*', 'fluent in {language}'),
                    $system
                );
                $user = str_replace(
                    'Vietnamese articles',
                    'The article must be written in {language}.',
                    $user
                );

                update_option($system_option, $system);
                update_option($user_option, $user);
            }

            update_option('azevent_seo_prompt_template_version', 'source-v2');
            return;
        }

        foreach ($prompts as $key => $prompt) {
            update_option("azevent_seo_{$key}_system", $prompt['system']);
            update_option("azevent_seo_{$key}_user", $prompt['user']);
        }
        update_option('azevent_seo_prompt_template_version', 'source-v2');
    }

    /**
     * Get instance.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Include required files.
     */
    private function includes()
    {
        require_once AZEVENT_SEO_PATH . 'includes/class-azevent-api-client.php';
        require_once AZEVENT_SEO_PATH . 'includes/class-azevent-ai-service.php';
        require_once AZEVENT_SEO_PATH . 'includes/class-azevent-content-pipeline.php';
        require_once AZEVENT_SEO_PATH . 'includes/class-azevent-editor-integration.php';
        require_once AZEVENT_SEO_PATH . 'includes/class-azevent-background-queue.php';
        require_once AZEVENT_SEO_PATH . 'includes/class-azevent-github-updater.php';

        new AzEvent_Background_Queue();
        new AzEvent_GitHub_Updater(__FILE__, 'leluongnghia', 'azevent-seo-content');

        if (is_admin()) {
            require_once AZEVENT_SEO_PATH . 'admin/class-azevent-admin.php';
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks()
    {
        add_action('plugins_loaded', array($this, 'on_plugins_loaded'));
    }

    /**
     * Fired when plugins are loaded.
     */
    public function on_plugins_loaded()
    {
        // Initialize services or integration here if needed
    }

    public static function activate()
    {
        require_once plugin_dir_path(__FILE__) . 'includes/class-azevent-background-queue.php';
        AzEvent_Background_Queue::install();
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook(AzEvent_Background_Queue::CRON_HOOK);
        delete_option(AzEvent_Background_Queue::LOCK_OPTION);
    }
}

register_activation_hook(__FILE__, array('AzEvent_SEO_Content', 'activate'));
register_deactivation_hook(__FILE__, array('AzEvent_SEO_Content', 'deactivate'));

// Start the plugin
AzEvent_SEO_Content::get_instance();
