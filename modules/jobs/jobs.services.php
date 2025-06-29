<?php
namespace Modules\Jobs;
use MilkCore\Token;
use MilkCore\Get;
use MilkCore\Config;
use MilkCore\Hooks;
use MilkCore\Mail;

!defined('MILK_DIR') && die(); // Prevent direct access

require_once __DIR__ . '/jobs-execution.object.php';
require_once __DIR__ . '/jobs-execution.model.php';
require_once __DIR__ . '/jobs.contract.php';

class JobsServices {
    public static $model = null;

    public static function set_model() {
        if (is_object(self::$model)) {
            return true;
        } 
        self::$model = new JobsExecutionModel();
        return true;
    }
    /**
     * Display the list of registered jobs.
     * Analyzes active jobs and updates the database table.
     * Does not execute jobs automatically, only displays the status.
     */
    public static function display_jobs_list() {
        self::set_model();
        // Get all registered jobs tasks directly from the JobsContract
        $jobs_tasks = JobsContract::get_tasks();
        $all_jobs_tasks = [];
        
        // Format the tasks for display
        foreach ($jobs_tasks as $name => $task) {
            $last_2_executions = self::$model->select('*')
                ->where('jobs_name = ?', [$name])
                ->order('id', 'desc')
                ->limit(0, 2)
                ->get();
           
            if (is_array($last_2_executions) && count($last_2_executions) > 1) {
                $last_execution = $last_2_executions[0];
                $preview_execution = $last_2_executions[1];
            } else if (is_array($last_2_executions) && count($last_2_executions) === 1) {
                $last_execution = $last_2_executions[0];
                $preview_execution = null;
            } else {
                $last_execution = null;
                $preview_execution = null;
            }
            
            // Get the duration of the last completed or ongoing execution
            $duration = null;
            
            // If the last job is running, calculate the duration from started_at to now
            if ($last_execution && $last_execution->status == 'running' && !empty($last_execution->started_at)) {
                $start_time = strtotime($last_execution->started_at);
                $duration = time() - $start_time;
            }
            // Otherwise, calculate the duration of the previous execution if completed or failed
            elseif ($preview_execution && ($preview_execution->status == 'completed' || $preview_execution->status == 'failed')) {
                $duration = $preview_execution->calculate_duration();
            }
            
            // Determine last_run based on the previously completed execution
            $last_run = null;
            if ($preview_execution) {
                if ($preview_execution->status == 'completed' || $preview_execution->status == 'running') {
                    $last_run = Get::format_date($preview_execution->completed_at, 'datetime');
                } else if ($preview_execution->status == 'failed') {
                   $last_run = '<span class="text-danger">' . Get::format_date($preview_execution->completed_at, 'datetime'). '<br><i class="bi bi-exclamation-circle-fill"></i> ' . $preview_execution->status . '</span>';
                }else {
                    $last_run = Get::format_date($preview_execution->scheduled_at, 'datetime') . ' (' . $preview_execution->status . ')';
                }
            } else {
                $last_run = _rt('Never run');
            }
            
            // Determine the current status and the message to display
            $next_execution_status = '';
            $status_type = 'scheduled'; // default
            $show_run_button = true;
            $show_block_button = false;
            $status_details = '';
            $next_scheduled = null;
            
            // If the job is inactive, show red "inactive" status and skip other status checks
            if (!$task['active']) {
                $next_execution_status = '<span class="text-danger"><i class="bi bi-x-circle"></i> Inactive</span>';
                $status_type = 'inactive';
                $show_run_button = false;
            } else if ($last_execution) {
                $next_scheduled = $last_execution->scheduled_at;
                
                switch ($last_execution->status) {
                    case 'running':
                        $next_execution_status = '<span class="text-primary"><i class="bi bi-play-circle-fill"></i> Running</span>';
                        $status_type = 'running';
                        $show_run_button = false;
                        $show_block_button = true;
                        break;
                        
                    case 'pending':
                        if (is_a($next_scheduled, 'DateTime')) {
                            if ($next_scheduled->getTimestamp() < time()) {
                                $next_execution_status = '<span class="text-warning"><i class="bi bi-calendar-x"></i> <span class="text-warning-emphasis">' . 
                                Get::format_date($next_scheduled, 'datetime') . '</span></span>';
                            } else {
                                $next_execution_status = Get::format_date($next_scheduled, 'datetime');
                            }
                        }
                        $status_type = 'pending';
                        break;
                        
                    case 'completed':
                        $next_execution_status = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Process Completed, next execution not scheduled</span>';
                        $status_type = 'completed';
                        break;
                        
                    case 'failed':
                        $next_execution_status = '<span class="text-danger"><i class="bi bi-exclamation-circle-fill"></i> Failed</span>';
                        $status_type = 'failed';
                        break;
                        
                    case 'blocked':
                        $next_execution_status = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Blocked</span>';
                        $status_type = 'blocked';
                        $status_details = '<br><small class="text-muted">Press "Run now" to attempt to unblock</small>';
                        break;
                }
            } else {
                $next_execution_status = '<span class="text-muted"><i class="bi bi-calendar-x"></i> Next execution not scheduled</span>';
            }
            
            // Add status details if present
            $next_execution_status .= $status_details;
            
            // Generate action buttons based on the status
            $actions = '';
            if ($show_run_button) {
                $actions .= '<div data-name="' . _r($name). '" class="link-action js-single-action jsfn-runjobs me-1"><i class="bi bi-play-fill"></i> Run</div>';
            }
            if ($show_block_button) {
                $actions .= '<div data-name="' . _r($name). '" class="link-action js-single-action btn-warning jsfn-blockjobs me-1">' .
                           '<i class="bi bi-stop-fill"></i> Stop</div>';
            }
            
            // Always add the "Block" button if there is a pending execution
            if ($last_execution && $last_execution->status === 'pending') {
                $actions .= '<div data-name="' . _r($name). '" class="link-action js-single-action text-danger jsfn-blockpendingjobs" title="Block the job by setting the pending execution as blocked">' .
                           '<i class="bi bi-x-octagon-fill"></i> Block</div>';
            }
        
            // Format the duration into a readable format
            $formatted_duration = null;
            if ($duration !== null) {
                $formatted_duration = self::format_duration($duration);
            }
            
            $all_jobs_tasks[] = [
                'name' => $task['name'],
                'schedule' => $task['schedule'],
                'schedule_description' => JobsContract::get_schedule_description($task['name']),
                'callback' => self::format_callback($task['callback']),
                'description' => $task['description'],
                'last_run' => $last_run,
                'next_scheduled' => $next_scheduled,
                'next_execution_status' => $next_execution_status,
                'status_type' => $status_type,
                'duration' => $formatted_duration,
                'actions' => $actions,
                'has_validation_error' => $task['has_validation_error'] ?? false,
                'error_message' => $task['error_message'] ?? ''
            ];
        }

        // Verify that the model is initialized
        if (!self::$model) {
            $debug_info['errors'][] = 'Model not initialized';
        }
        
        return [
            'jobs_tasks' => $all_jobs_tasks
        ];
    }

