/** 
 * FILTERS Help to add filters to tables
 * 
 * add Class and data attributes to the element you want to use as filter
 * 
 * Choose one of the following classes:
 * js-milk-filter-onchange Filter executed on change
 * js-milk-filter Filter non execute. You can use it to add custom filters and send filter data to the server in another way (onclick)
 * js-milk-filter-onclick Filter executed on click
 * js-milk-filter-clear Clear all filters and reload table
 * 
 * Data attributes:
 * data-filter-id (required) The ID of the table to filter (comma-separated list supported)
 * data-filter-type (required for js-milk-filter-onchange or js-milk-filter-onclick) The type of the filter
 *
 * @example
 * ```
 * <input type="text" class="js-milk-filter" data-filter-id="table_file_logs" data-filter-type="search">
 * <div class="btn btn-primary js-milk-filter-onclick" data-filter-id="table_file_logs">Search</div>
 * <div class="btn btn-secondary js-milk-filter-clear" data-filter-id="table_file_logs">Clear</div>
 * ```
 * 
 * @since version 1.2
 */

function build_filters() {
    var filters = document.querySelectorAll('.js-milk-filter-onchange');
    for (var i = 0; i < filters.length; i++) {
        var filter = filters[i];
        if (filter._milk_filter_active) {
            continue;
        }
        filter._milk_filter_active = true;
        // se è un select o un checkbox o un radio uso change altrimenti uso input
        if (filter.tagName == 'SELECT' || filter.tagName == 'INPUT' && (filter.type == 'checkbox' || filter.type == 'radio')) {
            filter.addEventListener('change', function() {
                var components = build_filter_helper(this);
                if (!components.length) {
                    console.warn('Component not found!');
                } else {
                    reload_components(components, true);
                }
            });
        } else {
            filter.addEventListener('input', function() {
                var components = build_filter_helper(this);
                if (!components.length) {
                    console.warn('Component not found!');
                } else {
                    reload_components(components, true);
                }
            });
        }
    }
    var filters = document.querySelectorAll('.js-milk-filter');
    for (var i = 0; i < filters.length; i++) {
        var filter = filters[i];
        if (filter._milk_filter_active) {
            continue;
        }
        filter._milk_filter_active = true;
        filter.addEventListener('change', function() {
            build_filter_helper(this);
        });
    }
    var filters = document.querySelectorAll('.js-milk-filter-onclick');
    for (var i = 0; i < filters.length; i++) {
        var filter = filters[i];
        if (filter._milk_filter_active) {
            continue;
        }
        filter._milk_filter_active = true;
        filter.addEventListener('click', function() {
            var table_id = this.getAttribute('data-filter-id');
            if (table_id == null) {
                console.warn('Missing data-filter-id');
                return;
            }
            var table_ids = normalize_filter_ids(table_id);
            if (!table_ids.length) {
                console.warn('Table component not found: ' + table_id);
                return;
            }
            var components = table_ids.map(function(id) {
                return getComponent(id);
            }).filter(Boolean);
            if (!components.length) {
                console.warn('Table component not found: ' + table_id);
                return;
            }
            reload_components(components, true);
        });
    }
    
    // Gestione pulsanti Clear
    var clear_buttons = document.querySelectorAll('.js-milk-filter-clear');
    for (var i = 0; i < clear_buttons.length; i++) {
        var clear_button = clear_buttons[i];
        if (clear_button._milk_clear_active) {
            continue;
        }
        clear_button._milk_clear_active = true;
        clear_button.addEventListener('click', function() {
            var table_id = this.getAttribute('data-filter-id');
            if (table_id == null) {
                console.warn('Missing data-filter-id for clear button');
                return;
            }
            
            // Pulisce tutti i campi filtro correlati a questa tabella
            clear_all_filters(table_id);
            
            // Ricarica la tabella
            var table_ids = normalize_filter_ids(table_id);
            if (!table_ids.length) {
                console.warn('Table component not found: ' + table_id);
                return;
            }
            table_ids.forEach(function(id) {
                var table = getComponent(id);
                if (typeof table === 'undefined' || table == null) {
                    console.warn('Table component not found: ' + id);
                    return;
                }
                if (typeof table.filter_clear === 'function') {
                    table.filter_clear();
                }
                if (typeof table.set_page === 'function') {
                    table.set_page(1);
                }
                if (typeof table.reload === 'function') {
                    table.reload();
                }
            });
        });
    }
}   

