<?php
namespace MilkCore;
/**
 * MathParser class for mathematical expression analysis
 * Integrated version with functionality to handle row references
 *
 * @package     MilkCore
 * @subpackage  MathParser
 * @ignore
 */

class MathParser {
    protected $expression;
    protected $position;
    protected $functions = [];
    protected $data_row_manager = null; // Reference to DataRowManager
    static $added_functions_class = [];

    /**
     * Constructor
     * 
     * @param DataRowManager $manager Optional DataRowManager instance
     * @return void
     */
    public function __construct($manager = null) {
        $this->data_row_manager = $manager;
        
        // Inizializza i provider di funzioni registrati
        foreach (self::$added_functions_class as $class) {
            $obj = new $class($manager);
            $this->add_functions_from_provider($obj);
        }
    }

    /**
     * Sets the data_row_manager to use for row references
     * 
     * @param DataRowManager $manager DataRowManager instance
     * @return void
     */
    public function set_data_row_manager($manager) {
        $this->data_row_manager = $manager;
        
        // Reinitialize function providers with the new manager
        $this->functions = [];
        foreach (self::$added_functions_class as $class) {
            $obj = new $class($manager);
            $this->add_functions_from_provider($obj);
        }
    }

    /**
     * Sets the current row index in the DataRowManager
     * 
     * @param int $index Row index
     * @return void
     */
    public function set_current_row_index($index) {
        if ($this->data_row_manager === null) {
            throw new \Exception("DataRowManager not set");
        }
        $this->data_row_manager->set_current_row_index($index);
    }

    /**
     * Registers a function that can be called in the expression
     * 
     * @param string $name Function name
     * @param callable $callback Function to execute
     * @return void
     */
    public function register_function($name, callable $callback) {
        $this->functions[strtoupper($name)] = $callback;
    }

    /**
     * Adds all functions from a function provider
     * 
     * @param FunctionProvider $provider Function provider
     * @return void
     */
    public function add_functions_from_provider($provider) {
        $functions = $provider->get_functions();
        foreach ($functions as $name => $callback) {
            $this->register_function($name, $callback);
        }
    }

    /**
     * Evaluates a mathematical expression
     * 
     * @param string|int|float $expression Expression to evaluate
     * @return mixed Result of the expression
     */
    public function evaluate($expression) {
        // Convert the expression to string to ensure it can be indexed
        $this->expression = (string)$expression;
        $this->position = 0;
        
        return $this->parse_expression();
    }

    /**
     * Parses the complete expression
     * 
     * @return mixed Result of the expression
     */
    private function parse_expression() {
        return $this->parse_logical_expression();
    }

    /**
     * Parses logical expressions (AND, OR, XOR)
     * 
     * @return mixed Result of the logical expression
     */
    private function parse_logical_expression() {
        $left = $this->parse_comparison_expression();
        
        while ($this->position < strlen($this->expression)) {
            $this->skip_whitespace();
            
            if ($this->match('AND')) {
                $right = $this->parse_comparison_expression();
                $left = $left && $right;
            } else if ($this->match('OR')) {
                $right = $this->parse_comparison_expression();
                $left = $left || $right;
            } else if ($this->match('XOR')) {
                $right = $this->parse_comparison_expression();
                $left = ($left xor $right);
            } else {
                break;
            }
        }
        
        return $left;
    }

    /**
     * Parses comparison expressions (==, !=, >, <, >=, <=)
     * 
     * @return mixed Result of the comparison
     */
    private function parse_comparison_expression() {
        $left = $this->parse_additive_expression();
        
        $this->skip_whitespace();
        
        if ($this->match('==')) {
            $right = $this->parse_additive_expression();
            return $left == $right;
        } else if ($this->match('!=')) {
            $right = $this->parse_additive_expression();
            return $left != $right;
        } else if ($this->match('>=')) {
            $right = $this->parse_additive_expression();
            return $left >= $right;
        } else if ($this->match('<=')) {
            $right = $this->parse_additive_expression();
            return $left <= $right;
        } else if ($this->match('>')) {
            $right = $this->parse_additive_expression();
            return $left > $right;
        } else if ($this->match('<')) {
            $right = $this->parse_additive_expression();
            return $left < $right;
        }
        
        return $left;
    }

