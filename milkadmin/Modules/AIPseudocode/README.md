# AI Module Generation - Quick Guide

Generate Milk Admin modules from pseudocode using standard patterns.

## 📚 Official Guides

**ALWAYS follow these guides as reference:**
- **Standard CRUD Module**: [milkadmin/Modules/Docs/Pages/Developer/GettingStarted/getting-started-post.page.php](../../Docs/Pages/Developer/GettingStarted/getting-started-post.page.php)
- **Fetch-Based Forms (Modal/Offcanvas)**: [milkadmin/Modules/Docs/Pages/Developer/Advanced/fetch-modal-crud.page.php](../../Docs/Pages/Developer/Advanced/fetch-modal-crud.page.php)

## ⚡ Quick Start

### 1. Read Pseudocode
```
start: milkadmin/Modules/AIPseudocode/pseudocode-guide.md

Module: Products
Access: authorized users
Menu: Products, Categories
Table: products
Fields:
- name (required)
- description
- price
```

See [pseudocode-guide.md](pseudocode-guide.md) for syntax.

### 2. Generate Module Files

Create 3 files in `milkadmin/Modules/{ModuleName}/` or `milkadmin_local/Modules/{ModuleName}/`:

**{ModuleName}Module.php**
```php
<?php
namespace Modules\Products;
use App\Abstracts\AbstractModule;

class ProductsModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('products')
             ->title('Products')
             ->menu('Products', '', 'bi bi-box-seam', 10)
             ->access('authorized')
             ->version(251215);
    }
}
```

**{ModuleName}Model.php**
```php
<?php
namespace Modules\Products;
use App\Abstracts\AbstractModel;

class ProductsModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__products')
            ->id()
            ->title('name')->required()
            ->text('description')->formType('textarea')
            ->decimal('price', 10, 2)->default(0);
    }
}
```

**{ModuleName}Controller.php**
```php
<?php
namespace Modules\Products;
use App\Abstracts\AbstractController;
use App\{Response};
use App\Attributes\RequestAction;
use Builders\{TableBuilder, FormBuilder};

class ProductsController extends AbstractController
{
    #[RequestAction('home')]
    public function list() {
        $tableBuilder = TableBuilder::create($this->model, 'idTableProducts')
            ->field('name')->link('?page='.$this->page.'&action=edit&id=%id%')
            ->setDefaultActions();
        $response = array_merge($this->getCommonData(), $tableBuilder->getResponse());
        $response['title_btns'] = [['label'=>'Add New', 'link'=>'?page='.$this->page.'&action=edit']];
        Response::render(MILK_DIR . '/Theme/SharedViews/list_page.php', $response);
    }

    #[RequestAction('edit')]
    public function edit() {
        $response = $this->getCommonData();
        $response['form'] = FormBuilder::create($this->model, $this->page)->getForm();
        $response['title'] = ($_REQUEST['id'] ?? 0) > 0 ? 'Edit Product' : 'Add Product';
        Response::render(MILK_DIR . '/Theme/SharedViews/edit_page.php', $response);
    }
}
```

### 3. Install Database

```bash
# First installation (creates table)
php milkadmin/cli.php products:install

# Update after model changes
php milkadmin/cli.php products:update
```

### 4. Test Controller

```bash
# Test list action
php milkadmin/cli.php test-controller "page=products"

# Test edit action
php milkadmin/cli.php test-controller "page=products&action=edit" --get="id=1"
```

## 🎯 Standard Patterns

### Pattern 1: Standard CRUD (Page Reload)

**Use when:** Basic list/edit operations, simple forms

**Example:** [milkadmin/Modules/Posts/PostsController.php](../Posts/PostsController.php)

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

### Pattern 2: Fetch-Based (Modal/Offcanvas)

**Use when:** Modern UX, no page reloads, quick edits

**Example:** [milkadmin_local/Modules/Recipe/RecipeModule.php](../../../milkadmin_local/Modules/Recipe/RecipeModule.php)

```php
#[RequestAction('home')]
public function list() {
    $tableBuilder = TableBuilder::create($this->model, 'idTable')
        ->activeFetch()  // Enable AJAX
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
            ->activeFetch()                    // Enable AJAX
            ->asOffcanvas()                     // Show in side panel
            ->setTitle('New Item', 'Edit Item') // Dynamic titles
            ->dataListId('idTable')             // Auto-reload table
            ->getResponse()
    );
    Response::json($response);  // Return JSON, not HTML
}
```

## 📖 Available Methods

See [api-reference.md](api-reference.md) for complete method reference.

## ⚠️ Critical Rules

See [critical-patterns.md](critical-patterns.md) for common mistakes to avoid.

## 🔑 Key Principles

### 1. **ALWAYS Use SharedViews**
```php
// ✅ CORRECT - Use SharedViews
Response::render(MILK_DIR . '/Theme/SharedViews/list_page.php', $response);
Response::render(MILK_DIR . '/Theme/SharedViews/edit_page.php', $response);

// ❌ WRONG - Don't create custom views unless absolutely necessary
Response::render(__DIR__ . '/Views/list_page.php', $response);
```

### 2. **Follow Official Examples**
- **Standard CRUD**: Copy pattern from [Posts module](../Posts/)
- **Fetch-Based**: Copy pattern from [Recipe module](../../../milkadmin_local/Modules/Recipe/)

### 3. **Use Only Documented Methods**
Check [api-reference.md](api-reference.md) before using any method. Don't invent methods.

### 4. **Test Everything**
Run `test-controller` command after generating controllers.

## 📋 Checklist

- [ ] Read pseudocode from module folder
- [ ] Check if table exists: `php milkadmin/cli.php sql-table-structure {table_name}`
- [ ] Create Module file (namespace, configure method)
- [ ] Create Model file (table, fields using RuleBuilder)
- [ ] Create Controller file (RequestAction methods, use SharedViews)
- [ ] Run install/update: `php milkadmin/cli.php {module}:install`
- [ ] Test controller: `php milkadmin/cli.php test-controller "page={module}"`
- [ ] Verify in browser

## 🚫 What NOT to Do

- ❌ Don't create Views unless explicitly requested
- ❌ Don't invent methods not in api-reference.md
- ❌ Don't use old patterns from module-instructions/ folder
- ❌ Don't add extra features beyond requirements
- ❌ Don't use methods without `_` prefix in views (`_ph()`, `_pt()`, `_p()`)

## 📚 Documentation Files

1. **pseudocode-guide.md** - How to write pseudocode
2. **api-reference.md** - All available methods
3. **critical-patterns.md** - Common mistakes and correct patterns

## 🎓 Learning Path

1. Read [getting-started-post.page.php](../../Docs/Pages/Developer/GettingStarted/getting-started-post.page.php)
2. Study [Posts module](../Posts/) source code
3. Read [fetch-modal-crud.page.php](../../Docs/Pages/Developer/Advanced/fetch-modal-crud.page.php)
4. Study [Recipe module](../../../milkadmin_local/Modules/Recipe/) source code
5. Review [api-reference.md](api-reference.md) for method syntax
6. Check [critical-patterns.md](critical-patterns.md) before coding

---

**Remember:** Keep it simple, follow the standard patterns, use SharedViews, and test everything.
