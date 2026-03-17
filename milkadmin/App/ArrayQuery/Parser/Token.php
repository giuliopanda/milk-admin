<?php
/**
 * Derivato da vimeo/php-mysql-engine
 * Copyright (c) 2019-present Scott Sandler, Slack Technologies, Matt Brown, Vimeo
 * MIT License - Vedi LICENSES/MIT-vimeo-php-mysql-engine.txt
 */

namespace App\ArrayQuery\Parser;

use App\ArrayQuery\Support\TokenType;

class Token
{
    /**
     * @var TokenType::*
     */
    public $type;

    /**
     * @var string
     */
    public $value;

    /**
     * @var string
     */
    public $raw;

    /**
     * @var int
     */
    public $start;

    /**
     * @var ?string
     */
    public $parameterName;

    /**
     * @var ?int
     */
    public $parameterOffset;

    /**
     * @param TokenType::* $type
     */
    public function __construct(
        string $type,
        string $value,
        string $raw,
        int $start
    ) {
        $this->type = $type;
        $this->value = $value;
        $this->raw = $raw;
        $this->start = $start;
    }
}
