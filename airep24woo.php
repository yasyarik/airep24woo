<?php
/**
 * Plugin Name: AiRep24 for WooCommerce
 * Description: Connects WooCommerce stores to AiRep24 assistant, knowledge sync, widget settings, voice mode, trial, and billing.
 * Version: 0.1.0
 * Author: AiRep24
 * Text Domain: airep24woo
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AIREP24WOO_VERSION', '0.1.0');
define('AIREP24WOO_FILE', __FILE__);
define('AIREP24WOO_PATH', plugin_dir_path(__FILE__));
define('AIREP24WOO_URL', plugin_dir_url(__FILE__));
define('AIREP24WOO_OPTION', 'airep24woo_settings');

require_once AIREP24WOO_PATH . 'includes/class-api-client.php';
require_once AIREP24WOO_PATH . 'includes/class-settings.php';
require_once AIREP24WOO_PATH . 'includes/class-admin-page.php';
require_once AIREP24WOO_PATH . 'includes/class-widget.php';
require_once AIREP24WOO_PATH . 'includes/class-sync.php';
require_once AIREP24WOO_PATH . 'includes/class-plugin.php';

add_action('plugins_loaded', static function () {
    AiRep24Woo_Plugin::instance()->init();
});

register_activation_hook(__FILE__, ['AiRep24Woo_Plugin', 'activate']);
