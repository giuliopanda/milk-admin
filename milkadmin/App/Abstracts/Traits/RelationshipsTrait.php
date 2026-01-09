<?php
namespace App\Abstracts\Traits;

/**
 * RelationshipsTrait - Handles model relationships (hasOne, belongsTo, hasMany)
 *
 * REFACTORED VERSION:
 * - Relationship data is stored directly in records_objects as arrays
 * - No separate cache (loaded_relationships removed)
 * - Lazy loading: data loaded on first access and stored in records_objects[i][alias]
 * - Batch queries: loads all missing relationships in one whereIn query
 *
 * Structure in records_objects:
 * - belongsTo/hasOne: records_objects[i]['doctor'] = ['id' => 5, 'name' => 'Dr. Smith', ...]
 * - hasMany: records_objects[i]['appointments'] = [['id' => 1, ...], ['id' => 2, ...]]
 *
 * Usage:
 * - Define relationships in configure() method
 * - Access via magic property: $appointment->doctor (lazy loads if needed)
 * - Preload with: $model->with('doctor')
 */
trait RelationshipsTrait
{
    /**
     * List of relationships to include when exporting data
     * Empty array = don't include any relationships (default)
     * Non-empty array = include only specified relationships
     * @var array
     */
    protected array $include_relationships = [];

    /**
     * Get a related model data for the current record
     * Triggers batch loading on first access if not already loaded
     *
     * @param string $alias Relationship alias
     * @return mixed|null Related data (array for belongsTo/hasOne, array of arrays for hasMany)
     */
    protected function getRelatedModel(string $alias): mixed
    {
        // Get relationship configuration
        $relationship = $this->getRelationship($alias);

        if (!$relationship) {
            return null;
        }

        if (!isset($this->records_objects[$this->current_index])) {
            return null;
        }

        // Check if relationship is already loaded in records_objects
        if (isset($this->records_objects[$this->current_index][$alias])) {
            return $this->records_objects[$this->current_index][$alias];
        }

        // Not loaded yet - trigger lazy loading for all records that need this relationship
        $this->loadMissingRelationships($alias, $relationship);

        // Return the loaded data from records_objects
        return $this->records_objects[$this->current_index][$alias] ?? null;
    }

    /**
     * Load relationships for records that don't have them loaded yet
     * Loads all missing records in a single whereIn query and stores as arrays in records_objects
     *
     * @param string $alias Relationship alias
     * @param array $relationship Relationship configuration
     * @return void
     */
    protected function loadMissingRelationships(string $alias, array $relationship): void
    {
        if ($this->records_objects === null || empty($this->records_objects)) {
            return;
        }

        // Determine key columns based on relationship type
        $is_has = in_array($relationship['type'], ['hasOne', 'hasMany'], true);
        $local_key   = $is_has ? $relationship['local_key']   : $relationship['foreign_key'];
        $foreign_key = $is_has ? $relationship['foreign_key'] : $relationship['related_key'];

        // Find records that don't have this relationship loaded yet
        // Collect the key values we need to query
        $keys_to_load = [];
        $records_needing_load = []; // Track which record indices need this key

        foreach ($this->records_objects as $index => $record) {
            // Skip if relationship already loaded for this record
            if (isset($record[$alias])) {
                continue;
            }

            $key_value = $record[$local_key] ?? null;

            // Skip if no key value
            if ($key_value === null) {
                continue;
            }

            // Track this key value
            if (!isset($keys_to_load[$key_value])) {
                $keys_to_load[$key_value] = [];
            }
            $keys_to_load[$key_value][] = $index;
            $records_needing_load[] = $index;
        }

        // If no records need loading, return
        if (empty($keys_to_load)) {
            return;
        }

        // Get unique key values to query
        $unique_keys = array_keys($keys_to_load);

        // Load related records with whereIn query
        $related_class = $relationship['related_model'];
        $related_model = new $related_class();

        // Block relations on the related model to prevent circular loading
        if (method_exists($related_model, 'setBlockRelations')) {
            $related_model->setBlockRelations(true);
        }

        // Build query with whereIn for foreign keys
        $query = $related_model->query()->whereIn($foreign_key, $unique_keys);

        // Add custom where condition if defined in relationship
        $where_config = $relationship['where'] ?? null;
        if ($where_config !== null) {
            $query->where($where_config['condition'], $where_config['params']);
        }

        $all_results_model  = $query->getResults();
        // Now for each parent record that needs this relationship,
        // filter the complete model to create a separate instance
        foreach ($records_needing_load as $index) {
            $key_value = $this->records_objects[$index][$local_key] ?? null;

            if ($key_value === null) {
                continue;
            }

            // Use filterByField to create a separate model with only data for this parent
            $filtered_model = $all_results_model->filterByField($foreign_key, [$key_value]);

            if ($relationship['type'] === 'hasMany') {
                // hasMany: assign the filtered model (even if empty)
                $this->records_objects[$index][$alias] = $filtered_model;
            } else {
                // hasOne/belongsTo: assign the first record or null
                if ($filtered_model->count() > 0) {
                    $filtered_model->first();
                    $this->records_objects[$index][$alias] = $filtered_model;
                } else {
                    $this->records_objects[$index][$alias] = null;
                }
            }
        }
    }

