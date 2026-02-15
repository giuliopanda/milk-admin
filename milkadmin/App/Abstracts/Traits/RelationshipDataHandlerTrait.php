<?php
namespace App\Abstracts\Traits;

use App\Abstracts\AbstractModel;

!defined('MILK_DIR') && die();

/**
 * RelationshipDataHandlerTrait - Handles relationship and meta data in fill() operations
 *
 * Relationship data is stored directly in records_objects with its original name.
 * Meta data (hasMeta) is stored as scalar values and synced to meta table on save.
 */
trait RelationshipDataHandlerTrait
{
    /**
     * Track meta values that have been modified and need saving
     * Format: [record_index => [alias => value]]
     * @var array
     */
    protected array $dirty_meta = [];

    /**
     * Handle relationship and meta data in fill() method
     * Validates and passes through relationship arrays and meta values
     *
     * @param array $data Input data
     * @return array Data with validated relationships and meta
     */
    protected function extractRelationshipData(array $data): array
    {
        if (!method_exists($this, 'hasRelationship')) {
            return $data; // No relationships support
        }

        foreach ($data as $key => $value) {
            // Check if this is a hasMeta field
            if ($this->hasMetaRelationship($key)) {
                // Meta values should be scalars (string, int, float, bool, null)
                if (is_array($value) || is_object($value)) {
                    // Try to JSON encode complex values
                    $data[$key] = json_encode($value);
                }
                // Mark as dirty for save
                $this->markMetaDirty($key, $data[$key]);
                continue;
            }

            // Check if this is a relationship
            if ($this->hasRelationship($key)) {
                $relationship = $this->getRelationship($key);

                // Only process hasOne, hasMany and belongsTo relationships with array values
                if (in_array($relationship['type'], ['hasOne', 'hasMany', 'belongsTo']) && is_array($value)) {
                    // Validate based on relationship type
                    if ($relationship['type'] === 'hasMany') {
                        // hasMany expects array of arrays
                        foreach ($value as $idx => $child_data) {
                            if (!is_array($child_data)) {
                                continue; // Skip non-array elements
                            }
                            // Validate no deeply nested arrays
                            foreach ($child_data as $field => $field_value) {
                                if (is_array($field_value) || is_object($field_value)) {
                                    $this->error = true;
                                    $this->last_error = "Relationship '{$key}' child record {$idx} field '{$field}' cannot contain nested arrays or objects";
                                    return [];
                                }
                            }
                        }
                    } else {
                        // hasOne/belongsTo expects single array
                        foreach ($value as $k => $v) {
                            if (is_array($v) || is_object($v)) {
                                $this->error = true;
                                $this->last_error = "Relationship '{$key}' field '{$k}' cannot contain nested arrays or objects";
                                return [];
                            }
                        }
                    }
                } elseif (in_array($relationship['type'], ['hasOne', 'hasMany', 'belongsTo']) && $value instanceof AbstractModel) {
                    $this->error = true;
                    $this->last_error = "Cannot assign Model instance to relationship '{$key}'. Use array notation instead.";
                    return [];
                }
            }
        }

        // Return data as-is (relationships and meta included)
        return $data;
    }

    /**
     * Mark a meta field as dirty (needs saving)
     *
     * @param string $alias Meta field alias
     * @param mixed $value New value
     * @return void
     */
    protected function markMetaDirty(string $alias, mixed $value): void
    {
        $index = $this->current_index ?? 0;
        if (!isset($this->dirty_meta[$index])) {
            $this->dirty_meta[$index] = [];
        }
        $this->dirty_meta[$index][$alias] = $value;
    }

    /**
     * Check if any meta values need saving for the current record
     *
     * @return bool
     */
    public function hasDirtyMeta(?int $index = null): bool
    {
        $index = $index ?? $this->current_index ?? 0;
        return !empty($this->dirty_meta[$index]);
    }

    /**
     * Get all dirty meta values for the current record
     *
     * @return array [alias => value]
     */
    public function getDirtyMeta(?int $index = null): array
    {
        $index = $index ?? $this->current_index ?? 0;
        return $this->dirty_meta[$index] ?? [];
    }

    /**
     * Get dirty meta map for all tracked records
     *
     * @return array [record_index => [alias => value]]
     */
    public function getAllDirtyMeta(): array
    {
        return $this->dirty_meta;
    }

    /**
     * Clear dirty meta tracking after save
     *
     * @param int|null $index Record index, or null for current
     * @return void
     */
    public function clearDirtyMeta(?int $index = null): void
    {
        $index = $index ?? $this->current_index ?? 0;
        unset($this->dirty_meta[$index]);
    }

