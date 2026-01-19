<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Query\Expression;

use App\ArrayQuery\Query\Expression\Expression;
use App\ArrayQuery\Parser\ExpressionParser;
use App\ArrayQuery\Parser\ParserException;
use App\ArrayQuery\Parser\Token;
use App\ArrayQuery\Support\TokenType;

final class IntervalOperatorExpression extends Expression
{
    private const UNIT_VALUES = [
        'DAY' => true,
        'DAY_HOUR' => true,
        'DAY_MICROSECOND' => true,
        'DAY_MINUTE' => true,
        'DAY_SECOND' => true,
        'HOUR' => true,
        'HOUR_MICROSECOND' => true,
        'HOUR_MINUTE' => true,
        'HOUR_SECOND' => true,
        'MICROSECOND' => true,
        'MINUTE' => true,
        'MINUTE_MICROSECOND' => true,
        'MINUTE_SECOND' => true,
        'MONTH' => true,
        'QUARTER' => true,
        'SECOND' => true,
        'SECOND_MICROSECOND' => true,
        'WEEK' => true,
        'YEAR' => true,
        'YEAR_MONTH' => true,
    ];

    /** @var ?Expression */
    public $number = null;

    /** @var ?string */
    public $unit = null;

    public function __construct(Token $token)
    {
        $this->name = '';
        $this->precedence = ExpressionParser::OPERATOR_PRECEDENCE['INTERVAL'];
        $this->operator = 'INTERVAL';
        $this->type = TokenType::OPERATOR;
        $this->start = $token->start;
    }

    /**
     * @return bool
     */
    public function isWellFormed()
    {
        return $this->number !== null && $this->unit !== null;
    }

    public function setNextChild(Expression $expr, bool $overwrite = false) : void
    {
        $this->number = $expr;
    }

    public function setUnit(string $unit): void
    {
        $unit = \strtoupper($unit);

        if (!\array_key_exists($unit, self::UNIT_VALUES)) {
            throw new ParserException("Unexpected unit");
        }

        $this->unit = $unit;
    }
}
