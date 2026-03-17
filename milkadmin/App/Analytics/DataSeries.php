<?php
namespace App\Analytics;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Time-series helpers for reporting.
 *
 * Input series supports:
 * - array<int, array{bucket:mixed, value:mixed}>
 * - array<int, object{bucket:mixed, value:mixed}>
 * - associative map bucket => value
 *
 * Output format is always:
 * - array<int, array{bucket:string, value:mixed}>
 */
final class DataSeries
{
    /**
     * Aggregate a series from a finer granularity to a coarser one.
     *
     * @param array<int|string, mixed> $series
     * @return array<int, array{bucket: string, value: mixed}>
     */
    public static function resample(
        array $series,
        string $fromGranularity,
        string $toGranularity,
        callable $aggregator
    ): array {
        $from = self::normalizeGranularity($fromGranularity);
        $to = self::normalizeGranularity($toGranularity);
        self::assertResampleDirectionAllowed($from, $to);

        $points = self::normalizeSeries($series);
        if ($points === []) {
            return [];
        }

        $points = self::sortPointsByGranularity($points, $from);
        $groupedValues = [];

        foreach ($points as $point) {
            $date = self::parseBucketToDate($point['bucket'], $from);
            if ($date === null) {
                continue;
            }

            $targetBucket = self::formatBucket($date, $to);
            if (!array_key_exists($targetBucket, $groupedValues)) {
                $groupedValues[$targetBucket] = [];
            }
            $groupedValues[$targetBucket][] = $point['value'];
        }

        $result = [];
        foreach ($groupedValues as $bucket => $values) {
            $result[] = [
                'bucket' => $bucket,
                'value' => $aggregator($values),
            ];
        }

        return $result;
    }

    /**
     * Fill missing buckets between min and max date bucket.
     *
     * @param array<int|string, mixed> $series
     * @return array<int, array{bucket: string, value: mixed}>
     */
    public static function fillGaps(array $series, string $granularity, mixed $fillValue = null): array
    {
        $g = self::normalizeGranularity($granularity);
        $points = self::normalizeSeries($series);
        if ($points === []) {
            return [];
        }

        $points = self::sortPointsByGranularity($points, $g);
        $indexed = [];
        foreach ($points as $point) {
            $indexed[(string) $point['bucket']] = $point['value'];
        }

        $first = self::parseBucketToDate($points[0]['bucket'], $g);
        $last = self::parseBucketToDate($points[count($points) - 1]['bucket'], $g);
        if ($first === null || $last === null) {
            return $points;
        }

        $filled = [];
        $cursor = $first;
        while ($cursor <= $last) {
            $bucket = self::formatBucket($cursor, $g);
            $filled[] = [
                'bucket' => $bucket,
                'value' => $indexed[$bucket] ?? $fillValue,
            ];
            $cursor = self::advanceDate($cursor, $g);
        }

        return $filled;
    }

    /**
     * Rolling average with fixed window.
     * First window-1 rows are null.
     *
     * @param array<int|string, mixed> $series
     * @return array<int, array{bucket: string, value: ?float}>
     */
    public static function rollingAverage(array $series, int $window): array
    {
        if ($window <= 0) {
            throw new InvalidArgumentException('Window must be greater than zero.');
        }

        $points = self::normalizeSeries($series);
        if ($points === []) {
            return [];
        }

        $result = [];
        $windowValues = [];

        foreach ($points as $point) {
            $windowValues[] = $point['value'];
            if (count($windowValues) > $window) {
                array_shift($windowValues);
            }

            $value = null;
            if (count($windowValues) === $window && self::allNumeric($windowValues)) {
                $sum = array_sum(array_map(static fn($v): float => (float) $v, $windowValues));
                $value = $sum / $window;
            }

            $result[] = [
                'bucket' => (string) $point['bucket'],
                'value' => $value,
            ];
        }

        return $result;
    }

