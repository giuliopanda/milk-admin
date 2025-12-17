<?php
// Model Extensions

// Audit extension - adds created_by, created_at, updated_by, updated_at
->extensions(['Audit'])

// SoftDelete extension - adds deleted_at, deleted_by
->extensions(['SoftDelete'])

// Author extension - adds author tracking
->extensions(['Author'])

// Multiple extensions
->extensions(['Audit', 'SoftDelete', 'Author'])

// Extension with options
->extensions(['Author' => ['show_username' => true]])

// Complete example from PostsModel
$rule->table('#__posts')
    ->id()
    ->title()->index()
    ->extensions(['Audit', 'SoftDelete', 'Author'])
    ->text('content')->formType('editor');
// Extensions auto-add fields, no need to define them
