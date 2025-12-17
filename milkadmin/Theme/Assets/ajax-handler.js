/**
 * AJAX response handler for Reports module
 * Centralizes handling of permission denied responses and other common response patterns
 */

// customizations the native fetch to add permission denied handling
const originalFetch = window.fetch;

window.fetch = function (...args) {
    let [url, options = {}] = args;
    const method = (options.method || 'GET').toUpperCase();

    // Inizializza headers se non esiste
    if (!options.headers) {
        options.headers = {};
    }

    if (options.headers instanceof Headers) {
        if (!options.headers.has('Accept')) {
            options.headers.set('Accept', 'application/json');
        }
    } else {
        if (!options.headers['Accept']) {
            options.headers['Accept'] = 'application/json';
        }
    }

    if (method === 'POST') {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const csrfTokenName = document.querySelector('meta[name="csrf-token-name"]')?.getAttribute('content') || 'csrf_token';

        if (csrfToken) {
            if (options.body instanceof FormData) {
                try {
                    if (!options.body.has(csrfTokenName)) {
                        options.body.append(csrfTokenName, csrfToken);
                    }
                } catch (err) {
                    console.error("Errore nell'aggiungere il token al FormData:", err);
                }
                // NON forzare un oggetto headers se già è Headers API
                if (!options.headers) {
                    options.headers = new Headers();
                }
                if (options.headers instanceof Headers) {
                    if (!options.headers.has('X-CSRF-Token')) {
                        options.headers.set('X-CSRF-Token', csrfToken);
                    }
                } else if (!options.headers['X-CSRF-Token'] && !options.headers['x-csrf-token']) {
                    options.headers['X-CSRF-Token'] = csrfToken;
                }
            } else {
                if (!options.headers) {
                    options.headers = {};
                }
                if (options.headers instanceof Headers) {
                    if (!options.headers.has('X-CSRF-Token')) {
                        options.headers.set('X-CSRF-Token', csrfToken);
                    }
                } else if (!options.headers['X-CSRF-Token'] && !options.headers['x-csrf-token']) {
                    options.headers['X-CSRF-Token'] = csrfToken;
                }
            }
        }
    }

    try {
        return originalFetch.apply(this, [url, options])
            .then(response => {
                const clone = response.clone();
                clone.json().then(data => {
                    if (data.permission_denied) {
                        window.toasts?.show(`Permission denied: ${data.msg}`, 'danger');
                    }
                    if (data.csrf_error && response.status === 422) {
                        window.toasts?.show(`Security token expired. Refreshing page...`, 'danger');
                        setTimeout(() => window.location.reload(), 3000);
                    }
                }).catch(() => {});
                return response;
            })
            .catch(error => {
                console.error('Fetch error:', error);
                throw error;
            });
    } catch (err) {
        console.error("Errore prima della chiamata fetch:", err);
        throw err;
    }
};



// Helper function to handle AJAX responses consistently
window.handleAjaxResponse = function(response) {
    if (!response) return;
    
    if (response.permission_denied === true) {
        window.toasts.show(`Permission denied: ${response.msg}`, 'danger');
        return false;
    }
    
    if (!response.success) {
        window.toasts.show(response.msg || 'An error occurred', 'danger');
        return false;
    }
    
    return true;
};

// Utility function to manually get CSRF token (for edge cases)
window.getCSRFToken = function() {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    return csrfMeta ? csrfMeta.getAttribute('content') : null;
};

// Add csfr in submit post

/**
 * CSRF Form Submit Handler
 * Aggiunge automaticamente il token CSRF ai form POST al momento del submit
 */
(function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const csrfTokenName = document.querySelector('meta[name="csrf-token-name"]')?.getAttribute('content');

    if (!csrfToken || !csrfTokenName) return;

    document.addEventListener('submit', function(event) {
        const form = event.target;
        const method = (form.getAttribute('method') || form.method || 'get').toLowerCase();
        if (form.matches('form') && 
            method === 'post' && 
            !form.querySelector(`input[name="${csrfTokenName}"]`)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = csrfTokenName;
            input.value = csrfToken;
            form.insertBefore(input, form.firstChild);
        }
    }, true);
})();


/**
 * MilkActions - JSON Action Response System
 * Handles structured JSON responses for controlling frontend via AJAX
 * @TODO container ?
 */
