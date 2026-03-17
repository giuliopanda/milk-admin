<?php
/**
 * Test for withCount() functionality
 *
 * Run with:
 * php vendor/bin/phpunit tests/Unit/App/Database/HasManyCountTest.php
 *
 * Documentation: milkadmin/Modules/Docs/Pages/Developer/AbstractsClass/abstract-model-relationships.page.php
 */

require_once dirname(__DIR__, 4) . '/public_html/milkadmin.php';
require_once MILK_DIR . '/autoload.php';

use PHPUnit\Framework\TestCase;
use App\Abstracts\AbstractModel;

class BookModel extends AbstractModel
{
    protected function configure($rule): void {
        $rule->table('test_books')
            ->id('book_id')
            ->string('title', 255)
            ->int('author_id')
            ->string('status', 20); // 'published', 'draft', 'deleted'
    }

    // Default scope: only non-deleted books
    #[App\Attributes\DefaultQuery]
    protected function onlyActive($query) {
        return $query->where('status != ?', ['deleted']);
    }
}

class AuthorWithCountModel extends AbstractModel
{
    protected function configure($rule): void {
        $rule->table('test_authors')
            ->id('author_id')
            ->withCount('books_count', BookModel::class, 'author_id')
            ->string('name', 100)
            ->string('country', 100)
            ->string('birth_year', 4);
            // Define withCount: adds a COUNT subquery for books
           
    }
}

class HasManyCountTest extends TestCase
{
    private $db;
    private $author_model;
    private $book_model;

    protected function setUp(): void
    {
        $this->db = App\Get::db();
        $this->author_model = new AuthorWithCountModel();
        $this->book_model = new BookModel();

        try {
            $this->book_model->dropTable();
            $this->author_model->dropTable();
        } catch (\Throwable) {
            // Fresh database or already-clean state.
        }

        // Create tables
        $this->author_model->buildTable();
        $this->book_model->buildTable();

        // Insert test data
        $this->insertTestData();
    }

    protected function tearDown(): void
    {
        try {
            $this->book_model->dropTable();
            $this->author_model->dropTable();
        } catch (\Throwable) {
            // Ignore cleanup errors during partial failures.
        }
    }

    private function insertTestData()
    {
        // Clean tables
        $this->db->query("DELETE FROM test_authors");
        $this->db->query("DELETE FROM test_books");

        // Author 1: J.K. Rowling with 3 published books + 1 deleted
        $this->db->query("INSERT INTO test_authors (author_id, name, country, birth_year) VALUES (1, 'J.K. Rowling', 'UK', 1965)");
        $this->db->query("INSERT INTO test_books (title, author_id, status) VALUES ('Book 1', 1, 'published')");
        $this->db->query("INSERT INTO test_books (title, author_id, status) VALUES ('Book 2', 1, 'published')");
        $this->db->query("INSERT INTO test_books (title, author_id, status) VALUES ('Book 3', 1, 'published')");
        $this->db->query("INSERT INTO test_books (title, author_id, status) VALUES ('Deleted Book', 1, 'deleted')");

        // Author 2: George Orwell with 1 published book
        $this->db->query("INSERT INTO test_authors (author_id, name, country, birth_year) VALUES (2, 'George Orwell', 'UK', 1903)");
        $this->db->query("INSERT INTO test_books (title, author_id, status) VALUES ('1984', 2, 'published')");

        // Author 3: Ernest Hemingway with 0 books
        $this->db->query("INSERT INTO test_authors (author_id, name, country, birth_year) VALUES (3, 'Ernest Hemingway', 'USA', 1899)");
    }

    public function test_withCount_adds_count_field()
    {
        // Get author 1 (should have 3 published books, 4 total)
        $author = $this->author_model->getById(1);

        // Verify books_count field is accessible (via __get magic method)
        $this->assertNotNull($author->books_count ?? null, 'books_count should be accessible');

        // Verify count is correct (3 published, 1 deleted excluded by scope)
        $this->assertEquals(3, $author->books_count);
    }

    public function test_withCount_applies_related_model_scopes()
    {
        // BookModel has onlyActive scope that excludes deleted books
        // Author 1 has 4 total books, but 1 is deleted
        // So books_count should be 3, not 4

        $author = $this->author_model->getById(1);

        // Verify scope is applied (excludes deleted book)
        $this->assertEquals(3, $author->books_count, 'Should count only non-deleted books');

        // Verify by getting actual count without scope
        $total_books = $this->book_model
            ->withoutGlobalScopes()
            ->where('author_id = ?', [1])
            ->getResults()
            ->count();

        $this->assertEquals(4, $total_books, 'Total books should be 4 (including deleted)');
    }

    public function test_withCount_can_be_disabled()
    {
        // Disable the books_count withCount
        $author = $this->author_model
            ->withoutGlobalScope('withCount:books_count')
            ->getById(1);

        // books_count should not be in the result
        $this->assertNull($author->books_count ?? null, 'books_count should not be present when scope is disabled');
    }

    public function test_withCount_with_zero_count()
    {
        // Get author 3 (has 0 books)
        $author = $this->author_model->getById(3);

        // Verify books_count is 0
        $this->assertEquals(0, $author->books_count);
    }

    public function test_withCount_works_with_getAll()
    {
        // Get all authors with book counts
        $authors = $this->author_model->getAll();

        // Verify all authors have books_count accessible
        foreach ($authors as $author) {
            $this->assertNotNull($author->books_count ?? null, 'books_count should be accessible for all authors');
        }

        // Verify counts
        $this->assertEquals(3, $authors[0]->books_count, 'Author 1 should have 3 books');
        $this->assertEquals(1, $authors[1]->books_count, 'Author 2 should have 1 book');
        $this->assertEquals(0, $authors[2]->books_count, 'Author 3 should have 0 books');
    }
}
