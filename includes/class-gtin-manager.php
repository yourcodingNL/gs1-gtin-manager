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
    GS1_GTIN_Logger::log("=== PREPARE REGISTRATION DATA START: Product {$product_id} ===", 'debug');
    
    try {
        // Get product
        $product = wc_get_product($product_id);
        if (!$product) {
            GS1_GTIN_Logger::log("Product {$product_id} not found in prepare_registration_data", 'error');
            return false;
        }
        
        GS1_GTIN_Logger::log("Product loaded", 'debug', [
            'id' => $product_id,
            'name' => $product->get_name(),
            'type' => $product->get_type()
        ]);
        
        // Get assignment
        $assignment = GS1_GTIN_Database::get_gtin_assignment($product_id);
        if (!$assignment) {
            GS1_GTIN_Logger::log("No assignment found for product {$product_id}", 'error');
            return false;
        }
        
        GS1_GTIN_Logger::log("Assignment loaded", 'debug', [
            'gtin' => $assignment->gtin,
            'contract' => $assignment->contract_number,
            'status' => $assignment->status
        ]);
        
        // Get brand
        $brand = '';
        try {
            $brand_attribute = $product->get_attribute('pa_brand');
            if ($brand_attribute) {
                $brand = $brand_attribute;
                GS1_GTIN_Logger::log("Brand found: {$brand}", 'debug');
            } else {
                GS1_GTIN_Logger::log("No brand attribute found", 'debug');
            }
        } catch (Exception $e) {
            GS1_GTIN_Logger::log("Error getting brand", 'warning', ['error' => $e->getMessage()]);
        }
        
        // Get image
        $image_url = '';
        try {
            $image_id = $product->get_image_id();
            if ($image_id) {
                $image_url = wp_get_attachment_url($image_id);
                GS1_GTIN_Logger::log("Image found: {$image_url}", 'debug');
            } else {
                GS1_GTIN_Logger::log("No image found", 'debug');
            }
        } catch (Exception $e) {
            GS1_GTIN_Logger::log("Error getting image", 'warning', ['error' => $e->getMessage()]);
        }
        
        // Get packaging type
        $packaging_type = get_post_meta($product_id, '_packaging_type', true);
        if (!$packaging_type) {
            $packaging_type = 'Zak';
            GS1_GTIN_Logger::log("No packaging type, using default: Zak", 'debug');
        } else {
            GS1_GTIN_Logger::log("Packaging type: {$packaging_type}", 'debug');
        }
        GS1_GTIN_Logger::log("About to get packaging mappings", 'debug');

// Ensure correct format
try {
    $packaging_mappings = GS1_GTIN_Helpers::get_packaging_types();
    GS1_GTIN_Logger::log("Got packaging mappings", 'debug', ['count' => count($packaging_mappings)]);
} catch (Exception $e) {
    GS1_GTIN_Logger::log("EXCEPTION getting packaging mappings", 'error', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    $packaging_mappings = ['zak' => 'Zak'];
}
        $packaging_key = strtolower(str_replace(' ', '_', $packaging_type));
        if (isset($packaging_mappings[$packaging_key])) {
            $packaging_type = $packaging_mappings[$packaging_key];
        }
        
        GS1_GTIN_Logger::log("Packaging type after mapping: {$packaging_type}", 'debug');
        
        // Get net content and measurement unit
$net_content = isset($assignment->net_content) && $assignment->net_content ? $assignment->net_content : 1;
$measurement_unit = isset($assignment->measurement_unit) && $assignment->measurement_unit ? $assignment->measurement_unit : 'Stuks';

GS1_GTIN_Logger::log("Net content: {$net_content}, Unit: {$measurement_unit}", 'debug');
        
        // Ensure correct capitalization
        $measurement_unit = ucfirst(strtolower($measurement_unit));
        
        // Build data array
        $data = [
            'gtin' => $assignment->gtin,
            'status' => 'Actief',
            'description' => substr($product->get_name(), 0, 300),
            'brandName' => $brand,
            'language' => 'Nederlands',
            'targetMarketCountry' => 'Europese Unie',
            'consumerUnit' => 'Ja',
            'packagingType' => $packaging_type,
            'contractNumber' => $assignment->contract_number,
            'netContent' => intval($net_content),
            'measurementUnit' => $measurement_unit
        ];
        
        GS1_GTIN_Logger::log("Base data built", 'debug', $data);
        
        // Add optional GPC
        if (!empty($assignment->gpc_code)) {
            $data['gpc'] = $assignment->gpc_code;
            GS1_GTIN_Logger::log("GPC code added: {$assignment->gpc_code}", 'debug');
        } else {
            GS1_GTIN_Logger::log("No GPC code in assignment", 'debug');
        }
        
        // Add optional image
        if ($image_url) {
            $data['imageUrl'] = $image_url;
            GS1_GTIN_Logger::log("Image URL added", 'debug');
        }
        
        GS1_GTIN_Logger::log("=== PREPARE REGISTRATION DATA SUCCESS ===", 'info', $data);
        
        return $data;
        
    } catch (Exception $e) {
        GS1_GTIN_Logger::log("=== PREPARE REGISTRATION DATA EXCEPTION ===", 'error', [
            'product_id' => $product_id,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    }
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
                    'gtin' => GS1_GTIN_Helpers::add_checkdigit($assignment->gtin),
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