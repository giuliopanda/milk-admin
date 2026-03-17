<?php
namespace Builders\Traits\FormBuilder;

use Builders\Exceptions\BuilderException;

!defined('MILK_DIR') && die();

/**
 * FieldFirstTrait - Field-first style API methods for FormBuilder
 *
 * Provides fluent interface: ->field('name')->label('...')->type('...')
 *
 * This trait allows configuring form fields using a field-first approach:
 * - If field exists: modifies it
 * - If field doesn't exist: creates it
 */
trait FieldFirstTrait
{
    /**
     * Current field being configured
     * @var string|null
     */
    private ?string $current_field = null;

    /**
     * Select a field for configuration (or create if it doesn't exist)
     *
     * @param string $key Field name
     * @return static For method chaining
     *
     * @example ->field('email')->label('Email Address')->required()
     */
    public function field(string $key): static
    {
        if ($key === '') {
            throw BuilderException::invalidField($key);
        }

        // If field doesn't exist, create it with minimal structure (only name)
        // Other defaults will be set by addFieldsFromObject() to allow FormBuilder changes to override Model
        if (!isset($this->fields[$key])) {
            if (isset($this->fields_copy[$key])) {
                $this->fields[$key] = $this->fields_copy[$key];
            } else {
                $this->fields[$key] = [
                    'name' => $key,
                    'type' => 'string'
                ];
            }
        }
        if (isset($this->removed_fields[$key])) {
            unset($this->removed_fields[$key]);
        }
        $this->current_field = $key;
        return $this;
    }

    /**
     * Get current field name
     *
     * @return string|null
     */
    private function getCurrentField(): ?string
    {
        return $this->current_field;
    }

    /**
     * Reset current field
     *
     * @return void
     */
    private function resetCurrentField(): void
    {
        $this->current_field = null;
    }

    /**
     * Require current field to be set (throws exception if not)
     *
     * @param string $method Method name for error message
     * @return string Current field name
     * @throws BuilderException
     */
    private function requireCurrentField(string $method): string
    {
        if ($this->current_field === null) {
            throw BuilderException::noCurrentField($method);
        }
        return $this->current_field;
    }

    // ========================================================================
    // FIELD CONFIGURATION METHODS
    // ========================================================================

    /**
     * Set data type for current field
     *
     * @param string $type Data type (string, int, date, datetime, etc.)
     * @return static For method chaining
     *
     * @example ->field('age')->type('int')
     */
    public function type(string $type): static
    {
        $key = $this->requireCurrentField('type');
        $this->fields[$key]['type'] = $type;
        return $this;
    }

    /**
     * Set form type for current field
     *
     * @param string $formType Form type (text, select, checkbox, textarea, file, image, etc.)
     * @return static For method chaining
     *
     * @example ->field('category')->formType('select')
     */
    public function formType(string $formType): static
    {
        $key = $this->requireCurrentField('formType');
        $this->fields[$key]['form-type'] = $formType;
        return $this;
    }

    /**
     * Set label for current field
     *
     * @param string $label Label text
     * @return static For method chaining
     *
     * @example ->field('email')->label('Email Address')
     */
    public function label(string $label): static
    {
        $key = $this->requireCurrentField('label');
        $this->fields[$key]['label'] = $label;
        return $this;
    }

    /**
     * Set options for current field (select, checkbox, radio)
     *
     * @param array $options Options array
     * @return static For method chaining
     *
     * @example ->field('status')->options(['active' => 'Active', 'inactive' => 'Inactive'])
     */
    public function options(array $options): static
    {
        $key = $this->requireCurrentField('options');
        $this->fields[$key]['options'] = $options;
        return $this;
    }

    /**
     * Make current field required
     *
     * @param bool $required Whether field is required
     * @return static For method chaining
     *
     * @example ->field('email')->required()
     */
    public function required(bool $required = true): static
    {
        $key = $this->requireCurrentField('required');
        $this->fields[$key]['required'] = $required;

        // Also set in form-params for proper rendering
        if (!isset($this->fields[$key]['form-params'])) {
            $this->fields[$key]['form-params'] = [];
        }
        $this->fields[$key]['form-params']['required'] = $required;

        return $this;
    }

    /**
     * Set help text for current field (small descriptive text below the field)
     *
     * @param string $helpText Help text to display below the field
     * @return static For method chaining
     *
     * @example ->field('email')->helpText('We will never share your email with anyone else')
     * @example ->field('phone')->helpText('Format: 555-1234')
     */
    public function helpText(string $helpText): static
    {
        $key = $this->requireCurrentField('helpText');

        if (!isset($this->fields[$key]['form-params'])) {
            $this->fields[$key]['form-params'] = [];
        }
        $this->fields[$key]['form-params']['help-text'] = $helpText;

        return $this;
    }

