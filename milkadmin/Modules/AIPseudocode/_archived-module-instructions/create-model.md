# Create Model

Create Model from table structure using RuleBuilder.

## Flow

1. Run: `php milkadmin/cli.php sql-table-structure {table_name}`
2. Decision:
   - Table NOT exists → use pseudocode fields
   - Table exists + NO pseudocode fields → use table structure
   - Table exists + pseudocode fields match → combine
   - Table exists + pseudocode fields CONFLICT → **STOP, ask user**
3. Generate Model configure() method

## Multiple Models in Module

**CRITICAL**: If you create additional Models in the same Module, you MUST register them in the Module's `configure()` method using `->addModels()` in the chain.

### Correct Way ✅

```php
// In YourModule.php configure() method
$rule->page('lessons')
     ->title('Corsi di Laurea')
     ->addModels([
         'Enrollment' => LessonsEnrollmentModel::class
     ])
     ->version(251205);
```

### Wrong Way ❌

```php
// DON'T DO THIS - This won't work!
protected function addModels(): array
{
    return [
        'main' => MainModel::class,
        'secondary' => SecondaryModel::class
    ];
}
```

**Without `->addModels()` in the configure chain**, the framework won't:
- Create/update secondary Model tables during install/update
- Include secondary Model in migrations
- Manage relationships properly

See `create-module.md` for complete examples.

## RuleBuilder Methods

### Field Types

```php
->id($name = 'id')                           // Primary key
->string($name, $length = 255)               // VARCHAR (default 255)
->title($name = 'title', $length = 255)      // Special string (used in belongsTo)
->text($name)                                // TEXT
->int($name)                                 // INTEGER
->datetime($name)                            // DATETIME (timezone_conversion = true default)
->date($name)                                // DATE
->time($name)                                // TIME
->timestamp($name)                           // TIMESTAMP
->decimal($name, $length = 10, $prec = 2)   // DECIMAL (creates 'float' type)
->boolean($name)                             // TINYINT(1)
->enum($name, array $options)                // ENUM
```

### Form Field Types

```php
->email($name)                               // string(255) + formType('email')
->tel($name)                                 // string(25) + formType('tel')
->url($name)                                 // string(255) + formType('url')
->list($name, array $options)                // SELECT dropdown
->select($name, array $options)              // Alias for list()
->radio($name, array $options)               // Radio buttons
->checkbox($name)                            // Alias for boolean()
->checkboxes($name, array $options)          // Multiple checkboxes (array type)
->file($name)                                // File upload (array type)
->image($name)                               // Image upload (array type)
->array($name)                               // Array type
```

### Modifiers

```php
->nullable($bool = true)        // NULL allowed (DEFAULT: true for all fields!)
->required()                    // Form validation only (adds form-params['required'])
->default($value)               // Default value
->unique()                      // UNIQUE constraint
->index()                       // INDEX
->unsigned()                    // UNSIGNED (numeric fields)
->label($label)                 // Label (auto-generated if not specified)
->formType($type)               // Form type: 'editor', 'password', 'list', 'hidden', etc.
->formParams(array $params)     // Custom form parameters
->hideFromList()                // Hide from list view
->hideFromEdit()                // Hide from edit form
->hideFromView()                // Hide from detail view
->hide()                        // Hide from all views
->excludeFromDatabase()         // Don't create in DB (sql = false)
```

### Special Methods

```php
->table($name)                  // Set table name
->extensions(array $exts)       // Load extensions: 'Audit', 'SoftDelete', 'Author'
->noTimezoneConversion()        // Disable timezone conversion for datetime
```

### Relationships (IMPORTANT)

**CRITICAL**: Relationship methods MUST be chained immediately after the foreign key field they refer to.

#### belongsTo()

Defines a many-to-one relationship. MUST be chained on the foreign key field.

**Syntax:**
```php
->int('foreign_key_field')->belongsTo('alias', ModelClass::class, $related_key = 'id')
```

**Correct Way ✅**

```php
// LessonsEnrollmentModel.php
$rule->table('#__lessons_enrollments')
     ->id()
     ->int('id_corso')->belongsTo('corso', LessonsModel::class)->required()->label('Corso')
     ->string('nome_utente', 255)->required();
```

**Wrong Way ❌**

```php
// DON'T DO THIS - belongsTo must be chained on the field!
$rule->table('#__lessons_enrollments')
     ->id()
     ->int('id_corso')->required()->label('Corso')
     ->string('nome_utente', 255)->required()
     ->belongsTo('corso', LessonsModel::class, 'id_corso');  // ❌ WRONG!
```

**Parameters:**
- `$alias` - Name to access the related object (e.g., `$enrollment->corso`)
- `$model` - Related model class
- `$related_key` - Primary key in related model (default: 'id')

**Example with custom key:**
```php
->int('user_id')->belongsTo('author', UserModel::class, 'user_id')
```

#### hasOne()

Defines a one-to-one relationship.

```php
->hasOne($alias, $model, $foreign_key, $onDelete = 'CASCADE')
```

**Example:**
```php
// UserModel.php
$rule->table('#__users')
     ->id()
     ->string('username', 255)
     ->hasOne('profile', UserProfileModel::class, 'user_id');
```

#### hasMany()

Defines a one-to-many relationship.

```php
->hasMany($alias, $model, $foreign_key, $onDelete = 'CASCADE')
```

**Example:**
```php
// LessonsModel.php
$rule->table('#__lessons')
     ->id()
     ->title('nome_corso', 255)
     ->hasMany('enrollments', LessonsEnrollmentModel::class, 'id_corso');
```

**Relationship chaining order:**
1. Define the field (e.g., `->int('id_corso')`)
2. Chain `->belongsTo()` immediately
3. Continue with modifiers (e.g., `->required()`, `->label()`)

