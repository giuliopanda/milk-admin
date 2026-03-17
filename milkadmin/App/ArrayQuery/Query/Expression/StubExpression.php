<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Query\Expression;

use App\ArrayQuery\Support\TokenType;

final class StubExpression extends Expression
{
    public function __construct()
    {
        $this->precedence = 0;
        $this->name = '';
        $this->type = TokenType::RESERVED;
        $this->start = -1;
    }

    /**
     * @return bool
     */
    public function isWellFormed()
    {
        return false;
    }

    public function negate()
    {
        $this->negated = true;
    }
}
