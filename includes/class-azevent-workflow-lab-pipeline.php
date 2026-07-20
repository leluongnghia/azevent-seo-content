<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_Workflow_Lab_Pipeline
{
    const SESSION_META = '_azevent_seo_workflow_lab';

    public static function get_default_prompts()
    {
        return require AZEVENT_SEO_PATH . 'includes/class-azevent-workflow-lab-prompt-templates.php';
    }

    public function create_session(array $input, $author_id)
    {
        $keyword = sanitize_text_field($input['keyword'] ?? '');
        if ($keyword === '') {
            return new WP_Error('azevent_lab_missing_keyword', 'Vui lòng nhập từ khóa chính.');
        }

        $post_id = wp_insert_post(array(
            'post_type' => 'post',
            'post_status' => 'draft',
            'post_title' => $keyword,
            'post_author' => absint($author_id),
        ), true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $context = array(
            'version' => 1,
            'status' => 'active',
            'current_step' => 'setup',
            'last_completed_step' => 'setup',
            'next_step' => 'research',
            'language' => sanitize_text_field(get_option('azevent_seo_default_language', 'Vietnamese')),
            'input' => array(
                'keyword' => $keyword,
                'secondary_keywords' => $this->sanitize_lines($input['secondary_keywords'] ?? ''),
                'audience' => sanitize_textarea_field($input['audience'] ?? ''),
                'competitor_notes' => sanitize_textarea_field($input['competitor_notes'] ?? ''),
                'generate_image' => !empty($input['generate_image']),
            ),
            'results' => array(),
            'created_at' => time(),
            'updated_at' => time(),
        );

        update_post_meta($post_id, self::SESSION_META, $context);
        update_post_meta($post_id, '_azevent_seo_workflow_lab_owner', absint($author_id));

        return array(
            'post_id' => absint($post_id),
            'context' => $context,
        );
    }

    public function process_step($post_id, $step, array $context, array $edits = array(), $skip_image = false)
    {
        $post_id = absint($post_id);
        $step = sanitize_key($step);
        $allowed_steps = array('research', 'brief', 'content', 'seo', 'quality', 'finalize');
        if (!in_array($step, $allowed_steps, true)) {
            return new WP_Error('azevent_lab_invalid_step', 'Bước SEO Workflow Lab không hợp lệ.');
        }

        $context = $this->apply_edits($context, $edits);
        $context['current_step'] = $step;
        $context['status'] = 'processing';
        $context['updated_at'] = time();
        update_post_meta($post_id, self::SESSION_META, $context);

        $method = 'run_' . $step;
        $result = $this->{$method}($post_id, $context, (bool) $skip_image);
        if (is_wp_error($result)) {
            $context['status'] = 'failed';
            $context['error'] = $result->get_error_message();
            $context['updated_at'] = time();
            update_post_meta($post_id, self::SESSION_META, $context);
            $result->add_data(array('context' => $context, 'post_id' => $post_id));
            return $result;
        }

        $context = $result['context'];
        update_post_meta($post_id, self::SESSION_META, $context);

        return array(
            'post_id' => $post_id,
            'step' => $step,
            'next_step' => $context['next_step'],
            'status' => $context['status'],
            'context' => $context,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
        );
    }

    private function run_research($post_id, array $context)
    {
        $manual_competitor_notes = trim((string) ($context['input']['competitor_notes'] ?? ''));
        if ($manual_competitor_notes === '' && empty($context['serp_snapshot']['organic_results'])) {
            $serp_snapshot = (new AzEvent_SERP_Client())->search($context['input']['keyword']);
            if (is_wp_error($serp_snapshot)) {
                return $serp_snapshot;
            }
            $context['serp_snapshot'] = $serp_snapshot;
            $context['competitor_source'] = 'automatic_serp';
        } elseif ($manual_competitor_notes !== '') {
            $context['competitor_source'] = 'manual';
        }
        $prompts = $this->build_prompts('research', $context);

        $result = $this->call_text('research', $prompts['user'], $prompts['system'], 4096);
        if (is_wp_error($result)) {
            return $result;
        }

        $context['results']['research'] = trim($result);
        return $this->complete_step($context, 'research', 'brief');
    }

    private function run_brief($post_id, array $context)
    {
        if (empty($context['results']['research'])) {
            return new WP_Error('azevent_lab_missing_research', 'Chưa có kết quả Research.');
        }

        $candidates = $this->find_internal_link_candidates($context['input']['keyword'], $post_id);
        $context['internal_link_candidates'] = $candidates;
        $prompts = $this->build_prompts('brief', $context, $candidates);

        $result = $this->call_text('brief', $prompts['user'], $prompts['system'], 6144, array('auto_continue' => true, 'max_continuations' => 1));
        if (is_wp_error($result)) {
            return $result;
        }

        $context['results']['brief'] = trim($result);
        return $this->complete_step($context, 'brief', 'content');
    }

    private function run_content($post_id, array $context)
    {
        if (empty($context['results']['brief'])) {
            return new WP_Error('azevent_lab_missing_brief', 'Chưa có Content Brief & Outline.');
        }

        $prompts = $this->build_prompts('content', $context);

        $result = $this->call_text('content', $prompts['user'], $prompts['system'], 8192, array(
            'auto_continue' => true,
            'max_continuations' => 2,
            'detect_incomplete_ending' => true,
        ));
        if (is_wp_error($result)) {
            return $result;
        }

        $content = $this->clean_html($result);
        if ($content === '') {
            return new WP_Error('azevent_lab_empty_content', 'AI không trả về nội dung HTML hợp lệ.');
        }
        $context['results']['content'] = $content;
        return $this->complete_step($context, 'content', 'seo');
    }

    private function run_seo($post_id, array $context)
    {
        if (empty($context['results']['content'])) {
            return new WP_Error('azevent_lab_missing_content', 'Chưa có nội dung để tạo SEO metadata.');
        }

        $prompts = $this->build_prompts('seo', $context);

        $result = $this->call_text('seo', $prompts['user'], $prompts['system'], 3072);
        if (is_wp_error($result)) {
            return $result;
        }

        $seo = $this->decode_json($result);
        if (!is_array($seo) || empty($seo['title']) || empty($seo['meta']) || empty($seo['image_prompt'])) {
            return new WP_Error('azevent_lab_invalid_seo', 'AI trả về dữ liệu SEO không đầy đủ.');
        }
        $context['results']['seo'] = $this->sanitize_seo($seo, $context['input']['keyword']);
        return $this->complete_step($context, 'seo', 'quality');
    }

    private function run_quality($post_id, array $context)
    {
        if (empty($context['results']['content']) || empty($context['results']['seo'])) {
            return new WP_Error('azevent_lab_missing_quality_input', 'Thiếu Content hoặc SEO metadata để kiểm tra chất lượng.');
        }

        $candidates = isset($context['internal_link_candidates']) && is_array($context['internal_link_candidates'])
            ? $context['internal_link_candidates']
            : $this->find_internal_link_candidates($context['input']['keyword'], $post_id);
        $original_content = $context['results']['content'];
        $prompts = $this->build_prompts('quality', $context, $candidates);

        $result = $this->call_text('quality', $prompts['user'], $prompts['system'], 4096);
        if (is_wp_error($result)) {
            return $result;
        }

        $quality = $this->decode_json($result);
        if (!is_array($quality) || !isset($quality['passed'])) {
            return new WP_Error('azevent_lab_invalid_quality', 'AI trả về báo cáo Quality Gate không hợp lệ.');
        }

        $corrected_content = $this->apply_quality_replacements($original_content, $quality['replacements'] ?? array());
        $corrected_content = $this->apply_internal_link_suggestions($corrected_content, $quality['internal_links'] ?? array(), $candidates);
        $corrected_content = $this->guard_internal_links($original_content, $corrected_content, $candidates);
        $corrected_seo = !empty($quality['corrected_seo']) && is_array($quality['corrected_seo'])
            ? $this->sanitize_seo(array_merge($context['results']['seo'], $quality['corrected_seo']), $context['input']['keyword'])
            : $context['results']['seo'];
        $deterministic = $this->run_deterministic_checks($corrected_content, $corrected_seo);
        $quality['score'] = max(0, min(100, absint($quality['score'] ?? 0)));
        $quality['critical_issues'] = $this->sanitize_string_array($quality['critical_issues'] ?? array());
        $quality['warnings'] = array_values(array_unique(array_merge(
            $this->sanitize_string_array($quality['warnings'] ?? array()),
            $deterministic['warnings']
        )));
        $quality['passed'] = !empty($quality['passed']) && empty($quality['critical_issues']) && empty($deterministic['critical']);
        $quality['critical_issues'] = array_values(array_unique(array_merge($quality['critical_issues'], $deterministic['critical'])));
        $quality['corrected_content'] = $corrected_content;
        $quality['corrected_seo'] = $corrected_seo;
        $quality['internal_links'] = $this->get_used_internal_links($corrected_content, $candidates);

        $context['results']['content'] = $corrected_content;
        $context['results']['seo'] = $corrected_seo;
        $context['results']['quality'] = $quality;
        return $this->complete_step($context, 'quality', 'finalize');
    }

    private function run_finalize($post_id, array $context, $skip_image)
    {
        if (empty($context['results']['quality']) || empty($context['results']['content']) || empty($context['results']['seo'])) {
            return new WP_Error('azevent_lab_missing_final_data', 'Chưa hoàn thành Quality Gate.');
        }

        $seo = $context['results']['seo'];
        $image_status = 'skipped';
        if (!$skip_image && !empty($context['input']['generate_image'])) {
            if (!AzEvent_API_Client::is_configured()) {
                return new WP_Error('azevent_lab_image_api_missing', 'Chưa cấu hình AzEvent API tạo ảnh. Bạn có thể chọn “Lưu Draft không ảnh”.');
            }
            $prompt = $seo['image_prompt'] . ' Professional event photography, realistic lighting, high resolution, no text, no logo, no watermark.';
            $image_result = (new AzEvent_AI_Service())->generate_image($prompt, '', '1:1');
            if (is_wp_error($image_result)) {
                return $image_result;
            }
            $attachment_id = $this->upload_image($image_result, $post_id, $seo['title']);
            if (is_wp_error($attachment_id)) {
                return $attachment_id;
            }
            set_post_thumbnail($post_id, $attachment_id);
            $image_status = 'created';
        }

        $updated = wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $seo['title'],
            'post_name' => $seo['slug'],
            'post_excerpt' => $seo['meta'],
            'post_content' => $context['results']['content'],
            'post_status' => 'draft',
        ), true);
        if (is_wp_error($updated)) {
            return $updated;
        }

        update_post_meta($post_id, 'rank_math_title', $seo['title']);
        update_post_meta($post_id, 'rank_math_description', $seo['meta']);
        update_post_meta($post_id, 'rank_math_focus_keyword', $seo['focus_keyword']);

        $context['status'] = 'completed';
        $context['current_step'] = 'finalize';
        $context['last_completed_step'] = 'finalize';
        $context['next_step'] = '';
        $context['image_status'] = $image_status;
        $context['completed_at'] = time();
        $context['updated_at'] = time();
        unset($context['error']);

        return array('context' => $context);
    }

    private function complete_step(array $context, $completed_step, $next_step)
    {
        $context['status'] = 'paused';
        $context['current_step'] = $completed_step;
        $context['last_completed_step'] = $completed_step;
        $context['next_step'] = $next_step;
        $context['updated_at'] = time();
        unset($context['error']);
        return array('context' => $context);
    }

    private function apply_edits(array $context, array $edits)
    {
        if (!isset($context['results']) || !is_array($context['results'])) {
            $context['results'] = array();
        }
        if (isset($edits['research'])) {
            $context['results']['research'] = wp_kses_post((string) $edits['research']);
        }
        if (isset($edits['brief'])) {
            $context['results']['brief'] = wp_kses_post((string) $edits['brief']);
        }
        if (isset($edits['content'])) {
            $context['results']['content'] = $this->clean_html(wp_kses_post((string) $edits['content']));
        }
        if (isset($edits['seo']) && is_array($edits['seo'])) {
            $context['results']['seo'] = $this->sanitize_seo($edits['seo'], $context['input']['keyword'] ?? '');
        }
        return $context;
    }

    private function build_prompts($step, array $context, array $internal_link_candidates = array())
    {
        $defaults = self::get_default_prompts();
        if (empty($defaults[$step])) {
            return array('system' => '', 'user' => '');
        }

        $system = (string) get_option("azevent_lab_{$step}_system", '');
        $user = (string) get_option("azevent_lab_{$step}_user", '');
        $system = trim($system) === '' ? $defaults[$step]['system'] : $system;
        $user = trim($user) === '' ? $defaults[$step]['user'] : $user;
        $input = isset($context['input']) && is_array($context['input']) ? $context['input'] : array();
        $results = isset($context['results']) && is_array($context['results']) ? $context['results'] : array();
        $brand = AzEvent_SEO_Content::get_brand_profile();
        $serp_snapshot = isset($context['serp_snapshot']) && is_array($context['serp_snapshot'])
            ? $context['serp_snapshot']
            : array();
        $serp_json = wp_json_encode($serp_snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $competitor_notes = trim((string) ($input['competitor_notes'] ?? ''));
        if ($competitor_notes === '' && !empty($serp_snapshot['organic_results'])) {
            $competitor_notes = "Dữ liệu được plugin tự động lấy từ SERP thật:\n" . $serp_json;
        } elseif ($competitor_notes === '') {
            $competitor_notes = 'Không có dữ liệu SERP/đối thủ. Không được tự nhận định website nào đang đứng top.';
        }
        $replacements = array(
            '{language}' => sanitize_text_field($context['language'] ?? 'Vietnamese'),
            '{keyword}' => sanitize_text_field($input['keyword'] ?? ''),
            '{secondary_keywords}' => implode(', ', (array) ($input['secondary_keywords'] ?? array())),
            '{audience}' => sanitize_textarea_field($input['audience'] ?? ''),
            '{competitor_notes}' => $competitor_notes,
            '{serp_snapshot}' => $serp_json,
            '{brand_name}' => $brand['azevent_seo_brand_name'],
            '{brand_info}' => $brand['azevent_seo_brand_info'],
            '{brand_solution}' => $brand['azevent_seo_brand_solution'],
            '{research}' => (string) ($results['research'] ?? ''),
            '{brief}' => (string) ($results['brief'] ?? ''),
            '{content}' => (string) ($results['content'] ?? ''),
            '{seo_json}' => wp_json_encode($results['seo'] ?? array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '{internal_link_candidates}' => wp_json_encode($internal_link_candidates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        return array(
            'system' => str_replace(array_keys($replacements), array_values($replacements), $system),
            'user' => str_replace(array_keys($replacements), array_values($replacements), $user),
        );
    }

    private function call_text($step, $user_prompt, $system_prompt, $max_tokens, array $options = array())
    {
        $model_map = array(
            'research' => 'intent',
            'brief' => 'outline',
            'content' => 'content',
            'seo' => 'seo',
            'quality' => 'content',
        );
        $model_step = $model_map[$step] ?? 'content';
        $provider = sanitize_key(get_option('azevent_seo_text_provider', 'azevent'));
        $default_model = $provider === 'ckey'
            ? AzEvent_CKey_Client::model_reference(get_option('azevent_seo_ckey_model', 'sypham98/claude-sonnet-5'))
            : sanitize_text_field(get_option('aprg_cliproxy_model', ''));
        $fallback_model = sanitize_text_field(get_option("azevent_seo_{$model_step}_model", $default_model));
        if ($fallback_model === '') {
            $fallback_model = $default_model;
        }
        $model = sanitize_text_field(get_option("azevent_lab_{$step}_model", $fallback_model));
        if ($model === '') {
            $model = $fallback_model;
        }

        return (new AzEvent_AI_Service())->call_anthropic($user_prompt, $system_prompt, $model, $max_tokens, $options);
    }

    private function find_internal_link_candidates($keyword, $exclude_post_id)
    {
        $query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 80,
            'post__not_in' => array(absint($exclude_post_id)),
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ));
        $terms = $this->keyword_terms($keyword);
        $candidates = array();
        foreach ($query->posts as $post) {
            $title = get_the_title($post);
            $excerpt = wp_strip_all_tags(get_the_excerpt($post));
            $haystack = $this->lower($title . ' ' . $excerpt);
            $score = 0;
            foreach ($terms as $term) {
                if ($term !== '' && strpos($haystack, $term) !== false) {
                    $score += strlen($term) > 5 ? 3 : 1;
                }
            }
            if ($score <= 0) {
                continue;
            }
            $candidates[] = array(
                'post_id' => absint($post->ID),
                'title' => $title,
                'url' => get_permalink($post),
                'excerpt' => function_exists('mb_substr') ? mb_substr($excerpt, 0, 220) : substr($excerpt, 0, 220),
                'score' => $score,
            );
        }
        usort($candidates, function ($left, $right) {
            return $right['score'] <=> $left['score'];
        });
        return array_slice($candidates, 0, 15);
    }

    private function keyword_terms($keyword)
    {
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $this->lower($keyword));
        $terms = preg_split('/\s+/u', trim($value));
        $stopwords = array('và', 'của', 'cho', 'tại', 'là', 'the', 'and', 'for', 'with');
        return array_values(array_filter(array_unique($terms), function ($term) use ($stopwords) {
            return strlen($term) >= 3 && !in_array($term, $stopwords, true);
        }));
    }

    private function guard_internal_links($original_content, $corrected_content, array $candidates)
    {
        $original_urls = $this->extract_urls($original_content);
        $allowed = array();
        foreach ($candidates as $candidate) {
            if (!empty($candidate['url'])) {
                $allowed[$this->normalize_url($candidate['url'])] = true;
            }
        }
        $new_link_count = 0;

        return preg_replace_callback('/<a\b([^>]*?)href=([' . "\"'" . '])([^' . "\"'" . ']*)\2([^>]*)>(.*?)<\/a>/isu', function ($matches) use ($original_urls, $allowed, &$new_link_count) {
            $url = $this->normalize_url(html_entity_decode($matches[3], ENT_QUOTES, 'UTF-8'));
            $is_original = isset($original_urls[$url]);
            $is_allowed = isset($allowed[$url]);
            if (!$is_original && (!$is_allowed || $new_link_count >= 5)) {
                return $matches[5];
            }
            if (!$is_original && $is_allowed) {
                $new_link_count++;
            }
            return $matches[0];
        }, $corrected_content);
    }

    private function apply_quality_replacements($content, $replacements)
    {
        $applied = 0;
        foreach ((array) $replacements as $replacement) {
            if ($applied >= 8 || !is_array($replacement)) {
                break;
            }
            $find = sanitize_text_field($replacement['find'] ?? '');
            $replace = sanitize_text_field($replacement['replace'] ?? '');
            if ($find === '' || $replace === '' || $find === $replace || strlen($find) < 12) {
                continue;
            }
            $updated = preg_replace('/' . preg_quote($find, '/') . '/u', $replace, $content, 1, $count);
            if ($count > 0) {
                $content = $updated;
                $applied++;
            }
        }
        return $content;
    }

    private function apply_internal_link_suggestions($content, $suggestions, array $candidates)
    {
        $allowed = array();
        foreach ($candidates as $candidate) {
            if (!empty($candidate['url'])) {
                $allowed[$this->normalize_url($candidate['url'])] = $candidate['url'];
            }
        }
        $used_urls = $this->extract_urls($content);
        $inserted = 0;
        foreach ((array) $suggestions as $suggestion) {
            if ($inserted >= 5 || !is_array($suggestion)) {
                break;
            }
            $normalized_url = $this->normalize_url($suggestion['url'] ?? '');
            $anchor = sanitize_text_field($suggestion['anchor'] ?? '');
            if ($anchor === '' || strlen($anchor) < 4 || !isset($allowed[$normalized_url]) || isset($used_urls[$normalized_url])) {
                continue;
            }
            $linked = $this->link_anchor_once($content, $anchor, $allowed[$normalized_url]);
            if ($linked !== $content) {
                $content = $linked;
                $used_urls[$normalized_url] = true;
                $inserted++;
            }
        }
        return $content;
    }

    private function link_anchor_once($content, $anchor, $url)
    {
        $parts = preg_split('/(<[^>]+>)/u', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        $inside_link = 0;
        foreach ($parts as $index => $part) {
            if (preg_match('/^<a\b/i', $part)) {
                $inside_link++;
                continue;
            }
            if (preg_match('/^<\/a\b/i', $part)) {
                $inside_link = max(0, $inside_link - 1);
                continue;
            }
            if ($inside_link > 0 || (isset($part[0]) && $part[0] === '<')) {
                continue;
            }
            $replacement = '<a href="' . esc_url($url) . '">' . esc_html($anchor) . '</a>';
            $updated = preg_replace('/' . preg_quote($anchor, '/') . '/iu', $replacement, $part, 1, $count);
            if ($count > 0) {
                $parts[$index] = $updated;
                return implode('', $parts);
            }
        }
        return $content;
    }

    private function get_used_internal_links($content, array $candidates)
    {
        $used_urls = $this->extract_urls($content);
        $links = array();
        foreach ($candidates as $candidate) {
            if (isset($used_urls[$this->normalize_url($candidate['url'])])) {
                $links[] = array(
                    'post_id' => absint($candidate['post_id']),
                    'title' => $candidate['title'],
                    'url' => $candidate['url'],
                );
            }
        }
        return array_slice($links, 0, 5);
    }

    private function extract_urls($content)
    {
        $urls = array();
        if (preg_match_all('/<a\b[^>]*href=([' . "\"'" . '])([^' . "\"'" . ']*)\1/isu', (string) $content, $matches)) {
            foreach ($matches[2] as $url) {
                $urls[$this->normalize_url(html_entity_decode($url, ENT_QUOTES, 'UTF-8'))] = true;
            }
        }
        return $urls;
    }

    private function run_deterministic_checks($content, array $seo)
    {
        $critical = array();
        $warnings = array();
        $plain = trim(wp_strip_all_tags($content));
        if ($plain === '') {
            $critical[] = 'Nội dung rỗng sau Quality Gate.';
        }
        if (preg_match('/```|~~~/u', $content)) {
            $critical[] = 'Nội dung vẫn còn Markdown fence.';
        }
        if (preg_match('/<h1\b/i', $content)) {
            $warnings[] = 'Nội dung có thẻ H1; WordPress thường dùng tiêu đề bài làm H1.';
        }
        $word_count = count(preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY));
        if ($word_count < 700) {
            $warnings[] = 'Bài viết ngắn hơn 700 từ; cần kiểm tra độ phủ chủ đề.';
        }
        $title_length = function_exists('mb_strlen') ? mb_strlen($seo['title']) : strlen($seo['title']);
        if ($title_length < 35 || $title_length > 70) {
            $warnings[] = 'Độ dài SEO title nằm ngoài khoảng tham khảo 35-70 ký tự.';
        }
        $meta_length = function_exists('mb_strlen') ? mb_strlen($seo['meta']) : strlen($seo['meta']);
        if ($meta_length < 120 || $meta_length > 170) {
            $warnings[] = 'Độ dài meta description nằm ngoài khoảng tham khảo 120-170 ký tự.';
        }
        return array('critical' => $critical, 'warnings' => $warnings);
    }

    private function sanitize_seo(array $seo, $keyword)
    {
        $secondary = isset($seo['secondary_keywords']) && is_array($seo['secondary_keywords'])
            ? $this->sanitize_string_array($seo['secondary_keywords'])
            : array();
        $faq = array();
        foreach ((array) ($seo['faq_schema'] ?? array()) as $item) {
            if (!is_array($item) || empty($item['question']) || empty($item['answer'])) {
                continue;
            }
            $faq[] = array(
                'question' => sanitize_text_field($item['question']),
                'answer' => sanitize_textarea_field($item['answer']),
            );
        }
        return array(
            'title' => sanitize_text_field($seo['title'] ?? $keyword),
            'slug' => sanitize_title($seo['slug'] ?? $keyword),
            'meta' => sanitize_textarea_field($seo['meta'] ?? ''),
            'focus_keyword' => sanitize_text_field($seo['focus_keyword'] ?? $keyword),
            'secondary_keywords' => $secondary,
            'faq_schema' => array_slice($faq, 0, 10),
            'schema_type' => in_array(($seo['schema_type'] ?? ''), array('Article', 'BlogPosting'), true)
                ? $seo['schema_type']
                : 'Article',
            'image_prompt' => sanitize_textarea_field($seo['image_prompt'] ?? ''),
        );
    }

    private function decode_json($content)
    {
        $content = trim(preg_replace('/^\s*(?:```|~~~)(?:json)?\s*|\s*(?:```|~~~)\s*$/iu', '', (string) $content));
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($content, $start, $end - $start + 1), true);
        }
        return is_array($decoded) ? $decoded : null;
    }

    private function clean_html($content)
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', (string) $content);
        $content = trim($content);
        $content = preg_replace('/^\s*(?:```|~~~)(?:html|htm)?\s*/i', '', $content);
        $content = preg_replace('/\s*(?:```|~~~)\s*$/', '', $content);
        return trim($content);
    }

    private function sanitize_lines($value)
    {
        $lines = is_array($value) ? $value : preg_split('/[\r\n,]+/u', (string) $value);
        return array_values(array_unique(array_filter(array_map('sanitize_text_field', $lines))));
    }

    private function sanitize_string_array($values)
    {
        return array_values(array_filter(array_map('sanitize_text_field', (array) $values)));
    }

    private function lower($value)
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }

    private function normalize_url($url)
    {
        return untrailingslashit(strtolower(trim((string) $url)));
    }

    private function upload_image($image_result, $post_id, $title)
    {
        if (empty($image_result['base64'])) {
            return new WP_Error('azevent_lab_empty_image', 'API không trả về dữ liệu ảnh.');
        }
        $image_data = base64_decode(preg_replace('/\s+/', '', $image_result['base64']), true);
        if ($image_data === false || $image_data === '') {
            return new WP_Error('azevent_lab_invalid_image', 'Dữ liệu ảnh không hợp lệ.');
        }
        $detected = function_exists('getimagesizefromstring') ? @getimagesizefromstring($image_data) : false;
        if (!$detected || empty($detected['mime'])) {
            return new WP_Error('azevent_lab_invalid_image_type', 'API trả về dữ liệu không phải ảnh.');
        }
        $extensions = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
        $mime = sanitize_mime_type($detected['mime']);
        $extension = $extensions[$mime] ?? 'png';
        $upload = wp_upload_bits(sanitize_title($title) . '-azevent.' . $extension, null, $image_data);
        if (!empty($upload['error'])) {
            return new WP_Error('azevent_lab_upload_failed', $upload['error']);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $mime,
            'post_title' => $title,
            'post_status' => 'inherit',
        ), $upload['file'], $post_id, true);
        if (is_wp_error($attachment_id)) {
            wp_delete_file($upload['file']);
            return $attachment_id;
        }
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $title);
        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
        return $attachment_id;
    }
}
