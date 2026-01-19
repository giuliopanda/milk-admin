<?php
/**
 * ExpressionEvaluator - Valutatore di espressioni SQL
 *
 * Implementazione originale per valutare espressioni SQL parsate.
 * Supporta operatori di confronto, logici, aritmetici e funzioni SQL base.
 *
 * @author Custom Implementation
 * @license MIT
 */

namespace App\ArrayQuery;

use App\ArrayQuery\Query\Expression\BinaryOperatorExpression;
use App\ArrayQuery\Query\Expression\UnaryExpression;
use App\ArrayQuery\Query\Expression\ColumnExpression;
use App\ArrayQuery\Query\Expression\ConstantExpression;
use App\ArrayQuery\Query\Expression\FunctionExpression;
use App\ArrayQuery\Query\Expression\InOperatorExpression;
use App\ArrayQuery\Query\Expression\BetweenOperatorExpression;
use App\ArrayQuery\Query\Expression\QuestionMarkPlaceholderExpression;
use App\ArrayQuery\Query\Expression\NamedPlaceholderExpression;

class ExpressionEvaluator
{
    /**
     * @var array<int, mixed>
     */
    private array $positionalParameters = [];

    /**
     * @var array<string, mixed>
     */
    private array $namedParameters = [];

    /**
     * Set positional and named parameters for placeholder evaluation.
     *
     * @param array $params
     * @return void
     */
    public function setParameters(array $params): void
    {
        $positional = [];
        $named = [];

        foreach ($params as $key => $value) {
            if (is_int($key)) {
                $positional[] = $value;
            } else {
                $named[$key] = $value;
            }
        }

        $this->positionalParameters = $positional;
        $this->namedParameters = $named;
    }
    /**
     * Valuta un'espressione SQL nel contesto di una riga
     *
     * @param mixed $expression Espressione parsata
     * @param array $row Riga di dati corrente
     * @return mixed Risultato della valutazione
     */
    public function evaluate($expression, array $row)
    {
        if ($expression === null) {
            return true;
        }

        // Gestione tipi di espressione
        $className = get_class($expression);

        switch ($className) {
            case BinaryOperatorExpression::class:
                return $this->evaluateBinaryOperator($expression, $row);

            case UnaryExpression::class:
                return $this->evaluateUnaryOperator($expression, $row);

            case ColumnExpression::class:
                return $this->evaluateColumn($expression, $row);

            case ConstantExpression::class:
                return $this->evaluateConstant($expression);

            case FunctionExpression::class:
                return $this->evaluateFunction($expression, $row);

            case InOperatorExpression::class:
                return $this->evaluateInOperator($expression, $row);

            case BetweenOperatorExpression::class:
                return $this->evaluateBetweenOperator($expression, $row);

            case QuestionMarkPlaceholderExpression::class:
                return $this->positionalParameters[$expression->offset] ?? null;

            case NamedPlaceholderExpression::class:
                $name = ltrim($expression->parameterName, ':');
                if (array_key_exists($name, $this->namedParameters)) {
                    return $this->namedParameters[$name];
                }
                return $this->namedParameters[$expression->parameterName] ?? null;

            default:
                // Fallback: prova a estrarre un valore diretto
                if (is_scalar($expression)) {
                    return $expression;
                }
                return null;
        }
    }

