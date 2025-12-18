<?php
/**
 * Exclusions Tab View
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

$exclusions = GS1_GTIN_Database::get_excluded_gtins();
?>

<div class="gs1-exclusions-tab">
    
    <h2>GTIN Exclusion List</h2>
    <p class="description">GTINs op deze lijst worden overgeslagen bij automatische toewijzing.</p>
    
    <!-- Add Single GTIN -->
    <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-bottom: 20px; border-radius: 4px;">
        <h3>GTIN Toevoegen</h3>
        <table class="form-table">
            <tr>
                <th><label for="gs1-exclusion-gtin">GTIN (13 cijfers):</label></th>
                <td>
                    <input type="text" id="gs1-exclusion-gtin" class="regular-text" 
                           placeholder="8721472560015" maxlength="13">
                    <p class="description">Met checkdigit!</p>
                </td>
            </tr>
            <tr>
                <th><label for="gs1-exclusion-reason">Reden (optioneel):</label></th>
                <td>
                    <input type="text" id="gs1-exclusion-reason" class="regular-text" 
                           placeholder="Bijv: Extern geregistreerd">
                </td>
            </tr>
        </table>
        <button type="button" id="gs1-add-exclusion" class="button button-primary">
            Toevoegen aan Exclusion List
        </button>
    </div>
    
    <!-- Bulk Import -->
    <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-bottom: 20px; border-radius: 4px;">
        <h3>Bulk Import</h3>
        <p class="description">Plak meerdere GTINs (één per regel, 13 cijfers met checkdigit):</p>
        <textarea id="gs1-bulk-exclusions" rows="8" class="large-text code" 
                  placeholder="8721472560015&#10;8721472560022&#10;8721472560039"></textarea>
        <br><br>
        <button type="button" id="gs1-bulk-import-exclusions" class="button button-primary">
            Import GTINs
        </button>
    </div>
    
    <!-- Exclusions Table -->
    <h3>Uitgesloten GTINs</h3>
    
    <?php if (empty($exclusions)): ?>
        <div class="notice notice-info inline">
            <p>Geen uitgesloten GTINs.</p>
        </div>
    <?php else: ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 20%;">GTIN (12 cijfers)</th>
                    <th style="width: 20%;">GTIN (13 cijfers)</th>
                    <th style="width: 30%;">Reden</th>
                    <th style="width: 15%;">Toegevoegd</th>
                    <th style="width: 15%;">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exclusions as $exclusion): 
                    $gtin13 = GS1_GTIN_Helpers::add_checkdigit($exclusion->gtin);
                ?>
                <tr>
                    <td><code><?php echo esc_html($exclusion->gtin); ?></code></td>
                    <td><code><?php echo esc_html($gtin13); ?></code></td>
                    <td><?php echo esc_html($exclusion->reason ?: '-'); ?></td>
                    <td><?php echo esc_html(date('d-m-Y H:i', strtotime($exclusion->created_at))); ?></td>
                    <td>
                        <button type="button" class="button gs1-remove-exclusion" 
                                data-gtin="<?php echo esc_attr($exclusion->gtin); ?>">
                            Verwijderen
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
    <?php endif; ?>
    
</div>

<style>
.gs1-exclusions-tab {
    margin-top: 20px;
}
</style>