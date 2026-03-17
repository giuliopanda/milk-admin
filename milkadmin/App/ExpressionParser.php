<?php
namespace App;

!defined('MILK_DIR') && die();

use App\ExpressionParser\TokenType;
use App\ExpressionParser\Lexer;
use App\ExpressionParser\Parser;
use App\ExpressionParser\Evaluator;
use App\ExpressionParser\BuiltinFunctions;
use App\ExpressionParser\ValueHelper;

/**
 * ExpressionParser - Parser for mini programming language
 * PHP version - Identical behavior to JavaScript version
 *
 * Facade class that maintains 100% backward compatibility.
 * Internally delegates to: Lexer, Parser, Evaluator, BuiltinFunctions.
 *
 * Supports:
 * - Mathematical operations: +, -, *, /, %, ^ (power)
 * - Comparison operators: ==, <>, !=, <, >, <=, >=
 * - Logical operators: AND, OR, NOT (or &&, ||, !)
 * - Parentheses: ()
 * - Variables and assignments: a = 5
 * - Parameters: [salary] takes value from set parameters
 * - IF statements: IF condition THEN ... ELSE ... ENDIF
 * - Dates: 2025-01-29 or 29/01/2025
 * - Functions: NOW(), AGE(date), ROUND(n, decimals), ABS(n), IFNULL(val, default),
 *             UPPER(str), LOWER(str), CONCAT(str1, str2, ...), TRIM(str),
 *             ISEMPTY(val), PRECISION(n, decimals), DATEONLY(datetime),
 *             TIMEADD(time, minutes), ADDMINUTES(time, minutes), USERID()
 */
class ExpressionParser
{
    use ValueHelper;

    // ==================== TOKEN CONSTANTS (backward compatibility) ====================
    const TOKEN_NUMBER = TokenType::TOKEN_NUMBER;
    const TOKEN_STRING = TokenType::TOKEN_STRING;
    const TOKEN_DATE = TokenType::TOKEN_DATE;
    const TOKEN_IDENTIFIER = TokenType::TOKEN_IDENTIFIER;
    const TOKEN_PARAMETER = TokenType::TOKEN_PARAMETER;
    const TOKEN_PLUS = TokenType::TOKEN_PLUS;
    const TOKEN_MINUS = TokenType::TOKEN_MINUS;
    const TOKEN_MULTIPLY = TokenType::TOKEN_MULTIPLY;
    const TOKEN_DIVIDE = TokenType::TOKEN_DIVIDE;
    const TOKEN_MODULO = TokenType::TOKEN_MODULO;
    const TOKEN_POWER = TokenType::TOKEN_POWER;
    const TOKEN_LPAREN = TokenType::TOKEN_LPAREN;
    const TOKEN_RPAREN = TokenType::TOKEN_RPAREN;
    const TOKEN_COMMA = TokenType::TOKEN_COMMA;
    const TOKEN_ASSIGN = TokenType::TOKEN_ASSIGN;
    const TOKEN_EQ = TokenType::TOKEN_EQ;
    const TOKEN_NEQ = TokenType::TOKEN_NEQ;
    const TOKEN_LT = TokenType::TOKEN_LT;
    const TOKEN_GT = TokenType::TOKEN_GT;
    const TOKEN_LTE = TokenType::TOKEN_LTE;
    const TOKEN_GTE = TokenType::TOKEN_GTE;
    const TOKEN_AND = TokenType::TOKEN_AND;
    const TOKEN_OR = TokenType::TOKEN_OR;
    const TOKEN_NOT = TokenType::TOKEN_NOT;
    const TOKEN_IF = TokenType::TOKEN_IF;
    const TOKEN_THEN = TokenType::TOKEN_THEN;
    const TOKEN_ELSE = TokenType::TOKEN_ELSE;
    const TOKEN_ENDIF = TokenType::TOKEN_ENDIF;
    const TOKEN_NEWLINE = TokenType::TOKEN_NEWLINE;
    const TOKEN_EOF = TokenType::TOKEN_EOF;

