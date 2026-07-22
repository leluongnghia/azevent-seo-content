<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_Workflow_Lab_Pipeline
{
    const SESSION_META = '_azevent_seo_workflow_lab';
    const SERP_META = '_azevent_seo_workflow_lab_serp_snapshot';
    private $last_ai_metrics = array();

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
            'logs' => array(
                array(
                    'timestamp' => time(),
                    'level' => 'success',
                    'step' => 'setup',
                    'message' => 'Đã tạo phiên SEO Workflow Lab bằng plugin v' . AZEVENT_SEO_VERSION . ' và lưu Draft checkpoint.',
                ),
            ),
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
        $step_started_at = microtime(true);
        $this->append_log($post_id, $context, 'info', $step, 'Bắt đầu xử lý bước ' . $this->step_label($step) . '.');

        $method = 'run_' . $step;
        $result = $this->{$method}($post_id, $context, (bool) $skip_image);
        if (is_wp_error($result)) {
            $context['status'] = 'failed';
            $context['error'] = $result->get_error_message();
            $context['updated_at'] = time();
            unset($context['pending_job']);
            $this->record_step_duration($context, $step, microtime(true) - $step_started_at, 'error');
            $this->append_log(
                $post_id,
                $context,
                'error',
                $step,
                'Bước ' . $this->step_label($step) . ' thất bại sau ' . $this->format_duration(microtime(true) - $step_started_at) . ': ' . $result->get_error_message()
            );
            $result->add_data(array('context' => $context, 'post_id' => $post_id));
            return $result;
        }

        $context = $result['context'];
        if (!empty($result['continue_step'])) {
            $this->record_step_duration($context, $step, microtime(true) - $step_started_at, 'partial');
            $next_job_id = wp_generate_uuid4();
            $context['status'] = 'queued';
            $context['current_step'] = $step;
            $context['pending_job'] = array(
                'id' => $next_job_id,
                'step' => $step,
                'skip_image' => (bool) $skip_image,
                'queued_at' => time(),
            );
            $context['updated_at'] = time();
            unset($context['error']);
            $this->append_log(
                $post_id,
                $context,
                'success',
                $step,
                (string) ($result['progress_message'] ?? 'Đã lưu checkpoint phần hiện tại và đưa phần tiếp theo vào hàng đợi nền.')
            );

            return array(
                'post_id' => $post_id,
                'step' => $step,
                'next_step' => $context['next_step'],
                'status' => $context['status'],
                'context' => $context,
                'job_id' => $next_job_id,
                'edit_url' => get_edit_post_link($post_id, 'raw'),
            );
        }
        unset($context['pending_job']);
        $this->record_step_duration($context, $step, microtime(true) - $step_started_at, 'success');
        $this->append_log(
            $post_id,
            $context,
            'success',
            $step,
            'Hoàn thành bước ' . $this->step_label($step) . ' trong ' . $this->format_duration(microtime(true) - $step_started_at) . '.'
        );

        return array(
            'post_id' => $post_id,
            'step' => $step,
            'next_step' => $context['next_step'],
            'status' => $context['status'],
            'context' => $context,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
        );
    }

    public function queue_step($post_id, $step, array $context, array $edits = array(), $skip_image = false)
    {
        $post_id = absint($post_id);
        $step = sanitize_key($step);
        $allowed_steps = array('research', 'brief', 'content', 'seo', 'quality', 'finalize');
        if (!in_array($step, $allowed_steps, true)) {
            return new WP_Error('azevent_lab_invalid_step', 'Bước SEO Workflow Lab không hợp lệ.');
        }
        if (in_array(($context['status'] ?? ''), array('queued', 'processing'), true)) {
            return new WP_Error('azevent_lab_step_busy', 'Phiên đang có một bước chạy nền. Vui lòng chờ bước đó hoàn tất.');
        }

        $context = $this->apply_edits($context, $edits);
        $job_id = wp_generate_uuid4();
        $context['status'] = 'queued';
        $context['current_step'] = $step;
        $context['pending_job'] = array(
            'id' => $job_id,
            'step' => $step,
            'skip_image' => (bool) $skip_image,
            'queued_at' => time(),
        );
        $context['updated_at'] = time();
        $this->append_log($post_id, $context, 'info', $step, 'Đã đưa bước ' . $this->step_label($step) . ' vào hàng đợi nền. Có thể đóng tab và mở lại phiên để theo dõi.');

        return array(
            'post_id' => $post_id,
            'job_id' => $job_id,
            'context' => $context,
        );
    }

    private function run_research($post_id, array &$context)
    {
        $manual_competitor_notes = trim((string) ($context['input']['competitor_notes'] ?? ''));
        if ($manual_competitor_notes === '') {
            $keyword = sanitize_text_field($context['input']['keyword'] ?? '');
            $serp_snapshot = $this->get_session_serp_snapshot($post_id, $keyword, $context['serp_snapshot'] ?? array());
            if (!empty($serp_snapshot['organic_results'])) {
                $context['serp_snapshot'] = $serp_snapshot;
                $context['competitor_source'] = 'automatic_serp';
                $this->append_log(
                    $post_id,
                    $context,
                    'info',
                    'research',
                    sprintf(
                        'Tái sử dụng %d nguồn SERP đã lưu của phiên; không gọi lại SerpApi.',
                        count((array) $serp_snapshot['organic_results'])
                    )
                );
            } else {
                $serp_started_at = microtime(true);
                $this->append_log(
                    $post_id,
                    $context,
                    'info',
                    'research',
                    sprintf(
                        'Đang gọi SerpApi và đọc cấu trúc tối đa %d trang đối thủ.',
                        min(5, max(0, absint(get_option('azevent_lab_serp_fetch_pages', 2))))
                    )
                );
                $serp_snapshot = (new AzEvent_SERP_Client())->search($keyword);
                if (is_wp_error($serp_snapshot)) {
                    return $serp_snapshot;
                }
                $context['serp_snapshot'] = $serp_snapshot;
                update_post_meta($post_id, self::SERP_META, $serp_snapshot);
                $context['competitor_source'] = 'automatic_serp';
                $this->append_log(
                    $post_id,
                    $context,
                    'success',
                    'research',
                    sprintf(
                        'Đã nhận và lưu %d kết quả đối thủ theo phiên trong %s%s.',
                        count((array) ($serp_snapshot['organic_results'] ?? array())),
                        $this->format_duration(microtime(true) - $serp_started_at),
                        !empty($serp_snapshot['cache_hit']) ? ' (dữ liệu cache)' : ''
                    )
                );
            }
        } elseif ($manual_competitor_notes !== '') {
            $context['competitor_source'] = 'manual';
            $this->append_log($post_id, $context, 'info', 'research', 'Đang dùng dữ liệu đối thủ nhập thủ công, không gọi SerpApi.');
        }
        $prompts = $this->build_prompts('research', $context);

        $this->log_ai_request($post_id, $context, 'research', 4096, array(), $prompts);
        $result = $this->call_text('research', $prompts['user'], $prompts['system'], 4096);
        $this->record_ai_metrics($post_id, $context, 'research', $prompts, $result);
        if (is_wp_error($result)) {
            return $result;
        }

        $context['results']['research'] = trim($result);
        return $this->complete_step($context, 'research', 'brief');
    }

    private function get_session_serp_snapshot($post_id, $keyword, $context_snapshot)
    {
        $context_snapshot = is_array($context_snapshot) ? $context_snapshot : array();
        if ($this->is_matching_serp_snapshot($context_snapshot, $keyword)) {
            update_post_meta(absint($post_id), self::SERP_META, $context_snapshot);
            return $context_snapshot;
        }

        $saved_snapshot = get_post_meta(absint($post_id), self::SERP_META, true);
        return $this->is_matching_serp_snapshot($saved_snapshot, $keyword) ? $saved_snapshot : array();
    }

    private function is_matching_serp_snapshot($snapshot, $keyword)
    {
        if (!is_array($snapshot) || empty($snapshot['organic_results']) || !is_array($snapshot['organic_results'])) {
            return false;
        }

        $snapshot_query = trim((string) ($snapshot['query'] ?? ''));
        $keyword = trim((string) $keyword);
        if ($snapshot_query === '' || $keyword === '') {
            return true;
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($snapshot_query, 'UTF-8') === mb_strtolower($keyword, 'UTF-8');
        }
        return strtolower($snapshot_query) === strtolower($keyword);
    }

    private function run_brief($post_id, array &$context)
    {
        if (empty($context['results']['research'])) {
            return new WP_Error('azevent_lab_missing_research', 'Chưa có kết quả Research.');
        }

        $candidates = $this->find_internal_link_candidates($context['input']['keyword'], $post_id);
        $context['internal_link_candidates'] = $candidates;
        $this->append_log($post_id, $context, 'info', 'brief', sprintf('Đã tìm thấy %d ứng viên internal link từ bài Published.', count($candidates)));
        $prompts = $this->build_prompts('brief', $context, $candidates);

        $this->log_ai_request($post_id, $context, 'brief', 6144, array('auto_continue' => true, 'max_continuations' => 1), $prompts);
        $result = $this->call_text('brief', $prompts['user'], $prompts['system'], 6144, array('auto_continue' => true, 'max_continuations' => 1));
        $this->record_ai_metrics($post_id, $context, 'brief', $prompts, $result);
        if (is_wp_error($result)) {
            return $result;
        }

        $context['results']['brief'] = trim($result);
        return $this->complete_step($context, 'brief', 'content');
    }

    private function run_content($post_id, array &$context)
    {
        if (empty($context['results']['brief'])) {
            return new WP_Error('azevent_lab_missing_brief', 'Chưa có Content Brief & Outline.');
        }

        $split_state = isset($context['content_split']) && is_array($context['content_split'])
            ? $context['content_split']
            : array();
        $split_in_progress = !empty($split_state['enabled']) && empty($split_state['completed']) && !empty($split_state['sections']);
        $split_enabled = $split_in_progress || absint(get_option('azevent_lab_split_content_by_outline', 0)) === 1;

        if ($split_enabled) {
            if (!$split_in_progress) {
                $sections = $this->extract_outline_sections($context['results']['brief']);
                if (count($sections) >= 2) {
                    $split_state = array(
                        'enabled' => true,
                        'completed' => false,
                        'current_index' => 0,
                        'sections' => array_slice($sections, 0, 20),
                        'parts' => array(),
                        'started_at' => time(),
                    );
                    $context['content_split'] = $split_state;
                    $this->append_log(
                        $post_id,
                        $context,
                        'info',
                        'content',
                        sprintf('Đã tách Outline thành %d phần H2. Mỗi phần sẽ chạy thành một background job riêng.', count($split_state['sections']))
                    );
                } else {
                    unset($context['content_split']);
                    $split_enabled = false;
                    $this->append_log($post_id, $context, 'info', 'content', 'Không nhận diện được ít nhất 2 mục H2 trong Outline; chuyển về chế độ viết toàn bài trong một request.');
                }
            }
        } else {
            unset($context['content_split']);
        }

        if ($split_enabled && !empty($split_state['sections'])) {
            $section_index = max(0, absint($split_state['current_index'] ?? 0));
            $sections = array_values($split_state['sections']);
            if (!isset($sections[$section_index])) {
                unset($context['content_split']);
                return new WP_Error('azevent_lab_invalid_content_checkpoint', 'Checkpoint viết theo H2 không hợp lệ. Hãy chạy lại bước Content.');
            }

            $prompts = $this->build_content_section_prompts($context, $split_state, $section_index);
            $generation_options = array(
                'auto_continue' => true,
                'max_continuations' => 1,
                'detect_incomplete_ending' => true,
            );
            $this->append_log(
                $post_id,
                $context,
                'info',
                'content',
                sprintf(
                    'Đang viết phần H2 %d/%d: %s.',
                    $section_index + 1,
                    count($sections),
                    sanitize_text_field($sections[$section_index]['title'] ?? '')
                )
            );
            $this->log_ai_request($post_id, $context, 'content', 4096, $generation_options, $prompts);
            $result = $this->call_text('content', $prompts['user'], $prompts['system'], 4096, $generation_options);
            $this->record_ai_metrics($post_id, $context, 'content', $prompts, $result);
            if (is_wp_error($result)) {
                return $result;
            }

            $section_content = $this->clean_html($result);
            if ($section_content === '') {
                return new WP_Error('azevent_lab_empty_content_section', 'AI không trả về HTML hợp lệ cho phần H2 hiện tại.');
            }

            if (!isset($split_state['parts']) || !is_array($split_state['parts'])) {
                $split_state['parts'] = array();
            }
            $split_state['parts'][$section_index] = $section_content;
            $split_state['current_index'] = $section_index + 1;
            $split_state['updated_at'] = time();
            $context['content_split'] = $split_state;

            if ($split_state['current_index'] < count($sections)) {
                return array(
                    'context' => $context,
                    'continue_step' => true,
                    'progress_message' => sprintf(
                        'Đã lưu phần H2 %d/%d. Plugin sẽ tự chạy phần H2 tiếp theo từ checkpoint này.',
                        $split_state['current_index'],
                        count($sections)
                    ),
                );
            }

            ksort($split_state['parts']);
            $content = $this->clean_html(implode("\n\n", $split_state['parts']));
            if ($content === '') {
                return new WP_Error('azevent_lab_empty_split_content', 'Không thể ghép các phần H2 thành nội dung hoàn chỉnh.');
            }
            $context['results']['content'] = $content;
            $context['content_split'] = array(
                'enabled' => true,
                'completed' => true,
                'total_sections' => count($sections),
                'completed_sections' => count($sections),
                'section_titles' => array_values(array_map(function ($section) {
                    return sanitize_text_field($section['title'] ?? '');
                }, $sections)),
                'started_at' => absint($split_state['started_at'] ?? time()),
                'completed_at' => time(),
            );
            $this->append_log($post_id, $context, 'success', 'content', sprintf('Đã ghép đủ %d phần H2 thành nội dung HTML hoàn chỉnh.', count($sections)));
            return $this->complete_step($context, 'content', 'seo');
        }

        $prompts = $this->build_prompts('content', $context);

        $generation_options = array(
            'auto_continue' => true,
            'max_continuations' => 2,
            'detect_incomplete_ending' => true,
        );
        $this->log_ai_request($post_id, $context, 'content', 8192, $generation_options, $prompts);
        $result = $this->call_text('content', $prompts['user'], $prompts['system'], 8192, $generation_options);
        $this->record_ai_metrics($post_id, $context, 'content', $prompts, $result);
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

    private function build_content_section_prompts(array $context, array $split_state, $section_index)
    {
        $defaults = self::get_default_prompts();
        $system = (string) get_option('azevent_lab_content_system', '');
        $user = (string) get_option('azevent_lab_content_user', '');
        $system = trim($system) === '' ? $defaults['content']['system'] : $system;
        $user = trim($user) === '' ? $defaults['content']['user'] : $user;
        $input = isset($context['input']) && is_array($context['input']) ? $context['input'] : array();
        $sections = array_values($split_state['sections'] ?? array());
        $section = isset($sections[$section_index]) && is_array($sections[$section_index]) ? $sections[$section_index] : array();
        $parts = isset($split_state['parts']) && is_array($split_state['parts']) ? $split_state['parts'] : array();
        ksort($parts);
        $previous_excerpt = $this->tail_text(wp_strip_all_tags(implode("\n", $parts)), 5000);
        $brand = AzEvent_SEO_Content::get_brand_profile();
        $outline_plan = array();
        foreach ($sections as $index => $outline_section) {
            $outline_plan[] = sprintf('%d. %s', $index + 1, sanitize_text_field($outline_section['title'] ?? ''));
        }
        $completed_titles = array_slice($outline_plan, 0, $section_index);
        $replacements = array(
            '{language}' => sanitize_text_field($context['language'] ?? 'Vietnamese'),
            '{keyword}' => sanitize_text_field($input['keyword'] ?? ''),
            '{secondary_keywords}' => implode(', ', (array) ($input['secondary_keywords'] ?? array())),
            '{audience}' => sanitize_textarea_field($input['audience'] ?? ''),
            '{research}' => 'Research đã được tổng hợp vào Content Brief. Chỉ dùng dữ kiện trong Brief và bối cảnh thương hiệu; không tự tạo claim mới.',
            '{brief}' => trim((string) ($section['outline'] ?? $section['title'] ?? '')),
            '{brand_name}' => $brand['azevent_seo_brand_name'],
            '{brand_info}' => $brand['azevent_seo_brand_info'],
            '{brand_solution}' => $brand['azevent_seo_brand_solution'],
            '{content}' => $previous_excerpt,
            '{seo_json}' => '{}',
            '{internal_link_candidates}' => '[]',
            '{competitor_notes}' => 'Đã tổng hợp trong Content Brief.',
            '{serp_snapshot}' => '{}',
        );
        $system = str_replace(array_keys($replacements), array_values($replacements), $system);
        $user = str_replace(array_keys($replacements), array_values($replacements), $user);

        $position_instruction = $section_index === 0
            ? 'Đây là phần đầu: viết mở bài ngắn trước H2 hiện tại, sau đó viết đầy đủ H2 này.'
            : 'Bắt đầu bằng H2 hiện tại và tạo chuyển tiếp tự nhiên từ phần trước; không viết lại mở bài.';
        if ($section_index === count($sections) - 1) {
            $position_instruction .= ' Đây là phần cuối: hoàn tất CTA hoặc đoạn kết nếu Content Brief yêu cầu, nhưng không tự tạo heading “Kết luận” nếu Outline không có.';
        }

        $user .= "\n\n## Chế độ bắt buộc: chỉ viết một phần H2\n";
        $user .= sprintf("- Phần hiện tại: %d/%d — %s\n", $section_index + 1, count($sections), sanitize_text_field($section['title'] ?? ''));
        $user .= "- Toàn bộ thứ tự H2:\n" . implode("\n", $outline_plan) . "\n";
        $user .= "- H2 đã hoàn thành:\n" . ($completed_titles ? implode("\n", $completed_titles) : 'Chưa có.') . "\n";
        $user .= "- Đoạn cuối của nội dung đã viết để nối mạch và tránh lặp:\n" . ($previous_excerpt !== '' ? $previous_excerpt : 'Chưa có nội dung trước.') . "\n";
        $user .= "- Chỉ dẫn vị trí: " . $position_instruction . "\n";
        $user .= "- Chỉ xuất HTML của phần hiện tại. Không viết các H2 khác, không nhắc lại nội dung đã hoàn thành và không giải thích quy trình.";

        return array('system' => $system, 'user' => $user);
    }

    private function extract_outline_sections($brief)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $brief);
        $sections = array();
        $current = null;
        $in_outline = false;
        $section_level = 0;

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            $plain = $this->clean_outline_heading($trimmed);
            if (!$in_outline) {
                if (preg_match('/(?:outline|dàn\s*ý)/ui', $plain)) {
                    $in_outline = true;
                }
                continue;
            }

            if ($sections && preg_match('/^(?:5|6|7|8|9)[\.\)]\s|^(?:chỉ dẫn mở bài|khối hỗ trợ|cta|kế hoạch internal link|cảnh báo)/ui', $plain)) {
                break;
            }

            if (preg_match('/(?:^|[\s*\-])H1\s*[:\.\-–]\s*(.+)$/ui', $trimmed)) {
                continue;
            }

            if (preg_match('/(?:^|[\s*\-])H2\s*[:\.\-–]\s*(.+)$/ui', $trimmed, $match)) {
                if (is_array($current)) {
                    $current['outline'] = trim(implode("\n", $current['lines']));
                    unset($current['lines']);
                    $sections[] = $current;
                }
                if (preg_match('/^(#{2,6})\s+/u', $trimmed, $heading_match)) {
                    $section_level = strlen($heading_match[1]);
                } elseif ($section_level === 0) {
                    $section_level = 2;
                }
                $current = array(
                    'title' => $this->clean_outline_heading($match[1]),
                    'lines' => array($trimmed),
                );
                continue;
            }

            if (preg_match('/(?:^|[\s*\-])H3\s*[:\.\-–]\s*(.+)$/ui', $trimmed)) {
                if (is_array($current)) {
                    $current['lines'][] = $trimmed;
                }
                continue;
            }

            if (preg_match('/^(#{2,6})\s+(.+)$/u', $trimmed, $match)) {
                $level = strlen($match[1]);
                $heading = $this->clean_outline_heading($match[2]);
                if (preg_match('/(?:outline|dàn\s*ý).*h2/ui', $heading)) {
                    continue;
                }
                if ($section_level === 0) {
                    $section_level = $level;
                }
                if ($level < $section_level && $sections) {
                    break;
                }
                if ($level === $section_level) {
                    if (is_array($current)) {
                        $current['outline'] = trim(implode("\n", $current['lines']));
                        unset($current['lines']);
                        $sections[] = $current;
                    }
                    $current = array('title' => $heading, 'lines' => array($trimmed));
                    continue;
                }
            }

            if (is_array($current) && $trimmed !== '') {
                $current['lines'][] = $trimmed;
            }
        }

        if (is_array($current)) {
            $current['outline'] = trim(implode("\n", $current['lines']));
            unset($current['lines']);
            $sections[] = $current;
        }

        $unique = array();
        $filtered = array();
        foreach ($sections as $section) {
            $title = sanitize_text_field($section['title'] ?? '');
            $key = function_exists('mb_strtolower') ? mb_strtolower($title) : strtolower($title);
            if ($title === '' || isset($unique[$key])) {
                continue;
            }
            $unique[$key] = true;
            $section['title'] = $title;
            $filtered[] = $section;
        }
        return $filtered;
    }

    private function clean_outline_heading($value)
    {
        $value = wp_strip_all_tags((string) $value);
        $value = preg_replace('/^[#\s>*+\-]+/u', '', $value);
        $value = preg_replace('/\*\*|__|`/u', '', $value);
        $value = preg_replace('/^(?:\d+(?:\.\d+)*[\.\)]?\s*)?(?:\[?H[23]\]?\s*[:\.\-–]?\s*)?/ui', '', $value);
        return trim((string) $value);
    }

    private function tail_text($value, $length)
    {
        $value = trim((string) $value);
        $length = max(1, absint($length));
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value) > $length ? mb_substr($value, -$length) : $value;
        }
        return strlen($value) > $length ? substr($value, -$length) : $value;
    }

    private function run_seo($post_id, array &$context)
    {
        if (empty($context['results']['content'])) {
            return new WP_Error('azevent_lab_missing_content', 'Chưa có nội dung để tạo SEO metadata.');
        }

        $prompts = $this->build_prompts('seo', $context);

        $this->log_ai_request($post_id, $context, 'seo', 3072, array(), $prompts);
        $result = $this->call_text('seo', $prompts['user'], $prompts['system'], 3072);
        $this->record_ai_metrics($post_id, $context, 'seo', $prompts, $result);
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

    private function run_quality($post_id, array &$context)
    {
        if (empty($context['results']['content']) || empty($context['results']['seo'])) {
            return new WP_Error('azevent_lab_missing_quality_input', 'Thiếu Content hoặc SEO metadata để kiểm tra chất lượng.');
        }

        $candidates = isset($context['internal_link_candidates']) && is_array($context['internal_link_candidates'])
            ? $context['internal_link_candidates']
            : $this->find_internal_link_candidates($context['input']['keyword'], $post_id);
        $original_content = $context['results']['content'];
        $prompts = $this->build_prompts('quality', $context, $candidates);

        $this->log_ai_request($post_id, $context, 'quality', 4096, array(), $prompts);
        $result = $this->call_text('quality', $prompts['user'], $prompts['system'], 4096);
        $this->record_ai_metrics($post_id, $context, 'quality', $prompts, $result);
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

    private function run_finalize($post_id, array &$context, $skip_image)
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
            $this->append_log($post_id, $context, 'info', 'finalize', 'Đang gọi AzEvent API để tạo ảnh đại diện.');
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
            $this->append_log($post_id, $context, 'success', 'finalize', 'Đã tạo, tải lên và gắn ảnh đại diện.');
        } else {
            $this->append_log($post_id, $context, 'info', 'finalize', 'Bỏ qua tạo ảnh đại diện theo lựa chọn hiện tại.');
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
            $brief = wp_kses_post((string) $edits['brief']);
            if ((string) ($context['results']['brief'] ?? '') !== $brief) {
                unset($context['content_split'], $context['results']['content']);
            }
            $context['results']['brief'] = $brief;
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

        $system = str_replace(array_keys($replacements), array_values($replacements), $system);
        $user = str_replace(array_keys($replacements), array_values($replacements), $user);
        if ($step === 'quality' && !empty($context['content_split']['enabled']) && !empty($context['content_split']['completed'])) {
            $user .= "\n\n## Kiểm tra bổ sung cho bài được ghép từ nhiều H2\n";
            $user .= "- Phát hiện và sửa câu chuyển đoạn gượng, ý lặp giữa các H2, phần mở đầu nhỏ bị lặp, CTA lặp và thay đổi giọng văn.\n";
            $user .= "- Bảo đảm toàn bài đọc như một tác giả viết liền mạch; chỉ dùng replacements ngắn, chính xác, không trả lại toàn bộ bài.";
        }

        return array('system' => $system, 'user' => $user);
    }

    private function call_text($step, $user_prompt, $system_prompt, $max_tokens, array $options = array())
    {
        $request = $this->resolve_text_request($step);
        $started_at = microtime(true);
        $service = new AzEvent_AI_Service();
        $result = $service->call_anthropic($user_prompt, $system_prompt, $request['model'], $max_tokens, $options);
        $this->last_ai_metrics = $service->get_last_text_metrics();
        if (empty($this->last_ai_metrics['duration_seconds'])) {
            $this->last_ai_metrics['duration_seconds'] = round(max(0, microtime(true) - $started_at), 3);
        }
        $this->last_ai_metrics['provider'] = $this->last_ai_metrics['provider'] ?? $request['provider'];
        $this->last_ai_metrics['model'] = $this->last_ai_metrics['model'] ?? $request['model'];

        return $result;
    }

    private function resolve_text_request($step)
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
            ? AzEvent_CKey_Client::model_reference(get_option('azevent_seo_ckey_model', ''))
            : sanitize_text_field(get_option('aprg_cliproxy_model', ''));
        $fallback_model = sanitize_text_field(get_option("azevent_seo_{$model_step}_model", $default_model));
        if ($fallback_model === '') {
            $fallback_model = $default_model;
        }
        $model = sanitize_text_field(get_option("azevent_lab_{$step}_model", $fallback_model));
        if ($model === '') {
            $model = $fallback_model;
        }

        if (AzEvent_CKey_Client::is_model_reference($model)) {
            $effective_provider = 'CKey';
        } elseif (AzEvent_API_Client::is_configured()) {
            $effective_provider = 'AzEvent API';
        } else {
            $effective_provider = 'Anthropic trực tiếp';
            if ($model === '') {
                $model = sanitize_text_field(get_option('azevent_seo_anthropic_model', 'claude-3-5-sonnet-20240620'));
            }
        }

        return array(
            'provider' => $effective_provider,
            'model' => $model,
        );
    }

    private function log_ai_request($post_id, array &$context, $step, $max_tokens, array $options = array(), array $prompts = array())
    {
        $request = $this->resolve_text_request($step);
        $continuations = !empty($options['auto_continue'])
            ? min(3, max(1, absint($options['max_continuations'] ?? 2)))
            : 0;
        $prompt_text = (string) ($prompts['system'] ?? '') . (string) ($prompts['user'] ?? '');
        $prompt_length = function_exists('mb_strlen') ? mb_strlen($prompt_text) : strlen($prompt_text);
        $message = sprintf(
            'Đang gọi %s · model %s · input %s ký tự · giới hạn %s output token',
            $request['provider'],
            $request['model'] !== '' ? $request['model'] : '(model mặc định của API)',
            number_format_i18n($prompt_length),
            number_format_i18n(absint($max_tokens))
        );
        if ($request['provider'] === 'AzEvent API') {
            $message .= ' · endpoint ' . AzEvent_API_Client::get_base_url();
        }
        if ($continuations > 0) {
            $message .= sprintf(' · có thể gọi tiếp tối đa %d lần nếu nội dung bị cắt', $continuations);
        }
        $this->append_log($post_id, $context, 'info', $step, $message . '.');
    }

    private function record_ai_metrics($post_id, array &$context, $step, array $prompts, $result)
    {
        $metrics = is_array($this->last_ai_metrics) ? $this->last_ai_metrics : array();
        $reported = !empty($metrics['reported']);
        $prompt_text = (string) ($prompts['system'] ?? '') . (string) ($prompts['user'] ?? '');
        $result_text = is_wp_error($result) ? '' : (string) $result;
        $input_tokens = absint($metrics['input_tokens'] ?? 0);
        $output_tokens = absint($metrics['output_tokens'] ?? 0);
        if (!$reported) {
            $input_tokens = $this->estimate_tokens($prompt_text);
            $output_tokens = $this->estimate_tokens($result_text);
        }
        $total_tokens = absint($metrics['total_tokens'] ?? 0);
        if (!$reported || $total_tokens <= 0) {
            $total_tokens = $input_tokens + $output_tokens;
        }

        if (!isset($context['metrics']) || !is_array($context['metrics'])) {
            $context['metrics'] = array('steps' => array());
        }
        if (!isset($context['metrics']['steps']) || !is_array($context['metrics']['steps'])) {
            $context['metrics']['steps'] = array();
        }
        $current = isset($context['metrics']['steps'][$step]) && is_array($context['metrics']['steps'][$step])
            ? $context['metrics']['steps'][$step]
            : array();
        $current['runs'] = absint($current['runs'] ?? 0) + 1;
        $current['input_tokens'] = absint($current['input_tokens'] ?? 0) + $input_tokens;
        $current['output_tokens'] = absint($current['output_tokens'] ?? 0) + $output_tokens;
        $current['total_tokens'] = absint($current['total_tokens'] ?? 0) + $total_tokens;
        $current['ai_duration_seconds'] = round((float) ($current['ai_duration_seconds'] ?? 0) + (float) ($metrics['duration_seconds'] ?? 0), 3);
        $current['requests'] = absint($current['requests'] ?? 0) + max(1, absint($metrics['requests'] ?? 1));
        $current['attempts'] = absint($current['attempts'] ?? 0) + max(1, absint($metrics['attempts'] ?? 1));
        $current['estimated_runs'] = absint($current['estimated_runs'] ?? 0) + ($reported ? 0 : 1);
        $current['provider'] = sanitize_text_field($metrics['provider'] ?? '');
        $current['model'] = sanitize_text_field($metrics['model'] ?? '');
        $current['last_status'] = is_wp_error($result) ? 'error' : 'success';
        $context['metrics']['steps'][$step] = $current;

        $this->append_log(
            $post_id,
            $context,
            is_wp_error($result) ? 'error' : 'success',
            $step,
            sprintf(
                'AI %s sau %s · input %s token + output %s token = %s token%s · %d request/%d lần thử.',
                is_wp_error($result) ? 'trả lỗi' : 'hoàn tất',
                $this->format_duration((float) ($metrics['duration_seconds'] ?? 0)),
                number_format_i18n($input_tokens),
                number_format_i18n($output_tokens),
                number_format_i18n($total_tokens),
                $reported ? '' : ' (ước tính)',
                max(1, absint($metrics['requests'] ?? 1)),
                max(1, absint($metrics['attempts'] ?? 1))
            )
        );
    }

    private function record_step_duration(array &$context, $step, $seconds, $status)
    {
        if (!isset($context['metrics']) || !is_array($context['metrics'])) {
            $context['metrics'] = array('steps' => array());
        }
        if (!isset($context['metrics']['steps']) || !is_array($context['metrics']['steps'])) {
            $context['metrics']['steps'] = array();
        }
        $current = isset($context['metrics']['steps'][$step]) && is_array($context['metrics']['steps'][$step])
            ? $context['metrics']['steps'][$step]
            : array();
        $current['step_duration_seconds'] = round((float) ($current['step_duration_seconds'] ?? 0) + max(0, (float) $seconds), 3);
        $current['last_status'] = sanitize_key($status);
        $context['metrics']['steps'][$step] = $current;
    }

    private function estimate_tokens($text)
    {
        $length = function_exists('mb_strlen') ? mb_strlen((string) $text) : strlen((string) $text);
        return $length > 0 ? max(1, (int) ceil($length / 3.5)) : 0;
    }

    private function append_log($post_id, array &$context, $level, $step, $message)
    {
        if (!isset($context['logs']) || !is_array($context['logs'])) {
            $context['logs'] = array();
        }
        $context['logs'][] = array(
            'timestamp' => time(),
            'level' => in_array($level, array('info', 'success', 'error'), true) ? $level : 'info',
            'step' => sanitize_key($step),
            'message' => sanitize_text_field($message),
        );
        $context['logs'] = array_slice($context['logs'], -200);
        $context['updated_at'] = time();
        update_post_meta(absint($post_id), self::SESSION_META, $context);
    }

    private function step_label($step)
    {
        $labels = array(
            'research' => 'Research',
            'brief' => 'Content Brief & Outline',
            'content' => 'Content',
            'seo' => 'SEO Metadata',
            'quality' => 'Links & Quality Gate',
            'finalize' => 'Ảnh & Draft',
        );
        return $labels[$step] ?? sanitize_text_field($step);
    }

    private function format_duration($seconds)
    {
        $seconds = max(0, (float) $seconds);
        if ($seconds < 60) {
            return number_format_i18n($seconds, 1) . ' giây';
        }
        $minutes = floor($seconds / 60);
        $remaining = round($seconds - ($minutes * 60));
        return $minutes . ' phút ' . $remaining . ' giây';
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
