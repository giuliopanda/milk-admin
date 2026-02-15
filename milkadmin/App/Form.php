<?php
namespace App;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Bootstrap-styled form generator with support for various input types and validation.
 * 
 * This class provides methods to generate form elements with proper Bootstrap 5 markup.
 * It includes support for all standard form controls, validation states, and can be
 * extended using hooks. For rapid form generation from objects, see the ObjectToForm class.
 *
 * @example
 * // Basic form example
 * ```php
 * <form method="post">
 *     <?php
 *     Form::input('text', 'username', 'Username', '', ['required' => true]);
 *     Form::input('password', 'password', 'Password', '', ['required' => true]);
 *     Form::submit('Login');
 *     ?>
 * </form>
 *
 * // Using with ObjectToForm for rapid development
 * $fields = [
 *     'name' => [
 *         'type' => 'string',
 *         'label' => 'Full Name',
 *         'form-params' => ['required' => true]
 *     ]
 * ];
 * ```
 * 
 * @see ObjectToForm For generating forms from object definitions
 * @package     App
 */

class Form
{
    /**
     * Renders a form input field with Bootstrap 5 styling
     *
     * @example
     * // Basic text input
     * Form::input('text', 'username', 'Username', 'johndoe', [
     *     'placeholder' => 'Enter your username',
     *     'required' => true,
     *     'class' => 'mb-3'
     * ]);
     *
     * // Email input with validation
     * Form::input('email', 'user_email', 'Email Address', '', [
     *     'required' => true,
     *     'invalid-feedback' => 'Please enter a valid email address',
     *     'help-text' => 'We will never share your email with anyone else'
     * ]);
     *
     * // Input with datalist
     * Form::input('text', 'browser', 'Browser', '', [
     *     'list' => ['Chrome', 'Firefox', 'Safari', 'Edge'],
     *     'help-text' => 'Select from the list or type a browser name'
     * ]);
     *
     * // Input with conditional visibility (wrapping div will have data-togglefield and data-togglevalue)
     * // Note: When used with ObjectToForm::row(), the toggle attributes should be in form-params
     * Form::input('text', 'details', 'Details', '', [
     *     'toggle-field' => 'status',
     *     'toggle-value' => 'active'
     * ]);
     *
     * @param string $type The input type (text, email, password, number, etc.)
     * @param string $name The name attribute of the input field
     * @param string $label The label text for the input field
     * @param string $value The default value of the input field
     * @param array $options Additional HTML attributes and options liks:
     *   - 'id' => string Custom ID (auto-generated if not provided)
     *   - 'class' => string Additional CSS classes
     *   - 'required' => bool Whether the field is required
     *   - 'invalid-feedback' => string in a div with class "invalid-feedback" Error message for validation
     *   - 'help-text' => string Small descriptive text displayed below the field
     *   - 'list' => array Options for datalist
     *   - 'toggle-field' => string Name of the field to watch for changes
     *   - 'toggle-value' => string Value that will make this field visible
     *   - Other standard HTML5 input attributes (min, max, step, pattern, etc.)
     * @param bool $return If true, returns the HTML instead of echoing it
     * @return string|void Returns HTML if $return is true, otherwise echoes it
     */

    public static function input($type, $name, $label, $value = '', $options = array(), $return = false) {
        $options['class'] = (array_key_exists('class', $options)) ? $options['class']." ".'form-control' : 'form-control';
        self::applyInvalidClass($options, $name);
        if ((array_key_exists('floating', $options) && $options['floating'] == false) || $type == 'file'|| $type == 'color') {
            $floating = false;
            unset($options['floating']);
            if ($type == 'color') {
                $options['class'] = (array_key_exists('class', $options)) ? $options['class']." ".'form-control-color' : 'form-control-color';
            }
        } else {
            $floating = true;
        }
        if (is_a($value, 'DateTime')) {
            switch($type) {
                case 'date':
                    $value = $value->format('Y-m-d');
                    break;
                case 'datetime-local':
                    $value = $value->format('Y-m-d\TH:i');
                    break;
                case 'time':
                    $value = $value->format('H:i');
                    break;
                case 'month':
                    $value = $value->format('Y-m');
                    break;
                case 'week':
                    $value = $value->format('Y-\WW'); // Anno-W[numero settimana]
                    break;
                default:
                    // Per input text o altri tipi, mantieni il formato originale
                    $value = $value->format('Y-m-d H:i:s');
                    break;
            }
        }
      

        $id = self::id($options, $name);
        $placeholer = ($options['placeholder'] ?? ($floating ? $label : ''));
        $label_html = ($label != '' && $type != "hidden") ? '<label for="'.$id.'"'.self::attr(self::getLabelOptions($options)).'>'. _rh($label).'</label>' : '';
        $field = ($floating) ? '<div class="form-floating">' : (($label != '') ? $label_html  : '');
        $field .= '<input type="'.$type.'" name="'._r($name).'" placeholder="'._r($placeholer).'"'.($value != '' ? ' value="'._r($value).'"' : '').' ';

        $field .= ' id="'.$id.'"';

        // Add data-error-message attribute if invalid-feedback is set
        if (array_key_exists('invalid-feedback', $options)) {
            $field .= ' data-error-message="'._r($options['invalid-feedback']).'"';
        }

        $field .= self::attr($options);
        /**
         * The list attribute refers to a datalist element that contains pre-defined options for an input element.
         */
        if (array_key_exists('list', $options)) {
            $id_list = _r('_list'.$id);
            $field .= ' list="'.$id_list.'"';
        }

        $field .= '>';

        if (array_key_exists('list', $options)) {
            $field .= '<datalist id="'.$id_list.'">';
            foreach ($options['list'] as $option) {
                $field .= '<option value="'._r($option).'">';
            }
            $field .= '</datalist>';
        }
        if ($floating) {
            $field .= $label_html ;
        }

        if (array_key_exists('invalid-feedback', $options)) {
            $field .= '<div class="invalid-feedback">'._rh($options['invalid-feedback']).'</div>';
        }

        if (array_key_exists('help-text', $options)) {
            $field .= '<div class="form-text">'._rh($options['help-text']).'</div>';
        }

        $field .=  ($floating) ? '</div>' : '';
      
        // hook per modificare il campo
        $field = Hooks::run('form_input', $field, $type, $name, $label, $value, $options);
        if ($return) {
            return $field;
        } else {
            echo $field;
        }
    }

