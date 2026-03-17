<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title RuleBuilder - Schema Configuration
 * @guide Models
 * @order 51
 * @tags RuleBuilder, schema, model, database, fields, validation, configuration, fluent-interface
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>RuleBuilder - Schema Configuration</h1>
    <p class="text-muted">Revision: 2026/02/22</p>
    <p class="lead">The <code>RuleBuilder</code> class provides a fluent interface for defining model schemas in the <code>configure()</code> method. It allows you to configure table structure, field types, validation rules, form behaviors, and relationships.</p>

    <div class="alert alert-info">
        <strong>💡 Key Concept:</strong> RuleBuilder is used inside the <code>configure($rule)</code> method of your Model to define all field properties, database schema, and UI behaviors in one place.
    </div>

    <h2 class="mt-4">Basic Usage</h2>

    <pre class="language-php"><code>protected function configure($rule): void {
    $rule->table('#__products')              // Define table name
        ->id()                                // Auto-increment primary key
        ->string('name', 100)->required()     // VARCHAR(100) NOT NULL
        ->decimal('price', 10, 2)->default(0) // DECIMAL(10,2) DEFAULT 0
        ->text('description')->nullable()     // TEXT NULL
        ->boolean('in_stock')->default(true)  // TINYINT(1) DEFAULT 1
        ->created_at()                        // Set only on first insert
        ->updated_at()                        // Updated at every save
        ->created_by()                        // Active user on first insert
        ->updated_by();                       // Active user at every save
}</code></pre>

    <h2 class="mt-4">Configuration Methods</h2>

    <h3>Core Configuration</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>table(string $name)</code></td>
                    <td>Define the database table name (use <code>#__</code> prefix)</td>
                    <td><code>$rule->table('#__products')</code></td>
                </tr>
                <tr>
                    <td><code>db(string $name)</code></td>
                    <td>Set database connection type ('db' or 'db2')</td>
                    <td><code>$rule->db('db2')</code></td>
                </tr>
                <tr>
                    <td><code>renameField(string $from, string $to)</code></td>
                    <td>Rename a column during schema updates</td>
                    <td><code>$rule->renameField('full_name', 'name')</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">Field Type Methods</h2>

    <h3>Basic Types</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>SQL Type</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>id(string $name = 'id')</code></td>
                    <td>INT AUTO_INCREMENT</td>
                    <td>Primary key (auto-increment, hidden in forms)</td>
                    <td><code>$rule->id()</code></td>
                </tr>
                <tr>
                    <td><code>string(string $name, int $length = 255)</code></td>
                    <td>VARCHAR(length)</td>
                    <td>String field with max length</td>
                    <td><code>$rule->string('name', 100)</code></td>
                </tr>
                <tr>
                    <td><code>title(string $name = 'title', int $length = 255)</code></td>
                    <td>VARCHAR(length)</td>
                    <td>Title field (auto-used in belongsTo relationships)</td>
                    <td><code>$rule->title()</code></td>
                </tr>
                <tr>
                    <td><code>text(string $name)</code></td>
                    <td>TEXT</td>
                    <td>Long text field</td>
                    <td><code>$rule->text('description')</code></td>
                </tr>
                <tr>
                    <td><code>int(string $name)</code></td>
                    <td>INT</td>
                    <td>Integer number</td>
                    <td><code>$rule->int('quantity')</code></td>
                </tr>
                <tr>
                    <td><code>decimal(string $name, int $length = 10, int $precision = 2)</code></td>
                    <td>DECIMAL(length, precision)</td>
                    <td>Decimal number with precision</td>
                    <td><code>$rule->decimal('price', 10, 2)</code></td>
                </tr>
                <tr>
                    <td><code>boolean(string $name)</code></td>
                    <td>TINYINT(1)</td>
                    <td>Boolean/checkbox field</td>
                    <td><code>$rule->boolean('is_active')</code></td>
                </tr>
                <tr>
                    <td><code>array(string $name)</code></td>
                    <td>TEXT (JSON)</td>
                    <td>Array stored as JSON</td>
                    <td><code>$rule->array('metadata')</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Date/Time Types</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>SQL Type</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>datetime(string $name)</code></td>
                    <td>DATETIME</td>
                    <td>Date and time</td>
                    <td><code>$rule->datetime('published_at')</code></td>
                </tr>
                <tr>
                    <td><code>date(string $name)</code></td>
                    <td>DATE</td>
                    <td>Date only</td>
                    <td><code>$rule->date('birth_date')</code></td>
                </tr>
                <tr>
                    <td><code>time(string $name)</code></td>
                    <td>TIME</td>
                    <td>Time only</td>
                    <td><code>$rule->time('open_time')</code></td>
                </tr>
                <tr>
                    <td><code>timestamp(string $name)</code></td>
                    <td>DATETIME</td>
                    <td>Timestamp (alias for datetime)</td>
                    <td><code>$rule->timestamp('created')</code></td>
                </tr>
                <tr>
                    <td><code>created_at(string $name = 'created_at')</code></td>
                    <td>DATETIME</td>
                    <td>Auto-preserved creation timestamp (hidden from edit)</td>
                    <td><code>$rule->created_at()</code></td>
                </tr>
                <tr>
                    <td><code>updated_at(string $name = 'updated_at')</code></td>
                    <td>DATETIME</td>
                    <td>Auto-updated timestamp on every save (hidden from edit)</td>
                    <td><code>$rule->updated_at()</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Audit Types</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>SQL Type</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>created_by(string $name = 'created_by')</code></td>
                    <td>INT</td>
                    <td>Auto-preserved creator user id on first insert (hidden from edit)</td>
                    <td><code>$rule->created_by()</code></td>
                </tr>
                <tr>
                    <td><code>updated_by(string $name = 'updated_by')</code></td>
                    <td>INT</td>
                    <td>Auto-updated editor user id on every save (hidden from edit)</td>
                    <td><code>$rule->updated_by()</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Special Input Types</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>email(string $name)</code></td>
                    <td>Email field with validation</td>
                    <td><code>$rule->email('email')</code></td>
                </tr>
                <tr>
                    <td><code>tel(string $name)</code></td>
                    <td>Telephone field</td>
                    <td><code>$rule->tel('phone')</code></td>
                </tr>
                <tr>
                    <td><code>url(string $name)</code></td>
                    <td>URL field with validation</td>
                    <td><code>$rule->url('website')</code></td>
                </tr>
                <tr>
                    <td><code>file(string $name)</code></td>
                    <td>File upload field</td>
                    <td><code>$rule->file('attachment')</code></td>
                </tr>
                <tr>
                    <td><code>image(string $name)</code></td>
                    <td>Image upload field (accepts images only)</td>
                    <td><code>$rule->image('photo')</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Selection Types</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>list(string $name, array $options)</code></td>
                    <td>Dropdown/select field</td>
                    <td><code>$rule->list('status', ['active' => 'Active', 'inactive' => 'Inactive'])</code></td>
                </tr>
                <tr>
                    <td><code>select(string $name, array $options)</code></td>
                    <td>Alias for list()</td>
                    <td><code>$rule->select('category', $categories)</code></td>
                </tr>
                <tr>
                    <td><code>enum(string $name, array $options)</code></td>
                    <td>Enum field (database-level constraint)</td>
                    <td><code>$rule->enum('size', ['S', 'M', 'L', 'XL'])</code></td>
                </tr>
                <tr>
                    <td><code>radio(string $name, array $options)</code></td>
                    <td>Radio buttons</td>
                    <td><code>$rule->radio('gender', ['M' => 'Male', 'F' => 'Female'])</code></td>
                </tr>
                <tr>
                    <td><code>checkbox(string $name)</code></td>
                    <td>Single checkbox (alias for boolean)</td>
                    <td><code>$rule->checkbox('agree_terms')</code></td>
                </tr>
                <tr>
                    <td><code>checkboxes(string $name, array $options)</code></td>
                    <td>Multiple checkboxes (stored as array)</td>
                    <td><code>$rule->checkboxes('features', ['wifi' => 'WiFi', 'parking' => 'Parking'])</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">Field Configuration Methods</h2>

    <h3>Validation & Constraints</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>required()</code></td>
                    <td>Make field required</td>
                    <td><code>$rule->string('name', 100)->required()</code></td>
                </tr>
                <tr>
                    <td><code>nullable(bool $nullable = true)</code></td>
                    <td>Allow NULL values</td>
                    <td><code>$rule->text('bio')->nullable()</code></td>
                </tr>
                <tr>
                    <td><code>default($value)</code></td>
                    <td>Set default value</td>
                    <td><code>$rule->int('views')->default(0)</code></td>
                </tr>
                <tr>
                    <td><code>saveValue($value)</code></td>
                    <td>Set a value that will always be saved</td>
                    <td><code>$rule->datetime('updated_at')->saveValue(date('Y-m-d H:i:s'))</code></td>
                </tr>
                <tr>
                    <td><code>unique()</code></td>
                    <td>Add UNIQUE constraint</td>
                    <td><code>$rule->email('email')->unique()</code></td>
                </tr>
                <tr>
                    <td><code>index()</code></td>
                    <td>Add database index</td>
                    <td><code>$rule->int('user_id')->index()</code></td>
                </tr>
                <tr>
                    <td><code>unsigned()</code></td>
                    <td>Make numeric field unsigned</td>
                    <td><code>$rule->int('quantity')->unsigned()</code></td>
                </tr>
                <tr>
                    <td><code>min($value)</code></td>
                    <td>Set minimum value (numeric/date/time) or minimum length (string/text). You can also pass another field name for backend comparison.</td>
                    <td><code>$rule->int('age')->min(18)</code><br><code>$rule->string('name', 100)->min(3)</code><br><code>$rule->date('start')->max('end')</code></td>
                </tr>
                <tr>
                    <td><code>max($value)</code></td>
                    <td>Set maximum value (numeric/date/time) or maximum length (string/text). You can also pass another field name for backend comparison.</td>
                    <td><code>$rule->int('quantity')->max(100)</code><br><code>$rule->string('title', 200)->max(120)</code><br><code>$rule->int('min_members')->max('max_members')</code></td>
                </tr>
                <tr>
                    <td><code>step($value)</code></td>
                    <td>Set step value for numeric inputs</td>
                    <td><code>$rule->decimal('price', 10, 2)->step(0.01)</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Display & Labeling</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>label(string $label)</code></td>
                    <td>Set display label</td>
                    <td><code>$rule->string('first_name', 50)->label('First Name')</code></td>
                </tr>
                <tr>
                    <td><code>formLabel(string $label)</code></td>
                    <td>Set form-specific label</td>
                    <td><code>$rule->text('content')->formLabel('Article Content')</code></td>
                </tr>
                 <tr>
                    <td><code>hide()</code></td>
                    <td>Hide from list/table view, edit form and detail view</td>
                    <td><code>$rule->text('notes')->hide()</code></td>
                </tr>
                <tr>
                    <td><code>hideFromList()</code></td>
                    <td>Hide from list/table view</td>
                    <td><code>$rule->text('notes')->hideFromList()</code></td>
                </tr>
                <tr>
                    <td><code>hideFromEdit()</code></td>
                    <td>Hide from edit form</td>
                    <td><code>$rule->created_at()->hideFromEdit()</code></td>
                </tr>
                <tr>
                    <td><code>hideFromView()</code></td>
                    <td>Hide from detail view</td>
                    <td><code>$rule->string('password', 255)->hideFromView()</code></td>
                </tr>
                <tr>
                    <td><code>excludeFromDatabase()</code></td>
                    <td>Don't create in database (virtual field)</td>
                    <td><code>$rule->string('computed_value', 100)->excludeFromDatabase()</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Form Configuration</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>formType(string $type)</code></td>
                    <td>Set HTML form input type</td>
                    <td><code>$rule->string('slug', 100)->formType('hidden')</code></td>
                </tr>
                <tr>
                    <td><code>formParams(array $params)</code></td>
                    <td>Set form parameters (HTML attributes)</td>
                    <td><code>$rule->text('bio')->formParams(['rows' => 5, 'cols' => 50])</code></td>
                </tr>
                <tr>
                    <td><code>error(string $message)</code></td>
                    <td>Set validation error message</td>
                    <td><code>$rule->email('email')->error('Please enter a valid email address')</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>File Upload Configuration</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>multiple(bool|int $multiple = true)</code></td>
                    <td>Allow multiple file uploads</td>
                    <td><code>$rule->image('photos')->multiple(5)</code></td>
                </tr>
                <tr>
                    <td><code>maxFiles(int $max)</code></td>
                    <td>Set maximum number of files</td>
                    <td><code>$rule->file('documents')->maxFiles(10)</code></td>
                </tr>
                <tr>
                    <td><code>accept(string $accept)</code></td>
                    <td>Set accepted file types</td>
                    <td><code>$rule->file('doc')->accept('.pdf,.doc,.docx')</code></td>
                </tr>
                <tr>
                    <td><code>maxSize(int $size)</code></td>
                    <td>Set max file size in bytes</td>
                    <td><code>$rule->image('avatar')->maxSize(2097152)</code> (2MB)</td>
                </tr>
                <tr>
                    <td><code>uploadDir(string $dir)</code></td>
                    <td>Set upload directory</td>
                    <td><code>$rule->image('photo')->uploadDir('/uploads/photos')</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Dynamic Options</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>options(array $options)</code></td>
                    <td>Set options for list/select/enum fields</td>
                    <td><code>$rule->list('status', [])->options($this->getStatusOptions())</code></td>
                </tr>
                <tr>
                    <td><code>apiUrl(string $url, ?string $display_field = null)</code></td>
                    <td>Set API URL for dynamic options loading</td>
                    <td><code>$rule->int('category_id')->apiUrl('/api/categories', 'name')</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">Relationships</h2>

    <div class="alert alert-info">
        <strong>📘 Important:</strong> Relationships are defined on the field that acts as the key. For more details, see <a href="?page=docs&action=Developer/Model/abstract-model-relationships">Relationships Documentation</a>.
    </div>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>belongsTo(string $alias, string $related_model, ?string $related_key = 'id')</code></td>
                    <td>Define a belongsTo relationship (foreign key in THIS table)</td>
                    <td><code>$rule->int('user_id')->belongsTo('user', UserModel::class)</code></td>
                </tr>
                <tr>
                    <td><code>hasOne(string $alias, string $related_model, string $foreign_key_in_related, string $onDelete = 'CASCADE')</code></td>
                    <td>Define a hasOne relationship (foreign key in RELATED table)</td>
                    <td><code>$rule->id()->hasOne('profile', ProfileModel::class, 'user_id')</code></td>
                </tr>
                <tr>
                    <td><code>hasMany(string $alias, string $related_model, string $foreign_key_in_related, string $onDelete = 'CASCADE')</code></td>
                    <td>Define a hasMany relationship (foreign key in RELATED table)</td>
                    <td><code>$rule->id()->hasMany('posts', PostModel::class, 'user_id')</code></td>
                </tr>
                <tr>
                    <td><code>withCount(string $alias, string $related_model, string $foreign_key_in_related)</code></td>
                    <td>Add a COUNT subquery (virtual field, read-only, always included in queries)</td>
                    <td><code>$rule->id()->withCount('posts_count', PostModel::class, 'user_id')</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Relationship Example</h3>

    <pre class="language-php"><code>// PostsModel
protected function configure($rule): void {
    $rule->table('#__posts')
        ->id()
        ->string('title', 200)->required()
        ->int('user_id')->belongsTo('author', UserModel::class)  // Foreign key here
        ->text('content');
}

// UserModel
protected function configure($rule): void {
    $rule->table('#__users')
        ->id()
            ->hasMany('posts', PostModel::class, 'user_id')      // Load actual posts
            ->withCount('posts_count', PostModel::class, 'user_id')  // Count posts efficiently
        ->title('username', 50)->required()
        ->email('email');
}

// Usage
$user = $userModel->getById(1);
echo $user->posts_count;  // 15 (fast COUNT subquery)
$posts = $user->posts;     // Array of PostModel (lazy loaded)</code></pre>

    <h2 class="mt-4">Advanced Customization</h2>

    <h3>Custom Getters, Setters & Editors</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>getter(callable $fn)</code></td>
                    <td>Custom getter function (for formatted display)</td>
                    <td><code>$rule->string('name', 100)->getter(fn($v) => strtoupper($v))</code></td>
                </tr>
                <tr>
                    <td><code>setter(callable $fn)</code></td>
                    <td>Custom setter function (before save to DB)</td>
                    <td><code>$rule->string('slug', 100)->setter(fn($v) => slugify($v))</code></td>
                </tr>
                <tr>
                    <td><code>editor(callable $fn)</code></td>
                    <td>Custom editor function (for form display)</td>
                    <td><code>$rule->text('bio')->editor(fn($v) => htmlspecialchars($v))</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3>Custom Properties</h3>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>property(string $key, $value)</code></td>
                    <td>Set a custom property</td>
                    <td><code>$rule->string('code', 20)->property('uppercase', true)</code></td>
                </tr>
                <tr>
                    <td><code>properties(array $properties)</code></td>
                    <td>Set multiple custom properties at once</td>
                    <td><code>$rule->text('content')->properties(['editor' => 'wysiwyg', 'toolbar' => 'full'])</code></td>
                </tr>
                <tr>
                    <td><code>customize(callable $callback)</code></td>
                    <td>Customize field with a callback</td>
                    <td><code>$rule->int('age')->customize(fn($field) => [...$field, 'custom' => true])</code></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">Utility Methods</h2>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Description</th>
                    <th>Returns</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>getRules(): array</code></td>
                    <td>Get all defined field rules</td>
                    <td>Array of field configurations</td>
                </tr>
                <tr>
                    <td><code>setRules(array $rules): self</code></td>
                    <td>Set all rules at once</td>
                    <td>RuleBuilder instance</td>
                </tr>
                <tr>
                    <td><code>clear(): self</code></td>
                    <td>Clear all rules</td>
                    <td>RuleBuilder instance</td>
                </tr>
                <tr>
                    <td><code>getTable(): ?string</code></td>
                    <td>Get the table name</td>
                    <td>Table name or null</td>
                </tr>
                <tr>
                    <td><code>getPrimaryKey(): ?string</code></td>
                    <td>Get the primary key name</td>
                    <td>Primary key field name or null</td>
                </tr>
                <tr>
                    <td><code>getDbType(): ?string</code></td>
                    <td>Get database connection type</td>
                    <td>'db' or 'db2'</td>
                </tr>
                <tr>
                    <td><code>changeCurrentField(string $name)</code></td>
                    <td>Switch to configuring a different field</td>
                    <td>void</td>
                </tr>
                <tr>
                    <td><code>changeType(string $name, string $type): self</code></td>
                    <td>Change the type of an existing field</td>
                    <td>RuleBuilder instance</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">Complete Example</h2>

    <pre class="language-php"><code>namespace Modules\Shop;
use App\Abstracts\AbstractModel;
use Modules\Users\UsersModel;
use Modules\Categories\CategoriesModel;

class ProductsModel extends AbstractModel
{
    protected function configure($rule): void {
        $rule->table('#__shop_products')

            // Primary key
            ->id()

            // Basic fields
            ->title('name', 200)->required()
            ->string('sku', 50)->unique()->required()

            // Relationships
            ->int('category_id')
                ->belongsTo('category', CategoriesModel::class)
                ->required()
                ->index()

            ->created_by()
                ->belongsTo('author', UsersModel::class)
                ->hideFromEdit()

            // Pricing
            ->decimal('price', 10, 2)
                ->default(0)
                ->min(0)
                ->step(0.01)
                ->required()

            ->decimal('sale_price', 10, 2)
                ->nullable()
                ->min(0)

            // Description
            ->text('description')->nullable()
            ->text('short_description')->nullable()->hideFromList()

            // Stock management
            ->int('stock_quantity')->default(0)->unsigned()
            ->boolean('track_inventory')->default(true)
            ->boolean('in_stock')->default(true)

            // Status
            ->enum('status', ['draft', 'published', 'archived'])
                ->default('draft')

            // Media
            ->image('featured_image')
                ->accept('image/*')
                ->maxSize(5242880)  // 5MB
                ->uploadDir('/uploads/products')
                ->nullable()

            ->file('gallery')
                ->multiple(10)
                ->accept('image/*')
                ->nullable()

            // Metadata
            ->array('meta_data')->nullable()->excludeFromDatabase()

            // Audit fields
            ->created_at()
            ->updated_at()
            ->updated_by()
                ->belongsTo('editor', UsersModel::class);
    }
}</code></pre>

    <h2 class="mt-4">Field Rule Structure</h2>

    <p>Each field rule contains the following properties:</p>

    <pre class="language-php"><code>[
    'type' => 'string',           // PHP and SQL type
    'length' => 100,              // Max length for strings
    'precision' => 2,             // Precision for floats/decimals
    'nullable' => true,           // Can be NULL
    'default' => null,            // Default value
    'primary' => false,           // Is primary key
    'label' => 'Field Name',      // Display label
    'options' => [],              // Options for list/enum
    'index' => false,             // Create database index
    'unique' => false,            // UNIQUE constraint
    'unsigned' => false,          // Unsigned numeric
    'list' => true,               // Show in list view
    'edit' => true,               // Show in edit form
    'view' => true,               // Show in detail view
    'sql' => true,                // Create in database
    'form-type' => 'text',        // HTML input type
    'form-label' => null,         // Form-specific label
    'form-params' => [],          // HTML attributes
    'relationship' => [],         // Relationship config
    'api_url' => null,            // API endpoint for options
    'save_value' => null,         // Value to always save
    '_auto_created_at' => false,  // Auto-preserve on update
    '_auto_updated_at' => false,  // Auto-update on every save
    '_auto_created_by' => false,  // Auto-preserve creator user id
    '_auto_updated_by' => false,  // Auto-update editor user id
    '_is_title_field' => false,   // Used in belongsTo display
    '_get' => callable,           // Custom getter
    '_set' => callable,           // Custom setter
    '_edit' => callable,          // Custom editor
]</code></pre>

    <h2 class="mt-4">Tests</h2>

    <div class="alert alert-info">
        RuleBuilder has a dedicated unit test suite:
        <code>php vendor/bin/phpunit tests/Unit/Builders/RuleBuilderMethodsTest.php</code>
    </div>

    <h2 class="mt-4">Next Steps</h2>

    <div class="alert alert-success">
        <strong>📚 Related Documentation:</strong>
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/AbstractsClass/abstract-model-overview">Abstract Model - Overview</a></li>
            <li><a href="?page=docs&action=Developer/Model/abstract-model-relationships">Relationships</a></li>
            <li><a href="?page=docs&action=Developer/Model/abstract-model-crud">CRUD Operations</a></li>
            <li><a href="?page=docs&action=Framework/Core/schema">Schema Management</a></li>
        </ul>
    </div>
</div>
