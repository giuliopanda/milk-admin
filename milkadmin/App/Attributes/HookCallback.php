<?php
namespace App\Attributes;

!defined('MILK_DIR') && die();

#[\Attribute(\Attribute::TARGET_METHOD)]
class HookCallback {

    public function __construct(
        public string $hook_name,
        public int $order = 20
    ) {
    }
}