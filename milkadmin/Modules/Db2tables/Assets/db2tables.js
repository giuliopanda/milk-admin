/**
 * DB2 Tables Explorer JavaScript
 */
// Function to toggle sidebar visibility
function toggleSidebar() {
    const sidebar = eI('#db2tSidebar');
    const content = eI('#db2tContent');
    const isVisible = !sidebar.classList.contains('d-none');
    if (isVisible) {
        elHide(sidebar, () => {
          content.classList.remove('col-md-8', 'col-lg-9');
           content.classList.add('col-12');
        });
    } else {
        content.classList.remove('col-12');
        content.classList.add('col-md-8');
        content.classList.add('col-lg-9');
        elShow(sidebar);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Handle tab URL functionality
    const tableTabs = document.getElementById('tableTabs');
    if (tableTabs) {
        // Update URL hash when tab is clicked
        tableTabs.addEventListener('shown.bs.tab', function(event) {
            const targetId = event.target.getAttribute('data-bs-target').substring(1);
            history.replaceState(null, null, '#' + targetId);
            const structureTab = document.getElementById('structure');
              
            // Load structure content if needed
            if (targetId === 'structure') {
                if (structureTab) {
                    structureTab.style.display = 'block';
                }
                loadStructureContent();
            } else {
                // Make sure to properly hide the structure tab content when switching to other tabs
                  if (structureTab) {
                    structureTab.style.display = 'none';
                }
            }
        });

        // Activate correct tab on page load if hash exists
        const hash = window.location.hash.substring(1);
        if (hash) {
            const tab = tableTabs.querySelector(`[data-bs-target="#${hash}"]`);
            if (tab) {
                const bsTab = new bootstrap.Tab(tab);
                bsTab.show();
            }
        }

        // Prevent default scroll behavior when clicking tabs
        tableTabs.addEventListener('click', function(event) {
            if (event.target.getAttribute('data-bs-toggle') === 'tab') {
                event.preventDefault();
            }
        });
    }


    // Sidebar table search functionality
    const searchInput = eI('#tableSearchInput');
    const clearSearchBtn = eI('#clearSearchBtn');
    const tablesList = eI('#tablesList');
    
    if (searchInput && tablesList) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            filterTables(searchTerm);
        });
        
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                filterTables('');
            });
        }
    }
    
    // Table search functionality
    const tableSearchInput = document.getElementById('searchTable');
    if (tableSearchInput) {
        // Enter key event already handled in the table_view.page.php file
        
        // Focus the search input when the page loads if it exists
        setTimeout(() => {
            if (document.querySelector('.card-header .form-control')) {
                document.querySelector('.card-header .form-control').focus();
            }
        }, 500);
    }
    
    // Structure toggle functionality
    const structureToggle = document.querySelector('[data-bs-target="#structureCollapse"]');
    if (structureToggle) {
        structureToggle.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (icon) {
                if (icon.classList.contains('bi-arrows-collapse')) {
                    icon.classList.remove('bi-arrows-collapse');
                    icon.classList.add('bi-arrows-expand');
                } else {
                    icon.classList.remove('bi-arrows-expand');
                    icon.classList.add('bi-arrows-collapse');
                }
            }
        });
    }
    
    /**
     * Filter tables in the sidebar based on search term
     */
    function filterTables(searchTerm) {
        if (!tablesList) return;
        
        const tableItems = tablesList.querySelectorAll('.sidebar-menu-item');
        const sidebarMenus = tablesList.querySelectorAll('.sidebar-menu');
        
        // Filter table items
        tableItems.forEach(item => {
            const link = item.querySelector('a');
            if (!link) return;
            
            const tableName = link.textContent.trim();
            const isVisible = tableName.toLowerCase().includes(searchTerm);
            
            item.style.display = isVisible ? '' : 'none';
        });
        
        // Show/hide menu sections based on whether they have visible items
        sidebarMenus.forEach(menu => {
            const hasVisibleItems = Array.from(menu.querySelectorAll('.sidebar-menu-item')).some(
                item => item.style.display !== 'none'
            );
            menu.style.display = hasVisibleItems ? '' : 'none';
        });
    }
    
    /**
     * Find the previous section header for a table item
     */
    function findPreviousSectionHeader(element) {
        let current = element.previousElementSibling;
        while (current) {
            if (current.classList.contains('section-header')) {
                return current;
            }
            current = current.previousElementSibling;
        }
        return null;
    }
});

