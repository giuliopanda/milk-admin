# API Reference - Quick Method Lookup

Complete reference of available methods for Module, Model, Controller, and Builders.

## Module Configuration (AbstractModule)

Used in `{ModuleName}Module.php` → `configure($rule)` method.

```php
protected function configure($rule): void {
    $rule->page('modulename')           // URL parameter (?page=modulename)
         ->title('Module Title')         // Module display title
         ->menu(label, url, icon, order) // Add to sidebar menu
         ->access(level)                 // Access level (see below)
         ->permissions(array)            // Custom permissions
         ->addModels(array)              // Register additional models
         ->version(YYMMDD);              // Version number
}
```

### Module Methods

| Method | Parameters | Description |
|--------|-----------|-------------|
| `page(string $name)` | Page identifier | URL parameter (?page=...) |
| `title(string $title)` | Display title | Module title |
| `menu(string $label, string $url, string $icon, int $order)` | Label, URL, Bootstrap icon, position | Add menu item |
| `access(string $level)` | 'public' \| 'registered' \| 'authorized' \| 'admin' | Access level |
| `permissions(array $perms)` | Array of permissions | Define custom permissions |
| `addModels(array $models)` | `['Alias' => ModelClass::class]` | Register additional models |
| `version(int $version)` | YYMMDD format | Version number |

### Access Levels
- `'public'` - Anyone can access
- `'registered'` - Logged-in users only
- `'authorized'` - Requires specific permission
- `'admin'` - Administrators only

---

## Model Configuration (AbstractModel)

Used in `{ModuleName}Model.php` → `configure($rule)` method.

Reference: [abstract-model-rulebuilder.page.php](../Docs/Pages/Developer/AbstractsClass/abstract-model-rulebuilder.page.php)

### Core Configuration

```php
protected function configure($rule): void {
    $rule->table('#__table_name')  // Define table
         ->db('db' | 'db2')        // Database connection (optional)
         ->id()                     // Primary key
         // ... fields ...
}
```

### Field Types

| Method | SQL Type | Description | Example |
|--------|----------|-------------|---------|
| `id(string $name = 'id')` | INT AUTO_INCREMENT | Primary key | `->id()` |
| `string(string $name, int $length = 255)` | VARCHAR | String field | `->string('name', 100)` |
| `title(string $name = 'title', int $length = 255)` | VARCHAR | Title field (used in relationships) | `->title()` |
| `text(string $name)` | TEXT | Long text | `->text('content')` |
| `int(string $name)` | INT | Integer | `->int('quantity')` |
| `decimal(string $name, int $p = 10, int $s = 2)` | DECIMAL | Decimal number | `->decimal('price', 10, 2)` |
| `boolean(string $name)` | TINYINT(1) | Boolean/checkbox | `->boolean('is_active')` |
| `array(string $name)` | TEXT (JSON) | Array stored as JSON | `->array('metadata')` |

### Date/Time Types

| Method | SQL Type | Description |
|--------|----------|-------------|
| `datetime(string $name)` | DATETIME | Date and time |
| `date(string $name)` | DATE | Date only |
| `time(string $name)` | TIME | Time only |
| `timestamp(string $name)` | DATETIME | Timestamp |
| `created_at(string $name = 'created_at')` | DATETIME | Auto-preserved creation time |

### Input Types

| Method | Description |
|--------|-------------|
| `email(string $name)` | Email field with validation |
| `tel(string $name)` | Telephone field |
| `url(string $name)` | URL field with validation |
| `file(string $name)` | File upload |
| `image(string $name)` | Image upload (images only) |

### Selection Types

| Method | Description | Example |
|--------|-------------|---------|
| `list(string $name, array $options)` | Dropdown select | `->list('status', ['active'=>'Active', 'inactive'=>'Inactive'])` |
| `select(string $name, array $options)` | Alias for list() | `->select('category', ['1'=>'Cat1', '2'=>'Cat2'])` |
| `enum(string $name, array $options)` | Database ENUM | `->enum('size', ['S', 'M', 'L', 'XL'])` |
| `radio(string $name, array $options)` | Radio buttons | `->radio('gender', ['M'=>'Male', 'F'=>'Female'])` |
| `checkbox(string $name)` | Single checkbox | `->checkbox('agree_terms')` |
| `checkboxes(string $name, array $options)` | Multiple checkboxes | `->checkboxes('features', ['wifi'=>'WiFi'])` |

