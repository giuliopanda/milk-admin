# Model Queries and Operations

Guida completa per interrogare e manipolare dati con AbstractModel.

## Common Mistakes ⚠️

### 1. Using all() instead of getAll()
**❌ Wrong:**
```php
$items = $model->all();  // ❌ Method doesn't exist!
```

**✅ Correct:**
```php
$items = $model->getAll();  // ✅ Get all records
```

### 2. Using count() with conditions
**❌ Wrong:**
```php
// count() doesn't accept conditions!
$total = $model->count(['status' => 'active']);  // ❌ Wrong!
```

**✅ Correct:**
```php
// Use query()->where()->total() for counting with conditions
$total = $model->query()
    ->where('status = ?', ['active'])
    ->total();

// Or count all records without conditions
$allRecords = $model->getAll();
$total = $allRecords->count();  // Returns number of records in result
```

### 3. Using find() instead of getById()
**❌ Wrong:**
```php
$item = $model->find($id);  // ❌ Method doesn't exist!
```

**✅ Correct:**
```php
$item = $model->getById($id);  // ✅ Get by ID
```

### 4. Not checking if result is empty
**❌ Wrong:**
```php
$post = $postModel->getById($id);
echo $post->title;  // ❌ May be empty!
```

**✅ Correct:**
```php
$post = $postModel->getById($id);
if ($post->getEmpty()) {
    return Response::error('Not found');
}
echo $post->title;  // ✅ Safe
```

### 5. Using whereIs() instead of where()
**❌ Wrong:**
```php
// whereIs() doesn't exist!
$items = $model->query()->whereIs('status', 'active')->getResults();  // ❌ ERROR!
```

**✅ Correct:**
```php
// Always use where() with SQL syntax
$items = $model->query()->where('status = ?', ['active'])->getResults();
```

### 6. Using array syntax in where()
**❌ Wrong:**
```php
// Array syntax is NOT supported!
$items = $model->query()->where(['status' => 'active'])->getResults();  // ❌ ERROR!
```

**✅ Correct:**
```php
// Use SQL string with placeholders
$items = $model->query()->where('status = ?', ['active'])->getResults();
```

### 7. Using Get::int() to get parameters
**❌ Wrong:**
```php
// Get::int() doesn't exist!
$id = Get::int('id');  // ❌ ERROR!
```

**✅ Correct:**
```php
// Use _absint() with null coalescing operator
$id = _absint($_GET['id'] ?? 0);
```

### 8. Using find() instead of getById() or where()->getRow()
**❌ Wrong:**
```php
// find() method doesn't exist for querying!
$corso = $this->model->find($id_corso);  // ❌ ERROR!
```

**✅ Correct:**
```php
// Option 1: Use getById() for simple ID lookup
$corso = $this->model->getById($id_corso);

// Option 2: Use where()->getRow() for custom conditions
$corso = $this->model->where("id = ?", [$id_corso])->getRow();
```

### 9. Using total() instead of getTotal()
**❌ Wrong:**
```php
// total() doesn't exist - use getTotal()!
$count = $model->query()
    ->where('status = ?', ['active'])
    ->total();  // ❌ ERROR!
```

**✅ Correct:**
```php
// Always use getTotal() for counting
$count = $model->query()
    ->where('status = ?', ['active'])
    ->getTotal();  // ✅ Correct
```

### 10. Using !$object instead of isEmpty()
**❌ Wrong:**
```php
$corso = $this->model->getById($id);
if (!$corso) {  // ❌ Wrong! Object always exists
    return Response::error('Not found');
}
```

**✅ Correct:**
```php
$corso = $this->model->getById($id);
if ($corso->isEmpty()) {  // ✅ Correct method to check
    return Response::error('Not found');
}
```

## Architettura Query

**Concetto fondamentale:**
- `$model->query()` ritorna un oggetto **Query**
- I metodi builder (`where`, `whereIn`, `whereHas`, `order`, `select`, `limit`) ritornano **Query** per permettere il chaining
- I metodi executor (`getResults`, `getRow`, `getVar`) eseguono la query e ritornano **Model**

