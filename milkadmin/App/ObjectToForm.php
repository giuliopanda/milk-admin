<?php
namespace App;
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
 * @package     App
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
    public static function getTokenName($page) { 
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
     * echo ObjectToForm::start('contact_form', 'send', [
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
    public static function start(string $page, string $action_save = 'save', array $attributes = [], bool $json = false, array $custom_data = [] ): string {

        if (!isset($attributes['id'])) {
            $attributes['id'] = 'form' . $page;
        }
        if ($json) {
             $attributes['data-ajax-submit'] = 'true';
        }
        $attributes['class'] = (array_key_exists('class', $attributes)) ? $attributes['class']." ".'needs-validation js-needs-validation' : 'needs-validation js-needs-validation';
        $html = '<form method="post" novalidate action="' . Route::url() . '"' . Form::attr($attributes) . '>';
        $html .= Token::input('token_' . $page);

        // Prepare hidden fields with defaults
        $hidden_fields = [
            'page' => $page,
            'action' => $action_save
        ];

        // Merge with custom data (custom data overrides defaults)
        $hidden_fields = array_merge($hidden_fields, $custom_data);

        // Generate hidden inputs
        foreach ($hidden_fields as $name => $value) {
            $html .= '<input type="hidden" name="' . _r($name) . '" value="' . _r($value) . '">';
        }

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
     *
     * // Generate a field with conditional visibility
     * $rule = [
     *     'name' => 'details',
     *     'type' => 'text',
     *     'label' => 'Details',
     *     'form-params' => [
     *         'toggle-field' => 'status',
     *         'toggle-value' => 'active'
     *     ]
     * ];
     * echo ObjectToForm::row($rule, $currentValue);
     * ```
     *
     * @param array $rule The field definition rule
     * @param mixed $value The current value of the field
     * @return string The HTML for the complete form field
     */
    public static function row($rule, $value) {
        $type = $rule['form-type'] ?? $rule['type'];
        $form_params = $rule['form-params'] ?? [];

        // Build data attributes for toggle visibility
        $data_attrs = '';
        $has_toggle = false;
        if (isset($form_params['toggle-field'])) {
            $data_attrs .= ' data-togglefield="' . _r($form_params['toggle-field']) . '"';
            $has_toggle = true;
        }
        if (isset($form_params['toggle-value'])) {
            $data_attrs .= ' data-togglevalue="' . _r($form_params['toggle-value']) . '"';
        }

        $has_show = false;
        if (isset($form_params['data-milk-show'])) {
            $data_attrs .= ' data-milk-show="' . _r($form_params['data-milk-show']) . '"';
            $has_show = true;
            unset($form_params['data-milk-show']);
        }

        // Add style="display:none" for fields with toggle/show to prevent flash of visible content
        if ($has_toggle || $has_show) {
            $data_attrs .= ' style="display:none"';
        }

        $rule_input = $rule;
        $rule_input['form-params'] = $form_params;
        $input = self::getInput($rule_input, $value);

        // Check if field is in a container (no extra wrapper needed)
        $in_container = isset($form_params['in-container']) && $form_params['in-container'] === true;

        if ($type == 'hidden') {
            return $input;
        }
        if ($type == 'html') {
            if ($has_toggle || $has_show) {
                return '<div' . $data_attrs . '>' . $input . '</div>';
            }
            return $input;
        }
        if ($type == 'checkbox') {
            // Build checkbox wrapper classes
            $checkbox_classes = 'form-check mb-3';
            if (isset($form_params['form-check-class'])) {
                $checkbox_classes .= ' ' . $form_params['form-check-class'];
            }
            if ($in_container && ($has_toggle || $has_show)) {
                return '<div' . $data_attrs . '>' . $input . '</div>';
            }
            return $in_container ? $input : '<div class="' . $checkbox_classes . '"' . $data_attrs . '>' . $input . '</div>';
        }
        if ($in_container && ($has_toggle || $has_show)) {
            return '<div' . $data_attrs . '>' . $input . '</div>';
        }
        return $in_container ? $input : '<div class="mb-3"' . $data_attrs . '>' . $input .'</div>';
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
     * $textInput = ObjectToForm::getInput($textRule, 'Current Title');
     * 
     * // Generate a select dropdown
     * $selectRule = [
     *     'name' => 'category',
     *     'type' => 'select',
     *     'label' => 'Category',
     *     'options' => ['1' => 'Books', '2' => 'Electronics']
     * ];
     * $selectInput = ObjectToForm::getInput($selectRule, '2');
     * ```
     *
     * @param array $rule The field definition rule
     * @param mixed $value The current value of the field
     * @return string The HTML for the input field
     */
    public static function getInput(array $rule, $value) {
        $type = $rule['form-type'] ?? $rule['type'];
        $rule['label'] = $rule['label'] ?? $rule['name'];
        $rule['label'] = $rule['form-label'] ?? $rule['label'];
        $form_params = $rule['form-params'] ?? [];
        $rule['value'] = $rule['value'] ?? '';
        if (strpos($rule['name'], '[') !== false) {
            $rule['name'] = 'data[' . str_replace('[', '][', $rule['name']);
        } else {
            $rule['name'] = 'data[' . $rule['name'] . ']';
        }
       
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
            case 'number':
                return Form::input('number', $rule['name'], $rule['label'], $value, $form_params, true);
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
            case 'tel':
                return Form::input('tel', $rule['name'], $rule['label'], $value, $form_params, true);
                 break;
            case 'date':
                return Form::input('date', $rule['name'], $rule['label'], $value, $form_params, true);
                break;
            case 'datetime':
            case 'datetime-local':
                return Form::input('datetime-local', $rule['name'], $rule['label'], $value, $form_params, true);
                break;
             case 'time':
                // Normalize time value to HH:MM format (required by HTML5 time input)
                // Replace common separators (. , space) with :
                if (is_string($value) && !empty($value)) {
                    $value = preg_replace('/[.,\s]+/', ':', $value);
                    // Ensure HH:MM format (remove seconds if present)
                    if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
                        $value = str_pad($matches[1], 2, '0', STR_PAD_LEFT) . ':' . $matches[2];
                    }
                }
                return Form::input('time', $rule['name'], $rule['label'], $value, $form_params, true);
                break;
            case 'timestamp':
                // Convert Unix timestamp to datetime-local format (Y-m-d\TH:i)
                if (is_numeric($value) && $value > 0) {
                    $value = date('Y-m-d\TH:i', $value);
                }
                return Form::input('datetime-local', $rule['name'], $rule['label'], $value, $form_params, true);
                break;
            case 'html':
                return $rule['html'] ?? '';
                break;
            case 'openTag':
                $tag = $rule['tag'] ?? 'div';
                 // Build data attributes for toggle visibility
                $data_attrs = '';
                $has_toggle = false;
                if (isset($rule['form-params']['toggle-field'])) {
                    $data_attrs .= ' data-togglefield="' . _r($rule['form-params']['toggle-field']) . '"';
                    $has_toggle = true;
                }
                if (isset($rule['form-params']['toggle-value'])) {
                    $data_attrs .= ' data-togglevalue="' . _r($rule['form-params']['toggle-value']) . '"';
                }

                $has_show = false;
                if (isset($rule['form-params']['data-milk-show'])) {
                    $data_attrs .= ' data-milk-show="' . _r($rule['form-params']['data-milk-show']) . '"';
                    $has_show = true;
                }

                // Add style="display:none" for fields with toggle/show to prevent flash of visible content
                if ($has_toggle || $has_show) {
                    if ($rule['attributes']['style'] ?? false) {
                        $rule['attributes']['style'] .= ';display:none';
                    } else {
                        $rule['attributes']['style'] = 'display:none';
                    }
                }
                $attributes = self::buildAttributesString ($rule['attributes'] ?? []) ;
                $html ='<'. $tag . ' ' . $attributes . ' id="' . $rule['id'] . '" name="' . $rule['name'] . '" ' . $data_attrs . ' >';
                return $html;
                break;
            case 'closeTag':
                $tag = $rule['tag'] ?? 'div';
                $html = '</' . $tag . ' >';
                return $html;
                break;
             case 'select':
             case 'list':
                $rule['options'] = $rule['options'] ?? [];
                return Form::select($rule['name'], $rule['label'],  $rule['options'], $value, $form_params, true);
                break;
            case 'milkSelect':
                $rule['options'] = $rule['options'] ?? [];

                // Pass api_url if present
                if (isset($rule['api_url'])) {
                    $form_params['api_url'] = $rule['api_url'];
                }

                // If using apiUrl with belongsTo, get display value from relationship
                $display_value = null;
                if (isset($rule['api_url']) && isset($rule['relationship']) && $rule['relationship']['type'] === 'belongsTo') {
                    $relationship_alias = $rule['relationship']['alias'];
                    // Use explicit api_display_field or fallback to auto-detected title field
                    $display_field = $rule['api_display_field'] ?? $rule['_auto_display_field'] ?? null;
                    if ($display_field && isset($rule['record_object'])) {
                        $related_object = $rule['record_object']->$relationship_alias;
                        // Check if record_object has the relationship loaded (lazy loading)
                        if (is_object($related_object) && isset($related_object->$display_field) ) {
                             $display_value = $related_object->$display_field;
                        }
                    }
                }

                // Pass display value if found
                if ($display_value !== null) {
                    $form_params['display_value'] = $display_value;
                }

                return Form::milkSelect($rule['name'], $rule['label'], $rule['options'], $value, $form_params, true);
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
                // value is the value the saved field has, rule['value'] is the value attribute of the checkbox. If the two values ​​are equal, the checkbox is checked. To select the checkbox, $value must equal rule['value'] or true.
                $checkbox_html = '';

                $is_disabled = isset($form_params['disabled']) && $form_params['disabled'] !== false;
                if ($is_disabled) {
                    $submit_value = $value;
                    if ($submit_value === true) {
                        $submit_value = $rule['value'] ?? '1';
                    } elseif ($submit_value === false) {
                        $submit_value = $rule['checkbox_unchecked'] ?? '0';
                    }
                    if ($submit_value === '' || $submit_value === null) {
                        if (isset($rule['default'])) {
                            $submit_value = $rule['default'];
                        } elseif (isset($rule['checkbox_unchecked'])) {
                            $submit_value = $rule['checkbox_unchecked'];
                        } elseif (isset($rule['value'])) {
                            $submit_value = $rule['value'];
                        }
                    }
                    $checkbox_html .= '<input type="hidden" name="' . _r($rule['name']) . '" value="' . _r($submit_value) . '">';
                } else {
                    // Add hidden field BEFORE checkbox to handle unchecked state
                    // The hidden field will be sent when checkbox is unchecked
                    // When checkbox is checked, its value will override the hidden field value
                    if (isset($rule['checkbox_unchecked']) && $rule['checkbox_unchecked'] !== null) {
                        $checkbox_html .= '<input type="hidden" name="' . _r($rule['name']) . '" value="' . _r($rule['checkbox_unchecked']) . '">';
                    }
                }

                $checkbox_html .= Form::checkbox($rule['name'], $rule['label'],  $rule['value'], ($value === $rule['value'] || $value === true), $form_params, true);

                return $checkbox_html;
                break;
            case 'checkboxes':
                return Form::checkboxes($rule['name'], $rule['options'],  $value, false, $form_params, [], true);
                break;
            case 'radio':
            case 'radios':
                return Form::radios($rule['name'], $rule['options'],  $value, false, $form_params, [], true);
                break;
            case 'file':

                return Get::themePlugin('UploadFiles', ['name'=>$rule['name'], 'label'=> $rule['label'], 'value'=>$value, 'options'=>$form_params ?? [], 'upload_name' => _raz($rule['name'])] );
                break;
            case 'image':

                return Get::themePlugin('UploadImages', ['name'=>$rule['name'], 'label'=> $rule['label'], 'value'=>$value, 'options'=>$form_params ?? [], 'upload_name' => _raz($rule['name'])] );
                break;
            case 'beauty-select':
                return Get::themePlugin('BeautySelect', ['id' => $rule['name'], 'label' => $rule['label'], 'value' => $value, 'options' => $rule['options'] ?? [], 'isMultiple' => $rule['isMultiple'] ?? false, 'floating'=> $rule['isMultiple'] ?? true ]);
                break;
            case 'editor':
                return Get::themePlugin('editor', ['id' => $rule['name'], 'name' => $rule['name'], 'label' => $rule['label'], 'value' => $value, 'height' => '200px']);
                break;
            default:
                // custom input
                $ret = Hooks::run('form-'.$type, $rule, $value);
                if (is_array($ret)) {
                    $ret = '<div class="text-danger">'.sprintf(_r('Hook %s not found for type %s'), 'form-'.$type, $type).'</div>';
                }
                return $ret;
                break;
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

    
    /**
     * Build attributes string from array
     *
     * @param array $attributes Array of attributes (class, style, id, etc.)
     * @return string Formatted attributes string
     */
    private static function buildAttributesString(array $attributes): string {
        if (empty($attributes)) {
            return '';
        }

        $attrs_string = '';
        foreach ($attributes as $key => $value) {
            $attrs_string .= ' ' . _r($key) . '="' . _r($value) . '"';
        }

        return $attrs_string;
    }
}
