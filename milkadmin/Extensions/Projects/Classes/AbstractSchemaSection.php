<?php
namespace Extensions\Projects\Classes;

!defined('MILK_DIR') && die();

/**
 * AbstractSchemaSection - Base class for schema section handlers
 *
 * Provides common functionality for section handlers.
 * Extend this class to create new section handlers with less boilerplate.
 */
abstract class AbstractSchemaSection implements SchemaSectionInterface
{
    protected array $options = [];

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * Get a specific option value
     *
     * @param string $key Option key
     * @param mixed $default Default value if not set
     * @return mixed
     */
    protected function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data): array
    {
        // Default implementation: always valid
        // Override in subclasses for specific validation
        return ['valid' => true, 'errors' => []];
    }
}
