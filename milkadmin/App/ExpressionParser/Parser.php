<?php
namespace App\ExpressionParser;

/**
 * Parser - Costruisce l'AST (Abstract Syntax Tree) dai token
 */
class Parser
{
    private int $nodeId = 0;

    /**
     * Reset del contatore nodi (usato da ExpressionParser prima di ogni parse)
     */
    public function resetNodeId(): void
    {
        $this->nodeId = 0;
    }

    /**
     * Crea un nodo AST
     */
    public function createNode(string $type, mixed $value = null): array
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

    /**
     * Parsa un array di token e costruisce l'AST
     * @param array $tokens Token dal Lexer
     * @return array AST root node (PROGRAM)
     */
    public function parseTokens(array $tokens): array
    {
        $pos = 0;

        $peek = function () use (&$pos, $tokens): array {
            return $tokens[$pos] ?? ['type' => TokenType::TOKEN_EOF, 'value' => null, 'line' => 0, 'column' => 0];
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
            while ($peek()['type'] === TokenType::TOKEN_NEWLINE) {
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

            while ($peek()['type'] === TokenType::TOKEN_OR) {
                $op = $advance();
                $node = $this->createNode(TokenType::NODE_BINARY_OP, $op['value']);
                $node['left'] = $left;
                $node['right'] = $parseAnd();
                $left = $node;
            }

            return $left;
        };

        $parseAnd = function () use (&$parseComparison, $peek, $advance): array {
            $left = $parseComparison();

            while ($peek()['type'] === TokenType::TOKEN_AND) {
                $op = $advance();
                $node = $this->createNode(TokenType::NODE_BINARY_OP, $op['value']);
                $node['left'] = $left;
                $node['right'] = $parseComparison();
                $left = $node;
            }

            return $left;
        };

        $parseComparison = function () use (&$parseAdditive, $peek, $advance): array {
            $left = $parseAdditive();

            $compOps = [TokenType::TOKEN_EQ, TokenType::TOKEN_NEQ, TokenType::TOKEN_LT, TokenType::TOKEN_GT, TokenType::TOKEN_LTE, TokenType::TOKEN_GTE];

            while (in_array($peek()['type'], $compOps)) {
                $op = $advance();
                $node = $this->createNode(TokenType::NODE_BINARY_OP, $op['value']);
                $node['left'] = $left;
                $node['right'] = $parseAdditive();
                $left = $node;
            }

            return $left;
        };

        $parseAdditive = function () use (&$parseMultiplicative, $peek, $advance): array {
            $left = $parseMultiplicative();

            while (in_array($peek()['type'], [TokenType::TOKEN_PLUS, TokenType::TOKEN_MINUS])) {
                $op = $advance();
                $node = $this->createNode(TokenType::NODE_BINARY_OP, $op['value']);
                $node['left'] = $left;
                $node['right'] = $parseMultiplicative();
                $left = $node;
            }

            return $left;
        };

        $parseMultiplicative = function () use (&$parsePower, $peek, $advance): array {
            $left = $parsePower();

            while (in_array($peek()['type'], [TokenType::TOKEN_MULTIPLY, TokenType::TOKEN_DIVIDE, TokenType::TOKEN_MODULO])) {
                $op = $advance();
                $node = $this->createNode(TokenType::NODE_BINARY_OP, $op['value']);
                $node['left'] = $left;
                $node['right'] = $parsePower();
                $left = $node;
            }

            return $left;
        };

        $parsePower = function () use (&$parseUnary, $peek, $advance, &$parsePower): array {
            $left = $parseUnary();

            if ($peek()['type'] === TokenType::TOKEN_POWER) {
                $op = $advance();
                $node = $this->createNode(TokenType::NODE_BINARY_OP, $op['value']);
                $node['left'] = $left;
                $node['right'] = $parsePower(); // Associatività a destra
                return $node;
            }

            return $left;
        };

        $parseUnary = function () use (&$parsePrimary, $peek, $advance, &$parseUnary): array {
            if (in_array($peek()['type'], [TokenType::TOKEN_MINUS, TokenType::TOKEN_NOT])) {
                $op = $advance();
                $node = $this->createNode(TokenType::NODE_UNARY_OP, $op['value']);
                $node['right'] = $parseUnary();
                return $node;
            }

            return $parsePrimary();
        };

        $parsePrimary = function () use ($peek, $advance, $expect, &$parseExpression): array {
            $token = $peek();

            if ($token['type'] === TokenType::TOKEN_NUMBER) {
                $advance();
                return $this->createNode(TokenType::NODE_NUMBER, $token['value']);
            }
            if ($token['type'] === TokenType::TOKEN_DATE) {
                $advance();
                return $this->createNode(TokenType::NODE_DATE, $token['value']);
            }
            if ($token['type'] === TokenType::TOKEN_STRING) {
                $advance();
                return $this->createNode(TokenType::NODE_STRING, $token['value']);
            }
            if ($token['type'] === TokenType::TOKEN_IDENTIFIER) {
                $idToken = $advance();

                // Controlla se è una funzione
                if ($peek()['type'] === TokenType::TOKEN_LPAREN) {
                    $advance(); // (

                    $args = [];
                    if ($peek()['type'] !== TokenType::TOKEN_RPAREN) {
                        $args[] = $parseExpression();
                        while ($peek()['type'] === TokenType::TOKEN_COMMA) {
                            $advance(); // ,
                            $args[] = $parseExpression();
                        }
                    }

                    $expect(TokenType::TOKEN_RPAREN);

                    $node = $this->createNode(TokenType::NODE_FUNCTION_CALL, strtoupper($idToken['value']));
                    $node['arguments'] = $args;
                    return $node;
                }

                return $this->createNode(TokenType::NODE_IDENTIFIER, $idToken['value']);
            }
            if ($token['type'] === TokenType::TOKEN_PARAMETER) {
                $advance();
                return $this->createNode(TokenType::NODE_PARAMETER, $token['value']);
            }
            if ($token['type'] === TokenType::TOKEN_LPAREN) {
                $advance();
                $expr = $parseExpression();
                $expect(TokenType::TOKEN_RPAREN);
                return $expr;
            }

            throw new \Exception("Token inatteso: {$token['type']} alla riga {$token['line']}");
        };

        $parseStatement = function () use ($peek, $advance, $expect, &$parseExpression, &$parseIfStatement, &$parseAssignment, $tokens, &$pos): array {
            $token = $peek();

            if ($token['type'] === TokenType::TOKEN_IF) {
                return $parseIfStatement();
            }

            if ($token['type'] === TokenType::TOKEN_IDENTIFIER && isset($tokens[$pos + 1]) && $tokens[$pos + 1]['type'] === TokenType::TOKEN_ASSIGN) {
                return $parseAssignment();
            }

            return $parseExpression();
        };

        $parseAssignment = function () use ($advance, $expect, &$parseExpression): array {
            $id = $advance();
            $expect(TokenType::TOKEN_ASSIGN);
            $node = $this->createNode(TokenType::NODE_ASSIGNMENT, $id['value']);
            $node['right'] = $parseExpression();
            return $node;
        };

        $parseIfStatement = function () use ($expect, $peek, $advance, $skipNewlines, &$parseExpression, &$parseStatement): array {
            $expect(TokenType::TOKEN_IF);
            $node = $this->createNode(TokenType::NODE_IF_STATEMENT);
            $node['condition'] = $parseExpression();

            if ($peek()['type'] === TokenType::TOKEN_THEN) {
                $advance();
            }
            $skipNewlines();

            // THEN branch
            $thenStatements = [];
            while (!in_array($peek()['type'], [TokenType::TOKEN_ELSE, TokenType::TOKEN_ENDIF, TokenType::TOKEN_EOF])) {
                $skipNewlines();
                if (in_array($peek()['type'], [TokenType::TOKEN_ELSE, TokenType::TOKEN_ENDIF, TokenType::TOKEN_EOF])) break;
                $thenStatements[] = $parseStatement();
                $skipNewlines();
            }
            $node['thenBranch'] = $thenStatements;

            // ELSE branch (opzionale)
            if ($peek()['type'] === TokenType::TOKEN_ELSE) {
                $advance();
                $skipNewlines();
                $elseStatements = [];
                while (!in_array($peek()['type'], [TokenType::TOKEN_ENDIF, TokenType::TOKEN_EOF])) {
                    $skipNewlines();
                    if (in_array($peek()['type'], [TokenType::TOKEN_ENDIF, TokenType::TOKEN_EOF])) break;
                    $elseStatements[] = $parseStatement();
                    $skipNewlines();
                }
                $node['elseBranch'] = $elseStatements;
            }

            if ($peek()['type'] === TokenType::TOKEN_ENDIF) {
                $advance();
            }

            return $node;
        };

        // Parse programma
        $statements = [];
        while ($peek()['type'] !== TokenType::TOKEN_EOF) {
            $skipNewlines();
            if ($peek()['type'] === TokenType::TOKEN_EOF) break;
            $statements[] = $parseStatement();
        }

        $program = $this->createNode(TokenType::NODE_PROGRAM);
        $program['statements'] = $statements;
        return $program;
    }
}
