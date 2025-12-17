/**
 * Table Structure Management JavaScript
 * Handles the functionality for the table structure editing tab
 */

// Global variables to track tab state
let structureTabInitialized = false;
let structureTabLoaded = false;

// DOM ready event
document.addEventListener('DOMContentLoaded', function() {
    // Add tab change event listeners
    const structureTab = document.getElementById('structure-tab');
    if (structureTab) {
        structureTab.addEventListener('click', function() {
            loadStructureTab();
        });
    }
    
    // Add event listeners to other tabs to unload structure tab content when switching
    document.querySelectorAll('#tableTabs .nav-link:not(#structure-tab)').forEach(tab => {
        tab.addEventListener('click', function() {
            unloadStructureTab();
        });
    });
});

/**
 * Load the structure tab content via AJAX
 */
function loadStructureTab() {
    // If the tab is already loaded, don't reload it
    if (structureTabLoaded) return;
    structureTabLoaded = true;
    // Get the table name from the URL
    const urlParams = new URLSearchParams(window.location.search);
    const tableName = urlParams.get('table');
    
    if (!tableName) return;
    
    // Show loading indicator
    const structureTabContent = document.getElementById('structureTabContent');
    structureTabContent.innerHTML = '<div class="d-flex justify-content-center mt-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    // Load the structure tab content via AJAX
    fetch(`?page=db2tables&action=load_structure_tab&table=${encodeURIComponent(tableName)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Insert the HTML into the tab content
                structureTabContent.innerHTML = data.html;

                // Initialize the structure tab functionality
                initializeStructureTab();
                
                // Mark the tab as loaded
               
            } else {
                // Show error message
                structureTabContent.innerHTML = `<div class="alert alert-danger mt-3">${data.error || 'Failed to load table structure'}</div>`;
            }
        })
        .catch(error => {
            console.error('Error loading structure tab:', error);
            structureTabContent.innerHTML = `<div class="alert alert-danger mt-3">Failed to load table structure: ${error.message}</div>`;
        });
}

/**
 * Unload the structure tab content to free up resources
 */
function unloadStructureTab() {
    if (!structureTabLoaded) return;
    
    // Clear the tab content
    const structureTabContent = document.getElementById('structureTabContent');
    if (structureTabContent) {
        structureTabContent.innerHTML = '';
    }
    
    // Mark the tab as unloaded
    structureTabLoaded = false;
    structureTabInitialized = false;
}

/**
 * Initialize the structure tab functionality
 */
function initializeStructureTab() {
    console.log('Initializing structure tab');
    // If already initialized, don't initialize again
    if (structureTabInitialized) return;
    // Get elements
    const structureForm = document.getElementById('tableStructureForm');
    const structureTableBody = document.getElementById('structureTableBody');
    const addFieldBtn = document.getElementById('addFieldBtn');
    
    // Only initialize if we have the necessary elements
    if (!structureForm || !structureTableBody || !addFieldBtn) return;
    // Handle field type changes
    document.querySelectorAll('.field-type').forEach(select => {
        select.addEventListener('change', function() {
            handleFieldTypeChange(this);
        });
        
        // Initialize field length inputs based on current type
        handleFieldTypeChange(select);
    });
  
    // Handle index changes
    document.querySelectorAll('.field-index').forEach(select => {
        select.addEventListener('change', function() {
            handleIndexChange(this);
        });
    });
    // Add new field button click handler
    addFieldBtn.addEventListener('click', function() {
        addNewField();
    });
    
    // Delete field button click handlers
    document.querySelectorAll('.delete-field').forEach(button => {
        button.addEventListener('click', function() {
            const fieldName = this.getAttribute('data-field');
            deleteField(this, fieldName);
        });
    });
    console.log('step 6');
    // Preview changes button click handler (only if button exists - not for SQLite)
    const previewBtn = document.getElementById('previewChangesBtn');
    if (previewBtn) {
        previewBtn.addEventListener('click', function() {
            previewStructureChanges();
        });
    }
    
    // Save changes button click handler
    const saveBtn = document.getElementById('commitBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            submitStructureForm();
        });
    }
    
    // Mark as initialized
    structureTabInitialized = true;
}

/**
 * Handle index change to enforce auto-increment rules
 * @param {HTMLElement} selectElement - The select element that changed
 */
function handleIndexChange(selectElement) {
    const selectedValue = selectElement.value;
    
    // If PRIMARY + AUTO_INCREMENT is selected, change all other PRIMARY and PRIMARY_AI fields to INDEX
    if (selectedValue === 'PRIMARY_AI') {
        // Get all index selects except the current one
        document.querySelectorAll('.field-index').forEach(select => {
            if (select !== selectElement) {
                if (select.value === 'PRIMARY_AI' || select.value === 'PRIMARY') {
                    // Change to INDEX
                    select.value = 'INDEX';
                    console.log('Changed another field from PRIMARY/PRIMARY_AI to INDEX');
                }
            }
        });
    }
    
    // If PRIMARY is selected, ensure there are no PRIMARY_AI fields
    if (selectedValue === 'PRIMARY') {
        let hasPrimaryAI = false;
        
        // Check if there's already a PRIMARY_AI field
        document.querySelectorAll('.field-index').forEach(select => {
            if (select.value === 'PRIMARY_AI') {
                hasPrimaryAI = true;
            }
        });
        
        // If there's a PRIMARY_AI field, change this field to INDEX
        if (hasPrimaryAI) {
            selectElement.value = 'INDEX';
            console.log('Changed to INDEX because a PRIMARY_AI field already exists');
        }
    }
}

/**
 * Handle field type change to show/hide length field
 * @param {HTMLElement} selectElement - The select element that changed
 */
function handleFieldTypeChange(selectElement) {
    const row = selectElement.getAttribute('data-row');
    const lengthInput = document.querySelector(`input[name="fields[${row}][length]"]`);
    
    if (!lengthInput) return;
    
    const selectedType = selectElement.value.toUpperCase();
    
    // Types that need length/values specification
    const typesWithLength = ['VARCHAR', 'CHAR', 'DECIMAL', 'ENUM', 'SET'];
    const numericTypes = ['INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT', 'FLOAT', 'DOUBLE'];
    
    if (typesWithLength.includes(selectedType)) {
        lengthInput.removeAttribute('disabled');
        lengthInput.setAttribute('required', 'required');
        
        // Set default values for certain types
        if (selectedType === 'VARCHAR' && !lengthInput.value) {
            lengthInput.value = '255';
        } else if (selectedType === 'CHAR' && !lengthInput.value) {
            lengthInput.value = '50';
        } else if (selectedType === 'DECIMAL' && !lengthInput.value) {
            lengthInput.value = '10,2';
        }
    } else if (numericTypes.includes(selectedType)) {
        // For numeric types, length is optional
        lengthInput.removeAttribute('disabled');
        lengthInput.removeAttribute('required');
    } else {
        // For other types, disable length field
        lengthInput.setAttribute('disabled', 'disabled');
        lengthInput.removeAttribute('required');
        lengthInput.value = '';
    }
}

/**
 * Add a new field row to the structure table
 */
function addNewField() {
    console.log('Adding new field');
    // Get the structure table body
    const structureTableBody = document.getElementById('structureTableBody');
    if (!structureTableBody) return;
    
    // Get the current number of rows
    const rowCount = structureTableBody.querySelectorAll('tr').length;
    
    // Create a new row
    const newRow = document.createElement('tr');
    newRow.setAttribute('data-field-row', rowCount);
    // HTML for the new row
    html = `
        <td>
            <input type="text" class="form-control" name="fields[${rowCount}][name]" required>
            <input type="hidden" name="fields[${rowCount}][original_name]" value="">
        </td>
        <td>
            <select class="form-select field-type" name="fields[${rowCount}][type]" data-row="${rowCount}">
        `;
        dbFieldTypes.forEach(type => {
            html += `<option value="${type}">${type}</option>`;
        });
    html += `
            </select>
        </td>
        <td>
            <input type="text" class="form-control field-length" name="fields[${rowCount}][length]" value="255" required>
        </td>
        <td>
            <input type="text" class="form-control" name="fields[${rowCount}][default]" value="">
        </td>
        <td>
            <select class="form-select" name="fields[${rowCount}][null]">
                <option value="NOT NULL" selected>NOT NULL</option>
                <option value="NULL">NULL</option>
            </select>
        </td>
        <td>
            <select class="form-select field-index" name="fields[${rowCount}][index]" data-row="${rowCount}">
                <option value="" selected>None</option>
                <option value="PRIMARY">PRIMARY</option>
                <option value="PRIMARY_AI">PRIMARY + AUTO_INCREMENT</option>
                <option value="UNIQUE">UNIQUE</option>
                <option value="INDEX">INDEX</option>
            </select>
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-danger delete-field">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;

    newRow.innerHTML = html;
    
    // Add the row to the table
    structureTableBody.appendChild(newRow);
    
    // Add event listeners to the new row
    const typeSelect = newRow.querySelector('.field-type');
    typeSelect.addEventListener('change', function() {
        handleFieldTypeChange(this);
    });
    
    const indexSelect = newRow.querySelector('.field-index');
    indexSelect.addEventListener('change', function() {
        handleIndexChange(this);
    });
    
    const deleteButton = newRow.querySelector('.delete-field');
    deleteButton.addEventListener('click', function() {
        deleteField(this);
    });
    
    // Initialize field length based on type
    handleFieldTypeChange(typeSelect);
    
    // Focus on the field name input
    newRow.querySelector('input[name^="fields"][name$="[name]"]').focus();
}

/**
 * Delete a field row
 * @param {HTMLElement} button - The delete button that was clicked
 * @param {string} fieldName - The name of the field to delete (optional)
 */
function deleteField(button, fieldName) {
    // Check if this is the last field
    const structureTableBody = document.getElementById('structureTableBody');
    if (structureTableBody && structureTableBody.querySelectorAll('tr').length === 1) {
        alert('Cannot delete the last field. A table must have at least one field.');
        return;
    }

    // Check if this field is a primary key
    const indexSelect = button.closest('tr').querySelector('.field-index');
    if (indexSelect && indexSelect.value.includes('PRIMARY')) {
        alert('Cannot delete a PRIMARY KEY field. Please change the index type before deleting.');
        return;
    }

    if (fieldName) {
        // Confirm deletion for existing fields
        if (!confirm(`Are you sure you want to delete the field "${fieldName}"? This action cannot be undone.`)) {
            return;
        }
    } else {
        // Confirm deletion for new fields
        if (!confirm('Are you sure you want to delete this field? This action cannot be undone.')) {
            return;
        }
    }
    
    // Get the structure table body
    if (!structureTableBody) return;
    
    // Remove the row
    const row = button.closest('tr');
    row.remove();
    
    // Renumber the remaining rows
    const rows = structureTableBody.querySelectorAll('tr');
    rows.forEach((row, index) => {
        row.setAttribute('data-field-row', index);
        
        // Update input names
        row.querySelectorAll('input, select').forEach(input => {
            const name = input.getAttribute('name');
            if (name) {
                const newName = name.replace(/fields\[\d+\]/, `fields[${index}]`);
                input.setAttribute('name', newName);
            }
        });
        
        // Update data-row attributes
        const typeSelect = row.querySelector('.field-type');
        if (typeSelect) {
            typeSelect.setAttribute('data-row', index);
        }
        
        const indexSelect = row.querySelector('.field-index');
        if (indexSelect) {
            indexSelect.setAttribute('data-row', index);
        }
    });
}

/**
 * Preview structure changes before saving
 */
function previewStructureChanges() {
    // Get the form element
    const structureForm = document.getElementById('tableStructureForm');
    if (!structureForm) {
        console.error('Form element not found');
        return;
    }
    
    // Show loading indicator
    window.toasts?.show('Analyzing changes...', 'primary') || null;
    
    // Get form data
    const formData = new FormData(structureForm);
    formData.append('action', 'preview_table_structure');
    formData.append('page', 'db2tables');
    
    // Send AJAX request
    fetch(window.milk_url || '', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Remove loading indicator
        window.toasts?.hide() || null;
        
        if (data.success) {
            // Display the changes preview
            displayChangesPreview(data);
        } else {
            // Show error message
            window.toasts?.show(data.error || 'An error occurred while analyzing the table structure.', 'danger') || 
                console.error(data.error || 'An error occurred while analyzing the table structure.');
        }
    })
    .catch(error => {
        console.error('Error analyzing table structure:', error);
        
        // Remove loading indicator and show error
        window.toasts?.hide() || null;
        window.toasts?.show('An error occurred while analyzing the table structure: ' + error.message, 'danger') || 
            console.error('An error occurred while analyzing the table structure: ' + error.message);
    });
}

