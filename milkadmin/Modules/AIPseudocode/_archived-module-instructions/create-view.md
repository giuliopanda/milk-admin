# Views and Controllers

## Views Structure

### Basic View Template

All views must follow this structure:

```php
<?php
namespace {Namespace}\{ModuleName}\Views;

use Builders\TitleBuilder;

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="card">
    <div class="card-header">
        <?php _ph(TitleBuilder::create($title)); ?>
    </div>
    <div class="card-body">
        <!-- Content here -->
    </div>
</div>
```

**Examples:** See [examples/list_page.php](examples/list_page.php) and [examples/edit_page.php](examples/edit_page.php)

### View Requirements

- **Location**: `{ModuleName}/Views/`
- **Naming**: Files end with `_page.php` (e.g., `list_page.php`, `edit_page.php`)
- **Namespace**: Must match module structure
  - `Modules\{ModuleName}\Views` (for milkadmin)
  - `Local\Modules\{ModuleName}\Views` (for milkadmin_local)
- **Security**: Must include `!defined('MILK_DIR') && die();`
- **Variables**: Receive data from `Response::render()` second parameter

### View Output Functions

**NEVER use `echo` or `print` in views.** Use these sanitization functions instead:

**CRITICAL: ALL functions start with underscore `_`**

**Output functions (print directly):**
- **`_p($text)`** - Print non-HTML text (for attributes, plain text)
- **`_pt($text)`** - Print text with translation support (NOT pt()!)
- **`_ph($html)`** - Print HTML content (already sanitized) (NOT ph()!)

**Return functions (return sanitized string):**
- **`_r($text)`** - Return non-HTML text (sanitized)
- **`_rt($text)`** - Return translated text (sanitized)
- **`_rh($html)`** - Return HTML content (sanitized)

**Examples:**
```php
<!-- Attribute values -->
<div class="<?php _p($className); ?>">

<!-- Translated text -->
<h1><?php _pt('Welcome'); ?></h1>

<!-- HTML content -->
<div><?php _ph($htmlContent); ?></div>

<!-- Return for concatenation -->
$title = _rt('Hello') . ' ' . _r($name);
```

**Common Mistakes:**
```php
<!-- ❌ WRONG - missing underscore! -->
<?php ph($html); ?>
<?php pt('Text'); ?>

<!-- ✅ CORRECT - with underscore -->
<?php _ph($html); ?>
<?php _pt('Text'); ?>
```

**Builder HTML variables:**
Tables and forms from builders are passed as `$response['html']` in the controller, and available as `$html` in the view (unless specified differently).

```php
// Controller
$response['html'] = TableBuilder::create(...)->render();

// View
<div><?php _ph($html); ?></div>
```

### TitleBuilder - Page Headers with Buttons

**CRITICAL: NEVER create custom HTML buttons in views.** Always use TitleBuilder methods for buttons and actions.

#### Basic Title

```php
<?php _ph(TitleBuilder::create($title)); ?>
```

#### Title with "Add New" Button (Standard Pattern)

**✅ CORRECT - Use addButton() method:**
```php
<?php
$title = TitleBuilder::create($title)
    ->addButton('Add New', '?page='.$page.'&action='.$link_action_edit, 'primary');
_ph($title);
?>
```

**✅ CORRECT - With fetch mode (opens modal):**
```php
<?php
$title = TitleBuilder::create($title)
    ->addButton('Add New', '?page='.$page.'&action='.$link_action_edit, 'primary', '', 'get');
_ph($title->addSearch($table_id, 'Search...', 'Search'));
?>
```

**❌ WRONG - Never use inline onclick with fetch:**
```php
<!-- DON'T DO THIS! -->
<button onclick="fetch('?page=...').then(...)">Add New</button>
```

#### addButton() Parameters

```php
->addButton($title, $link, $color = 'primary', $class = '', $fetch = null)
```

