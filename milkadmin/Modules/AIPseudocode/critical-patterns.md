# Critical Patterns - Common Mistakes & Solutions

**Read this before coding to avoid common mistakes.**

## 🎯 Core Principles

### 1. ALWAYS Use SharedViews

```php
// ✅ CORRECT - Use SharedViews
Response::render(MILK_DIR . '/Theme/SharedViews/list_page.php', $response);
Response::render(MILK_DIR . '/Theme/SharedViews/edit_page.php', $response);

// ❌ WRONG - Don't create custom views unless absolutely required
Response::render(__DIR__ . '/Views/list_page.php', $response);
```

**Exception:** Only create custom views when:
- Explicitly requested by user
- Unique layout requirements (gallery, dashboard, etc.)
- See Recipe module for fetch-based example that has custom view

### 2. Follow Standard Patterns

```php
// ✅ CORRECT - Standard list action pattern
#[RequestAction('home')]
public function list() {
    $tableBuilder = TableBuilder::create($this->model, 'idTable')
        ->field('title')->link('?page='.$this->page.'&action=edit&id=%id%')
        ->setDefaultActions();
    $response = array_merge($this->getCommonData(), $tableBuilder->getResponse());
    $response['title_btns'] = [['label'=>'Add New', 'link'=>'?page='.$this->page.'&action=edit']];
    Response::render(MILK_DIR . '/Theme/SharedViews/list_page.php', $response);
}

// ❌ WRONG - Don't invent your own patterns
public function list() {
    $data = $this->model->getAll();
    $html = '<table>...</table>';  // Don't build HTML manually
    echo $html;                     // Don't echo directly
}
```

---

## ⚠️ Critical Mistakes to Avoid

### Module Configuration

#### 1. addModels() Placement

```php
// ✅ CORRECT - Chain in configure()
protected function configure($rule): void {
    $rule->page('lessons')
         ->title('Lessons')
         ->addModels([
             'Enrollment' => LessonsEnrollmentModel::class
         ])
         ->version(251215);
}

// ❌ WRONG - Separate method doesn't work
protected function addModels(): array {
    return ['Enrollment' => LessonsEnrollmentModel::class];
}
```

#### 2. belongsTo() Placement

```php
// ✅ CORRECT - Chain immediately after field
->int('user_id')->belongsTo('author', UserModel::class)->required()

// ❌ WRONG - At end of chain (applies to wrong field!)
->int('user_id')->required()->belongsTo('author', UserModel::class)
```

#### 3. belongsTo() Third Parameter

```php
// ✅ CORRECT - Use related model's primary key or omit
->int('user_id')->belongsTo('author', UserModel::class)       // Defaults to 'id'
->int('user_id')->belongsTo('author', UserModel::class, 'id') // Explicit

// ❌ WRONG - Don't use the foreign key field name
->int('user_id')->belongsTo('author', UserModel::class, 'user_id')
```

---

### Model Methods

#### 4. Getting All Records

```php
// ✅ CORRECT
$records = $model->getAll();

// ❌ WRONG - Method doesn't exist
$records = $model->all();
```

#### 5. Getting Single Record

```php
// ✅ CORRECT
$record = $model->getById($id);

// ❌ WRONG - Method doesn't exist
$record = $model->find($id);
```

#### 6. Counting with Conditions

```php
// ✅ CORRECT
$count = $model->query()->where('status = ?', ['active'])->getTotal();

// ❌ WRONG - Method is getTotal(), not total()
$count = $model->query()->where('status = ?', ['active'])->total();

// ❌ WRONG - Wrong syntax
$count = $model->count(['status' => 'active']);
```

#### 7. Where Clause Syntax

```php
// ✅ CORRECT - SQL syntax with placeholders
$model->query()->where('status = ?', ['active'])
$model->query()->where('price > ? AND stock > ?', [100, 0])

// ❌ WRONG - Array syntax doesn't work
$model->query()->where(['status' => 'active'])

// ❌ WRONG - Method doesn't exist
$model->query()->whereIs('status', 'active')
```

