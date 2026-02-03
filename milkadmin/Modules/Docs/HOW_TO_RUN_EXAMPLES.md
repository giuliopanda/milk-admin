# Come Eseguire gli Esempi di Codice della Documentazione

Guida rapida per testare gli esempi presenti nella documentazione.

## 🚀 Quick Start

### 1. Crea un modulo di test

```
milkadmin_local/Modules/Playground/PlaygroundModule.php
```

### 2. Copia questo template

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
        // INCOLLA QUI IL CODICE DI ESEMPIO

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

### 3. Installa/Aggiorna

```bash
# Con database
php milkadmin/cli.php module:install

# Senza database
php milkadmin/cli.php module:update
```

### 4. Testa

Visita: `?page=playground`

---

## 📝 Esempi Comuni

### TableBuilder

```php
$table = TableBuilder::create($this->model, 'test')
    ->resetFields()
    ->field('name')->label('Nome')
    ->render();

Response::render('<div class="p-4">' . $table . '</div>');
```

### FormBuilder

```php
$form = FormBuilder::create($this->model)
    ->text('name')->label('Nome')
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

## 🧪 Test con Dati Fake (ArrayDB)

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

- **Usa `milkadmin_local/Modules/`** per i test
- **Menu order alto** (`999`) per metterlo in fondo
- **Access public** per test veloci senza login
- **Cancella il modulo** quando hai finito

## 🐛 Troubleshooting

| Problema | Soluzione |
|----------|-----------|
| Modulo non appare | `php milkadmin/cli.php module:update` |
| 404 | Verifica il nome in `page()` |
| Pagina bianca | Controlla `milkadmin_local/storage/error.log` |
| Tabella non creata | Usa `module:install` non `module:update` |
