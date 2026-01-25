/**
 * ScheduleGrid Component with AJAX updates and click handling
 *
 * Features:
 * 1. AJAX navigation (prev/next, period selectors)
 * 2. Click handling for events and empty cells
 * 3. Placeholder substitution in URLs (%id%, %row_id%, %col_id%, %date%, %time%)
 * 4. Support for dynamic content loading
 */
class ScheduleGrid {
    el_container = null;
    component_name = 'ScheduleGrid';
    is_init = false;
    plugin_loading = null;
    filters = '';

    constructor(el_container) {
        if (!el_container) {
            throw new Error('ScheduleGrid component requires a valid container element');
        }

        this.el_container = el_container;
        this.initFiltersFromForm();

        this.init();
        this.is_init = true;

        // Store component reference on DOM element
        this.el_container.__itoComponent = this;

        // Initialize loading plugin if exists
        const loading_element = this.el_container.querySelector('.js-ito-loading');
        if (loading_element) {
            this.plugin_loading = new Loading(loading_element);
        }
    }

    /**
     * Initialize all schedule grid functionality
     */
    init() {
        if (this.is_init) {
            return;
        }

        this.bind_events();
    }

    /**
     * Bind all event handlers to schedule grid elements
     * Can be called multiple times after AJAX updates
     */
    bind_events() {
        this.initialize_navigation();
        this.initialize_period_selectors();
        this.initialize_cell_clicks();
    }

    /**
     * Initialize click handlers using existing data-fetch system
     */
    initialize_cell_clicks() {
        // Use existing initFetchDiv function for cell clicks
        if (typeof initFetchDiv === 'function') {
            initFetchDiv(this.el_container);
        }
    }

    initFiltersFromForm() {
        const filters_field = this.el_container.querySelector('.js-field-schedulegrid-filters');
        this.filters = filters_field ? (filters_field.value || '') : '';
    }

    syncFiltersField() {
        const filters_field = this.el_container.querySelector('.js-field-schedulegrid-filters');
        if (filters_field) {
            filters_field.value = this.filters;
        }
    }

    /**
     * Initialize navigation buttons (prev/next/today)
     */
    initialize_navigation() {
        const prev_button = this.el_container.querySelector('.js-schedulegrid-prev');
        const next_button = this.el_container.querySelector('.js-schedulegrid-next');
        const today_button = this.el_container.querySelector('.js-schedulegrid-today');

        if (prev_button) {
            prev_button.addEventListener('click', () => {
                this.update_grid();
            });
        }

        if (next_button) {
            next_button.addEventListener('click', () => {
                this.update_grid();
            });
        }

        if (today_button) {
            today_button.addEventListener('click', () => {
                this.update_grid();
            });
        }
    }

    /**
     * Initialize period type, month, year, week selectors
     */
    initialize_period_selectors() {
        const period_select = this.el_container.querySelector('.js-schedulegrid-period-select');
        const month_select = this.el_container.querySelector('.js-schedulegrid-month-select');
        const year_select = this.el_container.querySelector('.js-schedulegrid-year-select');
        const week_select = this.el_container.querySelector('.js-schedulegrid-week-select');

        if (period_select) {
            period_select.addEventListener('change', () => {
                this.update_grid();
            });
        }

        if (month_select) {
            month_select.addEventListener('change', () => {
                this.update_grid();
            });
        }

        if (year_select) {
            year_select.addEventListener('change', () => {
                this.update_grid();
            });
        }

        if (week_select) {
            week_select.addEventListener('change', () => {
                this.update_grid();
            });
        }
    }

    reload() {
        this.update_grid();
    }

    /**
     * Update schedule grid via AJAX
     */
    async update_grid() {
        const form = this.el_container.querySelector('.js-schedulegrid-form');
        if (!form) {
            console.error('ScheduleGrid form not found');
            return;
        }

        // Show loading indicator
        if (this.plugin_loading) {
            this.plugin_loading.show();
        }

        try {
            const form_data = new FormData(form);

            // Sync filters
            this.syncFiltersField();

            // Add AJAX header
            const response = await fetch(form.getAttribute('action'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: form_data
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            // Update grid content
            if ('html' in data && data.html != '') {
                // Create temp div to parse HTML response
                const temp_div = document.createElement('div');
                temp_div.innerHTML = data.html;
                const new_grid = temp_div.querySelector('.js-schedulegrid-container');

                if (new_grid) {
                    const new_filters_field = new_grid.querySelector('.js-field-schedulegrid-filters');
                    if (new_filters_field) {
                        new_filters_field.value = this.filters;
                    }

                    // Replace entire container with new one
                    this.el_container.replaceWith(new_grid);

                    // Reinitialize component with new DOM element
                    new ScheduleGrid(new_grid);
                } else {
                    console.error('No .js-schedulegrid-container found in response');
                    if (this.plugin_loading) {
                        this.plugin_loading.hide();
                    }
                }
            } else {
                if (this.plugin_loading) {
                    this.plugin_loading.hide();
                }
            }

            // Show success/error message if provided
            if (data.msg && data.msg !== '' && typeof window.toasts !== 'undefined') {
                const message_type = data.success ? 'success' : 'danger';
                window.toasts.show(data.msg, message_type);
            }

        } catch (error) {
            console.error('ScheduleGrid update failed:', error);

            // Hide loading indicator on error
            if (this.plugin_loading) {
                this.plugin_loading.hide();
            }

            // Show error message if toast system is available
            if (typeof window.toasts !== 'undefined') {
                window.toasts.show('An error occurred while updating the schedule grid', 'danger');
            }
        }
    }

    /**
     * Public API methods for filtering
     */
    filter_add(filter) {
        const filters = this.getFiltersArray();
        filters.push(filter);
        this.setFiltersArray(filters);
    }

    filter_clear() {
        this.setFiltersArray([]);
    }

    filter_remove(filter) {
        const filters = this.getFiltersArray();
        const index = filters.indexOf(filter);
        if (index > -1) {
            filters.splice(index, 1);
            this.setFiltersArray(filters);
        }
    }

    filter_remove_start(filter) {
        const filters = this.getFiltersArray();
        const new_filters = filters.filter((val) => {
            return !val.toString().startsWith(filter);
        });
        this.setFiltersArray(new_filters);
    }

    filter_get() {
        return this.getFiltersArray();
    }

    getFiltersArray() {
        if (this.filters === '') {
            return [];
        }
        try {
            return JSON.parse(this.filters);
        } catch (e) {
            console.warn('Invalid filter JSON, resetting filters');
            return [];
        }
    }

    setFiltersArray(filters) {
        if (!Array.isArray(filters) || filters.length === 0) {
            this.filters = '';
        } else {
            this.filters = JSON.stringify(filters);
        }
        this.syncFiltersField();
    }
}

// Auto-initialize all schedule grids when DOM is loaded
window.addEventListener('load', function() {
    document.querySelectorAll('.js-schedulegrid-container').forEach((el) => {
        new ScheduleGrid(el);
    });
});

// Support for dynamic content loading
document.addEventListener('updateContainer', function(event) {
    event.detail.el.querySelectorAll('.js-schedulegrid-container').forEach((el) => {
        new ScheduleGrid(el);
    });
});