function jsonAction(data, container) {
    // 1. HTML replacement Removed
   
    // 2. Redirect
    if ('redirect' in data && data.redirect != '') {
        window.location.href = data.redirect;
        return;
    }

    // Set default success state
    if (!('success' in data)) {
        data.success = true;
    }
    const message_type = data.success ? 'success' : 'danger';

    // 3. Offcanvas End
    if (data.offcanvas_end && window.offcanvasEnd) {
        if (data.offcanvas_end.size) {
            window.offcanvasEnd.size(data.offcanvas_end.size);
        }

        // Set content first (if specified) - updateContainer is called here
        if (data.offcanvas_end.title) {
            window.offcanvasEnd.title(data.offcanvas_end.title);
        }
        if (data.offcanvas_end.body) {
            window.offcanvasEnd.body(data.offcanvas_end.body);
        }

        // Then handle actions (show/hide without re-setting content)
        if (data.offcanvas_end.action) {
            switch (data.offcanvas_end.action) {
                case 'loading_show':
                    window.offcanvasEnd.loading_show();
                    window.offcanvasEnd.show();
                    break;
                case 'loading_hide':
                    window.offcanvasEnd.loading_hide();
                    window.offcanvasEnd.show();
                    break;
                case 'show':
                    window.offcanvasEnd.show();
                    break;
                case 'hide':
                    window.offcanvasEnd.hide();
                    break;
                default:
                    break;
            }
        } else {
            // No action specified: just show if content was provided
            if (data.offcanvas_end.title || data.offcanvas_end.body) {
                window.offcanvasEnd.show();
            }
        }
    }

    // 4. Modal
    if (data.modal && window.modal) {
        if (data.modal.size) {
            window.modal.size(data.modal.size);
        }

        // Set content first (if specified) - updateContainer is called here
        if (data.modal.title) {
            window.modal.title(data.modal.title);
        }
        if (data.modal.body) {
            window.modal.body(data.modal.body);
        }
        if (data.modal.footer) {
            window.modal.footer(data.modal.footer);
        }

        // Then handle actions (show/hide without re-setting content)
        if (data.modal.action) {
            switch (data.modal.action) {
                case 'loading_show':
                    window.modal.loading_show();
                    window.modal.show();
                    break;
                case 'loading_hide':
                    window.modal.loading_hide();
                    window.modal.show();
                    break;
                case 'show':
                    window.modal.show();
                    break;
                case 'hide':
                    window.modal.hide();
                    break;
                default:
                    break;
            }
        } else {
            // No action specified: just show the modal (content already set above)
            if (data.modal.title || data.modal.body || data.modal.footer) {
                window.modal.show();
            }
        }
    }

    // 5. Toast messages
    if (data.toast && window.toasts) {
        if (data.toast.action === 'show') {
            window.toasts.show(data.toast.body || data.toast.message, data.toast.type || message_type);
        } else if (data.toast.action === 'hide') {
            window.toasts.hide();
        } else if (data.toast.body || data.toast.message) {
            window.toasts.show(data.toast.body || data.toast.message, data.toast.type || message_type);
        }
    } else {
        // message sigle + success
        if (data.msg ) {
            window.toasts.show(data.msg, message_type);
        } else if (data.message ) {
            window.toasts.show(data.message, message_type);
        }
    }

    // 6. Form management
    if (data.form) {
        // Reset form
        if (data.form.action === 'reset' && data.form.id) {
            const form = document.getElementById(data.form.id);
            if (form) form.reset();
        }

        // Set field values
        if (data.form.fields) {
            Object.keys(data.form.fields).forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    field.value = data.form.fields[fieldName];
                    // Trigger change event
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }

        // Set validation errors
        if (data.form.errors) {
            Object.keys(data.form.errors).forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    field.classList.add('is-invalid');
                    // Add error message if feedback div exists
                    const feedback = field.parentElement.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.textContent = data.form.errors[fieldName];
                    }
                }
            });
        }
    }

    // 7. DOM Elements manipulation (multiple elements support)
    if (data.elements && Array.isArray(data.elements)) {
        data.elements.forEach(elementData => {
            milkActionsProcessElement(elementData);
        });
    }
    // Single element support (backward compatibility)
    if (data.element) {
        milkActionsProcessElement(data.element);
    }

    // 8. Scroll
    if (data.scroll) {
        if (data.scroll.to === 'top') {
            window.scrollTo({ top: 0, behavior: data.scroll.behavior || 'smooth' });
        } else if (data.scroll.selector) {
            const el = document.querySelector(data.scroll.selector);
            if (el) {
                el.scrollIntoView({
                    behavior: data.scroll.behavior || 'smooth',
                    block: data.scroll.block || 'start'
                });
            }
        }
    }

    // 9. Table actions
    if ('table' in data && data.table.id) {
        const table = getComponent(data.table.id);
        if (table) {
            if (data.table.action == 'reload') {
                table.reload();
            }
        } else {
            console.warn('error table reload id: '+data.table.id)
        }
    }

    // 10. Calendar actions
    if ('calendar' in data && data.calendar.id) {
        const calendar = getComponent(data.calendar.id);
        if (calendar) {
            if (data.calendar.action == 'reload') {
                calendar.reload();
            }
        } else {
            console.warn('error table reload id: '+data.table.id)
        }
    }

    // GENERIC RELOAD LIST!
    if ('list' in data && data.list.id) {
        const list = getComponent(data.list.id);
        if (list) {
            if (data.list.action == 'reload') {
                list.reload();
            }
        } else {
            console.warn('error list reload id: '+data.table.id)
        }
    }


    // 11. JavaScript Hooks
    if (data.hook) {
        milkActionsProcessHook(data.hook);
    }
    if (data.hooks && Array.isArray(data.hooks)) {
        data.hooks.forEach(hookData => {
            milkActionsProcessHook(hookData);
        });
    }
}

