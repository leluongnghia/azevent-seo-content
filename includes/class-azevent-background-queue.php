<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_Background_Queue
{
    const SCHEMA_VERSION = '1.0';
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
            KEY status_created (status, created_at),
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
        $inserted = 0;

        foreach ($keywords as $keyword) {
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'keyword' => $keyword,
                    'language' => $language,
                    'status' => 'pending',
                    'step' => 'start',
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
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
            'completed' => 0,
            'failed' => 0,
        );

        foreach ($jobs as &$job) {
            if (isset($counts[$job['status']])) {
                $counts[$job['status']]++;
            }
            unset($job['context']);
            $job['post_url'] = $job['post_id'] > 0
                ? admin_url('post.php?post=' . absint($job['post_id']) . '&action=edit')
                : '';
        }
        unset($job);

        if ($this->has_pending_jobs()) {
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

        try {
            global $wpdb;
            $stale_before = gmdate('Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS + (get_option('gmt_offset') * HOUR_IN_SECONDS));
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->table_name} SET status = 'pending', updated_at = %s WHERE status = 'processing' AND updated_at < %s",
                    current_time('mysql'),
                    $stale_before
                )
            );

            $job = $wpdb->get_row(
                "SELECT * FROM {$this->table_name} WHERE status = 'pending' ORDER BY id ASC LIMIT 1",
                ARRAY_A
            );

            if (!$job) {
                return;
            }

            $now = current_time('mysql');
            $wpdb->update(
                $this->table_name,
                array(
                    'status' => 'processing',
                    'attempts' => absint($job['attempts']) + 1,
                    'started_at' => $job['started_at'] ?: $now,
                    'updated_at' => $now,
                ),
                array('id' => $job['id']),
                array('%s', '%d', '%s', '%s'),
                array('%d')
            );

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
                    'mode' => 'create',
                    'regenerate_image' => true,
                    'context' => $context,
                    'author_id' => absint($job['user_id']),
                ));
            }

            if (is_wp_error($result)) {
                $attempts = absint($job['attempts']) + 1;
                $status = $attempts >= 3 ? 'failed' : 'pending';
                $wpdb->update(
                    $this->table_name,
                    array(
                        'status' => $status,
                        'error' => $result->get_error_message(),
                        'attempts' => $attempts,
                        'updated_at' => current_time('mysql'),
                    ),
                    array('id' => $job['id']),
                    array('%s', '%s', '%d', '%s'),
                    array('%d')
                );
                $has_more = $status === 'pending' || $this->has_pending_jobs();
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
                $has_more = $this->has_pending_jobs();
            } else {
                $wpdb->update(
                    $this->table_name,
                    array(
                        'status' => 'pending',
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
            }
        } finally {
            $this->release_lock();
        }

        if ($has_more) {
            $this->dispatch_worker();
        }
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
        return (bool) $wpdb->get_var("SELECT id FROM {$this->table_name} WHERE status = 'pending' LIMIT 1");
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

    private function dispatch_worker()
    {
        $this->schedule_cron_fallback(15);
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