function build_filter_helper(el) {
    var table_id = el.getAttribute('data-filter-id');
    var filter_type = el.getAttribute('data-filter-type');
    if (table_id == null || filter_type == null) {
        console.warn('Missing data-filter-id or data-filter-type');
        return [];
    }
    var components = [];
    var table_ids = normalize_filter_ids(table_id);
    if (!table_ids.length) {
        console.warn('Table component not found: ' + table_id);
        return components;
    }
    table_ids.forEach(function(id) {
        var component = getComponent(id);
        if (typeof component === 'undefined' || component == null) {
            console.warn('Table component not found: ' + id);
            return;
        }
        if (typeof component.filter_remove_start === 'function' && typeof component.filter_add === 'function') {
            component.filter_remove_start(filter_type + ':');
            var value = el.value;
            component.filter_add(filter_type + ':' + value);
        }
        components.push(component);
    });
    return components;
}

/**
 * Pulisce tutti i campi filtro associati a una specifica tabella
 * @param {string} table_id - L'ID della tabella
 */
function clear_all_filters(table_id) {
    // Selettori per tutti i tipi di campi filtro
    var selectors = [
        '.js-milk-filter[data-filter-id="' + table_id + '"]',
        '.js-milk-filter-onchange[data-filter-id="' + table_id + '"]'
    ];
    
    selectors.forEach(function(selector) {
        var elements = document.querySelectorAll(selector);
        
        elements.forEach(function(element) {
            // Gestisce diversi tipi di input
            switch (element.tagName.toLowerCase()) {
                case 'input':
                    if (element.type === 'checkbox' || element.type === 'radio') {
                        element.checked = false;
                    } else {
                        element.value = '';
                    }
                    break;
                    
                case 'select':
                    // Reset select al primo valore (solitamente vuoto)
                    element.selectedIndex = 0;
                    break;
                    
                case 'textarea':
                    element.value = '';
                    break;
                    
                default:
                    // Per altri elementi, prova a pulire il value
                    if (element.value !== undefined) {
                        element.value = '';
                    }
                    break;
            }
            
            // Gestione speciale per action lists (pulsanti filtro)
            if (element.type === 'hidden') {
                // Cerca il contenitore action-list associato
                var action_list_container = element.parentNode.querySelector('.js-action-list');
                if (action_list_container) {
                    // Rimuove la classe active da tutti gli elementi
                    var action_items = action_list_container.querySelectorAll('.js-action-item');
                    action_items.forEach(function(item) {
                        item.classList.remove('active-action-list');
                    });
                    
                    // Attiva il primo elemento (solitamente "All" o vuoto)
                    if (action_items.length > 0) {
                        action_items[0].classList.add('active-action-list');
                        element.value = action_items[0].getAttribute('data-value') || '';
                    }
                }
            }
            
            // Trigger change event per assicurarsi che eventuali listener vengano notificati
            var event = new Event('change', { bubbles: true });
            element.dispatchEvent(event);
        });
    });
}

function normalize_filter_ids(raw_value) {
    if (!raw_value) {
        return [];
    }
    var value = raw_value.toString().trim();
    if (value === '') {
        return [];
    }
    if (value[0] === '[') {
        try {
            var parsed = JSON.parse(value);
            if (Array.isArray(parsed)) {
                return parsed.map(function(id) {
                    return id.toString().trim();
                }).filter(Boolean);
            }
        } catch (e) {
            // Fallback to comma parsing.
        }
    }
    return value.split(',').map(function(id) {
        return id.trim();
    }).filter(Boolean);
}

function reload_components(components, reset_page) {
    if (components.length > 1) {
        mark_skip_auto_scroll(components);
    }
    components.forEach(function(component) {
        if (!component) {
            return;
        }
        if (reset_page && typeof component.set_page === 'function') {
            component.set_page(1);
        }
        if (typeof component.reload === 'function') {
            component.reload();
        }
    });
}

function mark_skip_auto_scroll(components) {
    components.forEach(function(component) {
        component.skip_auto_scroll_once = true;
    });
}

window.addEventListener('load', function() {
    build_filters();
});
document.addEventListener('updateContainer', function(event) {
    build_filters();
});
