<?php
use App\{Sanitize, Lang};
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Global helper functions for sanitization and translation of variables
 * 
 * These functions provide a simple interface for outputting sanitized content
 * in different contexts. The naming convention follows these patterns:
 * - _p*: Print functions that output directly
 * - _r*: Return functions that return sanitized values
 * - *t: Functions that include translation (e.g., _pt, _rt)
 * - *h: Functions that handle HTML content safely
 * - *js: Functions for JavaScript context
 *
 * @package     App
 */

/**
 * Sanitizes and returns a string value
 * 
 * This function is used to safely handle user input by escaping special characters.
 * It's suitable for general string sanitization when the output context is plain text.
 *
 * @param mixed $var The variable to sanitize
 * @return string The sanitized string, or empty string if input is not scalar
 */
 function _r($var) {
    if (!is_scalar($var)) return '';
    return Sanitize::input($var, 'string');
 }

 /**
 * Returns sanitized and translated string
 * 
 * This function first translates the string using the current language settings
 * and then sanitizes it for safe output. It's ideal for user-facing text that
 * needs to support multiple languages.
 *
 * @param mixed $var The string to translate and sanitize
 * @return string The translated and sanitized string, or empty string if input is not scalar
 */
function _rt($var) {
    if (!is_scalar($var)) return '';
    $var = Lang::get($var, ($_REQUEST['page'] ?? ''));
    return Sanitize::html($var);
 }

 /**
 * Returns HTML-escaped string
 * 
 * Use this function when you need to output content within HTML. It converts
 * special characters to HTML entities to prevent XSS attacks.
 *
 * @param mixed $var The string to escape
 * @return string The HTML-escaped string, or empty string if input is not scalar
 */
 function _rh($var) {
    if (!is_scalar($var)) return '';
    return Sanitize::html($var);
 }

 /**
 * Esegue un preg_replace per eliminare i caratteri speciali
 * Aggiunge un carattere se il primo è un numero
 */
function _raz($var) {
    $var = preg_replace('/[^0-9a-zA-Z_]/', '', $var);
    // se il primo valore è un numero ci aggiungo una lettera casuale prima
    if (is_numeric(substr($var, 0, 1))) {
        $rand = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 1);
        $var = $rand.$var;
    }
    return $var;
}

/**
 * Outputs a sanitized string
 * 
 * This function is a convenience wrapper that sanitizes and immediately outputs
 * a string. It's equivalent to `echo _r($var)` but more concise.
 *
 * @param mixed $var The string to sanitize and output
 * @return void
 */
function _p($var) {
    if (!is_scalar($var)) return '';
    echo Sanitize::input($var, 'string');
}

/**
 * Outputs a translated and sanitized string
 * 
 * This function combines translation and sanitization for output. It's commonly
 * used for user interface text that needs to be both translated and safe to display.
 *
 * @param mixed $var The string to translate and output
 * @return void
 */
function _pt($var) {
    if (!is_scalar($var)) return '';
    $var = Lang::get($var, ($_REQUEST['page'] ?? ''));
    echo Sanitize::html($var);
}

/**
 * Outputs an HTML-escaped string
 * 
 * Use this function when you need to output content directly within HTML.
 * It's a shortcut for `echo _rh($var)`.
 *
 * @param mixed $var The string to escape and output
 * @return void
 */
function _ph($var) {
    if (!is_scalar($var)) return '';
    echo Sanitize::html($var);
}

/**
 * Outputs a value as a JavaScript literal
 * 
 * This function safely outputs values within JavaScript code blocks by using
 * json_encode() to properly escape the content. It handles all JavaScript data types
 * including strings, numbers, arrays, and objects.
 *
 * @example
 * <script>
 * var userName = <?php _pjs($userName); ?>;
 * var userData = <?php _pjs($userDataArray); ?>;
 * </script>
 *
 * @param mixed $var The value to output as JavaScript
 * @return void
 */
function _pjs($var) {
    echo json_encode($var);
}

/**
 * Sanitizes a string for use in HTML IDs, classes, or other identifiers
 * 
 * This function removes all non-alphanumeric characters and ensures the
 * result starts with a letter. It's useful for creating safe HTML identifiers
 * from user input.
 *
 * @param string $var The string to sanitize
 * @return string The sanitized string with only alphanumeric characters and underscores,
 *                starting with a letter
 */
function _paz($var) {
    $var = preg_replace('/[^0-9a-zA-Z_]/', '', $var);
    // se il primo valore è un numero ci aggiungo una lettera casuale prima
    if (is_numeric(substr($var, 0, 1))) {
        $rand = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 1);
        $var = $rand.$var;
    }
    echo $var;
}

/**
 * Sanitizes and returns a positive integer
 * 
 * This function ensures the returned value is always a non-negative integer.
 * It's particularly useful for sanitizing IDs, counts, and other numeric values
 * that should never be negative.
 *
 * @param mixed $var The value to sanitize
 * @return int The sanitized positive integer, or 0 if conversion fails
 */
function _absint($var) {
    if (!is_scalar($var)) {
        return 0;
    }
    return abs(intval($var));
}

/**
 * Retrieves a value from an object or array with fallback support
 * 
 * This helper function provides a consistent way to access values from different
 * data structures, including objects with magic getters or custom get_value methods.
 * It's particularly useful for working with model objects that might implement
 * custom value retrieval logic.
 *
 * @param object|array $class The object or array to get the value from
 * @param string $property The property name or array key to retrieve
 * @return mixed The value of the property, or null if not found
 * 
 * @example
 * // Works with arrays
 * $data = ['name' => 'John'];
 * $name = getVal($data, 'name'); // Returns 'John'
 * 
 * // Works with objects
 * $user = new User(); // Assuming User has a 'name' property
 * $name = getVal($user, 'name');
 * 
 * // Falls back to getValue() method if it exists
 * $value = getVal($someObject, 'custom_property');
 */
function getVal($class, $property) {
    if ($class instanceof \App\Abstracts\AbstractModel) {
        // Use getFormattedValue for proper date/array/list formatting in tables
        if (method_exists($class, 'getFormattedValue')) {
            return $class->getFormattedValue($property);
        }
        return $class->$property;
    } else if (is_array($class) && array_key_exists($property, $class)) {
        return $class[$property];
    } else if (property_exists($class, $property)) {
        return $class->$property;
    } else {
        return null;
    }
}

/**
 * Converts an object or array of data into a format suitable for MySQL
 * 
 * This function handles conversion of various data structures (objects, arrays, models)
 * into a format that can be used with MySQL queries. It supports model objects
 * with a toMysqlArray() method and falls back to default data when needed.
 *
 * @param mixed $obj The data to convert (model object, stdClass, or array)
 * @param array $default_data Default data to use as a base (typically $_REQUEST or $_POST)
 * @return array The converted data ready for MySQL operations
 * 
 * @example
 * // With a model object
 * $user = new User($id);
 * $data = toMysqlArray($user, $_POST);
 * 
 * // With a regular array
 * $data = toMysqlArray(['name' => 'John', 'age' => 30]);
 */
function toMysqlArray(mixed $obj, array $default_data = []) {
    $array_to_save = $default_data;
    if (method_exists($obj, 'toMysqlArray')) {
        $array_to_save = $obj->toMysqlArray();
    } else if (is_object($obj)) {
        $array_to_save = (array) $obj;
    } else if (is_array($obj)) {
        $array_to_save = $obj;
    }
    return $array_to_save;
}
