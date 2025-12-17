# FormBuilder

## Overview

FormBuilder generates forms automatically from Model definitions. Most form configuration comes from the Model's `configure()` method.

## ⚠️ DEFAULT BEHAVIOR: ALWAYS USE FETCH MODE

**IMPORTANT:** Unless explicitly specified otherwise, ALWAYS generate FormBuilder with fetch/modal mode enabled. This is the modern, recommended approach for modal forms and seamless user experience.

### Fetch Mode (DEFAULT - Use This)

```php
#[RequestAction('edit')]
public function postEdit()
{
    $id = _absint($_POST['data']['id'] ?? $_REQUEST['id'] ?? 0);
    $data = $this->model->getByIdForEdit($id, $_POST['data'] ?? []);

    $form = FormBuilder::create($this->model, $this->page)
        ->activeFetch()  // ⭐ ALWAYS ADD THIS (enables fetch mode)
        ->addFieldsFromObject($data, 'edit')
        ->setActions([
            'save' => [
                'label' => 'Save',
                'type' => 'submit',
                'class' => 'btn btn-primary',
                'action' => function($form_builder, $request) {
                    if (!$form_builder->save($request)) {
                        Response::Json([
                            'success' => false,
                            'msg' => MessagesHandler::errorsToString()
                        ]);
                    } else {
                        // After save, reload the table
                        $this->getTable();
                    }
                }
            ],
            'cancel' => [
                'label' => 'Cancel',
                'type' => 'submit',
                'class' => 'btn btn-secondary ms-2',
                'validate' => false,
                'action' => function($form_builder, $request) {
                    $this->getTable();
                }
            ]
        ])
        ->ActionExecution();

    Response::Json([
        'modal' => [
            'title' => ($id > 0 ? 'Edit' : 'Add') . ' ' . $this->title,
            'body' => $form->getForm(),
            'size' => 'lg'
        ]
    ]);
}
```

### Fetch Mode with Hidden Parameters (e.g., Foreign Keys)

**CRITICAL PATTERN:** When creating forms with foreign keys or hidden parameters passed via URL:

```php
#[RequestAction('create_item')]
public function createItem()
{
    // ⭐ Get parameter from REQUEST (initial load) or POST data (form submit)
    // When activeFetch() is used, form data is sent in $_POST['data']
    $parent_id = _absint($_POST['data']['parent_id'] ?? $_REQUEST['parent_id'] ?? 0);

    // ⭐ Prepare data with foreign key pre-set
    $data = $this->model->getEmpty();
    $data->parent_id = $parent_id;

    // ⚠️ NO manual validation! Model's ->required() handles validation automatically
    $form = FormBuilder::create($this->model, $this->page)
        ->activeFetch()  // ⭐ CRITICAL: Enables fetch mode
        ->addFieldsFromObject($data, 'edit')
        ->hideField('parent_id')  // ⭐ Field hidden but validated by Model
        ->setActions([
            'save' => [
                'label' => 'Save',
                'type' => 'submit',
                'class' => 'btn btn-primary',
                'action' => [$this, 'saveItem']  // Can use method reference
            ],
            'cancel' => [
                'label' => 'Cancel',
                'type' => 'submit',
                'class' => 'btn btn-secondary ms-2',
                'validate' => false,
                'action' => function($form_builder, $request) {
                    Response::json(['modal' => ['action' => 'hide']]);
                }
            ]
        ])
        ->ActionExecution();  // ⭐ CRITICAL: Executes action if present in request

    Response::json([
        'success' => true,
        'modal' => [
            'title' => 'New Item',
            'body' => $form->getForm(),
            'size' => 'md'
        ]
    ]);
}

// Save method (called by form action)
public function saveItem($form_builder, $request)
{
    if (!$form_builder->save($request)) {
        Response::json([
            'success' => false,
            'message' => MessagesHandler::errorsToString()
        ]);
    }

    Response::json([
        'success' => true,
        'message' => 'Item saved successfully',
        'modal' => ['action' => 'hide'],
        'reload_table' => 'idTableItems'
    ]);
}
```

