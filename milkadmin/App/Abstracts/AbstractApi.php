<?php
namespace App\Abstracts;

use App\{API, Hooks};
use App\Abstracts\Traits\AttributeApiTrait;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Abstract API Class
 *
 * This class serves as the base for API endpoint management in the framework.
 * It provides a standardized structure for defining API endpoints using attributes.
 *
 * @example
 * ```php
 * class PostsApi extends \App\Abstracts\AbstractApi {
 *     #[ApiEndpoint('posts/list', 'GET')]
 *     public function getPostList($request) {
 *         return ['posts' => $this->model->get()];
 *     }
 *
 *     #[ApiEndpoint('posts/create', 'POST', ['auth' => true, 'permissions' => 'posts.create'])]
 *     public function createPost($request) {
 *         $data = $request['body'];
 *         return ['post' => $this->model->store($data)];
 *     }
 * }
 * ```
 *
 * @package     App
 * @subpackage  Abstracts
 */

#[\AllowDynamicProperties]
abstract class AbstractApi {
    
    use AttributeApiTrait;

    /**
     * The module instance that owns this API
     * @var object|null
     */
    protected $module = null;

    /**
     * The page/module name for API prefixing
     * @var string|null
     */
    protected $page = null;

    /**
     * The model instance for data operations
     * @var object|null
     */
    protected $model = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->setupAttributeApiTraitHooks();
    }

    /**
     * Set the module that owns this API
     * 
     * @param object $module The module instance
     * @return void
     */
    public function setHandleApi($module): void {
        $this->module = $module;
        $this->page = $module->getPage();
        $this->model = $module->getModel();
    }

    /**
     * Get the page/module name for this API
     * 
     * @return string|null
     */
    public function getPage(): ?string {
        return $this->page;
    }

    /**
     * Get the module instance
     * 
     * @return object|null
     */
    public function getModule(): ?object {
        return $this->module;
    }

    /**
     * Get the model instance
     * 
     * @return object|null
     */
    public function getModel(): ?object {
        return $this->model;
    }

    /**
     * Helper method to get authenticated user from request
     * 
     * @return object|null
     */
    protected function getUser() {
        return API::user();
    }

    /**
     * Helper method to get JWT payload from request
     * 
     * @return array|null
     */
    protected function getPayload() {
        return API::payload();
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