#### 8. Checking Empty Results

```php
// ✅ CORRECT
if ($model->isEmpty()) {
    // No results
}

// ❌ WRONG - Object always exists
if (!$model) {
    // This never works
}

// ❌ WRONG - Method doesn't exist
if ($model->getEmpty()) {
    // Wrong method
}
```

---

### Model Save Pattern (CRITICAL!)

#### 9. Always Use This Save Pattern

```php
// ✅ CORRECT - save() returns ['success' => bool, 'error' => string]
$result = $this->model->save();

if ($result['success']) {
    Response::json([
        'success' => true,
        'message' => 'Saved successfully',
        'reload_table' => 'idTableName'  // Auto-reload table
    ]);
} else {
    Response::json([
        'success' => false,
        'message' => $result['error']
    ]);
}

// ❌ WRONG - Don't use fill() and validate() manually
$this->model->fill($_POST['data'] ?? []);
if (!$this->model->validate()) {
    $errors = \App\MessagesHandler::getErrors();
    return Response::json(['success' => false, 'errors' => $errors]);
}
if ($this->model->save()) { ... }

// ❌ WRONG - Old table reload format
Response::json([
    'table' => ['action' => 'reload', 'id' => 'idTableName']  // DON'T USE THIS
]);
```

---

### View Output Functions (CRITICAL!)

#### 10. All View Functions Start with Underscore

```php
// ✅ CORRECT - With underscore
<?php _ph($html); ?>
<?php _pt('Translated text'); ?>
<?php _p($text); ?>

// Return versions
$text = _r($value);
$text = _rt('Translated');
$text = _rh($html);

// ❌ WRONG - Missing underscore
<?php ph($html); ?>
<?php pt('text'); ?>
<?php echo $html; ?>  // Don't use echo in views
```

**Available functions:**
- `_ph($html)` - Print HTML (already sanitized)
- `_pt($text)` - Print translated text
- `_p($text)` - Print plain text
- `_r($text)` - Return plain text
- `_rt($text)` - Return translated text
- `_rh($html)` - Return HTML

---

### FormBuilder Patterns

#### 11. FormBuilder Fetch Mode

```php
// ✅ CORRECT - Fetch mode pattern
#[RequestAction('edit')]
public function edit() {
    $response = array_merge(
        $this->getCommonData(),
        FormBuilder::create($this->model, $this->page)
            ->activeFetch()              // REQUIRED for fetch mode
            ->asOffcanvas()               // Display type
            ->setTitle('New', 'Edit')     // Dynamic titles
            ->dataListId('idTable')       // Auto-reload table
            ->getResponse()               // Get JSON array
    );
    Response::json($response);            // Return JSON
}

// ❌ WRONG - Missing activeFetch()
FormBuilder::create($this->model, $this->page)
    ->asOffcanvas()  // Won't work without activeFetch()
    ->getForm();

// ❌ WRONG - Wrong return method
FormBuilder::create($this->model, $this->page)
    ->activeFetch()
    ->asOffcanvas()
    ->getForm();  // Should be getResponse() for fetch mode
```

#### 12. Fetch Mode with Hidden Parameters

```php
// ✅ CORRECT - Handle parameters in both GET and POST
#[RequestAction('add-comment')]
public function addComment() {
    // Get parameter from both sources (GET on first load, POST['data'] on submit)
    $post_id = $_POST['data']['post_id'] ?? $_REQUEST['post_id'] ?? 0;

    $response = array_merge(
        $this->getCommonData(),
        FormBuilder::create($this->model, $this->page)
            ->activeFetch()
            ->asOffcanvas()
            ->field('post_id')
                ->value($post_id)
                ->readonly()           // Or use hideField()
            ->dataListId('idTable')
            ->getResponse()
    );
    Response::json($response);
}

// ❌ WRONG - Only checking GET
$post_id = $_GET['post_id'] ?? 0;  // Won't work on form submit

// ❌ WRONG - Only checking REQUEST
$post_id = $_REQUEST['post_id'] ?? 0;  // Won't find in $_POST['data']

// ❌ WRONG - Manual validation (Model handles it)
if (!$post_id) {
    return Response::json(['success' => false, 'error' => 'Missing post_id']);
}
// Don't do manual checks, Model->required() handles validation
```

