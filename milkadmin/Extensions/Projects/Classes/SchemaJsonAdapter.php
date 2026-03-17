<?php
namespace Extensions\Projects\Classes;

!defined('MILK_DIR') && die();

/**
 * SchemaJsonAdapter - Main facade for JSON schema conversion
 *
 * Manages multiple schema sections (model, controller, view, routes, etc.)
 * through a plugin-based architecture. Each section is handled by a dedicated
 * class implementing SchemaSectionInterface.
 *
 * JSON Structure:
 * ```json
 * {
 *     "model": {
 *         "table": "users",
 *         "fields": [...]
 *     },
 *     "controller": {
 *         "actions": [...],
 *         "middleware": [...]
 *     },
 *     "view": {
 *         "list": {...},
 *         "form": {...}
 *     },
 *     "routes": {
 *         "prefix": "/api/users",
 *         "endpoints": [...]
 *     },
 *     "permissions": {
 *         "roles": [...],
 *         "rules": [...]
 *     }
 * }
 * ```
 *
 * Usage:
 * ```php
 * $adapter = new SchemaJsonAdapter();
 *
 * // Parse JSON into components
 * $components = $adapter->fromJson($jsonString);
 * $ruleBuilder = $components['model'];
 *
 * // Modify existing components
 * $adapter->applyJson($components, $modificationsJson);
 *
 * // Export back to JSON
 * $json = $adapter->toJson($components);
 * ```
 *
 * Adding new section handlers:
 * ```php
 * // 1. Create handler implementing SchemaSectionInterface
 * class ControllerSchemaSection implements SchemaSectionInterface { ... }
 *
 * // 2. Register with adapter
 * $adapter->registerSection(new ControllerSchemaSection());
 *
 * // 3. Now 'controller' section will be parsed/serialized automatically
 * ```
 */
class SchemaJsonAdapter
{
    /**
     * Registered section handlers
     * @var array<string, SchemaSectionInterface>
     */
    protected array $sections = [];

    /**
     * Schema metadata (version, name, etc.)
     * @var array
     */
    protected array $metadata = [];

    public function __construct()
    {
        // Register default sections
        $this->registerSection(new ModelSchemaSection());
    }

    /**
     * Register a section handler
     *
     * @param SchemaSectionInterface $section
     * @return self
     */
    public function registerSection(SchemaSectionInterface $section): self
    {
        $this->sections[$section->getKey()] = $section;
        return $this;
    }

    /**
     * Get a registered section handler
     *
     * @param string $key Section key
     * @return SchemaSectionInterface|null
     */
    public function getSection(string $key): ?SchemaSectionInterface
    {
        return $this->sections[$key] ?? null;
    }

    /**
     * Get all registered section keys
     *
     * @return array
     */
    public function getRegisteredSections(): array
    {
        return array_keys($this->sections);
    }

    /**
     * Set options for a specific section handler
     *
     * @param string $key Section key
     * @param array $options Options to set
     * @return self
     */
    public function setSectionOptions(string $key, array $options): self
    {
        if (isset($this->sections[$key])) {
            $this->sections[$key]->setOptions($options);
        }
        return $this;
    }

    /**
     * Set callable resolver for model section (convenience method)
     *
     * @param callable $resolver
     * @return self
     */
    public function setCallableResolver(callable $resolver): self
    {
        $modelSection = $this->getSection('model');
        if ($modelSection instanceof ModelSchemaSection) {
            $modelSection->setCallableResolver($resolver);
        }
        return $this;
    }

    /**
     * Set model class resolver for relationship model names (convenience method).
     *
     * @param callable $resolver Function that receives a model class string and returns resolved FQCN
     * @return self
     */
    public function setModelClassResolver(callable $resolver): self
    {
        $modelSection = $this->getSection('model');
        if ($modelSection instanceof ModelSchemaSection) {
            $modelSection->setModelClassResolver($resolver);
        }
        return $this;
    }

