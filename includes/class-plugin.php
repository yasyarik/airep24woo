<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AiRep24Woo_Plugin
{
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate()
    {
        $settings = AiRep24Woo_Settings::get();
        AiRep24Woo_Settings::update($settings);
    }

    public function init()
    {
        load_plugin_textdomain('airep24woo', false, dirname(plugin_basename(AIREP24WOO_FILE)) . '/languages');

        AiRep24Woo_Admin_Page::init();
        AiRep24Woo_Widget::init();
        AiRep24Woo_Sync::init();
    }
}
