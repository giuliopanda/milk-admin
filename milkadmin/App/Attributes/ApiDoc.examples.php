<?php
/**
 * EXAMPLES OF USING #[ApiDoc] ATTRIBUTE
 *
 * This file shows various ways to use the ApiDoc attribute to document your APIs.
 * DO NOT include this file in your actual code - it's for reference only.
 */

namespace Examples;

use App\Attributes\ApiEndpoint;
use App\Attributes\ApiDoc;
use App\Abstracts\Traits\AttributeApiTrait;

class ExampleApiController {
    use AttributeApiTrait;

    // ========================================
    // EXAMPLE 1: Simple API Documentation
    // ========================================

    #[ApiEndpoint('posts/create', 'POST', ['auth' => true])]
    #[ApiDoc(
        'Crea un nuovo post',
        ['body' => ['title' => 'string', 'content' => 'string', 'tags' => 'array']],
        ['id' => 'int', 'title' => 'string', 'content' => 'string', 'created_at' => 'datetime']
    )]
    public function createPost() {
        // Implementation...
    }

    // ========================================
    // EXAMPLE 2: Nested Parameters
    // ========================================

    #[ApiEndpoint('users/create', 'POST', ['auth' => true, 'permissions' => 'users.create'])]
    #[ApiDoc(
        'Crea un nuovo utente con profilo completo',
        [
            'body' => [
                'username' => 'string',
                'email' => 'string',
                'password' => 'string',
                'profile' => [
                    'first_name' => 'string',
                    'last_name' => 'string',
                    'age' => 'int',
                    'bio' => 'string',
                    'address' => [
                        'street' => 'string',
                        'city' => 'string',
                        'state' => 'string',
                        'zip' => 'string',
                        'country' => 'string'
                    ]
                ]
            ]
        ],
        [
            'id' => 'int',
            'username' => 'string',
            'email' => 'string',
            'profile' => [
                'first_name' => 'string',
                'last_name' => 'string',
                'full_name' => 'string'
            ],
            'created_at' => 'datetime'
        ]
    )]
    public function createUser() {
        // Implementation...
    }

    // ========================================
    // EXAMPLE 3: Multiple Parameter Types
    // ========================================

    #[ApiEndpoint('products/search', 'GET')]
    #[ApiDoc(
        'Cerca prodotti con filtri avanzati',
        [
            'query' => [
                'q' => 'string',                    // Search query
                'category_id' => 'int',              // Category filter
                'min_price' => 'float',              // Minimum price
                'max_price' => 'float',              // Maximum price
                'in_stock' => 'boolean',             // Stock filter
                'tags' => 'array',                   // Array of tags
                'sort_by' => 'string',               // Sort field
                'order' => 'string',                 // asc or desc
                'page' => 'int',
                'per_page' => 'int'
            ]
        ],
        [
            'products' => [
                '[0]' => [                           // Array notation for lists
                    'id' => 'int',
                    'name' => 'string',
                    'price' => 'float',
                    'category' => 'string',
                    'in_stock' => 'boolean',
                    'tags' => 'array'
                ]
            ],
            'pagination' => [
                'total' => 'int',
                'per_page' => 'int',
                'current_page' => 'int',
                'last_page' => 'int'
            ]
        ]
    )]
    public function searchProducts() {
        // Implementation...
    }

    // ========================================
    // EXAMPLE 4: File Upload API
    // ========================================

    #[ApiEndpoint('media/upload', 'POST', ['auth' => true])]
    #[ApiDoc(
        'Carica un file media (immagine, video, documento)',
        [
            'files' => [
                'file' => 'file'                     // File upload
            ],
            'body' => [
                'title' => 'string',
                'description' => 'string',
                'folder' => 'string',
                'is_public' => 'boolean'
            ]
        ],
        [
            'id' => 'int',
            'filename' => 'string',
            'url' => 'string',
            'size' => 'int',
            'mime_type' => 'string',
            'uploaded_at' => 'datetime'
        ]
    )]
    public function uploadMedia() {
        // Implementation...
    }

    // ========================================
    // EXAMPLE 5: Complex Nested Response
    // ========================================

    #[ApiEndpoint('orders/details', 'GET', ['auth' => true])]
    #[ApiDoc(
        'Ottieni i dettagli completi di un ordine',
        [
            'query' => [
                'order_id' => 'int'
            ]
        ],
        [
            'order' => [
                'id' => 'int',
                'order_number' => 'string',
                'status' => 'string',
                'total' => 'float',
                'customer' => [
                    'id' => 'int',
                    'name' => 'string',
                    'email' => 'string'
                ],
                'items' => [
                    '[0]' => [                       // Array of items
                        'id' => 'int',
                        'product_id' => 'int',
                        'name' => 'string',
                        'quantity' => 'int',
                        'price' => 'float',
                        'subtotal' => 'float'
                    ]
                ],
                'shipping_address' => [
                    'street' => 'string',
                    'city' => 'string',
                    'state' => 'string',
                    'zip' => 'string'
                ],
                'payment' => [
                    'method' => 'string',
                    'status' => 'string',
                    'paid_at' => 'datetime'
                ],
                'created_at' => 'datetime',
                'updated_at' => 'datetime'
            ]
        ]
    )]
    public function getOrderDetails() {
        // Implementation...
    }

    // ========================================
    // EXAMPLE 6: Authentication API
    // ========================================

    #[ApiEndpoint('auth/login', 'POST')]
    #[ApiDoc(
        'Autentica un utente e restituisce un token JWT',
        [
            'body' => [
                'username' => 'string',              // Username or email
                'password' => 'string',              // Plain password
                'remember_me' => 'boolean'           // Optional
            ]
        ],
        [
            'success' => [
                'token' => 'string',                 // JWT token
                'expires_at' => 'datetime',
                'user' => [
                    'id' => 'int',
                    'username' => 'string',
                    'email' => 'string',
                    'roles' => 'array'
                ]
            ],
            'error' => [
                'message' => 'string',
                'code' => 'string'
            ]
        ]
    )]
    public function login() {
        // Implementation...
    }

    // ========================================
    // EXAMPLE 7: Batch Operations
    // ========================================

    #[ApiEndpoint('posts/batch-update', 'PUT', ['auth' => true])]
    #[ApiDoc(
        'Aggiorna più post in una singola richiesta',
        [
            'body' => [
                'posts' => [
                    '[0]' => [                       // Array of posts
                        'id' => 'int',
                        'title' => 'string',
                        'status' => 'string',
                        'tags' => 'array'
                    ]
                ]
            ]
        ],
        [
            'updated' => 'int',                      // Number of updated posts
            'failed' => 'int',                       // Number of failed updates
            'results' => [
                '[0]' => [
                    'id' => 'int',
                    'success' => 'boolean',
                    'message' => 'string'
                ]
            ]
        ]
    )]
    public function batchUpdatePosts() {
        // Implementation...
    }

    // ========================================
    // EXAMPLE 8: Public API (No Auth)
    // ========================================

    #[ApiEndpoint('public/stats', 'GET')]
    #[ApiDoc(
        'Ottieni statistiche pubbliche del sito',
        [],                                          // No parameters
        [
            'stats' => [
                'total_users' => 'int',
                'total_posts' => 'int',
                'total_comments' => 'int',
                'active_users_today' => 'int'
            ],
            'last_updated' => 'datetime'
        ]
    )]
    public function getPublicStats() {
        // Implementation...
    }

    // ========================================
    // HOW TO ACCESS DOCUMENTATION
    // ========================================

    public function exampleUsage() {
        // Get documentation for a specific endpoint
        $doc = $this->getEndpointDocumentation('posts/create');

        /* Returns:
        [
            'url' => 'posts/create',
            'method' => 'POST',
            'description' => 'Crea un nuovo post',
            'parameters' => ['body' => ['title' => 'string', ...]],
            'response' => ['id' => 'int', ...],
            'parameter_paths' => ['body.title' => 'string', 'body.content' => 'string', ...],
            'response_paths' => ['id' => 'int', 'title' => 'string', ...]
        ]
        */

        // Get all documented endpoints
        $allDocs = $this->getAllEndpointsDocumentation();

        // Via API class
        $doc = \App\API::getDocumentation('posts/create');
        $allDocs = \App\API::getAllDocumentation();

        // Check if endpoint has documentation
        $hasDocs = \App\API::hasDocumentation('posts/create');

        // List all endpoints (includes documentation if available)
        $endpoints = \App\API::listEndpoints();
        /* Returns:
        [
            [
                'page' => 'posts/create',
                'method' => 'POST',
                'auth' => true,
                'permissions' => null,
                'documentation' => [
                    'description' => '...',
                    'parameters' => [...],
                    'response' => [...]
                ]
            ],
            ...
        ]
        */
    }
}

