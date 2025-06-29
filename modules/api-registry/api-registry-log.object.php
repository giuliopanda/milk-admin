<?php
namespace Modules\ApiRegistry;

use MilkCore\AbstractObject;
use MilkCore\Get;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * ApiRegistryLog Object
 * 
 * Represents a log entry for an API call, including details about the caller,
 * authentication status, response status, and timing.
 * 
 * @package     Modules\ApiRegistry
 * @subpackage  Objects
 * @author      Cascade
 * @version     1.0.0
 */
class ApiRegistryLogObject extends AbstractObject {
    
    /**
     * Initialize validation rules for the API registry log object
     */
    public function init_rules() {
        // Primary key
        $this->rule('id', [
            'type' => 'int',
            'primary' => true,
            'auto_increment' => true,
            'mysql' => true,
            'description' => 'Unique identifier for the API log entry'
        ]);
        
        // API Name
        $this->rule('api_name', [
            'type' => 'string',
            'length' => 100,
            'mysql' => true,
            'nullable' => false,
            'label' => 'API Name',
            'description' => 'Name of the API that was called'
        ]);
        
         
        // Start time of execution
        $this->rule('started_at', [
            'type' => 'datetime',
            'nullable' => true,
            'description' => 'When the API call started execution'
        ]);
        
        // End time of execution
        $this->rule('completed_at', [
            'type' => 'datetime',
            'nullable' => true,
            'description' => 'When the API call completed execution'
        ]);
        
        // Caller Information
        $this->rule('caller_ip', [
            'type' => 'string',
            'length' => 45,
            'mysql' => true,
            'nullable' => false,
            'label' => 'Caller IP',
            'description' => 'IP address of the API caller'
        ]);
        
        $this->rule('user_id', [
            'type' => 'int',
            'mysql' => true,
            'nullable' => false,
            'default' => 0,
            'label' => 'User ID',
            'description' => 'Identifier of the API caller if authenticated'
        ]);
        
        $this->rule('response_status', [
            'type' => 'enum',
            'options' => ['success', 'error', 'pending'],
            'mysql' => true,
            'nullable' => false,
            'default' => 'pending',
            'label' => 'Response Status',
            'description' => 'Status of the API response'
        ]);
        
        // Request/Response Data
        $this->rule('request_data', [
            'type' => 'array',
            'mysql' => true,
            'nullable' => true,
            'label' => 'Request Data',
            'description' => 'JSON encoded request data'
        ]);
        
        $this->rule('response_data', [
            'type' => 'array',
            'mysql' => true,
            'nullable' => true,
            'label' => 'Response Data',
            'description' => 'JSON encoded response data'
        ]);
        
    }
    
    /**
     * Format request data for display
     * 
     * @param mixed $value Raw request data
     * @return string Formatted request data
     */
    public function get_request_data($value) {
        if (empty($value)) {
            return '-';
        }
        
        $data = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return '<pre class="mb-0">'.htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)).'</pre>';
        }
        
        return htmlspecialchars($value);
    }
    
    /**
     * Format response data for display
     * 
     * @param mixed $value Raw response data
     * @return string Formatted response data
     */
    public function get_response_data($value) {
        return $this->get_request_data($value);
    }
    
    /**
     * Mark log entry as failed
     * 
     * @param string $error Error message
     */
    public function mark_failed($error = '') {
        $this->__set('response_status', 'failed');
        if (!empty($error)) {
            $this->__set('error', $error);
        }
    }
    
    /**
     * Mark log entry as successful
     * 
     * @param mixed $response_data Response data to store
     */
    public function mark_success($response_data = null) {
        $this->__set('response_status', 'success');
        if (!is_null($response_data)) {
            $this->__set('response_data', is_string($response_data) ? $response_data : json_encode($response_data));
        }
    }
    
    /**
     * Mark authentication as failed
     * 
     * @param string $error Error message
     */
    public function mark_auth_failed($error = '') {
        $this->__set('auth_status', 'failed');
        if (!empty($error)) {
            $this->__set('error', $error);
        }
    }
    
    /**
     * Mark authentication as successful
     * 
     * @param string $caller_id Optional caller ID to store
     */
    public function mark_auth_success($caller_id = '') {
        $this->__set('auth_status', 'success');
        if (!empty($caller_id)) {
            $this->__set('caller_id', $caller_id);
        }
    }
}
