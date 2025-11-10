<?php
namespace Modules\Docs\Pages;
/**
 * @title Model Relationships
 * @guide developer
 * @order 53
 * @tags model, relationships, hasOne, hasMany, belongsTo, foreign key, lazy loading, batch loading, eager loading, with
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Model Relationships</h1>
    <p class="text-muted">Revision: 2025/10/14</p>
    <p class="lead">The Model provides powerful relationship support for connecting related data across tables. Relationships are defined in the model's <code>configure()</code> method and support three types: <code>hasOne</code>, <code>hasMany</code>, and <code>belongsTo</code>.</p>

    <div class="alert alert-info">
        <strong>Key Features:</strong>
        <ul class="mb-0">
            <li><strong>Lazy Loading:</strong> Related data is loaded only when accessed</li>
            <li><strong>Batch Loading:</strong> Automatically optimizes queries to avoid N+1 problem</li>
            <li><strong>Eager Loading:</strong> Preload relationships with <code>with()</code> for data export</li>
            <li><strong>Magic Properties:</strong> Access relationships as properties: <code>$actor->biography</code></li>
            <li><strong>Type Safety:</strong> Automatic validation of compatible foreign key types</li>
            <li><strong>Cascade Operations:</strong> Save and delete related records automatically</li>
        </ul>
    </div>

    <h2 class="mt-4">Understanding Relationship Types</h2>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Foreign Key Location</th>
                    <th>Cardinality</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>hasOne()</code></td>
                    <td>One record "owns" one related record</td>
                    <td>In the RELATED table</td>
                    <td>1:1</td>
                    <td>Actor → Biography</td>
                </tr>
                <tr>
                    <td><code>belongsTo()</code></td>
                    <td>One record "belongs to" a parent record</td>
                    <td>In THIS table</td>
                    <td>N:1</td>
                    <td>Post → User</td>
                </tr>
                <tr>
                    <td><code>hasMany()</code></td>
                    <td>One record "owns" many related records</td>
                    <td>In the RELATED table</td>
                    <td>1:N</td>
                    <td>Author → Books</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="alert alert-warning">
        <strong>Important:</strong> The foreign key location determines which relationship type to use:
        <ul class="mb-0">
            <li><code>hasOne</code> and <code>hasMany</code>: Foreign key is in the <em>other</em> table</li>
            <li><code>belongsTo</code>: Foreign key is in <em>this</em> table</li>
        </ul>
    </div>

    <h2 class="mt-4">hasOne Relationship</h2>

    <p>Use <code>hasOne()</code> when one record is associated with exactly one related record, and the foreign key is in the related table.</p>

    <h3 class="mt-3">Defining hasOne</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * Actor hasOne Biography
 * Foreign key (actor_id) is in Biography table
 */

// BiographyModel - Contains the foreign key
class BiographyModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__biography')
            ->id()
            ->string('actor_name', 100)->required()
            ->date('birthdate')->nullable()
            ->text('bio_text')->nullable()
            ->int('actor_id')->nullable(); // Foreign key to Actor
    }
}

// ActorsModel - Defines the relationship
class ActorsModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__actors')
            ->id()
                ->hasOne('biography', BiographyModel::class, 'actor_id', 'CASCADE')
            ->string('name', 100)->required();
    }
}

/**
 * Syntax: hasOne(alias, relatedModel, foreignKeyInRelated, onDelete)
 *
 * @param string $alias           Property name to access relation ('biography')
 * @param string $relatedModel    Related model class (BiographyModel::class)
 * @param string $foreignKeyInRelated  Foreign key in related table ('actor_id')
 * @param string $onDelete        CASCADE | SET NULL | RESTRICT (default: CASCADE)
 */</code></pre>

    <h3 class="mt-3">Using hasOne</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get an actor
$actor = $actorsModel->getById(1);

// Access the biography (triggers lazy loading)
$bio = $actor->biography;

if ($bio !== null) {
    echo "Actor: " . $actor->name . "\n";
    echo "Born: " . $bio->birthdate->format('Y-m-d') . "\n";
    echo "Bio: " . $bio->bio_text . "\n";
} else {
    echo "No biography found for this actor";
}

// Batch loading example - only 1 query for all biographies
$allActors = $actorsModel->getAll();

