<?php
/**
 * Run quickly from shell:
 * php vendor/bin/phpunit tests/Unit/Extensions/Projects/ListSearchFiltersConfiguratorDbCompatibilityTest.php
 */

declare(strict_types=1);

if (!defined('MILK_TEST_CONTEXT')) {
    define('MILK_TEST_CONTEXT', true);
}
if (!defined('MILK_API_CONTEXT')) {
    define('MILK_API_CONTEXT', true);
}

require_once dirname(__DIR__, 4) . '/public_html/milkadmin.php';
require_once MILK_DIR . '/autoload.php';

use Extensions\Projects\Classes\Renderers\ListSearchFiltersConfigurator;
use PHPUnit\Framework\TestCase;

final class ListSearchFiltersConfiguratorDbCompatibilityTest extends TestCase
{
    private object $configurator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurator = new class extends ListSearchFiltersConfigurator {
            public function exposeSearchAllFields(object $model): array
            {
                $method = new ReflectionMethod(self::class, 'resolveSearchAllFields');
                return $method->invoke($this, $model);
            }

            public function exposeBuildWhereCondition(
                object $db,
                array $fields,
                string $operator,
                string $value,
                ?object $model = null
            ): array {
                $method = new ReflectionMethod(self::class, 'buildWhereCondition');
                return $method->invoke($this, $db, $fields, $operator, $value, $model);
            }
        };
    }

    public function testSearchAllSkipsCrossDbRelationshipFields(): void
    {
        require_once dirname(__DIR__, 2) . '/App/Database/Fixtures/CrossDbAuthorModel.php';
        require_once dirname(__DIR__, 2) . '/App/Database/Fixtures/CrossDbBookModel.php';

        $model = new \Tests\Unit\App\Database\Fixtures\CrossDbBookModel();

        $fields = $this->configurator->exposeSearchAllFields($model);
        $relationFields = array_values(array_filter($fields, static fn($field) => str_contains((string) $field, '.')));

        $this->assertSame([], $relationFields);

        $where = $this->configurator->exposeBuildWhereCondition(
            $model->getDb(),
            ['created_by.username'],
            'like',
            'gi',
            $model
        );

        $this->assertSame('', $where['sql']);
        $this->assertSame([], $where['params']);
    }

    public function testSearchAllKeepsSameDbRelationshipFields(): void
    {
        require_once dirname(__DIR__, 2) . '/App/Database/Fixtures/AuthorModel.php';
        require_once dirname(__DIR__, 2) . '/App/Database/Fixtures/BookModel.php';

        $model = new \Tests\Unit\App\Database\Fixtures\BookModel();

        $fields = $this->configurator->exposeSearchAllFields($model);
        $this->assertContains('author.name', $fields);

        $where = $this->configurator->exposeBuildWhereCondition(
            $model->getDb(),
            ['author.name'],
            'like',
            'gi',
            $model
        );

        $this->assertStringContainsString('EXISTS', $where['sql']);
        $this->assertSame(['%gi%'], $where['params']);
    }
}