    /**
     * Set value for current field (sets row_value for rendering priority)
     *
     * @param mixed $value Field value
     * @return static For method chaining
     *
     * @example ->field('status')->value('active')
     */
    public function value(mixed $value): static
    {
        $key = $this->requireCurrentField('value');
        $this->fields[$key]['set_value'] = $value;
        return $this;
    }

    /**
     * Set default value for current field (used if no value is set)
     *
     * @param mixed $value Default value
     * @return static For method chaining
     *
     * @example ->field('country')->default('US')
     */
    public function default(mixed $value): static
    {
        $key = $this->requireCurrentField('default');
        $this->fields[$key]['default'] = $value;
        return $this;
    }

    /**
     * Disable current field
     *
     * @param bool $disabled Whether field is disabled
     * @return static For method chaining
     *
     * @example ->field('id')->disabled()
     */
    public function disabled(bool $disabled = true): static
    {
        $key = $this->requireCurrentField('disabled');

        if (!isset($this->fields[$key]['form-params'])) {
            $this->fields[$key]['form-params'] = [];
        }
        $this->fields[$key]['form-params']['disabled'] = $disabled;

        return $this;
    }

    /**
     * Make current field readonly
     *
     * @param bool $readonly Whether field is readonly
     * @return static For method chaining
     *
     * @example ->field('created_at')->readonly()
     */
    public function readonly(bool $readonly = true): static
    {
        $key = $this->requireCurrentField('readonly');

        if (!isset($this->fields[$key]['form-params'])) {
            $this->fields[$key]['form-params'] = [];
        }
        $this->fields[$key]['form-params']['readonly'] = $readonly;

        return $this;
    }

    /**
     * Set CSS class for current field
     *
     * @param string $class CSS class names
     * @return static For method chaining
     *
     * @example ->field('email')->class('custom-input')
     */
    public function class(string $class): static
    {
        $key = $this->requireCurrentField('class');

        if (!isset($this->fields[$key]['form-params'])) {
            $this->fields[$key]['form-params'] = [];
        }
        $this->fields[$key]['form-params']['class'] = $class;

        return $this;
    }

    /**
     * Set error message for current field
     *
     * @param string $message Error message text
     * @return static For method chaining
     *
     * @example ->field('email')->errorMessage('Please enter a valid email address')
     */
    public function errorMessage(string $message): static
    {
        $key = $this->requireCurrentField('errorMessage');

        if (!isset($this->fields[$key]['form-params'])) {
            $this->fields[$key]['form-params'] = [];
        }
        $this->fields[$key]['form-params']['invalid-feedback'] = $message;

        return $this;
    }

    /**
     * Set calculated expression for current field (MilkForm)
     *
     * @param string $expression Expression to calculate field value
     * @return static For method chaining
     *
     * @example ->field('total')->calcExpr('[qty] * [price]')
     */
    public function calcExpr(string $expression): static
    {
        $key = $this->requireCurrentField('calcExpr');

        if (!isset($this->fields[$key]['form-params'])) {
            $this->fields[$key]['form-params'] = [];
        }
        $this->fields[$key]['form-params']['data-milk-expr'] = $expression;

        return $this;
    }

    /**
     * Set default expression for current field (MilkForm, JS only)
     *
     * @param string $expression Expression to calculate default value
     * @return static For method chaining
     *
     * @example ->field('end_time')->defaultExpr('TIMEADD([start_time], 45)')
     */
    public function defaultExpr(string $expression): static
    {
        $key = $this->requireCurrentField('defaultExpr');

        if (!isset($this->fields[$key]['form-params'])) {
            $this->fields[$key]['form-params'] = [];
        }
        $this->fields[$key]['form-params']['data-milk-default-expr'] = $expression;

        return $this;
    }

    /**
     * Set validation expression for current field (MilkForm)
     *
     * @param string $expression Expression that must evaluate to true
     * @param string|null $message Optional validation message
     * @return static For method chaining
     *
     * @example ->field('end_date')->validateExpr('[start_date] < [end_date]', 'La data di fine deve essere successiva')
     */
    public function validateExpr(string $expression, ?string $message = null): static
    {
        $key = $this->requireCurrentField('validateExpr');

        if (!isset($this->fields[$key]['form-params'])) {
            $this->fields[$key]['form-params'] = [];
        }
        $this->fields[$key]['form-params']['data-milk-validate-expr'] = $expression;
        if ($message !== null) {
            $this->fields[$key]['form-params']['data-milk-message'] = $message;
        }

        return $this;
    }