// ========================================
// ACCESSING PARAMETER PATHS
// ========================================

/*
When you document nested parameters like:

[
    'body' => [
        'user' => [
            'name' => 'string',
            'email' => 'string'
        ]
    ]
]

The getParameterPaths() method flattens them to:
[
    'body.user.name' => 'string',
    'body.user.email' => 'string'
]

This makes it easy to:
- Validate incoming parameters
- Generate forms automatically
- Create API documentation UI
- Build API testing tools
*/

// ========================================
// TYPE CONVENTIONS
// ========================================

/*
You can use any string to describe types. Common conventions:

Primitive types:
- 'string', 'int', 'float', 'boolean', 'array', 'object', 'null'

Special types:
- 'datetime' - ISO 8601 datetime
- 'date' - Date only
- 'time' - Time only
- 'email' - Email address
- 'url' - URL string
- 'uuid' - UUID string
- 'file' - File upload
- 'json' - JSON string

Array notations:
- 'array' - Simple array
- '[0]' => [...] - Array of objects (use [0] as key)
- 'array<string>' - Array of strings
- 'array<int>' - Array of integers

Custom types:
- 'User' - Custom object
- 'PostStatus' - Enum values
- 'string|null' - Nullable string
- 'int|string' - Union type

You have complete freedom in documenting types!
*/
