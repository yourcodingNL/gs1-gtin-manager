<?php
/**
 * GS1 API Client
 * 
 * Handles all communication with GS1 Nederland API
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

class GS1_GTIN_API_Client {
    
    private $api_token;
    private $account_number;
    private $api_mode;
    private $base_url;
    
    public function __construct() {
        $this->api_mode = get_option('gs1_gtin_api_mode', 'sandbox');
        $this->api_token = $this->api_mode === 'live' 
            ? get_option('gs1_gtin_api_token_live') 
            : get_option('gs1_gtin_api_token_sandbox');
        $this->account_number = get_option('gs1_gtin_account_number');
        
        $this->base_url = $this->api_mode === 'live'
            ? 'https://gs1nl-api.gs1.nl/basic-product-data-in'
            : 'https://gs1nl-api-acc.gs1.nl/basic-product-data-in';
    }
    
    /**
     * Make API request
     */
    private function request($endpoint, $method = 'GET', $data = null) {
        $url = $this->base_url . $endpoint;
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        
        // Add AccountNumber header if needed
        if (strpos($endpoint, '/GtinCodeRanges') !== false) {
            $headers['AccountNumberHeader'] = $this->account_number;
        }
        
        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
        ];
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }
        
        GS1_GTIN_Logger::log("API Request: {$method} {$endpoint}", 'debug', [
            'url' => $url,
            'data' => $data
        ]);
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            GS1_GTIN_Logger::log("API Error: {$error_message}", 'error', [
                'endpoint' => $endpoint,
                'method' => $method
            ]);
            return [
                'success' => false,
                'error' => $error_message
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        GS1_GTIN_Logger::log_api_request($endpoint, $method, $data, $response_data, $response_code);
        
        if ($response_code >= 200 && $response_code < 300) {
            return [
                'success' => true,
                'data' => $response_data,
                'code' => $response_code
            ];
        }
        
        return [
            'success' => false,
            'error' => $response_data,
            'code' => $response_code
        ];
    }
    
    /**
     * Get GTIN Code Ranges
     */
    public function get_gtin_code_ranges() {
        return $this->request('/GtinCodeRanges', 'GET');
    }
    
    /**
     * Register GTIN products in bulk
     */
    public function register_gtin_products($products) {
        $data = [
            'registrationProducts' => $products,
            'accountNumber' => $this->account_number
        ];
        
        return $this->request('/RegistrateGtinProducts', 'POST', $data);
    }
    
    /**
     * Get registration status
     */
    public function get_registration_status() {
        return $this->request('/RegistrateProductStatus', 'GET');
    }
    
    /**
     * Get registration results
     */
    public function get_registration_results($invocation_id) {
        return $this->request('/RegistrateProductResults/' . $invocation_id, 'GET');
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $result = $this->get_gtin_code_ranges();
        
        if ($result['success']) {
            GS1_GTIN_Logger::log('API connection test successful', 'info');
            return true;
        } else {
            GS1_GTIN_Logger::log('API connection test failed', 'error', $result);
            return false;
        }
    }
    
    /**
     * Sync GTIN ranges from API
     */
    public function sync_gtin_ranges() {
        $result = $this->get_gtin_code_ranges();
        
        if (!$result['success']) {
            return $result;
        }
        
        $ranges = $result['data'];
        $synced = 0;
        
        // Handle single range or array of ranges
        if (!isset($ranges[0])) {
            $ranges = [$ranges];
        }
        
        foreach ($ranges as $range) {
            if (empty($range['startNumber']) || empty($range['endNumber'])) {
                continue;
            }
            
            GS1_GTIN_Database::save_gtin_range([
                'start_number' => $range['startNumber'],
                'end_number' => $range['endNumber'],
                'contract_number' => $range['contractNumber'] ?? $this->account_number
            ]);
            
            $synced++;
        }
        
        GS1_GTIN_Logger::log("Synced {$synced} GTIN ranges from API", 'info');
        
        return [
            'success' => true,
            'synced' => $synced
        ];
    }
}
