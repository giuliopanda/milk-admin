<?php
/**
 * Run with:
 * php vendor/bin/phpunit tests/Unit/App/Analytics/AggregationEngineTest.php --testdox
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

use App\Analytics\AggregationEngine;
use PHPUnit\Framework\TestCase;

final class AggregationEngineTest extends TestCase
{
    public function testNumericMetricsUseFrameworkPrimitives(): void
    {
        $engine = new AggregationEngine();
        $values = [10, 11, 12, 13, 14, 1000];

        $this->assertSame(1060.0, $engine->aggregate('sum', $values));
        $this->assertSame(176.66666666666666, $engine->aggregate('avg', $values));
        $this->assertSame(60.0, $engine->aggregate('sum_no_outliers', $values));
        $this->assertSame(12.0, $engine->aggregate('avg_no_outliers', $values));
    }

    public function testDateMetricsReturnFormattedStrings(): void
    {
        $engine = new AggregationEngine();
        $values = ['2026-02-10 12:00:00', '2026-01-01 01:00:00', '2026-03-02 10:30:00'];

        $this->assertSame(
            '2026-01-01',
            $engine->aggregate('min', $values, ['field_type' => 'date'])
        );
        $this->assertSame(
            '2026-03-02 10:30:00',
            $engine->aggregate('max', $values, ['field_type' => 'datetime'])
        );
    }

    public function testCumulativeMetricsTrackStateByField(): void
    {
        $engine = new AggregationEngine();

        $this->assertSame(3.0, $engine->aggregate('cumsum', [1, 2], ['field_name' => 'a']));
        $this->assertSame(8.0, $engine->aggregate('cumsum', [5], ['field_name' => 'a']));
        $this->assertSame(1, $engine->aggregate('cumcount', [10, null, ''], ['field_name' => 'b']));
        $this->assertSame(2, $engine->aggregate('cumcount', [0], ['field_name' => 'b']));

        $this->assertSame(['a' => 8.0], $engine->getCumulativeSums());
        $this->assertSame(['b' => 2], $engine->getCumulativeCounts());
    }

    public function testPercentTotalStoresBaseCounterForFormattingPhase(): void
    {
        $engine = new AggregationEngine();

        $this->assertSame(2, $engine->aggregate('percent_total', [null, 0, 'x'], ['field_name' => 'p']));
        $this->assertSame(1, $engine->aggregate('percent_total', ['a'], ['field_name' => 'p']));
        $this->assertSame(['p' => 3], $engine->getCumulativeSums());
    }

    public function testFallbackAndLastValueUseNonEmptyValues(): void
    {
        $engine = new AggregationEngine();

        $this->assertSame('A', $engine->aggregate('', [null, '', false, 'A']));
        $this->assertSame('Z', $engine->aggregate('last_value', [null, 'A', '', 'Z']));
    }
}
