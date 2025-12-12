<?php
/**
 * Reference Data Tab View
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

$subtab = isset($_GET['subtab']) ? sanitize_text_field($_GET['subtab']) : 'packaging';

// Get data
$packaging_data = GS1_GTIN_Database::get_reference_data('packaging', false);
$measurement_data = GS1_GTIN_Database::get_reference_data('measurement', false);
$country_data = GS1_GTIN_Database::get_reference_data('country', false);
?>

<div class="gs1-reference-data-tab">
    
    <p class="description">
        Beheer de referentie data die gebruikt wordt in productregistraties. 
        Deze waarden worden gebruikt bij het registreren van GTINs bij GS1.
    </p>
    
    <!-- Subtabs -->
    <nav class="nav-tab-wrapper" style="margin-top: 20px;">
        <a href="?post_type=product&page=gs1-gtin-manager&tab=reference-data&subtab=packaging" 
           class="nav-tab <?php echo $subtab === 'packaging' ? 'nav-tab-active' : ''; ?>">
            Verpakkingstypes
        </a>
        <a href="?post_type=product&page=gs1-gtin-manager&tab=reference-data&subtab=measurement" 
           class="nav-tab <?php echo $subtab === 'measurement' ? 'nav-tab-active' : ''; ?>">
            Maateenheden
        </a>
        <a href="?post_type=product&page=gs1-gtin-manager&tab=reference-data&subtab=country" 
           class="nav-tab <?php echo $subtab === 'country' ? 'nav-tab-active' : ''; ?>">
            Doelmarkt Landen
        </a>
    </nav>
    
    <!-- Subtab Content -->
    <div class="subtab-content" style="background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 20px; margin-top: 0;">
        
        <?php if ($subtab === 'packaging'): ?>
            <h2>Verpakkingstypes</h2>
            <p class="description">Verpakkingstypes voor GS1 productregistratie.</p>
            
            <button type="button" class="button button-primary gs1-add-reference-item" 
                    data-category="packaging" style="margin-bottom: 15px;">
                + Nieuw Verpakkingstype
            </button>
            
            <table class="wp-list-table widefat fixed striped" id="gs1-reference-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Nederlands</th>
                        <th style="width: 25%;">Engels</th>
                        <th style="width: 15%;">Actief</th>
                        <th style="width: 10%;">Standaard</th>
                        <th style="width: 25%;">Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($packaging_data)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px;">
                            Geen verpakkingstypes gevonden.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($packaging_data as $item): ?>
                        <tr data-id="<?php echo $item->id; ?>">
                            <td><?php echo esc_html($item->value_nl); ?></td>
                            <td><?php echo esc_html($item->value_en); ?></td>
                            <td>
                                <span class="gs1-status-badge <?php echo $item->is_active ? 'gs1-status-registered' : 'gs1-status-pending'; ?>">
                                    <?php echo $item->is_active ? 'Actief' : 'Inactief'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="gs1-status-badge <?php echo $item->is_active ? 'gs1-status-registered' : 'gs1-status-pending'; ?>">
                                    <?php echo $item->is_active ? 'Actief' : 'Inactief'; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <input type="radio" name="default_country" 
                                       class="gs1-set-default" 
                                       data-id="<?php echo $item->id; ?>"
                                       data-category="country"
                                       <?php echo get_option('gs1_default_country') == $item->id ? 'checked' : ''; ?>>
                            </td>
                            <td>
                                <button type="button" class="button gs1-edit-reference-item"
                                        data-id="<?php echo $item->id; ?>"
                                        data-category="packaging"
                                        data-value-nl="<?php echo esc_attr($item->value_nl); ?>"
                                        data-value-en="<?php echo esc_attr($item->value_en); ?>"
                                        data-is-active="<?php echo $item->is_active; ?>">
                                    Bewerken
                                </button>
                                <button type="button" class="button gs1-delete-reference-item" 
                                        data-id="<?php echo $item->id; ?>">
                                    Verwijderen
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
        <?php elseif ($subtab === 'measurement'): ?>
            <h2>Maateenheden</h2>
            <p class="description">Maateenheden voor GS1 productregistratie. Format: "Nederlands (english)"</p>
            
            <button type="button" class="button button-primary gs1-add-reference-item" 
                    data-category="measurement" style="margin-bottom: 15px;">
                + Nieuwe Maateenheid
            </button>
            
            <table class="wp-list-table widefat fixed striped" id="gs1-reference-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Nederlands</th>
                        <th style="width: 30%;">Engels</th>
                        <th style="width: 15%;">Actief</th>
                        <th style="width: 25%;">Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($measurement_data)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px;">
                            Geen maateenheden gevonden.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($measurement_data as $item): ?>
                        <tr data-id="<?php echo $item->id; ?>">
                            <td><?php echo esc_html($item->value_nl); ?></td>
                            <td><?php echo esc_html($item->value_en); ?></td>
                            <td>
                                <span class="gs1-status-badge <?php echo $item->is_active ? 'gs1-status-registered' : 'gs1-status-pending'; ?>">
                                    <?php echo $item->is_active ? 'Actief' : 'Inactief'; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <input type="radio" name="default_measurement" 
                                       class="gs1-set-default" 
                                       data-id="<?php echo $item->id; ?>"
                                       data-category="measurement"
                                       <?php echo get_option('gs1_default_measurement') == $item->id ? 'checked' : ''; ?>>
                            </td>
                            <td>
                                <button type="button" class="button gs1-edit-reference-item"
                                        data-id="<?php echo $item->id; ?>"
                                        data-category="measurement"
                                        data-value-nl="<?php echo esc_attr($item->value_nl); ?>"
                                        data-value-en="<?php echo esc_attr($item->value_en); ?>"
                                        data-is-active="<?php echo $item->is_active; ?>">
                                    Bewerken
                                </button>
                                <button type="button" class="button gs1-delete-reference-item" 
                                        data-id="<?php echo $item->id; ?>">
                                    Verwijderen
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
        <?php elseif ($subtab === 'country'): ?>
            <h2>Doelmarkt Landen</h2>
            <p class="description">Doelmarkt landen voor GS1 productregistratie.</p>
            
            <button type="button" class="button button-primary gs1-add-reference-item" 
                    data-category="country" style="margin-bottom: 15px;">
                + Nieuw Land
            </button>
            
            <table class="wp-list-table widefat fixed striped" id="gs1-reference-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Code</th>
                        <th style="width: 25%;">Naam Nederlands</th>
                        <th style="width: 25%;">Naam Engels</th>
                        <th style="width: 10%;">Actief</th>
                        <th style="width: 25%;">Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($country_data)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">
                            Geen landen gevonden.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($country_data as $item): ?>
                        <tr data-id="<?php echo $item->id; ?>">
                            <td><code><?php echo esc_html($item->code); ?></code></td>
                            <td><?php echo esc_html($item->value_nl); ?></td>
                            <td><?php echo esc_html($item->value_en); ?></td>
                            <td>
                                <span class="gs1-status-badge <?php echo $item->is_active ? 'gs1-status-registered' : 'gs1-status-pending'; ?>">
                                    <?php echo $item->is_active ? 'Actief' : 'Inactief'; ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="button gs1-edit-reference-item" 
                                        data-id="<?php echo $item->id; ?>"
                                        data-category="country"
                                        data-value-nl="<?php echo esc_attr($item->value_nl); ?>"
                                        data-value-en="<?php echo esc_attr($item->value_en); ?>"
                                        data-code="<?php echo esc_attr($item->code); ?>"
                                        data-is-active="<?php echo $item->is_active; ?>">
                                    Bewerken
                                </button>
                                <button type="button" class="button gs1-delete-reference-item" 
                                        data-id="<?php echo $item->id; ?>">
                                    Verwijderen
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
        <?php endif; ?>
        
    </div>
    
</div>

<!-- Edit/Add Modal -->
<div id="gs1-reference-modal" class="gs1-modal" style="display:none;">
    <div class="gs1-modal-content" style="max-width: 600px;">
        <div class="gs1-modal-header">
            <h2 id="gs1-reference-modal-title">Reference Data</h2>
            <button type="button" class="gs1-modal-close">×</button>
        </div>
        
        <div class="gs1-modal-body">
            <form id="gs1-reference-form">
                <input type="hidden" id="gs1-ref-id" value="">
                <input type="hidden" id="gs1-ref-category" value="">
                
                <table class="form-table">
                    <tr id="gs1-ref-code-row" style="display: none;">
                        <th><label for="gs1-ref-code">Code *</label></th>
                        <td>
                            <input type="text" id="gs1-ref-code" class="regular-text" 
                                   placeholder="Bijv: NL, BE, EU" maxlength="10">
                            <p class="description">Landcode (bijv: NL, BE, EU)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gs1-ref-value-nl">Nederlands *</label></th>
                        <td>
                            <input type="text" id="gs1-ref-value-nl" class="regular-text" 
                                   placeholder="Nederlandse naam" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gs1-ref-value-en">Engels *</label></th>
                        <td>
                            <input type="text" id="gs1-ref-value-en" class="regular-text" 
                                   placeholder="Engelse naam" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gs1-ref-is-active">Actief</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="gs1-ref-is-active" value="1" checked>
                                Item is actief en beschikbaar voor gebruik
                            </label>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        
        <div class="gs1-modal-footer">
            <button type="button" class="button button-primary" id="gs1-save-reference-item">
                Opslaan
            </button>
            <button type="button" class="button" id="gs1-cancel-reference-modal">
                Annuleren
            </button>
        </div>
    </div>
</div>