<?php
/**
 * Run with:
 * php vendor/bin/phpunit tests/Unit/App/Analytics/GroupingPrimitivesTest.php --testdox
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

use App\Analytics\GroupingPrimitives;
use PHPUnit\Framework\TestCase;

final class GroupingPrimitivesTest extends TestCase
{
    public function testGroupByPreservesInsertionOrder(): void
    {
        $rows = [
            ['cat' => 'B', 'v' => 1],
            ['cat' => 'A', 'v' => 2],
            ['cat' => 'B', 'v' => 3],
        ];

        $grouped = GroupingPrimitives::groupBy($rows, static fn(array $row): string => $row['cat']);

        $this->assertSame(['B', 'A'], array_keys($grouped));
        $this->assertCount(2, $grouped['B']);
        $this->assertCount(1, $grouped['A']);
    }

    public function testGroupSequentialSplitsOnlyOnKeyChange(): void
    {
        $rows = [
            ['k' => 'A', 'id' => 1],
            ['k' => 'A', 'id' => 2],
            ['k' => 'B', 'id' => 3],
            ['k' => 'A', 'id' => 4],
        ];

        $groups = GroupingPrimitives::groupSequential($rows, static fn(array $row): string => $row['k']);

        $this->assertCount(3, $groups);
        $this->assertSame('A', $groups[0]['key']);
        $this->assertSame('B', $groups[1]['key']);
        $this->assertSame('A', $groups[2]['key']);
        $this->assertCount(2, $groups[0]['rows']);
    }

    public function testUniqueSortedValues(): void
    {
        $rows = [
            ['v' => 3],
            ['v' => 1],
            ['v' => 3],
            ['v' => 2],
        ];

        $unique = GroupingPrimitives::uniqueSortedValues($rows, static fn(array $row): int => $row['v']);

        $this->assertSame([1, 2, 3], $unique);
    }

    public function testUniqueDateBucketsByMonthAndDay(): void
    {
        $rows = [
            ['d' => '2026-01-01'],
            ['d' => '2026-01-15'],
            ['d' => '2026-02-01'],
            ['d' => 'invalid'],
        ];

        $months = GroupingPrimitives::uniqueDateBuckets($rows, static fn(array $row): string => $row['d'], 'M');
        $days = GroupingPrimitives::uniqueDateBuckets($rows, static fn(array $row): string => $row['d'], 'D');

        $this->assertSame(['2026-01', '2026-02'], $months);
        $this->assertSame(['2026-01-01', '2026-01-15', '2026-02-01'], $days);
    }

    public function testFormatDateBucketLabelYearMonthDay(): void
    {
        $this->assertSame('2026', GroupingPrimitives::formatDateBucketLabel('2026', 'Y'));
        $this->assertSame('January 2026', GroupingPrimitives::formatDateBucketLabel('2026-01', 'M'));
        $this->assertSame('01 January 2026', GroupingPrimitives::formatDateBucketLabel('2026-01-01', 'D'));
    }

    public function testFormatDateBucketLabelWeek(): void
    {
        $label = GroupingPrimitives::formatDateBucketLabel('2026-02', 'W');

        $this->assertStringStartsWith('W 02, ', $label);
        $this->assertStringContainsString(' 2026', $label);
    }

    public function testFormatDateBucketLabelReturnsOriginalOnMalformedBucket(): void
    {
        $this->assertSame('not-valid', GroupingPrimitives::formatDateBucketLabel('not-valid', 'D'));
        $this->assertSame('not-valid', GroupingPrimitives::formatDateBucketLabel('not-valid', 'M'));
    }

    public function testSplitIntoRangeBuckets(): void
    {
        $values = [1, 2, 3, 4, 5, 6, 7];
        $buckets = GroupingPrimitives::splitIntoRangeBuckets($values, 3);

        $this->assertCount(3, $buckets);
        $this->assertSame([1, 2, 3], $buckets[0]);
        $this->assertSame([4, 5, 6], $buckets[1]);
        $this->assertSame([7], $buckets[2]);
    }

    public function testSplitIntoRangeBucketsThrowsForInvalidGroupCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GroupingPrimitives::splitIntoRangeBuckets([1, 2, 3], 0);
    }

    public function testCreateRangeLabelForSingleElementAndRange(): void
    {
        $single = GroupingPrimitives::createRangeLabel('A', 'A', 1);
        $range = GroupingPrimitives::createRangeLabel('Start', 'End', 2);

        $this->assertSame('A', $single);
        $this->assertSame('Start - End', $range);
    }

    public function testCreateRangeLabelTruncatesLongValues(): void
    {
        $label = GroupingPrimitives::createRangeLabel(
            'ThisIsALongStartLabel',
            'ThisIsALongEndLabel',
            2,
            10
        );

        $this->assertSame('ThisIsA... - ThisIsA...', $label);
    }

    public function testUniqueDateBucketsThrowsForInvalidGranularity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GroupingPrimitives::uniqueDateBuckets(
            [['d' => '2026-01-01']],
            static fn(array $row): string => $row['d'],
            'Q'
        );
    }

    public function testGroupBySupportsComplexKeys(): void
    {
        $rows = [
            ['meta' => ['a' => 1]],
            ['meta' => ['a' => 1]],
            ['meta' => ['a' => 2]],
        ];

        $grouped = GroupingPrimitives::groupBy($rows, static fn(array $row): array => $row['meta']);

        $this->assertCount(2, $grouped);
        $this->assertSame(2, count(reset($grouped)));
    }

    public function testGroupByDistinguishesBooleanNumericAndStringKeys(): void
    {
        $rows = [
            ['k' => true],
            ['k' => '1'],
            ['k' => 1],
            ['k' => false],
            ['k' => '0'],
            ['k' => 0],
        ];

        $grouped = GroupingPrimitives::groupBy($rows, static fn(array $row) => $row['k']);

        $this->assertSame(['bool:1', 1, 'int:1', 'bool:0', 0, 'int:0'], array_keys($grouped));
        $this->assertCount(6, $grouped);
    }

    public function testPublicComparableAndGroupKeys(): void
    {
        $this->assertSame('bool:1', GroupingPrimitives::groupKey(true));
        $this->assertSame('int:1', GroupingPrimitives::groupKey(1));
        $this->assertSame('str:1', GroupingPrimitives::comparableKey('1'));
        $this->assertSame('int:1', GroupingPrimitives::comparableKey(1));
    }

    public function testDateBucketKeyParsesAndNormalizesGranularity(): void
    {
        $this->assertSame('2026-01', GroupingPrimitives::dateBucketKey('2026-01-15', 'm'));
        $this->assertSame('2026-03', GroupingPrimitives::dateBucketKey(new \DateTimeImmutable('2026-03-10'), 'M'));
        $this->assertNull(GroupingPrimitives::dateBucketKey('invalid-date', 'D'));
    }
}