foreach ($allActors as $actor) {
    // First access triggers batch loading for ALL actors
    $bio = $actor->biography;

    if ($bio !== null) {
        echo $actor->name . " -> " . $bio->actor_name . "\n";
    }
}</code></pre>

    <h3 class="mt-3">Cascade Save with hasOne</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Create actor with biography in one operation
$actor = new ActorsModel();
$actor->fill([
    'name' => 'Robert De Niro',
    'biography' => [
        'actor_name' => 'Robert De Niro',
        'birthdate' => '1943-08-17',
        'bio_text' => 'American actor known for his collaborations with Martin Scorsese.'
    ]
]);

// Save with cascade (saves both actor and biography)
if ($actor->save(cascade: true)) {
    $results = $actor->getCommitResults();

    $actor_id = $results[0]['id'];
    $bio_id = $results[0]['biography']['id'];

    echo "Actor saved with ID: $actor_id\n";
    echo "Biography saved with ID: $bio_id\n";
    echo "Biography.actor_id automatically set to: $actor_id\n";
}</code></pre>

    <h2 class="mt-4">belongsTo Relationship</h2>

    <p>Use <code>belongsTo()</code> when a record belongs to a parent record, and the foreign key is in the current table.</p>

    <h3 class="mt-3">Defining belongsTo</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * Post belongsTo User
 * Foreign key (user_id) is in Post table
 */

// UserModel - The parent model
class UserModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__users')
            ->id()
            ->string('username', 100)->required()
            ->string('email', 255)->nullable();
    }
}

// PostModel - Defines the relationship
class PostModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__posts')
            ->id()
            ->string('title', 200)->required()
            ->text('content')->nullable()
            ->int('user_id')
                ->nullable()
                ->belongsTo('user', UserModel::class, 'id'); // user_id references users.id
    }
}

/**
 * Syntax: belongsTo(alias, relatedModel, relatedKey)
 *
 * @param string $alias          Property name to access relation ('user')
 * @param string $relatedModel   Related model class (UserModel::class)
 * @param string $relatedKey     Primary key in related table (default: 'id')
 */</code></pre>

    <h3 class="mt-3">Using belongsTo</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get a post
$post = $postsModel->getById(1);

// Access the user (triggers lazy loading)
$user = $post->user;

if ($user !== null) {
    echo "Post: " . $post->title . "\n";
    echo "Author: " . $user->username . "\n";
    echo "Email: " . $user->email . "\n";
}

// Batch loading example
$allPosts = $postsModel->getAll();

foreach ($allPosts as $post) {
    $author = $post->user; // Batch loaded for all posts

    if ($author !== null) {
        echo $post->title . " by " . $author->username . "\n";
    }
}</code></pre>

    <h3 class="mt-3">Cascade Save with belongsTo</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Create post with new user
$post = new PostModel();
$post->fill([
    'title' => 'My First Post',
    'content' => 'Post content here...',
    'user' => [
        'username' => 'johndoe',
        'email' => 'john@example.com'
    ]
]);

// Save with cascade (saves user first, then post with user_id)
if ($post->save(cascade: true)) {
    $results = $post->getCommitResults();

    $post_id = $results[0]['id'];
    $user_id = $results[0]['user']['id'];

    echo "User saved with ID: $user_id\n";
    echo "Post saved with ID: $post_id\n";
    echo "Post.user_id automatically set to: $user_id\n";
}</code></pre>

    <h2 class="mt-4">hasMany Relationship</h2>

    <p>Use <code>hasMany()</code> when one record is associated with multiple related records, and the foreign key is in the related table.</p>

    <h3 class="mt-3">Defining hasMany</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
 * Author hasMany Books
 * Foreign key (author_id) is in Books table
 */

// BookModel - Contains the foreign key
class BookModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__books')
            ->id()
            ->string('title', 200)->required()
            ->int('published_year')->nullable()
            ->decimal('price', 8, 2)->nullable()
            ->int('author_id')->nullable()->index(); // Foreign key to Author
    }
}

// AuthorModel - Defines the relationship
class AuthorModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__authors')
            ->id()
                ->hasMany('books', BookModel::class, 'author_id', 'CASCADE')
            ->string('name', 100)->required()
            ->string('country', 50)->nullable();
    }
}

/**
 * Syntax: hasMany(alias, relatedModel, foreignKeyInRelated, onDelete)
 *
 * @param string $alias           Property name to access relation ('books')
 * @param string $relatedModel    Related model class (BookModel::class)
 * @param string $foreignKeyInRelated  Foreign key in related table ('author_id')
 * @param string $onDelete        CASCADE | SET NULL | RESTRICT (default: CASCADE)
 */</code></pre>

    <h3 class="mt-3">Using hasMany</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get an author
