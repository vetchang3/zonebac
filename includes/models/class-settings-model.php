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
}
