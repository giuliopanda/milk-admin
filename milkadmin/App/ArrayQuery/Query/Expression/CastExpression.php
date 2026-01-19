<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Query\Expression;

use App\ArrayQuery\Parser\Token;
use App\ArrayQuery\Support\TokenType;
use App\ArrayQuery\Query\MysqlColumnType;

final class CastExpression extends Expression
{
    /**
     * @var Token
     */
    public $token;

    /**
     * @var Expression
     */
    public $expr;

    /**
     * @var MysqlColumnType
     */
    public $castType;

    /**
     * @param Token $tokens
     */
    public function __construct(Token $token, Expression $expr, MysqlColumnType $cast_type)
    {
        $this->token = $token;
        $this->type = $token->type;
        $this->precedence = 0;
        $this->operator = (string) $this->type;
        $this->expr = $expr;
        $this->castType = $cast_type;
        $this->start = $token->start;
    }

    /**
     * @return bool
     */
    public function isWellFormed()
    {
        return true;
    }
}
