<?php
/**
 * Database Class
 * 
 * Handles all database operations
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

class GS1_GTIN_Database {
    
    /**
     * Create plugin tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // GTIN Assignments table
        $table_assignments = $wpdb->prefix . 'gs1_gtin_assignments';
        $sql_assignments = "CREATE TABLE {$table_assignments} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            gtin varchar(14) NOT NULL,
            contract_number varchar(50) DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            invocation_id varchar(100) DEFAULT NULL,
            error_message text DEFAULT NULL,
            gpc_code varchar(20) DEFAULT NULL,
            packaging_type varchar(100) DEFAULT 'Doos',
            net_content varchar(50) DEFAULT NULL,
            measurement_unit varchar(50) DEFAULT NULL,
            consumer_unit tinyint(1) DEFAULT 1,
            external_registration tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            synced_to_gs1_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY product_id (product_id),
            UNIQUE KEY gtin (gtin),
            KEY status (status),
            KEY invocation_id (invocation_id)
        ) $charset_collate;";
        
        dbDelta($sql_assignments);
        
        // GTIN Ranges table
        $table_ranges = $wpdb->prefix . 'gs1_gtin_ranges';
        $sql_ranges = "CREATE TABLE {$table_ranges} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            start_number varchar(14) NOT NULL,
            end_number varchar(14) NOT NULL,
            contract_number varchar(50) NOT NULL,
            last_used varchar(14) DEFAULT NULL,
            total_available int(11) DEFAULT 0,
            total_used int(11) DEFAULT 0,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contract_number (contract_number)
        ) $charset_collate;";
        
        dbDelta($sql_ranges);
        
        // Logs table
        $table_logs = $wpdb->prefix . 'gs1_gtin_logs';
        $sql_logs = "CREATE TABLE {$table_logs} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) DEFAULT 'info',
            message text NOT NULL,
            context longtext DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY timestamp (timestamp),
            KEY level (level),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql_logs);
        
        // GPC Mappings table
        $table_gpc = $wpdb->prefix . 'gs1_gtin_gpc_mappings';
        $sql_gpc = "CREATE TABLE {$table_gpc} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            wc_category_id bigint(20) UNSIGNED NOT NULL,
            gpc_code varchar(20) NOT NULL,
            gpc_title varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY wc_category_id (wc_category_id),
            KEY gpc_code (gpc_code)
        ) $charset_collate;";
        
        dbDelta($sql_gpc);
        
        GS1_GTIN_Logger::log('Database tables created/updated', 'info');
    }
    
    /**
     * Get GTIN assignment for product
     */
    public static function get_gtin_assignment($product_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'gs1_gtin_assignments';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE product_id = %d",
            $product_id
        ));
    }
    
    /**
     * Get all GTIN assignments with filters
     */
    public static function get_gtin_assignments($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'gs1_gtin_assignments';
        
        $defaults = [
            'status' => '',
            'search' => '',
            'brand' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ['1=1'];
        
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        if (!empty($args['search'])) {
            $where[] = $wpdb->prepare(
                "(gtin LIKE %s OR product_id IN (
                    SELECT ID FROM {$wpdb->posts} 
                    WHERE post_title LIKE %s 
                    OR post_name LIKE %s
                ))",
                '%' . $wpdb->esc_like($args['search']) . '%',
                '%' . $wpdb->esc_like($args['search']) . '%',
                '%' . $wpdb->esc_like($args['search']) . '%'
            );
        }
        
        if (!empty($args['brand'])) {
            // Get products with this brand
            $brand_products = get_posts([
                'post_type' => 'product',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => [
                    [
                        'taxonomy' => 'pa_brand',
                        'field' => 'slug',
                        'terms' => $args['brand']
                    ]
                ]
            ]);
            
            if (!empty($brand_products)) {
                $where[] = "product_id IN (" . implode(',', array_map('intval', $brand_products)) . ")";
            } else {
                $where[] = "1=0"; // No results if brand has no products
            }
        }
        
        $where_clause = implode(' AND ', $where);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE {$where_clause}
            ORDER BY {$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        ));
        
        return $results;
    }
    
    /**
     * Count GTIN assignments
     */
    public static function count_gtin_assignments($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'gs1_gtin_assignments';
        
        $where = ['1=1'];
        
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        if (!empty($args['search'])) {
            $where[] = $wpdb->prepare(
                "(gtin LIKE %s OR product_id IN (
                    SELECT ID FROM {$wpdb->posts} 
                    WHERE post_title LIKE %s
                ))",
                '%' . $wpdb->esc_like($args['search']) . '%',
                '%' . $wpdb->esc_like($args['search']) . '%'
            );
        }
        
        $where_clause = implode(' AND ', $where);
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where_clause}");
    }
    
    /**
     * Save GTIN assignment
     */
    public static function save_gtin_assignment($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'gs1_gtin_assignments';
        
        $defaults = [
            'product_id' => 0,
            'gtin' => '',
            'contract_number' => '',
            'status' => 'pending',
            'invocation_id' => null,
            'error_message' => null,
            'gpc_code' => null,
            'packaging_type' => 'Doos',
            'net_content' => null,
            'measurement_unit' => null,
            'consumer_unit' => 1,
            'external_registration' => 0
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Check if exists
        $existing = self::get_gtin_assignment($data['product_id']);
        
        if ($existing) {
            $wpdb->update(
                $table,
                $data,
                ['product_id' => $data['product_id']],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d'],
                ['%d']
            );
            
            GS1_GTIN_Logger::log_gtin_assignment($data['product_id'], $data['gtin'], 'updated');
            
            return $existing->id;
        } else {
            $wpdb->insert(
                $table,
                $data,
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d']
            );
            
            GS1_GTIN_Logger::log_gtin_assignment($data['product_id'], $data['gtin'], 'created');
            
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Get GTIN ranges
     */
    public static function get_gtin_ranges($contract_number = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'gs1_gtin_ranges';
        
        if ($contract_number) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE contract_number = %s ORDER BY start_number",
                $contract_number
            ));
        }
        
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY contract_number, start_number");
    }
    
    /**
     * Save GTIN range
     */
    public static function save_gtin_range($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'gs1_gtin_ranges';
        
        // Calculate total available
        if (!empty($data['start_number']) && !empty($data['end_number'])) {
            $data['total_available'] = intval($data['end_number']) - intval($data['start_number']) + 1;
        }
        
        // Check if exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE contract_number = %s AND start_number = %s",
            $data['contract_number'],
            $data['start_number']
        ));
        
        if ($existing) {
            $wpdb->update(
                $table,
                $data,
                ['id' => $existing->id],
                ['%s', '%s', '%s', '%s', '%d', '%d'],
                ['%d']
            );
            return $existing->id;
        } else {
            $wpdb->insert(
                $table,
                $data,
                ['%s', '%s', '%s', '%s', '%d', '%d']
            );
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Get next available GTIN
     */
    public static function get_next_available_gtin($contract_number) {
        global $wpdb;
        $ranges_table = $wpdb->prefix . 'gs1_gtin_ranges';
        $assignments_table = $wpdb->prefix . 'gs1_gtin_assignments';
        
        // Get range for this contract
        $range = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$ranges_table} WHERE contract_number = %s ORDER BY start_number LIMIT 1",
            $contract_number
        ));
        
        if (!$range) {
            return false;
        }
        
        // Get last used GTIN
        if ($range->last_used) {
            $next_gtin = strval(intval($range->last_used) + 1);
        } else {
            $next_gtin = $range->start_number;
        }
        
        // Check if within range
        if (intval($next_gtin) > intval($range->end_number)) {
            GS1_GTIN_Logger::log("GTIN range uitgeput voor contract {$contract_number}", 'error');
            return false;
        }
        
        // Check if already assigned (double check)
        $already_assigned = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$assignments_table} WHERE gtin = %s",
            $next_gtin
        ));
        
        if ($already_assigned > 0) {
            // Find next available
            $max_assigned = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(CAST(gtin AS UNSIGNED)) FROM {$assignments_table} 
                WHERE contract_number = %s AND CAST(gtin AS UNSIGNED) BETWEEN %d AND %d",
                $contract_number,
                intval($range->start_number),
                intval($range->end_number)
            ));
            
            if ($max_assigned) {
                $next_gtin = strval(intval($max_assigned) + 1);
            }
        }
        
        // Update last used
        $wpdb->update(
            $ranges_table,
            ['last_used' => $next_gtin],
            ['id' => $range->id],
            ['%s'],
            ['%d']
        );
        
        return $next_gtin;
    }
    
    /**
     * Get GPC mapping for category
     */
    public static function get_gpc_mapping($category_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'gs1_gtin_gpc_mappings';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE wc_category_id = %d",
            $category_id
        ));
    }
    
    /**
     * Save GPC mapping
     */
    public static function save_gpc_mapping($category_id, $gpc_code, $gpc_title) {
        global $wpdb;
        $table = $wpdb->prefix . 'gs1_gtin_gpc_mappings';
        
        $existing = self::get_gpc_mapping($category_id);
        
        if ($existing) {
            $wpdb->update(
                $table,
                [
                    'gpc_code' => $gpc_code,
                    'gpc_title' => $gpc_title
                ],
                ['wc_category_id' => $category_id],
                ['%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'wc_category_id' => $category_id,
                    'gpc_code' => $gpc_code,
                    'gpc_title' => $gpc_title
                ],
                ['%d', '%s', '%s']
            );
        }
        
        GS1_GTIN_Logger::log("GPC mapping saved: Category {$category_id} -> {$gpc_code}", 'info');
    }
}
