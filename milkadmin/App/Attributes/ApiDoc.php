<?php
namespace App\Attributes;

!defined('MILK_DIR') && die();

/**
 * ApiDoc attribute for documenting API endpoints
 *
 * This attribute works together with ApiEndpoint to provide comprehensive API documentation.
 * It allows flexible documentation structure with nested parameters.
 *
 * @example
 * ```php
 * #[ApiEndpoint('posts/create', 'POST', ['auth' => true])]
 * #[ApiDoc(
 *     'Crea un nuovo post',
 *     ['body' => ['title' => 'string', 'content' => 'string', 'tags' => 'array']],
 *     ['id' => 'int', 'title' => 'string', 'content' => 'string', 'created_at' => 'datetime']
 * )]
 * public function createPost() {
 *     // Handle post creation
 * }
 *
 * // Example with nested parameters
 * #[ApiDoc(
 *     'Crea un utente completo',
 *     [
 *         'body' => [
 *             'username' => 'string',
 *             'email' => 'string',
 *             'profile' => [
 *                 'first_name' => 'string',
 *                 'last_name' => 'string',
 *                 'age' => 'int',
 *                 'address' => [
 *                     'street' => 'string',
 *                     'city' => 'string',
 *                     'zip' => 'string'
 *                 ]
 *             ]
 *         ]
 *     ],
 *     [
 *         'id' => 'int',
 *         'username' => 'string',
 *         'created_at' => 'datetime'
 *     ]
 * )]
 * ```
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class ApiDoc {

    /**
     * @param string $description Brief description of what the API does
     * @param array $parameters Array describing the required parameters (can be nested)
     * @param array $response Array describing what the API returns (can be nested)
     */
    public function __construct(
        public string $description,
        public array $parameters = [],
        public array $response = []
    ) {}

    /**
     * Get a flattened list of parameter paths for easy validation
     *
     * @return array Array of parameter paths (e.g., ['body.title', 'body.content', 'body.tags'])
     */
    public function getParameterPaths(): array {
        return $this->flattenArray($this->parameters);
    }

    /**
     * Get a flattened list of response paths
     *
     * @return array Array of response paths
     */
    public function getResponsePaths(): array {
        return $this->flattenArray($this->response);
    }

    /**
     * Flatten nested array to dot notation paths
     *
     * @param array $array Array to flatten
     * @param string $prefix Current path prefix
     * @return array Flattened paths with their types
     */
    private function flattenArray(array $array, string $prefix = ''): array {
        $result = [];

        foreach ($array as $key => $value) {
            $path = $prefix ? "$prefix.$key" : $key;

            if (is_array($value)) {
                // Nested structure - recurse
                $result = array_merge($result, $this->flattenArray($value, $path));
            } else {
                // Leaf node - this is a type definition
                $result[$path] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if a parameter exists in the documentation
     *
     * @param string $path Dot notation path (e.g., 'body.title')
     * @return bool
     */
    public function hasParameter(string $path): bool {
        return array_key_exists($path, $this->getParameterPaths());
    }

    /**
     * Get parameter type by path
     *
     * @param string $path Dot notation path
     * @return string|null Type of the parameter or null if not found
     */
    public function getParameterType(string $path): ?string {
        $paths = $this->getParameterPaths();
        return $paths[$path] ?? null;
    }

    /**
     * Convert to array for serialization
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'description' => $this->description,
            'parameters' => $this->parameters,
            'response' => $this->response,
            'parameter_paths' => $this->getParameterPaths(),
            'response_paths' => $this->getResponsePaths()
        ];
    }
}
