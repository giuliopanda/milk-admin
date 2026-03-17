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
final class JoinType
{
    const JOIN = 'JOIN';
    const LEFT = 'LEFT';
    const RIGHT = 'RIGHT';
    const CROSS = 'CROSS';
    const STRAIGHT = 'STRAIGHT_JOIN';
    const NATURAL = 'NATURAL';

    private function __construct()
    {
    }
}
