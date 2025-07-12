<?php
namespace Modules\ApiRegistry;

use MilkCore\AbstractModel;
use MilkCore\Get;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * ApiRegistryLog Model Class
 * 
 * Handles database operations for API log records.
 * This model provides methods for creating, retrieving, updating, and deleting
 * API log records, as well as specialized queries for API monitoring and statistics.
 * 
 * @package     Modules\ApiRegistry
 */
class ApiRegistryLogModel extends AbstractModel {
    /**
     * Database table name
     */
    protected string $table = '#__api_registry_logs';
    
    /**
     * Primary key field name
     */
    protected string $primary_key = 'id';
    
    /**
     * Associated object class
     */
    protected string $object_class = 'Modules\\ApiRegistry\\ApiRegistryLogObject';
    
    /**
     * Constructor
     * 
     * Initializes the model and ensures the database table exists
     */
    public function __construct() {
        // Get database connection
        $this->db = Get::db();
    }
    
    /**
     * Get the table name
     * 
     * @return string The table name with prefix
     */
    public function get_table_name() {
        return $this->table;
    }
    
    /**
     * Get logs for a specific API
     * 
     * @param string $api_name Name of the API
     * @param int $limit Maximum number of logs to return
     * @param int $offset Offset for pagination
     * @return array Array of ApiRegistryLogObject instances
     */
    public function get_logs_by_api_name($api_name, $limit = 10, $offset = 0) {
        $query = "SELECT * FROM ".$this->db->qn($this->table)." 
                 WHERE api_name = ? 
                 ORDER BY id DESC 
                 LIMIT ?, ?";
                 
        return $this->get($query, [$api_name, $offset, $limit]);
    }
    
    /**
     * Get logs by response status
     * 
     * @param string $status Status to filter by (success, failed)
     * @param int $limit Maximum number of logs to return
     * @param int $offset Offset for pagination
     * @return array Array of ApiRegistryLogObject instances
     */
    public function get_logs_by_status($status, $limit = 100, $offset = 0) {
        $query = "SELECT * FROM ".$this->db->qn($this->table)." 
                 WHERE response_status = ? 
                 ORDER BY id DESC 
                 LIMIT ?, ?";
                 
        return $this->get($query, [$status, $offset, $limit]);
    }
    
    /**
     * Get logs by authentication status
     * 
     * @param string $auth_status Authentication status to filter by (success, failed)
     * @param int $limit Maximum number of logs to return
     * @param int $offset Offset for pagination
     * @return array Array of ApiRegistryLogObject instances
     */
    public function get_logs_by_auth_status($auth_status, $limit = 100, $offset = 0) {
        $query = "SELECT * FROM ".$this->db->qn($this->table)." 
                 WHERE auth_status = ? 
                 ORDER BY id DESC 
                 LIMIT ?, ?";
                 
        return $this->get($query, [$auth_status, $offset, $limit]);
    }
    
    /**
     * Get logs for a specific caller IP
     * 
     * @param string $caller_ip IP address of the caller
     * @param int $limit Maximum number of logs to return
     * @param int $offset Offset for pagination
     * @return array Array of ApiRegistryLogObject instances
     */
    public function get_logs_by_caller_ip($caller_ip, $limit = 100, $offset = 0) {
        $query = "SELECT * FROM ".$this->db->qn($this->table)." 
                 WHERE caller_ip = ? 
                 ORDER BY id DESC 
                 LIMIT ?, ?";
                 
        return $this->get($query, [$caller_ip, $offset, $limit]);
    }
    
    /**
     * Get logs for a specific caller ID
     * 
     * @param int $caller_id ID of the caller
     * @param int $limit Maximum number of logs to return
     * @param int $offset Offset for pagination
     * @return array Array of ApiRegistryLogObject instances
     */
    public function get_logs_by_caller_id($caller_id, $limit = 100, $offset = 0) {
        $query = "SELECT * FROM ".$this->db->qn($this->table)." 
                 WHERE caller_id = ? 
                 ORDER BY id DESC 
                 LIMIT ?, ?";
                 
        return $this->get($query, [$caller_id, $offset, $limit]);
    }
    
    
    /**
     * Get distinct API names from log entries
     * 
     * @return array Array of API names
     */
    public function get_distinct_api_names() {
        $query = "SELECT DISTINCT api_name FROM ".$this->db->qn($this->table)." ORDER BY api_name ASC";
        $results = $this->db->get_results($query);
        
        $api_names = [];
        if ($results) {
            foreach ($results as $row) {
                $api_names[] = $row->api_name;
            }
        }   
        
        return $api_names;
    }
    
    /**
     * Clean up old log records
     * 
     * @param int $days Number of days to keep records for
     * @return int Number of records deleted
     */
    public function cleanup_old_logs($days = 90) {
        $cutoff_date = Get::date_time_zone()
            ->modify("-{$days} days")
            ->format('Y-m-d H:i:s');
            
        $query = "DELETE FROM ". $this->db->qn($this->table) ." WHERE started_at < ?";
        $this->db->query($query, [$cutoff_date]);
        return $this->db->affected_rows();
    }
    
    /**
     * Clean up excess log records, keeping only the latest N records
     * 
     * @param int $max_records Maximum number of records to keep (default: 10000)
     * @return int Number of records deleted
     */
    public function cleanup_excess_logs($max_records = 10000) {
        // First, count how many records there are in total
        $total_query = "SELECT COUNT(*) as total FROM ".$this->db->qn($this->table)."";
        $total_result = $this->db->get_row($total_query);
        $total_records = $total_result->total ?? 0;
        
        // If we have fewer records than the limit, do nothing
        if ($total_records <= $max_records) {
            return 0;
        }
        
        // Calculate how many records to delete
        $records_to_delete = $total_records - $max_records;
        
        // Delete the oldest records (with the lowest ID)
        $threshold_query = "SELECT id FROM ".$this->db->qn($this->table)." ORDER BY id ASC LIMIT ?, 1";
        $threshold_result = $this->db->get_row($threshold_query, [$records_to_delete]);
        
        if (!$threshold_result) {
            return 0;
        }
        
        $threshold_id = $threshold_result->id;
        
        // Delete all records with an ID lower than the threshold
        $delete_query = "DELETE FROM ".$this->db->qn($this->table)." WHERE id < ?";
        $this->db->query($delete_query, [$threshold_id]);
        
        return $this->db->affected_rows();
    }
}
