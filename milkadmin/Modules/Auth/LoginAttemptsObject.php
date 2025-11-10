<?php
namespace Modules\Auth;

use App\Abstracts\AbstractObject;

/**
 * Login attempts object class for auth module
 * Maps to #__login_attempts table
 */
class LoginAttemptsObject extends AbstractObject {
    /**
     * Initialize rules for login attempts object based on database schema
     */
    public function initRules() {
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
