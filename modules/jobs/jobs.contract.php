<?php
namespace Modules\Jobs;

use MilkCore\Get;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * JobsContract Class
 * 
 * A class to manage jobs with unique names and schedules.
 * Supports both standard cron expressions and predefined intervals.
 * 
 * @package     Modules\Jobs
 */

class JobsContract {
    /**
     * @var array Stores all registered jobs in tasks
    */
    private static $tasks = [];

    /**
     * @var string Last error that occurred
    */
    public static $last_error = '';
    
    /**
     * @var array Schedule expressions translated to seconds
    */
    private static $schedule_intervals = [
        'minutely' => 60,          // Every minute
        'hourly' => 3600,          // Every hour
        'daily' => 86400,          // Every day
        'weekly' => 604800,        // Every week
        'monthly' => 2592000,      // Every 30 days (approximate)
        'yearly' => 31536000,      // Every 365 days (approximate)
    ];

    protected static $model = null;

    /**
     * Set the model for database management
    * 
    * @param mixed $model Model for execution management
    * @return bool True if set correctly, false otherwise
    */
    public static function set_model() {
        self::$last_error = '';
        
        if (!is_object(self::$model)) {
            self::$model = new JobsExecutionModel();
        } else {
            return true;
        }
        return true;
    }
    
    /**
     * Register a new job
    * 
    * @param string $name         Unique name for the job
    * @param mixed $callback      Callable function or method to execute
    * @param string|CronDateManager $schedule  How often to run the job (e.g. 'hourly', cron expression or CronDateManager instance)
    * @param string $description  Optional description of what the job does
    * @param bool $active         Whether the job is active or not
    * @param array $metadata      Optional metadata to pass to each execution
    * @return bool                True if the job was registered, false if the name already exists
    */
    public static function register($name, $callback, $schedule, $description = '', $active = true, $metadata = []) {
        self::$last_error = '';
        self::set_model();
        $has_validation_error = false;
        
        // Name validation
        if (empty($name)) {
            self::$last_error = 'Job name cannot be empty';
            return false;
        }
        
        // Check if a job with this name already exists
        if (isset(self::$tasks[$name])) {
            self::$last_error = "A job with name '{$name}' already exists";
            return false;
        }
        
        // Validate that the callback is callable
        if (!is_callable($callback)) {
            self::$last_error = 'The provided callback is not callable';
            return false;
        }
        
        // Convert CronDateManager instance to cron string if necessary
        $cron_schedule = $schedule;
        if ($schedule instanceof CronDateManager) {
            try {
                $cron_schedule = $schedule->to_cron_string(true); // Include year
            } catch (\Exception $e) {
                self::$last_error = 'Error converting schedule: ' . $e->getMessage();
                $has_validation_error = true;
                // Continue with registration despite the error
                $cron_schedule = '* * * * *'; // Default to every minute as fallback
            }
        }

        // Validate schedule
        if (empty($cron_schedule)) {
            self::$last_error = 'Schedule cannot be empty';
            $has_validation_error = true;
            // Continue with registration despite the error
            $cron_schedule = '* * * * *'; // Default to every minute as fallback
        } else {
            // Try to validate the cron schedule without throwing exceptions
            try {
                if (!in_array($cron_schedule, array_keys(self::$schedule_intervals))) {
                    CronDateManager::validate_cron_string($cron_schedule);
                }
            } catch (\InvalidArgumentException $e) {
                self::$last_error = 'Invalid schedule: ' . $e->getMessage();
                $has_validation_error = true;
                // Continue with registration despite the error
                $cron_schedule = '* * * * *'; // Default to every minute as fallback
            }
        }

         // Verifica che il cron sia scritto bene
         try {
            $cron_manager = new CronDateManager($cron_schedule);
            CronDescriptionHelper::get_description_from_manager($cron_manager);
        } catch (\Exception $e) {
            self::$last_error = 'Error parsing schedule: ' . $e->getMessage();
            $has_validation_error = true;
        }
        
        // Validate metadata is an array
        if (!is_array($metadata)) {
            self::$last_error = 'Metadata must be an array';
            $has_validation_error = true;
        }
        
        // Store the job - even with validation error, we'll still register it
        self::$tasks[$name] = [
            'name' => $name,
            'callback' => $callback,
            'schedule' => $cron_schedule,
            'description' => $description,
            'active' => $active,
            'metadata' => $metadata,
            'last_run' => null,
            'last_result' => null,
            'registered_at' => date('Y-m-d H:i:s'),
            'has_validation_error' => $has_validation_error,
            'error_message' => self::$last_error,
        ];
        self::reconcile_jobs_in_database($name);
      
        return true;
    }
    
