<?php
/**
 * GTIN Helpers
 * 
 * Helper functions for GTIN checkdigit calculation and field mappings
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

class GS1_GTIN_Helpers {
    
    /**
     * Calculate EAN-13 checkdigit from 12-digit GTIN
     * 
     * @param string $gtin12 12-digit GTIN without checkdigit
     * @return int Checkdigit (0-9)
     */
    public static function calculate_checkdigit($gtin12) {
        // Ensure 12 digits
        $gtin12 = str_pad($gtin12, 12, '0', STR_PAD_LEFT);
        
        $sum_odd = 0;
        $sum_even = 0;
        
        for ($i = 0; $i < 12; $i++) {
            if ($i % 2 == 0) {
                // Odd positions (1st, 3rd, 5th, etc.) - multiply by 1
                $sum_odd += intval($gtin12[$i]);
            } else {
                // Even positions (2nd, 4th, 6th, etc.) - multiply by 3
                $sum_even += intval($gtin12[$i]);
            }
        }
        
        $total = $sum_odd + ($sum_even * 3);
        $checkdigit = (10 - ($total % 10)) % 10;
        
        return $checkdigit;
    }
    
    /**
     * Add checkdigit to 12-digit GTIN
     * 
     * @param string $gtin12 12-digit GTIN
     * @return string 13-digit GTIN with checkdigit
     */
    public static function add_checkdigit($gtin12) {
        $checkdigit = self::calculate_checkdigit($gtin12);
        return $gtin12 . $checkdigit;
    }
    
    /**
     * Remove checkdigit from 13-digit GTIN
     * 
     * @param string $gtin13 13-digit GTIN
     * @return string 12-digit GTIN without checkdigit
     */
    public static function remove_checkdigit($gtin13) {
        return substr($gtin13, 0, 12);
    }
    
    /**
     * Validate GTIN checkdigit
     * 
     * @param string $gtin13 13-digit GTIN
     * @return bool True if checkdigit is valid
     */
    public static function validate_checkdigit($gtin13) {
        if (strlen($gtin13) !== 13) {
            return false;
        }
        
        $gtin12 = substr($gtin13, 0, 12);
        $provided_checkdigit = intval(substr($gtin13, 12, 1));
        $calculated_checkdigit = self::calculate_checkdigit($gtin12);
        
        return $provided_checkdigit === $calculated_checkdigit;
    }
    
    /**
     * Get measurement unit mappings
     * 
     * @return array Measurement units for GS1 API
     */
    public static function get_measurement_units() {
        return [
            'stuks' => 'Stuks',
            'paar' => 'Paar',
            'sets' => 'Sets'
        ];
    }
    
    /**
     * Get packaging type mappings
     * 
     * @return array Packaging types for GS1 API
     */
    public static function get_packaging_types() {
        return [
            'doos' => 'Doos',
            'zak' => 'Zak',
            'niet_verpakt' => 'Niet verpakt',
            'pot' => 'Pot',
            'hoesje' => 'Hoesje',
            'blister' => 'Blisterverpakking',
            'kaart' => 'Kaart',
            'tube' => 'Tube',
            'overig' => 'Zak' // Default
        ];
    }
    
    /**
     * Get target market countries
     * 
     * @return array Countries for GS1 API
     */
    public static function get_target_market_countries() {
        return [
            'eu' => 'Europese Unie',
            'nl' => 'Nederland',
            'be' => 'België',
            'de' => 'Duitsland'
        ];
    }
    
    /**
     * Convert ISO language code to GS1 format
     * 
     * @param string $iso_code ISO 639-1 code (e.g., 'nl', 'en')
     * @return string GS1 language name
     */
    public static function convert_language_code($iso_code) {
        $mappings = [
            'nl' => 'Nederlands',
            'en' => 'Engels',
            'de' => 'Duits',
            'fr' => 'Frans'
        ];
        
        return $mappings[$iso_code] ?? 'Nederlands';
    }
    
    /**
     * Convert boolean to GS1 Yes/No format
     * 
     * @param mixed $value Boolean or string value
     * @return string 'Ja' or 'Nee'
     */
    public static function convert_boolean($value) {
        if ($value === 'true' || $value === true || $value === 1 || $value === '1') {
            return 'Ja';
        }
        return 'Nee';
    }
    
    /**
     * Convert status to GS1 format
     * 
     * @param string $status Status value
     * @return string 'Actief' or 'Inactief'
     */
    public static function convert_status($status) {
        $mappings = [
            'active' => 'Actief',
            'inactive' => 'Inactief',
            'pending' => 'Actief' // Default
        ];
        
        $status_lower = strtolower($status);
        return $mappings[$status_lower] ?? 'Actief';
    }
}