    // ==================== AST NODE CONSTANTS (backward compatibility) ====================
    const NODE_NUMBER = TokenType::NODE_NUMBER;
    const NODE_STRING = TokenType::NODE_STRING;
    const NODE_DATE = TokenType::NODE_DATE;
    const NODE_IDENTIFIER = TokenType::NODE_IDENTIFIER;
    const NODE_PARAMETER = TokenType::NODE_PARAMETER;
    const NODE_BINARY_OP = TokenType::NODE_BINARY_OP;
    const NODE_UNARY_OP = TokenType::NODE_UNARY_OP;
    const NODE_ASSIGNMENT = TokenType::NODE_ASSIGNMENT;
    const NODE_IF_STATEMENT = TokenType::NODE_IF_STATEMENT;
    const NODE_FUNCTION_CALL = TokenType::NODE_FUNCTION_CALL;
    const NODE_PROGRAM = TokenType::NODE_PROGRAM;

    // ==================== INTERNAL COMPONENTS ====================
    private Lexer $lexer;
    private Parser $parser;
    private Evaluator $evaluator;
    private int $maxSourceLength = 0;
    private int $maxAstNodes = 0;
    private int $maxAstDepth = 0;
    private bool $allowAssignments = true;
    /** @var array<string, bool>|null */
    private ?array $allowedFunctions = null;

    public function __construct()
    {
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->evaluator = new Evaluator();
    }

    // ==================== COMPONENT ACCESS (for extensibility) ====================

    /**
     * Returns the internal Lexer
     */
    public function getLexer(): Lexer
    {
        return $this->lexer;
    }

    /**
     * Returns the internal Parser
     */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    /**
     * Returns the internal Evaluator
     */
    public function getEvaluator(): Evaluator
    {
        return $this->evaluator;
    }

    /**
     * Configure security policy applied to parse/execute.
     *
     * Supported options:
     * - max_source_length: int (0 = no limit)
     * - max_ast_nodes: int (0 = no limit)
     * - max_ast_depth: int (0 = no limit)
     * - allow_assignments: bool
     * - allowed_functions: string[]|null (null = all builtins)
     * - max_execution_steps: int (propagated to Evaluator)
     * - max_parameter_path_segments: int (propagated to Evaluator)
     * - allow_object_array_access: bool (propagated to Evaluator)
     * - allow_object_getter_methods: bool (propagated to Evaluator)
     * - allow_object_magic_access: bool (propagated to Evaluator)
     */
    public function setSecurityPolicy(array $policy): self
    {
        if (array_key_exists('max_source_length', $policy)) {
            $this->maxSourceLength = max(0, (int)$policy['max_source_length']);
        }
        if (array_key_exists('max_ast_nodes', $policy)) {
            $this->maxAstNodes = max(0, (int)$policy['max_ast_nodes']);
        }
        if (array_key_exists('max_ast_depth', $policy)) {
            $this->maxAstDepth = max(0, (int)$policy['max_ast_depth']);
        }
        if (array_key_exists('allow_assignments', $policy)) {
            $this->allowAssignments = (bool)$policy['allow_assignments'];
        }
        if (array_key_exists('allowed_functions', $policy)) {
            $allowed = $policy['allowed_functions'];
            if ($allowed === null) {
                $this->allowedFunctions = null;
            } elseif (is_array($allowed)) {
                $map = [];
                foreach ($allowed as $fn) {
                    if (!is_string($fn) || trim($fn) === '') {
                        continue;
                    }
                    $map[strtoupper(trim($fn))] = true;
                }
                $this->allowedFunctions = $map;
            }
        }

        $this->evaluator->configureSecurityPolicy($policy);

        return $this;
    }

    /**
     * Predefined hardening for expressions from runtime configurations.
     */
    public function useUntrustedMode(): self
    {
        return $this->setSecurityPolicy([
            'max_source_length' => 2000,
            'max_ast_nodes' => 500,
            'max_ast_depth' => 64,
            'max_execution_steps' => 2000,
            'max_parameter_path_segments' => 12,
            'allow_object_array_access' => false,
            'allow_object_getter_methods' => false,
            'allow_object_magic_access' => false,
            'allow_assignments' => true,
            'allowed_functions' => null
        ]);
    }

    // ==================== PARAMETER HANDLING ====================

    /**
     * Set parameters from an associative array
     * @param array $params Associative array [name => value]
     * @return self
     */
    public function setParameters(array $params): self
    {
        $parsed = [];
        foreach ($params as $key => $value) {
            $parsed[$key] = $this->parseValue($value);
        }
        $this->evaluator->setParameters($parsed);
        return $this;
    }

    /**
     * Set a single parameter
     * @param string $name Parameter name
     * @param mixed $value Parameter value
     * @return self
     */
    public function setParameter(string $name, mixed $value): self
    {
        $params = $this->evaluator->getParameters();
        $params[$name] = $this->parseValue($value);
        $this->evaluator->setParameters($params);
        return $this;
    }