    /**
     * Rolling sum with fixed window.
     * First window-1 rows are null.
     *
     * @param array<int|string, mixed> $series
     * @return array<int, array{bucket: string, value: ?float}>
     */
    public static function rollingSum(array $series, int $window): array
    {
        if ($window <= 0) {
            throw new InvalidArgumentException('Window must be greater than zero.');
        }

        $points = self::normalizeSeries($series);
        if ($points === []) {
            return [];
        }

        $result = [];
        $windowValues = [];

        foreach ($points as $point) {
            $windowValues[] = $point['value'];
            if (count($windowValues) > $window) {
                array_shift($windowValues);
            }

            $value = null;
            if (count($windowValues) === $window && self::allNumeric($windowValues)) {
                $value = array_sum(array_map(static fn($v): float => (float) $v, $windowValues));
            }

            $result[] = [
                'bucket' => (string) $point['bucket'],
                'value' => $value,
            ];
        }

        return $result;
    }

    /**
     * Period-over-period absolute difference.
     * First row is null.
     *
     * @param array<int|string, mixed> $series
     * @return array<int, array{bucket: string, value: ?float}>
     */
    public static function diff(array $series): array
    {
        $points = self::normalizeSeries($series);
        if ($points === []) {
            return [];
        }

        $result = [];
        $prev = null;

        foreach ($points as $point) {
            $current = is_numeric($point['value']) ? (float) $point['value'] : null;
            $value = null;

            if ($prev !== null && $current !== null) {
                $value = $current - $prev;
            }

            $result[] = [
                'bucket' => (string) $point['bucket'],
                'value' => $value,
            ];

            $prev = $current;
        }

        return $result;
    }

