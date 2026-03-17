<?php

declare(strict_types=1);

namespace App;

use Stringable;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Output sanitization / escaping utilities.
 *
 * IMPORTANT:
 * This class is meant for OUTPUT contexts, not for database storage.
 *
 * Correct rule:
 * - store original values in the database
 * - use prepared statements for SQL safety
 * - escape or sanitize only when rendering output
 *
 * Main responsibilities:
 * - Escape plain text for HTML output
 * - Escape values for HTML attributes
 * - Provide a basic HTML sanitizer for limited trusted markup
 * - Encode values safely for JavaScript contexts
 * - Normalize identifiers for HTML id/class/name-like usage
 *
 * Notes:
 * - text() and attr() escape output with htmlspecialchars()
 * - html() allows limited HTML but removes obviously dangerous constructs
 * - js() returns a JSON-safe JavaScript literal
 *
 * This class is intentionally lightweight and framework-friendly.
 * If in the future you want richer HTML sanitization, you may replace
 * the internals of html() with a DOM-based or whitelist-based sanitizer.
 */
final class Sanitize
{
    /**
     * Default charset used by htmlspecialchars().
     */
    private const CHARSET = 'UTF-8';

    /**
     * htmlspecialchars flags used for safe text output.
     */
    private const HTML_FLAGS = ENT_QUOTES | ENT_SUBSTITUTE;

    /**
     * JSON flags used for JavaScript-safe output.
     */
    private const JSON_FLAGS =
        JSON_HEX_TAG
        | JSON_HEX_APOS
        | JSON_HEX_AMP
        | JSON_HEX_QUOT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES;

    /**
     * Backward-compatible entry point.
     *
     * Supported types:
     * - string: escaped plain text
     * - text: escaped plain text
     * - attr: escaped HTML attribute
     * - email: sanitized email then escaped
     * - url: sanitized URL then escaped
     * - int: sanitized integer-like string then escaped
     * - float: sanitized float-like string then escaped
     * - html: basic sanitized HTML, not fully escaped
     * - js: JavaScript-safe JSON literal
     * - identifier: safe identifier for id/class/name-like usage
     */
    public static function input(mixed $input, string $type = 'string'): string
    {
        return match ($type) {
            'string', 'text' => self::text($input),
            'attr' => self::attr($input),
            'email' => self::email($input),
            'url' => self::url($input),
            'int' => self::int($input),
            'float' => self::float($input),
            'html' => self::html($input),
            'js' => self::js($input),
            'identifier' => self::identifier($input),
            default => self::text($input),
        };
    }

    /**
     * Escape plain text for safe HTML output.
     *
     * Use this for:
     * - normal text nodes
     * - most template output
     * - default safe output
     */
    public static function text(mixed $value): string
    {
        return htmlspecialchars(
            self::string($value),
            self::HTML_FLAGS,
            self::CHARSET
        );
    }

    /**
     * Escape a value for safe HTML attribute output.
     *
     * In this lightweight implementation it is intentionally the same as text().
     * Use it when outputting values inside quoted HTML attributes.
     */
    public static function attr(mixed $value): string
    {
        return htmlspecialchars(
            self::string($value),
            self::HTML_FLAGS,
            self::CHARSET
        );
    }

    /**
     * Sanitize an email-like value, then escape it for HTML output.
     *
     * This does not validate that the email is formally correct.
     * It only filters obvious invalid characters and escapes the result.
     */
    public static function email(mixed $value): string
    {
        $string = self::string($value);
        $sanitized = filter_var($string, FILTER_SANITIZE_EMAIL);

        return self::text(is_string($sanitized) ? $sanitized : '');
    }

    /**
     * Sanitize a URL-like value and escape it for HTML output.
     *
     * NOTE:
     * This method does NOT perform URL encoding.
     *
     * If you are inserting a value into a query string parameter,
     * you must still use urlencode() or rawurlencode().
     *
     * Example:
     * $url = "search.php?q=" . urlencode($query);
     * echo '<a href="' . Sanitize::attr($url) . '">';
     */
    public static function url(mixed $value): string
    {
        $string = self::string($value);
        $sanitized = filter_var($string, FILTER_SANITIZE_URL);

        return self::text(is_string($sanitized) ? $sanitized : '');
    }

    /**
     * Sanitize an integer-like string, then escape it for HTML output.
     */
    public static function int(mixed $value): string
    {
        $string = self::string($value);
        $sanitized = filter_var($string, FILTER_SANITIZE_NUMBER_INT);

        return self::text(is_string($sanitized) ? $sanitized : '');
    }

