<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_SEO_Workflow_Lab
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_azevent_lab_create_session', array($this, 'ajax_create_session'));
        add_action('wp_ajax_azevent_lab_process_step', array($this, 'ajax_process_step'));
        add_action('wp_ajax_azevent_lab_get_session', array($this, 'ajax_get_session'));
    }

    public static function render_page()
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Bạn không có quyền sử dụng SEO Workflow Lab.', 'azevent-seo-content'));
        }
        require AZEVENT_SEO_PATH . 'admin/views/workflow-lab-page.php';
    }

    public function enqueue_assets($hook)
    {
        $is_lab_page = isset($_GET['page'])
            && sanitize_key(wp_unslash($_GET['page'])) === 'azevent-seo-workflow-lab';
        if (!$is_lab_page) {
            return;
        }

        wp_enqueue_style('azevent-seo-workflow-lab', AZEVENT_SEO_URL . 'admin/css/workflow-lab.css', array('dashicons'), AZEVENT_SEO_VERSION);
        wp_enqueue_script('azevent-seo-workflow-lab', AZEVENT_SEO_URL . 'admin/js/workflow-lab.js', array(), AZEVENT_SEO_VERSION, true);
        wp_localize_script('azevent-seo-workflow-lab', 'azevent_workflow_lab', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('azevent_workflow_lab'),
            'post_id' => absint($_GET['azevent_lab_post'] ?? 0),
            'default_language' => get_option('azevent_seo_default_language', 'Vietnamese'),
            'edit_url_base' => admin_url('post.php?action=edit&post='),
        ));
    }

    public function ajax_create_session()
    {
        $this->verify_request();
        $pipeline = new AzEvent_Workflow_Lab_Pipeline();
        $result = $pipeline->create_session(array(
            'keyword' => wp_unslash($_POST['keyword'] ?? ''),
            'secondary_keywords' => wp_unslash($_POST['secondary_keywords'] ?? ''),
            'audience' => wp_unslash($_POST['audience'] ?? ''),
            'competitor_notes' => wp_unslash($_POST['competitor_notes'] ?? ''),
            'generate_image' => sanitize_text_field(wp_unslash($_POST['generate_image'] ?? '0')) === '1',
        ), get_current_user_id());

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        wp_send_json_success($result);
    }

    public function ajax_process_step()
    {
        $this->verify_request();
        ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$this->can_access_session($post_id)) {
            wp_send_json_error(array('message' => 'Không tìm thấy phiên hoặc bạn không có quyền truy cập.'), 403);
        }

        $context = get_post_meta($post_id, AzEvent_Workflow_Lab_Pipeline::SESSION_META, true);
        if (!is_array($context)) {
            wp_send_json_error(array('message' => 'Checkpoint SEO Workflow Lab không hợp lệ.'));
        }
        $edits = json_decode(wp_unslash($_POST['edits'] ?? '{}'), true);
        $edits = is_array($edits) ? $edits : array();
        $pipeline = new AzEvent_Workflow_Lab_Pipeline();
        $result = $pipeline->process_step(
            $post_id,
            sanitize_key(wp_unslash($_POST['step'] ?? '')),
            $context,
            $edits,
            sanitize_text_field(wp_unslash($_POST['skip_image'] ?? '0')) === '1'
        );

        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'post_id' => $post_id,
                'context' => is_array($error_data) && isset($error_data['context']) ? $error_data['context'] : $context,
            ));
        }
        wp_send_json_success($result);
    }

    public function ajax_get_session()
    {
        $this->verify_request();
        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$this->can_access_session($post_id)) {
            wp_send_json_error(array('message' => 'Không tìm thấy phiên hoặc bạn không có quyền truy cập.'), 404);
        }
        $context = get_post_meta($post_id, AzEvent_Workflow_Lab_Pipeline::SESSION_META, true);
        if (!is_array($context)) {
            wp_send_json_error(array('message' => 'Checkpoint SEO Workflow Lab không hợp lệ.'));
        }
        wp_send_json_success(array(
            'post_id' => $post_id,
            'context' => $context,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
        ));
    }

    private function verify_request()
    {
        check_ajax_referer('azevent_workflow_lab', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Bạn không có quyền sử dụng SEO Workflow Lab.'), 403);
        }
    }

    private function can_access_session($post_id)
    {
        if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
            return false;
        }
        $owner_id = absint(get_post_meta($post_id, '_azevent_seo_workflow_lab_owner', true));
        return current_user_can('manage_options') || $owner_id === get_current_user_id();
    }
}

new AzEvent_SEO_Workflow_Lab();
