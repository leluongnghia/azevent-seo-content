<?php
/**
 * Lightweight regression checks for the admin UI structure and responsive CSS.
 */

$root = dirname(__DIR__);
$settings = file_get_contents($root . '/admin/views/settings-page.php');
$background_queue = file_get_contents($root . '/admin/views/background-queue-page.php');
$admin = file_get_contents($root . '/admin/class-azevent-admin.php');
$updater = file_get_contents($root . '/includes/class-azevent-github-updater.php');
$editor_integration = file_get_contents($root . '/includes/class-azevent-editor-integration.php');
$editor_js = file_get_contents($root . '/admin/js/editor.js');
$workflow_css = file_get_contents($root . '/admin/css/workflow-lab.css');
$editor_css = file_get_contents($root . '/admin/css/editor.css');

function azevent_ui_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

azevent_ui_assert(
    strpos($admin, "'azevent-seo-background-queue',\n            array(\$this, 'render_background_queue_page')") !== false
        && strpos($admin, "add_submenu_page(\n            null,\n            __('AzEvent SEO Settings'") !== false
        && strpos($admin, "add_submenu_page(\n            null,\n            __('Content Studio'") !== false
        && strpos($admin, "add_submenu_page(\n            null,\n            __('SEO Workflow Lab'") !== false
        && strpos($admin, "remove_submenu_page('azevent-seo-background-queue', 'azevent-seo-settings')") === false
        && strpos($updater, "'azevent-seo-background-queue',\n            'GitHub Updates'") !== false,
    'Queue là trang mặc định; Studio, Workflow Lab và Settings dùng route admin ẩn hợp lệ.'
);
azevent_ui_assert(
    substr_count($background_queue, 'azq-open-modal"') === 4
        && strpos($background_queue, 'data-modal-section="studio"') !== false
        && strpos($background_queue, 'data-modal-section="workflow-lab"') !== false
        && strpos($background_queue, 'data-modal-title="<?php esc_attr_e(\'Settings\'') !== false
        && strpos($background_queue, 'data-modal-title="<?php esc_attr_e(\'Prompt\'') !== false
        && strpos($background_queue, 'role="dialog"') !== false
        && strpos($background_queue, "event.key === 'Escape'") !== false
        && strpos($admin, "postMessage('azevent-close-admin-modal'") !== false
        && strpos($background_queue, "event.data === 'azevent-close-admin-modal'") !== false
        && strpos($background_queue, "get('azevent_open')") !== false
        && strpos($editor_integration, "'azevent_open' => 'settings'") !== false,
    'Queue có bốn nút công cụ mở modal chung và hỗ trợ phím Escape.'
);
azevent_ui_assert(
    strpos($settings, 'azevent-settings-modal-prompts') !== false
        && strpos($settings, "\$azevent_initial_tab = \$azevent_modal_section === 'prompts' ? 'prompts'") !== false,
    'Settings modal tách đúng nhóm cấu hình và nhóm Prompt.'
);
azevent_ui_assert(
    strpos($admin, 'public function render_modal_frame_styles()') !== false
        && strpos($admin, '.azevent-studio-admin-page .azevent-modal-header { display: none; }') !== false
        && strpos($admin, '.azlab-page > .azlab-hero { display: none; }') !== false,
    'Content Studio và Workflow Lab có chế độ nhúng gọn trong modal Queue.'
);
azevent_ui_assert(
    strpos($background_queue, 'class="azq-toolbar-status"') !== false
        && strpos($background_queue, 'class="button azq-refresh" id="azq-refresh"') !== false
        && strpos($background_queue, 'grid-template-columns: repeat(auto-fit,minmax(120px,1fr))') !== false
        && strpos($background_queue, 'grid-template-columns: repeat(3,minmax(0,1fr))') !== false
        && strpos($background_queue, 'grid-template-columns: repeat(2,minmax(0,1fr))') !== false,
    'Nút Làm mới nằm trong toolbar Queue và nhóm công cụ responsive theo lưới cân đối.'
);
azevent_ui_assert(
    preg_match('/\\.azevent-settings-page\\s*\\{[^}]*max-width:\\s*1480px;/s', $settings) === 1
        && preg_match('/\\.azevent-settings-page\\s*\\{[^}]*max-width:\\s*none;/s', $settings) !== 1,
    'Settings giữ chiều rộng đọc tối đa và không còn kéo giãn vô hạn.'
);
azevent_ui_assert(
    strpos($workflow_css, '.azlab-page{width:auto;max-width:1480px}') !== false
        && strpos($workflow_css, '.azlab-page{width:auto;max-width:none}') === false,
    'Workflow Lab không còn override giới hạn chiều rộng.'
);
azevent_ui_assert(
    strpos($editor_css, '.azevent-studio-admin-page') !== false
        && substr_count($editor_css, 'max-width: 1480px;') >= 2,
    'Content Studio và modal dùng chiều rộng desktop có giới hạn.'
);
azevent_ui_assert(
    substr_count($settings, 'role="tab"') === 5
        && substr_count($settings, 'role="tabpanel"') === 5
        && strpos($settings, 'role="tablist"') !== false,
    'Settings tabs có đầy đủ semantics tablist/tab/tabpanel.'
);
azevent_ui_assert(
    strpos($settings, "event.key === 'ArrowRight'") !== false
        && strpos($settings, "event.key === 'ArrowLeft'") !== false
        && strpos($settings, "event.key === 'Home'") !== false
        && strpos($settings, "event.key === 'End'") !== false,
    'Settings tabs hỗ trợ bàn phím Arrow/Home/End.'
);
azevent_ui_assert(
    strpos($settings, 'repeat(6, minmax(0, 1fr))') !== false
        && strpos($settings, 'grid-column: span 2;') !== false
        && strpos($settings, 'grid-column: 2 / span 2;') !== false
        && strpos($settings, 'grid-column: 4 / span 2;') !== false
        && strpos($settings, 'repeat(5, minmax(0, 1fr))') === false,
    'Năm model dùng bố cục 3+2 cân giữa thay vì ép năm cột.'
);
azevent_ui_assert(
    strpos($settings, '.azevent-lab-model-card:last-child:nth-child(odd) { grid-column: 1 / -1; width: calc(50% - 6px); justify-self: center; }') !== false
        && strpos($settings, '.azevent-lab-model-card:last-child:nth-child(odd) { grid-column: auto; width: auto; max-width: none; }') !== false,
    'Model cuối được căn giữa trên tablet và trở về toàn chiều rộng trên mobile.'
);
azevent_ui_assert(
    strpos($settings, 'data-prompt-action="expand"') !== false
        && strpos($settings, 'data-prompt-action="collapse"') !== false,
    'Các nhóm prompt có điều khiển mở và thu gọn.'
);
azevent_ui_assert(
    strpos($settings, 'azevent-geo-formula') !== false
        && strpos($settings, 'Prompt AI Overview/GEO riêng cho Workflow Lab') !== false,
    'Khu GEO được phân cấp riêng và giải thích cách ghép prompt.'
);
azevent_ui_assert(
    strpos($settings, 'data-controlled-by="azevent_lab_validate_outline"') !== false
        && strpos($settings, 'data-controlled-by="azevent_seo_generate_h2_images"') !== false,
    'Các trường phụ chỉ hiện khi tùy chọn liên quan được bật.'
);
azevent_ui_assert(
    strpos($background_queue, "var keyword = document.createElement(destination ? 'a' : 'span')") !== false
        && strpos($background_queue, "keyword.setAttribute('aria-label', destination.label + ': ' + job.keyword)") !== false
        && strpos($background_queue, 'if (destination) addButton(actions, destination.label, destination.url') !== false,
    'Trang Background Queue gắn từ khóa vào đúng hành động chính và giữ nhãn truy cập.'
);
azevent_ui_assert(
    strpos($editor_js, '? $(\'<a class="azevent-queue-keyword">\')') !== false
        && strpos($editor_js, "'aria-label': destination.label + ': ' + job.keyword") !== false
        && strpos($editor_js, ".toggleClass('button-primary', destination.primary)") !== false,
    'Queue trong Content Studio dùng cùng đích đến cho từ khóa và nút hành động.'
);
azevent_ui_assert(
    strpos($background_queue, 'a.azq-keyword:focus-visible') !== false
        && strpos($editor_css, 'a.azevent-queue-keyword:focus-visible') !== false,
    'Liên kết từ khóa có trạng thái focus bàn phím rõ ràng ở cả hai giao diện.'
);

fwrite(STDOUT, "All admin UI regression checks passed.\n");
