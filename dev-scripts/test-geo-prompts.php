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
    array_keys($content_defaults) === array('intent', 'outline', 'content', 'seo', 'rewrite'),
    'Content Studio có file priority riêng cho đủ Create/Rewrite pipeline.'
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

fwrite(STDOUT, "All GEO prompt regression checks passed.\n");
