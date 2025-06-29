<?php
namespace Modules\Jobs;

use MilkCore\AbstractModel;
use MilkCore\Get;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * JobsExecution Model Class
 * 
 * Handles database operations for jobs execution records.
 * This model provides methods for creating, retrieving, updating, and deleting
 * jobs execution records, as well as specialized queries for jobs management.
 * 
 * @package     Modules\Jobs
 */
class JobsExecutionModel extends AbstractModel {
    /**
     * Database table name
     */
    protected string $table = '#__jobs_executions';
    
    /**
     * Primary key field name
     */
    protected string $primary_key = 'id';
    
    /**
     * Associated object class
     */
    protected string $object_class = 'Modules\\Jobs\\JobsExecutionObject';
    
    /**
     * Get the table name
     * 
     * @return string The table name with prefix
     */
    public function get_table_name() {
        return $this->table;
    }
    
    /**
     * Create a new execution record for a scheduled jobs
     * 
     * @param string $jobs_name Name of the jobs
     * @param string $scheduled_at When the job is scheduled to run
     * @param array $metadata Additional metadata for the execution
     * @return JobsExecutionObject|false
     */
    public function schedule_execution($jobs_name, $scheduled_at, $metadata = [], $status = 'pending') {
        $execution = new JobsExecutionObject([
            'jobs_name' => $jobs_name,
            'status' => $status,
            'scheduled_at' => $scheduled_at,
            'metadata' => $metadata
        ]);
       
        $save_result = $this->save($execution->to_mysql_array());
        
        if ($save_result) {
            // Get the last inserted ID from the database
            $id = $this->get_last_insert_id();
            if ($id) {
                $execution->id = $id;
                return $execution;
            }
        } 
        
        return false;
    }
    
    /**
     * Get pending executions that are due to run
     * 
     * @param int $limit Maximum number of executions to return
     * @return array Array of JobsExecutionObject instances
     */
    public function get_pending_executions($limit = 10) {
        $now = Get::date_time_zone()->format('Y-m-d H:i:s');
        
        $query = "SELECT * FROM {$this->table} 
                 WHERE status = 'pending' 
                 AND scheduled_at <= '{$now}' 
                 ORDER BY id ASC 
                 LIMIT {$limit}";
                 
        return $this->get($query);
    }
    
    /**
     * Get executions for a specific jobs
     * 
     * @param string $jobs_name Name of the jobs
     * @param int $limit Maximum number of executions to return
     * @param int $offset Offset for pagination
     * @return array Array of JobsExecutionObject instances
     */
    public function get_executions_by_jobs_name($jobs_name, $limit = 10, $offset = 0) {
        $query = "SELECT * FROM {$this->table} 
                 WHERE jobs_name = ? 
                 ORDER BY id DESC 
                 LIMIT ?, ?";
                 
        return $this->get($query, [$jobs_name, $offset, $limit]);
    }
    
    /**
     * Get future executions for a specific jobs
     * 
     * @param string $jobs_name Name of the jobs
     * @return array Array of JobsExecutionObject instances that are scheduled for the future
     */
    public function get_next_executions_by_jobs_name($jobs_name) {
        $now = Get::date_time_zone()->format('Y-m-d H:i:s');
        
        $query = "SELECT * FROM {$this->table} 
                 WHERE jobs_name = ? 
                 AND status = 'pending' 
                 ORDER BY id ASC";
        // $next_execution->scheduled_at              
       // $result = $this->get()
        $first =  $this->first($query, [$jobs_name]);
        if ($first) {
            return $first->scheduled_at;
        }
        return null;
    }

    /**
     * Get executions not yet executed; also returns blocked ones if $status_blocked = true
     */
    public function get_not_yet_executed_by_jobs_name($jobs_name, $status_blocked = false) {
        if ($status_blocked) {
            $this->select('*')->where('jobs_name = ? AND (status = ? OR status = ?)', [$jobs_name, 'pending', 'blocked'])->order('id', 'desc');
        } else {
            $this->select('*')->where('jobs_name = ? AND status = ?', [$jobs_name, 'pending'])->order('id', 'desc');
        }
                 
        return $this->first();
    }
    