### Field Modifiers

Chain after field type to modify behavior:

| Method | Description | Example |
|--------|-------------|---------|
| `required()` | Make field required | `->string('name', 100)->required()` |
| `nullable(bool $nullable = true)` | Allow NULL | `->text('bio')->nullable()` |
| `default($value)` | Set default value | `->int('views')->default(0)` |
| `saveValue($value)` | Always save this value | `->datetime('updated_at')->saveValue(date('Y-m-d H:i:s'))` |
| `unique()` | Add UNIQUE constraint | `->email('email')->unique()` |
| `index()` | Add database index | `->int('user_id')->index()` |
| `unsigned()` | Unsigned numeric | `->int('quantity')->unsigned()` |
| `min($value)` | Minimum value | `->int('age')->min(18)` |
| `max($value)` | Maximum value | `->int('quantity')->max(100)` |
| `step($value)` | Step for numeric inputs | `->decimal('price', 10, 2)->step(0.01)` |

### Display Modifiers

| Method | Description |
|--------|-------------|
| `label(string $label)` | Set display label |
| `formLabel(string $label)` | Set form-specific label |
| `hide()` | Hide from list, edit, and view |
| `hideFromList()` | Hide from table/list |
| `hideFromEdit()` | Hide from edit form |
| `hideFromView()` | Hide from detail view |
| `excludeFromDatabase()` | Virtual field (not in DB) |

### Form Configuration

| Method | Description | Example |
|--------|-------------|---------|
| `formType(string $type)` | Set form input type | `->text('content')->formType('editor')` |
| `formParams(array $params)` | Set HTML attributes | `->text('bio')->formParams(['rows'=>5])` |
| `error(string $message)` | Validation error message | `->email('email')->error('Invalid email')` |

### File Upload

| Method | Description | Example |
|--------|-------------|---------|
| `multiple(bool\|int $max = true)` | Allow multiple uploads | `->image('photos')->multiple(5)` |
| `maxFiles(int $max)` | Max number of files | `->file('docs')->maxFiles(10)` |
| `accept(string $types)` | Accepted file types | `->file('doc')->accept('.pdf,.doc')` |
| `maxSize(int $bytes)` | Max file size | `->image('avatar')->maxSize(2097152)` |
| `uploadDir(string $dir)` | Upload directory | `->image('photo')->uploadDir('/uploads/photos')` |

### Relationships

| Method | Description | Example |
|--------|-------------|---------|
| `belongsTo(string $alias, string $model, ?string $key = 'id')` | Foreign key (in THIS table) | `->int('user_id')->belongsTo('author', UserModel::class)` |
| `hasOne(string $alias, string $model, string $foreign_key, string $onDelete = 'CASCADE')` | One-to-one (foreign key in RELATED table) | `->id()->hasOne('profile', ProfileModel::class, 'user_id')` |
| `hasMany(string $alias, string $model, string $foreign_key, string $onDelete = 'CASCADE')` | One-to-many (foreign key in RELATED table) | `->id()->hasMany('posts', PostModel::class, 'user_id')` |

### Advanced

| Method | Description |
|--------|-------------|
| `getter(callable $fn)` | Custom getter function |
| `setter(callable $fn)` | Custom setter function |
| `editor(callable $fn)` | Custom editor function |
| `property(string $key, $value)` | Set custom property |
| `properties(array $props)` | Set multiple properties |
| `options(array $options)` | Set dynamic options |
| `apiUrl(string $url, ?string $display = null)` | API endpoint for options |

---

## Controller (AbstractController)

Used in `{ModuleName}Controller.php`.

### Inherited Properties

```php
$this->page      // Module page name (from configure)
$this->title     // Module title (from configure)
$this->model     // Model instance
$this->module    // Module instance
```

### Helper Methods

```php
$this->getCommonData()              // Returns ['page'=>..., 'title'=>...]
$this->access()                      // Check access permission
$this->getAdditionalModels('Alias')  // Get additional model
```

### Routing (Attributes)

