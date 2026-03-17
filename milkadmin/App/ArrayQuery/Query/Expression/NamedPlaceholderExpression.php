<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Query\Expression;

use App\ArrayQuery\Parser\Token;
use App\ArrayQuery\Support\TokenType;

final class NamedPlaceholderExpression extends Expression
{
    /**
     * @var string
     */
    public $parameterName;

    /**
     * @param Token $token
     */
    public function __construct(Token $token, string $parameter_name)
    {
        $this->type = $token->type;
        $this->precedence = 0;
        $this->parameterName = $parameter_name;
        $this->name = '?';
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