    /**
     * Parse JSON string into components
     *
     * @param string $json JSON string
     * @return array Associative array of parsed components keyed by section name
     * @throws \JsonException
     */
    public function fromJson(string $json): array
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return $this->fromArray($data);
    }

    /**
     * Parse array into components
     *
     * @param array $data Schema data
     * @return array Associative array of parsed components
     */
    public function fromArray(array $data): array
    {
        $components = [];

        // Extract metadata
        $this->metadata = $this->extractMetadata($data);

        // Parse each registered section
        foreach ($this->sections as $key => $section) {
            if (isset($data[$key])) {
                $components[$key] = $section->parse($data[$key]);
            }
        }

        // Store unhandled sections for passthrough
        foreach ($data as $key => $value) {
            if (!isset($this->sections[$key]) && !$this->isMetadataKey($key)) {
                $components['_unhandled'][$key] = $value;
            }
        }

        return $components;
    }

    /**
     * Apply JSON modifications to existing components
     *
     * @param array $components Existing components array
     * @param string $json JSON string with modifications
     * @return array Modified components
     * @throws \JsonException
     */
    public function applyJson(array $components, string $json): array
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return $this->applyArray($components, $data);
    }

    /**
     * Apply array modifications to existing components
     *
     * @param array $components Existing components array
     * @param array $data Modifications data
     * @return array Modified components
     */
    public function applyArray(array $components, array $data): array
    {
        // Update metadata
        $newMetadata = $this->extractMetadata($data);
        $this->metadata = array_merge($this->metadata, $newMetadata);

        // Apply modifications to each section
        foreach ($this->sections as $key => $section) {
            if (isset($data[$key])) {
                $target = $components[$key] ?? null;
                $components[$key] = $section->parse($data[$key], $target);
            }
        }

        // Handle unhandled sections
        foreach ($data as $key => $value) {
            if (!isset($this->sections[$key]) && !$this->isMetadataKey($key)) {
                $components['_unhandled'][$key] = $value;
            }
        }

        return $components;
    }

    /**
     * Convert components to JSON string
     *
     * @param array $components Components array
     * @param int $flags JSON encode flags
     * @return string
     */
    public function toJson(array $components, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray($components), $flags);
    }

    /**
     * Convert components to array
     *
     * @param array $components Components array
     * @return array
     */
    public function toArray(array $components): array
    {
        $data = [];

        // Add metadata first
        if (!empty($this->metadata)) {
            $data = array_merge($data, $this->metadata);
        }

        // Serialize each section
        foreach ($this->sections as $key => $section) {
            if (isset($components[$key])) {
                $data[$key] = $section->serialize($components[$key]);
            }
        }

        // Include unhandled sections
        if (isset($components['_unhandled'])) {
            $data = array_merge($data, $components['_unhandled']);
        }

        return $data;
    }

    /**
     * Validate JSON schema
     *
     * @param string $json JSON string to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return $this->validateArray($data);
        } catch (\JsonException $e) {
            return [
                'valid' => false,
                'errors' => ['Invalid JSON: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Validate array schema
     *
     * @param array $data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateArray(array $data): array
    {
        $allErrors = [];

        foreach ($this->sections as $key => $section) {
            if (isset($data[$key])) {
                $result = $section->validate($data[$key]);
                if (!$result['valid']) {
                    foreach ($result['errors'] as $error) {
                        $allErrors[] = "[$key] $error";
                    }
                }
            }
        }

        return [
            'valid' => empty($allErrors),
            'errors' => $allErrors
        ];
    }

    /**
     * Get stored metadata
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set metadata
     *
     * @param array $metadata
     * @return self
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Extract metadata from data array
     */
    protected function extractMetadata(array $data): array
    {
        $metadataKeys = ['_version', '_name', '_description', '_author', '_created', '_updated'];
        $metadata = [];

        foreach ($metadataKeys as $key) {
            if (isset($data[$key])) {
                $metadata[$key] = $data[$key];
            }
        }

        return $metadata;
    }

    /**
     * Check if key is a metadata key
     */
    protected function isMetadataKey(string $key): bool
    {
        return str_starts_with($key, '_');
    }
}