// Function to load structure tab content
function loadStructureContent() {
    if (typeof loadStructureTab === 'function') {
        loadStructureTab();
    }
}

/** 
 * Table view page actions
 */

 // Field list click handling
 document.querySelectorAll('.list-group-item').forEach(field => {
    field.addEventListener('click', function() {
        // Remove active class from all fields
        document.querySelectorAll('.list-group-item').forEach(f => f.classList.remove('active'));
        this.classList.add('active');
        
        // Get field name and table name
        const fieldName = this.getAttribute('data-fieldname');
        const tableName = new URLSearchParams(window.location.search).get('table');
        
        // Fetch field details
        fetch(`?page=db2tables&action=get_field_details&table=${encodeURIComponent(tableName)}&field=${encodeURIComponent(fieldName)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const fieldData = data.data;
                    const stats = data.stats || {};
                    
                    // Update basic field info
                    document.getElementById('field-type').textContent = fieldData.Type || '-';
                    document.getElementById('field-null').textContent = fieldData.Null || '-';
                    document.getElementById('field-key').textContent = fieldData.Key || '-';
                    document.getElementById('field-default').textContent = fieldData.Default || 'NULL';
                    document.getElementById('field-extra').textContent = fieldData.Extra || '-';

                    // Show statistics section
                    const statsSection = document.getElementById('field-stats');
                    const fieldSummary = document.getElementById('field-summary');
                    const distributionSection = document.getElementById('distribution-stats');
                    const topValuesSection = document.getElementById('top-values-stats');
                    const numericSection = document.getElementById('numeric-stats');
                    
                    // Reset visibility and search
                    statsSection.style.display = 'none';
                    fieldSummary.style.display = 'none';
                   
                    topValuesSection.style.display = 'none';
                    numericSection.style.display = 'none';
                    document.getElementById('value-search').value = '';

                    // Store current field and table for search
                    topValuesSection.dataset.currentField = fieldName;
                    topValuesSection.dataset.currentTable = tableName;

                    // Always show field summary statistics
                    document.getElementById('distinct-count').textContent = stats.distinct_count.toLocaleString();
                    document.getElementById('null-count').textContent = stats.null_count.toLocaleString();
                    document.getElementById('total-rows').textContent = stats.total_rows.toLocaleString();
                    fieldSummary.style.display = 'block';
                    statsSection.style.display = 'block';

                    // Get DOM references

                    const topValuesBody = document.getElementById('top-values-body');

                    // Clear previous content
                    if (topValuesBody) topValuesBody.innerHTML = '';

                    // Handle value distribution
                    if (stats.distinct_count) {
                        // Show top values table for >= 100 distinct values
                        if (stats.top_values && stats.top_values.length > 0) {
                            stats.top_values.forEach(item => {
                                const row = document.createElement('tr');
                                if (item.value == null) {
                                    item.value = 'NULL';
                                }
                                if (item.value.length > 50) {
                                    item.value = item.value.substring(0, 50) + '...';
                                }
                                row.innerHTML = `<td>${item.value}</td><td>${item.count.toLocaleString()}</td><td>${item.percentage}%</td>`;
                                topValuesBody.appendChild(row);
                            });
                            
                            topValuesSection.style.display = 'block';
                        }
                    }

                    // Handle numeric statistics if available
                    if (stats.numeric) {
                        const numeric = stats.numeric;
                        document.getElementById('stat-mean').textContent = parseFloat(numeric.mean).toFixed(2);
                        document.getElementById('stat-stddev').textContent = parseFloat(numeric.std_dev).toFixed(2);
                        document.getElementById('stat-min').textContent = numeric.min;
                        document.getElementById('stat-max').textContent = numeric.max;
                        
                        numericSection.style.display = 'block';
                        statsSection.style.display = 'block';
                    }
                }
            })
            .catch(error => console.error('Error fetching field details:', error));
    });
});

// Handle search functionality
document.getElementById('search-button')?.addEventListener('click', performSearch);
document.getElementById('value-search')?.addEventListener('keyup', function(event) {
    if (event.key === 'Enter') {
        performSearch();
    }
});

// Add event listener for the "Add new" button
document.getElementById('addNewBtn')?.addEventListener('click', function() {
    console.log('[ADD NEW] Button clicked');
    // Get the table name from the hidden input
    let table = document.getElementById('editTableName').value;
    console.log('[ADD NEW] Table name:', table);
    // Call the edit_row function with ID 0 to create a new record
    edit_row('0');
});

function performSearch() {
    const topValuesSection = document.getElementById('top-values-stats');
    const searchInput = document.getElementById('value-search');
    const searchValue = searchInput.value.trim();
    
    const fieldName = topValuesSection.dataset.currentField;
    const tableName = topValuesSection.dataset.currentTable;
    
    fetch(`?page=db2tables&action=search_field_values&table=${encodeURIComponent(tableName)}&field=${encodeURIComponent(fieldName)}&search=${encodeURIComponent(searchValue)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const tbody = document.getElementById('top-values-body');
                tbody.innerHTML = '';
                
                data.data.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.value || 'NULL'}</td>
                        <td>${item.count.toLocaleString()}</td>
                        <td>${item.percentage}%</td>
                    `;
                    tbody.appendChild(row);
                });
            }
        })
        .catch(error => console.error('Error searching field values:', error));
}

function table_data_search() {
    var comp_table = getComponent('tableDataId')
    if (comp_table == null) return
    let val =  document.getElementById('searchTable').value
    comp_table.filter_remove_start('search:')
    if (val != '') {
        comp_table.filter_add('search:' + val)
    }
    comp_table.set_page(1)
    comp_table.reload()
}

function table_edit_search() {
    var comp_table = getComponent('tableEditId')
    if (comp_table == null) return
    let val = document.getElementById('searchEditTable').value
    comp_table.filter_remove_start('search:')
    if (val != '') {
        comp_table.filter_add('search:' + val)
    }
    comp_table.set_page(1)
    comp_table.reload()
}

/**
 * Handle single cell edits in the editable table
 * 
 * This function is triggered when an input or textarea in the editable table is changed.
 * It collects the primary key values and sends the updated value to the server.
 * 
 * @param {HTMLElement} element - The input or textarea element that was changed
 */
function handleSingleCellEdit(element) {
    console.log('[CELL EDIT] Starting single cell edit');
    var tableName = document.getElementById('editTableName').value;
    var token = document.getElementById('editToken').value;
    console.log('[CELL EDIT] Table:', tableName, 'Token:', token ? 'present' : 'missing');

    // Get the parent row (tr)
    const row = element.closest('tr');
    if (!row) {
        console.error('[CELL EDIT] Row not found');
        return;
    }
    
    // Find all primary key cells in the row
    const primaryKeyCells = row.querySelectorAll('input[data-primary-key="true"], textarea[data-primary-key="true"]');
    if (primaryKeyCells.length === 0) return;
    
    // Collect primary key data
    const primaryKeys = {};
    primaryKeyCells.forEach(cell => {
        primaryKeys[cell.name] = cell.value;
    });
    
    // Get the field name and value being edited
    const fieldName = element.name;
    const fieldValue = element.value;
    
    // Create the data to send
    const data = {
        primary_keys: primaryKeys,
        field_name: fieldName,
        field_value: fieldValue,
        table: tableName,
        token: token
    };
    
    // Show a small loading indicator
    const originalBackgroundColor = element.style.backgroundColor;
    element.style.backgroundColor = '#ffffe0'; // Light yellow to indicate processing
    
    // Send the data to the server
    fetch('?page=db2tables&action=save-edit-single-cell&table=' + encodeURIComponent(tableName), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Briefly flash green to indicate success
            element.style.backgroundColor = '#d4edda';
            
            // Update the element value if the server returned an updated value
            // This ensures the displayed value matches what's in the database
            if (result.updated_value !== undefined) {
                if (element.tagName === 'INPUT') {
                    element.value = result.updated_value;
                } else if (element.tagName === 'TEXTAREA') {
                    element.value = result.updated_value;
                    // Adjust height if needed
                    element.style.height = 'auto';
                    element.style.height = (element.scrollHeight) + 'px';
                }
            }
            
            setTimeout(() => {
                element.style.backgroundColor = originalBackgroundColor;
            }, 1000);
        } else {
            // Show error
            element.style.backgroundColor = '#f8d7da';
            console.error('Error saving cell:', result.error);
            alert('Error saving cell: ' + (result.error || 'Unknown error'));
            setTimeout(() => {
                element.style.backgroundColor = originalBackgroundColor;
            }, 2000);
        }
    })
    .catch(error => {
        // Show error
        element.style.backgroundColor = '#f8d7da';
        console.error('Error saving cell:', error);
        alert('Error saving cell: ' + error.message);
        setTimeout(() => {
            element.style.backgroundColor = originalBackgroundColor;
        }, 2000);
    });
}


registerHook('table-init', function (table_class) {
    if(table_class.el_container) {
        let id = table_class.el_container.getAttribute('id')
        console.log('TABLE INIT: ' + id);
        if (id == "tableEditId") {
            // la tabella è stata inizializzata o ricaricata
            eI(table_class.el_container).eIs('.js-auto-save-value', function (el) {
                el.addEventListener('change', function () {
                    console.log('CHANGE: ' + el.value);
                    handleSingleCellEdit(el);
                });
            });
            eI(table_class.el_container).eIs('.js-show-view-row', function (el) {
                el.addEventListener('click', function () {
                    console.log('SHOW VIEW ROW: ' + el.getAttribute('data-show-view-row'));
                    show_view_row(el.getAttribute('data-show-view-row'));
                });
            });
            // Gestione del pulsante Edit
            eI(table_class.el_container).eIs('.js-edit-row', function (el) {
                el.addEventListener('click', function () {
                    console.log('EDIT ROW: ' + el.getAttribute('data-edit-row'));
                    edit_row(el.getAttribute('data-edit-row'));
                });
            });
            // Gestione del pulsante Delete con conferma
            eI(table_class.el_container).eIs('.js-delete-row', function (el) {
                el.addEventListener('click', function () {
                    const id = el.getAttribute('data-delete-row');
                    const table = el.getAttribute('data-table');
                    const confirmMessage = el.getAttribute('data-confirm') || 'Are you sure you want to delete this record?';
                    
                    if (confirm(confirmMessage)) {
                        console.log('DELETE ROW: ' + id);
                        delete_row(id, table, el);
                    }
                });
            });
        }
    }
});



/**
 * Edit table actions
 */
// document ready

function show_view_row(id) {
    window.offcanvasEnd.show()
    window.offcanvasEnd.loading_show()
    console.log('[VIEW] SHOW VIEW ROW ID:', id);

    // Get table name from Table component custom_data
    const tableComponent = getComponent('tableEditId');
    if (!tableComponent) {
        console.error('[VIEW] Table component not found');
        window.offcanvasEnd.loading_hide();
        return;
    }

    const table = tableComponent.getCustomData('table');
    console.log('[VIEW] Table name from custom_data:', table);
    fetch(milk_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: getFormData('?id=' + id + '&action=single_offcanvas_view&page=db2tables&table=' + table)
    }).then((response) => {
        window.offcanvasEnd.loading_hide()
        return response.json()
    }).then((data) => {
        window.offcanvasEnd.body(data.html)
        window.offcanvasEnd.title(data.title);
    })
}

/**
 * Funzione per modificare un record in un offcanvas
 * @param {string} id - ID del record da modificare
 */
function edit_row(id) {
    window.offcanvasEnd.show()
    window.offcanvasEnd.loading_show()
    console.log('Editing record:', id);

    // Get table name from the hidden input in the view
    let table = document.getElementById('editTableName').value;
    console.log('Table name:', table);
    
    // Invia la richiesta per ottenere il form di modifica
    fetch(milk_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: getFormData('?id=' + id + '&action=edit_offcanvas_view&page=db2tables&table=' + table)
    }).then((response) => {
        window.offcanvasEnd.loading_hide()
        return response.json()
    }).then((data) => {
        if (data.success) {
            window.offcanvasEnd.body(data.html)
            window.offcanvasEnd.title(data.title);
            
            // Aggiungi event listener al form
            setupEditFormHandlers();
        } else {
            window.offcanvasEnd.body('<div class="alert alert-danger">Error: ' + (data.error || 'Unknown error') + '</div>');
            window.offcanvasEnd.title('Error');
        }
    }).catch(error => {
        window.offcanvasEnd.loading_hide();
        window.offcanvasEnd.body('<div class="alert alert-danger">Error: ' + error.message + '</div>');
        window.offcanvasEnd.title('Error');
        console.error('Error loading edit form:', error);
    });
}

/**
 * Configura i gestori di eventi per il form di modifica
 */
function setupEditFormHandlers() {
    // Gestione del form di modifica
    const form = document.getElementById('edit-record-form');
    if (!form) return;
    
    // Gestione delle checkbox NULL
    const nullCheckboxes = form.querySelectorAll('.js-null-checkbox');
    nullCheckboxes.forEach(checkbox => {
        // Configura lo stato iniziale usando l'attributo data-field
        const fieldName = checkbox.getAttribute('data-field');
        const inputField = form.querySelector(`[name="${fieldName}"]`);

        if (checkbox.checked && inputField) {
            inputField.disabled = true;
        }

        // Aggiungi l'event listener per il cambiamento
        checkbox.addEventListener('change', function() {
            if (inputField) {
                inputField.disabled = this.checked;
            }
        });
    });
    
    // Gestione dell'invio del form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Mostra un indicatore di caricamento
        window.offcanvasEnd.loading_show();
        
        // Raccogli i dati del form
        const formData = new FormData(form);
        
        // Invia la richiesta per salvare le modifiche
        fetch(milk_url + '?action=save_record&page=db2tables', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(response => response.json())
        .then(data => {
            window.offcanvasEnd.loading_hide();
            
            if (data.success) {
                // Chiudi l'offcanvas
                window.offcanvasEnd.hide();
                
                // Ricarica solo la tabella
                const editTable = getComponent('tableEditId');
                if (editTable) {
                    editTable.reload();
                }
                
                // Mostra un messaggio di successo usando il toast di sistema
                window.toasts.show(data.message || 'Record updated successfully', 'success');
                
                // Ricarica la tabella
                const tableComponent = getComponent('tableEditId');
                if (tableComponent) {
                    tableComponent.reload();
                }
            } else {
                // Mostra un messaggio di errore nell'offcanvas e nel toast
                const errorMessage = data.error || 'An error occurred while saving the record.';
                
                // Mostra errore nel form
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger';
                errorDiv.textContent = errorMessage;
                form.prepend(errorDiv);
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Mostra errore nel toast
                window.toasts.show(errorMessage, 'danger');
            }
        })
        .catch(error => {
            window.offcanvasEnd.loading_hide();
            console.error('Error saving record:', error);
            
            // Mostra un messaggio di errore nell'offcanvas e nel toast
            const errorMessage = 'An error occurred while saving the record: ' + error.message;
            
            // Mostra errore nel form
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger';
            errorDiv.textContent = errorMessage;
            form.prepend(errorDiv);
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Mostra errore nel toast
            window.toasts.show(errorMessage, 'danger');
        });
    });
}

/**
 * Funzione per eliminare un record
 * @param {string} id - ID del record da eliminare
 * @param {string} table - Nome della tabella
 * @param {HTMLElement} buttonElement - Elemento del pulsante cliccato
 */
function delete_row(id, table, buttonElement) {   
    // Salva il testo originale del pulsante
    const originalText = buttonElement.textContent;
    
    // Cambia il testo del pulsante per indicare che sta cancellando
    buttonElement.textContent = 'Deleting...';
    buttonElement.disabled = true;
    
    // Invia la richiesta di eliminazione
    fetch(milk_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: getFormData('?id=' + id + '&action=delete_record&page=db2tables&table=' + table)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Toast di successo
            window.toasts.show('Record deleted successfully.', 'success');
            
            // Ricarica la tabella
            const comp_table = getComponent('tableEditId');
            if (comp_table) {
                comp_table.reload();
            }
        } else {
            // Toast di errore - controlla sia 'error' che 'msg'
            const errorMessage = data.error || data.msg;
            if (errorMessage == '') {
                errorMessage = 'An error occurred while deleting the record.';
            }
            console.log ("errorMessage: " + errorMessage);
            window.toasts.show(errorMessage, 'danger');
        }

    }) .catch(error => {
        console.error('Error deleting record:', error);
        
        // Rimuovi il toast di caricamento e mostra errore
        window.toasts.hide();
        window.toasts.show('An error occurred while deleting the record: ' + error.message, 'danger');
    })
    .finally(() => {
        // Ripristina il testo originale del pulsante e riabilita il pulsante
        buttonElement.textContent = originalText;
        buttonElement.disabled = false;
    });
}

