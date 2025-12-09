/**
 * Calendar Component con aggiornamento AJAX
 *
 * Funziona come Table.js:
 * 1. Intercetta i cambiamenti dei select mese/anno
 * 2. Aggiorna i campi nascosti del form
 * 3. Invia il form via AJAX
 * 4. Sostituisce l'HTML del calendario con la risposta
 */
class Calendar {
   el_container = null;
    component_name = 'Calendar';
    is_init = false;
    plugin_loading = null;

    constructor(el_container) {
        if (!el_container) {
            throw new Error('Calendar component requires a valid container element');
        }

        console.log('Initializing Calendar component');
        this.el_container = el_container;

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
     * Initialize all calendar functionality
     */
    init() {
        if (this.is_init) {
            return;
        }

        this.bind_events();
    }

    /**
     * Bind all event handlers to calendar elements
     * Can be called multiple times after AJAX updates
     */
    bind_events() {
        this.initialize_month_select();
        this.initialize_year_select();
        this.initialize_navigation_buttons();
        this.initialize_week_select();
        this.initialize_appointment_clicks();
    }

    /**
     * Initialize click handlers for appointments with data-fetch
     */
    initialize_appointment_clicks() {
        initFetchLinks(this.el_container);
        initFetchDiv(this.el_container);
    }

    /**
     * Initialize month select change handler
     */
    initialize_month_select() {
        const month_select = this.el_container.querySelector('.js-calendar-month-select');

        if (month_select) {
            month_select.addEventListener('change', () => {
                this.update_calendar();
            });
        }
    }

    /**
     * Initialize year select change handler
     */
    initialize_year_select() {
        const year_select = this.el_container.querySelector('.js-calendar-year-select');

        if (year_select) {
            year_select.addEventListener('change', () => {
                this.update_calendar();
            });
        }
    }

    /**
     * Initialize navigation buttons (prev/next month/today)
     */
    initialize_navigation_buttons() {
        const prev_button = this.el_container.querySelector('.js-calendar-prev');
        const next_button = this.el_container.querySelector('.js-calendar-next');
        const today_button = this.el_container.querySelector('.js-calendar-today');
        
        // Detecta se è vista settimanale
        const isWeekly = this.el_container.querySelector('.weekly-view') !== null;
        
        if (prev_button) {
            prev_button.addEventListener('click', () => {
                if (isWeekly) {
                    this.navigate_week(-1);
                } else {
                    const month = prev_button.getAttribute('data-month');
                    const year = prev_button.getAttribute('data-year');
                    this.set_month_year(month, year);
                    this.update_calendar();
                }
            });
        }
        
        if (next_button) {
            next_button.addEventListener('click', () => {
                if (isWeekly) {
                    this.navigate_week(1);
                } else {
                    const month = next_button.getAttribute('data-month');
                    const year = next_button.getAttribute('data-year');
                    this.set_month_year(month, year);
                    this.update_calendar();
                }
            });
        }

        if (today_button) {
            today_button.addEventListener('click', () => {
                const month = today_button.getAttribute('data-month');
                const year = today_button.getAttribute('data-year');
                this.set_month_year(month, year);
                this.update_calendar();
            });
        }
    }

    navigate_week(direction) {
        const week_select = this.el_container.querySelector('.js-calendar-week-select');
        if (!week_select) return;
        
        const current_week = parseInt(week_select.value);
        const new_week = current_week + direction;
        
        // Se fuori range, cambia mese
        if (new_week < 1) {
            const month_select = this.el_container.querySelector('.js-calendar-month-value');
            const year_select = this.el_container.querySelector('.js-calendar-year-select');
            
            let month = parseInt(month_select.value) - 1;
            let year = parseInt(year_select.value);
            
            if (month < 1) {
                month = 12;
                year--;
            }
            
            this.set_month_year(month, year);
            // Imposta ultima settimana del mese precedente
            week_select.value = 4; // Approssimazione
        } else if (new_week > week_select.options.length) {
            // Vai al mese successivo
            const month_select = this.el_container.querySelector('.js-calendar-month-value');
            const year_select = this.el_container.querySelector('.js-calendar-year-select');
            
            let month = parseInt(month_select.value) + 1;
            let year = parseInt(year_select.value);
            
            if (month > 12) {
                month = 1;
                year++;
            }
            
            this.set_month_year(month, year);
            week_select.value = 1;
        } else {
            week_select.value = new_week;
        }
        
        this.update_calendar();
    }

    // Aggiungere inizializzazione week select
    initialize_week_select() {
        const week_select = this.el_container.querySelector('.js-calendar-week-select');
        
        if (week_select) {
            week_select.addEventListener('change', () => {
                this.update_calendar();
            });
        }
    }

    /**
     * Set month and year values in hidden fields and select elements
     */
    set_month_year(month, year) {
        const isWeekly = this.el_container.querySelector('.weekly-view') !== null;
        
        // Update hidden fields
        const month_field = this.el_container.querySelector('.js-field-calendar-month');
        const year_field = this.el_container.querySelector('.js-field-calendar-year');

        if (month_field) month_field.value = month;
        if (year_field) year_field.value = year;

        // Update select elements
        if (isWeekly) {
            const month_value = this.el_container.querySelector('.js-calendar-month-value');
            const year_select = this.el_container.querySelector('.js-calendar-year-select');
            
            if (month_value) month_value.value = month;
            if (year_select) year_select.value = year;
        } else {
            const month_select = this.el_container.querySelector('.js-calendar-month-select');
            const year_select = this.el_container.querySelector('.js-calendar-year-select');
            
            if (month_select) month_select.value = month;
            if (year_select) year_select.value = year;
        }
    }

    
    reload() {
        this.update_calendar();
    }

    /**
     * Update calendar via AJAX
     * Similar to Table.sendForm()
     */
    async update_calendar() {
        const form = this.el_container.querySelector('.js-calendar-form');
        if (!form) {
            console.error('Calendar form not found');
            return;
        }
        
        // Detecta se è vista settimanale
        const isWeekly = this.el_container.querySelector('.weekly-view') !== null;
        
        // Get current values from select elements
        let month_select, year_select, week_select;
        
        if (isWeekly) {
            // Vista settimanale: usa hidden field per mese e select per settimana
            month_select = this.el_container.querySelector('.js-calendar-month-value');
            week_select = this.el_container.querySelector('.js-calendar-week-select');
            year_select = this.el_container.querySelector('.js-calendar-year-select');
            
            if (!month_select || !year_select || !week_select) {
                console.error('Calendar weekly select elements not found');
                return;
            }
        } else {
            // Vista mensile: usa select per mese
            month_select = this.el_container.querySelector('.js-calendar-month-select');
            year_select = this.el_container.querySelector('.js-calendar-year-select');
            
            if (!month_select || !year_select) {
                console.error('Calendar select elements not found');
                return;
            }
        }

        // Update hidden fields with current select values
        const month_field = this.el_container.querySelector('.js-field-calendar-month');
        const year_field = this.el_container.querySelector('.js-field-calendar-year');
        const type_field = this.el_container.querySelector('.js-field-calendar-type');
        const filters_field = this.el_container.querySelector('.js-field-calendar-filters');

        if (month_field) month_field.value = month_select.value;
        if (year_field) year_field.value = year_select.value;

        // Update type field with current view type
        if (type_field) {
            type_field.value = isWeekly ? 'weekly' : 'monthly';
        }

        // Per vista settimanale, aggiungi week_number al form
        if (isWeekly && week_select) {
            let week_field = form.querySelector('input[name$="[week]"]');
            if (!week_field) {
                week_field = document.createElement('input');
                week_field.type = 'hidden';
                week_field.name = form.querySelector('input[name$="[month]"]').name.replace('[month]', '[week]');
                form.appendChild(week_field);
            }
            week_field.value = week_select.value;
        }
        // Show loading indicator
        if (this.plugin_loading) {
            this.plugin_loading.show();
        }

        try {
            const form_data = new FormData(form);

            // Add special header to indicate AJAX request
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

            // Update calendar content
            if ('html' in data && data.html != '') {
                // Create temp div to parse HTML response
                const temp_div = document.createElement('div');
                temp_div.innerHTML = data.html;
                const new_calendar = temp_div.querySelector('.js-calendar-container');

                if (new_calendar) {
                    // Replace entire container with new one
                    this.el_container.replaceWith(new_calendar);

                    // Reinitialize calendar instance with new DOM element
                    new Calendar(new_calendar);

                    // Reinitialize fetch links for empty date clicks (day numbers)
                    if (typeof initFetchLinks === 'function') {
                        initFetchLinks(new_calendar);
                    }
                } else {
                    console.error('No .js-calendar-container found in response');
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
            console.error('Calendar update failed:', error);

            // Hide loading indicator on error
            if (this.plugin_loading) {
                this.plugin_loading.hide();
            }

            // Show error message if toast system is available
            if (typeof window.toasts !== 'undefined') {
                window.toasts.show('An error occurred while updating the calendar', 'danger');
            }
        }
    }

    /**
     * Public API methods
     */

    // Get current month
    get_month() {
        const month_field = this.el_container.querySelector('.js-field-calendar-month');
        return month_field ? parseInt(month_field.value) : null;
    }

    // Get current year
    get_year() {
        const year_field = this.el_container.querySelector('.js-field-calendar-year');
        return year_field ? parseInt(year_field.value) : null;
    }

    // Navigate to specific month/year
    navigate_to(month, year) {
        this.set_month_year(month, year);
        this.update_calendar();
    }

    // Navigate to today
    navigate_to_today() {
        const today = new Date();
        const month = today.getMonth() + 1; // JavaScript months are 0-indexed
        const year = today.getFullYear();
        this.navigate_to(month, year);
    }
}

// Auto-initialize all calendars when DOM is loaded
window.addEventListener('load', function() {
    document.querySelectorAll('.js-calendar-container').forEach((el) => {
        new Calendar(el);
        // Initialize fetch links for empty date clicks (day numbers)
        if (typeof initFetchLinks === 'function') {
            initFetchLinks(el);
        }
    });
});

// Support for dynamic content loading
document.addEventListener('updateContainer', function(event) {
    event.detail.el.querySelectorAll('.js-calendar-container').forEach((el) => {
        new Calendar(el);
        // Initialize fetch links for empty date clicks (day numbers)
        if (typeof initFetchLinks === 'function') {
            initFetchLinks(el);
        }
    });
});
