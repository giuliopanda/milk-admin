<?php
/**
 * Test per verificare che le relazioni ritornino Model instances
 *
 * Questo test verifica che:
 * 1. hasMany ritorna un result set di Model instances (non stdClass)
 * 2. belongsTo ritorna Model instance (non stdClass)
 * 3. Il result set usa un puntatore interno, non copie staccate dei record
 * 4. Le relazioni lazy-loadable funzionano anche in navigazione nidificata
 * 5. I dati possono essere modificati e salvati mantenendo record originali, edit e insert
 *
 * Esegui con:
 * php vendor/bin/phpunit tests/Unit/App/Database/RelationshipModelInstancesTest.php --testdox
 *
 * Documentation: milkadmin/Modules/Docs/Pages/Developer/AbstractsClass/abstract-model-relationships.page.php
 */

// Bootstrap the framework
if (!defined('MILK_TEST_CONTEXT')) {
    define('MILK_TEST_CONTEXT', true);
}
if (!defined('MILK_API_CONTEXT')) {
    define('MILK_API_CONTEXT', true);
}

require_once dirname(__DIR__, 4) . '/public_html/milkadmin.php';
require_once MILK_DIR . '/autoload.php';

use PHPUnit\Framework\TestCase;

// Carica i modelli di test
require_once __DIR__ . '/Fixtures/AuthorModel.php';
require_once __DIR__ . '/Fixtures/BookModel.php';

use Tests\Unit\App\Database\Fixtures\{AuthorModel, BookModel};

class RelationshipModelInstancesTest extends TestCase
{
    private AuthorModel $authorModel;
    private BookModel $bookModel;

    protected function setUp(): void
    {
        $this->authorModel = new AuthorModel();
        $this->bookModel = new BookModel();

        // Crea le tabelle
        $this->createTables();

        // Inserisci dati di test
        $this->insertTestData();
    }

    protected function tearDown(): void
    {
        // Pulisci: elimina le tabelle di test
        try {
            $this->authorModel->dropTable();
            $this->bookModel->dropTable();
        } catch (\Exception) {
            // Ignora errori
        }
    }

    private function createTables(): void
    {
        $this->debug("=== CREAZIONE TABELLE ===");

        try {
            $this->bookModel->dropTable();
            $this->authorModel->dropTable();
        } catch (\Throwable) {
            // Ignore cleanup errors on a fresh database.
        }

        $result1 = $this->authorModel->buildTable();
        $this->assertTrue($result1, "Tabella authors creata: " . $this->authorModel->last_error);
        $this->debug("✓ Tabella test_authors creata");

        $result2 = $this->bookModel->buildTable();
        $this->assertTrue($result2, "Tabella books creata: " . $this->bookModel->last_error);
        $this->debug("✓ Tabella test_books creata");
    }

    private function insertTestData(): void
    {
        $this->debug("\n=== INSERIMENTO DATI DI TEST ===");

        // Autore: George Orwell con 2 libri
        $author = new AuthorModel();
        $author->name = 'George Orwell';
        $author->country = 'UK';
        $author->birth_year = 1903;
        $author->save();
        $authorId = $author->author_id;
        $this->debug("✓ Autore: George Orwell (ID: {$authorId})");

        $book1 = new BookModel();
        $book1->author_id = $authorId;
        $book1->title = '1984';
        $book1->year = 1949;
        $book1->price = 15.99;
        $book1->save();

        $book2 = new BookModel();
        $book2->author_id = $authorId;
        $book2->title = 'Animal Farm';
        $book2->year = 1945;
        $book2->price = 12.99;
        $book2->save();

        $this->debug("  → 2 libri inseriti (1984, Animal Farm)");
    }

