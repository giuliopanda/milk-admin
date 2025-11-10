<?php
namespace Modules\Auth;

use App\Abstracts\AbstractObject;

/**
 * User object class for auth module
 * Maps to #__users table
 */
class UserObject extends AbstractObject {
    /**
     * Initialize rules for user object based on database schema
     */
    public function initRules() {
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
