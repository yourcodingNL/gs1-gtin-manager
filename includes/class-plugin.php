<?php
/**
 * Main Plugin Class
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

class GS1_GTIN_Plugin {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
    require_once GS1_GTIN_PLUGIN_DIR . 'includes/class-logger.php';
    require_once GS1_GTIN_PLUGIN_DIR . 'includes/class-gtin-helpers.php';
    require_once GS1_GTIN_PLUGIN_DIR . 'includes/class-database.php';
    require_once GS1_GTIN_PLUGIN_DIR . 'includes/class-api-client.php';
    require_once GS1_GTIN_PLUGIN_DIR . 'includes/class-gtin-manager.php';
    require_once GS1_GTIN_PLUGIN_DIR . 'includes/class-background-processor.php';
        if (is_admin()) {
            require_once GS1_GTIN_PLUGIN_DIR . 'admin/class-admin.php';
            require_once GS1_GTIN_PLUGIN_DIR . 'admin/class-settings.php';
        }
    }
    
    private function init_hooks() {
        add_action('init', [$this, 'load_textdomain']);
        
        // Schedule cron
        if (!wp_next_scheduled('gs1_gtin_check_registrations')) {
            wp_schedule_event(time(), 'hourly', 'gs1_gtin_check_registrations');
        }
        
        add_action('gs1_gtin_check_registrations', ['GS1_GTIN_Background_Processor', 'check_pending_registrations']);
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('gs1-gtin-manager', false, dirname(GS1_GTIN_PLUGIN_BASENAME) . '/languages');
    }
}
