<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('edit_posts')) {
    wp_die(esc_html__('Bạn không có quyền xem Background Queue.', 'azevent-seo-content'));
}
?>
<div class="wrap azevent-queue-page">
    <style>
        .azevent-queue-page { width: auto; max-width: none; margin: 24px 24px 0 2px; color: #0f172a; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .azevent-queue-page * { box-sizing: border-box; }
        .azq-hero { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; padding: 24px 26px; border: 1px solid #1e3a8a; border-radius: 18px; background: radial-gradient(circle at 86% 8%, rgba(129,140,248,.34), transparent 26%), linear-gradient(135deg, #0f172a, #172554 58%, #312e81); color: #fff; box-shadow: 0 16px 38px rgba(15,23,42,.18); }
        .azq-eyebrow { display: block; margin-bottom: 7px; color: #c7d2fe; font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .azq-hero h1 { margin: 0; color: #fff; font-size: 25px; line-height: 1.2; }
        .azq-hero p { max-width: 760px; margin: 9px 0 0; color: #dbeafe; font-size: 13px; line-height: 1.6; }
        .azq-hero-actions { display: flex; gap: 8px; flex: 0 0 auto; }
        .azq-hero .button { display: inline-flex; align-items: center; min-height: 39px; border-color: rgba(255,255,255,.35); border-radius: 9px; background: rgba(255,255,255,.12); color: #fff; font-weight: 700; }
        .azq-hero .button-primary { border-color: #fff; background: #fff; color: #3730a3; }
        .azq-stats { display: grid; grid-template-columns: repeat(5, minmax(0,1fr)); gap: 12px; margin: 16px 0; }
        .azq-stat { padding: 16px 17px; border: 1px solid #dbe4f3; border-radius: 13px; background: #fff; box-shadow: 0 5px 16px rgba(15,23,42,.04); }
        .azq-stat strong, .azq-stat span { display: block; }
        .azq-stat strong { color: #172554; font-size: 25px; line-height: 1; }
        .azq-stat span { margin-top: 7px; color: #64748b; font-size: 11px; font-weight: 700; }
        .azq-card { overflow: hidden; border: 1px solid #dbe4f3; border-radius: 15px; background: #fff; box-shadow: 0 10px 28px rgba(15,23,42,.06); }
        .azq-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 16px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .azq-filters { display: flex; flex-wrap: wrap; gap: 7px; }
        .azq-filter { min-height: 34px; padding: 0 12px; border: 1px solid #dbe4f3; border-radius: 999px; background: #fff; color: #475569; cursor: pointer; font-size: 11px; font-weight: 700; }
        .azq-filter.is-active { border-color: #6366f1; background: #eef2ff; color: #4338ca; box-shadow: 0 0 0 2px rgba(99,102,241,.1); }
        .azq-refresh { min-height: 36px; border-radius: 8px !important; font-weight: 700 !important; }
        .azq-notice { margin: 14px 16px 0; padding: 11px 13px; border: 1px solid #bbf7d0; border-radius: 9px; background: #f0fdf4; color: #166534; }
        .azq-notice.is-error { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
        .azq-table-wrap { overflow: auto; min-height: 360px; }
        .azq-table { width: 100%; border: 0; border-collapse: collapse; }
        .azq-table th { padding: 12px 14px; border-bottom: 1px solid #e2e8f0; background: #fff; color: #64748b; text-align: left; font-size: 10px; letter-spacing: .05em; text-transform: uppercase; }
        .azq-table td { padding: 13px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .azq-table tr:hover td { background: #fafbff; }
        .azq-keyword { display: block; max-width: 460px; color: #172033; font-size: 13px; font-weight: 750; }
        a.azq-keyword { color: #4338ca; cursor: pointer; text-decoration: underline; text-decoration-color: transparent; text-underline-offset: 2px; transition: color .15s ease, text-decoration-color .15s ease; }
        a.azq-keyword:hover { color: #312e81; text-decoration-color: currentColor; }
        a.azq-keyword:focus-visible { border-radius: 3px; outline: 2px solid #6366f1; outline-offset: 3px; }
        .azq-type { display: inline-block; margin-top: 5px; padding: 3px 7px; border-radius: 999px; background: #eef2ff; color: #4f46e5; font-size: 9px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .azq-error { display: block; max-width: 520px; margin-top: 5px; color: #b91c1c; font-size: 11px; line-height: 1.45; }
        .azq-status { display: inline-flex; padding: 5px 9px; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: 10px; font-weight: 800; white-space: nowrap; }
        .azq-status.is-pending { background: #fff7ed; color: #9a3412; }
        .azq-status.is-processing, .azq-status.is-paused { background: #eef2ff; color: #4338ca; }
        .azq-status.is-completed { background: #dcfce7; color: #166534; }
        .azq-status.is-failed { background: #fee2e2; color: #991b1b; }
        .azq-actions { display: flex; flex-wrap: wrap; gap: 6px; min-width: 150px; }
        .azq-actions .button { min-height: 32px; border-radius: 7px; font-size: 11px; font-weight: 700; }
        .azq-empty { padding: 60px 20px; color: #64748b; text-align: center; }
        .azq-empty .dashicons { width: 42px; height: 42px; color: #a5b4fc; font-size: 42px; }
        .azq-empty strong { display: block; margin-top: 10px; color: #334155; font-size: 15px; }
        .azq-empty p { margin: 6px 0 0; }
        @media (max-width: 900px) { .azq-hero, .azq-toolbar { align-items: stretch; flex-direction: column; } .azq-stats { grid-template-columns: repeat(2,minmax(0,1fr)); } .azq-hero-actions { flex-wrap: wrap; } }
        @media (prefers-reduced-motion: reduce) { .azq-table tr:hover td { transition: none; } }
    </style>

    <header class="azq-hero">
        <div>
            <span class="azq-eyebrow"><?php _e('AzEvent AI SEO', 'azevent-seo-content'); ?></span>
            <h1><?php _e('Background Queue', 'azevent-seo-content'); ?></h1>
            <p><?php _e('Theo dõi Job tự động và các phiên Content Studio đã lưu. Item “Chờ tiếp tục” có thể mở lại đúng bài, đúng bước và giữ nguyên checkpoint.', 'azevent-seo-content'); ?></p>
        </div>
        <div class="azq-hero-actions">
            <a class="button" href="<?php echo esc_url(admin_url('post-new.php')); ?>"><?php _e('＋ Tạo bài mới', 'azevent-seo-content'); ?></a>
            <button type="button" class="button button-primary" id="azq-refresh"><?php _e('↻ Làm mới', 'azevent-seo-content'); ?></button>
        </div>
    </header>

    <section class="azq-stats" aria-label="<?php esc_attr_e('Thống kê hàng đợi', 'azevent-seo-content'); ?>">
        <div class="azq-stat"><strong id="azq-count-pending">0</strong><span><?php _e('Đang chờ', 'azevent-seo-content'); ?></span></div>
        <div class="azq-stat"><strong id="azq-count-processing">0</strong><span><?php _e('Đang chạy', 'azevent-seo-content'); ?></span></div>
        <div class="azq-stat"><strong id="azq-count-paused">0</strong><span><?php _e('Chờ tiếp tục', 'azevent-seo-content'); ?></span></div>
        <div class="azq-stat"><strong id="azq-count-completed">0</strong><span><?php _e('Hoàn tất', 'azevent-seo-content'); ?></span></div>
        <div class="azq-stat"><strong id="azq-count-failed">0</strong><span><?php _e('Lỗi', 'azevent-seo-content'); ?></span></div>
    </section>

    <section class="azq-card">
        <div class="azq-toolbar">
            <div class="azq-filters" role="group" aria-label="<?php esc_attr_e('Lọc Job', 'azevent-seo-content'); ?>">
                <button type="button" class="azq-filter is-active" data-filter="all"><?php _e('Tất cả', 'azevent-seo-content'); ?></button>
                <button type="button" class="azq-filter" data-filter="browser"><?php _e('Content Studio', 'azevent-seo-content'); ?></button>
                <button type="button" class="azq-filter" data-filter="background"><?php _e('Tự động', 'azevent-seo-content'); ?></button>
                <button type="button" class="azq-filter" data-filter="paused"><?php _e('Chờ tiếp tục', 'azevent-seo-content'); ?></button>
                <button type="button" class="azq-filter" data-filter="failed"><?php _e('Lỗi', 'azevent-seo-content'); ?></button>
            </div>
            <span id="azq-updated" aria-live="polite"></span>
        </div>
        <div id="azq-notice" class="azq-notice" hidden></div>
        <div class="azq-table-wrap">
            <table class="azq-table">
                <thead><tr><th><?php _e('Nội dung', 'azevent-seo-content'); ?></th><th><?php _e('Trạng thái', 'azevent-seo-content'); ?></th><th><?php _e('Bước', 'azevent-seo-content'); ?></th><th><?php _e('Cập nhật', 'azevent-seo-content'); ?></th><th><?php _e('Thao tác', 'azevent-seo-content'); ?></th></tr></thead>
                <tbody id="azq-rows"></tbody>
            </table>
            <div id="azq-empty" class="azq-empty" hidden><span class="dashicons dashicons-list-view"></span><strong><?php _e('Chưa có Job phù hợp', 'azevent-seo-content'); ?></strong><p><?php _e('Tạo nội dung mới hoặc thêm từ khóa vào Background Queue để bắt đầu.', 'azevent-seo-content'); ?></p></div>
        </div>
    </section>

    <script>
        (function () {
            var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var nonce = <?php echo wp_json_encode(wp_create_nonce('azevent_seo_nonce')); ?>;
            var jobs = [];
            var activeFilter = 'all';
            var rows = document.getElementById('azq-rows');
            var empty = document.getElementById('azq-empty');
            var notice = document.getElementById('azq-notice');
            var statusLabels = { pending: 'Đang chờ', processing: 'Đang chạy', paused: 'Chờ tiếp tục', completed: 'Hoàn tất', failed: 'Lỗi' };
            var stepLabels = { start: 'Search Intent', search_intent: 'Search Intent', outline: 'Outline', content: 'Content', seo: 'SEO Metadata', section_images: 'Ảnh H2', image: 'Tạo ảnh', finalize: 'Lưu Draft', completed: 'Đã hoàn tất' };

            function request(action, data) {
                var body = new URLSearchParams(Object.assign({ action: action, nonce: nonce }, data || {}));
                return fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() }).then(function (response) { return response.json(); });
            }
            function setNotice(message, error) { notice.hidden = !message; notice.className = 'azq-notice' + (error ? ' is-error' : ''); notice.textContent = message || ''; }
            function addButton(container, label, href, className, jobId) { var element = href ? document.createElement('a') : document.createElement('button'); element.className = 'button ' + (className || ''); element.textContent = label; if (href) element.href = href; else { element.type = 'button'; element.dataset.jobId = jobId; } container.appendChild(element); }
            function getPrimaryDestination(job) {
                if (job.workflow_type === 'browser' && job.resume_url && job.status !== 'completed') {
                    return { url: job.resume_url, label: job.status === 'processing' || job.auto_background ? 'Mở tiến trình' : 'Tiếp tục', primary: true };
                }
                if (job.status === 'completed' && job.post_url) {
                    return { url: job.post_url, label: 'Mở Draft', primary: false };
                }
                return null;
            }
            function visible(job) { return activeFilter === 'all' || job.workflow_type === activeFilter || job.status === activeFilter; }
            function render() {
                rows.innerHTML = '';
                var filtered = jobs.filter(visible);
                empty.hidden = filtered.length > 0;
                filtered.forEach(function (job) {
                    var row = document.createElement('tr');
                    var content = document.createElement('td');
                    var destination = getPrimaryDestination(job);
                    var keyword = document.createElement(destination ? 'a' : 'span'); keyword.className = 'azq-keyword'; keyword.textContent = job.keyword;
                    if (destination) { keyword.href = destination.url; keyword.title = destination.label; keyword.setAttribute('aria-label', destination.label + ': ' + job.keyword); }
                    content.appendChild(keyword);
                    var type = document.createElement('span'); type.className = 'azq-type'; type.textContent = job.workflow_type === 'browser' ? 'Content Studio' : 'Tự động'; content.appendChild(type);
                    if (job.error) { var error = document.createElement('span'); error.className = 'azq-error'; error.textContent = job.error; content.appendChild(error); }
                    var statusCell = document.createElement('td'); var status = document.createElement('span'); status.className = 'azq-status is-' + job.status; status.textContent = job.auto_background && job.status === 'paused' ? 'Đang chạy nền' : (statusLabels[job.status] || job.status); statusCell.appendChild(status);
                    var actions = document.createElement('td'); actions.className = 'azq-actions';
                    if (destination) addButton(actions, destination.label, destination.url, destination.primary ? 'button-primary' : '');
                    else if (job.status === 'failed') addButton(actions, 'Thử lại', '', 'azq-retry', job.id);
                    if (job.status !== 'processing') addButton(actions, 'Xóa', '', 'azq-delete', job.id);
                    row.appendChild(content); row.appendChild(statusCell);
                    var step = document.createElement('td'); step.textContent = job.section_progress || stepLabels[job.step] || job.step; row.appendChild(step);
                    var updated = document.createElement('td'); updated.textContent = job.updated_at || job.created_at || ''; row.appendChild(updated); row.appendChild(actions); rows.appendChild(row);
                });
            }
            function load(silent) {
                request('azevent_get_background_jobs').then(function (response) {
                    if (!response.success) throw new Error(response.data && response.data.message ? response.data.message : 'Không thể tải queue.');
                    jobs = response.data.jobs || []; var counts = response.data.counts || {};
                    ['pending','processing','paused','completed','failed'].forEach(function (status) { document.getElementById('azq-count-' + status).textContent = counts[status] || 0; });
                    document.getElementById('azq-updated').textContent = 'Cập nhật ' + new Date().toLocaleTimeString(); render(); if (!silent) setNotice('', false);
                }).catch(function (error) { if (!silent) setNotice(error.message, true); });
            }
            document.querySelectorAll('.azq-filter').forEach(function (button) { button.addEventListener('click', function () { document.querySelectorAll('.azq-filter').forEach(function (item) { item.classList.toggle('is-active', item === button); }); activeFilter = button.dataset.filter; render(); }); });
            document.getElementById('azq-refresh').addEventListener('click', function () { load(false); });
            rows.addEventListener('click', function (event) {
                var button = event.target.closest('button[data-job-id]'); if (!button) return;
                var jobId = button.dataset.jobId;
                if (button.classList.contains('azq-delete') && !window.confirm('Xóa Job này khỏi Background Queue?')) return;
                button.disabled = true;
                request(button.classList.contains('azq-delete') ? 'azevent_delete_background_job' : 'azevent_retry_background_job', { job_id: jobId }).then(function (response) { setNotice(response.data && response.data.message ? response.data.message : 'Đã cập nhật Job.', !response.success); load(true); }).catch(function () { setNotice('Không thể cập nhật Job.', true); button.disabled = false; });
            });
            load(false); window.setInterval(function () { load(true); }, 10000);
        }());
    </script>
</div>
