<?php
namespace MilkCore;
use MilkCore\Hooks;
use MilkCore\Route;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Message Handling System
 * 
 * This class manages various types of messages throughout the application,
 * including form validation errors, success notifications, and field-specific
 * validation errors. It provides a centralized way to collect and display
 * messages to users.
 * 
 * @example
 * ```php
 * // Add error messages
 * MessagesHandler::add_error('Invalid email format', 'email');
 * MessagesHandler::add_error('Please fill out all required fields');
 * 
 * // Add success message
 * MessagesHandler::add_success('Your profile has been updated successfully');
 * 
 * // Mark a field as invalid without a specific message
 * MessagesHandler::add_field_error('password');
 * 
 * // Display messages in a template
 * $errors = MessagesHandler::get_error_messages();
 * $success = MessagesHandler::get_success_messages();
 * ```
 *
 * @package     MilkCore
 */

class MessagesHandler {
    /**
     * Storage for error messages
     * 
     * Associative array where keys can be field names and values are error messages.
     * Messages without a specific field are stored with numeric keys.
     * 
     * @var array
     */
    private static array $error_messages = [];
    
    /**
     * Storage for success messages
     * 
     * Array of success messages to display to the user.
     * 
     * @var array
     */
    private static array $success_messages = [];
    
    /**
     * List of invalid field names
     * 
     * Used to track which form fields have validation errors.
     * 
     * @var array
     */
    private static array $invalid_fields = [];

    /**
     * Adds an error message
     * 
     * This method adds an error message to the collection. If a field name is provided,
     * the message is associated with that field and the field is marked as invalid.
     * 
     * @example
     * ```php
     * // Add a general error message
     * MessagesHandler::add_error('An unexpected error occurred');
     * 
     * // Add a field-specific error message
     * MessagesHandler::add_error('Email is already in use', 'email');
     * 
     * // Add an error for multiple fields
     * MessagesHandler::add_error('Passwords do not match', ['password', 'confirm_password']);
     * ```
     *
     * @param string $message The error message to display
     * @param mixed $field Field name or array of field names to associate with the error
     * @return void
     */
    public static function add_error(string $message, mixed $field = ''): void {
       
        if (is_array($field)) {
            foreach ($field as $f) {
                self::$invalid_fields[] = $f;
            }
            $field = implode("|",$field);
        }
        if ($field != '') {
            self::$error_messages[$field] = $message;
        } else {
            self::$error_messages[] = $message;
        }
        if ($field) {
            self::$invalid_fields[] = $field;
        }
    }

    /**
     * Adds a success message
     * 
     * This method adds a success message to be displayed to the user.
     * 
     * @example
     * ```php
     * MessagesHandler::add_success('Your changes have been saved');
     * ```
     *
     * @param string $message The success message to display
     * @return void
     */
    public static function add_success(string $message): void {
        self::$success_messages[] = $message;
    }

    /**
     * Marks a field as invalid without adding a specific error message
     * 
     * This method is useful when you want to highlight a field as invalid
     * but the error message is handled elsewhere or is not needed.
     * 
     * @example
     * ```php
     * // Mark a field as invalid
     * MessagesHandler::add_field_error('username');
     * ```
     *
     * @param string $field The name of the field to mark as invalid
     * @return void
     */
    public static function add_field_error(string $field): void {
        if (!in_array($field, self::$invalid_fields)) {
            self::$invalid_fields[] = $field;
        }
    }

    /**
     * Checks if there are any error messages or invalid fields
     * 
     * This method determines if there are any validation errors that need to be displayed.
     * 
     * @example
     * ```php
     * if (MessagesHandler::has_errors()) {
     *     // Don't proceed with form processing
     *     return;
     * }
     * ```
     *
     * @return bool True if there are error messages or invalid fields, false otherwise
     */
    public static function has_errors(): bool {
        return !empty(self::$error_messages) || !empty(self::$invalid_fields);
    }


    /**
     * Gets the CSS class for invalid fields
     * 
     * This method returns the appropriate CSS class for form fields that have validation errors.
     * It's typically used in templates to highlight invalid fields.
     * 
     * @example
     * ```php
     * // In a form template
     * $invalidClass = MessagesHandler::get_invalid_class('email');
     * echo '<input type="email" name="email" class="form-control ' . $invalidClass . '">';
     * ```
     *
     * @param string $field_name The name of the field to check
     * @return string The CSS class for invalid fields or an empty string if the field is valid
     */
    public static function get_invalid_class(string $field_name): string {
        return in_array($field_name, self::$invalid_fields) ? 'is-invalid js-focus-remove-is-invalid' : '';
    }