    /**
     * Get the latest execution for each jobs
     * 
     * @return array Array of JobsExecutionObject instances
     */
    public function get_latest_executions($name) {
        return $this->where('jobs_name = ?', [$name])->order('id', 'desc')->first();
    }

    /**
     * Get executions by status
     * 
     * @param string $status Status to filter by
     * @param int $limit Maximum number of executions to return
     * @param int $offset Offset for pagination
     * @return array Array of JobsExecutionObject instances
     */
    public function get_executions_by_status($status, $limit = 100, $offset = 0) {
        $query = "SELECT * FROM {$this->table} 
                 WHERE status = ? 
                 ORDER BY id DESC 
                 LIMIT ?, ?";
        
                 
        return $this->get($query, [$status, $offset, $limit]);
    }
    
    /**
     * Get executions that have been running for too long (potentially stuck)
     * 
     * @param int $minutes Number of minutes to consider as too long
     * @return array Array of JobsExecutionObject instances
     */
    public function get_stuck_executions($minutes = 30) {
        $cutoff_time = Get::date_time_zone()
            ->modify("-{$minutes} minutes")
            ->format('Y-m-d H:i:s');
            
        $query = "SELECT * FROM {$this->table} 
                 WHERE status = 'running' 
                 AND started_at < '{$cutoff_time}' 
                 ORDER BY id ASC";
                 
        return $this->get($query);
    }
    