$author = $authorsModel->getById(1);

// Access books (returns array of BookModel instances)
$books = $author->books;

if ($books !== null && count($books) > 0) {
    echo "Author: " . $author->name . "\n";
    echo "Books: " . count($books) . "\n\n";

    foreach ($books as $book) {
        echo "- " . $book->title . " (" . $book->published_year . ")\n";
        echo "  Price: €" . $book->price . "\n";
    }
} else {
    echo "No books found for this author";
}

// Batch loading example
$allAuthors = $authorsModel->getAll();

foreach ($allAuthors as $author) {
    $books = $author->books; // Batch loaded for all authors

    if ($books !== null) {
        echo $author->name . " has " . count($books) . " books\n";

        foreach ($books as $book) {
            echo "  - " . $book->title . "\n";
        }
    }
}</code></pre>

    <h3 class="mt-3">Cascade Save with hasMany</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Create author with multiple books
$author = new AuthorModel();
$author->fill([
    'name' => 'J.K. Rowling',
    'country' => 'UK',
    'books' => [
        [
            'title' => 'Harry Potter and the Philosopher\'s Stone',
            'published_year' => 1997,
            'price' => 19.99
        ],
        [
            'title' => 'Harry Potter and the Chamber of Secrets',
            'published_year' => 1998,
            'price' => 19.99
        ],
        [
            'title' => 'Harry Potter and the Prisoner of Azkaban',
            'published_year' => 1999,
            'price' => 19.99
        ]
    ]
]);

// Save with cascade (saves author and all books)
if ($author->save(cascade: true)) {
    $results = $author->getCommitResults();

    $author_id = $results[0]['id'];
    $books = $results[0]['books'];

    echo "Author saved with ID: $author_id\n";
    echo "Books saved: " . count($books) . "\n";

    foreach ($books as $book) {
        echo "- Book ID: " . $book['id'] . " (author_id set to $author_id)\n";
    }
}</code></pre>

    <h2 class="mt-4">Querying with Relationships</h2>

    <h3 class="mt-3">whereHas() - Filter by Related Data</h3>

    <p>The <code>whereHas()</code> method filters records based on conditions in related tables using an efficient EXISTS subquery.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Find authors who have books published after 2020
$authors = $authorsModel
    ->whereHas('books', 'published_year > ?', [2020])
    ->getResults();

echo "Authors with books after 2020:\n";
foreach ($authors as $author) {
    echo "- " . $author->name . "\n";
}

// Find books with high-rated reviews
$books = $booksModel
    ->whereHas('reviews', 'rating > ?', [4])
    ->order('title', 'asc')
    ->getResults();

// Combine with regular WHERE clauses
$usAuthors = $authorsModel
    ->where('country = ?', ['USA'])
    ->whereHas('books', 'published_year > ?', [2020])
    ->getResults();

echo "US authors with recent books:\n";
foreach ($usAuthors as $author) {
    echo "- " . $author->name . "\n";

    // You can still access the relationship
    $books = $author->books;
    if ($books !== null) {
        foreach ($books as $book) {
            if ($book->published_year > 2020) {
                echo "  * " . $book->title . " (" . $book->published_year . ")\n";
            }
        }
    }
}</code></pre>

    <div class="alert alert-info">
        <strong>Performance Note:</strong> <code>whereHas()</code> uses an EXISTS subquery which is very efficient and doesn't affect lazy loading. The relationship data can still be accessed normally after filtering.
    </div>

    <h3 class="mt-3">Complex Queries with Multiple Relationships</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Find products that have been ordered recently AND have high stock
$products = $productsModel
    ->where('in_stock = ?', [true])
    ->where('stock_quantity > ?', [10])
    ->whereHas('orders', 'created_at > ?', [date('Y-m-d', strtotime('-30 days'))])
    ->order('name', 'asc')
    ->limit(0, 20)
    ->getResults();

