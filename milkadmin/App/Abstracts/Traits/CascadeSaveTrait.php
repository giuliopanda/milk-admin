<?php
namespace App\Abstracts\Traits;

use App\Logs;

!defined('MILK_DIR') && die();

/**
 * CascadeSaveTrait - ULTRA-SIMPLIFIED version
 *
 * Relationship data is stored directly in records_objects with its original key name.
 * We identify relationships by checking if the key matches a relationship alias.
 */
trait CascadeSaveTrait
{
    /**
     * Process belongsTo relationships BEFORE saving parent
     *
     * DEPRECATED: belongsTo relationships are no longer saved automatically
     * This method is kept for backward compatibility but does nothing
     *
     * @param int $index Record index
     * @param array $record Current record
     * @return array|null Updated record with foreign keys, or null on error
     */
    protected function processCascadeRelationships(int $index, array $record): ?array
    {
        // belongsTo relationships are no longer saved automatically
        // They are meant to reference existing records, not create/modify them
        return $record;
    }

    /**
     * Process hasOne and hasMany relationships AFTER parent is saved
     *
     * @param int $index Record index
     * @param array $record Record data WITH ID
     * @return array Results array
     */
    protected function processHasOneRelationships(int $index, array $record): array
    {
        $relationships_results = [];

        // Check each field to see if it's a relationship
        foreach ($record as $field_name => $value) {
            // Skip special fields
            if (strpos($field_name, '___') === 0) {
                continue;
            }

            // Check if this field is a relationship
            if (!method_exists($this, 'hasRelationship') || !$this->hasRelationship($field_name)) {
                continue;
            }

            $relationship = $this->getRelationship($field_name);
            if (!$relationship) {
                continue;
            }

            // Skip belongsTo (should never be saved)
            if ($relationship['type'] === 'belongsTo') {
                continue;
            }

            // Check if value is null or empty - skip save if so
            if ($value === null || (is_array($value) && empty($value))) {
                continue;
            }

            // Process hasOne and hasMany
            if ($relationship['type'] === 'hasOne') {
                // Check if cascade save is allowed for this hasOne relationship
                $allowCascadeSave = $relationship['allowCascadeSave'] ?? false;
                if (!$allowCascadeSave) {
                    // Skip: cascade save not explicitly enabled for this hasOne
                    continue;
                }

                $result = $this->saveHasOneRelatedModel($relationship, $value, $record);
                if ($result !== null) {
                    $relationships_results[$field_name] = $result;
                }
            } elseif ($relationship['type'] === 'hasMany') {
                // Check if cascade save is allowed for this hasMany relationship
                $allowCascadeSave = $relationship['allowCascadeSave'] ?? false;
                if (!$allowCascadeSave) {
                    // Skip: cascade save not explicitly enabled for this hasMany
                    continue;
                }

                // hasMany: save multiple related records
                $results = $this->saveHasManyRelatedModels($relationship, $value, $record);
                if (!empty($results)) {
                    $relationships_results[$field_name] = $results;
                }
            }
        }

        return $relationships_results;
    }

    /**
     * Save a belongsTo related model
     *
     * DEPRECATED: belongsTo relationships are no longer saved automatically
     * This method is kept for backward compatibility but should not be used
     */
    protected function saveBelongsToRelatedModel(array $relationship, array $data, array $parent_record): ?array
    {
        // belongsTo relationships are no longer saved automatically
        // They are meant to reference existing records, not create/modify them
        return null;
    }

