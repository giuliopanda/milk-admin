'use strict';

/**
 * Enhanced Table Component - Formless Version
 * Manages interactive table functionality including row selection, pagination, 
 * sorting, bulk actions, and dynamic content loading via AJAX.
 * 
 * This version stores state internally and builds FormData dynamically,
 * instead of relying on hidden form fields.
 * 
 * MIGRATION NOTES:
 * - State is now stored in this.state object instead of hidden inputs
 * - FormData is built dynamically in buildFormData()
 * - Hooks now receive the Table instance instead of form element
 * - custom_data replaces form_html_input_hidden (passed via data-custom attribute)
 */
class Table {
    // DOM element containing the table
    el_container = null;
    
    // Component identifier for debugging
    component_name = 'Table';
    
    // Scroll target element (defaults to container)
    el_scroll = null;
    
    // Initialization state flag
    is_init = false;
    
    // Loading plugin instance
    plugin_loading = null;
    
    // Custom initialization callback function
    custom_init_fn = null;

    // Internal state (replaces hidden form fields)
    state = {
        page: '',
        action: '',
        table_id: '',
        token: '',
        action_url: '',
        table_action: '',
        table_ids: '',
        current_page: 1,
        limit: 10,
        order_field: '',
        order_dir: '',
        filters: '',
        custom: {}
    };

    /**
     * Constructor - Initialize table component
     * @param {HTMLElement} el_container - The container element for the table
     * @param {Function|null} custom_init_fn - Optional custom initialization function
     */
    constructor(el_container, custom_init_fn = null) {
        // Validate required container element
        if (!el_container) {
            throw new Error('Table component requires a valid container element');
        }

        this.el_container = el_container;
        this.el_scroll = el_container; // Default scroll target
        this.custom_init_fn = custom_init_fn;
        
        // Initialize state from data attributes
        this.initStateFromAttributes();
        
        // Initialize component
        this.init();
        this.is_init = true;
        
        // Store component reference on DOM element for external access
        this.el_container.__itoComponent = this;
        
        // Initialize loading plugin if loading element exists
        const loading_element = this.el_container.querySelector('.js-ito-loading');
        if (loading_element) {
            this.plugin_loading = new Loading(loading_element);
        }
    }

    /**
     * Initialize state from data-* attributes on container
     */
    initStateFromAttributes() {
        const container = this.el_container;
        
        this.state.page = container.getAttribute('data-page') || '';
        this.state.action = container.getAttribute('data-action') || '';
        this.state.table_id = container.getAttribute('data-table-id') || container.getAttribute('id') || '';
        this.state.token = container.getAttribute('data-token') || '';
        this.state.action_url = container.getAttribute('data-action-url') || '';
        this.state.current_page = parseInt(container.getAttribute('data-current-page')) || 1;
        this.state.limit = parseInt(container.getAttribute('data-limit')) || 10;
        this.state.order_field = container.getAttribute('data-order-field') || '';
        this.state.order_dir = container.getAttribute('data-order-dir') || '';
        this.state.filters = container.getAttribute('data-filters') || '';
        
        // Parse custom data
        const custom_json = container.getAttribute('data-custom');
        if (custom_json) {
            try {
                this.state.custom = JSON.parse(custom_json);
            } catch (e) {
                console.warn('Invalid custom data JSON:', e);
                this.state.custom = {};
            }
        }
        
        // Reset action fields
        this.state.table_action = '';
        this.state.table_ids = '';
    }

