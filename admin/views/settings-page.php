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
$azevent_lab_validate_outline = absint(get_option('azevent_lab_validate_outline', 0));
$azevent_lab_outline_validation_model = get_option('azevent_lab_outline_validation_model', '');
$azevent_studio_split_content = absint(get_option('azevent_seo_split_content_by_outline', 0));
$azevent_generate_h2_images = absint(get_option('azevent_seo_generate_h2_images', 0));
$azevent_h2_image_limit = min(10, max(1, absint(get_option('azevent_seo_h2_image_limit', 6))));
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
foreach (array_merge(array_values($azevent_lab_step_models), array($azevent_lab_outline_validation_model)) as $step_model) {
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
$content_studio_geo_defaults = AzEvent_GEO_Prompts::get_defaults(AzEvent_GEO_Prompts::CONTENT_STUDIO);
$content_studio_geo_sections = array(
    'intent' => __('Search Intent', 'azevent-seo-content'),
    'outline' => __('Outline', 'azevent-seo-content'),
    'content' => __('Content', 'azevent-seo-content'),
    'seo' => __('SEO Metadata', 'azevent-seo-content'),
    'rewrite' => __('Bổ sung riêng cho Rewrite', 'azevent-seo-content'),
);
$lab_prompt_defaults = AzEvent_Workflow_Lab_Pipeline::get_default_prompts();
$lab_prompt_english = AzEvent_Workflow_Lab_Pipeline::get_english_prompts();
$workflow_lab_geo_defaults = AzEvent_GEO_Prompts::get_defaults(AzEvent_GEO_Prompts::WORKFLOW_LAB);
$workflow_lab_geo_sections = array(
    'research' => __('Research', 'azevent-seo-content'),
    'brief' => __('Content Brief & Outline', 'azevent-seo-content'),
    'outline_validation' => __('Outline Validation', 'azevent-seo-content'),
    'content' => __('Content', 'azevent-seo-content'),
    'seo' => __('SEO Metadata', 'azevent-seo-content'),
    'quality' => __('Quality Gate', 'azevent-seo-content'),
);
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
$azevent_model_display_label = function ($model) use ($azevent_text_models, $azevent_base_url) {
    $model = sanitize_text_field($model);
    if (AzEvent_CKey_Client::is_model_reference($model)) {
        return 'CKEY.VN — ' . AzEvent_CKey_Client::strip_model_prefix($model);
    }
    return AzEvent_API_Client::get_provider_label($azevent_base_url) . ' — ' . ($azevent_text_models[$model] ?? $model);
};
$azevent_lab_fallback_labels = array();
$azevent_lab_effective_labels = array();
foreach ($lab_prompt_sections as $lab_step => $lab_section) {
    $fallback_model = $azevent_step_models[$lab_section['fallback_step']] ?? '';
    $fallback_label = $fallback_model === '' ? $azevent_default_text_label : $azevent_model_display_label($fallback_model);
    $azevent_lab_fallback_labels[$lab_step] = $fallback_label;
    $lab_model = $azevent_lab_step_models[$lab_step] ?? '';
    $azevent_lab_effective_labels[$lab_step] = $lab_model === '' ? $fallback_label : $azevent_model_display_label($lab_model);
}
$azevent_lab_step_hints = array(
    'research' => __('Phân tích intent và SERP', 'azevent-seo-content'),
    'brief' => __('Lập Brief và Outline', 'azevent-seo-content'),
    'content' => __('Viết nội dung HTML', 'azevent-seo-content'),
    'seo' => __('Tạo metadata và schema', 'azevent-seo-content'),
    'quality' => __('Kiểm tra và chèn liên kết', 'azevent-seo-content'),
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
$azevent_modal_mode = isset($_GET['azevent_modal'])
    && absint(wp_unslash($_GET['azevent_modal'])) === 1;
$azevent_modal_section = isset($_GET['azevent_section'])
    ? sanitize_key(wp_unslash($_GET['azevent_section']))
    : '';
if (!in_array($azevent_modal_section, array('settings', 'prompts'), true)) {
    $azevent_modal_section = '';
}
$azevent_initial_tab = $azevent_modal_section === 'prompts' ? 'prompts' : ($azevent_modal_section === 'settings' ? 'api' : '');
?>
<?php if ($azevent_modal_mode) : ?>
    <style>
        html.wp-toolbar { padding-top: 0; }
        body { min-width: 0; background: #f8fafc; }
        #wpadminbar, #adminmenumain, #wpfooter, .update-nag, .notice:not(.settings-error) { display: none !important; }
        #wpcontent, #wpfooter { margin-left: 0; }
        #wpbody-content { min-height: 100vh; padding-bottom: 0; }
    </style>
<?php endif; ?>
<div class="wrap azevent-settings-page<?php echo $azevent_modal_mode ? ' azevent-settings-modal azevent-settings-modal-' . esc_attr($azevent_modal_section) : ''; ?>">
    <style>
        .azevent-settings-page {
            width: auto;
            max-width: 1480px;
            margin-right: 24px;
            color: #0f172a;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .azevent-settings-page * { box-sizing: border-box; }
        .azevent-settings-page main, .azevent-card-title > div, .azevent-model-routing-header > div { min-width: 0; }
        .azevent-settings-page p, .azevent-settings-page span, .azevent-settings-page label { overflow-wrap: anywhere; }
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
            scrollbar-width: thin;
            scroll-snap-type: x proximity;
        }
        .azevent-tabs::-webkit-scrollbar, .azevent-flow::-webkit-scrollbar { height: 6px; }
        .azevent-tabs::-webkit-scrollbar-thumb, .azevent-flow::-webkit-scrollbar-thumb { border-radius: 999px; background: #cbd5e1; }
        .azevent-tabs::-webkit-scrollbar-track, .azevent-flow::-webkit-scrollbar-track { background: transparent; }
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
            scroll-snap-align: start;
        }
        .azevent-tab:hover { background: #f8fafc; color: #1e293b; }
        .azevent-tab.is-active { background: #4f46e5; color: #fff; box-shadow: 0 4px 12px rgba(79,70,229,.2); }
        .azevent-tab:focus-visible, .azevent-prompt-toggle:focus-visible, .azevent-legacy-refresh:focus-visible, .azevent-model-add:focus-visible {
            outline: 3px solid rgba(99,102,241,.28);
            outline-offset: 2px;
        }
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
        .azevent-field label.azevent-workflow-option, label.azevent-workflow-option { display: flex; align-items: flex-start; gap: 13px; margin-bottom: 0; padding: 15px; border: 1px solid #dbe4f3; border-radius: 11px; background: #f8fafc; cursor: pointer; transition: border-color .15s, background .15s, box-shadow .15s; }
        label.azevent-workflow-option:hover { border-color: #c7d2fe; background: #fff; }
        label.azevent-workflow-option:focus-within { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
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
        .azevent-lab-model-routing { margin-bottom: 18px; padding: 18px; border: 1px solid #c7d2fe; border-radius: 14px; background: linear-gradient(145deg, #f8fafc, #eef2ff); }
        .azevent-lab-model-toolbar { display: grid; grid-template-columns: minmax(260px, 1fr) auto auto; gap: 9px; align-items: end; margin: 14px 0; padding: 12px; border: 1px solid #dbe4f3; border-radius: 11px; background: rgba(255,255,255,.78); }
        .azevent-lab-model-toolbar .azevent-field { margin: 0; }
        .azevent-lab-model-toolbar .button { min-height: 39px; padding: 0 14px; border-radius: 8px; font-weight: 700; }
        .azevent-lab-model-status { grid-column: 1/-1; min-height: 16px; color: #047857; font-size: 11px; }
        .azevent-lab-model-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; }
        .azevent-lab-model-card { grid-column: span 2; min-width: 0; padding: 13px; border: 1px solid #dbe4f3; border-radius: 11px; background: #fff; }
        .azevent-lab-model-card:nth-last-child(2):nth-child(3n + 1) { grid-column: 2 / span 2; }
        .azevent-lab-model-card:last-child:nth-child(3n + 2) { grid-column: 4 / span 2; }
        .azevent-lab-model-card-head { display: flex; align-items: flex-start; gap: 9px; margin-bottom: 9px; }
        .azevent-lab-model-number { display: inline-grid; place-items: center; width: 25px; height: 25px; flex: 0 0 auto; border-radius: 8px; background: #4f46e5; color: #fff; font-size: 10px; font-weight: 800; }
        .azevent-lab-model-card strong, .azevent-lab-model-card span { display: block; }
        .azevent-lab-model-card strong { color: #172554; font-size: 12px; line-height: 1.35; }
        .azevent-lab-model-card-head span:last-child { margin-top: 2px; color: #64748b; font-size: 10px; line-height: 1.35; }
        .azevent-lab-model-card select { width: 100%; }
        .azevent-lab-model-state { margin-top: 7px; color: #64748b; font-size: 10px; line-height: 1.45; overflow-wrap: anywhere; }
        .azevent-outline-validator-model { max-width: 720px; margin-top: 13px; padding-top: 13px; border-top: 1px solid #dbeafe; }
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
        .azevent-settings-section { margin: 26px 0 12px; padding-top: 23px; border-top: 1px solid #e2e8f0; }
        .azevent-settings-section:first-of-type { margin-top: 0; padding-top: 0; border-top: 0; }
        .azevent-settings-section-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 14px; }
        .azevent-settings-section-title { display: flex; align-items: flex-start; gap: 10px; min-width: 0; }
        .azevent-settings-section-number { display: inline-grid; place-items: center; width: 28px; height: 28px; flex: 0 0 28px; border-radius: 8px; background: #eef2ff; color: #4338ca; font-size: 11px; font-weight: 800; }
        .azevent-settings-section-heading h3 { margin: 0 0 4px; color: #172554; font-size: 15px; }
        .azevent-settings-section-heading p { margin: 0; color: #64748b; font-size: 11px; line-height: 1.55; }
        .azevent-prompt-tools { display: flex; flex: 0 0 auto; gap: 7px; }
        .azevent-prompt-toggle { min-height: 36px; padding: 0 11px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #475569; cursor: pointer; font-size: 11px; font-weight: 700; }
        .azevent-prompt-toggle:hover { border-color: #a5b4fc; color: #4338ca; }
        .azevent-lab-reset-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0 0 16px; }
        .azevent-lab-reset-actions { display: flex; flex-wrap: wrap; gap: 9px; }
        .azevent-lab-reset-status { color: #64748b; font-size: 11px; }
        .azevent-lab-reset-status.is-ready { color: #047857; }
        .azevent-serp-box { margin-bottom: 18px; padding: 17px; border: 1px solid #bfdbfe; border-radius: 13px; background: linear-gradient(135deg, #eff6ff, #f8fafc); }
        .azevent-serp-box h3 { margin: 0; color: #1e3a8a; font-size: 14px; }
        .azevent-serp-box > p { margin: 6px 0 15px; color: #64748b; font-size: 11px; line-height: 1.55; }
        .azevent-geo-box { margin-bottom: 0; border-color: #c4b5fd; background: linear-gradient(145deg, #faf5ff, #f8fafc 58%, #eff6ff); }
        .azevent-geo-box h3 { color: #4c1d95; }
        .azevent-geo-formula { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; margin: 12px 0 15px; color: #475569; font-size: 11px; font-weight: 700; }
        .azevent-geo-formula span { padding: 5px 8px; border: 1px solid #ddd6fe; border-radius: 7px; background: rgba(255,255,255,.82); color: #5b21b6; }
        .azevent-geo-formula b { color: #7c3aed; }
        .azevent-geo-priority { border-color: #ddd6fe; background: rgba(255,255,255,.7); }
        .azevent-geo-priority summary { background: rgba(255,255,255,.74); }
        .azevent-footer { position: sticky; bottom: 16px; z-index: 4; display: flex; justify-content: flex-end; gap: 10px; margin-top: 4px; padding: 13px 15px; border: 1px solid #e2e8f0; border-radius: 12px; background: rgba(255,255,255,.92); box-shadow: 0 10px 26px rgba(15,23,42,.1); backdrop-filter: blur(10px); }
        .azevent-footer .button-primary { min-width: 154px; height: 42px; border: 0; border-radius: 10px; background: linear-gradient(135deg, #4f46e5, #7c3aed); box-shadow: 0 8px 18px rgba(79,70,229,.24); font-weight: 700; }
        .azevent-settings-modal { width: auto; max-width: none; margin: 0; padding: 18px; }
        .azevent-settings-modal .azevent-hero, .azevent-settings-modal .azevent-flow { display: none; }
        .azevent-settings-modal .azevent-tabs { top: 0; }
        .azevent-settings-modal-prompts .azevent-tab[data-tab="api"],
        .azevent-settings-modal-prompts .azevent-tab[data-tab="brand"],
        .azevent-settings-modal-prompts .azevent-tab[data-tab="content-settings"],
        .azevent-settings-modal-settings .azevent-tab[data-tab="prompts"],
        .azevent-settings-modal-settings .azevent-tab[data-tab="lab-prompts"] { display: none; }
        @media (max-width: 800px) {
            .azevent-settings-page { width: auto; margin-right: 12px; }
            .azevent-settings-modal { margin: 0; padding: 12px; }
            .azevent-hero { padding: 22px 20px; border-radius: 16px; }
            .azevent-brand-row { margin-bottom: 18px; }
            .azevent-hero h1 { font-size: 25px; }
            .azevent-tabs { margin-bottom: 14px; }
            .azevent-grid, .azevent-prompt-body, .azevent-step-model-grid, .azevent-lab-model-grid, .azevent-lab-model-toolbar { grid-template-columns: 1fr; }
            .azevent-lab-model-card,
            .azevent-lab-model-card:nth-last-child(2):nth-child(3n + 1),
            .azevent-lab-model-card:last-child:nth-child(3n + 2),
            .azevent-lab-model-card:last-child:nth-child(odd) { grid-column: auto; width: auto; max-width: none; }
            .azevent-tabs { top: 10px; }
            .azevent-tab { min-height: 44px; justify-content: flex-start; }
            .azevent-version { position: static; display: inline-block; margin-top: 18px; }
            .azevent-flow { overflow-x: auto; }
            .azevent-card { padding: 18px 16px; border-radius: 13px; }
            .azevent-card-header, .azevent-settings-section-heading { flex-direction: column; }
            .azevent-legacy-actions, .azevent-lab-reset-row { align-items: stretch; flex-direction: column; }
            .azevent-lab-reset-actions, .azevent-prompt-tools { width: 100%; }
            .azevent-lab-reset-actions > button, .azevent-prompt-tools > button { flex: 1 1 auto; min-height: 42px; }
            .azevent-model-tools { align-items: stretch; flex-direction: column; }
            .azevent-model-add { min-height: 40px; }
            .azevent-prompt summary { min-height: 56px; align-items: center; }
            .azevent-footer { bottom: 0; padding: 10px; }
            .azevent-footer .button-primary { width: 100%; }
        }
        @media (min-width: 801px) and (max-width: 1240px) {
            .azevent-lab-model-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .azevent-lab-model-card,
            .azevent-lab-model-card:nth-last-child(2):nth-child(3n + 1),
            .azevent-lab-model-card:last-child:nth-child(3n + 2) { grid-column: auto; }
            .azevent-lab-model-card:last-child:nth-child(odd) { grid-column: 1 / -1; width: calc(50% - 6px); justify-self: center; }
        }
        @media (max-width: 480px) {
            .azevent-settings-page { margin-left: 0; margin-right: 10px; }
            .azevent-settings-modal { margin: 0; padding: 10px; }
            .azevent-brand-label, .azevent-tab-icon { display: none; }
            .azevent-card-title { gap: 8px; }
            .azevent-section-icon { width: 30px; height: 30px; flex-basis: 30px; }
            .azevent-token-subtitle { display: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .azevent-settings-page *, .azevent-settings-page *:before, .azevent-settings-page *:after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; }
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
            <nav class="azevent-tabs" aria-label="Settings sections" role="tablist">
                <button type="button" id="azevent-tab-api" class="azevent-tab is-active" data-tab="api" role="tab" aria-controls="azevent-panel-api" aria-selected="true" tabindex="0"><span class="azevent-tab-icon">⚡</span><?php _e('AI Providers', 'azevent-seo-content'); ?></button>
                <button type="button" id="azevent-tab-brand" class="azevent-tab" data-tab="brand" role="tab" aria-controls="azevent-panel-brand" aria-selected="false" tabindex="-1"><span class="azevent-tab-icon">◈</span><?php _e('Thương hiệu', 'azevent-seo-content'); ?></button>
                <button type="button" id="azevent-tab-content-settings" class="azevent-tab" data-tab="content-settings" role="tab" aria-controls="azevent-panel-content-settings" aria-selected="false" tabindex="-1"><span class="azevent-tab-icon">文</span><?php _e('Nội dung', 'azevent-seo-content'); ?></button>
                <button type="button" id="azevent-tab-prompts" class="azevent-tab" data-tab="prompts" role="tab" aria-controls="azevent-panel-prompts" aria-selected="false" tabindex="-1"><span class="azevent-tab-icon">✎</span><?php _e('AI Prompts', 'azevent-seo-content'); ?></button>
                <button type="button" id="azevent-tab-lab-prompts" class="azevent-tab" data-tab="lab-prompts" role="tab" aria-controls="azevent-panel-lab-prompts" aria-selected="false" tabindex="-1"><span class="azevent-tab-icon">◫</span><?php _e('Workflow Lab Prompts', 'azevent-seo-content'); ?></button>
            </nav>

            <main>
                <section id="azevent-panel-api" class="azevent-panel is-active" data-panel="api" role="tabpanel" aria-labelledby="azevent-tab-api">
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
                                        <input id="azevent-new-ckey-model" type="text" placeholder="claude-opus-4.6">
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
                                    <p class="azevent-help"><?php _e('Claude Messages dùng x-api-key; OpenAI Chat dùng Bearer token. Auto tự chọn Messages cho model có tên Claude.', 'azevent-seo-content'); ?></p>
                                    <div class="azevent-model-tools">
                                        <button type="button" class="button" id="azevent-test-ckey" data-nonce="<?php echo esc_attr(wp_create_nonce('azevent_test_ckey_connection')); ?>"><?php _e('Kiểm tra kết nối CKey', 'azevent-seo-content'); ?></button>
                                        <span id="azevent-test-ckey-result" class="azevent-help" aria-live="polite"></span>
                                    </div>
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

                <section id="azevent-panel-brand" class="azevent-panel" data-panel="brand" role="tabpanel" aria-labelledby="azevent-tab-brand" hidden>
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

                <section id="azevent-panel-content-settings" class="azevent-panel" data-panel="content-settings" role="tabpanel" aria-labelledby="azevent-tab-content-settings" hidden>
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

                <section id="azevent-panel-prompts" class="azevent-panel" data-panel="prompts" role="tabpanel" aria-labelledby="azevent-tab-prompts" hidden>
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
                        <div class="azevent-settings-section">
                            <div class="azevent-settings-section-heading">
                                <div class="azevent-settings-section-title">
                                    <span class="azevent-settings-section-number">1</span>
                                    <div>
                                        <h3><?php _e('Cách Content Studio vận hành', 'azevent-seo-content'); ?></h3>
                                        <p><?php _e('Các tùy chọn ảnh hưởng đến cách chia request và tạo ảnh trong phiên mới.', 'azevent-seo-content'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="azevent-serp-box">
                            <h3><?php _e('Cách viết Content trong Content Studio', 'azevent-seo-content'); ?></h3>
                            <input type="hidden" name="azevent_seo_split_content_by_outline" value="0">
                            <label class="azevent-workflow-option" for="azevent_seo_split_content_by_outline">
                                <input id="azevent_seo_split_content_by_outline" type="checkbox" name="azevent_seo_split_content_by_outline" value="1" <?php checked($azevent_studio_split_content, 1); ?>>
                                <span>
                                    <strong><?php _e('Tách Content và viết lần lượt theo từng H2', 'azevent-seo-content'); ?></strong>
                                    <span><?php _e('Áp dụng riêng cho Tạo bài mới, Viết lại bài hiện tại và Background Queue của Content Studio. Mỗi H2 lưu một checkpoint.', 'azevent-seo-content'); ?></span>
                                </span>
                            </label>
                            <p class="azevent-help"><?php _e('Nếu Outline không có ít nhất 2 H2, plugin tự viết toàn bài trong một request như trước.', 'azevent-seo-content'); ?></p>
                        </div>
                        <div class="azevent-serp-box">
                            <h3><?php _e('Ảnh minh họa tự động theo H2', 'azevent-seo-content'); ?></h3>
                            <input type="hidden" name="azevent_seo_generate_h2_images" value="0">
                            <label class="azevent-workflow-option" for="azevent_seo_generate_h2_images">
                                <input id="azevent_seo_generate_h2_images" type="checkbox" name="azevent_seo_generate_h2_images" value="1" aria-controls="azevent-h2-image-limit-field" aria-expanded="<?php echo $azevent_generate_h2_images ? 'true' : 'false'; ?>" <?php checked($azevent_generate_h2_images, 1); ?>>
                                <span>
                                    <strong><?php _e('Tạo và chèn ảnh minh họa cho các H2 quan trọng', 'azevent-seo-content'); ?></strong>
                                    <span><?php _e('Áp dụng cho Content Studio và SEO Workflow Lab. AI chọn section, tạo ảnh 16:9 và chèn sau đoạn mở đầu của H2.', 'azevent-seo-content'); ?></span>
                                </span>
                            </label>
                            <div id="azevent-h2-image-limit-field" class="azevent-field azevent-conditional-field" data-controlled-by="azevent_seo_generate_h2_images" style="max-width:260px;margin-top:14px" <?php echo $azevent_generate_h2_images ? '' : 'hidden'; ?>>
                                <label for="azevent_seo_h2_image_limit"><?php _e('Số ảnh H2 tối đa mỗi bài', 'azevent-seo-content'); ?></label>
                                <select id="azevent_seo_h2_image_limit" name="azevent_seo_h2_image_limit">
                                    <?php foreach (array(3, 4, 5, 6, 8, 10) as $image_limit) : ?>
                                        <option value="<?php echo esc_attr($image_limit); ?>" <?php selected($azevent_h2_image_limit, $image_limit); ?>><?php echo esc_html($image_limit); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <p class="azevent-help"><?php _e('Mặc định tắt. Ảnh lỗi sẽ thử lại hai lần, sau đó bỏ qua để bài vẫn hoàn tất. Ảnh đại diện vẫn được tạo riêng.', 'azevent-seo-content'); ?></p>
                        </div>
                        <div class="azevent-settings-section">
                            <div class="azevent-settings-section-heading">
                                <div class="azevent-settings-section-title">
                                    <span class="azevent-settings-section-number">2</span>
                                    <div>
                                        <h3><?php _e('Prompt chính của Content Studio', 'azevent-seo-content'); ?></h3>
                                        <p><?php _e('System Prompt và User Prompt điều khiển nhiệm vụ chính của từng bước.', 'azevent-seo-content'); ?></p>
                                    </div>
                                </div>
                                <div class="azevent-prompt-tools" aria-label="<?php esc_attr_e('Điều khiển nhóm prompt Content Studio', 'azevent-seo-content'); ?>">
                                    <button type="button" class="azevent-prompt-toggle" data-prompt-action="expand" data-prompt-group="content-studio"><?php _e('Mở tất cả', 'azevent-seo-content'); ?></button>
                                    <button type="button" class="azevent-prompt-toggle" data-prompt-action="collapse" data-prompt-group="content-studio"><?php _e('Thu gọn', 'azevent-seo-content'); ?></button>
                                </div>
                            </div>
                        </div>
                        <?php foreach ($prompt_sections as $key => $section) : ?>
                            <details class="azevent-prompt" data-prompt-group="content-studio">
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
                        <div class="azevent-settings-section">
                            <div class="azevent-settings-section-heading">
                                <div class="azevent-settings-section-title">
                                    <span class="azevent-settings-section-number">3</span>
                                    <div>
                                        <h3><?php _e('AI Overview/GEO — tùy chọn', 'azevent-seo-content'); ?></h3>
                                        <p><?php _e('Lớp bổ sung độc lập; không thay thế prompt chính ở phía trên.', 'azevent-seo-content'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="azevent-serp-box azevent-geo-box">
                            <h3><?php _e('Prompt AI Overview/GEO riêng cho Content Studio', 'azevent-seo-content'); ?></h3>
                            <div class="azevent-geo-formula" aria-label="<?php esc_attr_e('Cách ghép prompt khi bật GEO', 'azevent-seo-content'); ?>">
                                <span><?php _e('Prompt chính', 'azevent-seo-content'); ?></span><b>+</b><span><?php _e('GEO của bước', 'azevent-seo-content'); ?></span><b>→</b><?php _e('Prompt gửi AI', 'azevent-seo-content'); ?>
                            </div>
                            <input type="hidden" name="azevent_geo_content_studio_default_enabled" value="0">
                            <label class="azevent-workflow-option" for="azevent_geo_content_studio_default_enabled">
                                <input id="azevent_geo_content_studio_default_enabled" type="checkbox" name="azevent_geo_content_studio_default_enabled" value="1" <?php checked(absint(get_option('azevent_geo_content_studio_default_enabled', 0)), 1); ?>>
                                <span>
                                    <strong><?php _e('Bật mặc định AI Overview/GEO cho phiên Content Studio mới', 'azevent-seo-content'); ?></strong>
                                    <span><?php _e('Khi lưu lựa chọn này, ô GEO ở màn hình bắt đầu Content Studio sẽ được tích sẵn. Bạn vẫn có thể tắt riêng cho từng phiên hoặc Background Queue.', 'azevent-seo-content'); ?></span>
                                </span>
                            </label>
                            <p class="azevent-help"><?php _e('Không tích: phiên mới dùng nguyên prompt cũ. Nút bên dưới chỉ khôi phục nội dung prompt GEO tiếng Anh và không tự bật chế độ.', 'azevent-seo-content'); ?></p>
                            <div class="azevent-lab-reset-row">
                                <div class="azevent-lab-reset-actions">
                                    <button type="button" class="azevent-legacy-refresh" id="azevent-load-content-studio-geo-defaults"><?php _e('↺ Nạp lại GEO tiếng Anh mặc định', 'azevent-seo-content'); ?></button>
                                    <button type="button" class="azevent-prompt-toggle" data-prompt-action="expand" data-prompt-group="content-studio-geo"><?php _e('Mở prompt GEO', 'azevent-seo-content'); ?></button>
                                    <button type="button" class="azevent-prompt-toggle" data-prompt-action="collapse" data-prompt-group="content-studio-geo"><?php _e('Thu gọn', 'azevent-seo-content'); ?></button>
                                </div>
                                <span class="azevent-lab-reset-status" id="azevent-content-studio-geo-status" aria-live="polite"></span>
                            </div>
                            <?php foreach ($content_studio_geo_sections as $geo_step => $geo_label) : ?>
                                <?php $geo_option = AzEvent_GEO_Prompts::option_name(AzEvent_GEO_Prompts::CONTENT_STUDIO, $geo_step); ?>
                                <details class="azevent-prompt azevent-geo-priority" data-prompt-group="content-studio-geo">
                                    <summary>
                                        <span><span class="azevent-prompt-title"><?php echo esc_html($geo_label); ?></span><span class="azevent-prompt-description"><?php _e('Prompt bổ sung, không thay prompt gốc.', 'azevent-seo-content'); ?></span></span>
                                    </summary>
                                    <div class="azevent-prompt-body" style="grid-template-columns:1fr">
                                        <div class="azevent-field">
                                            <label for="<?php echo esc_attr($geo_option); ?>"><?php _e('GEO Priority Prompt', 'azevent-seo-content'); ?></label>
                                            <textarea id="<?php echo esc_attr($geo_option); ?>" name="<?php echo esc_attr($geo_option); ?>" rows="10"><?php echo esc_textarea($get_prompt($geo_option, $content_studio_geo_defaults[$geo_step])); ?></textarea>
                                        </div>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section id="azevent-panel-lab-prompts" class="azevent-panel" data-panel="lab-prompts" role="tabpanel" aria-labelledby="azevent-tab-lab-prompts" hidden>
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
                        <div class="azevent-settings-section">
                            <div class="azevent-settings-section-heading">
                                <div class="azevent-settings-section-title">
                                    <span class="azevent-settings-section-number">1</span>
                                    <div>
                                        <h3><?php _e('Model theo từng bước', 'azevent-seo-content'); ?></h3>
                                        <p><?php _e('Chọn một model chung hoặc tinh chỉnh riêng từng bước mà không làm thay đổi prompt.', 'azevent-seo-content'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="azevent-lab-model-routing" id="azevent-lab-model-routing">
                            <div class="azevent-model-routing-header">
                                <div>
                                    <h3><?php _e('Chọn nhanh model cho Workflow Lab', 'azevent-seo-content'); ?></h3>
                                    <p><?php _e('Toàn bộ 5 bước được đặt cạnh nhau. Bạn có thể đổi riêng từng bước hoặc áp dụng một model cho tất cả.', 'azevent-seo-content'); ?></p>
                                </div>
                                <span class="azevent-model-routing-badge"><?php _e('5 bước', 'azevent-seo-content'); ?></span>
                            </div>
                            <div class="azevent-lab-model-toolbar">
                                <div class="azevent-field">
                                    <label for="azevent-lab-bulk-model"><?php _e('Áp dụng nhanh một model', 'azevent-seo-content'); ?></label>
                                    <select id="azevent-lab-bulk-model" class="azevent-lab-model-select">
                                        <option value=""><?php _e('Chọn model để áp dụng…', 'azevent-seo-content'); ?></option>
                                        <optgroup label="<?php echo esc_attr(AzEvent_API_Client::get_provider_label($azevent_base_url)); ?>">
                                            <?php foreach ($azevent_text_models as $model_id => $model_label) : ?>
                                                <option value="<?php echo esc_attr($model_id); ?>"><?php echo esc_html($model_label); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="CKEY.VN">
                                            <?php foreach ($azevent_ckey_models as $model_id => $model_label) : ?>
                                                <option value="<?php echo esc_attr(AzEvent_CKey_Client::model_reference($model_id)); ?>"><?php echo esc_html($model_label); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                </div>
                                <button type="button" class="button button-primary" id="azevent-apply-lab-model"><?php _e('Áp dụng cho 5 bước', 'azevent-seo-content'); ?></button>
                                <button type="button" class="button" id="azevent-reset-lab-models"><?php _e('Đặt về kế thừa', 'azevent-seo-content'); ?></button>
                                <span class="azevent-lab-model-status" id="azevent-lab-model-status" aria-live="polite"></span>
                            </div>
                            <div class="azevent-lab-model-grid">
                                <?php $lab_step_number = 0; ?>
                                <?php foreach ($lab_prompt_sections as $key => $section) : ?>
                                    <?php $lab_step_number++; ?>
                                    <div class="azevent-lab-model-card">
                                        <div class="azevent-lab-model-card-head">
                                            <span class="azevent-lab-model-number"><?php echo esc_html($lab_step_number); ?></span>
                                            <span><strong><?php echo esc_html($section['label']); ?></strong><span><?php echo esc_html($azevent_lab_step_hints[$key] ?? ''); ?></span></span>
                                        </div>
                                        <select class="azevent-lab-step-model-select azevent-lab-model-select" id="azevent_lab_<?php echo esc_attr($key); ?>_model" name="azevent_lab_<?php echo esc_attr($key); ?>_model" data-inherit-label="<?php echo esc_attr($azevent_lab_fallback_labels[$key]); ?>">
                                            <option value="" <?php selected($azevent_lab_step_models[$key], ''); ?>><?php _e('Kế thừa cấu hình hiện tại', 'azevent-seo-content'); ?></option>
                                            <optgroup label="<?php echo esc_attr(AzEvent_API_Client::get_provider_label($azevent_base_url)); ?>">
                                                <?php foreach ($azevent_text_models as $model_id => $model_label) : ?>
                                                    <option value="<?php echo esc_attr($model_id); ?>" <?php selected($azevent_lab_step_models[$key], $model_id); ?>><?php echo esc_html($model_label); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <optgroup label="CKEY.VN">
                                                <?php foreach ($azevent_ckey_models as $model_id => $model_label) : ?>
                                                    <?php $model_reference = AzEvent_CKey_Client::model_reference($model_id); ?>
                                                    <option value="<?php echo esc_attr($model_reference); ?>" <?php selected($azevent_lab_step_models[$key], $model_reference); ?>><?php echo esc_html($model_label); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        </select>
                                        <span class="azevent-lab-model-state"></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="azevent-settings-section">
                            <div class="azevent-settings-section-heading">
                                <div class="azevent-settings-section-title">
                                    <span class="azevent-settings-section-number">2</span>
                                    <div>
                                        <h3><?php _e('Dữ liệu và cách chạy Workflow Lab', 'azevent-seo-content'); ?></h3>
                                        <p><?php _e('Cấu hình SERP, lượt kiểm định Outline và cách chia Content thành background job.', 'azevent-seo-content'); ?></p>
                                    </div>
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
                            <h3><?php _e('Kiểm định Outline bằng AI', 'azevent-seo-content'); ?></h3>
                            <input type="hidden" name="azevent_lab_validate_outline" value="0">
                            <label class="azevent-workflow-option" for="azevent_lab_validate_outline">
                                <input id="azevent_lab_validate_outline" type="checkbox" name="azevent_lab_validate_outline" value="1" aria-controls="azevent-outline-validator-model" aria-expanded="<?php echo $azevent_lab_validate_outline ? 'true' : 'false'; ?>" <?php checked($azevent_lab_validate_outline, 1); ?>>
                                <span>
                                    <strong><?php _e('Kiểm định Outline bằng AI lần hai', 'azevent-seo-content'); ?></strong>
                                    <span><?php _e('Thêm bước Kiểm định Outline độc lập sau Brief & Outline để AI rà soát intent, loại heading biên tập nội bộ và gộp mục trùng trước khi viết Content.', 'azevent-seo-content'); ?></span>
                                </span>
                            </label>
                            <div id="azevent-outline-validator-model" class="azevent-field azevent-outline-validator-model azevent-conditional-field" data-controlled-by="azevent_lab_validate_outline" <?php echo $azevent_lab_validate_outline ? '' : 'hidden'; ?>>
                                <label for="azevent_lab_outline_validation_model"><?php _e('Model kiểm định Outline', 'azevent-seo-content'); ?></label>
                                <select id="azevent_lab_outline_validation_model" class="azevent-lab-model-select" name="azevent_lab_outline_validation_model" data-inherit-label="<?php echo esc_attr($azevent_lab_effective_labels['brief']); ?>">
                                    <option value="" <?php selected($azevent_lab_outline_validation_model, ''); ?>><?php printf(esc_html__('Dùng cùng model Brief & Outline — %s', 'azevent-seo-content'), esc_html($azevent_lab_effective_labels['brief'])); ?></option>
                                    <optgroup label="<?php echo esc_attr(AzEvent_API_Client::get_provider_label($azevent_base_url)); ?>">
                                        <?php foreach ($azevent_text_models as $model_id => $model_label) : ?>
                                            <option value="<?php echo esc_attr($model_id); ?>" <?php selected($azevent_lab_outline_validation_model, $model_id); ?>><?php echo esc_html($model_label); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="CKEY.VN">
                                        <?php foreach ($azevent_ckey_models as $model_id => $model_label) : ?>
                                            <?php $model_reference = AzEvent_CKey_Client::model_reference($model_id); ?>
                                            <option value="<?php echo esc_attr($model_reference); ?>" <?php selected($azevent_lab_outline_validation_model, $model_reference); ?>><?php echo esc_html($model_label); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                                <p class="azevent-help"><?php _e('Để trống để dùng cùng model của bước Brief. Chọn riêng nếu muốn lượt kiểm định dùng model mạnh hơn.', 'azevent-seo-content'); ?></p>
                            </div>
                            <p class="azevent-help"><?php _e('Mặc định tắt. Khi bật, Workflow Lab có thêm bước duyệt riêng và một lượt API. Nếu kiểm định lỗi hoặc không đủ H2 hợp lệ, plugin giữ kết quả Brief ban đầu để bạn xem và chạy lại bước này.', 'azevent-seo-content'); ?></p>
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
                        <div class="azevent-settings-section">
                            <div class="azevent-settings-section-heading">
                                <div class="azevent-settings-section-title">
                                    <span class="azevent-settings-section-number">3</span>
                                    <div>
                                        <h3><?php _e('Workflow Lab Prompts chính', 'azevent-seo-content'); ?></h3>
                                        <p><?php _e('Năm cặp System/User Prompt điều khiển nhiệm vụ và định dạng đầu ra của từng bước.', 'azevent-seo-content'); ?></p>
                                    </div>
                                </div>
                                <div class="azevent-prompt-tools" aria-label="<?php esc_attr_e('Điều khiển nhóm Workflow Lab Prompts', 'azevent-seo-content'); ?>">
                                    <button type="button" class="azevent-prompt-toggle" data-prompt-action="expand" data-prompt-group="workflow-lab"><?php _e('Mở tất cả', 'azevent-seo-content'); ?></button>
                                    <button type="button" class="azevent-prompt-toggle" data-prompt-action="collapse" data-prompt-group="workflow-lab"><?php _e('Thu gọn', 'azevent-seo-content'); ?></button>
                                </div>
                            </div>
                        </div>
                        <div class="azevent-lab-reset-row">
                            <div class="azevent-lab-reset-actions">
                                <button type="button" class="azevent-legacy-refresh" id="azevent-load-english-lab-prompts"><?php _e('EN Nạp Prompt tiếng Anh & lưu', 'azevent-seo-content'); ?></button>
                                <button type="button" class="azevent-legacy-refresh" id="azevent-reset-lab-prompts"><?php _e('↺ Khôi phục bộ prompt SEO nâng cao', 'azevent-seo-content'); ?></button>
                            </div>
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
                            <details class="azevent-prompt" data-prompt-group="workflow-lab">
                                <summary>
                                    <span><span class="azevent-prompt-title"><?php echo esc_html($section['label']); ?></span><span class="azevent-prompt-description"><?php echo esc_html($section['description']); ?></span></span>
                                </summary>
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
                        <div class="azevent-settings-section">
                            <div class="azevent-settings-section-heading">
                                <div class="azevent-settings-section-title">
                                    <span class="azevent-settings-section-number">4</span>
                                    <div>
                                        <h3><?php _e('AI Overview/GEO — tùy chọn', 'azevent-seo-content'); ?></h3>
                                        <p><?php _e('Lớp prompt bổ sung riêng; chỉ được ghép khi ô GEO của phiên đang bật.', 'azevent-seo-content'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="azevent-serp-box azevent-geo-box">
                            <h3><?php _e('Prompt AI Overview/GEO riêng cho Workflow Lab', 'azevent-seo-content'); ?></h3>
                            <div class="azevent-geo-formula" aria-label="<?php esc_attr_e('Cách ghép prompt khi bật GEO', 'azevent-seo-content'); ?>">
                                <span><?php _e('Workflow Lab User Prompt', 'azevent-seo-content'); ?></span><b>+</b><span><?php _e('GEO của bước', 'azevent-seo-content'); ?></span><b>→</b><?php _e('Prompt gửi AI', 'azevent-seo-content'); ?>
                            </div>
                            <input type="hidden" name="azevent_geo_workflow_lab_default_enabled" value="0">
                            <label class="azevent-workflow-option" for="azevent_geo_workflow_lab_default_enabled">
                                <input id="azevent_geo_workflow_lab_default_enabled" type="checkbox" name="azevent_geo_workflow_lab_default_enabled" value="1" <?php checked(absint(get_option('azevent_geo_workflow_lab_default_enabled', 0)), 1); ?>>
                                <span>
                                    <strong><?php _e('Bật mặc định AI Overview/GEO cho phiên Workflow Lab mới', 'azevent-seo-content'); ?></strong>
                                    <span><?php _e('Khi lưu lựa chọn này, ô GEO ở màn hình tạo phiên Workflow Lab sẽ được tích sẵn. Bạn vẫn có thể tắt riêng cho từng phiên.', 'azevent-seo-content'); ?></span>
                                </span>
                            </label>
                            <p class="azevent-help"><?php _e('Không tích: phiên mới dùng nguyên prompt cũ. Nút bên dưới chỉ khôi phục nội dung prompt GEO tiếng Anh và không tự bật chế độ.', 'azevent-seo-content'); ?></p>
                            <div class="azevent-lab-reset-row">
                                <div class="azevent-lab-reset-actions">
                                    <button type="button" class="azevent-legacy-refresh" id="azevent-load-workflow-lab-geo-defaults"><?php _e('↺ Nạp lại GEO tiếng Anh mặc định', 'azevent-seo-content'); ?></button>
                                    <button type="button" class="azevent-prompt-toggle" data-prompt-action="expand" data-prompt-group="workflow-lab-geo"><?php _e('Mở prompt GEO', 'azevent-seo-content'); ?></button>
                                    <button type="button" class="azevent-prompt-toggle" data-prompt-action="collapse" data-prompt-group="workflow-lab-geo"><?php _e('Thu gọn', 'azevent-seo-content'); ?></button>
                                </div>
                                <span class="azevent-lab-reset-status" id="azevent-workflow-lab-geo-status" aria-live="polite"></span>
                            </div>
                            <?php foreach ($workflow_lab_geo_sections as $geo_step => $geo_label) : ?>
                                <?php $geo_option = AzEvent_GEO_Prompts::option_name(AzEvent_GEO_Prompts::WORKFLOW_LAB, $geo_step); ?>
                                <details class="azevent-prompt azevent-geo-priority" data-prompt-group="workflow-lab-geo">
                                    <summary>
                                        <span><span class="azevent-prompt-title"><?php echo esc_html($geo_label); ?></span><span class="azevent-prompt-description"><?php _e('Prompt bổ sung, không thay prompt gốc.', 'azevent-seo-content'); ?></span></span>
                                    </summary>
                                    <div class="azevent-prompt-body" style="grid-template-columns:1fr">
                                        <div class="azevent-field">
                                            <label for="<?php echo esc_attr($geo_option); ?>"><?php _e('GEO Priority Prompt', 'azevent-seo-content'); ?></label>
                                            <textarea id="<?php echo esc_attr($geo_option); ?>" name="<?php echo esc_attr($geo_option); ?>" rows="10"><?php echo esc_textarea($get_prompt($geo_option, $workflow_lab_geo_defaults[$geo_step])); ?></textarea>
                                        </div>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
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
            var isModalMode = <?php echo wp_json_encode($azevent_modal_mode); ?>;
            if (isModalMode) {
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && window.parent !== window) {
                        window.parent.postMessage('azevent-close-settings-modal', window.location.origin);
                    }
                });
            }
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
            var ckeyTestButton = document.getElementById('azevent-test-ckey');
            var ckeyTestResult = document.getElementById('azevent-test-ckey-result');
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

            document.querySelectorAll('.azevent-conditional-field[data-controlled-by]').forEach(function (field) {
                var control = document.getElementById(field.getAttribute('data-controlled-by'));
                if (!control) {
                    return;
                }
                var syncConditionalField = function () {
                    field.hidden = !control.checked;
                    control.setAttribute('aria-expanded', control.checked ? 'true' : 'false');
                };
                control.addEventListener('change', syncConditionalField);
                syncConditionalField();
            });

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

            var stepModelSelects = Array.prototype.slice.call(document.querySelectorAll('.azevent-step-model-select, .azevent-lab-model-select'));
            var labStepModelSelects = Array.prototype.slice.call(document.querySelectorAll('.azevent-lab-step-model-select'));
            var labBulkModel = document.getElementById('azevent-lab-bulk-model');
            var applyLabModelButton = document.getElementById('azevent-apply-lab-model');
            var resetLabModelsButton = document.getElementById('azevent-reset-lab-models');
            var labModelStatus = document.getElementById('azevent-lab-model-status');
            var outlineValidationModel = document.getElementById('azevent_lab_outline_validation_model');

            function selectedModelLabel(select) {
                if (!select || !select.value) {
                    return select ? (select.dataset.inheritLabel || '<?php echo esc_js(__('cấu hình mặc định', 'azevent-seo-content')); ?>') : '';
                }
                var option = select.options[select.selectedIndex];
                var group = option && option.parentElement && option.parentElement.tagName === 'OPTGROUP'
                    ? option.parentElement.label + ' — '
                    : '';
                return group + (option ? option.textContent : select.value);
            }

            function updateLabModelStates() {
                labStepModelSelects.forEach(function (select) {
                    var state = select.parentElement.querySelector('.azevent-lab-model-state');
                    if (state) {
                        state.textContent = select.value
                            ? '<?php echo esc_js(__('Dùng riêng: ', 'azevent-seo-content')); ?>' + selectedModelLabel(select)
                            : '<?php echo esc_js(__('Kế thừa: ', 'azevent-seo-content')); ?>' + selectedModelLabel(select);
                        state.title = state.textContent;
                    }
                });
                var briefSelect = document.getElementById('azevent_lab_brief_model');
                if (briefSelect && outlineValidationModel && outlineValidationModel.options.length) {
                    var briefLabel = selectedModelLabel(briefSelect);
                    outlineValidationModel.dataset.inheritLabel = briefLabel;
                    outlineValidationModel.options[0].textContent = '<?php echo esc_js(__('Dùng cùng model Brief & Outline — ', 'azevent-seo-content')); ?>' + briefLabel;
                }
            }

            labStepModelSelects.forEach(function (select) {
                select.addEventListener('change', updateLabModelStates);
            });
            if (applyLabModelButton) {
                applyLabModelButton.addEventListener('click', function () {
                    if (!labBulkModel || !labBulkModel.value) {
                        labModelStatus.style.color = '#b91c1c';
                        labModelStatus.textContent = '<?php echo esc_js(__('Hãy chọn một model trước khi áp dụng.', 'azevent-seo-content')); ?>';
                        return;
                    }
                    labStepModelSelects.forEach(function (select) {
                        select.value = labBulkModel.value;
                    });
                    updateLabModelStates();
                    labModelStatus.style.color = '#047857';
                    labModelStatus.textContent = '<?php echo esc_js(__('Đã áp dụng model cho 5 bước. Nhấn Lưu cấu hình để hoàn tất.', 'azevent-seo-content')); ?>';
                });
            }
            if (resetLabModelsButton) {
                resetLabModelsButton.addEventListener('click', function () {
                    labStepModelSelects.forEach(function (select) {
                        select.value = '';
                    });
                    updateLabModelStates();
                    labModelStatus.style.color = '#047857';
                    labModelStatus.textContent = '<?php echo esc_js(__('Đã đặt 5 bước về chế độ kế thừa. Nhấn Lưu cấu hình để hoàn tất.', 'azevent-seo-content')); ?>';
                });
            }
            updateLabModelStates();

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
                        updateLabModelStates();
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

            if (ckeyTestButton) {
                ckeyTestButton.addEventListener('click', function () {
                    var apiKeyField = document.getElementById('azevent_seo_ckey_api_key');
                    var apiFormatField = document.getElementById('azevent_seo_ckey_api_format');
                    ckeyTestButton.disabled = true;
                    ckeyTestResult.textContent = '<?php echo esc_js(__('Đang kiểm tra kết nối...', 'azevent-seo-content')); ?>';
                    ckeyTestResult.style.color = '';

                    var body = new URLSearchParams({
                        action: 'azevent_test_ckey_connection',
                        nonce: ckeyTestButton.dataset.nonce,
                        api_key: apiKeyField ? apiKeyField.value : '',
                        model: ckeyModelSelect ? ckeyModelSelect.value : '',
                        api_format: apiFormatField ? apiFormatField.value : 'messages'
                    });
                    fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: body.toString()
                    }).then(function (response) {
                        return response.json();
                    }).then(function (response) {
                        var data = response.data || {};
                        if (!response.success) {
                            throw new Error(data.message || '<?php echo esc_js(__('Không thể kết nối CKey.', 'azevent-seo-content')); ?>');
                        }
                        ckeyTestResult.style.color = '#15803d';
                        ckeyTestResult.textContent = data.message;
                    }).catch(function (error) {
                        ckeyTestResult.style.color = '#b91c1c';
                        ckeyTestResult.textContent = error.message;
                    }).finally(function () {
                        ckeyTestButton.disabled = false;
                    });
                });
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
                        updateLabModelStates();
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
            var labPromptEnglish = <?php echo wp_json_encode($lab_prompt_english); ?>;
            var labPromptEnglishButton = document.getElementById('azevent-load-english-lab-prompts');
            var labPromptResetButton = document.getElementById('azevent-reset-lab-prompts');
            var labPromptResetStatus = document.getElementById('azevent-lab-reset-status');

            function fillLabPrompts(promptSet) {
                Object.keys(promptSet).forEach(function (step) {
                    ['system', 'user'].forEach(function (type) {
                        var field = document.getElementById('azevent_lab_' + step + '_' + type);
                        if (!field || !promptSet[step] || typeof promptSet[step][type] !== 'string') {
                            return;
                        }
                        field.value = promptSet[step][type];
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                });
            }

            if (labPromptEnglishButton) {
                labPromptEnglishButton.addEventListener('click', function () {
                    if (!window.confirm('<?php echo esc_js(__('Thay toàn bộ 5 System Prompt và 5 User Prompt của Workflow Lab bằng bản tiếng Anh rồi lưu ngay? Model và các cài đặt khác đang hiển thị cũng sẽ được lưu.', 'azevent-seo-content')); ?>')) {
                        return;
                    }
                    fillLabPrompts(labPromptEnglish);
                    labPromptResetStatus.className = 'azevent-lab-reset-status is-ready';
                    labPromptResetStatus.textContent = '<?php echo esc_js(__('Đang lưu toàn bộ Prompt tiếng Anh…', 'azevent-seo-content')); ?>';
                    labPromptEnglishButton.disabled = true;
                    var settingsForm = labPromptEnglishButton.closest('form');
                    if (settingsForm) {
                        if (typeof settingsForm.requestSubmit === 'function') {
                            settingsForm.requestSubmit();
                        } else {
                            settingsForm.submit();
                        }
                    }
                });
            }

            if (labPromptResetButton) {
                labPromptResetButton.addEventListener('click', function () {
                    if (!window.confirm('<?php echo esc_js(__('Khôi phục toàn bộ System Prompt và User Prompt của Workflow Lab về bộ SEO nâng cao mặc định? Model đã chọn sẽ được giữ nguyên.', 'azevent-seo-content')); ?>')) {
                        return;
                    }
                    fillLabPrompts(labPromptDefaults);
                    labPromptResetStatus.className = 'azevent-lab-reset-status is-ready';
                    labPromptResetStatus.textContent = '<?php echo esc_js(__('Đã nạp bộ prompt mặc định. Hãy bấm Lưu cấu hình.', 'azevent-seo-content')); ?>';
                });
            }

            function fillGeoPriorities(workflow, promptSet) {
                Object.keys(promptSet).forEach(function (step) {
                    var field = document.getElementById('azevent_geo_' + workflow + '_' + step);
                    if (!field || typeof promptSet[step] !== 'string') {
                        return;
                    }
                    field.value = promptSet[step];
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }

            var contentStudioGeoDefaults = <?php echo wp_json_encode($content_studio_geo_defaults); ?>;
            var workflowLabGeoDefaults = <?php echo wp_json_encode($workflow_lab_geo_defaults); ?>;
            var contentStudioGeoButton = document.getElementById('azevent-load-content-studio-geo-defaults');
            var workflowLabGeoButton = document.getElementById('azevent-load-workflow-lab-geo-defaults');
            var contentStudioGeoStatus = document.getElementById('azevent-content-studio-geo-status');
            var workflowLabGeoStatus = document.getElementById('azevent-workflow-lab-geo-status');

            if (contentStudioGeoButton) {
                contentStudioGeoButton.addEventListener('click', function () {
                    if (!window.confirm('<?php echo esc_js(__('Nạp lại bộ GEO tiếng Anh mặc định riêng của Content Studio? Thao tác này không thay System/User Prompt hiện có, không tự bật GEO và chỉ được lưu khi bạn bấm Lưu cấu hình.', 'azevent-seo-content')); ?>')) {
                        return;
                    }
                    fillGeoPriorities('content_studio', contentStudioGeoDefaults);
                    contentStudioGeoStatus.className = 'azevent-lab-reset-status is-ready';
                    contentStudioGeoStatus.textContent = '<?php echo esc_js(__('Đã nạp bộ GEO Content Studio. Hãy bấm Lưu cấu hình.', 'azevent-seo-content')); ?>';
                });
            }

            if (workflowLabGeoButton) {
                workflowLabGeoButton.addEventListener('click', function () {
                    if (!window.confirm('<?php echo esc_js(__('Nạp lại bộ GEO tiếng Anh mặc định riêng của Workflow Lab? Thao tác này không thay prompt Workflow Lab hiện có, không tự bật GEO và chỉ được lưu khi bạn bấm Lưu cấu hình.', 'azevent-seo-content')); ?>')) {
                        return;
                    }
                    fillGeoPriorities('workflow_lab', workflowLabGeoDefaults);
                    workflowLabGeoStatus.className = 'azevent-lab-reset-status is-ready';
                    workflowLabGeoStatus.textContent = '<?php echo esc_js(__('Đã nạp bộ GEO Workflow Lab. Hãy bấm Lưu cấu hình.', 'azevent-seo-content')); ?>';
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
                    var currentPanel = tokenButton.closest('.azevent-panel');
                    var focusedFieldIsRelevant = activePromptField
                        && currentPanel
                        && currentPanel.contains(activePromptField);
                    var target = focusedFieldIsRelevant
                        ? activePromptField
                        : (currentPanel ? currentPanel.querySelector('.azevent-prompt[open] textarea') : null);
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

            document.querySelectorAll('.azevent-prompt-toggle').forEach(function (toggleButton) {
                toggleButton.addEventListener('click', function () {
                    var group = toggleButton.getAttribute('data-prompt-group');
                    var shouldOpen = toggleButton.getAttribute('data-prompt-action') === 'expand';
                    document.querySelectorAll('.azevent-prompt[data-prompt-group="' + group + '"]').forEach(function (prompt) {
                        prompt.open = shouldOpen;
                    });
                });
            });

            var tabs = Array.prototype.filter.call(document.querySelectorAll('.azevent-tab'), function (tab) {
                return window.getComputedStyle(tab).display !== 'none';
            });
            var panels = document.querySelectorAll('.azevent-panel');
            var activeTabStorageKey = 'azevent-settings-active-tab';
            var requestedTab = <?php echo wp_json_encode($azevent_initial_tab); ?>;

            function activateTab(tab) {
                var target = tab.getAttribute('data-tab');
                tabs.forEach(function (item) {
                    var active = item === tab;
                    item.classList.toggle('is-active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                    item.setAttribute('tabindex', active ? '0' : '-1');
                });
                panels.forEach(function (panel) {
                    var active = panel.getAttribute('data-panel') === target;
                    panel.classList.toggle('is-active', active);
                    panel.hidden = !active;
                });
                tab.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                try {
                    window.localStorage.setItem(activeTabStorageKey, target);
                } catch (error) {
                }
            }

            var requestedTabButton = requestedTab
                ? tabs.find(function (tab) { return tab.getAttribute('data-tab') === requestedTab; })
                : null;
            if (requestedTabButton) {
                activateTab(requestedTabButton);
            } else {
                try {
                    var storedTab = window.localStorage.getItem(activeTabStorageKey);
                    var storedTabButton = storedTab
                        ? tabs.find(function (tab) { return tab.getAttribute('data-tab') === storedTab; })
                        : null;
                    if (storedTabButton) {
                        activateTab(storedTabButton);
                    } else if (tabs[0]) {
                        activateTab(tabs[0]);
                    }
                } catch (error) {
                    if (tabs[0]) {
                        activateTab(tabs[0]);
                    }
                }
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activateTab(tab);
                });
                tab.addEventListener('keydown', function (event) {
                    var tabList = tabs;
                    var currentIndex = tabList.indexOf(tab);
                    var targetIndex = currentIndex;
                    if (event.key === 'ArrowRight') {
                        targetIndex = (currentIndex + 1) % tabList.length;
                    } else if (event.key === 'ArrowLeft') {
                        targetIndex = (currentIndex - 1 + tabList.length) % tabList.length;
                    } else if (event.key === 'Home') {
                        targetIndex = 0;
                    } else if (event.key === 'End') {
                        targetIndex = tabList.length - 1;
                    } else {
                        return;
                    }
                    event.preventDefault();
                    activateTab(tabList[targetIndex]);
                    tabList[targetIndex].focus();
                });
            });
        }());
    </script>
</div>
