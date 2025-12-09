/**
 * Audit Extension JavaScript
 *
 * This file contains JavaScript functionality for the Audit extension
 */

// Document ready
document.addEventListener('DOMContentLoaded', function() {

    // Initialize restore buttons
    initRestoreButtons();
});
document.addEventListener('updateContainer', function(event) {
    initRestoreButtons();
});

/**
 * Initialize restore button handlers
 */
function initRestoreButtons() {
    document.querySelectorAll('.js-audit-restore-btn').forEach(button => {
        button.addEventListener('click', function() {
            const restoreUrl = this.dataset.restoreUrl;
            const auditDate = this.dataset.auditDate;
            const auditUser = this.dataset.auditUser;
            const changedFieldsJson = this.dataset.changedFields;

            // Parse changed fields
            let changedFields = [];
            try {
                changedFields = JSON.parse(changedFieldsJson);
            } catch (e) {
                console.error('Failed to parse changed fields:', e);
                window.toasts?.show('Error loading restore data', 'danger');
                return;
            }

            // Build modal content
            const modalTitle = `
                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                Confirm Restore Operation
            `;

            const modalBody = `
                <div class="alert alert-danger">
                    <strong>Warning:</strong> You are about to modify the production record.
                    This operation <strong>cannot be undone</strong> and will immediately
                    update the record to the selected version.
                </div>

                <h6 class="mb-3">The following fields will be changed:</h6>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 30%;">Field</th>
                                <th style="width: 35%;">Current Value</th>
                                <th style="width: 35%;">Will Be Restored To</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${changedFields.map(field => `
                                <tr>
                                    <td><strong>${escapeHtml(field.label)}</strong></td>
                                    <td>
                                        <div style="max-height: 100px; overflow-y: auto;">
                                            <code>${escapeHtml(truncateString(field.current, 100))}</code>
                                        </div>
                                    </td>
                                    <td class="bg-success bg-opacity-10">
                                        <div style="max-height: 100px; overflow-y: auto;">
                                            <code>${escapeHtml(truncateString(field.restore, 100))}</code>
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <p class="mb-0 text-muted">
                        <small>
                            <i class="bi bi-info-circle"></i>
                            Restoring version from <strong>${escapeHtml(auditDate)}</strong>
                            by <strong>${escapeHtml(auditUser)}</strong>
                        </small>
                    </p>
                </div>
            `;

            const modalFooter = `
                <button type="button" class="btn btn-secondary" onclick="window.modal.hide()">
                    Cancel
                </button>
                <a href="${escapeHtml(restoreUrl)}"
                   data-fetch="post"
                   class="btn btn-danger">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    Confirm Restore
                </a>
            `;

            // Set modal size to large
            window.modal.size('lg');

            // Set modal content
            window.modal.title(modalTitle);
            window.modal.body(modalBody);
            window.modal.footer(modalFooter);
            // apply data-fetch
            // Show modal
            window.modal.show();
            updateContainer(window.modal.get_el());
        });
    });
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Truncate string and add ellipsis if needed
 */
function truncateString(str, maxLength) {
    if (!str && str !== 0 && str !== false) return '';
    // Convert to string to handle numbers, booleans, etc.
    const stringValue = String(str);
    if (stringValue.length <= maxLength) return stringValue;
    return stringValue.substring(0, maxLength) + '...';
}
