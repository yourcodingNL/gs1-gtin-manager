<?php
/**
 * Plugin Name: GS1 GTIN Manager
 * Plugin URI: https://github.com/yourcodingNL/gs1-gtin-manager
 * Description: Beheer GS1 GTIN codes voor WooCommerce producten via de GS1 Nederland API
 * Version: 1.0.0
 * Author: YoCo - Sebastiaan Kalkman
 * Author URI: https://www.yourcoding.nl
 * Text Domain: gs1-gtin-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: yourcodingNL/gs1-gtin-manager
 * Primary Branch: main
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('GS1_GTIN_VERSION', '1.0.0');
define('GS1_GTIN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GS1_GTIN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GS1_GTIN_PLUGIN_BASENAME', plugin_basename(__FILE__));

// GitHub Update Checker
require_once GS1_GTIN_PLUGIN_DIR . 'includes/class-github-updater.php';
if (is_admin()) {
    new GS1_GTIN_GitHub_Updater(__FILE__, 'yourcodingNL', 'gs1-gtin-manager');
}

// Check WooCommerce
add_action('plugins_loaded', 'gs1_gtin_check_woocommerce');
function gs1_gtin_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p><strong>GS1 GTIN Manager</strong> vereist WooCommerce om te functioneren.</p></div>';
        });
        return;
    }
    
    // Initialize plugin
    require_once GS1_GTIN_PLUGIN_DIR . 'includes/class-plugin.php';
    GS1_GTIN_Plugin::instance();
}

// Activation
register_activation_hook(__FILE__, 'gs1_gtin_activate');
function gs1_gtin_activate() {
    require_once GS1_GTIN_PLUGIN_DIR . 'includes/class-logger.php';
    require_once GS1_GTIN_PLUGIN_DIR . 'includes/class-database.php';
    GS1_GTIN_Database::create_tables();
    
    // Default options
    add_option('gs1_gtin_api_mode', 'sandbox');
    add_option('gs1_gtin_log_retention_days', 30);
    
    flush_rewrite_rules();
}

// Deactivation
register_deactivation_hook(__FILE__, 'gs1_gtin_deactivate');
function gs1_gtin_deactivate() {
    flush_rewrite_rules();
}