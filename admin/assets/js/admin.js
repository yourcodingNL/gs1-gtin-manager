/**
 * GS1 GTIN Manager - Admin JavaScript - FIXED UNASSIGN
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

(function($) {
    'use strict';

    const GS1Admin = {
        
        // State
        currentPage: 1,
        currentFilters: {},
        selectedProducts: [],
        registrationStep: 1,
        registrationData: {},
        
        // Calculate checkdigit for 12-digit GTIN
        calculateCheckdigit: function(gtin12) {
            if (!gtin12 || gtin12 === '-' || gtin12.length !== 12) {
                return gtin12;
            }
            
            gtin12 = gtin12.padStart(12, '0');
            
            let sum_odd = 0;
            let sum_even = 0;
            
            for (let i = 0; i < 12; i++) {
                if (i % 2 === 0) {
                    sum_odd += parseInt(gtin12[i]);
                } else {
                    sum_even += parseInt(gtin12[i]);
                }
            }
            
            let total = sum_odd + (sum_even * 3);
            let checkdigit = (10 - (total % 10)) % 10;
            
            return gtin12 + checkdigit;
        },
        
        init: function() {
            this.bindEvents();
            
            // Load products if on overview tab
            if ($('#gs1-products-table').length) {
                this.loadProducts();
            }
        },
        
        bindEvents: function() {
            // Overview tab
            $(document).on('click', '#gs1-apply-filters', () => this.applyFilters());
            $(document).on('click', '#gs1-reset-filters', () => this.resetFilters());
            $(document).on('click', '#gs1-select-all', (e) => this.selectAll(e));
            $(document).on('change', '.gs1-product-checkbox', () => this.updateSelection());
            $(document).on('click', '#gs1-assign-selected', () => this.assignGtins());
            $(document).on('click', '#gs1-unassign-selected', () => this.unassignGtins()); // FIXED!
            $(document).on('click', '#gs1-register-selected', () => this.startRegistration());
            $(document).on('click', '#gs1-mark-external', () => this.markExternal());
            $(document).on('click', '#gs1-prev-page', () => this.changePage(-1));
            $(document).on('click', '#gs1-next-page', () => this.changePage(1));
            $(document).on('keyup', '#gs1-search-input', _.debounce(() => this.applyFilters(), 500));
            
            // Registration modal
            $(document).on('click', '.gs1-modal-close, #gs1-registration-cancel', () => this.closeModal());
            $(document).on('click', '#gs1-registration-next', () => this.nextRegistrationStep());
            $(document).on('click', '#gs1-registration-prev', () => this.prevRegistrationStep());
            $(document).on('click', '#gs1-registration-submit', () => this.submitRegistration());
            $(document).on('change', '#gs1-confirm-registration', (e) => {
                $('#gs1-registration-submit').prop('disabled', !e.target.checked);
            });
            
            // Ranges tab
            $(document).on('click', '#gs1-sync-ranges', () => this.syncRanges());
            $(document).on('click', '.gs1-reset-last-used', (e) => this.resetLastUsed(e));
            $(document).on('click', '.gs1-set-last-used', (e) => this.openSetLastUsedModal(e));
            $(document).on('click', '#gs1-confirm-set-last-used', () => this.confirmSetLastUsed());
            $(document).on('click', '#gs1-cancel-set-last-used, #gs1-set-last-used-modal .gs1-modal-close', () => {
                $('#gs1-set-last-used-modal').hide();
            });
            
            // Status tab
            $(document).on('click', '.gs1-check-registration', (e) => {
                const invocationId = $(e.target).data('invocation-id');
                this.checkRegistration(invocationId);
            });
            $(document).on('click', '.gs1-view-registration-details', (e) => {
                const invocationId = $(e.target).data('invocation-id');
                this.viewRegistrationDetails(invocationId);
            });
            $(document).on('click', '#gs1-close-details-modal', () => {
                $('#gs1-registration-details-modal').hide();
            });
            
            // Logs tab
            $(document).on('click', '.gs1-view-log', (e) => {
                const filename = $(e.target).data('filename');
                this.viewLog(filename);
            });
            $(document).on('click', '#gs1-close-log', () => {
                $('.gs1-log-viewer').hide();
            });
            $(document).on('click', '#gs1-copy-log', () => this.copyLog());
            $(document).on('click', '#gs1-clear-log', () => this.clearLog());
            $(document).on('click', '#gs1-delete-current-log', () => this.deleteCurrentLog());
            $(document).on('click', '.gs1-delete-log', (e) => {
                const filename = $(e.target).data('filename');
                this.deleteLog(filename);
            });
            $(document).on('change', '#gs1-log-level-filter', (e) => {
                this.filterLogs($(e.target).val());
            });
            
            // Settings tab
            $(document).on('click', '.gs1-test-connection', () => this.testConnection());
            $(document).on('click', '.gs1-add-gpc-mapping', () => this.addGpcMapping());
            $(document).on('click', '.gs1-save-gpc-mapping', (e) => this.saveGpcMapping(e));
            $(document).on('click', '.gs1-cancel-gpc-mapping', (e) => {
                $(e.target).closest('tr').remove();
            });
            $(document).on('click', '.gs1-delete-gpc-mapping', (e) => this.deleteGpcMapping(e));
        },
        
        // Load products
        loadProducts: function() {
            $('#gs1-products-tbody').html('<tr><td colspan="9" class="gs1-loading"><span class="spinner is-active"></span> Producten laden...</td></tr>');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_search_products',
                    nonce: gs1GtinAdmin.nonce,
                    search: this.currentFilters.search || '',
                    brand: this.currentFilters.brand || '',
                    status: this.currentFilters.status || '',
                    page: this.currentPage
                },
                success: (response) => {
                    if (response.success) {
                        this.renderProducts(response.data);
                    } else {
                        this.showError('Fout bij laden producten');
                    }
                }
            });
        },
        
        renderProducts: function(data) {
            const tbody = $('#gs1-products-tbody');
            tbody.empty();
            
            if (data.products.length === 0) {
                tbody.html('<tr><td colspan="9" style="text-align:center;padding:40px;">Geen producten gevonden</td></tr>');
                return;
            }
            
            data.products.forEach((product) => {
                const statusClass = 'gs1-status-' + product.status;
                const statusLabels = {
                    'no_gtin': 'Geen GTIN',
                    'pending': 'Nog niet geregistreerd',
                    'pending_registration': 'In registratie',
                    'registered': 'Geregistreerd',
                    'error': 'Error',
                    'external': 'Extern'
                };
                
                // Calculate 13-digit GTIN for display
                let gtinDisplay = product.gtin || '-';
                if (product.gtin && product.gtin !== '-' && product.gtin.length === 12) {
                    gtinDisplay = this.calculateCheckdigit(product.gtin);
                }
                
                const row = $('<tr>');
                row.append(`
                    <th class="check-column">
                        <input type="checkbox" class="gs1-product-checkbox" 
                               value="${product.product_id}" 
                               data-external="${product.external}"
                               data-has-gtin="${product.gtin !== '-' ? '1' : '0'}">
                    </th>
                    <td>${product.product_id}</td>
                    <td><strong>${product.product_name}</strong></td>
                    <td>${product.sku || '-'}</td>
                    <td>${product.brand || '-'}</td>
                    <td>${product.ean || '-'}</td>
                    <td>${gtinDisplay}</td>
                    <td>
                        <span class="gs1-status-badge ${statusClass}">
                            ${statusLabels[product.status] || product.status}
                        </span>
                        ${product.error_message ? '<br><small style="color:#d63638;">' + product.error_message + '</small>' : ''}
                    </td>
                    <td>
                        ${product.external ? '<em>Extern geregistreerd</em>' : ''}
                    </td>
                `);
                
                tbody.append(row);
            });
            
            // Update pagination
            $('#gs1-page-info').text(`Pagina ${data.page} van ${data.total_pages}`);
            $('#gs1-prev-page').prop('disabled', data.page === 1);
            $('#gs1-next-page').prop('disabled', data.page === data.total_pages);
        },
        
        applyFilters: function() {
            this.currentFilters = {
                search: $('#gs1-search-input').val(),
                brand: $('#gs1-brand-filter').val(),
                status: $('#gs1-status-filter').val()
            };
            this.currentPage = 1;
            this.loadProducts();
        },
        
        resetFilters: function() {
            $('#gs1-search-input').val('');
            $('#gs1-brand-filter').val('');
            $('#gs1-status-filter').val('');
            this.currentFilters = {};
            this.currentPage = 1;
            this.loadProducts();
        },
        
        selectAll: function(e) {
            $('.gs1-product-checkbox').prop('checked', e.target.checked);
            this.updateSelection();
        },
        
        updateSelection: function() {
            this.selectedProducts = [];
            let hasGtin = 0;
            
            $('.gs1-product-checkbox:checked').each((i, el) => {
                this.selectedProducts.push(parseInt($(el).val()));
                if ($(el).data('has-gtin') === 1) {
                    hasGtin++;
                }
            });
            
            const hasSelection = this.selectedProducts.length > 0;
            
            // Enable/disable buttons based on selection
            $('#gs1-assign-selected').prop('disabled', !hasSelection);
            $('#gs1-unassign-selected').prop('disabled', hasGtin === 0); // Only enable if has GTIN!
            $('#gs1-register-selected').prop('disabled', hasGtin === 0);
            $('#gs1-mark-external').prop('disabled', !hasSelection);
        },
        
        changePage: function(direction) {
            this.currentPage += direction;
            this.loadProducts();
        },
        
        // GTIN Assignment
        assignGtins: function() {
            if (this.selectedProducts.length === 0) return;
            
            if (!confirm(`GTIN toewijzen aan ${this.selectedProducts.length} producten?`)) return;
            
            const button = $('#gs1-assign-selected');
            button.prop('disabled', true).text('Toewijzen...');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_assign_gtins',
                    nonce: gs1GtinAdmin.nonce,
                    product_ids: this.selectedProducts,
                    external: false
                },
                success: (response) => {
                    if (response.success) {
                        const result = response.data;
                        this.showSuccess(`${result.success.length} GTINs toegewezen. ${result.errors.length} fouten.`);
                        this.loadProducts();
                    } else {
                        this.showError(response.data.message);
                    }
                },
                complete: () => {
                    button.prop('disabled', false).text('GTIN Toewijzen aan Geselecteerde');
                }
            });
        },
        
        // FIXED: Unassign GTINs
        unassignGtins: function() {
            if (this.selectedProducts.length === 0) return;
            
            if (!confirm(`Weet je zeker dat je ${this.selectedProducts.length} GTIN(s) wilt verwijderen? Dit kan NIET ongedaan worden gemaakt!`)) return;
            
            const button = $('#gs1-unassign-selected');
            button.prop('disabled', true).text('Verwijderen...');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_unassign_gtins',
                    nonce: gs1GtinAdmin.nonce,
                    product_ids: this.selectedProducts
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message || 'GTINs verwijderd');
                        this.loadProducts();
                    } else {
                        this.showError(response.data.message || 'Fout bij verwijderen');
                    }
                },
                error: () => {
                    this.showError('Netwerkfout bij verwijderen');
                },
                complete: () => {
                    button.prop('disabled', false).text('❌ GTIN Verwijderen');
                }
            });
        },
        
        markExternal: function() {
            if (this.selectedProducts.length === 0) return;
            
            if (!confirm(`${this.selectedProducts.length} producten markeren als extern geregistreerd?`)) return;
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_assign_gtins',
                    nonce: gs1GtinAdmin.nonce,
                    product_ids: this.selectedProducts,
                    external: true
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Producten gemarkeerd als extern');
                        this.loadProducts();
                    } else {
                        this.showError(response.data.message);
                    }
                }
            });
        },
        
        // Registration workflow
        startRegistration: function() {
            if (this.selectedProducts.length === 0) return;
            
            // Reset
            this.registrationStep = 1;
            this.registrationData = {};
            
            // Show modal
            $('#gs1-registration-modal').show();
            
            // Load registration data
            this.loadRegistrationData();
        },
        
        loadRegistrationData: function() {
            $('#gs1-registration-products-list').html('<p><span class="spinner is-active"></span> Data voorbereiden...</p>');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_get_registration_data',
                    nonce: gs1GtinAdmin.nonce,
                    product_ids: this.selectedProducts
                },
                success: (response) => {
                    if (response.success) {
                        this.registrationData = response.data.products;
                        this.renderRegistrationStep1();
                    } else {
                        this.showError(response.data.message);
                    }
                }
            });
        },
        
        renderRegistrationStep1: function() {
            const html = this.registrationData.map((product) => {
                // Show 13-digit GTIN if available
                let gtinDisplay = product.data.Gtin || '-';
                
                return `
                    <div class="gs1-registration-product-item">
                        <strong>${product.product_name}</strong> (SKU: ${product.sku})<br>
                        <small>GTIN: ${gtinDisplay} | Merk: ${product.data.BrandName || '-'}</small>
                    </div>
                `;
            }).join('');
            
            $('#gs1-registration-products-list').html(html);
        },
        
        nextRegistrationStep: function() {
            if (this.registrationStep === 1) {
                this.showRegistrationStep(2);
                this.renderRegistrationStep2();
            } else if (this.registrationStep === 2) {
                // Validate and show confirmation
                if (!this.validateRegistrationData()) {
                    return;
                }
                $('#gs1-registration-next').hide();
                $('#gs1-registration-submit').show();
            }
        },
        
        prevRegistrationStep: function() {
            if (this.registrationStep > 1) {
                this.showRegistrationStep(this.registrationStep - 1);
            }
        },
        
        showRegistrationStep: function(step) {
            this.registrationStep = step;
            
            $('.gs1-registration-step').hide();
            $(`#gs1-registration-step-${step}`).show();
            
            $('#gs1-registration-prev').toggle(step > 1);
            $('#gs1-registration-next').toggle(step < 3);
            $('#gs1-registration-submit').hide();
            $('#gs1-registration-cancel').toggle(step !== 3);
        },
        
        renderRegistrationStep2: function() {
            const html = this.registrationData.map((product, index) => {
                const data = product.data;
                return `
                    <div class="gs1-registration-data-item">
                        <h4>${product.product_name}</h4>
                        
                        <div class="gs1-form-row">
                            <div class="gs1-form-group">
                                <label>GTIN (13 digits met checkdigit) *</label>
                                <input type="text" readonly value="${data.Gtin || ''}" class="regular-text">
                            </div>
                            <div class="gs1-form-group">
                                <label>Status *</label>
                                <input type="text" value="${data.Status || 'Actief'}" class="regular-text" 
                                       data-index="${index}" data-field="Status">
                            </div>
                        </div>
                        
                        <div class="gs1-form-row">
                            <div class="gs1-form-group">
                                <label>Merknaam</label>
                                <input type="text" value="${data.BrandName || ''}" class="regular-text" 
                                       data-index="${index}" data-field="BrandName">
                            </div>
                            <div class="gs1-form-group">
                                <label>GPC Titel (niet code!)</label>
                                <input type="text" value="${data.Gpc || ''}" class="regular-text" 
                                       data-index="${index}" data-field="Gpc" 
                                       placeholder="Bijv: Gevechtsport Artikelen - Overig">
                            </div>
                        </div>
                        
                        <div class="gs1-form-row">
                            <div class="gs1-form-group">
                                <label>Verpakkingstype</label>
                                <select data-index="${index}" data-field="PackagingType">
                                    <option value="Doos" ${data.PackagingType === 'Doos' ? 'selected' : ''}>Doos</option>
                                    <option value="Zak" ${data.PackagingType === 'Zak' ? 'selected' : ''}>Zak</option>
                                    <option value="Blister" ${data.PackagingType === 'Blister' ? 'selected' : ''}>Blister</option>
                                    <option value="Fles" ${data.PackagingType === 'Fles' ? 'selected' : ''}>Fles</option>
                                    <option value="Blik" ${data.PackagingType === 'Blik' ? 'selected' : ''}>Blik</option>
                                </select>
                            </div>
                            <div class="gs1-form-group">
                                <label>Consumenteneenheid</label>
                                <select data-index="${index}" data-field="ConsumerUnit">
                                    <option value="Ja" ${data.ConsumerUnit === 'Ja' ? 'selected' : ''}>Ja</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="gs1-form-row">
                            <div class="gs1-form-group">
                                <label>Netto inhoud</label>
                                <input type="text" value="${data.NetContent || ''}" class="regular-text" 
                                       data-index="${index}" data-field="NetContent" 
                                       placeholder="Bijv: 1">
                            </div>
                            <div class="gs1-form-group">
                                <label>Maateenheid (met Engels!)</label>
                                <input type="text" value="${data.MeasurementUnit || 'Stuks (piece)'}" class="regular-text" 
                                       data-index="${index}" data-field="MeasurementUnit"
                                       placeholder="Bijv: Paar (pair)">
                            </div>
                        </div>
                        
                        <div class="gs1-form-group">
                            <label>Beschrijving (max 300 tekens) *</label>
                            <textarea rows="3" class="large-text" data-index="${index}" data-field="Description" 
                                      maxlength="300">${data.Description || ''}</textarea>
                        </div>
                    </div>
                `;
            }).join('');
            
            $('#gs1-registration-data-form').html(html);
            
            // Bind change events
            $('#gs1-registration-data-form input, #gs1-registration-data-form select, #gs1-registration-data-form textarea').on('change', (e) => {
                const index = $(e.target).data('index');
                const field = $(e.target).data('field');
                const value = $(e.target).val();
                
                if (this.registrationData[index]) {
                    this.registrationData[index].data[field] = value;
                }
            });
        },
        
        validateRegistrationData: function() {
            let isValid = true;
            
            this.registrationData.forEach((product) => {
                if (!product.data.Description || product.data.Description.trim() === '') {
                    this.showError('Beschrijving is verplicht voor alle producten');
                    isValid = false;
                }
            });
            
            return isValid;
        },
        
        submitRegistration: function() {
            this.showRegistrationStep(3);
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_submit_registration',
                    nonce: gs1GtinAdmin.nonce,
                    products_data: this.registrationData
                },
                success: (response) => {
                    this.showRegistrationStep(4);
                    
                    if (response.success) {
                        $('#gs1-registration-result').html(`
                            <div class="notice notice-success">
                                <p><strong>Registratie succesvol gestart!</strong></p>
                                <p>Invocation ID: <code>${response.data.invocation_id}</code></p>
                                <p>${response.data.products_count} producten worden geregistreerd.</p>
                                <p>De status wordt automatisch gecontroleerd. Je kunt de voortgang volgen in het "Registratie Status" tabblad.</p>
                            </div>
                        `);
                    } else {
                        $('#gs1-registration-result').html(`
                            <div class="notice notice-error">
                                <p><strong>Registratie mislukt</strong></p>
                                <p>${response.data.error || 'Onbekende fout'}</p>
                                <p>Check de logs voor meer details.</p>
                            </div>
                        `);
                    }
                    
                    $('#gs1-registration-cancel').hide();
                    $('#gs1-registration-modal .gs1-modal-footer').append(
                        '<button type="button" class="button button-primary" id="gs1-close-registration-modal">Sluiten</button>'
                    );
                    
                    $(document).on('click', '#gs1-close-registration-modal', () => {
                        this.closeModal();
                        this.loadProducts();
                    });
                },
                error: () => {
                    this.showRegistrationStep(4);
                    $('#gs1-registration-result').html(`
                        <div class="notice notice-error">
                            <p><strong>Netwerkfout</strong></p>
                            <p>Er is een fout opgetreden bij het versturen van de registratie.</p>
                        </div>
                    `);
                }
            });
        },
        
        closeModal: function() {
            $('.gs1-modal').hide();
        },
        
        // Ranges
        syncRanges: function() {
            $('#gs1-sync-ranges').prop('disabled', true).text('Synchroniseren...');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_sync_ranges',
                    nonce: gs1GtinAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(`${response.data.synced} ranges gesynchroniseerd`);
                        location.reload();
                    } else {
                        this.showError(response.data.error || 'Sync mislukt');
                    }
                },
                complete: () => {
                    $('#gs1-sync-ranges').prop('disabled', false).text('🔄 Sync Ranges van GS1 API');
                }
            });
        },
        
        resetLastUsed: function(e) {
            const contract = $(e.target).data('contract');
            
            if (!confirm(`Weet je zeker dat je de "Laatst Gebruikt" voor contract ${contract} wilt resetten?\n\nDe volgende GTIN begint weer vanaf het begin van de range.`)) {
                return;
            }
            
            const button = $(e.target);
            button.prop('disabled', true).text('Resetten...');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_reset_last_used',
                    nonce: gs1GtinAdmin.nonce,
                    contract_number: contract
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Last used gereset');
                        location.reload();
                    } else {
                        this.showError(response.data.message || 'Reset mislukt');
                    }
                },
                error: () => {
                    this.showError('Netwerkfout');
                },
                complete: () => {
                    button.prop('disabled', false).text('🔄 Reset');
                }
            });
        },
        
        openSetLastUsedModal: function(e) {
            const button = $(e.target);
            const contract = button.data('contract');
            const start = button.data('start');
            const end = button.data('end');
            
            // Get current last_used via table
            const currentText = button.closest('tr').find('td:eq(5)').text().trim();
            const current = currentText === 'Niet gebruikt' ? '-' : currentText;
            
            $('#gs1-modal-contract').text(contract);
            $('#gs1-modal-current').text(current);
            $('#gs1-modal-start').text(start);
            $('#gs1-modal-end').text(end);
            $('#gs1-new-last-used').val('').data('contract', contract).data('start', start).data('end', end);
            
            $('#gs1-set-last-used-modal').show();
        },
        
        confirmSetLastUsed: function() {
            const input = $('#gs1-new-last-used');
            const newValue = input.val().trim();
            const contract = input.data('contract');
            const start = parseInt(input.data('start'));
            const end = parseInt(input.data('end'));
            
            // Validate
            if (!newValue) {
                this.showError('Vul een GTIN in');
                return;
            }
            
            if (newValue.length !== 12) {
                this.showError('GTIN moet exact 12 cijfers zijn (zonder checkdigit)');
                return;
            }
            
            if (!/^\d+$/.test(newValue)) {
                this.showError('GTIN mag alleen cijfers bevatten');
                return;
            }
            
            const newValueInt = parseInt(newValue);
            if (newValueInt < start || newValueInt > end) {
                this.showError(`GTIN moet tussen ${start} en ${end} liggen`);
                return;
            }
            
            $('#gs1-confirm-set-last-used').prop('disabled', true).text('Instellen...');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_set_last_used',
                    nonce: gs1GtinAdmin.nonce,
                    contract_number: contract,
                    last_used: newValue
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Last used ingesteld');
                        $('#gs1-set-last-used-modal').hide();
                        location.reload();
                    } else {
                        this.showError(response.data.message || 'Instellen mislukt');
                    }
                },
                error: () => {
                    this.showError('Netwerkfout');
                },
                complete: () => {
                    $('#gs1-confirm-set-last-used').prop('disabled', false).text('Instellen');
                }
            });
        },
        
        checkRegistration: function(invocationId) {
            const button = $(`button[data-invocation-id="${invocationId}"]`);
            button.prop('disabled', true).text('Checken...');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_check_registration',
                    nonce: gs1GtinAdmin.nonce,
                    invocation_id: invocationId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(`${response.data.updated} producten bijgewerkt`);
                        location.reload();
                    } else {
                        this.showError(response.data.message || 'Check mislukt');
                    }
                },
                error: () => {
                    this.showError('Netwerkfout');
                },
                complete: () => {
                    button.prop('disabled', false).text('Status Checken');
                }
            });
        },
        
        viewRegistrationDetails: function(invocationId) {
            $('#gs1-registration-details-modal').show();
            $('#gs1-registration-details-body').html('<p><span class="spinner is-active"></span> Details laden...</p>');
            
            // Load details - in real implementation this would be an AJAX call
            setTimeout(() => {
                $('#gs1-registration-details-body').html(`
                    <p>Details voor Invocation ID: <code>${invocationId}</code></p>
                    <p><em>Hier komen de details van de registratie</em></p>
                `);
            }, 500);
        },
        
        // Logs
        viewLog: function(filename) {
            $('.gs1-log-viewer').show();
            $('#gs1-current-log-name').text(filename).data('filename', filename);
            $('#gs1-log-content-pre').html('<span class="spinner is-active"></span> Log laden...');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_get_log',
                    nonce: gs1GtinAdmin.nonce,
                    filename: filename
                },
                success: (response) => {
                    if (response.success) {
                        $('#gs1-log-content-pre').text(response.data.content);
                    } else {
                        $('#gs1-log-content-pre').text('Fout bij laden van log');
                    }
                }
            });
        },
        
        copyLog: function() {
            const content = $('#gs1-log-content-pre').text();
            navigator.clipboard.writeText(content).then(() => {
                this.showSuccess('Log gekopieerd naar clipboard');
            });
        },
        
        clearLog: function() {
            if (!confirm('Weet je zeker dat je deze log wilt legen?')) return;
            
            const filename = $('#gs1-current-log-name').data('filename');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_clear_log',
                    nonce: gs1GtinAdmin.nonce,
                    filename: filename
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Log geleegd');
                        $('#gs1-log-content-pre').text('');
                    } else {
                        this.showError(response.data.message);
                    }
                }
            });
        },
        
        deleteCurrentLog: function() {
            if (!confirm('Weet je zeker dat je deze log wilt verwijderen?')) return;
            
            const filename = $('#gs1-current-log-name').data('filename');
            this.deleteLog(filename);
        },
        
        deleteLog: function(filename) {
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_delete_log',
                    nonce: gs1GtinAdmin.nonce,
                    filename: filename
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Log verwijderd');
                        $('.gs1-log-viewer').hide();
                        location.reload();
                    } else {
                        this.showError(response.data.message);
                    }
                }
            });
        },
        
        filterLogs: function(level) {
            if (level === '') {
                $('#gs1-db-logs-table tbody tr').show();
            } else {
                $('#gs1-db-logs-table tbody tr').hide();
                $(`#gs1-db-logs-table tbody tr[data-level="${level}"]`).show();
            }
        },
        
        // Settings
        testConnection: function() {
            $('.gs1-test-connection').prop('disabled', true).text('Testen...');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_test_connection',
                    nonce: gs1GtinAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                    } else {
                        this.showError(response.data.message);
                    }
                },
                complete: () => {
                    $('.gs1-test-connection').prop('disabled', false).text('Test API Verbinding');
                }
            });
        },
        
        addGpcMapping: function() {
            const template = $('#gs1-gpc-mapping-template').html();
            $('.gs1-gpc-mappings table tbody').append(template);
        },
        
        saveGpcMapping: function(e) {
            const row = $(e.target).closest('tr');
            const itemGroupId = row.find('.gs1-item-group-select').val();
            const gpcCode = row.find('input[name="gpc_code"]').val();
            const gpcTitle = row.find('input[name="gpc_title"]').val();
            
            if (!itemGroupId || !gpcCode || !gpcTitle) {
                this.showError('Alle velden zijn verplicht');
                return;
            }
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_save_gpc_mapping',
                    nonce: gs1GtinAdmin.nonce,
                    item_group_id: itemGroupId,
                    gpc_code: gpcCode,
                    gpc_title: gpcTitle
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('GPC mapping opgeslagen');
                        location.reload();
                    } else {
                        this.showError(response.data.message);
                    }
                }
            });
        },
        
        deleteGpcMapping: function(e) {
            if (!confirm('Weet je zeker dat je deze mapping wilt verwijderen?')) return;
            
            const categoryId = $(e.target).data('category-id');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_delete_gpc_mapping',
                    nonce: gs1GtinAdmin.nonce,
                    category_id: categoryId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('GPC mapping verwijderd');
                        location.reload();
                    } else {
                        this.showError(response.data.message);
                    }
                }
            });
        },
        
        // Helpers
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },
        
        showError: function(message) {
            this.showNotice(message, 'error');
        },
        
        showNotice: function(message, type) {
            const notice = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
            $('.wrap > h1').after(notice);
            
            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 5000);
        }
    };
    
    // Initialize on document ready
    $(document).ready(() => {
        GS1Admin.init();
    });
    
})(jQuery);