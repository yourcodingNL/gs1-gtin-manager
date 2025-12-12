<?php
/**
 * Status Tab View
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get pending registrations
global $wpdb;
$table = $wpdb->prefix . 'gs1_gtin_assignments';
$pending_registrations = $wpdb->get_results(
    "SELECT invocation_id, COUNT(*) as count, MIN(created_at) as started_at
    FROM {$table}
    WHERE status = 'pending_registration' AND invocation_id IS NOT NULL
    GROUP BY invocation_id
    ORDER BY started_at DESC"
);

// Get recent registrations
$recent_registrations = $wpdb->get_results(
    "SELECT invocation_id, status, COUNT(*) as count, MAX(synced_to_gs1_at) as completed_at
    FROM {$table}
    WHERE status IN ('registered', 'error') AND invocation_id IS NOT NULL
    GROUP BY invocation_id, status
    ORDER BY completed_at DESC
    LIMIT 20"
);
?>

<div class="gs1-status-tab">
    
    <h2>Lopende Registraties</h2>
    
    <?php if (empty($pending_registrations)): ?>
        <div class="notice notice-info">
            <p>Geen lopende registraties.</p>
        </div>
    <?php else: ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Invocation ID</th>
                    <th>Aantal Producten</th>
                    <th>Gestart Op</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_registrations as $reg): ?>
                <tr>
                    <td><code><?php echo esc_html($reg->invocation_id); ?></code></td>
                    <td><?php echo number_format_i18n($reg->count); ?></td>
                    <td><?php echo esc_html($reg->started_at); ?></td>
                    <td>
                        <button type="button" class="button gs1-check-registration" 
        data-invocation-id="<?php echo esc_attr(trim($reg->invocation_id, '"')); ?>">
    Status Checken
</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
    <?php endif; ?>
    
    <h2 style="margin-top: 40px;">Recente Registraties</h2>
    
    <?php if (empty($recent_registrations)): ?>
        <div class="notice notice-info">
            <p>Geen recente registraties.</p>
        </div>
    <?php else: ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Invocation ID</th>
                    <th>Status</th>
                    <th>Aantal Producten</th>
                    <th>Voltooid Op</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_registrations as $reg): ?>
                <tr>
                    <td><code><?php echo esc_html($reg->invocation_id); ?></code></td>
                    <td>
                        <span class="gs1-status-badge gs1-status-<?php echo esc_attr($reg->status); ?>">
                            <?php 
                            $status_labels = [
                                'registered' => 'Geregistreerd',
                                'error' => 'Error'
                            ];
                            echo $status_labels[$reg->status] ?? $reg->status;
                            ?>
                        </span>
                    </td>
                    <td><?php echo number_format_i18n($reg->count); ?></td>
                    <td><?php echo esc_html($reg->completed_at ?: '-'); ?></td>
                    <td>
                        <button type="button" class="button gs1-view-registration-details" 
                                data-invocation-id="<?php echo esc_attr($reg->invocation_id); ?>">
                            Details
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
    <?php endif; ?>
    
</div>

<!-- Registration Details Modal -->
<div id="gs1-registration-details-modal" class="gs1-modal" style="display:none;">
    <div class="gs1-modal-content">
        <div class="gs1-modal-header">
            <h2>Registratie Details</h2>
            <button type="button" class="gs1-modal-close">×</button>
        </div>
        
        <div class="gs1-modal-body" id="gs1-registration-details-body">
            <!-- Content loaded via AJAX -->
        </div>
        
        <div class="gs1-modal-footer">
            <button type="button" class="button" id="gs1-close-details-modal">Sluiten</button>
        </div>
    </div>
</div>

<style>
.gs1-status-tab {
    margin-top: 20px;
}
</style>