    /**
     * Get relationship configuration by alias
     *
     * @param string $alias Relationship alias
     * @return array|null Relationship configuration or null if not found
     */
    protected function getRelationship(string $alias): ?array
    {

        $rules = $this->rule_builder->getRules();

        foreach ($rules as $field_name => $rule) {
            if (isset($rule['relationship']) && $rule['relationship']['alias'] === $alias) {
                return $rule['relationship'];
            }
        }

        return null;
    }

    /**
     * Check if a relationship alias exists
     *
     * @param string $alias Relationship alias
     * @return bool
     */
    protected function hasRelationship(string $alias): bool
    {
        return $this->getRelationship($alias) !== null;
    }

    /**
     * Clear loaded relationships from records_objects
     * Useful when data is modified and relationships need to be reloaded
     *
     * @param string|null $alias Specific relationship to clear, or null for all
     * @return void
     */
    public function clearRelationshipCache(?string $alias = null): void
    {
        if ($this->records_objects === null) {
            return;
        }

        foreach ($this->records_objects as $index => $record) {
            if ($alias === null) {
                // Clear all relationships
                $rules = $this->rule_builder->getRules();
                foreach ($rules as $field_name => $rule) {
                    if (isset($rule['relationship'])) {
                        $rel_alias = $rule['relationship']['alias'];
                        unset($this->records_objects[$index][$rel_alias]);
                    }
                }
            } else {
                // Clear specific relationship
                unset($this->records_objects[$index][$alias]);
            }
        }
    }

    /**
     * Get the list of relationships to include in data export
     *
     * @return array List of relationship aliases to include
     */
    public function getIncludeRelationships(): array
    {

        return $this->include_relationships;
    }

    /**
     * Include relationships in data export operations
     *
     * This method loads specified relationships and marks them for inclusion
     * when calling getFormattedData(), getRawData(), getSqlData(), or toArray()
     *
     * @param string|array|null $relations Relationship(s) to include:
     *                                     - null: include ALL defined relationships
     *                                     - string: include single relationship (e.g., 'doctor')
     *                                     - array: include multiple relationships (e.g., ['doctor', 'appointments'])
     * @return static Returns $this for method chaining
     *
     * @example
     * // Include all relationships
     * $appointments->with()->getFormattedData();
     *
     * // Include only 'doctor' relationship
     * $appointments->with('doctor')->getFormattedData();
     *
     * // Include multiple specific relationships
     * $user->with(['posts', 'comments'])->getRawData();
     */
    public function with(string|array|null $relations = null): static
    {
        // Get all defined relationships from rules
        $rules = $this->rule_builder->getRules();
        $defined_relationships = [];

        foreach ($rules as $field_name => $rule) {
            if (isset($rule['relationship'])) {
                $defined_relationships[] = $rule['relationship']['alias'];
            }
        }

        // Determine which relationships to include
        if ($relations === null) {
            // Include ALL defined relationships
            $relations_to_load = $defined_relationships;
        } elseif (is_string($relations)) {
            // Include single relationship
            $relations_to_load = [$relations];
        } else {
            // Include array of relationships
            $relations_to_load = $relations;
        }

        // Validate and load relationships
        $this->include_relationships = [];
        foreach ($relations_to_load as $alias) {
            if (in_array($alias, $defined_relationships)) {
                $this->include_relationships[] = $alias;

                // Load relationship immediately for all records
                $relationship = $this->getRelationship($alias);
                if ($relationship) {
                    $this->loadMissingRelationships($alias, $relationship);
                }
            }
        }

        return $this;
    }