```php
#[RequestAction('home')]       // Called when ?action=home (or no action)
#[RequestAction('edit')]       // Called when ?action=edit

#[AccessLevel('public')]       // Override module access level
#[AccessLevel('registered')]
#[AccessLevel('admin')]
#[AccessLevel('authorized')]
```

### Response Methods

```php
Response::render(string $path, array $data)  // Render HTML view
Response::json(array $data)                   // Return JSON
Response::denyAccess()                        // Deny access
Response::csv(array $data, string $filename) // CSV export
Response::isJson()                            // Check if AJAX request
```

---

## TableBuilder

Generate data tables from models.

Reference: Standard CRUD in [Posts module](../Posts/PostsController.php)

### Basic Usage

```php
$tableBuilder = TableBuilder::create($model, 'idTableName')
    ->field('title')->link('?page=posts&action=edit&id=%id%')
    ->setDefaultActions();
$response = array_merge($this->getCommonData(), $tableBuilder->getResponse());
```

### Methods

| Method | Description | Example |
|--------|-------------|---------|
| `create($model, string $id)` | Create table builder | `TableBuilder::create($this->model, 'idTable')` |
| `activeFetch()` | Enable AJAX mode | `->activeFetch()` |
| `field(string $name)` | Select field | `->field('title')` |
| `->link(string $url)` | Make field clickable | `->link('?page=posts&action=edit&id=%id%')` |
| `->label(string $label)` | Set column label | `->label('Product Name')` |
| `->truncate(int $length)` | Truncate text | `->truncate(50)` |
| `->fn(callable $fn)` | Custom formatter | `->fn(function($row) { return strtoupper($row->name); })` |
| `setDefaultActions()` | Add edit/delete buttons | `->setDefaultActions()` |
| `addAction(string $name, array $config)` | Add custom action | `->addAction('view', ['label'=>'View', 'link'=>'?page=...&id=%id%'])` |
| `where(string $sql, array $params)` | Filter records | `->where('status = ?', ['active'])` |
| `customData(string $key, $value)` | Add custom data | `->customData('post_id', $id)` |
| `getResponse()` | Get array for Response | `->getResponse()` |
| `render()` | Get HTML string | `->render()` |

---

## FormBuilder

Generate forms from models.

Reference: [builders-form-fields.page.php](../Docs/Pages/Developer/Form/builders-form-fields.page.php)

### Standard CRUD Pattern

```php
$response['form'] = FormBuilder::create($this->model, $this->page)
    ->getForm();
```

### Fetch-Based Pattern

```php
$response = array_merge(
    $this->getCommonData(),
    FormBuilder::create($this->model, $this->page)
        ->activeFetch()
        ->asOffcanvas()
        ->setTitle('New Item', 'Edit Item')
        ->dataListId('idTable')
        ->getResponse()
);
Response::json($response);
```

### Core Methods

| Method | Description | Example |
|--------|-------------|---------|
| `create($model, string $page, ?string $redirect = null)` | Create form builder | `FormBuilder::create($this->model, $this->page)` |
| `activeFetch()` | Enable AJAX mode | `->activeFetch()` |
| `getForm()` | Get HTML (for page reload) | `->getForm()` |
| `getResponse()` | Get JSON array (for fetch mode) | `->getResponse()` |

### Display Methods

| Method | Description |
|--------|-------------|
| `asOffcanvas()` | Show in side panel |
| `asModal()` | Show in modal dialog |
| `asDom(string $id)` | Render in DOM element |
| `size(string $size)` | Set size: 'sm', 'lg', 'xl', 'fullscreen' |
| `setTitle(string $new, ?string $edit = null)` | Dynamic titles for new/edit |

### Data Methods

| Method | Description |
|--------|-------------|
| `dataListId(string $id)` | Auto-reload table on save |
| `customData(string $key, $value)` | Pass custom data |

### Field Configuration Methods

Reference: [builders-form-fields.page.php](../Docs/Pages/Developer/Form/builders-form-fields.page.php)

Chain after `field(string $name)`:

| Method | Description | Example |
|--------|-------------|---------|
| `field(string $name)` | Select field to modify | `->field('email')` |
| `type(string $type)` | Set data type | `->type('string')` |
| `formType(string $type)` | Set input type | `->formType('select')` |
| `label(string $label)` | Set field label | `->label('Email Address')` |
| `options(array $options)` | Set select/radio options | `->options(['1'=>'Yes', '0'=>'No'])` |
| `required(bool $req = true)` | Make required | `->required()` |
| `helpText(string $text)` | Help text below field | `->helpText('Format: xxx-xxxx')` |
| `value($value)` | Set field value | `->value('default')` |
| `default($value)` | Default if no value | `->default('IT')` |
| `disabled(bool $dis = true)` | Disable field | `->disabled()` |
| `readonly(bool $ro = true)` | Make readonly | `->readonly()` |
| `class(string $class)` | CSS class | `->class('form-control-lg')` |
| `errorMessage(string $msg)` | Custom error message | `->errorMessage('Invalid email')` |
| `moveBefore(string $field)` | Move before another field | `->moveBefore('status')` |

### Field Management

| Method | Description |
|--------|-------------|
| `addField(string $name, string $type, array $options, string $before = '')` | Add new field |
| `modifyField(string $name, array $options, string $before = '')` | Modify existing field |
| `removeField(string $name)` | Remove field |
| `hideField(string $name)` | Hide field (but include value) |
| `fieldOrder(array $fields)` | Set field order |

### Actions

| Method | Description |
|--------|-------------|
| `addStandardActions(bool $show_delete = false, ?string $redirect = null)` | Add Save/Cancel buttons |
| `ActionExecution()` | Execute save action (for fetch mode) |

---

## Model Query Methods

Used in Controller to fetch data.

Reference: [abstract-model-crud.page.php](../Docs/Pages/Developer/AbstractsClass/abstract-model-crud.page.php)

### Retrieval

```php
$model->getAll()                    // Get all records
$model->getById($id)                // Get single record by ID
$model->getEmpty()                  // Get empty object for new record
$model->isEmpty()                   // Check if result is empty

// With conditions
$model->where('field = ?', [$value])->getAll()
$model->where('field = ?', [$value])->getRow()
$model->where('field = ?', [$value])->getTotal()
```

### Save & Delete

```php
// Save (handles both insert and update)
$result = $model->save();
if ($result['success']) {
    // Success
} else {
    // Error: $result['error']
}

// Delete
$model->delete($id)
```

### Query Building

```php
$model->query()
    ->where('status = ?', ['active'])
    ->orderBy('created_at DESC')
    ->limit(10)
    ->getAll()
```

---

## Common Patterns

### Standard List + Edit (Page Reload)

```php
#[RequestAction('home')]
public function list() {
    $tableBuilder = TableBuilder::create($this->model, 'idTable')
        ->field('title')->link('?page='.$this->page.'&action=edit&id=%id%')
        ->setDefaultActions();
    $response = array_merge($this->getCommonData(), $tableBuilder->getResponse());
    $response['title_btns'] = [['label'=>'Add New', 'link'=>'?page='.$this->page.'&action=edit']];
    Response::render(MILK_DIR . '/Theme/SharedViews/list_page.php', $response);
}

#[RequestAction('edit')]
public function edit() {
    $response = $this->getCommonData();
    $response['form'] = FormBuilder::create($this->model, $this->page)->getForm();
    Response::render(MILK_DIR . '/Theme/SharedViews/edit_page.php', $response);
}
```

### Fetch-Based List + Edit (No Reload)

```php
#[RequestAction('home')]
public function list() {
    $tableBuilder = TableBuilder::create($this->model, 'idTable')
        ->activeFetch()
        ->field('name')->link('?page='.$this->page.'&action=edit&id=%id%')
        ->setDefaultActions();
    $response = array_merge($this->getCommonData(), $tableBuilder->getResponse());
    Response::render(__DIR__ . '/Views/list_page.php', $response);
}

#[RequestAction('edit')]
public function edit() {
    $response = array_merge(
        $this->getCommonData(),
        FormBuilder::create($this->model, $this->page)
            ->activeFetch()
            ->asOffcanvas()
            ->setTitle('New', 'Edit')
            ->dataListId('idTable')
            ->getResponse()
    );
    Response::json($response);
}
```

---

**For complete examples, see:**
- [milkadmin/Modules/Posts/](../Posts/) - Standard CRUD
- [milkadmin_local/Modules/Recipe/](../../../milkadmin_local/Modules/Recipe/) - Fetch-based
