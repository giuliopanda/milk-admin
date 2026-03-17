<?php
/**
 * Run quickly from shell:
 * php vendor/bin/phpunit tests/Unit/Builders/TableBuilderShowIfTest.php
 */

declare(strict_types=1);

namespace {
    if (!defined('MILK_TEST_CONTEXT')) {
        define('MILK_TEST_CONTEXT', true);
    }
    if (!defined('MILK_API_CONTEXT')) {
        define('MILK_API_CONTEXT', true);
    }

    $projectRoot = dirname(__DIR__, 3);
    require_once $projectRoot . '/public_html/milkadmin.php';
    require_once MILK_DIR . '/autoload.php';
}

namespace Tests\Unit\Builders\TableBuilder {
    use App\Abstracts\AbstractModel;
    use App\Get;
    use Builders\TableBuilder;
    use PHPUnit\Framework\TestCase;

    /**
     * @property int|null $ID
     * @property string|null $NAME
     * @property string|null $STATUS
     */
    final class ShowIfTestModel extends AbstractModel
    {
        public static string $tableName = 'tb_showif_test';

        protected function configure($rule): void
        {
            $rule->table(self::$tableName)
                ->db('array')
                ->id('ID')
                ->string('NAME', 50)
                ->string('STATUS', 20);
        }
    }

    final class TableBuilderShowIfTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            // Avoid cross-test contamination (DataProcessor reads $_REQUEST for filters)
            $_REQUEST = [];
            ShowIfTestModel::$tableName = 'tb_showif_' . uniqid('', true);
            // ArrayDb tables must be explicitly created in the in-memory engine
            Get::arrayDb()->addTable(ShowIfTestModel::$tableName, [], 'ID');
        }

        public function testShowIfSkipsFormatterWhenConditionIsFalse(): void
        {
            // Seed data
            $m = new ShowIfTestModel();
            $m->fill(['ID' => 1, 'NAME' => 'Alpha', 'STATUS' => 'show']);
            $this->assertTrue($m->save());

            $m = new ShowIfTestModel();
            $m->fill(['ID' => 2, 'NAME' => 'Beta', 'STATUS' => 'hide']);
            $this->assertTrue($m->save());

            $m = new ShowIfTestModel();
            $m->fill(['ID' => 3, 'NAME' => 'Gamma', 'STATUS' => 'show']);
            $this->assertTrue($m->save());

            $calls = 0;

            $table = TableBuilder::create(new ShowIfTestModel(), 'table_showif_test');
            $table
                ->field('NAME')
                ->type('html')
                ->fn(function (AbstractModel $list) use (&$calls): string {
                    if (!$list instanceof ShowIfTestModel) {
                        return 'CUSTOM-unknown';
                    }
                    $calls++;
                    return 'CUSTOM-' . $list->ID;
                })
                // Use lowercase param name on purpose to verify aliasing (STATUS -> status)
                ->showIf('[status] == "show"', '---');

            $data = $table->getData();
            $rows = $data['rows'] ?? [];

            $this->assertCount(3, $rows);

            // show rows: formatter executed
            $this->assertSame('CUSTOM-1', $rows[0]->NAME);
            $this->assertSame('CUSTOM-3', $rows[2]->NAME);

            // hide row: formatter skipped and placeholder used
            $this->assertSame('---', $rows[1]->NAME);

            // Formatter executed only for rows where showIf is true
            $this->assertSame(2, $calls);
        }
    }
}
