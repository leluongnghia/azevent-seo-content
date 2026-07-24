<?php

define('ABSPATH', __DIR__ . '/');
define('AZEVENT_SEO_PATH', dirname(__DIR__) . '/');

$azevent_geo_test_options = array();

function sanitize_key($key)
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
}

function get_option($name, $default = false)
{
    global $azevent_geo_test_options;
    return array_key_exists($name, $azevent_geo_test_options)
        ? $azevent_geo_test_options[$name]
        : $default;
}

function update_option($name, $value, $autoload = null)
{
    global $azevent_geo_test_options;
    $azevent_geo_test_options[$name] = $value;
    return true;
}

require AZEVENT_SEO_PATH . 'includes/class-azevent-geo-prompts.php';

function azevent_geo_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$message}\n");
}

$base_prompt = 'PROMPT GỐC KHÔNG ĐƯỢC THAY ĐỔI';
$disabled_prompt = AzEvent_GEO_Prompts::append(
    $base_prompt,
    AzEvent_GEO_Prompts::CONTENT_STUDIO,
    'content',
    false
);
azevent_geo_assert(
    $disabled_prompt === $base_prompt,
    'Bỏ chọn GEO trả lại prompt gốc chính xác từng ký tự.'
);

$enabled_prompt = AzEvent_GEO_Prompts::append(
    $base_prompt,
    AzEvent_GEO_Prompts::CONTENT_STUDIO,
    'content',
    true
);
azevent_geo_assert(
    strpos($enabled_prompt, $base_prompt) === 0
        && strpos($enabled_prompt, 'AI Overview/GEO') !== false,
    'Bật GEO chỉ nối priority prompt sau prompt gốc.'
);

$content_defaults = AzEvent_GEO_Prompts::get_defaults(AzEvent_GEO_Prompts::CONTENT_STUDIO);
$lab_defaults = AzEvent_GEO_Prompts::get_defaults(AzEvent_GEO_Prompts::WORKFLOW_LAB);
azevent_geo_assert(
    array_keys($content_defaults) === array('intent', 'outline', 'outline_validation', 'content', 'seo', 'rewrite'),
    'Content Studio có file priority riêng cho đủ Create/Rewrite pipeline và Outline Validation.'
);
azevent_geo_assert(
    array_keys($lab_defaults) === array('research', 'brief', 'outline_validation', 'content', 'seo', 'quality'),
    'Workflow Lab có file priority riêng, gồm cả Outline Validation và Quality Gate.'
);

$custom_option = AzEvent_GEO_Prompts::option_name(AzEvent_GEO_Prompts::WORKFLOW_LAB, 'quality');
$azevent_geo_test_options[$custom_option] = 'GEO CUSTOM KHÔNG GHI ĐÈ PROMPT GỐC';
azevent_geo_assert(
    AzEvent_GEO_Prompts::get_priority(AzEvent_GEO_Prompts::WORKFLOW_LAB, 'quality')
        === 'GEO CUSTOM KHÔNG GHI ĐÈ PROMPT GỐC',
    'Priority GEO tùy chỉnh được đọc từ option riêng.'
);

azevent_geo_assert(
    AzEvent_GEO_Prompts::option_name(AzEvent_GEO_Prompts::CONTENT_STUDIO, 'content')
        !== AzEvent_GEO_Prompts::option_name(AzEvent_GEO_Prompts::WORKFLOW_LAB, 'content'),
    'Option GEO của Content Studio và Workflow Lab không trùng nhau.'
);

$legacy_vietnamese_intent = <<<'PROMPT'
## Ưu tiên bổ sung: AI Overview/GEO
- Phân tích theo nhu cầu thực tế của người đọc, không theo mục tiêu thao túng thứ hạng.
- Mở rộng truy vấn thành các câu hỏi tiếp nối hợp lý: định nghĩa, lựa chọn, so sánh, quy trình, chi phí, thời gian, rủi ro và hành động tiếp theo; chỉ giữ nhóm phù hợp với chủ đề.
- Xác định entities chính, quan hệ giữa các entities, information gain có thể tạo và những dữ kiện cần bằng chứng.
- Tách rõ dữ kiện đã được cung cấp, suy luận hợp lý, thông tin có nguy cơ lỗi thời và thông tin chưa xác minh.
- Không bịa nguồn, URL, số liệu, ngày tháng, khách hàng, giá, case study hoặc tuyên bố đang xếp hạng.
PROMPT;
$legacy_option = AzEvent_GEO_Prompts::option_name(AzEvent_GEO_Prompts::CONTENT_STUDIO, 'intent');
$azevent_geo_test_options[$legacy_option] = $legacy_vietnamese_intent;
$custom_quality_before_migration = $azevent_geo_test_options[$custom_option];
AzEvent_GEO_Prompts::maybe_upgrade_to_english();
azevent_geo_assert(
    strpos($azevent_geo_test_options[$legacy_option], 'Additional AI Overview/GEO priorities') !== false,
    'Migration thay bộ GEO tiếng Việt mặc định bằng bộ tiếng Anh.'
);
azevent_geo_assert(
    $azevent_geo_test_options[$custom_option] === $custom_quality_before_migration,
    'Migration giữ nguyên GEO priority đã tùy chỉnh.'
);
azevent_geo_assert(
    $azevent_geo_test_options[AzEvent_GEO_Prompts::TEMPLATE_VERSION_OPTION]
        === AzEvent_GEO_Prompts::TEMPLATE_VERSION,
    'Migration ghi nhận đúng version English GEO template.'
);

fwrite(STDOUT, "All GEO prompt regression checks passed.\n");
