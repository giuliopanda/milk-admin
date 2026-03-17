<?php
/**
 * Run with:
 * php vendor/bin/phpunit tests/Unit/App/Analytics/DataSeriesTest.php --testdox
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

use App\Analytics\DataSeries;
use PHPUnit\Framework\TestCase;

final class DataSeriesTest extends TestCase
{
    public function testFillGapsDaily(): void
    {
        $series = [
            ['bucket' => '2026-01-01', 'value' => 10],
            ['bucket' => '2026-01-03', 'value' => 30],
        ];

        $filled = DataSeries::fillGaps($series, 'D');

        $this->assertSame(
            [
                ['bucket' => '2026-01-01', 'value' => 10],
                ['bucket' => '2026-01-02', 'value' => null],
                ['bucket' => '2026-01-03', 'value' => 30],
            ],
            $filled
        );
    }

    public function testFillGapsMonthlyFromAssociativeMap(): void
    {
        $series = [
            '2026-01' => 1,
            '2026-03' => 3,
        ];

        $filled = DataSeries::fillGaps($series, 'M', 0);

        $this->assertSame(
            [
                ['bucket' => '2026-01', 'value' => 1],
                ['bucket' => '2026-02', 'value' => 0],
                ['bucket' => '2026-03', 'value' => 3],
            ],
            $filled
        );
    }

    public function testResampleDayToMonthWithSumAggregator(): void
    {
        $series = [
            ['bucket' => '2026-01-01', 'value' => 10],
            ['bucket' => '2026-01-02', 'value' => 20],
            ['bucket' => '2026-02-01', 'value' => 5],
        ];

        $resampled = DataSeries::resample(
            $series,
            'D',
            'M',
            static fn(array $values): float => array_sum(array_map(static fn($v): float => (float) $v, $values))
        );

        $this->assertSame(
            [
                ['bucket' => '2026-01', 'value' => 30.0],
                ['bucket' => '2026-02', 'value' => 5.0],
            ],
            $resampled
        );
    }

    public function testResampleDayToWeekWithAverageAggregator(): void
    {
        $series = [
            ['bucket' => '2026-01-05', 'value' => 10], // week 02
            ['bucket' => '2026-01-06', 'value' => 20], // week 02
            ['bucket' => '2026-01-12', 'value' => 30], // week 03
        ];

        $resampled = DataSeries::resample(
            $series,
            'D',
            'W',
            static fn(array $values): float => array_sum($values) / count($values)
        );

        $this->assertSame(
            [
                ['bucket' => '2026-02', 'value' => 15.0],
                ['bucket' => '2026-03', 'value' => 30.0],
            ],
            $resampled
        );
    }

    public function testRollingAverageAndRollingSum(): void
    {
        $series = [
            ['bucket' => 'p1', 'value' => 1],
            ['bucket' => 'p2', 'value' => 2],
            ['bucket' => 'p3', 'value' => 3],
            ['bucket' => 'p4', 'value' => 4],
        ];

        $avg = DataSeries::rollingAverage($series, 3);
        $sum = DataSeries::rollingSum($series, 2);

        $this->assertSame(
            [
                ['bucket' => 'p1', 'value' => null],
                ['bucket' => 'p2', 'value' => null],
                ['bucket' => 'p3', 'value' => 2.0],
                ['bucket' => 'p4', 'value' => 3.0],
            ],
            $avg
        );

        $this->assertSame(
            [
                ['bucket' => 'p1', 'value' => null],
                ['bucket' => 'p2', 'value' => 3.0],
                ['bucket' => 'p3', 'value' => 5.0],
                ['bucket' => 'p4', 'value' => 7.0],
            ],
            $sum
        );
    }

    public function testDiffAndPercentChangePeriod(): void
    {
        $series = [
            ['bucket' => 'p1', 'value' => 10],
            ['bucket' => 'p2', 'value' => 15],
            ['bucket' => 'p3', 'value' => 0],
            ['bucket' => 'p4', 'value' => 5],
            ['bucket' => 'p5', 'value' => -5],
        ];

        $diff = DataSeries::diff($series);
        $pct = DataSeries::percentChangePeriod($series);

        $this->assertSame(
            [
                ['bucket' => 'p1', 'value' => null],
                ['bucket' => 'p2', 'value' => 5.0],
                ['bucket' => 'p3', 'value' => -15.0],
                ['bucket' => 'p4', 'value' => 5.0],
                ['bucket' => 'p5', 'value' => -10.0],
            ],
            $diff
        );

        $this->assertSame(
            [
                ['bucket' => 'p1', 'value' => null],
                ['bucket' => 'p2', 'value' => 50.0],
                ['bucket' => 'p3', 'value' => -100.0],
                ['bucket' => 'p4', 'value' => null],
                ['bucket' => 'p5', 'value' => -200.0],
            ],
            $pct
        );
    }

    public function testResampleRejectsFinerDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DataSeries::resample(
            [['bucket' => '2026-01', 'value' => 1]],
            'M',
            'D',
            static fn(array $values) => $values[0] ?? null
        );
    }
}