/**
 * Display the changes preview in the UI
 * @param {Object} data - The response data from the preview API
 */
function displayChangesPreview(data) {
    // Get preview elements
    const previewSection = document.getElementById('changesPreviewSection');
    const noChangesAlert = document.getElementById('noChangesAlert');
    const commitBtn = document.getElementById('commitBtn');
    
    // Get sections for different types of changes
    const addedFieldsSection = document.getElementById('addedFieldsSection');
    const addedFieldsList = document.getElementById('addedFieldsList');
    const modifiedFieldsSection = document.getElementById('modifiedFieldsSection');
    const modifiedFieldsList = document.getElementById('modifiedFieldsList');
    const renamedFieldsSection = document.getElementById('renamedFieldsSection');
    const renamedFieldsList = document.getElementById('renamedFieldsList');
    const droppedFieldsSection = document.getElementById('droppedFieldsSection');
    const droppedFieldsList = document.getElementById('droppedFieldsList');
    const indexChangesSection = document.getElementById('indexChangesSection');
    const indexChangesList = document.getElementById('indexChangesList');
    const sqlPreviewSection = document.getElementById('sqlPreviewSection');
    const sqlPreview = document.getElementById('sqlPreview');
    
    // Reset all sections
    previewSection.style.display = 'block';
    noChangesAlert.style.display = 'none';
    addedFieldsSection.style.display = 'none';
    modifiedFieldsSection.style.display = 'none';
    renamedFieldsSection.style.display = 'none';
    droppedFieldsSection.style.display = 'none';
    indexChangesSection.style.display = 'none';
    sqlPreviewSection.style.display = 'none';
    addedFieldsList.innerHTML = '';
    modifiedFieldsList.innerHTML = '';
    renamedFieldsList.innerHTML = '';
    droppedFieldsList.innerHTML = '';
    indexChangesList.innerHTML = '';
    sqlPreview.textContent = '';
    
    // Show/hide save button based on whether there are changes
    commitBtn.style.display = data.has_changes ? 'inline-block' : 'none';
    
    // If no changes, show the no changes alert and return
    if (!data.has_changes) {
        noChangesAlert.style.display = 'block';
        return;
    }
    
    // Display added fields
    if (data.changes.add && data.changes.add.length > 0) {
        addedFieldsSection.style.display = 'block';
        data.changes.add.forEach(field => {
            const item = document.createElement('li');
            item.className = 'list-group-item';
            item.innerHTML = `<strong>${escapeHtml(field.field)}</strong>: ${escapeHtml(field.definition)}`;
            addedFieldsList.appendChild(item);
        });
    }
    
    // Display modified fields
    if (data.changes.modify && data.changes.modify.length > 0) {
        modifiedFieldsSection.style.display = 'block';
        data.changes.modify.forEach(field => {
            const item = document.createElement('li');
            item.className = 'list-group-item';
            item.innerHTML = `<strong>${escapeHtml(field.field)}</strong>: ${escapeHtml(field.definition)}`;
            modifiedFieldsList.appendChild(item);
        });
    }
    
    // Display renamed fields
    if (data.changes.rename && data.changes.rename.length > 0) {
        renamedFieldsSection.style.display = 'block';
        data.changes.rename.forEach(field => {
            const item = document.createElement('li');
            item.className = 'list-group-item';
            item.innerHTML = `<strong>${escapeHtml(field.from)}</strong> → <strong>${escapeHtml(field.to)}</strong>: ${escapeHtml(field.definition)}`;
            renamedFieldsList.appendChild(item);
        });
    }
    
    // Display dropped fields
    if (data.changes.drop && data.changes.drop.length > 0) {
        droppedFieldsSection.style.display = 'block';
        data.changes.drop.forEach(field => {
            const item = document.createElement('li');
            item.className = 'list-group-item list-group-item-danger';
            item.innerHTML = `<strong>${escapeHtml(field.field)}</strong>`;
            droppedFieldsList.appendChild(item);
        });
    }
    
    // Display index changes
    if (data.changes.indexes && data.changes.indexes.length > 0) {
        indexChangesSection.style.display = 'block';
        data.changes.indexes.forEach(index => {
            const item = document.createElement('li');
            item.className = 'list-group-item';
            if (index.action === 'add') {
                item.innerHTML = `Add <strong>${escapeHtml(index.type)}</strong> on <strong>${escapeHtml(index.field)}</strong>`;
            } else if (index.action === 'drop') {
                item.innerHTML = `Drop <strong>${escapeHtml(index.type)}</strong> from <strong>${escapeHtml(index.field)}</strong>`;
            }
            indexChangesList.appendChild(item);
        });
    }
    
    // Display SQL preview
    if (data.sql) {
        sqlPreviewSection.style.display = 'block';
        sqlPreview.textContent = data.sql;
    }
    
    // Scroll to the preview section
    previewSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * Submit the structure form via AJAX
 */
function submitStructureForm() {
    // Get the form element
    const structureForm = document.getElementById('tableStructureForm');
    if (!structureForm) {
        console.error('Form element not found');
        return;
    }
    
    // Show loading indicator
    window.toasts?.show('Saving table structure...', 'primary') || null;
    
    // Get form data
    const formData = new FormData(structureForm);
    formData.append('action', 'save-table-structure');
    formData.append('page', 'db2tables');
    
    // Send AJAX request
    fetch(window.milk_url || '', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Remove loading indicator
        window.toasts?.hide() || null;
        
        if (data.success) {
            // Show success message
            window.toasts?.show(data.message || 'Table structure saved successfully.', 'success') || 
                console.log(data.message || 'Table structure saved successfully.');
            
            // Reload the page to reflect changes
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            // Show error message
            window.toasts?.show(data.error || 'An error occurred while saving the table structure.', 'danger') || 
                console.error(data.error || 'An error occurred while saving the table structure.');
        }
    })
    .catch(error => {
        console.error('Error saving table structure:', error);
        
        // Remove loading indicator and show error
        window.toasts?.hide() || null;
        window.toasts?.show('An error occurred while saving the table structure: ' + error.message, 'danger') || 
            console.error('An error occurred while saving the table structure: ' + error.message);
    });
}

