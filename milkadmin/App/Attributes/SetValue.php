<?php
namespace App\Attributes;

!defined('MILK_DIR') && die();

#[\Attribute(\Attribute::TARGET_METHOD)]
class SetValue {

    public function __construct(
        public string $field_name
    ) {}
}