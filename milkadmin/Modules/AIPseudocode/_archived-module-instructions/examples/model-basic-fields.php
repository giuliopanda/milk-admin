<?php
// Basic Model Field Types

// String fields
->string('username', 255)->required()
->string('email', 255)->required()->formType('email')
->string('url', 500)->label('URL')

// Title field (special string used in belongsTo)
->title()->index()

// Text fields
->text('content')->formType('editor')
->text('description')->nullable()->formType('textarea')
->text('permissions')->default('{}')

// Integer fields
->int('status')->default(0)
->int('post_id')->formType('hidden')

// Boolean
->boolean('is_admin')->default(0)

// Datetime fields
->datetime('registered')->nullable()
->datetime('last_login')->nullable()
->datetime('start_datetime')
->datetime('end_datetime')

// Decimal
->decimal('price', 10, 2)->default(0)