    /**
     * Unregister a job by name
     * 
     * @param string $name Name of the job to remove
     * @return bool True if the job was removed, false if not found
     */
    public static function unregister($name) {
        self::$last_error = '';
        
        if (empty($name)) {
            self::$last_error = 'Job name cannot be empty';
            return false;
        }
        
        if (!isset(self::$tasks[$name])) {
            self::$last_error = "Job '{$name}' not found";
            return false;
        }
        
        unset(self::$tasks[$name]);
        return true;
    }
    
    /**
     * Execute a specific job immediately
     * 
     * @param string $name Name of the job to execute
     * @return mixed Result of job execution or false if not found/inactive
     */
    public static function run($name) {
        self::set_model();
        self::$last_error = '';
        
        if (empty($name)) {
            self::$last_error = 'Job name cannot be empty';
            return false;
        }
        
        if (!isset(self::$tasks[$name])) {
            self::$last_error = "Job '{$name}' not found";
            return false;
        }
        
        if (!self::$tasks[$name]['active']) {
            self::$last_error = "Job '{$name}' is not active";
            return false;
        }

        if (self::$tasks[$name]['has_validation_error']) {
            self::$last_error = "Job '{$name}' has validation errors";
            return false;
        }

        if (self::$tasks[$name]['active'] == false) {
            self::$last_error = "Job '{$name}' is not active";
            return false;
        }
        
        $task = self::$tasks[$name];
        $started = new \DateTime();
        $error = '';
        $result = false;
        $output = '';
        
        // trovo l'ultima riga di log
        $last_log = self::$model->get_latest_executions($name);
        if ($last_log && $last_log->status == 'running') {
            self::$last_error = "Job '{$name}' is already running";
            return false;
        } 
        // Verify that the callback is still callable
        if (!is_callable($task['callback'])) {
            self::$last_error = 'Job callback is no longer callable';
            $last_log->status = 'failed';
            $last_log->end = new \DateTime();
            $last_log->error = self::$last_error;
            self::$model->save($last_log);
            return false;
        }
        

        if ($last_log && $last_log->status == 'pending' ) {
            $last_log->status = 'running';
            $last_log->started_at = $started;
            self::$model->save($last_log->to_mysql_array(), $last_log->id);
        } else {
            $last_log = new JobsExecutionObject([
                'jobs_name' => $name,
                'status' => 'running',
                'scheduled_at' => $started,
                'metadata' => $task['metadata']
            ]);
            self::$model->save($last_log->to_mysql_array());
        }
      
        // Execute the callback capturing output
        ob_start();
        try {
            $result = call_user_func($task['callback'], $task['metadata']);
        } catch (\Exception $e) {
            $error = 'Exception during execution: ' . $e->getMessage();
            $result = false;
        } catch (\Error $e) {
            $error = 'Fatal error during execution: ' . $e->getMessage();
            $result = false;
        }
        $output = ob_get_clean();
        

        $to_save = self::$model->get_latest_executions($name);
        // Save current execution
        if ($to_save) {
            $to_save->status = $result ? 'completed' : 'failed';
            $to_save->completed_at = new \DateTime();
            $to_save->started_at = $started;
            $to_save->output = $output;
            
            if (!$result && !empty($error)) {
                $to_save->error = $error;
            }
            
            $id = $to_save->id;
            unset($to_save->id);
            
            try {
                $save_result = self::$model->save($to_save->to_mysql_array(), $id);
                if (!$save_result) {
                    self::$last_error = 'Error saving job execution';
                    return false;
                }
            } catch (\Exception $e) {
                self::$last_error = 'Exception saving execution: ' . $e->getMessage();
                return false;
            }
        }
        
        // Schedule next execution
        self::reconcile_jobs_in_database($name);
      
        // If there was an error during callback execution, set it now
        if (!$result && !empty($error)) {
            self::$last_error = $error;
        }
        
        return $result;
    }
    