    /**
     * Get statistics for jobs executions
     * 
     * @param string|null $jobs_name Optional jobs name to filter by
     * @param string|null $period Period to filter by (today, week, month, year)
     * @return array Statistics including counts by status, average duration, etc.
     */
    public function get_execution_statistics($jobs_name = null, $period = null) {
        $where_clauses = [];
        $params = [];
        $stats = [];
        
        // Add jobs name filter if provided
        if ($jobs_name) {
            $where_clauses[] = "jobs_name = ?";
            $params[] = $jobs_name;
        }
        
        // Add period filter if provided
        if ($period) {
            $date = Get::date_time_zone();
            
            switch ($period) {
                case 'today':
                    $start_date = $date->format('Y-m-d 00:00:00');
                    break;
                case 'week':
                    $start_date = $date->modify('-7 days')->format('Y-m-d H:i:s');
                    break;
                case 'month':
                    $start_date = $date->modify('-30 days')->format('Y-m-d H:i:s');
                    break;
                case 'year':
                    $start_date = $date->modify('-365 days')->format('Y-m-d H:i:s');
                    break;
                default:
                    $start_date = null;
            }
            
            if ($start_date) {
                $where_clauses[] = "scheduled_at >= ?";
                $params[] = $start_date;
            }
        }
        
        // Build the WHERE clause
        $where = '';
        if (!empty($where_clauses)) {
            $where = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Get total count
        $query = "SELECT COUNT(*) as total FROM {$this->table} {$where}";
        $result = $this->db->get_row($query, $params);
        $stats['total'] = $result->total ?? 0;
        
        // Get counts by status
        $query = "SELECT status, COUNT(*) as count FROM {$this->table} {$where} GROUP BY status";
        $results = $this->db->get_results($query);
        
        $stats['by_status'] = [
            'pending' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'blocked' => 0
        ];
        
        if ($results) {
            foreach ($results as $row) {
                $stats['by_status'][$row->status] = $row->count;
            }
        }
        
        // Get average duration for completed jobs
        $where_completed = $where ? "{$where} AND status = 'completed'" : "WHERE status = 'completed'";
        $result = $this->db->get_row(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration 
             FROM {$this->table} {$where_completed}
             WHERE started_at IS NOT NULL AND completed_at IS NOT NULL"
        );
        $stats['avg_duration'] = $result->avg_duration ?? 0;
        
        // Get success rate
        if ($stats['total'] > 0) {
            $stats['success_rate'] = round(($stats['by_status']['completed'] / $stats['total']) * 100, 2);
        } else {
            $stats['success_rate'] = 0;
        }
        
        return $stats;
    }


    /**
     * Clean up old execution records
     * 
     * @param int $days Number of days to keep records for
     * @return int Number of records deleted
     */
    public function cleanup_old_executions($days = 30) {
        $cutoff_date = Get::date_time_zone()
            ->modify("-{$days} days")
            ->format('Y-m-d H:i:s');
            
        $query = "DELETE FROM {$this->table} WHERE completed_at < '{$cutoff_date}'";
        $this->db->query($query);
        
        return $this->db->affected_rows();
    }

    /**
     * Clean up excess log records, keeping only the latest N records
     * 
     * @param int $max_records Maximum number of records to keep (default: 5000)
     * @return int Number of records deleted
     */
    public function cleanup_excess_logs($max_records = 5000) {
        // First, count how many records there are in total
        $total_query = "SELECT COUNT(*) as total FROM {$this->table}";
        $total_result = $this->db->get_row($total_query);
        $total_records = $total_result->total ?? 0;
        
        // If we have fewer records than the limit, do nothing
        if ($total_records <= $max_records) {
            return 0;
        }
        
        // Calculate how many records to delete
        $records_to_delete = $total_records - $max_records;
        
        // Delete the oldest records (with the lowest ID)
        // Use a subquery to find the threshold ID
        $threshold_query = "SELECT id FROM {$this->table} ORDER BY id ASC LIMIT {$records_to_delete}, 1";
        $threshold_result = $this->db->get_row($threshold_query);
        
        if (!$threshold_result) {
            return 0;
        }
        
        $threshold_id = $threshold_result->id;
        
        // Delete all records with an ID lower than the threshold
        $delete_query = "DELETE FROM {$this->table} WHERE id < {$threshold_id}";
        $this->db->query($delete_query);
        
        return $this->db->affected_rows();
    }


    /**
     * Get the count of consecutive failures for a specific job
     * 
     * @param string $jobs_name Name of the job
     * @return int Number of consecutive failures
     */
    public function get_consecutive_failures($jobs_name) {
        $executions = $this->select('*')->where('jobs_name = ? AND status IN ("completed", "failed")', [$jobs_name])->order('id', 'desc')->limit(10)->get();
        
        $consecutive_failures = 0;
        
        foreach ($executions as $execution) {
            if ($execution->status === 'failed') {
                $consecutive_failures++;
            } else {
                // If we find a success, stop counting
                break;
            }
        }
        
        return $consecutive_failures;
    }

    /**
     * Check if a job should be blocked due to consecutive failures
     * 
     * @param string $jobs_name Name of the job
     * @param int $max_failures Maximum allowed consecutive failures (default: 3)
     * @return bool True if the job should be blocked
     */
    public function should_block_job($jobs_name, $max_failures = 3) {
        return $this->get_consecutive_failures($jobs_name) >= $max_failures;
    }

    /**
     * Block future executions for a job by marking pending executions as blocked
     * 
     * @param string $jobs_name Name of the job to block
     * @param string $reason Reason for blocking
     * @return int Number of executions blocked
     */
    public function block_job_executions($jobs_name, $reason = 'Too many consecutive failures') {
        $query = "UPDATE {$this->table} 
                SET status = 'blocked', error = ? 
                WHERE jobs_name = ? AND status = 'pending'";
                
        $this->db->query($query, [$reason, $jobs_name]);
        
        return $this->db->affected_rows();
    }

    /**
     * Unblock a job by removing blocked executions and scheduling a new one
     * 
     * @param string $jobs_name Name of the job to unblock
     * @return bool True if unblocked successfully
     */
    public function unblock_job($jobs_name) {
        // Remove all blocked executions for this job
        $query = "DELETE FROM {$this->table} 
                WHERE jobs_name = ? AND status = 'blocked'";
                
        $this->db->query($query, [$jobs_name]);
        
        return true;
    }
        
}
