<?php
namespace MilkCore;
use MilkCore\Hooks;
use MilkCore\MessagesHandler;
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
 * @package     MilkCore
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
     *     'placeholder' => 'your.email@example.com',
     *     'invalid-feedback' => 'Please enter a valid email address'
     * ]);
     *
     * // Input with datalist
     * Form::input('text', 'browser', 'Browser', '', [
     *     'list' => ['Chrome', 'Firefox', 'Safari', 'Edge'],
     *     'placeholder' => 'Select or type a browser'
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
     *   - 'placeholder' => string Placeholder text
     *   - 'invalid-feedback' => string in a div with class "invalid-feedback" Error message for validation
     *   - 'list' => array Options for datalist
     *   - Other standard HTML5 input attributes (min, max, step, pattern, etc.)
     * @param bool $return If true, returns the HTML instead of echoing it
     * @return string|void Returns HTML if $return is true, otherwise echoes it
     */

    public static function input($type, $name, $label, $value = '', $options = array(), $return = false) {
        $options['class'] = (array_key_exists('class', $options)) ? $options['class']." ".'form-control' : 'form-control';
        self::apply_invalid_class($options, $name);
        if ((array_key_exists('floating', $options) && $options['floating'] == false) || $type == 'file'|| $type == 'color') {
            $floating = false;
            unset($options['floating']);
            if ($type == 'color') {
                $options['class'] = (array_key_exists('class', $options)) ? $options['class']." ".'form-control-color' : 'form-control-color';
            }
        } else {
            $floating = true;
        }
      
        $id = self::id($options, $name);
        $placeholer = ($options['placeholder'] ?? '');
        $label_html = ($label != '' && $type != "hidden") ? '<label for="'.$id.'">'._rh($label).'</label>' : '';
        $field = ($floating) ? '<div class="form-floating">' : (($label != '') ? $label_html  : '');
        $field .= '<input type="'.$type.'" name="'._r($name).'" placeholder="'._r($placeholer).'"'.($value != '' ? ' value="'._r($value).'"' : '').' ';
       
      
        $field .= ' id="'.$id.'"';
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
        self::apply_invalid_class($options, $name);
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
        $options['class'] = (array_key_exists('class', $options)) ? $options['class']." ".'form-check-input' : 'form-check-input';
     
        $field = '<input type="checkbox" value="'._r($value).'" name="'._r($name).'"';
        if ($is_checked) {
            $field .= ' checked';
        }
        $id = self::id($options, $name);
        $field .= ' id="'.$id.'"';
        $field .= self::attr($options);
        $field .= '>';
        $field .= '<label class="form-check-label" for="'.$id.'">'._rh($label).'</label>';
        if (array_key_exists('invalid-feedback', $options)) {
            $field .= '<div class="invalid-feedback">'._rh($options['invalid-feedback']).'</div>';
        }
        // invalid 
        if (class_exists('MilkCore\MessagesHandler') && strpos($name, '[]') === false) {
            $invalid_class = MessagesHandler::get_invalid_class($name);
            if ($invalid_class != '') {
                $field = '<div class="' . $invalid_class . ' js-checkbox-remove-is-invalid ">' .  $field . '</div>';
            }
        }
      
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
     ];
     * $selected = ['sports', 'music'];
     * 
     * Form::checkboxes('interests', $interests, $selected, true, [
     *     'form-group-class' => 'mb-3',
     *     'label' => 'Your Interests',
     *     'invalid-feedback' => 'Please select at least one interest'
     ], [
     *     'class' => 'form-check-input me-2',
     *     'required' => true
     ]);
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
        self::apply_invalid_class($options_group, $name);
        
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
     ]);
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
        self::apply_invalid_class($options, $name);
        $field = '<input class="form-check-input" type="radio" value="'._r($value).'" name="'._r($name).'"';
        if ($selected_value == $value) {
            $field .= ' checked';
        }
        $id = self::id($options, $name);
        $field .= ' id="'.$id.'"';
        $field .= self::attr($options);
        $field .= '>';
        $field .= '<label class="form-check-label" for="'.$id.'">'._rt($label).'</label>';
        if (array_key_exists('invalid-feedback', $options)) {
            $field .= '<div class="invalid-feedback">'._rt($options['invalid-feedback']).'</div>';
        }
        // invalid-feedback is not supported on single radio buttons (It will be supported on radio groups!)

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
        self::apply_invalid_class($options_group, $name);
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
            $field .= '<label class="label-radios me-4">'._rt($options_group['label']).'</label>';
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
        self::apply_invalid_class($options, $name);
       
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
        $field .= ($floating) ? $label_dom : '';
        if (array_key_exists('invalid-feedback', $options)) {
            $field .= '<div class="invalid-feedback">'._rt($options['invalid-feedback']).'</div>';
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
     ]);
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
        
        // Process all other attributes
        foreach ($options as $key => $option) {
            // Skip special cases and non-scalar values
            if ($key != _r($key) || in_array($key, $array_attributes) || 
                $key == 'floating' || $key == 'invalid-feedback') {
                continue;
            }
            if (!in_array($key, $array_attributes) && is_scalar($options[$key])) {
                $field .= ' '.$key.'="'._r($option).'"';
            }
        }
       
        return $field;
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
    private static function apply_invalid_class(&$options, $name) {
        if (class_exists('MilkCore\MessagesHandler')) {
            $invalid_class = MessagesHandler::get_invalid_class($name);
            if ($invalid_class != '') {
                $options['class'] = (array_key_exists('class', $options)) ? 
                    $options['class'] . " " . $invalid_class : 
                    $invalid_class;
            }
        }
    }
}
