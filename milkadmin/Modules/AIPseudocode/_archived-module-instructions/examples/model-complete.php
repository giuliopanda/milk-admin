<?php
// Complete Model Examples

// 1. PostsModel - Simple with extensions
$rule->table('#__posts')
    ->id()
    ->title()->index()
    ->extensions(['Audit', 'SoftDelete', 'Author'])
    ->text('content')->formType('editor');

// 2. EventsModel - Datetime and list
$rule->table('#__events')
    ->id()
    ->string('title', 255)->index()
    ->text('description')->formType('editor')
    ->datetime('start_datetime')
    ->datetime('end_datetime')
    ->list('event_class', [
        'event-primary' => 'Primary',
        'event-success' => 'Success'
    ])->default('event-primary');

// 3. LinksDataModel - Dynamic list, validation
$rule->table('#__links')
    ->id()
    ->list('category_id', $categories)->label('Category')
    ->title()->index()
    ->string('url', 500)->label('URL')
    ->extensions(['Author' => ['show_username' => true]])
    ->text('description')->nullable()->formType('textarea');

// 4. UserModel - Complex with password, permissions
$rule->table('#__users')
    ->id()
    ->string('username', 255)->required()
    ->string('email', 255)->required()->formType('email')
    ->string('password', 255)->required()->formType('password')
    ->datetime('registered')->nullable()
    ->int('status')->default(0)
        ->formType('list')
        ->formParams(['options' => [0 => 'Inactive', 1 => 'Active']])
    ->boolean('is_admin')->default(0)
    ->text('permissions')->default('{}')
    ->select('locale', $languages)->default($default_language);

// 5. PostsCommentModel - With belongsTo relationship
$rule->table('#__posts_comments')
    ->id()
    // ✅ CORRECT: Chain belongsTo immediately after the foreign key field
    ->int('post_id')->belongsTo('post', PostsModel::class)->formType('hidden')
    ->text('comment')->formType('editor')
    ->extensions(['Audit']);
// Now you can use: $comment->post->title