    /**
     * Get data for included relationships for the current record
     *
     * Returns an associative array with relationship data for the current record index
     *
     * @param string $output_mode Output format: 'raw', 'formatted', or 'sql'
     * @return array Associative array [alias => data]
     */
    protected function getIncludedRelationshipsData(string $output_mode = 'raw'): array
    {

        $result = [];
        if (empty($this->include_relationships)) {
            return $result;
        }

        if (!isset($this->records_objects[$this->current_index])) {
            return $result;
        }

        $current_record = $this->records_objects[$this->current_index];

        foreach ($this->include_relationships as $alias) {
            // Check if relationship data exists in records_objects
            if (!isset($current_record[$alias])) {
                $result[$alias] = null;
                continue;
            }

            $related_data = $current_record[$alias];

            // Convert to appropriate format based on output mode
            if ($output_mode === 'raw') {
                // Already in array format, just return as-is
                $result[$alias] = $related_data;
            } else {
                // For formatted/sql, convert the arrays to objects and apply formatters
                $relationship = $this->getRelationship($alias);

                if ($relationship['type'] === 'hasMany') {
                    // hasMany: array of arrays -> array of formatted objects
                    $formatted_array = [];
                    if (is_array($related_data)) {
                        foreach ($related_data as $item_array) {
                            $formatted_array[] = $this->convertRelatedArrayData(
                                $item_array,
                                $output_mode,
                                $alias
                            );
                        }
                    }
                    $result[$alias] = $formatted_array;
                } else {
                    // belongsTo/hasOne: single array -> formatted object
                    $result[$alias] = $this->convertRelatedArrayData(
                        $related_data,
                        $output_mode,
                        $alias
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Convert related data array to appropriate format with formatters applied
     *
     * @param array|null $data_array Related data as array
     * @param string $output_mode Output format: 'formatted' or 'sql'
     * @param string $alias Relationship alias for custom formatter lookup
     * @return object|null Converted data as object
     */
    protected function convertRelatedArrayData(?array $data_array, string $output_mode, string $alias): ?object
    {
      
        if ($data_array === null) {
            return null;
        }

        // Apply custom formatters registered in parent model for this relationship
        $data_array = $this->applyRelationshipFormatters($data_array, $alias, $output_mode);

        return (object)$data_array;
    }

    /**
     * Apply custom formatters registered in parent model to relationship data
     *
     * @param array $data Relationship data
     * @param string $alias Relationship alias
     * @param string $output_mode Output mode (raw, formatted, sql)
     * @return array Modified data with custom formatters applied
     */
    protected function applyRelationshipFormatters(array $data, string $alias, string $output_mode): array
    {
        // Determine which handler type to use based on output mode
        $handler_type = match($output_mode) {
            'formatted' => 'get_formatted',
            'sql' => 'get_sql',
            'raw' => 'get_raw',
            default => 'get_formatted'
        };

        // Get handlers registered for this relationship (e.g., "doctor.name")
        if (method_exists($this, 'getRelationshipHandlers')) {
            $handlers = $this->getRelationshipHandlers($alias, $handler_type);

            // Apply each handler to the corresponding field
            foreach ($handlers as $field_name => $handler) {
                if (array_key_exists($field_name, $data) && is_callable($handler)) {
                    // Create a temporary object to pass to the handler
                    $temp_obj = (object)[$alias => (object)$data];

                    // DEBUG: Uncomment to debug formatter issues
                    // echo "<pre>DEBUG applyRelationshipFormatters:\n";
                    // echo "Alias: $alias, Field: $field_name, Mode: $output_mode\n";
                    // echo "Temp obj: "; var_dump($temp_obj);
                    // echo "Handler result: ";

                    $data[$field_name] = $handler($temp_obj);

                    // echo var_dump($data[$field_name]);
                    // echo "</pre>";
                }
            }
        }

        return $data;
    }

    /**
     * Apply custom formatters to related data when accessed directly via __get
     * Creates Model instances instead of stdClass objects
     *
     * @param mixed $related_data Related data (array or array of arrays)
     * @param string $alias Relationship alias
     * @param string $output_mode Output mode (raw, formatted, sql)
     * @return mixed Related data as Model instances
     */
    protected function applyRelationshipFormattersToModel($related_data, string $alias, string $output_mode): mixed
    {
     
        if ($related_data === null) {
            return null;
        }

        $relationship = $this->getRelationship($alias);

        if (!$relationship) {
            return null;
        }

        if ($relationship['type'] === 'hasMany') {
            // hasMany: array of arrays -> array of Model instances
            $result = [];
            if (is_array($related_data)) {
                foreach ($related_data as $item_array) {
                    $model = $this->arrayToModelInstance($item_array, $relationship, $alias, $output_mode);
                    if ($model !== null) {
                        $result[] = $model;
                    }
                }
            }
            return $result;
        } else {
            // belongsTo/hasOne: single array -> single Model instance
            return $this->arrayToModelInstance($related_data, $relationship, $alias, $output_mode);
        }
    }

    /**
     * Convert array data to Model instance
     *
     * @param array|null $data_array Related data as array
     * @param array $relationship Relationship configuration
     * @param string $alias Relationship alias
     * @param string $output_mode Output mode (raw, formatted, sql)
     * @return mixed Model instance or null
     */
    protected function arrayToModelInstance(?array $data_array, array $relationship, string $alias, string $output_mode): mixed
    {
        if ($data_array === null) {
            return null;
        }

        // Apply formatters if needed (for formatted/sql modes)
        if ($output_mode !== 'raw') {
            $data_array = $this->applyRelationshipFormatters($data_array, $alias, $output_mode);
        }

        // Create Model instance
        $related_class = $relationship['related_model'];
        $model = new $related_class();

        // Separate regular fields from relationship data
        $relationship_data = [];
        $field_data = [];

        // Get all relationship aliases for this model
        $model_relationships = [];
        if (method_exists($model, 'getRules')) {
            $rules = $model->getRules();
            foreach ($rules as $field_name => $rule) {
                if (isset($rule['relationship'])) {
                    $model_relationships[] = $rule['relationship']['alias'];
                }
            }
        }

        foreach ($data_array as $field => $value) {
            if (in_array($field, $model_relationships)) {
                $relationship_data[$field] = $value;
            } else {
                $field_data[$field] = $value;
            }
        }

        // Populate the model with regular field data
        foreach ($field_data as $field => $value) {
            try {
                $model->$field = $value;
            } catch (\Exception $e) {
                // Skip fields that can't be set
                continue;
            }
        }

        // Manually populate relationship data into records_objects
        // This allows lazy loading to work properly via __get
        if (!empty($relationship_data)) {
            $reflection = new \ReflectionClass($model);

            if (property_exists($model, 'records_objects')) {
                $records_objects_prop = $reflection->getProperty('records_objects');
                $records_objects_prop->setAccessible(true);

                // Get current records_objects (should have been populated by field setters above)
                $current_records = $records_objects_prop->getValue($model);

                // If records_objects wasn't initialized, create it
                if ($current_records === null || !isset($current_records[0])) {
                    $current_records = [0 => []];
                }

                // Add relationship data to the existing records_objects[0]
                foreach ($relationship_data as $rel_alias => $rel_data) {
                    $current_records[0][$rel_alias] = $rel_data;
                }

                $records_objects_prop->setValue($model, $current_records);
            }

            // Ensure current_index is set to 0
            if (property_exists($model, 'current_index')) {
                $current_index_prop = $reflection->getProperty('current_index');
                $current_index_prop->setAccessible(true);
                $current_index_prop->setValue($model, 0);
            }
        }

        // Mark as original (not modified)
        if (method_exists($model, 'markAsOriginal')) {
            $model->markAsOriginal();
        }

        // Explicitly enable relations - this instance should support lazy loading
        // The blocking is only needed during batch loading to prevent infinite loops
        // Once the instance is created, it should behave like a normal model
        if (method_exists($model, 'setBlockRelations')) {
            $model->setBlockRelations(false);
        }

        return $model;
    }
}
