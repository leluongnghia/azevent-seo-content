<?php
/**
 * Settings page view.
 */

if (!defined('ABSPATH')) {
    exit;
}

$azevent_base_url = AzEvent_API_Client::get_base_url(
    get_option('aprg_cliproxy_base_url', AzEvent_API_Client::DEFAULT_BASE_URL)
);
$azevent_image_model = get_option('aprg_seo_default_cliproxy_image_model', 'gpt-image-2');
$azevent_text_model = get_option('aprg_cliproxy_model', 'claude-sonnet-4-6');
$azevent_text_provider = get_option('azevent_seo_text_provider', 'azevent');
$azevent_ckey_model = get_option('azevent_seo_ckey_model', '');
$azevent_ckey_api_format = get_option('azevent_seo_ckey_api_format', 'messages');
$azevent_step_models = array(
    'intent' => get_option('azevent_seo_intent_model', ''),
    'outline' => get_option('azevent_seo_outline_model', ''),
    'content' => get_option('azevent_seo_content_model', ''),
    'seo' => get_option('azevent_seo_seo_model', ''),
);
$azevent_lab_step_models = array();
foreach (array('research', 'brief', 'content', 'seo', 'quality') as $lab_step) {
    $azevent_lab_step_models[$lab_step] = get_option("azevent_lab_{$lab_step}_model", '');
}
$azevent_lab_split_content = absint(get_option('azevent_lab_split_content_by_outline', 0));
$azevent_brand_defaults = AzEvent_SEO_Content::get_default_brand_profile();
$azevent_brand_profile = AzEvent_SEO_Content::get_brand_profile();
$azevent_custom_models = json_decode(get_option('aprg_cliproxy_custom_models', '[]'), true);
$azevent_custom_models = is_array($azevent_custom_models) ? array_values(array_filter(array_map('sanitize_text_field', $azevent_custom_models))) : array();
$azevent_ckey_custom_models = json_decode(get_option('azevent_seo_ckey_custom_models', '[]'), true);
$azevent_ckey_custom_models = is_array($azevent_ckey_custom_models) ? array_values(array_filter(array_map('sanitize_text_field', $azevent_ckey_custom_models))) : array();
$azevent_legacy_openai_models = json_decode(get_option('azevent_seo_legacy_openai_models', '[]'), true);
$azevent_legacy_openai_models = is_array($azevent_legacy_openai_models) ? array_values(array_filter(array_map('sanitize_text_field', $azevent_legacy_openai_models))) : array();
$azevent_legacy_anthropic_models = json_decode(get_option('azevent_seo_legacy_anthropic_models', '[]'), true);
$azevent_legacy_anthropic_models = is_array($azevent_legacy_anthropic_models) ? array_values(array_filter(array_map('sanitize_text_field', $azevent_legacy_anthropic_models))) : array();
$azevent_legacy_openai_model = get_option('azevent_seo_openai_model', 'gpt-4o-mini');
$azevent_legacy_anthropic_model = get_option('azevent_seo_anthropic_model', 'claude-3-5-sonnet-20240620');
if (empty($azevent_legacy_openai_models)) {
    $azevent_legacy_openai_models = array('gpt-4o-mini');
}
if (empty($azevent_legacy_anthropic_models)) {
    $azevent_legacy_anthropic_models = array('claude-3-5-sonnet-20240620');
}
if (!in_array($azevent_legacy_openai_model, $azevent_legacy_openai_models, true)) {
    $azevent_legacy_openai_models[] = $azevent_legacy_openai_model;
}
if (!in_array($azevent_legacy_anthropic_model, $azevent_legacy_anthropic_models, true)) {
    $azevent_legacy_anthropic_models[] = $azevent_legacy_anthropic_model;
}
$azevent_text_models = array(
    'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
    'gpt-5-mini' => 'GPT-5 Mini',
    'grok-4-1-fast-reasoning' => 'Grok 4.1 Fast Reasoning',
);
$azevent_ckey_models = array();
foreach ($azevent_ckey_custom_models as $ckey_model) {
    $azevent_ckey_models[$ckey_model] = $ckey_model;
}
if ($azevent_ckey_model !== '' && !isset($azevent_ckey_models[$azevent_ckey_model])) {
    $azevent_ckey_models[$azevent_ckey_model] = $azevent_ckey_model . ' (Current)';
}
if (class_exists('APRG_AI_Factory') && method_exists('APRG_AI_Factory', 'get_available_models')) {
    $available_models = APRG_AI_Factory::get_available_models();
    if (!empty($available_models['cliproxy']) && is_array($available_models['cliproxy'])) {
        foreach ($available_models['cliproxy'] as $model_id => $model_label) {
            $azevent_text_models[$model_id] = $model_label;
        }
    }
}
foreach ($azevent_custom_models as $custom_model) {
    $azevent_text_models[$custom_model] = $custom_model . ' (Custom)';
}
if (!isset($azevent_text_models[$azevent_text_model]) && $azevent_text_model !== '') {
    $azevent_text_models[$azevent_text_model] = $azevent_text_model . ' (Current)';
}
foreach ($azevent_step_models as $step_model) {
    if (AzEvent_CKey_Client::is_model_reference($step_model)) {
        $ckey_model = AzEvent_CKey_Client::strip_model_prefix($step_model);
        if ($ckey_model !== '' && !isset($azevent_ckey_models[$ckey_model])) {
            $azevent_ckey_models[$ckey_model] = $ckey_model . ' (Current)';
        }
    } elseif ($step_model !== '' && !isset($azevent_text_models[$step_model])) {
        $azevent_text_models[$step_model] = $step_model . ' (Current)';
    }
}
foreach ($azevent_lab_step_models as $step_model) {
    if (AzEvent_CKey_Client::is_model_reference($step_model)) {
        $ckey_model = AzEvent_CKey_Client::strip_model_prefix($step_model);
        if ($ckey_model !== '' && !isset($azevent_ckey_models[$ckey_model])) {
            $azevent_ckey_models[$ckey_model] = $ckey_model . ' (Current Lab)';
        }
    } elseif ($step_model !== '' && !isset($azevent_text_models[$step_model])) {
        $azevent_text_models[$step_model] = $step_model . ' (Current Lab)';
    }
}
$azevent_api_ready = AzEvent_API_Client::is_configured();
$azevent_ckey_ready = AzEvent_CKey_Client::is_configured();
$azevent_default_text_label = $azevent_text_provider === 'ckey'
    ? ($azevent_ckey_model !== '' ? 'CKEY.VN — ' . ($azevent_ckey_models[$azevent_ckey_model] ?? $azevent_ckey_model) : 'CKEY.VN — chưa chọn model')
    : AzEvent_API_Client::get_provider_label($azevent_base_url) . ' — ' . ($azevent_text_models[$azevent_text_model] ?? $azevent_text_model);