    /**
     * Parses additive expressions (+, -)
     * 
     * @return mixed Result of the additive expression
     */
    private function parse_additive_expression() {
        $left = $this->parse_multiplicative_expression();
        
        while ($this->position < strlen($this->expression)) {
            $this->skip_whitespace();
            
            if ($this->match('+')) {
                $right = $this->parse_multiplicative_expression();
                $left += $right;
            } else if ($this->match('-')) {
                $right = $this->parse_multiplicative_expression();
                $left -= $right;
            } else {
                break;
            }
        }
        
        return $left;
    }

    /**
     * Parses multiplicative expressions (*, /, %, ^)
     * 
     * @return mixed Result of the multiplicative expression
     */
    private function parse_multiplicative_expression() {
        $left = $this->parse_exponential_expression();
        
        while ($this->position < strlen($this->expression)) {
            $this->skip_whitespace();
            
            if ($this->match('*')) {
                $right = $this->parse_exponential_expression();
                $left *= $right;
            } else if ($this->match('/')) {
                $right = $this->parse_exponential_expression();
                if ($right == 0) {
                    throw new \Exception("division by zero");
                }
                $left /= $right;
            } else if ($this->match('%')) {
                $right = $this->parse_exponential_expression();
                $left %= $right;
            } else {
                break;
            }
        }
        
        return $left;
    }
    
    /**
     * Parses exponential expressions (^)
     * The ^ operator is right-associative (2^3^2 = 2^(3^2))
     * 
     * @return mixed Result of the exponential expression
     */
    private function parse_exponential_expression() {
        $left = $this->parse_primary();
        
        $this->skip_whitespace();
        
        if ($this->position < strlen($this->expression) && substr($this->expression, $this->position, 1) == '^') {
            $this->position++; // Consuma l'operatore ^
            $right = $this->parse_exponential_expression(); // Ricorsione per associatività a destra
            return pow($left, $right);
        }
        
        return $left;
    }

    /**
     * Parses primary expressions (numbers, strings, booleans, parentheses, functions, unary operations)
     * Includes support for [column_name] and variants to access data through DataRowManager
     * 
     * @return mixed Result of the primary expression
     */
    protected function parse_primary() {
        $this->skip_whitespace();
        
        // Controllo se è un operatore unario (+ o -)
        if ($this->match('+')) {
            // Unario positivo (non fa nulla)
            return $this->parse_primary();
        } else if ($this->match('-')) {
            // Unario negativo
            return -$this->parse_primary();
        } else if ($this->match('!')) {
            // Operatore NOT logico
            return !$this->parse_primary();
        }
        
        // Controllo se è un valore booleano
        if ($this->match('true')) {
            return true;
        } else if ($this->match('false')) {
            return false;
        }
        
        // Controllo se è un riferimento a riga [...]
        if ($this->position < strlen($this->expression) && 
            $this->expression[$this->position] === '[') {
            $start = $this->position;
            $this->position++; // Salta la parentesi quadra iniziale
            
            // Trova la parentesi di chiusura
            $nesting_level = 1;
            while ($this->position < strlen($this->expression) && $nesting_level > 0) {
                if ($this->expression[$this->position] === '[') {
                    $nesting_level++;
                } elseif ($this->expression[$this->position] === ']') {
                    $nesting_level--;
                }
                $this->position++;
            }
            
            if ($nesting_level > 0) {
                throw new \Exception("missing closing square bracket");
            }
            
            // Estrai l'espressione tra parentesi quadre
            $row_expression = substr($this->expression, $start, $this->position - $start);
            
            // Verifica che il data_row_manager sia stato impostato
            if ($this->data_row_manager === null) {
                throw new \Exception("DataRowManager not set to handle row references");
            }
            
            // Delega al data_row_manager
            return $this->data_row_manager->parse_row_expression($row_expression);
        }
        
        // Controllo se è una stringa (tra apici singoli o doppi)
        if ($this->position < strlen($this->expression)) {
            $char = $this->expression[$this->position];
            
            if ($char === "'" || $char === '"') {
                $quote_char = $char;
                $this->position++; // Salta il primo carattere di quotazione
                $start = $this->position;
                
                // Cerca la chiusura della stringa
                while ($this->position < strlen($this->expression) && 
                       $this->expression[$this->position] !== $quote_char) {
                    // Gestione degli escape
                    if ($this->expression[$this->position] === '\\' && 
                        $this->position + 1 < strlen($this->expression)) {
                        $this->position++; // Salta il backslash
                    }
                    $this->position++;
                }
                
                if ($this->position >= strlen($this->expression)) {
                    throw new \Exception("unterminated string");
                }
                
                $string = substr($this->expression, $start, $this->position - $start);
                // Processa gli escape
                $string = str_replace('\\' . $quote_char, $quote_char, $string);
                $string = str_replace('\\n', "\n", $string);
                $string = str_replace('\\t', "\t", $string);
                $string = str_replace('\\r', "\r", $string);
                
                $this->position++; // Salta l'ultimo carattere di quotazione
                return $string;
            }
        }
        
        // Controllo se è una funzione
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*/', substr($this->expression, $this->position), $matches)) {
            $function_name = $matches[0];
            $start_pos = $this->position;
            $this->position += strlen($function_name);
            
            $this->skip_whitespace();
            
            if ($this->match('(')) {
                // È una chiamata a funzione
                $args = $this->parse_arguments();
                
                // Verifica se la funzione esiste
                $function_name = strtoupper($function_name);
                if (isset($this->functions[$function_name])) {
                    return call_user_func_array($this->functions[$function_name], $args);
                } else {
                    throw new \Exception("undefined function: $function_name");
                }
            } else {
                // Non è una funzione, ripristina la posizione
                $this->position = $start_pos;
            }
        }
        
