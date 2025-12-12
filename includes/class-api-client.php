<?php
/**
 * GS1 API Client - FIXED for GS1 API requirements
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
    
    private $client_id;
    private $client_secret;
    private $account_number;
    private $api_mode;
    private $base_url;
    
    public function __construct() {
        $this->api_mode = get_option('gs1_gtin_api_mode', 'sandbox');
        
        if ($this->api_mode === 'live') {
            $this->client_id = get_option('gs1_gtin_client_id_live');
            $this->client_secret = get_option('gs1_gtin_client_secret_live');
            $this->base_url = 'https://gs1nl-api.gs1.nl/basic-product-data-in';
        } else {
            $this->client_id = get_option('gs1_gtin_client_id_sandbox');
            $this->client_secret = get_option('gs1_gtin_client_secret_sandbox');
            $this->base_url = 'https://gs1nl-api-acc.gs1.nl/basic-product-data-in';
        }
        
        $this->account_number = get_option('gs1_gtin_account_number');
    }
    
    /**
     * Get OAuth2 access token
     */
    private function get_access_token() {
        // Check cached token
        $cache_key = 'gs1_access_token_' . $this->api_mode;
        $cached = get_transient($cache_key);
        
        if ($cached) {
            return $cached;
        }
        
        $token_url = $this->api_mode === 'live' 
            ? 'https://gs1nl-api.gs1.nl/authorization/token'
            : 'https://gs1nl-api-acc.gs1.nl/authorization/token';
        
        // Request new token - CLIENT ID/SECRET IN HEADERS!
        $response = wp_remote_post($token_url, [
            'headers' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            GS1_GTIN_Logger::log('OAuth token request failed: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['access_token'])) {
            GS1_GTIN_Logger::log('No access token in OAuth response', 'error', $body);
            return false;
        }
        
        $token = $body['access_token'];
        $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) - 60 : 3540;
        
        // Cache token
        set_transient($cache_key, $token, $expires_in);
        
        GS1_GTIN_Logger::log('OAuth token obtained successfully', 'info');
        
        return $token;
    }
    
    /**
     * Make API request
     */
    private function request($endpoint, $method = 'GET', $data = null) {
        // Get access token
        $access_token = $this->get_access_token();
        
        if (!$access_token) {
            return [
                'success' => false,
                'error' => 'Could not obtain access token'
            ];
        }
        
        $url = $this->base_url . $endpoint;
        
        $headers = [
            'Authorization' => 'Bearer ' . $access_token,
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

        // LOG EXACTE JSON DIE NAAR GS1 GAAT
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $json_body = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            GS1_GTIN_Logger::log("=== EXACTE JSON NAAR GS1 API ===", 'info', [
                'endpoint' => $endpoint,
                'json_body' => $json_body,
                'json_length' => strlen($json_body)
            ]);
        }

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
        
        // BELANGRIJK: 202 response is plain text (InvocationId), geen JSON!
        if ($response_code === 202 && strpos($endpoint, '/RegistrateGtinProducts') !== false) {
            GS1_GTIN_Logger::log("API Request: {$method} {$endpoint}", 'info', [
                'endpoint' => $endpoint,
                'method' => $method,
                'request' => $data,
                'response' => $response_body,
                'response_code' => $response_code
            ]);
            
            return [
    'success' => true,
    'data' => trim($response_body, '"'), // Remove quotes from InvocationId!
    'code' => $response_code
];
        }
        
        // Andere responses zijn JSON
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
     * FIXED: Use PascalCase wrapper key 'RegistrationProducts'
     */
    public function register_gtin_products($products) {
        $data = [
            'RegistrationProducts' => $products, // FIXED: PascalCase!
            'AccountNumber' => $this->account_number
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
        // Wis ALLE oude ranges voor verse start
        GS1_GTIN_Database::delete_all_gtin_ranges();
        
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
        
        // Sync existing GTINs from GS1
        $this->sync_existing_gtins_from_gs1();
        
        GS1_GTIN_Logger::log("Synced {$synced} GTIN ranges from API", 'info');
        
        return [
            'success' => true,
            'synced' => $synced
        ];
    }
    
    /**
     * Sync existing GTINs that are already registered at GS1
     */
    private function sync_existing_gtins_from_gs1() {
        // Check registration status to get last invocation
        $status_result = $this->get_registration_status();
        
        if (!$status_result['success'] || empty($status_result['data'])) {
            GS1_GTIN_Logger::log('No previous registrations found at GS1', 'info');
            return;
        }
        
        // De response is een object met Item1 en Item2
        $invocation_id = null;
        
        if (isset($status_result['data']['Item1'])) {
            $invocation_id = $status_result['data']['Item1'];
        } elseif (isset($status_result['data']['invocationId'])) {
            $invocation_id = $status_result['data']['invocationId'];
        }
        
        if (!$invocation_id || empty($invocation_id)) {
            GS1_GTIN_Logger::log('No invocation ID in status response', 'info');
            return;
        }
        
        $results = $this->get_registration_results($invocation_id);
        
        if (!$results['success']) {
            return;
        }
        
        $existing_gtins = 0;
        
        if (!empty($results['data']['successfulProducts'])) {
            foreach ($results['data']['successfulProducts'] as $product) {
                if (empty($product['gtin'])) {
                    continue;
                }
                
                // Check if GTIN already exists in our database
                $existing = GS1_GTIN_Database::get_gtin_assignment_by_gtin($product['gtin']);
                
                if (!$existing) {
                    // Mark GTIN as used (external registration)
                    GS1_GTIN_Database::mark_gtin_as_external($product['gtin'], [
                        'contract_number' => $product['contractNumber'] ?? null,
                        'description' => $product['description'] ?? null,
                        'brand' => $product['brandName'] ?? null
                    ]);
                    
                    $existing_gtins++;
                }
            }
        }
        
        if ($existing_gtins > 0) {
            GS1_GTIN_Logger::log("Synced {$existing_gtins} existing GTINs from GS1", 'info');
        }
    }
}