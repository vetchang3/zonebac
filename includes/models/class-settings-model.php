<?php

class Zonebac_Settings_Model
{
    private static $option_name = 'zb_lms_settings';

    public static function get_api_key()
    {
        $options = get_option(self::$option_name, []);
        return $options['deepseek_key'] ?? '';
    }

    public static function save_settings($data)
    {
        update_option(self::$option_name, $data);
    }

    public static function get_settings()
    {
        $settings = get_option('zb_lms_settings');
        return is_array($settings) ? $settings : [];
    }

    public static function log_api_usage($api_name, $prompt_tokens, $completion_tokens)
    {
        $usage = get_option('zb_api_usage', []);

        if (!isset($usage[$api_name])) {
            $usage[$api_name] = [
                'prompt' => 0,
                'completion' => 0,
                'last_update' => ''
            ];
        }

        $usage[$api_name]['prompt'] += intval($prompt_tokens);
        $usage[$api_name]['completion'] += intval($completion_tokens);
        $usage[$api_name]['last_update'] = current_time('mysql');

        update_option('zb_api_usage', $usage);
    }
}