    /**
     * Outputs error and success messages
     * 
     * This method directly outputs HTML for both error and success messages.
     * It's typically used in templates to display all messages at once.
     * 
     * @example
     * ```php
     * // In a template file
     * MessagesHandler::display_messages();
     * ```
     *
     * @return void
     */
    public static function display_messages(): void {
        $html = '';
        if (!empty(self::$error_messages)) {
            $html .= self::get_error_alert();
        }
        if (!empty(self::$success_messages)) {
            $html .= self::get_success_alert();
        }
        _pt($html);
    }
   
    /**
     * Gets HTML for error messages in an alert box
     * 
     * This method generates a Bootstrap alert box containing all error messages.
     * Each error is wrapped in a div with a data-field attribute for JavaScript targeting.
     * 
     * @example
     * ```php
     * // Get and display error messages
     * $errorHtml = MessagesHandler::get_error_alert();
     * if (!empty($errorHtml)) {
     *     echo $errorHtml;
     * }
     * ```
     *
     * @return string HTML for the error alert box or an empty string if there are no errors
     */
    public static function get_error_alert(): string {
        if (empty(self::$error_messages)) {
            return '';
        }

        $html = '<div class="alert alert-danger js-alert-container" role="alert">';
        foreach (self::$error_messages as $key => $error) {
            $html .= '<div data-field="' . _r($key). '">' . _r($error) . '</div>';
        }
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Gets all error messages
     * 
     * This method returns the array of all error messages, which can be used
     * for custom error handling or display.
     * 
     * @example
     * ```php
     * // Get all error messages
     * $errors = MessagesHandler::get_errors();
     * foreach ($errors as $field => $message) {
     *     echo "Error in {$field}: {$message}\n";
     * }
     * ```
     *
     * @return array Array of error messages
     */
    public static function get_errors(): array {
        return self::$error_messages;
    }

    /**
     * Converts all error messages to a single string
     * 
     * This method combines all error messages into a single string, with either
     * HTML line breaks or newlines between messages.
     * 
     * @example
     * ```php
     * // Get errors as a string with HTML breaks
     * $errorString = MessagesHandler::errors_to_string(true);
     * echo $errorString;
     * 
     * // Get errors as a plain text string
     * $plainErrors = MessagesHandler::errors_to_string();
     * mail('admin@example.com', 'Form Errors', $plainErrors);
     * ```
     *
     * @param bool $br Whether to use HTML line breaks (true) or newlines (false)
     * @return string All error messages combined into a single string
     */
    public static function errors_to_string($br = false): string {
        $br = $br ? '<br>' : "\n";
        return implode($br, self::$error_messages);
    }

    /**
     * Gets HTML for success messages in an alert box
     * 
     * This method generates a Bootstrap alert box containing all success messages.
     * Each message is wrapped in a div for consistent styling.
     * 
     * @example
     * ```php
     * // Get and display success messages
     * $successHtml = MessagesHandler::get_success_alert();
     * if (!empty($successHtml)) {
     *     echo $successHtml;
     * }
     * ```
     *
     * @return string HTML for the success alert box or an empty string if there are no success messages
     */
    public static function get_success_alert(): string {
        if (empty(self::$success_messages)) {
            return '';
        }

        $html = '<div class="alert alert-success" role="alert">';
        foreach (self::$success_messages as $message) {
            $html .= '<div>' . _r($message) . '</div>';
        }
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Gets all success messages
     * 
     * This method returns the array of all success messages, which can be used
     * for custom success handling or display.
     * 
     * @example
     * ```php
     * // Get all success messages
     * $successMessages = MessagesHandler::get_success_messages();
     * foreach ($successMessages as $message) {
     *     echo "Success: {$message}\n";
     * }
     * ```
     *
     * @return array Array of success messages
     */
    public static function get_success_messages(): array {
        return self::$success_messages;
    }

    /**
     * Converts all success messages to a single string
     * 
     * This method combines all success messages into a single string, with either
     * HTML line breaks or newlines between messages.
     * 
     * @example
     * ```php
     * // Get success messages as a string with HTML breaks
     * $successString = MessagesHandler::success_to_string(true);
     * echo $successString;
     * ```
     *
     * @param bool $br Whether to use HTML line breaks (true) or newlines (false)
     * @return string All success messages combined into a single string
     */
    public static function success_to_string($br = false): string {
        $br = $br ? '<br>' : "\n";
        return implode($br, self::$success_messages);
    }

    /**
     * Resets all error messages and invalid fields
     * 
     * This method clears all error messages and invalid field markers.
     * It's typically used when you want to start fresh with a new validation.
     * 
     * @example
     * ```php
     * // Reset all error messages before new validation
     * MessagesHandler::reset();
     * ```
     *
     * @return void
     */
    public static function reset(): void {
        self::$error_messages = [];
        self::$invalid_fields = [];
    }
}
