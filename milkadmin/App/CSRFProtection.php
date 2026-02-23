<?php
namespace App;
/**
 * CSRF Protection Middleware
 * 
 * Automatically validates CSRF tokens for all POST requests from authenticated users.
 * Provides different responses for AJAX/fetch requests vs traditional form submissions.
 * 
 * @package     App
 * @ignore
 */
class CSRFProtection {
    
    /**
     * Routes exempted from CSRF protection
     * 
     * @var array
     */
    private static array $exempt_routes = [];
    
    /**
     * Main CSRF validation method
     * Call this early in your application bootstrap
     * 
     * @return void
     */
    public static function validate(): void {
       
        // Skip if not a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        // Skip if route is exempted
        if (self::isExemptRoute()) {
            return;
        }
      
        // Get CSRF token from various sources
        $token = self::extractToken();
        // Validate token
        if (!Token::checkValue($token, session_id())) {
            self::handleInvalidToken();
        }
    }
    
    /**
     * Extract CSRF token from request
     * Checks headers first, then POST data
     * 
     * @return string|null
     */
    public static function extractToken(): ?string {
        // Check custom header (for fetch requests)
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!is_null($token) && $token !== "") {
            return $token;
        }
        // Check POST data (for traditional forms)
        $token_name = Token::getTokenName(session_id());
        $token = $_POST[$token_name] ?? null;
        return $token;
    }
    
    /**
     * Check if current route is exempted from CSRF protection
     * 
     * @return bool
     */
    private static function isExemptRoute(): bool {
        $current_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        foreach (self::$exempt_routes as $exempt_route) {
            if (str_ends_with($exempt_route, '*')) {
                $pattern = rtrim($exempt_route, '*');
                if (str_starts_with($current_uri, $pattern)) {
                    return true;
                }
            } elseif ($current_uri === $exempt_route) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect if request is AJAX/fetch
     * 
     * @return bool
     */
    private static function isAjaxRequest(): bool {
        // Check for XMLHttpRequest header
        $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($requested_with) === 'xmlhttprequest') {
            return true;
        }
        
        // Check for JSON content type
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains(strtolower($content_type), 'application/json')) {
            return true;
        }
        
        // Check for custom CSRF header (indicates fetch request, including file uploads)
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return true;
        }
        
        // Check Accept header for JSON preference
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            return true;
        }
        
        // Check if it's a fetch request with multipart/form-data (file upload via fetch)
        if (str_contains(strtolower($content_type), 'multipart/form-data')) {
            // If it has custom headers or specific user agents that indicate fetch
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            // Modern browsers using fetch for file upload still send the CSRF header
            // This is handled by the HTTP_X_CSRF_TOKEN check above
        }
        
        return false;
    }
    
    /**
     * Handle invalid CSRF token
     * Different responses for AJAX vs traditional requests
     * 
     * @return void
     */
    private static function handleInvalidToken(): void {
        if (self::isAjaxRequest()) {
            // AJAX/fetch request - respond with JSON
            self::respondWithJson();
        } else {
            // Traditional form submission - clear POST and set error
            self::handleFormSubmission();
        }
    }
    
    /**
     * Respond with JSON for AJAX requests
     * 
     * @return void
     */
    private static function respondWithJson(): void {
        http_response_code(422);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'error' => 'CSRF token mismatch',
            'message' => 'Security token expired. Please refresh the page.',
            'csrf_error' => true
        ];
        
        echo json_encode($response);

        exit;
    }
    
    /**
     * Handle traditional form submission with invalid token
     * 
     * @return void
     */
    private static function handleFormSubmission(): void {

        // Preserve original POST data for debugging/logging
        $original_post = $_POST;
        $has_files = !empty($_FILES);
        // Preserve minimal route context so the current page can be rendered again.
        $route_context = [];
        foreach (['page', 'action', 'id', 'page-output'] as $key) {
            if (isset($_REQUEST[$key]) && $_REQUEST[$key] !== '') {
                $route_context[$key] = $_REQUEST[$key];
            }
        }
        if (!isset($route_context['id']) && isset($_REQUEST['data']['id'])) {
            $id_from_data = _absint($_REQUEST['data']['id']);
            if ($id_from_data > 0) {
                $route_context['id'] = $id_from_data;
            }
        }

        // Clear POST data to prevent further processing
        $_POST = [];
        
        // Clear FILES data for security
        if ($has_files) {
            // Clean up uploaded files from temp directory
            foreach ($_FILES as $file_field => $file_data) {
                if (is_array($file_data['tmp_name'])) {
                    foreach ($file_data['tmp_name'] as $tmp_file) {
                        if (is_file($tmp_file)) {
                            @unlink($tmp_file);
                        }
                    }
                } else if (is_file($file_data['tmp_name'])) {
                    @unlink($file_data['tmp_name']);
                }
            }
            $_FILES = [];
        }
        
        // Build user-facing message
        $error_message = $has_files
            ? 'Security token expired during file upload. Please refresh the page and try uploading again.'
            : 'Security token expired. Please try again. If the problem persists, please contact support.';
        MessagesHandler::addError($error_message);
        
        // Log the CSRF attempt for security monitoring
       
        $context = $has_files ? 'file upload' : 'form submission';
        $post_keys = implode(', ', array_keys($original_post));
        Logs::set('SECURITY', "CSRF token mismatch on {$context} from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " (POST keys: {$post_keys})",  'WARNING');
        // Invalidate submission as if it were a normal validation failure:
        // keep only routing keys and drop submitted payload/action flags.
        $_REQUEST = array_merge($_GET, $route_context);
    }
    
    /**
     * Add exempt route
     * 
     * @param string $route Route to exempt from CSRF protection
     * @return void
     */
    public static function addExemptRoute(string $route): void {
        if (!in_array($route, self::$exempt_routes)) {
            self::$exempt_routes[] = $route;
        }
    }
    
    /**
     * Get current exempt routes
     * 
     * @return array
     */
    public static function getExemptRoutes(): array {
        return self::$exempt_routes;
    }
}

// Usage example - call this early in your application bootstrap:
// CSRFProtection::validate();
