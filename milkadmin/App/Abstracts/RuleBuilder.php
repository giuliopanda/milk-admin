<?php
namespace App\Abstracts;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * RuleBuilder - Simple fluent interface for building field rules
 *
 * Builds an array of field rules that can be retrieved with getRules()
 * Sets values directly in $rules array without intermediate storage
 *
 * Usage:
 * $builder = new RuleBuilder();
 * $builder->id()
 *         ->string('name', 100)->required()
 *         ->email('email');
 * $rules = $builder->getRules();
 */
class RuleBuilder
{
    /**
     * All defined field rules
     * @var array
     */
    protected array $rules = [];

    /**
     * Current field being built
     * @var string|null
     */
    protected ?string $current_field = null;

    /**
     * Primary key name
     * @var string|null
     */
    protected ?string $primary_key = null;
    /**
     * Table name
     * @var string|null
     */
    protected ?string $table = null;

    /**
     * Database type
     * @var string
     */
    protected string $db_type = 'db';

    /**
     * Extensions to load
     * @var array|null
     */
    protected ?array $extensions = null;

    /**
     * Start defining a new field
     *
     * @param string $name Field name
     * @param string $type Field type
     * @return self
     */
    public function field(string $name, string $type = 'string'): self
    {
        $this->current_field = $name;
        $this->rules[$name] = [
            'type' => $type,// tipo PHP e SQL (primary, text, string, int, float, bool, date, datetime, time, list, enum, array)
            'length' => null,         // lunghezza massima per stringhe
            'precision' => null,      // precisione per i float
            'nullable' => true,      // se può essere null
            'default' => null,        // valore default
            'primary' => false,       // se è chiave primaria
            'label' => $name,         // etichetta per la visualizzazione
            'options' => null,        // opzioni per i list e enum
            'index' => false,         // se deve essere creato un indice nel database
            'unique' => false,        // se deve essere impostato come campo unico nel database
            // le visualizzazioni
            'list' => true,   // se deve essere visualizzato nella lista
            'edit' => true,   // se deve essere visualizzato nel form
            'view' => true,   // se deve essere visualizzato nella vista
            'sql' => true,  // se deve essere creato nel database
            //
            'form-type' => null,   // tipo di campo per il form
            'form-label' => null,  // etichetta per il form
            'timezone_conversion' => false,
        ];
        return $this;
    }

    /**
     * Change current field
     *
     * @param string $name Field name
     * @return self
     */
    public function ChangeCurrentField($name) {
        $this->current_field = $name;
    }

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
     * Define table
     *
     * @param string $name Field name
     * @return self
     */
    public function table(string $name): self
    {
        $this->table = $name;
        return $this;
    }

    /**
     * Define database type
     *
     * @param string $name Field name
     * @return self
     */
    public function db(string $name): self
    {
        $this->db_type = $name;
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
     * Use for fields that should remain in UTC (system timestamps, etc)
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

        $this->formType('string');
        $this->error('The field must be a decimal number with a maximum of '.$precision.' decimal places');
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
     * Define a file upload field
     *
     * @param string $name Field name
     * @return self
     */
    public function file(string $name): self
    {
        $this->field($name, 'array');
        $this->formType('file');
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define an image upload field
     *
     * @param string $name Field name
     * @return self
     */
    public function image(string $name): self
    {
        $this->field($name, 'array');
        $this->formType('image');
        $this->label($this->createLabel($name));

        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['accept'] = 'image/*';

        return $this;
    }

    /**
     * Allow multiple file uploads
     *
     * @param bool|int $multiple True for multiple, or max number
     * @return self
     */
    public function multiple(bool|int $multiple = true): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }

        if (is_bool($multiple)) {
            if ($multiple) {
                $this->rules[$this->current_field]['form-params']['multiple'] = 'multiple';
            } else {
                unset($this->rules[$this->current_field]['form-params']['multiple']);
            }
        } elseif (is_int($multiple)) {
            $this->maxFiles($multiple);
        }
        return $this;
    }

