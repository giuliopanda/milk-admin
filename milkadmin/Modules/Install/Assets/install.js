// Install module JavaScript functions

// Toggle module enable/disable functionality
function toggleModule(moduleName, action) {
    const formData = new FormData();
    formData.append('page', 'install');
    formData.append('action', action);
    formData.append('module', moduleName);
    
    fetch(milk_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    }).then((response) => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    }).then((data) => {
        console.log(data);
        
        if (data.status === 'success') {
            // Show success message if available
            if (data.data && data.data.message) {
                if (typeof window.toasts !== 'undefined') {
                    window.toasts.show(data.data.message, 'success');
                } else {
                    alert(data.data.message);
                }
            }
            // Reload page to show updated status
            location.reload();
        } else {
            // Show error message
            const errorMessage = data.data && data.data.message ? data.data.message : 'An error occurred while processing the request';
            if (typeof window.toasts !== 'undefined') {
                window.toasts.show(errorMessage, 'error');
            } else {
                alert(errorMessage);
            }
        }
    }).catch(error => {
        console.error('Error:', error);
        const errorMessage = 'Network error occurred while ' + action.replace('-', ' ') + 'ing module';
        if (typeof window.toasts !== 'undefined') {
            window.toasts.show(errorMessage, 'error');
        } else {
            alert(errorMessage);
        }
    });
}

// Uninstall module functionality with enhanced confirmation
function uninstallModule(moduleName) {
    showUninstallConfirmationModal(moduleName);
}

