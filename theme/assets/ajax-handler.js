/**
 * AJAX response handler for Reports module
 * Centralizes handling of permission denied responses and other common response patterns
 */

// customizations the native fetch to add permission denied handling
const originalFetch = window.fetch;
window.fetch = function(...args) {
    // Get the original arguments
    let [url, options = {}] = args;
    
    // Check if this is a POST request and we have a CSRF token
    const method = options.method?.toUpperCase() || 'GET';
    if (method === 'POST') {
        // Try to get CSRF token from meta tag
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            const csrfToken = csrfMeta.getAttribute('content');
            if (csrfToken) {
                // Ensure headers object exists
                options.headers = options.headers || {};
                
                // Add CSRF token to headers (only if not already present)
                if (!options.headers['X-CSRF-Token'] && !options.headers['x-csrf-token']) {
                    options.headers['X-CSRF-Token'] = csrfToken;
                }
            }
        }
    }
    
    // Make the request with modified options
    return originalFetch.apply(this, [url, options])
        .then(response => {
            // Clone the response so we can read it multiple times
            const responseClone = response.clone();
            
            // Process the response for permission denied
            responseClone.json().then(data => {
                // Check for permission denied responses
                if (data.permission_denied === true) {
                    // Show toast notification for permission denied
                    window.toasts.show(`Permission denied: ${data.msg}`, 'danger');
                    
                    // You might want to handle redirection or other actions here
                    console.log('Permission denied:', data.msg);
                }
                if (data.csrf_error === true && response.status === 422 ) {
                    window.toasts.show(`Security token expired. Refreshing page...`, 'danger');
                    // Reload the page to get a fresh CSRF token
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                }
            }).catch(err => {
                // If the response is not JSON, just continue
                console.log('Response is not JSON or is empty');
            });
            
            // Return the original response for further processing
            return response;
        })
        .catch(error => {
            // Handle network errors
            console.error('Fetch error:', error);
            throw error;
        });
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