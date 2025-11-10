<?php
namespace Modules\Auth;

use App\{API, Get, Route, Token};

/**
 * Registration of API test endpoints
 * 
 * This file contains all the API endpoint registrations
 * to test the JWT authentication system and API functionality.
 */

// Public endpoints (without authentication)
API::group(['prefix' => 'auth'], function() {
    
    // Login - generates a JWT token
    API::set('login', function($request) {
        // Retrieve data from the request
        $credentials = Route::extractCredentials();
        if (!$credentials['username'] || !$credentials['password']) {
            return [
                'error' => true,
                'message' => 'Username and password required'
            ];
        }
        $auth = Get::make('Auth');

        // Simulate credential verification (in production you will use your authentication system)
        if (Get::has('Auth')) {
            $auth = Get::make('Auth');
            if (!$auth || !$auth->login($credentials['username'], $credentials['password'], false)) {
                return [
                    'error' => true,
                    'message' => 'Invalid credentials'
                ];
            } else {
                $user = $auth->getUser();
            }

            // Generate the JWT token
            $token_response = API::generateToken($user->id, [
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->is_admin ? 'admin' : 'user'
            ]);

            if ($token_response['error'] ?? false) {
                return $token_response;
            }

            return [
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'is_admin' => $user->is_admin
                    ],
                    'token' => $token_response['token'],
                    'expires_at' => $token_response['expires_at'],
                    'expires_in' => $token_response['expires_in']
                ]
            ];
        }

        return [
            'error' => true,
            'message' => 'Authentication failed'
        ];
    },
    ['method' => 'POST'],
    'Authenticates a user and generates a JWT token for accessing protected APIs.',
    [
        'body' => [
            'username' => 'string',
            'password' => 'string'
        ]
    ],
    [
        'success' => 'boolean',
        'message' => 'string',
        'data' => [
            'user' => [
                'id' => 'int',
                'username' => 'string',
                'email' => 'string',
                'is_admin' => 'boolean'
            ],
            'token' => 'string',
            'expires_at' => 'datetime',
            'expires_in' => 'int'
        ],
        'error' => 'boolean'
    ]);
    
    // Refresh token
    API::set('refresh', function($request) {
        // The token must be present in the Authorization header
        $token = Route::getBearerToken();

        if (!$token) {
            return [
                'error' => true,
                'message' => 'Token not provided'
            ];
        }

        // Verify and renew the token
        $new_token = API::refreshToken();

        return $new_token;
    },
    ['method' => 'POST'],
    'Renew an existing JWT token by generating a new one with an updated expiration date',
    [
        'headers' => [
            'Authorization' => 'string'  // Bearer {token}
        ]
    ],
    [
        'token' => 'string',
        'expires_at' => 'datetime',
        'expires_in' => 'int',
        'error' => 'boolean',
        'message' => 'string'
    ]);
    
    // Verify token (test endpoint)
    API::set('verify', function($request) {
        $token = $request['input']('token') ?? Route::getBearerToken();

        if (!$token) {
            return [
                'error' => true,
                'message' => 'Token not provided'
            ];
        }

        $payload = Token::verifyJwt($token);

        if (!$payload) {
            return [
                'error' => true,
                'message' => 'Invalid token: ' . Token::$last_error
            ];
        }

        return [
            'success' => true,
            'message' => 'Valid token',
            'payload' => $payload
        ];
    },
    ['method' => 'POST'],
    'Verifies the validity of a JWT token and returns the decoded payload',
    [
        'body' => [
            'token' => 'string'
        ],
        'headers' => [
            'Authorization' => 'string'  // Alternative: Bearer {token}
        ]
    ],
    [
        'success' => 'boolean',
        'message' => 'string',
        'payload' => [
            'user_id' => 'int',
            'username' => 'string',
            'email' => 'string',
            'role' => 'string',
            'iat' => 'int',
            'exp' => 'int',
            'jti' => 'string',
            'iss' => 'string'
        ],
        'error' => 'boolean'
    ]);
});

