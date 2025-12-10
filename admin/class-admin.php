<?php
/**
 * Admin Class
 * 
 * Handles admin menu and pages
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

class GS1_GTIN_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_gs1_search_products', [$this, 'ajax_search_products']);
        add_action('wp_ajax_gs1_assign_gtins', [$this, 'ajax_assign_gtins']);
        add_action('wp_ajax_gs1_unassign_gtins', [$this, 'ajax_unassign_gtins']);
        add_action('wp_ajax_gs1_get_registration_data', [$this, 'ajax_get_registration_data']);
        add_action('wp_ajax_gs1_submit_registration', [$this, 'ajax_submit_registration']);
        add_action('wp_ajax_gs1_sync_ranges', [$this, 'ajax_sync_ranges']);
        add_action('wp_ajax_gs1_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_gs1_get_log', [$this, 'ajax_get_log']);
        add_action('wp_ajax_gs1_clear_log', [$this, 'ajax_clear_log']);
        add_action('wp_ajax_gs1_delete_log', [$this, 'ajax_delete_log']);
    }
    
    public function add_menu_pages() {
        add_submenu_page(
            'edit.php?post_type=product',
            'GS1 GTIN Beheer',
            'GS1 GTIN Beheer',
            'manage_woocommerce',
            'gs1-gtin-manager',
            [$this, 'render_main_page']
        );
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'gs1-gtin-manager') === false && strpos($hook, 'gs1-gtin-settings') === false) {
            return;
        }
        
        wp_enqueue_style(
            'gs1-gtin-admin',
            GS1_GTIN_PLUGIN_URL . 'admin/assets/css/admin.css',
            [],
            GS1_GTIN_VERSION
        );
        
        wp_enqueue_script(
            'gs1-gtin-admin',
            GS1_GTIN_PLUGIN_URL . 'admin/assets/js/admin.js',
            ['jquery'],
            time(),
            true
        );
        
        wp_localize_script('gs1-gtin-admin', 'gs1GtinAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gs1_gtin_nonce'),
            'strings' => [
                'confirmDelete' => 'Weet je zeker dat je deze toewijzing wilt verwijderen?',
                'confirmRegister' => 'Weet je zeker dat je deze producten wilt registreren bij GS1?',
                'searching' => 'Zoeken...',
                'loading' => 'Laden...',
                'error' => 'Er is een fout opgetreden',
                'success' => 'Succesvol uitgevoerd'
            ]
        ]);
    }
    
    public function render_main_page() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        
        ?>
        <div class="wrap gs1-gtin-manager">
            <h1>
                GS1 GTIN Beheer
                <span class="gs1-version">v<?php echo GS1_GTIN_VERSION; ?></span>
            </h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?post_type=product&page=gs1-gtin-manager&tab=overview" 
                   class="nav-tab <?php echo $tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    Overzicht
                </a>
                <a href="?post_type=product&page=gs1-gtin-manager&tab=ranges" 
                   class="nav-tab <?php echo $tab === 'ranges' ? 'nav-tab-active' : ''; ?>">
                    GTIN Ranges
                </a>
                <a href="?post_type=product&page=gs1-gtin-manager&tab=status" 
                   class="nav-tab <?php echo $tab === 'status' ? 'nav-tab-active' : ''; ?>">
                    Registratie Status
                </a>
                <a href="?post_type=product&page=gs1-gtin-manager&tab=logs" 
                   class="nav-tab <?php echo $tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    Logs
                </a>
                <a href="?post_type=product&page=gs1-gtin-manager&tab=settings" 
                   class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    Instellingen
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($tab) {
                    case 'overview':
                        $this->render_overview_tab();
                        break;
                    case 'ranges':
                        $this->render_ranges_tab();
                        break;
                    case 'status':
                        $this->render_status_tab();
                        break;
                    case 'logs':
                        $this->render_logs_tab();
                        break;
                    case 'settings':
                        $settings = new GS1_GTIN_Settings();
                        $settings->render_settings_page();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_overview_tab() {
        include GS1_GTIN_PLUGIN_DIR . 'admin/views/overview.php';
    }
    
    private function render_ranges_tab() {
        include GS1_GTIN_PLUGIN_DIR . 'admin/views/ranges.php';
    }
    
    private function render_status_tab() {
        include GS1_GTIN_PLUGIN_DIR . 'admin/views/status.php';
    }
    
    private function render_logs_tab() {
        include GS1_GTIN_PLUGIN_DIR . 'admin/views/logs.php';
    }
    
    // AJAX handlers
    
    public function ajax_search_products() {
        check_ajax_referer('gs1_gtin_nonce', 'nonce');
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $brand = isset($_POST['brand']) ? sanitize_text_field($_POST['brand']) : '';
        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 50;
        
        // WooCommerce producten ophalen
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        $query = new WP_Query($args);
        
        $results = [];
        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) continue;
            
            $assignment = GS1_GTIN_Database::get_gtin_assignment($post->ID);
            
            $ean = get_post_meta($post->ID, 'ean_13', true);
            $brand_attr = $product->get_attribute('pa_brand');
            
            // Bepaal correcte status
            if (!$assignment) {
                // Geen assignment = geen GTIN
                $status = 'no_gtin';
                $gtin = '-';
                $external = false;
                $error_message = '';
            } else {
                // Wel assignment = heeft GTIN
                $status = $assignment->status;
                $gtin = $assignment->gtin;
                $external = $assignment->external_registration;
                $error_message = $assignment->error_message;
            }
            
            // Status filter
            if (!empty($status_filter)) {
                if ($status_filter === 'pending' && $status !== 'pending') {
                    continue; // Alleen pending (= heeft GTIN, niet geregistreerd)
                }
                if ($status_filter !== 'pending' && $status_filter !== $status) {
                    continue;
                }
            }
            
            // Brand filter
            if (!empty($brand) && $brand_attr !== $brand) {
                continue;
            }
            
            $results[] = [
                'product_id' => $post->ID,
                'product_name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'ean' => $ean,
                'gtin' => $gtin,
                'status' => $status,
                'brand' => $brand_attr,
                'external' => $external,
                'error_message' => $error_message
            ];
        }
        
        wp_send_json_success([
            'products' => $results,
            'total' => $query->found_posts,
            'page' => $page,
            'total_pages' => $query->max_num_pages
        ]);
    }
    
    public function ajax_assign_gtins() {
        check_ajax_referer('gs1_gtin_nonce', 'nonce');
        
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];
        $external = isset($_POST['external']) ? (bool) $_POST['external'] : false;
        
        if (empty($product_ids)) {
            wp_send_json_error(['message' => 'Geen producten geselecteerd']);
        }
        
        $results = GS1_GTIN_Manager::assign_gtins_bulk($product_ids);
        
        wp_send_json_success($results);
    }
    
    public function ajax_unassign_gtins() {
        check_ajax_referer('gs1_gtin_nonce', 'nonce');
        
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];
        
        if (empty($product_ids)) {
            wp_send_json_error(['message' => 'Geen producten geselecteerd']);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'gs1_gtin_assignments';
        $deleted = 0;
        
        foreach ($product_ids as $product_id) {
            $result = $wpdb->delete($table, ['product_id' => $product_id], ['%d']);
            if ($result) {
                $deleted++;
                GS1_GTIN_Logger::log("GTIN assignment deleted for product {$product_id}", 'info');
            }
        }
        
        wp_send_json_success([
            'deleted' => $deleted,
            'message' => "{$deleted} GTIN(s) verwijderd"
        ]);
    }
    
    public function ajax_get_registration_data() {
    GS1_GTIN_Logger::log('=== AJAX GET REGISTRATION DATA START ===', 'debug');
    
    try {
        check_ajax_referer('gs1_gtin_nonce', 'nonce');
        
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];
        
        GS1_GTIN_Logger::log('Product IDs received', 'debug', ['product_ids' => $product_ids]);
        
        if (empty($product_ids)) {
            GS1_GTIN_Logger::log('No product IDs provided', 'error');
            wp_send_json_error(['message' => 'Geen producten geselecteerd']);
        }
        
        $products_data = [];
        
        foreach ($product_ids as $product_id) {
            GS1_GTIN_Logger::log("Processing product {$product_id}", 'debug');
            
            try {
                $product = wc_get_product($product_id);
                if (!$product) {
                    GS1_GTIN_Logger::log("Product {$product_id} not found", 'warning');
                    continue;
                }
                
                GS1_GTIN_Logger::log("Product {$product_id} loaded: {$product->get_name()}", 'debug');
                
                $assignment = GS1_GTIN_Database::get_gtin_assignment($product_id);
                
                if (!$assignment) {
                    GS1_GTIN_Logger::log("Product {$product_id} has no GTIN assignment", 'warning');
                    continue;
                }
                
                GS1_GTIN_Logger::log("Product {$product_id} assignment found", 'debug', [
                    'gtin' => $assignment->gtin,
                    'status' => $assignment->status,
                    'external' => $assignment->external_registration
                ]);
                
                if ($assignment->external_registration) {
                    GS1_GTIN_Logger::log("Product {$product_id} is external registration, skipping", 'info');
                    continue;
                }
                
                GS1_GTIN_Logger::log("Preparing registration data for product {$product_id}", 'debug');
                
                $product_data = GS1_GTIN_Manager::prepare_registration_data($product_id);
                
                if (!$product_data) {
                    GS1_GTIN_Logger::log("Failed to prepare registration data for product {$product_id}", 'error');
                    continue;
                }
                
                GS1_GTIN_Logger::log("Registration data prepared for product {$product_id}", 'debug', $product_data);
                
                $products_data[] = [
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'data' => $product_data
                ];
                
                GS1_GTIN_Logger::log("Product {$product_id} added to registration batch", 'info');
                
            } catch (Exception $e) {
                GS1_GTIN_Logger::log("EXCEPTION processing product {$product_id}", 'error', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                continue;
            }
        }
        
        GS1_GTIN_Logger::log("Registration data preparation complete", 'info', [
            'total_products' => count($products_data)
        ]);
        
        wp_send_json_success(['products' => $products_data]);
        
    } catch (Exception $e) {
        GS1_GTIN_Logger::log("FATAL EXCEPTION in ajax_get_registration_data", 'error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        wp_send_json_error(['message' => 'Server error: ' . $e->getMessage()]);
    }
    
    GS1_GTIN_Logger::log('=== AJAX GET REGISTRATION DATA END ===', 'debug');
}
    
public function ajax_submit_registration() {
        check_ajax_referer('gs1_gtin_nonce', 'nonce');
        
        $products_data = isset($_POST['products_data']) ? $_POST['products_data'] : [];
        
        if (empty($products_data)) {
            wp_send_json_error(['message' => 'Geen product data ontvangen']);
        }
        
        $product_ids = [];
        $registration_data = [];
        
        foreach ($products_data as $item) {
            $product_id = intval($item['product_id']);
            $product_ids[] = $product_id;
            $registration_data[$product_id] = $item['data'];
        }
        
        $result = GS1_GTIN_Manager::register_products($product_ids, $registration_data);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_sync_ranges() {
        check_ajax_referer('gs1_gtin_nonce', 'nonce');
        
        $api = new GS1_GTIN_API_Client();
        $result = $api->sync_gtin_ranges();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_test_connection() {
        check_ajax_referer('gs1_gtin_nonce', 'nonce');
        
        $api = new GS1_GTIN_API_Client();
        $success = $api->test_connection();
        
        if ($success) {
            wp_send_json_success(['message' => 'API verbinding succesvol']);
        } else {
            wp_send_json_error(['message' => 'API verbinding mislukt - check de logs']);
        }
    }
    
    public function ajax_get_log() {
        check_ajax_referer('gs1_gtin_nonce', 'nonce');
        
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : '';
        
        if (empty($filename)) {
            wp_send_json_error(['message' => 'Geen bestandsnaam opgegeven']);
        }
        
        $content = GS1_GTIN_Logger::read_log($filename);
        
        if ($content === false) {
            wp_send_json_error(['message' => 'Bestand niet gevonden']);
        }
        
        wp_send_json_success(['content' => $content]);
    }
    
    public function ajax_clear_log() {
        check_ajax_referer('gs1_gtin_nonce', 'nonce');
        
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : '';
        
        if (empty($filename)) {
            wp_send_json_error(['message' => 'Geen bestandsnaam opgegeven']);
        }
        
        $log_dir = wp_upload_dir()['basedir'] . '/gs1-gtin-logs/';
        $file_path = $log_dir . $filename;
        
        if (!file_exists($file_path)) {
            wp_send_json_error(['message' => 'Bestand niet gevonden']);
        }
        
        file_put_contents($file_path, '');
        
        GS1_GTIN_Logger::log('Log file cleared: ' . $filename, 'info');
        
        wp_send_json_success(['message' => 'Log geleegd']);
    }
    
    public function ajax_delete_log() {
        check_ajax_referer('gs1_gtin_nonce', 'nonce');
        
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : '';
        
        if (empty($filename)) {
            wp_send_json_error(['message' => 'Geen bestandsnaam opgegeven']);
        }
        
        // Check if it's today's log file
        $today_log = 'gs1-gtin-' . date('Y-m-d') . '.log';
        if ($filename === $today_log) {
            wp_send_json_error(['message' => 'Kan het huidige dag log bestand niet verwijderen. Gebruik "Leeg bestand" om de inhoud te wissen.']);
        }
        
        $log_dir = wp_upload_dir()['basedir'] . '/gs1-gtin-logs/';
        $file_path = $log_dir . $filename;
        
        if (!file_exists($file_path)) {
            wp_send_json_error(['message' => 'Bestand niet gevonden']);
        }
        
        if (!unlink($file_path)) {
            wp_send_json_error(['message' => 'Kon bestand niet verwijderen. Mogelijk is het bestand nog in gebruik.']);
        }
        
        GS1_GTIN_Logger::log('Log file deleted: ' . $filename, 'info');
        
        wp_send_json_success(['message' => 'Log bestand verwijderd']);
    }
}

new GS1_GTIN_Admin();