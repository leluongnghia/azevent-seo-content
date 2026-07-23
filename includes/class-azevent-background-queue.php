<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_Background_Queue
{
    const SCHEMA_VERSION = '1.1';
    const CRON_HOOK = 'azevent_process_background_queue';
    const LOCK_OPTION = 'azevent_seo_queue_worker_lock';

    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'azevent_seo_jobs';

        $this->maybe_install();
        add_action('wp_ajax_azevent_enqueue_background_jobs', array($this, 'enqueue_jobs_ajax'));
        add_action('wp_ajax_azevent_get_background_jobs', array($this, 'get_jobs_ajax'));
        add_action('wp_ajax_azevent_retry_background_job', array($this, 'retry_job_ajax'));
        add_action('wp_ajax_azevent_delete_background_job', array($this, 'delete_job_ajax'));
        add_action('wp_ajax_azevent_background_worker', array($this, 'worker_ajax'));
        add_action('wp_ajax_nopriv_azevent_background_worker', array($this, 'worker_ajax'));
        add_action(self::CRON_HOOK, array($this, 'process_queue'));
    }

    public static function install()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table_name = $wpdb->prefix . 'azevent_seo_jobs';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            session_id varchar(64) NULL,
            workflow_type varchar(20) NOT NULL DEFAULT 'background',
            content_mode varchar(20) NOT NULL DEFAULT 'create',
            request_id varchar(64) NULL,
            keyword varchar(255) NOT NULL,
            language varchar(50) NOT NULL DEFAULT 'Vietnamese',
            status varchar(20) NOT NULL DEFAULT 'pending',
            step varchar(30) NOT NULL DEFAULT 'start',
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            context longtext NULL,
            error text NULL,
            attempts smallint(5) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            started_at datetime NULL,
            completed_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_id (session_id),
            KEY status_created (status, created_at),
            KEY workflow_status (workflow_type, status, created_at),
            KEY user_created (user_id, created_at)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option('azevent_seo_queue_schema_version', self::SCHEMA_VERSION, false);

        if ((string) get_option('azevent_seo_queue_worker_token', '') === '') {
            update_option('azevent_seo_queue_worker_token', wp_generate_password(48, false, false), false);
        }
    }

    public function enqueue_jobs_ajax()
    {
        check_ajax_referer('azevent_seo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Bạn không có quyền tạo hàng đợi.'), 403);
        }

        $raw_keywords = isset($_POST['keywords']) ? (array) wp_unslash($_POST['keywords']) : array();
        $keywords = array();
        $seen = array();

        foreach ($raw_keywords as $raw_keyword) {
            $keyword = sanitize_text_field($raw_keyword);
            $key = function_exists('mb_strtolower') ? mb_strtolower($keyword) : strtolower($keyword);
            if ($keyword === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $keywords[] = $keyword;
            if (count($keywords) >= 100) {
                break;
            }
        }

        if (!$keywords) {
            wp_send_json_error(array('message' => 'Vui lòng nhập ít nhất một từ khóa.'));
        }

        global $wpdb;
        $now = current_time('mysql');
        $language = sanitize_text_field(get_option('azevent_seo_default_language', 'Vietnamese'));
        $user_id = get_current_user_id();
        $geo_enabled = sanitize_text_field(wp_unslash($_POST['optimize_ai_overview_geo'] ?? '0')) === '1';
        $inserted = 0;

        foreach ($keywords as $keyword) {
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'workflow_type' => 'background',
                    'content_mode' => 'create',
                    'keyword' => $keyword,
                    'language' => $language,
                    'status' => 'pending',
                    'step' => 'start',
                    'context' => wp_json_encode(array(
                        'optimize_ai_overview_geo' => $geo_enabled,
                    )),
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            if ($result !== false) {
                $inserted++;
            }
        }

        if ($inserted <= 0) {
            wp_send_json_error(array('message' => 'Không thể thêm từ khóa vào hàng đợi.'));
        }

        $this->dispatch_worker();
        wp_send_json_success(array(
            'message' => "Đã thêm {$inserted} từ khóa vào Background Queue.",
            'count' => $inserted,
        ));
    }

    public function get_jobs_ajax()
    {
        check_ajax_referer('azevent_seo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Bạn không có quyền xem hàng đợi.'), 403);
        }

        global $wpdb;
        $user_id = get_current_user_id();
        if (current_user_can('manage_options')) {
            $jobs = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY id DESC LIMIT 100", ARRAY_A);
        } else {
            $jobs = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY id DESC LIMIT 100", $user_id),
                ARRAY_A
            );
        }

        $counts = array(
            'pending' => 0,
            'processing' => 0,
            'paused' => 0,
            'completed' => 0,
            'failed' => 0,
        );

        foreach ($jobs as &$job) {
            if (isset($counts[$job['status']])) {
                $counts[$job['status']]++;
            }
            $job_context = json_decode((string) $job['context'], true);
            $split_state = is_array($job_context) && isset($job_context['content_split']) && is_array($job_context['content_split'])
                ? $job_context['content_split']
                : array();
            $job['section_progress'] = '';
            if ($job['step'] === 'content' && !empty($split_state['enabled']) && empty($split_state['completed'])) {
                $sections = array_values($split_state['sections'] ?? array());
                $section_index = max(0, absint($split_state['current_index'] ?? 0));
                if (isset($sections[$section_index])) {
                    $job['section_progress'] = sprintf(
                        'H2 %1$d/%2$d: %3$s',
                        $section_index + 1,
                        count($sections),
                        sanitize_text_field($sections[$section_index]['title'] ?? '')
                    );
                }
            }
            $section_images = is_array($job_context) && isset($job_context['section_images']) && is_array($job_context['section_images'])
                ? $job_context['section_images']
                : array();
            $job['auto_background'] = !empty($job_context['background_delivery_approved'])
                && in_array($job['step'], array('section_images', 'image', 'finalize'), true);
            if ($job['step'] === 'section_images' && !empty($section_images['items'])) {
                $image_index = max(0, absint($section_images['current_index'] ?? 0));
                $image_items = array_values($section_images['items']);
                if (isset($image_items[$image_index])) {
                    $job['section_progress'] = sprintf(
                        'Ảnh H2 %1$d/%2$d: %3$s',
                        $image_index + 1,
                        count($image_items),
                        sanitize_text_field($image_items[$image_index]['title'] ?? '')
                    );
                }
            }
            unset($job['context']);
            $job['post_url'] = $job['post_id'] > 0
                ? admin_url('post.php?post=' . absint($job['post_id']) . '&action=edit')
                : '';
            $job['resume_url'] = $job['workflow_type'] === 'browser' && in_array($job['status'], array('paused', 'failed', 'processing'), true)
                ? self::get_resume_url($job)
                : '';
        }
        unset($job);

        if ($this->has_pending_jobs() || $this->has_auto_background_jobs()) {
            $this->dispatch_worker();
        }

        wp_send_json_success(array(
            'jobs' => $jobs,
            'counts' => $counts,
        ));
    }

    public function retry_job_ajax()
    {
        check_ajax_referer('azevent_seo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Bạn không có quyền thử lại Job.'), 403);
        }

        global $wpdb;
        $job_id = absint($_POST['job_id'] ?? 0);
        $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $job_id), ARRAY_A);
        if (!$job || ($job['user_id'] != get_current_user_id() && !current_user_can('manage_options'))) {
            wp_send_json_error(array('message' => 'Không tìm thấy Job.'));
        }

        if ($job['status'] !== 'failed') {
            wp_send_json_error(array('message' => 'Chỉ có thể thử lại Job đang lỗi.'));
        }

        if (($job['workflow_type'] ?? 'background') === 'browser') {
            wp_send_json_error(array(
                'message' => 'Job Content Studio cần được mở để tiếp tục đúng bước đang dở.',
                'resume_url' => self::get_resume_url($job),
            ));
        }

        $wpdb->update(
            $this->table_name,
            array(
                'status' => 'pending',
                'error' => null,
                'attempts' => 0,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $job_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );

        $this->dispatch_worker();
        wp_send_json_success(array('message' => 'Đã đưa Job trở lại hàng đợi.'));
    }

    public function delete_job_ajax()
    {
        check_ajax_referer('azevent_seo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Bạn không có quyền xóa Job.'), 403);
        }

        global $wpdb;
        $job_id = absint($_POST['job_id'] ?? 0);
        $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $job_id), ARRAY_A);
        if (!$job || ($job['user_id'] != get_current_user_id() && !current_user_can('manage_options'))) {
            wp_send_json_error(array('message' => 'Không tìm thấy Job.'));
        }
        if ($job['status'] === 'processing') {
            wp_send_json_error(array('message' => 'Không thể xóa Job đang xử lý. Hãy chờ bước hiện tại kết thúc.'));
        }

        $wpdb->delete($this->table_name, array('id' => $job_id), array('%d'));
        wp_send_json_success(array('message' => 'Đã xóa Job khỏi Background Queue.'));
    }

    public function worker_ajax()
    {
        $provided_token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
        $stored_token = (string) get_option('azevent_seo_queue_worker_token', '');
        if ($stored_token === '' || !hash_equals($stored_token, $provided_token)) {
            status_header(403);
            wp_die('Invalid worker token.');
        }

        $this->process_queue();
        wp_die('OK');
    }

    public function process_queue()
    {
        if (!$this->acquire_lock()) {
            $this->schedule_cron_fallback(20);
            return;
        }

        $has_more = false;
        $dispatch_delay = 15;

        try {
            global $wpdb;
            $stale_before = gmdate('Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS + (get_option('gmt_offset') * HOUR_IN_SECONDS));
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->table_name} SET status = 'pending', updated_at = %s WHERE workflow_type = 'background' AND status = 'processing' AND updated_at < %s",
                    current_time('mysql'),
                    $stale_before
                )
            );
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->table_name} SET status = 'failed', error = %s, request_id = NULL, updated_at = %s WHERE workflow_type = 'browser' AND status = 'processing' AND updated_at < %s",
                    'Phiên trình duyệt đã dừng quá lâu. Mở Job để thử lại đúng bước đang dở.',
                    current_time('mysql'),
                    $stale_before
                )
            );

            $job = $wpdb->get_row(
                "SELECT * FROM {$this->table_name} WHERE workflow_type = 'background' AND status = 'pending' ORDER BY id ASC LIMIT 1",
                ARRAY_A
            );

            if (!$job) {
                $resume_before = gmdate('Y-m-d H:i:s', current_time('timestamp') - 20);
                $browser_jobs = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$this->table_name} WHERE workflow_type = 'browser' AND status = 'paused' AND step IN ('section_images','image','finalize') AND updated_at <= %s ORDER BY updated_at ASC LIMIT 10",
                        $resume_before
                    ),
                    ARRAY_A
                );
                foreach ((array) $browser_jobs as $browser_job) {
                    $browser_context = json_decode((string) $browser_job['context'], true);
                    if (is_array($browser_context) && !empty($browser_context['background_delivery_approved'])) {
                        $job = $browser_job;
                        break;
                    }
                }
                if (!$job) {
                    return;
                }
            }

            $now = current_time('mysql');
            $claimed = $wpdb->update(
                $this->table_name,
                array(
                    'status' => 'processing',
                    'attempts' => absint($job['attempts']) + 1,
                    'started_at' => $job['started_at'] ?: $now,
                    'updated_at' => $now,
                ),
                array('id' => $job['id'], 'status' => $job['status']),
                array('%s', '%d', '%s', '%s'),
                array('%d', '%s')
            );
            if (!$claimed) {
                $has_more = true;
                return;
            }

            $context = json_decode((string) $job['context'], true);
            $context = is_array($context) ? $context : array();
            $job_post_id = absint($job['post_id']);

            if ($job_post_id <= 0) {
                $job_post_id = wp_insert_post(array(
                    'post_type' => 'post',
                    'post_status' => 'draft',
                    'post_title' => $job['keyword'],
                    'post_author' => absint($job['user_id']),
                ), true);

                if (!is_wp_error($job_post_id)) {
                    $wpdb->update(
                        $this->table_name,
                        array('post_id' => absint($job_post_id), 'updated_at' => current_time('mysql')),
                        array('id' => $job['id']),
                        array('%d', '%s'),
                        array('%d')
                    );
                }
            }

            if (is_wp_error($job_post_id)) {
                $result = $job_post_id;
            } else {
                $pipeline = new AzEvent_Content_Pipeline();
                $result = $pipeline->process_step(array(
                    'keyword' => $job['keyword'],
                    'language' => $job['language'],
                    'post_id' => absint($job_post_id),
                    'step' => $job['step'],
                    'mode' => in_array(($job['content_mode'] ?? ''), array('create', 'rewrite'), true) ? $job['content_mode'] : 'create',
                    'regenerate_image' => array_key_exists('regenerate_image', $context) ? !empty($context['regenerate_image']) : true,
                    'context' => $context,
                    'author_id' => absint($job['user_id']),
                ));
            }

            if (is_wp_error($result)) {
                $attempts = absint($job['attempts']) + 1;
                $status = $attempts >= 3
                    ? 'failed'
                    : (($job['workflow_type'] ?? 'background') === 'browser' ? 'paused' : 'pending');
                $error_data = $result->get_error_data();
                $error_context = isset($error_data['context']) && is_array($error_data['context'])
                    ? $error_data['context']
                    : $context;
                $wpdb->update(
                    $this->table_name,
                    array(
                        'status' => $status,
                        'error' => $result->get_error_message(),
                        'context' => wp_json_encode($error_context),
                        'attempts' => $attempts,
                        'updated_at' => current_time('mysql'),
                    ),
                    array('id' => $job['id']),
                    array('%s', '%s', '%s', '%d', '%s'),
                    array('%d')
                );
                $has_more = in_array($status, array('pending', 'paused'), true) || $this->has_pending_jobs();
                if ($status === 'paused') {
                    $dispatch_delay = 20;
                }
            } elseif ($result['status'] === 'completed') {
                $wpdb->update(
                    $this->table_name,
                    array(
                        'status' => 'completed',
                        'step' => 'completed',
                        'post_id' => absint($result['post_id']),
                        'context' => null,
                        'error' => null,
                        'attempts' => 0,
                        'updated_at' => current_time('mysql'),
                        'completed_at' => current_time('mysql'),
                    ),
                    array('id' => $job['id']),
                    array('%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s'),
                    array('%d')
                );
                $has_more = $this->has_pending_jobs() || $this->has_auto_background_jobs();
            } else {
                $next_status = ($job['workflow_type'] ?? 'background') === 'browser' ? 'paused' : 'pending';
                $wpdb->update(
                    $this->table_name,
                    array(
                        'status' => $next_status,
                        'step' => sanitize_key($result['next_step']),
                        'post_id' => absint($result['post_id']),
                        'context' => wp_json_encode($result['context']),
                        'error' => null,
                        'attempts' => 0,
                        'updated_at' => current_time('mysql'),
                    ),
                    array('id' => $job['id']),
                    array('%s', '%s', '%d', '%s', '%s', '%d', '%s'),
                    array('%d')
                );
                $has_more = true;
                if ($next_status === 'paused') {
                    $dispatch_delay = 20;
                }
            }
        } finally {
            $this->release_lock();
        }

        if ($has_more) {
            $this->dispatch_worker($dispatch_delay);
        }
    }

    public static function save_browser_checkpoint($session_id, array $checkpoint, $user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'azevent_seo_jobs';
        $session_id = substr(sanitize_key($session_id), 0, 64);
        $user_id = absint($user_id);
        if ($session_id === '' || $user_id <= 0) {
            return 0;
        }

        $existing_id = absint($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE session_id = %s AND user_id = %d LIMIT 1",
            $session_id,
            $user_id
        )));
        $status = sanitize_key($checkpoint['status'] ?? 'paused');
        if (!in_array($status, array('processing', 'paused', 'failed'), true)) {
            $status = 'paused';
        }
        $step = $status === 'paused'
            ? sanitize_key($checkpoint['next_step'] ?? $checkpoint['current_step'] ?? 'start')
            : sanitize_key($checkpoint['current_step'] ?? $checkpoint['next_step'] ?? 'start');
        $data = array(
            'user_id' => $user_id,
            'session_id' => $session_id,
            'workflow_type' => 'browser',
            'content_mode' => in_array(($checkpoint['mode'] ?? ''), array('create', 'rewrite'), true) ? $checkpoint['mode'] : 'create',
            'request_id' => !empty($checkpoint['request_id']) ? substr(sanitize_key($checkpoint['request_id']), 0, 64) : null,
            'keyword' => sanitize_text_field($checkpoint['keyword'] ?? ''),
            'language' => sanitize_text_field($checkpoint['language'] ?? 'Vietnamese'),
            'status' => $status,
            'step' => $step ?: 'start',
            'post_id' => absint($checkpoint['post_id'] ?? 0),
            'context' => wp_json_encode(isset($checkpoint['context']) && is_array($checkpoint['context']) ? $checkpoint['context'] : array()),
            'error' => !empty($checkpoint['error']) ? sanitize_textarea_field($checkpoint['error']) : null,
            'updated_at' => current_time('mysql'),
        );
        $should_continue_background = $status === 'paused'
            && !empty($checkpoint['context']['background_delivery_approved'])
            && in_array($step, array('section_images', 'image', 'finalize'), true);

        if ($existing_id > 0) {
            $wpdb->update($table_name, $data, array('id' => $existing_id));
            if ($should_continue_background) {
                self::dispatch_async(20);
            }
            return $existing_id;
        }

        $data['created_at'] = current_time('mysql');
        $data['started_at'] = $status === 'processing' ? current_time('mysql') : null;
        $inserted = $wpdb->insert($table_name, $data);
        if ($inserted !== false && $should_continue_background) {
            self::dispatch_async(20);
        }
        return $inserted === false ? 0 : absint($wpdb->insert_id);
    }

    public static function complete_browser_checkpoint($session_id, $user_id, $post_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'azevent_seo_jobs';
        $wpdb->update(
            $table_name,
            array(
                'status' => 'completed',
                'step' => 'completed',
                'post_id' => absint($post_id),
                'request_id' => null,
                'context' => null,
                'error' => null,
                'updated_at' => current_time('mysql'),
                'completed_at' => current_time('mysql'),
            ),
            array(
                'session_id' => substr(sanitize_key($session_id), 0, 64),
                'user_id' => absint($user_id),
            )
        );
    }

    public static function get_browser_checkpoint($user_id, $job_id = 0)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'azevent_seo_jobs';
        if ($job_id > 0) {
            $job = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d AND user_id = %d AND workflow_type = 'browser' LIMIT 1",
                absint($job_id),
                absint($user_id)
            ), ARRAY_A);
        } else {
            $job = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE user_id = %d AND workflow_type = 'browser' AND status IN ('processing','paused','failed') ORDER BY updated_at DESC, id DESC LIMIT 1",
                absint($user_id)
            ), ARRAY_A);
        }
        if (!$job) {
            return null;
        }

        if (!in_array($job['status'], array('processing', 'paused', 'failed'), true)) {
            return null;
        }

        $context = json_decode((string) $job['context'], true);
        return array(
            'job_id' => absint($job['id']),
            'session_id' => (string) $job['session_id'],
            'status' => (string) $job['status'],
            'keyword' => (string) $job['keyword'],
            'language' => (string) $job['language'],
            'mode' => (string) $job['content_mode'],
            'post_id' => absint($job['post_id']),
            'current_step' => (string) $job['step'],
            'next_step' => (string) $job['step'],
            'request_id' => (string) $job['request_id'],
            'context' => is_array($context) ? $context : array(),
            'error' => (string) $job['error'],
            'updated_at' => mysql2date('U', $job['updated_at'], false),
        );
    }

    public static function delete_browser_checkpoint($session_id, $user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'azevent_seo_jobs';
        $wpdb->delete(
            $table_name,
            array(
                'session_id' => substr(sanitize_key($session_id), 0, 64),
                'user_id' => absint($user_id),
                'workflow_type' => 'browser',
            )
        );
    }

    public static function get_resume_url(array $job)
    {
        $job_id = absint($job['id'] ?? 0);
        $post_id = absint($job['post_id'] ?? 0);
        $base_url = $post_id > 0
            ? admin_url('post.php?post=' . $post_id . '&action=edit')
            : admin_url('post-new.php');
        return add_query_arg('azevent_resume_job', $job_id, $base_url);
    }

    private function maybe_install()
    {
        if ((string) get_option('azevent_seo_queue_schema_version', '') !== self::SCHEMA_VERSION) {
            self::install();
        }
    }

    private function has_pending_jobs()
    {
        global $wpdb;
        return (bool) $wpdb->get_var("SELECT id FROM {$this->table_name} WHERE workflow_type = 'background' AND status = 'pending' LIMIT 1");
    }

    private function has_auto_background_jobs($minimum_age = 20)
    {
        global $wpdb;
        $resume_before = gmdate('Y-m-d H:i:s', current_time('timestamp') - max(0, absint($minimum_age)));
        $jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT context FROM {$this->table_name} WHERE workflow_type = 'browser' AND status = 'paused' AND step IN ('section_images','image','finalize') AND updated_at <= %s ORDER BY updated_at ASC LIMIT 20",
                $resume_before
            ),
            ARRAY_A
        );
        foreach ((array) $jobs as $job) {
            $context = json_decode((string) ($job['context'] ?? ''), true);
            if (is_array($context) && !empty($context['background_delivery_approved'])) {
                return true;
            }
        }
        return false;
    }

    private function acquire_lock()
    {
        $now = time();
        if (add_option(self::LOCK_OPTION, $now, '', false)) {
            return true;
        }

        $locked_at = absint(get_option(self::LOCK_OPTION, 0));
        if ($locked_at > 0 && $locked_at < $now - 30 * MINUTE_IN_SECONDS) {
            delete_option(self::LOCK_OPTION);
            return add_option(self::LOCK_OPTION, $now, '', false);
        }

        return false;
    }

    private function release_lock()
    {
        delete_option(self::LOCK_OPTION);
    }

    private function dispatch_worker($delay = 15)
    {
        self::dispatch_async($delay);
    }

    private static function dispatch_async($delay = 15)
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time() + max(1, absint($delay)), self::CRON_HOOK);
        }
        wp_remote_post(admin_url('admin-ajax.php'), array(
            'timeout' => 0.5,
            'blocking' => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body' => array(
                'action' => 'azevent_background_worker',
                'token' => get_option('azevent_seo_queue_worker_token', ''),
            ),
        ));
    }

    private function schedule_cron_fallback($delay)
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time() + max(1, absint($delay)), self::CRON_HOOK);
        }
    }
}
