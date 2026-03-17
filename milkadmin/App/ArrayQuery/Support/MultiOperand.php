<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Support;

/**
 * Generated enum class, do not extend
 */
abstract class MultiOperand
{
    const UNION = 'UNION';
    const UNION_ALL = 'UNION_ALL';
    const EXCEPT = 'EXCEPT';
    const INTERSECT = 'INTERSECT';
}
