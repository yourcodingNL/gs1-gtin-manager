<?php
/**
 * Background Processor
 * 
 * Handles background tasks and cron jobs
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

class GS1_GTIN_Background_Processor {
    
    /**
     * Check pending registrations
     */
    public static function check_pending_registrations() {
        global $wpdb;
        $table = $wpdb->prefix . 'gs1_gtin_assignments';
        
        // Get unique invocation IDs with pending status
        $invocation_ids = $wpdb->get_col(
            "SELECT DISTINCT invocation_id FROM {$table} 
            WHERE status = 'pending_registration' 
            AND invocation_id IS NOT NULL"
        );
        
        if (empty($invocation_ids)) {
            return;
        }
        
        GS1_GTIN_Logger::log(
            sprintf('Checking %d pending registrations', count($invocation_ids)),
            'info'
        );
        
        foreach ($invocation_ids as $invocation_id) {
            $result = GS1_GTIN_Manager::check_registration_results($invocation_id);
            
            if ($result['success']) {
                GS1_GTIN_Logger::log(
                    "Processed registration {$invocation_id}: {$result['updated']} products updated",
                    'info'
                );
            }
            
            // Sleep to avoid rate limiting
            sleep(2);
        }
    }
    
    /**
     * Sync GTIN ranges
     */
    public static function sync_gtin_ranges() {
        $api = new GS1_GTIN_API_Client();
        $result = $api->sync_gtin_ranges();
        
        if ($result['success']) {
            GS1_GTIN_Logger::log(
                "GTIN ranges synced: {$result['synced']} ranges",
                'info'
            );
        } else {
            GS1_GTIN_Logger::log(
                'Failed to sync GTIN ranges',
                'error',
                $result
            );
        }
    }
}