/**
 * Helper function to escape HTML special characters
 * @param {string} unsafe - The unsafe string to escape
 * @return {string} The escaped string
 */
function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function getToken() {
    return document.getElementById('tokenContainer').dataset.token;
}

function truncateTable(tableName) {
    if (confirm('Are you sure you want to empty this table? This action cannot be undone.')) {
        fetch(window.milk_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `page=db2tables&action=truncate_table&table=${encodeURIComponent(tableName)}&token=${getToken()}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.toasts?.show('Table emptied successfully', 'success');
                setTimeout(() => {
                    location.reload();
                }, 3000);
            } else {
                window.toasts?.show(data.error || 'Error emptying table', 'danger');
            }
        });
    }
}

function dropTable(tableName, isView) {
    if (confirm(`Are you sure you want to drop this ${isView ? 'view' : 'table'}? This action cannot be undone.`)) {
        fetch(window.milk_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `page=db2tables&action=drop_table&table=${encodeURIComponent(tableName)}&is_view=${isView}&token=${getToken()}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.toasts?.show(`${isView ? 'View' : 'Table'} dropped successfully`, 'success');
                setTimeout(() => {
                    window.location.href = 'index.php?page=db2tables';
                }, 3000);
            } else {
                window.toasts?.show(data.error || 'Error dropping table', 'danger');
            }
        });
    }
}

