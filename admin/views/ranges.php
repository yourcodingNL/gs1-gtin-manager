<?php
/**
 * Ranges Tab View
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
                    <th>Percentage Gebruikt</th>
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
                    <td><?php echo esc_html($range->last_used ?: '-'); ?></td>
                    <td>
                        <div class="gs1-progress-bar">
                            <div class="gs1-progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <span><?php echo $percentage; ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
    <?php endif; ?>
    
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
</style>