// Access relationships after filtering
foreach ($products as $product) {
    echo $product->name . " (Stock: " . $product->stock_quantity . ")\n";

    // Get recent orders
    $orders = $product->orders;
    if ($orders !== null) {
        $recentOrders = array_filter($orders, function($order) {
            return $order->created_at > new DateTime('-30 days');
        });
        echo "  Recent orders: " . count($recentOrders) . "\n";
    }
}</code></pre>

    <h2 class="mt-4">Delete Behaviors</h2>

    <p>Control what happens to related records when the parent is deleted using the <code>onDelete</code> parameter:</p>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Behavior</th>
                    <th>Description</th>
                    <th>Use Case</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>CASCADE</code></td>
                    <td>Delete related records automatically</td>
                    <td>Author deleted → all their books are deleted</td>
                </tr>
                <tr>
                    <td><code>SET NULL</code></td>
                    <td>Set foreign key to NULL</td>
                    <td>Author deleted → books.author_id becomes NULL</td>
                </tr>
                <tr>
                    <td><code>RESTRICT</code></td>
                    <td>Prevent deletion if related records exist</td>
                    <td>Cannot delete author if they have books</td>
                </tr>
            </tbody>
        </table>
    </div>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Define relationship with CASCADE (default)
->id()->hasMany('books', BookModel::class, 'author_id', 'CASCADE')

// When author is deleted, all books are deleted too
$author = $authorsModel->getById(1);
if ($authorsModel->delete(1)) {
    echo "Author and all their books deleted";
}

// Define relationship with SET NULL
->id()->hasMany('books', BookModel::class, 'author_id', 'SET NULL')

// When author is deleted, books.author_id becomes NULL
$authorsModel->delete(1);
// Books remain but author_id is NULL

// Define relationship with RESTRICT
->id()->hasMany('books', BookModel::class, 'author_id', 'RESTRICT')

// Cannot delete author if they have books
if (!$authorsModel->delete(1)) {
    echo "Cannot delete: Author has books";
}</code></pre>

    <h2 class="mt-4">Eager Loading with with()</h2>

    <p>The <code>with()</code> method allows you to preload relationships when exporting data with <code>getFormattedData()</code>, <code>getRawData()</code>, <code>getSqlData()</code>, or <code>toArray()</code>. This includes relationship data in the exported results.</p>

    <h3 class="mt-3">Basic Usage</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Load appointments with doctor relationship
$appointments = $appointmentModel->getAll();

// Include 'doctor' relationship in exported data
$appointments->with('doctor');

// Now getFormattedData() includes doctor data
$data = $appointments->getFormattedData();

foreach ($data as $appointment) {
    echo "Patient: " . $appointment->patient_name . "\n";

    // Doctor data is included automatically
    if (isset($appointment->doctor)) {
        echo "Doctor: " . $appointment->doctor->name . "\n";
        echo "Specialty: " . $appointment->doctor->specialty . "\n";
    }
}</code></pre>

    <h3 class="mt-3">Multiple Relationships</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Include multiple relationships using array
$posts = $postModel->getAll()
    ->with(['author', 'comments', 'tags']);

$data = $posts->getFormattedData();

foreach ($data as $post) {
    echo $post->title . "\n";
    echo "By: " . $post->author->username . "\n";
    echo "Comments: " . count($post->comments) . "\n";
}</code></pre>

    <h3 class="mt-3">All Relationships</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Include ALL defined relationships with null parameter
$authors = $authorModel->getAll()
    ->with(null);

$data = $authors->getRawData();

// All relationships (books, biography, etc.) are included</code></pre>

    <h3 class="mt-3">Propagation Through Queries</h3>

    <p>When you call <code>with()</code> on a model before executing queries, the relationships are automatically propagated to the results:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Set with() before query
$appointmentModel = new AppointmentTestModel();
$appointmentModel->with('doctor');

// with() is automatically propagated to results
$appointment = $appointmentModel->getById(1);
$data = $appointment->getFormattedData('object', false);

// Doctor relationship is already included
echo $data->patient_name . " -> " . $data->doctor->name;

// Works with all query methods
$results = $appointmentModel->where('patient_name LIKE ?', ['%John%'])->getResults();
$results2 = $appointmentModel->getAll();
$results3 = $appointmentModel->limit(0, 10)->getResults();

// All results have doctor relationship included</code></pre>

    <h3 class="mt-3">Export Formats</h3>

    <p>The <code>with()</code> method works with all data export formats:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$appointments = $appointmentModel->getAll()->with('doctor');

// Format 1: Formatted data (with field formatting applied)
$formatted = $appointments->getFormattedData();
// Doctor appears as: $formatted[0]->doctor->name