    /**
     * Renders a Bootstrap-styled textarea field
     *
     * @example
     * // Basic textarea
     * Form::textarea('description', 'Description', '', 4, [
     *     'placeholder' => 'Enter your description here...',
     *     'required' => true
     * ]);
     *
     * // Textarea with custom height and validation
     * Form::textarea('bio', 'Biography', $userBio, 6, [
     *     'minlength' => 20,
     *     'maxlength' => 1000,
     *     'class' => 'bg-light',
     *     'invalid-feedback' => 'Please enter at least 20 characters'
     * ]);
     *
     * @param string $name The name attribute of the textarea
     * @param string $label The label text for the textarea
     * @param string $value The default value of the textarea
     * @param int $rows Number of visible text lines (default: 4)
     * @param array $options Additional HTML attributes and options:
     *   - 'class' => string Additional CSS classes
     *   - 'required' => bool Whether the field is required
     *   - 'placeholder' => string Placeholder text
     *   - 'invalid-feedback' => string Error message for validation
     *   - Other standard HTML5 textarea attributes
     * @param bool $return If true, returns the HTML instead of echoing it
     * @return string|void Returns HTML if $return is true, otherwise echoes it
     */
    public static function textarea($name, $label, $value = '', $rows=4, $options = [], $return = false) {
        $options['class'] = (array_key_exists('class', $options)) ? $options['class']." ".'form-control' : 'form-control';
        self::applyInvalidClass($options, $name);
        if ((array_key_exists('floating', $options) && $options['floating'] == false) ) {
            $floating = false;
            unset($options['floating']);
        } else {
            $floating = true;
        }
        $id = self::id($options, $name);

        $noFloatingLabel = ($label != '') ? '<label for="'.$id.'">'._rh($label).'</label>' : '';
        $field = ($floating) ? '<div class="form-floating">' : $noFloatingLabel;
        $placeholer = ($options['placeholder'] ?? $label);
        $field .= '<textarea name="'._r($name).'" placeholder="'._rh($placeholer).'"';

        $field .= ' id="'.$id.'"';

        // Add data-error-message attribute if invalid-feedback is set
        if (array_key_exists('invalid-feedback', $options)) {
            $field .= ' data-error-message="'._r($options['invalid-feedback']).'"';
        }

        // To set a custom height on your <textarea>, do not use the rows attribute. Instead, set an explicit height (either inline or via custom CSS).
        
        $rows = _absint(((int)$rows > 20) ? 20 : $rows);
        $options['class']  = (array_key_exists('class', $options)) ? $options['class']." ".'textarea-rows-'.$rows : 'textarea-rows-'.$rows;
        //$field .= ' rows="'._absint($rows).'"';
       
        $field .= self::attr($options);

        $field .= '>';

        if ($value != '') {
           $field .=_r($value);
        }
        $field .= '</textarea>';
        
        if ($label != '') {
            $field .= '<label for="'.$id.'">'._rh($label).'</label>';
        }
        if (array_key_exists('invalid-feedback', $options)) {
            $field .= '<div class="invalid-feedback">'._rh($options['invalid-feedback']).'</div>';
        }

        if (array_key_exists('help-text', $options)) {
            $field .= '<div class="form-text">'._rh($options['help-text']).'</div>';
        }

        $field .=  ($floating) ? '</div>' : '';
        // hook per modificare il campo
        $field = Hooks::run('form_textarea', $field, $name, $label, $value, $rows, $options);
        if ($return) {
            return $field;
        } else {
            echo $field;
        }
    }