function exportTable(tableName, format) {
    fetch(window.milk_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `page=db2tables&action=export_table&table=${encodeURIComponent(tableName)}&format=${format}&token=${getToken()}`
    })
    .then(response => response.blob())
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${tableName}.${format}`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
    })
    .catch(error => window.toasts?.show('Error exporting table', 'danger'));
}

function renameTable(tableName) {
    const newName = prompt('Enter new name:', tableName);
    if (newName && newName !== tableName) {
        fetch(window.milk_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `page=db2tables&action=rename_table&table=${encodeURIComponent(tableName)}&new_name=${encodeURIComponent(newName)}&token=${getToken()}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.toasts?.show('Table renamed successfully', 'success');
                setTimeout(() => {
                    window.location.href = `index.php?page=db2tables&action=view-table&table=${encodeURIComponent(data.new_name)}`;
                }, 3000);
            } else {
                window.toasts?.show(data.error || 'Error renaming table', 'danger');
            }
        });
    }
}

function duplicateTable(tableName) {
    const newName = prompt('Enter name for the duplicate table:', tableName + '_copy');
    if (newName) {
        fetch(window.milk_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `page=db2tables&action=duplicate_table&table=${encodeURIComponent(tableName)}&new_name=${encodeURIComponent(newName)}&token=${getToken()}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.toasts?.show('Table duplicated successfully', 'success');
                window.location.href = `index.php?page=db2tables&table=${encodeURIComponent(newName)}`;
            } else {
                window.toasts?.show(data.error || 'Error duplicating table', 'danger');
            }
        });
    }
}

/**
 * Delete a field row
 * @param {HTMLElement} button - The delete button that was clicked
 * @param {string} fieldName - The name of the field to delete (optional)
 */
function deleteField(button, fieldName) {
    // Check if this is the last field
    const structureTableBody = document.getElementById('structureTableBody');
    if (structureTableBody && structureTableBody.querySelectorAll('tr').length === 1) {
        alert('Cannot delete the last field. A table must have at least one field.');
        return;
    }
    
    if (fieldName) {
        // Check if this field is a primary key
        const indexSelect = button.closest('tr').querySelector('.field-index');
        if (indexSelect && indexSelect.value.includes('PRIMARY')) {
            alert('Cannot delete a PRIMARY KEY field. Please change the index type before deleting.');
            return;
        }
        
        // Confirm deletion for existing fields
        if (!confirm(`Are you sure you want to delete the field "${fieldName}"? This action cannot be undone.`)) {
            return;
        }
    }
    
    if (!structureTableBody) return;
    
    // Remove the row
    const row = button.closest('tr');
    row.remove();
    
    // Renumber the remaining rows
    const rows = structureTableBody.querySelectorAll('tr');
    rows.forEach((row, index) => {
        row.setAttribute('data-field-row', index);
        
        // Update input names
        row.querySelectorAll('input, select').forEach(input => {
            const name = input.getAttribute('name');
            if (name) {
                const newName = name.replace(/fields\[\d+\]/, `fields[${index}]`);
                input.setAttribute('name', newName);
            }
        });
        
        // Update data-row attributes
        const typeSelect = row.querySelector('.field-type');
        if (typeSelect) {
            typeSelect.setAttribute('data-row', index);
        }
        
        const indexSelect = row.querySelector('.field-index');
        if (indexSelect) {
            indexSelect.setAttribute('data-row', index);
        }
    });
}