    /**
     * Calculate next execution time based on schedule
    * 
    * @param string $schedule Schedule expression
    * @return string|false Formatted datetime string of next execution or false on error
    */
    public static function calculate_next_run($schedule) {
        self::$last_error = '';
        
        if (empty($schedule)) {
            self::$last_error = 'Schedule is empty';
            return false;
        }
        
        $now = new \DateTime();
        
        // Handle predefined intervals
        if (isset(self::$schedule_intervals[$schedule])) {
            $interval_seconds = self::$schedule_intervals[$schedule];
            $now->modify("+{$interval_seconds} seconds");
            return $now->format('Y-m-d H:i:s');
        }
        
        // Use CronDateManager to parse custom cron expressions
        try {
            $cron_manager = new CronDateManager($schedule);
            $next_run_timestamp = $cron_manager->get_next_run_time(time());
            return date('Y-m-d H:i:s', $next_run_timestamp);
        } catch (\Exception $e) {
            self::$last_error = 'Invalid cron expression: ' . $e->getMessage();
            // Fallback to 1 hour
            $now->modify("+1 hour");
            return $now->format('Y-m-d H:i:s');
        }
    }
    
    /**
     * Create a new CronDateManager instance for fluent configuration
    * 
    * @return CronDateManager CronDateManager instance for chainable configuration
    */
    public static function create_scheduler() {
        self::$last_error = '';
        return new CronDateManager();
    }

    /**
     * Get all registered jobs
    * 
    * @return array Array of all jobs
    */
    public static function get_tasks() {
        self::$last_error = '';
        return self::$tasks;
    }

    /**
     * Get a specific job by name
    * 
    * @param string $name Job name
    * @return array|null Job details or null if not found
    */
    public static function get_task($name) {
        self::$last_error = '';
        
        if (empty($name)) {
            self::$last_error = 'Job name cannot be empty';
            return null;
        }
        
        if (!isset(self::$tasks[$name])) {
            self::$last_error = "Job '{$name}' not found";
            return null;
        }
        
        return self::$tasks[$name];
    }

    
    /**
     * Save a new execution for a job if needed
    * 
    * @param string $name Job name
    * @return void
    */
    public static function reconcile_jobs_in_database($name) {
        self::$last_error = '';
        self::set_model();
        
        if (empty($name)) {
            self::$last_error = 'Job name cannot be empty';
            return;
        }
        
        if (!isset(self::$tasks[$name])) {
            self::$last_error = "Job '{$name}' not found in registered tasks";
            return;
        }
        
        $task = self::$tasks[$name];
        $latest_executions = self::$model->get_latest_executions($name);

        // Prevent scheduling new executions if the job's schedule is marked as invalid
        if (!empty($task['has_validation_error'])) {
            self::$last_error = "Job '{$name}' has an invalid schedule. No new executions will be scheduled.";
            // se latest_execution è in pending , cancello
            if ($latest_executions && in_array( $latest_executions->status, ['pending'])) {
                self::$model->delete($latest_executions->id);
            }
            return; 
        }
        
        if ( $task['active'] == false) {
            self::$last_error = "Job '{$name}' is not active. No new executions will be scheduled.";
            // se latest_execution è in pending , cancello
            if ($latest_executions && in_array( $latest_executions->status, ['pending'])) {
                self::$model->delete($latest_executions->id);
            }
            return; 
        }

        // Get all not yet executed executions for this job
       
        $should_block = self::$model->should_block_job($name);
        if ($should_block) {
            if ($latest_executions && in_array( $latest_executions->status, ['running', 'pending', 'blocked'])) {
                self::$model->block_job_executions($name, 'Blocked due to 3 consecutive failures');
                return;
            } else {
                $next_scheduled_time = self::calculate_next_run($task['schedule']);
                if ($next_scheduled_time === false) {
                    self::$last_error = "Job '{$name}': Failed to calculate next run time for blocking. Schedule: " . $task['schedule'];
                    // Optionally, consider marking the task with a validation error here if not already set
                    return;
                }
                $metadata = $task['metadata'] ?? [];
                self::$model->schedule_execution($name, $next_scheduled_time, $metadata, 'blocked');
                return;
            }
        } else {
            if ($latest_executions) {
                if ($latest_executions->status == 'running') {
                    self::$last_error = "Job '{$name}' is already running";
                    return;
                } else if ($latest_executions->status == 'pending') {
                    return; 
                } else if ($latest_executions->status == 'blocked') {
                    return;
                }
            }
            // create a new job
            $next_scheduled_time = self::calculate_next_run($task['schedule']);
            if ($next_scheduled_time === false) {
                self::$last_error = "Job '{$name}': Failed to calculate next run time. Schedule: " . $task['schedule'];
                // Optionally, consider marking the task with a validation error here if not already set
                return;
            }
            $metadata = $task['metadata'] ?? [];
            self::$model->schedule_execution($name, $next_scheduled_time, $metadata);
            return;
        }
    }

