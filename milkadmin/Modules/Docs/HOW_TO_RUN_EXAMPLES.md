# How to Run Documentation Code Examples

Quick guide to test the examples used in the documentation.

## 🚀 Quick Start

### 1. Create a test module

```
milkadmin_local/Modules/Playground/PlaygroundModule.php
```

### 2. Copy this template

```php
<?php
namespace Local\Modules\Playground;

use App\Abstracts\{AbstractModule, AbstractModel};
use App\Attributes\RequestAction;
use App\Response;
use Builders\{TableBuilder, FormBuilder, CalendarBuilder, ScheduleGridBuilder};

class PlaygroundModule extends AbstractModule {

    protected function configure($rule): void
    {
        $rule->page('playground')
             ->title('Code Playground')
             ->menu('🧪 Playground', '', 'bi bi-code-square', 999)
             ->access('public')
             ->model(PlaygroundModel::class);
    }

    #[RequestAction('home')]
    public function index() {
        // PASTE EXAMPLE CODE HERE

        Response::render('<div class="p-4"><h1>Test</h1></div>');
    }
}

class PlaygroundModel extends AbstractModel {
    protected function configure($rule): void
    {
        $rule->table('playground_test')
            ->id()
            ->string('name', 100)
            ->string('email', 100);
    }
}
```

### 3. Install/Update

```bash
# Con database
php milkadmin/cli.php module:install

# Without database
php milkadmin/cli.php module:update
```

### 4. Test

Visita: `?page=playground`

---

## 📝 Common Examples

### TableBuilder

```php
$table = TableBuilder::create($this->model, 'test')
    ->resetFields()
    ->field('name')->label('Name')
    // Conditionally print a field (evaluated before custom formatter)
    ->field('email')
        ->label('Email')
        ->type('html')
        ->showIf('[active] == 1', '<span class="text-muted">Hidden</span>')
    ->render();

Response::render('<div class="p-4">' . $table . '</div>');
```

### FormBuilder

```php
$form = FormBuilder::create($this->model)
    ->text('name')->label('Name')
    ->getForm();

Response::render('<div class="p-4">' . $form . '</div>');
```

### ScheduleGridBuilder

```php
$schedule = ScheduleGridBuilder::create($this->model, 'test_schedule')
    ->setPeriod('week')
    ->detectPeriodFromRequest()
    ->setHeaderTitle('Test Schedule')
    ->render();

Response::render('<div class="p-4">' . $schedule . '</div>');
```

---

## 🧪 Test with Fake Data (ArrayDB)

```php
use App\Get;

$db = Get::arrayDb();
$db->addTable('test_data', [
    ['id' => 1, 'name' => 'Alice', 'email' => 'alice@test.com'],
    ['id' => 2, 'name' => 'Bob', 'email' => 'bob@test.com'],
], 'id');

$table = TableBuilder::create($this->model, 'test')->render();
Response::render($table);
```

---

## ⚡ Tips

- **Use `milkadmin_local/Modules/`** for tests
- **Set a high menu order** (`999`) to place it at the bottom
- **Use public access** for quick testing without login
- **Remove the module** when you are done

## 🐛 Troubleshooting

| Problem | Solution |
|----------|-----------|
| Module not visible | `php milkadmin/cli.php module:update` |
| 404 | Check the value used in `page()` |
| Blank page | Check `milkadmin_local/storage/error.log` |
| Table not created | Use `module:install` instead of `module:update` |
