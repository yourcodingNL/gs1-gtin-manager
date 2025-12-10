<?php
/**
 * Logs Tab View
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

$log_files = GS1_GTIN_Logger::get_log_files();
?>

<div class="gs1-logs-tab">
    
    <h2>Plugin Logs</h2>
    
    <div class="gs1-logs-info">
        <p>
            Logs worden bewaard voor <strong><?php echo get_option('gs1_gtin_log_retention_days', 30); ?> dagen</strong>.
            Oudere logs worden automatisch verwijderd.
        </p>
    </div>
    
    <?php if (empty($log_files)): ?>
        <div class="notice notice-info">
            <p>Geen log bestanden gevonden.</p>
        </div>
    <?php else: ?>
        
        <div class="gs1-log-files">
            <h3>Beschikbare Log Bestanden</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Bestandsnaam</th>
                        <th style="width: 100px;">Grootte</th>
                        <th style="width: 150px;">Laatst gewijzigd</th>
                        <th style="width: 200px;">Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log_files as $file): ?>
                    <tr>
                        <td>
                            <button type="button" class="button-link gs1-view-log" 
                                    data-filename="<?php echo esc_attr($file['name']); ?>">
                                📄 <?php echo esc_html($file['name']); ?>
                            </button>
                        </td>
                        <td><?php echo size_format($file['size']); ?></td>
                        <td><?php echo human_time_diff($file['modified'], current_time('timestamp')); ?> geleden</td>
                        <td>
                            <button type="button" class="button gs1-view-log" 
                                    data-filename="<?php echo esc_attr($file['name']); ?>">
                                👁️ Bekijken
                            </button>
                            <button type="button" class="button gs1-delete-log" 
                                    data-filename="<?php echo esc_attr($file['name']); ?>">
                                🗑️ Verwijderen
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="gs1-log-viewer" style="display:none;">
            <h3>
                <span id="gs1-current-log-name"></span>
                <div style="float:right;">
                    <button type="button" class="button" id="gs1-copy-log">📋 Kopieer</button>
                    <button type="button" class="button" id="gs1-clear-log">🧹 Leeg bestand</button>
                    <button type="button" class="button button-link-delete" id="gs1-delete-current-log">🗑️ Verwijder bestand</button>
                    <button type="button" class="button" id="gs1-close-log">✕ Sluiten</button>
                </div>
            </h3>
            <div class="gs1-log-content">
                <pre id="gs1-log-content-pre"></pre>
            </div>
        </div>
        
    <?php endif; ?>
    
    <div class="gs1-database-logs">
        <h3>Recente Database Logs</h3>
        
        <?php
        global $wpdb;
        $logs_table = $wpdb->prefix . 'gs1_gtin_logs';
        $recent_logs = $wpdb->get_results(
            "SELECT * FROM {$logs_table} 
            ORDER BY timestamp DESC 
            LIMIT 100"
        );
        ?>
        
        <?php if (empty($recent_logs)): ?>
            <p>Geen logs in de database.</p>
        <?php else: ?>
            
            <div class="gs1-log-filters">
                <label>
                    Filter op level:
                    <select id="gs1-log-level-filter">
                        <option value="">Alle levels</option>
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                        <option value="debug">Debug</option>
                    </select>
                </label>
            </div>
            
            <table class="wp-list-table widefat fixed striped" id="gs1-db-logs-table">
                <thead>
                    <tr>
                        <th style="width: 150px;">Timestamp</th>
                        <th style="width: 80px;">Level</th>
                        <th>Message</th>
                        <th style="width: 80px;">User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                    <tr data-level="<?php echo esc_attr($log->level); ?>">
                        <td><?php echo esc_html($log->timestamp); ?></td>
                        <td>
                            <span class="gs1-log-level gs1-log-level-<?php echo esc_attr($log->level); ?>">
                                <?php echo strtoupper($log->level); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log->message); ?></td>
                        <td>
                            <?php 
                            if ($log->user_id) {
                                $user = get_userdata($log->user_id);
                                echo $user ? esc_html($user->user_login) : 'N/A';
                            } else {
                                echo 'System';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
        <?php endif; ?>
    </div>
    
</div>

<style>
.gs1-logs-tab {
    margin-top: 20px;
}

.gs1-logs-info {
    background: #f0f6fc;
    border: 1px solid #2271b1;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.gs1-log-files {
    margin-bottom: 30px;
}

.button-link.gs1-view-log {
    text-decoration: none;
    color: #2271b1;
    font-weight: 600;
}

.button-link.gs1-view-log:hover {
    color: #135e96;
}

.gs1-log-viewer {
    margin-top: 30px;
}

.gs1-log-viewer h3 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f0f0f1;
    padding: 15px;
    margin: 0;
    border: 1px solid #c3c4c7;
    border-bottom: none;
}

.gs1-log-content {
    background: #1e1e1e;
    color: #d4d4d4;
    border: 1px solid #c3c4c7;
    max-height: 600px;
    overflow: auto;
}

.gs1-log-content pre {
    margin: 0;
    padding: 20px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.gs1-database-logs {
    margin-top: 40px;
}

.gs1-log-filters {
    margin-bottom: 15px;
    padding: 15px;
    background: #f0f0f1;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

.gs1-log-level {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.gs1-log-level-info {
    background: #2271b1;
    color: #fff;
}

.gs1-log-level-warning {
    background: #dba617;
    color: #fff;
}

.gs1-log-level-error {
    background: #d63638;
    color: #fff;
}

.gs1-log-level-debug {
    background: #646970;
    color: #fff;
}
</style>