document.addEventListener('DOMContentLoaded', function() {
    // Export format toggle
    const formatCSV = document.getElementById('formatCSV');
    const formatSQL = document.getElementById('formatSQL');
    const csvOptions = document.querySelectorAll('.csv-option');
    const sqlOptions = document.querySelectorAll('.sql-option');
    
    if (formatCSV && formatSQL) {
        formatCSV.addEventListener('change', function() {
            if (this.checked) {
                csvOptions.forEach(option => option.style.display = 'block');
                sqlOptions.forEach(option => option.style.display = 'none');
            }
        });
        
        formatSQL.addEventListener('change', function() {
            if (this.checked) {
                csvOptions.forEach(option => option.style.display = 'none');
                sqlOptions.forEach(option => option.style.display = 'block');
            }
        });
    }
    
    // Export button handling
    const exportBtn = document.getElementById('exportBtn');
    const exportForm = document.getElementById('exportForm');
    
    if (exportBtn && exportForm) {
        exportBtn.addEventListener('click', function() {
            // Get selected tables
            const selectedTables = [];
            const tableCheckboxes = document.querySelectorAll('input[name="tables[]"]:checked');
            
            tableCheckboxes.forEach(checkbox => {
                selectedTables.push(checkbox.value);
            });
            
            if (selectedTables.length === 0) {
                alert('Please select at least one table to export');
                return;
            }
            
            // Get export format
            const exportFormat = document.querySelector('input[name="exportFormat"]:checked').value;
            
            // Get options
            const includeHeaders = document.getElementById('includeHeaders').checked;
            const includeStructure = document.getElementById('includeStructure').checked;
            
            // Create form data
            const formData = new FormData();
            selectedTables.forEach(table => {
                formData.append('tables[]', table);
            });
            formData.append('exportFormat', exportFormat);
            formData.append('includeHeaders', includeHeaders ? '1' : '0');
            formData.append('includeStructure', includeStructure ? '1' : '0');
            
            // Show loading state
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Exporting...';
            
            // Send request
            fetch('?page=db2tables&action=export_data', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Create download
                    if (exportFormat === 'csv' && data.csv) {
                        downloadFile(data.csv, data.filename, 'text/csv');
                    } else if (exportFormat === 'sql' && data.sql) {
                        downloadFile(data.sql, data.filename, 'application/sql');
                    }
                    
                    // Close modal
                    const exportModal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
                    exportModal.hide();
                } else {
                    // Show error
                    alert(data.error || 'Export failed');
                }
                
                // Reset button state
                exportBtn.disabled = false;
                exportBtn.textContent = 'Export';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during export');
                
                // Reset button state
                exportBtn.disabled = false;
                exportBtn.textContent = 'Export';
            });
        });
        
        // Helper function to download file
        function downloadFile(content, filename, contentType) {
            const blob = new Blob([content], { type: contentType });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            setTimeout(() => {
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }, 100);
        }
    }
    
    
    // Select all tables checkbox
    const selectAllTables = document.getElementById('selectAllTables');
    if (selectAllTables) {
        selectAllTables.addEventListener('change', function() {
            const tableCheckboxes = document.querySelectorAll('input[name="tables[]"]');
            tableCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // Create Table form handling
    const createTableBtn = document.getElementById('createTableBtn');
    const createTableForm = document.getElementById('createTableForm');
    const createTableError = document.getElementById('createTableError');
    
    if (createTableBtn && createTableForm) {
        createTableBtn.addEventListener('click', function() {
            // Validate form
            const tableName = document.getElementById('table_name').value.trim();
            const primaryKey = document.getElementById('primary_key').value.trim();
            
            if (!tableName) {
                showError('Table name is required');
                return;
            }
            
            if (!primaryKey) {
                showError('Primary key field name is required');
                return;
            }
            
            // Regular expression to validate table name (alphanumeric and underscores only)
            const validNameRegex = /^[a-z0-9_]+$/;
            if (!validNameRegex.test(tableName)) {
                showError('Table name can only contain lowercase letters, numbers, and underscores');
                return;
            }
            
            if (!validNameRegex.test(primaryKey)) {
                showError('Primary key field name can only contain lowercase letters, numbers, and underscores');
                return;
            }
            
            // Hide any previous errors
            createTableError.classList.add('d-none');
            
            // Disable button and show loading state
            createTableBtn.disabled = true;
            createTableBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...';
            
            // Send request to create table
            fetch('?page=db2tables&action=create-table', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    table_name: tableName,
                    primary_key: primaryKey
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to the new table's structure page
                    window.location.href = '?page=db2tables&action=view-table&table=' + data.table_name + '#structure';
                } else {
                    // Show error message
                    showError(data.error || 'Failed to create table');
                    
                    // Reset button state
                    createTableBtn.disabled = false;
                    createTableBtn.textContent = 'Create Table';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('An error occurred while creating the table');
                
                // Reset button state
                createTableBtn.disabled = false;
                createTableBtn.textContent = 'Create Table';
            });
        });
        
        function showError(message) {
            createTableError.textContent = message;
            createTableError.classList.remove('d-none');
        }
    }
});