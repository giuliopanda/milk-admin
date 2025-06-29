<?php
namespace MilkCore;
use MilkCore\Form;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Form Generator from Object Definitions
 * 
 * This class provides methods to automatically generate forms from object definitions.
 * It simplifies form creation by converting object properties into appropriate form fields
 * based on their types and validation rules.
 * 
 * @example
 * ```php
 * // Define object properties
 * $fields = [
 *     'name' => [
 *         'type' => 'string',
 *         'label' => 'Full Name',
 *         'form-params' => ['required' => true]
 *     ],
 *     'email' => [
 *         'type' => 'email',
 *         'label' => 'Email Address',
 *         'form-params' => ['required' => true]
 *     ],
 *     'age' => [
 *         'type' => 'int',
 *         'label' => 'Age',
 *         'form-params' => ['min' => 18]
 *     ]
 * ];
 * 
 * // Generate the form
 * echo ObjectToForm::start('user_form');
 * foreach ($fields as $field_name => $rule) {
 *     echo ObjectToForm::row($rule, $_POST[$field_name] ?? '');
 * }
 * echo ObjectToForm::end('Save User');
 * ```
 *
 * @package     MilkCore
 */

class ObjectToForm
{

    /**
     * Generates a token name for a form
     * 
     * This method creates a standardized token name for CSRF protection
     * based on the page name.
     *
     * @param string $page The page identifier
     * @return string The generated token name
     */
    public static function get_token_name($page) { 
        return 'token_'.$page;
    }

    /**
     * Generates the opening HTML for a form
     * 
     * This method creates the opening form tag with proper attributes,
     * including CSRF token, action, and redirect URLs.
     * 
     * @example
     * ```php
     * // Basic form start
     * echo ObjectToForm::start('user_form');
     * 
     * // Form with success and error URLs
     * echo ObjectToForm::start(
     *     'product_form',
     *     Route::url(['page' => 'products']),
     *     Route::url(['page' => 'product_form', 'error' => 1])
     * );
     * 
     * // Form with custom attributes
     * echo ObjectToForm::start('contact_form', '', '', 'send', [
     *     'class' => 'contact-form',
     *     'enctype' => 'multipart/form-data'
     * ]);
     * ```
     *
     * @param string $page The page identifier for the form
     * @param string $url_success The URL to redirect to on successful submission
     * @param string $url_error The URL to redirect to on error (defaults to current URL)
     * @param string $action_save The action name for form processing (default: 'save')
     * @param array $attributes Additional form attributes
     * @return string The HTML for the form opening tag and hidden fields
     */
    public static function start(string $page, string $url_success = '', string $url_error = '', string $action_save = 'save', array $attributes = [] ): string {
        if ($url_error == '') {
            $url_error = Route::current_url();
        }
        if (!isset($attributes['id'])) {
            $attributes['id'] = 'form' . $page;
        } 
        $attributes['class'] = (array_key_exists('class', $attributes)) ? $attributes['class']." ".'needs-validation js-needs-validation' : 'needs-validation js-needs-validation';
        $html = '<form method="post" novalidate action="' . Route::url('?page=' . _r($page)) . '"' . Form::attr($attributes) . '>';
        $html .= Token::input('token_' . $page);
        $html .= '<input id="action" type="hidden" name="action" value="' . _r($action_save) . '">';
        $html .= '<input id="urlError" type="hidden" name="url_error" value="' . _r($url_error) . '">';
        $html .= '<input id="urlSuccess" type="hidden" name="url_success" value="' .  _r($url_success). '">';
        return $html;
    }


    /**
     * Generates a form row based on a field rule
     * 
     * This method creates a complete form field with proper wrapping elements
     * based on the field type and rules.
     * 
     * @example
     * ```php
     * // Generate a text input field
     * $rule = [
     *     'name' => 'username',
     *     'type' => 'string',
     *     'label' => 'Username',
     *     'form-params' => ['required' => true]
     * ];
     * echo ObjectToForm::row($rule, $currentValue);
     * ```
     *
     * @param array $rule The field definition rule
     * @param mixed $value The current value of the field
     * @return string The HTML for the complete form field
     */
    public static function row($rule, $value) {   
        $input = self::get_input($rule, $value);
        $type = $rule['form-type'] ?? $rule['type'];
        if ($type == 'hidden') {
            return $input;
        } if ($type == 'checkbox') {
            return '<div class="form-check mb-3">' . $input . '</div>';
        }
        return '<div class="mb-3">' . $input .'</div>';
    }


