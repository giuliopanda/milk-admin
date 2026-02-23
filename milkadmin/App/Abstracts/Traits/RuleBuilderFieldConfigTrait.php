<?php
namespace App\Abstracts\Traits;

!defined('MILK_DIR') && die();

/**
 * RuleBuilderFieldConfigTrait - Field configuration methods
 *
 * Provides methods for configuring fields: label, default, nullable, required,
 * primary, unique, index, visibility (hide*), form type/params, validation,
 * min/max, getter/setter, options, apiUrl, etc.
 */
trait RuleBuilderFieldConfigTrait
{
    /**
     * Set field options
     *
     * @param array $options Options array
     * @return self
     */
    public function options(array $options): self
    {
        $this->rules[$this->current_field]['options'] = $options;
        return $this;
    }

    /**
     * Set API URL for dynamic options loading
     *
     * @param string $url API endpoint URL for fetching options
     * @param string|null $display_field Field name to display (e.g., 'name')
     * @return self
     */
    public function apiUrl(string $url, ?string $display_field = null): self
    {
        $this->rules[$this->current_field]['api_url'] = $url;
        if ($display_field !== null) {
            $this->rules[$this->current_field]['api_display_field'] = $display_field;
        }
        return $this;
    }

    // ========================================
    // Field configuration methods
    // ========================================

    /**
     * Set field label
     *
     * @param string $label Label text
     * @return self
     */
    public function label(string $label): self
    {
        $this->rules[$this->current_field]['label'] = $label;
        return $this;
    }

    /**
     * Set default value
     *
     * @param mixed $value Default value
     * @return self
     */
    public function default($value): self
    {
        $this->rules[$this->current_field]['default'] = $value;
        return $this;
    }

    /**
     * Define checkbox values (checked and unchecked)
     *
     * @param mixed $checked_value Value when checkbox is checked
     * @param mixed $unchecked_value Value when checkbox is unchecked
     * @return self
     */
    public function checkboxValues($checked_value, $unchecked_value = null): self
    {
        $this->rules[$this->current_field]['checkbox_checked'] = $checked_value;
        $this->rules[$this->current_field]['checkbox_unchecked'] = $unchecked_value;
        return $this;
    }

    /**
     * Set a value that will always be saved
     *
     * @param mixed $value Value to save
     * @return self
     */
    public function saveValue($value): self
    {
        $this->rules[$this->current_field]['save_value'] = $value;
        return $this;
    }

    public function changeType($name, string $type): self
    {
        $this->rules[$name]['type'] = $type;
        return $this;
    }

    /**
     * Make field nullable
     *
     * @param bool $nullable Nullable flag
     * @return self
     */
    public function nullable(bool $nullable = true): self
    {
        $this->rules[$this->current_field]['nullable'] = $nullable;
        return $this;
    }

