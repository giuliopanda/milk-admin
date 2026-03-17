<?php
/**
 * Run with:
 * php vendor/bin/phpunit tests/Unit/App/Analytics/AggregationPrimitivesTest.php --testdox
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

use App\Analytics\AggregationPrimitives;
use PHPUnit\Framework\TestCase;

final class AggregationPrimitivesTest extends TestCase
{
    public function testNonEmptyValuesKeepsZeroValues(): void
    {
        $values = [null, false, '', 0, '0', 'abc'];
        $result = AggregationPrimitives::nonEmptyValues($values);

        $this->assertSame([0, '0', 'abc'], $result);
    }

    public function testNumericValuesConvertsAndFilters(): void
    {
        $values = [1, '2', '3.5', 'abc', null, INF, -4];
        $result = AggregationPrimitives::numericValues($values);

        $this->assertSame([1.0, 2.0, 3.5, -4.0], $result);
    }

    public function testDateTimeValuesParsesValidDatesOnly(): void
    {
        $values = ['2026-01-01', 'not-a-date', new DateTimeImmutable('2026-02-10')];
        $result = AggregationPrimitives::dateTimeValues($values);

        $this->assertCount(2, $result);
        $this->assertSame('2026-01-01', $result[0]->format('Y-m-d'));
        $this->assertSame('2026-02-10', $result[1]->format('Y-m-d'));
    }

    public function testSumReturnsNullWhenNoNumericValues(): void
    {
        $this->assertNull(AggregationPrimitives::sum(['a', null, false]));
    }

    public function testSumAndAverageWithOutlierExclusion(): void
    {
        $values = [10, 11, 12, 13, 14, 1000];

        $sum = AggregationPrimitives::sum($values, true);
        $avg = AggregationPrimitives::average($values, true);

        $this->assertSame(60.0, $sum);
        $this->assertSame(12.0, $avg);
    }

    public function testMinAndMaxNumber(): void
    {
        $values = [7, 1.5, '9', -2];

        $this->assertSame(-2.0, AggregationPrimitives::minNumber($values));
        $this->assertSame(9.0, AggregationPrimitives::maxNumber($values));
    }

    public function testMinAndMaxDate(): void
    {
        $values = ['2026-03-01', '2026-01-02', '2026-02-10'];

        $this->assertSame('2026-01-02', AggregationPrimitives::minDate($values)?->format('Y-m-d'));
        $this->assertSame('2026-03-01', AggregationPrimitives::maxDate($values)?->format('Y-m-d'));
    }

    public function testCountsForEmptyAndNonEmpty(): void
    {
        $values = [null, false, '', 0, '0', 'x', []];

        $this->assertSame(4, AggregationPrimitives::countNonEmpty($values));
        $this->assertSame(3, AggregationPrimitives::countEmpty($values));
    }

    public function testUniqueAndNotUniqueCounts(): void
    {
        $values = ['A', 'A', 'B', 'C', null, '', false, 'C'];

        $this->assertSame(3, AggregationPrimitives::countUniqueNonEmpty($values));
        $this->assertSame(2, AggregationPrimitives::countNotUniqueNonEmpty($values));
    }

    public function testModeReturnsFirstMostFrequentValue(): void
    {
        $values = ['A', 'B', 'A', 'B', 'C'];
        $mode = AggregationPrimitives::modeNonEmpty($values);

        // A and B are tied (2 each), first encountered wins.
        $this->assertSame('A', $mode);
    }

    public function testVarianceAndStdDevSample(): void
    {
        $values = [2, 4, 4, 4, 5, 5, 7, 9];

        $variance = AggregationPrimitives::varianceSample($values);
        $stdDev = AggregationPrimitives::stdDevSample($values);

        $this->assertEqualsWithDelta(4.5714285714, (float) $variance, 0.0000001);
        $this->assertEqualsWithDelta(2.1380899353, (float) $stdDev, 0.0000001);
    }

    public function testRangeWithOutlierExclusion(): void
    {
        $values = [10, 11, 12, 13, 14, 1000];
        $range = AggregationPrimitives::range($values, true);

        $this->assertSame(4.0, $range);
    }

    public function testGrowthRate(): void
    {
        $values = [10, 15, 20];
        $growth = AggregationPrimitives::growthRate($values);

        $this->assertSame(100.0, $growth);
    }

    public function testGrowthRateReturnsNullForInvalidInput(): void
    {
        $this->assertNull(AggregationPrimitives::growthRate([0, 10]));
        $this->assertNull(AggregationPrimitives::growthRate([1.0e-20, 10]));
        $this->assertNull(AggregationPrimitives::growthRate([10]));
    }

    public function testPercentileInterpolationAndBounds(): void
    {
        $values = [1, 2, 3, 4];

        $this->assertSame(1.0, AggregationPrimitives::percentile($values, 0));
        $this->assertSame(4.0, AggregationPrimitives::percentile($values, 100));
        $this->assertSame(2.5, AggregationPrimitives::percentile($values, 50));
        $this->assertSame(1.75, AggregationPrimitives::percentile($values, 25));
    }

    public function testMedianAlias(): void
    {
        $this->assertSame(3.0, AggregationPrimitives::median([1, 3, 5]));
        $this->assertSame(2.5, AggregationPrimitives::median([1, 2, 3, 4]));
    }

    public function testPercentileThrowsOnInvalidRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AggregationPrimitives::percentile([1, 2, 3], -1);
    }

    public function testRemoveOutliersIqr(): void
    {
        $values = [10, 11, 12, 13, 14, 1000];
        $filtered = AggregationPrimitives::removeOutliersIqr($values);

        $this->assertSame([10.0, 11.0, 12.0, 13.0, 14.0], $filtered);
    }

    public function testRemoveOutliersThrowsOnInvalidThreshold(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AggregationPrimitives::removeOutliersIqr([1, 2, 3, 4], 0);
    }

    public function testCumulativeSumHandlesNonNumericGracefully(): void
    {
        $values = [1, '2', 'x', 3];
        $result = AggregationPrimitives::cumulativeSum($values);

        $this->assertSame([1.0, 3.0, 3.0, 6.0], $result);
    }

    public function testCumulativeCountSupportsNonEmptyOnlyAndAll(): void
    {
        $values = [null, '', 0, 'A'];

        $this->assertSame([0, 0, 1, 2], AggregationPrimitives::cumulativeCount($values, true));
        $this->assertSame([1, 2, 3, 4], AggregationPrimitives::cumulativeCount($values, false));
    }
}