    /**
     * Generates the appropriate input field based on field type
     * 
     * This method creates the actual form input element based on the field type
     * specified in the rule. It handles various input types including text, number,
     * email, select, textarea, checkbox, etc.
     * 
     * @example
     * ```php
     * // Generate a text input
     * $textRule = ['name' => 'title', 'type' => 'string', 'label' => 'Title'];
     * $textInput = ObjectToForm::get_input($textRule, 'Current Title');
     * 
     * // Generate a select dropdown
     * $selectRule = [
     *     'name' => 'category',
     *     'type' => 'select',
     *     'label' => 'Category',
     *     'options' => ['1' => 'Books', '2' => 'Electronics']
     * ];
     * $selectInput = ObjectToForm::get_input($selectRule, '2');
     * ```
     *
     * @param array $rule The field definition rule
     * @param mixed $value The current value of the field
     * @return string The HTML for the input field
     */
    public static function get_input(array $rule, $value) {
        $type = $rule['form-type'] ?? $rule['type'];
        $rule['label'] = $rule['label'] ?? $rule['name'];
        $rule['label'] = $rule['form-label'] ?? $rule['label'];
        $form_params = $rule['form-params'] ?? [];
        $rule['value'] = $rule['value'] ?? '';
        switch ($type) {
            case 'hidden':
                return Form::input('hidden', $rule['name'], $rule['label'], $value, $form_params, true);
                break;
            case 'string':
                return Form::input('text', $rule['name'], $rule['label'], $value, $form_params, true);
                break;
            case 'int':
                return Form::input('text', $rule['name'], $rule['label'], $value, $form_params, true);
                break;
            case 'float':
                return Form::input('text', $rule['name'], $rule['label'], $value, $form_params, true);
                 break;
            case 'text':
            case 'textarea':
                $form_params['rows'] = $form_params['rows'] ?? 7;
                return Form::textarea($rule['name'], $rule['label'], $value, $form_params['rows'], $form_params, true);
                break;
           case 'password':
               return Form::input('password', $rule['name'], $rule['label'], $value, $form_params, true);
               break;
           case 'email':
                return Form::input('email', $rule['name'], $rule['label'], $value, $form_params, true);
                 break;
            case 'url':
                return Form::input('url', $rule['name'], $rule['label'], $value, $form_params, true);
                 break;
            case 'date':
                return Form::input('date', $rule['name'], $rule['label'], $value, $form_params, true);
                break;
            case 'datetime':
                return Form::input('datetime-local', $rule['name'], $rule['label'], $value, $form_params, true);
                break;
             case 'time':
                return Form::input('time', $rule['name'], $rule['label'], $value, $form_params, true);
                break;
             case 'select':
             case 'list':
                $rule['options'] = $rule['options'] ?? [];         
                return Form::select($rule['name'], $rule['label'],  $rule['options'], $value, $form_params, true);
                break;
            case 'enum':
                $rule['options'] = $rule['options'] ?? [];
                $rule_options = [];
                foreach ($rule['options'] as $value) {
                    $rule_options[$value] = $value;
                }
                return Form::select($rule['name'], $rule['label'], $rule_options, $value, $form_params, true);
                break;
             case 'checkbox':
                return Form::checkbox($rule['name'], $rule['label'],  $rule['value'], ($value == $rule['value']), $form_params, true);
                break;
            case 'checkboxes':
                return Form::checkboxes($rule['name'], $rule['options'],  $value, false, $form_params, [], true);
                break;
            case 'radios':
                return Form::radios($rule['name'], $rule['options'],  $value, false, $form_params, [], true);
                break;
            case 'file':
                return Get::theme_plugin('upload-files', ['name'=>$rule['name'], 'label'=> $rule['label'], 'value'=>$value, 'options'=>$rule['options'] ?? [], 'upload_name' => _raz($rule['name'])] );
                break;
            case 'beauty-select':
                return Get::theme_plugin('beauty-select', ['id' => $rule['name'], 'label' => $rule['label'], 'value' => $value, 'options' => $rule['options'] ?? [], 'isMultiple' => $rule['isMultiple'] ?? false, 'floating'=> $rule['isMultiple'] ?? true ]);
                break;
            case 'editor':
                return Get::theme_plugin('editor', ['id' => $rule['name'], 'name' => $rule['name'], 'label' => $rule['label'], 'value' => $value, 'height' => '200px']);
                break;
            default:
                // custom input
                return Hooks::run('form-'.$type, $rule, $value);
        }
    }

    public static function submit(string $button_text = 'Save', array $button_attr = []): string {
        $button_attr['class'] = (array_key_exists('class', $button_attr)) ? $button_attr['class']." ".'btn btn-primary' : 'btn btn-primary';
        $button =  '<button type="submit"';
        $button .= Form::attr($button_attr);
        $button .= ' >'._r($button_text).'</button>';
        return $button;
    }


    // stampo la parte finale del form
    public static function end(): string {
        return '</form>';
    }
}