**View button that opens the form:**
```php
<?php
$title = TitleBuilder::create('Items for Parent #' . $parent->id)
    ->addButton('New Item', '?page='.$page.'&action=create_item&parent_id='.$parent->id, 'primary', '', 'get');
_ph($title);
?>
```

**Key points for this pattern:**
1. ✅ Use `$_POST['data']['param'] ?? $_REQUEST['param']` (handles both initial load and form submit)
2. ✅ Create empty object with `getEmpty()` and set foreign key value
3. ✅ Use `->hideField('foreign_key')` to include but not show the field
4. ✅ Use `->activeFetch()` + `->ActionExecution()` for fetch mode
5. ✅ Pass parameter in button URL: `&parent_id=123`
6. ✅ Trust Model validation - NO manual checks needed (`->required()` is validated automatically)
7. ❌ DON'T use only `$_REQUEST` (parameter in $_POST['data'] on form submit with activeFetch)
8. ❌ DON'T use only `$_GET` (won't work on form submit)
9. ❌ DON'T add manual validation like `if (!$id) return error` (Model handles it)
10. ❌ DON'T forget `->hideField()` (user could modify the value)
11. ❌ DON'T forget `->ActionExecution()` (actions won't execute)

### Traditional Mode (Only if explicitly requested)

```php
use Builders\FormBuilder;

// Simple form - traditional page mode
$form = FormBuilder::create($this->model, $this->page)
    // NO activeFetch() - traditional mode
    ->getForm();
```

## Configuration from Model

FormBuilder reads these properties from your Model:

### Field Definitions

```php
// In Model configure()
$rule->string('title', 255)->required()->label('Title')
$rule->text('content')->formType('editor')
$rule->int('status')->default(0)
    ->formType('list')
    ->formParams(['options' => [0 => 'Inactive', 1 => 'Active']])
```

**Properties used:**
- Field type: `string()`, `text()`, `int()`, `datetime()`, etc.
- `->formType()` - HTML input type: `'editor'`, `'password'`, `'hidden'`, `'email'`, `'list'`, `'textarea'`, `'milkSelect'`
- `->formParams([])` - Additional form parameters (e.g., options for selects)
- `->label()` - Field label
- `->required()` / `->nullable()` - Validation rules
- `->default()` - Default value

### Extensions

```php
// In Model
$rule->extensions(['Audit', 'SoftDelete', 'Author'])
```

Extensions automatically add their fields to forms.

## FormBuilder Methods

### Core Methods

**`create($model, $page)`**
Creates FormBuilder instance. Automatically generates fields from Model.

**`getForm()`**
Returns HTML string of the form.

**`render()`**
Alternative to `getForm()`. Returns HTML string.

### Field Management

**`addField($name, $type, $options)`**
Manually add a field not in Model.
```php
->addField('username', 'string', ['label' => 'Username'])
->addField('password', 'password', ['label' => 'Password'])
```

**`addFieldsFromObject($data, $action)`**
Load field values from data object. Used for editing existing records.
```php
$data = $this->model->getByIdForEdit($id, $_POST['data'] ?? []);
->addFieldsFromObject($data, 'edit')
```

**`removeField($name)`**
Remove a field from the form.
```php
->removeField('created_at')
->removeField('updated_at')
```

### AJAX & Actions

**`activeFetch()`**
Enable AJAX mode for fetch-based submissions. Required for modal/offcanvas forms.
```php
->activeFetch()
```

**`setActions(array $actions)`**
Define form buttons and their handlers.
```php
->setActions([
    'save' => [
        'label' => 'Save',
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'action' => [$this, 'saveItem']
    ],
    'cancel' => [
        'label' => 'Cancel',
        'type' => 'button',
        'class' => 'btn btn-secondary',
        'attributes' => [
            'data-bs-dismiss' => 'modal'
        ]
    ],
    'delete' => [
        'label' => 'Delete',
        'type' => 'submit',
        'class' => 'btn btn-danger',
        'action' => [$this, 'deleteItem'],
        'validate' => false,
        'showIf' => ['id', 'not_empty', 0]
    ]
])
```

**`ActionExecution()`**
Execute actions immediately and return FormBuilder. Used when form needs to process action in same request.
```php
->setActions([...])->ActionExecution()
```

### Extensions