    /**
     * Sanitize a float-like string, then escape it for HTML output.
     */
    public static function float(mixed $value): string
    {
        $string = self::string($value);
        $sanitized = filter_var(
            $string,
            FILTER_SANITIZE_NUMBER_FLOAT,
            FILTER_FLAG_ALLOW_FRACTION
        );

        return self::text(is_string($sanitized) ? $sanitized : '');
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


     /**
     * Basic HTML sanitizer.
     *
     * This method is meant for limited HTML output where some markup is allowed.
     * It does NOT perform full HTML policy enforcement like a dedicated library.
     *
     * It removes:
     * - <script> blocks
     * - <iframe>, <object>, <embed>, <style>, <link>, <meta> tags
     * - inline event handlers (onclick, onload, ...)
     * - javascript:, vbscript:, data: URIs in common attributes
     * - PHP tags
     *
     * Use this only when you deliberately want to allow HTML.
     * For normal text, always use text().
     */
    public static function safeHtml(mixed $html): string
    {
        $html = self::string($html);

        if ($html === '') {
            return '';
        }

        // Remove HTML comments (used to obfuscate XSS)
        $html = preg_replace('#<!--.*?-->#s', '', $html) ?? '';

        // Remove script/style-like dangerous blocks
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? '';
        $html = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $html) ?? '';
        $html = preg_replace('#<object\b[^>]*>.*?</object>#is', '', $html) ?? '';
        $html = preg_replace('#<embed\b[^>]*>.*?</embed>#is', '', $html) ?? '';
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? '';

        // Remove standalone dangerous tags
        $html = preg_replace('#</?(?:link|meta)\b[^>]*>#is', '', $html) ?? '';

        // Remove PHP tags
        $html = preg_replace('#<\?(?:php|=)?.*?\?>#is', '', $html) ?? '';

        // Remove inline event handlers
        $html = preg_replace('/\s+on[a-z0-9_-]+\s*=\s*"[^"]*"/i', '', $html) ?? '';
        $html = preg_replace("/\s+on[a-z0-9_-]+\s*=\s*'[^']*'/i", '', $html) ?? '';
        $html = preg_replace('/\s+on[a-z0-9_-]+\s*=\s*[^\s>]+/i', '', $html) ?? '';

        // Remove style attribute
        $html = preg_replace('/\s+style\s*=\s*"[^"]*"/i', '', $html) ?? '';
        $html = preg_replace("/\s+style\s*=\s*'[^']*'/i", '', $html) ?? '';
        $html = preg_replace('/\s+style\s*=\s*[^\s>]+/i', '', $html) ?? '';

        // Remove dangerous protocols
        $dangerous = '(?:javascript|vbscript|data)\s*:';

        $html = preg_replace('/\s+href\s*=\s*"'.$dangerous.'[^"]*"/i', '', $html) ?? '';
        $html = preg_replace("/\s+href\s*=\s*'".$dangerous."[^']*'/i", '', $html) ?? '';
        $html = preg_replace('/\s+href\s*=\s*'.$dangerous.'[^\s>]*/i', '', $html) ?? '';

        $html = preg_replace('/\s+src\s*=\s*"'.$dangerous.'[^"]*"/i', '', $html) ?? '';
        $html = preg_replace("/\s+src\s*=\s*'".$dangerous."[^']*'/i", '', $html) ?? '';
        $html = preg_replace('/\s+src\s*=\s*'.$dangerous.'[^\s>]*/i', '', $html) ?? '';

        // Final whitelist
        $allowed = '<a><abbr><b><blockquote><br><code><div><em><i><li><ol><p><span><strong><sub><sup><u><ul>';
        $html = strip_tags($html, $allowed);

        return $html;
    }

    /**
     * Encode a value for safe output inside JavaScript.
     *
     * Returns a JSON literal:
     * - string => quoted JS string
     * - array => JS array/object literal
     * - bool/int/float/null => corresponding literal
     *
     * Example:
     * <script>
     * const name = <?= Sanitize::js($name) ?>;
     * </script>
     */
    public static function js(mixed $value): string
    {
        $json = json_encode($value, self::JSON_FLAGS);

        return is_string($json) ? $json : 'null';
    }

    /**
     * Create a safe identifier for HTML id/class/name-like values.
     *
     * Rules:
     * - keep only letters, numbers and underscores
     * - if empty, return the prefix
     * - if it starts with a digit, prepend the prefix
     */
    public static function identifier(mixed $value, string $prefix = 'id_'): string
    {
        $identifier = self::string($value);
        $identifier = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier) ?? '';

        if ($identifier === '') {
            return $prefix;
        }

        if (preg_match('/^[0-9]/', $identifier) === 1) {
            return $prefix . $identifier;
        }

        return $identifier;
    }

    /**
     * Convert a mixed value to string in a predictable way.
     *
     * Accepted:
     * - scalar values
     * - Stringable objects
     * - null => empty string
     *
     * Rejected:
     * - arrays
     * - non-stringable objects
     * - resources
     *
     * This method is intentionally strict to avoid accidental output of
     * complex values in unsafe or confusing forms.
     */
    public static function string(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Backward-compatible alias for old code.
     */
    public static function getString(mixed $value): string
    {
        return self::string($value);
    }
}