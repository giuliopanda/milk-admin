<?php
namespace App\Attributes;

!defined('MILK_DIR') && die();

#[\Attribute(\Attribute::TARGET_METHOD)]
class GetRawValue {

    public function __construct(
        public string $field_name
    ) {}
}