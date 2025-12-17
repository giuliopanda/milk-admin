<?php
namespace Modules\Db2tables;

use App\Database\Query;
use App\Get;

!defined('MILK_DIR') && die(); // Prevent direct access

class Db2tablesModel
{
    /**
     * Get the active database connection
     *
     * @return \App\Abstracts\AbstractDb Database connection instance
     */
    private function getDb() {
        return Db2tablesServices::getDb();
    }

    /**
     * Get all tables from the database
     *
     * @return array List of tables
     */
    public function getTables()
    {
        // Using the native MySQL method to get tables
        $tables = $this->getDb()->getTables(false);
        
        $result = [];
        if (!empty($tables)) {
            foreach ($tables as $table_name) {
                $result[] = [
                    'name' => $table_name,
                    'type' => 'table'
                ];
            }
        }
        
        return $result;
    }

    /**
     * Get all views from the db2 database
     * 
     * @return array List of views
     */
    public function getViews()
    {
        // Using the native MySQL method to get views
        $views = $this->getDb()->getViews(false);
        
        $result = [];
        if (!empty($views)) {
            foreach ($views as $view_name) {
                $result[] = [
                    'name' => $view_name,
                    'type' => 'view'
                ];
            }
        }
        
        return $result;
    }

    /**
     * Get all tables and views from the db2 database
     * 
     * @return array Combined list of tables and views
     */
    public function getAllTablesAndViews()
    {
        return array_merge($this->getTables(), $this->getViews());
    }

    public function getTableStructure($table) {
        $table_structure = [];
       // $ris = $this->db2->getResults("SHOW COLUMNS FROM " . $this->db2->qn($table));
       $ris = $this->getDb()->getColumns($table);
        foreach ($ris as $row) {
            $table_structure[$row->Field] = $row;
        }
        return $table_structure;
    }

    /**
     * Get table data
     * 
     * @param string $table_name Table name
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Table data
     */
    public function getTableData($table_name, $limit = 100, $offset = 0)
    {
        $db = $this->getDb();
        // Using Query class to build the query
        $query = new Query($table_name, $db);
        $query->select(['*'])
              ->limit($offset, $limit);

        // Get the SQL and parameters
        list($sql, $params) = $query->get();

        // Execute the query
        return $db->getResults($sql, $params);
    }

    /**
     * Execute a custom query on the db2 database
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array|null Query results
     */
    public function executeQuery($sql, $params = [])
    {
        return $this->getDb()->getResults($sql, $params);
    }
    
}
