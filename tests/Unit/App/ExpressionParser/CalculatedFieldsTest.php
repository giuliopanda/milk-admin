<?php
/**
 * Run quickly from shell:
 * php vendor/bin/phpunit tests/Unit/App/ExpressionParser/CalculatedFieldsTest.php
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
}

namespace Tests\Unit\App\ExpressionParser {
    use App\Abstracts\AbstractModel;
    use App\MessagesHandler;
    use PHPUnit\Framework\TestCase;

    /**
     * @property int|null $ID
     * @property int|null $QTY
     * @property float|null $PRICE
     * @property float|null $TOTAL
     * @property string|null $STATUS
     * @property \DateTimeInterface|string|null $START_DATE
     * @property \DateTimeInterface|null $NEXT_DATE
     */
    class CalculatedFieldsModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_calculated_fields')
                ->db('array')
                ->id('ID')
                ->int('QTY')->label('Qty')
                ->decimal('PRICE', 10, 2)->label('Price')
                ->decimal('TOTAL', 10, 2)->label('Total')
                    ->calcExpr('[QTY] * [PRICE]')
                ->string('STATUS', 10)->label('Status')
                    ->calcExpr('IF [TOTAL] >= 100 THEN "BIG" ELSE "SMALL" ENDIF')
                ->date('START_DATE')->label('Start Date')
                ->date('NEXT_DATE')->label('Next Date')
                    ->calcExpr('[START_DATE] + 1');
        }
    }

    final class CalculatedFieldsTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            MessagesHandler::reset();
        }

        public function testCalculatedNumericAndIfFields(): void
        {
            $model = new CalculatedFieldsModel();
            $model->fill([
                'QTY' => 5,
                'PRICE' => 25.5,
                'START_DATE' => '2025-01-01'
            ]);

            $this->assertEquals(127.5, $model->TOTAL);
            $this->assertSame('BIG', $model->STATUS);
        }

        public function testCalculatedDateField(): void
        {
            $model = new CalculatedFieldsModel();
            $model->fill([
                'QTY' => 1,
                'PRICE' => 1,
                'START_DATE' => '2025-01-01'
            ]);

            $nextDate = $model->NEXT_DATE;
            $this->assertInstanceOf(\DateTimeInterface::class, $nextDate);
            $this->assertSame('2025-01-02', $nextDate->format('Y-m-d'));
        }

        public function testCalculatedFieldsDoNotMarkOriginalRecordWhenValueIsUnchanged(): void
        {
            $model = new CalculatedFieldsModel();
            $model->setRow([
                'ID' => 1,
                'QTY' => 5,
                'PRICE' => 25.5,
                'TOTAL' => 127.5,
                'STATUS' => 'BIG',
                'START_DATE' => '2025-01-01',
                'NEXT_DATE' => '2025-01-02',
            ]);

            $this->assertNull($model->getRecordAction());
        }

        public function testCalculatedFieldsMarkOriginalRecordWhenStoredValueIsStale(): void
        {
            $model = new CalculatedFieldsModel();
            $model->setRow([
                'ID' => 1,
                'QTY' => 5,
                'PRICE' => 25.5,
                'TOTAL' => 0,
                'STATUS' => 'SMALL',
                'START_DATE' => '2025-01-01',
                'NEXT_DATE' => '2025-01-01',
            ]);

            $this->assertSame('edit', $model->getRecordAction());
            $this->assertEquals(127.5, $model->TOTAL);
            $this->assertSame('BIG', $model->STATUS);
            $this->assertSame('2025-01-02', $model->NEXT_DATE->format('Y-m-d'));
        }
    }
}