    /**
     * Renders a single checkbox input with Bootstrap styling
     *
     * @example
     * // Basic checkbox
     * Form::checkbox('terms', 'I agree to the terms and conditions', '1', false, [
     *     'required' => true,
     *     'class' => 'me-2',
     *     'invalid-feedback' => 'You must accept the terms to continue'
     ]);
     *
     * @param string $name The name attribute of the checkbox
     * @param string $label The label text displayed next to the checkbox
     * @param string $value The value submitted when the checkbox is checked
     * @param bool $is_checked Whether the checkbox should be checked by default
     * @param array $options Additional HTML attributes and options:
     *   - 'class' => string Additional CSS classes
     *   - 'required' => bool Whether the checkbox is required
     *   - 'invalid-feedback' => string Error message for validation
     *   - Standard HTML5 input attributes (disabled, readonly, etc.)
     * @param bool $return If true, returns the HTML instead of echoing it
     * @return string|void Returns HTML if $return is true, otherwise echoes it
     */
    public static function checkbox($name, $label, $value, $is_checked = false, $options = [], $return = false) {
        $normalized_name = $name;
        if (preg_match('/^data\[(.+)\]$/', $name, $matches)) {
            $normalized_name = $matches[1];
        }

        $invalid_class = '';
        if (strpos($name, '[]') === false) {
            $invalid_class = MessagesHandler::getInvalidClass($normalized_name);
            if ($invalid_class != '') {
                $options['class'] = (array_key_exists('class', $options)) ?
                    $options['class'] . " " . $invalid_class :
                    $invalid_class;

                if (!array_key_exists('invalid-feedback', $options)) {
                    $errors = MessagesHandler::getErrors();
                    if (isset($errors[$normalized_name])) {
                        $options['invalid-feedback'] = $errors[$normalized_name];
                    } else {
                        foreach ($errors as $key => $message) {
                            if (strpos($key, '|') !== false) {
                                $fields = explode('|', $key);
                                if (in_array($normalized_name, $fields, true)) {
                                    $options['invalid-feedback'] = $message;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        $options['class'] = (array_key_exists('class', $options)) ? $options['class']." ".'form-check-input' : 'form-check-input';
       $field = '<div class="d-flex align-items-center w-100 h-100 no-form-check-mt">';
        $field .= '<input type="checkbox" value="'._r($value).'" name="'._r($name).'"';
        if ($is_checked) {
            $field .= ' checked';
        }
        $id = self::id($options, $name);
        $field .= ' id="'.$id.'"';
        $field .= self::attr($options);
        $field .= '>';
        $field .= '<label class="form-check-label ms-2" for="'.$id.'">'._rh($label).'</label>';
        if (array_key_exists('invalid-feedback', $options)) {
            $field .= '<div class="invalid-feedback">'._rh($options['invalid-feedback']).'</div>';
        }
        // invalid 
        if (strpos($name, '[]') === false && $invalid_class != '') {
            $field = '<div class="' . $invalid_class . ' js-checkbox-remove-is-invalid ">' .  $field . '</div>';
        }
        $field .= '</div>';
        // hook per modificare il campo
        $field = Hooks::run('form_checkbox', $field, $name, $label, $value, $options);
        if ($return) {
            return $field;
        } else {
            echo $field;
        }
    }

    /**
     * Renders a group of checkboxes with Bootstrap styling
     *
     * @example
     * // Basic checkboxes group
     * $interests = [
     *     'sports' => 'Sports',
     *     'music' => 'Music',
     *     'reading' => 'Reading'
     * ];
     * $selected = ['sports', 'music'];
     * 
     * Form::checkboxes('interests', $interests, $selected, true, [
     *     'form-group-class' => 'mb-3',
     *     'label' => 'Your Interests',
     *     'invalid-feedback' => 'Please select at least one interest'
     * ], [
     *     'class' => 'form-check-input me-2',
     *     'required' => true
     * ]);
     *
     * @param string $name The name attribute for all checkboxes in the group (will be suffixed with [] for array submission)
     * @param array $list_of_radio Associative array of value => label pairs for the checkboxes
     * @param string|array $selected_value The currently selected value(s)
     * @param bool $inline Whether to display checkboxes inline (default: false)
     * @param array $options_group Options for the checkbox group container:
     *   - 'form-group-class' => string CSS class for the form group div
     *   - 'form-check-class' => string CSS class for individual checkbox containers
     *   - 'label' => string Group label text
     *   - 'invalid-feedback' => string Error message for validation
     * @param array $options_field Options for individual checkboxes
     * @param bool $return If true, returns the HTML instead of echoing it
     * @return string|void Returns HTML if $return is true, otherwise echoes it
     */
    public static function checkboxes($name, $list_of_radio, $selected_value = '', $inline = false,  $options_group = [], $options_field = [], $return = false) {
        $checkboxes = [];
        $base_id = self::id($options_field, $name);
        $count = 0;
        self::applyInvalidClass($options_group, $name);
        
        foreach ($list_of_radio as $value => $label) {
            $temp_field = '<div class="form-check';
            if ($inline) {
                $temp_field .= ' form-check-inline';
            }
            if (array_key_exists('form-check-class', $options_group)) {
                $temp_field .= ' '._r($options_group['form-check-class']).'';
            }
            $temp_field .= '">';

            $options_field['id'] = $base_id.'_'. str_pad($count++, 2, '0', STR_PAD_LEFT);
            $selected = (is_array($selected_value) && in_array($value, $selected_value)) ? true : false;
            $temp_field .= self::checkbox($name."[]", $label, $value, $selected, $options_field, true);

            // se è l'ultimo campo e c'è un invalid-feedback
            if ($count == count($list_of_radio) && array_key_exists('invalid-feedback', $options_group)) {
                $temp_field .= '<div class="invalid-feedback">'._rh($options_group['invalid-feedback']).'</div>';
            }

            $temp_field .= '</div>';

            $checkboxes[] = $temp_field;
            
        }
        //   https://stackoverflow.com/questions/47546087/display-invalid-feedback-text-for-radio-button-group-in-bootstrap-4
        $classes_field = (array_key_exists('form-group-class', $options_group)) ? ' '._r($options_group['form-group-class']).'' : '';
        if ($options_group['class'] ?? '') {
            $classes_field .= ' '._r($options_group['class']).'';
        }
        if (stripos($classes_field, 'is-invalid') !== false) {
            $classes_field .= ' js-checkbox-remove-is-invalid';
        }
        $field = '<div class="js-form-checkboxes-group form-group'.$classes_field.'">';

        if (array_key_exists('label', $options_group)) {
            if ($options_group['label'] != '') {
                $field .= '<label class="label-checkboxes me-4">'._rt($options_group['label']).'</label>';
            }
        }
        $field .= implode('', $checkboxes);
     
        $field .= '</div>';
        // hook per modificare il campo
        $field = Hooks::run('form_checkboxes', $field, $name, $list_of_radio, $selected_value,  $inline,  $options_group, $options_field);
        if ($return) {
            return $field;
        } else {
            echo $field;
        }
    }

    /**
     * Renders a single radio button with Bootstrap styling
     *
     * @example
     * // Basic radio button
     * Form::radio('gender', 'Male', 'm', $selectedGender, [
     *     'class' => 'form-check-input me-2',
     *     'required' => true
     * ]);
     *
     * @param string $name The name attribute of the radio button (should be the same for all radio buttons in a group)
     * @param string $label The label text displayed next to the radio button
     * @param string $value The value submitted when this radio is selected
     * @param string $selected_value The currently selected value in the radio group
     * @param array $options Additional HTML attributes and options:
     *   - 'class' => string Additional CSS classes
     *   - 'required' => bool Whether the radio group is required
     *   - Standard HTML5 input attributes (disabled, readonly, etc.)
     * @param bool $return If true, returns the HTML instead of echoing it
     * @return string|void Returns HTML if $return is true, otherwise echoes it
     */
    public static function radio($name, $label, $value, $selected_value = '',  $options = [], $return = false) {
        $options['class'] = (array_key_exists('class', $options)) ? $options['class']." ".'form-control' : 'form-control';
        self::applyInvalidClass($options, $name);
        $field = '<div class="d-flex align-items-center w-100 h-100 no-form-check-mt">';
        $field .= '<input class="form-check-input" type="radio" value="'._r($value).'" name="'._r($name).'"';
        if ($selected_value == $value) {
            $field .= ' checked';
        }
        $id = self::id($options, $name);
        $field .= ' id="'.$id.'"';
        $field .= self::attr($options);
        $field .= '>';
        $field .= '<label class="form-check-label ms-2" for="'.$id.'">'._rt($label).'</label>';
        if (array_key_exists('invalid-feedback', $options)) {
            $field .= '<div class="invalid-feedback">'._rt($options['invalid-feedback']).'</div>';
        }
        // invalid-feedback is not supported on single radio buttons (It will be supported on radio groups!)
        $field .= "</div>";
        // hook per modificare il campo
        $field = Hooks::run('form_radio', $field, $name, $label, $value, $options);
        if ($return) {
            return $field;
        } else {
            echo $field;
        }
    }

    /**
     * Renders a group of radio buttons
     * 
     * Example usage:
     * ```php
     * $options = [
     *     '1' => 'Option One',
     *     '2' => 'Option Two',
     *     '3' => 'Option Three'
     * ];
     * $selected = '2';
     * $inline = true; // Display radios inline
     * $groupOptions = [
     *     'form-group-class' => 'mb-3',
     *     'label' => 'Select an option',
     *     'invalid-feedback' => 'Please select an option'
     * ];
     * $fieldOptions = [
     *     'class' => 'custom-radio',
     *     'required' => true
     * ];
     * echo Form::radios('option', $options, $selected, $inline, $groupOptions, $fieldOptions);
     * ```
     *
     * @param string $name The name attribute for all radio buttons in the group
     * @param array $list_of_radio Associative array of value => label pairs for the radio buttons
     * @param string $selected_value [Optional] The value of the currently selected radio button
     * @param bool $inline [Optional] Whether to display radio buttons inline (default: false)
     * @param array $options_group [Optional] Options for the radio button group container:
     *   - 'form-group-class': CSS class for the form group div
     *   - 'form-check-class': CSS class for individual radio containers
     *   - 'label': Group label text
     *   - 'invalid-feedback': Error message to display when validation fails
     * @param array $options_field [Optional] Options for individual radio buttons
     * @param bool $return [Optional] If true, returns the HTML instead of echoing it
     * @return string|void Returns HTML if $return is true, otherwise echoes it
     */
    public static function radios($name, $list_of_radio, $selected_value = '',  $inline = false, $options_group = [], $options_field = [], $return = false) {
        $radios = [];
        $base_id = self::id($options_field, $name);
        $count = 0;
        self::applyInvalidClass($options_group, $name);
        foreach ($list_of_radio as $value => $label) {
            $temp_field = '<div class="form-check';
            if ($inline) {
                $temp_field .= ' form-check-inline';
            }
            if (array_key_exists('form-check-class', $options_group)) {
                $temp_field .= ' '._r($options_group['form-check-class']).'';
            }
            $temp_field .= '">';
           
            $options_field['id'] = $base_id.'_'. str_pad($count++, 2, '0', STR_PAD_LEFT);
            $temp_field .= self::radio($name, $label, $value, $selected_value, $options_field, true);
           
            // se è l'ultimo campo e c'è un invalid-feedback
            if ($count == count($list_of_radio) && array_key_exists('invalid-feedback', $options_group)) {
                $temp_field .= '<div class="invalid-feedback">'._rt($options_group['invalid-feedback']).'</div>';
            }

            $temp_field .= '</div>';
            $radios[] = $temp_field;
            
        }
        //   https://stackoverflow.com/questions/47546087/display-invalid-feedback-text-for-radio-button-group-in-bootstrap-4
        $classes_field = (array_key_exists('form-group-class', $options_group)) ? ' '._r($options_group['form-group-class']).'' : '';
        if ($options_group['class'] ?? '') {
            $classes_field .= ' '._r($options_group['class']).'';
        }
        if (stripos($classes_field, 'is-invalid') !== false) {
            $classes_field .= ' js-radio-remove-is-invalid';
        }
        $field = '<div class="js-form-radios-group form-group'.$classes_field.'">';

        if (array_key_exists('label', $options_group)) {
            $field .= '<label class="label-radios me-4 text-body-secondary">'._rt($options_group['label']).'</label>';
        }
        $field .= implode('', $radios);
        
        $field .= '</div>';
        // hook per modificare il campo
        $field = Hooks::run('form_radios', $field, $name, $list_of_radio, $selected_value,  $inline,  $options_group, $options_field);
        if ($return) {
            return $field;
        } else {
            echo $field;
        }
    }

    /**
     * Renders a Bootstrap-styled select dropdown
     *
     * @example
     * // Basic select with options
     * $countries = [
     *     'us' => 'United States',
     *     'ca' => 'Canada',
     *     'uk' => 'United Kingdom',
     *     'au' => 'Australia'
     * ];
     * Form::select('country', 'Select Country', $countries, 'us', [
     *     'required' => true,
     *     'class' => 'form-select-lg',
     *     'invalid-feedback' => 'Please select a country'
     * ]);
     *
     * // Select with option groups
     * $groupedOptions = [
     *     'Americas' => [
     *         'us' => 'United States',
     *         'ca' => 'Canada',
     *         'br' => 'Brazil'
     *     ],
     *     'Europe' => [
     *         'uk' => 'United Kingdom',
     *         'fr' => 'France',
     *         'de' => 'Germany'
     *     ]
     * ];
     * Form::select('region', 'Select Region', $groupedOptions, 'fr', [
     *     'floating' => false // Disable floating label if needed
     * ]);
     *
     * @param string $name The name attribute of the select element
     * @param string $label The label text for the select field
     * @param array $select_options Associative array of options or option groups
     *   - Simple array: ['value1' => 'Label 1', 'value2' => 'Label 2']
     *   - With groups: ['Group 1' => ['v1' => 'Label 1'], 'Group 2' => ['v2' => 'Label 2']]
     * @param string $selected The value of the selected option (optional)
     * @param array $options Additional HTML attributes and options:
     *   - 'class' => string Additional CSS classes
     *   - 'required' => bool Whether the field is required
     *   - 'multiple' => bool Allow multiple selections
     *   - 'floating' => bool Enable/disable floating label (default: true)
     *   - 'invalid-feedback' => string Error message for validation
     *   - Other standard HTML5 select attributes (size, disabled, etc.)
     * @param bool $return If true, returns the HTML instead of echoing it
     * @return string|void Returns HTML if $return is true, otherwise echoes it
     */
    public static function select($name, $label, $select_options, $selected = '', $options = [],  $return = false) {
        $options['class'] = (array_key_exists('class', $options)) ? $options['class']." ".'form-select' : 'form-select';
        self::applyInvalidClass($options, $name);

        // HTML select does not support readonly. Convert it to disabled behavior.
        $isReadonly = array_key_exists('readonly', $options) && $options['readonly'] !== false;
        if ($isReadonly && (!array_key_exists('disabled', $options) || $options['disabled'] !== true)) {
            $options['disabled'] = true;
        }
       
        if ((array_key_exists('floating', $options) && $options['floating'] == false) ) {
            $floating = false;
            unset($options['floating']);
        } else {
            $floating = true;
        }
        $id = self::id($options, $name);
        $label_dom = ($label != '') ? '<label for="'.$id.'">'._rt($label).'</label>' : '';
        $field = ($floating) ? '<div class="form-floating">' : $label_dom;
        $field .= '<select name="'._r($name).'"';
        $field .= ' id="'._r($id).'"';

        // Add data-error-message attribute if invalid-feedback is set
        if (array_key_exists('invalid-feedback', $options)) {
            $field .= ' data-error-message="'._r($options['invalid-feedback']).'"';
        }

        $field .= self::attr($options);
        $field .= '>';
        // select_options is an associative array that accepts option groups
        foreach ($select_options as $key => $value) {
            if (is_array($value)) {
                $field .= '<optgroup label="'._r($key).'">';
                foreach ($value as $k => $v) {
                    $field .= '<option value="'._r($k).'"';
                    if ($selected == $k) {
                        $field .= ' selected';
                    }
                    $field .= '>'._rt($v).'</option>';
                }
                $field .= '</optgroup>';
            } else {
                $field .= '<option value="'._r($key).'"';
                if ($selected == $key) {
                    $field .= ' selected';
                }
                $field .= '>'._rt($value).'</option>';
            }
        }

        $field .= '</select>';

        // Disabled controls are not submitted, so preserve readonly select value(s).
        if ($isReadonly) {
            $isMultiple = array_key_exists('multiple', $options) && $options['multiple'] !== false;
            if ($isMultiple) {
                $hiddenName = str_ends_with((string) $name, '[]') ? (string) $name : ((string) $name . '[]');
                $selectedValues = is_array($selected) ? $selected : (($selected === '' || $selected === null) ? [] : [$selected]);
                foreach ($selectedValues as $selectedValue) {
                    $field .= '<input type="hidden" name="' . _r($hiddenName) . '" value="' . _r((string) $selectedValue) . '">';
                }
            } else {
                $hiddenValue = is_array($selected) ? (string) (reset($selected) ?: '') : (string) $selected;
                $field .= '<input type="hidden" name="' . _r($name) . '" value="' . _r($hiddenValue) . '">';
            }
        }

        $field .= ($floating) ? $label_dom : '';
        if (array_key_exists('invalid-feedback', $options)) {
            $field .= '<div class="invalid-feedback">'._rt($options['invalid-feedback']).'</div>';
        }

        if (array_key_exists('help-text', $options)) {
            $field .= '<div class="form-text">'._rt($options['help-text']).'</div>';
        }

        $field .= ($floating) ? '</div>' : '';
        // hook per modificare il campo
        $field = Hooks::run('form_select', $field, $name, $label, $options, $selected);
        if ($return) {
            return $field;
        } else {
            echo $field;
        }
    }

    /**
     * Renders a clickable action list with hidden input for value storage
     *
     * @example
     * // Basic action list
     * $filters = [
     *     'all' => 'All',
     *     'active' => 'Active', 
     *     'suspended' => 'Suspended',
     *     'trash' => 'Trash'
     * ];
     * Form::actionList('filter', 'Filter by status', $filters, 'trash', [
     *     'class' => 'filter-actions mb-3',
     *     'item-class' => 'link-action',
     *     'active-class' => 'active-action-list',
     *     'onchange' => 'handleFilterChange(this.value)'
     * ], [
     *     'data-validate' => 'required',
     *     'class' => 'custom-hidden-input'
     * ]);
     *
     * @param string $name The name attribute for the hidden input
     * @param string $label The label text for the action list (can be empty)
     * @param array $list_options Associative array of value => label pairs
     * @param string $selected The currently selected value
     * @param array $options Additional options for the container:
     *   - 'class' => string CSS class for the container
     *   - 'item-class' => string CSS class for each action item (default: 'link-action')
     *   - 'active-class' => string CSS class for the active item (default: 'active-action-list')
     *   - 'container-tag' => string HTML tag for container (default: 'div')
     *   - 'item-tag' => string HTML tag for items (default: 'span')
     *   - 'onchange' => string JavaScript to execute when selection changes
     *   - Other HTML attributes for the container
     * @param array $input_options Additional HTML attributes for the hidden input field:
     *   - 'class' => string CSS class for the hidden input
     *   - 'data-*' => string Data attributes
     *   - Other standard HTML5 input attributes
     * @param bool $return If true, returns the HTML instead of echoing it
     * @return string|void Returns HTML if $return is true, otherwise echoes it
     */
    public static function actionList($name, $label, $list_options, $selected = '', $options = array(), $input_options = array(), $return = false) {
        $id = self::id($options, $name);
        $container_tag = (array_key_exists('container-tag', $options)) ? $options['container-tag'] : 'div';
        $item_tag = (array_key_exists('item-tag', $options)) ? $options['item-tag'] : 'span';
        $item_class = (array_key_exists('item-class', $options)) ? $options['item-class'] : 'link-action';
        $active_class = (array_key_exists('active-class', $options)) ? $options['active-class'] : 'active-action-list';
        $onchange = (array_key_exists('onchange', $options)) ? $options['onchange'] : '';
        
        // Container class
        $container_class = (array_key_exists('class', $options)) ? $options['class'] : '';
        
        // Process container attributes using the same method as other form elements
        $container_options = $options;
        $exclude_attrs = array('item-class', 'active-class', 'container-tag', 'item-tag', 'onchange');
        foreach ($exclude_attrs as $attr) {
            unset($container_options[$attr]);
        }
        $container_attrs = self::attr($container_options);
        
        // Apply validation classes to input options if MessagesHandler exists
        self::applyInvalidClass($input_options, $name);
        
        // Set input ID if not provided in input_options
        if (!array_key_exists('id', $input_options)) {
            $input_options['id'] = $id;
        }
        
        // Label HTML - render before hidden input like other form elements
        $label_html = ($label != '') ? '<label for="'.$id.'">'._rh($label).'</label>' : '';
        
        // Hidden input to store the selected value
        $field = $label_html;
        $field .= '<input type="hidden" name="' . _r($name) . '" value="' . _r($selected) . '"';
        
        // Add onchange from main options if provided (for backward compatibility)
        if ($onchange) {
            $input_options['onchange'] = $onchange;
        }
        
        // Apply all input attributes using the standard method
        $field .= self::attr($input_options);
        $field .= '>';
        
        // Container with proper class handling
        $container_classes = 'js-action-list action-list-container';
        if ($container_class != '') {
            $container_classes .= ' ' . $container_class;
        }
        $field .= '<' . $container_tag . ' class="' . $container_classes . '" data-target-input="' . $id . '"' . $container_attrs . '>';
        
        // Action items
        foreach ($list_options as $value => $label) {
            $is_active = ($selected == $value);
            $item_classes = $item_class . ' js-action-item';
            if ($is_active) {
                $item_classes .= ' ' . $active_class;
            }
            
            $field .= '<' . $item_tag . ' class="' . $item_classes . '" data-value="' . _r($value) . '">';
            $field .= _rh($label);
            $field .= '</' . $item_tag . '>';
        }
        
        $field .= '</' . $container_tag . '>';
        
        // Hook per modificare il campo
        $field = Hooks::run('form_action_list', $field, $name, $label, $list_options, $selected, $options, $input_options);
        
        if ($return) {
            return $field;
        } else {
            echo $field;
        }
    }

    /**
     * Generates a unique ID for form elements
     *
     * @internal This method is used internally to generate unique IDs for form elements
     * when no explicit ID is provided. It ensures each form element has a unique identifier
     * which is important for proper label association and form accessibility.
     *
     * @param array $options The options array that may contain a custom 'id' key
     * @param string $name The name of the form field (used to generate default ID)
     * @return string The generated or provided ID
     */
    static private function id($options, $name) {
        if (array_key_exists('id', $options)) {
            $id = _r($options['id']);
        } else {
            $id = "form"._raz($name);
        }
        return $id;
    }

    /**
     * Converts an associative array of attributes to HTML attributes string
     *
     * This internal method handles the conversion of PHP array options to
     * proper HTML attributes. It's used by all form element methods to generate
     * the HTML attributes string.
     *
     * @example
     * // Returns: class="form-control" id="username" required minlength="3"
     * Form::attr([
     *     'class' => 'form-control',
     *     'id' => 'username',
     *     'required' => true,
     *     'minlength' => 3,
     *     'placeholder' => 'Enter username'
     * ]);
     *
     * @param array $options Associative array of HTML attributes
     * @return string HTML attributes as a string
     */
    public static function attr($options) {
        $field = '';

        // Handle special boolean attributes
        $array_attributes = array('required', 'disabled', 'readonly', 'input', 'autocomplete', 'autofocus','hidden');
        foreach ($array_attributes as $attribute) {
            if (array_key_exists($attribute, $options)) {
                if ($attribute == 'hidden') {
                    $field .= ' hidden aria-hidden="true" tabindex="-1" ';
                } else if ($options[$attribute] !== false) {
                    $field .= ' '._rh($attribute);
                }
            }
        }

        // Handle toggle visibility attributes (convert toggle-field to data-togglefield)
        if (array_key_exists('toggle-field', $options)) {
            $field .= ' data-togglefield="'._r($options['toggle-field']).'"';
        }
        if (array_key_exists('toggle-value', $options)) {
            $field .= ' data-togglevalue="'._r($options['toggle-value']).'"';
        }

        // Process all other attributes
        foreach ($options as $key => $option) {
            // Skip special cases and non-scalar values
            // Skip toggle-* as they're handled above
            // Skip in-container as it's only for internal logic
            if ($key != _r($key) || in_array($key, $array_attributes) ||
                $key == 'floating' || $key == 'invalid-feedback' || $key == 'help-text' ||
                $key == 'toggle-field' || $key == 'toggle-value' ||
                $key == 'in-container') {
                continue;
            }
            if (!in_array($key, $array_attributes) && is_scalar($options[$key])) {
                $field .= ' '.$key.'="'._r($option).'"';
            }
        }

        return $field;
    }

    private static function getLabelOptions($options) {
        $label_options = array();
        foreach ($options as $key => $option) {
            if (substr($key, 0, 12) == 'label-attrs-') {
                $label_options[substr($key, 12)] = $option;
            }
        }
        return $label_options;
    }

    /**
     * Renders a MilkSelect autocomplete field with single or multiple selection
     *
     * @example
     * // Basic single select
     * Form::milkSelect('city_id', 'City', [
     *     1 => 'Milano',
     *     2 => 'Roma',
     *     3 => 'Napoli'
     * ], 2);
     *
     * // Multiple select with preselection
     * Form::milkSelect('skills', 'Skills', [
     *     10 => 'PHP',
     *     20 => 'JavaScript',
     *     30 => 'Python'
     * ], [10, 20], ['type' => 'multiple']);
     *
     * @param string $name The name attribute for the hidden input
     * @param string $label The label text for the field
     * @param array $select_options Associative array of options (can use IDs as keys)
     * @param mixed $selected The selected value(s) - can be single value or array for multiple
     * @param array $options Additional options:
     *   - 'type' => 'single'|'multiple' (default: 'single')
     *   - 'required' => bool
     *   - 'placeholder' => string
     *   - 'class' => string (CSS classes for hidden input)
     *   - 'floating' => bool (default: true)
     *   - 'invalid-feedback' => string (validation error message)
     * @param bool $return If true, returns the HTML instead of echoing it
     * @return string|void Returns HTML if $return is true, otherwise echoes it
     */
    public static function milkSelect($name, $label, $select_options, $selected = '', $options = [], $return = false) {
        // Determine select type
        $type = (array_key_exists('type', $options)) ? $options['type'] : 'single';
        unset($options['type']);

        // Check if floating label should be used
        if ((array_key_exists('floating', $options) && $options['floating'] == false)) {
            $floating = false;
            unset($options['floating']);
        } else {
            $floating = true;
        }

        // Generate ID
        $id = self::id($options, $name);

        // Apply validation classes
        self::applyInvalidClass($options, $name);

        // Extract placeholder if provided, default to label for floating
        $placeholder = '';
        if (array_key_exists('placeholder', $options)) {
            $placeholder = $options['placeholder'];
            unset($options['placeholder']);
        } else if ($floating) {
            $placeholder = $label;
        }

        // Extract invalid feedback
        $invalid_feedback = '';
        if (array_key_exists('invalid-feedback', $options)) {
            $invalid_feedback = $options['invalid-feedback'];
            unset($options['invalid-feedback']);
        }

        // Build field HTML
        $label_dom = ($label != '') ? '<label for="'.$id.'">'._rt($label).'</label>' : '';

        // For non-floating, show label before plugin
        $field = ($floating) ? '<div class="form-floating milkselect-floating">' : $label_dom;

        // Generate MilkSelect plugin
        $plugin_options = [
            'id' => $id,
            'options' => $select_options,
            'type' => $type,
            'value' => $selected,
            'name' => $name,
            'placeholder' => $placeholder,
            'floating' => $floating
        ];

        // Add optional parameters
        if (array_key_exists('required', $options) && $options['required']) {
            $plugin_options['required'] = true;
            unset($options['required']);
        }
        if (array_key_exists('class', $options)) {
            $plugin_options['class'] = $options['class'];
            unset($options['class']);
        }
        if (array_key_exists('api_url', $options)) {
            $plugin_options['api_url'] = $options['api_url'];
            unset($options['api_url']);
        }
        if (array_key_exists('display_value', $options)) {
            $plugin_options['display_value'] = $options['display_value'];
            unset($options['display_value']);
        }

        // Render the plugin
        $field .= Get::themePlugin('MilkSelect', $plugin_options);

        // Add label for floating (after the plugin/input)
        $field .= ($floating) ? $label_dom : '';

        // Add validation feedback
        if ($invalid_feedback) {
            $field .= '<div class="invalid-feedback">'._rt($invalid_feedback).'</div>';
        }

        $field .= ($floating) ? '</div>' : '';

        // Apply hook
        $field = Hooks::run('form_milkselect', $field, $name, $label, $options, $selected);

        if ($return) {
            return $field;
        } else {
            echo $field;
        }
    }

    /**
     * Applies validation error classes to form fields
     *
     * This internal method checks if a form field has validation errors
     * and adds the appropriate error class to the field's options.
     * It's used automatically by all form field methods.
     *
     * @internal
     * @param array &$options Reference to the field's options array
     * @param string $name The name of the form field to check for errors
     * @return void
     */
    private static function applyInvalidClass(&$options, $name) {
        // Normalize field name by removing data[ prefix and ] suffix
        // This allows MessagesHandler to match field names correctly
        $normalized_name = $name;
        if (preg_match('/^data\[(.+)\]$/', $name, $matches)) {
            $normalized_name = $matches[1];
        }

        $invalid_class = MessagesHandler::getInvalidClass($normalized_name);
        if ($invalid_class == '' && $normalized_name !== $name) {
            $invalid_class = MessagesHandler::getInvalidClass($name);
        }
        if ($invalid_class != '') {
            $options['class'] = (array_key_exists('class', $options)) ?
                $options['class'] . " " . $invalid_class :
                $invalid_class;

            // Add invalid-feedback message if not already set
            if (!array_key_exists('invalid-feedback', $options)) {
                $errors = MessagesHandler::getErrors();
                // First check if there's a message with this exact field name
                if (isset($errors[$normalized_name])) {
                    $options['invalid-feedback'] = $errors[$normalized_name];
                } else if (isset($errors[$name])) {
                    $options['invalid-feedback'] = $errors[$name];
                } else {
                    // Otherwise, search for the field in composite keys (e.g., "field1|field2")
                    foreach ($errors as $key => $message) {
                        if (strpos($key, '|') !== false) {
                            $fields = explode('|', $key);
                            if (in_array($normalized_name, $fields, true) || in_array($name, $fields, true)) {
                                $options['invalid-feedback'] = $message;
                                break;
                            }
                        }
                    }
                }
            }
        }

    }
}