    /**
     * Returns a human-readable description from CronDateManager instance
     * 
     * @param string $task_name
     * @return string Description of when the job will run
     */
    public static function get_schedule_description($task_name): string
    {
        $task = self::get_task($task_name);
        
        if (isset($task['has_validation_error']) && $task['has_validation_error']) {
            return (isset($task['error_message']) ? $task['error_message'] : 'Invalid schedule');
        }
        
        try {
            $cron_manager = new CronDateManager($task['schedule']);
            return CronDescriptionHelper::get_description_from_manager($cron_manager);
        } catch (\Exception $e) {
            return 'Error parsing schedule: ' . $e->getMessage();
        }
    }
    
}

// Register the class so it's accessible via Get::service()
Get::bind('jobs_contract', JobsContract::class);

/*Usage examples:
<?php
// Example 1: Registering a job with a standard cron expression
// Run every Monday at 8:00 AM
JobsContract::register(
    'backup_settimanale', 
    function() { 
        // Logic to perform backup
        return true; 
    },
    '0 8 * * 1',
    'Performs a weekly database backup',
    true
);

// Example 2: Using the fluent API with CronDateManager
$jobs_manager = JobsContract::create_scheduler();
$jobs_manager->set_minutes('0')
             ->set_hours('12')
             ->set_day_of_month('1,15')
             ->set_month('*')
             ->set_day_of_week('*');

JobsContract::register(
    'report_bisettimanale',
    function() {
        // Generate and send report
        return true;
    },
    $jobs_manager,
    'Generates and sends a report on the 1st and 15th of each month at 12:00 PM',
    true
);

// Example 3: Using month and day names
$jobs_manager = JobsContract::create_scheduler();
$jobs_manager->set_minutes('30')
             ->set_hours('9')
             ->set_day_of_month('*')
             ->set_month('jan,apr,jul,oct')
             ->set_day_of_week('mon-fri');

JobsContract::register(
    'report_trimestrale',
    function() {
        // Logic to generate quarterly report
        return true;
    },
    $jobs_manager,
    'Generates a quarterly report on weekdays in January, April, July, and October at 9:30 AM',
    true
);

// Example 4: Using predefined intervals
JobsContract::register(
    'pulizia_cache', 
    function() { 
        // Logic to clear cache
        return true; 
    },
    'hourly',
    'Clears the cache hourly',
    true
);

// Example 5: Using steps/intervals
$jobs_manager = JobsContract::create_scheduler();
$jobs_manager->set_minutes('*\/15')
->set_hours('*')
->set_day_of_month('*')
->set_month('*')
->set_day_of_week('*');

JobsContract::register(
'controlla_connessione',
function() {
// Check connection status
return true;
},
$jobs_manager,
'Checks connection status every 15 minutes',
true
);

// Example 6: Check if a cron expression matches the current time
$is_time_to_run = JobsContract::schedule_matches('0 3 * * *'); // Every day at 3:00 AM

// Example 7: Manual execution of a job
JobsContract::run('backup_settimanale');

// Example 8: Execution of all due jobs
$results = JobsContract::run_due();
print_r($results);


*/