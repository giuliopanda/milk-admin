<?php
namespace App;

!defined('MILK_DIR') && die();

/**
 * ExpressionParser - Parser per mini linguaggio di programmazione
 * PHP version - Comportamento identico alla versione JavaScript
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
    // ==================== COSTANTI TOKEN ====================
    const TOKEN_NUMBER = 'NUMBER';
    const TOKEN_STRING = 'STRING';
    const TOKEN_DATE = 'DATE';
    const TOKEN_IDENTIFIER = 'IDENTIFIER';
    const TOKEN_PARAMETER = 'PARAMETER';
    const TOKEN_PLUS = 'PLUS';
    const TOKEN_MINUS = 'MINUS';
    const TOKEN_MULTIPLY = 'MULTIPLY';
    const TOKEN_DIVIDE = 'DIVIDE';
    const TOKEN_MODULO = 'MODULO';
    const TOKEN_POWER = 'POWER';
    const TOKEN_LPAREN = 'LPAREN';
    const TOKEN_RPAREN = 'RPAREN';
    const TOKEN_COMMA = 'COMMA';
    const TOKEN_ASSIGN = 'ASSIGN';
    const TOKEN_EQ = 'EQ';
    const TOKEN_NEQ = 'NEQ';
    const TOKEN_LT = 'LT';
    const TOKEN_GT = 'GT';
    const TOKEN_LTE = 'LTE';
    const TOKEN_GTE = 'GTE';
    const TOKEN_AND = 'AND';
    const TOKEN_OR = 'OR';
    const TOKEN_NOT = 'NOT';
    const TOKEN_IF = 'IF';
    const TOKEN_THEN = 'THEN';
    const TOKEN_ELSE = 'ELSE';
    const TOKEN_ENDIF = 'ENDIF';
    const TOKEN_NEWLINE = 'NEWLINE';
    const TOKEN_EOF = 'EOF';

    // ==================== COSTANTI NODO AST ====================
    const NODE_NUMBER = 'NUMBER';
    const NODE_STRING = 'STRING';
    const NODE_DATE = 'DATE';
    const NODE_IDENTIFIER = 'IDENTIFIER';
    const NODE_PARAMETER = 'PARAMETER';
    const NODE_BINARY_OP = 'BINARY_OP';
    const NODE_UNARY_OP = 'UNARY_OP';
    const NODE_ASSIGNMENT = 'ASSIGNMENT';
    const NODE_IF_STATEMENT = 'IF_STATEMENT';
    const NODE_FUNCTION_CALL = 'FUNCTION_CALL';
    const NODE_PROGRAM = 'PROGRAM';

    private array $variables = [];
    private array $parameters = [];
    private int $nodeId = 0;

    // Funzioni builtin disponibili
    private array $builtinFunctions = [
        'NOW', 'AGE', 'ROUND', 'ABS', 'IFNULL',
        'UPPER', 'LOWER', 'CONCAT', 'TRIM', 'ISEMPTY',
        'PRECISION', 'DATEONLY', 'TIMEADD', 'ADDMINUTES'
    ];

    // ==================== GESTIONE PARAMETRI ====================

    /**
     * Imposta i parametri da un array associativo
     * @param array $params Array associativo [nome => valore]
     * @return self
     */
    public function setParameters(array $params): self
    {
        $this->parameters = [];
        foreach ($params as $key => $value) {
            $this->parameters[$key] = $this->parseValue($value);
        }
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
        $this->parameters[$name] = $this->parseValue($value);
        return $this;
    }

    /**
     * Converte un valore nel tipo appropriato
     */
    private function parseValue(mixed $value): mixed
    {
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        // Prova a convertire in numero
        if (is_numeric($trimmed)) {
            return strpos($trimmed, '.') !== false ? (float)$trimmed : (int)$trimmed;
        }

        // Prova a convertire in data ISO (YYYY-MM-DD)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            $date = \DateTime::createFromFormat('Y-m-d', $trimmed);
            if ($date !== false) {
                $date->setTime(0, 0, 0);
                return $date;
            }
        }

        // Prova a convertire in data europea (DD/MM/YYYY)
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $trimmed)) {
            $date = \DateTime::createFromFormat('d/m/Y', $trimmed);
            if ($date !== false) {
                $date->setTime(0, 0, 0);
                return $date;
            }
        }

        return $trimmed;
    }

    /**
     * Normalizza un valore per checkbox
     * @param mixed $value
     * @return bool
     */
    public function normalizeCheckboxValue(mixed $value): bool
    {
        if ($value === true) {
            return true;
        }
        if ($value === false || $value === null) {
            return false;
        }

        if (is_numeric($value)) {
            return (string)$value === '1';
        }

        if (is_string($value)) {
            $trimmed = strtolower(trim($value));
            if ($trimmed === '' || $trimmed === '0' || $trimmed === 'false') {
                return false;
            }
            if ($trimmed === '1' || $trimmed === 'true' || $trimmed === 'on' || $trimmed === 'yes') {
                return true;
            }
            return $trimmed !== '';
        }

        return (bool)$value;
    }

    /**
     * Restituisce il valore coerente per checkbox (checked/unchecked)
     * @param mixed $value
     * @param mixed $checkedValue
     * @param mixed $uncheckedValue
     * @param bool $nullable
     * @param string|null $type
     * @return mixed
     */
    public function formatCheckboxValue(
        mixed $value,
        mixed $checkedValue = null,
        mixed $uncheckedValue = null,
        bool $nullable = false,
        ?string $type = null
    ): bool {
        // NOTE: Allineato al comportamento JS (expression-parser.js):
        // per i checkbox il calc_expr deve produrre SEMPRE un boolean.
        // Eventuali mapping (es. S/N) vanno gestiti dal chiamante.
        return $this->normalizeCheckboxValue($value);
    }

    // ==================== LEXER (Tokenizzatore) ====================

    private function tokenize(string $input): array
    {
        $tokens = [];
        $pos = 0;
        $line = 1;
        $column = 1;
        $length = strlen($input);

        $peek = function (int $offset = 0) use (&$pos, $input, $length): ?string {
            $idx = $pos + $offset;
            return $idx < $length ? $input[$idx] : null;
        };

        $advance = function () use (&$pos, &$line, &$column, $input, $length): ?string {
            if ($pos >= $length) return null;
            $char = $input[$pos++];
            if ($char === "\n") {
                $line++;
                $column = 1;
            } else {
                $column++;
            }
            return $char;
        };

        $skipWhitespace = function () use ($peek, $advance): void {
            while ($peek() !== null && preg_match('/[ \t\r]/', $peek())) {
                $advance();
            }
        };

        $readNumber = function () use (&$pos, &$line, &$column, $peek, $advance, $input): array {
            $num = '';
            $startCol = $column;
            $startPos = $pos;
            $startLine = $line;

            // Leggi cifre iniziali
            while ($peek() !== null && ctype_digit($peek())) {
                $num .= $advance();
            }

            // Controlla se è una data YYYY-MM-DD
            if ($peek() === '-' && strlen($num) === 4) {
                $savedPos = $pos;
                $savedCol = $column;
                $savedLine = $line;

                $dateStr = $num;
                $dateStr .= $advance(); // -

                $month = '';
                while ($peek() !== null && ctype_digit($peek())) {
                    $month .= $advance();
                }

                if (strlen($month) === 2 && $peek() === '-') {
                    $dateStr .= $month;
                    $dateStr .= $advance(); // -

                    $day = '';
                    while ($peek() !== null && ctype_digit($peek())) {
                        $day .= $advance();
                    }

                    if (strlen($day) === 2) {
                        $dateStr .= $day;
                        $date = \DateTime::createFromFormat('Y-m-d', $dateStr);
                        if ($date !== false) {
                            return [
                                'type' => self::TOKEN_DATE,
                                'value' => $dateStr,
                                'line' => $startLine,
                                'column' => $startCol
                            ];
                        }
                    }
                }

                // Non è una data valida, torna indietro
                $pos = $savedPos;
                $column = $savedCol;
                $line = $savedLine;
            }

            // Controlla se è una data DD/MM/YYYY
            if ($peek() === '/' && (strlen($num) === 1 || strlen($num) === 2)) {
                $savedPos = $pos;
                $savedCol = $column;
                $savedLine = $line;

                $day = $num;
                $advance(); // /

                $month = '';
                while ($peek() !== null && ctype_digit($peek())) {
                    $month .= $advance();
                }

                if ((strlen($month) === 1 || strlen($month) === 2) && $peek() === '/') {
                    $advance(); // /

                    $year = '';
                    while ($peek() !== null && ctype_digit($peek())) {
                        $year .= $advance();
                    }

                    if (strlen($year) === 4) {
                        $isoDate = sprintf('%s-%s-%s', $year, str_pad($month, 2, '0', STR_PAD_LEFT), str_pad($day, 2, '0', STR_PAD_LEFT));
                        $date = \DateTime::createFromFormat('Y-m-d', $isoDate);
                        if ($date !== false) {
                            return [
                                'type' => self::TOKEN_DATE,
                                'value' => $isoDate,
                                'line' => $startLine,
                                'column' => $startCol
                            ];
                        }
                    }
                }

                // Non è una data valida, torna indietro
                $pos = $savedPos;
                $column = $savedCol;
                $line = $savedLine;
            }

            // Leggi parte decimale se presente
            if ($peek() === '.') {
                $num .= $advance();
                while ($peek() !== null && ctype_digit($peek())) {
                    $num .= $advance();
                }
            }

            return [
                'type' => self::TOKEN_NUMBER,
                'value' => strpos($num, '.') !== false ? (float)$num : (int)$num,
                'line' => $startLine,
                'column' => $startCol
            ];
        };

        $readString = function () use ($peek, $advance, &$column, &$line): array {
            $quote = $advance();
            $str = '';
            $startCol = $column;
            $startLine = $line;

            while ($peek() !== null && $peek() !== $quote) {
                if ($peek() === '\\') {
                    $advance();
                    $str .= $advance();
                } else {
                    $str .= $advance();
                }
            }

            if ($peek() === $quote) {
                $advance();
            }

            return [
                'type' => self::TOKEN_STRING,
                'value' => $str,
                'line' => $startLine,
                'column' => $startCol
            ];
        };

        $readIdentifier = function () use ($peek, $advance, &$column, &$line): array {
            $id = '';
            $startCol = $column;
            $startLine = $line;

            while ($peek() !== null && preg_match('/[a-zA-Z0-9_]/', $peek())) {
                $id .= $advance();
            }

            $keywords = [
                'IF' => self::TOKEN_IF, 'if' => self::TOKEN_IF,
                'THEN' => self::TOKEN_THEN, 'then' => self::TOKEN_THEN,
                'ELSE' => self::TOKEN_ELSE, 'else' => self::TOKEN_ELSE,
                'ENDIF' => self::TOKEN_ENDIF, 'endif' => self::TOKEN_ENDIF,
                'AND' => self::TOKEN_AND, 'and' => self::TOKEN_AND,
                'OR' => self::TOKEN_OR, 'or' => self::TOKEN_OR,
                'NOT' => self::TOKEN_NOT, 'not' => self::TOKEN_NOT
            ];

            return [
                'type' => $keywords[$id] ?? self::TOKEN_IDENTIFIER,
                'value' => $id,
                'line' => $startLine,
                'column' => $startCol
            ];
        };

        $readParameter = function () use ($peek, $advance, &$column, &$line): array {
            $advance(); // [
            $param = '';
            $startCol = $column;
            $startLine = $line;

            while ($peek() !== null && $peek() !== ']') {
                $param .= $advance();
            }

            if ($peek() === ']') {
                $advance();
            }

            return [
                'type' => self::TOKEN_PARAMETER,
                'value' => trim($param),
                'line' => $startLine,
                'column' => $startCol
            ];
        };

        while ($pos < $length) {
            $skipWhitespace();
            if ($pos >= $length) break;

            $char = $peek();
            $startCol = $column;
            $startLine = $line;

            if (ctype_digit($char)) {
                $tokens[] = $readNumber();
            } elseif ($char === '"' || $char === "'") {
                $tokens[] = $readString();
            } elseif ($char === '[') {
                $tokens[] = $readParameter();
            } elseif (preg_match('/[a-zA-Z_]/', $char)) {
                $tokens[] = $readIdentifier();
            } elseif ($char === ',') {
                $advance();
                $tokens[] = ['type' => self::TOKEN_COMMA, 'value' => ',', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '+') {
                $advance();
                $tokens[] = ['type' => self::TOKEN_PLUS, 'value' => '+', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '-') {
                $advance();
                $tokens[] = ['type' => self::TOKEN_MINUS, 'value' => '-', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '*') {
                $advance();
                $tokens[] = ['type' => self::TOKEN_MULTIPLY, 'value' => '*', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '/') {
                $advance();
                $tokens[] = ['type' => self::TOKEN_DIVIDE, 'value' => '/', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '%') {
                $advance();
                $tokens[] = ['type' => self::TOKEN_MODULO, 'value' => '%', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '^') {
                $advance();
                $tokens[] = ['type' => self::TOKEN_POWER, 'value' => '^', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '(') {
                $advance();
                $tokens[] = ['type' => self::TOKEN_LPAREN, 'value' => '(', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === ')') {
                $advance();
                $tokens[] = ['type' => self::TOKEN_RPAREN, 'value' => ')', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '=') {
                $advance();
                if ($peek() === '=') {
                    $advance();
                    $tokens[] = ['type' => self::TOKEN_EQ, 'value' => '==', 'line' => $startLine, 'column' => $startCol];
                } else {
                    $tokens[] = ['type' => self::TOKEN_ASSIGN, 'value' => '=', 'line' => $startLine, 'column' => $startCol];
                }
            } elseif ($char === '<') {
                $advance();
                if ($peek() === '=') {
                    $advance();
                    $tokens[] = ['type' => self::TOKEN_LTE, 'value' => '<=', 'line' => $startLine, 'column' => $startCol];
                } elseif ($peek() === '>') {
                    $advance();
                    $tokens[] = ['type' => self::TOKEN_NEQ, 'value' => '<>', 'line' => $startLine, 'column' => $startCol];
                } else {
                    $tokens[] = ['type' => self::TOKEN_LT, 'value' => '<', 'line' => $startLine, 'column' => $startCol];
                }
            } elseif ($char === '>') {
                $advance();
                if ($peek() === '=') {
                    $advance();
                    $tokens[] = ['type' => self::TOKEN_GTE, 'value' => '>=', 'line' => $startLine, 'column' => $startCol];
                } else {
                    $tokens[] = ['type' => self::TOKEN_GT, 'value' => '>', 'line' => $startLine, 'column' => $startCol];
                }
            } elseif ($char === '!') {
                $advance();
                if ($peek() === '=') {
                    $advance();
                    $tokens[] = ['type' => self::TOKEN_NEQ, 'value' => '!=', 'line' => $startLine, 'column' => $startCol];
                } else {
                    $tokens[] = ['type' => self::TOKEN_NOT, 'value' => '!', 'line' => $startLine, 'column' => $startCol];
                }
            } elseif ($char === '&' && $peek(1) === '&') {
                $advance();
                $advance();
                $tokens[] = ['type' => self::TOKEN_AND, 'value' => '&&', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '|' && $peek(1) === '|') {
                $advance();
                $advance();
                $tokens[] = ['type' => self::TOKEN_OR, 'value' => '||', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === "\n") {
                $advance();
                $tokens[] = ['type' => self::TOKEN_NEWLINE, 'value' => '\\n', 'line' => $startLine, 'column' => $startCol];
            } else {
                $bad = $advance();
                $code = ord($bad);
                throw new \Exception(
                    "Carattere non riconosciuto '{$bad}' (ASCII {$code}) alla riga {$startLine}, colonna {$startCol}"
                );
            }
        }

        $tokens[] = ['type' => self::TOKEN_EOF, 'value' => null, 'line' => $line, 'column' => $column];
        return $tokens;
    }

    // ==================== PARSER (Costruisce AST) ====================

    private function createNode(string $type, mixed $value = null): array
    {
        return [
            'type' => $type,
            'value' => $value,
            'left' => null,
            'right' => null,
            'condition' => null,
            'thenBranch' => null,
            'elseBranch' => null,
            'statements' => null,
            'arguments' => null,
            'id' => ++$this->nodeId
        ];
    }

    private function parseTokens(array $tokens): array
    {
        $pos = 0;

        $peek = function () use (&$pos, $tokens): array {
            return $tokens[$pos] ?? ['type' => self::TOKEN_EOF, 'value' => null, 'line' => 0, 'column' => 0];
        };

        $advance = function () use (&$pos, $tokens): array {
            return $tokens[$pos++];
        };

        $expect = function (string $type) use ($peek, $advance): array {
            $token = $peek();
            if ($token['type'] === $type) {
                return $advance();
            }
            throw new \Exception("Atteso {$type} ma trovato {$token['type']} alla riga {$token['line']}");
        };

        $skipNewlines = function () use ($peek, $advance): void {
            while ($peek()['type'] === self::TOKEN_NEWLINE) {
                $advance();
            }
        };

        // Funzioni di parsing con precedenza
        $parseExpression = function () use (&$parseOr): array {
            return $parseOr();
        };

        $parseOr = function () use (&$parseAnd, $peek, $advance, &$parseOr): array {
            $parseAnd = $parseAnd ?? function () { return []; };
            $left = $parseAnd();

            while ($peek()['type'] === self::TOKEN_OR) {
                $op = $advance();
                $node = $this->createNode(self::NODE_BINARY_OP, $op['value']);
                $node['left'] = $left;
                $node['right'] = $parseAnd();
                $left = $node;
            }

            return $left;
        };

        $parseAnd = function () use (&$parseComparison, $peek, $advance): array {
            $left = $parseComparison();

            while ($peek()['type'] === self::TOKEN_AND) {
                $op = $advance();
                $node = $this->createNode(self::NODE_BINARY_OP, $op['value']);
                $node['left'] = $left;
                $node['right'] = $parseComparison();
                $left = $node;
            }

            return $left;
        };

        $parseComparison = function () use (&$parseAdditive, $peek, $advance): array {
            $left = $parseAdditive();

            $compOps = [self::TOKEN_EQ, self::TOKEN_NEQ, self::TOKEN_LT, self::TOKEN_GT, self::TOKEN_LTE, self::TOKEN_GTE];

            while (in_array($peek()['type'], $compOps)) {
                $op = $advance();
                $node = $this->createNode(self::NODE_BINARY_OP, $op['value']);
                $node['left'] = $left;
                $node['right'] = $parseAdditive();
                $left = $node;
            }

            return $left;
        };

        $parseAdditive = function () use (&$parseMultiplicative, $peek, $advance): array {
            $left = $parseMultiplicative();

            while (in_array($peek()['type'], [self::TOKEN_PLUS, self::TOKEN_MINUS])) {
                $op = $advance();
                $node = $this->createNode(self::NODE_BINARY_OP, $op['value']);
                $node['left'] = $left;
                $node['right'] = $parseMultiplicative();
                $left = $node;
            }

            return $left;
        };

        $parseMultiplicative = function () use (&$parsePower, $peek, $advance): array {
            $left = $parsePower();

            while (in_array($peek()['type'], [self::TOKEN_MULTIPLY, self::TOKEN_DIVIDE, self::TOKEN_MODULO])) {
                $op = $advance();
                $node = $this->createNode(self::NODE_BINARY_OP, $op['value']);
                $node['left'] = $left;
                $node['right'] = $parsePower();
                $left = $node;
            }

            return $left;
        };

        $parsePower = function () use (&$parseUnary, $peek, $advance, &$parsePower): array {
            $left = $parseUnary();

            if ($peek()['type'] === self::TOKEN_POWER) {
                $op = $advance();
                $node = $this->createNode(self::NODE_BINARY_OP, $op['value']);
                $node['left'] = $left;
                $node['right'] = $parsePower(); // Associatività a destra
                return $node;
            }

            return $left;
        };

        $parseUnary = function () use (&$parsePrimary, $peek, $advance, &$parseUnary): array {
            if (in_array($peek()['type'], [self::TOKEN_MINUS, self::TOKEN_NOT])) {
                $op = $advance();
                $node = $this->createNode(self::NODE_UNARY_OP, $op['value']);
                $node['right'] = $parseUnary();
                return $node;
            }

            return $parsePrimary();
        };

        $parsePrimary = function () use ($peek, $advance, $expect, &$parseExpression): array {
            $token = $peek();

            if ($token['type'] === self::TOKEN_NUMBER) {
                $advance();
                return $this->createNode(self::NODE_NUMBER, $token['value']);
            }
            if ($token['type'] === self::TOKEN_DATE) {
                $advance();
                return $this->createNode(self::NODE_DATE, $token['value']);
            }
            if ($token['type'] === self::TOKEN_STRING) {
                $advance();
                return $this->createNode(self::NODE_STRING, $token['value']);
            }
            if ($token['type'] === self::TOKEN_IDENTIFIER) {
                $idToken = $advance();
                
                // Controlla se è una funzione
                if ($peek()['type'] === self::TOKEN_LPAREN) {
                    $advance(); // (
                    
                    // Leggi argomenti
                    $args = [];
                    if ($peek()['type'] !== self::TOKEN_RPAREN) {
                        $args[] = $parseExpression();
                        while ($peek()['type'] === self::TOKEN_COMMA) {
                            $advance(); // ,
                            $args[] = $parseExpression();
                        }
                    }
                    
                    $expect(self::TOKEN_RPAREN);
                    
                    $node = $this->createNode(self::NODE_FUNCTION_CALL, strtoupper($idToken['value']));
                    $node['arguments'] = $args;
                    return $node;
                }
                
                return $this->createNode(self::NODE_IDENTIFIER, $idToken['value']);
            }
            if ($token['type'] === self::TOKEN_PARAMETER) {
                $advance();
                return $this->createNode(self::NODE_PARAMETER, $token['value']);
            }
            if ($token['type'] === self::TOKEN_LPAREN) {
                $advance();
                $expr = $parseExpression();
                $expect(self::TOKEN_RPAREN);
                return $expr;
            }

            throw new \Exception("Token inatteso: {$token['type']} alla riga {$token['line']}");
        };

        $parseStatement = function () use ($peek, $advance, $expect, &$parseExpression, &$parseIfStatement, &$parseAssignment, $tokens, &$pos): array {
            $token = $peek();

            if ($token['type'] === self::TOKEN_IF) {
                return $parseIfStatement();
            }

            if ($token['type'] === self::TOKEN_IDENTIFIER && isset($tokens[$pos + 1]) && $tokens[$pos + 1]['type'] === self::TOKEN_ASSIGN) {
                return $parseAssignment();
            }

            return $parseExpression();
        };

        $parseAssignment = function () use ($advance, $expect, &$parseExpression): array {
            $id = $advance();
            $expect(self::TOKEN_ASSIGN);
            $node = $this->createNode(self::NODE_ASSIGNMENT, $id['value']);
            $node['right'] = $parseExpression();
            return $node;
        };

        $parseIfStatement = function () use ($expect, $peek, $advance, $skipNewlines, &$parseExpression, &$parseStatement): array {
            $expect(self::TOKEN_IF);
            $node = $this->createNode(self::NODE_IF_STATEMENT);
            $node['condition'] = $parseExpression();

            if ($peek()['type'] === self::TOKEN_THEN) {
                $advance();
            }
            $skipNewlines();

            // THEN branch
            $thenStatements = [];
            while (!in_array($peek()['type'], [self::TOKEN_ELSE, self::TOKEN_ENDIF, self::TOKEN_EOF])) {
                $skipNewlines();
                if (in_array($peek()['type'], [self::TOKEN_ELSE, self::TOKEN_ENDIF, self::TOKEN_EOF])) break;
                $thenStatements[] = $parseStatement();
                $skipNewlines();
            }
            $node['thenBranch'] = $thenStatements;

            // ELSE branch (opzionale)
            if ($peek()['type'] === self::TOKEN_ELSE) {
                $advance();
                $skipNewlines();
                $elseStatements = [];
                while (!in_array($peek()['type'], [self::TOKEN_ENDIF, self::TOKEN_EOF])) {
                    $skipNewlines();
                    if (in_array($peek()['type'], [self::TOKEN_ENDIF, self::TOKEN_EOF])) break;
                    $elseStatements[] = $parseStatement();
                    $skipNewlines();
                }
                $node['elseBranch'] = $elseStatements;
            }

            if ($peek()['type'] === self::TOKEN_ENDIF) {
                $advance();
            }

            return $node;
        };

        // Parse programma
        $statements = [];
        while ($peek()['type'] !== self::TOKEN_EOF) {
            $skipNewlines();
            if ($peek()['type'] === self::TOKEN_EOF) break;
            $statements[] = $parseStatement();
        }

        $program = $this->createNode(self::NODE_PROGRAM);
        $program['statements'] = $statements;
        return $program;
    }

    // ==================== FUNZIONI BUILTIN ====================

    /**
     * NOW() - Restituisce data e ora corrente
     */
    private function func_NOW(array $args): \DateTime
    {
        if (!empty($args)) {
            throw new \Exception("NOW() non accetta argomenti");
        }
        return new \DateTime();
    }

    /**
     * AGE(birthdate) - Calcola l'età in anni da una data di nascita
     */
    private function func_AGE(array $args): int
    {
        if (count($args) !== 1) {
            throw new \Exception("AGE() richiede esattamente 1 argomento (data di nascita)");
        }

        $birthdate = $args[0];
        if (!($birthdate instanceof \DateTime)) {
            throw new \Exception("AGE() richiede una data come argomento");
        }

        $now = new \DateTime();
        $age = $now->diff($birthdate);
        return (int)$age->y;
    }

    /**
     * ROUND(number, decimals=0) - Arrotonda un numero
     */
    private function func_ROUND(array $args): float|int
    {
        if (count($args) < 1 || count($args) > 2) {
            throw new \Exception("ROUND() richiede 1 o 2 argomenti (numero, decimali opzionali)");
        }

        $number = $args[0];
        if (!is_numeric($number)) {
            throw new \Exception("ROUND() richiede un numero come primo argomento");
        }

        $decimals = isset($args[1]) ? (int)$args[1] : 0;
        
        $result = round((float)$number, $decimals);
        
        // Se decimals è 0, ritorna int
        return $decimals === 0 ? (int)$result : $result;
    }

    /**
     * ABS(number) - Valore assoluto
     */
    private function func_ABS(array $args): float|int
    {
        if (count($args) !== 1) {
            throw new \Exception("ABS() richiede esattamente 1 argomento");
        }

        $number = $args[0];
        if (!is_numeric($number)) {
            throw new \Exception("ABS() richiede un numero come argomento");
        }

        return abs($number);
    }

    /**
     * IFNULL(value, default) - Restituisce default se value è null
     */
    private function func_IFNULL(array $args): mixed
    {
        if (count($args) !== 2) {
            throw new \Exception("IFNULL() richiede esattamente 2 argomenti (valore, default)");
        }

        return $args[0] === null ? $args[1] : $args[0];
    }

    /**
     * UPPER(string) - Converte in maiuscolo
     */
    private function func_UPPER(array $args): string
    {
        if (count($args) !== 1) {
            throw new \Exception("UPPER() richiede esattamente 1 argomento");
        }

        $str = $args[0];
        if ($str === null) {
            return '';
        }

        return mb_strtoupper((string)$str, 'UTF-8');
    }

    /**
     * LOWER(string) - Converte in minuscolo
     */
    private function func_LOWER(array $args): string
    {
        if (count($args) !== 1) {
            throw new \Exception("LOWER() richiede esattamente 1 argomento");
        }

        $str = $args[0];
        if ($str === null) {
            return '';
        }

        return mb_strtolower((string)$str, 'UTF-8');
    }

    /**
     * CONCAT(str1, str2, ...) - Concatena stringhe
     */
    private function func_CONCAT(array $args): string
    {
        if (empty($args)) {
            return '';
        }

        $result = '';
        foreach ($args as $arg) {
            if ($arg === null) {
                continue; // Salta i null
            }
            if ($arg instanceof \DateTime) {
                $result .= $arg->format('Y-m-d H:i:s');
            } else {
                $result .= (string)$arg;
            }
        }

        return $result;
    }

    /**
     * TRIM(string) - Rimuove spazi iniziali e finali
     */
    private function func_TRIM(array $args): string
    {
        if (count($args) !== 1) {
            throw new \Exception("TRIM() richiede esattamente 1 argomento");
        }

        $str = $args[0];
        if ($str === null) {
            return '';
        }

        return trim((string)$str);
    }

    /**
     * ISEMPTY(value) - Verifica se vuoto o null
     * Ritorna true per: null, stringa vuota, stringa con solo spazi
     */
    private function func_ISEMPTY(array $args): bool
    {
        if (count($args) !== 1) {
            throw new \Exception("ISEMPTY() richiede esattamente 1 argomento");
        }

        $value = $args[0];

        // null è vuoto
        if ($value === null) {
            return true;
        }

        // Stringa vuota o solo spazi
        if (is_string($value) && trim($value) === '') {
            return true;
        }

        return false;
    }

    /**
     * PRECISION(number, decimals) - Forza un numero ad avere N decimali
     * Se decimals = 0, si comporta come FLOOR
     */
    private function func_PRECISION(array $args): float|int
    {
        if (count($args) !== 2) {
            throw new \Exception("PRECISION() richiede esattamente 2 argomenti (numero, decimali)");
        }

        $number = $args[0];
        if (!is_numeric($number)) {
            throw new \Exception("PRECISION() richiede un numero come primo argomento");
        }

        $decimals = (int)$args[1];

        // Se decimals è 0, comportamento FLOOR
        if ($decimals === 0) {
            return (int)floor((float)$number);
        }

        // Altrimenti formatta con N decimali
        return (float)number_format((float)$number, $decimals, '.', '');
    }

    /**
     * DATEONLY(datetime) - Rimuove ore, minuti, secondi da una data
     * Restituisce una nuova data con ore azzerate
     */
    private function func_DATEONLY(array $args): \DateTime
    {
        if (count($args) !== 1) {
            throw new \Exception("DATEONLY() richiede esattamente 1 argomento");
        }

        $date = $args[0];
        if (!($date instanceof \DateTime)) {
            throw new \Exception("DATEONLY() richiede una data come argomento");
        }

        // Crea una nuova istanza per non modificare l'originale
        $result = clone $date;
        $result->setTime(0, 0, 0, 0);
        return $result;
    }

    /**
     * TIMEADD(time, minutes) - Somma minuti ad un orario (HH:MM o HH:MM:SS)
     */
    private function func_TIMEADD(array $args): string
    {
        if (count($args) !== 2) {
            throw new \Exception("TIMEADD() richiede esattamente 2 argomenti (orario, minuti)");
        }

        [$hours, $minutes, $seconds, $hasSeconds] = $this->parseTimeValue($args[0]);

        $deltaMinutes = $args[1];
        if (!is_numeric($deltaMinutes)) {
            throw new \Exception("TIMEADD() richiede minuti numerici come secondo argomento");
        }

        $deltaMinutes = (int)round((float)$deltaMinutes);
        [$hours, $minutes, $seconds] = $this->addMinutesToTime($hours, $minutes, $seconds, $deltaMinutes);

        return $this->formatTimeValue($hours, $minutes, $seconds, $hasSeconds);
    }

    /**
     * ADDMINUTES(time, minutes) - Alias di TIMEADD
     */
    private function func_ADDMINUTES(array $args): string
    {
        return $this->func_TIMEADD($args);
    }

    /**
     * Parse orario (HH:MM o HH:MM:SS) o DateTime
     *
     * @return array [hours, minutes, seconds, hasSeconds]
     */
    private function parseTimeValue(mixed $value): array
    {
        if ($value instanceof \DateTime) {
            return [
                (int)$value->format('H'),
                (int)$value->format('i'),
                (int)$value->format('s'),
                true
            ];
        }

        if (!is_string($value)) {
            throw new \Exception("TIMEADD() richiede un orario come stringa o DateTime");
        }

        $trimmed = trim($value);
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $trimmed, $matches)) {
            throw new \Exception("TIMEADD() richiede orario nel formato HH:MM o HH:MM:SS");
        }

        $hours = (int)$matches[1];
        $minutes = (int)$matches[2];
        $seconds = isset($matches[3]) ? (int)$matches[3] : 0;

        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59 || $seconds < 0 || $seconds > 59) {
            throw new \Exception("TIMEADD(): orario fuori range");
        }

        return [$hours, $minutes, $seconds, isset($matches[3])];
    }

    private function addMinutesToTime(int $hours, int $minutes, int $seconds, int $deltaMinutes): array
    {
        $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds + ($deltaMinutes * 60);
        $daySeconds = 24 * 3600;
        $normalized = (($totalSeconds % $daySeconds) + $daySeconds) % $daySeconds;

        $hours = intdiv($normalized, 3600);
        $minutes = intdiv($normalized % 3600, 60);
        $seconds = $normalized % 60;

        return [$hours, $minutes, $seconds];
    }

    private function formatTimeValue(int $hours, int $minutes, int $seconds, bool $hasSeconds): string
    {
        if ($hasSeconds) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }
        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Esegue una funzione builtin
     */
    private function executeFunction(string $funcName, array $args): mixed
    {
        $funcName = strtoupper($funcName);

        if (!in_array($funcName, $this->builtinFunctions)) {
            throw new \Exception("Funzione non riconosciuta: {$funcName}");
        }

        $methodName = 'func_' . $funcName;
        
        if (!method_exists($this, $methodName)) {
            throw new \Exception("Funzione {$funcName} non implementata");
        }

        return $this->$methodName($args);
    }

    // ==================== METODI PUBBLICI ====================

    /**
     * Parsa il codice sorgente e restituisce l'AST
     * @param string $source Codice sorgente
     * @return array AST
     */
    public function parse(string $source): array
    {
        $this->nodeId = 0;
        $tokens = $this->tokenize($source);
        return $this->parseTokens($tokens);
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
                case self::NODE_NUMBER:
                    return (string)$node['value'];
                case self::NODE_DATE:
                    return $node['value'];
                case self::NODE_STRING:
                    return '"' . $node['value'] . '"';
                case self::NODE_IDENTIFIER:
                    return $node['value'];
                case self::NODE_PARAMETER:
                    return '[' . $node['value'] . ']';
                case self::NODE_FUNCTION_CALL:
                    $argStrs = array_map($nodeToString, $node['arguments'] ?? []);
                    return $node['value'] . '(' . implode(', ', $argStrs) . ')';
                case self::NODE_BINARY_OP:
                    return '(' . $nodeToString($node['left']) . ' ' . $node['value'] . ' ' . $nodeToString($node['right']) . ')';
                case self::NODE_UNARY_OP:
                    return $node['value'] . '(' . $nodeToString($node['right']) . ')';
                default:
                    return '?';
            }
        };

        $traverse = function (array $node, int $depth = 0) use (&$traverse, &$operations, &$order, $nodeToString): void {
            if (empty($node)) return;

            if ($node['type'] === self::NODE_PROGRAM) {
                foreach ($node['statements'] ?? [] as $stmt) {
                    $traverse($stmt, $depth);
                }
                return;
            }

            if ($node['type'] === self::NODE_FUNCTION_CALL) {
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
            } elseif ($node['type'] === self::NODE_BINARY_OP) {
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
            } elseif ($node['type'] === self::NODE_UNARY_OP) {
                $traverse($node['right'], $depth + 1);
                $operations[] = [
                    'order' => $order++,
                    'operation' => $node['value'],
                    'nodeId' => $node['id'],
                    'depth' => $depth,
                    'operand' => $nodeToString($node['right']),
                    'description' => $node['value'] . '(' . $nodeToString($node['right']) . ')'
                ];
            } elseif ($node['type'] === self::NODE_ASSIGNMENT) {
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
            } elseif ($node['type'] === self::NODE_IF_STATEMENT) {
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

        if ($ast['type'] === self::NODE_PROGRAM) {
            $result .= "PROGRAM\n";
            $stmts = $ast['statements'] ?? [];
            foreach ($stmts as $i => $stmt) {
                $result .= $this->visualizeTree($stmt, '', $i === count($stmts) - 1);
            }
            return $result;
        }

        $result .= $indent . $connector;

        switch ($ast['type']) {
            case self::NODE_NUMBER:
                $result .= "NUM({$ast['value']})\n";
                break;
            case self::NODE_DATE:
                $result .= "DATE({$ast['value']})\n";
                break;
            case self::NODE_STRING:
                $result .= "STR(\"{$ast['value']}\")\n";
                break;
            case self::NODE_IDENTIFIER:
                $result .= "VAR({$ast['value']})\n";
                break;
            case self::NODE_PARAMETER:
                $result .= "PARAM[{$ast['value']}]\n";
                break;
            case self::NODE_FUNCTION_CALL:
                $result .= "FUNC({$ast['value']}) [id:{$ast['id']}]\n";
                $args = $ast['arguments'] ?? [];
                foreach ($args as $i => $arg) {
                    $result .= $this->visualizeTree($arg, $childIndent, $i === count($args) - 1);
                }
                break;
            case self::NODE_BINARY_OP:
                $result .= "OP({$ast['value']}) [id:{$ast['id']}]\n";
                if ($ast['left']) $result .= $this->visualizeTree($ast['left'], $childIndent, !$ast['right']);
                if ($ast['right']) $result .= $this->visualizeTree($ast['right'], $childIndent, true);
                break;
            case self::NODE_UNARY_OP:
                $result .= "UNARY({$ast['value']}) [id:{$ast['id']}]\n";
                if ($ast['right']) $result .= $this->visualizeTree($ast['right'], $childIndent, true);
                break;
            case self::NODE_ASSIGNMENT:
                $result .= "ASSIGN({$ast['value']}) [id:{$ast['id']}]\n";
                if ($ast['right']) $result .= $this->visualizeTree($ast['right'], $childIndent, true);
                break;
            case self::NODE_IF_STATEMENT:
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

        $exec = function (array $node) use (&$exec): mixed {
            if (empty($node)) return null;

            switch ($node['type']) {
                case self::NODE_PROGRAM:
                    $result = null;
                    foreach ($node['statements'] ?? [] as $stmt) {
                        $result = $exec($stmt);
                    }
                    return $result;

                case self::NODE_NUMBER:
                case self::NODE_STRING:
                    return $node['value'];

                case self::NODE_DATE:
                    $date = \DateTime::createFromFormat('Y-m-d', $node['value']);
                    $date->setTime(0, 0, 0);
                    return $date;

                case self::NODE_IDENTIFIER:
                    if (!array_key_exists($node['value'], $this->variables)) {
                        throw new \Exception("Variabile non definita: {$node['value']}");
                    }
                    return $this->variables[$node['value']];

                case self::NODE_PARAMETER:
                    if (!array_key_exists($node['value'], $this->parameters)) {
                        throw new \Exception("Parametro non definito: [{$node['value']}]");
                    }
                    return $this->parameters[$node['value']];

                case self::NODE_FUNCTION_CALL:
                    // Valuta gli argomenti
                    $args = [];
                    foreach ($node['arguments'] ?? [] as $argNode) {
                        $args[] = $exec($argNode);
                    }
                    // Esegui la funzione
                    return $this->executeFunction($node['value'], $args);

                case self::NODE_BINARY_OP:
                    $left = $exec($node['left']);
                    $right = $exec($node['right']);

                    $isDate = fn($v) => $v instanceof \DateTime;
                    $isNumber = fn($v) => is_int($v) || is_float($v);
                    $isBool = fn($v) => is_bool($v);

                    switch ($node['value']) {
                        case '+':
                            if ($isDate($left) && $isNumber($right)) {
                                $result = clone $left;
                                $result->modify("+{$right} days");
                                return $result;
                            }
                            if ($isNumber($left) && $isDate($right)) {
                                $result = clone $right;
                                $result->modify("+{$left} days");
                                return $result;
                            }
                            if ($isDate($left) && $isDate($right)) {
                                throw new \Exception('Non è possibile sommare due date');
                            }
                            return $left + $right;

                        case '-':
                            if ($isDate($left) && $isDate($right)) {
                                // Differenza in giorni: left - right
                                // Se left > right, risultato positivo
                                // Se left < right, risultato negativo
                                $diffSeconds = $left->getTimestamp() - $right->getTimestamp();
                                return (int)floor($diffSeconds / 86400); // 86400 = secondi in un giorno
                            }
                            if ($isDate($left) && $isNumber($right)) {
                                $result = clone $left;
                                $result->modify("-{$right} days");
                                return $result;
                            }
                            if ($isNumber($left) && $isDate($right)) {
                                throw new \Exception('Non è possibile sottrarre una data da un numero');
                            }
                            return $left - $right;

                        case '*':
                            if ($isDate($left) || $isDate($right)) {
                                throw new \Exception('Non è possibile moltiplicare date');
                            }
                            return $left * $right;

                        case '/':
                            if ($isDate($left) || $isDate($right)) {
                                throw new \Exception('Non è possibile dividere date');
                            }
                            return $left / $right;

                        case '%':
                            if ($isDate($left) || $isDate($right)) {
                                throw new \Exception('Non è possibile usare modulo con date');
                            }
                            return $left % $right;

                        case '^':
                            if ($isDate($left) || $isDate($right)) {
                                throw new \Exception('Non è possibile usare potenza con date');
                            }
                            return pow($left, $right);

                        case '==':
                            if ($isDate($left) && $isDate($right)) {
                                return $left == $right;
                            }
                            if ($isDate($left) || $isDate($right)) {
                                throw new \Exception('Non è possibile confrontare una data con un non-data usando ==');
                            }
                            return $left === $right;

                        case '!=':
                        case '<>':
                            if ($isDate($left) && $isDate($right)) {
                                return $left != $right;
                            }
                            if ($isDate($left) || $isDate($right)) {
                                throw new \Exception('Non è possibile confrontare una data con un non-data usando <>');
                            }
                            return $left !== $right;

                        case '<':
                            if ($isDate($left) && $isDate($right)) {
                                return $left < $right;
                            }
                            if ($isDate($left) || $isDate($right)) {
                                throw new \Exception('Non è possibile confrontare una data con un non-data usando <');
                            }
                            return $left < $right;

                        case '>':
                            if ($isDate($left) && $isDate($right)) {
                                return $left > $right;
                            }
                            if ($isDate($left) || $isDate($right)) {
                                throw new \Exception('Non è possibile confrontare una data con un non-data usando >');
                            }
                            return $left > $right;

                        case '<=':
                            if ($isDate($left) && $isDate($right)) {
                                return $left <= $right;
                            }
                            if ($isDate($left) || $isDate($right)) {
                                throw new \Exception('Non è possibile confrontare una data con un non-data usando <=');
                            }
                            return $left <= $right;

                        case '>=':
                            if ($isDate($left) && $isDate($right)) {
                                return $left >= $right;
                            }
                            if ($isDate($left) || $isDate($right)) {
                                throw new \Exception('Non è possibile confrontare una data con un non-data usando >=');
                            }
                            return $left >= $right;

                        case '&&':
                        case 'and':
                        case 'AND':
                            if (!$isBool($left)) {
                                $type = gettype($left);
                                throw new \Exception("Operatore AND: il valore sinistro deve essere booleano, ricevuto {$type} ({$left})");
                            }
                            if (!$isBool($right)) {
                                $type = gettype($right);
                                throw new \Exception("Operatore AND: il valore destro deve essere booleano, ricevuto {$type} ({$right})");
                            }
                            return $left && $right;

                        case '||':
                        case 'or':
                        case 'OR':
                            if (!$isBool($left)) {
                                $type = gettype($left);
                                throw new \Exception("Operatore OR: il valore sinistro deve essere booleano, ricevuto {$type} ({$left})");
                            }
                            if (!$isBool($right)) {
                                $type = gettype($right);
                                throw new \Exception("Operatore OR: il valore destro deve essere booleano, ricevuto {$type} ({$right})");
                            }
                            return $left || $right;

                        default:
                            throw new \Exception("Operatore non supportato: {$node['value']}");
                    }

                case self::NODE_UNARY_OP:
                    $operand = $exec($node['right']);
                    switch ($node['value']) {
                        case '-':
                            return -$operand;
                        case '!':
                        case 'not':
                        case 'NOT':
                            if (!is_bool($operand)) {
                                $type = gettype($operand);
                                throw new \Exception("Operatore NOT: il valore deve essere booleano, ricevuto {$type} ({$operand})");
                            }
                            return !$operand;
                        default:
                            throw new \Exception("Operatore unario non supportato: {$node['value']}");
                    }

                case self::NODE_ASSIGNMENT:
                    $value = $exec($node['right']);
                    $this->variables[$node['value']] = $value;
                    return $value;

                case self::NODE_IF_STATEMENT:
                    $condition = $exec($node['condition']);

                    if (!is_bool($condition)) {
                        $type = gettype($condition);
                        $val = $this->formatResult($condition);
                        throw new \Exception("IF: la condizione deve essere booleano, ricevuto {$type} ({$val})");
                    }

                    if ($condition === true) {
                        $result = null;
                        foreach ($node['thenBranch'] ?? [] as $stmt) {
                            $result = $exec($stmt);
                        }
                        return $result;
                    }

                    if (!empty($node['elseBranch'])) {
                        $result = null;
                        foreach ($node['elseBranch'] as $stmt) {
                            $result = $exec($stmt);
                        }
                        return $result;
                    }

                    return null;

                default:
                    throw new \Exception("Tipo nodo non supportato: {$node['type']}");
            }
        };

        return $exec($ast);
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
     * Formatta il risultato per output leggibile
     * @param mixed $value Valore da formattare
     * @return string Valore formattato
     */
    public function formatResult(mixed $value): string
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        return (string)$value;
    }

    /**
     * Reset delle variabili
     * @return self
     */
    public function reset(): self
    {
        $this->variables = [];
        return $this;
    }

    /**
     * Reset completo (variabili e parametri)
     * @return self
     */
    public function resetAll(): self
    {
        $this->variables = [];
        $this->parameters = [];
        return $this;
    }

    /**
     * Restituisce le variabili correnti
     * @return array
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Restituisce i parametri correnti
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Restituisce la lista delle funzioni builtin disponibili
     * @return array
     */
    public function getBuiltinFunctions(): array
    {
        return $this->builtinFunctions;
    }
}
