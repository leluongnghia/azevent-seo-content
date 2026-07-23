<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_GEO_Prompts
{
    const CONTENT_STUDIO = 'content_studio';
    const WORKFLOW_LAB = 'workflow_lab';

    public static function get_defaults($workflow)
    {
        $workflow = sanitize_key($workflow);
        if ($workflow === self::CONTENT_STUDIO) {
            return require AZEVENT_SEO_PATH . 'includes/geo-prompts/content-studio.php';
        }
        if ($workflow === self::WORKFLOW_LAB) {
            return require AZEVENT_SEO_PATH . 'includes/geo-prompts/workflow-lab.php';
        }
        return array();
    }

    public static function get_priority($workflow, $step)
    {
        $workflow = sanitize_key($workflow);
        $step = sanitize_key($step);
        $defaults = self::get_defaults($workflow);
        if (!array_key_exists($step, $defaults)) {
            return '';
        }

        $value = (string) get_option(self::option_name($workflow, $step), '');
        return trim($value) === '' ? (string) $defaults[$step] : $value;
    }

    public static function append($prompt, $workflow, $step, $enabled)
    {
        if (!$enabled) {
            return $prompt;
        }

        $priority = trim(self::get_priority($workflow, $step));
        if ($priority === '') {
            return $prompt;
        }

        return rtrim((string) $prompt) . "\n\n" . $priority;
    }

    public static function option_name($workflow, $step)
    {
        return 'azevent_geo_' . sanitize_key($workflow) . '_' . sanitize_key($step);
    }
}
