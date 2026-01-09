<?php
namespace Modules\Docs\Pages;
/**
 * @title RuleBuilder - Schema Definition
 * @guide framework
 * @order 45
 * @tags RuleBuilder, schema, database, fields, validation, relationships, fluent-interface, configure, model-definition
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>RuleBuilder - Schema Definition</h1>
    <p class="text-muted">Revision: 2025/10/13</p>
    <p class="lead">The <code>RuleBuilder</code> class provides a fluent interface for defining your database schema, validation rules, and relationships in the Model's <code>configure()</code> method. It handles table structure, field types, constraints, form generation, and relationships all in one place.</p>

    <div class="alert alert-info">
        <strong>💡 What RuleBuilder Does:</strong>
        <ul class="mb-0">
            <li><strong>Database Schema:</strong> Defines table structure and field types</li>
            <li><strong>Validation Rules:</strong> Sets required fields, constraints, and validation</li>
            <li><strong>Form Generation:</strong> Automatically generates form fields from schema</li>
            <li><strong>Relationships:</strong> Defines hasOne, belongsTo, hasMany relationships</li>
            <li><strong>Display Control:</strong> Controls visibility in lists, forms, and views</li>
        </ul>
    </div>

    <h2 class="mt-4">Basic Usage</h2>

    <p>RuleBuilder is used inside your Model's <code>configure()</code> method:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class ProductsModel extends AbstractModel
{
    protected function configure($rule): void {
        // $rule is a RuleBuilder instance
        $rule->table('#__products')
            ->id()                                    // Auto-increment primary key
            ->string('name', 100)->required()         // VARCHAR(100) NOT NULL
            ->decimal('price', 10, 2)->default(0)     // DECIMAL(10,2) DEFAULT 0
            ->text('description')->nullable()         // TEXT NULL
            ->boolean('in_stock')->default(true)      // TINYINT(1) DEFAULT 1
            ->datetime('created_at')->nullable();     // DATETIME NULL
    }
}</code></pre>

    <h2 class="mt-4">Configuration Methods</h2>

    <h3 class="mt-3"><code>table(string $name): self</code></h3>
    <p>Sets the database table name. Use <code>#__</code> prefix which will be replaced with the configured database prefix.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->table('#__products');
// Creates table: prefix_products

$rule->table('#__orders');
// Creates table: prefix_orders</code></pre>

    <h3 class="mt-3"><code>db(string $type): self</code></h3>
    <p>Sets which database connection to use (default: 'db', or 'db2' for second database).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->table('#__logs')
    ->db('db2');  // Use secondary database</code></pre>

    <h3 class="mt-3"><code>renameField(string $from, string $to): self</code></h3>
    <p>Declares a rename operation during schema updates (use with <code>buildTable()</code>).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->table('#__users')
    ->string('name', 150)
    ->renameField('full_name', 'name');</code></pre>

    <h2 class="mt-4">Field Type Methods</h2>

    <h3 class="mt-3">Primary Key</h3>

    <h4><code>id(string $name = 'id'): self</code></h4>
    <p>Creates an auto-increment primary key field.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Standard primary key
$rule->id();  // Creates 'id' INT AUTO_INCREMENT PRIMARY KEY

// Custom name
$rule->id('product_id');  // Creates 'product_id' as primary key</code></pre>

    <h3 class="mt-3">String Fields</h3>

    <h4><code>string(string $name, int $length = 255): self</code></h4>
    <p>Creates a VARCHAR field.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Basic string
$rule->string('name', 100);  // VARCHAR(100)

// With constraints
$rule->string('username', 50)
    ->required()
    ->unique();

// With default value
$rule->string('status', 20)
    ->default('active');</code></pre>

    <h4><code>text(string $name): self</code></h4>
    <p>Creates a TEXT field for long content.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->text('description');
$rule->text('content')->nullable();</code></pre>

    <h4><code>email(string $name): self</code></h4>
    <p>Creates an email field with automatic email validation.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->email('user_email')
    ->required();</code></pre>

    <h4><code>tel(string $name): self</code></h4>
    <p>Creates a telephone field (VARCHAR 25).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->tel('phone')
    ->nullable();</code></pre>

    <h4><code>url(string $name): self</code></h4>
    <p>Creates a URL field with validation.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->url('website')
    ->nullable();</code></pre>

    <h3 class="mt-3">Numeric Fields</h3>

    <h4><code>int(string $name): self</code></h4>
    <p>Creates an INTEGER field.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Basic integer
$rule->int('quantity');

// With constraints
$rule->int('age')
    ->min(0)
    ->max(150);

// Unsigned integer
$rule->int('views')
    ->unsigned()
    ->default(0);

// Foreign key with index
$rule->int('category_id')
    ->index();</code></pre>

    <h4><code>decimal(string $name, int $length = 10, int $precision = 2): self</code></h4>
    <p>Creates a DECIMAL field for precise numeric values (e.g., prices, measurements).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Price field: 10 total digits, 2 decimal places
$rule->decimal('price', 10, 2)
    ->default(0)
    ->min(0);

// Weight: 8 total digits, 3 decimal places
$rule->decimal('weight', 8, 3)
    ->nullable();

// Percentage: 5 total digits, 2 decimal places
$rule->decimal('discount_rate', 5, 2)
    ->min(0)
    ->max(100);</code></pre>

    <h3 class="mt-3">Boolean Fields</h3>

    <h4><code>boolean(string $name): self</code> / <code>checkbox(string $name): self</code></h4>
    <p>Creates a boolean/checkbox field (TINYINT 1).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->boolean('is_active')
    ->default(true);

$rule->checkbox('accept_terms')
    ->required();</code></pre>

    <h3 class="mt-3">Date & Time Fields</h3>

    <h4><code>datetime(string $name): self</code></h4>
    <p>Creates a DATETIME field (format: Y-m-d H:i:s).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->datetime('created_at')
    ->nullable();

$rule->datetime('updated_at')
    ->default('CURRENT_TIMESTAMP');</code></pre>

    <h4><code>date(string $name): self</code></h4>
    <p>Creates a DATE field (format: Y-m-d).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->date('birth_date')
    ->nullable();

$rule->date('published_at');</code></pre>

    <h4><code>time(string $name): self</code></h4>
    <p>Creates a TIME field (format: H:i:s).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->time('opening_time')
    ->nullable();</code></pre>

    <h3 class="mt-3">Selection Fields</h3>

    <h4><code>list(string $name, array $options): self</code> / <code>select(string $name, array $options): self</code></h4>
    <p>Creates a dropdown/select field.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Basic dropdown
$rule->list('status', [
    'draft' => 'Draft',
    'published' => 'Published',
    'archived' => 'Archived'
])->default('draft');

// From Model
$rule->list('category_id', (new CategoriesModel())->getList())
    ->required();</code></pre>

    <h4><code>enum(string $name, array $options): self</code></h4>
    <p>Creates an ENUM field in the database.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->enum('priority', [
    'low' => 'Low Priority',
    'medium' => 'Medium Priority',
    'high' => 'High Priority'
])->default('medium');</code></pre>

    <h4><code>radio(string $name, array $options): self</code></h4>
    <p>Creates radio buttons.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->radio('gender', [
    'M' => 'Male',
    'F' => 'Female',
    'O' => 'Other'
]);</code></pre>

    <h4><code>checkboxes(string $name, array $options): self</code></h4>
    <p>Creates multiple checkboxes (stores as array).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->checkboxes('features', [
    'wifi' => 'WiFi',
    'parking' => 'Parking',
    'pool' => 'Swimming Pool'
]);</code></pre>

    <h3 class="mt-3">File Upload Fields</h3>

    <h4><code>file(string $name): self</code></h4>
    <p>Creates a file upload field.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Single file
$rule->file('document')
    ->accept('.pdf,.doc,.docx')
    ->maxSize(5 * 1024 * 1024)  // 5MB
    ->uploadDir('uploads/documents');

// Multiple files
$rule->file('attachments')
    ->multiple()
    ->maxFiles(5)
    ->accept('.pdf,.jpg,.png');</code></pre>

    <h4><code>image(string $name): self</code></h4>
    <p>Creates an image upload field (automatically accepts image/* types).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Single image
$rule->image('profile_picture')
    ->maxSize(2 * 1024 * 1024)  // 2MB
    ->uploadDir('uploads/profiles');

// Multiple images
$rule->image('gallery')
    ->multiple()
    ->maxFiles(10)
    ->uploadDir('uploads/gallery');</code></pre>

    <h2 class="mt-4">Constraint Methods</h2>

    <h3 class="mt-3"><code>required(): self</code></h3>
    <p>Makes the field required (NOT NULL in database + form validation).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->string('name', 100)
    ->required();</code></pre>

    <h3 class="mt-3"><code>nullable(bool $nullable = true): self</code></h3>
    <p>Allows NULL values (opposite of required).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->string('middle_name', 50)
    ->nullable();</code></pre>

    <h3 class="mt-3"><code>default($value): self</code></h3>
    <p>Sets default value for the field.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->boolean('is_active')
    ->default(true);

$rule->string('status', 20)
    ->default('pending');

$rule->int('views')
    ->default(0);</code></pre>

    <h3 class="mt-3"><code>unique(): self</code></h3>
    <p>Creates a UNIQUE constraint in the database.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->string('username', 50)
    ->required()
    ->unique();

$rule->email('user_email')
    ->unique();</code></pre>

    <h3 class="mt-3"><code>index(): self</code></h3>
    <p>Creates a database index for faster queries.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Foreign keys should have indexes
$rule->int('category_id')
    ->index();

// Frequently searched fields
$rule->string('sku', 50)
    ->index();</code></pre>

    <h3 class="mt-3"><code>unsigned(): self</code></h3>
    <p>Makes numeric fields unsigned (only positive values).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->int('quantity')
    ->unsigned()
    ->default(0);</code></pre>

    <h3 class="mt-3">Numeric Constraints</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// min($value): Minimum value
$rule->int('age')
    ->min(18);

// max($value): Maximum value
$rule->int('quantity')
    ->max(9999);

// step($value): Step increment (for HTML5 input)
$rule->decimal('price', 10, 2)
    ->step(0.01)
    ->min(0);</code></pre>

    <h2 class="mt-4">Display Control Methods</h2>

    <h3 class="mt-3"><code>hideFromList(): self</code></h3>
    <p>Hides field from table/list views.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->text('long_description')
    ->hideFromList();  // Don't show in lists</code></pre>

    <h3 class="mt-3"><code>hideFromEdit(): self</code></h3>
    <p>Hides field from edit forms.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->datetime('created_at')
    ->hideFromEdit();  // Auto-set, don't allow editing</code></pre>

    <h3 class="mt-3"><code>hideFromView(): self</code></h3>
    <p>Hides field from detail/view pages.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->string('password_hash', 255)
    ->hideFromView();  // Never display passwords</code></pre>

    <h3 class="mt-3"><code>excludeFromDatabase(): self</code></h3>
    <p>Field won't be created in database (virtual/computed fields).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->string('full_name', 255)
    ->excludeFromDatabase()  // Computed from first_name + last_name
    ->getter(function($obj) {
        return $obj->first_name . ' ' . $obj->last_name;
    });</code></pre>

    <h2 class="mt-4">Form Customization</h2>

    <h3 class="mt-3"><code>label(string $label): self</code></h3>
    <p>Sets the display label for the field.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->string('user_email', 100)
    ->label('Email Address')
    ->required();</code></pre>

    <h3 class="mt-3"><code>formType(string $type): self</code></h3>
    <p>Sets the HTML5 input type.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->string('website', 255)
    ->formType('url');

$rule->string('phone', 25)
    ->formType('tel');

$rule->text('content')
    ->formType('textarea');</code></pre>

    <h3 class="mt-3"><code>formLabel(string $label): self</code></h3>
    <p>Sets a different label specifically for forms.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->string('pwd', 255)
    ->label('Password')           // Display label
    ->formLabel('Enter Password');  // Form-specific label</code></pre>

    <h3 class="mt-3"><code>formParams(array $params): self</code></h3>
    <p>Sets custom form parameters (HTML attributes).</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->string('username', 50)
    ->formParams([
        'placeholder' => 'Enter username',
        'autocomplete' => 'username',
        'pattern' => '[a-zA-Z0-9]+',
        'minlength' => 3,
        'maxlength' => 50
    ]);</code></pre>

    <h3 class="mt-3"><code>error(string $message): self</code></h3>
    <p>Sets custom validation error message.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->string('username', 50)
    ->required()
    ->error('Username is required and must be 3-50 characters');</code></pre>

    <h2 class="mt-4">Relationship Methods</h2>

    <h3 class="mt-3"><code>hasOne(string $alias, string $relatedModel, string $foreignKey, string $onDelete = 'CASCADE'): self</code></h3>
    <p>Defines a one-to-one relationship where the foreign key is in the RELATED table.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Actor hasOne Biography (biography.actor_id references actor.id)
class ActorsModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__actors')
            ->id()->hasOne('biography', BiographyModel::class, 'actor_id', 'CASCADE')
            ->string('name', 100)->required();
    }
}

// Access the relationship
$actor = $actorsModel->getById(1);
$bio = $actor->biography;  // Lazy loads BiographyModel
echo $bio->bio_text;</code></pre>

    <p><strong>onDelete options:</strong></p>
    <ul>
        <li><code>CASCADE</code> - Delete child record when parent is deleted</li>
        <li><code>SET NULL</code> - Set foreign key to NULL in child</li>
        <li><code>RESTRICT</code> - Prevent parent deletion if child exists</li>
    </ul>

    <h3 class="mt-3"><code>hasMany(string $alias, string $relatedModel, string $foreignKey, string $onDelete = 'CASCADE'): self</code></h3>
    <p>Defines a one-to-many relationship where the foreign key is in the RELATED table.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Author hasMany Books (books.author_id references authors.id)
class AuthorsModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__authors')
            ->id()->hasMany('books', BooksModel::class, 'author_id', 'CASCADE')
            ->string('name', 100)->required();
    }
}

// Access the relationship
$author = $authorsModel->getById(1);
$books = $author->books;  // Returns array of BooksModel instances
foreach ($books as $book) {
    echo $book->title . "\n";
}</code></pre>

    <h3 class="mt-3"><code>belongsTo(string $alias, string $relatedModel, string $relatedKey = 'id'): self</code></h3>
    <p>Defines a many-to-one relationship where the foreign key is in THIS table.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Post belongsTo User (posts.user_id references users.id)
class PostsModel extends AbstractModel {
    protected function configure($rule): void {
        $rule->table('#__posts')
            ->id()
            ->string('title', 200)->required()
            ->int('user_id')->belongsTo('author', UsersModel::class, 'id')
            ->text('content');
    }
}

// Access the relationship
$post = $postsModel->getById(1);
$author = $post->author;  // Lazy loads UsersModel
echo $author->name;</code></pre>

    <h2 class="mt-4">Advanced Customization</h2>

    <h3 class="mt-3">Custom Getters and Setters</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// getter(callable $fn): Custom getter function
$rule->string('price_formatted', 50)
    ->excludeFromDatabase()
    ->getter(function($obj) {
        return '€' . number_format($obj->price, 2);
    });

// setter(callable $fn): Custom setter function
$rule->string('email', 255)
    ->setter(function($value) {
        return strtolower(trim($value));
    });

// rawGetter(callable $fn): Raw value getter
$rule->datetime('created_at')
    ->rawGetter(function($value) {
        return $value->format('Y-m-d H:i:s');
    });</code></pre>

    <h3 class="mt-3"><code>property(string $key, $value): self</code></h3>
    <p>Sets a custom property on the field configuration.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->string('code', 50)
    ->property('auto-generate', true)
    ->property('prefix', 'PRD-');</code></pre>

    <h3 class="mt-3"><code>customize(callable $callback): self</code></h3>
    <p>Applies a custom callback to modify field configuration.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$rule->string('status', 20)
    ->customize(function($fieldConfig) {
        $fieldConfig['custom_validator'] = 'validateStatus';
        $fieldConfig['allow_transitions'] = ['draft' => 'published'];
        return $fieldConfig;
    });</code></pre>

    <h2 class="mt-4">Complete Example</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class ProductsModel extends AbstractModel
{
    protected function configure($rule): void {
        $rule->table('#__products')

            // Primary Key
            ->id()

            // Basic Fields
            ->string('sku', 50)
                ->required()
                ->unique()
                ->index()
                ->label('Product SKU')

            ->string('name', 200)
                ->required()
                ->label('Product Name')

            ->text('description')
                ->nullable()
                ->hideFromList()

            // Numeric Fields
            ->decimal('price', 10, 2)
                ->required()
                ->min(0)
                ->step(0.01)
                ->default(0)

            ->int('stock_quantity')
                ->unsigned()
                ->default(0)
                ->min(0)

            ->decimal('weight', 8, 3)
                ->nullable()
                ->label('Weight (kg)')

            // Selection Fields
            ->list('status', [
                'draft' => 'Draft',
                'active' => 'Active',
                'discontinued' => 'Discontinued'
            ])->default('draft')

            ->list('category_id', (new CategoriesModel())->getList())
                ->required()
                ->index()

            // Relationships
            ->int('brand_id')
                ->index()
                ->belongsTo('brand', BrandsModel::class, 'id')

            ->id()
                ->hasMany('images', ProductImagesModel::class, 'product_id', 'CASCADE')
                ->hasMany('reviews', ProductReviewsModel::class, 'product_id', 'CASCADE')

            // Boolean Fields
            ->boolean('featured')
                ->default(false)

            ->boolean('is_active')
                ->default(true)

            // File Upload
            ->image('main_image')
                ->nullable()
                ->maxSize(5 * 1024 * 1024)
                ->uploadDir('uploads/products')
                ->accept('image/*')

            // Date Fields
            ->datetime('created_at')
                ->nullable()
                ->hideFromEdit()

            ->datetime('updated_at')
                ->nullable()
                ->hideFromEdit()

            // Virtual Field (computed)
            ->string('price_formatted', 50)
                ->excludeFromDatabase()
                ->hideFromEdit()
                ->getter(function($obj) {
                    return '€' . number_format($obj->price, 2);
                });
    }
}</code></pre>

    <h2 class="mt-4">Field Types Reference</h2>

    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Database Type</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>id($name)</code></td><td>INT AUTO_INCREMENT</td><td>Auto-increment primary key</td></tr>
                <tr><td><code>string($name, $len)</code></td><td>VARCHAR($len)</td><td>Variable length string</td></tr>
                <tr><td><code>text($name)</code></td><td>TEXT</td><td>Long text content</td></tr>
                <tr><td><code>email($name)</code></td><td>VARCHAR(255)</td><td>Email with validation</td></tr>
                <tr><td><code>tel($name)</code></td><td>VARCHAR(25)</td><td>Telephone number</td></tr>
                <tr><td><code>url($name)</code></td><td>VARCHAR(255)</td><td>URL with validation</td></tr>
                <tr><td><code>int($name)</code></td><td>INT</td><td>Integer number</td></tr>
                <tr><td><code>decimal($n, $l, $p)</code></td><td>DECIMAL($l,$p)</td><td>Precise decimal number</td></tr>
                <tr><td><code>boolean($name)</code></td><td>TINYINT(1)</td><td>True/False value</td></tr>
                <tr><td><code>datetime($name)</code></td><td>DATETIME</td><td>Date and time</td></tr>
                <tr><td><code>date($name)</code></td><td>DATE</td><td>Date only</td></tr>
                <tr><td><code>time($name)</code></td><td>TIME</td><td>Time only</td></tr>
                <tr><td><code>list($name, $opts)</code></td><td>varies</td><td>Dropdown select</td></tr>
                <tr><td><code>enum($name, $opts)</code></td><td>ENUM</td><td>Database enum</td></tr>
                <tr><td><code>radio($name, $opts)</code></td><td>varies</td><td>Radio buttons</td></tr>
                <tr><td><code>checkboxes($n, $o)</code></td><td>TEXT/JSON</td><td>Multiple selection</td></tr>
                <tr><td><code>file($name)</code></td><td>VARCHAR(255)</td><td>File upload</td></tr>
                <tr><td><code>image($name)</code></td><td>VARCHAR(255)</td><td>Image upload</td></tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">Tests</h2>

    <div class="alert alert-info">
        RuleBuilder has a dedicated unit test suite:
        <code>php vendor/bin/phpunit tests/Unit/Builders/RuleBuilderMethodsTest.php</code>
    </div>

    <h2 class="mt-4">See Also</h2>

    <div class="alert alert-success">
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-overview">Model Overview</a> - General Model concepts</li>
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-relationships">Relationships</a> - Detailed relationship documentation</li>
            <li><a href="?page=docs&action=Framework/Core/schema">Schema Management</a> - Advanced schema operations</li>
        </ul>
    </div>
</div>