    /**
     * Make current field required if expression evaluates to true (MilkForm)
     *
     * @param string $expression Expression that makes field required
     * @param string|null $message Optional validation message
     * @return static For method chaining
     *
     * @example ->field('notes')->requireIf('[plan] == "enterprise"', 'Note obbligatorie per piano enterprise')
     */
    public function requireIf(string $expression, ?string $message = null): static
    {
        $key = $this->requireCurrentField('requireIf');

        if (!isset($this->fields[$key]['form-params'])) {
            $this->fields[$key]['form-params'] = [];
        }
        $this->fields[$key]['form-params']['data-milk-required-if'] = $expression;
        if ($message !== null) {
            $this->fields[$key]['form-params']['data-milk-message'] = $message;
        }

        return $this;
    }

  /**
     * Reset all fields - hides all existing fields from the model
     * Useful when you want to start with a clean slate and only show specific fields
     *
     * @return static For method chaining
     * @example
     * FormBuilder::create($model, $this->page)
     *     ->resetFields()  // Hide all existing fields
     *     ->field('id')    // Show only the fields you want
     *     ->field('name')
     *     ->field('email')
     */
    public function resetFields(): static
    {
        $this->fields_copy = $this->fields;
        foreach ($this->fields as $key => $_) {
            if ($this->fields[$key]['primary'] === true) continue;
            $this->removeField($key);
            unset($this->fields[$key]);
        }
        return $this;
    }

    /**
     * Move current field before another field
     *
     * @param string $fieldName Field name to insert before
     * @return static For method chaining
     *
     * @example ->field('email')->moveBefore('password')
     */
    public function moveBefore(string $fieldName): static
    {
        $key = $this->requireCurrentField('moveBefore');

        if (!isset($this->fields[$key])) {
            return $this;
        }

        // If target field doesn't exist, do nothing
        if (!isset($this->fields[$fieldName])) {
            return $this;
        }

        // Store the field to move
        $fieldToMove = $this->fields[$key];

        // Remove from current position
        unset($this->fields[$key]);

        // Rebuild array with field in new position
        $newFields = [];
        foreach ($this->fields as $name => $field) {
            if ($name === $fieldName) {
                // Insert field before target
                $newFields[$key] = $fieldToMove;
            }
            $newFields[$name] = $field;
        }

        $this->fields = $newFields;

        return $this;
    }

    /**
     * Alias for moveBefore() - keeps fluent chains short
     *
     * @param string $fieldName Field name to insert before
     * @return static For method chaining
     *
     * @example ->addHtml('...')->before('email')
     */
    public function before(string $fieldName): static
    {
        return $this->moveBefore($fieldName);
    }

    /**
     * Move current field after another field
     *
     * @param string $fieldName Field name to insert after
     * @return static For method chaining
     *
     * @example ->field('email')->moveAfter('password')
     */
    public function moveAfter(string $fieldName): static
    {
        $key = $this->requireCurrentField('moveAfter');

        if (!isset($this->fields[$key])) {
            return $this;
        }

        // If target field doesn't exist, do nothing
        if (!isset($this->fields[$fieldName])) {
            return $this;
        }

        // Store the field to move
        $fieldToMove = $this->fields[$key];

        // Remove from current position
        unset($this->fields[$key]);

        // Rebuild array with field in new position
        $newFields = [];
        foreach ($this->fields as $name => $field) {
            $newFields[$name] = $field;
            if ($name === $fieldName) {
                // Insert field after target
                $newFields[$key] = $fieldToMove;
            }
        }

        $this->fields = $newFields;

        return $this;
    }

    /**
     * Alias for moveAfter() - keeps fluent chains short
     *
     * @param string $fieldName Field name to insert after
     * @return static For method chaining
     *
     * @example ->addHtml('...')->after('email')
     */
    public function after(string $fieldName): static
    {
        return $this->moveAfter($fieldName);
    }

    /**
     * DEBUG: Print current field structure and die
     *
     * @return void
     */
    public function debug(): void
    {
        $key = $this->requireCurrentField('debug');

        echo "<pre style='background: #f5f5f5; padding: 20px; border: 2px solid #333; margin: 20px;'>";
        echo "<h3 style='margin: 0 0 10px 0; color: #c00;'>🐛 DEBUG FIELD: {$key}</h3>";
        echo "<strong>Field Structure:</strong>\n";
        print_r($this->fields[$key]);
        echo "\n\n<strong>All Fields Keys:</strong>\n";
        print_r(array_keys($this->fields));
        echo "</pre>";
        die();
    }
}
