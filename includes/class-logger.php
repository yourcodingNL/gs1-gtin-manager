<?php
/**
 * Logger Class
 * 
 * Dedicated logging system voor GS1 GTIN Manager
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

class GS1_GTIN_Logger {
    
    private static $log_file = null;
    
    /**
     * Initialize logger
     */
    public static function init() {
        if (is_null(self::$log_file)) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/gs1-gtin-logs';
            
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
                // Protect log directory
                file_put_contents($log_dir . '/.htaccess', 'Deny from all');
                file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
            }
            
            self::$log_file = $log_dir . '/gs1-gtin-' . date('Y-m-d') . '.log';
        }
    }
    
    /**
     * Log message
     * 
     * @param string $message Message to log
     * @param string $level Log level: info, warning, error, debug
     * @param array $context Additional context data
     */
    public static function log($message, $level = 'info', $context = []) {
        self::init();
        
        $timestamp = current_time('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $user_info = $user_id ? " [User: {$user_id}]" : '';
        
        $log_entry = sprintf(
            "[%s] [%s]%s %s\n",
            $timestamp,
            strtoupper($level),
            $user_info,
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= "Context: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        
        $log_entry .= str_repeat('-', 80) . "\n";
        
        error_log($log_entry, 3, self::$log_file);
        
        // Also store in database for easy viewing
        global $wpdb;
        $table_name = $wpdb->prefix . 'gs1_gtin_logs';
        
        $wpdb->insert(
            $table_name,
            [
                'timestamp' => current_time('mysql'),
                'level' => $level,
                'message' => $message,
                'context' => !empty($context) ? json_encode($context) : null,
                'user_id' => $user_id
            ],
            ['%s', '%s', '%s', '%s', '%d']
        );
    }
    
    /**
     * Log API request
     */
    public static function log_api_request($endpoint, $method, $request_data, $response, $response_code) {
        self::log(
            "API Request: {$method} {$endpoint}",
            'info',
            [
                'endpoint' => $endpoint,
                'method' => $method,
                'request' => $request_data,
                'response' => $response,
                'response_code' => $response_code
            ]
        );
    }
    
    /**
     * Log GTIN assignment
     */
    public static function log_gtin_assignment($product_id, $gtin, $status = 'assigned') {
        self::log(
            "GTIN {$status} voor product {$product_id}: {$gtin}",
            'info',
            [
                'product_id' => $product_id,
                'gtin' => $gtin,
                'status' => $status
            ]
        );
    }
    
    /**
     * Log registration process
     */
    public static function log_registration($invocation_id, $products_count, $status) {
        self::log(
            "Registratie {$invocation_id}: {$products_count} producten - Status: {$status}",
            'info',
            [
                'invocation_id' => $invocation_id,
                'products_count' => $products_count,
                'status' => $status
            ]
        );
    }
    
    /**
     * Get log files
     */
    public static function get_log_files() {
        self::init();
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/gs1-gtin-logs';
        
        if (!file_exists($log_dir)) {
            return [];
        }
        
        $files = glob($log_dir . '/gs1-gtin-*.log');
        $log_files = [];
        
        foreach ($files as $file) {
            $log_files[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file)
            ];
        }
        
        usort($log_files, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $log_files;
    }
    
    /**
     * Read log file
     */
    public static function read_log($filename) {
        self::init();
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/gs1-gtin-logs';
        $file_path = $log_dir . '/' . basename($filename);
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        return file_get_contents($file_path);
    }
    
    /**
     * Clean old logs
     */
    public static function cleanup_old_logs() {
        $retention_days = get_option('gs1_gtin_log_retention_days', 30);
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/gs1-gtin-logs';
        
        if (!file_exists($log_dir)) {
            return;
        }
        
        $files = glob($log_dir . '/gs1-gtin-*.log');
        $cutoff_time = strtotime("-{$retention_days} days");
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
                self::log("Oude log verwijderd: " . basename($file), 'info');
            }
        }
        
        // Also clean database logs
        global $wpdb;
        $table_name = $wpdb->prefix . 'gs1_gtin_logs';
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );
    }
}

// Schedule log cleanup
if (!wp_next_scheduled('gs1_gtin_cleanup_logs')) {
    wp_schedule_event(time(), 'daily', 'gs1_gtin_cleanup_logs');
}
add_action('gs1_gtin_cleanup_logs', ['GS1_GTIN_Logger', 'cleanup_old_logs']);
