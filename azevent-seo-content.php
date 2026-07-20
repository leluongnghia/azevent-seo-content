<?php
/**
 * Plugin Name: AzEvent SEO Content Creator
 * Plugin URI:  https://azevent.vn/
 * Description: Tự động hóa việc tạo nội dung chuẩn SEO từ từ khóa sử dụng AI (Claude/GPT) và tạo ảnh đại diện bằng DALL-E. Tích hợp trực tiếp vào Classic Editor.
 * Version:     1.2.0
 * Author:      AzEvent Team
 * Author URI:  https://azevent.vn/
 * License:     GPL2
 * Text Domain: azevent-seo-content
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define constants
define('AZEVENT_SEO_VERSION', '1.2.0');
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
        $this->maybe_seed_brand_profile();
        $this->maybe_upgrade_prompt_templates();
        $this->init_hooks();
    }

    public static function get_default_brand_profile()
    {
        return array(
            'azevent_seo_brand_name' => 'AzEvent',
            'azevent_seo_brand_info' => 'AzEvent là công ty tổ chức sự kiện chuyên nghiệp tại Hà Nội, phục vụ khách hàng doanh nghiệp tại Hà Nội và TP. Hồ Chí Minh. Thương hiệu đồng hành từ tư vấn chiến lược, phát triển ý tưởng, thiết kế và sản xuất đến thi công, vận hành hiện trường, nghiệm thu và đánh giá sau sự kiện. Định hướng triển khai là mục tiêu rõ ràng, thông điệp phù hợp, quy trình đồng bộ, kiểm soát ngân sách và tiến độ, đồng thời tối ưu trải nghiệm khách mời và hình ảnh thương hiệu. Phong cách nội dung: chuyên nghiệp, thực tế, rõ ràng, đáng tin cậy, không phô trương hoặc đưa ra cam kết tuyệt đối. Trụ sở: LK 17-19(245) Khu đô thị mới Văn Khê, phường La Khê, quận Hà Đông, Hà Nội. Hotline: 09123.86.968. Email: info@azevent.vn. Website: https://azevent.vn/.',
            'azevent_seo_brand_solution' => 'AzEvent cung cấp giải pháp tổ chức sự kiện trọn gói cho hội nghị, hội thảo, hội nghị khách hàng, khai trương, khởi công và động thổ, lễ kỷ niệm, ra mắt sản phẩm, Year End Party; đồng thời thiết kế và thi công sân khấu, gian hàng triển lãm và hội chợ. Quy trình gồm: tiếp nhận brief và xác định mục tiêu; tư vấn chiến lược, concept và ý tưởng; lập kế hoạch, timeline, checklist và báo giá; thiết kế 2D/3D, sản xuất, thi công, chuẩn bị thiết bị và nhân sự; tổng duyệt và vận hành onsite; nghiệm thu, bàn giao tư liệu và đánh giá sau sự kiện. Khi viết nội dung, ưu tiên giải pháp phù hợp với quy mô, ngân sách, địa điểm và mục tiêu của khách hàng. Không tự bịa báo giá, số năm kinh nghiệm, số lượng dự án, khách hàng hoặc thành tích chưa có dữ liệu xác minh. CTA mềm: mời khách hàng gửi brief để AzEvent tư vấn concept, timeline, checklist hạng mục và báo giá phù hợp.',
        );
    }

    public static function get_brand_profile()
    {
        $profile = self::get_default_brand_profile();

        foreach ($profile as $option => $default_value) {
            $saved_value = get_option($option, '');
            $profile[$option] = trim((string) $saved_value) === '' ? $default_value : $saved_value;
        }

        return $profile;
    }

    private function maybe_seed_brand_profile()
    {
        foreach (self::get_default_brand_profile() as $option => $default_value) {
            $current_value = get_option($option, null);
            if ($current_value === null || trim((string) $current_value) === '') {
                update_option($option, $default_value);
            }
        }

        if ((string) get_option('azevent_seo_brand_profile_version', '') !== 'website-v1') {
            update_option('azevent_seo_brand_profile_version', 'website-v1', false);
        }
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
        require_once AZEVENT_SEO_PATH . 'includes/class-azevent-ckey-client.php';
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