    /**
     * Valuta un operatore binario (=, >, <, AND, OR, ecc.)
     */
    private function evaluateBinaryOperator(BinaryOperatorExpression $expr, array $row)
    {
        $left = $this->evaluate($expr->left, $row);
        $right = $this->evaluate($expr->right, $row);
        $operator = strtoupper($expr->operator);

        switch ($operator) {
            // Confronto
            case '=':
                return $left == $right;
            case '!=':
            case '<>':
                return $left != $right;
            case '<=>':
                if ($left === null && $right === null) {
                    return true;
                }
                if ($left === null || $right === null) {
                    return false;
                }
                return $left == $right;
            case '>':
                return $left > $right;
            case '>=':
                return $left >= $right;
            case '<':
                return $left < $right;
            case '<=':
                return $left <= $right;
            case 'IS':
                $result = $this->evaluateIsOperator($left, $right);
                return $expr->negated ? !$result : $result;

            // Logici
            case 'AND':
            case '&&':
                return $left && $right;
            case 'OR':
            case '||':
                return $left || $right;
            case 'XOR':
                return ($left xor $right);

            // Aritmetici
            case '+':
                return $left + $right;
            case '-':
                return $left - $right;
            case '*':
                return $left * $right;
            case '/':
                return $right != 0 ? $left / $right : null;
            case 'DIV':
                return $right != 0 ? (int) ($left / $right) : null;
            case '%':
            case 'MOD':
                return $right != 0 ? $left % $right : null;

            // Stringa
            case 'LIKE':
                if ($left === null || $right === null) {
                    return null;
                }
                $result = $this->evaluateLike($left, $right);
                return $expr->negated ? !$result : $result;
            case 'REGEXP':
                if ($left === null || $right === null) {
                    return null;
                }
                $result = $this->evaluateRegexp($left, $right);
                return $expr->negated ? !$result : $result;

            // Bitwise
            case '&':
                return $this->evaluateBitwise(function ($a, $b) { return $a & $b; }, $left, $right);
            case '|':
                return $this->evaluateBitwise(function ($a, $b) { return $a | $b; }, $left, $right);
            case '^':
                return $this->evaluateBitwise(function ($a, $b) { return $a ^ $b; }, $left, $right);
            case '<<':
                return $this->evaluateBitwise(function ($a, $b) { return $a << $b; }, $left, $right);
            case '>>':
                return $this->evaluateBitwise(function ($a, $b) { return $a >> $b; }, $left, $right);

            // Assignment (no-op storage; returns RHS)
            case ':=':
                return $right;

            default:
                throw new \Exception("Unsupported operator: $operator");
        }
    }

    /**
     * Valuta un operatore unario (NOT, -, +)
     */
    private function evaluateUnaryOperator(UnaryExpression $expr, array $row)
    {
        $value = $this->evaluate($expr->subject, $row);
        $operator = strtoupper($expr->operator);

        switch ($operator) {
            case 'NOT':
            case '!':
                return !$value;
            case '-':
                return -$value;
            case '+':
                return +$value;
            case '~':
                if ($value === null) {
                    return null;
                }
                return ~((int) $value);
            default:
                throw new \Exception("Unsupported unary operator: $operator");
        }
    }

    /**
     * Valuta una colonna (estrae il valore dalla riga)
     */
    private function evaluateColumn(ColumnExpression $expr, array $row)
    {
        $column = $expr->columnName ?? $expr->name;

        // Se c'è un nome di tabella qualificato, cerca prima con qualificazione
        if (isset($expr->tableName) && $expr->tableName) {
            $qualifiedName = $expr->tableName . '.' . $column;
            if (array_key_exists($qualifiedName, $row)) {
                return $row[$qualifiedName];
            }
        }

        // Fallback: cerca solo il nome della colonna
        return $row[$column] ?? null;
    }

    /**
     * Valuta una costante
     */
    private function evaluateConstant(ConstantExpression $expr)
    {
        return $expr->value;
    }

