<?php
namespace App\Attributes;

!defined('MILK_DIR') && die();

#[\Attribute(\Attribute::TARGET_METHOD)]
class RequestAction {

    public function __construct(
        public string $action,
        public ?string $url = null
    ) {
        $this->url = $this->url ?? $this->action;
    }
}