    // ==================== PUBLIC METHODS ====================

    /**
     * Parse the source code and return the AST
     * @param string $source Source code
     * @return array AST
     */
    public function parse(string $source): array
    {
        if ($this->maxSourceLength > 0 && strlen($source) > $this->maxSourceLength) {
            throw new \Exception("Expression too long: maximum {$this->maxSourceLength} characters");
        }

        $this->parser->resetNodeId();
        $tokens = $this->lexer->tokenize($source);
        $ast = $this->parser->parseTokens($tokens);
        $this->validateAstSecurity($ast);
        return $ast;
    }

    /**
     * Returns the list of operations in execution order
     * @param array|string $ast AST tree or string to parse
     * @return array List of operations in order
     */
    public function getOperationOrder(array|string $ast): array
    {
        if (is_string($ast)) {
            $ast = $this->parse($ast);
        }

        $operations = [];
        $order = 1;

        $nodeToString = function (array $node) use (&$nodeToString): string {
            if (empty($node)) return '';

            switch ($node['type']) {
                case TokenType::NODE_NUMBER:
                    return (string)$node['value'];
                case TokenType::NODE_DATE:
                    return $node['value'];
                case TokenType::NODE_STRING:
                    return '"' . $node['value'] . '"';
                case TokenType::NODE_IDENTIFIER:
                    return $node['value'];
                case TokenType::NODE_PARAMETER:
                    return '[' . $node['value'] . ']';
                case TokenType::NODE_FUNCTION_CALL:
                    $argStrs = array_map($nodeToString, $node['arguments'] ?? []);
                    return $node['value'] . '(' . implode(', ', $argStrs) . ')';
                case TokenType::NODE_BINARY_OP:
                    return '(' . $nodeToString($node['left']) . ' ' . $node['value'] . ' ' . $nodeToString($node['right']) . ')';
                case TokenType::NODE_UNARY_OP:
                    return $node['value'] . '(' . $nodeToString($node['right']) . ')';
                default:
                    return '?';
            }
        };

        $traverse = function (array $node, int $depth = 0) use (&$traverse, &$operations, &$order, $nodeToString): void {
            if (empty($node)) return;

            if ($node['type'] === TokenType::NODE_PROGRAM) {
                foreach ($node['statements'] ?? [] as $stmt) {
                    $traverse($stmt, $depth);
                }
                return;
            }

            if ($node['type'] === TokenType::NODE_FUNCTION_CALL) {
                foreach ($node['arguments'] ?? [] as $arg) {
                    $traverse($arg, $depth + 1);
                }
                $operations[] = [
                    'order' => $order++,
                    'operation' => 'FUNCTION',
                    'nodeId' => $node['id'],
                    'depth' => $depth,
                    'function' => $node['value'],
                    'description' => $nodeToString($node)
                ];
            } elseif ($node['type'] === TokenType::NODE_BINARY_OP) {
                $traverse($node['left'], $depth + 1);
                $traverse($node['right'], $depth + 1);
                $operations[] = [
                    'order' => $order++,
                    'operation' => $node['value'],
                    'nodeId' => $node['id'],
                    'depth' => $depth,
                    'left' => $nodeToString($node['left']),
                    'right' => $nodeToString($node['right']),
                    'description' => $nodeToString($node['left']) . ' ' . $node['value'] . ' ' . $nodeToString($node['right'])
                ];
            } elseif ($node['type'] === TokenType::NODE_UNARY_OP) {
                $traverse($node['right'], $depth + 1);
                $operations[] = [
                    'order' => $order++,
                    'operation' => $node['value'],
                    'nodeId' => $node['id'],
                    'depth' => $depth,
                    'operand' => $nodeToString($node['right']),
                    'description' => $node['value'] . '(' . $nodeToString($node['right']) . ')'
                ];
            } elseif ($node['type'] === TokenType::NODE_ASSIGNMENT) {
                $traverse($node['right'], $depth + 1);
                $operations[] = [
                    'order' => $order++,
                    'operation' => '=',
                    'nodeId' => $node['id'],
                    'depth' => $depth,
                    'variable' => $node['value'],
                    'value' => $nodeToString($node['right']),
                    'description' => $node['value'] . ' = ' . $nodeToString($node['right'])
                ];
            } elseif ($node['type'] === TokenType::NODE_IF_STATEMENT) {
                $operations[] = [
                    'order' => $order++,
                    'operation' => 'IF',
                    'nodeId' => $node['id'],
                    'depth' => $depth,
                    'description' => 'IF (evaluate condition)'
                ];
                $traverse($node['condition'], $depth + 1);
                foreach ($node['thenBranch'] ?? [] as $stmt) {
                    $traverse($stmt, $depth + 1);
                }
                foreach ($node['elseBranch'] ?? [] as $stmt) {
                    $traverse($stmt, $depth + 1);
                }
            }
        };

        $traverse($ast);
        return $operations;
    }

