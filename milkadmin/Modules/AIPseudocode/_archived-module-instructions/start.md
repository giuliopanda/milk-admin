# Module Generation - Start

Generate Module, Model, Controller and Views from pseudocode description.

## First: Create a step-by-step project
1. Read what's already been created about the module
1. Read the start module instructions and then create a development project, indicating which files and methods you need to develop/modify.
2. Read the examples and generate the changes.
3. Don't invent anything; check the classes and methods you use.

> **IMPORTANT:** Develop ONLY what is requested. Do not add extra features, refactoring, or improvements beyond the specific requirements.

## ⚠️ CRITICAL PATTERNS - READ FIRST

### Model Save Pattern
**ALWAYS use this pattern when saving:**

```php
// ✅ CORRECT: save() returns ['success' => bool, 'error' => string]
$result = $this->model->save();

if ($result['success']) {
    Response::json([
        'success' => true,
        'message' => 'Saved successfully',
        'modal' => ['action' => 'hide'],
        'reload_table' => 'idTableName'  // NOT 'table' => ['action'=>'reload', 'id'=>'...']
    ]);
} else {
    Response::json([
        'success' => false,
        'message' => $result['error']
    ]);
}
```

**❌ NEVER use these patterns:**
```php
// ❌ WRONG - Don't use fill() and validate() manually
$this->model->fill($_POST['data'] ?? []);
if (!$this->model->validate()) {
    $errors = \App\MessagesHandler::getErrors();
    return Response::json(['success' => false, 'errors' => $errors]);
}
if ($this->model->save()) { ... }

// ❌ WRONG - Old table reload format
'table' => ['action'=>'reload', 'id'=>'idTableName']
```

### Table Reload Pattern
**ALWAYS use:**
- ✅ `'reload_table' => 'idTableName'`

**NEVER use:**
- ❌ `'table' => ['action'=>'reload', 'id'=>'idTableName']`

## AI Processing

1. Read start.md for instructions
2. Parse module name, access, menus
3. Check if table exists
4. Generate Module and Model files
5. Generate Controller and Views (if needed)
6. Use examples/ folder for reference
7. **Test the generated controller** with `test-controller` command (see "Testing Controllers" section)

## Workflow

1. Read pseudocode file in module folder
2. Extract: module name, access, menus, table name, fields
3. Check table existence: `php milkadmin/cli.php sql-table-structure {table_name}`
4. Generate Module file using `create-module.md` template
5. Generate Model file using `create-model.md` guidelines
6. Generate Controller and Views using `create-controller.md` (if views/actions specified)
7. Apply configurations from `module-configuration.md`
8. **Test controller actions:** `php milkadmin/cli.php test-controller "page={module}&action={action}"`

## Key Files
Start by reading only the necessary files among these:

- **create-module.md** - Module and Model templates
- **create-controller.md** - Controller and Views structure
- **module-configuration.md** - All Module configure() options
- **create-model.md** - All Model RuleBuilder methods, flow, and PHP attributes
- **model-queries.md** - Query methods, CRUD operations, data retrieval

After you have read the necessary files see if there are any necessary examples to read:

- **examples/** - Real code snippets to reference

## Implementation Steps Summary

For detailed instructions, read the specific documentation files listed in "Key Files" above:

1. **Module & Model**: See [create-module.md](create-module.md) and [create-model.md](create-model.md)
   - Namespace determination, module configuration, table structure
   - RuleBuilder methods, extensions, and PHP attributes

2. **Controller & Views** (optional): See [create-controller.md](create-controller.md) and [create-view.md](create-view.md)
   - Controller with `#[RequestAction]` routing
   - View templates and Response methods

3. **Builders** (optional): See builder documentation files
   - FormBuilder for forms ([create-builder-form.md](create-builder-form.md))
   - TableBuilder/ListBuilder for data display ([create-builder-table.md](create-builder-table.md), [create-builder-list.md](create-builder-list.md))
   - CalendarBuilder for calendars ([create-builder-calendar.md](create-builder-calendar.md))

## Decision Logic

**Table exists + no fields in pseudocode:**
- Use table structure only

**Table NOT exists + fields in pseudocode:**
- Use pseudocode fields only

**Table exists + fields in pseudocode + match:**
- Combine both sources

**Table exists + fields in pseudocode + conflict:**
- Stop, ask user for clarification

## Examples Available

See `examples/` folder for real working code:
- module-basic.php - Module patterns
- model-basic-fields.php - Field types
- model-list-enum.php - Lists and enums
- model-extensions.php - Extensions usage
- model-complete.php - Complete models with PHP attributes



## Quick Start - Create a New Page

Follow these steps to create a new page in an existing module:

1. **Create or open the Controller** - Use the module's Controller class (e.g., `TestListController.php`)
2. **Add a new method** - Create a method with `#[RequestAction('pagename')]` attribute
3. **Create the view template** - Add a new PHP template in the module's `Views/` folder (e.g., `pagename_page.php`)
4. **Render the template** - Use `Response::render(__DIR__ . '/Views/pagename_page.php', $data)` in your method

**Read these for more details:**
- [create-controller.md](create-controller.md) - Controller structure and routing
- [create-view.md](create-view.md) - View templates and Response methods


## Auto-Generated

Never ask user for:
- Version (use current date YYMMDD)
- Icons (use appropriate Bootstrap icons)
- Default values (see create-model.md)
- Table prefix (auto `#__`)

## Installation and Updates

**CRITICAL:** After generating or modifying Module and Model files, you MUST run CLI commands to create/update the database table:

### First Installation
```bash
php milkadmin/cli.php {modulename}:install
```

### Update Existing Table
```bash
php milkadmin/cli.php {modulename}:update
```

**Example for TestList module:**
```bash
php milkadmin/cli.php testlist:install
```

**When to use:**
- `:install` - First time creating the module (creates table from Model)
- `:update` - After modifying Model fields (alters existing table)

Without running these commands, the table will NOT exist in the database and the module will fail.

## Testing Controllers

**CRITICAL:** After generating or modifying controller code, **ALWAYS test it** using the CLI test command to verify it works correctly.

### Test Command Syntax

```bash
php milkadmin/cli.php test-controller "page={modulename}&action={actionname}" [--get="params"] [--post="params"]
```

### Examples

```bash
# Test home/list action
php milkadmin/cli.php test-controller "page=lessons&action=home"

# Test view action with GET parameter
php milkadmin/cli.php test-controller "page=lessons&action=view" --get="id=123"

# Test save action with POST data
php milkadmin/cli.php test-controller "page=lessons&action=saveEnrollment" --post="student_name=John&course_id=5"

# Test edit with both GET and POST
php milkadmin/cli.php test-controller "page=lessons&action=edit" --get="id=5" --post="title=Updated Title&description=New description"
```

### Testing Features

- **Automatic admin permissions** - Command bypasses all permission checks
- **Output capture** - Shows the complete controller response (HTML or JSON)
- **Error display** - Catches and displays exceptions with stack traces
- **Parameter simulation** - Simulates both GET and POST parameters

### When to Test

1. ✅ **After creating a new controller method** - Verify the action works
2. ✅ **After modifying save/update logic** - Check data is saved correctly
3. ✅ **After changing validation rules** - Test error handling
4. ✅ **Before marking task as complete** - Final verification

**Important:** If the test shows errors, fix them and test again. Don't consider a task complete until the controller test passes successfully.

## Remember

- All fields nullable by default
- String default length: 255
- Decimal default: 10,2
- Use title() for main display field
- Extensions auto-add fields (don't duplicate)
- **ALWAYS run CLI install/update after generating Module and Model**
- **ALWAYS test controllers** with `test-controller` command after creating/modifying controller methods
- **Controller and Views are optional** - create only if views/actions are specified in pseudocode
- **Views folder structure:** `{ModuleName}/Views/{action}_page.php`
- **Views namespace:** `{Namespace}\{ModuleName}\Views`
- **Response methods:** Use `Response::render()` for HTML, `Response::json()` for AJAX

## Critical Mistakes to Avoid ⚠️

### Module and Model Configuration

1. **addModels() placement:**
   - ✅ Use `$rule->page('name')->addModels([...])->version()` in configure()
   - ❌ DON'T create separate `addModels()` method

2. **belongsTo() placement:**
   - ✅ Chain immediately: `->int('id_corso')->belongsTo('corso', Model::class)->required()`
   - ❌ DON'T put at end of chain (applies to wrong field!)

3. **belongsTo() third parameter:**
   - ✅ Use related model's primary key or omit (defaults to 'id')
   - ❌ DON'T use the foreign key field name

### Model Methods (IMPORTANT!)

4. **Getting data:**
   - ✅ Use `$model->getAll()` to get all records
   - ❌ DON'T use `$model->all()` (doesn't exist!)

5. **Getting by ID:**
   - ✅ Use `$model->getById($id)` to get single record
   - ❌ DON'T use `$model->find($id)` (doesn't exist!)

6. **Counting with conditions:**
   - ✅ Use `$model->query()->where('field = ?', [$value])->getTotal()`
   - ❌ DON'T use `$model->query()->where('field = ?', [$value])->total()` (method is getTotal()!)
   - ❌ DON'T use `$model->count(['field' => $value])` (wrong syntax!)

### View Output Functions (CRITICAL!)

7. **All view functions start with underscore:**
   - ✅ Use `_ph($html)` to print HTML (NOT ph()!)
   - ✅ Use `_pt('text')` to print translated text (NOT pt()!)
   - ✅ Use `_p($text)` to print plain text
   - ❌ DON'T use `echo` or `print` in views
   - ❌ DON'T use `ph()`, `pt()` without underscore

**Return functions:** `_r()`, `_rt()`, `_rh()` (all with underscore)

### TitleBuilder and Buttons (CRITICAL!)

8. **NEVER create custom HTML buttons in views:**
   - ✅ Use `TitleBuilder::create($title)->addButton('Add New', '?page='.$page.'&action=edit', 'primary')`
   - ✅ Use fetch parameter for modals: `->addButton('Add New', '?page='.$page.'&action=edit', 'primary', '', 'get')`
   - ❌ DON'T create `<button onclick="fetch(...)">` in views
   - ❌ DON'T write inline JavaScript fetch calls
   - ❌ DON'T create custom HTML buttons for "Add New" or similar actions

**See create-view.md for complete TitleBuilder documentation and examples.**

### FormBuilder Fetch Mode with Parameters (CRITICAL!)

9. **When passing parameters (foreign keys) to forms opened via buttons:**
   - ✅ Use `$_POST['data']['param'] ?? $_REQUEST['param']` (handles both initial load and form submit)
   - ✅ With `activeFetch()`, form data is in `$_POST['data']` not `$_REQUEST`
   - ✅ Use `$data = $model->getEmpty(); $data->foreign_key = $value;` to set foreign key
   - ✅ Use `->activeFetch()` to enable fetch mode
   - ✅ Use `->hideField('foreign_key')` to hide but include the field
   - ✅ Use `->ActionExecution()` to execute form actions
   - ✅ Trust Model validation - NO manual checks needed (`->required()` is validated automatically)
   - ❌ DON'T use only `$_GET` or `$_REQUEST` (parameter in `$_POST['data']` on submit)
   - ❌ DON'T add manual validation like `if (!$id) return error` (Model handles it)
   - ❌ DON'T forget `->activeFetch()` (form won't work with fetch)
   - ❌ DON'T forget `->ActionExecution()` (save action won't execute)

**See create-builder-form.md "Fetch Mode with Hidden Parameters" section for complete example.**

### Query and Parameters (CRITICAL!)

10. **Using where() correctly:**
   - ✅ Use `->where('field = ?', [$value])` with SQL syntax
   - ❌ DON'T use `->where(['field' => $value])` (array syntax doesn't work!)
   - ❌ DON'T use `->whereIs('field', $value)` (method doesn't exist!)

11. **Getting GET parameters:**
   - ✅ Use `_absint($_GET['id'] ?? 0)` for integers
   - ✅ Use `_absint($_REQUEST['id'] ?? 0)` when handling both GET and POST (e.g., form with parameters)
   - ❌ DON'T use `Get::int('id')` (class doesn't exist!)

12. **Checking if result is empty:**
   - ✅ Use `if ($model->isEmpty())` to check for empty results
   - ❌ DON'T use `if (!$model)` (object always exists!)
   - ❌ DON'T use `$model->getEmpty()` (method is isEmpty()!)

13. **Getting single record with conditions:**
   - ✅ Use `$model->getById($id)` for simple ID lookup
   - ✅ Use `$model->where("field = ?", [$value])->getRow()` for custom conditions
   - ❌ DON'T use `$model->find($id)` (doesn't exist!)

See `model-queries.md` for complete Model methods reference.
See `create-view.md` for complete View output functions reference.
See `create-model.md` and `create-module.md` for detailed examples and common mistakes.