```php
$results = $model->query()          // Returns Query
    ->where('status = ?', ['active']) // Returns Query
    ->order('created_at', 'desc')     // Returns Query
    ->getResults();                   // Returns Model
```

## Metodi Query Builder

**Metodi che costruiscono la query (ritornano Query):**

| Metodo | Parametri | Ritorna | Descrizione |
|--------|-----------|---------|-------------|
| `where($condition, $params)` | `string $condition`, `array $params` | Query | Aggiunge condizione WHERE |
| `whereIn($field, $values)` | `string $field`, `array $values` | Query | WHERE IN |
| `whereHas($relation, $callback)` | `string $relation`, `callable $callback` | Query | WHERE EXISTS su relazione |
| `order($field, $dir)` | `string\|array $field`, `string $dir = 'asc'` | Query | ORDER BY |
| `select($fields)` | `array $fields` | Query | SELECT specifici campi |
| `limit($offset, $count)` | `int $offset`, `int $count` | Query | LIMIT |
| `with($relations)` | `array $relations` | Query | Eager loading relazioni |

**Metodi che eseguono la query (ritornano Model o valore):**

| Metodo | Parametri | Ritorna | Descrizione |
|--------|-----------|---------|-------------|
| `getResults()` | - | Model | Tutti i record trovati |
| `getRow()` | - | Model | Primo record trovato |
| `getVar()` | - | mixed | Primo valore della prima riga |
| `getTotal()` | - | int | Conteggio record (NOT total()!) |

## Quick Reference - Most Common Methods 📌

**Getting data:**
```php
$model->getAll()                                      // Get all records
$model->getById($id)                                  // Get single record by ID (NOT find()!)
$model->query()->where('field = ?', [$value])->getResults()  // Filter by field
$model->query()->where('field = ?', [$value])->getTotal()    // Count with conditions (NOT total()!)
```

**Saving data:**
```php
$model->fill($data)                 // Fill model with data
$model->save()                      // Save (insert or update)
$model->store($data, $id)           // Fill + save in one call
```

**Deleting:**
```php
$model->delete($id)                 // Delete by ID
```

## Metodi Model Diretti

**Metodi senza query() (ritornano Model direttamente):**

| Metodo | Parametri | Ritorna | Descrizione |
|--------|-----------|---------|-------------|
| `getById($id, $use_cache)` | `mixed $id`, `bool $use_cache = true` | Model | Record per ID (NOT find()!) |
| `getByIds($ids)` | `array\|string $ids` | Model | Record multipli per ID |
| `getFirst($order_field, $order_dir)` | `string $order_field`, `string $order_dir = 'asc'` | Model | Primo record ordinato |
| `getAll()` | - | Model | Tutti i record (NOT all()!) |

## Verifica Risultati

Usa `isEmpty()` per verificare se ci sono dati:

```php
$post = $postModel->getById($id);
if ($post->isEmpty()) {
    // Nessun dato trovato
}
```

## Operazioni CRUD

| Metodo | Parametri | Ritorna | Descrizione |
|--------|-----------|---------|-------------|
| `fill($data)` | `array $data` | void | Popola il Model con dati |
| `save()` | - | bool | Salva (INSERT o UPDATE) |
| `store($data, $id)` | `array $data`, `mixed $id = null` | bool | Combina fill + save |
| `delete($id)` | `mixed $id` | bool | Elimina per ID |
| `validate()` | - | bool | Valida i dati correnti |

**Esempi:**

```php
// CREATE - metodo 1
$post = new PostModel();
$post->fill(['title' => 'New Post', 'content' => 'Content']);
$post->save();
$id = $post->getLastInsertId();

// CREATE - metodo 2
$postModel->store(['title' => 'New Post', 'content' => 'Content']);
$id = $postModel->getLastInsertId();

// UPDATE - modifica proprietà
$post = $postModel->getById(5);
$post->title = "Updated";
$post->save();

// UPDATE - con fill
$post->fill(['title' => 'Updated', 'status' => 'draft']);
$post->save();

// UPDATE - con store
$postModel->store(['title' => 'Updated'], $id);

// DELETE
$postModel->delete($id);
```

## Formattazione Dati

