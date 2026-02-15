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
 * ExpressionParser - Parser per mini linguaggio di programmazione
 * PHP version - Comportamento identico alla versione JavaScript
 *
 * Facade class che mantiene la retrocompatibilità al 100%.
 * Internamente delega a: Lexer, Parser, Evaluator, BuiltinFunctions.
 *
 * Supporta:
 * - Operazioni matematiche: +, -, *, /, %, ^ (potenza)
 * - Operatori di confronto: ==, <>, !=, <, >, <=, >=
 * - Operatori logici: AND, OR, NOT (o &&, ||, !)
 * - Parentesi: ()
 * - Variabili e assegnazioni: a = 5
 * - Parametri: [salary] prende valore dai parametri impostati
 * - IF statements: IF condizione THEN ... ELSE ... ENDIF
 * - Date: 2025-01-29 o 29/01/2025
 * - Funzioni: NOW(), AGE(date), ROUND(n, decimals), ABS(n), IFNULL(val, default),
 *             UPPER(str), LOWER(str), CONCAT(str1, str2, ...), TRIM(str),
 *             ISEMPTY(val), PRECISION(n, decimals), DATEONLY(datetime),
 *             TIMEADD(time, minutes), ADDMINUTES(time, minutes)
 */
class ExpressionParser
{
    use ValueHelper;

    // ==================== COSTANTI TOKEN (retrocompatibilità) ====================
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

    // ==================== COSTANTI NODO AST (retrocompatibilità) ====================
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

    // ==================== COMPONENTI INTERNI ====================
    private Lexer $lexer;
    private Parser $parser;
    private Evaluator $evaluator;

    public function __construct()
    {
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->evaluator = new Evaluator();
    }

    // ==================== ACCESSO AI COMPONENTI (per estensibilità) ====================

    /**
     * Restituisce il Lexer interno
     */
    public function getLexer(): Lexer
    {
        return $this->lexer;
    }

    /**
     * Restituisce il Parser interno
     */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    /**
     * Restituisce l'Evaluator interno
     */
    public function getEvaluator(): Evaluator
    {
        return $this->evaluator;
    }

    // ==================== GESTIONE PARAMETRI ====================

    /**
     * Imposta i parametri da un array associativo
     * @param array $params Array associativo [nome => valore]
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
     * Imposta un singolo parametro
     * @param string $name Nome del parametro
     * @param mixed $value Valore del parametro
     * @return self
     */
    public function setParameter(string $name, mixed $value): self
    {
        $params = $this->evaluator->getParameters();
        $params[$name] = $this->parseValue($value);
        $this->evaluator->setParameters($params);
        return $this;
    }

    // ==================== METODI PUBBLICI ====================

    /**
     * Parsa il codice sorgente e restituisce l'AST
     * @param string $source Codice sorgente
     * @return array AST
     */
    public function parse(string $source): array
    {
        $this->parser->resetNodeId();
        $tokens = $this->lexer->tokenize($source);
        return $this->parser->parseTokens($tokens);
    }

    /**
     * Restituisce l'elenco delle operazioni nell'ordine di esecuzione
     * @param array|string $ast Albero AST o stringa da parsare
     * @return array Lista operazioni in ordine
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
                    'description' => 'IF (valuta condizione)'
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
     * Visualizza l'albero AST come stringa formattata
     * @param array|string $ast Albero AST o stringa da parsare
     * @param string $indent Indentazione corrente
     * @param bool $isLast Se è l'ultimo figlio
     * @return string Rappresentazione testuale dell'albero
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
     * Esegue l'AST e restituisce il risultato
     * @param array|string $ast Albero AST o stringa da parsare
     * @return mixed Risultato dell'esecuzione
     */
    public function execute(array|string $ast): mixed
    {
        if (is_string($ast)) {
            $ast = $this->parse($ast);
        }

        return $this->evaluator->execute($ast);
    }

    /**
     * Metodo completo: parsa, analizza e opzionalmente esegue
     * @param string $source Codice sorgente
     * @param bool $executeCode Se true, esegue il codice
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
     * Reset delle variabili
     * @return self
     */
    public function reset(): self
    {
        $this->evaluator->resetVariables();
        return $this;
    }

    /**
     * Reset completo (variabili e parametri)
     * @return self
     */
    public function resetAll(): self
    {
        $this->evaluator->resetAll();
        return $this;
    }

    /**
     * Restituisce le variabili correnti
     * @return array
     */
    public function getVariables(): array
    {
        return $this->evaluator->getVariables();
    }

    /**
     * Restituisce i parametri correnti
     * @return array
     */
    public function getParameters(): array
    {
        return $this->evaluator->getParameters();
    }

    /**
     * Restituisce la lista delle funzioni builtin disponibili
     * @return array
     */
    public function getBuiltinFunctions(): array
    {
        return $this->evaluator->getBuiltinFunctions()->getRegistry();
    }
}
