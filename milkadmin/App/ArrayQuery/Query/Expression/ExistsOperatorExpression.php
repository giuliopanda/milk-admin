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

final class ExistsOperatorExpression extends Expression
{
    /**
     * @var Expression|null
     */
    public $exists = null;

    /**
     * @var bool
     */
    public $negated = false;

    public function __construct(bool $negated, Token $token)
    {
        $this->negated = $negated;
        $this->name = '';
        $this->precedence = ExpressionParser::OPERATOR_PRECEDENCE['EXISTS'];
        $this->operator = 'EXISTS';
        $this->type = TokenType::OPERATOR;
        $this->start = $token->start;
    }

    /**
     * @return void
     */
    public function negate()
    {
        $this->negated = true;
    }

    /**
     * @return bool
     */
    public function isWellFormed()
    {
        return $this->exists !== null;
    }

    public function setNextChild(Expression $expr, bool $overwrite = false) : void
    {
        $this->exists = $expr;
    }
}
