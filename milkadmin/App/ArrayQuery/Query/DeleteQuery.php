<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Query;

use App\ArrayQuery\Support\JoinType;
use App\ArrayQuery\Query\Expression\Expression;
use App\ArrayQuery\Query\Expression\SubqueryExpression;

final class DeleteQuery
{
    /**
     * @var ?Expression
     */
    public $whereClause = null;

    /**
     * @var array<int, array{expression: Expression, direction: string}>|null
     */
    public $orderBy = null;

    /**
     * @var LimitClause|null
     */
    public $limitClause = null;

    /**
     * @var array{
     *      name:string,
     *      subquery:SubqueryExpression,
     *      join_type:JoinType::*,
     *      join_operator:'ON'|'USING',
     *      alias:string,
     *      join_expression:null|Expression
     * }|null
     */
    public $fromClause = null;

    /**
     * @var string
     */
    public $sql;

    public function __construct(string $sql)
    {
        $this->sql = $sql;
    }
}
