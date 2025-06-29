<?php
namespace Modules\Jobs;

use MilkCore\AbstractObject;
use MilkCore\Get;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * JobsExecution Object Class
 * 
 * Represents a single execution record of a jobs.
 * This class defines the structure and validation rules for jobs execution data.
 * 
 * @package     Modules\Jobs
 */
class JobsExecutionObject extends AbstractObject {
    
    /**
     * Initialize the validation rules for the jobs execution object
     */
    public function init_rules() {
        // Primary key
        $this->rule('id', [
            'type' => 'int',
            'primary' => true,
            'auto_increment' => true,
            'description' => 'Unique identifier for the jobs execution record'
        ]);
        
        // Jobs job name (matches the name in JobsContract)
        $this->rule('jobs_name', [
            'type' => 'string',
            'length' => 100,
            'required' => true,
            'description' => 'Unique name of the jobs'
        ]);
        
        // Status of the execution
        $this->rule('status', [
            'type' => 'list',
            'options' => [
                'pending' => 'Pending',
                'running' => 'Running',
                'completed' => 'Completed',
                'failed' => 'Failed',
                'blocked' => 'Blocked'
            ],
            'default' => 'pending',
            'description' => 'Current status of the jobs execution'
        ]);
        
        // Scheduled time for execution
        $this->rule('scheduled_at', [
            'type' => 'datetime',
            'required' => true,
            'description' => 'When the jobs job is scheduled to run'
        ]);
        
        // Start time of execution
        $this->rule('started_at', [
            'type' => 'datetime',
            'nullable' => true,
            'description' => 'When the job started execution'
        ]);
        
        // End time of execution
        $this->rule('completed_at', [
            'type' => 'datetime',
            'nullable' => true,
            'description' => 'When the job completed execution'
        ]);
        
        // Duration is calculated on-demand as completed_at - started_at
        
        // Output of the job
        $this->rule('output', [
            'type' => 'text',
            'nullable' => true,
            'description' => 'Output or result from the job execution'
        ]);
        
        // Error message if failed
        $this->rule('error', [
            'type' => 'text',
            'nullable' => true,
            'description' => 'Error message if the jobs job failed'
        ]);
        
       
        /**
         * Additional metadata (JSON)
         * Metadata is saved when registering the jobs job
         * and is passed to the callback when the jobs job is executed.
         * Every time a function is registered as pending for the next execution,
         * the metadata is saved in pending status.
         */
        $this->rule('metadata', [
            'type' => 'array',
            'nullable' => true,
            'description' => 'Additional metadata about the execution'
        ]);
    }
    
    /**
     * Format the status with color coding for display
     * 
     * @return string HTML formatted status
     */
    public function get_formatted_status() {
        $status = $this->status;
        $status_classes = [
            'pending' => 'text-secondary',
            'running' => 'text-primary',
            'completed' => 'text-success',
            'failed' => 'text-danger',
            'blocked' => 'text-warning'
        ];
        
        $class = $status_classes[$status] ?? 'text-secondary';
        return '<span class="' . $class . '">' . ucfirst($status) . '</span>';
    }
    
    /**
     * Calculate and return the duration between start and completion
     * 
     * @return float|null Duration in seconds or null if not completed
     */
    public function calculate_duration() {
        if (is_a($this->started_at, \DateTime::class)) {
            $start = $this->started_at->getTimestamp();
        } else  if (is_null($this->started_at)) {
            return 0;
        } else {
            $start = strtotime($this->started_at);
        }
        if (is_a($this->completed_at, \DateTime::class)) {
            $end = $this->completed_at->getTimestamp();
        } else {
            $end = strtotime($this->completed_at);
        }
        
        return round($end - $start, 2);
    }
    
    /**
     * Mark the execution as started
     * 
     * @return void
     */
    public function mark_started() {
        $this->status = 'running';
        $this->started_at = Get::date_time_zone()->format('Y-m-d H:i:s');
    }
    
    /**
     * Mark the execution as completed
     * 
     * @param string $output Output message
     */
    public function mark_completed($output = '') {
        $this->status = 'completed';
        $this->completed_at = new \DateTime();
        $this->output = $output;
    }
    
    /**
     * Mark the execution as failed
     * 
     * @param string $error Error message
     * @return void
     */
    public function mark_failed($error = '') {
        $this->status = 'failed';
        $this->completed_at = new \DateTime();
        $this->error = $error;
    }
    
    /**
     * Mark the execution as blocked
     * 
     * @param string $reason Reason for blocking
     * @return void
     */
    public function mark_blocked($reason = '') {
        $this->status = 'blocked';
        $this->error = $reason;
    }
}
