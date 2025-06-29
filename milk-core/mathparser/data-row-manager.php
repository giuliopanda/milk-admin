<?php
namespace MilkCore;
/**
 * DataRowManager class for handling tabular data and accessing rows
 * 
 * @package     MilkCore
 * @subpackage  MathParser
 * @ignore
 */

class DataRowManager {
    private $data = []; // Array of rows, each row is an associative array column=>value
    private $current_row_index = 0; // Index of the current row

    /**
     * Sets the data to be managed
     * 
     * @param array $data Array of rows (associative arrays)
     * @return void
     */
    public function set_data($data) {
        $this->data = &$data;
    }

    /**
     * Returns all data
     * 
     * @return array All managed data
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Sets the current row index
     * 
     * @param int $index Row index to set as current
     * @throws \Exception If index is out of range
     * @return void
     */
    public function set_current_row_index($index) {
        if ($index < 0 || $index >= count($this->data)) {
            throw new \Exception("Row index out of range: $index");
        }
        $this->current_row_index = $index;
    }

    /**
     * Gets the current row index
     * 
     * @return int Current row index
     */
    public function get_current_row_index() {
        return $this->current_row_index;
    }

    /**
     * Parses a row access expression like [prev.column_name]
     * and returns the corresponding value
     * 
     * @param string $expression Expression to parse
     * @return mixed Value of the specified column
     * @throws \Exception If column or row is not found
     */
    public function parse_row_expression($expression) {
        // Remove outer square brackets
        if (substr($expression, 0, 1) === '[' && substr($expression, -1) === ']') {
            $expression = substr($expression, 1, -1);
        }
        
        // Special cases: prev, next, first, last, row(n)
        if (preg_match('/^prev\.(.+)$/', $expression, $matches)) {
            return $this->get_value_from_relative_row(-1, $matches[1]);
        } else if (preg_match('/^next\.(.+)$/', $expression, $matches)) {
            return $this->get_value_from_relative_row(1, $matches[1]);
        } else if (preg_match('/^first\.(.+)$/', $expression, $matches)) {
            return $this->get_value_from_absolute_row(0, $matches[1]);
        } else if (preg_match('/^last\.(.+)$/', $expression, $matches)) {
            return $this->get_value_from_absolute_row(count($this->data) - 1, $matches[1]);
        } else if (preg_match('/^row\((\d+)\)\.(.+)$/', $expression, $matches)) {
            return $this->get_value_from_absolute_row((int)$matches[1], $matches[2]);
        } else {
            // Base case: [column_name] => current row
            return $this->get_value_from_current_row($expression);
        }
    }

    /**
     * Gets the value of a column from the current row
     * 
     * @param string $column_name Column name
     * @return mixed Value of the column in the current row
     * @throws \Exception If column is not found
     */
    public function get_value_from_current_row($column_name) {
        if (is_object($this->data[$this->current_row_index]) && property_exists($this->data[$this->current_row_index],$column_name)) {
            return $this->data[$this->current_row_index]->$column_name;
        }
        if (!is_array($this->data[$this->current_row_index]) || !array_key_exists($column_name, $this->data[$this->current_row_index])) {
            throw new \Exception("Column not found: $column_name");
        }
        return $this->data[$this->current_row_index][$column_name];
    }

    /**
     * Gets the value of a column from a row relative to the current one
     * 
     * @param int $offset Offset relative to current row (-1 for prev, 1 for next)
     * @param string $column_name Column name
     * @return mixed Value of the column in the relative row
     * @throws \Exception If row is out of range or column is not found
     */
    private function get_value_from_relative_row($offset, $column_name) {
        $target_index = $this->current_row_index + $offset;
        
        if ($target_index < 0 || $target_index >= count($this->data)) {
            throw new \Exception("Relative row out of range: offset $offset from index {$this->current_row_index}");
        }
        
        if (is_object($this->data[$target_index]) && property_exists($this->data[$target_index], $column_name)) {
            return $this->data[$this->current_row_index]->$column_name;
        }
        if (!is_array($this->data[$this->current_row_index]) || !array_key_exists($column_name, $this->data[$target_index])) {
            throw new \Exception("Column not found: $column_name");
        }
        return $this->data[$target_index][$column_name];
    }

    /**
     * Gets the value of a column from a row with absolute index
     * 
     * @param int $row_index Absolute row index
     * @param string $column_name Column name
     * @return mixed Value of the column in the specified row
     * @throws \Exception If row index is out of range or column is not found
     */
    private function get_value_from_absolute_row($row_index, $column_name) {
        if ($row_index < 0 || $row_index >= count($this->data)) {
            throw new \Exception("Absolute row index out of range: $row_index");
        }
        
        if (is_object($this->data[$row_index]) && property_exists($this->data[$row_index], $column_name)) {
            return $this->data[$this->current_row_index]->$column_name;
        }
        if (!is_array($this->data[$this->current_row_index]) || !array_key_exists($column_name, $this->data[$row_index])) {
            throw new \Exception("Column not found: $column_name");
        }
        
        return $this->data[$row_index][$column_name];
    }
}