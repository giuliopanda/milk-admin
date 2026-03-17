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

        // Prepare hidden fields with defaults
        $hidden_fields = [
            'page' => $page,
            'action' => $action_save
        ];

        // Merge with custom data (custom data overrides defaults)
        $hidden_fields = array_merge($hidden_fields, $custom_data);

        // Debug helper: show how custom_data is injected (top-level hidden inputs, not inside data[...] payload).
        // Enable only when debug is true and ?debug_objecttoform=1 is present (keeps normal pages clean).
        if (Config::get('debug', false) && _absint($_REQUEST['debug_objecttoform'] ?? 0) === 1) {
            $debug_payload = [
                'page' => $page,
                'action_save' => $action_save,
                'custom_data' => $custom_data,
                'hidden_fields' => $hidden_fields,
                '_REQUEST_keys' => array_keys($_REQUEST),
                '_REQUEST_data_keys' => (isset($_REQUEST['data']) && is_array($_REQUEST['data'])) ? array_keys($_REQUEST['data']) : null,
            ];
            $dump = json_encode($debug_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($dump === false) {
                $dump = '';
            }
            $html .= '<pre style="background:#111;color:#eee;padding:12px;white-space:pre-wrap;overflow:auto;">'
                . htmlspecialchars($dump, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</pre>';
        }

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
    public static function getInput(array $rule, $value): string {
        $type = $rule['form-type'] ?? $rule['type'];
        $rule['label'] = $rule['label'] ?? $rule['name'];
        $rule['label'] = $rule['form-label'] ?? $rule['label'];
        $form_params = $rule['form-params'] ?? [];
        if (($rule['unsigned'] ?? false) && !isset($form_params['min']) && in_array($type, ['number', 'range'], true)) {
            $form_params['min'] = 0;
        }
        $rule['value'] = $rule['value'] ?? '';
        if (strpos($rule['name'], '[') !== false) {
            $rule['name'] = 'data[' . str_replace('[', '][', $rule['name']);
        } else {
            $rule['name'] = 'data[' . $rule['name'] . ']';
        }
       
        switch ($type) {
            case 'hidden':
                return self::asString(Form::input('hidden', $rule['name'], $rule['label'], $value, $form_params, true));
            case 'string':
                return self::asString(Form::input('text', $rule['name'], $rule['label'], $value, $form_params, true));
            case 'int':
                return self::asString(Form::input('text', $rule['name'], $rule['label'], $value, $form_params, true));
            case 'number':
                return self::asString(Form::input('number', $rule['name'], $rule['label'], $value, $form_params, true));
            case 'float':
                return self::asString(Form::input('text', $rule['name'], $rule['label'], $value, $form_params, true));
            case 'text':
            case 'textarea':
                $form_params['rows'] = $form_params['rows'] ?? 7;
                return self::asString(Form::textarea($rule['name'], $rule['label'], $value, $form_params['rows'], $form_params, true));
           case 'password':
               return self::asString(Form::input('password', $rule['name'], $rule['label'], $value, $form_params, true));
           case 'email':
                return self::asString(Form::input('email', $rule['name'], $rule['label'], $value, $form_params, true));
            case 'url':
                return self::asString(Form::input('url', $rule['name'], $rule['label'], $value, $form_params, true));
            case 'tel':
                return self::asString(Form::input('tel', $rule['name'], $rule['label'], $value, $form_params, true));
            case 'date':
                return self::asString(Form::input('date', $rule['name'], $rule['label'], $value, $form_params, true));
            case 'datetime':
            case 'datetime-local':
                return self::asString(Form::input('datetime-local', $rule['name'], $rule['label'], $value, $form_params, true));
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
                return self::asString(Form::input('time', $rule['name'], $rule['label'], $value, $form_params, true));
            case 'timestamp':
                // Convert Unix timestamp to datetime-local format (Y-m-d\TH:i)
                if (is_numeric($value) && $value > 0) {
                    $value = date('Y-m-d\TH:i', $value);
                }
                return self::asString(Form::input('datetime-local', $rule['name'], $rule['label'], $value, $form_params, true));
            case 'html':
                return $rule['html'] ?? '';
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
            case 'closeTag':
                $tag = $rule['tag'] ?? 'div';
                $html = '</' . $tag . ' >';
                return $html;
             case 'select':
             case 'list':
                $rule['options'] = $rule['options'] ?? [];
                $allowEmpty = !empty($rule['allow_empty']);
                // When multiple is enabled, use milkSelect with type=multiple
                $hasMultiple = isset($form_params['multiple']) && ($form_params['multiple'] === 'multiple' || $form_params['multiple'] === true);
                if ($hasMultiple) {
                    unset($form_params['multiple']);
                    $form_params['type'] = 'multiple';
                    // Decode JSON string from DB into array for milkSelect
                    if (is_string($value) && $value !== '' && $value[0] === '[') {
                        $decoded = json_decode($value, true);
                        if (is_array($decoded)) {
                            $value = $decoded;
                        }
                    }
                    return self::asString(Form::milkSelect($rule['name'], $rule['label'], $rule['options'], $value, $form_params, true));
                }
                // When allowEmpty + options <= 25 + not multiple: normal select with empty option
                if ($allowEmpty && count($rule['options']) <= 25) {
                    $rule['options'] = ['' => ''] + $rule['options'];
                    return self::asString(Form::select($rule['name'], $rule['label'], $rule['options'], $value, $form_params, true));
                }
                // Use milkSelect for large option sets (> 25) for better UX (autocomplete/search)
                if (count($rule['options']) > 25) {
                    return self::asString(Form::milkSelect($rule['name'], $rule['label'], $rule['options'], $value, $form_params, true));
                }
                return self::asString(Form::select($rule['name'], $rule['label'],  $rule['options'], $value, $form_params, true));
            case 'milkSelect':
                $rule['options'] = $rule['options'] ?? [];

                // When multiple is enabled, convert form-params to milkSelect type=multiple
                $hasMultiple = isset($form_params['multiple']) && ($form_params['multiple'] === 'multiple' || $form_params['multiple'] === true);
                if ($hasMultiple) {
                    unset($form_params['multiple']);
                    $form_params['type'] = 'multiple';
                    // Decode JSON string from DB into array for milkSelect
                    if (is_string($value) && $value !== '' && $value[0] === '[') {
                        $decoded = json_decode($value, true);
                        if (is_array($decoded)) {
                            $value = $decoded;
                        }
                    }
                }

                // Pass api_url if present
                if (isset($rule['api_url'])) {
                    $form_params['api_url'] = $rule['api_url'];
                }

                // If using apiUrl with belongsTo, resolve display label from relationship/model
                $display_value = null;
                if (
                    isset($rule['api_url']) &&
                    isset($rule['relationship']) &&
                    is_array($rule['relationship']) &&
                    ($rule['relationship']['type'] ?? null) === 'belongsTo'
                ) {
                    // Use explicit api_display_field or fallback to belongsTo auto_display_field
                    $display_field = $rule['api_display_field'] ?? $rule['relationship']['auto_display_field'] ?? $rule['_auto_display_field'] ?? null;
                    $display_value = self::resolveBelongsToDisplayValue($rule, $value, $display_field);
                }

                // Pass display value if found
                if ($display_value !== null) {
                    $form_params['display_value'] = $display_value;
                }

                return self::asString(Form::milkSelect($rule['name'], $rule['label'], $rule['options'], $value, $form_params, true));
            case 'enum':
                $rule['options'] = $rule['options'] ?? [];
                $rule_options = [];
                foreach ($rule['options'] as $value) {
                    $rule_options[$value] = $value;
                }
                return self::asString(Form::select($rule['name'], $rule['label'], $rule_options, $value, $form_params, true));
            case 'checkbox':
                // value is the value the saved field has, rule['value'] is the value attribute of the checkbox. If the two values ​​are equal, the checkbox is checked. To select the checkbox, $value must equal rule['value'] or true.
                $checkbox_html = '';
                unset($form_params['inline'], $form_params['columns'], $form_params['label-position'], $form_params['label-width']);

                $is_disabled = isset($form_params['disabled']) && $form_params['disabled'] !== false;
                if ($is_disabled) {
                    $submit_value = $value;
                    if ($submit_value === true) {
                        $submit_value = $rule['value'];
                    } elseif ($submit_value === false) {
                        $submit_value = $rule['checkbox_unchecked'] ?? '0';
                    }
                    if ($submit_value === '' || $submit_value === null) {
                        if (isset($rule['default'])) {
                            $submit_value = $rule['default'];
                        } elseif (isset($rule['checkbox_unchecked'])) {
                            $submit_value = $rule['checkbox_unchecked'];
                        } elseif (array_key_exists('value', $rule)) {
                            $submit_value = $rule['value'];
                        }
                    }
                    $checkbox_html .= '<input type="hidden" name="' . _r($rule['name']) . '" value="' . _r($submit_value) . '">';
                } else {
                    // Add hidden field BEFORE checkbox to handle unchecked state
                    // The hidden field will be sent when checkbox is unchecked
                    // When checkbox is checked, its value will override the hidden field value
                    if (isset($rule['checkbox_unchecked'])) {
                        $checkbox_html .= '<input type="hidden" name="' . _r($rule['name']) . '" value="' . _r($rule['checkbox_unchecked']) . '">';
                    }
                }

                $is_checked = self::isCheckboxChecked($value, $rule['value']);
                $checkbox_html .= Form::checkbox($rule['name'], $rule['label'],  $rule['value'], $is_checked, $form_params, true);

                return $checkbox_html;
            case 'checkboxes':
                $inline = self::normalizeBool($form_params['inline'] ?? false);
                unset($form_params['inline']);
                return self::asString(Form::checkboxes($rule['name'], $rule['options'],  $value, $inline, $form_params, [], true));
            case 'radio':
            case 'radios':
                $inline = self::normalizeBool($form_params['inline'] ?? false);
                unset($form_params['inline']);
                return self::asString(Form::radios($rule['name'], $rule['options'],  $value, $inline, $form_params, [], true));
            case 'file':

                return self::asString(Get::themePlugin('UploadFiles', ['name'=>$rule['name'], 'label'=> $rule['label'], 'value'=>$value, 'options'=>$form_params ?? [], 'upload_name' => _raz($rule['name'])] ));
            case 'image':

                return self::asString(Get::themePlugin('UploadImages', ['name'=>$rule['name'], 'label'=> $rule['label'], 'value'=>$value, 'options'=>$form_params ?? [], 'upload_name' => _raz($rule['name'])] ));
            case 'milk-select':
            case 'milkselect':
                $milk_options = $form_params;
                $milk_options['type'] = ($rule['isMultiple'] ?? false) ? 'multiple' : 'single';
                $milk_options['floating'] = $rule['isMultiple'] ?? true;
                return self::asString(Form::milkSelect($rule['name'], $rule['label'], $rule['options'] ?? [], $value, $milk_options, true));
            case 'editor':
                $is_readonly = self::normalizeBool($form_params['readonly'] ?? false);
                $is_disabled = self::normalizeBool($form_params['disabled'] ?? false);

                // Editor plugin is interactive; for readonly/disabled render a plain disabled textarea.
                if ($is_readonly || $is_disabled) {
                    $form_params['disabled'] = true;
                    unset($form_params['readonly']);
                    $rows = isset($form_params['rows']) ? (int) $form_params['rows'] : 7;
                    unset($form_params['rows']);
                    return self::asString(Form::textarea($rule['name'], $rule['label'], $value, $rows, $form_params, true));
                }

                $editor_required = self::normalizeBool($form_params['required'] ?? false);
                $editor_invalid_feedback = '';
                if (isset($form_params['invalid-feedback']) && is_scalar($form_params['invalid-feedback'])) {
                    $editor_invalid_feedback = (string) $form_params['invalid-feedback'];
                } elseif (isset($form_params['invalid_feedback']) && is_scalar($form_params['invalid_feedback'])) {
                    $editor_invalid_feedback = (string) $form_params['invalid_feedback'];
                }

                return self::asString(Get::themePlugin('editor', [
                    'id' => $rule['name'],
                    'name' => $rule['name'],
                    'label' => $rule['label'],
                    'value' => $value,
                    'height' => '200px',
                    'required' => $editor_required,
                    'invalidFeedback' => $editor_invalid_feedback
                ]));
            default:
                // custom input
                $ret = Hooks::run('form-'.$type, $rule, $value);
                if (is_array($ret)) {
                    $ret = '<div class="text-danger">'.sprintf(_r('Hook %s not found for type %s'), 'form-'.$type, $type).'</div>';
                }
                return self::asString($ret);
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

    private static function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private static function asString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if ($value === null || $value === false) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    private static function isCheckboxChecked(mixed $currentValue, mixed $checkedValue): bool
    {
        if ($currentValue === true) {
            return true;
        }

        // Strict match first to preserve native behavior.
        if ($currentValue === $checkedValue) {
            return true;
        }

        // Normalize scalar values to avoid false negatives like "1" vs 1.
        if (is_scalar($currentValue) || $currentValue === null) {
            if ((string) $currentValue === (string) $checkedValue) {
                return true;
            }
        }

        return false;
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

    /**
     * Resolve display label for belongsTo + apiUrl fields.
     * First tries loaded relationship object, then falls back to querying related model by key.
     */
    private static function resolveBelongsToDisplayValue(array $rule, mixed $value, mixed $displayField): mixed
    {
        $display_field = trim((string) $displayField);
        if ($display_field === '') {
            return null;
        }

        // 1) Try from loaded relationship (edit mode)
        $relationship = is_array($rule['relationship'] ?? null) ? $rule['relationship'] : [];
        $relationship_alias = trim((string) ($relationship['alias'] ?? ''));
        if ($relationship_alias !== '' && isset($rule['record_object'])) {
            $related_object = $rule['record_object']->$relationship_alias;
            if (is_object($related_object) && isset($related_object->$display_field)) {
                return $related_object->$display_field;
            }
        }

        // 2) Fallback for default/new-record value: query related model by selected key
        if ($value === null || $value === '' || is_array($value) || is_object($value)) {
            return null;
        }

        $related_model_class = trim((string) ($relationship['related_model'] ?? ''));
        $related_key = trim((string) ($relationship['related_key'] ?? 'id'));
        if ($related_model_class === '' || $related_key === '' || !class_exists($related_model_class)) {
            return null;
        }

        try {
            $relatedModel = new $related_model_class();
            $query = $relatedModel->query()->where("$related_key = ?", [$value]);

            $where_config = is_array($relationship['where'] ?? null) ? $relationship['where'] : null;
            if ($where_config !== null) {
                $where_condition = trim((string) ($where_config['condition'] ?? ''));
                $where_params = is_array($where_config['params'] ?? null) ? $where_config['params'] : [];
                if ($where_condition !== '') {
                    $query->where($where_condition, $where_params);
                }
            }

            $related_object = $query->limit(0, 1)->getRow();
            if (is_object($related_object) && isset($related_object->$display_field)) {
                return $related_object->$display_field;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
