<?php
/**
 * GTIN Manager
 * 
 * Handles GTIN assignment and management
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

class GS1_GTIN_Manager {
    
    /**
     * Assign GTIN to product
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
        
        // Get next available GTIN
        $gtin = GS1_GTIN_Database::get_next_available_gtin($contract_number);
        
        if (!$gtin) {
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
        
        // Prepare data
        $data = [
            'product_id' => $product_id,
            'gtin' => $gtin,
            'contract_number' => $contract_number,
            'status' => $external ? 'external' : 'pending',
            'external_registration' => $external ? 1 : 0,
            'net_content' => $product->get_weight(),
            'measurement_unit' => 'kilogram'
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
        
        return [
            'success' => true,
            'gtin' => $gtin,
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
                    'gtin' => $result['gtin']
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
        
        // Get weight en converteer naar correct formaat
        $net_content = $product->get_weight();
        $measurement_unit = 'Kilogram (1 kg)';
        
        // Convert weight to grams if needed
        if ($net_content && $net_content < 1) {
            $net_content = $net_content * 1000;
            $measurement_unit = 'Gram (0.001 kg)';
        }
        
        // Ensure numeric value
        if ($net_content) {
            $net_content = floatval($net_content);
        }
        
        // Prepare data met CORRECTE veldwaardes volgens GS1 API spec
        $data = [
            'gtin' => $assignment->gtin,
            'status' => 'Actief', // "Actief" zoals in GS1 voorbeeld
            'description' => substr($product->get_name(), 0, 300), // Max 300 chars
            'brandName' => $brand,
            'language' => 'Nederlands', // "Nederlands" ipv "nl"
            'targetMarketCountry' => 'Nederland', // "Nederland" ipv "NL"
            'consumerUnit' => $assignment->consumer_unit ? 'Ja' : 'Nee', // "Ja"/"Nee" ipv "true"/"false"
            'packagingType' => $assignment->packaging_type ?: 'Doos',
            'contractNumber' => $assignment->contract_number
        ];
        
        // Add optional fields
        if ($assignment->gpc_code) {
            $data['gpc'] = $assignment->gpc_code;
        }
        
        if ($net_content) {
            $data['netContent'] = $net_content; // Numeric value
            $data['measurementUnit'] = $measurement_unit; // "Kilogram (1 kg)" formaat
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
                
                // Ensure correcte veldwaardes (gebruiker kan deze hebben aangepast)
                if (isset($product_data['language']) && $product_data['language'] === 'nl') {
                    $product_data['language'] = 'Nederlands';
                }
                if (isset($product_data['targetMarketCountry']) && $product_data['targetMarketCountry'] === 'NL') {
                    $product_data['targetMarketCountry'] = 'Nederland';
                }
                if (isset($product_data['consumerUnit'])) {
                    if ($product_data['consumerUnit'] === 'true' || $product_data['consumerUnit'] === true) {
                        $product_data['consumerUnit'] = 'Ja';
                    } elseif ($product_data['consumerUnit'] === 'false' || $product_data['consumerUnit'] === false) {
                        $product_data['consumerUnit'] = 'Nee';
                    }
                }
                if (isset($product_data['status']) && strtolower($product_data['status']) === 'active') {
                    $product_data['status'] = 'Actief';
                }
                
                // Ensure netContent is numeric
                if (isset($product_data['netContent']) && is_string($product_data['netContent'])) {
                    $product_data['netContent'] = floatval($product_data['netContent']);
                }
                
                $product_data['index'] = $index;
                $product_data['gtin'] = $assignment->gtin;
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
                
                // Find assignment by GTIN
                global $wpdb;
                $table = $wpdb->prefix . 'gs1_gtin_assignments';
                $assignment = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE gtin = %s AND invocation_id = %s",
                    $product['gtin'],
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