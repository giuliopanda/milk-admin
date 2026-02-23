<?php
namespace App\Abstracts\Traits;

!defined('MILK_DIR') && die();

/**
 * RuleBuilderRelationshipsTrait - Relationship definition methods
 *
 * Provides methods for defining relationships: hasOne, hasMany, belongsTo,
 * withCount, hasMeta, where
 */
trait RuleBuilderRelationshipsTrait
{
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
     * @param bool $allowCascadeSave Allow automatic cascade save when parent is saved
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
            'local_key' => $local_key,
            'foreign_key' => $foreign_key_in_related,
            'related_model' => $related_model,
            'onDelete' => $onDelete,
            'allowCascadeSave' => $allowCascadeSave,
        ];

        // Activate relationship for potential where() call
        $this->active_relationship = [
            'type' => 'hasOne',
            'field' => $local_key,
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
     * @param bool $allowCascadeSave If true, allows automatic cascade save when parent is saved with $cascade=true
     * @return self
     */
    public function hasMany(string $alias, string $related_model, string $foreign_key_in_related, string $onDelete = 'CASCADE', bool $allowCascadeSave = false): self
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

        // Store relationship configuration in the local key field
        $this->rules[$local_key]['relationship'] = [
            'type' => 'hasMany',
            'alias' => $alias,
            'local_key' => $local_key,
            'foreign_key' => $foreign_key_in_related,
            'related_model' => $related_model,
            'onDelete' => $onDelete,
            'allowCascadeSave' => $allowCascadeSave,
        ];

        // Activate relationship for potential where() call
        $this->active_relationship = [
            'type' => 'hasMany',
            'field' => $local_key,
        ];

        return $this;
    }

    /**
     * Define a withCount aggregate relationship
     *
     * withCount: Adds a subquery COUNT to the SELECT clause without loading the actual related records.
     *
     * @param string $alias Field name for the count (e.g., 'books_count')
     * @param string $related_model Related model class name
     * @param string $foreign_key_in_related Foreign key field name in the related table
     * @return self
     */
    public function withCount(string $alias, string $related_model, string $foreign_key_in_related): self
    {
        // Current field should be the local key (typically primary key)
        $local_key = $this->current_field;

        // Ensure the local key field exists in rules
        if (!isset($this->rules[$local_key])) {
            throw new \InvalidArgumentException("Local key field must be defined before calling withCount()");
        }

        // Initialize withCount array if not exists
        if (!isset($this->rules[$local_key]['withCount'])) {
            $this->rules[$local_key]['withCount'] = [];
        }

        // Store withCount configuration
        $this->rules[$local_key]['withCount'][] = [
            'alias' => $alias,
            'local_key' => $local_key,
            'foreign_key' => $foreign_key_in_related,
            'related_model' => $related_model,
        ];

        // Add the alias as a virtual field in rules so it's preserved by filterDataByRules()
        $this->rules[$alias] = [
            'type' => 'int',
            'virtual' => true,
            'withCount' => true,
            'sql' => false,
            'nullable' => true,
            'label' => ucfirst(str_replace('_', ' ', $alias)),
        ];

        // Activate relationship for potential where() call
        $index = count($this->rules[$local_key]['withCount']) - 1;
        $this->active_relationship = [
            'type' => 'withCount',
            'field' => $local_key,
            'index' => $index,
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

        // Store relationship configuration first
        $this->rules[$foreign_key]['relationship'] = [
            'type' => 'belongsTo',
            'alias' => $alias,
            'foreign_key' => $foreign_key,
            'related_key' => $related_key,
            'related_model' => $related_model
        ];

        // Now try to get auto_display_field - wrapped in try/catch to prevent loops
        $auto_display_field = null;
        try {
            static $instantiating = [];
            $key = $related_model;

            if (!isset($instantiating[$key])) {
                $instantiating[$key] = true;
                $related_instance = new $related_model();
                $related_rules = $related_instance->getRules();

                // Find title field
                foreach ($related_rules as $field_name => $rule) {
                    if (($rule['title'] ?? false) === true) {
                        $auto_display_field = $field_name;
                        break;
                    }
                }
                unset($instantiating[$key]);
            }
        } catch (\Throwable) {
            // Ignore errors during auto-detect to prevent circular loops
        }

        // Update with auto_display_field if found
        if ($auto_display_field) {
            $this->rules[$foreign_key]['relationship']['auto_display_field'] = $auto_display_field;
        }

        // Activate relationship for potential where() call
        $this->active_relationship = [
            'type' => 'belongsTo',
            'field' => $foreign_key,
        ];

        return $this;
    }

    /**
     * Define a hasMeta relationship (EAV pattern - Entity-Attribute-Value)
     *
     * hasMeta: Links to a meta table following the WordPress-style EAV pattern.
     * Each meta is stored as a separate row with (entity_id, meta_key, meta_value).
     *
     * @param string $alias Meta field alias (used as property name AND as meta_key value in DB)
     * @param string $related_model Meta table model class name
     * @param string|null $foreign_key Foreign key column in meta table
     * @param string|null $local_key Local key column in main table
     * @param string $meta_key_column Column name for meta keys (default: 'meta_key')
     * @param string $meta_value_column Column name for meta values (default: 'meta_value')
     * @param string|null $meta_key_value Actual meta_key value in DB (default: same as $alias)
     * @return self
     */
    public function hasMeta(
        string $alias,
        string $related_model,
        ?string $foreign_key = null,
        ?string $local_key = null,
        string $meta_key_column = 'meta_key',
        string $meta_value_column = 'meta_value',
        ?string $meta_key_value = null
    ): self {
        // Use current field as local key if not specified (typically primary key)
        $local_key = $local_key ?? $this->current_field;

        // Ensure the local key field exists in rules
        if (!isset($this->rules[$local_key])) {
            throw new \InvalidArgumentException("Local key field '$local_key' must be defined before calling hasMeta()");
        }

        // Auto-generate foreign key if not provided (table_name + '_id')
        if ($foreign_key === null) {
            $table_name = $this->table;
            if ($table_name !== null) {
                $clean_table = preg_replace('/^#__/', '', $table_name);
                if (str_ends_with($clean_table, 's')) {
                    $clean_table = substr($clean_table, 0, -1);
                }
                $foreign_key = $clean_table . '_id';
            } else {
                $foreign_key = 'entity_id';
            }
        }

        // Use alias as meta_key value if not specified
        $meta_key_value = $meta_key_value ?? $alias;

        // Initialize hasMeta array if not exists
        if (!isset($this->rules[$local_key]['hasMeta'])) {
            $this->rules[$local_key]['hasMeta'] = [];
        }

        // Store hasMeta configuration
        $this->rules[$local_key]['hasMeta'][] = [
            'alias' => $alias,
            'local_key' => $local_key,
            'foreign_key' => $foreign_key,
            'related_model' => $related_model,
            'meta_key_column' => $meta_key_column,
            'meta_value_column' => $meta_value_column,
            'meta_key_value' => $meta_key_value,
        ];

        // Add the alias as a virtual field in rules
        $this->rules[$alias] = [
            'type' => 'string',
            'virtual' => true,
            'hasMeta' => true,
            'sql' => false,
            'nullable' => true,
            'label' => $this->createLabel($alias),
            'list' => true,
            'edit' => true,
            'view' => true,
            '_meta_config' => [
                'local_key' => $local_key,
                'index' => count($this->rules[$local_key]['hasMeta']) - 1,
            ],
        ];

        // Activate relationship for potential where() call
        $index = count($this->rules[$local_key]['hasMeta']) - 1;
        $this->active_relationship = [
            'type' => 'hasMeta',
            'field' => $local_key,
            'index' => $index,
        ];

        return $this;
    }

    /**
     * Add a WHERE condition to the active relationship
     *
     * This method can only be called immediately after a relationship method
     * (withCount, hasMany, hasOne, belongsTo, hasMeta).
     *
     * @param string $condition SQL WHERE condition with ? placeholders
     * @param array $params Parameters to bind to the placeholders
     * @return self
     * @throws \LogicException if called without an active relationship
     */
    public function where(string $condition, array $params = []): self
    {
        if ($this->active_relationship === null) {
            throw new \LogicException(
                "where() can only be called immediately after a relationship method " .
                "(withCount, hasMany, hasOne, belongsTo, hasMeta). " .
                "Example: ->withCount('lezioni', LezioniModel::class, 'MATR_CRS')->where('DISPONIBILE = ?', ['D'])"
            );
        }

        $type = $this->active_relationship['type'];
        $field = $this->active_relationship['field'];

        if ($type === 'withCount') {
            $index = $this->active_relationship['index'];
            $this->rules[$field]['withCount'][$index]['where'] = [
                'condition' => $condition,
                'params' => $params,
            ];
        } elseif ($type === 'hasMeta') {
            $index = $this->active_relationship['index'];
            $this->rules[$field]['hasMeta'][$index]['where'] = [
                'condition' => $condition,
                'params' => $params,
            ];
        } elseif (in_array($type, ['hasMany', 'hasOne', 'belongsTo'])) {
            $this->rules[$field]['relationship']['where'] = [
                'condition' => $condition,
                'params' => $params,
            ];
        }

        return $this;
    }

    /**
     * Deactivate the current relationship context
     *
     * @return void
     */
    protected function deactivateRelationship(): void
    {
        $this->active_relationship = null;
    }
}
