<?php
namespace Modules\Auth;
use MilkCore\API;
use MilkCore\Get;
use MilkCore\Route;
use MilkCore\Token;

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
        $credentials = Route::extract_credentials();
       
        
        if (!$credentials['username'] || !$credentials['password']) {
            return [
                'error' => true,
                'message' => 'Username and password required'
            ];
        }
        
        // Simulate credential verification (in production you will use your authentication system)
        if (Get::has('auth')) {
            $auth = Get::make('auth');
            if (!$auth || !$auth->login($credentials['username'], $credentials['password'], false)) {
                return [
                    'error' => true,
                    'message' => 'Invalid credentials'
                ];
            } else {
                $user = $auth->get_user();
            }
            
            // Generate the JWT token
            $token_response = API::generate_token($user->id, [
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
    }, ['method' => 'POST']);
    
    // Refresh token
    API::set('refresh', function($request) {
        // The token must be present in the Authorization header
        $token = Route::get_bearer_token();
        
        if (!$token) {
            return [
                'error' => true,
                'message' => 'Token not provided'
            ];
        }
        
        // Verify and renew the token
        $new_token = API::refresh_token();
        
        return $new_token;
    });
    
    // Verify token (test endpoint)
    API::set('verify', function($request) {
        $token = $request['input']('token') ?? Route::get_bearer_token();
        
        if (!$token) {
            return [
                'error' => true,
                'message' => 'Token not provided'
            ];
        }
        
        $payload = Token::verify_jwt($token);
        
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
    });
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
    });
    
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
    }, ['permissions' => 'users.manage']);
    
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
    });
    
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
    }, ['method' => 'POST', 'permissions' => 'users.manage']);
    
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
    }, ['method' => 'PUT', 'permissions' => 'users.manage']);
    
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
    }, ['method' => 'DELETE', 'permissions' => 'users.manage']);
});
*/