    /**
     * Format duration into readable format
     */
    public static function format_duration($duration) {
        if ($duration < 1) {
            return '< 1 sec';
        } elseif ($duration < 60) {
            return round($duration, 2) . ' sec';
        } elseif ($duration < 3600) {
            return round($duration / 60, 2) . ' min';
        } else {
            return round($duration / 3600, 2) . ' hours';
        }
    }

     /**
     * Formats a callback for display
     * 
     * @param callable $callback The callback to format
     * @return string Formatted callback string
     */
    public static function format_callback($callback) {
        if (is_string($callback)) {
            return $callback;
        }
        
        if (is_array($callback) && count($callback) === 2) {
            if (is_object($callback[0])) {
                return get_class($callback[0]) . '->' . $callback[1];
            } else {
                return $callback[0] . '::' . $callback[1];
            }
        }
        
        if (is_object($callback) && $callback instanceof \Closure) {
            return 'Closure function';
        }
        
        return 'Unknown callback type';
    }

     /**
     * Cleans job logs with a dual strategy:
     * 1. Delete records older than X days
     * 2. Keep only the last N records
     * 
     * @param int $max_records Maximum number of records to keep
     * @param int $max_days Maximum days to keep
     * @return array Result of the operation
     */
    public static function cleanup_logs($max_records = 23, $max_days = 1) {
        self::set_model();
        try {
            // First cleanup: delete old records by date
            $deleted_old = self::$model->cleanup_old_executions($max_days);
            
            // Second cleanup: keep only the last N records
            $deleted_excess = self::$model->cleanup_excess_logs($max_records);
            
           echo $deleted_old + $deleted_excess;
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @TODO da verificare
     */
    public static function check_errors_jobs() {
        self::set_model();
        $admin_email = Config::get('admin-email');
        $admin_email = Hooks::run('jobs-get-admin-email', $admin_email);
        if (!$admin_email) {
            throw new \Exception('Admin email not found');
        }
        // Get the last completed execution of this check_errors_jobs task
        $last_check = self::$model->select('*')
            ->where('jobs_name = ? AND status = ?', ['check_errors_jobs', 'completed'])
            ->order('id', 'desc')
            ->limit(0, 1)
            ->first();
        
        // Get the starting ID for our check
        $start_id = $last_check ? $last_check->id : 0;
        
        // Get all failed jobs since the last check
        $failed_jobs = self::$model->select('*')
            ->where('id > ? AND status = ?', [$start_id, 'failed'])
            ->order('id', 'asc')
            ->get();
        
        if (!empty($failed_jobs)) {
            // Format the error message
            $message = "The following jobs have failed:\n\n";
            
            foreach ($failed_jobs as $job) {
                $message .= sprintf(
                    "<br><b>Job: %s</b><br>Scheduled: %s<br>Error: %s<br><br>",
                    $job->jobs_name,
                    $job->scheduled_at,
                    $job->error
                );
            }

            $mail = new Mail();
            $mail->load_template(__DIR__.'/assets/mails-error-jobs.php', [
                'site-title' => Config::get('site-title'),
                'base_url' => Config::get('base_url'),
                'message' => $message,
            ])->to($admin_email)->send();
            if ($mail->has_error()) {
                throw new \Exception($mail->get_error());
            }
           
        }
        
        return true;
    }

}