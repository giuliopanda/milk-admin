# Test Database - Query e relazioni

Test completi per valutare come vengono caricati i dati con relazioni hasMany nel framework MilkAdmin.

## Struttura

```
tests/Unit/App/Database/
├── README.md
├── Fixtures/
│   ├── AuthorModel.php
│   └── BookModel.php
├── HasManyLoadingTest.php
├── HasManyCountTest.php
├── QueryScopeInheritanceTest.php
├── RelationshipModelInstancesTest.php
└── SelectMethodsTest.php
```

## Relazione Testata

Il test usa la relazione **Author → Books** (1:N) come da documentazione ufficiale:

- **AuthorModel**: Un autore ha molti libri (`hasMany`)
- **BookModel**: Un libro appartiene a un autore (`belongsTo`)

## Test Inclusi

### 1. Lazy Loading
Verifica che `$author->books` carichi i libri on-demand quando si accede alla proprietà.

```php
$author = $authorsModel->getById(1);
$books = $author->books; // Caricamento lazy
```

### 2. Eager Loading (Single Relationship)
Verifica che `with('books')` precarichi una singola relazione.

```php
$authors = $authorsModel->query()->getResults();
$authors->with('books'); // Precaricamento
```

### 3. Eager Loading (All Relationships)
Verifica che `with(null)` precarichi tutte le relazioni disponibili.

```php
$authors->with(null); // Tutte le relazioni
```

### 4. Batch Loading (N+1 Prevention)
Verifica che il framework ottimizzi automaticamente le query per prevenire il problema N+1.

```php
foreach ($authors as $author) {
    $books = $author->books; // Batch: carica tutti i libri in 1 query
}
```

### 5. BelongsTo Relationship
Verifica la relazione inversa: da libro a autore.

```php
$book = $booksModel->getById(1);
$author = $book->author; // Relazione belongsTo
```

### 6. Count in PHP
Verifica il conteggio standard usando `count()` in PHP.

```php
$bookCount = count($author->books); // Count in PHP
```

**Nota**: Questo approccio carica tutti i libri in memoria, non è ordinabile.

### 7. Data Structure
Verifica che `hasMany` restituisca un array di oggetti.

### 8. Direct Iteration
Verifica l'iterazione diretta come da documentazione.

```php
foreach ($author->books as $book) {
    echo $book->title;
}
```

### 9. Access Book Properties
Verifica l'accesso alle proprietà dei libri.

```php
echo $book->title;
echo $book->year;
echo $book->price;
```

## Dataset di Test

Il test crea automaticamente questi dati:

- **J.K. Rowling** (UK, 1965)
  - 7 libri di Harry Potter (1997-2003)

- **George Orwell** (UK, 1903)
  - 1984 (1949)
  - Animal Farm (1945)

- **Ernest Hemingway** (USA, 1899)
  - 0 libri (caso edge per testare relazioni vuote)

## Esecuzione

### Metodo 1: Script rapido
```bash
php tests/Unit/App/Database/run-has-many-loading.php
```

### Metodo 2: PHPUnit diretto
```bash
# Test normali
php vendor/bin/phpunit tests/Unit/App/Database/HasManyLoadingTest.php --testdox

# Con debug dettagliato
php vendor/bin/phpunit tests/Unit/App/Database/HasManyLoadingTest.php --testdox --debug

# Senza messaggi di debug del framework
php vendor/bin/phpunit tests/Unit/App/Database/HasManyLoadingTest.php --testdox 2>&1 | grep -v "^\[DEBUG"
```

### Metodo 3: Test specifico
```bash
php vendor/bin/phpunit tests/Unit/App/Database/HasManyLoadingTest.php --filter testLazyLoading
```

## Output Atteso

```
Has Many Loading
 ✔ Lazy loading
 ✔ Eager loading with single
 ✔ Eager loading with all
 ✔ Batch loading prevents n plus one
 ✔ Belongs to relationship
 ✔ Count books in p h p
 ✔ Data structure
 ✔ Direct iteration
 ✔ Access book properties

OK (9 tests, 45 assertions)
```

## Prossimi Passi

Dopo aver verificato il funzionamento classico di hasMany, i prossimi test potrebbero includere:

1. **Query Optimization Test**: Testare subquery COUNT per ordinamento
2. **Cascade Save Test**: Testare `save(true)` con relazioni
3. **Cascade Delete Test**: Testare CASCADE, SET NULL, RESTRICT
4. **whereHas Test**: Filtrare autori in base ai libri
5. **Nested Relationships Test**: Author → Books → Reviews

## Riferimenti

- Documentazione ufficiale: `/milkadmin/Modules/Docs/Pages/Developer/AbstractsClass/abstract-model-relationships.page.php`
- Esempio Extension: `/milkadmin/Extensions/Comments/`
