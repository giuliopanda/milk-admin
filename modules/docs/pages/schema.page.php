<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title Create/modify db tables
 * @category Framework
 * @order 
 * @tags 
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
   <h1>Schema Class Documentation</h1>

   <h2>Description</h2>
   <p>The <code>Schema</code> class handles the creation and modification of MySQL tables programmatically using a fluent syntax.</p>

   <p>You can create a new instance of the Schema class by passing the table name:</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('#__users');</code></pre>

   <h2>Field Definition Methods</h2>
      
   <h4 class="mt-4">id($name = 'id')</h4>
   <p>Create an auto-incrementing primary key field.</p>
   <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema->id();</code></pre>

    <h4 class="mt-4">string($name, $length = 255, $null = false, $default = null, $after = null)</h4>
    <p>Create a VARCHAR field.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema->string('username', 100)               // NOT NULL
       ->string('middle_name', 50, true)       // NULL
       ->string('status', 20, false, 'active') // Default value
       ->string('notes', 200, false, '', 'status'); // After 'status' field</code></pre>

    <h4 class="mt-4">text($name, $null = false, $after = null)</h4>
    <p>Create a TEXT field.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema->text('description', true)  // Nullable text field
       ->text('content')            // Required text field</code></pre>

    <h4 class="mt-4">int($name, $null = false, $default = null, $after = null)</h4>
    <p>Create an INT field.</p>
   <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema->int('age', false, 18)      // NOT NULL DEFAULT 18
       ->int('score', true)         // NULL
       ->int('parent_id', true, null, 'id')  // After 'id' field</code></pre>

    <h4 class="mt-4">decimal($name, $precision = 10, $scale = 2, $null = false, $default = null, $after = null)</h4>
    <p>Create a DECIMAL field.</p>
   <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema->decimal('price', 10, 2)           // 10 digits, 2 decimals
       ->decimal('tax', 5, 2, true)         // Nullable
       ->decimal('total', 10, 2, false, 0)  // Default 0</code></pre>

    <h4 class="mt-4">datetime($name, $null = false, $default = null, $after = null)</h4>
    <p>Create a DATETIME field.</p>
<p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema->datetime('created_at')
       ->datetime('updated_at', true)      // Nullable
       ->datetime('deleted_at', true)      // For soft deletes</code></pre>

    <h4 class="mt-4">timestamp($name, $null = false, $default = 'CURRENT_TIMESTAMP', $after = null)</h4>
    <p>Create a TIMESTAMP field.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema->timestamp('created_at')                    // Default CURRENT_TIMESTAMP
       ->timestamp('updated_at', false, 'CURRENT_TIMESTAMP')  // With explicit default</code></pre>

    <h4 class="mt-4">boolean($name, $null = false, $default = false, $after = null)</h4>
    <p>Create a boolean field (TINYINT(1)).</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema->boolean('active', false, true)   // NOT NULL DEFAULT 1
       ->boolean('verified')               // NOT NULL DEFAULT 0
       ->boolean('deleted', true)          // NULL</code></pre>

    <h4 class="mt-4">index($name, $columns, $unique = false)</h4>
    <p>Create an index on the table.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema->index('email_idx', ['email'])                    // Simple index
       ->index('name_email_idx', ['name', 'email'])         // Compound index
       ->index('username_idx', ['username'], true)          // Unique index</code></pre>

   <h2>Table Manipulation Methods</h2>

    <h4 class="mt-4">create()</h4>
    <p>Create the table in the database.</p>
    <p><strong>Full creation example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('#__users');
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

    <h4 class="mt-4">modify()</h4>
    <p>Modify an existing table by adding, changing, or removing fields and indexes.</p>
    <p><strong>Modification example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"># Prima modify
$schema = Get::schema('#__users');
$schema->id()
    ->string('username', 100)
    ->create();

# Then I add and edit fields
$schema = Get::schema('#__users');
$schema->id()
    ->string('username', 150)        // Change length
    ->string('email', 100)          // Add new field
    ->boolean('active')             // Add new field
    ->index('email_idx', ['email']) // Add index
    ->modify();</code></pre>

    <h4 class="mt-4">drop()</h4>
    <p>Delete the table from the database.</p>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('#__temporary_table');
$schema->drop();</code></pre>

<h2>Common Examples</h2>

<h4>Posts Table</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('#__posts');
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

    <h4>Many-to-Many Relationship Table</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('#__post_tags');
$schema->int('post_id')
    ->int('tag_id')
    ->datetime('created_at')
    ->index('post_id_idx', ['post_id'])
    ->index('tag_id_idx', ['tag_id'])
    ->index('post_tag_idx', ['post_id', 'tag_id'], true)
    ->create();</code></pre>

</div>