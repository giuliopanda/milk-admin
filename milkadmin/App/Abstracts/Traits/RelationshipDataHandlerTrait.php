<?php
namespace App\Abstracts\Traits;

use App\Abstracts\AbstractModel;

!defined('MILK_DIR') && die();

/**
 * RelationshipDataHandlerTrait - ULTRA-SIMPLIFIED version
 *
 * Relationship data is stored directly in records_array with its original name (e.g. 'films')
 * No prefixes, no temporary arrays - just store it as-is.
 */
trait RelationshipDataHandlerTrait
{
    /**
     * Handle relationship data in fill() method
     * Validates and passes through relationship arrays
     *
     * @param array $data Input data
     * @return array Data with validated relationships
     */
    protected function extractRelationshipData(array $data): array
    {
        if (!method_exists($this, 'hasRelationship')) {
            return $data; // No relationships support
        }

        foreach ($data as $key => $value) {
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

                    // Keep relationship data as-is (no prefix needed)
                    // It will be stored in records_array with its original key

                } elseif (in_array($relationship['type'], ['hasOne', 'hasMany', 'belongsTo']) && $value instanceof AbstractModel) {
                    $this->error = true;
                    $this->last_error = "Cannot assign Model instance to relationship '{$key}'. Use array notation instead.";
                    return [];
                }
            }
        }

        // Return data as-is (relationships included)
        return $data;
    }
}
