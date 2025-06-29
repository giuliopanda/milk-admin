var tableComponent;
document.addEventListener('DOMContentLoaded', function() {
    const apiFilterElement = document.getElementById('api-filter');
    const statusFilterElement = document.getElementById('status-filter');
    const tableId = 'logs-table'; // This should be the ID used in PageInfo->set_id()

    // Wait for the table component to be available, as it might be initialized after DOMContentLoaded
    initializeFilters();
    
    function initializeFilters() {
        if (apiFilterElement) {
            apiFilterElement.addEventListener('change', function() {
                const apiName = this.value;
                tableComponent = getComponent('logs-table');
                console.log(tableComponent);
                tableComponent.filter_remove_start('api:');
                if (apiName) {
                    tableComponent.filter_add('api:' + apiName);
                }
                tableComponent.set_page(1);
                tableComponent.reload();
            });
        }

        if (statusFilterElement) {
            statusFilterElement.addEventListener('change', function() {
                const status = this.value;
                tableComponent = getComponent('logs-table');
                tableComponent.filter_remove_start('status:');
                if (status) {
                    tableComponent.filter_add('status:' + status);
                }
                tableComponent.set_page(1);
                tableComponent.reload();
            });
        }
    }
});

// Aggiungi il hook per gestire i dettagli dei log (simile a quello dei jobs)
registerHook('table-init', function (table_class) {
    if(table_class.el_container) {
        let id = table_class.el_container.getAttribute('id');
        if (id == "logs-table") {
            table_class.el_container.querySelectorAll('.js-show-info').forEach((element) => {
                element.onclick = function(e) {
                    e.preventDefault();
                    const logId = this.getAttribute('data-id');
                    
                    // Mostra l'offcanvas
                    window.offcanvasEnd.show();
                    window.offcanvasEnd.loading_show();
                    
                    // Fetch dei dettagli del log
                    fetch('?page=api-registry&action=get-log-details&id=' + logId, {
                        method: 'POST',
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        window.offcanvasEnd.loading_hide();
                        if (data.success) {
                            window.offcanvasEnd.title(data.title);
                            window.offcanvasEnd.body(data.html);
                        } else {
                            window.offcanvasEnd.title('Error');
                            window.offcanvasEnd.body('<div class="alert alert-danger">' + data.msg + '</div>');
                        }
                    })
                    .catch(error => {
                        window.offcanvasEnd.loading_hide();
                        window.offcanvasEnd.title('Error');
                        window.offcanvasEnd.body('<div class="alert alert-danger">Failed to load log details</div>');
                        console.error('Error loading log details:', error);
                    });
                };
            });
        }
    }
});