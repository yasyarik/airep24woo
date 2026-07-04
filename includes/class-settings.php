<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AiRep24Woo_Settings
{
    public static function defaults()
    {
        return [
            'api_base_url' => 'https://web.airep24.com',
            'widget_base_url' => 'https://web.airep24.com',
            'connection_token' => '',
            'tenant_id' => '',
            'site_id' => '',
            'bot_key' => '',
            'bot_name' => '',
            'plan' => '',
            'plan_status' => '',
            'widget_enabled' => '1',
            'voice_enabled' => '1',
            'primary_color' => '#176b5b',
            'background_color' => '#ffffff',
            'position' => 'bottom-right',
            'avatar_id' => 'anna',
            'welcome_message' => '',
            'tone' => 'friendly',
            'last_sync_at' => '',
            'last_sync_status' => '',
            'site_palette' => [
                'colors' => [],
                'gradients' => [],
            ],
        ];
    }

    public static function get()
    {
        $settings = get_option(AIREP24WOO_OPTION, []);
        if (!is_array($settings)) {
            $settings = [];
        }
        return array_replace_recursive(self::defaults(), $settings);
    }

    public static function update(array $settings)
    {
        update_option(AIREP24WOO_OPTION, array_replace_recursive(self::defaults(), $settings), false);
    }

    public static function is_connected()
    {
        $settings = self::get();
        return !empty($settings['connection_token']) && !empty($settings['bot_key']);
    }

    public static function sanitize_hex($value, $fallback)
    {
        $value = sanitize_text_field((string) $value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }

    public static function sanitize_css_color_value($value, $fallback)
    {
        $value = trim(wp_strip_all_tags((string) $value));
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return strtolower($value);
        }
        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value)) {
            return $value;
        }
        if (preg_match('/^linear-gradient\([#a-zA-Z0-9\s,().%-]+\)$/', $value)) {
            return $value;
        }
        return $fallback;
    }
}
