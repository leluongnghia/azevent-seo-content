<?php

if (!defined('ABSPATH')) {
    exit;
}

class AzEvent_GEO_Prompts
{
    const CONTENT_STUDIO = 'content_studio';
    const WORKFLOW_LAB = 'workflow_lab';
    const TEMPLATE_VERSION_OPTION = 'azevent_geo_prompt_template_version';
    const TEMPLATE_VERSION = 'english-v1';

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

    public static function maybe_upgrade_to_english()
    {
        if ((string) get_option(self::TEMPLATE_VERSION_OPTION, '') === self::TEMPLATE_VERSION) {
            return;
        }

        $legacy_hashes = array(
            self::CONTENT_STUDIO => array(
                'intent' => '0fa621dde7c52bd75afbed22533b1f4526ff52cd9d88158d8a72c83b73256acd',
                'outline' => 'a4b755b14d90f20d21687cd431f401224e32acd11de90f9fcc93074153120d5a',
                'content' => '3a138e1a31ab6dc0562648e833f98f0342c30abe4806f30d439d664f0210247a',
                'seo' => '586870e023925aaa3a62b5d03974786f47f32229d356082744289b2453a67820',
                'rewrite' => 'ce5c13ceedc09935cfbfa3ebfc0646a5112008e41862cefccec36198a3f86907',
            ),
            self::WORKFLOW_LAB => array(
                'research' => 'b1cc0d12c6ccdb012586ab564c85f72c2067202dcd4cb6ad4423842d8a03e5b0',
                'brief' => '1735007bcebe308ab3beac9226708a73a30ba75bf5519ddbf1d9184e63074e69',
                'outline_validation' => '10bbe1400ef9a5ea6300eab4f525e30e2ab5dbfa5f3ea434cc003ef8690bff45',
                'content' => '4a0343f69f8b1b8372f83e00b250edfb2d4674edaff8dfa6c35416cd5c2e7841',
                'seo' => '82fedf6fdbb0f621a86c50cf208c2a2cca347317cce979c91e9f683f85c23eb2',
                'quality' => '19ad32eeab1b93811622d94e0205531f6a37936b8d5e69da75f5856051f4de3a',
            ),
        );

        foreach (array(self::CONTENT_STUDIO, self::WORKFLOW_LAB) as $workflow) {
            foreach (self::get_defaults($workflow) as $step => $default_prompt) {
                $option = self::option_name($workflow, $step);
                $saved_prompt = get_option($option, null);
                $saved_hash = is_string($saved_prompt) && trim($saved_prompt) !== ''
                    ? hash('sha256', trim($saved_prompt))
                    : '';
                $is_legacy_default = isset($legacy_hashes[$workflow][$step])
                    && hash_equals($legacy_hashes[$workflow][$step], $saved_hash);

                if ($saved_prompt === null || trim((string) $saved_prompt) === '' || $is_legacy_default) {
                    update_option($option, $default_prompt, false);
                }
            }
        }

        update_option(self::TEMPLATE_VERSION_OPTION, self::TEMPLATE_VERSION, false);
    }
}
