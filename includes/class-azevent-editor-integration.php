<?php
/**
 * Editor Integration for AzEvent SEO Content Creator.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_Editor_Integration
{

    public static function get_default_prompts()
    {
        return require AZEVENT_SEO_PATH . 'includes/class-azevent-prompt-templates.php';
    }

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_seo_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_azevent_generate_content', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_azevent_get_browser_step_status', array($this, 'ajax_get_browser_step_status'));
        add_action('wp_ajax_azevent_get_browser_checkpoint', array($this, 'ajax_get_browser_checkpoint'));
        add_action('wp_ajax_azevent_clear_browser_checkpoint', array($this, 'ajax_clear_browser_checkpoint'));
        add_action('wp_ajax_azevent_regenerate_section_image', array($this, 'ajax_regenerate_section_image'));
    }

    /**
     * Add Meta Box to the side of the post editor.
     */
    public function add_seo_meta_box()
    {
        add_meta_box(
            'azevent_seo_meta_box',
            __('AzEvent AI SEO Content', 'azevent-seo-content'),
            array(__CLASS__, 'render_meta_box'),
            'post',
            'side',
            'high'
        );
    }

    /**
     * Render Meta Box content.
     */
    public static function render_meta_box($post, $standalone = false)
    {
        $is_existing_post = !$standalone && $post->ID > 0 && $post->post_status !== 'auto-draft';
        $default_mode = $is_existing_post ? 'rewrite' : 'create';
        $default_language = get_option('azevent_seo_default_language', 'Vietnamese');
        $language_labels = array(
            'Vietnamese' => __('Tiếng Việt', 'azevent-seo-content'),
            'English' => __('English', 'azevent-seo-content'),
        );
        $default_language_label = isset($language_labels[$default_language])
            ? $language_labels[$default_language]
            : $default_language;
        ?>
        <div class="azevent-launch-card">
            <span class="azevent-launch-eyebrow"><?php _e('AzEvent Content Studio', 'azevent-seo-content'); ?></span>
            <strong><?php _e('Tạo bài SEO theo quy trình AI', 'azevent-seo-content'); ?></strong>
            <p><?php _e('Chọn chế độ và từ khóa trong cửa sổ làm việc tập trung.', 'azevent-seo-content'); ?></p>
            <div class="azevent-launch-actions">
                <button type="button" id="azevent-open-studio" class="button azevent-launch-button">
                    <span class="dashicons dashicons-edit-page" aria-hidden="true"></span>
                    <?php _e('Mở Content Studio', 'azevent-seo-content'); ?>
                </button>
                <button type="button" id="azevent-open-queue" class="button azevent-queue-button">
                    <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                    <?php _e('Xem Background Queue', 'azevent-seo-content'); ?>
                </button>
            </div>
        </div>

        <div id="azevent-studio-modal" class="azevent-modal" aria-hidden="true">
            <div class="azevent-modal-backdrop" data-azevent-close></div>
            <div class="azevent-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="azevent-modal-title">
                <header class="azevent-modal-header">
                    <div>
                        <span class="azevent-modal-eyebrow"><?php _e('AzEvent AI SEO', 'azevent-seo-content'); ?></span>
                        <h2 id="azevent-modal-title"><?php _e('Content Studio', 'azevent-seo-content'); ?></h2>
                    </div>
                    <button type="button" id="azevent-modal-close" class="azevent-icon-button" aria-label="<?php esc_attr_e('Đóng cửa sổ', 'azevent-seo-content'); ?>" data-azevent-close>
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </header>

                <div class="azevent-modal-body">
                    <ol id="azevent-workflow-stepper" class="azevent-stepper" aria-label="<?php esc_attr_e('Tiến trình tạo nội dung', 'azevent-seo-content'); ?>" hidden>
                        <li data-step="intent"><span>1</span><small>Search Intent</small></li>
                        <li data-step="outline"><span>2</span><small>Outline</small></li>
                        <li data-step="outline_validation"><span>3</span><small><?php _e('Kiểm định', 'azevent-seo-content'); ?></small></li>
                        <li data-step="content"><span>4</span><small>Content</small></li>
                        <li data-step="seo"><span>5</span><small>SEO</small></li>
                        <li data-step="finish"><span>6</span><small><?php _e('Ảnh & Lưu', 'azevent-seo-content'); ?></small></li>
                    </ol>

                    <section id="azevent-setup-view" class="azevent-view">
                        <div class="azevent-view-heading">
                            <span class="azevent-step-kicker"><?php _e('Bước 1', 'azevent-seo-content'); ?></span>
                            <h3><?php _e('Thiết lập nội dung', 'azevent-seo-content'); ?></h3>
                            <p><?php _e('Chọn cách xử lý và nhập từ khóa. Ngôn ngữ được lấy tự động từ phần cài đặt.', 'azevent-seo-content'); ?></p>
                        </div>

                        <div id="azevent-resume-card" class="azevent-resume-card" hidden>
                            <div class="azevent-resume-icon">
                                <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                            </div>
                            <div class="azevent-resume-content">
                                <span class="azevent-resume-eyebrow"><?php _e('Đã tìm thấy checkpoint', 'azevent-seo-content'); ?></span>
                                <strong id="azevent-resume-keyword"></strong>
                                <p><span id="azevent-resume-step"></span><span aria-hidden="true"> · </span><span id="azevent-resume-time"></span></p>
                            </div>
                            <div class="azevent-resume-actions">
                                <button type="button" id="azevent-discard-session" class="button azevent-secondary-button"><?php _e('Bỏ bản lưu', 'azevent-seo-content'); ?></button>
                                <button type="button" id="azevent-resume-session" class="button button-primary azevent-primary-button">
                                    <?php _e('Tiếp tục quy trình', 'azevent-seo-content'); ?>
                                    <span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>

                        <fieldset class="azevent-mode-picker">
                            <legend><?php _e('Chế độ', 'azevent-seo-content'); ?></legend>
                            <label class="azevent-mode-card">
                                <input type="radio" name="azevent_mode" value="create" <?php checked($default_mode, 'create'); ?>>
                                <span class="dashicons dashicons-welcome-write-blog" aria-hidden="true"></span>
                                <span><strong><?php _e('Tạo nội dung mới', 'azevent-seo-content'); ?></strong><small><?php _e('Mỗi dòng tạo một Draft riêng.', 'azevent-seo-content'); ?></small></span>
                            </label>
                            <?php if (!$standalone) : ?>
                                <label class="azevent-mode-card">
                                    <input type="radio" name="azevent_mode" value="rewrite" <?php checked($default_mode, 'rewrite'); ?>>
                                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                                    <span><strong><?php _e('Viết lại bài hiện tại', 'azevent-seo-content'); ?></strong><small><?php _e('Đọc bài đang mở và cải thiện nội dung.', 'azevent-seo-content'); ?></small></span>
                                </label>
                            <?php endif; ?>
                            <label class="azevent-mode-card">
                                <input type="radio" name="azevent_mode" value="background">
                                <span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>
                                <span><strong><?php _e('Background Queue', 'azevent-seo-content'); ?></strong><small><?php _e('Nhiều từ khóa, chạy tuần tự khi đóng tab.', 'azevent-seo-content'); ?></small></span>
                            </label>
                        </fieldset>

                        <div class="azevent-field">
                            <label for="azevent-keywords"><?php _e('Từ khóa chính', 'azevent-seo-content'); ?></label>
                            <textarea id="azevent-keywords" name="azevent_keywords" rows="6"
                                placeholder="<?php esc_attr_e("Mỗi dòng một từ khóa. Ví dụ:\nTổ chức hội nghị doanh nghiệp\nDịch vụ gala dinner", 'azevent-seo-content'); ?>"><?php echo esc_textarea($default_mode === 'rewrite' ? $post->post_title : ''); ?></textarea>
                            <span id="azevent-keyword-help"><?php _e('Tạo mới: mỗi dòng sẽ tạo một Draft riêng.', 'azevent-seo-content'); ?></span>
                        </div>

                        <div class="azevent-field">
                            <label for="azevent-secondary-keywords"><?php _e('Từ khóa phụ', 'azevent-seo-content'); ?></label>
                            <textarea id="azevent-secondary-keywords" name="azevent_secondary_keywords" rows="4"
                                placeholder="<?php esc_attr_e("Mỗi dòng một từ khóa phụ. Danh sách này dùng chung cho các từ khóa chính trong phiên.", 'azevent-seo-content'); ?>"></textarea>
                            <span><?php _e('AI dùng danh sách này ở Search Intent, Outline, bước kiểm định và Content.', 'azevent-seo-content'); ?></span>
                        </div>

                        <div class="azevent-language-summary">
                            <span class="dashicons dashicons-translation" aria-hidden="true"></span>
                            <span><?php _e('Ngôn ngữ đầu ra', 'azevent-seo-content'); ?><strong><?php echo esc_html($default_language_label); ?></strong></span>
                            <a href="<?php echo esc_url(add_query_arg(array('page' => 'azevent-seo-background-queue', 'azevent_open' => 'settings'), admin_url('admin.php'))); ?>"><?php _e('Thay đổi trong cài đặt', 'azevent-seo-content'); ?></a>
                        </div>

                        <label class="azevent-geo-toggle">
                            <input id="azevent-optimize-ai-overview-geo" type="checkbox" value="1" <?php checked(absint(get_option('azevent_geo_content_studio_default_enabled', 0)), 1); ?>>
                            <span>
                                <strong><?php _e('Tối ưu AI Overview/GEO', 'azevent-seo-content'); ?></strong>
                                <small><?php _e('Chỉ khi tích, plugin mới nối bộ ưu tiên GEO riêng vào prompt của phiên hoặc Background Queue. Bỏ chọn sẽ chạy nguyên luồng cũ.', 'azevent-seo-content'); ?></small>
                            </span>
                        </label>

                        <div class="azevent-actions azevent-actions-end">
                            <button type="button" id="azevent-start-btn" class="button button-primary azevent-primary-button">
                                <?php _e('Bắt đầu phân tích', 'azevent-seo-content'); ?>
                                <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                            </button>
                        </div>
                    </section>

                    <section id="azevent-workflow-view" class="azevent-view" hidden>
                        <div id="azevent-processing-panel" class="azevent-processing-panel">
                            <div class="azevent-spinner" aria-hidden="true"></div>
                            <div>
                                <span class="azevent-processing-label"><?php _e('Đang xử lý trên trình duyệt', 'azevent-seo-content'); ?></span>
                                <h3 id="azevent-status-text"><?php _e('Đang chuẩn bị...', 'azevent-seo-content'); ?></h3>
                                <p><?php _e('Có thể đóng modal hoặc tải lại trang; plugin sẽ lưu checkpoint để tiếp tục sau.', 'azevent-seo-content'); ?></p>
                            </div>
                        </div>

                        <div id="azevent-log" class="azevent-log" aria-live="polite"></div>
                        <div id="azevent-error-actions" class="azevent-actions azevent-review-actions" hidden>
                            <button type="button" id="azevent-restart-btn" class="button azevent-secondary-button"><?php _e('Quay lại thiết lập', 'azevent-seo-content'); ?></button>
                            <div class="azevent-review-action-group">
                                <button type="button" id="azevent-error-back-btn" class="button azevent-secondary-button" hidden>
                                    <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                                    <span><?php _e('Xem lại bước trước', 'azevent-seo-content'); ?></span>
                                </button>
                                <button type="button" id="azevent-retry-btn" class="button button-primary azevent-primary-button"><?php _e('Thử lại bước này', 'azevent-seo-content'); ?></button>
                            </div>
                        </div>
                    </section>

                    <section id="azevent-intent-review-view" class="azevent-view" hidden>
                        <div class="azevent-review-header">
                            <div>
                                <span class="azevent-step-kicker"><?php _e('Search Intent hoàn tất', 'azevent-seo-content'); ?></span>
                                <h3><?php _e('Kiểm tra kết quả trước khi tạo Outline', 'azevent-seo-content'); ?></h3>
                                <p><?php _e('Bạn có thể chỉnh trực tiếp kết quả phân tích để định hướng Outline chính xác hơn.', 'azevent-seo-content'); ?></p>
                            </div>
                            <span class="azevent-review-badge"><?php _e('Chờ tiếp tục', 'azevent-seo-content'); ?></span>
                        </div>

                        <div class="azevent-intent-result">
                            <label for="azevent-intent-result-text"><?php _e('Kết quả Search Intent', 'azevent-seo-content'); ?></label>
                            <textarea id="azevent-intent-result-text" rows="20"></textarea>
                        </div>

                        <div class="azevent-intent-note">
                            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                            <span><?php _e('Outline chưa chạy. Chỉ khi bấm Tiếp tục tạo Outline, plugin mới gửi request tiếp theo.', 'azevent-seo-content'); ?></span>
                        </div>

                        <div class="azevent-actions azevent-review-actions">
                            <button type="button" id="azevent-rerun-intent-btn" class="button azevent-secondary-button">
                                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                                <?php _e('Phân tích lại Search Intent', 'azevent-seo-content'); ?>
                            </button>
                            <button type="button" id="azevent-continue-outline-btn" class="button button-primary azevent-primary-button">
                                <?php _e('Tiếp tục tạo Outline', 'azevent-seo-content'); ?>
                                <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                            </button>
                        </div>
                    </section>

                    <section id="azevent-outline-review-view" class="azevent-view" hidden>
                        <div class="azevent-review-header">
                            <div>
                                <span id="azevent-outline-review-kicker" class="azevent-step-kicker"><?php _e('Outline hoàn tất', 'azevent-seo-content'); ?></span>
                                <h3 id="azevent-outline-review-title"><?php _e('Kiểm tra dàn ý trước khi viết Content', 'azevent-seo-content'); ?></h3>
                                <p><?php _e('Bạn có thể chỉnh dàn ý khi Content chưa được tạo. Các nút Quay lại chỉ mở kết quả đã lưu, không gọi lại AI.', 'azevent-seo-content'); ?></p>
                            </div>
                            <span id="azevent-outline-validation-badge" class="azevent-review-badge"><?php _e('Chờ kiểm định', 'azevent-seo-content'); ?></span>
                        </div>

                        <div class="azevent-step-result">
                            <label for="azevent-outline-result-text"><?php _e('Kết quả Outline', 'azevent-seo-content'); ?></label>
                            <textarea id="azevent-outline-result-text" rows="22"></textarea>
                        </div>

                        <div class="azevent-actions azevent-review-actions">
                            <button type="button" id="azevent-back-to-intent-btn" class="button azevent-secondary-button">
                                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                                <?php _e('Xem lại Search Intent', 'azevent-seo-content'); ?>
                            </button>
                            <div class="azevent-review-action-group">
                                <button type="button" id="azevent-rerun-outline-btn" class="button azevent-secondary-button"><?php _e('Tạo lại Outline', 'azevent-seo-content'); ?></button>
                                <button type="button" id="azevent-continue-content-btn" class="button button-primary azevent-primary-button">
                                    <?php _e('Tiếp tục tạo Content', 'azevent-seo-content'); ?>
                                    <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>
                    </section>

                    <section id="azevent-content-review-view" class="azevent-view" hidden>
                        <div class="azevent-review-header">
                            <div>
                                <span class="azevent-step-kicker"><?php _e('Content hoàn tất', 'azevent-seo-content'); ?></span>
                                <h3><?php _e('Đọc toàn bộ nội dung trước khi tạo SEO', 'azevent-seo-content'); ?></h3>
                                <p><?php _e('Bước SEO chưa chạy. Bạn có thể quay lại xem Outline hoặc tạo lại Content trước khi tiếp tục.', 'azevent-seo-content'); ?></p>
                            </div>
                            <span class="azevent-review-badge"><?php _e('Chờ tiếp tục', 'azevent-seo-content'); ?></span>
                        </div>

                        <div class="azevent-preview-shell">
                            <div class="azevent-preview-toolbar"><span><?php _e('Xem trước Content', 'azevent-seo-content'); ?></span></div>
                            <iframe id="azevent-content-review-frame" sandbox="" title="<?php esc_attr_e('Bản xem trước Content AI', 'azevent-seo-content'); ?>"></iframe>
                        </div>

                        <div class="azevent-actions azevent-review-actions">
                            <button type="button" id="azevent-back-to-outline-btn" class="button azevent-secondary-button">
                                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                                <?php _e('Xem lại Outline', 'azevent-seo-content'); ?>
                            </button>
                            <div class="azevent-review-action-group">
                                <button type="button" id="azevent-regenerate-content-btn" class="button azevent-secondary-button"><?php _e('Tạo lại Content', 'azevent-seo-content'); ?></button>
                                <button type="button" id="azevent-continue-seo-btn" class="button button-primary azevent-primary-button">
                                    <?php _e('Tiếp tục tạo SEO', 'azevent-seo-content'); ?>
                                    <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>
                    </section>

                    <section id="azevent-review-view" class="azevent-view" hidden>
                        <div class="azevent-review-header">
                            <div>
                                <span class="azevent-step-kicker"><?php _e('SEO hoàn tất', 'azevent-seo-content'); ?></span>
                                <h3><?php _e('Kiểm tra dữ liệu SEO trước khi tạo ảnh và lưu', 'azevent-seo-content'); ?></h3>
                                <p><?php _e('Đây là bước duyệt cuối. Nội dung chưa được ghi vào Draft cho đến khi bạn bấm duyệt.', 'azevent-seo-content'); ?></p>
                            </div>
                            <span class="azevent-review-badge"><?php _e('Chờ duyệt', 'azevent-seo-content'); ?></span>
                        </div>

                        <div class="azevent-review-meta">
                            <div><span><?php _e('SEO Title', 'azevent-seo-content'); ?></span><strong id="azevent-review-title"></strong></div>
                            <div><span><?php _e('Meta Description', 'azevent-seo-content'); ?></span><p id="azevent-review-meta"></p></div>
                            <div><span><?php _e('Slug', 'azevent-seo-content'); ?></span><p id="azevent-review-slug"></p></div>
                            <div><span><?php _e('Image Prompt', 'azevent-seo-content'); ?></span><p id="azevent-review-image-prompt"></p></div>
                            <div><span><?php _e('Image ALT', 'azevent-seo-content'); ?></span><p id="azevent-review-image-alt"></p></div>
                        </div>

                        <label id="azevent-image-option" class="azevent-check-row">
                            <input type="checkbox" id="azevent-regenerate-image" value="1">
                            <span><strong><?php _e('Tạo ảnh đại diện bằng AI', 'azevent-seo-content'); ?></strong><small><?php _e('Bỏ chọn để giữ ảnh hiện tại khi viết lại.', 'azevent-seo-content'); ?></small></span>
                        </label>

                        <div class="azevent-actions azevent-review-actions">
                            <button type="button" id="azevent-back-to-content-btn" class="button azevent-secondary-button">
                                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                                <?php _e('Xem lại Content', 'azevent-seo-content'); ?>
                            </button>
                            <div class="azevent-review-action-group">
                                <button type="button" id="azevent-regenerate-seo-btn" class="button azevent-secondary-button"><?php _e('Tạo lại SEO', 'azevent-seo-content'); ?></button>
                                <button type="button" id="azevent-approve-btn" class="button button-primary azevent-primary-button">
                                    <?php _e('Duyệt, tạo ảnh và lưu Draft', 'azevent-seo-content'); ?>
                                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>
                    </section>

                    <section id="azevent-complete-view" class="azevent-view azevent-complete-view" hidden>
                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                        <h3><?php _e('Đã hoàn tất', 'azevent-seo-content'); ?></h3>
                        <p id="azevent-complete-message"></p>
                        <div id="azevent-section-images-result" class="azevent-section-images-result" hidden></div>
                        <div class="azevent-actions">
                            <button type="button" class="button azevent-secondary-button" data-azevent-close><?php _e('Đóng', 'azevent-seo-content'); ?></button>
                            <a id="azevent-complete-link" class="button button-primary azevent-primary-button" href="#"><?php _e('Mở bài Draft', 'azevent-seo-content'); ?></a>
                        </div>
                    </section>

                    <section id="azevent-queue-view" class="azevent-view" hidden>
                        <div class="azevent-queue-header">
                            <div>
                                <span class="azevent-step-kicker"><?php _e('Background Queue', 'azevent-seo-content'); ?></span>
                                <h3><?php _e('Hàng đợi tạo bài tự động', 'azevent-seo-content'); ?></h3>
                                <p><?php _e('Mỗi Job xử lý trọn quy trình theo từng từ khóa và tự lưu thành Draft.', 'azevent-seo-content'); ?></p>
                            </div>
                            <button type="button" id="azevent-refresh-queue" class="button azevent-secondary-button">
                                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                                <?php _e('Làm mới', 'azevent-seo-content'); ?>
                            </button>
                        </div>

                        <div class="azevent-queue-stats">
                            <div><span id="azevent-count-pending">0</span><small><?php _e('Đang chờ', 'azevent-seo-content'); ?></small></div>
                            <div><span id="azevent-count-processing">0</span><small><?php _e('Đang chạy', 'azevent-seo-content'); ?></small></div>
                            <div><span id="azevent-count-paused">0</span><small><?php _e('Chờ tiếp tục', 'azevent-seo-content'); ?></small></div>
                            <div><span id="azevent-count-completed">0</span><small><?php _e('Hoàn tất', 'azevent-seo-content'); ?></small></div>
                            <div><span id="azevent-count-failed">0</span><small><?php _e('Lỗi', 'azevent-seo-content'); ?></small></div>
                        </div>

                        <div id="azevent-queue-notice" class="azevent-queue-notice" hidden></div>
                        <div class="azevent-queue-table-wrap">
                            <table class="widefat striped azevent-queue-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('Từ khóa', 'azevent-seo-content'); ?></th>
                                        <th><?php _e('Trạng thái', 'azevent-seo-content'); ?></th>
                                        <th><?php _e('Bước hiện tại', 'azevent-seo-content'); ?></th>
                                        <th><?php _e('Thời gian', 'azevent-seo-content'); ?></th>
                                        <th><?php _e('Thao tác', 'azevent-seo-content'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="azevent-queue-rows"></tbody>
                            </table>
                            <div id="azevent-queue-empty" class="azevent-queue-empty" hidden>
                                <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                                <strong><?php _e('Hàng đợi đang trống', 'azevent-seo-content'); ?></strong>
                                <p><?php _e('Chọn Background Queue và nhập danh sách từ khóa để bắt đầu.', 'azevent-seo-content'); ?></p>
                            </div>
                        </div>

                        <div class="azevent-actions azevent-review-actions">
                            <button type="button" id="azevent-add-queue-jobs" class="button azevent-secondary-button">
                                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                <?php _e('Thêm từ khóa', 'azevent-seo-content'); ?>
                            </button>
                            <p class="azevent-queue-footnote"><?php _e('Có thể đóng tab sau khi Job được thêm vào hàng đợi.', 'azevent-seo-content'); ?></p>
                        </div>
                    </section>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_standalone_page()
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Bạn không có quyền sử dụng Content Studio.', 'azevent-seo-content'));
        }

        $post = (object) array(
            'ID' => 0,
            'post_status' => 'auto-draft',
            'post_title' => '',
        );
        ?>
        <div class="wrap azevent-studio-admin-page">
            <h1><?php _e('AzEvent Content Studio', 'azevent-seo-content'); ?></h1>
            <p class="description"><?php _e('Tạo bài SEO mới, chạy quy trình duyệt từng bước hoặc thêm nhiều từ khóa vào Background Queue.', 'azevent-seo-content'); ?></p>
            <?php self::render_meta_box($post, true); ?>
        </div>
        <?php
    }

    /**
     * Enqueue JS/CSS.
     */
    public function enqueue_assets($hook)
    {
        $is_studio_page = isset($_GET['page'])
            && sanitize_key(wp_unslash($_GET['page'])) === 'azevent-seo-content-studio';
        if ('post-new.php' !== $hook && 'post.php' !== $hook && !$is_studio_page) {
            return;
        }

        wp_enqueue_style('azevent-seo-editor', AZEVENT_SEO_URL . 'admin/css/editor.css', array('dashicons'), AZEVENT_SEO_VERSION);
        wp_enqueue_script('azevent-seo-js', AZEVENT_SEO_URL . 'admin/js/editor.js', array('jquery'), AZEVENT_SEO_VERSION, true);
        wp_localize_script('azevent-seo-js', 'azevent_seo', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('azevent_seo_nonce'),
            'section_image_nonce' => wp_create_nonce('azevent_section_image'),
            'post_id' => $is_studio_page ? 0 : get_the_ID(),
            'default_language' => get_option('azevent_seo_default_language', 'Vietnamese'),
            'auto_advance' => (bool) get_option('azevent_seo_browser_auto_advance', false),
            'resume_job_id' => absint($_GET['azevent_resume_job'] ?? 0),
            'admin_url' => admin_url(),
            'standalone' => $is_studio_page,
        ));
    }

    /**
     * AJAX handler for content generation.
     */
    public function ajax_generate_content()
    {
        check_ajax_referer('azevent_seo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Quyền truy cập bị từ chối.'));
        }

        ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $request_id = sanitize_key(wp_unslash($_POST['request_id'] ?? ''));
        if ($request_id !== '') {
            $this->store_browser_step_status($request_id, array(
                'status' => 'processing',
                'step' => sanitize_key(wp_unslash($_POST['step'] ?? 'start')),
                'updated_at' => time(),
            ));
        }

        $pipeline_arguments = array(
            'keyword' => sanitize_text_field(wp_unslash($_POST['keyword'] ?? '')),
            'language' => sanitize_text_field(wp_unslash($_POST['language'] ?? 'Vietnamese')),
            'post_id' => absint($_POST['post_id'] ?? 0),
            'step' => sanitize_key(wp_unslash($_POST['step'] ?? 'start')),
            'mode' => sanitize_key(wp_unslash($_POST['mode'] ?? 'create')),
            'regenerate_image' => sanitize_text_field(wp_unslash($_POST['regenerate_image'] ?? '0')) === '1',
            'context' => json_decode(wp_unslash($_POST['context'] ?? '{}'), true),
            'author_id' => get_current_user_id(),
        );
        $pipeline_arguments['context'] = is_array($pipeline_arguments['context']) ? $pipeline_arguments['context'] : array();
        $pipeline_arguments['context']['optimize_ai_overview_geo'] = !empty($pipeline_arguments['context']['optimize_ai_overview_geo']);
        $session_id = substr(sanitize_key(wp_unslash($_POST['session_id'] ?? '')), 0, 64);
        $replace_checkpoint = sanitize_text_field(wp_unslash($_POST['replace_checkpoint'] ?? '0')) === '1';

        if ($pipeline_arguments['mode'] === 'rewrite' && $pipeline_arguments['post_id'] > 0 && !current_user_can('edit_post', $pipeline_arguments['post_id'])) {
            wp_send_json_error(array('message' => 'Bạn không có quyền chỉnh sửa bài viết này.'));
        }

        if ($session_id !== '') {
            $this->save_browser_checkpoint($session_id, array(
                'status' => 'processing',
                'keyword' => $pipeline_arguments['keyword'],
                'language' => $pipeline_arguments['language'],
                'mode' => $pipeline_arguments['mode'],
                'post_id' => $pipeline_arguments['post_id'],
                'current_step' => $pipeline_arguments['step'],
                'next_step' => $pipeline_arguments['step'],
                'request_id' => $request_id,
                'context' => $pipeline_arguments['context'],
            ), $replace_checkpoint);
        }

        $pipeline = new AzEvent_Content_Pipeline();
        $pipeline_result = $pipeline->process_step($pipeline_arguments);
        if (is_wp_error($pipeline_result)) {
            $error_data = $pipeline_result->get_error_data();
            $error_response = array(
                'message' => $pipeline_result->get_error_message(),
                'post_id' => isset($error_data['post_id']) ? absint($error_data['post_id']) : 0,
                'context' => isset($error_data['context']) && is_array($error_data['context']) ? $error_data['context'] : array(),
            );
            if ($request_id !== '') {
                $this->store_browser_step_status($request_id, array(
                    'status' => 'failed',
                    'payload' => array('success' => false, 'data' => $error_response),
                    'updated_at' => time(),
                ));
            }
            if ($session_id !== '') {
                $this->save_browser_checkpoint($session_id, array(
                    'status' => 'failed',
                    'keyword' => $pipeline_arguments['keyword'],
                    'language' => $pipeline_arguments['language'],
                    'mode' => $pipeline_arguments['mode'],
                    'post_id' => $error_response['post_id'] ?: $pipeline_arguments['post_id'],
                    'current_step' => $pipeline_arguments['step'],
                    'next_step' => $pipeline_arguments['step'],
                    'request_id' => '',
                    'context' => $error_response['context'] ?: $pipeline_arguments['context'],
                    'error' => $error_response['message'],
                ));
            }
            wp_send_json_error($error_response);
        }

        if ($request_id !== '') {
            $this->store_browser_step_status($request_id, array(
                'status' => 'completed',
                'payload' => array('success' => true, 'data' => $pipeline_result),
                'updated_at' => time(),
            ));
        }

        if ($session_id !== '') {
            if (($pipeline_result['status'] ?? '') === 'completed') {
                $this->complete_browser_checkpoint($session_id, absint($pipeline_result['post_id'] ?? $pipeline_arguments['post_id']));
            } else {
                $completed_step = $pipeline_arguments['step'] === 'start'
                    ? 'search_intent'
                    : $pipeline_arguments['step'];
                $this->save_browser_checkpoint($session_id, array(
                    'status' => 'paused',
                    'keyword' => $pipeline_arguments['keyword'],
                    'language' => $pipeline_arguments['language'],
                    'mode' => $pipeline_arguments['mode'],
                    'post_id' => absint($pipeline_result['post_id'] ?? $pipeline_arguments['post_id']),
                    'completed_step' => $completed_step,
                    'current_step' => $completed_step,
                    'next_step' => sanitize_key($pipeline_result['next_step'] ?? ''),
                    'request_id' => '',
                    'context' => isset($pipeline_result['context']) && is_array($pipeline_result['context'])
                        ? $pipeline_result['context']
                        : $pipeline_arguments['context'],
                ));
            }
        }

        wp_send_json_success($pipeline_result);
        return;

        $keyword = sanitize_text_field(wp_unslash($_POST['keyword'] ?? ''));
        $language = sanitize_text_field(wp_unslash($_POST['language'] ?? 'Vietnamese'));
        $post_id = absint($_POST['post_id'] ?? 0);
        $step = sanitize_text_field(wp_unslash($_POST['step'] ?? 'start'));
        $mode = sanitize_key(wp_unslash($_POST['mode'] ?? 'create'));
        $regenerate_image = sanitize_text_field(wp_unslash($_POST['regenerate_image'] ?? '0')) === '1';
        $context = json_decode(wp_unslash($_POST['context'] ?? '{}'), true);
        $context = is_array($context) ? $context : array();

        if (!in_array($mode, array('create', 'rewrite'), true)) {
            $mode = 'create';
        }

        if (empty($keyword)) {
            wp_send_json_error(array('message' => 'Vui lòng nhập từ khóa.'));
        }

        if ($mode === 'create' && $post_id <= 0 && $step === 'start') {
            $post_id = wp_insert_post(array(
                'post_type' => 'post',
                'post_status' => 'draft',
                'post_title' => $keyword,
                'post_author' => get_current_user_id(),
            ), true);
            if (is_wp_error($post_id)) {
                wp_send_json_error(array('message' => 'Không thể tạo Draft cho từ khóa: ' . $post_id->get_error_message()));
            }
        }

        if ($post_id > 0 && !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Bạn không có quyền chỉnh sửa bài viết này.'));
        }

        if ($post_id <= 0) {
            wp_send_json_error(array('message' => 'Không xác định được bài viết đang xử lý.'));
        }

        $context['post_id'] = $post_id;

        if ($mode === 'rewrite') {
            if ($post_id <= 0) {
                wp_send_json_error(array('message' => 'Cần lưu bài viết trước khi viết lại.'));
            }

            $existing_post = get_post($post_id);
            if (!$existing_post || $existing_post->post_type !== 'post') {
                wp_send_json_error(array('message' => 'Không tìm thấy bài viết cần viết lại.'));
            }

            if ($step === 'start' || empty($context['existing_post'])) {
                $existing_content = (string) $existing_post->post_content;
                if (function_exists('mb_substr')) {
                    $existing_content = mb_substr($existing_content, 0, 30000);
                } else {
                    $existing_content = substr($existing_content, 0, 30000);
                }

                $context['existing_post'] = array(
                    'title' => (string) $existing_post->post_title,
                    'content' => $existing_content,
                    'excerpt' => (string) $existing_post->post_excerpt,
                    'slug' => (string) $existing_post->post_name,
                    'has_thumbnail' => has_post_thumbnail($post_id),
                );
            }
        }

        $ai = new AzEvent_AI_Service();
        $brand_profile = AzEvent_SEO_Content::get_brand_profile();
        $brand_name = $brand_profile['azevent_seo_brand_name'];
        $brand_info = $brand_profile['azevent_seo_brand_info'];
        $brand_solution = $brand_profile['azevent_seo_brand_solution'];

        // Helper to replace placeholders
        $replace_placeholders = function ($text, $ctx) use ($keyword, $language, $brand_name, $brand_info, $brand_solution) {
            $placeholders = array(
                '{keyword}' => $keyword,
                '{secondary_keywords}' => $keyword,
                '{language}' => $language,
                '{outline_focus}' => isset($ctx['outline_focus']) ? $ctx['outline_focus'] : '',
                '{brand_name}' => $brand_name,
                '{brand_info}' => $brand_info,
                '{brand_solution}' => $brand_solution,
                '{search_intent}' => isset($ctx['search_intent']) ? $ctx['search_intent'] : '',
                '{outline}' => isset($ctx['outline']) ? $ctx['outline'] : '',
                '{content}' => isset($ctx['content']) ? $ctx['content'] : '',
                '{existing_title}' => isset($ctx['existing_post']['title']) ? $ctx['existing_post']['title'] : '',
                '{existing_content}' => isset($ctx['existing_post']['content']) ? $ctx['existing_post']['content'] : '',
                '{existing_excerpt}' => isset($ctx['existing_post']['excerpt']) ? $ctx['existing_post']['excerpt'] : '',
                '{existing_slug}' => isset($ctx['existing_post']['slug']) ? $ctx['existing_post']['slug'] : '',
                '{rewrite_goal}' => $mode === 'rewrite' ? 'Viết lại bài hiện tại, giữ thông tin đúng và cải thiện chất lượng SEO.' : 'Tạo nội dung mới từ đầu.',
            );
            return str_replace(array_keys($placeholders), array_values($placeholders), $text);
        };

        $prompt_defaults = self::get_default_prompts();
        $defaults = array(
            'intent_system' => $prompt_defaults['intent']['system'],
            'intent_user' => $prompt_defaults['intent']['user'],
            'outline_system' => $prompt_defaults['outline']['system'],
            'outline_user' => $prompt_defaults['outline']['user'],
            'content_system' => $prompt_defaults['content']['system'],
            'content_user' => $prompt_defaults['content']['user'],
            'seo_system' => $prompt_defaults['seo']['system'],
            'seo_user' => $prompt_defaults['seo']['user'],
        );
        $get_prompt = function ($option, $default) {
            $value = get_option($option, '');
            return trim((string) $value) === '' ? $default : $value;
        };

        $rewrite_instructions = array(
            'intent' => "\n\nChế độ viết lại. Hãy phân tích bài hiện tại và chỉ ra search intent, điểm yếu, thông tin thiếu hoặc lỗi thời. Bài hiện tại có tiêu đề '{existing_title}':\n{existing_content}",
            'outline' => "\n\nChế độ viết lại. Hãy tạo outline cải tiến dựa trên intent và bài hiện tại dưới đây. Giữ lại thông tin đúng, bổ sung phần còn thiếu, tránh lặp lại nội dung cũ không cần thiết.\n{existing_content}",
            'content' => "\n\nChế độ viết lại. Hãy viết lại bài hiện tại dưới đây theo outline mới. Giữ lại thông tin đúng, không tự bịa số liệu hoặc cam kết, cải thiện chiều sâu SEO và khả năng chuyển đổi.\n{existing_content}",
            'seo' => "\n\nĐây là chế độ viết lại. Hãy tạo metadata mới cho nội dung, nhưng giữ slug hiện tại '{existing_slug}' trừ khi có lý do SEO rõ ràng.",
        );

        switch ($step) {
            case 'start':
            case 'search_intent':
                $sys = $get_prompt('azevent_seo_intent_system', $defaults['intent_system']);
                $user = $get_prompt('azevent_seo_intent_user', $defaults['intent_user']);
                if ($mode === 'rewrite') {
                    $user .= $rewrite_instructions['intent'];
                }
                $result = $ai->call_anthropic($replace_placeholders($user, $context), $replace_placeholders($sys, $context), 'claude-3-5-haiku-20241022');
                if (is_wp_error($result))
                    wp_send_json_error(array('message' => $result->get_error_message()));

                $context['search_intent'] = $result;
                wp_send_json_success(array(
                    'status' => 'processing',
                    'message' => 'Đã hoàn thành phân tích mục đích tìm kiếm. Đang xây dựng dàn ý bài viết...',
                    'next_step' => 'outline',
                    'context' => $context
                ));
                break;

            case 'outline':
                $sys = $get_prompt('azevent_seo_outline_system', $defaults['outline_system']);
                $user = $get_prompt('azevent_seo_outline_user', $defaults['outline_user']);
                if ($mode === 'rewrite') {
                    $user .= $rewrite_instructions['outline'];
                }
                $result = $ai->call_anthropic($replace_placeholders($user, $context), $replace_placeholders($sys, $context), 'claude-3-5-sonnet-20240620');
                if (is_wp_error($result))
                    wp_send_json_error(array('message' => $result->get_error_message()));

                $context['outline'] = $result;
                wp_send_json_success(array(
                    'status' => 'processing',
                    'message' => 'Đã xây dựng dàn ý chi tiết. Đang tiến hành viết nội dung bài viết...',
                    'next_step' => 'content',
                    'context' => $context
                ));
                break;

            case 'content':
                $sys = $get_prompt('azevent_seo_content_system', $defaults['content_system']);
                $user = $get_prompt('azevent_seo_content_user', $defaults['content_user']);
                if ($mode === 'rewrite') {
                    $user .= $rewrite_instructions['content'];
                }
                $result = $ai->call_anthropic($replace_placeholders($user, $context), $replace_placeholders($sys, $context), 'claude-3-5-sonnet-20240620');
                if (is_wp_error($result))
                    wp_send_json_error(array('message' => $result->get_error_message()));

                $context['content'] = $result;
                wp_send_json_success(array(
                    'status' => 'processing',
                    'message' => 'Nội dung bài viết đã hoàn tất. Đang tạo dữ liệu SEO Meta (Title, Slug, Description)...',
                    'next_step' => 'seo',
                    'context' => $context
                ));
                break;

            case 'seo':
                $sys = $get_prompt('azevent_seo_seo_system', $defaults['seo_system']);
                $user = $get_prompt('azevent_seo_seo_user', $defaults['seo_user']);
                if ($mode === 'rewrite') {
                    $user .= $rewrite_instructions['seo'];
                }
                $result = $ai->call_openai($replace_placeholders($user, $context), $replace_placeholders($sys, $context), 'chat/completions', array('response_format' => array('type' => 'json_object')));
                if (is_wp_error($result))
                    wp_send_json_error(array('message' => $result->get_error_message()));

                $seo_data = json_decode($result, true);
                if (!is_array($seo_data) || empty($seo_data['title']) || empty($seo_data['meta']) || empty($seo_data['image_prompt'])) {
                    wp_send_json_error(array('message' => 'AI trả về dữ liệu SEO không đầy đủ.'));
                }
                $seo_data['image_alt'] = AzEvent_Image_SEO::normalize_alt(
                    $seo_data['image_alt'] ?? '',
                    $seo_data['title'],
                    $keyword
                );
                $context['seo'] = $seo_data;
                $context['mode'] = $mode;
                $context['regenerate_image'] = $regenerate_image;

                $should_generate_image = $mode !== 'rewrite' || $regenerate_image || !has_post_thumbnail($post_id);
                $next_step = $should_generate_image ? 'image' : 'finalize';

                wp_send_json_success(array(
                    'status' => 'processing',
                    'message' => $should_generate_image ? 'Đã tối ưu SEO. Đang xử lý ảnh đại diện...' : 'Đã tối ưu SEO. Đang lưu bản viết lại dưới dạng bản nháp...',
                    'next_step' => $next_step,
                    'context' => $context
                ));
                break;

            case 'finalize':
                $this->complete_generation($post_id, $context, $mode);
                break;

            case 'image':
                $image_prompt = $context['seo']['image_prompt'] . " Cinematic style, professional photography, high resolution, no text, no watermark.";
                $image_result = $ai->generate_image($image_prompt, '', '1:1');
                if (is_wp_error($image_result))
                    wp_send_json_error(array('message' => $image_result->get_error_message()));

                $attachment_id = AzEvent_Image_SEO::upload_base64($image_result, $post_id, array(
                    'role' => 'featured',
                    'title' => $context['seo']['title'],
                    'alt' => $context['seo']['image_alt'] ?? '',
                    'keyword' => $keyword,
                    'max_width' => 1600,
                    'max_height' => 1600,
                    'quality' => 82,
                ));
                if (is_wp_error($attachment_id))
                    wp_send_json_error(array('message' => 'Lỗi tải ảnh: ' . $attachment_id->get_error_message()));

                $featured_result = AzEvent_Image_SEO::set_featured_image($post_id, $attachment_id);
                if (is_wp_error($featured_result))
                    wp_send_json_error(array('message' => $featured_result->get_error_message()));

                $this->complete_generation($post_id, $context, $mode);
                break;

            default:
                wp_send_json_error(array('message' => 'Bước không hợp lệ.'));
                break;
        }
    }

    public function ajax_regenerate_section_image()
    {
        check_ajax_referer('azevent_section_image', 'nonce');
        ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        $post_id = absint($_POST['post_id'] ?? 0);
        $section_key = sanitize_key(wp_unslash($_POST['section_key'] ?? ''));
        if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Bạn không có quyền tạo lại ảnh của bài viết này.'), 403);
        }
        if ($section_key === '') {
            wp_send_json_error(array('message' => 'Thiếu mã H2 cần tạo lại ảnh.'), 400);
        }
        $result = AzEvent_Section_Images::regenerate($post_id, $section_key);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 502);
        }
        wp_send_json_success($result);
    }

    public function ajax_get_browser_step_status()
    {
        check_ajax_referer('azevent_seo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Quyền truy cập bị từ chối.'), 403);
        }

        $request_id = sanitize_key(wp_unslash($_POST['request_id'] ?? ''));
        if ($request_id === '') {
            wp_send_json_error(array('message' => 'Thiếu mã request cần kiểm tra.'), 400);
        }

        $status = get_transient($this->get_browser_step_transient_key($request_id));
        if (!is_array($status)) {
            wp_send_json_error(array('message' => 'Chưa tìm thấy trạng thái request.'), 404);
        }

        wp_send_json_success($status);
    }

    public function ajax_get_browser_checkpoint()
    {
        check_ajax_referer('azevent_seo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Quyền truy cập bị từ chối.'), 403);
        }

        $job_id = absint($_POST['job_id'] ?? 0);
        $checkpoint = AzEvent_Background_Queue::get_browser_checkpoint(get_current_user_id(), $job_id);
        if (!$checkpoint) {
            $legacy_checkpoint = get_user_meta(get_current_user_id(), '_azevent_seo_browser_checkpoint', true);
            if (is_array($legacy_checkpoint) && !empty($legacy_checkpoint['session_id'])) {
                AzEvent_Background_Queue::save_browser_checkpoint(
                    $legacy_checkpoint['session_id'],
                    $legacy_checkpoint,
                    get_current_user_id()
                );
                delete_user_meta(get_current_user_id(), '_azevent_seo_browser_checkpoint');
                $checkpoint = AzEvent_Background_Queue::get_browser_checkpoint(get_current_user_id(), $job_id);
            }
        }
        if (!$checkpoint) {
            wp_send_json_success(array('checkpoint' => null));
        }

        $post_id = absint($checkpoint['post_id'] ?? 0);
        if ($post_id > 0 && (!get_post($post_id) || !current_user_can('edit_post', $post_id))) {
            AzEvent_Background_Queue::delete_browser_checkpoint($checkpoint['session_id'], get_current_user_id());
            wp_send_json_success(array('checkpoint' => null));
        }

        if (
            ($checkpoint['status'] ?? '') === 'processing' &&
            absint($checkpoint['updated_at'] ?? 0) < time() - 30 * MINUTE_IN_SECONDS
        ) {
            $checkpoint['status'] = 'failed';
            $checkpoint['request_id'] = '';
            $checkpoint['error'] = 'Phiên xử lý trước đã dừng quá lâu. Bạn có thể thử lại đúng bước đang dở.';
            $checkpoint['updated_at'] = time();
            AzEvent_Background_Queue::save_browser_checkpoint($checkpoint['session_id'], $checkpoint, get_current_user_id());
        }

        wp_send_json_success(array('checkpoint' => $checkpoint));
    }

    public function ajax_clear_browser_checkpoint()
    {
        check_ajax_referer('azevent_seo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Quyền truy cập bị từ chối.'), 403);
        }

        $session_id = substr(sanitize_key(wp_unslash($_POST['session_id'] ?? '')), 0, 64);
        if ($session_id !== '') {
            set_transient($this->get_cancelled_session_transient_key($session_id), 1, 30 * MINUTE_IN_SECONDS);
        }
        $this->delete_browser_checkpoint($session_id);
        wp_send_json_success(array('message' => 'Đã bỏ phiên làm việc đã lưu.'));
    }

    private function store_browser_step_status($request_id, array $status)
    {
        set_transient(
            $this->get_browser_step_transient_key($request_id),
            $status,
            30 * MINUTE_IN_SECONDS
        );
    }

    private function get_browser_step_transient_key($request_id)
    {
        return 'azevent_browser_step_' . get_current_user_id() . '_' . md5($request_id);
    }

    private function save_browser_checkpoint($session_id, array $checkpoint, $replace_existing = false)
    {
        if (get_transient($this->get_cancelled_session_transient_key($session_id))) {
            return;
        }
        AzEvent_Background_Queue::save_browser_checkpoint($session_id, $checkpoint, get_current_user_id());
    }

    private function delete_browser_checkpoint($session_id = '')
    {
        if ($session_id !== '') {
            AzEvent_Background_Queue::delete_browser_checkpoint($session_id, get_current_user_id());
        }
    }

    private function complete_browser_checkpoint($session_id, $post_id)
    {
        AzEvent_Background_Queue::complete_browser_checkpoint($session_id, get_current_user_id(), $post_id);
    }

    private function get_cancelled_session_transient_key($session_id)
    {
        return 'azevent_cancelled_session_' . get_current_user_id() . '_' . md5($session_id);
    }

    private function complete_generation($post_id, $context, $mode)
    {
        $updated_post = array(
            'ID' => $post_id,
            'post_title' => $context['seo']['title'],
            'post_content' => $context['content'],
            'post_excerpt' => $context['seo']['meta'],
            'post_status' => 'draft',
        );

        if ($mode !== 'rewrite') {
            $updated_post['post_name'] = $context['seo']['slug'];
        }

        $updated = wp_update_post($updated_post, true);
        if (is_wp_error($updated)) {
            wp_send_json_error(array('message' => 'Không thể lưu bài viết: ' . $updated->get_error_message()));
        }

        wp_send_json_success(array(
            'status' => 'completed',
            'post_id' => $post_id,
            'message' => $mode === 'rewrite'
                ? 'Đã viết lại bài và lưu thành bản nháp. Slug và ảnh đại diện cũ được giữ nguyên nếu bạn không chọn tạo lại ảnh.'
                : 'Tuyệt vời! Bài viết mới đã được tạo thành công dưới dạng bản nháp.',
            'title' => $context['seo']['title'],
            'content' => $context['content'],
        ));
    }

    private function upload_image_from_result($image_result, $post_id, $title)
    {
        return AzEvent_Image_SEO::upload_base64($image_result, $post_id, array(
            'role' => 'featured',
            'title' => $title,
            'alt' => $title,
            'keyword' => $title,
            'max_width' => 1600,
            'max_height' => 1600,
            'quality' => 82,
        ));
    }

    /**
     * Helper to upload image from URL to Media Library.
     */
    private function upload_image_from_url($url, $post_id, $title)
    {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $desc = "AI Generated Image for " . $title;
        $file_array = array();

        // Download file to temp location
        $temp_file = download_url($url);
        if (is_wp_error($temp_file))
            return $temp_file;

        $file_array['name'] = sanitize_title($title) . '.png';
        $file_array['tmp_name'] = $temp_file;

        // Do the real install
        $id = media_handle_sideload($file_array, $post_id, $desc);

        // If error, unlink
        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
        } else {
            $alt = AzEvent_Image_SEO::normalize_alt('', $title, $title);
            update_post_meta($id, '_wp_attachment_image_alt', $alt);
            update_post_meta($id, AzEvent_Image_SEO::GENERATED_META_KEY, 1);
            update_post_meta($id, AzEvent_Image_SEO::ROLE_META_KEY, 'featured');
            wp_update_post(array('ID' => $id, 'post_content' => $alt));
        }

        return $id;
    }
}

new AzEvent_Editor_Integration();