**`extensions(array $extensions)`**
Load extensions for the form (must also be loaded in Model).
```php
->extensions(['SoftDelete', 'Author'])
```

### URLs

**`urlSuccess($url)`**
Set redirect URL on successful submission.
```php
->urlSuccess('?page=posts&action=list')
```

**`urlError($url)`**
Set redirect URL on error.
```php
->urlError('?page=posts&action=edit&id=5')
```

## Advanced Field Management

All methods from `FormFieldManagementTrait`:

**`removeField(string $name)`**
Remove a field from the form.

**`showFieldWhen(string $field_name, string $toggle_field, string $toggle_value)`**
Set conditional visibility for a field. Field is shown only when toggle_field equals toggle_value.

**`showFieldsWhen(array $field_names, string $toggle_field, string $toggle_value)`**
Set conditional visibility for multiple fields at once.

**`removeFieldCondition(string $field_name)`**
Remove conditional visibility from a field.

**`fieldOrder(array $order)`**
Set the display order of fields.

**`addFieldsFromObject(object $object, string $context, array $values)`**
Add fields from object definition with values.

**`addField(string $field_name, string $type, array $options, string $position_before)`**
Add a single field at specific position.

**`modifyField(string $field_name, array $options, string $position_before)`**
Modify an existing field by merging options. Optionally reposition it before another field.

**`addHtmlBeforeFields(string $html)`**
Add custom HTML before all fields.

**`addHtmlAfterFields(string $html)`**
Add custom HTML after all fields.

**`addHtmlBeforeSubmit(string $html)`**
Add custom HTML before submit button.

**`addHtml(string $html, string $position_before)`**
Add custom HTML at specific position in the form.

**`setActions(array $actions)`**
Set form actions (replaces existing actions).

**`addActions(array $actions)`**
Add actions to existing ones.

**`addStandardActions(bool $include_delete, ?string $cancel_link)`**
Quick helper to add standard save/cancel/delete buttons.

## Usage Patterns

**⚠️ DEFAULT: Always use "AJAX Form with Modal" pattern unless explicitly requested otherwise.**

### AJAX Form with Modal (DEFAULT - Use This)

```php
#[RequestAction('edit')]
public function editPage()
{
    $id = _absint($_POST['data']['id'] ?? $_REQUEST['id'] ?? 0);
    $data = $this->model->getByIdForEdit($id, $_POST['data'] ?? []);

    $form = FormBuilder::create($this->model, $this->page)
        ->activeFetch()
        ->addFieldsFromObject($data, 'edit')
        ->setActions([
            'save' => [
                'label' => 'Save',
                'type' => 'submit',
                'class' => 'btn btn-primary',
                'action' => [$this, 'saveItem']
            ],
            'cancel' => [
                'label' => 'Cancel',
                'type' => 'button',
                'class' => 'btn btn-secondary',
                'attributes' => ['data-bs-dismiss' => 'modal']
            ]
        ])
        ->ActionExecution();

    Response::json([
        'success' => true,
        'modal' => [
            'title' => 'Edit Item',
            'body' => $form->getForm()
        ]
    ]);
}
```

### Save Action Handler (DEFAULT Pattern)

**IMPORTANT:** After save/cancel, ALWAYS call the method that returns the updated table/list (e.g., `$this->getTable()`).

```php
public function saveItem($formBuilder, $request)
{
    if (!$formBuilder->save($request)) {
        Response::Json([
            'success' => false,
            'msg' => MessagesHandler::errorsToString()
        ]);
    } else {
        // ⭐ CRITICAL: Call getTable() to reload the table after save
        $this->getTable();
    }
}
```

### Alternative: Manual Save Handler

```php
public function saveItem($formBuilder, $request)
{
    // ✅ CORRECT: save() handles fill() and validate() internally
    // Returns ['success' => bool, 'error' => string]
    $result = $this->model->save();

    if ($result['success']) {
        // After save, reload the table
        $this->getTable();
    } else {
        return [
            'success' => false,
            'message' => $result['error']
        ];
    }
}

// ❌ WRONG - Don't use fill() and validate() manually:
/*
public function saveItem($formBuilder, $request)
{
    $this->model->fill($request);

    if (!$this->model->validate()) {
        return ['success' => false, 'message' => 'Validation failed'];
    }

    if (!$this->model->save()) {
        return ['success' => false, 'message' => $this->model->getLastError()];
    }

    $this->getTable();
}
*/
```

