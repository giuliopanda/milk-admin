<?php
namespace Extensions\Projects\Classes;

!defined('MILK_DIR') && die();

/**
 * SchemaSectionInterface - Interface for schema section handlers
 *
 * Each section of the JSON schema (model, controller, view, routes, etc.)
 * is handled by a dedicated class implementing this interface.
 *
 * To add a new section handler:
 * 1. Create a class implementing this interface
 * 2. Implement parse() to convert JSON data to your target object
 * 3. Implement serialize() to convert your object back to array
 * 4. Register the handler with SchemaJsonAdapter::registerSection()
 *
 * Example for a future ControllerSchemaSection:
 * ```php
 * class ControllerSchemaSection implements SchemaSectionInterface
 * {
 *     public function getKey(): string { return 'controller'; }
 *
 *     public function parse(array $data, mixed $target = null): ControllerConfig
 *     {
 *         $config = $target ?? new ControllerConfig();
 *         // Parse actions, middleware, permissions, etc.
 *         if (isset($data['actions'])) {
 *             foreach ($data['actions'] as $action) {
 *                 $config->addAction($action['name'], $action['method'] ?? 'GET');
 *             }
 *         }
 *         return $config;
 *     }
 *
 *     public function serialize(mixed $source): array
 *     {
 *         // Convert ControllerConfig back to array
 *         return ['actions' => $source->getActions(), ...];
 *     }
 * }
 * ```
 */
interface SchemaSectionInterface
{
    /**
     * Get the JSON key for this section (e.g., 'model', 'controller', 'view')
     *
     * @return string
     */
    public function getKey(): string;

    /**
     * Parse section data from array into target object
     *
     * @param array $data Section data from JSON
     * @param mixed $target Existing object to modify, or null to create new
     * @return mixed The parsed/modified object
     */
    public function parse(array $data, mixed $target = null): mixed;

    /**
     * Serialize object back to array format
     *
     * @param mixed $source The object to serialize
     * @return array Array representation for JSON
     */
    public function serialize(mixed $source): array;

    /**
     * Validate section data structure
     *
     * @param array $data Section data to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate(array $data): array;

    /**
     * Set options for this section handler
     *
     * @param array $options Handler-specific options
     * @return self
     */
    public function setOptions(array $options): self;
}
