<?php
/**
 * GTIN Manager - HYBRID A Implementation
 * 
 * Handles GTIN assignment and management with 12-digit GTINs (without checkdigit)
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

class GS1_GTIN_Manager {
    
    /**
     * Assign GTIN to product (12 digits, without checkdigit)
     */
    public static function assign_gtin($product_id, $contract_number = null, $external = false) {
        // Check if already has GTIN
        $existing = GS1_GTIN_Database::get_gtin_assignment($product_id);
        if ($existing) {
            return [
                'success' => false,
                'error' => 'Product heeft al een GTIN toegewezen'
            ];
        }
        
        // Get contract number
        if (!$contract_number) {
            $contract_number = get_option('gs1_gtin_default_contract');
        }
        
        if (!$contract_number) {
            return [
                'success' => false,
                'error' => 'Geen contract nummer beschikbaar'
            ];
        }
        
        // Get next available GTIN (12 digits, without checkdigit)
        $gtin12 = GS1_GTIN_Database::get_next_available_gtin($contract_number);
        
        if (!$gtin12) {
            return [
                'success' => false,
                'error' => 'Geen beschikbare GTIN in range'
            ];
        }
        
        // Get product data
        $product = wc_get_product($product_id);
        if (!$product) {
            return [
                'success' => false,
                'error' => 'Product niet gevonden'
            ];
        }
        
        // Get quantity from product (default 1)
        $quantity = get_post_meta($product_id, '_quantity_per_unit', true) ?: 1;
        
        // Bepaal measurement unit
        $measurement_unit = 'Stuks'; // Default
        
        // Check if it's a pair
        if (stripos($product->get_name(), 'paar') !== false || 
            stripos($product->get_name(), 'pair') !== false) {
            $measurement_unit = 'Paar';
            $quantity = 1; // 1 paar
        }
        
        // Prepare data
        $data = [
            'product_id' => $product_id,
            'gtin' => $gtin12, // 12 digits!
            'contract_number' => $contract_number,
            'status' => $external ? 'external' : 'pending',
            'external_registration' => $external ? 1 : 0,
            'net_content' => $quantity,
            'measurement_unit' => $measurement_unit
        ];
        
        // Try to get GPC from Item Group (pa_xcore_item_group) mapping
        $item_groups = wp_get_post_terms($product_id, 'pa_xcore_item_group', ['fields' => 'ids']);
        if (!empty($item_groups) && !is_wp_error($item_groups)) {
            $gpc_mapping = GS1_GTIN_Database::get_gpc_mapping($item_groups[0]);
            if ($gpc_mapping) {
                $data['gpc_code'] = $gpc_mapping->gpc_code;
            }
        }
        
        $assignment_id = GS1_GTIN_Database::save_gtin_assignment($data);
        
        // Calculate checkdigit for display
        $gtin13_display = GS1_GTIN_Helpers::add_checkdigit($gtin12);
        
        return [
            'success' => true,
            'gtin' => $gtin12, // 12 digits for storage/API
            'gtin_display' => $gtin13_display, // 13 digits for user display
            'assignment_id' => $assignment_id
        ];
    }
    
    /**
     * Assign GTINs to multiple products
     */
    public static function assign_gtins_bulk($product_ids, $contract_number = null) {
        $results = [
            'success' => [],
            'errors' => []
        ];
        
        foreach ($product_ids as $product_id) {
            $result = self::assign_gtin($product_id, $contract_number);
            
            if ($result['success']) {
                $results['success'][] = [
                    'product_id' => $product_id,
                    'gtin' => $result['gtin'],
                    'gtin_display' => $result['gtin_display']
                ];
            } else {
                $results['errors'][] = [
                    'product_id' => $product_id,
                    'error' => $result['error']
                ];
            }
        }
        
        GS1_GTIN_Logger::log(
            sprintf('Bulk GTIN assignment: %d success, %d errors', 
                count($results['success']), 
                count($results['errors'])
            ),
            'info',
            $results
        );
        
        return $results;
    }
    
    /**
     * Prepare product data for GS1 registration
     */
    public static function prepare_registration_data($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $assignment = GS1_GTIN_Database::get_gtin_assignment($product_id);
        if (!$assignment) {
            return false;
        }
        
        // Get brand from attributes
        $brand = '';
        $brand_attribute = $product->get_attribute('pa_brand');
        if ($brand_attribute) {
            $brand = $brand_attribute;
        }
        
        // Get first image
        $image_url = '';
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_url = wp_get_attachment_url($image_id);
        }
        
        // Get packaging type from product meta or default
        $packaging_type = get_post_meta($product_id, '_packaging_type', true);
        if (!$packaging_type) {
            $packaging_type = 'Zak'; // Default
        }
        
        // Ensure packaging type is in correct format
        $packaging_mappings = GS1_GTIN_Helpers::get_packaging_types();
        $packaging_key = strtolower(str_replace(' ', '_', $packaging_type));
        if (isset($packaging_mappings[$packaging_key])) {
            $packaging_type = $packaging_mappings[$packaging_key];
        }
        
        // Get net content and measurement unit from assignment or product
        $net_content = $assignment->net_content ?: 1;
        $measurement_unit = $assignment->measurement_unit ?: 'Stuks';
        
        // Ensure correct capitalization
        $measurement_unit = ucfirst(strtolower($measurement_unit));
        
        // Prepare data with CORRECT field values according to GS1 API spec
        $data = [
            'gtin' => $assignment->gtin, // 12 digits (without checkdigit)
            'status' => 'Actief',
            'description' => substr($product->get_name(), 0, 300), // Max 300 chars
            'brandName' => $brand,
            'language' => 'Nederlands',
            'targetMarketCountry' => 'Europese Unie',
            'consumerUnit' => 'Ja', // Always Ja for consumer products
            'packagingType' => $packaging_type,
            'contractNumber' => $assignment->contract_number,
            'netContent' => intval($net_content), // Integer!
            'measurementUnit' => $measurement_unit // "Stuks", "Paar", or "Sets"
        ];
        
        // Add optional fields
        if ($assignment->gpc_code && !empty($assignment->gpc_code)) {
            $data['gpc'] = $assignment->gpc_code;
        }
        
        if ($image_url) {
            $data['imageUrl'] = $image_url;
        }
        
        return $data;
    }
    
    /**
     * Register products with GS1
     */
    public static function register_products($product_ids, $registration_data = []) {
        $api = new GS1_GTIN_API_Client();
        
        // Prepare products for registration
        $products = [];
        $index = 0;
        
        foreach ($product_ids as $product_id) {
            // Get assignment
            $assignment = GS1_GTIN_Database::get_gtin_assignment($product_id);
            if (!$assignment || $assignment->external_registration) {
                continue;
            }
            
            // Use provided data or prepare from product
            if (isset($registration_data[$product_id])) {
                $product_data = $registration_data[$product_id];
                
                // Sanitize and convert user-edited values
                if (isset($product_data['language'])) {
                    $product_data['language'] = GS1_GTIN_Helpers::convert_language_code($product_data['language']);
                }
                
                if (isset($product_data['targetMarketCountry'])) {
                    $countries = GS1_GTIN_Helpers::get_target_market_countries();
                    $country_key = strtolower($product_data['targetMarketCountry']);
                    if (isset($countries[$country_key])) {
                        $product_data['targetMarketCountry'] = $countries[$country_key];
                    }
                }
                
                if (isset($product_data['consumerUnit'])) {
                    $product_data['consumerUnit'] = GS1_GTIN_Helpers::convert_boolean($product_data['consumerUnit']);
                }
                
                if (isset($product_data['status'])) {
                    $product_data['status'] = GS1_GTIN_Helpers::convert_status($product_data['status']);
                }
                
                // Ensure netContent is integer
                if (isset($product_data['netContent'])) {
                    $product_data['netContent'] = intval($product_data['netContent']);
                }
                
                // Ensure measurementUnit has correct capitalization
                if (isset($product_data['measurementUnit'])) {
                    $product_data['measurementUnit'] = ucfirst(strtolower($product_data['measurementUnit']));
                }
                
                $product_data['index'] = $index;
                $product_data['gtin'] = $assignment->gtin; // 12 digits!
                $product_data['contractNumber'] = $assignment->contract_number;
            } else {
                $product_data = self::prepare_registration_data($product_id);
                if (!$product_data) {
                    continue;
                }
                $product_data['index'] = $index;
            }
            
            $products[] = $product_data;
            $index++;
        }
        
        if (empty($products)) {
            return [
                'success' => false,
                'error' => 'Geen producten om te registreren'
            ];
        }
        
        // Make API call
        $result = $api->register_gtin_products($products);
        
        if (!$result['success']) {
            GS1_GTIN_Logger::log('Registration failed', 'error', $result);
            return $result;
        }
        
        // Get invocation ID (plain text response)
        $invocation_id = $result['data'];
        
        if (empty($invocation_id)) {
            return [
                'success' => false,
                'error' => 'Geen invocation ID ontvangen van GS1'
            ];
        }
        
        // Update assignments
        foreach ($product_ids as $product_id) {
            $assignment = GS1_GTIN_Database::get_gtin_assignment($product_id);
            if ($assignment && !$assignment->external_registration) {
                GS1_GTIN_Database::save_gtin_assignment([
                    'product_id' => $product_id,
                    'gtin' => $assignment->gtin,
                    'contract_number' => $assignment->contract_number,
                    'status' => 'pending_registration',
                    'invocation_id' => $invocation_id
                ]);
            }
        }
        
        GS1_GTIN_Logger::log_registration($invocation_id, count($products), 'initiated');
        
        return [
            'success' => true,
            'invocation_id' => $invocation_id,
            'products_count' => count($products)
        ];
    }
    
    /**
     * Check registration results
     */
    public static function check_registration_results($invocation_id) {
        $api = new GS1_GTIN_API_Client();
        $result = $api->get_registration_results($invocation_id);
        
        if (!$result['success']) {
            return $result;
        }
        
        $data = $result['data'];
        $updated = 0;
        
        // Process successful products
        if (!empty($data['successfulProducts'])) {
            foreach ($data['successfulProducts'] as $product) {
                if (empty($product['gtin'])) {
                    continue;
                }
                
                // Remove checkdigit if GS1 added it (we store 12 digits)
                $gtin = strlen($product['gtin']) === 13 
                    ? GS1_GTIN_Helpers::remove_checkdigit($product['gtin'])
                    : $product['gtin'];
                
                // Find assignment by GTIN
                global $wpdb;
                $table = $wpdb->prefix . 'gs1_gtin_assignments';
                $assignment = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE gtin = %s AND invocation_id = %s",
                    $gtin,
                    $invocation_id
                ));
                
                if ($assignment) {
                    GS1_GTIN_Database::save_gtin_assignment([
                        'product_id' => $assignment->product_id,
                        'gtin' => $assignment->gtin,
                        'contract_number' => $assignment->contract_number,
                        'status' => 'registered',
                        'invocation_id' => $invocation_id
                    ]);
                    
                    // Update synced timestamp
                    $wpdb->update(
                        $table,
                        ['synced_to_gs1_at' => current_time('mysql')],
                        ['id' => $assignment->id],
                        ['%s'],
                        ['%d']
                    );
                    
                    $updated++;
                }
            }
        }
        
        // Process errors
        if (!empty($data['errorMessages'])) {
            foreach ($data['errorMessages'] as $error) {
                if (empty($error['contractNumber'])) {
                    continue;
                }
                
                // Find assignment
                global $wpdb;
                $table = $wpdb->prefix . 'gs1_gtin_assignments';
                $assignment = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE contract_number = %s AND invocation_id = %s LIMIT 1",
                    $error['contractNumber'],
                    $invocation_id
                ));
                
                if ($assignment) {
                    $error_message = !empty($error['errorMessageNl']) 
                        ? $error['errorMessageNl'] 
                        : $error['errorMessageEn'];
                    
                    GS1_GTIN_Database::save_gtin_assignment([
                        'product_id' => $assignment->product_id,
                        'gtin' => $assignment->gtin,
                        'contract_number' => $assignment->contract_number,
                        'status' => 'error',
                        'invocation_id' => $invocation_id,
                        'error_message' => $error_message
                    ]);
                }
            }
        }
        
        GS1_GTIN_Logger::log_registration($invocation_id, $updated, 'completed');
        
        return [
            'success' => true,
            'updated' => $updated,
            'data' => $data
        ];
    }
    
    /**
     * Get product EAN from meta
     */
    public static function get_product_ean($product_id) {
        return get_post_meta($product_id, 'ean_13', true);
    }
    
    /**
     * Update product EAN meta
     */
    public static function update_product_ean($product_id, $ean) {
        update_post_meta($product_id, 'ean_13', $ean);
        GS1_GTIN_Logger::log("EAN updated for product {$product_id}: {$ean}", 'info');
    }
}