    /**
     * Valuta una funzione SQL
     */
    private function evaluateFunction(FunctionExpression $expr, array $row)
    {
        $func = strtoupper($expr->functionName);
        $args = [];

        // Valuta gli argomenti
        if (isset($expr->args)) {
            foreach ($expr->args as $arg) {
                $args[] = $this->evaluate($arg, $row);
            }
        }

        switch ($func) {
            // Funzioni stringa
            case 'UPPER':
                return strtoupper($args[0] ?? '');
            case 'LOWER':
                return strtolower($args[0] ?? '');
            case 'LENGTH':
                return strlen($args[0] ?? '');
            case 'CONCAT':
                return implode('', $args);
            case 'SUBSTRING':
            case 'SUBSTR':
                $str = $args[0] ?? '';
                $start = ($args[1] ?? 1) - 1; // SQL è 1-based
                $length = $args[2] ?? null;
                return $length ? substr($str, $start, $length) : substr($str, $start);

            // Funzioni numeriche
            case 'ABS':
                return abs($args[0] ?? 0);
            case 'CEIL':
            case 'CEILING':
                return ceil($args[0] ?? 0);
            case 'FLOOR':
                return floor($args[0] ?? 0);
            case 'ROUND':
                $decimals = $args[1] ?? 0;
                return round($args[0] ?? 0, $decimals);

            // Funzioni NULL
            case 'COALESCE':
                foreach ($args as $arg) {
                    if ($arg !== null) {
                        return $arg;
                    }
                }
                return null;
            case 'IFNULL':
                return $args[0] !== null ? $args[0] : ($args[1] ?? null);

            // Funzioni data/ora
            case 'NOW':
            case 'CURRENT_TIMESTAMP':
                return $this->now()->format('Y-m-d H:i:s');
            case 'CURDATE':
            case 'CURRENT_DATE':
                return $this->now()->format('Y-m-d');
            case 'CURTIME':
            case 'CURRENT_TIME':
                return $this->now()->format('H:i:s');
            case 'DATE':
                $dt = $this->parseDateTimeValue($args[0] ?? null);
                return $dt ? $dt->format('Y-m-d') : null;
            case 'TIME':
                $dt = $this->parseDateTimeValue($args[0] ?? null);
                return $dt ? $dt->format('H:i:s') : null;
            case 'YEAR':
                $dt = $this->parseDateTimeValue($args[0] ?? null);
                return $dt ? (int) $dt->format('Y') : null;
            case 'MONTH':
                $dt = $this->parseDateTimeValue($args[0] ?? null);
                return $dt ? (int) $dt->format('n') : null;
            case 'DAY':
                $dt = $this->parseDateTimeValue($args[0] ?? null);
                return $dt ? (int) $dt->format('j') : null;
            case 'HOUR':
                $dt = $this->parseDateTimeValue($args[0] ?? null);
                return $dt ? (int) $dt->format('G') : null;
            case 'MINUTE':
                $dt = $this->parseDateTimeValue($args[0] ?? null);
                return $dt ? (int) $dt->format('i') : null;
            case 'SECOND':
                $dt = $this->parseDateTimeValue($args[0] ?? null);
                return $dt ? (int) $dt->format('s') : null;
            case 'DATE_FORMAT':
                $dt = $this->parseDateTimeValue($args[0] ?? null);
                if (!$dt) {
                    return null;
                }
                $format = (string) ($args[1] ?? '');
                return $dt->format($this->mysqlDateFormatToPhp($format));
            case 'STR_TO_DATE':
                $value = (string) ($args[0] ?? '');
                $format = (string) ($args[1] ?? '');
                return $this->parseDateFromFormat($value, $format);
            case 'DATEDIFF':
                $left = $this->parseDateTimeValue($args[0] ?? null);
                $right = $this->parseDateTimeValue($args[1] ?? null);
                if (!$left || !$right) {
                    return null;
                }
                return (int) $left->diff($right)->format('%r%a');

            // Aggregazioni (per non-GROUP BY queries)
            case 'COUNT':
                return 1; // In contesto non-aggregato
            case 'SUM':
            case 'AVG':
            case 'MAX':
            case 'MIN':
                return $args[0] ?? null;

            default:
                throw new \Exception("Unsupported function: $func");
        }
    }

    /**
     * Valuta operatore IN
     */
    private function evaluateInOperator(InOperatorExpression $expr, array $row)
    {
        $value = $this->evaluate($expr->left, $row);
        $list = [];

        foreach ($expr->inList as $item) {
            $list[] = $this->evaluate($item, $row);
        }

        $result = in_array($value, $list, false);

        return $expr->negated ? !$result : $result;
    }

