<?php
namespace Modules\Auth;
use MilkCore\AbstractObject;

/**
 * User object class for auth module
 * Maps to #__users table
 */
class UserObject extends AbstractObject {
    /**
     * Initialize rules for user object based on database schema
     */
    public function init_rules() {
        $this->rule('id', [
            'type' => 'int',
            'primary' => true,
            'mysql' => true
        ]);
        
        $this->rule('username', [
            'type' => 'string',
            'length' => 255,
            'mysql' => true,
            'nullable' => false,
            'default' => '',
            'label' => 'Username'
        ]);
        
        $this->rule('email', [
            'type' => 'string',
            'length' => 255,
            'mysql' => true,
            'nullable' => false,
            'default' => '',
            'label' => 'Email',
            'form' => 'email'
        ]);
        
        $this->rule('password', [
            'type' => 'string',
            'length' => 255,
            'mysql' => true,
            'nullable' => false,
            'default' => '',
            'label' => 'Password',
            'form' => 'password'
        ]);
        
        $this->rule('registered', [
            'type' => 'datetime',
            'mysql' => true,
            'nullable' => true,
            'default' => null,
            'label' => 'Registration Date'
        ]);
        
        $this->rule('last_login', [
            'type' => 'datetime',
            'mysql' => true,
            'nullable' => true,
            'label' => 'Last Login'
        ]);
        
        $this->rule('activation_key', [
            'type' => 'string',
            'length' => 255,
            'mysql' => true,
            'nullable' => false,
            'default' => '',
            'label' => 'Activation Key'
        ]);
        
        $this->rule('status', [
            'type' => 'int',
            'mysql' => true,
            'nullable' => false,
            'default' => 0,
            'label' => 'Status',
            'form' => 'list',
            'options' => [
                0 => 'Inactive',
                1 => 'Active'
            ]
        ]);
        
        $this->rule('is_admin', [
            'type' => 'bool',
            'mysql' => true,
            'nullable' => false,
            'default' => 0,
            'label' => 'Administrator'
        ]);
        
        $this->rule('permissions', [
            'type' => 'array',
            'mysql' => true,
            'nullable' => false,
            'default' => '{}',
            'label' => 'Permissions',
            '_set' => function($value) {
                if (is_array($value)) {
                    return json_encode($value);
                }
                return $value;
            },
            '_get_raw' => function($value) {
                return json_decode($value, true);
            }
        ]);
    }
}

/**
 * Session object class for auth module
 * Maps to #__sessions table
 */
class SessionObject extends AbstractObject {
    /**
     * Initialize rules for session object based on database schema
     */
    public function init_rules() {
        $this->rule('id', [
            'type' => 'int',
            'primary' => true,
        ]);
        
        $this->rule('phpsessid', [
            'type' => 'string',
            'length' => 64,
            'nullable' => false,
            'label' => 'PHP Session ID'
        ]);

        $this->rule('old_phpsessid', [
            'type' => 'string',
            'length' => 64,
            'nullable' => true,
            'label' => 'Old PHP Session ID'
        ]);
        
        $this->rule('ip_address', [
            'type' => 'string',
            'length' => 64,
            'nullable' => false,
            'label' => 'IP Address'
        ]);

        $this->rule('user_agent', [
            'type' => 'string',
            'length' => 255,
            'nullable' => true,
            'label' => 'User Agent'
        ]);
        
        $this->rule('session_date', [
            'type' => 'datetime',
            'nullable' => false,
            'label' => 'Session Date'
        ]);
        
        $this->rule('user_id', [
            'type' => 'int',
            'nullable' => false,
            'label' => 'User ID'
        ]);
        
        $this->rule('secret_key', [
            'type' => 'string',
            'length' => 64,
            'nullable' => false,
            'label' => 'Secret Key'
        ]);
    }
}

/**
 * Login attempts object class for auth module
 * Maps to #__login_attempts table
 */
class LoginAttemptsObject extends AbstractObject {
    /**
     * Initialize rules for login attempts object based on database schema
     */
    public function init_rules() {
        $this->rule('id', [
            'type' => 'int',
            'primary' => true,
        ]);
        
        $this->rule('username_email', [
            'type' => 'string',
            'length' => 255,
            'nullable' => false,
            'label' => 'Username/Email'
        ]);
        
        $this->rule('ip_address', [
            'type' => 'string',
            'length' => 64,
            'nullable' => false,
            'label' => 'IP Address'
        ]);
        
        $this->rule('session_id', [
            'type' => 'string',
            'length' => 128,
            'nullable' => false,
            'label' => 'Session ID'
        ]);
        
        $this->rule('attempt_time', [
            'type' => 'datetime',
            'nullable' => false,
            'label' => 'Attempt Time'
        ]);
    }
}
