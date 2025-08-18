/** 
 * FILTERS Help to add filters to tables
 * 
 * add Class and data attributes to the element you want to use as filter
 * 
 * Choose one of the following classes:
 * js-milk-filter-onchange Filter executed on change
 * js-milk-filter Filter non execute. You can use it to add custom filters and send filter data to the server in another way (onclick)
 * js-milk-filter-onclick Filter executed on click
 * 
 * Data attributes:
 * data-filter-id (required) The ID of the table to filter
 * data-filter-type (required for js-milk-filter-onchange or js-milk-filter-onclick) The type of the filter
 *
 * @example
 * ```
 * <input type="text" class="js-milk-filter" data-filter-id="table_file_logs" data-filter-type="search">
 * <div class="btn btn-primary js-milk-filter-onclick" data-filter-id="table_file_logs">Search</div>
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
                var component = build_filter_helper(this);
                component.set_page(1);
                component.reload(); 
            });
        } else {
            filter.addEventListener('input', function() {
                var component = build_filter_helper(this);
                component.set_page(1);
                component.reload(); 
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
            var table = getComponent(table_id);
            table.set_page(1);
            table.reload();
        });
    }
}   

function build_filter_helper(el) {
    var table_id = el.getAttribute('data-filter-id');
    var filter_type = el.getAttribute('data-filter-type');
    if (table_id == null || filter_type == null) {
        console.warn('Missing data-filter-id or data-filter-type');
        return;
    }
    var component = getComponent(table_id);
    component.filter_remove_start(filter_type + ':');
    var value = el.value;
    component.filter_add(filter_type + ':' + value);
    return component;
}

window.addEventListener('load', function() {
    build_filters();
});