// Show enhanced confirmation modal for module uninstall
function showUninstallConfirmationModal(moduleName) {
    // Create modal HTML if it doesn't exist
    let modal = document.getElementById('uninstallConfirmModal');
    if (!modal) {
        createUninstallConfirmModal();
        modal = document.getElementById('uninstallConfirmModal');
    }
    
    // Update modal content with module name
    const moduleNameSpan = modal.querySelector('#confirmModuleName');
    const moduleNameInput = modal.querySelector('#moduleNameInput');
    const confirmButton = modal.querySelector('#confirmUninstallBtn');
    
    if (moduleNameSpan) moduleNameSpan.textContent = moduleName;
    if (moduleNameInput) {
        moduleNameInput.value = '';
        moduleNameInput.setAttribute('data-module-name', moduleName);
    }
    if (confirmButton) confirmButton.disabled = true;
    
    // Show modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

// Create the uninstall confirmation modal HTML
function createUninstallConfirmModal() {
    const modalHTML = `
        <div class="modal fade" id="uninstallConfirmModal" tabindex="-1" aria-labelledby="uninstallConfirmModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="uninstallConfirmModalLabel">
                            <i class="bi bi-exclamation-triangle me-2"></i>Uninstall Module
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>⚠️ Warning:</strong> This action cannot be undone!
                        </div>
                        
                        <p class="mb-3">
                            You are about to permanently uninstall the module <strong id="confirmModuleName"></strong>.
                            This will:
                        </p>
                        
                        <ul class="mb-3">
                            <li>Delete all module files permanently</li>
                            <li>Remove module configuration</li>
                            <li>Potentially cause data loss</li>
                        </ul>
                        
                        <div class="mb-3">
                            <label for="moduleNameInput" class="form-label">
                                <strong>To confirm, type the module name exactly:</strong>
                            </label>
                            <input type="text" class="form-control" id="moduleNameInput" 
                                   placeholder="Enter module name to confirm" 
                                   autocomplete="off">
                            <div class="form-text">
                                Type: <code id="confirmModuleName2"></code>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmUninstallBtn" disabled>
                            <i class="bi bi-trash me-2"></i>Uninstall Module
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Set up event listeners
    setupUninstallModalListeners();
}

// Set up event listeners for the uninstall modal
function setupUninstallModalListeners() {
    const modal = document.getElementById('uninstallConfirmModal');
    const moduleNameInput = modal.querySelector('#moduleNameInput');
    const confirmButton = modal.querySelector('#confirmUninstallBtn');
    
    // Update both module name displays when modal is shown
    modal.addEventListener('shown.bs.modal', function() {
        const moduleName = moduleNameInput.getAttribute('data-module-name');
        const confirmModuleName2 = modal.querySelector('#confirmModuleName2');
        if (confirmModuleName2) confirmModuleName2.textContent = moduleName;
        moduleNameInput.focus();
    });
    
    // Enable/disable confirm button based on input
    moduleNameInput.addEventListener('input', function() {
        const expectedName = this.getAttribute('data-module-name');
        const enteredName = this.value.trim();
        
        if (enteredName === expectedName) {
            confirmButton.disabled = false;
            confirmButton.classList.remove('btn-danger');
            confirmButton.classList.add('btn-success');
            confirmButton.innerHTML = '<i class="bi bi-check2 me-2"></i>Confirm Uninstall';
        } else {
            confirmButton.disabled = true;
            confirmButton.classList.remove('btn-success');
            confirmButton.classList.add('btn-danger');
            confirmButton.innerHTML = '<i class="bi bi-trash me-2"></i>Uninstall Module';
        }
    });
    
    // Handle enter key in input
    moduleNameInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !confirmButton.disabled) {
            confirmButton.click();
        }
    });
    
    // Handle confirm button click
    confirmButton.addEventListener('click', function() {
        const moduleName = moduleNameInput.getAttribute('data-module-name');
        const enteredName = moduleNameInput.value.trim();
        
        // Double-check validation
        if (enteredName !== moduleName) {
            window.toasts.show('Module name does not match. Please type the exact module name.', 'error');
            moduleNameInput.focus();
            return;
        }
        
        // Close modal
        const bsModal = bootstrap.Modal.getInstance(modal);
        bsModal.hide();
        
        // Proceed with uninstall
        performModuleUninstall(moduleName);
    });
}

// Perform the actual module uninstall
function performModuleUninstall(moduleName) {
    // Show loading toast
    window.toasts.show('Uninstalling module ' + moduleName + '...', 'info');
    
    const formData = new FormData();
    formData.append('page', 'install');
    formData.append('action', 'uninstall-module');
    formData.append('module', moduleName);
    
    fetch(milk_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    }).then((response) => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    }).then((data) => {
        console.log(data);
        
        if (data.status === 'success') {
            // Show success message if available
            if (data.data && data.data.message) {
                window.toasts.show(data.data.message, 'success');
            } else {
                window.toasts.show('Module ' + moduleName + ' uninstalled successfully', 'success');
            }
            // Reload page to show updated status
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            // Show error message
            const errorMessage = data.data && data.data.message ? data.data.message : 'An error occurred while uninstalling the module';
            window.toasts.show(errorMessage, 'error');
        }
    }).catch(error => {
        console.error('Uninstall error:', error);
        const errorMessage = 'Network error occurred while uninstalling module';
        window.toasts.show(errorMessage, 'error');
    });
}

// Install form validation and submission handling
(function() {
    'use strict';
    
    const form = document.getElementById('installForm');
    
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            if (form.checkValidity()) {
                // Validation passed - proceed with installation
               
                // Show loading
                const btn = form.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Installing..';
                
                // Submit form normally
                form.submit();
                
            } else {
                // Validation failed
                window.toasts.show('Check the form fields', 'error');
             
                // Scroll to first invalid field
                const firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
            
            form.classList.add('was-validated');
        });
    }
})();

// Update file upload handling
document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('upload-form');
    
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(event) {
            const submitBtn = document.getElementById('submit-btn');
            const fileInput = document.getElementById('update_file');
            
            if (fileInput && fileInput.files.length > 0) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';
            }
        });
    }
    
    // Module upload handling  
    const moduleUploadForm = document.getElementById('module-upload-form');
    
    if (moduleUploadForm) {
        moduleUploadForm.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('module-upload-btn');
            const fileInput = document.getElementById('module_file');
            
            if (!fileInput.files.length) {
                e.preventDefault();
                window.toasts.show('Please select a module file to upload', 'error');
                return;
            }
            
            // Check file type
            const file = fileInput.files[0];
            if (!file.name.toLowerCase().endsWith('.zip')) {
                e.preventDefault();
                window.toasts.show('Please select a ZIP file', 'error');
                return;
            }
            
            // Show loading state  
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading and Installing...';
        });
    }
});

// Attempt module installation after upload
function attemptModuleInstallation(moduleName) {
    const formData = new FormData();
    formData.append('page', 'install');
    formData.append('action', 'install-module');
    formData.append('module_name', moduleName);
    
    fetch(milk_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    }).then((response) => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    }).then((data) => {
        console.log('Installation result:', data);
        
        if (data.status === 'success' && data.data && data.data.message) {
            window.toasts.show(data.data.message, 'success');
            // Reload page after installation attempt
            setTimeout(() => {
                location.reload();
            }, 2500);
        } else if (data.data && data.data.message) {
            // Even if installation "failed", show the message as it might be a success with explanation
            window.toasts.show(data.data.message, 'info');
        }
        
        
        
    }).catch(error => {
        console.error('Installation error:', error);
        window.toasts.show('Installation error:', error, 'error');
    });
}

// Auto-update modules when "Not installed" modules are detected
function autoUpdateModules() {
    const formData = new FormData();
    formData.append('page', 'install');
    formData.append('action', 'update-modules-json');
    
    fetch(milk_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    }).then((response) => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    }).then((data) => {
        console.log('Update result:', data);
        
        const preloader = document.getElementById('module-preloader');
        const resultDiv = document.getElementById('module-result');
        const messageDiv = document.getElementById('result-message');
        
        if (preloader) preloader.style.display = 'none';
        if (resultDiv) resultDiv.classList.remove('d-none');
        
        if (data.status === 'success' && data.data) {
            if (data.data.success) {
                // Success - show green alert
                messageDiv.innerHTML = `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> ${data.data.message || 'Installation completed successfully'}
                        ${data.data.updated_modules && data.data.updated_modules.length > 0 ? 
                            '<br><small>Updated modules: ' + data.data.updated_modules.join(', ') + '</small>' 
                            : ''}
                    </div>`;
                
                // Reload page after a few seconds
                setTimeout(() => {
                    location.reload();
                }, 3000);
            } else {
                // Error - show red alert
                messageDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> ${data.data.message || 'Installation failed'}
                    </div>`;
            }
        } else {
            // Unexpected response - show warning
             if (data.message) {
                messageDiv.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> ${data.message}
                    </div>`;
            } else {
            messageDiv.innerHTML = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> Unexpected response from server
                </div>`;
            }
        }
        
    }).catch(error => {
        console.error('Auto-update error:', error);
        
        const preloader = document.getElementById('module-preloader');
        const resultDiv = document.getElementById('module-result');
        const messageDiv = document.getElementById('result-message');
        
        if (preloader) preloader.style.display = 'none';
        if (resultDiv) resultDiv.classList.remove('d-none');
        
        messageDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> Network error occurred during installation
            </div>`;
    });
}

// Initialize auto-update when page loads if needed
document.addEventListener('DOMContentLoaded', function() {
    const modulePreloader = document.getElementById('module-preloader');
    
    if (modulePreloader) {
        // Start auto-update process
        setTimeout(() => {
            autoUpdateModules();
        }, 1000); // Wait 1 second to let user see the preloader
    }
});