$azevent_default_language = get_option('azevent_seo_default_language', 'Vietnamese');
$azevent_browser_auto_advance = absint(get_option('azevent_seo_browser_auto_advance', 0));
$azevent_step_model_fields = array(
    'intent' => array(
        'label' => __('Search Intent', 'azevent-seo-content'),
        'hint' => __('Phân tích', 'azevent-seo-content'),
    ),
    'outline' => array(
        'label' => __('Outline', 'azevent-seo-content'),
        'hint' => __('Lập dàn ý', 'azevent-seo-content'),
    ),
    'content' => array(
        'label' => __('Content', 'azevent-seo-content'),
        'hint' => __('Viết bài', 'azevent-seo-content'),
    ),
    'seo' => array(
        'label' => __('SEO Metadata', 'azevent-seo-content'),
        'hint' => __('JSON SEO', 'azevent-seo-content'),
    ),
);
$default_prompts = AzEvent_Editor_Integration::get_default_prompts();
$get_prompt = function ($option, $default) {
    $value = get_option($option, '');
    return trim((string) $value) === '' ? $default : $value;
};
$prompt_sections = array(
    'intent' => array(
        'label' => __('Search Intent', 'azevent-seo-content'),
        'description' => __('Phân tích nhu cầu tìm kiếm và điểm yếu của bài hiện tại.', 'azevent-seo-content'),
    ),
    'outline' => array(
        'label' => __('Outline', 'azevent-seo-content'),
        'description' => __('Xây dựng cấu trúc H2/H3 theo mục tiêu SEO.', 'azevent-seo-content'),
    ),
    'content' => array(
        'label' => __('Content', 'azevent-seo-content'),
        'description' => __('Viết hoặc viết lại nội dung HTML chuẩn chuyển đổi.', 'azevent-seo-content'),
    ),
    'seo' => array(
        'label' => __('SEO Metadata', 'azevent-seo-content'),
        'description' => __('Sinh title, slug, meta description và image prompt.', 'azevent-seo-content'),
    ),
);
$prompt_tokens = array(
    '{keyword}' => __('Từ khóa nhập ở màn hình Post.', 'azevent-seo-content'),
    '{secondary_keywords}' => __('Từ khóa phụ; hiện dùng cùng từ khóa chính trong luồng hiện tại.', 'azevent-seo-content'),
    '{language}' => __('Ngôn ngữ được chọn khi tạo bài.', 'azevent-seo-content'),
    '{outline_focus}' => __('Trọng tâm outline; có thể bổ sung thủ công trong prompt.', 'azevent-seo-content'),
    '{brand_name}' => __('Tên thương hiệu trong tab Thương hiệu.', 'azevent-seo-content'),
    '{brand_info}' => __('Thông tin thương hiệu trong tab Thương hiệu.', 'azevent-seo-content'),
    '{brand_solution}' => __('Dịch vụ/giải pháp trong tab Thương hiệu.', 'azevent-seo-content'),
    '{search_intent}' => __('Kết quả từ bước Search Intent.', 'azevent-seo-content'),
    '{outline}' => __('Kết quả từ bước Outline.', 'azevent-seo-content'),
    '{content}' => __('Nội dung đã viết, dùng ở bước SEO Metadata.', 'azevent-seo-content'),
    '{existing_title}' => __('Tiêu đề bài hiện tại khi Rewrite.', 'azevent-seo-content'),
    '{existing_content}' => __('Nội dung bài hiện tại khi Rewrite.', 'azevent-seo-content'),
    '{existing_excerpt}' => __('Excerpt hiện tại khi Rewrite.', 'azevent-seo-content'),
    '{existing_slug}' => __('Slug hiện tại khi Rewrite.', 'azevent-seo-content'),
    '{rewrite_goal}' => __('Mục tiêu tự động theo chế độ Create/Rewrite.', 'azevent-seo-content'),
);
$lab_prompt_defaults = AzEvent_Workflow_Lab_Pipeline::get_default_prompts();
$lab_prompt_sections = array(
    'research' => array(
        'label' => __('Research & Search Intent', 'azevent-seo-content'),
        'description' => __('Intent, audience, topic map, evidence và information gain.', 'azevent-seo-content'),
        'fallback_step' => 'intent',
    ),
    'brief' => array(
        'label' => __('Content Brief & Outline', 'azevent-seo-content'),
        'description' => __('Kiến trúc nội dung, evidence map và kế hoạch internal link.', 'azevent-seo-content'),
        'fallback_step' => 'outline',
    ),
    'content' => array(
        'label' => __('People-first Content', 'azevent-seo-content'),
        'description' => __('Viết HTML hữu ích, đáng tin cậy và tránh nội dung SEO máy móc.', 'azevent-seo-content'),
        'fallback_step' => 'content',
    ),
    'seo' => array(
        'label' => __('Search Appearance & SEO', 'azevent-seo-content'),
        'description' => __('Title, slug, meta, FAQ hiển thị và image prompt.', 'azevent-seo-content'),
        'fallback_step' => 'seo',
    ),
    'quality' => array(
        'label' => __('Internal Links & Quality Gate', 'azevent-seo-content'),
        'description' => __('Chấm điểm intent, trust, originality, spam risk và link thật.', 'azevent-seo-content'),
        'fallback_step' => 'content',
    ),
);
$lab_prompt_tokens = array(
    '{language}' => __('Ngôn ngữ mặc định của plugin.', 'azevent-seo-content'),
    '{keyword}' => __('Từ khóa chính của phiên Lab.', 'azevent-seo-content'),
    '{secondary_keywords}' => __('Danh sách từ khóa phụ do người dùng nhập.', 'azevent-seo-content'),
    '{audience}' => __('Đối tượng đọc do người dùng nhập.', 'azevent-seo-content'),
    '{competitor_notes}' => __('Dữ liệu SERP/đối thủ do người dùng cung cấp.', 'azevent-seo-content'),
    '{serp_snapshot}' => __('Snapshot SERP tự động gồm vị trí, title, snippet và cấu trúc trang.', 'azevent-seo-content'),
    '{brand_name}' => __('Tên thương hiệu.', 'azevent-seo-content'),
    '{brand_info}' => __('Thông tin thương hiệu đã xác thực.', 'azevent-seo-content'),
    '{brand_solution}' => __('Dịch vụ và giải pháp thương hiệu.', 'azevent-seo-content'),
    '{research}' => __('Kết quả Research đã duyệt.', 'azevent-seo-content'),
    '{brief}' => __('Content Brief & Outline đã duyệt.', 'azevent-seo-content'),
    '{content}' => __('Nội dung HTML đã duyệt.', 'azevent-seo-content'),
    '{seo_json}' => __('SEO metadata dạng JSON.', 'azevent-seo-content'),
    '{internal_link_candidates}' => __('Danh sách bài Published thật được plugin tìm thấy.', 'azevent-seo-content'),
);
?>
<div class="wrap azevent-settings-page">
    <style>
        .azevent-settings-page {
            width: auto;
            max-width: none;
            margin-right: 24px;
            color: #0f172a;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .azevent-settings-page * { box-sizing: border-box; }
        .azevent-hero {
            position: relative;
            overflow: hidden;
            margin: 24px 0 18px;
            padding: 27px 32px 30px;
            border: 1px solid #1e3a8a;
            border-radius: 22px;
            background: radial-gradient(circle at 88% 14%, rgba(129,140,248,.34), transparent 27%), linear-gradient(135deg, #0f172a 0%, #172554 56%, #312e81 100%);
            color: #fff;
            box-shadow: 0 18px 42px rgba(15, 23, 42, .2);
        }
        .azevent-hero:after {
            content: '';
            position: absolute;
            width: 260px;
            height: 260px;
            right: -75px;
            top: -115px;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 50%;
            box-shadow: 0 0 0 28px rgba(255,255,255,.05), 0 0 0 58px rgba(255,255,255,.04);
        }
        .azevent-hero-content { position: relative; z-index: 1; }
        .azevent-brand-row { display: flex; align-items: center; gap: 10px; margin-bottom: 26px; }
        .azevent-brand-mark { display: inline-grid; place-items: center; width: 30px; height: 30px; border: 1px solid rgba(255,255,255,.25); border-radius: 9px; background: linear-gradient(135deg, #818cf8, #c084fc); color: #fff; box-shadow: 0 7px 18px rgba(129,140,248,.3); font-size: 15px; }
        .azevent-brand-name { color: #f8fafc; font-size: 13px; font-weight: 800; letter-spacing: -.01em; }
        .azevent-brand-label { padding: 4px 8px; border: 1px solid rgba(255,255,255,.15); border-radius: 999px; background: rgba(255,255,255,.08); color: #c7d2fe; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .azevent-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 10px;
            color: #bfdbfe;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .azevent-hero h1 { margin: 0 0 8px; color: #fff; font-size: 31px; letter-spacing: -.035em; line-height: 1.15; }
        .azevent-hero p { max-width: 680px; margin: 0; color: #cbd5e1; font-size: 13px; line-height: 1.6; }
        .azevent-version {
            position: absolute;
            right: 28px;
            bottom: 24px;
            z-index: 1;
            padding: 5px 10px;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 999px;
            background: rgba(255,255,255,.1);
            color: #dbeafe;
            font-size: 11px;
            font-weight: 700;
        }
        .azevent-flow { display: flex; align-items: center; gap: 8px; margin: 0 0 18px; padding: 11px 14px; border: 1px solid #e2e8f0; border-radius: 12px; background: rgba(255,255,255,.82); box-shadow: 0 4px 14px rgba(15,23,42,.035); color: #64748b; font-size: 11px; font-weight: 700; }
        .azevent-flow-step { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .azevent-flow-dot { width: 7px; height: 7px; border-radius: 50%; background: #6366f1; box-shadow: 0 0 0 3px #eef2ff; }
        .azevent-flow-arrow { color: #cbd5e1; font-size: 15px; }
        .azevent-layout { display: block; }
        .azevent-tabs {
            position: sticky;
            top: 32px;
            z-index: 5;
            display: flex;
            gap: 6px;
            padding: 8px;
            margin-bottom: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .055);
            overflow-x: auto;
        }
        .azevent-tab {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            gap: 7px;
            padding: 11px 12px;
            border: 0;
            border-radius: 9px;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            text-align: left;
            white-space: nowrap;
        }
        .azevent-tab:hover { background: #f8fafc; color: #1e293b; }
        .azevent-tab.is-active { background: #4f46e5; color: #fff; box-shadow: 0 4px 12px rgba(79,70,229,.2); }
        .azevent-tab-icon { width: 24px; color: currentColor; font-size: 15px; }
        .azevent-panel { display: none; }
        .azevent-panel.is-active { display: block; }
        .azevent-card {
            margin-bottom: 18px;
            padding: 23px 25px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .045);
        }
        .azevent-card-header { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; margin-bottom: 20px; }
        .azevent-card-title { display: flex; align-items: flex-start; gap: 11px; }
        .azevent-section-icon { display: inline-grid; place-items: center; width: 34px; height: 34px; flex: 0 0 34px; border-radius: 9px; background: #eef2ff; color: #4f46e5; font-size: 16px; }
        .azevent-card h2 { margin: 0 0 5px; color: #0f172a; font-size: 18px; letter-spacing: -.02em; }
        .azevent-card h3 { margin: 0; color: #1e293b; font-size: 14px; }
        .azevent-card-description { margin: 0; color: #64748b; font-size: 12px; line-height: 1.6; }
        .azevent-brand-reset-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .azevent-brand-reset-status { color: #64748b; font-size: 11px; }
        .azevent-brand-reset-status.is-ready { color: #047857; }
        .azevent-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }
        .azevent-status.is-ready { background: #ecfdf5; color: #047857; }
        .azevent-status.is-pending { background: #fff7ed; color: #c2410c; }
        .azevent-status-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
        .azevent-provider-card { margin-top: 18px; padding: 18px; border: 1px solid #dbe4f3; border-radius: 14px; background: #fff; }
        .azevent-provider-card-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 16px; }
        .azevent-provider-card-header h3 { margin: 0 0 5px; color: #172554; font-size: 15px; }
        .azevent-provider-card-header p { margin: 0; color: #64748b; font-size: 12px; line-height: 1.55; }
        .azevent-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .azevent-field { margin-bottom: 17px; }
        .azevent-field:last-child { margin-bottom: 0; }
        .azevent-field label { display: block; margin-bottom: 7px; color: #334155; font-size: 12px; font-weight: 700; }
        .azevent-field input[type="text"],
        .azevent-field input[type="password"],
        .azevent-field select,
        .azevent-field textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #0f172a;
            font-size: 13px;
            transition: border-color .15s, box-shadow .15s;
        }
        .azevent-field input[type="text"], .azevent-field input[type="password"], .azevent-field select { height: 39px; padding: 0 11px; }
        .azevent-field textarea { min-height: 105px; padding: 10px 11px; resize: vertical; line-height: 1.55; }
        .azevent-field input:focus, .azevent-field select:focus, .azevent-field textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); outline: none; }
        .azevent-endpoint-key[hidden] { display: none; }
        .azevent-help { margin: 6px 0 0; color: #94a3b8; font-size: 11px; line-height: 1.5; }
        .azevent-field label.azevent-workflow-option { display: flex; align-items: flex-start; gap: 13px; margin-bottom: 0; padding: 15px; border: 1px solid #dbe4f3; border-radius: 11px; background: #f8fafc; }
        .azevent-workflow-option input[type="checkbox"] { width: 18px; height: 18px; margin: 2px 0 0; accent-color: #4f46e5; }
        .azevent-workflow-option strong, .azevent-workflow-option span { display: block; }
        .azevent-workflow-option strong { margin-bottom: 4px; color: #1e293b; font-size: 13px; }
        .azevent-workflow-option span { color: #64748b; font-size: 12px; line-height: 1.55; }
        .azevent-model-tools { display: flex; gap: 8px; align-items: stretch; margin-top: 9px; }
        .azevent-model-routing { margin-top: 18px; padding: 18px; border: 1px solid #dbe4f3; border-radius: 14px; background: linear-gradient(145deg, #f8fafc, #eef2ff); }
        .azevent-model-routing-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 14px; }
        .azevent-model-routing-header h3 { margin: 0 0 5px; color: #172554; font-size: 15px; }
        .azevent-model-routing-header p { margin: 0; color: #64748b; font-size: 12px; line-height: 1.55; }
        .azevent-model-routing-badge { flex: 0 0 auto; padding: 5px 9px; border: 1px solid #c7d2fe; border-radius: 999px; background: #fff; color: #4338ca; font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .azevent-step-model-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .azevent-step-model { padding: 13px; border: 1px solid #e2e8f0; border-radius: 11px; background: rgba(255,255,255,.9); }
        .azevent-step-model label { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .azevent-step-model small { color: #818cf8; font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .azevent-step-model select { margin-top: 8px; }
        .azevent-model-tools input { flex: 1; min-width: 0; }
        .azevent-model-add { flex: 0 0 auto; padding: 0 12px; border: 1px solid #c7d2fe; border-radius: 8px; background: #eef2ff; color: #4338ca; cursor: pointer; font-size: 12px; font-weight: 700; }
        .azevent-model-add:hover { background: #e0e7ff; }
        .azevent-model-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }
        .azevent-model-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 8px; border: 1px solid #c7d2fe; border-radius: 999px; background: #eef2ff; color: #4338ca; font-size: 11px; font-weight: 700; }
        .azevent-model-remove { display: inline-grid; place-items: center; width: 16px; height: 16px; padding: 0; border: 0; border-radius: 50%; background: #c7d2fe; color: #3730a3; cursor: pointer; font-size: 12px; line-height: 1; }
        .azevent-model-remove:hover { background: #a5b4fc; }
        .azevent-legacy-actions { display: flex; align-items: center; gap: 10px; margin-top: 16px; }
        .azevent-legacy-refresh { padding: 9px 13px; border: 1px solid #c7d2fe; border-radius: 8px; background: #eef2ff; color: #4338ca; cursor: pointer; font-size: 12px; font-weight: 700; }
        .azevent-legacy-refresh:hover { background: #e0e7ff; }
        .azevent-legacy-refresh:disabled { cursor: wait; opacity: .65; }
        .azevent-legacy-status { color: #64748b; font-size: 11px; }
        .azevent-legacy-status.is-error { color: #b91c1c; }
        .azevent-legacy-status.is-success { color: #047857; }
        .azevent-token-card { margin: 0 0 18px; padding: 16px 17px; border: 1px solid #c7d2fe; border-radius: 12px; background: linear-gradient(135deg, #f5f3ff, #eff6ff); }
        .azevent-token-header { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
        .azevent-token-title { color: #312e81; font-size: 13px; font-weight: 800; }
        .azevent-token-subtitle { color: #6366f1; font-size: 11px; }
        .azevent-token-list { display: flex; flex-wrap: wrap; gap: 7px; }
        .azevent-token { padding: 6px 9px; border: 1px solid #c4b5fd; border-radius: 7px; background: rgba(255,255,255,.72); color: #4338ca; cursor: pointer; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; font-weight: 700; }
        .azevent-token:hover, .azevent-token:focus { border-color: #6366f1; background: #fff; box-shadow: 0 3px 8px rgba(79,70,229,.12); outline: none; }
        .azevent-token-description { margin: 11px 0 0; color: #64748b; font-size: 11px; line-height: 1.55; }
        .azevent-note { margin: 0 0 18px; padding: 13px 14px; border: 1px solid #c7d2fe; border-radius: 10px; background: linear-gradient(135deg, #eef2ff, #f5f3ff); color: #3730a3; font-size: 12px; line-height: 1.55; }
        .azevent-legacy { margin-top: 18px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #fff; transition: border-color .15s, box-shadow .15s; }
        .azevent-legacy:hover, .azevent-legacy[open] { border-color: #c7d2fe; box-shadow: 0 4px 14px rgba(15,23,42,.04); }
        .azevent-legacy summary { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 14px 16px; color: #334155; cursor: pointer; font-size: 12px; font-weight: 700; list-style: none; }
        .azevent-legacy summary::-webkit-details-marker { display: none; }
        .azevent-legacy summary:after { content: '⌄'; color: #64748b; font-size: 17px; line-height: 1; transition: transform .15s; }
        .azevent-legacy[open] summary { border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .azevent-legacy[open] summary:after { transform: rotate(180deg); }
        .azevent-legacy-title { display: flex; align-items: center; gap: 10px; }
        .azevent-legacy-icon { display: inline-grid; place-items: center; width: 30px; height: 30px; border-radius: 8px; background: #f1f5f9; color: #64748b; font-size: 15px; }
        .azevent-legacy-description { display: block; margin-top: 2px; color: #94a3b8; font-size: 11px; font-weight: 400; }
        .azevent-legacy-badge { padding: 4px 8px; border-radius: 999px; background: #f1f5f9; color: #64748b; font-size: 10px; font-weight: 700; }
        .azevent-legacy-grid { margin: 16px; }
        .azevent-legacy-actions { margin: 0 16px 16px; }
        .azevent-prompt {
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .azevent-prompt summary { display: flex; justify-content: space-between; gap: 12px; padding: 14px 15px; background: #f8fafc; color: #0f172a; cursor: pointer; list-style: none; }
        .azevent-prompt summary::-webkit-details-marker { display: none; }
        .azevent-prompt summary:after { content: '+'; color: #6366f1; font-size: 18px; line-height: 1; }
        .azevent-prompt[open] summary:after { content: '−'; }
        .azevent-prompt-title { display: block; margin-bottom: 3px; font-size: 13px; font-weight: 700; }
        .azevent-prompt-description { display: block; color: #64748b; font-size: 11px; font-weight: 400; }
        .azevent-prompt-body { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; padding: 17px 15px; }
        .azevent-prompt-body .azevent-field { margin: 0; }
        .azevent-lab-prompt-model { padding: 15px 15px 0; border-top: 1px solid #eef2f7; }
        .azevent-lab-prompt-model .azevent-field { margin: 0; }
        .azevent-lab-reset-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0 0 16px; }
        .azevent-lab-reset-status { color: #64748b; font-size: 11px; }
        .azevent-lab-reset-status.is-ready { color: #047857; }
        .azevent-serp-box { margin-bottom: 18px; padding: 17px; border: 1px solid #bfdbfe; border-radius: 13px; background: linear-gradient(135deg, #eff6ff, #f8fafc); }
        .azevent-serp-box h3 { margin: 0; color: #1e3a8a; font-size: 14px; }
        .azevent-serp-box > p { margin: 6px 0 15px; color: #64748b; font-size: 11px; line-height: 1.55; }
        .azevent-footer { position: sticky; bottom: 16px; z-index: 4; display: flex; justify-content: flex-end; gap: 10px; margin-top: 4px; padding: 13px 15px; border: 1px solid #e2e8f0; border-radius: 12px; background: rgba(255,255,255,.92); box-shadow: 0 10px 26px rgba(15,23,42,.1); backdrop-filter: blur(10px); }
        .azevent-footer .button-primary { min-width: 154px; height: 42px; border: 0; border-radius: 10px; background: linear-gradient(135deg, #4f46e5, #7c3aed); box-shadow: 0 8px 18px rgba(79,70,229,.24); font-weight: 700; }
        @media (max-width: 800px) {
            .azevent-settings-page { margin-right: 12px; }
            .azevent-tabs { margin-bottom: 14px; }
            .azevent-grid, .azevent-prompt-body, .azevent-step-model-grid { grid-template-columns: 1fr; }
            .azevent-tabs { top: 10px; }
            .azevent-tab { justify-content: flex-start; }
            .azevent-version { position: static; display: inline-block; margin-top: 18px; }
            .azevent-flow { overflow-x: auto; }
            .azevent-card-header { flex-direction: column; }
            .azevent-legacy-actions { align-items: flex-start; flex-direction: column; }
        }
    </style>

    <div class="azevent-hero">
        <div class="azevent-hero-content">
            <div class="azevent-brand-row"><span class="azevent-brand-mark">✦</span><span class="azevent-brand-name">AzEvent Content Studio</span><span class="azevent-brand-label">Settings</span></div>
            <div class="azevent-eyebrow"><span>✦</span> AzEvent SEO Content</div>
            <h1><?php _e('AzEvent SEO Content Creator', 'azevent-seo-content'); ?></h1>
            <p><?php _e('Thiết lập API, thương hiệu và prompt để tạo mới hoặc viết lại nội dung sự kiện theo một quy trình nhất quán.', 'azevent-seo-content'); ?></p>
        </div>
        <div class="azevent-version">v<?php echo esc_html(AZEVENT_SEO_VERSION); ?></div>
    </div>

    <div class="azevent-flow" aria-label="AI content pipeline">
        <span class="azevent-flow-step"><span class="azevent-flow-dot"></span>Brand context</span><span class="azevent-flow-arrow">→</span>
        <span class="azevent-flow-step"><span class="azevent-flow-dot"></span>SEO intent</span><span class="azevent-flow-arrow">→</span>
        <span class="azevent-flow-step"><span class="azevent-flow-dot"></span>Rewrite / Create</span><span class="azevent-flow-arrow">→</span>
        <span class="azevent-flow-step"><span class="azevent-flow-dot"></span>SEO metadata</span><span class="azevent-flow-arrow">→</span>
        <span class="azevent-flow-step"><span class="azevent-flow-dot"></span>Draft review</span>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields('azevent_seo_settings_group'); ?>
        <div class="azevent-layout">
            <nav class="azevent-tabs" aria-label="Settings sections">
                <button type="button" class="azevent-tab is-active" data-tab="api" aria-selected="true"><span class="azevent-tab-icon">⚡</span><?php _e('AI Providers', 'azevent-seo-content'); ?></button>
                <button type="button" class="azevent-tab" data-tab="brand" aria-selected="false"><span class="azevent-tab-icon">◈</span><?php _e('Thương hiệu', 'azevent-seo-content'); ?></button>
                <button type="button" class="azevent-tab" data-tab="content-settings" aria-selected="false"><span class="azevent-tab-icon">文</span><?php _e('Nội dung', 'azevent-seo-content'); ?></button>
                <button type="button" class="azevent-tab" data-tab="prompts" aria-selected="false"><span class="azevent-tab-icon">✎</span><?php _e('AI Prompts', 'azevent-seo-content'); ?></button>
                <button type="button" class="azevent-tab" data-tab="lab-prompts" aria-selected="false"><span class="azevent-tab-icon">◫</span><?php _e('Workflow Lab Prompts', 'azevent-seo-content'); ?></button>
            </nav>

            <main>
                <section class="azevent-panel is-active" data-panel="api">
                    <div class="azevent-card">
                        <div class="azevent-card-header">
                            <div class="azevent-card-title">
                                <span class="azevent-section-icon">⚡</span>
                                <div>
                                <h2><?php _e('AzEvent API / AzEvent CLI API', 'azevent-seo-content'); ?></h2>
                                <p class="azevent-card-description"><?php _e('Hai endpoint AzEvent được tích hợp trực tiếp; không cần cài thêm plugin khác.', 'azevent-seo-content'); ?></p>
                                </div>
                            </div>
                            <div class="azevent-status <?php echo $azevent_api_ready ? 'is-ready' : 'is-pending'; ?>"><span class="azevent-status-dot"></span><?php echo $azevent_api_ready ? esc_html__('Đã cấu hình', 'azevent-seo-content') : esc_html__('Chưa cấu hình', 'azevent-seo-content'); ?></div>
                        </div>
                        <p class="azevent-note"><?php printf(esc_html__('Provider text mặc định điều khiển bốn bước nội dung. Ảnh đại diện dùng %s khi key của endpoint này đã được cấu hình.', 'azevent-seo-content'), esc_html(AzEvent_API_Client::get_provider_label($azevent_base_url))); ?></p>
                        <div class="azevent-grid">
                            <div class="azevent-field">
                                <label for="azevent_seo_text_provider"><?php _e('Provider text mặc định', 'azevent-seo-content'); ?></label>
                                <select id="azevent_seo_text_provider" name="azevent_seo_text_provider">
                                    <option value="azevent" <?php selected($azevent_text_provider, 'azevent'); ?>>AzEvent API / AzEvent CLI API</option>
                                    <option value="ckey" <?php selected($azevent_text_provider, 'ckey'); ?>>CKEY.VN</option>
                                </select>
                                <p class="azevent-help"><?php _e('Áp dụng khi một bước để ở chế độ dùng model mặc định.', 'azevent-seo-content'); ?></p>
                            </div>
                            <div class="azevent-field">
                                <label for="aprg_cliproxy_base_url"><?php _e('Môi trường API', 'azevent-seo-content'); ?></label>
                                <select id="aprg_cliproxy_base_url" name="aprg_cliproxy_base_url">
                                    <option value="<?php echo esc_attr(AzEvent_API_Client::DEFAULT_BASE_URL); ?>" <?php selected($azevent_base_url, AzEvent_API_Client::DEFAULT_BASE_URL); ?>>AzEvent CLI API — cliapi.azevent.vn (Mặc định)</option>
                                    <option value="<?php echo esc_attr(AzEvent_API_Client::REMOTE_BASE_URL); ?>" <?php selected($azevent_base_url, AzEvent_API_Client::REMOTE_BASE_URL); ?>>AzEvent API — api.azevent.vn</option>
                                    <?php if ($azevent_base_url === AzEvent_API_Client::LEGACY_LOCAL_BASE_URL) : ?>
                                        <option value="<?php echo esc_attr(AzEvent_API_Client::LEGACY_LOCAL_BASE_URL); ?>" selected>Local cũ — 192.168.1.5:8317</option>
                                    <?php endif; ?>
                                </select>
                                <p class="azevent-help"><?php _e('Plugin tự gọi endpoint /v1. Cấu hình Local cũ vẫn được giữ nếu website đang sử dụng.', 'azevent-seo-content'); ?></p>
                            </div>
                            <div class="azevent-field azevent-endpoint-key" data-endpoint="<?php echo esc_attr(AzEvent_API_Client::DEFAULT_BASE_URL); ?>">
                                <label for="azevent_cliapi_api_key"><?php _e('AzEvent CLI API Key — cliapi.azevent.vn', 'azevent-seo-content'); ?></label>
                                <input id="azevent_cliapi_api_key" type="password" name="azevent_cliapi_api_key" value="<?php echo esc_attr(get_option('azevent_cliapi_api_key', get_option('aprg_cliproxy_api_key', ''))); ?>" autocomplete="off" placeholder="sk-...">
                                <p class="azevent-help"><?php _e('Key dùng riêng cho https://cliapi.azevent.vn/v1.', 'azevent-seo-content'); ?></p>
                            </div>
                            <div class="azevent-field azevent-endpoint-key" data-endpoint="<?php echo esc_attr(AzEvent_API_Client::REMOTE_BASE_URL); ?>">
                                <label for="azevent_remote_api_key"><?php _e('AzEvent API Key — api.azevent.vn', 'azevent-seo-content'); ?></label>
                                <input id="azevent_remote_api_key" type="password" name="azevent_remote_api_key" value="<?php echo esc_attr(get_option('azevent_remote_api_key', get_option('aprg_cliproxy_api_key', ''))); ?>" autocomplete="off" placeholder="sk-...">
                                <p class="azevent-help"><?php _e('Key dùng riêng cho https://api.azevent.vn/v1.', 'azevent-seo-content'); ?></p>
                            </div>
                            <?php if ($azevent_base_url === AzEvent_API_Client::LEGACY_LOCAL_BASE_URL) : ?>
                                <div class="azevent-field azevent-endpoint-key" data-endpoint="<?php echo esc_attr(AzEvent_API_Client::LEGACY_LOCAL_BASE_URL); ?>">
                                    <label for="aprg_cliproxy_api_key"><?php _e('Local API Key — 192.168.1.5', 'azevent-seo-content'); ?></label>
                                    <input id="aprg_cliproxy_api_key" type="password" name="aprg_cliproxy_api_key" value="<?php echo esc_attr(get_option('aprg_cliproxy_api_key', '')); ?>" autocomplete="off">
                                    <p class="azevent-help"><?php _e('Chỉ dùng để duy trì cấu hình Local cũ.', 'azevent-seo-content'); ?></p>
                                </div>
                            <?php endif; ?>
                            <div class="azevent-field">
                                <label for="aprg_cliproxy_model"><?php _e('Text Model', 'azevent-seo-content'); ?></label>
                                <select id="aprg_cliproxy_model" name="aprg_cliproxy_model">
                                    <?php foreach ($azevent_text_models as $model_id => $model_label) : ?>
                                        <option value="<?php echo esc_attr($model_id); ?>" <?php selected($azevent_text_model, $model_id); ?>><?php echo esc_html($model_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="aprg_cliproxy_custom_models" name="aprg_cliproxy_custom_models" value="<?php echo esc_attr(wp_json_encode($azevent_custom_models)); ?>">
                                <div class="azevent-model-tools">
                                    <input id="azevent-new-text-model" type="text" placeholder="<?php esc_attr_e('Nhập model ID, ví dụ: claude-sonnet-4-6', 'azevent-seo-content'); ?>">
                                    <button type="button" class="azevent-model-add" id="azevent-add-text-model"><?php _e('＋ Thêm model', 'azevent-seo-content'); ?></button>
                                </div>
                                <div class="azevent-model-list" id="azevent-custom-model-list"></div>
                                <p class="azevent-help"><?php printf(esc_html__('Thêm model ID thủ công để dùng với %s. Nhấn Lưu cấu hình để lưu danh sách.', 'azevent-seo-content'), esc_html(AzEvent_API_Client::get_provider_label($azevent_base_url))); ?></p>
                            </div>
                            <div class="azevent-field">
                                <label for="aprg_seo_default_cliproxy_image_model"><?php _e('Image Model', 'azevent-seo-content'); ?></label>
                                <select id="aprg_seo_default_cliproxy_image_model" name="aprg_seo_default_cliproxy_image_model">
                                    <option value="gpt-image-2" <?php selected($azevent_image_model, 'gpt-image-2'); ?>>GPT Image 2</option>
                                    <option value="grok-imagine-image" <?php selected($azevent_image_model, 'grok-imagine-image'); ?>>Grok Imagine Image</option>
                                    <option value="gemini-3.1-flash-image" <?php selected($azevent_image_model, 'gemini-3.1-flash-image'); ?>>Gemini 3.1 Flash Image</option>
                                </select>
                            </div>
                        </div>
                        <div class="azevent-provider-card">
                            <div class="azevent-provider-card-header">
                                <div>
                                    <h3><?php _e('CKEY.VN Text API', 'azevent-seo-content'); ?></h3>
                                    <p><?php _e('Mặc định gọi Claude Messages. Có thể chuyển sang Auto hoặc OpenAI Chat cho model đặc biệt. CKey chỉ xử lý text.', 'azevent-seo-content'); ?></p>
                                </div>
                                <div class="azevent-status <?php echo $azevent_ckey_ready ? 'is-ready' : 'is-pending'; ?>"><span class="azevent-status-dot"></span><?php echo $azevent_ckey_ready ? esc_html__('Đã cấu hình', 'azevent-seo-content') : esc_html__('Chưa cấu hình', 'azevent-seo-content'); ?></div>
                            </div>
                            <div class="azevent-grid">
                                <div class="azevent-field">
                                    <label for="azevent_seo_ckey_api_key"><?php _e('CKey API Key', 'azevent-seo-content'); ?></label>
                                    <input id="azevent_seo_ckey_api_key" type="password" name="azevent_seo_ckey_api_key" value="<?php echo esc_attr(get_option('azevent_seo_ckey_api_key', '')); ?>" autocomplete="off" placeholder="ckey-...">
                                    <p class="azevent-help"><?php _e('Key được lưu trên server và gửi trực tiếp tới api.xah.io.', 'azevent-seo-content'); ?></p>
                                </div>
                                <div class="azevent-field">
                                    <label for="azevent_seo_ckey_model"><?php _e('CKey Model mặc định', 'azevent-seo-content'); ?></label>
                                    <select id="azevent_seo_ckey_model" name="azevent_seo_ckey_model">
                                        <option value="" <?php selected($azevent_ckey_model, ''); ?>><?php _e('Chưa chọn model — hãy thêm thủ công', 'azevent-seo-content'); ?></option>
                                        <?php foreach ($azevent_ckey_models as $model_id => $model_label) : ?>
                                            <option value="<?php echo esc_attr($model_id); ?>" <?php selected($azevent_ckey_model, $model_id); ?>><?php echo esc_html($model_label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" id="azevent_seo_ckey_custom_models" name="azevent_seo_ckey_custom_models" value="<?php echo esc_attr(wp_json_encode($azevent_ckey_custom_models)); ?>">
                                    <div class="azevent-model-tools">
                                        <input id="azevent-new-ckey-model" type="text" placeholder="owner/model-id">
                                        <button type="button" class="azevent-model-add" id="azevent-add-ckey-model"><?php _e('＋ Thêm model', 'azevent-seo-content'); ?></button>
                                    </div>
                                    <div class="azevent-model-list" id="azevent-ckey-model-list"></div>
                                    <p class="azevent-help"><?php _e('Plugin không nạp sẵn hoặc tự tải model CKey. Hãy nhập chính xác model ID bạn muốn sử dụng.', 'azevent-seo-content'); ?></p>
                                </div>
                                <div class="azevent-field">
                                    <label for="azevent_seo_ckey_api_format"><?php _e('Định dạng gọi CKey API', 'azevent-seo-content'); ?></label>
                                    <select id="azevent_seo_ckey_api_format" name="azevent_seo_ckey_api_format">
                                        <option value="messages" <?php selected($azevent_ckey_api_format, 'messages'); ?>>Claude Messages — /v1/messages (Mặc định)</option>
                                        <option value="auto" <?php selected($azevent_ckey_api_format, 'auto'); ?>>Tự nhận diện theo model</option>
                                        <option value="chat" <?php selected($azevent_ckey_api_format, 'chat'); ?>>OpenAI Chat — /v1/chat/completions</option>
                                    </select>
                                    <p class="azevent-help"><?php _e('Giữ Claude Messages nếu bạn chủ yếu dùng model Claude từ CKey.', 'azevent-seo-content'); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="azevent-model-routing">
                            <div class="azevent-model-routing-header">
                                <div>
                                    <h3><?php _e('Model theo từng bước nội dung', 'azevent-seo-content'); ?></h3>
                                    <p><?php _e('Chọn model riêng cho Search Intent, Outline, Content và SEO. Để mặc định nếu muốn tất cả dùng Text Model phía trên.', 'azevent-seo-content'); ?></p>
                                </div>
                                <span class="azevent-model-routing-badge"><?php _e('4 bước', 'azevent-seo-content'); ?></span>
                            </div>
                            <div class="azevent-step-model-grid">
                                <?php foreach ($azevent_step_model_fields as $step_key => $step_field) : ?>
                                    <div class="azevent-field azevent-step-model">
                                        <label for="azevent_seo_<?php echo esc_attr($step_key); ?>_model">
                                            <span><?php echo esc_html($step_field['label']); ?></span>
                                            <small><?php echo esc_html($step_field['hint']); ?></small>
                                        </label>
                                        <select class="azevent-step-model-select" id="azevent_seo_<?php echo esc_attr($step_key); ?>_model" name="azevent_seo_<?php echo esc_attr($step_key); ?>_model">
                                            <option value="" <?php selected($azevent_step_models[$step_key], ''); ?>><?php printf(esc_html__('Dùng provider mặc định — %s', 'azevent-seo-content'), esc_html($azevent_default_text_label)); ?></option>
                                            <optgroup label="<?php echo esc_attr(AzEvent_API_Client::get_provider_label($azevent_base_url)); ?>">
                                                <?php foreach ($azevent_text_models as $model_id => $model_label) : ?>
                                                    <option value="<?php echo esc_attr($model_id); ?>" <?php selected($azevent_step_models[$step_key], $model_id); ?>><?php echo esc_html($model_label); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <optgroup label="CKEY.VN">
                                                <?php foreach ($azevent_ckey_models as $model_id => $model_label) : ?>
                                                    <?php $model_reference = AzEvent_CKey_Client::model_reference($model_id); ?>
                                                    <option value="<?php echo esc_attr($model_reference); ?>" <?php selected($azevent_step_models[$step_key], $model_reference); ?>><?php echo esc_html($model_label); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <details class="azevent-legacy">
                            <summary>
                                <span class="azevent-legacy-title">
                                    <span class="azevent-legacy-icon">↻</span>
                                    <span><?php _e('Fallback API cũ', 'azevent-seo-content'); ?><span class="azevent-legacy-description"><?php printf(esc_html__('Dùng khi %s không được cấu hình.', 'azevent-seo-content'), esc_html(AzEvent_API_Client::get_provider_label($azevent_base_url))); ?></span></span>
                                </span>
                                <span class="azevent-legacy-badge"><?php _e('Tuỳ chọn', 'azevent-seo-content'); ?></span>
                            </summary>
                            <div class="azevent-grid azevent-legacy-grid">
                                <div class="azevent-field">
                                    <label for="azevent_seo_openai_key"><?php _e('OpenAI API Key', 'azevent-seo-content'); ?></label>
                                    <input id="azevent_seo_openai_key" type="password" name="azevent_seo_openai_key" value="<?php echo esc_attr(get_option('azevent_seo_openai_key')); ?>" autocomplete="off">
                                    <label for="azevent_seo_openai_model"><?php _e('Model fallback mặc định', 'azevent-seo-content'); ?></label>
                                    <select id="azevent_seo_openai_model" name="azevent_seo_openai_model">
                                        <?php foreach ($azevent_legacy_openai_models as $model) : ?>
                                            <option value="<?php echo esc_attr($model); ?>" <?php selected($azevent_legacy_openai_model, $model); ?>><?php echo esc_html($model); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="azevent-field">
                                    <label for="azevent_seo_anthropic_key"><?php _e('Anthropic API Key', 'azevent-seo-content'); ?></label>
                                    <input id="azevent_seo_anthropic_key" type="password" name="azevent_seo_anthropic_key" value="<?php echo esc_attr(get_option('azevent_seo_anthropic_key')); ?>" autocomplete="off">
                                    <label for="azevent_seo_anthropic_model"><?php _e('Model fallback mặc định', 'azevent-seo-content'); ?></label>
                                    <select id="azevent_seo_anthropic_model" name="azevent_seo_anthropic_model">
                                        <?php foreach ($azevent_legacy_anthropic_models as $model) : ?>
                                            <option value="<?php echo esc_attr($model); ?>" <?php selected($azevent_legacy_anthropic_model, $model); ?>><?php echo esc_html($model); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="azevent-legacy-actions">
                                <button type="button" class="azevent-legacy-refresh" id="azevent-refresh-legacy-models"><?php _e('↻ Tải model mới nhất', 'azevent-seo-content'); ?></button>
                                <span class="azevent-legacy-status" id="azevent-legacy-model-status" aria-live="polite"><?php _e('Nhập API key rồi bấm Lưu cấu hình để tải model.', 'azevent-seo-content'); ?></span>
                            </div>
                        </details>
                    </div>
                </section>

                <section class="azevent-panel" data-panel="brand">
                    <div class="azevent-card">
                        <div class="azevent-card-header">
                            <div class="azevent-card-title">
                                <span class="azevent-section-icon">◈</span>
                                <div>
                                <h2><?php _e('Thông tin thương hiệu', 'azevent-seo-content'); ?></h2>
                                <p class="azevent-card-description"><?php _e('Dữ liệu này được đưa vào prompt để nội dung nhất quán với dịch vụ tổ chức sự kiện.', 'azevent-seo-content'); ?></p>
                                </div>
                            </div>
                            <div class="azevent-brand-reset-actions">
                                <button type="button" class="azevent-legacy-refresh" id="azevent-reset-brand-defaults"><?php _e('↺ Khôi phục mặc định AzEvent', 'azevent-seo-content'); ?></button>
                                <span class="azevent-brand-reset-status" id="azevent-brand-reset-status" aria-live="polite"></span>
                            </div>
                        </div>
                        <div class="azevent-field">
                            <label for="azevent_seo_brand_name"><?php _e('Tên thương hiệu', 'azevent-seo-content'); ?></label>
                            <input id="azevent_seo_brand_name" type="text" name="azevent_seo_brand_name" value="<?php echo esc_attr($azevent_brand_profile['azevent_seo_brand_name']); ?>">
                        </div>
                        <div class="azevent-field">
                            <label for="azevent_seo_brand_info"><?php _e('Thông tin thương hiệu', 'azevent-seo-content'); ?></label>
                            <textarea id="azevent_seo_brand_info" name="azevent_seo_brand_info" rows="7"><?php echo esc_textarea($azevent_brand_profile['azevent_seo_brand_info']); ?></textarea>
                            <p class="azevent-help"><?php _e('Ví dụ: năng lực, kinh nghiệm, khu vực phục vụ, khách hàng tiêu biểu.', 'azevent-seo-content'); ?></p>
                        </div>
                        <div class="azevent-field">
                            <label for="azevent_seo_brand_solution"><?php _e('Giải pháp / dịch vụ cung cấp', 'azevent-seo-content'); ?></label>
                            <textarea id="azevent_seo_brand_solution" name="azevent_seo_brand_solution" rows="8"><?php echo esc_textarea($azevent_brand_profile['azevent_seo_brand_solution']); ?></textarea>
                            <p class="azevent-help"><?php _e('Mô tả dịch vụ, quy trình, điểm khác biệt và lời kêu gọi hành động.', 'azevent-seo-content'); ?></p>
                        </div>
                        <p class="azevent-note"><?php _e('Plugin luôn ưu tiên nội dung bạn tự nhập. Nút khôi phục chỉ điền lại hồ sơ AzEvent mặc định và chỉ có hiệu lực sau khi bấm Lưu cấu hình.', 'azevent-seo-content'); ?></p>
                    </div>
                </section>

                <section class="azevent-panel" data-panel="content-settings">
                    <div class="azevent-card">
                        <div class="azevent-card-header">
                            <div class="azevent-card-title">
                                <span class="azevent-section-icon">文</span>
                                <div>
                                <h2><?php _e('Thiết lập nội dung', 'azevent-seo-content'); ?></h2>
                                <p class="azevent-card-description"><?php _e('Các giá trị mặc định dùng khi mở trình tạo bài viết và chạy batch keyword.', 'azevent-seo-content'); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="azevent-field">
                            <label for="azevent_seo_default_language"><?php _e('Ngôn ngữ mặc định', 'azevent-seo-content'); ?></label>
                            <select id="azevent_seo_default_language" name="azevent_seo_default_language">
                                <option value="Vietnamese" <?php selected($azevent_default_language, 'Vietnamese'); ?>>Tiếng Việt</option>
                                <option value="English" <?php selected($azevent_default_language, 'English'); ?>>English</option>
                            </select>
                            <p class="azevent-help"><?php _e('Được áp dụng tự động khi tạo mới, viết lại và chạy Background Queue.', 'azevent-seo-content'); ?></p>
                        </div>
                        <div class="azevent-field">
                            <label><?php _e('Quy trình trên trình duyệt', 'azevent-seo-content'); ?></label>
                            <input type="hidden" name="azevent_seo_browser_auto_advance" value="0">
                            <label class="azevent-workflow-option" for="azevent_seo_browser_auto_advance">
                                <input id="azevent_seo_browser_auto_advance" type="checkbox" name="azevent_seo_browser_auto_advance" value="1" <?php checked($azevent_browser_auto_advance, 1); ?>>
                                <span>
                                    <strong><?php _e('Bỏ qua duyệt thủ công', 'azevent-seo-content'); ?></strong>
                                    <span><?php _e('Khi tích chọn, Content Studio tự chạy Search Intent → Outline → Content → SEO → tạo ảnh và lưu Draft. Khi bỏ tích, plugin dừng để bạn duyệt từng bước.', 'azevent-seo-content'); ?></span>
                                </span>
                            </label>
                        </div>
                    </div>
                </section>

                <section class="azevent-panel" data-panel="prompts">
                    <div class="azevent-card">
                        <div class="azevent-card-header">
                            <div class="azevent-card-title">
                                <span class="azevent-section-icon">✎</span>
                                <div>
                                <h2><?php _e('AI Prompts', 'azevent-seo-content'); ?></h2>
                                <p class="azevent-card-description"><?php _e('Mở từng nhóm để tinh chỉnh cách AI phân tích, viết mới hoặc viết lại bài.', 'azevent-seo-content'); ?></p>
                                </div>
                            </div>
                        </div>
                        <p class="azevent-note"><?php _e('Placeholder hỗ trợ: {keyword}, {secondary_keywords}, {language}, {outline_focus}, {brand_name}, {brand_info}, {brand_solution}, {search_intent}, {outline}, {content}, {existing_title}, {existing_content}, {existing_excerpt}, {existing_slug}, {rewrite_goal}.', 'azevent-seo-content'); ?></p>
                        <div class="azevent-token-card">
                            <div class="azevent-token-header">
                                <span class="azevent-token-title"><?php _e('Prompt Variables', 'azevent-seo-content'); ?></span>
                                <span class="azevent-token-subtitle"><?php _e('Click biến để chèn vào textarea đang chọn', 'azevent-seo-content'); ?></span>
                            </div>
                            <div class="azevent-token-list">
                                <?php foreach ($prompt_tokens as $token => $description) : ?>
                                    <button type="button" class="azevent-token" data-token="<?php echo esc_attr($token); ?>" title="<?php echo esc_attr($description); ?>"><?php echo esc_html($token); ?></button>
                                <?php endforeach; ?>
                            </div>
                            <p class="azevent-token-description"><?php _e('Bạn không cần tự thay các biến này. Khi chạy, plugin sẽ tự lấy dữ liệu tương ứng. Các biến {existing_*} chỉ có giá trị ở chế độ Viết lại bài hiện tại.', 'azevent-seo-content'); ?></p>
                        </div>
                        <?php foreach ($prompt_sections as $key => $section) : ?>
                            <details class="azevent-prompt" <?php echo $key === 'content' ? 'open' : ''; ?>>
                                <summary>
                                    <span><span class="azevent-prompt-title"><?php echo esc_html($section['label']); ?></span><span class="azevent-prompt-description"><?php echo esc_html($section['description']); ?></span></span>
                                </summary>
                                <div class="azevent-prompt-body">
                                    <div class="azevent-field">
                                        <label for="azevent_seo_<?php echo esc_attr($key); ?>_system">System Prompt</label>
                                        <textarea id="azevent_seo_<?php echo esc_attr($key); ?>_system" name="azevent_seo_<?php echo esc_attr($key); ?>_system" rows="7"><?php echo esc_textarea($get_prompt("azevent_seo_{$key}_system", $default_prompts[$key]['system'])); ?></textarea>
                                    </div>
                                    <div class="azevent-field">
                                        <label for="azevent_seo_<?php echo esc_attr($key); ?>_user">User Prompt</label>
                                        <textarea id="azevent_seo_<?php echo esc_attr($key); ?>_user" name="azevent_seo_<?php echo esc_attr($key); ?>_user" rows="7"><?php echo esc_textarea($get_prompt("azevent_seo_{$key}_user", $default_prompts[$key]['user'])); ?></textarea>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="azevent-panel" data-panel="lab-prompts">
                    <div class="azevent-card">
                        <div class="azevent-card-header">
                            <div class="azevent-card-title">
                                <span class="azevent-section-icon">◫</span>
                                <div>
                                <h2><?php _e('Workflow Lab Prompts', 'azevent-seo-content'); ?></h2>
                                <p class="azevent-card-description"><?php _e('Prompt và model độc lập cho 5 bước SEO Workflow Lab; không ảnh hưởng Content Studio.', 'azevent-seo-content'); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="azevent-serp-box">
                            <h3><?php _e('Tự động nghiên cứu đối thủ bằng SERP thật', 'azevent-seo-content'); ?></h3>
                            <p><?php _e('Khi ô “Dữ liệu đối thủ / SERP thực tế” trong Workflow Lab để trống, plugin dùng SerpApi để lấy kết quả Google, loại domain của website hiện tại và đọc có giới hạn title, meta, H1–H3 của các trang đầu. Snapshot được cache 6 giờ và lưu trong checkpoint.', 'azevent-seo-content'); ?></p>
                            <div class="azevent-grid">
                                <div class="azevent-field">
                                    <label for="azevent_lab_serpapi_key"><?php _e('SerpApi API Key', 'azevent-seo-content'); ?></label>
                                    <input id="azevent_lab_serpapi_key" type="password" name="azevent_lab_serpapi_key" value="<?php echo esc_attr(get_option('azevent_lab_serpapi_key', '')); ?>" autocomplete="off">
                                    <p class="azevent-help"><?php _e('Không đưa key vào prompt hoặc JavaScript frontend.', 'azevent-seo-content'); ?></p>
                                </div>
                                <div class="azevent-field">
                                    <label for="azevent_lab_serp_location"><?php _e('Vị trí tìm kiếm', 'azevent-seo-content'); ?></label>
                                    <input id="azevent_lab_serp_location" type="text" name="azevent_lab_serp_location" value="<?php echo esc_attr(get_option('azevent_lab_serp_location', 'Vietnam')); ?>" placeholder="Vietnam hoặc Hanoi, Vietnam">
                                </div>
                                <div class="azevent-field">
                                    <label for="azevent_lab_serp_country"><?php _e('Mã quốc gia Google', 'azevent-seo-content'); ?></label>
                                    <input id="azevent_lab_serp_country" type="text" name="azevent_lab_serp_country" value="<?php echo esc_attr(get_option('azevent_lab_serp_country', 'vn')); ?>" maxlength="2" placeholder="vn">
                                </div>
                                <div class="azevent-field">
                                    <label for="azevent_lab_serp_language"><?php _e('Ngôn ngữ SERP', 'azevent-seo-content'); ?></label>
                                    <input id="azevent_lab_serp_language" type="text" name="azevent_lab_serp_language" value="<?php echo esc_attr(get_option('azevent_lab_serp_language', 'vi')); ?>" maxlength="5" placeholder="vi">
                                </div>
                                <div class="azevent-field">
                                    <label for="azevent_lab_serp_result_count"><?php _e('Số kết quả organic', 'azevent-seo-content'); ?></label>
                                    <select id="azevent_lab_serp_result_count" name="azevent_lab_serp_result_count">
                                        <option value="5" <?php selected(absint(get_option('azevent_lab_serp_result_count', 10)), 5); ?>>5</option>
                                        <option value="10" <?php selected(absint(get_option('azevent_lab_serp_result_count', 10)), 10); ?>>10</option>
                                    </select>
                                </div>
                                <div class="azevent-field">
                                    <label for="azevent_lab_serp_fetch_pages"><?php _e('Đọc cấu trúc trang đối thủ', 'azevent-seo-content'); ?></label>
                                    <select id="azevent_lab_serp_fetch_pages" name="azevent_lab_serp_fetch_pages">
                                        <option value="0" <?php selected(absint(get_option('azevent_lab_serp_fetch_pages', 2)), 0); ?>><?php _e('Không đọc trang — chỉ dùng SERP', 'azevent-seo-content'); ?></option>
                                        <option value="1" <?php selected(absint(get_option('azevent_lab_serp_fetch_pages', 2)), 1); ?>><?php _e('Đọc 1 trang đầu — nhanh', 'azevent-seo-content'); ?></option>
                                        <option value="2" <?php selected(absint(get_option('azevent_lab_serp_fetch_pages', 2)), 2); ?>><?php _e('Đọc 2 trang đầu — khuyến nghị', 'azevent-seo-content'); ?></option>
                                        <option value="3" <?php selected(absint(get_option('azevent_lab_serp_fetch_pages', 2)), 3); ?>><?php _e('Đọc 3 trang đầu', 'azevent-seo-content'); ?></option>
                                        <option value="5" <?php selected(absint(get_option('azevent_lab_serp_fetch_pages', 2)), 5); ?>><?php _e('Đọc 5 trang đầu — chậm hơn', 'azevent-seo-content'); ?></option>
                                    </select>
                                    <p class="azevent-help"><?php _e('Chỉ lưu title, meta và tối đa 24 heading; không sao chép toàn bộ nội dung.', 'azevent-seo-content'); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="azevent-serp-box">
                            <h3><?php _e('Cách viết nội dung từ Outline', 'azevent-seo-content'); ?></h3>
                            <input type="hidden" name="azevent_lab_split_content_by_outline" value="0">
                            <label class="azevent-workflow-option" for="azevent_lab_split_content_by_outline">
                                <input id="azevent_lab_split_content_by_outline" type="checkbox" name="azevent_lab_split_content_by_outline" value="1" <?php checked($azevent_lab_split_content, 1); ?>>
                                <span>
                                    <strong><?php _e('Tách Content và viết lần lượt theo từng H2', 'azevent-seo-content'); ?></strong>
                                    <span><?php _e('Khi bật, mỗi H2 chạy thành một background job riêng và lưu checkpoint sau từng phần. Khi tắt, plugin vẫn viết toàn bài trong một request như hiện tại.', 'azevent-seo-content'); ?></span>
                                </span>
                            </label>
                            <p class="azevent-help"><?php _e('Chế độ tách giúp giảm timeout và cho phép tiếp tục từ H2 bị lỗi. Sau khi ghép, bước Links & Quality Gate vẫn kiểm tra toàn bộ bài để giảm lặp ý và giữ giọng văn nhất quán.', 'azevent-seo-content'); ?></p>
                        </div>
                        <p class="azevent-note"><?php _e('Bộ mặc định ưu tiên people-first content, intent, information gain, bằng chứng thương hiệu, internal link thật và kiểm soát spam. Không prompt nào có thể đảm bảo thứ hạng; kết quả còn phụ thuộc website, cạnh tranh, kỹ thuật, backlink và dữ liệu thực tế.', 'azevent-seo-content'); ?></p>
                        <div class="azevent-lab-reset-row">
                            <button type="button" class="azevent-legacy-refresh" id="azevent-reset-lab-prompts"><?php _e('↺ Khôi phục bộ prompt SEO nâng cao', 'azevent-seo-content'); ?></button>
                            <span class="azevent-lab-reset-status" id="azevent-lab-reset-status" aria-live="polite"></span>
                        </div>
                        <div class="azevent-token-card">
                            <div class="azevent-token-header">
                                <span class="azevent-token-title"><?php _e('Workflow Lab Variables', 'azevent-seo-content'); ?></span>
                                <span class="azevent-token-subtitle"><?php _e('Click biến để chèn vào textarea đang chọn', 'azevent-seo-content'); ?></span>
                            </div>
                            <div class="azevent-token-list">
                                <?php foreach ($lab_prompt_tokens as $token => $description) : ?>
                                    <button type="button" class="azevent-token" data-token="<?php echo esc_attr($token); ?>" title="<?php echo esc_attr($description); ?>"><?php echo esc_html($token); ?></button>
                                <?php endforeach; ?>
                            </div>
                            <p class="azevent-token-description"><?php _e('Plugin tự thay các biến khi chạy. Dữ liệu bước trước chỉ có giá trị sau khi bước đó đã hoàn tất hoặc được bạn chỉnh sửa và tiếp tục.', 'azevent-seo-content'); ?></p>
                        </div>
                        <?php foreach ($lab_prompt_sections as $key => $section) : ?>
                            <?php
                            $lab_model = $azevent_lab_step_models[$key] ?? '';
                            $fallback_model = $azevent_step_models[$section['fallback_step']] ?? '';
                            $fallback_label = $fallback_model === ''
                                ? $azevent_default_text_label
                                : (AzEvent_CKey_Client::is_model_reference($fallback_model)
                                    ? 'CKEY.VN — ' . AzEvent_CKey_Client::strip_model_prefix($fallback_model)
                                    : AzEvent_API_Client::get_provider_label($azevent_base_url) . ' — ' . $fallback_model);
                            ?>
                            <details class="azevent-prompt" <?php echo $key === 'content' ? 'open' : ''; ?>>
                                <summary>
                                    <span><span class="azevent-prompt-title"><?php echo esc_html($section['label']); ?></span><span class="azevent-prompt-description"><?php echo esc_html($section['description']); ?></span></span>
                                </summary>
                                <div class="azevent-lab-prompt-model">
                                    <div class="azevent-field">
                                        <label for="azevent_lab_<?php echo esc_attr($key); ?>_model"><?php _e('Model riêng cho bước này', 'azevent-seo-content'); ?></label>
                                        <select id="azevent_lab_<?php echo esc_attr($key); ?>_model" name="azevent_lab_<?php echo esc_attr($key); ?>_model">
                                            <option value="" <?php selected($lab_model, ''); ?>><?php printf(esc_html__('Kế thừa cấu hình hiện tại — %s', 'azevent-seo-content'), esc_html($fallback_label)); ?></option>
                                            <optgroup label="<?php echo esc_attr(AzEvent_API_Client::get_provider_label($azevent_base_url)); ?>">
                                                <?php foreach ($azevent_text_models as $model_id => $model_label) : ?>
                                                    <option value="<?php echo esc_attr($model_id); ?>" <?php selected($lab_model, $model_id); ?>><?php echo esc_html($model_label); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <optgroup label="CKEY.VN">
                                                <?php foreach ($azevent_ckey_models as $model_id => $model_label) : ?>
                                                    <?php $model_reference = AzEvent_CKey_Client::model_reference($model_id); ?>
                                                    <option value="<?php echo esc_attr($model_reference); ?>" <?php selected($lab_model, $model_reference); ?>><?php echo esc_html($model_label); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        </select>
                                    </div>
                                </div>
                                <div class="azevent-prompt-body">
                                    <div class="azevent-field">
                                        <label for="azevent_lab_<?php echo esc_attr($key); ?>_system">System Prompt</label>
                                        <textarea id="azevent_lab_<?php echo esc_attr($key); ?>_system" name="azevent_lab_<?php echo esc_attr($key); ?>_system" rows="12"><?php echo esc_textarea($get_prompt("azevent_lab_{$key}_system", $lab_prompt_defaults[$key]['system'])); ?></textarea>
                                    </div>
                                    <div class="azevent-field">
                                        <label for="azevent_lab_<?php echo esc_attr($key); ?>_user">User Prompt</label>
                                        <textarea id="azevent_lab_<?php echo esc_attr($key); ?>_user" name="azevent_lab_<?php echo esc_attr($key); ?>_user" rows="12"><?php echo esc_textarea($get_prompt("azevent_lab_{$key}_user", $lab_prompt_defaults[$key]['user'])); ?></textarea>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </section>
            </main>
        </div>
        <div class="azevent-footer">
            <?php submit_button(__('Lưu cấu hình', 'azevent-seo-content'), 'primary large', 'submit', false); ?>
        </div>
    </form>

    <script>
        (function () {
            var legacyRefreshButton = document.getElementById('azevent-refresh-legacy-models');
            var legacyStatus = document.getElementById('azevent-legacy-model-status');
            var openaiModelSelect = document.getElementById('azevent_seo_openai_model');
            var anthropicModelSelect = document.getElementById('azevent_seo_anthropic_model');
            var legacyModelsAjax = {
                url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
                nonce: <?php echo wp_json_encode(wp_create_nonce('azevent_fetch_legacy_models')); ?>
            };
            var ckeyModelSelect = document.getElementById('azevent_seo_ckey_model');
            var ckeyCustomModelsField = document.getElementById('azevent_seo_ckey_custom_models');
            var ckeyModelInput = document.getElementById('azevent-new-ckey-model');
            var ckeyAddModelButton = document.getElementById('azevent-add-ckey-model');
            var ckeyModelList = document.getElementById('azevent-ckey-model-list');
            var apiEndpointSelect = document.getElementById('aprg_cliproxy_base_url');
            var endpointKeyFields = Array.prototype.slice.call(document.querySelectorAll('.azevent-endpoint-key'));

            function syncEndpointKeyField() {
                if (!apiEndpointSelect) {
                    return;
                }
                endpointKeyFields.forEach(function (field) {
                    field.hidden = field.dataset.endpoint !== apiEndpointSelect.value;
                });
            }

            if (apiEndpointSelect) {
                apiEndpointSelect.addEventListener('change', syncEndpointKeyField);
                syncEndpointKeyField();
            }

            function replaceLegacyModels(select, models) {
                if (!select || !Array.isArray(models) || !models.length) {
                    return;
                }
                var currentValue = select.value;
                select.innerHTML = '';
                models.forEach(function (model) {
                    var option = document.createElement('option');
                    option.value = model;
                    option.textContent = model;
                    select.appendChild(option);
                });
                select.value = models.indexOf(currentValue) !== -1 ? currentValue : models[0];
            }

            function refreshLegacyModels() {
                if (!legacyRefreshButton) {
                    return;
                }
                legacyRefreshButton.disabled = true;
                legacyStatus.className = 'azevent-legacy-status';
                legacyStatus.textContent = '<?php echo esc_js(__('Đang tải danh sách model...', 'azevent-seo-content')); ?>';

                var body = new URLSearchParams({
                    action: 'azevent_fetch_legacy_models',
                    nonce: legacyModelsAjax.nonce
                });
                fetch(legacyModelsAjax.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                }).then(function (response) {
                    return response.json();
                }).then(function (response) {
                    var data = response.data || {};
                    if (!response.success) {
                        throw new Error(data.message || '<?php echo esc_js(__('Không thể tải model.', 'azevent-seo-content')); ?>');
                    }
                    var models = data.models || {};
                    replaceLegacyModels(openaiModelSelect, models.openai || []);
                    replaceLegacyModels(anthropicModelSelect, models.anthropic || []);
                    legacyStatus.className = 'azevent-legacy-status ' + (Object.keys(data.errors || {}).length ? 'is-error' : 'is-success');
                    legacyStatus.textContent = data.message || '<?php echo esc_js(__('Đã cập nhật model.', 'azevent-seo-content')); ?>';
                }).catch(function (error) {
                    legacyStatus.className = 'azevent-legacy-status is-error';
                    legacyStatus.textContent = error.message;
                }).finally(function () {
                    legacyRefreshButton.disabled = false;
                });
            }

            if (legacyRefreshButton) {
                legacyRefreshButton.addEventListener('click', refreshLegacyModels);
                if (new URLSearchParams(window.location.search).get('settings-updated') === '1') {
                    var legacyPanel = document.querySelector('.azevent-legacy');
                    if (legacyPanel) {
                        legacyPanel.open = true;
                    }
                    refreshLegacyModels();
                }
            }

            var stepModelSelects = Array.prototype.slice.call(document.querySelectorAll('.azevent-step-model-select'));

            function addCKeyModelOption(model, custom) {
                if (!model) {
                    return;
                }
                if (!Array.prototype.some.call(ckeyModelSelect.options, function (option) { return option.value === model; })) {
                    var defaultOption = document.createElement('option');
                    defaultOption.value = model;
                    defaultOption.textContent = model + (custom ? ' (Custom)' : '');
                    if (custom) {
                        defaultOption.dataset.ckeyCustom = '1';
                    }
                    ckeyModelSelect.appendChild(defaultOption);
                }
                stepModelSelects.forEach(function (select) {
                    var reference = 'ckey::' + model;
                    if (Array.prototype.some.call(select.options, function (option) { return option.value === reference; })) {
                        return;
                    }
                    var group = select.querySelector('optgroup[label="CKEY.VN"]');
                    if (!group) {
                        group = document.createElement('optgroup');
                        group.label = 'CKEY.VN';
                        select.appendChild(group);
                    }
                    var option = document.createElement('option');
                    option.value = reference;
                    option.textContent = model + (custom ? ' (Custom)' : '');
                    if (custom) {
                        option.dataset.ckeyCustom = '1';
                    }
                    group.appendChild(option);
                });
            }

            var ckeyCustomModels = [];
            try {
                ckeyCustomModels = JSON.parse(ckeyCustomModelsField.value || '[]');
            } catch (error) {
                ckeyCustomModels = [];
            }

            function syncCKeyCustomModels() {
                ckeyCustomModelsField.value = JSON.stringify(ckeyCustomModels);
                ckeyModelList.innerHTML = '';
                ckeyCustomModels.forEach(function (model) {
                    var chip = document.createElement('span');
                    chip.className = 'azevent-model-chip';
                    chip.appendChild(document.createTextNode(model));
                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'azevent-model-remove';
                    remove.setAttribute('aria-label', 'Remove ' + model);
                    remove.textContent = '×';
                    remove.addEventListener('click', function () {
                        ckeyCustomModels = ckeyCustomModels.filter(function (item) { return item !== model; });
                        Array.prototype.slice.call(ckeyModelSelect.options).forEach(function (option) {
                            if (option.value === model && option.dataset.ckeyCustom === '1') {
                                option.remove();
                            }
                        });
                        stepModelSelects.forEach(function (select) {
                            Array.prototype.slice.call(select.options).forEach(function (option) {
                                if (option.value === 'ckey::' + model && option.dataset.ckeyCustom === '1') {
                                    option.remove();
                                }
                            });
                        });
                        syncCKeyCustomModels();
                    });
                    chip.appendChild(remove);
                    ckeyModelList.appendChild(chip);
                });
            }

            if (ckeyAddModelButton) {
                ckeyAddModelButton.addEventListener('click', function () {
                    var model = ckeyModelInput.value.trim().replace(/^ckey::/, '');
                    if (!model || ckeyCustomModels.indexOf(model) !== -1) {
                        return;
                    }
                    ckeyCustomModels.push(model);
                    addCKeyModelOption(model, true);
                    ckeyModelSelect.value = model;
                    ckeyModelInput.value = '';
                    syncCKeyCustomModels();
                });
                ckeyModelInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        ckeyAddModelButton.click();
                    }
                });
                syncCKeyCustomModels();
            }

            var customModelsField = document.getElementById('aprg_cliproxy_custom_models');
            var modelSelect = document.getElementById('aprg_cliproxy_model');
            var modelInput = document.getElementById('azevent-new-text-model');
            var addModelButton = document.getElementById('azevent-add-text-model');
            var modelList = document.getElementById('azevent-custom-model-list');
            var customModels = [];

            try {
                customModels = JSON.parse(customModelsField.value || '[]');
            } catch (error) {
                customModels = [];
            }

            function syncCustomModels() {
                customModelsField.value = JSON.stringify(customModels);
                modelList.innerHTML = '';
                customModels.forEach(function (model) {
                    var chip = document.createElement('span');
                    chip.className = 'azevent-model-chip';
                    chip.appendChild(document.createTextNode(model));
                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'azevent-model-remove';
                    remove.setAttribute('aria-label', 'Remove ' + model);
                    remove.textContent = '×';
                    remove.addEventListener('click', function () {
                        customModels = customModels.filter(function (item) { return item !== model; });
                        [modelSelect].concat(stepModelSelects).forEach(function (select) {
                            for (var index = select.options.length - 1; index >= 0; index--) {
                                if (select.options[index].value === model) {
                                    select.remove(index);
                                }
                            }
                        });
                        if (modelSelect.value === model) {
                            modelSelect.value = modelSelect.options[0] ? modelSelect.options[0].value : '';
                        }
                        syncCustomModels();
                    });
                    chip.appendChild(remove);
                    modelList.appendChild(chip);
                });
            }

            addModelButton.addEventListener('click', function () {
                var model = modelInput.value.trim();
                if (!model || customModels.indexOf(model) !== -1) {
                    return;
                }
                customModels.push(model);
                var option = document.createElement('option');
                option.value = model;
                option.textContent = model + ' (Custom)';
                modelSelect.appendChild(option);
                stepModelSelects.forEach(function (select) {
                    var stepOption = option.cloneNode(true);
                    select.appendChild(stepOption);
                });
                modelSelect.value = model;
                modelInput.value = '';
                syncCustomModels();
            });

            modelInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    addModelButton.click();
                }
            });
            syncCustomModels();

            var brandResetButton = document.getElementById('azevent-reset-brand-defaults');
            var brandResetStatus = document.getElementById('azevent-brand-reset-status');
            var brandDefaultValues = <?php echo wp_json_encode($azevent_brand_defaults); ?>;

            if (brandResetButton) {
                brandResetButton.addEventListener('click', function () {
                    if (!window.confirm('<?php echo esc_js(__('Thay nội dung hiện tại bằng hồ sơ AzEvent mặc định? Thay đổi chỉ được lưu khi bạn bấm Lưu cấu hình.', 'azevent-seo-content')); ?>')) {
                        return;
                    }
                    Object.keys(brandDefaultValues).forEach(function (fieldName) {
                        var field = document.getElementById(fieldName);
                        if (!field) {
                            return;
                        }
                        field.value = brandDefaultValues[fieldName];
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    brandResetStatus.className = 'azevent-brand-reset-status is-ready';
                    brandResetStatus.textContent = '<?php echo esc_js(__('Đã nạp mặc định. Hãy bấm Lưu cấu hình.', 'azevent-seo-content')); ?>';
                });
            }

            var labPromptDefaults = <?php echo wp_json_encode($lab_prompt_defaults); ?>;
            var labPromptResetButton = document.getElementById('azevent-reset-lab-prompts');
            var labPromptResetStatus = document.getElementById('azevent-lab-reset-status');
            if (labPromptResetButton) {
                labPromptResetButton.addEventListener('click', function () {
                    if (!window.confirm('<?php echo esc_js(__('Khôi phục toàn bộ System Prompt và User Prompt của Workflow Lab về bộ SEO nâng cao mặc định? Model đã chọn sẽ được giữ nguyên.', 'azevent-seo-content')); ?>')) {
                        return;
                    }
                    Object.keys(labPromptDefaults).forEach(function (step) {
                        ['system', 'user'].forEach(function (type) {
                            var field = document.getElementById('azevent_lab_' + step + '_' + type);
                            if (!field || !labPromptDefaults[step] || typeof labPromptDefaults[step][type] !== 'string') {
                                return;
                            }
                            field.value = labPromptDefaults[step][type];
                            field.dispatchEvent(new Event('input', { bubbles: true }));
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    });
                    labPromptResetStatus.className = 'azevent-lab-reset-status is-ready';
                    labPromptResetStatus.textContent = '<?php echo esc_js(__('Đã nạp bộ prompt mặc định. Hãy bấm Lưu cấu hình.', 'azevent-seo-content')); ?>';
                });
            }

            var activePromptField = null;
            document.querySelectorAll('.azevent-prompt textarea').forEach(function (field) {
                field.addEventListener('focus', function () {
                    activePromptField = field;
                });
            });
            document.querySelectorAll('.azevent-token').forEach(function (tokenButton) {
                tokenButton.addEventListener('click', function () {
                    var target = activePromptField || document.querySelector('.azevent-prompt[open] textarea');
                    if (!target) {
                        return;
                    }
                    var token = tokenButton.getAttribute('data-token');
                    var start = typeof target.selectionStart === 'number' ? target.selectionStart : target.value.length;
                    var end = typeof target.selectionEnd === 'number' ? target.selectionEnd : start;
                    target.value = target.value.substring(0, start) + token + target.value.substring(end);
                    target.focus();
                    target.setSelectionRange(start + token.length, start + token.length);
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                });
            });

            var tabs = document.querySelectorAll('.azevent-tab');
            var panels = document.querySelectorAll('.azevent-panel');
            var activeTabStorageKey = 'azevent-settings-active-tab';

            function activateTab(tab) {
                var target = tab.getAttribute('data-tab');
                tabs.forEach(function (item) {
                    var active = item === tab;
                    item.classList.toggle('is-active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                panels.forEach(function (panel) {
                    panel.classList.toggle('is-active', panel.getAttribute('data-panel') === target);
                });
                try {
                    window.localStorage.setItem(activeTabStorageKey, target);
                } catch (error) {
                }
            }

            try {
                var storedTab = window.localStorage.getItem(activeTabStorageKey);
                var storedTabButton = storedTab ? document.querySelector('.azevent-tab[data-tab="' + storedTab + '"]') : null;
                if (storedTabButton) {
                    activateTab(storedTabButton);
                }
            } catch (error) {
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activateTab(tab);
                });
            });
        }());
    </script>
</div>