// Protected endpoints (require authentication)
/*
API::group(['prefix' => 'users', 'auth' => true], function() {

    // Current user profile
    API::set('profile', function($request) {
        $user = API::user();
        $payload = API::payload();

        return [
            'success' => true,
            'data' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'is_admin' => $user->is_admin
                ] : null,
                'jwt_payload' => $payload
            ]
        ];
    },
    ['method' => 'GET'],
    'Ottieni il profilo dell\'utente corrente autenticato tramite JWT',
    [
        'headers' => [
            'Authorization' => 'string'  // Bearer {token}
        ]
    ],
    [
        'success' => 'boolean',
        'data' => [
            'user' => [
                'id' => 'int',
                'username' => 'string',
                'email' => 'string',
                'is_admin' => 'boolean'
            ],
            'jwt_payload' => [
                'user_id' => 'int',
                'username' => 'string',
                'email' => 'string',
                'role' => 'string',
                'iat' => 'int',
                'exp' => 'int',
                'jti' => 'string',
                'iss' => 'string'
            ]
        ]
    ]);
    
    // Users list (requires admin permissions)
    API::set('list', function($request) {
        // Simulate user retrieval
        return [
            'success' => true,
            'data' => [
                'users' => [
                    ['id' => 1, 'username' => 'admin', 'email' => 'admin@example.com'],
                    ['id' => 2, 'username' => 'user1', 'email' => 'user1@example.com'],
                    ['id' => 3, 'username' => 'user2', 'email' => 'user2@example.com']
                ],
                'total' => 3
            ]
        ];
    },
    ['method' => 'GET', 'permissions' => 'users.manage'],
    'Ottieni la lista di tutti gli utenti del sistema (richiede permesso users.manage)',
    [
        'headers' => [
            'Authorization' => 'string'  // Bearer {token}
        ],
        'query' => [
            'page' => 'int',
            'per_page' => 'int',
            'sort_by' => 'string',
            'order' => 'string'
        ]
    ],
    [
        'success' => 'boolean',
        'data' => [
            'users' => [
                '[0]' => [
                    'id' => 'int',
                    'username' => 'string',
                    'email' => 'string'
                ]
            ],
            'total' => 'int'
        ]
    ]);
    
    // User details
    API::set('show', function($request) {
        $id = $request['input']('id');

        if (!$id) {
            return [
                'error' => true,
                'message' => 'ID utente richiesto'
            ];
        }

        // Simulate user retrieval
        return [
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $id,
                    'username' => 'user' . $id,
                    'email' => "user{$id}@example.com",
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]
        ];
    },
    ['method' => 'GET'],
    'Ottieni i dettagli di un utente specifico tramite ID',
    [
        'headers' => [
            'Authorization' => 'string'  // Bearer {token}
        ],
        'query' => [
            'id' => 'int'
        ]
    ],
    [
        'success' => 'boolean',
        'message' => 'string',
        'data' => [
            'user' => [
                'id' => 'int',
                'username' => 'string',
                'email' => 'string',
                'created_at' => 'datetime'
            ]
        ],
        'error' => 'boolean'
    ]);
    
    // Create user
    API::set('create', function($request) {
        $data = [
            'username' => $request['input']('username'),
            'email' => $request['input']('email'),
            'password' => $request['input']('password')
        ];

        // Validation
        if (!$data['username'] || !$data['email'] || !$data['password']) {
            return [
                'error' => true,
                'message' => 'All fields are required'
            ];
        }

        // Simulate creation
        return [
            'success' => true,
            'message' => 'User created successfully',
            'data' => [
                'user' => [
                    'id' => rand(100, 999),
                    'username' => $data['username'],
                    'email' => $data['email']
                ]
            ]
        ];
    },
    ['method' => 'POST', 'permissions' => 'users.manage'],
    'Crea un nuovo utente nel sistema (richiede permesso users.manage)',
    [
        'headers' => [
            'Authorization' => 'string'  // Bearer {token}
        ],
        'body' => [
            'username' => 'string',
            'email' => 'string',
            'password' => 'string'
        ]
    ],
    [
        'success' => 'boolean',
        'message' => 'string',
        'data' => [
            'user' => [
                'id' => 'int',
                'username' => 'string',
                'email' => 'string'
            ]
        ],
        'error' => 'boolean'
    ]);
    
    // Update user
    API::set('update', function($request) {
        $id = $request['input']('id');

        if (!$id) {
            return [
                'error' => true,
                'message' => 'ID utente richiesto'
            ];
        }

        $data = [];
        if ($request['has']('username')) {
            $data['username'] = $request['input']('username');
        }
        if ($request['has']('email')) {
            $data['email'] = $request['input']('email');
        }

        return [
            'success' => true,
            'message' => 'User updated successfully',
            'data' => [
                'user' => array_merge(['id' => $id], $data)
            ]
        ];
    },
    ['method' => 'PUT', 'permissions' => 'users.manage'],
    'Aggiorna i dati di un utente esistente (richiede permesso users.manage)',
    [
        'headers' => [
            'Authorization' => 'string'  // Bearer {token}
        ],
        'body' => [
            'id' => 'int',
            'username' => 'string',
            'email' => 'string'
        ]
    ],
    [
        'success' => 'boolean',
        'message' => 'string',
        'data' => [
            'user' => [
                'id' => 'int',
                'username' => 'string',
                'email' => 'string'
            ]
        ],
        'error' => 'boolean'
    ]);
    
    // Delete user
    API::set('delete', function($request) {
        $id = $request['input']('id');

        if (!$id) {
            return [
                'error' => true,
                'message' => 'ID utente richiesto'
            ];
        }

        return [
            'success' => true,
            'message' => 'User deleted successfully'
        ];
    },
    ['method' => 'DELETE', 'permissions' => 'users.manage'],
    'Elimina un utente dal sistema (richiede permesso users.manage)',
    [
        'headers' => [
            'Authorization' => 'string'  // Bearer {token}
        ],
        'body' => [
            'id' => 'int'
        ]
    ],
    [
        'success' => 'boolean',
        'message' => 'string',
        'error' => 'boolean'
    ]);
});
*/