/**
 * Process JavaScript hook call for MilkActions
 * @param {Object} hookData - Hook configuration object
 */
function milkActionsProcessHook(hookData) {
    if (!hookData.name) {
        console.warn('MilkActions: Hook name is required');
        return;
    }

    // Prepare arguments
    const args = hookData.args || [];

    // Call the hook
    const result = callHook(hookData.name, ...args);

    // Optional: Log result for debugging
    if (hookData.debug) {
        console.log(`MilkActions Hook "${hookData.name}" called with args:`, args, 'Result:', result);
    }

    return result;
}

/**
 * Process single element manipulation for MilkActions
 * @param {Object} elementData - Element configuration object
 */
function milkActionsProcessElement(elementData) {
    if (!elementData.selector) return;

    const el = document.querySelector(elementData.selector);
    if (!el) {
        console.warn('MilkActions: Element not found:', elementData.selector);
        return;
    }

    // Action: show/hide/remove/toggle
    if (elementData.action) {
        switch (elementData.action) {
            case 'show':
                // Uses fade animation (200ms)
                elShow(el, elementData.callback);
                break;
            case 'hide':
                // Uses fade animation (200ms)
                elHide(el, elementData.callback);
                break;
            case 'remove':
                // Uses fade animation (200ms) then removes from DOM
                elRemove(el);
                break;
            case 'toggle':
                // Toggle display state
                toggleEl(el);
                break;
        }
    }

    // Set innerHTML
    if (elementData.innerHTML !== undefined) {
        el.innerHTML = elementData.innerHTML;
        updateContainer(el);
    }

    // Set innerText
    if (elementData.innerText !== undefined) {
        el.innerText = elementData.innerText;
    }

    // Set value (for inputs)
    if (elementData.value !== undefined) {
        el.value = elementData.value;
    }

    // Add classes
    if (elementData.addClass) {
        const classes = Array.isArray(elementData.addClass)
            ? elementData.addClass
            : elementData.addClass.split(' ');
        el.classList.add(...classes);
    }

    // Remove classes
    if (elementData.removeClass) {
        const classes = Array.isArray(elementData.removeClass)
            ? elementData.removeClass
            : elementData.removeClass.split(' ');
        el.classList.remove(...classes);
    }

    // Toggle classes
    if (elementData.toggleClass) {
        const classes = Array.isArray(elementData.toggleClass)
            ? elementData.toggleClass
            : elementData.toggleClass.split(' ');
        classes.forEach(cls => el.classList.toggle(cls));
    }

    // Set attributes
    if (elementData.attributes) {
        Object.keys(elementData.attributes).forEach(attr => {
            el.setAttribute(attr, elementData.attributes[attr]);
        });
    }

    // Remove attributes
    if (elementData.removeAttributes) {
        const attrs = Array.isArray(elementData.removeAttributes)
            ? elementData.removeAttributes
            : [elementData.removeAttributes];
        attrs.forEach(attr => el.removeAttribute(attr));
    }

    // Set styles
    if (elementData.style) {
        _eIStyle(el, elementData.style);
    }

    // Append HTML
    if (elementData.append) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = elementData.append;
        while (tempDiv.firstChild) {
            el.appendChild(tempDiv.firstChild);
        }

        updateContainer(el);
    }

    // Prepend HTML
    if (elementData.prepend) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = elementData.prepend;
        while (tempDiv.lastChild) {
            el.insertBefore(tempDiv.lastChild, el.firstChild);
        }
        updateContainer(el);
    }

    // Insert before
    if (elementData.before) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = elementData.before;
        while (tempDiv.firstChild) {
            el.parentNode.insertBefore(tempDiv.firstChild, el);
        }
        updateContainer(el);
    }

    // Insert after
    if (elementData.after) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = elementData.after;
        let refNode = el.nextSibling;
        while (tempDiv.lastChild) {
            el.parentNode.insertBefore(tempDiv.lastChild, refNode);
        }
        updateContainer(el)
    }
}



/**
 * Se creo un elemento poi questo deve essere aggiornato con tutti i javascript
 * @Todo dovrebbe essere questo ad avviarsi a inizio caricamento con el = document
 * @param {} el
 */
function updateContainer(el) {

    const container = eI(el);

    initFetchLinks(el);
    initFetchDiv(el);
    //
    const forms = container.querySelectorAll('.js-needs-validation');
    Array.prototype.slice.call(forms).forEach(function (form) {
        setFormSubmit(form);
    });
    //
    container.querySelectorAll('.js-milk-clear-search').forEach(button => {
        clearSearchInputButton(button);
    });
    //
    container.querySelectorAll('.js-action-list').forEach(container => {
        setupActionList(container);
    });
    //
    container.querySelectorAll('[data-togglefield]').forEach(el => {  new toggleEls(el); });

    // Auto-dismiss alerts after 8 seconds
    autoDismissAlerts(8000, container);

    document.dispatchEvent(new CustomEvent('updateContainer', { detail: { el: el } }));
}