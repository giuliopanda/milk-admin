<?php
namespace App\ExpressionParser;

/**
 * Lexer - Tokenizzatore per il mini linguaggio di espressioni
 */
class Lexer
{
    use ValueHelper;

    /**
     * Tokenizza una stringa di input in un array di token
     * @param string $input Codice sorgente
     * @return array Lista di token
     */
    public function tokenize(string $input): array
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
                                'type' => TokenType::TOKEN_DATE,
                                'value' => $dateStr,
                                'line' => $startLine,
                                'column' => $startCol
                            ];
                        }
                    }
                }

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
                                'type' => TokenType::TOKEN_DATE,
                                'value' => $isoDate,
                                'line' => $startLine,
                                'column' => $startCol
                            ];
                        }
                    }
                }

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
                'type' => TokenType::TOKEN_NUMBER,
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
                'type' => TokenType::TOKEN_STRING,
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
                'IF' => TokenType::TOKEN_IF, 'if' => TokenType::TOKEN_IF,
                'THEN' => TokenType::TOKEN_THEN, 'then' => TokenType::TOKEN_THEN,
                'ELSE' => TokenType::TOKEN_ELSE, 'else' => TokenType::TOKEN_ELSE,
                'ENDIF' => TokenType::TOKEN_ENDIF, 'endif' => TokenType::TOKEN_ENDIF,
                'END' => TokenType::TOKEN_ENDIF, 'end' => TokenType::TOKEN_ENDIF,
                'AND' => TokenType::TOKEN_AND, 'and' => TokenType::TOKEN_AND,
                'OR' => TokenType::TOKEN_OR, 'or' => TokenType::TOKEN_OR,
                'NOT' => TokenType::TOKEN_NOT, 'not' => TokenType::TOKEN_NOT
            ];

            return [
                'type' => $keywords[$id] ?? TokenType::TOKEN_IDENTIFIER,
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
                'type' => TokenType::TOKEN_PARAMETER,
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
                $tokens[] = ['type' => TokenType::TOKEN_COMMA, 'value' => ',', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '+') {
                $advance();
                $tokens[] = ['type' => TokenType::TOKEN_PLUS, 'value' => '+', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '-') {
                $advance();
                $tokens[] = ['type' => TokenType::TOKEN_MINUS, 'value' => '-', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '*') {
                $advance();
                $tokens[] = ['type' => TokenType::TOKEN_MULTIPLY, 'value' => '*', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '/') {
                $advance();
                $tokens[] = ['type' => TokenType::TOKEN_DIVIDE, 'value' => '/', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '%') {
                $advance();
                $tokens[] = ['type' => TokenType::TOKEN_MODULO, 'value' => '%', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '^') {
                $advance();
                $tokens[] = ['type' => TokenType::TOKEN_POWER, 'value' => '^', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '(') {
                $advance();
                $tokens[] = ['type' => TokenType::TOKEN_LPAREN, 'value' => '(', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === ')') {
                $advance();
                $tokens[] = ['type' => TokenType::TOKEN_RPAREN, 'value' => ')', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '=') {
                $advance();
                if ($peek() === '=') {
                    $advance();
                    $tokens[] = ['type' => TokenType::TOKEN_EQ, 'value' => '==', 'line' => $startLine, 'column' => $startCol];
                } else {
                    $tokens[] = ['type' => TokenType::TOKEN_ASSIGN, 'value' => '=', 'line' => $startLine, 'column' => $startCol];
                }
            } elseif ($char === '<') {
                $advance();
                if ($peek() === '=') {
                    $advance();
                    $tokens[] = ['type' => TokenType::TOKEN_LTE, 'value' => '<=', 'line' => $startLine, 'column' => $startCol];
                } elseif ($peek() === '>') {
                    $advance();
                    $tokens[] = ['type' => TokenType::TOKEN_NEQ, 'value' => '<>', 'line' => $startLine, 'column' => $startCol];
                } else {
                    $tokens[] = ['type' => TokenType::TOKEN_LT, 'value' => '<', 'line' => $startLine, 'column' => $startCol];
                }
            } elseif ($char === '>') {
                $advance();
                if ($peek() === '=') {
                    $advance();
                    $tokens[] = ['type' => TokenType::TOKEN_GTE, 'value' => '>=', 'line' => $startLine, 'column' => $startCol];
                } else {
                    $tokens[] = ['type' => TokenType::TOKEN_GT, 'value' => '>', 'line' => $startLine, 'column' => $startCol];
                }
            } elseif ($char === '!') {
                $advance();
                if ($peek() === '=') {
                    $advance();
                    $tokens[] = ['type' => TokenType::TOKEN_NEQ, 'value' => '!=', 'line' => $startLine, 'column' => $startCol];
                } else {
                    $tokens[] = ['type' => TokenType::TOKEN_NOT, 'value' => '!', 'line' => $startLine, 'column' => $startCol];
                }
            } elseif ($char === '&' && $peek(1) === '&') {
                $advance();
                $advance();
                $tokens[] = ['type' => TokenType::TOKEN_AND, 'value' => '&&', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === '|' && $peek(1) === '|') {
                $advance();
                $advance();
                $tokens[] = ['type' => TokenType::TOKEN_OR, 'value' => '||', 'line' => $startLine, 'column' => $startCol];
            } elseif ($char === "\n") {
                $advance();
                $tokens[] = ['type' => TokenType::TOKEN_NEWLINE, 'value' => '\\n', 'line' => $startLine, 'column' => $startCol];
            } else {
                $bad = $advance();
                $code = ord($bad);
                throw new \Exception(
                    "Carattere non riconosciuto '{$bad}' (ASCII {$code}) alla riga {$startLine}, colonna {$startCol}"
                );
            }
        }

        $tokens[] = ['type' => TokenType::TOKEN_EOF, 'value' => null, 'line' => $line, 'column' => $column];
        return $tokens;
    }
}