- **$title** - Button text (e.g., 'Add New', 'Export', 'Nuovo Iscritto')
- **$link** - URL with action (e.g., `'?page='.$page.'&action=edit'`)
- **$color** - Bootstrap color: 'primary', 'secondary', 'success', 'danger', 'warning', 'info'
- **$class** - Additional CSS classes
- **$fetch** - Fetch mode: `'get'` or `'post'` (transforms link into fetch request, opens modal automatically)

#### Title with Multiple Buttons

```php
<?php
$title = TitleBuilder::create('Products')
    ->addButton('Add New', '?page='.$page.'&action=edit', 'primary', '', 'get')
    ->addButton('Export', '?page='.$page.'&action=export', 'secondary')
    ->addButton('Import', '?page='.$page.'&action=import', 'info');
_ph($title);
?>
```

#### Title with Search

```php
<?php
$title = TitleBuilder::create($title)
    ->addButton('Add New', '?page='.$page.'&action='.$link_action_edit, 'primary');
_ph($title->addSearch($table_id, 'Search...', 'Search'));
?>
```

#### Title with Custom Description

```php
<?php
$title = TitleBuilder::create('Users Management')
    ->description('Manage system users and permissions')
    ->addButton('Add New User', '?page='.$page.'&action=edit', 'primary');
_ph($title);
?>
```

#### Common Patterns

**List page with new button:**
```php
<div class="card">
    <div class="card-header">
    <?php
    $title = TitleBuilder::create($title)
        ->addButton('Add New', '?page='.$page.'&action='.$link_action_edit, 'primary');
    _ph(isset($search_html) ? $title->addRightContent($search_html) : $title->addSearch($table_id, 'Search...', 'Search'));
    ?>
    </div>
    <div class="card-body">
        <?php _ph($html); ?>
    </div>
</div>
```

**Detail page with back button:**
```php
<div class="card">
    <div class="card-header">
    <?php
    $title = TitleBuilder::create('Item Details')
        ->addButton('Back to List', '?page='.$page, 'secondary')
        ->addButton('Edit', '?page='.$page.'&action=edit&id='.$item->id, 'primary');
    _ph($title);
    ?>
    </div>
    <div class="card-body">
        <!-- Content -->
    </div>
</div>
```

### Common View Files

- `list_page.php` - List view with TableBuilder
- `edit_page.php` - Edit form with FormBuilder
- `calendar_page.php` - Calendar view with CalendarBuilder
- `{custom}_page.php` - Custom views

## Controller Structure

### AbstractController

All controllers extend `AbstractController` which provides:

- **`$this->page`** - Current page/module name
- **`$this->title`** - Module title
- **`$this->model`** - Primary model instance
- **`$this->module`** - Module instance reference
- **`getCommonData()`** - Returns `['page' => ..., 'title' => ...]`

### Request Actions

Use `#[RequestAction]` attribute to map URL actions to methods:

```php
#[RequestAction('home')]    // Default action
public function listPage() { }

#[RequestAction('edit')]
public function editPage() { }
```

When user navigates to `?page=yourmodule&action=edit`, the `editPage()` method is called.

## Controller Patterns

### List Page Pattern

**Controller:**

```php
#[RequestAction('home')]
public function listPage()
{
    $response = $this->getCommonData();

    // Optional: Add SearchBuilder HTML
    $response['search_html'] = SearchBuilder::create(...)->render([], true);

    // Add list/table specific data
    $response['link_action_edit'] = 'edit';
    $response['table_id'] = 'idTableItems';

    // Generate table/list (always use getResponse())
    $response = array_merge($response, $this->getTableResponse());

    Response::render(__DIR__ . '/Views/list_page.php', $response);
}

private function getTableResponse()
{
    return TableBuilder::create($this->model, 'idTableItems')
        ->field('title')
        ->setDefaultActions()
        ->getResponse();  // Always use getResponse() for lists
}
```

**View variables received:**
- `$page` - Current page name
- `$title` - Page title
- `$html` - Table/list HTML
- `$table_id` - Table identifier
- `$search_html` - (optional) Search form HTML
- `$link_action_edit` - Action name for edit button

