<?php
namespace App\Attributes;

!defined('MILK_DIR') && die();

/**
 * ApiEndpoint attribute for marking methods as API endpoints
 *
 * @example
 * ```php
 * #[ApiEndpoint('users/list', 'GET')]
 * public function getUserList() {
 *     return ['users' => $this->model->get()];
 * }
 * 
 * #[ApiEndpoint('users/create', 'POST', ['auth' => true, 'permissions' => 'users.create'])]
 * public function createUser() {
 *     // Handle user creation
 * }
 * ```
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class ApiEndpoint {
    
    public function __construct(
        public string $url,
        public string $method = 'ANY',
        public array $options = []
    ) {
        $this->method = strtoupper($this->method);
    }
}