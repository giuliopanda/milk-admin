<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Query;

use App\ArrayQuery\Query\Expression\ConstantExpression;
use App\ArrayQuery\Query\Expression\Expression;
use App\ArrayQuery\Query\Expression\NamedPlaceholderExpression;
use App\ArrayQuery\Query\Expression\QuestionMarkPlaceholderExpression;

final class LimitClause
{
    /** @var ConstantExpression|NamedPlaceholderExpression|QuestionMarkPlaceholderExpression|null */
    public $offset;

    /** @var ConstantExpression|NamedPlaceholderExpression|QuestionMarkPlaceholderExpression */
    public $rowcount;

    /**
     * @param ConstantExpression|NamedPlaceholderExpression|QuestionMarkPlaceholderExpression $offset
     * @param ConstantExpression|NamedPlaceholderExpression|QuestionMarkPlaceholderExpression $rowcount
     */
    public function __construct(?Expression $offset, Expression $rowcount)
    {
        $this->offset = $offset;
        $this->rowcount = $rowcount;
    }
}
