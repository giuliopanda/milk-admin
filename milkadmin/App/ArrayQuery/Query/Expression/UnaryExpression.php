<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Query\Expression;

use App\ArrayQuery\Parser\ParserException;
use App\ArrayQuery\Parser\Token;
use App\ArrayQuery\Support\TokenType;

final class UnaryExpression extends Expression
{
    /**
     * @var Expression|null
     */
    public $subject = null;

    /**
     * @var string
     */
    public $operator;

    public function __construct(string $operator, Token $token)
    {
        $this->operator = $operator;
        $this->type = TokenType::OPERATOR;
        $this->precedence = 14;
        $this->name = $operator;
        $this->start = $token->start;
    }

    public function setNextChild(Expression $expr, bool $overwrite = false) : void
    {
        if ($this->subject !== null && !$overwrite) {
            throw new ParserException("Unexpected expression after unary operand");
        }

        $this->subject = $expr;
    }

    /**
     * @return bool
     */
    public function isWellFormed()
    {
        return $this->subject !== null;
    }
}