    /**
     * Set maximum number of files
     *
     * @param int $max Maximum files
     * @return self
     */
    public function maxFiles(int $max): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        if ($max > 1) {
            $this->rules[$this->current_field]['form-params']['multiple'] = 'multiple';
        }
        $this->rules[$this->current_field]['form-params']['max-files'] = $max;
        return $this;
    }

    /**
     * Set accepted file types
     *
     * @param string $accept e.g., 'image/*', '.pdf,.doc'
     * @return self
     */
    public function accept(string $accept): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['accept'] = $accept;
        return $this;
    }

    /**
     * Set maximum file size in bytes
     *
     * @param int $size Max size in bytes
     * @return self
     */
    public function maxSize(int $size): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['max-size'] = $size;
        return $this;
    }

    /**
     * Set upload directory
     *
     * @param string $dir Directory path
     * @return self
     */
    public function uploadDir(string $dir): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['upload-dir'] = $dir;
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

    public function options(array $options): self
    {
        $this->rules[$this->current_field]['options'] = $options;
        return $this;
    }

    /**
     * Set API URL for dynamic options loading
     *
     * @param string $url API endpoint URL for fetching options
     * @param string|null $display_field Field name to display (e.g., 'name'). If null, auto-detects title field from belongsTo relationship
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
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['min'] = $value;
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
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['max'] = $value;
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
     * Define a hasOne relationship
     *
     * hasOne: The foreign key is in the RELATED table, not in this table.
     * Example: Actor hasOne Biography -> biography.actor_id references actor.id
     *
     * @param string $alias Relationship alias (property name to access the related model)
     * @param string $related_model Related model class name
     * @param string $foreign_key_in_related Foreign key field name in the related table (e.g., 'actor_id')
     * @param string $onDelete Delete behavior: 'CASCADE' (default), 'SET NULL', or 'RESTRICT'
     *                         - CASCADE: Delete child records when parent is deleted
     *                         - SET NULL: Set foreign key to NULL in child records
     *                         - RESTRICT: Prevent parent deletion if child records exist
     * @return self
     */
    public function hasOne(string $alias, string $related_model, string $foreign_key_in_related, string $onDelete = 'CASCADE', bool $allowCascadeSave = false): self
    {
        // Validate onDelete parameter
        $valid_onDelete = ['CASCADE', 'SET NULL', 'RESTRICT'];
        $onDelete = strtoupper($onDelete);
        if (!in_array($onDelete, $valid_onDelete)) {
            throw new \InvalidArgumentException(
                "Invalid onDelete value '$onDelete'. Must be one of: " . implode(', ', $valid_onDelete)
            );
        }

        // Current field should be the local key (typically primary key)
        $local_key = $this->current_field;

        // Ensure the local key field exists in rules
        if (!isset($this->rules[$local_key])) {
            throw new \InvalidArgumentException("Local key field must be defined before calling hasOne()");
        }

        // Instantiate the related model to verify foreign key exists
        $related_instance = new $related_model();
        $related_rules = $related_instance->getRules();

        // Verify the foreign key exists in related model
        if (!isset($related_rules[$foreign_key_in_related])) {
            throw new \InvalidArgumentException(
                "Foreign key '$foreign_key_in_related' not found in related model '$related_model'"
            );
        }

        // Verify type compatibility between local key and foreign key in related table
        $local_type = $this->rules[$local_key]['type'];
        $foreign_type = $related_rules[$foreign_key_in_related]['type'];

        // Map compatible types
        $compatible_types = [
            'id' => ['int', 'id', 'string'],
            'int' => ['int', 'id', 'string'],
            'string' => ['string', 'int', 'id'],
        ];

        if (isset($compatible_types[$local_type]) && !in_array($foreign_type, $compatible_types[$local_type])) {
            throw new \InvalidArgumentException(
                "Local key '$local_key' type ($local_type) is not compatible with foreign key '$foreign_key_in_related' type ($foreign_type) in model '$related_model'"
            );
        }

        // Store relationship configuration in the local key field
        $this->rules[$local_key]['relationship'] = [
            'type' => 'hasOne',
            'alias' => $alias,
            'local_key' => $local_key,              // Field in THIS table (e.g., 'id')
            'foreign_key' => $foreign_key_in_related, // Field in RELATED table (e.g., 'actor_id')
            'related_model' => $related_model,
            'onDelete' => $onDelete,                 // Delete behavior: CASCADE, SET NULL, RESTRICT
            'allowCascadeSave' => $allowCascadeSave, // Allow automatic cascade save when parent is saved
        ];

        return $this;
    }

    /**
     * Define a hasMany relationship
     *
     * hasMany: The foreign key is in the RELATED table, not in this table.
     * Similar to hasOne but returns multiple records instead of one.
     * Example: Actor hasMany Films -> films.actor_id references actor.id
     *
     * @param string $alias Relationship alias (property name to access the related model)
     * @param string $related_model Related model class name
     * @param string $foreign_key_in_related Foreign key field name in the related table (e.g., 'actor_id')
     * @param string $onDelete Delete behavior: 'CASCADE' (default), 'SET NULL', or 'RESTRICT'
     *                         - CASCADE: Delete child records when parent is deleted
     *                         - SET NULL: Set foreign key to NULL in child records
     *                         - RESTRICT: Prevent parent deletion if child records exist
     * @return self
     */
    public function hasMany(string $alias, string $related_model, string $foreign_key_in_related, string $onDelete = 'CASCADE'): self
    {
        // Validate onDelete parameter
        $valid_onDelete = ['CASCADE', 'SET NULL', 'RESTRICT'];
        $onDelete = strtoupper($onDelete);
        if (!in_array($onDelete, $valid_onDelete)) {
            throw new \InvalidArgumentException(
                "Invalid onDelete value '$onDelete'. Must be one of: " . implode(', ', $valid_onDelete)
            );
        }

        // Current field should be the local key (typically primary key)
        $local_key = $this->current_field;

        // Ensure the local key field exists in rules
        if (!isset($this->rules[$local_key])) {
            throw new \InvalidArgumentException("Local key field must be defined before calling hasMany()");
        }

        // Instantiate the related model to verify foreign key exists
        $related_instance = new $related_model();
        $related_rules = $related_instance->getRules();

        // Verify the foreign key exists in related model
        if (!isset($related_rules[$foreign_key_in_related])) {
            throw new \InvalidArgumentException(
                "Foreign key '$foreign_key_in_related' not found in related model '$related_model'"
            );
        }

        // Verify type compatibility between local key and foreign key in related table
        $local_type = $this->rules[$local_key]['type'];
        $foreign_type = $related_rules[$foreign_key_in_related]['type'];

        // Map compatible types
        $compatible_types = [
            'id' => ['int', 'id', 'string'],
            'int' => ['int', 'id', 'string'],
            'string' => ['string', 'int', 'id'],
        ];

        if (isset($compatible_types[$local_type]) && !in_array($foreign_type, $compatible_types[$local_type])) {
            throw new \InvalidArgumentException(
                "Local key '$local_key' type ($local_type) is not compatible with foreign key '$foreign_key_in_related' type ($foreign_type) in model '$related_model'"
            );
        }

        // Store relationship configuration in the local key field
        $this->rules[$local_key]['relationship'] = [
            'type' => 'hasMany',
            'alias' => $alias,
            'local_key' => $local_key,              // Field in THIS table (e.g., 'id')
            'foreign_key' => $foreign_key_in_related, // Field in RELATED table (e.g., 'actor_id')
            'related_model' => $related_model,
            'onDelete' => $onDelete,                 // Delete behavior: CASCADE, SET NULL, RESTRICT
        ];

        return $this;
    }

    /**
     * Define a belongsTo relationship
     *
     * belongsTo: The foreign key is in THIS table, referencing the related table.
     * Example: Post belongsTo User -> post.user_id references user.id
     *
     * @param string $alias Relationship alias (property name to access the related model)
     * @param string $related_model Related model class name
     * @param string|null $related_key Primary key of the related model (default: 'id')
     * @return self
     */
    public function belongsTo(string $alias, string $related_model, ?string $related_key = 'id'): self
    {
        // Use current field as foreign key (in THIS table)
        $foreign_key = $this->current_field;

        // Ensure the foreign key field exists in rules
        if (!isset($this->rules[$foreign_key])) {
            throw new \InvalidArgumentException("Foreign key field must be defined before calling belongsTo()");
        }

        // Instantiate the related model to get its configuration
        $related_instance = new $related_model();
        $related_rules = $related_instance->getRules();

        // Get the related primary key rule
        $related_pk_rule = $related_rules[$related_key] ?? null;

        if (!$related_pk_rule) {
            throw new \InvalidArgumentException("Related key '$related_key' not found in model '$related_model'");
        }

        // Verify type compatibility
        $fk_type = $this->rules[$foreign_key]['type'];
        $pk_type = $related_pk_rule['type'];

        // Map compatible types
        $compatible_types = [
            'id' => ['int', 'id', 'string'],
            'int' => ['int', 'id', 'string'],
            'string' => ['string', 'int', 'id'],
        ];

        if (isset($compatible_types[$pk_type]) && !in_array($fk_type, $compatible_types[$pk_type])) {
            throw new \InvalidArgumentException(
                "Foreign key '$foreign_key' type ($fk_type) is not compatible with related key '$related_key' type ($pk_type) in model '$related_model'"
            );
        }

        // Auto-detect title field in related model
        $auto_display_field = null;
        foreach ($related_rules as $field_name => $field_rule) {
            if (isset($field_rule['_is_title_field']) && $field_rule['_is_title_field'] === true) {
                $auto_display_field = $field_name;
                break;
            }
        }

        // Store relationship configuration in the foreign key field
        $this->rules[$foreign_key]['relationship'] = [
            'type' => 'belongsTo',
            'alias' => $alias,
            'foreign_key' => $foreign_key,
            'related_model' => $related_model,
            'related_key' => $related_key,
        ];

        // Store auto-detected display field if found
        if ($auto_display_field !== null) {
            $this->rules[$foreign_key]['_auto_display_field'] = $auto_display_field;
        }

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

    // ========================================
    // Build and retrieve rules
    // ========================================

    /**
     * Get all built rules
     *
     * @return array Array of field rules
     */
    public function getRules(): array
    {
        // Special handling for radio fields
        foreach ($this->rules as $name => $rule) {
            if (isset($rule['type']) && $rule['type'] === 'radio') {
                if (isset($rule['label']) && $rule['label'] !== null) {
                    if (!isset($this->rules[$name]['form-params'])) {
                        $this->rules[$name]['form-params'] = [];
                    }
                    $this->rules[$name]['form-params']['label'] = $rule['label'];
                    $this->rules[$name]['label'] = null;
                }
            }
        }

        return $this->rules;
    }

    public function setRules(array $rules): self
    {
        $this->rules = $rules;
        $this->current_field = null;
        return $this;
    }

    /**
     * Clear all rules
     *
     * @return self
     */
    public function clear(): self
    {
        $this->rules = [];
        $this->current_field = null;
        return $this;
    }

    // ========================================
    // Helper methods
    // ========================================

    /**
     * Create a human-readable label from field name
     *
     * @param string $fieldName Field name
     * @return string Label
     */
    protected function createLabel(string $fieldName): string
    {
        // Convert snake_case and camelCase to words
        $label = preg_replace(['/([a-z])([A-Z])/', '/_+/', '/[-\s]+/'], ['\\1 \\2', ' ', ' '], $fieldName);

        // Remove multiple spaces
        $label = preg_replace('/\s+/', ' ', trim($label));

        return ucfirst($label);
    }

    /**
     * Get table name
     *
     * @return string Table name
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Get primary key name
     *
     * @return string Primary key name
     */
    public function getPrimaryKey(): ?string
    {
        return $this->primary_key;
    }

    /**
     * Get database type (db or db2)
     *
     * @return string Database type
     */
    public function getDbType(): ?string
    {
        return $this->db_type;
    }

    /**
     * Set extensions to load
     *
     * @param array $extensions Array of extension names
     * @return self
     */
    public function extensions(array $extensions): self
    {
        $this->extensions = $extensions;
        return $this;
    }

    /**
     * Get extensions
     *
     * @return array|null Extensions array
     */
    public function getExtensions(): ?array
    {
        return $this->extensions;
    }

    /**
     * Remove primary key flags from all fields
     * Useful when creating audit tables where the primary key will be different
     *
     * @return self
     */
    public function removePrimaryKeys(): self
    {
       foreach ($this->rules as $field_name => &$field_rule) {
            if ($field_rule['primary']) {
                unset($this->rules[$field_name]);
                break;
            }
        }
        $this->primary_key = null;
        return $this;
    }
}