        // Controllo se è un numero (scientifico o normale)
        if (preg_match('/^-?\d+(\.\d+)?([eE][-+]?\d+)?/', substr($this->expression, $this->position), $matches)) {
            $number = $matches[0];
            $this->position += strlen($number);
            return (float)$number; // Tutti i numeri come float per gestire la notazione scientifica
        }
        
        // Controllo per costanti matematiche
        if ($this->match('PI')) {
            return M_PI;
        } else if ($this->match('E')) {
            return M_E;
        }
        
        // Controllo se è una parentesi
        if ($this->match('(')) {
            $value = $this->parse_expression();
            
            if (!$this->match(')')) {
                throw new \Exception("missing closing parenthesis");
            }
            
            return $value;
        }
        
        throw new \Exception("invalid syntax at position {$this->position}");
    }

    /**
     * Parses function arguments
     * 
     * @return array List of evaluated arguments
     */
    private function parse_arguments() {
        $args = [];
        
        // Se c'è subito una parentesi di chiusura, non ci sono argomenti
        if ($this->peek() == ')') {
            $this->position++;
            return $args;
        }
        
        // Analizza gli argomenti separati da virgole
        do {
            $args[] = $this->parse_expression();
        } while ($this->match(','));
        
        // Verifica la parentesi di chiusura
        if (!$this->match(')')) {
            throw new \Exception("missing closing parenthesis");
        }
        
        return $args;
    }

    /**
     * Skips whitespace
     * 
     * @return void
     */
    protected function skip_whitespace() {
        while ($this->position < strlen($this->expression) && 
               ctype_space($this->expression[$this->position])) {
            $this->position++;
        }
    }

    /**
     * Checks if the current string matches the pattern
     * 
     * @param string $pattern String to search for
     * @return bool True if there's a match
     */
    private function match($pattern) {
        $len = strlen($pattern);
        
        if ($this->position + $len <= strlen($this->expression) && 
            substr($this->expression, $this->position, $len) === $pattern) {
            $this->position += $len;
            return true;
        }
        
        return false;
    }

    /**
     * Returns the current character without advancing
     * 
     * @return string Current character
     */
    private function peek() {
        return ($this->position < strlen($this->expression)) ? 
               $this->expression[$this->position] : null;
    }

    /**
     * Registers a function provider class to be used in all parsers
     * 
     * @param string $class_name Provider class name
     * @return void
     */
    static function add_class($class_name) {
        self::$added_functions_class[] = $class_name;
    }
}

/**
 * FunctionProvider class to provide functions to the parser
 *
 * @package     MilkCore
 * @subpackage  MathParser
 * @version     1.0.0
 * @ignore
 */
abstract class FunctionProvider {
    /**
     * Returns an array of available functions
     * 
     * @return array Associative array of functions (name => callback)
     */
    abstract public function get_functions();
}