<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Query\Expression;

use App\ArrayQuery\Parser\Token;
use App\ArrayQuery\Support\TokenType;

final class RowExpression extends Expression
{
    /**
     * @var array<int, Expression>
     */
    public $elements;

    /**
     * @param array<int, Expression> $elements
     */
    public function __construct(array $elements, Token $token)
    {
        $this->elements = $elements;
        $this->precedence = 0;
        $this->name = '';
        $this->type = TokenType::PAREN;
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
