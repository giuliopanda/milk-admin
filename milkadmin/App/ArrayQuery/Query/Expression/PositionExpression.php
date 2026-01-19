<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Query\Expression;

use App\ArrayQuery\Parser\Token;
use App\ArrayQuery\Support\TokenType;

final class PositionExpression extends Expression
{
    /**
     * @var int
     */
    public $position;

    public function __construct(int $position, Token $token)
    {
        $this->position = $position;
        $this->type = TokenType::IDENTIFIER;
        $this->precedence = 0;
        $this->name = (string) $position;
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
