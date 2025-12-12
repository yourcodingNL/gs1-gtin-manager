<?php
/**
 * GTIN Manager - HYBRID A Implementation with GS1 API FIXES
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
     * Get target market country from Reference Data
     */
    private static function get_target_market_country() {
        $countries = GS1_GTIN_Database::get_reference_data('country', true);
        if (!empty($countries)) {
            // Get default country from wp_options
            $default_id = get_option('gs1_default_country');
            if ($default_id) {
                foreach ($countries as $country) {
                    if ($country->id == $default_id) {
                        return $country->value_nl;
                    }
                }
            }
            // Fallback: first active country
            return $countries[0]->value_nl;
        }
        return 'Europese Unie'; // Ultimate fallback
    }
    
    /**
     * Prepare product data for GS1 registration
     * 
     * FIXED: All 4 issues from GS1 developer feedback:
     * 1. PascalCase field names
     * 2. 13-digit GTIN with checkdigit
     * 3. GPC title instead of code
     * 4. Measurement unit with English translation
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
            
            // FORCE CORRECT VALUES - Ignore potentially wrong database values
            
            // FORCE ConsumerUnit = always "Ja" for consumer products
            $consumer_unit = 'Ja';
            
            // Get default packaging type from Reference Data (first active item)
            $packaging_types = GS1_GTIN_Database::get_reference_data('packaging', true);
            $default_packaging = !empty($packaging_types) ? $packaging_types[0]->value_nl : 'Doos';
            
            // FORCE PackagingType - use database value if valid, otherwise use default
            $packaging_type = $default_packaging;
            if (!empty($assignment->packaging_type)) {
                // Check if assignment packaging type exists in reference data
                $valid_packaging = false;
                foreach ($packaging_types as $pt) {
                    if ($pt->value_nl === $assignment->packaging_type) {
                        $valid_packaging = true;
                        $packaging_type = $assignment->packaging_type;
                        break;
                    }
                }
                if (!$valid_packaging) {
                    GS1_GTIN_Logger::log("Invalid packaging type '{$assignment->packaging_type}', using default '{$default_packaging}'", 'warning');
                }
            }
            
            // FORCE NetContent = minimum 1, always integer
            $net_content = 1;
            if (isset($assignment->net_content) && $assignment->net_content && intval($assignment->net_content) > 0) {
                $net_content = intval($assignment->net_content);
            }
            
            // FORCE MeasurementUnit based on product name detection OR database
            $measurement_units = GS1_GTIN_Database::get_reference_data('measurement', true);
            $default_measurement = !empty($measurement_units) ? $measurement_units[0]->value_nl : 'Stuks';
            
            $measurement_unit = $default_measurement;
            $product_name_lower = strtolower($product->get_name());
            
            // First check product name for explicit hints
            if (stripos($product_name_lower, 'paar') !== false || 
                stripos($product_name_lower, 'pair') !== false) {
                // Find "Paar" in reference data
                foreach ($measurement_units as $mu) {
                    if (strtolower($mu->value_nl) === 'paar') {
                        $measurement_unit = $mu->value_nl;
                        break;
                    }
                }
            }
            elseif (stripos($product_name_lower, 'set') !== false) {
                // Find "Sets" in reference data
                foreach ($measurement_units as $mu) {
                    if (strtolower($mu->value_nl) === 'sets') {
                        $measurement_unit = $mu->value_nl;
                        break;
                    }
                }
            }
            elseif (!empty($assignment->measurement_unit)) {
                // Check if assignment measurement unit exists in reference data
                foreach ($measurement_units as $mu) {
                    if ($mu->value_nl === $assignment->measurement_unit) {
                        $measurement_unit = $assignment->measurement_unit;
                        break;
                    }
                }
            }
            
            GS1_GTIN_Logger::log("FORCED correct values", 'debug', [
                'net_content' => $net_content,
                'measurement_unit' => $measurement_unit,
                'packaging_type' => $packaging_type,
                'consumer_unit' => $consumer_unit
            ]);
            
            // Prepare data with FORCED CORRECT field values
            $data = [
                'Gtin' => $assignment->gtin, // 12 digits (without checkdigit)
                'Status' => 'Actief',
                'Description' => substr($product->get_name(), 0, 300), // Max 300 chars
                'BrandName' => $brand,
                'Language' => 'Nederlands',
                'TargetMarketCountry' => self::get_target_market_country(),
                'ConsumerUnit' => $consumer_unit, // FORCED to "Ja"
                'PackagingType' => $packaging_type, // From reference data or default
                'ContractNumber' => $assignment->contract_number,
                'NetContent' => $net_content, // FORCED to integer, minimum 1
                'MeasurementUnit' => $measurement_unit // From reference data or product name
            ];

            GS1_GTIN_Logger::log("Net content: {$net_content}, Unit: {$measurement_unit}", 'debug');
            
            // FIX 4: Add English translation to measurement unit
            $measurement_translations = [
                'Stuks' => 'Stuks (piece)',
                'Paar' => 'Paar (pair)',
                'Sets' => 'Sets (sets)',
                'Gram' => 'Gram (gram)',
                'Kilogram' => 'Kilogram (kilogram)',
                'Liter' => 'Liter (liter)',
                'Milliliter' => 'Milliliter (milliliter)'
            ];
            
            $measurement_unit_capitalized = ucfirst(strtolower($measurement_unit));
            $measurement_unit_with_english = isset($measurement_translations[$measurement_unit_capitalized]) 
                ? $measurement_translations[$measurement_unit_capitalized] 
                : $measurement_unit_capitalized . ' (piece)'; // Default fallback
            
            
            
            GS1_GTIN_Logger::log("Base data built with PascalCase and 13-digit GTIN", 'debug', $data);
            
          // FIX 3: Add GPC TITLE - ALWAYS fresh from product
$item_groups = wp_get_post_terms($product_id, 'pa_xcore_item_group', ['fields' => 'ids']);
if (!empty($item_groups) && !is_wp_error($item_groups)) {
    $gpc_mapping = GS1_GTIN_Database::get_gpc_mapping($item_groups[0]);
    if ($gpc_mapping && !empty($gpc_mapping->gpc_title)) {
        $data['Gpc'] = $gpc_mapping->gpc_title;
        GS1_GTIN_Logger::log("GPC title added: {$gpc_mapping->gpc_title}", 'debug');
    } else {
        GS1_GTIN_Logger::log("No GPC mapping found for item group", 'warning');
    }
} else {
    GS1_GTIN_Logger::log("No item groups found for product", 'warning');
}

// Add optional image
            if ($image_url) {
                $data['ImageUrl'] = $image_url;
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
     * FIXED: Use PascalCase wrapper key
     */
    public static function register_products($product_ids, $registration_data = []) {
        $api = new GS1_GTIN_API_Client();
        
        // Prepare products for registration
        $products = [];
        $index = 1;  // GS1 API wil Index starten bij 1, niet 0!

foreach ($product_ids as $product_id) {
            // Get assignment
            $assignment = GS1_GTIN_Database::get_gtin_assignment($product_id);
            if (!$assignment || $assignment->external_registration) {
                continue;
            }
            // SKIP als product al registered is
if ($assignment->status === 'registered') {
    GS1_GTIN_Logger::log("Product {$product_id} already registered, re-registering with same GTIN", 'info');
}
            // Use provided data or prepare from product
            if (isset($registration_data[$product_id])) {
                $product_data = $registration_data[$product_id];
                
                // VALIDATE against Reference Data - REJECT invalid values
                $validation_errors = [];

                // Validate PackagingType
                if (isset($product_data['PackagingType'])) {
                    $packaging_types = GS1_GTIN_Database::get_reference_data('packaging', true);
                    $valid = false;
                    foreach ($packaging_types as $pt) {
                        if ($pt->value_nl === $product_data['PackagingType']) {
                            $valid = true;
                            break;
                        }
                    }
                    if (!$valid) {
                        $validation_errors[] = "Verpakkingstype '{$product_data['PackagingType']}' is niet geldig";
                    }
                }

                // Validate MeasurementUnit
                if (isset($product_data['MeasurementUnit'])) {
                    $measurement_units = GS1_GTIN_Database::get_reference_data('measurement', true);
                    $valid = false;
                    foreach ($measurement_units as $mu) {
                        if ($mu->value_nl === $product_data['MeasurementUnit']) {
                            $valid = true;
                            break;
                        }
                    }
                    if (!$valid) {
                        $validation_errors[] = "Maateenheid '{$product_data['MeasurementUnit']}' is niet geldig";
                    }
                }

                // If validation errors, SKIP this product
                if (!empty($validation_errors)) {
                    GS1_GTIN_Logger::log('Validation failed for product ' . $product_id, 'error', $validation_errors);
                    continue;
                }
                
                // Sanitize user-edited values (keep PascalCase)
if (isset($product_data['Language'])) {
    $product_data['Language'] = GS1_GTIN_Helpers::convert_language_code($product_data['Language']);
}

if (isset($product_data['TargetMarketCountry'])) {
    $countries = GS1_GTIN_Helpers::get_target_market_countries();
    $country_key = strtolower($product_data['TargetMarketCountry']);
    if (isset($countries[$country_key])) {
        $product_data['TargetMarketCountry'] = $countries[$country_key];
    }
}

// NIET MEER CONVERTEREN! ConsumerUnit is al "Ja" uit prepare_registration_data()
// if (isset($product_data['ConsumerUnit'])) {
//     $product_data['ConsumerUnit'] = GS1_GTIN_Helpers::convert_boolean($product_data['ConsumerUnit']);
// }

if (isset($product_data['Status'])) {
    $product_data['Status'] = GS1_GTIN_Helpers::convert_status($product_data['Status']);
}
                
                // Ensure NetContent is integer
                if (isset($product_data['NetContent'])) {
                    $product_data['NetContent'] = intval($product_data['NetContent']);
                }
                
                // GS1 wil ALLEEN Nederlands, GEEN Engels!
// Verwijder Engels als het er is
if (isset($product_data['MeasurementUnit'])) {
    // Verwijder alles na spatie en haakje: "Paar (pair)" → "Paar"
    $parts = explode(' (', $product_data['MeasurementUnit']);
    $product_data['MeasurementUnit'] = $parts[0];
}
                
                $product_data['Index'] = $index;
                $product_data['Gtin'] = $assignment->gtin; // 12 digits ZONDER checkdigit
                $product_data['ContractNumber'] = $assignment->contract_number;
            } else {
                $product_data = self::prepare_registration_data($product_id);
                if (!$product_data) {
                    continue;
                }
                $product_data['Index'] = $index;
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
                    'gtin' => $assignment->gtin, // Keep 12 digits in DB
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
                // GS1 stuurt GTIN MET leading zero: "08721472560008" (14 chars!)
$gtin = $product['gtin'];
// Strip leading zeros en checkdigit
$gtin = ltrim($gtin, '0'); // "8721472560008" (13 chars)
if (strlen($gtin) === 13) {
    $gtin = substr($gtin, 0, 12); // "872147256000" (12 chars)
}
                
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