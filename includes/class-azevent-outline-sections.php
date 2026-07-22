<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_Outline_Sections
{
    public static function extract($outline)
    {
        $outline = preg_replace_callback('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/isu', function ($match) {
            return "\n" . str_repeat('#', absint($match[1])) . ' ' . wp_strip_all_tags($match[2]) . "\n";
        }, (string) $outline);
        $lines = preg_split('/\r\n|\r|\n/', (string) $outline);
        $sections = array();
        $current = null;
        $in_outline = false;
        $section_level = 0;

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            $plain = self::clean_heading($trimmed);
            if (!$in_outline) {
                if (preg_match('/(?:outline|dàn\s*ý)/ui', $plain)
                    || preg_match('/(?:^|[\s*\-])H[12]\s*[:\.\-–]/ui', $trimmed)
                    || preg_match('/^#{1,2}\s+/u', $trimmed)) {
                    $in_outline = true;
                } else {
                    continue;
                }
            }

            if ($sections && preg_match('/^(?:5|6|7|8|9)[\.\)]\s|^(?:chỉ dẫn mở bài|khối hỗ trợ|cta|kế hoạch internal link|cảnh báo)/ui', $plain)) {
                break;
            }
            if (preg_match('/(?:^|[\s*\-])H1\s*[:\.\-–]\s*(.+)$/ui', $trimmed)) {
                continue;
            }
            if (preg_match('/(?:^|[\s*\-])H2\s*[:\.\-–]\s*(.+)$/ui', $trimmed, $match)) {
                self::finish_section($sections, $current);
                if (preg_match('/^(#{2,6})\s+/u', $trimmed, $heading_match)) {
                    $section_level = strlen($heading_match[1]);
                } elseif ($section_level === 0) {
                    $section_level = 2;
                }
                $current = array('title' => self::clean_heading($match[1]), 'lines' => array($trimmed));
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
                $heading = self::clean_heading($match[2]);
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
                    self::finish_section($sections, $current);
                    $current = array('title' => $heading, 'lines' => array($trimmed));
                    continue;
                }
            }
            if (is_array($current) && $trimmed !== '') {
                $current['lines'][] = $trimmed;
            }
        }

        self::finish_section($sections, $current);
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

    private static function finish_section(array &$sections, &$current)
    {
        if (!is_array($current)) {
            return;
        }
        $current['outline'] = trim(implode("\n", $current['lines']));
        unset($current['lines']);
        $sections[] = $current;
        $current = null;
    }

    private static function clean_heading($value)
    {
        $value = wp_strip_all_tags((string) $value);
        $value = preg_replace('/^[#\s>*+\-]+/u', '', $value);
        $value = preg_replace('/\*\*|__|`/u', '', $value);
        $value = preg_replace('/^(?:\d+(?:\.\d+)*[\.\)]?\s*)?(?:\[?H[23]\]?\s*[:\.\-–]?\s*)?/ui', '', $value);
        return trim((string) $value);
    }
}