    /**
     * Period-over-period percentage change.
     * First row is null.
     *
     * @param array<int|string, mixed> $series
     * @return array<int, array{bucket: string, value: ?float}>
     */
    public static function percentChangePeriod(array $series): array
    {
        $points = self::normalizeSeries($series);
        if ($points === []) {
            return [];
        }

        $result = [];
        $prev = null;

        foreach ($points as $point) {
            $current = is_numeric($point['value']) ? (float) $point['value'] : null;
            $value = null;

            if ($prev !== null && $current !== null && abs($prev) >= PHP_FLOAT_EPSILON) {
                $value = (($current - $prev) / abs($prev)) * 100;
            }

            $result[] = [
                'bucket' => (string) $point['bucket'],
                'value' => $value,
            ];

            $prev = $current;
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed> $series
     * @return array<int, array{bucket: string, value: mixed}>
     */
    private static function normalizeSeries(array $series): array
    {
        if ($series === []) {
            return [];
        }

        $points = [];
        if (self::isAssociativeMap($series)) {
            foreach ($series as $bucket => $value) {
                $points[] = [
                    'bucket' => (string) $bucket,
                    'value' => $value,
                ];
            }
            return $points;
        }

        foreach ($series as $item) {
            $points[] = self::extractPoint($item);
        }

        return $points;
    }

    /**
     * @return array{bucket: string, value: mixed}
     */
    private static function extractPoint(mixed $item): array
    {
        if (is_array($item)) {
            if (array_key_exists('bucket', $item)) {
                return ['bucket' => (string) $item['bucket'], 'value' => $item['value'] ?? null];
            }
            if (array_key_exists('date', $item)) {
                return ['bucket' => (string) $item['date'], 'value' => $item['value'] ?? null];
            }
            if (array_key_exists(0, $item) && array_key_exists(1, $item)) {
                return ['bucket' => (string) $item[0], 'value' => $item[1]];
            }
        }

        if (is_object($item)) {
            if (isset($item->bucket) || property_exists($item, 'bucket')) {
                return ['bucket' => (string) $item->bucket, 'value' => $item->value ?? null];
            }
            if (isset($item->date) || property_exists($item, 'date')) {
                return ['bucket' => (string) $item->date, 'value' => $item->value ?? null];
            }
        }

        throw new InvalidArgumentException(
            'Invalid series item format. Expected map bucket=>value or rows with bucket/date and value.'
        );
    }

    /**
     * @param array<int|string, mixed> $series
     */
    private static function isAssociativeMap(array $series): bool
    {
        if ($series === []) {
            return false;
        }

        $keys = array_keys($series);
        foreach ($keys as $key) {
            if (!is_int($key)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeGranularity(string $granularity): string
    {
        $g = strtoupper(trim($granularity));

        $aliases = [
            'DAY' => 'D',
            'DAILY' => 'D',
            'WEEK' => 'W',
            'WEEKLY' => 'W',
            'MONTH' => 'M',
            'MONTHLY' => 'M',
            'YEAR' => 'Y',
            'YEARLY' => 'Y',
        ];

        if (isset($aliases[$g])) {
            return $aliases[$g];
        }

        if (!in_array($g, ['D', 'W', 'M', 'Y'], true)) {
            throw new InvalidArgumentException('Granularity must be one of: D, W, M, Y.');
        }

        return $g;
    }

    private static function assertResampleDirectionAllowed(string $from, string $to): void
    {
        $allowed = [
            'D' => ['D', 'W', 'M', 'Y'],
            'W' => ['W', 'M', 'Y'],
            'M' => ['M', 'Y'],
            'Y' => ['Y'],
        ];

        if (!in_array($to, $allowed[$from], true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported resample direction from %s to %s.',
                $from,
                $to
            ));
        }
    }

    /**
     * @param array<int, array{bucket: string, value: mixed}> $points
     * @return array<int, array{bucket: string, value: mixed}>
     */
    private static function sortPointsByGranularity(array $points, string $granularity): array
    {
        usort($points, static function (array $a, array $b) use ($granularity): int {
            $ad = self::parseBucketToDate($a['bucket'], $granularity);
            $bd = self::parseBucketToDate($b['bucket'], $granularity);

            if ($ad === null && $bd === null) {
                return strcmp($a['bucket'], $b['bucket']);
            }
            if ($ad === null) {
                return 1;
            }
            if ($bd === null) {
                return -1;
            }

            return $ad <=> $bd;
        });

        return $points;
    }

    private static function parseBucketToDate(string $bucket, string $granularity): ?DateTimeImmutable
    {
        $bucket = trim($bucket);
        if ($bucket === '') {
            return null;
        }

        switch ($granularity) {
            case 'D':
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $bucket);
                if ($parsed !== false) {
                    return $parsed;
                }
                break;

            case 'M':
                $parsed = DateTimeImmutable::createFromFormat('!Y-m', $bucket);
                if ($parsed !== false) {
                    return $parsed;
                }
                break;

            case 'Y':
                $parsed = DateTimeImmutable::createFromFormat('!Y', $bucket);
                if ($parsed !== false) {
                    return $parsed;
                }
                break;

            case 'W':
                if (preg_match('/^(\d{4})-(\d{1,2})$/', $bucket, $matches) === 1) {
                    $year = (int) $matches[1];
                    $week = (int) $matches[2];
                    if ($week >= 1 && $week <= 53) {
                        return (new DateTimeImmutable('1970-01-01'))->setISODate($year, $week, 1);
                    }
                }
                break;
        }

        try {
            return new DateTimeImmutable($bucket);
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function formatBucket(DateTimeImmutable $date, string $granularity): string
    {
        return match ($granularity) {
            'D' => $date->format('Y-m-d'),
            'W' => $date->format('o-W'),
            'M' => $date->format('Y-m'),
            'Y' => $date->format('Y'),
            default => $date->format('Y-m-d'),
        };
    }

    private static function advanceDate(DateTimeImmutable $date, string $granularity): DateTimeImmutable
    {
        $interval = match ($granularity) {
            'D' => new DateInterval('P1D'),
            'W' => new DateInterval('P7D'),
            'M' => new DateInterval('P1M'),
            'Y' => new DateInterval('P1Y'),
            default => new DateInterval('P1D'),
        };

        return $date->add($interval);
    }

    /**
     * @param array<int, mixed> $values
     */
    private static function allNumeric(array $values): bool
    {
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                return false;
            }
        }
        return true;
    }
}
