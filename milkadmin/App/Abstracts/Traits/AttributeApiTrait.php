<?php
namespace App\Abstracts\Traits;

use App\Attributes\ApiEndpoint;
use App\Attributes\ApiDoc;
use App\API;
use App\Hooks;
use ReflectionClass;
use ReflectionMethod;

!defined('MILK_DIR') && die();

/**
 * Trait for handling API endpoints with attributes
 */
trait AttributeApiTrait {

    private array $apiEndpointMap = [];

    /**
     * Set up API hooks for endpoints using attributes
     */
    public function setupAttributeApiTraitHooks() {
        // find class name of child class

        // Register immediately if we're in API context
        if (defined('MILK_API_CONTEXT')) {

            $this->registerApiEndpoints();
        }
    }

    /**
     * Register API endpoints based on method attributes
     */
    public function registerApiEndpoints() {
        $this->buildApiEndpointMap();
        // Register API endpoints based on attributes
        foreach ($this->apiEndpointMap as $endpoint) {
            $options = array_merge($endpoint['options'], [
                'method' => $endpoint['method'] ?? 'ANY'
            ]);
            Api::set($endpoint['url'], [$this, $endpoint['method_name']], $options);

            // Register documentation if available
            if ($endpoint['doc'] !== null) {
                Api::setDocumentation($endpoint['url'], $endpoint['doc']->toArray());
            }
        }
    }

    /**
     * Build the map of API endpoints from method attributes
     */
    private function buildApiEndpointMap(): void {
        if (!empty($this->apiEndpointMap)) {
            return;
        }

        $reflection = new ReflectionClass($this);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);

        $reflection = new ReflectionClass($this);
        $class_name = $reflection->getShortName();


        foreach ($methods as $method) {
            $attributes = $method->getAttributes(ApiEndpoint::class);

            foreach ($attributes as $attribute) {
                $api = $attribute->newInstance();

                // Check if this method also has ApiDoc attribute
                $docAttributes = $method->getAttributes(ApiDoc::class);
                $apiDoc = null;
                if (!empty($docAttributes)) {
                    $apiDoc = $docAttributes[0]->newInstance();
                }

                $this->apiEndpointMap[] = [
                    'url' => $api->url,
                    'method' => $api->method,
                    'method_name' => $method->getName(),
                    'options' => $api->options,
                    'doc' => $apiDoc // Store the ApiDoc instance
                ];
            }
        }
    }

    /**
     * Get documentation for a specific endpoint
     *
     * @param string $url The endpoint URL
     * @return array|null Documentation data or null if not found
     */
    public function getEndpointDocumentation(string $url): ?array {
        $this->buildApiEndpointMap();

        foreach ($this->apiEndpointMap as $endpoint) {
            if ($endpoint['url'] === $url && $endpoint['doc'] !== null) {
                return [
                    'url' => $endpoint['url'],
                    'method' => $endpoint['method'],
                    'description' => $endpoint['doc']->description,
                    'parameters' => $endpoint['doc']->parameters,
                    'response' => $endpoint['doc']->response,
                    'parameter_paths' => $endpoint['doc']->getParameterPaths(),
                    'response_paths' => $endpoint['doc']->getResponsePaths()
                ];
            }
        }

        return null;
    }

    /**
     * Get all documented endpoints
     *
     * @return array Array of endpoints with their documentation
     */
    public function getAllEndpointsDocumentation(): array {
        $this->buildApiEndpointMap();
        $documented = [];

        foreach ($this->apiEndpointMap as $endpoint) {
            $doc = [
                'url' => $endpoint['url'],
                'method' => $endpoint['method'],
                'method_name' => $endpoint['method_name'],
                'options' => $endpoint['options']
            ];

            if ($endpoint['doc'] !== null) {
                $doc['documentation'] = $endpoint['doc']->toArray();
            }

            $documented[] = $doc;
        }

        return $documented;
    }

    /**
     * Helper method to create success response
     *
     * @param mixed $data Response data
     * @param string $message Success message
     * @return array
     */
    protected function success($data = null, string $message = 'Success'): array {
        $response = ['success' => true, 'message' => $message];
        if ($data !== null) {
            $response['data'] = $data;
        }
        return $response;
    }

    /**
     * Helper method to create error response
     *
     * @param string $message Error message
     * @param mixed $errors Additional error details
     * @return array
     */
    protected function error(string $message = 'Error', $errors = null): array {
        $response = ['error' => true, 'message' => $message];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        return $response;
    }
}