    /**
     * Save a hasOne related model
     */
    protected function saveHasOneRelatedModel(array $relationship, array $data, array $parent_record): ?array
    {
        $related_class = $relationship['related_model'];
        $foreign_key_in_child = $relationship['foreign_key'];
        $local_key = $relationship['local_key'];
        $parent_id = $parent_record[$local_key] ?? null;

        if ($parent_id === null) {
            return null;
        }

        try {
            $related_model = new $related_class(true);
            $existing = $related_model->query()->where("$foreign_key_in_child = ?", [$parent_id])->getResults();
            $is_update = ($existing && $existing->count() > 0);

            if ($is_update) {
                foreach ($data as $key => $value) {
                    $existing->$key = $value;
                }
                $existing->$foreign_key_in_child = $parent_id;
                if (!$existing->save()) {
                    return null;
                }
                return ['id' => $existing->id, 'action' => 'edit', 'result' => true, 'last_error' => ''];
            } else {
                $data[$foreign_key_in_child] = $parent_id;
                $related_model->fill($data);
                if (!$related_model->save()) {
                    return null;
                }
                $save_results = $related_model->getCommitResults();
                $inserted_id = $save_results[0]['id'] ?? null;
                return ['id' => $inserted_id, 'action' => 'insert', 'result' => true, 'last_error' => ''];
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Save hasMany related models (multiple records)
     */
    protected function saveHasManyRelatedModels(array $relationship, array $data, array $parent_record): array
    {
        $related_class = $relationship['related_model'];
        $foreign_key_in_child = $relationship['foreign_key'];
        $local_key = $relationship['local_key'];
        $parent_id = $parent_record[$local_key] ?? null;

        if ($parent_id === null) {
            return [];
        }

        // Check if data is array of arrays
        $is_array_of_arrays = false;
        if (!empty($data)) {
            $first_element = reset($data);
            $is_array_of_arrays = is_array($first_element);
        }

        if (!$is_array_of_arrays) {
            $data = [$data];
        }

        $results = [];

        try {
            foreach ($data as $idx => $child_data) {
                if (empty($child_data) || !is_array($child_data)) {
                    continue;
                }

                $child_data[$foreign_key_in_child] = $parent_id;
                $child_id = $child_data['id'] ?? null;
                $is_update = ($child_id !== null && $child_id !== '' && $child_id !== 0);

                $related_model = new $related_class(true);

                if ($is_update) {
                    $existing = $related_model->getById($child_id);
                    if ($existing && !$existing->isEmpty()) {
                        foreach ($child_data as $key => $value) {
                            $existing->$key = $value;
                        }
                        if ($existing->save()) {
                            $results[] = ['id' => $child_id, 'action' => 'edit', 'result' => true, 'last_error' => ''];
                        } else {
                            $results[] = ['id' => $child_id, 'action' => 'edit', 'result' => false, 'last_error' => $existing->getLastError()];
                        }
                        continue;
                    }
                }

                $related_model->fill($child_data);
                if ($related_model->save()) {
                    $save_results = $related_model->getCommitResults();
                    $inserted_id = $save_results[0]['id'] ?? null;
                    $results[] = ['id' => $inserted_id, 'action' => 'insert', 'result' => true, 'last_error' => ''];
                } else {
                    $results[] = ['id' => null, 'action' => 'insert', 'result' => false, 'last_error' => $related_model->getLastError()];
                }
            }
        } catch (\Exception $e) {
            $this->error = true;
            $this->last_error = "Exception saving hasMany: " . $e->getMessage();
        }

        return $results;
    }

    /**
     * Process cascade delete for hasOne/hasMany relationships
     */
    protected function processCascadeDelete($parent_id): bool
    {
        $rules = $this->getRules();

        foreach ($rules as $field_name => $rule) {
            if (!isset($rule['relationship'])) {
                continue;
            }

            $relationship = $rule['relationship'];

            if ($relationship['type'] !== 'hasOne' && $relationship['type'] !== 'hasMany') {
                continue;
            }

            $related_class = $relationship['related_model'];
            $foreign_key_in_child = $relationship['foreign_key'];
            $onDelete = $relationship['onDelete'] ?? 'CASCADE';

            try {
                $related_model = new $related_class(true);
                $table_name = $related_model->getRuleBuilder()->getTable();

                if (empty($table_name)) {
                    $this->error = true;
                    $this->last_error = "Related model table name is empty";
                    return false;
                }

                switch ($onDelete) {
                    case 'CASCADE':
                        $success = $this->db->delete($table_name, [$foreign_key_in_child => $parent_id]);
                        if (!$success) {
                            $this->error = true;
                            $this->last_error = "Failed to cascade delete";
                            return false;
                        }
                        break;

                    case 'SET NULL':
                        $success = $this->db->update($table_name, [$foreign_key_in_child => null], [$foreign_key_in_child => $parent_id]);
                        if (!$success) {
                            $this->error = true;
                            $this->last_error = "Failed to set NULL";
                            return false;
                        }
                        break;

                    case 'RESTRICT':
                        $query = "SELECT COUNT(*) as count FROM " . $this->db->qn($table_name) . " WHERE " . $this->db->qn($foreign_key_in_child) . " = ?";
                        $result = $this->db->getRow($query, [$parent_id]);
                        $count = $result ? (int)$result->count : 0;
                        if ($count > 0) {
                            $this->error = true;
                            $this->last_error = "Cannot delete parent: {$count} related record(s) exist (onDelete: RESTRICT)";
                            return false;
                        }
                        break;
                }
            } catch (\Exception $e) {
                $this->error = true;
                $this->last_error = "Exception during cascade delete: " . $e->getMessage();
                return false;
            }
        }

        return true;
    }
}
