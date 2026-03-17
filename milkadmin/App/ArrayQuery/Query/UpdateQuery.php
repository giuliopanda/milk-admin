<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Query;

use App\ArrayQuery\Support\JoinType;
use App\ArrayQuery\Query\Expression\BinaryOperatorExpression;
use App\ArrayQuery\Query\Expression\Expression;
use App\ArrayQuery\Query\Expression\SubqueryExpression;
use App\ArrayQuery\Query\LimitClause;

final class UpdateQuery
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
     * @var string
     */
    public $tableName;

    /**
     * @var string
     */
    public $sql;

    /**
     * @var array<int, BinaryOperatorExpression>
     */
    public $setClause = [];

    public function __construct(string $tableName, string $sql)
    {
        $this->tableName = $tableName;
        $this->sql = $sql;
    }
}
