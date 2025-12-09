<?php
/**
 * Overview Tab View
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get stats
global $wpdb;
$table = $wpdb->prefix . 'gs1_gtin_assignments';
$stats = [
    'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
    'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"),
    'registered' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'registered'"),
    'error' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'error'"),
    'external' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE external_registration = 1")
];

// Get brands
$brands = get_terms([
    'taxonomy' => 'pa_brand',
    'hide_empty' => false
]);
?>

<div class="gs1-overview-tab">
    
    <!-- Stats -->
    <div class="gs1-stats">
        <div class="gs1-stat-box">
            <div class="gs1-stat-number"><?php echo number_format_i18n($stats['total']); ?></div>
            <div class="gs1-stat-label">Totaal GTINs</div>
        </div>
        <div class="gs1-stat-box">
            <div class="gs1-stat-number"><?php echo number_format_i18n($stats['registered']); ?></div>
            <div class="gs1-stat-label">Geregistreerd</div>
        </div>
        <div class="gs1-stat-box">
            <div class="gs1-stat-number"><?php echo number_format_i18n($stats['pending']); ?></div>
            <div class="gs1-stat-label">In behandeling</div>
        </div>
        <div class="gs1-stat-box">
            <div class="gs1-stat-number"><?php echo number_format_i18n($stats['error']); ?></div>
            <div class="gs1-stat-label">Errors</div>
        </div>
        <div class="gs1-stat-box">
            <div class="gs1-stat-number"><?php echo number_format_i18n($stats['external']); ?></div>
            <div class="gs1-stat-label">Extern</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="gs1-filters">
        <div class="gs1-filter-row">
            <div class="gs1-filter-group">
                <label>Zoeken:</label>
                <input type="text" id="gs1-search-input" placeholder="Zoek op product naam, SKU of GTIN..." class="regular-text">
            </div>
            
            <div class="gs1-filter-group">
                <label>Merk:</label>
                <select id="gs1-brand-filter">
                    <option value="">Alle merken</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?php echo esc_attr($brand->slug); ?>">
                            <?php echo esc_html($brand->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="gs1-filter-group">
                <label>Status:</label>
                <select id="gs1-status-filter">
                    <option value="">Alle statussen</option>
                    <option value="pending">Nog niet geregistreerd</option>
                    <option value="pending_registration">In registratie</option>
                    <option value="registered">Geregistreerd</option>
                    <option value="error">Error</option>
                    <option value="external">Extern geregistreerd</option>
                </select>
            </div>
            
            <div class="gs1-filter-group">
                <button type="button" id="gs1-apply-filters" class="button">Filter Toepassen</button>
                <button type="button" id="gs1-reset-filters" class="button">Reset</button>
            </div>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="gs1-actions">
        <button type="button" id="gs1-assign-selected" class="button button-primary" disabled>
            GTIN Toewijzen aan Geselecteerde
        </button>
        <button type="button" id="gs1-register-selected" class="button button-primary" disabled>
            Registreren bij GS1
        </button>
        <button type="button" id="gs1-mark-external" class="button" disabled>
            Markeer als Extern
        </button>
    </div>
    
    <!-- Products Table -->
    <div class="gs1-products-table-wrapper">
        <table class="wp-list-table widefat fixed striped" id="gs1-products-table">
            <thead>
                <tr>
                    <td class="check-column">
                        <input type="checkbox" id="gs1-select-all">
                    </td>
                    <th>Product ID</th>
                    <th>Product Naam</th>
                    <th>SKU</th>
                    <th>Merk</th>
                    <th>Huidige EAN</th>
                    <th>Toegewezen GTIN</th>
                    <th>Status</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody id="gs1-products-tbody">
                <tr>
                    <td colspan="9" class="gs1-loading">
                        <span class="spinner is-active"></span> Producten laden...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="gs1-pagination">
        <button type="button" class="button" id="gs1-prev-page" disabled>← Vorige</button>
        <span id="gs1-page-info">Pagina 1 van 1</span>
        <button type="button" class="button" id="gs1-next-page" disabled>Volgende →</button>
    </div>
    
</div>

<!-- Registration Modal -->
<div id="gs1-registration-modal" class="gs1-modal" style="display:none;">
    <div class="gs1-modal-content">
        <div class="gs1-modal-header">
            <h2>Producten Registreren bij GS1</h2>
            <button type="button" class="gs1-modal-close">×</button>
        </div>
        
        <div class="gs1-modal-body">
            <div id="gs1-registration-step-1" class="gs1-registration-step">
                <h3>Stap 1: Controleer de te registreren producten</h3>
                <div id="gs1-registration-products-list"></div>
            </div>
            
            <div id="gs1-registration-step-2" class="gs1-registration-step" style="display:none;">
                <h3>Stap 2: Controleer en pas de registratiegegevens aan</h3>
                <p class="description">
                    Vul voor elk product de verplichte velden in. De data wordt automatisch uit het product gehaald,
                    maar je kunt deze hier aanpassen voordat je registreert.
                </p>
                <div id="gs1-registration-data-form"></div>
                
                <div class="gs1-registration-confirm">
                    <label>
                        <input type="checkbox" id="gs1-confirm-registration">
                        Ik heb alle data gecontroleerd en wil doorgaan met registratie
                    </label>
                </div>
            </div>
            
            <div id="gs1-registration-step-3" class="gs1-registration-step" style="display:none;">
                <h3>Stap 3: Registratie wordt verwerkt...</h3>
                <div class="gs1-registration-progress">
                    <span class="spinner is-active"></span>
                    <p>De producten worden geregistreerd bij GS1. Dit kan enkele momenten duren...</p>
                </div>
            </div>
            
            <div id="gs1-registration-step-4" class="gs1-registration-step" style="display:none;">
                <h3>Registratie Voltooid</h3>
                <div id="gs1-registration-result"></div>
            </div>
        </div>
        
        <div class="gs1-modal-footer">
            <button type="button" class="button" id="gs1-registration-prev" style="display:none;">← Vorige</button>
            <button type="button" class="button button-primary" id="gs1-registration-next">Volgende →</button>
            <button type="button" class="button" id="gs1-registration-cancel">Annuleren</button>
            <button type="button" class="button button-primary" id="gs1-registration-submit" style="display:none;" disabled>
                Registreren bij GS1
            </button>
        </div>
    </div>
</div>

<style>
.gs1-overview-tab {
    margin-top: 20px;
}

.gs1-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
}

.gs1-stat-box {
    flex: 1;
    background: #fff;
    border: 1px solid #c3c4c7;
    padding: 20px;
    text-align: center;
    border-radius: 4px;
}

.gs1-stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #2271b1;
    margin-bottom: 5px;
}

.gs1-stat-label {
    font-size: 14px;
    color: #646970;
}

.gs1-filters {
    background: #fff;
    border: 1px solid #c3c4c7;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.gs1-filter-row {
    display: flex;
    gap: 15px;
    align-items: flex-end;
}

.gs1-filter-group {
    flex: 1;
}

.gs1-filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.gs1-actions {
    margin-bottom: 20px;
}

.gs1-actions button {
    margin-right: 10px;
}

.gs1-products-table-wrapper {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

.gs1-loading {
    text-align: center;
    padding: 40px;
}

.gs1-pagination {
    margin-top: 20px;
    text-align: center;
}

.gs1-pagination button {
    margin: 0 10px;
}

.gs1-status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.gs1-status-pending {
    background: #f0f0f1;
    color: #646970;
}

.gs1-status-registered {
    background: #00a32a;
    color: #fff;
}

.gs1-status-error {
    background: #d63638;
    color: #fff;
}

.gs1-status-external {
    background: #2271b1;
    color: #fff;
}

/* Modal Styles */
.gs1-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}

