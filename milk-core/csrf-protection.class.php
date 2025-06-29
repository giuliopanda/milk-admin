<?php
namespace MilkCore;
use MilkCore\Token;
use MilkCore\MessagesHandler;
use MilkCore\Permissions;

/**
 * CSRF Protection Middleware
 * 
 * Automatically validates CSRF tokens for all POST requests from authenticated users.
 * Provides different responses for AJAX/fetch requests vs traditional form submissions.
 * 
 * @package     MilkCore
 * @ignore
 */
class CSRFProtection {
    
    /**
     * Routes exempted from CSRF protection
     * 
     * @var array
     */
    private static array $exempt_routes = [
        '/api/webhooks/*',
        '/oauth/callback',
        '/api/public/*'
    ];
    
    /**
     * Main CSRF validation method
     * Call this early in your application bootstrap
     * 
     * @return void
     */
    public static function validate(): void {
        // Skip if user is guest (no CSRF token expected)
        if (Permissions::check('_user.is_guest')) {
            return;
        }
        
        // Skip if not a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        // Skip if route is exempted
        if (self::is_exempt_route()) {
            return;
        }
        
        // Get CSRF token from various sources
        $token = self::extract_token();
        
        // Validate token
        if (!Token::check_value($token, session_id())) {
            self::handle_invalid_token();
        }
    }
    
    /**
     * Extract CSRF token from request
     * Checks headers first, then POST data
     * 
     * @return string|null
     */
    private static function extract_token(): ?string {
        // Check custom header (for fetch requests)
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if ($token) {
            return $token;
        }
        // Check POST data (for traditional forms)
        $token_name = Token::get_token_name(session_id());
        $token = $_POST[$token_name] ?? null;
        
        return $token;
    }
    
    /**
     * Check if current route is exempted from CSRF protection
     * 
     * @return bool
     */
    private static function is_exempt_route(): bool {
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
    private static function is_ajax_request(): bool {
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
        
        // Check for custom CSRF header (indicates fetch request)
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return true;
        }
        
        // Check Accept header for JSON preference
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle invalid CSRF token
     * Different responses for AJAX vs traditional requests
     * 
     * @return void
     */
    private static function handle_invalid_token(): void {
        if (self::is_ajax_request()) {
            // AJAX/fetch request - respond with JSON
            self::respond_with_json();
        } else {
            // Traditional form submission - clear POST and set error
            self::handle_form_submission();
        }
    }
    
    /**
     * Respond with JSON for AJAX requests
     * 
     * @return void
     */
    private static function respond_with_json(): void {
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
    private static function handle_form_submission(): void {
        // Clear POST data to prevent further processing
        $_POST = [];
        
        // Add error message using MessagesHandler
        MessagesHandler::add_error('Security token expired. Please try again.');
        
        // Log the CSRF attempt for security monitoring
        if (class_exists('MilkCore\Logs')) {
            Logs::set('security', 'WARNING', 'CSRF token mismatch from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    }
    
    /**
     * Add exempt route
     * 
     * @param string $route Route to exempt from CSRF protection
     * @return void
     */
    public static function add_exempt_route(string $route): void {
        if (!in_array($route, self::$exempt_routes)) {
            self::$exempt_routes[] = $route;
        }
    }
    
    /**
     * Get current exempt routes
     * 
     * @return array
     */
    public static function get_exempt_routes(): array {
        return self::$exempt_routes;
    }
}

// Usage example - call this early in your application bootstrap:
// CSRFProtection::validate();