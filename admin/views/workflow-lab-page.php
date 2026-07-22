<?php
if (!defined('ABSPATH')) {
    exit;
}

$recent_query = new WP_Query(array(
    'post_type' => 'post',
    'post_status' => array('draft', 'pending', 'private'),
    'posts_per_page' => 12,
    'author' => current_user_can('manage_options') ? 0 : get_current_user_id(),
    'meta_key' => AzEvent_Workflow_Lab_Pipeline::SESSION_META,
    'orderby' => 'modified',
    'order' => 'DESC',
    'no_found_rows' => true,
));
?>
<div class="wrap azlab-page">
    <header class="azlab-hero">
        <div>
            <span class="azlab-eyebrow"><?php _e('AzEvent AI SEO · Experimental', 'azevent-seo-content'); ?></span>
            <h1><?php _e('SEO Workflow Lab', 'azevent-seo-content'); ?></h1>
            <p><?php _e('Quy trình thử nghiệm độc lập với Content Studio. Mỗi bước đều có xem trước, quay lại và checkpoint lưu theo Draft.', 'azevent-seo-content'); ?></p>
        </div>
        <div class="azlab-hero-badges">
            <span><?php _e('Không thay đổi Content Studio', 'azevent-seo-content'); ?></span>
            <span><?php echo esc_html(get_option('azevent_seo_default_language', 'Vietnamese')); ?></span>
        </div>
    </header>

    <div id="azlab-notice" class="azlab-notice" hidden></div>

    <main class="azlab-layout">
        <section class="azlab-main-card">
            <div id="azlab-setup" class="azlab-panel">
                <div class="azlab-panel-heading">
                    <span class="azlab-kicker"><?php _e('Phiên thử nghiệm mới', 'azevent-seo-content'); ?></span>
                    <h2><?php _e('Chuẩn bị đầu vào', 'azevent-seo-content'); ?></h2>
                    <p><?php _e('Lab xử lý một từ khóa mỗi phiên để bạn kiểm tra kỹ chất lượng từng bước.', 'azevent-seo-content'); ?></p>
                </div>

                <div class="azlab-form-grid">
                    <label class="azlab-field azlab-field-wide">
                        <span><?php _e('Từ khóa chính', 'azevent-seo-content'); ?> <b>*</b></span>
                        <input id="azlab-keyword" type="text" placeholder="Ví dụ: công ty tổ chức sự kiện chuyên nghiệp">
                    </label>
                    <label class="azlab-field">
                        <span><?php _e('Từ khóa phụ', 'azevent-seo-content'); ?></span>
                        <textarea id="azlab-secondary" rows="5" placeholder="Mỗi dòng một từ khóa"></textarea>
                    </label>
                    <label class="azlab-field">
                        <span><?php _e('Đối tượng đọc', 'azevent-seo-content'); ?></span>
                        <textarea id="azlab-audience" rows="5" placeholder="Để trống nếu muốn AI đề xuất"></textarea>
                    </label>
                    <label class="azlab-field azlab-field-wide">
                        <span><?php _e('Dữ liệu đối thủ / SERP thực tế', 'azevent-seo-content'); ?></span>
                        <textarea id="azlab-competitors" rows="6" placeholder="Dán dữ liệu bạn đã thu thập hoặc để trống để Lab tự tìm SERP và phân tích đối thủ."></textarea>
                        <small><?php _e('Để trống: Lab dùng SerpApi đã cấu hình. Có dữ liệu: Lab ưu tiên nội dung bạn nhập và không tốn lượt SERP API.', 'azevent-seo-content'); ?></small>
                    </label>
                </div>

                <label class="azlab-toggle">
                    <input id="azlab-generate-image" type="checkbox" checked>
                    <span><strong><?php _e('Tạo ảnh đại diện ở bước cuối', 'azevent-seo-content'); ?></strong><small><?php _e('Có thể bỏ qua ảnh nếu API ảnh chưa sẵn sàng.', 'azevent-seo-content'); ?></small></span>
                </label>

                <div class="azlab-actions azlab-actions-end">
                    <button id="azlab-start" type="button" class="button button-primary azlab-primary"><?php _e('Tạo phiên & chạy Research', 'azevent-seo-content'); ?> <span class="dashicons dashicons-arrow-right-alt2"></span></button>
                </div>
            </div>

            <div id="azlab-workflow" class="azlab-panel" hidden>
                <ol class="azlab-stepper" aria-label="SEO Workflow Lab steps">
                    <li data-step="research"><span>1</span><small>Research</small></li>
                    <li data-step="brief"><span>2</span><small>Brief & Outline</small></li>
                    <li data-step="content"><span>3</span><small>Content</small></li>
                    <li data-step="seo"><span>4</span><small>SEO</small></li>
                    <li data-step="quality"><span>5</span><small><?php _e('Links & QA', 'azevent-seo-content'); ?></small></li>
                    <li data-step="finalize"><span>6</span><small><?php _e('Ảnh & Draft', 'azevent-seo-content'); ?></small></li>
                </ol>

                <div id="azlab-processing" class="azlab-processing" hidden>
                    <span class="azlab-spinner"></span>
                    <div><strong id="azlab-processing-title"><?php _e('AI đang xử lý...', 'azevent-seo-content'); ?></strong><p><?php _e('Checkpoint trước bước hiện tại đã được lưu. Nếu request lỗi, bạn có thể mở lại phiên và chạy lại bước này.', 'azevent-seo-content'); ?></p><small id="azlab-processing-elapsed"><?php _e('Đã chạy 0 giây', 'azevent-seo-content'); ?></small></div>
                </div>

                <div id="azlab-review" class="azlab-review" hidden>
                    <div class="azlab-review-heading">
                        <div><span id="azlab-step-kicker" class="azlab-kicker"></span><h2 id="azlab-step-title"></h2><p id="azlab-step-description"></p></div>
                        <span id="azlab-status-badge" class="azlab-status-badge"><?php _e('Đã lưu checkpoint', 'azevent-seo-content'); ?></span>
                    </div>

                    <div id="azlab-text-editor" class="azlab-result" hidden>
                        <textarea id="azlab-text-result" rows="28"></textarea>
                        <div id="azlab-serp-sources" class="azlab-serp-sources" hidden>
                            <div class="azlab-serp-heading"><strong><?php _e('Nguồn SERP đã phân tích', 'azevent-seo-content'); ?></strong><span id="azlab-serp-meta"></span></div>
                            <ol id="azlab-serp-list"></ol>
                        </div>
                    </div>

                    <div id="azlab-content-editor" class="azlab-result" hidden>
                        <div class="azlab-result-tabs">
                            <button type="button" data-content-tab="preview" class="is-active"><?php _e('Xem trước', 'azevent-seo-content'); ?></button>
                            <button type="button" data-content-tab="html"><?php _e('Chỉnh HTML', 'azevent-seo-content'); ?></button>
                        </div>
                        <iframe id="azlab-content-preview" sandbox="allow-same-origin" title="<?php esc_attr_e('Xem trước nội dung bài viết', 'azevent-seo-content'); ?>"></iframe>
                        <textarea id="azlab-content-html" rows="30" hidden></textarea>
                    </div>

                    <div id="azlab-seo-editor" class="azlab-result" hidden>
                        <div class="azlab-form-grid">
                            <label class="azlab-field azlab-field-wide"><span>SEO Title</span><input id="azlab-seo-title" type="text"></label>
                            <label class="azlab-field"><span>Slug</span><input id="azlab-seo-slug" type="text"></label>
                            <label class="azlab-field"><span>Focus Keyword</span><input id="azlab-seo-focus" type="text"></label>
                            <label class="azlab-field azlab-field-wide"><span>Meta Description</span><textarea id="azlab-seo-meta" rows="4"></textarea></label>
                            <label class="azlab-field azlab-field-wide"><span>Image Prompt</span><textarea id="azlab-seo-image" rows="5"></textarea></label>
                        </div>
                    </div>

                    <div id="azlab-quality-result" class="azlab-result" hidden>
                        <div class="azlab-quality-summary">
                            <div class="azlab-score"><strong id="azlab-quality-score">0</strong><span>/100</span></div>
                            <div><strong id="azlab-quality-state"></strong><p id="azlab-quality-coverage"></p></div>
                        </div>
                        <div class="azlab-quality-grid">
                            <div><h3><?php _e('Lỗi quan trọng', 'azevent-seo-content'); ?></h3><ul id="azlab-critical-list"></ul></div>
                            <div><h3><?php _e('Cảnh báo', 'azevent-seo-content'); ?></h3><ul id="azlab-warning-list"></ul></div>
                            <div><h3><?php _e('Internal link đã dùng', 'azevent-seo-content'); ?></h3><ul id="azlab-link-list"></ul></div>
                        </div>
                        <div class="azlab-preview-shell"><iframe id="azlab-quality-preview" sandbox="allow-same-origin" title="<?php esc_attr_e('Xem trước nội dung sau QA', 'azevent-seo-content'); ?>"></iframe></div>
                    </div>

                    <div id="azlab-final-result" class="azlab-result azlab-final-result" hidden>
                        <div class="azlab-complete-icon"><span class="dashicons dashicons-yes-alt"></span></div>
                        <h2><?php _e('Draft đã sẵn sàng', 'azevent-seo-content'); ?></h2>
                        <p><?php _e('Nội dung, SEO metadata, Rank Math và ảnh đại diện (nếu chọn) đã được lưu.', 'azevent-seo-content'); ?></p>
                        <figure id="azlab-featured-image-card" class="azlab-featured-image-card" hidden>
                            <img id="azlab-featured-image" src="" alt="">
                            <figcaption><?php _e('Ảnh đại diện đã tạo và gắn vào bài Draft', 'azevent-seo-content'); ?></figcaption>
                        </figure>
                        <p id="azlab-featured-image-empty" class="azlab-featured-image-empty" hidden><?php _e('Phiên này đã lưu Draft không có ảnh đại diện.', 'azevent-seo-content'); ?></p>
                        <div id="azlab-section-images-result" class="azevent-section-images-result" hidden></div>
                        <div class="azlab-final-result-actions">
                            <button id="azlab-regenerate-image" type="button" class="button" hidden><span class="dashicons dashicons-update"></span> <?php _e('Tạo lại ảnh đại diện', 'azevent-seo-content'); ?></button>
                            <a id="azlab-edit-post" class="button button-primary azlab-primary" href="#"><?php _e('Mở Draft để kiểm tra', 'azevent-seo-content'); ?></a>
                        </div>
                    </div>
                    <div id="azlab-final-confirmation" class="azlab-result" hidden>
                        <div class="azlab-quality-summary"><div><strong><?php _e('Đã sẵn sàng lưu Draft', 'azevent-seo-content'); ?></strong><p><?php _e('Nội dung chỉ được ghi vào bài sau khi bạn duyệt bước này.', 'azevent-seo-content'); ?></p></div></div>
                    </div>

                    <div id="azlab-review-actions" class="azlab-actions azlab-review-actions">
                        <button id="azlab-back" type="button" class="button"><span class="dashicons dashicons-arrow-left-alt2"></span> <?php _e('Xem bước trước', 'azevent-seo-content'); ?></button>
                        <div>
                            <button id="azlab-rerun" type="button" class="button"><?php _e('Chạy lại bước này', 'azevent-seo-content'); ?></button>
                            <button id="azlab-next" type="button" class="button button-primary azlab-primary"><?php _e('Tiếp tục', 'azevent-seo-content'); ?> <span class="dashicons dashicons-arrow-right-alt2"></span></button>
                        </div>
                    </div>
                    <div id="azlab-final-actions" class="azlab-actions azlab-review-actions" hidden>
                        <button id="azlab-back-quality" type="button" class="button"><span class="dashicons dashicons-arrow-left-alt2"></span> <?php _e('Xem lại QA', 'azevent-seo-content'); ?></button>
                        <div>
                            <button id="azlab-save-no-image" type="button" class="button"><?php _e('Lưu Draft không ảnh', 'azevent-seo-content'); ?></button>
                            <button id="azlab-finalize" type="button" class="button button-primary azlab-primary"><?php _e('Duyệt, tạo ảnh & lưu Draft', 'azevent-seo-content'); ?></button>
                        </div>
                    </div>
                </div>

                <section id="azlab-log-panel" class="azlab-log-panel" hidden>
                    <div class="azlab-log-heading">
                        <div><span class="azlab-kicker"><?php _e('Chẩn đoán', 'azevent-seo-content'); ?></span><h3><?php _e('Nhật ký phiên', 'azevent-seo-content'); ?></h3></div>
                        <button id="azlab-copy-log" type="button" class="button"><span class="dashicons dashicons-clipboard"></span> <?php _e('Sao chép log', 'azevent-seo-content'); ?></button>
                    </div>
                    <p><?php _e('Log hiển thị bước, model, provider, thời gian và lỗi API; không chứa API key.', 'azevent-seo-content'); ?></p>
                    <pre id="azlab-log-output" aria-live="polite"></pre>
                </section>

                <section id="azlab-metrics-panel" class="azlab-metrics-panel" hidden>
                    <div class="azlab-log-heading">
                        <div><span class="azlab-kicker"><?php _e('Usage Report', 'azevent-seo-content'); ?></span><h3><?php _e('Token & thời gian xử lý', 'azevent-seo-content'); ?></h3></div>
                        <span id="azlab-metrics-note" class="azlab-metrics-note"></span>
                    </div>
                    <div class="azlab-metrics-summary">
                        <div><span><?php _e('Tổng token', 'azevent-seo-content'); ?></span><strong id="azlab-total-tokens">0</strong></div>
                        <div><span><?php _e('Input token', 'azevent-seo-content'); ?></span><strong id="azlab-input-tokens">0</strong></div>
                        <div><span><?php _e('Output token', 'azevent-seo-content'); ?></span><strong id="azlab-output-tokens">0</strong></div>
                        <div><span><?php _e('Tổng thời gian', 'azevent-seo-content'); ?></span><strong id="azlab-total-duration">0 giây</strong></div>
                    </div>
                    <div class="azlab-metrics-table-wrap">
                        <table class="azlab-metrics-table">
                            <thead><tr><th><?php _e('Bước', 'azevent-seo-content'); ?></th><th><?php _e('Model', 'azevent-seo-content'); ?></th><th><?php _e('Lượt', 'azevent-seo-content'); ?></th><th><?php _e('Input', 'azevent-seo-content'); ?></th><th><?php _e('Output', 'azevent-seo-content'); ?></th><th><?php _e('Tổng token', 'azevent-seo-content'); ?></th><th><?php _e('AI / toàn bước', 'azevent-seo-content'); ?></th></tr></thead>
                            <tbody id="azlab-metrics-body"></tbody>
                        </table>
                    </div>
                </section>
            </div>
        </section>

        <aside class="azlab-sidebar">
            <div class="azlab-side-card">
                <div class="azlab-side-heading"><div><span class="azlab-kicker"><?php _e('Checkpoint', 'azevent-seo-content'); ?></span><h2><?php _e('Phiên gần đây', 'azevent-seo-content'); ?></h2></div><span class="dashicons dashicons-backup"></span></div>
                <div class="azlab-session-list">
                    <?php if ($recent_query->have_posts()) : ?>
                        <?php foreach ($recent_query->posts as $recent_post) : ?>
                            <?php $session = get_post_meta($recent_post->ID, AzEvent_Workflow_Lab_Pipeline::SESSION_META, true); ?>
                            <?php if (!is_array($session)) { continue; } ?>
                            <article class="azlab-session-item" data-session-id="<?php echo esc_attr($recent_post->ID); ?>">
                                <a href="<?php echo esc_url(add_query_arg('azevent_lab_post', $recent_post->ID, admin_url('admin.php?page=azevent-seo-workflow-lab'))); ?>">
                                    <strong><?php echo esc_html($session['input']['keyword'] ?? get_the_title($recent_post)); ?></strong>
                                    <span><?php echo esc_html(($session['status'] ?? 'paused') === 'completed' ? 'Đã hoàn tất' : 'Đã lưu: ' . ($session['last_completed_step'] ?? 'setup')); ?></span>
                                    <small><?php echo esc_html(get_the_modified_date('d/m/Y H:i', $recent_post)); ?></small>
                                </a>
                                <button type="button" class="azlab-delete-session" data-session-id="<?php echo esc_attr($recent_post->ID); ?>" aria-label="<?php esc_attr_e('Xoá phiên', 'azevent-seo-content'); ?>" title="<?php esc_attr_e('Xoá phiên, giữ nguyên bài Draft', 'azevent-seo-content'); ?>"><span class="dashicons dashicons-trash"></span></button>
                            </article>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class="azlab-empty"><span class="dashicons dashicons-media-document"></span><p><?php _e('Chưa có phiên SEO Workflow Lab.', 'azevent-seo-content'); ?></p></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="azlab-side-card azlab-safety-card">
                <span class="dashicons dashicons-shield-alt"></span>
                <h3><?php _e('Nguyên tắc của Lab', 'azevent-seo-content'); ?></h3>
                <ul>
                    <li><?php _e('Không tự nhận định đối thủ đang top.', 'azevent-seo-content'); ?></li>
                    <li><?php _e('Internal link chỉ lấy từ bài Published thật.', 'azevent-seo-content'); ?></li>
                    <li><?php _e('Quality Gate chạy trước khi ghi nội dung.', 'azevent-seo-content'); ?></li>
                    <li><?php _e('Mọi bước đều lưu checkpoint vào Draft.', 'azevent-seo-content'); ?></li>
                </ul>
            </div>
        </aside>
    </main>
</div>