---

### GET Parameters

#### 13. Getting GET Parameters

```php
// ✅ CORRECT - Sanitize integers
$id = _absint($_GET['id'] ?? 0);
$id = _absint($_REQUEST['id'] ?? 0);  // When handling both GET and POST

// ❌ WRONG - Class doesn't exist
$id = Get::int('id');

// ✅ CORRECT - Sanitize strings
$search = $_GET['search'] ?? '';
$search = filter_var($_GET['search'] ?? '', FILTER_SANITIZE_STRING);
```

---

### TitleBuilder (CRITICAL!)

#### 14. Never Create Custom HTML Buttons

```php
// ✅ CORRECT - Use TitleBuilder
$response['title_btns'] = [
    ['label' => 'Add New', 'link' => '?page='.$this->page.'&action=edit']
];

// For fetch mode (opens in modal/offcanvas):
$response['title_btns'] = [
    ['label' => 'Add New', 'link' => '?page='.$this->page.'&action=edit', 'fetch' => 'post']
];

// ❌ WRONG - Don't create custom HTML buttons
$response['custom_button'] = '<button onclick="fetch(...)">Add New</button>';

// ❌ WRONG - Don't write inline JavaScript
$response['script'] = '<script>function addNew() { fetch(...) }</script>';
```

---

### Table Reload Pattern

#### 15. Use Correct Table Reload Syntax

```php
// ✅ CORRECT - New format
Response::json([
    'success' => true,
    'reload_table' => 'idTableName'
]);

// ❌ WRONG - Old format, don't use
Response::json([
    'success' => true,
    'table' => ['action' => 'reload', 'id' => 'idTableName']
]);
```

---

## 📋 Pre-Flight Checklist

Before writing code, verify:

- [ ] Are you using SharedViews? (MILK_DIR . '/Theme/SharedViews/list_page.php')
- [ ] Are you following Posts or Recipe module patterns?
- [ ] Are all methods from api-reference.md?
- [ ] Are you using `_ph()`, `_pt()`, `_p()` in views (with underscore)?
- [ ] Are you using `getAll()` and `getById()` (not `all()` or `find()`)?
- [ ] Are you using `where('field = ?', [$value])` syntax?
- [ ] Is `belongsTo()` chained immediately after field?
- [ ] Is `addModels()` in the configure() chain?
- [ ] Are you using `$result = $model->save()` pattern?
- [ ] Are you using `activeFetch()` for fetch-based forms?
- [ ] Are you using `getResponse()` + `Response::json()` for fetch mode?
- [ ] Are you using `reload_table` (not `table => ['action'=>'reload']`)?

---

## 🎓 When In Doubt

1. **Check official guides:**
   - [getting-started-post.page.php](../Docs/Pages/Developer/GettingStarted/getting-started-post.page.php)
   - [fetch-modal-crud.page.php](../Docs/Pages/Developer/Advanced/fetch-modal-crud.page.php)

2. **Study working examples:**
   - Standard CRUD: [milkadmin/Modules/Posts/](../Posts/)
   - Fetch-based: [milkadmin_local/Modules/Recipe/](../../../milkadmin_local/Modules/Recipe/)

3. **Verify method exists:**
   - Check [api-reference.md](api-reference.md)

4. **Test your code:**
   ```bash
   php milkadmin/cli.php test-controller "page=yourmodule&action=yourAction"
   ```

---

**Remember:** If a pattern isn't in this document or api-reference.md, it probably doesn't exist. Don't invent methods or patterns.
