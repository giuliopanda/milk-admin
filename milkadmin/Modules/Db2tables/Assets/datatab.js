// IL TAB DATA

// Table search functionality
function searchTable() {
    var comp_table = getComponent('tableDataId');
    if (comp_table == null) return;
    
    let val = document.getElementById('searchTable').value;
    comp_table.filter_remove_start('search:');
    
    if (val != '') {
        comp_table.filter_add('search:' + val);
    }
    
    comp_table.set_page(1);
    comp_table.reload();
}

// Add event listener for Enter key in search field
document.getElementById('searchTable')?.addEventListener('keyup', function(event) {
    if (event.key === 'Enter') {
        searchTable();
    }
});


// Handle view definition form submission
document.addEventListener('DOMContentLoaded', function() {
    const saveViewDefinitionBtn = document.getElementById('saveViewDefinitionBtn');
    if (saveViewDefinitionBtn) {
        saveViewDefinitionBtn.addEventListener('click', function() {
            const viewName = document.getElementById('viewName').value;
            const originalViewName = document.getElementById('originalViewName').value;
            const viewDefinition = document.getElementById('viewDefinition').value;
            const token = saveViewDefinitionBtn.getAttribute('data-token');
            
            if (!viewName || !viewDefinition) {
                window.toasts.show('View name and definition are required', 'error');
                return;
            }
            
            // Validate view name format
            if (!/^[a-zA-Z0-9_]+$/.test(viewName)) {
                window.toasts.show('View name can only contain letters, numbers, and underscores', 'error');
                return;
            }
            
            // Show loading state
            saveViewDefinitionBtn.disabled = true;
            saveViewDefinitionBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
            
            // Send the data to the server
            fetch('?page=db2tables&action=edit-view-definition', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    viewName: viewName,
                    originalViewName: originalViewName,
                    viewDefinition: viewDefinition,
                    token: token
                })
            })
            .then(response => response.json())
            .then(data => {
                // Reset button state
                saveViewDefinitionBtn.disabled = false;
                saveViewDefinitionBtn.innerHTML = '<i class="bi bi-save"></i> Save View Definition';
                
                if (data.success) {
                    window.toasts.show(data.message, 'success');
                    
                    // If view name was changed, redirect to the new view page
                    if (viewName !== originalViewName) {
                        window.location.href = '?page=db2tables&action=view-table&table=' + encodeURIComponent(viewName);
                        return;
                    }
                    
                    // Update the table data
                    const tableComponent = getComponent('tableDataId');
                    if (tableComponent) {
                        tableComponent.reload();
                    }
                } else {
                    window.toasts.show(data.error, 'error');
                }
            })
            .catch(error => {
                // Reset button state
                saveViewDefinitionBtn.disabled = false;
                saveViewDefinitionBtn.innerHTML = '<i class="bi bi-save"></i> Save View Definition';
                
                window.toasts.show('An error occurred while saving the view definition', 'error');
                console.error('Error:', error);
            });
        });
    }
});