    /**
     * Build FormData from internal state
     * Maintains the same POST structure as the original form-based version
     * @returns {FormData}
     */
    buildFormData() {
        const formData = new FormData();
        const table_id = this.state.table_id;
        
        // Base parameters
        formData.append('page', this.state.page);
        formData.append('action', this.state.action);
        formData.append('page-output', 'json');
        formData.append('is-inside-request', '1');
        formData.append('table_id', table_id);
        
        // Token
        formData.append('token', this.state.token);
        
        // Table-specific parameters (namespaced under table_id)
        formData.append(`${table_id}[table_action]`, this.state.table_action);
        formData.append(`${table_id}[table_ids]`, this.state.table_ids);
        formData.append(`${table_id}[page]`, this.state.current_page);
        formData.append(`${table_id}[limit]`, this.state.limit);
        formData.append(`${table_id}[order_field]`, this.state.order_field);
        formData.append(`${table_id}[order_dir]`, this.state.order_dir);
        formData.append(`${table_id}[filters]`, this.state.filters);
        
        // Custom data parameters
        if (this.state.custom && typeof this.state.custom === 'object') {
            for (const [key, value] of Object.entries(this.state.custom)) {
                formData.append(key, value);
            }
        }
        
        return formData;
    }

    /**
     * Initialize all table functionality
     * Sets up event listeners for checkboxes, sorting, pagination, and actions
     */
    init() {
        // Prevent double initialization
        if (this.is_init) {
            return;
        }

        this.initialize_row_interactions();
        this.initialize_row_selection();
        this.initialize_header_checkbox();
        this.initialize_sorting();
        this.initialize_bulk_actions();
        this.initialize_single_actions();
        this.initialize_link_confirms();
        this.initialize_pagination();
        this.execute_custom_initialization();
        this.trigger_initialization_hook();
    }

    /**
     * Initialize row hover effects and click interactions
     */
    initialize_row_interactions() {
        const table_rows = this.el_container.querySelectorAll('.js-table-tr');
        
        table_rows.forEach(row => {
            // Add hover effects
            row.addEventListener('mouseover', () => {
                row.classList.add('js-hover');
            });

            row.addEventListener('mouseout', () => {
                row.classList.remove('js-hover');
            });

            // Handle row clicks for selection
            row.addEventListener('click', (event) => {
                this.handle_row_click(event);
            });
        });
    }

    /**
     * Handle row click events for checkbox selection
     * @param {Event} event - The click event
     */
    handle_row_click(event) {
        const row = event.target.closest('.js-table-tr');
        if (!row) return;

        const checkbox = row.querySelector('.js-col-checkbox');
        if (!checkbox) return;

        // Skip if clicking directly on checkbox or links
        if (event.target === checkbox || event.target.tagName === 'A') {
            return;
        }

        // Toggle checkbox state
        checkbox.checked = !checkbox.checked;
        
        // Sync row selection class with checkbox state
        if (checkbox.checked) {
            row.classList.add('js-selected');
        } else {
            row.classList.remove('js-selected');
        }

        // Trigger change event for checkbox
        const change_event = new Event('change', { bubbles: true });
        checkbox.dispatchEvent(change_event);
    }