### Simple Form - Traditional (Only if explicitly requested)

```php
#[RequestAction('edit')]
public function editPage()
{
    $response = $this->getCommonData();

    $response['form'] = FormBuilder::create($this->model, $this->page)
        // NO activeFetch() - traditional mode
        ->getForm();

    Response::render(__DIR__ . '/Views/edit_page.php', $response);
}
```

## Fetch Mode Quick Reference

### Key Concepts

1. **Always use `->activeFetch()`** on FormBuilder (unless traditional mode is explicitly requested)
2. **Always return `Response::Json()`** with modal configuration
3. **After save/cancel, call `$this->getTable()`** to reload the table/list
4. **Use `data-fetch="post"`** on links/buttons in views

### Complete Fetch-Based CRUD Pattern

```php
// Controller
class ItemsController extends AbstractController
{
    #[RequestAction('get-table')]
    public function getTable()
    {
        $table = TableBuilder::create($this->model, 'idTableItems')
            ->activeFetch()
            ->asLink('title', '?page='.$this->page.'&action=edit&id=%id%')
            ->setRequestAction('get-table')
            ->setDefaultActions();

        $response = [];
        if ($table->isInsideRequest()) {
            $response['html'] = $table->render();
        } else {
            $response['modal'] = [
                'title' => $this->title,
                'body' => $table->render(),
                'size' => 'xl'
            ];
        }
        Response::Json($response);
    }

    #[RequestAction('edit')]
    public function postEdit()
    {
        $id = _absint($_POST['data']['id'] ?? $_REQUEST['id'] ?? 0);
        $data = $this->model->getByIdForEdit($id, $_POST['data'] ?? []);

        $form = FormBuilder::create($this->model, $this->page)
            ->activeFetch()
            ->addFieldsFromObject($data, 'edit')
            ->setActions([
                'save' => [
                    'label' => 'Save',
                    'type' => 'submit',
                    'class' => 'btn btn-primary',
                    'action' => function($fb, $req) {
                        if (!$fb->save($req)) {
                            Response::Json(['success' => false, 'msg' => MessagesHandler::errorsToString()]);
                        } else {
                            $this->getTable();
                        }
                    }
                ],
                'cancel' => [
                    'label' => 'Cancel',
                    'type' => 'submit',
                    'class' => 'btn btn-secondary ms-2',
                    'validate' => false,
                    'action' => function($fb, $req) { $this->getTable(); }
                ]
            ])
            ->ActionExecution();

        Response::Json([
            'modal' => [
                'title' => ($id > 0 ? 'Edit' : 'Add') . ' ' . $this->title,
                'body' => $form->getForm(),
                'size' => 'lg'
            ]
        ]);
    }

    #[RequestAction('home')]
    public function listPage()
    {
        $response = $this->getCommonData();
        Response::render(__DIR__ . '/Views/list_page.php', $response);
    }
}
```

```php
// View: list_page.php
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3><?php echo $title; ?></h3>
        <a href="?page=<?php echo $page; ?>&action=edit" class="btn btn-primary" data-fetch="post">
            <i class="bi bi-plus-circle"></i> Add New
        </a>
    </div>
    <div class="card-body">
        <a id="tableContainer" data-fetch="post" href="?page=<?php echo $page; ?>&action=get-table">ShowList</a>
    </div>
</div>
```

## Advanced Topics

For advanced FormBuilder features, see `create-builder-form-advanced.md`:

- **Form Containers** - Organize fields in Bootstrap grid columns
- **Autocomplete Search** - Add searchable relationship fields for large datasets
- **Custom Validation** - Client-side and server-side validation
- **Pre-loaded Select with Relationships** - Dropdown fields with relationship data

Only read the advanced topics if you need these specific features.

## See Also

- **Advanced features**: `create-builder-form-advanced.md`
- **Model configuration**: `create-model.md`
- **Controller patterns**: `create-view.md`
- **Field types**: See Model RuleBuilder documentation
