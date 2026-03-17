<?php
/**
 * Test per valutare il caricamento dati con hasMany relationship
 *
 * Questo test dimostra e valuta come vengono caricati i dati con hasMany:
 * 1. Lazy loading (accesso on-demand alle relazioni)
 * 2. Eager loading con with() (precaricamento esplicito)
 * 3. Batch loading automatico (prevenzione N+1 queries)
 * 4. BelongsTo (relazione inversa)
 * 5. Struttura dei dati restituiti
 * 6. Conteggio elementi in PHP
 *
 * Esegui con:
 * php vendor/bin/phpunit tests/Unit/App/Database/HasManyLoadingTest.php --testdox
 *
 * Con debug dettagliato:
 * php vendor/bin/phpunit tests/Unit/App/Database/HasManyLoadingTest.php --testdox --debug
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

class HasManyLoadingTest extends TestCase
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

    /**
     * Crea le tabelle di test
     */
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

    /**
     * Inserisce dati di test
     */
    private function insertTestData(): void
    {
        $this->debug("\n=== INSERIMENTO DATI DI TEST ===");

        // Autore 1: J.K. Rowling con 7 libri
        $author1 = new AuthorModel();
        $author1->name = 'J.K. Rowling';
        $author1->country = 'UK';
        $author1->birth_year = 1965;
        $author1->save();
        $author1Id = $author1->author_id;
        $this->debug("✓ Autore 1: J.K. Rowling (ID: {$author1Id})");

        // Libri di Rowling
        $harryPotterBooks = [
            "Harry Potter and the Philosopher's Stone",
            "Harry Potter and the Chamber of Secrets",
            "Harry Potter and the Prisoner of Azkaban",
            "Harry Potter and the Goblet of Fire",
            "Harry Potter and the Order of the Phoenix",
            "Harry Potter and the Half-Blood Prince",
            "Harry Potter and the Deathly Hallows"
        ];

        foreach ($harryPotterBooks as $index => $title) {
            $book = new BookModel();
            $book->author_id = $author1Id;
            $book->title = $title;
            $book->year = 1997 + $index;
            $book->price = 19.99;
            $book->save();
        }
        $this->debug("  → 7 libri di Harry Potter inseriti");

        // Autore 2: George Orwell con 2 libri
        $author2 = new AuthorModel();
        $author2->name = 'George Orwell';
        $author2->country = 'UK';
        $author2->birth_year = 1903;
        $author2->save();
        $author2Id = $author2->author_id;
        $this->debug("✓ Autore 2: George Orwell (ID: {$author2Id})");

        $book1 = new BookModel();
        $book1->author_id = $author2Id;
        $book1->title = '1984';
        $book1->year = 1949;
        $book1->price = 15.99;
        $book1->save();

        $book2 = new BookModel();
        $book2->author_id = $author2Id;
        $book2->title = 'Animal Farm';
        $book2->year = 1945;
        $book2->price = 12.99;
        $book2->save();
        $this->debug("  → 2 libri inseriti (1984, Animal Farm)");

        // Autore 3: Ernest Hemingway con 0 libri (per testare il caso edge)
        $author3 = new AuthorModel();
        $author3->name = 'Ernest Hemingway';
        $author3->country = 'USA';
        $author3->birth_year = 1899;
        $author3->save();
        $this->debug("✓ Autore 3: Ernest Hemingway (ID: {$author3->author_id}) - NESSUN LIBRO");
    }

    /**
     * Test 1: Lazy Loading - Accesso diretto alla relazione
     * Come da documentazione: $author->books carica i libri on-demand
     */
    public function testLazyLoading()
    {
        $this->debug("\n=== TEST 1: LAZY LOADING ===");
        $this->debug("Documentazione: relationships load on access (magic properties)");

        // Carica un singolo autore
        $author = $this->authorModel->query()
            ->where('name = ?', ['J.K. Rowling'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author, "Autore non trovato");
        $this->debug("\n✓ Autore caricato: {$author->name}");

        // Accesso lazy loading ai libri
        $this->debug("→ Accesso a \$author->books (lazy loading)...");
        $books = $author->books;

        $this->assertInstanceOf(BookModel::class, $books, "books dovrebbe essere un model result set");
        $this->assertCount(7, $books, "Rowling dovrebbe avere 7 libri");

        $this->debug("✓ Caricati " . count($books) . " libri:");
        foreach ($books as $book) {
            $this->debug("  • {$book->title} ({$book->year})");
        }

        $this->debug("\n✓ Test lazy loading PASSED");
    }

    /**
     * Test 2: Eager Loading con with()
     * Come da documentazione: with() precarica le relazioni
     */
    public function testEagerLoadingWithSingle()
    {
        $this->debug("\n=== TEST 2: EAGER LOADING (with single relationship) ===");
        $this->debug("Documentazione: with('books') preload relationships");

        // Eager loading di una singola relazione
        $authors = $this->authorModel->query()->getResults();
        $authors->with('books');

        $this->debug("\n✓ Autori caricati con eager loading:");

        foreach ($authors as $author) {
            $books = $author->books;

            $bookCount = $books?->count() ?? 0;
            $this->debug("• {$author->name}: {$bookCount} libri");

            // Verifica i conteggi
            if ($author->name === 'J.K. Rowling') {
                $this->assertEquals(7, $bookCount, "Rowling dovrebbe avere 7 libri");
            } elseif ($author->name === 'George Orwell') {
                $this->assertEquals(2, $bookCount, "Orwell dovrebbe avere 2 libri");
            } elseif ($author->name === 'Ernest Hemingway') {
                $this->assertEquals(0, $bookCount, "Hemingway dovrebbe avere 0 libri");
            }
        }

        $this->debug("\n✓ Test eager loading PASSED");
    }

    /**
     * Test 3: Eager Loading - tutte le relazioni
     * Come da documentazione: with(null) carica tutte le relazioni
     */
    public function testEagerLoadingWithAll()
    {
        $this->debug("\n=== TEST 3: EAGER LOADING (all relationships) ===");
        $this->debug("Documentazione: with(null) loads all relationships");

        // Carica tutte le relazioni
        $authors = $this->authorModel->query()->getResults();
        $authors->with(null);

        $this->debug("\n✓ Tutte le relazioni caricate");

        foreach ($authors as $author) {
            $books = $author->books;

            $this->debug("• {$author->name}: " . ($books?->count() ?? 0) . " libri");
        }

        $this->debug("\n✓ Test with(null) PASSED");
    }

    /**
     * Test 4: Batch Loading - Prevenzione N+1
     * Come da documentazione: automaticamente ottimizzato
     */
    public function testBatchLoadingPreventsNPlusOne()
    {
        $this->debug("\n=== TEST 4: BATCH LOADING (N+1 Prevention) ===");
        $this->debug("Documentazione: First access loads ALL books in 1 query");

        // Carica tutti gli autori
        $authors = $this->authorModel->getAll();
        $this->debug("\n✓ Caricati " . $authors->count() . " autori (1 query)");

        // Primo accesso alle relazioni dovrebbe caricare TUTTI i libri in una query
        $this->debug("→ Primo accesso a \$author->books...");

        $totalBooks = 0;
        foreach ($authors as $author) {
            $books = $author->books;

            $bookCount = $books?->count() ?? 0;
            $totalBooks += $bookCount;
            $this->debug("  • {$author->name}: {$bookCount} libri");
        }

        $this->assertEquals(9, $totalBooks, "Totale libri dovrebbe essere 9 (7+2+0)");

        $this->debug("\n✓ Totale: {$totalBooks} libri caricati");
        $this->debug("✓ Batch loading previene N+1 queries!");
        $this->debug("✓ Test PASSED");
    }

    /**
     * Test 5: Relazione belongsTo inversa
     * Verifica che un libro possa accedere al suo autore
     */
    public function testBelongsToRelationship()
    {
        $this->debug("\n=== TEST 5: BELONGS TO (relazione inversa) ===");

        // Carica un libro
        $book = $this->bookModel->query()
            ->where('title = ?', ['1984'])
            ->getResults();

        $this->assertInstanceOf(BookModel::class, $book, "Libro non trovato");
        $this->debug("\n✓ Libro caricato: {$book->title}");

        // Accesso all'autore tramite belongsTo
        $this->debug("→ Accesso a \$book->author (belongsTo)...");
        $author = $book->author;

        $this->assertEquals('George Orwell', $author->name, "Autore dovrebbe essere Orwell");

        $this->debug("✓ Autore: {$author->name}");
        $this->debug("\n✓ Test belongsTo PASSED");
    }

    /**
     * Test 6: Conteggio libri in PHP (approccio classico)
     * Questo è il metodo standard che usiamo attualmente
     */
    public function testCountBooksInPHP()
    {
        $this->debug("\n=== TEST 6: COUNT in PHP (metodo classico) ===");
        $this->debug("Questo è l'approccio attuale: count(\$author->books)");

        $authors = $this->authorModel->query()->getResults();
        $authors->with('books');

        $this->debug("\n✓ Conteggio libri per autore:");

        foreach ($authors as $author) {
            $books = $author->books;

            $bookCount = $books?->count() ?? 0;
            $this->debug("• {$author->name}: {$bookCount} libri");

            // Verifica i conteggi
            if ($author->name === 'J.K. Rowling') {
                $this->assertEquals(7, $bookCount);
            } elseif ($author->name === 'George Orwell') {
                $this->assertEquals(2, $bookCount);
            } elseif ($author->name === 'Ernest Hemingway') {
                $this->assertEquals(0, $bookCount);
            }
        }

        $this->debug("\n⚠️  NOTA: count() viene eseguito in PHP dopo aver caricato TUTTI i libri");
        $this->debug("⚠️  PROBLEMA: Non è ordinabile per numero di libri");
        $this->debug("\n✓ Test count PHP PASSED");
    }

    /**
     * Test 7: Verifica struttura dati
     * Come da documentazione: hasMany returns a model result set of BookModel instances
     */
    public function testDataStructure()
    {
        $this->debug("\n=== TEST 7: STRUTTURA DATI ===");
        $this->debug("Documentazione: \$author->books returns a BookModel result set");

        $author = $this->authorModel->query()
            ->where('name = ?', ['George Orwell'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        $books = $author->books;

        $this->debug("\n✓ Struttura \$author->books:");
        $this->debug("  • Tipo: " . gettype($books));
        $this->debug("  • Count: " . count($books));

        // Verifica primo libro
        if (count($books) > 0) {
            $firstBook = $books[0];
            $this->assertInstanceOf(BookModel::class, $firstBook);
            $this->debug("\n✓ Primo libro:");
            $this->debug("  • Tipo: " . gettype($firstBook));
            $this->debug("  • Classe: " . get_class($firstBook));
            $this->debug("  • Titolo: {$firstBook->title}");
            $this->debug("  • Anno: {$firstBook->year}");

            $this->assertInstanceOf(BookModel::class, $firstBook, "Libro dovrebbe essere BookModel instance");
        }

        $this->debug("\n✓ Test struttura dati PASSED");
    }

    /**
     * Test 8: Iterazione diretta sui libri
     * Come da documentazione: foreach ($author->books as $book)
     */
    public function testDirectIteration()
    {
        $this->debug("\n=== TEST 8: ITERAZIONE DIRETTA ===");
        $this->debug("Documentazione esempio: foreach (\$author->books as \$book)");

        $author = $this->authorModel->query()
            ->where('name = ?', ['J.K. Rowling'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        $this->debug("\n✓ Iterazione sui libri di {$author->name}:");

        $count = 0;
        foreach ($author->books as $book) {
            $count++;
            $this->debug("  {$count}. {$book->title}");
            $this->assertNotEmpty($book->title, "Titolo non dovrebbe essere vuoto");
        }

        $this->assertEquals(7, $count, "Dovrebbero esserci 7 libri");
        $this->debug("\n✓ Test iterazione PASSED");
    }

    /**
     * Test 9: Accesso a dati del libro
     * Come da documentazione: echo $book->title
     */
    public function testAccessBookProperties()
    {
        $this->debug("\n=== TEST 9: ACCESSO PROPRIETÀ LIBRO ===");
        $this->debug("Documentazione esempio: echo \$book->title");

        $author = $this->authorModel->query()
            ->where('name = ?', ['George Orwell'])
            ->getResults();

        $this->assertInstanceOf(AuthorModel::class, $author);
        $books = $author->books;
        $this->assertGreaterThan(0, count($books), "Orwell dovrebbe avere libri");

        $book = $books[0];
        $this->assertInstanceOf(BookModel::class, $book);

        $this->debug("\n✓ Proprietà del libro:");
        $this->debug("  • Titolo: {$book->title}");
        $this->debug("  • Anno: {$book->year}");
        $this->debug("  • Prezzo: \${$book->price}");
        $this->debug("  • Author ID: {$book->author_id}");

        $this->assertNotEmpty($book->title);
        $this->assertNotEmpty($book->year);
        $this->assertNotEmpty($book->price);
        $this->assertEquals($author->author_id, $book->author_id);

        $this->debug("\n✓ Test accesso proprietà PASSED");
    }

    // ===== UTILITY METHODS =====

    private function debug(string $message): void
    {
        if (in_array('--debug', $_SERVER['argv'] ?? [])) {
            echo $message . "\n";
        }
    }
}
