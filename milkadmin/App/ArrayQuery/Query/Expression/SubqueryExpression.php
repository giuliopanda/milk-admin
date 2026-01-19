<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Query\Expression;

use App\ArrayQuery\Query\SelectQuery;
use App\ArrayQuery\Support\TokenType;
use App\ArrayQuery\Support\Processor\SelectProcessor;

final class SubqueryExpression extends Expression
{
    /**
     * @var SelectQuery
     */
    public $query;

    /**
     * @var string
     */
    public $name;

    public function __construct(SelectQuery $query, string $name)
    {
        $this->query = $query;
        $this->name = $name;
        $this->precedence = 0;
        $this->type = TokenType::CLAUSE;
        $this->start = $query->start;
    }

    /**
     * @return bool
     */
    public function isWellFormed()
    {
        return true;
    }
}
