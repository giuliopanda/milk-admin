<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Query\Expression;

use App\ArrayQuery\Parser\Token;
use App\ArrayQuery\Support\TokenType;

final class VariableExpression extends Expression
{
    /**
     * @var string
     */
    public $variableName;

    /**
     * @param Token $token
     */
    public function __construct(Token $token)
    {
        $this->type = $token->type;
        $this->precedence = 0;
        $this->variableName = substr($token->value, 1);
        $this->name = $this->variableName;
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
