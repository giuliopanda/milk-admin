<?php
/**
 * Query and select methods coverage for AbstractModel.
 *
 * Run with:
 * php vendor/bin/phpunit tests/Unit/App/Database/SelectMethodsTest.php
 */

declare(strict_types=1);

namespace {
    if (!defined('MILK_TEST_CONTEXT')) {
        define('MILK_TEST_CONTEXT', true);
    }
    if (!defined('MILK_API_CONTEXT')) {
        define('MILK_API_CONTEXT', true);
    }

    $projectRoot = dirname(__DIR__, 4);
    require_once $projectRoot . '/public_html/milkadmin.php';
    require_once MILK_DIR . '/autoload.php';
    require_once __DIR__ . '/Fixtures/AuthorModel.php';
    require_once __DIR__ . '/Fixtures/BookModel.php';
}

namespace Tests\Unit\App\Database {
    use PHPUnit\Framework\TestCase;
    use Tests\Unit\App\Database\Fixtures\AuthorModel;
    use Tests\Unit\App\Database\Fixtures\BookModel;

    final class SelectMethodsTest extends TestCase
    {
        private AuthorModel $authorModel;
        private BookModel $bookModel;

        protected function setUp(): void
        {
            parent::setUp();

            $this->authorModel = new AuthorModel();
            $this->bookModel = new BookModel();

            $this->createTables();
            $this->insertTestData();
        }

        protected function tearDown(): void
        {
            try {
                $this->bookModel->dropTable();
                $this->authorModel->dropTable();
            } catch (\Throwable) {
                // Tables may already be gone during partial failures.
            }

            parent::tearDown();
        }

        public function testRetrievalHelpersReturnExpectedRecords(): void
        {
            $all = $this->authorModel->getAll();
            $byId = $this->authorModel->getById(1);
            $byIds = $this->authorModel->getByIds([1, 2, 3]);
            $first = $this->authorModel->getFirst();
            $firstDesc = $this->authorModel->getFirst('name', 'DESC');
            $empty = $this->authorModel->getEmpty(['name' => 'New Author']);
            $updated = $this->authorModel->getByIdAndUpdate(1, ['name' => 'Updated Name']);

            $this->assertInstanceOf(AuthorModel::class, $byId);
            $this->assertInstanceOf(AuthorModel::class, $first);
            $this->assertInstanceOf(AuthorModel::class, $firstDesc);
            $this->assertInstanceOf(AuthorModel::class, $empty);
            $this->assertInstanceOf(AuthorModel::class, $updated);

            $this->assertSame(5, $all->count());
            $this->assertSame('John Smith', $byId->name);
            $this->assertSame(3, $byIds->count());
            $this->assertSame('John Smith', $first->name);
            $this->assertSame('John Smith', $firstDesc->name);
            $this->assertSame('New Author', $empty->name);
            $this->assertSame('insert', $empty->getRecordAction());
            $this->assertSame('Updated Name', $updated->name);
            $this->assertSame('edit', $updated->getRecordAction());
        }

        public function testGetByIdAndUpdateWithSameValuesKeepsOriginalAction(): void
        {
            $unchanged = $this->authorModel->getByIdAndUpdate(1, ['name' => 'John Smith']);
            $this->assertInstanceOf(AuthorModel::class, $unchanged);

            $this->assertSame('John Smith', $unchanged->name);
            $this->assertNull($unchanged->getRecordAction());
        }

        public function testLoadedRecordReturnsToNullActionWhenFieldIsRestored(): void
        {
            $author = $this->authorModel->getById(1);

            $this->assertInstanceOf(AuthorModel::class, $author);
            $this->assertNull($author->getRecordAction());

            $author->name = 'Temporary Change';
            $this->assertSame('edit', $author->getRecordAction());

            $author->name = 'John Smith';
            $this->assertNull($author->getRecordAction());
        }

        public function testGetByIdAndUpdatePreservesDatabaseFieldsAndAppliesTemporaryFormValues(): void
        {
            $merged = $this->authorModel->getByIdAndUpdate(1, [
                'country' => 'Spain',
            ]);
            $this->assertInstanceOf(AuthorModel::class, $merged);

            $this->assertSame('John Smith', $merged->name);
            $this->assertSame('Spain', $merged->country);
            $this->assertSame(1970, $merged->birth_year);
            $this->assertSame('edit', $merged->getRecordAction());
        }

        public function testFilteringOrderingAndPaginationMethods(): void
        {
            $recentAuthors = $this->authorModel
                ->query()
                ->where('birth_year > ?', [1980])
                ->order('name', 'ASC')
                ->getResults();

            $selected = $this->authorModel
                ->query()
                ->select(['author_id', 'name'])
                ->order('author_id', 'ASC')
                ->limit(0, 2)
                ->getResults();

            $pageTwo = $this->authorModel
                ->query()
                ->order('author_id', 'ASC')
                ->limit(2, 2)
                ->getResults();

            $whereIn = $this->authorModel
                ->query()
                ->whereIn('author_id', [1, 2, 3])
                ->getResults();

            $this->assertSame(2, $recentAuthors->count());
            $recentFirst = $recentAuthors->first();
            $this->assertInstanceOf(AuthorModel::class, $recentFirst);
            $this->assertSame('Alice Williams', $recentFirst->name);
            $this->assertSame(2, $selected->count());
            $this->assertSame(2, $pageTwo->count());
            $this->assertSame(3, $whereIn->count());
            $this->assertSame(5, $this->authorModel->total());
        }

