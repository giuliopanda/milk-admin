<?php
namespace Modules\Docs\Pages;
/**
 * @title Model Relationships
 * @guide Models
 * @order 53
 * @tags model, relationships, hasOne, hasMany, belongsTo, foreign key, lazy loading, batch loading, eager loading, with, cascade save, withoutGlobalScopes
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Model Relationships</h1>
    <p class="text-muted">Revision: 2025/12/27</p>
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
                <tr>
                    <td><code>withCount()</code></td>
                    <td>
                        <code>$alias, $relatedModel, $foreignKey</code>
                    </td>
                    <td>❌ Read-only</td>
                    <td>Adds a COUNT subquery. Returns count of related records.</td>
                </tr>
                <tr>
                    <td><code>where()</code></td>
                    <td>
                        <code>$condition, $params = []</code>
                    </td>
                    <td>N/A</td>
                    <td>Filter relationship query. Must be called immediately after a relationship method.</td>
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

    <h2 class="mt-4">Disabling Relationships</h2>

    <p>Control when and how relationships are loaded or disabled for performance optimization or to prevent circular dependencies.</p>

    <h3 class="mt-3">Methods Reference</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Parameters</th>
                    <th>What it Disables</th>
                    <th>Use Case</th>
                </tr>
            </thead>
            <tbody>
              
                <tr>
                    <td><code>withoutGlobalScope()</code></td>
                    <td><code>string|array $scopes</code></td>
                    <td>Specific withCount scope(s)</td>
                    <td>Disable COUNT subqueries for specific relationships</td>
                </tr>
                <tr>
                    <td><code>withoutGlobalScopes()</code></td>
                    <td>None</td>
                    <td>All withCount scopes and default scopes</td>
                    <td>Disable all COUNT subqueries and default query filters</td>
                </tr>
                <tr>
                    <td><code>with([])</code></td>
                    <td><code>array</code> (empty)</td>
                    <td>Eager loading for data export</td>
                    <td>Prevent relationships from being included in getFormattedData()</td>
                </tr>
                <tr>
                    <td>Don't use <code>with()</code></td>
                    <td>N/A</td>
                    <td>Eager loading (default behavior)</td>
                    <td>Relationships load only on access (lazy), not pre-loaded</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3 class="mt-3">Usage Examples</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// 1. Block lazy loading completely
$corso = $corsiModel->getById(1);

$frequenze = $corso->frequenze; // Returns null instead of loading data


// Re-enable lazy loading


// 2. Disable specific withCount
$authors = $authorsModel
    ->withoutGlobalScope('withCount:books_count')
    ->getAll();
// books_count will be null in results

// 3. Disable all withCount and default scopes
$authors = $authorsModel
    ->withoutGlobalScopes()
    ->getAll();
// All COUNT subqueries and default WHERE clauses removed

// 4. Query without eager loading for export
$corsi = $corsiModel->getAll();
// Relationships NOT included in getFormattedData()
// But lazy loading still works: $corso->frequenze

// 5. Combine methods for complete control
$corsi = $corsiModel
    ->withoutGlobalScopes()           // No withCount
    ->getAll()
// Maximum performance - no relationship queries at all</code></pre>

    <h3 class="mt-3">Performance Comparison</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Scenario</th>
                    <th>SQL Queries</th>
                    <th>Methods Used</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Default behavior</td>
                    <td>1 main + withCount subqueries + lazy loading on access</td>
                    <td>None</td>
                </tr>
                <tr>
                    <td>Disable withCount only</td>
                    <td>1 main + lazy loading on access</td>
                    <td><code>withoutGlobalScopes()</code></td>
                </tr>
              
            </tbody>
        </table>
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

    <h2 class="mt-4">withCount - Count Related Records</h2>

    <p>Add a COUNT subquery to efficiently count related records without loading them. The count is automatically included in query results as a virtual field.</p>

    <h3 class="mt-3">Definition</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// AuthorModel
protected function configure($rule): void {
    $rule->table('#__authors')
        ->id()
            ->withCount('books_count', BookModel::class, 'author_id')
        ->string('name', 100)->required();
}

// BookModel (contains author_id foreign key)
protected function configure($rule): void {
    $rule->table('#__books')
        ->id()
        ->int('author_id')->nullable()->index()
        ->string('title', 200)->required()
        ->string('status', 20); // 'published', 'draft', 'deleted'
}</code></pre>

    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$alias</code>: Virtual field name for the count ('books_count')</li>
        <li><code>$relatedModel</code>: BookModel::class</li>
        <li><code>$foreignKey</code>: Field in related table ('author_id')</li>
    </ul>

    <h3 class="mt-3">Key Features</h3>

    <div class="alert alert-info">
        <ul class="mb-0">
            <li><strong>Efficient:</strong> Single SQL query with COUNT subquery - no N+1 problem</li>
            <li><strong>Automatic:</strong> Count always included in queries (like default scopes)</li>
            <li><strong>Scope-Aware:</strong> Applies default scopes from related model (e.g., excludes soft-deleted records)</li>
            <li><strong>Disableable:</strong> Can be disabled with <code>withoutGlobalScope('withCount:alias')</code></li>
        </ul>
    </div>

    <h3 class="mt-3">Usage</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Read single record
$author = $authorsModel->getById(1);
echo "Books: " . $author->books_count; // 5

// Read all records
$authors = $authorsModel->getAll();
foreach ($authors as $author) {
    echo $author->name . ": " . $author->books_count . " books\n";
}

// Use in table views (GetDataBuilder)
// Count automatically appears in list columns</code></pre>

    <h3 class="mt-3">Generated SQL</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-sql">SELECT
    authors.*,
    (SELECT COUNT(*)
     FROM books
     WHERE books.author_id = authors.author_id) AS books_count
FROM authors</code></pre>

    <h3 class="mt-3">Applying Related Model Scopes</h3>

    <p>withCount automatically applies default scopes from the related model:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// BookModel with default scope
class BookModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__books')
            ->id()
            ->int('author_id')
            ->string('status', 20);
    }

    #[App\Attributes\DefaultQuery]
    protected function onlyPublished($query) {
        return $query->where('status = ?', ['published']);
    }
}

// AuthorModel
protected function configure($rule): void {
    $rule->table('#__authors')
        ->id()
            ->withCount('published_books', BookModel::class, 'author_id')
        ->string('name', 100);
}

// Usage
$author = $authorsModel->getById(1);
// published_books counts ONLY published books (scope applied)</code></pre>

    <h3 class="mt-3">Disabling withCount</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Disable specific withCount
$author = $authorsModel
    ->withoutGlobalScope('withCount:books_count')
    ->getById(1);

// books_count will be null

// Disable all default scopes (including withCount)
$authors = $authorsModel
    ->withoutGlobalScopes()
    ->getAll();</code></pre>

    <h3 class="mt-3">Multiple withCount</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function configure($rule): void {
    $rule->table('#__authors')
        ->id()
            ->withCount('books_count', BookModel::class, 'author_id')
            ->withCount('reviews_count', ReviewModel::class, 'author_id')
        ->string('name', 100);
}

// Usage
$author = $authorsModel->getById(1);
echo "Books: " . $author->books_count;
echo "Reviews: " . $author->reviews_count;</code></pre>

    <h2 class="mt-4">Filtering Relationships with where()</h2>

    <p>Apply custom WHERE conditions to relationship queries using the <code>where()</code> method. This method must be called immediately after a relationship method (withCount, hasMany, hasOne, belongsTo).</p>

    <h3 class="mt-3">Syntax</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->relationshipMethod(...)->where($condition, $params)</code></pre>

    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$condition</code>: SQL WHERE condition with <code>?</code> placeholders</li>
        <li><code>$params</code>: Array of parameters to bind to placeholders</li>
    </ul>

    <div class="alert alert-warning">
        <strong>Important:</strong> <code>where()</code> must be called <strong>immediately after</strong> a relationship method. Calling it elsewhere will throw a <code>LogicException</code>.
    </div>

    <h3 class="mt-3">Usage with withCount</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Count only available lessons
protected function configure($rule): void {
    $rule->table('corsi')
        ->id()
            ->withCount('lezioni_disponibili', LezioniModel::class, 'MATR_CRS')
                ->where('DISPONIBILE = ?', ['D'])
        ->string('nome', 100);
}

// Usage
$corso = $corsiModel->getById(1);
echo "Available lessons: " . $corso->lezioni_disponibili;

// Multiple conditions with AND
->withCount('lezioni_attive', LezioniModel::class, 'MATR_CRS')
    ->where('DISPONIBILE = ? AND BLOCCO != ?', ['D', 'S'])</code></pre>

    <h3 class="mt-3">Generated SQL for withCount</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-sql">SELECT
    corsi.*,
    (SELECT COUNT(*)
     FROM lezioni
     WHERE lezioni.MATR_CRS = corsi.MATR_CRS
     AND DISPONIBILE = 'S') AS lezioni_disponibili
FROM corsi</code></pre>

    <h3 class="mt-3">Usage with hasMany</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Load only active books
protected function configure($rule): void {
    $rule->table('#__authors')
        ->id()
            ->hasMany('active_books', BookModel::class, 'author_id')
                ->where('status = ?', ['published'])
        ->string('name', 100);
}

// Usage
$author = $authorsModel->getById(1);
$publishedBooks = $author->active_books; // Only published books</code></pre>

    <h3 class="mt-3">Usage with hasOne</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Load only verified profile
protected function configure($rule): void {
    $rule->table('#__users')
        ->id()
            ->hasOne('verified_profile', ProfileModel::class, 'user_id')
                ->where('is_verified = ?', [1])
        ->string('username', 100);
}</code></pre>

    <h3 class="mt-3">Usage with belongsTo</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Load only active author
protected function configure($rule): void {
    $rule->table('#__books')
        ->id()
        ->int('author_id')
            ->belongsTo('active_author', AuthorModel::class, 'id')
                ->where('status = ?', ['active'])
        ->string('title', 200);
}</code></pre>

    <h3 class="mt-3">Combining where() with Default Scopes</h3>

    <p>The <code>where()</code> method is applied in addition to any default scopes defined on the related model:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// BookModel has a default scope to exclude deleted
class BookModel extends AbstractModel {
    #[App\Attributes\DefaultQuery]
    protected function notDeleted($query) {
        return $query->where('deleted_at IS NULL');
    }
}

// AuthorModel adds additional filter
protected function configure($rule): void {
    $rule->table('#__authors')
        ->id()
            ->withCount('published_books', BookModel::class, 'author_id')
                ->where('status = ?', ['published'])
        ->string('name', 100);
}

// Generated SQL includes BOTH conditions
SELECT authors.*,
(SELECT COUNT(*) FROM books
 WHERE books.author_id = authors.id
 AND deleted_at IS NULL          -- From default scope
 AND status = 'published') AS published_books  -- From where()
FROM authors</code></pre>

    <h3 class="mt-3">Error Handling</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// ❌ WRONG - where() not after relationship method
$rule->table('authors')
    ->id()
    ->where('name = ?', ['John'])  // LogicException!

// ✅ CORRECT - where() immediately after relationship
$rule->table('authors')
    ->id()
        ->withCount('books', BookModel::class, 'author_id')
            ->where('status = ?', ['published'])  // OK!
    ->string('name', 100);</code></pre>

    <div class="alert alert-info">
        <strong>Error Message:</strong> If you call <code>where()</code> without an active relationship, you'll get:
        <br><code>LogicException: where() can only be called immediately after a relationship method (withCount, hasMany, hasOne, belongsTo).</code>
    </div>

    <h3 class="mt-3">Difference from hasMany</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>hasMany</th>
                    <th>withCount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Returns</td>
                    <td>Array of related records</td>
                    <td>Integer count only</td>
                </tr>
                <tr>
                    <td>Loading</td>
                    <td>Lazy (on access) or Eager (with <code>with()</code>)</td>
                    <td>Always included in query</td>
                </tr>
                <tr>
                    <td>Performance</td>
                    <td>Loads all data (can be heavy)</td>
                    <td>Fast - single COUNT subquery</td>
                </tr>
                <tr>
                    <td>Use Case</td>
                    <td>When you need the actual records</td>
                    <td>When you only need the count</td>
                </tr>
                <tr>
                    <td>Cascade Save</td>
                    <td>✅ Supported (if enabled)</td>
                    <td>❌ Read-only</td>
                </tr>
            </tbody>
        </table>
    </div>

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
}

</code></pre>

    <h2 class="mt-4">Quick Reference</h2>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Method</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Define 1:1 relationship</td>
                    <td><code>hasOne()</code></td>
                    <td><code>->id()->hasOne('profile', ProfileModel::class, 'user_id')</code></td>
                </tr>
                <tr>
                    <td>Define 1:N relationship</td>
                    <td><code>hasMany()</code></td>
                    <td><code>->id()->hasMany('posts', PostModel::class, 'author_id')</code></td>
                </tr>
                <tr>
                    <td>Define N:1 relationship</td>
                    <td><code>belongsTo()</code></td>
                    <td><code>->int('user_id')->belongsTo('user', UserModel::class)</code></td>
                </tr>
                <tr>
                    <td>Add count subquery</td>
                    <td><code>withCount()</code></td>
                    <td><code>->id()->withCount('posts_count', PostModel::class, 'author_id')</code></td>
                </tr>
                <tr>
                    <td>Filter relationship query</td>
                    <td><code>where()</code></td>
                    <td><code>->withCount('active', Model::class, 'fk')->where('status = ?', ['active'])</code></td>
                </tr>
                <tr>
                    <td>Cascade save child records</td>
                    <td><code>save(true)</code></td>
                    <td><code>$author->save(true)</code> (requires <code>allowCascadeSave = true</code>)</td>
                </tr>
                <tr>
                    <td>Preload for export</td>
                    <td><code>with()</code></td>
                    <td><code>$posts->with(['author', 'comments'])</code></td>
                </tr>
                
                <tr>
                    <td>Disable withCount</td>
                    <td><code>withoutGlobalScopes()</code></td>
                    <td><code>$model->withoutGlobalScopes()->getAll()</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">See Also</h2>

    <div class="alert alert-info">
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-overview">Model Overview</a></li>
            <li><a href="?page=docs&action=Developer/Model/abstract-model-queries">Query Builder</a></li>
            <li><a href="?page=docs&action=Developer/Model/abstract-model-crud">CRUD Operations</a></li>
            <li><a href="?page=docs&action=Developer/Model/abstract-model-attributes">Query Scopes (withoutGlobalScopes)</a></li>
            <li><a href="?page=docs&action=Framework/Core/rulebuilder">RuleBuilder</a></li>
        </ul>
    </div>
</div>
