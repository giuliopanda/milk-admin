<?php
namespace App\Abstracts;

!defined('MILK_DIR') && die();

use App\Abstracts\Traits\RuleBuilderFieldTypesTrait;
use App\Abstracts\Traits\RuleBuilderFileFieldsTrait;
use App\Abstracts\Traits\RuleBuilderFieldConfigTrait;
use App\Abstracts\Traits\RuleBuilderRelationshipsTrait;

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
    use RuleBuilderFieldTypesTrait;
    use RuleBuilderFileFieldsTrait;
    use RuleBuilderFieldConfigTrait;
    use RuleBuilderRelationshipsTrait;

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
     * Field rename map (from => to)
     * @var array<string, string>
     */
    protected array $rename_fields = [];

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
     * Track if we are currently defining a relationship
     * Format: ['type' => 'withCount|hasMany|hasOne|belongsTo|hasMeta', 'field' => 'field_name', 'index' => 0]
     * @var array|null
     */
    protected ?array $active_relationship = null;

    // ========================================
    // Core field definition
    // ========================================

    /**
     * Start defining a new field
     *
     * @param string $name Field name
     * @param string $type Field type
     * @return self
     */
    public function field(string $name, string $type = 'string'): self
    {
        $this->deactivateRelationship();
        $this->current_field = $name;
        $this->rules[$name] = [
            'type' => $type,
            'length' => null,
            'precision' => null,
            'nullable' => true,
            'default' => null,
            'primary' => false,
            'label' => $name,
            'options' => null,
            'index' => false,
            'unique' => false,
            'list' => true,
            'edit' => true,
            'view' => true,
            'sql' => true,
            'form-type' => null,
            'form-label' => null,
            'timezone_conversion' => false,
            'checkbox_checked' => null,
            'checkbox_unchecked' => null,
        ];
        return $this;
    }

    /**
     * Change current field
     *
     * @param string $name Field name
     * @return self
     */
    public function ChangeCurrentField($name): self
    {
        $this->current_field = $name;
        return $this;
    }

    // ========================================
    // Table and database configuration
    // ========================================

    /**
     * Define table
     *
     * @param string $name Table name
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
     * @param string $name Database type
     * @return self
     */
    public function db(string $name): self
    {
        $this->db_type = $name;
        return $this;
    }

    /**
     * Rename a field in the schema (from => to)
     *
     * @param string $from Existing field name
     * @param string $to New field name
     * @return self
     */
    public function renameField(string $from, string $to): self
    {
        $this->rename_fields[$from] = $to;
        return $this;
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
        // Normalize group labels for option groups.
        foreach ($this->rules as $name => $rule) {
            $type = strtolower(trim((string) ($rule['form-type'] ?? ($rule['type'] ?? ''))));
            if (!in_array($type, ['radio', 'checkboxes'], true)) {
                continue;
            }

            $label = trim((string) ($rule['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            if (!isset($this->rules[$name]['form-params']) || !is_array($this->rules[$name]['form-params'])) {
                $this->rules[$name]['form-params'] = [];
            }

            $groupLabel = trim((string) ($this->rules[$name]['form-params']['label'] ?? ''));
            if ($groupLabel === '') {
                $this->rules[$name]['form-params']['label'] = $label;
            }
        }

        return $this->rules;
    }

    /**
     * Get all field renames
     *
     * @return array<string, string>
     */
    public function getRenameFields(): array
    {
        return $this->rename_fields;
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
    // Getters
    // ========================================

    /**
     * Get table name
     *
     * @return string|null Table name
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Get primary key name
     *
     * @return string|null Primary key name
     */
    public function getPrimaryKey(): ?string
    {
        return $this->primary_key;
    }

    /**
     * Get database type (db or db2)
     *
     * @return string|null Database type
     */
    public function getDbType(): ?string
    {
        return $this->db_type;
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

    /**
     * Get all hasMeta configurations from all fields
     *
     * @return array Array of hasMeta configurations with their local_key
     */
    public function getAllHasMeta(): array
    {
        $all_meta = [];
        foreach ($this->rules as $field_name => $rule) {
            if (isset($rule['hasMeta']) && is_array($rule['hasMeta'])) {
                foreach ($rule['hasMeta'] as $meta_config) {
                    $all_meta[] = $meta_config;
                }
            }
        }
        return $all_meta;
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
        $label = preg_replace(['/([a-z])([A-Z])/', '/_+/', '/[-\s]+/'], ['\\1 \\2', ' ', ' '], $fieldName);
        $label = preg_replace('/\s+/', ' ', trim($label));
        return ucfirst($label);
    }
}