        public function testNavigationAndFormattingHelpersWorkOnResultSets(): void
        {
            $results = $this->authorModel
                ->query()
                ->order('name', 'ASC')
                ->getResults();

            $first = $results->first();
            $this->assertInstanceOf(AuthorModel::class, $first);
            $this->assertSame('Alice Williams', $first->name);
            $this->assertTrue($results->hasNext());

            $moved = $results->moveNext();
            $this->assertInstanceOf(AuthorModel::class, $moved);
            $this->assertSame('Bob Johnson', $moved->name);

            $last = $results->last();
            $this->assertInstanceOf(AuthorModel::class, $last);
            $this->assertSame('John Smith', $last->name);
            $this->assertTrue($results->hasPrev());

            $previous = $results->prev();
            $this->assertInstanceOf(AuthorModel::class, $previous);
            $this->assertSame('Jane Doe', $previous->name);

            $jumped = $results->moveTo(1);
            $this->assertInstanceOf(AuthorModel::class, $jumped);
            $this->assertSame('Bob Johnson', $jumped->name);
            $this->assertSame(1, $results->getCurrentIndex());
            $results->setOutputMode('raw');
            $rawData = $results->getRawData('array');
            $results->setOutputMode('formatted');
            $formatted = $results->getFormattedData();

            $this->assertIsArray($rawData);
            $this->assertIsArray($rawData[0] ?? null);
            $this->assertArrayHasKey('name', $rawData[0]);
            $this->assertSame('Alice Williams', $rawData[0]['name']);
            $this->assertIsArray($formatted);
            $this->assertSame('formatted', $results->getOutputMode());
        }

        public function testRelationshipMethodsAndQueryIntrospectionWork(): void
        {
            $authors = $this->authorModel->query()->order('author_id', 'ASC')->getResults();
            $authors->with('books');

            $this->assertTrue(isset($authors[0]->books));
            $this->assertTrue(isset($authors[1]->books));

            $withAll = $this->authorModel->query()->order('author_id', 'ASC')->getResults();
            $withAll->with();
            $this->assertTrue(isset($withAll[0]->books));

            $whereHas = $this->authorModel
                ->query()
                ->whereHas('books', 'year > ?', [2000])
                ->getResults();

            $orderedByRelated = $this->authorModel
                ->query()
                ->orderHas('books', 'title', 'ASC')
                ->getResults();

            $query = $this->authorModel
                ->query()
                ->where('country = ?', ['USA'])
                ->order('name', 'ASC')
                ->limit(0, 2);

            [$sql, $params] = $query->get();

            $this->assertSame(2, $whereHas->count());
            $this->assertSame(5, $orderedByRelated->count());
            $this->assertStringContainsString('SELECT', strtoupper($sql));
            $this->assertStringContainsString('SELECT', strtoupper($query->toSql()));
            $this->assertTrue($query->hasWhere());
            $this->assertTrue($query->hasOrder());
            $this->assertTrue($query->hasLimit());
        }

        public function testRequestStyleQueryParamsCanBeApplied(): void
        {
            $results = $this->authorModel
                ->setQueryParams([
                    'limit_start' => 0,
                    'limit' => 2,
                ])
                ->getResults();

            $this->assertSame(2, $results->count());
        }

        private function createTables(): void
        {
            try {
                $this->bookModel->dropTable();
                $this->authorModel->dropTable();
            } catch (\Throwable) {
                // Ignore missing tables on a clean database.
            }

            $this->assertTrue($this->authorModel->buildTable(), $this->authorModel->last_error);
            $this->assertTrue($this->bookModel->buildTable(), $this->bookModel->last_error);
        }

        private function insertTestData(): void
        {
            $authors = [
                ['name' => 'John Smith', 'country' => 'USA', 'birth_year' => 1970],
                ['name' => 'Jane Doe', 'country' => 'UK', 'birth_year' => 1985],
                ['name' => 'Bob Johnson', 'country' => 'Canada', 'birth_year' => 1960],
                ['name' => 'Alice Williams', 'country' => 'Australia', 'birth_year' => 1990],
                ['name' => 'Charlie Brown', 'country' => 'USA', 'birth_year' => 1975],
            ];

            foreach ($authors as $authorData) {
                $author = new AuthorModel();
                $author->name = $authorData['name'];
                $author->country = $authorData['country'];
                $author->birth_year = $authorData['birth_year'];
                $author->save();
            }

            $books = [
                ['title' => 'Mystery Novel', 'author_id' => 1, 'year' => 2000],
                ['title' => 'Romance Story', 'author_id' => 2, 'year' => 2010],
                ['title' => 'Sci-Fi Adventure', 'author_id' => 3, 'year' => 1995],
                ['title' => 'Fantasy Epic', 'author_id' => 4, 'year' => 2015],
            ];

            foreach ($books as $bookData) {
                $book = new BookModel();
                $book->title = $bookData['title'];
                $book->author_id = $bookData['author_id'];
                $book->year = $bookData['year'];
                $book->save();
            }
        }
    }
}
