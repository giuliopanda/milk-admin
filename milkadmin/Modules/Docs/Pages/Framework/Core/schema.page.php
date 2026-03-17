<?php
namespace Modules\Docs\Pages;
/**
 * @title Create/modify db tables
 * @guide framework
 * @order 
 * @tags schema, database-schema, table-creation, table-modification, database-migration, field-definition, index-management, MySQL-tables, fluent-syntax, table-structure, database-design
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
   <h1>Schema Class</h1>
   <p class="lead">Create and modify database tables (MySQL or SQLite) using a fluent API.</p>

   <h2 class="mt-4">Create a Schema Instance</h2>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('users');</code></pre>
   <div class="alert alert-info mt-3">
       You can pass a specific connection (e.g. db2):
       <code>$schema = Get::schema('users', Get::db2());</code>
   </div>

   <h2 class="mt-4">Field Methods (Examples)</h2>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema->id()
    ->string('username', 100, false)
    ->text('bio', true)
    ->int('age', false, 18)
    ->boolean('active', false, true)
    ->datetime('created_at');</code></pre>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema->decimal('price', 10, 2)
    ->date('birthday', true)
    ->time('start_time', true)
    ->timestamp('updated_at')
    ->tinyint('priority', false, 5)
    ->longtext('notes', true);</code></pre>

   <h2 class="mt-4">Indexes and Primary Keys</h2>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema->index('email_idx', ['email'])
    ->index('name_email_idx', ['name', 'email'])
    ->index('username_idx', ['username'], true);</code></pre>

   <div class="alert alert-info mt-3">
       <strong>Composite Primary Key:</strong> for MySQL you can use
       <code>$schema->setPrimaryKey(['post_id', 'tag_id']);</code>.
       On SQLite, prefer a unique composite index.
   </div>

   <h2 class="mt-4">Create, Modify, Drop</h2>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('users');
$schema->id()
    ->string('username', 100)
    ->string('email', 100)
    ->string('password', 255)
    ->text('bio', true)
    ->boolean('active', false, true)
    ->datetime('created_at')
    ->datetime('updated_at', true)
    ->index('email_idx', ['email'], true)
    ->index('username_idx', ['username'], true)
    ->create();</code></pre>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('users');
$schema->id()
    ->string('username', 150)
    ->string('email', 100)
    ->boolean('active')
    ->index('email_idx', ['email'])
    ->modify();</code></pre>

   <h2 class="mt-4">Rename a Column</h2>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('users');
$schema->id()
    ->string('name', 150)
    ->renameField('full_name', 'name')
    ->modify();</code></pre>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('temporary_table');
$schema->drop();</code></pre>

   <h2 class="mt-4">Introspection</h2>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('users');

if ($schema->exists()) {
    $fields = $schema->getFields();
}

$differences = $schema->getFieldDifferences();
$lastError = $schema->getLastError();</code></pre>

   <h2 class="mt-4">Complete Examples</h2>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('posts');
$schema->id()
    ->string('title', 200)
    ->text('content')
    ->int('author_id')
    ->string('status', 20, false, 'draft')
    ->datetime('published_at', true)
    ->datetime('created_at')
    ->datetime('updated_at', true)
    ->index('author_id_idx', ['author_id'])
    ->index('status_idx', ['status'])
    ->index('published_idx', ['published_at'])
    ->create();</code></pre>

   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('post_tags');
$schema->int('post_id')
    ->int('tag_id')
    ->datetime('created_at')
    ->index('post_id_idx', ['post_id'])
    ->index('tag_id_idx', ['tag_id'])
    ->index('post_tag_idx', ['post_id', 'tag_id'], true)
    ->create();</code></pre>

</div>
