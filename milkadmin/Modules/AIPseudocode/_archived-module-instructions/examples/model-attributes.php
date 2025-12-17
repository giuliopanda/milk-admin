<?php
// Model Attributes Examples

use App\Attributes\{Validate, BeforeSave, ToDisplayValue, ToDatabaseValue};

// === VALIDATE ===

#[Validate('title')]
public function validateTitle($current_record_obj): string {
    if (strlen($current_record_obj->title) < 5) {
        return 'Title must be at least 5 characters long';
    }
    return '';
}

#[Validate('url')]
public function validateUrl($current_record_obj): string {
    if (!filter_var($current_record_obj->url, FILTER_VALIDATE_URL)) {
        return 'Please enter a valid URL';
    }
    return '';
}

// === ToDatabaseValue ===

// Hash password
#[BToDatabaseValueeforeSave('password')]
public function sqlPassword($current_record_obj) {
    return password_hash($current_record_obj->password, PASSWORD_DEFAULT);
}

// JSON encode permissions
#[BToDatabaseValueeforeSave('permissions')]
public function sqlPermissions($current_record_obj) {
    $permissions = $current_record_obj->permissions;
    // ... process
    return json_encode($save_permissions);
}

// Set created_by on new records
#[ToDatabaseValue('created_by')]
public function setCreatedBy($current_record, $value) {
    if (empty($current_record->created_by)) {
        $user = \App\Get::make('Auth')->getUser();
        return $user->id ?? 0;
    }
    return $value;
}


// === TODISPLAYVALUE ===

// Show username instead of ID
#[ToDisplayValue('created_by')]
public function getFormattedCreatedBy($current_record) {
    return $current_record->created_user->username ?? '-';
}

// Format timestamp
#[ToDisplayValue('audit_timestamp')]
public function getAuditTimestampFormatted($current_record_obj) {
    $value = $current_record_obj->audit_timestamp;
    return Get::formatDate(date('Y-m-d H:i:s', $value), 'dateTime', true);
}

// User + date combined
#[ToDisplayValue('created_by')]
public function getCreatedByFormatted($current_record_obj) {
    $user = Get::make('Auth')->getUser($current_record_obj->created_by);
    $date = Get::formatDate($current_record_obj->created_at, 'dateTime', true);
    return ($user ? $user->username : '-') . ' - ' . $date;
}

// Hide password
#[ToDisplayValue('password')]
public function hidePassword($obj) {
    return '********';
}
