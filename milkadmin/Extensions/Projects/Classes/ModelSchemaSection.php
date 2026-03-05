<?php
namespace Extensions\Projects\Classes;

!defined('MILK_DIR') && die();

use App\Abstracts\RuleBuilder;

/**
 * ModelSchemaSection - Handles the 'model' section of the schema
 *
 * Manages conversion between JSON model definitions and RuleBuilder instances.
 *
 * JSON structure for 'model' section:
 * ```json
 * {
 *     "model": {
 *         "table": "table_name",
 *         "db": "db_connection",
 *         "extensions": ["ext1", "ext2"],
 *         "rename_fields": {"old_name": "new_name"},
 *         "fields": [
 *             {
 *                 "name": "field_name",
 *                 "method": "string|int|date|...",
 *                 "label": "Field Label",
 *                 ...
 *             }
 *         ]
 *     }
 * }
 * ```
 */
class ModelSchemaSection extends AbstractSchemaSection
{
    protected ModelJsonParser $parser;
    protected ModelJsonSerializer $serializer;

    public function __construct()
    {
        $this->parser = new ModelJsonParser();
        $this->serializer = new ModelJsonSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function getKey(): string
    {
        return 'model';
    }

    /**
     * Set callable resolver for dynamic options like "\\StaticData::Days()"
     *
     * @param callable $resolver Function that receives a string and returns resolved value
     * @return self
     */
    public function setCallableResolver(callable $resolver): self
    {
        $this->parser->setCallableResolver($resolver);
        return $this;
    }

    /**
     * Set model class resolver for relationship model names.
     *
     * @param callable $resolver Function that receives a model class string and returns resolved FQCN
     * @return self
     */
    public function setModelClassResolver(callable $resolver): self
    {
        $this->parser->setModelClassResolver($resolver);
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $data Model section data
     * @param RuleBuilder|null $target Existing RuleBuilder to modify
     * @return RuleBuilder
     */
    public function parse(array $data, mixed $target = null): RuleBuilder
    {
        return $this->parser->parse($data, $target);
    }

    /**
     * Analyze which fields in the provided model section will be ignored when applied to $target.
     *
     * A field is ignored when the same field name already exists in the PHP model RuleBuilder.
     *
     * @param array $data Model section data (expects 'fields' key)
     * @param RuleBuilder $target Existing RuleBuilder
     * @return array<string,string> Map field_name => reason
     */
    public function analyzeIgnoredFields(array $data, RuleBuilder $target): array
    {
        return $this->parser->analyzeIgnoredFields($data, $target);
    }

    /**
     * Get ignored fields collected during the last parse().
     *
     * @return array<string,string> Map field_name => reason
     */
    public function getLastIgnoredFields(): array
    {
        return $this->parser->getLastIgnoredFields();
    }

    /**
     * {@inheritdoc}
     *
     * @param RuleBuilder $source RuleBuilder to serialize
     * @return array
     */
    public function serialize(mixed $source): array
    {
        if (!$source instanceof RuleBuilder) {
            throw new \InvalidArgumentException('ModelSchemaSection expects a RuleBuilder instance');
        }

        return $this->serializer->serialize($source);
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data): array
    {
        $errors = [];

        if (isset($data['fields'])) {
            if (!is_array($data['fields'])) {
                $errors[] = "'fields' must be an array";
            } else {
                foreach ($data['fields'] as $index => $field) {
                    if (!isset($field['name'])) {
                        $errors[] = "Field at index $index is missing 'name'";
                    }
                    if (isset($field['method']) && !in_array($field['method'], ModelJsonParser::VALID_METHODS)) {
                        $errors[] = "Field '{$field['name']}' has invalid method '{$field['method']}'";
                    }
                }
            }
        }

        if (isset($data['table']) && !is_string($data['table'])) {
            $errors[] = "'table' must be a string";
        }

        if (isset($data['db']) && !is_string($data['db'])) {
            $errors[] = "'db' must be a string";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
