<?php
namespace MilkCore;

/**
 * Implementation of common Excel-like mathematical functions
 * 
 * @package     MilkCore
 * @subpackage  MathParser
 * @ignore
 */

class MathematicalFunctions extends FunctionProvider {
    private $data_row_manager;

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
            // Logical functions
            'IF' => [$this, 'func_if'],
            'AND' => [$this, 'func_and'],
            'OR' => [$this, 'func_or'],
            'NOT' => [$this, 'func_not'],
            
            // Mathematical functions
            'SUM' => [$this, 'func_sum'],
            'COUNT' => [$this, 'func_count'],
            'AVERAGE' => [$this, 'func_average'],
            'MIN' => [$this, 'func_min'],
            'MAX' => [$this, 'func_max'],
            'ROUND' => [$this, 'func_round'],
            'ABS' => [$this, 'func_abs'],
            
            // Text functions
            'CONCAT' => [$this, 'func_concat'],
            'LEFT' => [$this, 'func_left'],
            'RIGHT' => [$this, 'func_right'],
            'LEN' => [$this, 'func_len']
        ];
    }
    
    // Function implementations
    
    // Logical functions
    public function func_if($condition, $true_value, $false_value) {
        return $condition ? $true_value : $false_value;
    }
    
    public function func_and($args) {
        if (is_array($args[0]) && count($args) === 1) {
            $args = $args[0];
        }
        foreach ($args as $arg) {
            if (!$arg) {
                return false;
            }
        }
        return true;
    }
    
    public function func_or(...$args) {
        if (is_array($args[0]) && count($args) === 1) {
            $args = $args[0];
        }
        foreach ($args as $arg) {
            if ($arg) {
                return true;
            }
        }
        return false;
    }
    
    public function func_not($value) {
        return !$value;
    }
    
    // Mathematical functions
    public function func_sum(...$args) {
        if (is_array($args[0]) && count($args) === 1) {
            return array_sum($args[0]);
        }
        return array_sum($args);
    }


    public function func_count(...$args) {
        if (is_array($args[0]) && count($args) === 1) {
            return count($args[0]);
        }
        return count($args);
    }
    
    public function func_average(...$args) {
        if (is_array($args[0]) && count($args) === 1) {
            return array_sum($args[0]) / count($args[0]);
        }
        return array_sum($args) / count($args);
    }
    
    public function func_min(...$args) {
        if (is_array($args[0]) && count($args) === 1) {
            return min($args[0]);
        }
        return min($args);
    }
    
    public function func_max(...$args) {
        if (is_array($args[0]) && count($args) === 1) {
            return max($args[0]);
        }
        return max($args);
    }
    
    public function func_round($number, $decimals = 0) {
        if (is_string($number)) {
            $number = (float)$number;
        }  
        return round($number, $decimals);
    }
    
    public function func_abs($number) {
        if (is_string($number)) {
            $number = (float)$number;
        }  
        return abs($number);
    }
    
    // Text functions
    public function func_concat(...$args) {
        if (is_array($args[0]) && count($args) === 1) {
            return implode('', $args);
        }
        if (is_array($args)) {
            return implode('', $args);
        }
    }
    
    public function func_left($text, $num_chars) {
        if (is_string($text)) {
            return substr($text, 0, $num_chars);
        } else {
            return $text;
        }
    }
    
    public function func_right($text, $num_chars) {
        if (is_string($text)) {
            return substr($text, -$num_chars);
        } else {
            return $text;
        }
    }
    
    public function func_len($text) {
        if (is_array($text)) {
            return count($text);
        } else if (is_string($text)) {
            return strlen($text);
        }
        return 0;
    }
}


// Usage example:
/*
$parser = new MathParser();
$excel_functions = new excelFunctions();
$parser->add_functions_from_provider($excel_functions);

try {
    echo $parser->evaluate("2 + 3 * 4"); // 14
    echo "\n";
    echo $parser->evaluate("(2 + 3) * 4"); // 20
    echo "\n";
    echo $parser->evaluate("IF(10 > 5, 1, 0)"); // 1
    echo "\n";
    echo $parser->evaluate("2 + 3 > 4 AND 5 == 5"); // true (1)
    echo "\n";
    echo $parser->evaluate("SUM(1, 2, 3, 4, 5)"); // 15
    echo "\n";
    echo $parser->evaluate("AVERAGE(10, 20, 30)"); // 20
} catch (Exception $e) {
    echo "error: " . $e->getMessage();
}
*/