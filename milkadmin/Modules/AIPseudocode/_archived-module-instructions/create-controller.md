# Create Controller and Views

Generate Controller and Views files for a module.

## Structure

```
Modules/{ModuleName}/
├── {ModuleName}Module.php
├── {ModuleName}Model.php
├── {ModuleName}Controller.php
└── Views/
    ├── list_page.php
    ├── edit_page.php
    └── {custom}_page.php
```

## Controller Template

```php
<?php
namespace {Namespace}\{ModuleName};

use App\Abstracts\AbstractController;
use App\Response;
use App\Attributes\RequestAction;
use App\Attributes\AccessLevel;
use Builders\{FormBuilder, TableBuilder};

class {ModuleName}Controller extends AbstractController
{
    /**
     * Home action - Default page
     */
    #[RequestAction('home')]
    public function helloWorld()
    {
        $response = ['html'=>'hello world'];
        Response::render(__DIR__ . '/Views/list_page.php', $response);
    }

}
```

## Controller Components

### 1. Namespace and Imports

```php
namespace {Namespace}\{ModuleName};

use App\Abstracts\AbstractController;
use App\Response;
use App\Attributes\RequestAction;
use App\Attributes\AccessLevel;  // Optional
use Builders\{FormBuilder, TableBuilder, CalendarBuilder, SearchBuilder};
```

**Namespace determination:**
- `milkadmin/Modules/{ModuleName}/` → `namespace Modules\{ModuleName}`
- `milkadmin_local/Modules/{ModuleName}/` → `namespace Local\Modules\{ModuleName}`

### 2. PHP Attributes

#### #[RequestAction]
Maps URL action parameter to controller method.

```php
#[RequestAction('home')]
public function listPage() {
    // Called when: ?page=modulename&action=home
}

#[RequestAction('edit')]
public function editItem() {
    // Called when: ?page=modulename&action=edit
}
```

#### #[AccessLevel] (Optional)
Overrides module-level access for specific methods.

```php
#[AccessLevel('public')]
#[RequestAction('view')]
public function viewItem() {
    // Public access even if module requires 'registered'
}

#[AccessLevel('admin')]
#[RequestAction('delete')]
public function deleteItem() {
    // Only admins can access
}
```

**Access levels:**
- `'public'` - Anyone can access
- `'registered'` - Logged-in users only
- `'admin'` - Administrators only
- `'authorized'` - Requires specific permission

### 3. Inherited Properties

From `AbstractController`:

```php
$this->page      // Module page name (e.g., 'posts')
$this->title     // Module title (e.g., 'Posts')
$this->model     // Model instance
$this->module    // Module instance
```

### 4. Helper Methods

```php
// Get common data (page, title)
$response = $this->getCommonData();

// Check access permission
if (!$this->access()) {
    Response::denyAccess();
}

// Get additional models
$commentModel = $this->getAdditionalModels('Comment');
```

## Response Methods

Controllers use the `Response` class to return data to the browser. Common methods:

```php
// Render HTML view (auto-detects JSON for AJAX)
Response::render(__DIR__ . '/Views/list_page.php', $response);

// Return JSON response
Response::json(['success' => true, 'message' => 'Saved']);

// Access control
Response::denyAccess();

// CSV export
Response::csv($data, 'filename');
```

### ⚠️ CRITICAL: Model Save and Table Reload Patterns

**ALWAYS use this pattern when saving:**

```php
// ✅ CORRECT: save() returns ['success' => bool, 'error' => string]
$result = $this->model->save();

if ($result['success']) {
    Response::json([
        'success' => true,
        'message' => 'Saved successfully',
        'modal' => ['action' => 'hide'],
        'reload_table' => 'idTableName'  // ✅ CORRECT format
    ]);
} else {
    Response::json([
        'success' => false,
        'message' => $result['error']
    ]);
}
```

**❌ NEVER use these patterns:**
- `$model->fill($_POST['data']); $model->validate(); $model->save();` - save() handles this internally
- `'table' => ['action'=>'reload', 'id'=>'...']` - Use `'reload_table' => 'idTableName'` instead

**For complete Response documentation and JSON patterns** (modal, offcanvas, table reload, etc.), see [create-view.md - Response Methods](create-view.md#response-methods)

## See Also

- **View templates**: [create-view.md](create-view.md) - View structure and Response methods
- **Model queries**: [model-queries.md](model-queries.md) - Query and CRUD operations
- **Form builders**: [create-builder-form.md](create-builder-form.md) - Creating forms
- **Table/List builders**: [create-builder-table.md](create-builder-table.md), [create-builder-list.md](create-builder-list.md)

