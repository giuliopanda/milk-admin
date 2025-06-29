<?php
namespace MilkCore;
use MilkCore\FunctionProvider;
/**
 * Function provider for tabular data operations
 * 
 * @package     MilkCore
 * @subpackage  MathParser
 * @ignore
 */

class DataRowFunctions extends FunctionProvider {
    private $data_row_manager;
    
    /**
     * Constructor
     * 
     * @param DataRowManager $manager DataRowManager instance
     * @return void
     */
    public function __construct($manager = null) {
        $this->data_row_manager = $manager;
    }
    
    /**
     * Returns all implemented functions
     * 
     * @return array Associative array of functions (name => callback)
     */
    public function get_functions() {
        return [
            'GET_RAW' => [$this, 'func_get_raw'],
            'ROW_COUNT' => [$this, 'func_row_count'],
            'COLUMN_SUM' => [$this, 'func_column_sum'],
            'COLUMN_AVG' => [$this, 'func_column_avg'],
            'COLUMN_MAX' => [$this, 'func_column_max'],
            'COLUMN_MIN' => [$this, 'func_column_min'],
            'ROW_INDEX' => [$this, 'func_row_index'],
        ];
    }

    /**
     * Gets raw values from a column
     * 
     * @param string $column_name Column name to retrieve values from
     * @param int|string $index Specific index to retrieve or -1 for all values
     * @return mixed The value at the specified index or all values
     */
    public function func_get_raw($column_name, $index = -1) {
        $values = [];
        $raw_data = $this->data_row_manager->get_value_from_current_row('___raw_data___');
        if (isset($raw_data[$column_name]) && is_array($raw_data[$column_name])) {
            $values = $raw_data[$column_name];
        }
        if ((strtoupper($index) === "LAST" || strtoupper($index) === "END") && count($values) > 0) {
            return end($values);
        } elseif (strtoupper($index) === "FIRST"  && count($values) > 0) {
            return reset($values);
        } else if ($index >= 0 && count($values) > 0) {
            if (array_key_exists($index, $values)) {
                return $values[$index];
            } else {
                return null;
            }
        }
        return $values;
    }
    
    /**
     * Returns the number of rows in the data
     * 
     * @return int Row count
     */
    public function func_row_count() {
        return count($this->data_row_manager->get_data());
    }
    
    /**
     * Returns the current row index
     * 
     * @return int Current row index
     */
    public function func_row_index() {
        return $this->data_row_manager->get_current_row_index();
    }
    
    /**
     * Calculates the sum of values in a column
     * 
     * @param string $column_name Column name to sum
     * @return float Sum of numeric values in the column
     */
    public function func_column_sum($column_name) {
        $sum = 0;
        foreach ($this->data_row_manager->get_data() as $row) {
            if (isset($row[$column_name]) && is_numeric($row[$column_name])) {
                $sum += $row[$column_name];
            }
        }
        return $sum;
    }
    
    /**
     * Calculates the average of values in a column
     * 
     * @param string $column_name Column name to average
     * @return float Average of numeric values in the column
     */
    public function func_column_avg($column_name) {
        $sum = $this->func_column_sum($column_name);
        $count = count($this->data_row_manager->get_data());
        return $count > 0 ? $sum / $count : 0;
    }
    
    /**
     * Finds the maximum value in a column
     * 
     * @param string $column_name Column name to search
     * @return mixed Maximum value in the column or null if empty
     */
    public function func_column_max($column_name) {
        $max = null;
        foreach ($this->data_row_manager->get_data() as $row) {
            if (isset($row[$column_name]) && (is_null($max) || $row[$column_name] > $max)) {
                $max = $row[$column_name];
            }
        }
        return $max;
    }
    
    /**
     * Finds the minimum value in a column
     * 
     * @param string $column_name Column name to search
     * @return mixed Minimum value in the column or null if empty
     */
    public function func_column_min($column_name) {
        $min = null;
        foreach ($this->data_row_manager->get_data() as $row) {
            if (isset($row[$column_name]) && (is_null($min) || $row[$column_name] < $min)) {
                $min = $row[$column_name];
            }
        }
        return $min;
    }
}


