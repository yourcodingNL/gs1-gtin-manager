<?php
/**
 * Settings Class
 * 
 * Handles plugin settings
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

class GS1_GTIN_Settings {
    
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_gs1_save_gpc_mapping', [$this, 'ajax_save_gpc_mapping']);
        add_action('wp_ajax_gs1_delete_gpc_mapping', [$this, 'ajax_delete_gpc_mapping']);
    }
    
    public function register_settings() {
        // API Settings
        register_setting('gs1_gtin_settings', 'gs1_gtin_api_mode');
        register_setting('gs1_gtin_settings', 'gs1_gtin_api_token_sandbox');
        register_setting('gs1_gtin_settings', 'gs1_gtin_api_token_live');
        register_setting('gs1_gtin_settings', 'gs1_gtin_account_number');
        register_setting('gs1_gtin_settings', 'gs1_gtin_default_contract');
        register_setting('gs1_gtin_settings', 'gs1_gtin_log_retention_days');
        register_setting('gs1_gtin_settings', 'gs1_gtin_auto_sync_ean');
    }
    
    public function render_settings_page() {
        if (isset($_POST['submit']) && check_admin_referer('gs1_gtin_settings_nonce')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>Instellingen opgeslagen</p></div>';
        }
        
        $api_mode = get_option('gs1_gtin_api_mode', 'sandbox');
        $api_token_sandbox = get_option('gs1_gtin_api_token_sandbox', '');
        $api_token_live = get_option('gs1_gtin_api_token_live', '');
        $account_number = get_option('gs1_gtin_account_number', '');
        $default_contract = get_option('gs1_gtin_default_contract', '');
        $log_retention_days = get_option('gs1_gtin_log_retention_days', 30);
        $auto_sync_ean = get_option('gs1_gtin_auto_sync_ean', 'no');
        
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('gs1_gtin_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">API Modus</th>
                    <td>
                        <label>
                            <input type="radio" name="gs1_gtin_api_mode" value="sandbox" <?php checked($api_mode, 'sandbox'); ?>>
                            Sandbox (Test)
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="gs1_gtin_api_mode" value="live" <?php checked($api_mode, 'live'); ?>>
                            Live (Productie)
                        </label>
                        <p class="description">Start altijd in sandbox modus voor testen</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">API Token (Sandbox)</th>
                    <td>
                        <input type="text" name="gs1_gtin_api_token_sandbox" 
                               value="<?php echo esc_attr($api_token_sandbox); ?>" 
                               class="regular-text" placeholder="Bearer token voor sandbox">
                        <p class="description">API token voor test omgeving</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">API Token (Live)</th>
                    <td>
                        <input type="text" name="gs1_gtin_api_token_live" 
                               value="<?php echo esc_attr($api_token_live); ?>" 
                               class="regular-text" placeholder="Bearer token voor live">
                        <p class="description">API token voor productie omgeving</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Account Nummer</th>
                    <td>
                        <input type="text" name="gs1_gtin_account_number" 
                               value="<?php echo esc_attr($account_number); ?>" 
                               class="regular-text" placeholder="1162186">
                        <p class="description">Jouw GS1 account nummer</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Standaard Contract</th>
                    <td>
                        <input type="text" name="gs1_gtin_default_contract" 
                               value="<?php echo esc_attr($default_contract); ?>" 
                               class="regular-text" placeholder="Contract nummer">
                        <p class="description">Standaard contract voor GTIN toewijzing</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Log Bewaarperiode</th>
                    <td>
                        <input type="number" name="gs1_gtin_log_retention_days" 
                               value="<?php echo esc_attr($log_retention_days); ?>" 
                               min="1" max="365" class="small-text"> dagen
                        <p class="description">Logs ouder dan dit worden automatisch verwijderd</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Auto-sync naar EAN veld</th>
                    <td>
                        <label>
                            <input type="checkbox" name="gs1_gtin_auto_sync_ean" value="yes" 
                                   <?php checked($auto_sync_ean, 'yes'); ?>>
                            Automatisch GTIN naar ean_13 meta veld kopiëren
                        </label>
                        <p class="description">Als actief wordt de GTIN automatisch naar het ean_13 veld gekopieerd</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="submit" class="button button-primary">Instellingen Opslaan</button>
                <button type="button" class="button gs1-test-connection">Test API Verbinding</button>
            </p>
        </form>
        
        <hr>
        
        <h2>GPC Categorie Mappings</h2>
        <p>Koppel WooCommerce productcategorieën aan GS1 GPC codes. Dit zorgt ervoor dat de juiste GPC code automatisch wordt toegewezen.</p>
        
        <div class="gs1-gpc-mappings">
            <?php $this->render_gpc_mappings(); ?>
        </div>
        
        <button type="button" class="button gs1-add-gpc-mapping">Nieuwe Mapping Toevoegen</button>
        
        <script type="text/template" id="gs1-gpc-mapping-template">
            <tr>
                <td>
                    <select name="category_id" class="gs1-category-select">
                        <option value="">Selecteer categorie...</option>
                        <?php
                        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                        foreach ($categories as $cat) {
                            echo '<option value="' . $cat->term_id . '">' . esc_html($cat->name) . '</option>';
                        }
                        ?>
                    </select>
                </td>
                <td>
                    <input type="text" name="gpc_code" placeholder="10001234" class="regular-text">
                </td>
                <td>
                    <input type="text" name="gpc_title" placeholder="Sportartikelen" class="regular-text">
                    <p class="description">Zoek GPC codes op: <a href="https://gpc-browser.gs1.org/" target="_blank">GPC Browser</a></p>
                </td>
                <td>
                    <button type="button" class="button gs1-save-gpc-mapping">Opslaan</button>
                    <button type="button" class="button gs1-delete-gpc-mapping">Verwijderen</button>
                </td>
            </tr>
        </script>
        <?php
    }
    
    private function render_gpc_mappings() {
        global $wpdb;
        $table = $wpdb->prefix . 'gs1_gtin_gpc_mappings';
        $mappings = $wpdb->get_results("SELECT * FROM {$table} ORDER BY wc_category_id");
        
        if (empty($mappings)) {
            echo '<p>Geen GPC mappings geconfigureerd.</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>WooCommerce Categorie</th>
                    <th>GPC Code</th>
                    <th>GPC Titel</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mappings as $mapping): 
                    $category = get_term($mapping->wc_category_id, 'product_cat');
                ?>
                <tr data-mapping-id="<?php echo $mapping->id; ?>">
                    <td><?php echo $category ? esc_html($category->name) : 'Categorie niet gevonden'; ?></td>
                    <td><?php echo esc_html($mapping->gpc_code); ?></td>
                    <td><?php echo esc_html($mapping->gpc_title); ?></td>
                    <td>
                        <button type="button" class="button gs1-delete-gpc-mapping" 
                                data-category-id="<?php echo $mapping->wc_category_id; ?>">
                            Verwijderen
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    private function save_settings() {
        update_option('gs1_gtin_api_mode', sanitize_text_field($_POST['gs1_gtin_api_mode'] ?? 'sandbox'));
        update_option('gs1_gtin_api_token_sandbox', sanitize_text_field($_POST['gs1_gtin_api_token_sandbox'] ?? ''));
        update_option('gs1_gtin_api_token_live', sanitize_text_field($_POST['gs1_gtin_api_token_live'] ?? ''));
        update_option('gs1_gtin_account_number', sanitize_text_field($_POST['gs1_gtin_account_number'] ?? ''));
        update_option('gs1_gtin_default_contract', sanitize_text_field($_POST['gs1_gtin_default_contract'] ?? ''));
        update_option('gs1_gtin_log_retention_days', intval($_POST['gs1_gtin_log_retention_days'] ?? 30));
        update_option('gs1_gtin_auto_sync_ean', isset($_POST['gs1_gtin_auto_sync_ean']) ? 'yes' : 'no');
        
        GS1_GTIN_Logger::log('Settings saved', 'info');
    }
    
    public function ajax_save_gpc_mapping() {
        check_ajax_referer('gs1_gtin_nonce', 'nonce');
        
        $category_id = intval($_POST['category_id'] ?? 0);
        $gpc_code = sanitize_text_field($_POST['gpc_code'] ?? '');
        $gpc_title = sanitize_text_field($_POST['gpc_title'] ?? '');
        
        if (empty($category_id) || empty($gpc_code) || empty($gpc_title)) {
            wp_send_json_error(['message' => 'Alle velden zijn verplicht']);
        }
        
        GS1_GTIN_Database::save_gpc_mapping($category_id, $gpc_code, $gpc_title);
        
        wp_send_json_success(['message' => 'GPC mapping opgeslagen']);
    }
    
    public function ajax_delete_gpc_mapping() {
        check_ajax_referer('gs1_gtin_nonce', 'nonce');
        
        $category_id = intval($_POST['category_id'] ?? 0);
        
        if (empty($category_id)) {
            wp_send_json_error(['message' => 'Geen categorie ID opgegeven']);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'gs1_gtin_gpc_mappings';
        
        $wpdb->delete($table, ['wc_category_id' => $category_id], ['%d']);
        
        GS1_GTIN_Logger::log("GPC mapping deleted for category {$category_id}", 'info');
        
        wp_send_json_success(['message' => 'GPC mapping verwijderd']);
    }
}

new GS1_GTIN_Settings();