    /**
     * Display the AST tree as formatted string
     * @param array|string $ast AST tree or string to parse
     * @param string $indent Current indentation
     * @param bool $isLast If it is the last child
     * @return string Text representation of the tree
     */
    public function visualizeTree(array|string $ast, string $indent = '', bool $isLast = true): string
    {
        if (is_string($ast)) {
            $ast = $this->parse($ast);
        }

        $result = '';
        $connector = $isLast ? '└── ' : '├── ';
        $childIndent = $indent . ($isLast ? '    ' : '│   ');

        if ($ast['type'] === TokenType::NODE_PROGRAM) {
            $result .= "PROGRAM\n";
            $stmts = $ast['statements'] ?? [];
            foreach ($stmts as $i => $stmt) {
                $result .= $this->visualizeTree($stmt, '', $i === count($stmts) - 1);
            }
            return $result;
        }

        $result .= $indent . $connector;

        switch ($ast['type']) {
            case TokenType::NODE_NUMBER:
                $result .= "NUM({$ast['value']})\n";
                break;
            case TokenType::NODE_DATE:
                $result .= "DATE({$ast['value']})\n";
                break;
            case TokenType::NODE_STRING:
                $result .= "STR(\"{$ast['value']}\")\n";
                break;
            case TokenType::NODE_IDENTIFIER:
                $result .= "VAR({$ast['value']})\n";
                break;
            case TokenType::NODE_PARAMETER:
                $result .= "PARAM[{$ast['value']}]\n";
                break;
            case TokenType::NODE_FUNCTION_CALL:
                $result .= "FUNC({$ast['value']}) [id:{$ast['id']}]\n";
                $args = $ast['arguments'] ?? [];
                foreach ($args as $i => $arg) {
                    $result .= $this->visualizeTree($arg, $childIndent, $i === count($args) - 1);
                }
                break;
            case TokenType::NODE_BINARY_OP:
                $result .= "OP({$ast['value']}) [id:{$ast['id']}]\n";
                if ($ast['left']) $result .= $this->visualizeTree($ast['left'], $childIndent, !$ast['right']);
                if ($ast['right']) $result .= $this->visualizeTree($ast['right'], $childIndent, true);
                break;
            case TokenType::NODE_UNARY_OP:
                $result .= "UNARY({$ast['value']}) [id:{$ast['id']}]\n";
                if ($ast['right']) $result .= $this->visualizeTree($ast['right'], $childIndent, true);
                break;
            case TokenType::NODE_ASSIGNMENT:
                $result .= "ASSIGN({$ast['value']}) [id:{$ast['id']}]\n";
                if ($ast['right']) $result .= $this->visualizeTree($ast['right'], $childIndent, true);
                break;
            case TokenType::NODE_IF_STATEMENT:
                $result .= "IF [id:{$ast['id']}]\n";
                $result .= $childIndent . "├── CONDITION:\n";
                if ($ast['condition']) $result .= $this->visualizeTree($ast['condition'], $childIndent . '│   ', true);
                $result .= $childIndent . "├── THEN:\n";
                $thenBranch = $ast['thenBranch'] ?? [];
                foreach ($thenBranch as $i => $stmt) {
                    $result .= $this->visualizeTree($stmt, $childIndent . '│   ', $i === count($thenBranch) - 1);
                }
                $elseBranch = $ast['elseBranch'] ?? [];
                if (!empty($elseBranch)) {
                    $result .= $childIndent . "└── ELSE:\n";
                    foreach ($elseBranch as $i => $stmt) {
                        $result .= $this->visualizeTree($stmt, $childIndent . '    ', $i === count($elseBranch) - 1);
                    }
                }
                break;
            default:
                $result .= "UNKNOWN({$ast['type']})\n";
        }

        return $result;
    }

    /**
     * Execute the AST and return the result
     * @param array|string $ast AST tree or string to parse
     * @return mixed Execution result
     */
    public function execute(array|string $ast): mixed
    {
        if (is_string($ast)) {
            $ast = $this->parse($ast);
        } else {
            $this->validateAstSecurity($ast);
        }

        return $this->evaluator->execute($ast);
    }

