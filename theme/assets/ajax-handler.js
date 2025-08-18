/**
 * AJAX response handler for Reports module
 * Centralizes handling of permission denied responses and other common response patterns
 */

// customizations the native fetch to add permission denied handling
const originalFetch = window.fetch;

window.fetch = function (...args) {
    let [url, options = {}] = args;
    const method = (options.method || 'GET').toUpperCase();

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

// Utility function to refresh CSRF token (if needed)
window.refreshCSRFToken = function() {
    return fetch('/api/csrf-token', {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.token) {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) {
                csrfMeta.setAttribute('content', data.token);
            }
            return data.token;
        }
    })
    .catch(err => {
        console.error('Failed to refresh CSRF token:', err);
    });
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
        
        if (form.matches('form') && 
            form.method.toLowerCase() === 'post' && 
            !form.querySelector(`input[name="${csrfTokenName}"]`)) {
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = csrfTokenName;
            input.value = csrfToken;
            form.insertBefore(input, form.firstChild);
        }
    }, true);
})();