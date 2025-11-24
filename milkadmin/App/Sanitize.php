<?php
namespace App;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Data Sanitization Class
 * 
 * This class handles data sanitization for output to prevent security vulnerabilities.
 * Data is sanitized only when displayed, not when stored in the database.
 * 
 * FUNDAMENTAL RULE: Never sanitize data when saving to the database, only when displaying it!
 * In the database, we store the original data and use prepared statements for security.
 * 
 * The sanitization functions protect against:
 * - XSS (Cross-Site Scripting): Prevents injection and execution of malicious JavaScript
 * - Code Injection: Prevents insertion of PHP or other language code
 * - HTML Injection: Prevents insertion of malicious HTML tags
 * 
 * @example
 * ```php
 * // Example: Display a user-entered message safely
 * echo "Message: ";
 * echo Sanitize::html($message); // Converts characters like < > " ' to HTML entities
 * 
 * // DANGEROUS (NEVER DO THIS):
 * echo $message; // Could execute malicious JavaScript!
 * ```
 *
 * @see ObjectToForm For generating forms from object definitions
 * @package     App
 */

class Sanitize
{

    /**
     * Sanitizes input data based on its type
     * 
     * This method sanitizes input data according to the specified type and escapes
     * special characters to prevent XSS attacks. It's the primary method used by
     * the helper functions in functions.php.
     *
     * @example
     * ```php
     * // Sanitize a string
     * $safe_text = Sanitize::input($user_input);
     * 
     * // Sanitize an email address
     * $safe_email = Sanitize::input($email, 'email');
     * 
     * // Sanitize a URL
     * $safe_url = Sanitize::input($url, 'url');
     * 
     * // Sanitize an integer
     * $safe_id = Sanitize::input($id, 'int');
     * ```
     *
     * @param mixed $input The input data to sanitize
     * @param string $type The type of data ('string', 'email', 'url', 'int', 'float', 'html')
     * @return string The sanitized output
     */
    public static function input(mixed $input, string $type = 'string'): string {
        $input = self::getString($input);
        switch ($type) {
            case 'email':
                $sanitizedInput = filter_var($input, FILTER_SANITIZE_EMAIL);
                break;
            case 'url':
                $sanitizedInput = filter_var($input, FILTER_SANITIZE_URL);
                break;
            case 'int':
                $sanitizedInput = filter_var($input, FILTER_SANITIZE_NUMBER_INT);
                break;
            case 'float':
                $sanitizedInput = filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                break;
            case 'html':
                $sanitizedInput = self::html($input);
                break;
            default:
                $sanitizedInput = $input;
        }
        if ($type != 'html') {
            $sanitizedInput = htmlspecialchars($sanitizedInput, ENT_QUOTES, 'UTF-8');
        }
        return $sanitizedInput;
    }

    /**
     * Sanitizes HTML content while preserving safe HTML tags
     * 
     * Use this method when you need to display HTML content but want to remove
     * potentially harmful elements like JavaScript. This method removes script tags,
     * event handlers, and JavaScript URIs while preserving safe HTML formatting.
     *
     * @example
     * ```php
     * // Sanitize HTML content
     * $content = '<p>This is <strong>bold</strong> text.</p><script>alert("XSS");</script>';
     * $safe_html = Sanitize::html($content);
     * // Result: '<p>This is <strong>bold</strong> text.</p>'
     * 
     * // Dangerous attributes are also removed
     * $link = '<a href="javascript:alert(\'XSS\');">Click me</a>';
     * $safe_link = Sanitize::html($link);
     * // Result: '<a>Click me</a>'
     * ```
     *
     * @param string $html The HTML input to sanitize
     * @return string The sanitized HTML output with harmful elements removed
     */
    public static function html(mixed $html): string {
        $html = self::getString($html);
        // Remove script tags and their content
        
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

        // Remove event handler attributes like onclick, onload, etc.
        $html = preg_replace('/\s+on\w+="[^"]*"/i', '', $html);
        $html = preg_replace("/\s+on\w+='[^']*'/i", '', $html);
        $html = preg_replace('/\s+on\w+=\S*/i', '', $html);

        // Remove javascript: URIs
        $html = preg_replace('/\s+href\s*=\s*"javascript:[^"]*"/i', '', $html);
        $html = preg_replace("/\s+href\s*=\s*'javascript:[^']*'/i", '', $html);
        $html = preg_replace('/\s+href\s*=\s*javascript:[^\s>]*/i', '', $html);

        $html = preg_replace('/\s+src\s*=\s*"javascript:[^"]*"/i', '', $html);
        $html = preg_replace("/\s+src\s*=\s*'javascript:[^']*'/i", '', $html);
        $html = preg_replace('/\s+src\s*=\s*javascript:[^\s>]*/i', '', $html);

        // Remove iframe tags and their content
        $html = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $html);

        // remove php code
        $html = preg_replace('/<\?php.*\?>/', '', $html);
        return $html;
    }


    public static function getString(mixed $var): string {
        // Check if the variable can be converted to string
        if (is_scalar($var)) {
            // Scalar types: string, int, float, bool
            return (string)$var;
        } elseif (is_object($var)) {
            // Check for __toString method
            if (method_exists($var, '__toString')) {
                return (string)$var; // Or: return $var->__toString();
            }
            // Check for Stringable interface (PHP 8+)
            if ($var instanceof \Stringable) {
                return (string)$var;
            }
        } elseif (is_null($var)) {
            // Handle NULL explicitly if needed
            return '';
        }
        
        // Cannot be converted to string
        return '';
    }
}