    /**
     * Save all dirty meta values for a specific entity
     * Should be called after the main record is saved
     *
     * @param mixed $entity_id The ID of the main entity
     * @return bool Success status
     */
    public function saveMeta(mixed $entity_id, ?int $index = null): bool
    {
        $index = $index ?? $this->current_index ?? 0;

        if (!$this->hasDirtyMeta($index)) {
            return true;
        }

        $dirty = $this->getDirtyMeta($index);
        $rules = $this->rule_builder->getRules();
        $all_meta = $this->rule_builder->getAllHasMeta();

        if (empty($all_meta)) {
            return true;
        }

        // Group by model class
        $by_model = [];
        foreach ($dirty as $alias => $value) {
            // Find the meta config for this alias
            foreach ($all_meta as $config) {
                if ($config['alias'] === $alias) {
                    $model_class = $config['related_model'];
                    if (!isset($by_model[$model_class])) {
                        $by_model[$model_class] = [];
                    }
                    $by_model[$model_class][] = [
                        'config' => $config,
                        'value' => $value,
                    ];
                    break;
                }
            }
        }

        // Save each group
        foreach ($by_model as $model_class => $items) {
            if (!$this->saveMetaBatch($model_class, $items, $entity_id)) {
                return false;
            }
        }

        $this->clearDirtyMeta($index);
        return true;
    }

    /**
     * Save a batch of meta values to a single meta table
     *
     * @param string $model_class Meta model class
     * @param array $items Array of ['config' => ..., 'value' => ...]
     * @param mixed $entity_id Entity ID
     * @return bool Success status
     */
    protected function saveMetaBatch(string $model_class, array $items, mixed $entity_id): bool
    {
        if (empty($items)) {
            return true;
        }

        $first_config = $items[0]['config'];
        $foreign_key = $first_config['foreign_key'];
        $meta_key_column = $first_config['meta_key_column'];
        $meta_value_column = $first_config['meta_value_column'];

        // Get meta model instance
        $meta_model = new $model_class();
        $meta_table = $meta_model->getRuleBuilder()->getTable();
        $meta_pk = $meta_model->getPrimaryKey();

        // Get database connection
        $db = $this->db;

        foreach ($items as $item) {
            $config = $item['config'];
            $value = $item['value'];
            $meta_key = $config['meta_key_value'];

            // Check if meta already exists
            $existing = $db->getRow(
                'SELECT ' . $db->qn($meta_pk) . ' FROM ' . $db->qn($meta_table) . 
                ' WHERE ' . $db->qn($foreign_key) . ' = ? AND ' . $db->qn($meta_key_column) . ' = ?',
                [$entity_id, $meta_key]
            );

            if ($value === null || $value === '') {
                // Delete if value is empty/null
                if ($existing) {
                    $db->query(
                        'DELETE FROM ' . $db->qn($meta_table) . 
                        ' WHERE ' . $db->qn($foreign_key) . ' = ? AND ' . $db->qn($meta_key_column) . ' = ?',
                        [$entity_id, $meta_key]
                    );
                }
            } elseif ($existing) {
                // Update existing
                $db->query(
                    'UPDATE ' . $db->qn($meta_table) . 
                    ' SET ' . $db->qn($meta_value_column) . ' = ? ' .
                    ' WHERE ' . $db->qn($foreign_key) . ' = ? AND ' . $db->qn($meta_key_column) . ' = ?',
                    [$value, $entity_id, $meta_key]
                );
            } else {
                // Insert new
                $db->query(
                    'INSERT INTO ' . $db->qn($meta_table) . 
                    ' (' . $db->qn($foreign_key) . ', ' . $db->qn($meta_key_column) . ', ' . $db->qn($meta_value_column) . ')' .
                    ' VALUES (?, ?, ?)',
                    [$entity_id, $meta_key, $value]
                );
            }
        }

        return true;
    }

    /**
     * Delete all meta values for an entity
     * Should be called when deleting the main record
     *
     * @param mixed $entity_id The ID of the main entity
     * @return bool Success status
     */
    public function deleteMeta(mixed $entity_id): bool
    {
        $all_meta = $this->rule_builder->getAllHasMeta();

        if (empty($all_meta)) {
            return true;
        }

        // Group by model class
        $by_model = [];
        foreach ($all_meta as $config) {
            $model_class = $config['related_model'];
            if (!isset($by_model[$model_class])) {
                $by_model[$model_class] = $config;
            }
        }

        $db = $this->db;

        foreach ($by_model as $model_class => $config) {
            $meta_model = new $model_class();
            $meta_table = $meta_model->getRuleBuilder()->getTable();
            $foreign_key = $config['foreign_key'];

            $db->query(
                'DELETE FROM ' . $db->qn($meta_table) . 
                ' WHERE ' . $db->qn($foreign_key) . ' = ?',
                [$entity_id]
            );
        }

        return true;
    }
}