**Example:** See [examples/Controller-listPage.php](examples/Controller-listPage.php)

**Builder details:** See `create-builder-list.md` documentation

### Edit Page Pattern (Simple)

**Controller:**

```php
#[RequestAction('edit')]
public function editPage()
{
    $response = $this->getCommonData();

    $response['form'] = FormBuilder::create($this->model, $this->page)
        ->getForm();

    Response::render(__DIR__ . '/Views/edit_page.php', $response);
}
```

**View variables received:**
- `$page` - Current page name
- `$title` - Page title
- `$form` - Form HTML

**Example:** See [examples/Controller-editPage.php](examples/Controller-editPage.php)

**Builder details:** See `create-builder-form.md` documentation

## Response Methods

The `Response` class provides several methods to return data to the browser:

### Response::render()

Renders a view file with variables:

```php
Response::render(__DIR__ . '/Views/list_page.php', [
    'page' => $this->page,
    'title' => $this->title,
    'html' => $tableHtml
]);
```

- Automatically detects AJAX requests and returns JSON if needed
- Terminates execution (calls `exit`)
- First parameter: absolute path to view file
- Second parameter: array of variables to pass to view

### Response::json()

Returns JSON response for AJAX requests:

```php
Response::json([
    'success' => true,
    'message' => 'Saved successfully',
    'reload_table' => 'idTableItems'
]);
```

**Special keys:**
- `modal` - Controls modal dialogs
- `offcanvas_end` - Controls offcanvas panels
- `reload_table` - Triggers table reload (format: `'reload_table' => 'idTableName'`)
- `calendar` - Triggers calendar reload

#### JSON Response Patterns

**Modal with Form:**
```php
Response::json([
    'success' => true,
    'modal' => [
        'title' => 'Edit Item',
        'body' => $formHtml,
        'size' => 'lg'  // sm, md, lg, xl
    ]
]);
```

**Offcanvas with Form:**
```php
Response::json([
    'success' => true,
    'offcanvas_end' => [
        'title' => 'Add Comment',
        'body' => $formHtml
    ]
]);
```

**Table Reload:**
```php
Response::json([
    'success' => true,
    'message' => 'Saved',
    'reload_table' => 'idTableName',
    'reload_params' => ['post_id' => $id]
]);
```

**Calendar Reload:**
```php
Response::json([
    'success' => true,
    'calendar' => [
        'id' => 'calendar_events',
        'action' => 'reload'
    ]
]);
```

**Close Modal + Reload:**
```php
Response::json([
    'success' => true,
    'message' => 'Deleted',
    'modal' => ['action' => 'hide'],
    'reload_table' => 'idTableName'
]);
```

### Response::themePage()

Renders theme-based public pages (for non-admin pages).

### Other Response Methods

```php
// Deny access
Response::denyAccess();

// Show error message
Response::error('Error message');

// Show success message
Response::success('Success message');

// CSV export
Response::csv($data, 'filename');
```

## Access Control

Controllers automatically handle access control via `$this->access()` inherited from AbstractController.

Override module-level access for specific actions:

```php
#[AccessLevel('admin')]  // Only admins can access
#[RequestAction('delete')]
public function deletePage() { }
```

## Variables to Replace

When creating new modules:

- `{Namespace}` - `Modules` (milkadmin) or `Local\Modules` (milkadmin_local)
- `{ModuleName}` - CamelCase module name (e.g., `Posts`, `LinksData`)
- `{custom}` - Custom view name (e.g., `calendar`, `dashboard`)

## See Also

- **Controller structure**: [create-controller.md](create-controller.md)
- **Form creation**: [create-builder-form.md](create-builder-form.md)
- **List/Table creation**: [create-builder-list.md](create-builder-list.md)
- **Model operations**: [create-model.md](create-model.md)
- **Complete examples**: `examples/` folder