    /**
     * Initialize individual row checkbox change handlers
     */
    initialize_row_selection() {
        const checkboxes = this.el_container.querySelectorAll('.js-col-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                // Sync row selection class with checkbox state
                const row = checkbox.closest('.js-table-tr');
                if (row) {
                    if (checkbox.checked) {
                        row.classList.add('js-selected');
                    } else {
                        row.classList.remove('js-selected');
                    }
                }
                
                this.update_header_checkbox_state();
                this.recalculating();
            });
        });
    }

    /**
     * Update header checkbox state based on selected rows
     */
    update_header_checkbox_state() {
        const selected_checkboxes = this.el_container.querySelectorAll('.js-col-checkbox:checked');
        const all_checkboxes = this.el_container.querySelectorAll('.js-col-checkbox');
        const table_head_checkbox = this.el_container.querySelector('.js-click-all-checkbox');
        
        if (!table_head_checkbox) return;

        if (selected_checkboxes.length === all_checkboxes.length) {
            // All selected
            table_head_checkbox.checked = true;
            table_head_checkbox.indeterminate = false;
        } else if (selected_checkboxes.length > 0) {
            // Some selected
            table_head_checkbox.checked = true;
            table_head_checkbox.indeterminate = true;
        } else {
            // None selected
            table_head_checkbox.checked = false;
            table_head_checkbox.indeterminate = false;
        }
    }

    /**
     * Initialize header "select all" checkbox
     */
    initialize_header_checkbox() {
        const table_head_checkbox = this.el_container.querySelector('.js-click-all-checkbox');
        
        if (table_head_checkbox) {
            table_head_checkbox.addEventListener('click', () => {
                this.toggleAllCheckboxes(this.el_container.querySelector('.js-table'));
                this.recalculating();
            });
        }
    }

    /**
     * Initialize column sorting functionality
     */
    initialize_sorting() {
        const sorting_elements = this.el_container.querySelectorAll('.js-table-change-order');
        
        sorting_elements.forEach(element => {
            element.addEventListener('click', () => {
                this.tableChangeOrder(element);
            });
        });
    }

    /**
     * Initialize bulk action buttons
     */
    initialize_bulk_actions() {
        const bulk_action_elements = this.el_container.querySelectorAll('.js-table-bulk-action');
        
        bulk_action_elements.forEach(element => {
            element.addEventListener('click', () => {
                this.tableBulkAction(element);
            });
        });
    }

    /**
     * Initialize single row action buttons
     */
    initialize_single_actions() {
        const single_action_elements = this.el_container.querySelectorAll('.js-single-action');
        
        single_action_elements.forEach(element => {
            element.addEventListener('click', (event) => {
                this.tableSingleAction(element);
                event.stopPropagation();
            });
        });
    }

    /**
     * Initialize link confirmation dialogs
     */
    initialize_link_confirms() {
        const link_confirm_elements = this.el_container.querySelectorAll('.js-link-confirm');
        
        link_confirm_elements.forEach(element => {
            element.addEventListener('click', (event) => {
                const confirm_message = element.getAttribute('data-confirm');
                if (confirm_message) {
                    if (!confirm(confirm_message)) {
                        event.preventDefault();
                        return false;
                    }
                }
            });
        });
    }

    /**
     * Initialize pagination controls
     */
    initialize_pagination() {
        // Page navigation buttons
        const pagination_buttons = this.el_container.querySelectorAll('.js-pagination-click');
        pagination_buttons.forEach(element => {
            element.addEventListener('click', () => {
                this.paginationClick(element);
            });
        });

        // Page selection dropdowns
        const pagination_selects = this.el_container.querySelectorAll('.js-pagination-select');
        pagination_selects.forEach(element => {
            element.addEventListener('change', () => {
                this.paginationSelect(element);
            });
        });

        // Items per page selection
        const items_per_page_selects = this.el_container.querySelectorAll('.js-pagination-el-per-page');
        items_per_page_selects.forEach(element => {
            element.addEventListener('change', () => {
                this.paginationElPerPage(element);
            });
        });
    }

    /**
     * Execute custom initialization function if provided
     */
    execute_custom_initialization() {
        if (this.custom_init_fn && typeof this.custom_init_fn === 'function') {
            this.custom_init_fn(this);
        }
    }

    /**
     * Trigger initialization hook for external plugins
     */
    trigger_initialization_hook() {
        if (typeof callHook === 'function') {
            callHook('table-init', this);
        }
    }

    // ===========================================
    // PUBLIC API METHODS
    // ===========================================

    /**
     * Reload table data
     */
    reload() {
        this.sendForm();
    }

    /**
     * Get the current state
     * @returns {Object} Current state object
     */
    getState() {
        return { ...this.state };
    }

    /**
     * Get a specific state value
     * @param {string} key - State key
     * @returns {*} State value
     */
    getStateValue(key) {
        return this.state[key];
    }

    /**
     * Set a custom data value
     * @param {string} key - Custom data key
     * @param {*} value - Value to set
     */
    setCustomData(key, value) {
        this.state.custom[key] = value;
    }

    /**
     * Get a custom data value
     * @param {string} key - Custom data key
     * @returns {*} Custom data value
     */
    getCustomData(key) {
        return this.state.custom[key];
    }

    /**
     * Add a filter to the table
     * @param {Object} filter - Filter object to add
     */
    filter_add(filter) {
        let json_val = [];
        if (this.state.filters !== '') {
            try {
                json_val = JSON.parse(this.state.filters);
            } catch (e) {
                console.warn('Invalid filter JSON, resetting filters');
                json_val = [];
            }
        }
        
        json_val.push(filter);
        this.state.filters = JSON.stringify(json_val);
    }

    /**
     * Clear all filters
     */
    filter_clear() {
        this.state.filters = '';
    }

    /**
     * Remove a specific filter
     * @param {Object} filter - Filter object to remove
     */
    filter_remove(filter) {
        let json_val = [];
        if (this.state.filters !== '') {
            try {
                json_val = JSON.parse(this.state.filters);
            } catch (e) {
                console.warn('Invalid filter JSON');
                return;
            }
        }

        let index = json_val.indexOf(filter);
        if (index > -1) {
            json_val.splice(index, 1);
            this.state.filters = JSON.stringify(json_val);
        }
    }

    /**
     * Remove filters that start with a specific value
     * @param {string} filter - Prefix to match for removal
     */
    filter_remove_start(filter) {
        let json_val = [];
        if (this.state.filters !== '') {
            try {
                json_val = JSON.parse(this.state.filters);
            } catch (e) {
                console.warn('Invalid filter JSON');
                return;
            }
        }

        let new_json_val = json_val.filter((val) => {
            return !val.toString().startsWith(filter);
        });
        
        this.state.filters = JSON.stringify(new_json_val);
    }

    /**
     * Get current filters as array
     * @returns {Array} Current filters
     */
    filter_get() {
        if (this.state.filters === '') {
            return [];
        }
        try {
            return JSON.parse(this.state.filters);
        } catch (e) {
            return [];
        }
    }

    /**
     * Set the current page
     * @param {number} page - Page number (defaults to 1)
     */
    set_page(page = 1) {
        this.state.current_page = page;
    }

    /**
     * Get the current page
     * @returns {number} Current page number
     */
    get_page() {
        return this.state.current_page;
    }

    /**
     * Set items per page limit
     * @param {number} limit - Number of items per page
     */
    set_limit(limit) {
        this.state.limit = limit;
    }

    /**
     * Get items per page limit
     * @returns {number} Current limit
     */
    get_limit() {
        return this.state.limit;
    }

    /**
     * Set order field and direction
     * @param {string} field - Field name to order by
     * @param {string} dir - Direction ('asc' or 'desc')
     */
    set_order(field, dir = 'asc') {
        this.state.order_field = field;
        this.state.order_dir = dir;
    }

    // ===========================================
    // PRIVATE METHODS
    // ===========================================

    /**
     * Handle bulk action execution
     * @param {HTMLElement} el - The clicked bulk action element
     */
    tableBulkAction(el) {
        const action_val = el.getAttribute('data-table-action');
        
        if (!action_val) {
            console.warn('Bulk action configuration incomplete');
            return;
        }

        // Collect selected checkbox values
        const checkboxes = this.el_container.querySelectorAll('.js-col-checkbox:checked');
        const ids = [];
        Array.prototype.slice.call(checkboxes).forEach((checkbox) => {
            ids.push(checkbox.value);
        });

        let should_proceed = true;
        if (typeof callHook === 'function') {
            // Pass Table instance instead of form
            should_proceed = callHook(`table-action-${action_val}`, ids, el, this, true);
        }

        if (should_proceed) {
            this.state.table_action = action_val;
            this.state.table_ids = ids.join(',');
            this.sendForm();
        }
    }

    /**
     * Handle single row action execution
     * @param {HTMLElement} el - The clicked action element
     */
    tableSingleAction(el) {
        const action_val = el.getAttribute('data-table-action');
        const id_val = el.getAttribute('data-table-id');
        const confirm_message = el.getAttribute('data-confirm');
        
        if (!action_val || !id_val) {
            console.warn('Single action configuration incomplete');
            return;
        }

        // Se c'è un messaggio di conferma, mostralo
        if (confirm_message) {
            if (!confirm(confirm_message)) {
                return; // L'utente ha annullato
            }
        }

        // Check if hook allows action to proceed
        let should_proceed = true;
        if (typeof callHook === 'function') {
            // Pass Table instance instead of form
            should_proceed = callHook(`table-action-${action_val}`, id_val, el, this, true);
        }

        if (should_proceed) {
            this.state.table_action = action_val;
            this.state.table_ids = id_val;
            this.sendForm();
        }
    }

    /**
     * Toggle all checkboxes selection state
     */
    toggleAllCheckboxes(table) {
        const table_head_checkbox = table.querySelector('.js-click-all-checkbox');
        const checkboxes = table.querySelectorAll('.js-col-checkbox');

        if (!table_head_checkbox) return;

        Array.prototype.slice.call(checkboxes).forEach((checkbox) => {
            if (table_head_checkbox.checked == false) {
                checkbox.checked = false;
                checkbox.closest('.js-table-tr').classList.remove('js-selected');
            } else {
                checkbox.checked = true;
                checkbox.closest('.js-table-tr').classList.add('js-selected');
            }
        });
    }

    /**
     * Ricaclola il numero di righe selezionate per la riga bulk
     */
    recalculating() {
        const selected_checkboxes = this.el_container.querySelectorAll('.js-col-checkbox:checked');
        
        // Update selected count display
        const sel = this.el_container.querySelector('.js-count-selected');
        if (sel) sel.innerHTML = selected_checkboxes.length;

        // Show/hide bulk actions row
        const row = this.el_container.querySelector('.js-row-bulk-actions');
        if (row) {
            if (selected_checkboxes.length > 0) {
                row.classList.remove('invisible');
                row.classList.add('visible');
            } else {
                row.classList.remove('visible');
                row.classList.add('invisible');
            }
        }

        // Trigger selection change hook
        let idname = this.el_container.getAttribute('id');
        if (idname && typeof callHook === 'function') {
            const ids = [];
            Array.prototype.slice.call(selected_checkboxes).forEach((checkbox) => {
                ids.push(checkbox.value);
            });
            callHook(`${idname}-checkbox-change`, ids, this);
        }
    }

    /**
     * gestione dell'ordine delle colonne
     */
    tableChangeOrder(el) {
        const field_val = el.getAttribute('data-table-field');
        const order_val = el.getAttribute('data-table-dir');

        if (field_val && order_val) {
            this.state.order_field = field_val;
            this.state.order_dir = order_val;
            
            // Clear action fields for sorting
            this.clearActionFields();
            this.sendForm();
        }
    }

    /**
     * gestione della paginazione
     */
    paginationClick(el) {
        const page_val = el.getAttribute('data-table-page');

        if (page_val) {
            this.state.current_page = parseInt(page_val);
            this.clearActionFields();
            this.sendForm();
        }
    }

    /**
     * Handle pagination dropdown selection
     */
    paginationSelect(el) {
        const page_val = el[el.selectedIndex].value;

        this.state.current_page = parseInt(page_val);
        this.clearActionFields();
        this.sendForm();
    }

    /**
     * Handle items per page selection change
     */
    paginationElPerPage(el) {
        // Reset to first page when changing items per page
        this.state.current_page = 1;
        
        const limit_val = el[el.selectedIndex].value;
        this.state.limit = parseInt(limit_val);
        
        this.clearActionFields();
        this.sendForm();
    }

    /**
     * Clear action and ID fields
     */
    clearActionFields() {
        this.state.table_action = '';
        this.state.table_ids = '';
    }

    /**
     * Set action field
     */
    setActionFields(action) {
        this.state.table_action = action;
    }

    /**
     * Verifica se l'elemento è visibile nel viewport
     * Restituisce true se l'angolo in alto a sinistra dell'elemento è visibile verticalmente
     */
    isElementTopVisible(element) {
        if (!element) return false;

        const rect = element.getBoundingClientRect();
        const window_height = window.innerHeight || document.documentElement.clientHeight;
        
        // Verifica se l'angolo in alto a sinistra è visibile verticalmente
        return rect.top >= 0 && rect.top < window_height;
    }

    /**
     * Get the built FormData (for external use if needed)
     * @returns {FormData}
     */
    getFormData() {
        return this.buildFormData();
    }

    /**
     * Update container data attributes with current state
     * This ensures state persists across DOM updates
     * @param {Object} state - State object to apply
     */
    updateContainerAttributes(state) {
        this.el_container.setAttribute('data-current-page', state.current_page);
        this.el_container.setAttribute('data-limit', state.limit);
        this.el_container.setAttribute('data-order-field', state.order_field);
        this.el_container.setAttribute('data-order-dir', state.order_dir);
        this.el_container.setAttribute('data-filters', state.filters);

        if (state.custom && Object.keys(state.custom).length > 0) {
            this.el_container.setAttribute('data-custom', JSON.stringify(state.custom));
        }
    }

    /**
     * Reinitialize the table without creating a new instance
     * Resets initialization flag and re-runs all initialization methods
     */
    reinitialize() {
        // Reset initialization flag
        this.is_init = false;

        // Re-read state from updated attributes
        this.initStateFromAttributes();

        // Reinitialize loading plugin if needed
        const loading_element = this.el_container.querySelector('.js-ito-loading');
        if (loading_element) {
            this.plugin_loading = new Loading(loading_element);
        }

        // Run initialization
        this.init();
    }

    /**
     * invio del form tramite fetch
     */
    async sendForm() {
        // Show loading indicator
        if (this.plugin_loading) {
            this.plugin_loading.show();
        }

        try {
            const form_data = this.buildFormData();
            const response = await fetch(this.state.action_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: form_data
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            if (!('success' in data)) {
                data.success = true;
            }
            const message_type = data.success ? 'success' : 'danger';

            if (data.success && data.offcanvas_end && typeof window.offcanvasEnd !== 'undefined' && data.offcanvas_end.title && data.offcanvas_end.body) {
                window.offcanvasEnd.show(data.offcanvas_end.title, data.offcanvas_end.body);
                if (data.offcanvas_end.size) window.offcanvasEnd.size(data.offcanvas_end.size);
            }
            if (data.success && data.modal && typeof window.modal !== 'undefined' && data.modal.title && data.modal.body) {
                window.modal.show(data.modal.title, data.modal.body, data.modal.footer);
            }

            // Update table content
            if ('html' in data && data.html != '') {
                // Save current state before updating DOM
                const saved_state = { ...this.state };

                this.el_container.innerHTML = data.html;
                updateContainer(this.el_container);

                // Update data attributes to reflect current state
                this.updateContainerAttributes(saved_state);

                // Reinitialize with saved state
                this.reinitialize();
            } else {
                if (this.plugin_loading) {
                    this.plugin_loading.hide();
                }
            }

            // Show success/error message if provided
            /*
            if (data.msg && data.msg !== '' && typeof window.toasts !== 'undefined') {
                window.toasts.show(data.msg, message_type);
            }
            */

            // Auto-scroll to table if not disabled and not already visible
            if (!this.el_container.hasAttribute('data-no-auto-scroll')) {
                if (!this.isElementTopVisible(this.el_scroll)) {
                    this.el_scroll.scrollIntoView({ behavior: "smooth" });
                }
            }

            jsonAction(data);


        } catch (error) {
            console.error('Table form submission failed:', error);

            // Hide loading indicator on error
            if (this.plugin_loading) {
                this.plugin_loading.hide();
            }

            // Show error message if toast system is available
            if (typeof window.toasts !== 'undefined') {
                window.toasts.show('An error occurred while updating the table', 'danger');
            }
        }
    }
}

// Auto-initialize all tables when DOM is loaded
window.addEventListener('load', function() {
    document.querySelectorAll('.js-table-container').forEach((el) => {
        new Table(el);
    });
});

// document.dispatchEvent(new CustomEvent('updateContainer', { detail: { el: el } })
document.addEventListener('updateContainer', function(event) {
    event.detail.el.querySelectorAll('.js-table-container').forEach((el) => {
        new Table(el);
    });
});