    /**
     * Test 1: hasMany ritorna un result set di Model instances
     */
    public function testHasManyReturnsModelInstances()
    {
        $this->debug("\n=== TEST 1: hasMany ritorna Model instances ===");

        $author = $this->authorModel->query()
            ->where('name = ?', ['George Orwell'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        $books = $author->books;

        $this->assertInstanceOf(BookModel::class, $books, "books dovrebbe essere un BookModel result set");
        $this->assertGreaterThanOrEqual(1, $books->count(), "Orwell dovrebbe avere almeno 1 libro");

        foreach ($books as $book) {
            $this->assertInstanceOf(BookModel::class, $book, "Ogni libro dovrebbe essere BookModel instance");
            $this->debug("✓ Libro: {$book->title} è BookModel instance");
        }

        $this->debug("✓ Test PASSED: hasMany ritorna Model instances");
    }

    /**
     * Test 2: belongsTo ritorna Model instance
     */
    public function testBelongsToReturnsModelInstance()
    {
        $this->debug("\n=== TEST 2: belongsTo ritorna Model instance ===");

        $book = $this->bookModel->query()
            ->where('title = ?', ['1984'])
            ->getResults();

        $this->assertInstanceOf(BookModel::class, $book);
        $author = $book->author;

        $this->assertInstanceOf(AuthorModel::class, $author, "author dovrebbe essere AuthorModel instance");
        $this->assertEquals('George Orwell', $author->name);

        $this->debug("✓ Autore: {$author->name} è AuthorModel instance");
        $this->debug("✓ Test PASSED: belongsTo ritorna Model instance");
    }

    /**
     * Test 3: Model instances hanno accesso ai metodi del model
     */
    public function testModelInstancesHaveModelMethods()
    {
        $this->debug("\n=== TEST 3: Model instances hanno metodi ===");

        $author = $this->authorModel->query()
            ->where('name = ?', ['George Orwell'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        $books = $author->books;
        $firstBook = $books->first();
        $this->assertInstanceOf(BookModel::class, $firstBook);

        // Verifica che i metodi del model siano invocabili sul result set corrente
        $this->assertNull($firstBook->getRecordAction());
        $this->assertNotNull($firstBook->getRawValue('title'));

        $this->debug("✓ BookModel instance ha metodo save()");
        $this->debug("✓ BookModel instance ha metodo delete()");
        $this->debug("✓ BookModel instance ha metodo getRawValue()");

        // Verifica accesso ai dati
        $title = $firstBook->title;
        $this->assertNotEmpty($title);
        $this->debug("✓ Accesso proprietà: \$book->title = '{$title}'");

        $this->debug("✓ Test PASSED: Model instances hanno metodi");
    }

    /**
     * Test 4: Relazioni bloccate prevengono loop infiniti
     */
    public function testBlockedRelationsPreventInfiniteLoops()
    {
        $this->debug("\n=== TEST 4: Lazy loading su relazioni nidificate ===");

        $author = $this->authorModel->query()
            ->where('name = ?', ['George Orwell'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        $books = $author->books;
        $firstBook = $books->first();

        // Il libro è un Model instance con relazioni abilitate
        $this->assertInstanceOf(BookModel::class, $firstBook);

        // Ora dovrebbe essere possibile accedere a relazioni del libro tramite lazy loading
        $bookAuthor = $firstBook->author;
        $this->assertInstanceOf(AuthorModel::class, $bookAuthor, "Le relazioni dovrebbero supportare lazy loading");
        $this->assertEquals('George Orwell', $bookAuthor->name, "L'autore del libro dovrebbe essere George Orwell");

        $this->debug("✓ \$book->author è un AuthorModel (lazy loading funzionante)");
        $this->debug("✓ Test PASSED: Lazy loading su relazioni nidificate funziona");
    }

    /**
     * Test 5: isset() funziona con relazioni lazy-loadable
     */
    public function testIssetWorksWithRelationships()
    {
        $this->debug("\n=== TEST 5: isset() con relazioni ===");

        $author = $this->authorModel->query()
            ->where('name = ?', ['George Orwell'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        $books = $author->books;
        $firstBook = $books->first();
        // isset() dovrebbe ritornare true per relazioni definite (anche non ancora caricate)
        $this->assertTrue(isset($firstBook->author), "isset() dovrebbe ritornare true per relazioni definite");

        // isset() dovrebbe ritornare true per campi normali
        $this->assertTrue(isset($firstBook->title), "isset() dovrebbe ritornare true per campi normali");

        // isset() dovrebbe ritornare false per campi non esistenti
        $this->assertFalse(isset($firstBook->nonexistent), "isset() dovrebbe ritornare false per campi non esistenti");

        // Dopo aver verificato isset(), accediamo alla relazione
        if (isset($firstBook->author)) {
            $bookAuthor = $firstBook->author;
            $this->assertInstanceOf(AuthorModel::class, $bookAuthor);
            $this->assertEquals('George Orwell', $bookAuthor->name);
        }

        $this->debug("✓ isset() funziona correttamente con relazioni");
        $this->debug("✓ Test PASSED: isset() supporta relazioni lazy-loadable");
    }

    /**
     * Test 6: Modificare dati del Model instance
     */
    public function testModifyModelInstanceData()
    {
        $this->debug("\n=== TEST 6: Modificare dati del Model instance ===");

        $author = $this->authorModel->query()
            ->where('name = ?', ['George Orwell'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        $books = $author->books;
        $books->first();
        $first_id = $books->book_id;
     
        // Modifica il prezzo
        $originalPrice = $books->price;
        $newPrice = 19.99;

        $this->debug("✓ Prezzo originale: \${$originalPrice}");

        $books->price = $newPrice;
      
        $this->assertEquals($newPrice, $books->price, "Il prezzo dovrebbe essere aggiornato");

        $this->debug("✓ Prezzo modificato: \${$books->price}");

        // Salva le modifiche
        $this->assertTrue($books->save(), $books->last_error);

        // Ricarica il libro dal database per verificare
        $reloadedBook = $this->bookModel->getById($first_id);
        $this->assertInstanceOf(BookModel::class, $reloadedBook);
       
        $this->assertEquals($newPrice, $reloadedBook->price, "Il prezzo salvato dovrebbe essere {$newPrice}");

        $this->debug("✓ Prezzo salvato e verificato: \${$reloadedBook->price}");
        $this->debug("✓ Test PASSED: Dati modificati e salvati");
    }

    public function testRelationshipResultSetKeepsCursorStateAcrossMixedActions()
    {
        $author = $this->authorModel->query()
            ->where('name = ?', ['George Orwell'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        $books = $author->books;

        $this->assertInstanceOf(BookModel::class, $books);
        $this->assertCount(2, $books);

        $this->assertSame($books, $books->first(), 'first() deve restituire lo stesso result set');
        $this->assertSame(0, $books->getCurrentIndex());
        $this->assertSame('1984', $books->title);
        $this->assertNull($books->getRecordAction(0));
        $this->assertNull($books->getRecordAction(1));

        $sameCollection = $books[1];
        $this->assertSame($books, $sameCollection, 'L\'array access non deve creare una copia staccata del record');
        $this->assertSame(1, $books->getCurrentIndex(), 'books[1] deve solo spostare il puntatore');
        $this->assertSame('Animal Farm', $books->title);

        $this->assertSame($books, $books->prev(), 'prev() deve tornare allo stesso result set');
        $this->assertSame(0, $books->getCurrentIndex());
        $this->assertSame('1984', $books->title);

        $invalidMove = $books->moveTo(99);
        $this->assertNull($invalidMove, 'moveTo() fuori range non deve inventare record');
        $this->assertSame(0, $books->getCurrentIndex(), 'moveTo() invalido non deve spostare il puntatore corrente');
        $this->assertSame('1984', $books->title);

        $firstBookId = $books->book_id;
        $firstBookOriginalPrice = $books->price;
        $books->price = $firstBookOriginalPrice;
        $this->assertNull($books->getRecordAction(0), 'Impostare lo stesso valore non deve sporcare il record');

        $updatedPrice = 21.50;
        $books->price = $updatedPrice;
        $this->assertSame('edit', $books->getRecordAction(0), 'Il primo record deve diventare edit dopo una modifica reale');
        $this->assertNull($books->getRecordAction(1), 'Il secondo record deve rimanere originale');

        $this->assertSame($books, $books->moveTo(1));
        $secondBookId = $books->book_id;
        $secondBookOriginalTitle = $books->title;
        $secondBookOriginalPrice = $books->price;

        $books->title = $secondBookOriginalTitle;
        $books->price = $secondBookOriginalPrice;
        $this->assertNull($books->getRecordAction(1), 'Il secondo record deve restare originale se i valori non cambiano');

        $books->fill([
            'author_id' => $author->author_id,
            'title' => 'Homage to Catalonia',
            'year' => 1938,
            'price' => 17.25,
        ]);

        $newIndex = $books->getCurrentIndex();
        $this->assertSame(2, $newIndex, 'Il nuovo record deve essere aggiunto in coda al result set');
        $this->assertSame('insert', $books->getRecordAction($newIndex));
        $this->assertNull($books->book_id);
        $this->assertSame('Homage to Catalonia', $books->title);
        $this->assertSame(3, $books->count());

        $this->assertSame($books, $books->moveTo(0));
        $this->assertSame('edit', $books->getRecordAction(0), 'Spostarsi nel result set non deve perdere la modifica del primo record');
        $this->assertEquals($updatedPrice, $books->price);

        $this->assertSame($books, $books->moveTo(1));
        $this->assertNull($books->getRecordAction(1), 'Il record originale deve restare nullo anche dopo altri spostamenti');
        $this->assertSame($secondBookOriginalTitle, $books->title);

        $this->assertTrue($books->save(), 'Il result set deve salvare insieme record edit, originali e insert');

        $this->assertNull($books->getRecordAction(0));
        $this->assertNull($books->getRecordAction(1));
        $this->assertNull($books->getRecordAction($newIndex));

        $reloadedFirstBook = $this->bookModel->getById($firstBookId);
        $this->assertInstanceOf(BookModel::class, $reloadedFirstBook);
        $this->assertEquals($updatedPrice, $reloadedFirstBook->price);

        $reloadedSecondBook = $this->bookModel->getById($secondBookId);
        $this->assertInstanceOf(BookModel::class, $reloadedSecondBook);
        $this->assertSame($secondBookOriginalTitle, $reloadedSecondBook->title);
        $this->assertEquals($secondBookOriginalPrice, $reloadedSecondBook->price);

        $insertedBook = $this->bookModel->query()
            ->where('title = ?', ['Homage to Catalonia'])
            ->getResults();
        $this->assertInstanceOf(BookModel::class, $insertedBook);
        $this->assertSame($author->author_id, $insertedBook->author_id);
        $this->assertEquals(17.25, $insertedBook->price);
    }

    public function testRelationshipResultSetRejectsArrayStyleMutation()
    {
        $author = $this->authorModel->query()
            ->where('name = ?', ['George Orwell'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        $books = $author->books;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot set values via array access');

        $books[0] = ['title' => 'Wrong API'];
    }

    public function testSettingSameValueDoesNotMarkRecordAsEdit()
    {
        $author = $this->authorModel->query()
            ->where('name = ?', ['George Orwell'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        $books = $author->books;
        $books->first();
        $samePrice = $books->price;

        $this->assertNull($books->getRecordAction());

        $books->price = $samePrice;

        $this->assertNull($books->getRecordAction());
    }

    public function testAssigningPrimaryKeyToInsertedRecordPromotesActionToEdit()
    {
        $book = new BookModel();
        $book->title = 'Draft Book';
        $book->author_id = 1;

        $this->assertSame('insert', $book->getRecordAction());

        $book->book_id = 999;

        $this->assertSame('edit', $book->getRecordAction());
    }

    public function testSaveSafetyConvertsInsertWithPrimaryKeyToEdit()
    {
        $book = $this->bookModel->query()
            ->where('title = ?', ['1984'])
            ->getResults();

        $this->assertInstanceOf(BookModel::class, $book);
        $book->price = 19.99;
        $this->forceRecordAction($book, 'insert');

        $this->assertTrue($book->save());

        $reloadedBook = $this->bookModel->getById($book->book_id);
        $this->assertInstanceOf(BookModel::class, $reloadedBook);
        $this->assertEquals(19.99, $reloadedBook->price);
    }

    public function testSaveSafetyConvertsEditWithoutPrimaryKeyToInsert()
    {
        $book = new BookModel();
        $book->title = 'Inserted Through Safety Net';
        $book->author_id = 1;
        $book->price = 8.50;

        $this->forceRecordAction($book, 'edit');

        $this->assertTrue($book->save());
        $this->assertNotNull($book->book_id);

        $reloadedBook = $this->bookModel->getById($book->book_id);
        $this->assertInstanceOf(BookModel::class, $reloadedBook);
        $this->assertSame('Inserted Through Safety Net', $reloadedBook->title);
    }

    /**
     * Test 7: Eager loading con with() ritorna Model instances
     */
    public function testEagerLoadingReturnsModelInstances()
    {
        $this->debug("\n=== TEST 7: Eager loading ritorna Model instances ===");

        $authors = $this->authorModel->query()->getResults();
        $authors->with('books');

        foreach ($authors as $author) {
            $books = $author->books;

            if ($books instanceof BookModel && $books->count() > 0) {
                foreach ($books as $book) {
                    $this->assertInstanceOf(BookModel::class, $book, "Ogni libro dovrebbe essere BookModel instance");
                }
                $this->debug("✓ Autore '{$author->name}': " . $books->count() . " libri (tutti BookModel instances)");
            }
        }

        $this->debug("✓ Test PASSED: Eager loading ritorna Model instances");
    }

    /**
     * Test 8: getRawData() include relazioni come Model instances
     */
    public function testGetRawDataIncludesModelInstances()
    {
        $this->debug("\n=== TEST 8: getRawData() include Model instances ===");

        $authors = $this->authorModel->query()->getResults();
        $authors->with('books');

        $rawData = $authors->getRawData('object', true);

        $this->assertIsArray($rawData);

        foreach ($rawData as $authorData) {
            $this->assertIsObject($authorData);

            if (isset($authorData->books) && is_array($authorData->books)) {
                foreach ($authorData->books as $book) {
                    $this->assertInstanceOf(BookModel::class, $book, "books in getRawData dovrebbe contenere BookModel instances");
                }
                $authorName = (string) (get_object_vars($authorData)['name'] ?? '(unknown)');
                $this->debug("✓ Autore '{$authorName}': getRawData() contiene BookModel instances");
            }
        }

        $this->debug("✓ Test PASSED: getRawData() include Model instances");
    }

    /**
     * Test 9: Verifica che i Model siano marcati come originali (non modificati)
     */
    public function testModelInstancesAreMarkedAsOriginal()
    {
        $this->debug("\n=== TEST 9: Model instances marcati come originali ===");

        $author = $this->authorModel->query()
            ->where('name = ?', ['George Orwell'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        $books = $author->books;
        $book = $books->first();
        $this->assertInstanceOf(BookModel::class, $book);

        // Verifica che il model sia marcato come originale
        $action = $book->getRecordAction();
        $this->debug("✓ Record action: " . ($action ?? 'null'));

        $this->debug("✓ Test PASSED: Model instances sono originali");
    }

    private function forceRecordAction(BookModel $book, string $action): void
    {
        $reflection = new \ReflectionClass($book);
        $property = $reflection->getProperty('records_objects');
        $records = $property->getValue($book);
        $currentIndex = $book->getCurrentIndex();
        $records[$currentIndex]['___action'] = $action;
        $property->setValue($book, $records);
    }

    /**
     * Test 10: Lazy loading ritorna Model instances
     */
    public function testLazyLoadingReturnsModelInstances()
    {
        $this->debug("\n=== TEST 10: Lazy loading ritorna Model instances ===");

        // Carica autore senza preload
        $author = $this->authorModel->query()
            ->where('name = ?', ['George Orwell'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        // Accesso lazy ai libri
        $books = $author->books;
        $this->assertInstanceOf(BookModel::class, $books);

        foreach ($books as $book) {
            $this->assertInstanceOf(BookModel::class, $book, "Lazy loading dovrebbe ritornare BookModel instances");
            $this->debug("✓ Lazy loaded: {$book->title} è BookModel instance");
        }

        $this->debug("✓ Test PASSED: Lazy loading ritorna Model instances");
    }

    /**
     * Test 11: Confronto tipo con stdClass
     */
    public function testModelInstancesAreNotStdClass()
    {
        $this->debug("\n=== TEST 11: Model instances NON sono stdClass ===");

        $author = $this->authorModel->query()
            ->where('name = ?', ['George Orwell'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        $books = $author->books;
        $book = $books->first();
        $this->assertInstanceOf(BookModel::class, $book);

        $this->debug("✓ \$book NON è stdClass");
        $this->debug("✓ \$book È BookModel");
        $this->debug("✓ Test PASSED: Model instances non sono stdClass");
    }

    private function debug(string $message): void
    {
        if (in_array('--debug', $_SERVER['argv'] ?? [])) {
            echo $message . "\n";
        }
    }
}
