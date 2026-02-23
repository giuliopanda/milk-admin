<?php
namespace App\Abstracts\Traits;

!defined('MILK_DIR') && die();

/**
 * RuleBuilderFieldTypesTrait - Field type definition methods
 *
 * Provides methods for defining typed fields: string, text, int, datetime,
 * date, time, timestamp, decimal, email, tel, url, boolean, checkbox,
 * checkboxes, radio, list/select, enum, array, title, created_at, updated_at,
 * created_by, updated_by
 */
trait RuleBuilderFieldTypesTrait
{
    /**
     * Define an ID field
     *
     * @param string $name Field name (default: 'id')
     * @return self
     */
    public function id(string $name = 'id'): self
    {
        $this->field($name, 'id');
        $this->primary($name);
        $this->formType('hidden');
        $this->nullable(false);
        return $this;
    }

    /**
     * Define a primary key field
     *
     * @param string $name Field name
     * @return self
     */
    public function primaryKey(string $name): self
    {
        $this->id($name);
        return $this;
    }

    public function array(string $name): self
    {
        $this->field($name, 'array');
        return $this;
    }

    /**
     * Define a string field
     *
     * @param string $name Field name
     * @param int $length Max length (default: 255)
     * @return self
     */
    public function string(string $name, int $length = 255): self
    {
        $this->field($name, 'string');
        $this->rules[$this->current_field]['length'] = $length;
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define a title field (varchar 255, auto-used in belongsTo relationships)
     *
     * @param string $name Field name (default: 'title')
     * @param int $length Maximum length (default: 255)
     * @return self
     */
    public function title(string $name = 'title', int $length = 255): self
    {
        $this->field($name, 'string');
        $this->rules[$this->current_field]['length'] = $length;
        $this->rules[$this->current_field]['_is_title_field'] = true;
        $this->rules[$this->current_field]['form-params']['required'] = true;
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define a text field
     *
     * @param string $name Field name
     * @return self
     */
    public function text(string $name): self
    {
        $this->field($name, 'text');
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define an integer field
     *
     * @param string $name Field name
     * @return self
     */
    public function int(string $name): self
    {
        $this->field($name, 'int');
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define a datetime field
     *
     * @param string $name Field name
     * @return self
     */
    public function datetime(string $name): self
    {
        $this->field($name, 'datetime');
        $this->label($this->createLabel($name));
        $this->rules[$name]['timezone_conversion'] = true;
        return $this;
    }

    /**
     * Disable timezone conversion for current datetime field
     *
     * @return self
     */
    public function noTimezoneConversion(): self
    {
        if ($this->current_field !== null) {
            $this->rules[$this->current_field]['timezone_conversion'] = false;
        }
        return $this;
    }

    /**
     * Define a created_at field with auto-preservation on updates
     *
     * @param string $name Field name (default: 'created_at')
     * @return self
     */
    public function created_at(string $name = 'created_at'): self
    {
        $this->field($name, 'datetime');
        $this->rules[$this->current_field]['_auto_created_at'] = true;
        $this->label($this->createLabel($name));
        $this->hideFromEdit();
        return $this;
    }

    /**
     * Define an updated_at field with auto-update on every save
     *
     * @param string $name Field name (default: 'updated_at')
     * @return self
     */
    public function updated_at(string $name = 'updated_at'): self
    {
        $this->field($name, 'datetime');
        $this->rules[$this->current_field]['_auto_updated_at'] = true;
        $this->label($this->createLabel($name));
        $this->hideFromEdit();
        return $this;
    }

    /**
     * Define a created_by field with auto-preservation on updates
     *
     * @param string $name Field name (default: 'created_by')
     * @return self
     */
    public function created_by(string $name = 'created_by'): self
    {
        $this->field($name, 'int');
        $this->rules[$this->current_field]['_auto_created_by'] = true;
        $this->label($this->createLabel($name));
        $this->hideFromEdit();
        return $this;
    }

    /**
     * Define an updated_by field with auto-update on every save
     *
     * @param string $name Field name (default: 'updated_by')
     * @return self
     */
    public function updated_by(string $name = 'updated_by'): self
    {
        $this->field($name, 'int');
        $this->rules[$this->current_field]['_auto_updated_by'] = true;
        $this->label($this->createLabel($name));
        $this->hideFromEdit();
        return $this;
    }

    /**
     * Define a date field
     *
     * @param string $name Field name
     * @return self
     */
    public function date(string $name): self
    {
        $this->field($name, 'date');
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define a time field
     *
     * @param string $name Field name
     * @return self
     */
    public function time(string $name): self
    {
        $this->field($name, 'time');
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define a timestamp field
     *
     * @param string $name Field name
     * @return self
     */
    public function timestamp(string $name): self
    {
        $this->field($name, 'timestamp');
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define a decimal/float field
     *
     * @param string $name Field name
     * @param int $length Total digits
     * @param int $precision Decimal places
     * @return self
     */
    public function decimal(string $name, int $length = 10, int $precision = 2): self
    {
        $this->field($name, 'float');
        if ($length < $precision) {
            $length = $precision + 1;
        }
        $this->rules[$this->current_field]['length'] = $length;
        $this->rules[$this->current_field]['precision'] = $precision;
        $integer_digits = $length - $precision;

        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['pattern'] = "^-?\\d{1,$integer_digits}(\\.\\d{0,$precision})?$";
        $this->rules[$this->current_field]['form-params']['step'] = $precision > 0
            ? '0.' . str_repeat('0', $precision - 1) . '1'
            : '1';

        $this->formType('number');
        $this->error('The field must be a decimal number with a maximum of ' . $precision . ' decimal places');
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define an email field
     *
     * @param string $name Field name
     * @return self
     */
    public function email(string $name): self
    {
        $this->field($name, 'string');
        $this->rules[$this->current_field]['length'] = 255;
        $this->formType('email');
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define a telephone field
     *
     * @param string $name Field name
     * @return self
     */
    public function tel(string $name): self
    {
        $this->field($name, 'string');
        $this->rules[$this->current_field]['length'] = 25;
        $this->formType('tel');
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define a URL field
     *
     * @param string $name Field name
     * @return self
     */
    public function url(string $name): self
    {
        $this->field($name, 'string');
        $this->rules[$this->current_field]['length'] = 255;
        $this->formType('url');
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define a boolean/checkbox field
     *
     * @param string $name Field name
     * @return self
     */
    public function boolean(string $name): self
    {
        $this->field($name, 'bool');
        $this->formType('checkbox');
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Alias for boolean()
     */
    public function checkbox(string $name): self
    {
        return $this->boolean($name);
    }

    /**
     * Define a checkboxes field with options
     *
     * @param string $name Field name
     * @param array $options Options array
     * @return self
     */
    public function checkboxes(string $name, array $options): self
    {
        $this->field($name, 'array');
        $this->rules[$this->current_field]['options'] = $options;
        $this->formType('checkboxes');
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define a radio field with options
     *
     * @param string $name Field name
     * @param array $options Options array
     * @return self
     */
    public function radio(string $name, array $options): self
    {
        $this->field($name, 'radio');
        $this->rules[$this->current_field]['options'] = $options;
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define a list/select field with options
     *
     * @param string $name Field name
     * @param array $options Options array
     * @return self
     */
    public function list(string $name, array $options): self
    {
        $this->field($name, 'list');
        $this->rules[$this->current_field]['options'] = $options;
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Alias for list()
     */
    public function select(string $name, array $options): self
    {
        return $this->list($name, $options);
    }

    /**
     * Define an enum field
     *
     * @param string $name Field name
     * @param array $options Options array
     * @return self
     */
    public function enum(string $name, array $options): self
    {
        $this->field($name, 'enum');
        $this->rules[$this->current_field]['options'] = $options;
        $this->label($this->createLabel($name));
        return $this;
    }
}