.gs1-modal-content {
    background-color: #fff;
    margin: 50px auto;
    border: 1px solid #c3c4c7;
    width: 90%;
    max-width: 1200px;
    border-radius: 4px;
}

.gs1-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #c3c4c7;
}

.gs1-modal-header h2 {
    margin: 0;
}

.gs1-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #646970;
}

.gs1-modal-body {
    padding: 20px;
    max-height: 70vh;
    overflow-y: auto;
}

.gs1-modal-footer {
    padding: 20px;
    border-top: 1px solid #c3c4c7;
    text-align: right;
}

.gs1-modal-footer button {
    margin-left: 10px;
}

.gs1-registration-product-item {
    padding: 15px;
    border: 1px solid #c3c4c7;
    margin-bottom: 10px;
    border-radius: 4px;
}

.gs1-registration-data-item {
    border: 1px solid #c3c4c7;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.gs1-registration-data-item h4 {
    margin-top: 0;
    border-bottom: 1px solid #c3c4c7;
    padding-bottom: 10px;
}

.gs1-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.gs1-form-group {
    display: flex;
    flex-direction: column;
}

.gs1-form-group label {
    font-weight: 600;
    margin-bottom: 5px;
}

.gs1-form-group input,
.gs1-form-group select {
    padding: 8px;
}

.gs1-registration-confirm {
    background: #f0f6fc;
    border: 1px solid #2271b1;
    padding: 15px;
    margin-top: 20px;
    border-radius: 4px;
}

.gs1-registration-progress {
    text-align: center;
    padding: 40px;
}
</style>
