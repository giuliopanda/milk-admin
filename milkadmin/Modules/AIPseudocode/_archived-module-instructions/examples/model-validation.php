<?php
// Model Validation Examples

use App\Attributes\Validate;

// Title validation - minimum length
#[Validate('title')]
public function validateTitle($current_record_obj): string {
    $value = $current_record_obj->title;
    if (strlen($value) < 5) {
        return 'Title must be at least 5 characters long';
    }
    return '';
}

// URL validation - filter_var
#[Validate('url')]
public function validateUrl($current_record_obj): string
{
    $value = $current_record_obj->url;
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        return 'Please enter a valid URL';
    }
    return '';
}