    /**
     * Complete method: parse, analyze and optionally execute
     * @param string $source Source code
     * @param bool $executeCode If true, execute the code
     * @return array ['ast', 'operations', 'tree', 'result'?, 'error'?]
     */
    public function analyze(string $source, bool $executeCode = true): array
    {
        $ast = $this->parse($source);
        $operations = $this->getOperationOrder($ast);
        $tree = $this->visualizeTree($ast);

        $result = [
            'ast' => $ast,
            'operations' => $operations,
            'tree' => $tree,
            'source' => $source
        ];

        if ($executeCode) {
            try {
                $result['result'] = $this->execute($ast);
            } catch (\Exception $e) {
                $result['error'] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Reset variables
     * @return self
     */
    public function reset(): self
    {
        $this->evaluator->resetVariables();
        return $this;
    }

    /**
     * Complete reset (variables and parameters)
     * @return self
     */
    public function resetAll(): self
    {
        $this->evaluator->resetAll();
        return $this;
    }

    /**
     * Returns current variables
     * @return array
     */
    public function getVariables(): array
    {
        return $this->evaluator->getVariables();
    }

    /**
     * Returns current parameters
     * @return array
     */
    public function getParameters(): array
    {
        return $this->evaluator->getParameters();
    }

    /**
     * Returns the list of available builtin functions
     * @return array
     */
    public function getBuiltinFunctions(): array
    {
        return $this->evaluator->getBuiltinFunctions()->getRegistry();
    }

    private function validateAstSecurity(array $ast): void
    {
        $stack = [[$ast, 1]];
        $nodeCount = 0;

        while (!empty($stack)) {
            [$node, $depth] = array_pop($stack);
            if (!is_array($node) || empty($node)) {
                continue;
            }

            $nodeCount++;
            if ($this->maxAstNodes > 0 && $nodeCount > $this->maxAstNodes) {
                throw new \Exception("Expression too complex: exceeded AST nodes limit ({$this->maxAstNodes})");
            }
            if ($this->maxAstDepth > 0 && $depth > $this->maxAstDepth) {
                throw new \Exception("Expression too nested: maximum depth {$this->maxAstDepth}");
            }

            $type = (string)($node['type'] ?? '');
            if (!$this->allowAssignments && $type === TokenType::NODE_ASSIGNMENT) {
                throw new \Exception('Assignments are not allowed in this mode');
            }

            if ($this->allowedFunctions !== null && $type === TokenType::NODE_FUNCTION_CALL) {
                $fn = strtoupper((string)($node['value'] ?? ''));
                if (!isset($this->allowedFunctions[$fn])) {
                    throw new \Exception("Function not allowed in this mode: {$fn}");
                }
            }

            switch ($type) {
                case TokenType::NODE_PROGRAM:
                    foreach (($node['statements'] ?? []) as $child) {
                        if (is_array($child)) {
                            $stack[] = [$child, $depth + 1];
                        }
                    }
                    break;
                case TokenType::NODE_BINARY_OP:
                    if (is_array($node['left'] ?? null)) {
                        $stack[] = [$node['left'], $depth + 1];
                    }
                    if (is_array($node['right'] ?? null)) {
                        $stack[] = [$node['right'], $depth + 1];
                    }
                    break;
                case TokenType::NODE_UNARY_OP:
                case TokenType::NODE_ASSIGNMENT:
                    if (is_array($node['right'] ?? null)) {
                        $stack[] = [$node['right'], $depth + 1];
                    }
                    break;
                case TokenType::NODE_IF_STATEMENT:
                    if (is_array($node['condition'] ?? null)) {
                        $stack[] = [$node['condition'], $depth + 1];
                    }
                    foreach (($node['thenBranch'] ?? []) as $child) {
                        if (is_array($child)) {
                            $stack[] = [$child, $depth + 1];
                        }
                    }
                    foreach (($node['elseBranch'] ?? []) as $child) {
                        if (is_array($child)) {
                            $stack[] = [$child, $depth + 1];
                        }
                    }
                    break;
                case TokenType::NODE_FUNCTION_CALL:
                    foreach (($node['arguments'] ?? []) as $arg) {
                        if (is_array($arg)) {
                            $stack[] = [$arg, $depth + 1];
                        }
                    }
                    break;
            }
        }
    }
}