    /**
     * Make field required
     *
     * @return self
     */
    public function required(): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['required'] = true;
        return $this;
    }

    /**
     * Make field required only if expression evaluates to true
     *
     * @param string $expression ExpressionParser expression
     * @return self
     */
    public function requireIf(string $expression): self
    {
        $this->rules[$this->current_field]['required_expr'] = $expression;
        return $this;
    }

    /**
     * Set as primary key
     *
     * @return self
     */
    public function primary($primary_key): self
    {
        $this->primary_key = $primary_key;
        $this->rules[$this->current_field]['primary'] = true;
        return $this;
    }

    /**
     * Make field unique
     *
     * @return self
     */
    public function unique(): self
    {
        $this->rules[$this->current_field]['unique'] = true;
        return $this;
    }

    /**
     * Add database index
     *
     * @return self
     */
    public function index(): self
    {
        $this->rules[$this->current_field]['index'] = true;
        return $this;
    }

    /**
     * Hide from list view
     *
     * @return self
     */
    public function hideFromList(): self
    {
        $this->rules[$this->current_field]['list'] = false;
        return $this;
    }

    public function hide(): self
    {
        $this->rules[$this->current_field]['list'] = false;
        $this->rules[$this->current_field]['edit'] = false;
        $this->rules[$this->current_field]['view'] = false;
        return $this;
    }

    /**
     * Hide from edit form
     *
     * @return self
     */
    public function hideFromEdit(): self
    {
        $this->rules[$this->current_field]['edit'] = false;
        return $this;
    }

    /**
     * Hide from detail view
     *
     * @return self
     */
    public function hideFromView(): self
    {
        $this->rules[$this->current_field]['view'] = false;
        return $this;
    }

    /**
     * Exclude from database
     *
     * @return self
     */
    public function excludeFromDatabase(): self
    {
        $this->rules[$this->current_field]['sql'] = false;
        return $this;
    }

    /**
     * Set form type
     *
     * @param string $type Form type
     * @return self
     */
    public function formType(string $type): self
    {
        $this->rules[$this->current_field]['form-type'] = $type;
        return $this;
    }

    /**
     * Set form label
     *
     * @param string $label Form label
     * @return self
     */
    public function formLabel(string $label): self
    {
        $this->rules[$this->current_field]['form-label'] = $label;
        return $this;
    }

    /**
     * Set form parameters
     *
     * @param array $params Parameters array
     * @return self
     */
    public function formParams(array $params): self
    {
        $this->rules[$this->current_field]['form-params'] = $params;
        if (isset($params['pattern'])) {
            $this->rules[$this->current_field]['pattern'] = $params['pattern'];
        }
        if (isset($params['minlength'])) {
            $this->rules[$this->current_field]['min_length'] = $params['minlength'];
        }
        if (isset($params['min_length'])) {
            $this->rules[$this->current_field]['min_length'] = $params['min_length'];
        }
        if (isset($params['maxlength'])) {
            $this->rules[$this->current_field]['max_length'] = $params['maxlength'];
        }
        if (isset($params['max_length'])) {
            $this->rules[$this->current_field]['max_length'] = $params['max_length'];
        }
        return $this;
    }

    /**
     * Set validation error message
     *
     * @param string $message Error message
     * @return self
     */
    public function error(string $message): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['invalid-feedback'] = $message;
        return $this;
    }

    /**
     * Set calculated value expression (ExpressionParser syntax)
     *
     * @param string $expression Calculation expression
     * @return self
     */
    public function calcExpr(string $expression): self
    {
        $this->rules[$this->current_field]['calc_expr'] = $expression;
        return $this;
    }

    /**
     * Set validation expression (ExpressionParser syntax)
     *
     * @param string $expression Validation expression
     * @param string|null $message Optional error message
     * @return self
     */
    public function validateExpr(string $expression, ?string $message = null): self
    {
        $this->rules[$this->current_field]['validate_expr'] = $expression;
        if ($message !== null) {
            $this->error($message);
        }
        return $this;
    }

    /**
     * Set step value for numeric fields
     *
     * @param mixed $value Step value
     * @return self
     */
    public function step($value): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['step'] = $value;
        return $this;
    }

    /**
     * Set minimum value
     *
     * @param mixed $value Min value
     * @return self
     */
    public function min($value): self
    {
        $type = $this->rules[$this->current_field]['type'] ?? null;
        if (is_string($value) && !is_numeric($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            $this->rules[$this->current_field]['min_field'] = $value;
            return $this;
        }
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        if (in_array($type, ['string', 'text'], true)) {
            $this->rules[$this->current_field]['min_length'] = $value;
            $this->rules[$this->current_field]['form-params']['minlength'] = $value;
        } else {
            $this->rules[$this->current_field]['form-params']['min'] = $value;
        }
        return $this;
    }

    /**
     * Set maximum value
     *
     * @param mixed $value Max value
     * @return self
     */
    public function max($value): self
    {
        $type = $this->rules[$this->current_field]['type'] ?? null;
        if (is_string($value) && !is_numeric($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            $this->rules[$this->current_field]['max_field'] = $value;
            return $this;
        }
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        if (in_array($type, ['string', 'text'], true)) {
            $this->rules[$this->current_field]['max_length'] = $value;
            $this->rules[$this->current_field]['form-params']['maxlength'] = $value;
        } else {
            $this->rules[$this->current_field]['form-params']['max'] = $value;
        }
        return $this;
    }

    /**
     * Make numeric field unsigned
     *
     * @return self
     */
    public function unsigned(): self
    {
        $this->rules[$this->current_field]['unsigned'] = true;
        return $this;
    }

    /**
     * Set custom getter function
     *
     * @param callable $fn Getter function
     * @return self
     */
    public function getter(callable $fn): self
    {
        $this->rules[$this->current_field]['_get'] = $fn;
        return $this;
    }

    /**
     * Set custom raw getter function
     *
     * @param callable $fn Raw getter function
     * @deprecated
     * @return self
     */
    public function rawGetter(callable $fn): self
    {
        $this->rules[$this->current_field]['_get_raw'] = $fn;
        return $this;
    }

    /**
     * Set custom setter function
     *
     * @param callable $fn Setter function
     * @return self
     */
    public function setter(callable $fn): self
    {
        $this->rules[$this->current_field]['_set'] = $fn;
        return $this;
    }

    /**
     * Set custom editor function
     *
     * @param callable $fn Editor function
     * @return self
     */
    public function editor(callable $fn): self
    {
        $this->rules[$this->current_field]['_edit'] = $fn;
        return $this;
    }

    /**
     * Set a custom property
     *
     * @param string $key Property key
     * @param mixed $value Property value
     * @return self
     */
    public function property(string $key, $value): self
    {
        $this->rules[$this->current_field][$key] = $value;
        return $this;
    }

    /**
     * Set multiple properties at once
     *
     * @param array $properties Properties array
     * @return self
     */
    public function properties(array $properties): self
    {
        $this->rules[$this->current_field] = array_merge($this->rules[$this->current_field], $properties);
        return $this;
    }

    /**
     * Customize options with a callback
     *
     * @param callable $callback Callback function
     * @return self
     */
    public function customize(callable $callback): self
    {
        $this->rules[$this->current_field] = $callback($this->rules[$this->current_field]);
        return $this;
    }
}