**Common errors:**
- Putting `->belongsTo()` at the end of the chain (it applies to the last field!)
- Using the foreign key field name as the third parameter (use the related model's primary key instead)
- Forgetting to chain `->belongsTo()` directly on the foreign key field

## SQL Type → RuleBuilder Mapping

From `sql-table-structure` output:

```
INTEGER + PRI → ->id()
INTEGER + is_X/status → ->boolean($name)
INTEGER → ->int($name)
TEXT → ->string($name, 100) or ->text($name) (context-based)
DATETIME → ->datetime($name)
DATE → ->date($name)
TIME → ->time($name)
```

## Important Defaults

- **nullable = true** (all fields nullable by default!)
- **string length = 255** (if not specified)
- **decimal = 10,2** (length, precision)
- **label = auto-generated** from field name (e.g., 'user_name' → 'User name')
- **datetime timezone_conversion = true** (use noTimezoneConversion() to disable)

## Examples

### UserModel (Auth)
```php
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
```

### PostsModel
```php
$rule->table('#__posts')
    ->id()
    ->title()->index()  // Special string field with _is_title_field flag
    ->extensions(['Audit', 'SoftDelete', 'Author'])
    ->text('content')->formType('editor');
// Extensions auto-add: created_by, created_at, updated_by, updated_at, deleted_at, deleted_by
```

### EventsModel
```php
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
```

## Field Interpretation (Natural Language)

- "name", "username" → `string($name, 100)`
- "email" → `email($name)` (string 255 + email formType)
- "description", "content" → `text($name)->formType('editor')`
- "price", "amount" → `decimal($name, 10, 2)`
- "count", "quantity" → `int($name)`
- "is_active", "enabled" → `boolean($name)`
- "created_at" → `datetime($name)`
- "status" with options → `list($name, $options)` or `enum($name, $options)`

## Auto-add Fields

Don't add if using extensions:
- `extensions(['Audit'])` auto-adds: created_by, created_at, updated_by, updated_at
- `extensions(['SoftDelete'])` auto-adds: deleted_at, deleted_by
- `extensions(['Author'])` auto-adds: author tracking

## Model Attributes (PHP Attributes)

Custom behaviors using PHP attributes. Use sparingly, only when needed.

### #[Validate('field_name')]
Custom field validation. Return empty string if valid, error message if invalid.

```php
use App\Attributes\Validate;

#[Validate('email')]
public function validateEmail($obj): string {
    return filter_var($obj->email, FILTER_VALIDATE_EMAIL) ? '' : 'Invalid email';
}

#[Validate('title')]
public function validateTitle($obj): string {
    return strlen($obj->title) < 5 ? 'Title must be at least 5 characters' : '';
}
```

### #[ToDatabaseValue('field_name')]
Transform value before saving to database. Return transformed value.

```php
use App\Attributes\ToDatabaseValue;

#[ToDatabaseValue('password')]
public function hashPassword($obj) {
    return password_hash($obj->password, PASSWORD_DEFAULT);
}

#[ToDatabaseValue('permissions')]
public function jsonPermissions($obj) {
    return json_encode($obj->permissions);
}
```

### #[ToDisplayValue('field_name')]
Format value for display (list view, detail view). Return formatted string.

```php
use App\Attributes\ToDisplayValue;

#[ToDisplayValue('created_by')]
public function showUsername($obj) {
    return $obj->created_user->username ?? '-';
}

#[ToDisplayValue('password')]
public function hidePassword($obj) {
    return '********';
}
```

### #[ToDatabaseValue('field_name')]
Alias for BeforeSave. Same usage.

**When to use attributes**:
- **Validate**: Complex validation not possible with RuleBuilder
- **BeforeSave**: Transform before DB (hash, JSON, set user ID)
- **ToDisplayValue**: Format for display (username from ID, hide sensitive data)

## Common Mistakes ⚠️

### 1. Wrong addModels() placement
**❌ Wrong:**
```php
// As separate method - won't work!
protected function addModels(): array {
    return ['Enrollment' => EnrollmentModel::class];
}
```

**✅ Correct:**
```php
// In configure() chain
$rule->page('lessons')
     ->addModels(['Enrollment' => EnrollmentModel::class])
     ->version(251205);
```

### 2. Wrong belongsTo() placement
**❌ Wrong:**
```php
// At the end - applies to wrong field!
->int('id_corso')->required()
->string('nome_utente', 255)
->belongsTo('corso', LessonsModel::class);  // ❌ Will fail!
```

**✅ Correct:**
```php
// Chained immediately after the foreign key field
->int('id_corso')->belongsTo('corso', LessonsModel::class)->required()
->string('nome_utente', 255);
```

### 3. Wrong belongsTo() third parameter
**❌ Wrong:**
```php
// Using foreign key instead of related key
->int('id_corso')->belongsTo('corso', LessonsModel::class, 'id_corso');  // ❌
```

**✅ Correct:**
```php
// Using related model's primary key (or omit - default is 'id')
->int('id_corso')->belongsTo('corso', LessonsModel::class, 'id');
// Or simply:
->int('id_corso')->belongsTo('corso', LessonsModel::class);
```

## Notes

- All fields nullable by default (no need to specify ->nullable())
- Only use ->required() for form validation, not DB constraint
- Table prefix `#__` auto-replaced with configured prefix
- Use ->title() for main display field (auto-used in belongsTo relationships)
- **Use `->addModels([...])` in configure() chain, NOT as separate method**
- **Chain `->belongsTo()` immediately after the foreign key field**

## See Also

- **Query and CRUD operations**: `model-queries.md` - Complete guide to querying and manipulating data
- **Module creation**: `create-module.md` - Creating modules and models
- **Examples**: `examples/` folder - Real working code
