/**
 * GS1 GTIN Manager - Admin JavaScript - FIXED: Better error handling
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 * 
 * CHANGELOG:
 * - FIXED: Show detailed error messages when GTIN assignment fails
 * - FIXED: Disable "GTIN Toewijzen" button for products that already have GTINs
 * - IMPROVED: Better user feedback on duplicate assignment attempts
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
            $(document).on('click', '#gs1-unassign-selected', () => this.unassignGtins());
            $(document).on('click', '#gs1-register-selected', () => this.startRegistration());
            $(document).on('click', '#gs1-mark-external', () => this.markExternal());
            $(document).on('click', '#gs1-update-gtin', () => this.openUpdateModal());
            $(document).on('click', '#gs1-update-cancel, #gs1-update-modal .gs1-modal-close', () => {
                $('#gs1-update-modal').hide();
            });
            $(document).on('click', '#gs1-update-save', () => this.saveUpdate());
            $(document).on('change', '#gs1-update-gtin-input', () => this.validateGtinInput());
            $(document).on('change', '#gs1-update-external', (e) => {
    if (e.target.checked) {
        $('#gs1-update-gs1-data').hide();
    } else {
        $('#gs1-update-gs1-data').show();
    }
    // Force update blijft altijd zichtbaar als GTIN bestaat
});
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
            
            // Database management
            $(document).on('click', '.gs1-check-database', () => this.checkDatabase());
            $(document).on('click', '.gs1-fix-database', () => this.fixDatabase());
            
            // Reference data tab
            $(document).on('click', '.gs1-add-reference-item', (e) => this.openReferenceModal(e));
            $(document).on('click', '.gs1-edit-reference-item', (e) => this.openReferenceModal(e));
            $(document).on('click', '.gs1-delete-reference-item', (e) => this.deleteReferenceItem(e));
            $(document).on('click', '#gs1-save-reference-item', () => this.saveReferenceItem());
            $(document).on('click', '#gs1-cancel-reference-modal, #gs1-reference-modal .gs1-modal-close', () => {
                $('#gs1-reference-modal').hide();
            });
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
            let noGtin = 0;
            
            $('.gs1-product-checkbox:checked').each((i, el) => {
                this.selectedProducts.push(parseInt($(el).val()));
                if ($(el).data('has-gtin') === 1) {
                    hasGtin++;
                } else {
                    noGtin++;
                }
            });
            
            const hasSelection = this.selectedProducts.length > 0;
            const singleSelection = this.selectedProducts.length === 1;
            
            // ==========================================
            // CRITICAL FIX: Smart button enabling
            // ==========================================
            
            // "GTIN Toewijzen" button: Only enable for products WITHOUT GTIN
            $('#gs1-assign-selected').prop('disabled', noGtin === 0);
            
            // Update button text to show what it will do
            if (noGtin > 0 && hasGtin > 0) {
                $('#gs1-assign-selected').text(`GTIN Toewijzen aan ${noGtin} producten (${hasGtin} worden genegeerd)`);
            } else if (noGtin > 0) {
                $('#gs1-assign-selected').text('GTIN Toewijzen aan Geselecteerde');
            } else {
                $('#gs1-assign-selected').text('GTIN Toewijzen (geen producten zonder GTIN geselecteerd)');
            }
            
            // "UPDATE GTIN Data" button: Only enable for SINGLE selection
            $('#gs1-update-gtin').prop('disabled', !singleSelection);
            
            // "GTIN Verwijderen" button: Only enable if has GTIN
            $('#gs1-unassign-selected').prop('disabled', hasGtin === 0);
            
            // "Registreren" button: Only enable if has GTIN
            $('#gs1-register-selected').prop('disabled', hasGtin === 0);
            
            // "Markeer als Extern" button: Always enabled if selection
            $('#gs1-mark-external').prop('disabled', !hasSelection);
        },
        
        changePage: function(direction) {
            this.currentPage += direction;
            this.loadProducts();
        },
        
        // ==========================================
        // CRITICAL FIX: Better error handling
        // ==========================================
        assignGtins: function() {
            if (this.selectedProducts.length === 0) return;
            
            if (!confirm(`GTIN toewijzen aan ${this.selectedProducts.length} producten?`)) return;
            
            const button = $('#gs1-assign-selected');
            const originalText = button.text();
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
                        
                        // Show detailed message
                        let message = `✅ ${result.success.length} GTINs toegewezen`;
                        
                        if (result.errors.length > 0) {
                            message += `\n⚠️ ${result.errors.length} fouten:`;
                            
                            // Show first 3 errors in detail
                            const errorsToShow = result.errors.slice(0, 3);
                            errorsToShow.forEach(err => {
                                message += `\n  - Product ${err.product_id}: ${err.error}`;
                            });
                            
                            if (result.errors.length > 3) {
                                message += `\n  ... en ${result.errors.length - 3} meer (check logs)`;
                            }
                            
                            this.showWarning(message);
                        } else {
                            this.showSuccess(message);
                        }
                        
                        this.loadProducts();
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('Netwerkfout: ' + error);
                },
                complete: () => {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        unassignGtins: function() {
            if (this.selectedProducts.length === 0) return;
            
            if (!confirm(`⚠️ WAARSCHUWING: Je staat op het punt om ${this.selectedProducts.length} GTIN(s) te verwijderen!\n\nDit kan NIET ongedaan worden gemaakt!\n\nWeet je het zeker?`)) return;
            
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
        
        // UPDATE GTIN Modal functions
        openUpdateModal: function() {
            if (this.selectedProducts.length !== 1) {
                this.showError('Selecteer exact 1 product om bij te werken');
                return;
            }
            
            const productId = this.selectedProducts[0];
            
            // Get product data
            const row = $(`.gs1-product-checkbox[value="${productId}"]`).closest('tr');
            const productName = row.find('td:eq(1) strong').text();
            const sku = row.find('td:eq(2)').text();
            const currentGtin = row.find('td:eq(5)').text();
            
            // Show product info
            $('#gs1-update-product-info').html(`
                <strong>Product:</strong> ${productName}<br>
                <strong>SKU:</strong> ${sku}
            `);
            
           // Set current GTIN or empty
            if (currentGtin && currentGtin !== '-') {
                $('#gs1-update-gtin-input').val(currentGtin).prop('readonly', true);
                $('#gs1-gtin-status').html('✅ Bestaande GTIN (kan niet worden gewijzigd)').css('color', '#00a32a');
                $('#gs1-update-gs1-data').show();
                $('#gs1-force-update-row').show(); // NIEUW: Toon force update row
            } else {
                $('#gs1-update-gtin-input').val('').prop('readonly', false);
                $('#gs1-gtin-status').html('Vul een 13-cijferige GTIN in').css('color', '#646970');
                $('#gs1-update-gs1-data').hide();
                $('#gs1-force-update-row').hide(); // NIEUW: Verberg force update row
            }
            
            
            // Check if product is marked as external in the Actions column
const isExternal = row.find('td:eq(7)').text().includes('Extern');
$('#gs1-update-external').prop('checked', isExternal);

// If external, hide GS1 data section
if (isExternal) {
    $('#gs1-update-gs1-data').hide();
}
            
            // Load product data for GS1 update
            this.loadProductDataForUpdate(productId);
            
            // Store product ID
            $('#gs1-update-modal').data('product-id', productId);
            
            // Show modal
            $('#gs1-update-modal').show();
        },
        
        validateGtinInput: function() {
            const gtin = $('#gs1-update-gtin-input').val().trim();
            const statusSpan = $('#gs1-gtin-status');
            
            if (!gtin) {
                statusSpan.html('Vul een GTIN in').css('color', '#646970');
                return false;
            }
            
            // Remove non-digits
            const gtinClean = gtin.replace(/[^0-9]/g, '');
            
            if (gtinClean.length === 13) {
                // Validate checkdigit
                const isValid = this.validateCheckdigit(gtinClean);
                
                if (isValid) {
                    statusSpan.html('✅ Checkdigit correct').css('color', '#00a32a');
                    return true;
                } else {
                    statusSpan.html('❌ Checkdigit incorrect').css('color', '#d63638');
                    return false;
                }
            } else if (gtinClean.length === 12) {
                // Calculate and show checkdigit
                const checkdigit = this.calculateCheckdigit(gtinClean);
                const gtin13 = gtinClean + checkdigit;
                statusSpan.html(`ℹ️ Met checkdigit: ${gtin13}`).css('color', '#2271b1');
                return true;
            } else {
                statusSpan.html('❌ GTIN moet 12 of 13 cijfers zijn').css('color', '#d63638');
                return false;
            }
        },
        
        validateCheckdigit: function(gtin13) {
    if (gtin13.length !== 13) return false;
    
    const gtin12 = gtin13.substring(0, 12);
    const providedCheck = parseInt(gtin13[12]);
    
    // Calculate just the checkdigit (not the full 13-digit GTIN)
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
    let calculatedCheck = (10 - (total % 10)) % 10;
    
    return providedCheck === calculatedCheck;
},
        loadProductDataForUpdate: function(productId) {
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_get_registration_data',
                    nonce: gs1GtinAdmin.nonce,
                    product_ids: [productId]
                },
                success: (response) => {
                    if (response.success && response.data.products.length > 0) {
                        const product = response.data.products[0];
                        const data = product.data;
                        
                        // Fill form
                        $('#gs1-update-description').val(data.Description || '');
                        $('#gs1-update-gpc').val(data.Gpc || '');
                        $('#gs1-update-packaging').val(data.PackagingType || 'Doos');
                    }
                }
            });
        },
        
        saveUpdate: function() {
            const productId = $('#gs1-update-modal').data('product-id');
            const gtin = $('#gs1-update-gtin-input').val().trim();
            const isExternal = $('#gs1-update-external').is(':checked');
            
            if (!productId) {
                this.showError('Geen product geselecteerd');
                return;
            }
            
            if (!gtin) {
                this.showError('GTIN is verplicht');
                return;
            }
            
            // Validate GTIN
            if (!this.validateGtinInput()) {
                this.showError('GTIN is niet geldig');
                return;
            }
            
            // Confirm
            const gtinClean = gtin.replace(/[^0-9]/g, '');
            const gtin13 = gtinClean.length === 12 ? this.calculateCheckdigit(gtinClean) + gtinClean : gtinClean;
            
            if (!$('#gs1-update-gtin-input').prop('readonly')) {
                if (!confirm(`⚠️ Weet je zeker dat GTIN ${gtin13} correct is?\n\nDit kan niet ongedaan worden gemaakt!`)) {
                    return;
                }
            }
            
            const button = $('#gs1-update-save');
            button.prop('disabled', true).text('Opslaan...');
            
            // Prepare update data
            const updateData = {};
            if (!isExternal) {
                updateData.Description = $('#gs1-update-description').val();
                updateData.Gpc = $('#gs1-update-gpc').val();
                updateData.PackagingType = $('#gs1-update-packaging').val();
            }
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_update_gtin_data',
                    nonce: gs1GtinAdmin.nonce,
                    product_id: productId,
                    gtin: gtin,
                    is_external: isExternal,
                    update_data: updateData,
                    force_update: $('#gs1-force-update').is(':checked')
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        $('#gs1-update-modal').hide();
                        this.loadProducts();
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: () => {
                    this.showError('Netwerkfout');
                },
                complete: () => {
                    button.prop('disabled', false).text('Opslaan');
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
                                <label>Maateenheid</label>
                                <select data-index="${index}" data-field="MeasurementUnit" class="gs1-measurement-select">
                                    <option value="">Laden...</option>
                                </select>
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
            
            // Load measurement units
            this.loadMeasurementUnits();
            
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
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_get_registration_details',
                    nonce: gs1GtinAdmin.nonce,
                    invocation_id: invocationId
                },
                success: (response) => {
                    if (response.success) {
                        const data = response.data;
                        let html = `<h3>Invocation ID: <code>${invocationId}</code></h3>`;
                        
                        if (data.successfulProducts && data.successfulProducts.length > 0) {
                            html += `<h4 style="color: #00a32a;">✅ Succesvol (${data.successfulProducts.length})</h4>`;
                            html += '<table class="wp-list-table widefat fixed striped"><thead><tr><th>GTIN</th><th>Beschrijving</th><th>Status</th></tr></thead><tbody>';
                            data.successfulProducts.forEach(p => {
                                html += `<tr><td><code>${p.gtin}</code></td><td>${p.description}</td><td>${p.status}</td></tr>`;
                            });
                            html += '</tbody></table>';
                        }
                        
                        if (data.errorMessages && data.errorMessages.length > 0) {
                            html += `<h4 style="color: #d63638; margin-top: 20px;">❌ Errors (${data.errorMessages.length})</h4>`;
                            html += '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Index</th><th>Error Code</th><th>Bericht</th></tr></thead><tbody>';
                            data.errorMessages.forEach(e => {
                                html += `<tr><td>${e.index}</td><td>${e.errorCode}</td><td>${e.errorMessageNl || e.errorMessageEn}</td></tr>`;
                            });
                            html += '</tbody></table>';
                        }
                        
                        $('#gs1-registration-details-body').html(html);
                    } else {
                        $('#gs1-registration-details-body').html('<p style="color: #d63638;">Fout bij laden</p>');
                    }
                }
            });
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
        
        // Database Management
        checkDatabase: function() {
            const button = $('.gs1-check-database');
            button.prop('disabled', true).text('Checken...');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_check_database',
                    nonce: gs1GtinAdmin.nonce
                },
                success: (response) => {
                    const statusDiv = $('#gs1-database-status');
                    
                    if (response.success) {
                        statusDiv.html(`<div class="notice notice-success inline"><p>${response.data.message}</p></div>`);
                    } else {
                        let html = `<div class="notice notice-warning inline"><p>${response.data.message}</p><ul>`;
                        for (const [table, exists] of Object.entries(response.data.status)) {
                            const icon = exists ? '✓' : '✗';
                            const color = exists ? 'green' : 'red';
                            html += `<li style="color: ${color}">${icon} ${table}</li>`;
                        }
                        html += '</ul></div>';
                        statusDiv.html(html);
                    }
                },
                complete: () => {
                    button.prop('disabled', false).text('✓ Check Database Tabellen');
                }
            });
        },
        
        fixDatabase: function() {
            if (!confirm('Database tabellen aanmaken/bijwerken?')) return;
            
            const button = $('.gs1-fix-database');
            button.prop('disabled', true).text('Fixen...');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_fix_database',
                    nonce: gs1GtinAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        $('#gs1-database-status').html('<div class="notice notice-success inline"><p>✓ Database OK</p></div>');
                    } else {
                        this.showError('Fout bij aanmaken tabellen');
                    }
                },
                complete: () => {
                    button.prop('disabled', false).text('🔧 Fix Database Tabellen');
                }
            });
        },
        
        // Reference Data Methods
        openReferenceModal: function(e) {
            const button = $(e.target);
            const category = button.data('category');
            const id = button.data('id') || '';
            const valueNl = button.data('value-nl') || '';
            const valueEn = button.data('value-en') || '';
            const code = button.data('code') || '';
            const isActive = button.data('is-active') || 1;
            
            $('#gs1-ref-id').val(id);
            $('#gs1-ref-category').val(category);
            $('#gs1-ref-value-nl').val(valueNl);
            $('#gs1-ref-value-en').val(valueEn);
            $('#gs1-ref-code').val(code);
            $('#gs1-ref-is-active').prop('checked', isActive == 1);
            
            if (category === 'country') {
                $('#gs1-ref-code-row').show();
                $('#gs1-ref-code').prop('required', true);
            } else {
                $('#gs1-ref-code-row').hide();
                $('#gs1-ref-code').prop('required', false);
            }
            
            const titles = {
                'packaging': id ? 'Verpakkingstype Bewerken' : 'Nieuw Verpakkingstype',
                'measurement': id ? 'Maateenheid Bewerken' : 'Nieuwe Maateenheid',
                'country': id ? 'Land Bewerken' : 'Nieuw Land'
            };
            $('#gs1-reference-modal-title').text(titles[category] || 'Reference Data');
            
            $('#gs1-reference-modal').show();
        },
        
        saveReferenceItem: function() {
            const id = $('#gs1-ref-id').val();
            const category = $('#gs1-ref-category').val();
            const valueNl = $('#gs1-ref-value-nl').val().trim();
            const valueEn = $('#gs1-ref-value-en').val().trim();
            const code = $('#gs1-ref-code').val().trim();
            const isActive = $('#gs1-ref-is-active').is(':checked') ? 1 : 0;
            
            if (!valueNl || !valueEn) {
                this.showError('Nederlands en Engels zijn verplicht');
                return;
            }
            
            if (category === 'country' && !code) {
                this.showError('Code is verplicht voor landen');
                return;
            }
            
            const button = $('#gs1-save-reference-item');
            button.prop('disabled', true).text('Opslaan...');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_save_reference_data',
                    nonce: gs1GtinAdmin.nonce,
                    id: id,
                    category: category,
                    value_nl: valueNl,
                    value_en: valueEn,
                    code: code || null,
                    is_active: isActive
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message || 'Opgeslagen');
                        $('#gs1-reference-modal').hide();
                        location.reload();
                    } else {
                        this.showError(response.data.message || 'Fout bij opslaan');
                    }
                },
                error: () => {
                    this.showError('Netwerkfout');
                },
                complete: () => {
                    button.prop('disabled', false).text('Opslaan');
                }
            });
        },
        
        loadMeasurementUnits: function() {
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_get_measurement_units',
                    nonce: gs1GtinAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const units = response.data.units;
                        
                        $('.gs1-measurement-select').each(function() {
                            const select = $(this);
                            const index = select.data('index');
                            const currentValue = select.closest('.gs1-registration-data-item')
                                .find(`[data-index="${index}"][data-field="MeasurementUnit"]`)
                                .val() || 'Stuks (piece)';
                            
                            select.empty();
                            
                            units.forEach(unit => {
                                const value = unit.value_nl + ' (' + unit.value_en + ')';
                                const selected = currentValue === value ? 'selected' : '';
                                select.append(`<option value="${value}" ${selected}>${value}</option>`);
                            });
                        });
                    }
                }
            });
        },
        
        deleteReferenceItem: function(e) {
            if (!confirm('Weet je zeker dat je dit item wilt verwijderen?')) {
                return;
            }
            
            const id = $(e.target).data('id');
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_delete_reference_data',
                    nonce: gs1GtinAdmin.nonce,
                    id: id
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message || 'Verwijderd');
                        location.reload();
                    } else {
                        this.showError(response.data.message || 'Fout bij verwijderen');
                    }
                },
                error: () => {
                    this.showError('Netwerkfout');
                }
            });
        },
        
        // Helpers
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },
        
        showWarning: function(message) {
            this.showNotice(message, 'warning');
        },
        
        showError: function(message) {
            this.showNotice(message, 'error');
        },
        
        showNotice: function(message, type) {
            // Use alert for multiline messages
            if (message.includes('\n')) {
                alert(message);
                return;
            }
            
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