// Format 2: Raw data (original database values)
$raw = $appointments->getRawData();
// Doctor appears as: $raw[0]->doctor->name

// Format 3: SQL data (ready for database insert/update)
$sql = $appointments->getSqlData();
// Doctor appears as: $sql[0]->doctor->name

// Format 4: Single record as array
$appointment = $appointmentModel->getById(1)->with('doctor');
$array = $appointment->toArray();
// Doctor appears as: $array['doctor']->name</code></pre>

    <h3 class="mt-3">Without with() - Default Behavior</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Without with(), relationships are NOT included in exports
$appointments = $appointmentModel->getAll();
$data = $appointments->getFormattedData();

// Doctor is NOT in the exported data
echo isset($data[0]->doctor); // false

// But you can still access it via magic property (lazy load)
foreach ($appointments as $appointment) {
    $doctor = $appointment->doctor; // This works - triggers lazy loading
}</code></pre>

    <div class="alert alert-info">
        <strong>Key Difference:</strong>
        <ul class="mb-0">
            <li><strong>Without with():</strong> Relationships are accessed via magic properties during iteration, triggering lazy/batch loading</li>
            <li><strong>With with():</strong> Relationships are preloaded and included in exported data (getFormattedData, etc.)</li>
        </ul>
    </div>

    <h3 class="mt-3">Practical Examples</h3>

    <h4>API Response with Nested Data</h4>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// API endpoint returning appointments with doctor info
$appointments = $appointmentModel
    ->where('appointment_date >= ?', [date('Y-m-d')])
    ->order('appointment_date', 'asc')
    ->limit(0, 20)
    ->getResults()
    ->with('doctor');

// Return as JSON with relationships included
Response::json([
    'success' => true,
    'data' => $appointments->getFormattedData()
]);

// Output:
// {
//   "success": true,
//   "data": [
//     {
//       "id": 1,
//       "patient_name": "John Doe",
//       "appointment_date": "2025-10-20 10:00:00",
//       "doctor": {
//         "id": 1,
//         "name": "Dr. Smith",
//         "specialty": "Cardiology"
//       }
//     }
//   ]
// }</code></pre>

    <h4>Table Display with Related Data</h4>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Display posts with author information
$posts = $postModel->getAll()->with('author');

foreach ($posts->getFormattedData() as $post) {
    echo "&lt;tr&gt;";
    echo "&lt;td&gt;" . $post->title . "&lt;/td&gt;";
    echo "&lt;td&gt;" . $post->author->username . "&lt;/td&gt;";
    echo "&lt;td&gt;" . $post->author->email . "&lt;/td&gt;";
    echo "&lt;td&gt;" . $post->created_at . "&lt;/td&gt;";
    echo "&lt;/tr&gt;";
}</code></pre>

    <h2 class="mt-4">Performance Optimization</h2>

    <h3>Batch Loading (N+1 Prevention)</h3>

    <p>The relationship system automatically prevents the N+1 query problem through batch loading:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// WITHOUT batch loading (N+1 problem):
// 1 query for authors + N queries for biographies = N+1 queries
$authors = $authorsModel->getAll(); // 1 query

foreach ($authors as $author) {
    $bio = $author->biography; // N queries (one per author) ❌
}

// WITH batch loading (automatic):
// 1 query for authors + 1 query for all biographies = 2 queries
$authors = $authorsModel->getAll(); // 1 query

foreach ($authors as $author) {
    // First access triggers ONE query to load ALL biographies
    $bio = $author->biography; // 1 query total ✓
}

// The second, third, etc. accesses use cached data
// No additional queries!</code></pre>

    <div class="alert alert-success">
        <strong>Automatic Optimization:</strong> The first time you access a relationship in a result set, the system loads ALL related records in a single batch query. Subsequent accesses use the cached data.
    </div>

    <h3>Clearing Relationship Cache</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Clear all relationship caches
$model->clearRelationshipCache();

// Clear specific relationship cache
$model->clearRelationshipCache('books');

// Useful after modifying related data
$author->books; // Loads books
// ... modify books ...
$author->clearRelationshipCache('books');
$author->books; // Reloads books from database</code></pre>

    <h2 class="mt-4">Common Patterns</h2>

    <h3>Author-Books-Reviews (Nested Relationships)</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class ReviewModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__reviews')
            ->id()
            ->int('book_id')->index()
            ->string('reviewer_name', 100)
            ->int('rating')
            ->text('comment');
    }
}

class BookModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__books')
            ->id()
                ->hasMany('reviews', ReviewModel::class, 'book_id')
            ->int('author_id')->index()
            ->string('title', 200)->required();
    }
}

class AuthorModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__authors')
            ->id()
                ->hasMany('books', BookModel::class, 'author_id')
            ->string('name', 100)->required();
    }
}

// Usage
$authors = $authorsModel->getAll();

foreach ($authors as $author) {
    echo $author->name . "\n";

    $books = $author->books;
    if ($books !== null) {
        foreach ($books as $book) {
            echo "  - " . $book->title . "\n";

            $reviews = $book->reviews;
            if ($reviews !== null) {
                foreach ($reviews as $review) {
                    echo "    * " . $review->rating . "/5 - " . $review->reviewer_name . "\n";
                }
            }
        }
    }
}</code></pre>

    <h3>Circular References (Careful!)</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// User hasMany Posts, Post belongsTo User

class UserModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__users')
            ->id()
                ->hasMany('posts', PostModel::class, 'user_id')
            ->string('username', 100);
    }
}

class PostModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__posts')
            ->id()
            ->string('title', 200)
            ->int('user_id')
                ->belongsTo('author', UserModel::class, 'id'); // Reference back to User
    }
}

// Usage - works both directions
$user = $usersModel->getById(1);
$posts = $user->posts; // Get user's posts

$post = $postsModel->getById(1);
$author = $post->author; // Get post's author</code></pre>

    <h2 class="mt-4">Troubleshooting</h2>

    <h3>Common Issues</h3>

    <div class="alert alert-warning">
        <strong>Issue:</strong> Relationship returns null when data exists<br>
        <strong>Solution:</strong> Check that:
        <ul class="mb-0">
            <li>Foreign key field exists in the correct table</li>
            <li>Foreign key values match primary key values</li>
            <li>Relationship alias is correct</li>
            <li>Related model class is correct</li>
        </ul>
    </div>

    <div class="alert alert-warning">
        <strong>Issue:</strong> Type compatibility error<br>
        <strong>Solution:</strong> Ensure foreign key and primary key have compatible types:
        <ul class="mb-0">
            <li><code>id</code> is compatible with <code>int</code>, <code>string</code></li>
            <li><code>int</code> is compatible with <code>id</code>, <code>string</code></li>
            <li><code>string</code> is compatible with <code>int</code>, <code>id</code></li>
        </ul>
    </div>

    <h3>Debugging Relationships</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Check relationship configuration
$rules = $model->getRules();
if (isset($rules['id']['relationship'])) {
    $rel = $rules['id']['relationship'];
    echo "Type: " . $rel['type'] . "\n";
    echo "Alias: " . $rel['alias'] . "\n";
    echo "Local key: " . $rel['local_key'] . "\n";
    echo "Foreign key: " . $rel['foreign_key'] . "\n";
    echo "Related model: " . $rel['related_model'] . "\n";
}

// Check if relationship exists
if ($model->hasRelationship('books')) {
    echo "Relationship 'books' exists\n";
}</code></pre>

    <h2 class="mt-4">Best Practices</h2>

    <div class="alert alert-success">
        <strong>Recommendations:</strong>
        <ul class="mb-0">
            <li>Use <code>hasOne/hasMany</code> when foreign key is in the related table</li>
            <li>Use <code>belongsTo</code> when foreign key is in the current table</li>
            <li>Add indexes to foreign key fields for better performance</li>
            <li>Use <code>with()</code> to include relationships in API responses and data exports</li>
            <li>Use <code>whereHas()</code> instead of JOIN for filtering by related data</li>
            <li>Choose appropriate <code>onDelete</code> behavior for data integrity</li>
            <li>Clear relationship cache after modifying related data</li>
            <li>Test cascade operations thoroughly before production</li>
            <li>Call <code>with()</code> before queries to automatically propagate to results</li>
        </ul>
    </div>

    <h2 class="mt-4">See Also</h2>

    <div class="alert alert-info">
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-overview">Model Overview</a> - General concepts</li>
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-queries">Query Builder</a> - Using whereHas() and other query methods</li>
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-crud">CRUD Operations</a> - Cascade save operations</li>
            <li><a href="?page=docs&action=Framework/Core/rulebuilder">RuleBuilder</a> - Defining relationships in configure()</li>
        </ul>
    </div>
</div>
