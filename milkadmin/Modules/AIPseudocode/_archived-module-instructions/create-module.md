# Create Module

Generate empty Module and Model files.

## Structure

```
Modules/{ModuleName}/
├── {ModuleName}Module.php
└── {ModuleName}Model.php
```

## Module Template

```php
<?php
namespace {Namespace}\{ModuleName};

use App\Abstracts\AbstractModule;

class {ModuleName}Module extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('{module_page}')
             ->title('{Module Title}')
             ->access('authorized')
             ->version({YYMMDD});
    }

    public function bootstrap()
    {
        // Bootstrap code
    }
}
```

## Model Template

```php
<?php
namespace {Namespace}\{ModuleName};

use App\Abstracts\AbstractModel;

class {ModuleName}Model extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__{table_name}')
             ->id();
    }
}
```

## Variables to replace

- `{Namespace}` - `Modules` for milkadmin, `Local\Modules` for milkadmin_local
- `{ModuleName}` - CamelCase module name (e.g., TestList)
- `{module_page}` - Lowercase page name (e.g., testlist)
- `{Module Title}` - Human readable title
- `{YYMMDD}` - Version number (e.g., 251205)
- `{table_name}` - Database table name (e.g., testlist)

## Namespace Determination (AI Internal)

Determine namespace from module path:
- `milkadmin/Modules/{ModuleName}/` → `namespace Modules\{ModuleName}`
- `milkadmin_local/Modules/{ModuleName}/` → `namespace Local\Modules\{ModuleName}`

Check file path to determine correct namespace automatically.

## Multiple Models (Additional Models)

**CRITICAL**: If your module needs additional models (beyond the main Model), you MUST register them using `->addModels()` in the `configure()` method, NOT as a separate method.

### Correct Way ✅

```php
protected function configure($rule): void
{
    $rule->page('lessons')
         ->title('Corsi di Laurea')
         ->menu('Corsi', '', 'bi bi-mortarboard-fill', 10)
         ->access('public')
         ->addModels([
             'Enrollment' => LessonsEnrollmentModel::class,
             'Category' => LessonsCategoryModel::class
         ])
         ->version(251205);
}
```

### Wrong Way ❌

```php
// DON'T DO THIS - This won't work!
protected function addModels(): array
{
    return [
        'Enrollment' => LessonsEnrollmentModel::class
    ];
}
```

**Why it matters:**
- Without `->addModels()` in the chain, secondary models won't be installed/updated
- The framework won't create tables for additional models
- Relationships won't work properly

**Example with multiple models:**

```php
// LinksDataModule.php
protected function configure($rule): void
{
    $rule->page('linksdata')
         ->title('Links Data')
         ->menu('Links', '', 'bi bi-link-45deg', 10)
         ->access('authorized')
         ->addModels([
             'LinkCategory' => LinkCategoryModel::class,
             'LinkTag' => LinkTagModel::class
         ])
         ->permissions(['access' => 'Access'])
         ->version(251130);
}
```

## Notes

- Module file extends `AbstractModule`
- Model file extends `AbstractModel`
- Use `configure()` method with `$rule` builder
- Default access: `'authorized'`
- Primary key auto-created with `->id()`
- **Use `->addModels([...])` in the chain, NOT as a separate method**
