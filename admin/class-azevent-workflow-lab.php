<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_SEO_Workflow_Lab
{
    const CRON_HOOK = 'azevent_lab_process_background_step';
    const WORKER_TOKEN_OPTION = 'azevent_lab_worker_token';

    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_azevent_lab_create_session', array($this, 'ajax_create_session'));
        add_action('wp_ajax_azevent_lab_process_step', array($this, 'ajax_process_step'));
        add_action('wp_ajax_azevent_lab_get_session', array($this, 'ajax_get_session'));
        add_action('wp_ajax_azevent_lab_delete_session', array($this, 'ajax_delete_session'));
        add_action('wp_ajax_azevent_lab_worker', array($this, 'ajax_background_worker'));
        add_action('wp_ajax_nopriv_azevent_lab_worker', array($this, 'ajax_background_worker'));
        add_action(self::CRON_HOOK, array($this, 'process_background_step'), 10, 2);

        if ((string) get_option(self::WORKER_TOKEN_OPTION, '') === '') {
            update_option(self::WORKER_TOKEN_OPTION, $this->generate_worker_token(), false);
        }
    }

    private function generate_worker_token()
    {
        try {
            return bin2hex(random_bytes(24));
        } catch (Exception $exception) {
            return hash('sha256', wp_generate_uuid4() . microtime(true));
        }
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
            'section_image_nonce' => wp_create_nonce('azevent_section_image'),
            'post_id' => absint($_GET['azevent_lab_post'] ?? 0),
            'default_language' => get_option('azevent_seo_default_language', 'Vietnamese'),
            'outline_validation_enabled' => absint(get_option('azevent_lab_validate_outline', 0)) === 1,
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
            'optimize_ai_overview_geo' => sanitize_text_field(wp_unslash($_POST['optimize_ai_overview_geo'] ?? '0')) === '1',
        ), get_current_user_id());

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        wp_send_json_success($result);
    }

    public function ajax_process_step()
    {
        $this->verify_request();

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
        $result = $pipeline->queue_step(
            $post_id,
            sanitize_key(wp_unslash($_POST['step'] ?? '')),
            $context,
            $edits,
            sanitize_text_field(wp_unslash($_POST['skip_image'] ?? '0')) === '1'
        );

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'post_id' => $post_id,
                'context' => $context,
            ));
        }
        $this->dispatch_background_step($post_id, $result['job_id']);
        $result['queued'] = true;
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
        $context_changed = false;
        $normalized_context = AzEvent_Workflow_Lab_Pipeline::normalize_outline_validation_context($context);
        if ($normalized_context !== $context) {
            $context = $normalized_context;
            $context_changed = true;
        }
        if (($context['status'] ?? '') === 'completed' && empty($context['featured_image'])) {
            $attachment_id = get_post_thumbnail_id($post_id);
            if ($attachment_id) {
                $context['image_status'] = 'created';
                $context['featured_image'] = array(
                    'id' => absint($attachment_id),
                    'url' => esc_url_raw(wp_get_attachment_image_url($attachment_id, 'large')),
                    'full_url' => esc_url_raw(wp_get_attachment_image_url($attachment_id, 'full')),
                    'alt' => sanitize_text_field(get_post_meta($attachment_id, '_wp_attachment_image_alt', true)),
                );
                $context_changed = true;
            }
        }
        if (($context['status'] ?? '') === 'completed') {
            $section_images = get_post_meta($post_id, AzEvent_Section_Images::META_KEY, true);
            if (is_array($section_images) && !empty($section_images['items'])) {
                $context['section_images'] = $section_images;
                $context_changed = true;
            }
        }
        if ($context_changed) {
            update_post_meta($post_id, AzEvent_Workflow_Lab_Pipeline::SESSION_META, $context);
            $context_changed = false;
        }
        $pending = isset($context['pending_job']) && is_array($context['pending_job']) ? $context['pending_job'] : array();
        if (($context['status'] ?? '') === 'queued' && !empty($pending['id'])) {
            $this->dispatch_background_step($post_id, $pending['id']);
        } elseif (($context['status'] ?? '') === 'processing' && !empty($pending['id']) && absint($context['updated_at'] ?? 0) < time() - 90 * MINUTE_IN_SECONDS) {
            $context['status'] = 'failed';
            $context['error'] = 'Job nền không cập nhật quá 90 phút và đã được đánh dấu thất bại. Bạn có thể chạy lại bước này.';
            if (!isset($context['logs']) || !is_array($context['logs'])) {
                $context['logs'] = array();
            }
            $context['logs'][] = array(
                'timestamp' => time(),
                'level' => 'error',
                'step' => sanitize_key($pending['step'] ?? 'system'),
                'message' => $context['error'],
            );
            unset($context['pending_job']);
            $context['updated_at'] = time();
            $context_changed = true;
        }
        if ($context_changed) {
            update_post_meta($post_id, AzEvent_Workflow_Lab_Pipeline::SESSION_META, $context);
        }
        wp_send_json_success(array(
            'post_id' => $post_id,
            'context' => $context,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
        ));
    }

    public function ajax_delete_session()
    {
        $this->verify_request();
        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$this->can_access_session($post_id)) {
            wp_send_json_error(array('message' => 'Không tìm thấy phiên hoặc bạn không có quyền xoá.'), 404);
        }

        $context = get_post_meta($post_id, AzEvent_Workflow_Lab_Pipeline::SESSION_META, true);
        if (is_array($context) && in_array(($context['status'] ?? ''), array('queued', 'processing'), true)) {
            wp_send_json_error(array('message' => 'Phiên đang xử lý. Hãy chờ bước hiện tại hoàn tất hoặc thất bại rồi xoá phiên.'), 409);
        }

        delete_post_meta($post_id, AzEvent_Workflow_Lab_Pipeline::SESSION_META);
        delete_post_meta($post_id, AzEvent_Workflow_Lab_Pipeline::SERP_META);
        delete_post_meta($post_id, '_azevent_seo_workflow_lab_owner');

        wp_send_json_success(array(
            'post_id' => $post_id,
            'message' => 'Đã xoá dữ liệu phiên Workflow Lab. Bài Draft liên quan vẫn được giữ trong Posts.',
        ));
    }

    public function ajax_background_worker()
    {
        $provided_token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
        $stored_token = (string) get_option(self::WORKER_TOKEN_OPTION, '');
        if ($stored_token === '' || !hash_equals($stored_token, $provided_token)) {
            status_header(403);
            wp_die('Invalid worker token.');
        }

        $this->process_background_step(
            absint($_POST['post_id'] ?? 0),
            sanitize_text_field(wp_unslash($_POST['job_id'] ?? ''))
        );
        wp_die('OK');
    }

    public function process_background_step($post_id, $job_id)
    {
        $post_id = absint($post_id);
        $job_id = sanitize_text_field($job_id);
        if ($post_id <= 0 || $job_id === '' || !$this->acquire_job_lock($job_id)) {
            return;
        }

        ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        try {
            $context = get_post_meta($post_id, AzEvent_Workflow_Lab_Pipeline::SESSION_META, true);
            $pending = is_array($context) && isset($context['pending_job']) && is_array($context['pending_job'])
                ? $context['pending_job']
                : array();
            if (($context['status'] ?? '') !== 'queued' || ($pending['id'] ?? '') !== $job_id) {
                return;
            }

            $pipeline = new AzEvent_Workflow_Lab_Pipeline();
            $result = $pipeline->process_step(
                $post_id,
                sanitize_key($pending['step'] ?? ''),
                $context,
                array(),
                !empty($pending['skip_image'])
            );
            if (!is_wp_error($result) && ($result['status'] ?? '') === 'queued') {
                $next_context = isset($result['context']) && is_array($result['context']) ? $result['context'] : array();
                $next_job = isset($next_context['pending_job']) && is_array($next_context['pending_job'])
                    ? $next_context['pending_job']
                    : array();
                if (!empty($next_job['id'])) {
                    $this->dispatch_background_step($post_id, $next_job['id']);
                }
            }
        } finally {
            wp_clear_scheduled_hook(self::CRON_HOOK, array($post_id, $job_id));
            $this->release_job_lock($job_id);
        }
    }

    private function dispatch_background_step($post_id, $job_id)
    {
        $args = array(absint($post_id), sanitize_text_field($job_id));
        if (!wp_next_scheduled(self::CRON_HOOK, $args)) {
            wp_schedule_single_event(time() + 15, self::CRON_HOOK, $args);
        }
        $dispatch_key = 'azevent_lab_dispatch_' . md5((string) $job_id);
        if (get_transient($dispatch_key)) {
            return;
        }
        set_transient($dispatch_key, 1, 10);
        wp_remote_post(admin_url('admin-ajax.php'), array(
            'timeout' => 0.5,
            'blocking' => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body' => array(
                'action' => 'azevent_lab_worker',
                'token' => get_option(self::WORKER_TOKEN_OPTION, ''),
                'post_id' => absint($post_id),
                'job_id' => sanitize_text_field($job_id),
            ),
        ));
    }

    private function acquire_job_lock($job_id)
    {
        $option = 'azevent_lab_lock_' . md5((string) $job_id);
        $now = time();
        if (add_option($option, $now, '', false)) {
            return true;
        }
        $locked_at = absint(get_option($option, 0));
        if ($locked_at > 0 && $locked_at < $now - 30 * MINUTE_IN_SECONDS) {
            delete_option($option);
            return add_option($option, $now, '', false);
        }
        return false;
    }

    private function release_job_lock($job_id)
    {
        delete_option('azevent_lab_lock_' . md5((string) $job_id));
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
