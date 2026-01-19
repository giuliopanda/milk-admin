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
final class TokenType
{
    const NUMERIC_CONSTANT = "Number";
    const STRING_CONSTANT = "String";
    const CLAUSE = "Clause";
    const OPERATOR = "Operator";
    const RESERVED = "Reserved";
    const PAREN = "Paren";
    const SEPARATOR = "Separator";
    const SQLFUNCTION = "Function";
    const IDENTIFIER = "Identifier";
    const NULL_CONSTANT = "Null";

    private function __construct()
    {
    }
}