    /**
     * Valuta operatore BETWEEN
     */
    private function evaluateBetweenOperator(BetweenOperatorExpression $expr, array $row)
    {
        $value = $this->evaluate($expr->left, $row);
        $min = $this->evaluate($expr->beginning, $row);
        $max = $this->evaluate($expr->end, $row);

        $result = ($value >= $min && $value <= $max);

        return $expr->negated ? !$result : $result;
    }

    /**
     * Valuta l'operatore LIKE
     */
    private function evaluateLike($value, $pattern): bool
    {
        if ($value === null || $pattern === null) {
            return false;
        }
        $value = (string) $value;
        $pattern = (string) $pattern;

        // Converti il pattern SQL LIKE in regex
        // % = qualsiasi sequenza di caratteri
        // _ = singolo carattere
        $regex = str_replace(
            ['%', '_', '\\'],
            ['.*', '.', '\\\\'],
            preg_quote($pattern, '/')
        );

        $regex = '/^' . $regex . '$/i'; // Case insensitive

        return preg_match($regex, $value) === 1;
    }

    private function evaluateRegexp($value, $pattern): bool
    {
        if ($value === null || $pattern === null) {
            return false;
        }
        $value = (string) $value;
        $pattern = (string) $pattern;
        if ($pattern === '') {
            return false;
        }

        $delim = '/';
        $regex = $delim . str_replace($delim, '\\' . $delim, $pattern) . $delim . 'i';

        return @preg_match($regex, $value) === 1;
    }

    private function evaluateIsOperator($left, $right): bool
    {
        if ($right === null) {
            return $left === null;
        }

        if (is_bool($right)) {
            return $left !== null && (bool) $left === $right;
        }

        if ((is_int($right) || is_float($right)) && ($right == 0 || $right == 1)) {
            return $left !== null && (bool) $left === ((int) $right === 1);
        }

        return $left == $right;
    }

    private function evaluateBitwise(callable $operator, $left, $right)
    {
        if ($left === null || $right === null) {
            return null;
        }

        return $operator((int) $left, (int) $right);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->getDefaultTimezone());
    }

    private function parseDateTimeValue($value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        if ($value === null) {
            return null;
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $dt = new \DateTimeImmutable('@' . $value);
            return $dt->setTimezone($this->getDefaultTimezone());
        }
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $tz = $this->getDefaultTimezone();
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $value, $tz);
            if ($dt !== false) {
                return $dt;
            }
        }

        try {
            return new \DateTimeImmutable($value, $tz);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseDateFromFormat(string $value, string $mysqlFormat): ?string
    {
        $phpFormat = $this->mysqlDateFormatToPhp($mysqlFormat);
        $dt = \DateTimeImmutable::createFromFormat($phpFormat, $value, $this->getDefaultTimezone());
        if ($dt === false) {
            return null;
        }

        if ($this->mysqlFormatHasTime($mysqlFormat)) {
            return $dt->format('Y-m-d H:i:s');
        }

        return $dt->format('Y-m-d');
    }

    private function mysqlFormatHasTime(string $format): bool
    {
        return preg_match('/%[HhIkilrsT]/', $format) === 1;
    }

    private function mysqlDateFormatToPhp(string $format): string
    {
        $map = [
            '%%' => '%',
            '%Y' => 'Y',
            '%y' => 'y',
            '%m' => 'm',
            '%c' => 'n',
            '%d' => 'd',
            '%e' => 'j',
            '%H' => 'H',
            '%k' => 'G',
            '%h' => 'h',
            '%I' => 'h',
            '%l' => 'g',
            '%i' => 'i',
            '%s' => 's',
            '%S' => 's',
            '%p' => 'A',
            '%r' => 'h:i:s A',
            '%T' => 'H:i:s',
            '%b' => 'M',
            '%M' => 'F',
            '%a' => 'D',
            '%W' => 'l',
        ];

        return strtr($format, $map);
    }

    private function getDefaultTimezone(): \DateTimeZone
    {
        $tz = date_default_timezone_get();
        return new \DateTimeZone($tz ?: 'UTC');
    }
}
