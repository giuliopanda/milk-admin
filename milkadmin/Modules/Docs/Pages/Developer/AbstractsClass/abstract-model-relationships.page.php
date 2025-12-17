<?php
namespace Modules\Docs\Pages;
/**
 * @title Model Relationships
 * @guide developer
 * @order 53
 * @tags model, relationships, hasOne, hasMany, belongsTo, foreign key, lazy loading, batch loading, eager loading, with, cascade save
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Model Relationships</h1>
    <p class="text-muted">Revision: 2025/12/14</p>
    <p class="lead">Define and use relationships between models with <code>hasOne</code>, <code>hasMany</code>, and <code>belongsTo</code>.</p>

    <div class="alert alert-info">
        <strong>Key Features:</strong>
        <ul class="mb-0">
            <li><strong>Lazy Loading:</strong> Related data loads only when accessed</li>
            <li><strong>Batch Loading:</strong> Prevents N+1 queries automatically</li>
            <li><strong>Eager Loading:</strong> Preload relationships with <code>with()</code></li>
            <li><strong>Cascade Save:</strong> Save related records with <code>save($cascade = true)</code></li>
            <li><strong>Cascade Delete:</strong> Auto-delete child records with CASCADE</li>
        </ul>
    </div>

    <h2 class="mt-4">Relationship Methods Reference</h2>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Parameters</th>
                    <th>Cascade Save</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>hasOne()</code></td>
                    <td>
                        <code>$alias, $relatedModel, $foreignKey, $onDelete = 'CASCADE', $allowCascadeSave = false</code>
                    </td>
                    <td>✅ If <code>allowCascadeSave = true</code></td>
                    <td>1:1 relationship. Foreign key in related table.</td>
                </tr>
                <tr>
                    <td><code>hasMany()</code></td>
                    <td>
                        <code>$alias, $relatedModel, $foreignKey, $onDelete = 'CASCADE', $allowCascadeSave = false</code>
                    </td>
                    <td>✅ If <code>allowCascadeSave = true</code></td>
                    <td>1:N relationship. Foreign key in related table.</td>
                </tr>
                <tr>
                    <td><code>belongsTo()</code></td>
                    <td>
                        <code>$alias, $relatedModel, $relatedKey = 'id'</code>
                    </td>
                    <td>❌ Never saved</td>
                    <td>N:1 relationship. Foreign key in this table.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="alert alert-warning">
        <strong>Important - Cascade Save Behavior:</strong>
        <ul class="mb-0">
            <li><code>save($cascade = false)</code>: Default. Saves only the main record.</li>
            <li><code>save($cascade = true)</code>: Saves main record + related records that have <code>allowCascadeSave = true</code>.</li>
            <li><code>belongsTo</code> relationships are <strong>never saved</strong> (read-only).</li>
        </ul>
    </div>

    <h2 class="mt-4">Understanding Relationships</h2>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Foreign Key Location</th>
                    <th>Cardinality</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>hasOne()</code></td>
                    <td>In the RELATED table</td>
                    <td>1:1</td>
                    <td>Actor → Biography</td>
                </tr>
                <tr>
                    <td><code>belongsTo()</code></td>
                    <td>In THIS table</td>
                    <td>N:1</td>
                    <td>Post → User</td>
                </tr>
                <tr>
                    <td><code>hasMany()</code></td>
                    <td>In the RELATED table</td>
                    <td>1:N</td>
                    <td>Author → Books</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">hasOne Relationship</h2>

    <p>One record owns one related record. Foreign key is in the related table.</p>

    <h3 class="mt-3">Definition</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// ActorsModel
protected function configure($rule): void {
    $rule->table('#__actors')
        ->id()
            ->hasOne('biography', BiographyModel::class, 'actor_id', 'CASCADE', true)
        ->string('name', 100)->required();
}

// BiographyModel (contains actor_id foreign key)
protected function configure($rule): void {
    $rule->table('#__biography')
        ->id()
        ->int('actor_id')->nullable()
        ->text('bio_text')->nullable();
}</code></pre>

    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$alias</code>: Property name ('biography')</li>
        <li><code>$relatedModel</code>: BiographyModel::class</li>
        <li><code>$foreignKey</code>: Field in related table ('actor_id')</li>
        <li><code>$onDelete</code>: CASCADE | SET NULL | RESTRICT</li>
        <li><code>$allowCascadeSave</code>: <code>true</code> to enable cascade save</li>
    </ul>

    <h3 class="mt-3">Usage</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Read
$actor = $actorsModel->getById(1);
$bio = $actor->biography; // Lazy loaded

if ($bio !== null) {
    echo $bio->bio_text;
}

// Cascade Save (requires allowCascadeSave = true)
$actor = new ActorsModel();
$actor->name = 'Robert De Niro';
$actor->biography = [
    'bio_text' => 'American actor...'
];

$actor->save(true); // Saves actor + biography
// biography.actor_id automatically set to actor.id</code></pre>

    <h2 class="mt-4">belongsTo Relationship</h2>

    <p>Record belongs to a parent record. Foreign key is in this table. <strong>Read-only - never saved via cascade.</strong></p>

    <h3 class="mt-3">Definition</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// PostModel (contains user_id foreign key)
protected function configure($rule): void {
    $rule->table('#__posts')
        ->id()
        ->string('title', 200)->required()
        ->int('user_id')
            ->nullable()
            ->belongsTo('user', UserModel::class, 'id');
}

// UserModel
protected function configure($rule): void {
    $rule->table('#__users')
        ->id()
        ->string('username', 100)->required();
}</code></pre>

    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$alias</code>: Property name ('user')</li>
        <li><code>$relatedModel</code>: UserModel::class</li>
        <li><code>$relatedKey</code>: Primary key in parent table (default: 'id')</li>
    </ul>

    <h3 class="mt-3">Usage</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Read
$post = $postsModel->getById(1);
$user = $post->user; // Lazy loaded

if ($user !== null) {
    echo "Author: " . $user->username;
}

// Save - belongsTo is NEVER saved automatically
$post = new PostModel();
$post->title = 'My Post';
$post->user_id = 5; // Must set foreign key manually

$post->save(); // Only saves the post
// User with ID 5 must already exist</code></pre>

    <div class="alert alert-info">
        <strong>Why belongsTo is Read-Only:</strong> The parent record (User) exists independently. Creating/modifying it when saving a child (Post) doesn't make semantic sense.
    </div>

    <h2 class="mt-4">hasMany Relationship</h2>

    <p>One record owns multiple related records. Foreign key is in the related table.</p>

    <h3 class="mt-3">Definition</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// AuthorModel
protected function configure($rule): void {
    $rule->table('#__authors')
        ->id()
            ->hasMany('books', BookModel::class, 'author_id', 'CASCADE', true)
        ->string('name', 100)->required();
}

// BookModel (contains author_id foreign key)
protected function configure($rule): void {
    $rule->table('#__books')
        ->id()
        ->int('author_id')->nullable()->index()
        ->string('title', 200)->required();
}</code></pre>

    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$alias</code>: Property name ('books')</li>
        <li><code>$relatedModel</code>: BookModel::class</li>
        <li><code>$foreignKey</code>: Field in related table ('author_id')</li>
        <li><code>$onDelete</code>: CASCADE | SET NULL | RESTRICT</li>
        <li><code>$allowCascadeSave</code>: <code>true</code> to enable cascade save</li>
    </ul>

    <h3 class="mt-3">Usage</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Read
$author = $authorsModel->getById(1);
$books = $author->books; // Returns array of BookModel

foreach ($books as $book) {
    echo $book->title . "\n";
}

// Cascade Save (requires allowCascadeSave = true)
$author = new AuthorModel();
$author->name = 'J.K. Rowling';
$author->books = [
    ['title' => 'Harry Potter 1'],
    ['title' => 'Harry Potter 2'],
    ['title' => 'Harry Potter 3']
];

$author->save(true); // Saves author + all books
// Each book.author_id automatically set to author.id</code></pre>

    <h2 class="mt-4">Cascade Save</h2>

    <h3 class="mt-3">Overview</h3>

    <p>Control whether related records are saved with the main record:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Default: save(false) - Only saves main record
$post->comments = [...];
$post->save(); // Comments NOT saved

// Cascade: save(true) - Saves main + related (if allowCascadeSave = true)
$post->comments = [...];
$post->save(true); // Comments saved</code></pre>

    <h3 class="mt-3">Requirements</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Relationship Type</th>
                    <th>Requires</th>
                    <th>Saved when</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>hasOne</code></td>
                    <td><code>allowCascadeSave = true</code></td>
                    <td><code>save(true)</code> AND <code>allowCascadeSave = true</code></td>
                </tr>
                <tr>
                    <td><code>hasMany</code></td>
                    <td><code>allowCascadeSave = true</code></td>
                    <td><code>save(true)</code> AND <code>allowCascadeSave = true</code></td>
                </tr>
                <tr>
                    <td><code>belongsTo</code></td>
                    <td>N/A</td>
                    <td>Never</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3 class="mt-3">Example: Posts with Comments</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// PostsModel - Enable cascade save for comments
protected function configure($rule): void {
    $rule->table('#__posts')
        ->id()
            ->hasMany('comments', CommentsModel::class, 'post_id', 'CASCADE', true)
        ->title();
}

// Usage
$post = new PostsModel();
$post->title = 'My Post';
$post->comments = [
    ['comment' => 'Great post!'],
    ['comment' => 'Thanks for sharing!']
];

// Save with cascade
if ($post->save(true)) {
    $results = $post->getCommitResults();
    echo "Post ID: " . $results[0]['id'] . "\n";
    echo "Comments saved: " . count($results[0]['comments']) . "\n";
}</code></pre>

    <h2 class="mt-4">Cascade Delete</h2>

    <p>Control what happens to related records when parent is deleted:</p>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Behavior</th>
                    <th>Effect</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>CASCADE</code></td>
                    <td>Delete child records automatically</td>
                </tr>
                <tr>
                    <td><code>SET NULL</code></td>
                    <td>Set foreign key to NULL in child records</td>
                </tr>
                <tr>
                    <td><code>RESTRICT</code></td>
                    <td>Prevent deletion if child records exist</td>
                </tr>
            </tbody>
        </table>
    </div>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// CASCADE: Delete author deletes all books
->hasMany('books', BookModel::class, 'author_id', 'CASCADE')

$authorsModel->delete(1); // Author + all books deleted

// SET NULL: Delete author keeps books
->hasMany('books', BookModel::class, 'author_id', 'SET NULL')

$authorsModel->delete(1); // Books remain with author_id = NULL

// RESTRICT: Cannot delete if books exist
->hasMany('books', BookModel::class, 'author_id', 'RESTRICT')

if (!$authorsModel->delete(1)) {
    echo $authorsModel->getLastError(); // "Cannot delete: has child records"
}</code></pre>

    <h2 class="mt-4">Querying Relationships</h2>

    <h3 class="mt-3">whereHas() - Filter by Related Data</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Find authors with books after 2020
$authors = $authorsModel
    ->whereHas('books', 'published_year > ?', [2020])
    ->getResults();

// Combine with WHERE
$usAuthors = $authorsModel
    ->where('country = ?', ['USA'])
    ->whereHas('books', 'price > ?', [20])
    ->getResults();</code></pre>

    <h2 class="mt-4">Eager Loading with with()</h2>

    <p>Preload relationships for data export (API responses, JSON, etc.):</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Single relationship
$appointments = $appointmentModel->getAll()->with('doctor');
$data = $appointments->getFormattedData();

foreach ($data as $appointment) {
    echo $appointment->doctor->name; // Already loaded
}

// Multiple relationships
$posts = $postModel->getAll()->with(['author', 'comments']);

// All relationships
$authors = $authorModel->getAll()->with(null);</code></pre>

    <div class="alert alert-info">
        <strong>with() vs Lazy Loading:</strong>
        <ul class="mb-0">
            <li><strong>Without with():</strong> Relationships load on access (magic properties)</li>
            <li><strong>With with():</strong> Relationships included in exported data (getFormattedData, etc.)</li>
        </ul>
    </div>

    <h2 class="mt-4">Batch Loading (N+1 Prevention)</h2>

    <p>Automatically optimized to prevent N+1 queries:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$authors = $authorsModel->getAll(); // 1 query

foreach ($authors as $author) {
    // First access loads ALL biographies in 1 query
    $bio = $author->biography; // Total: 2 queries (not N+1)
}</code></pre>

    <h2 class="mt-4">Nested Relationships</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Author -> Books -> Reviews
class ReviewModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__reviews')
            ->id()
            ->int('book_id')->index()
            ->int('rating');
    }
}

class BookModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__books')
            ->id()
                ->hasMany('reviews', ReviewModel::class, 'book_id')
            ->int('author_id')->index()
            ->string('title', 200);
    }
}

class AuthorModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__authors')
            ->id()
                ->hasMany('books', BookModel::class, 'author_id')
            ->string('name', 100);
    }
}

// Usage
$author = $authorsModel->getById(1);
foreach ($author->books as $book) {
    echo $book->title . "\n";
    foreach ($book->reviews as $review) {
        echo "  Rating: " . $review->rating . "\n";
    }
}</code></pre>

    <h2 class="mt-4">Troubleshooting</h2>

    <div class="alert alert-warning">
        <strong>Relationship returns null:</strong>
        <ul class="mb-0">
            <li>Check foreign key field exists and has correct values</li>
            <li>Verify relationship alias is correct</li>
            <li>Ensure related model class is correct</li>
        </ul>
    </div>

    <div class="alert alert-warning">
        <strong>Cascade save not working:</strong>
        <ul class="mb-0">
            <li>Ensure <code>allowCascadeSave = true</code> in relationship definition</li>
            <li>Call <code>save(true)</code> not <code>save()</code></li>
            <li>Check that relationship data is provided as array</li>
        </ul>
    </div>

    <div class="alert alert-warning">
        <strong>Type compatibility error:</strong>
        <ul class="mb-0">
            <li>Foreign key and primary key must have compatible types</li>
            <li><code>id</code> ↔ <code>int</code> ↔ <code>string</code> are compatible</li>
        </ul>
    </div>

    <h3 class="mt-3">Debug Relationships</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Check relationship configuration
$rules = $model->getRules();
if (isset($rules['id']['relationship'])) {
    print_r($rules['id']['relationship']);
}

// Check if relationship exists
if ($model->hasRelationship('books')) {
    echo "Relationship exists";
}</code></pre>

    <h2 class="mt-4">See Also</h2>

    <div class="alert alert-info">
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-overview">Model Overview</a></li>
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-queries">Query Builder</a></li>
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-crud">CRUD Operations</a></li>
            <li><a href="?page=docs&action=Framework/Core/rulebuilder">RuleBuilder</a></li>
        </ul>
    </div>
</div>
