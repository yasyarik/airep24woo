<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AiRep24Woo_Widget
{
    public static function init()
    {
        add_action('wp_footer', [__CLASS__, 'render_embed'], 100);
    }

    public static function render_embed()
    {
        if (is_admin()) {
            return;
        }

        $settings = AiRep24Woo_Settings::get();
        if (empty($settings['widget_enabled']) || empty($settings['bot_key'])) {
            return;
        }

        $src = trailingslashit($settings['widget_base_url']) . 'v1/widgets/' . rawurlencode($settings['bot_key']) . '/embed.js';
        printf("\n<!-- AiRep24 Assistant -->\n<script async src=\"%s\"></script>\n", esc_url($src));
    }
}
