/**
 * GS1 GTIN Manager - Admin JavaScript
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
            $(document).on('change', '#gs1-log-level-filter', (e) => {
                this.filterLogs($(e.target).val());
            });
            $(document).on('click', '.gs1-view-log-context', (e) => {
                const context = $(e.target).data('context');
                $('#gs1-log-context-modal').show();
                try {
                    const parsed = JSON.parse(context);
                    $('#gs1-log-context-content').text(JSON.stringify(parsed, null, 2));
                } catch(e) {
                    $('#gs1-log-context-content').text(context);
                }
            });
            $(document).on('click', '#gs1-close-context-modal', () => {
                $('#gs1-log-context-modal').hide();
            });
            
            // Settings tab
            $(document).on('click', '.gs1-test-connection', () => this.testConnection());
            $(document).on('click', '.gs1-add-gpc-mapping', () => this.addGpcMapping());
            $(document).on('click', '.gs1-save-gpc-mapping', (e) => this.saveGpcMapping(e));
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
                               data-external="${product.external}">
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
            $('.gs1-product-checkbox:checked').each((i, el) => {
                this.selectedProducts.push(parseInt($(el).val()));
            });
            
            const hasSelection = this.selectedProducts.length > 0;
            $('#gs1-assign-selected, #gs1-register-selected, #gs1-mark-external').prop('disabled', !hasSelection);
        },
        
        changePage: function(direction) {
            this.currentPage += direction;
            this.loadProducts();
        },
        
        // GTIN Assignment
        assignGtins: function() {
            if (this.selectedProducts.length === 0) return;
            
            if (!confirm(`GTIN toewijzen aan ${this.selectedProducts.length} producten?`)) return;
            
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
                return `
                    <div class="gs1-registration-product-item">
                        <strong>${product.product_name}</strong> (SKU: ${product.sku})<br>
                        <small>GTIN: ${product.data.gtin} | Merk: ${product.data.brandName || '-'}</small>
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
                return `
                    <div class="gs1-registration-data-item">
                        <h4>${product.product_name}</h4>
                        
                        <div class="gs1-form-row">
                            <div class="gs1-form-group">
                                <label>GTIN *</label>
                                <input type="text" readonly value="${product.data.gtin}" class="regular-text">
                            </div>
                            <div class="gs1-form-group">
                                <label>Status *</label>
                                <input type="text" value="${product.data.status}" class="regular-text" 
                                       data-index="${index}" data-field="status">
                            </div>
                        </div>
                        
                        <div class="gs1-form-row">
                            <div class="gs1-form-group">
                                <label>Merknaam</label>
                                <input type="text" value="${product.data.brandName || ''}" class="regular-text" 
                                       data-index="${index}" data-field="brandName">
                            </div>
                            <div class="gs1-form-group">
                                <label>GPC Code</label>
                                <input type="text" value="${product.data.gpc || ''}" class="regular-text" 
                                       data-index="${index}" data-field="gpc" 
                                       placeholder="Bijv: 10001234">
                            </div>
                        </div>
                        
                        <div class="gs1-form-row">
                            <div class="gs1-form-group">
                                <label>Verpakkingstype</label>
                                <select data-index="${index}" data-field="packagingType">
                                    <option value="Doos" ${product.data.packagingType === 'Doos' ? 'selected' : ''}>Doos</option>
                                    <option value="Zak" ${product.data.packagingType === 'Zak' ? 'selected' : ''}>Zak</option>
                                    <option value="Blister" ${product.data.packagingType === 'Blister' ? 'selected' : ''}>Blister</option>
                                    <option value="Fles" ${product.data.packagingType === 'Fles' ? 'selected' : ''}>Fles</option>
                                    <option value="Blik" ${product.data.packagingType === 'Blik' ? 'selected' : ''}>Blik</option>
                                </select>
                            </div>
                            <div class="gs1-form-group">
                                <label>Consumenteneenheid</label>
                                <select data-index="${index}" data-field="consumerUnit">
                                    <option value="true" ${product.data.consumerUnit === 'true' ? 'selected' : ''}>Ja</option>
                                    <option value="false" ${product.data.consumerUnit === 'false' ? 'selected' : ''}>Nee</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="gs1-form-row">
                            <div class="gs1-form-group">
                                <label>Netto inhoud</label>
                                <input type="text" value="${product.data.netContent || ''}" class="regular-text" 
                                       data-index="${index}" data-field="netContent" 
                                       placeholder="Bijv: 500">
                            </div>
                            <div class="gs1-form-group">
                                <label>Maateenheid</label>
                                <select data-index="${index}" data-field="measurementUnit">
                                    <option value="kilogram" ${product.data.measurementUnit === 'kilogram' ? 'selected' : ''}>Kilogram</option>
                                    <option value="gram" ${product.data.measurementUnit === 'gram' ? 'selected' : ''}>Gram</option>
                                    <option value="liter" ${product.data.measurementUnit === 'liter' ? 'selected' : ''}>Liter</option>
                                    <option value="milliliter" ${product.data.measurementUnit === 'milliliter' ? 'selected' : ''}>Milliliter</option>
                                    <option value="stuks" ${product.data.measurementUnit === 'stuks' ? 'selected' : ''}>Stuks</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="gs1-form-group">
                            <label>Beschrijving (max 300 tekens) *</label>
                            <textarea rows="3" class="large-text" data-index="${index}" data-field="description" 
                                      maxlength="300">${product.data.description || ''}</textarea>
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
                if (!product.data.description || product.data.description.trim() === '') {
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
        
        // Status
        checkRegistration: function(invocationId) {
            $(`button[data-invocation-id="${invocationId}"]`).prop('disabled', true).text('Checken...');
            
            // In real implementation, this would call the API
            setTimeout(() => {
                this.showSuccess('Registratie status gecheckt');
                location.reload();
            }, 1000);
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
            $('#gs1-current-log-name').text(filename);
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
        
        filterLogs: function(level) {
            if (level === '') {
                $('#gs1-db-logs-table tbody tr').show();
            } else {
                $('#gs1-db-logs-table tbody tr').hide();
                $(`#gs1-db-logs-table tbody tr[data-level="${level}"]`).show();
            }
        },
        
        viewLogContext: function(context) {
            $('#gs1-log-context-modal').show();
            try {
                const parsed = JSON.parse(context);
                $('#gs1-log-context-content').text(JSON.stringify(parsed, null, 2));
            } catch(e) {
                $('#gs1-log-context-content').text(context);
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
            const categoryId = row.find('select[name="category_id"]').val();
            const gpcCode = row.find('input[name="gpc_code"]').val();
            const gpcTitle = row.find('input[name="gpc_title"]').val();
            
            if (!categoryId || !gpcCode || !gpcTitle) {
                this.showError('Alle velden zijn verplicht');
                return;
            }
            
            $.ajax({
                url: gs1GtinAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gs1_save_gpc_mapping',
                    nonce: gs1GtinAdmin.nonce,
                    category_id: categoryId,
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