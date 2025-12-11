<?php
/**
 * Ranges Tab View - WITH RESET CONTROLS
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

$ranges = GS1_GTIN_Database::get_gtin_ranges();
?>

<div class="gs1-ranges-tab">
    
    <div class="gs1-actions">
        <button type="button" id="gs1-sync-ranges" class="button button-primary">
            🔄 Sync Ranges van GS1 API
        </button>
    </div>
    
    <?php if (empty($ranges)): ?>
        <div class="notice notice-info">
            <p>Geen GTIN ranges gevonden. Klik op "Sync Ranges" om ze op te halen van de GS1 API.</p>
        </div>
    <?php else: ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Contract Nummer</th>
                    <th>Start Nummer</th>
                    <th>Eind Nummer</th>
                    <th>Totaal Beschikbaar</th>
                    <th>Gebruikt</th>
                    <th>Laatst Gebruikt</th>
                    <th>Percentage</th>
                    <th style="width: 250px;">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ranges as $range): 
                    // Count used GTINs
                    global $wpdb;
                    $assignments_table = $wpdb->prefix . 'gs1_gtin_assignments';
                    $used_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$assignments_table} 
                        WHERE contract_number = %s 
                        AND CAST(gtin AS UNSIGNED) BETWEEN %d AND %d",
                        $range->contract_number,
                        intval($range->start_number),
                        intval($range->end_number)
                    ));
                    
                    $percentage = $range->total_available > 0 
                        ? round(($used_count / $range->total_available) * 100, 1) 
                        : 0;
                ?>
                <tr>
                    <td><strong><?php echo esc_html($range->contract_number); ?></strong></td>
                    <td><?php echo esc_html($range->start_number); ?></td>
                    <td><?php echo esc_html($range->end_number); ?></td>
                    <td><?php echo number_format_i18n($range->total_available); ?></td>
                    <td><?php echo number_format_i18n($used_count); ?></td>
                    <td>
                        <?php if ($range->last_used): ?>
                            <code><?php echo esc_html($range->last_used); ?></code>
                        <?php else: ?>
                            <em style="color: #999;">Niet gebruikt</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="gs1-progress-bar">
                            <div class="gs1-progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <span><?php echo $percentage; ?>%</span>
                    </td>
                    <td>
                        <button type="button" class="button gs1-reset-last-used" 
                                data-contract="<?php echo esc_attr($range->contract_number); ?>"
                                title="Reset naar begin van range">
                            🔄 Reset
                        </button>
                        <button type="button" class="button gs1-set-last-used" 
                                data-contract="<?php echo esc_attr($range->contract_number); ?>"
                                data-start="<?php echo esc_attr($range->start_number); ?>"
                                data-end="<?php echo esc_attr($range->end_number); ?>"
                                title="Handmatig instellen">
                            ✏️ Instellen
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
    <?php endif; ?>
    
</div>

<!-- Modal voor handmatig instellen -->
<div id="gs1-set-last-used-modal" class="gs1-modal" style="display:none;">
    <div class="gs1-modal-content" style="max-width: 500px;">
        <div class="gs1-modal-header">
            <h2>Laatst Gebruikt GTIN Instellen</h2>
            <button type="button" class="gs1-modal-close">×</button>
        </div>
        
        <div class="gs1-modal-body">
            <p>Stel de laatst gebruikte GTIN in voor contract <strong id="gs1-modal-contract"></strong>.</p>
            <p style="color: #d63638;">⚠️ <strong>Let op:</strong> De volgende toegewezen GTIN wordt dit nummer + 1</p>
            
            <table class="form-table">
                <tr>
                    <th>Huidige Waarde:</th>
                    <td><code id="gs1-modal-current">-</code></td>
                </tr>
                <tr>
                    <th>Range:</th>
                    <td>
                        <code id="gs1-modal-start"></code> tot <code id="gs1-modal-end"></code>
                    </td>
                </tr>
                <tr>
                    <th><label for="gs1-new-last-used">Nieuwe Waarde:</label></th>
                    <td>
                        <input type="text" id="gs1-new-last-used" class="regular-text" 
                               placeholder="Bijv: 872147256010" maxlength="12">
                        <p class="description">12 cijfers, ZONDER checkdigit</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="gs1-modal-footer">
            <button type="button" class="button button-primary" id="gs1-confirm-set-last-used">
                Instellen
            </button>
            <button type="button" class="button" id="gs1-cancel-set-last-used">
                Annuleren
            </button>
        </div>
    </div>
</div>

<style>
.gs1-ranges-tab {
    margin-top: 20px;
}

.gs1-progress-bar {
    width: 200px;
    height: 20px;
    background: #f0f0f1;
    border-radius: 3px;
    overflow: hidden;
    display: inline-block;
    vertical-align: middle;
    margin-right: 10px;
}

.gs1-progress-fill {
    height: 100%;
    background: #2271b1;
    transition: width 0.3s;
}

.gs1-reset-last-used,
.gs1-set-last-used {
    margin-right: 5px;
}
</style>