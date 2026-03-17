<?php
/**
 * Run with:
 * php vendor/bin/phpunit tests/Unit/App/Analytics/DataSetTest.php --testdox
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

use App\Analytics\DataSet;
use PHPUnit\Framework\TestCase;

final class DataSetTest extends TestCase
{
    public function testReportStyleGroupingAggregationSortingAndTake(): void
    {
        $rows = [
            (object) ['country' => 'IT', 'revenue' => 100],
            (object) ['country' => 'IT', 'revenue' => 50],
            (object) ['country' => 'FR', 'revenue' => 70],
            (object) ['country' => 'DE', 'revenue' => 20],
            (object) ['country' => 'FR', 'revenue' => 30],
        ];

        $top = DataSet::fromRows($rows)
            ->groupBy('country', 'country')
            ->aggregate('revenue', 'sum', 'total_revenue')
            ->countRows()
            ->sortBy('total_revenue', 'desc')
            ->take(2)
            ->toRows(false);

        $this->assertCount(2, $top);
        $this->assertSame('IT', $top[0]['country']);
        $this->assertSame(150.0, $top[0]['total_revenue']);
        $this->assertSame(2, $top[0]['___count___']);

        $this->assertSame('FR', $top[1]['country']);
        $this->assertSame(100.0, $top[1]['total_revenue']);
        $this->assertSame(2, $top[1]['___count___']);
    }

    public function testSequentialGroupingPreservesConsecutiveRuns(): void
    {
        $rows = [
            (object) ['bucket' => 'A', 'value' => 1],
            (object) ['bucket' => 'A', 'value' => 2],
            (object) ['bucket' => 'B', 'value' => 3],
            (object) ['bucket' => 'A', 'value' => 4],
        ];

        $groups = DataSet::fromRows($rows)
            ->groupSequential('bucket', 'bucket')
            ->aggregate('value', 'sum', 'total')
            ->countRows()
            ->toRows(false);

        $this->assertCount(3, $groups);
        $this->assertSame('A', $groups[0]['bucket']);
        $this->assertSame(3.0, $groups[0]['total']);
        $this->assertSame(2, $groups[0]['___count___']);

        $this->assertSame('B', $groups[1]['bucket']);
        $this->assertSame(3.0, $groups[1]['total']);
        $this->assertSame('A', $groups[2]['bucket']);
        $this->assertSame(4.0, $groups[2]['total']);
    }

    public function testSupportsArrayRowsAndDotNotationResolvers(): void
    {
        $rows = [
            ['meta' => ['country' => 'IT'], 'amount' => 10],
            ['meta' => ['country' => 'IT'], 'amount' => 5],
            ['meta' => ['country' => 'DE'], 'amount' => 7],
        ];

        $aggregated = DataSet::fromRows($rows)
            ->groupBy('meta.country', 'country')
            ->aggregate('amount', 'sum', 'amount_total')
            ->sortBy('country', 'asc')
            ->toRows(false);

        $this->assertCount(2, $aggregated);
        $this->assertSame('DE', $aggregated[0]['country']);
        $this->assertSame(7.0, $aggregated[0]['amount_total']);
        $this->assertSame('IT', $aggregated[1]['country']);
        $this->assertSame(15.0, $aggregated[1]['amount_total']);
    }

    public function testGroupFieldKeepsOriginalValueTypes(): void
    {
        $rows = [
            ['k' => true, 'v' => 1],
            ['k' => '1', 'v' => 2],
            ['k' => 1, 'v' => 3],
        ];

        $grouped = DataSet::fromRows($rows)
            ->groupBy('k', 'k')
            ->countRows('c')
            ->toRows(false);

        $this->assertTrue($grouped[0]['k']);
        $this->assertSame('1', $grouped[1]['k']);
        $this->assertSame(1, $grouped[2]['k']);
        $this->assertSame(1, $grouped[0]['c']);
        $this->assertSame(1, $grouped[1]['c']);
        $this->assertSame(1, $grouped[2]['c']);
    }

    public function testFromIterableAndTake(): void
    {
        $generator = (static function (): \Generator {
            yield (object) ['v' => 1];
            yield (object) ['v' => 2];
            yield (object) ['v' => 3];
        })();

        $rows = DataSet::fromIterable($generator)
            ->sortBy('v', 'desc')
            ->take(2)
            ->toArray();

        $this->assertCount(2, $rows);
        $this->assertSame(3, $rows[0]->v);
        $this->assertSame(2, $rows[1]->v);
    }
}