| Metodo | Parametri | Ritorna | Descrizione |
|--------|-----------|---------|-------------|
| `getFormattedData($type, $all)` | `string $type = 'object'`, `bool $all = true` | object\|array | Dati formattati con `#[ToDisplayValue]` |
| `toRawArray()` | - | array | Dati raw senza formattazione |
| `isEmpty()` | - | bool | Verifica se vuoto (NOT getEmpty()!) |
| `count()` | - | int | Numero record |

## Navigazione Record Multipli

| Metodo | Descrizione |
|--------|-------------|
| `first()` | Va al primo record |
| `next()` | Va al record successivo |
| `prev()` | Va al record precedente |
| `last()` | Va all'ultimo record |
| `moveTo($index)` | Va a indice specifico |
| `$model[$index]` | Accesso array-like |
| `foreach ($model as $item)` | Iterazione |

## Gestione Errori

| Metodo | Ritorna | Descrizione |
|--------|---------|-------------|
| `getLastError()` | string | Ultimo errore SQL |
| `getLastInsertId()` | int | Ultimo ID inserito |
| `clearCache()` | void | Pulisce cache model |

## Metodi Avanzati

| Metodo | Parametri | Ritorna | Descrizione |
|--------|-----------|---------|-------------|
| `setQueryParams($request)` | `array $request` | Query | Imposta limit, order, filter da request |
| `filterSearch($search, $query)` | `string $search`, `Query $query` | Query | Aggiunge ricerca su tutti i campi |
| `addFilter($type, $fn)` | `string $type`, `callable $fn` | void | Registra funzione filtro custom |

## Esempi Pratici

### CREATE con validazione
```php
// ✅ CORRECT: save() gestisce automaticamente fill() e validate()
// Restituisce ['success' => bool, 'error' => string]
$result = $this->model->save();

if ($result['success']) {
    Response::json([
        'success' => true,
        'message' => 'Saved successfully',
        'reload_table' => 'idTableName'  // NOT 'table' => ['action'=>'reload', 'id'=>'...']
    ]);
} else {
    Response::json([
        'success' => false,
        'message' => $result['error']
    ]);
}

// ❌ WRONG - Don't use fill() and validate() manually:
// $post = new PostModel();
// $post->fill($_POST['data']);
// if (!$post->validate()) {
//     $errors = \App\MessagesHandler::getErrors();
//     return Response::json(['success' => false, 'errors' => $errors]);
// }
// $post->save();
```

### READ con relazioni
```php
$post = $postModel->query()
    ->where('id = ?', [$id])
    ->with(['author', 'category'])
    ->getRow();

if (!$post->isEmpty()) {
    echo $post->author->username;
}
```

### UPDATE
```php
// ❌ WRONG - Don't use fill() and validate() manually
// $post = $postModel->getById($id);
// if ($post->isEmpty()) return Response::error('Not found');
// $post->fill($_POST['data']);
// if (!$post->validate()) return Response::error('Validation failed');
// $post->save();

// ✅ CORRECT: save() handles everything
// For UPDATE, pass the ID in $_POST['data']['id']
$result = $this->model->save();
if ($result['success']) {
    Response::json(['success' => true, 'message' => 'Updated', 'reload_table' => 'idTableName']);
} else {
    Response::json(['success' => false, 'message' => $result['error']]);
}
```

### DELETE
```php
if (!$postModel->delete($id)) {
    return Response::error($postModel->getLastError());
}
```

### Query complesse
```php
// Ricerca con filtri
$posts = $postModel->query()
    ->where('title LIKE ?', ['%' . $search . '%'])
    ->whereIn('category_id', [1, 2, 3])
    ->order('created_at', 'desc')
    ->limit(0, 20)
    ->getResults();

// Aggregazioni
$stats = $postModel->query()
    ->select(['COUNT(*) as total', 'AVG(views) as avg_views'])
    ->where('created_at > ?', ['2024-01-01'])
    ->getRow();
```

## See Also

- **Model structure**: [create-model.md](create-model.md) - RuleBuilder methods, PHP attributes, validation
- **Controller patterns**: [create-controller.md](create-controller.md) - Using models in controllers
- **Relationships**: [create-model.md](create-model.md) - belongsTo, hasMany, hasOne
