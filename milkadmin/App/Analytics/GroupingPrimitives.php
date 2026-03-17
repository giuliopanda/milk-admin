<?php
namespace App\Analytics;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Pure grouping/bucketing primitives reusable across modules.
 */
final class GroupingPrimitives
{
    public static function groupKey($key): string
    {
        return self::normalizeGroupKey($key);
    }

    public static function comparableKey($value): string
    {
        return self::normalizeComparableValue($value);
    }

    public static function dateBucketKey($value, string $granularity): ?string
    {
        $granularity = self::normalizeGranularity($granularity);
        $date = self::toDateTimeImmutable($value);
        if ($date === null) {
            return null;
        }

        return self::formatDateBucket($date, $granularity);
    }

    /**
     * Groups rows by a computed key preserving insertion order.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function groupBy(array $rows, callable $keyResolver): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $key = self::normalizeGroupKey($keyResolver($row));
            if (!array_key_exists($key, $grouped)) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $row;
        }
        return $grouped;
    }

    /**
     * Groups rows by consecutive key changes.
     *
     * @return array<int, array{key: string, rows: array<int, mixed>}>
     */
    public static function groupSequential(array $rows, callable $keyResolver): array
    {
        if ($rows === []) {
            return [];
        }

        $groups = [];
        $currentKey = null;
        $currentRows = [];

        foreach ($rows as $row) {
            $resolvedKey = self::normalizeGroupKey($keyResolver($row));
            if ($currentKey === null) {
                $currentKey = $resolvedKey;
            }

            if ($resolvedKey !== $currentKey) {
                $groups[] = ['key' => $currentKey, 'rows' => $currentRows];
                $currentKey = $resolvedKey;
                $currentRows = [];
            }

            $currentRows[] = $row;
        }

        $groups[] = ['key' => $currentKey, 'rows' => $currentRows];

        return $groups;
    }

    /**
     * @return array<int, mixed>
     */
    public static function uniqueSortedValues(array $rows, callable $valueResolver, int $sortFlags = SORT_REGULAR): array
    {
        $uniqueMap = [];
        foreach ($rows as $row) {
            $value = $valueResolver($row);
            $key = self::normalizeComparableValue($value);
            if (!array_key_exists($key, $uniqueMap)) {
                $uniqueMap[$key] = $value;
            }
        }

        $uniqueValues = array_values($uniqueMap);
        sort($uniqueValues, $sortFlags);

        return $uniqueValues;
    }

    /**
     * @return array<int, string> Bucket keys sorted in ascending order.
     */
    public static function uniqueDateBuckets(array $rows, callable $dateResolver, string $granularity): array
    {
        $granularity = self::normalizeGranularity($granularity);
        $buckets = [];

        foreach ($rows as $row) {
            $date = self::toDateTimeImmutable($dateResolver($row));
            if ($date === null) {
                continue;
            }
            $bucket = self::formatDateBucket($date, $granularity);
            $buckets[$bucket] = true;
        }

        $bucketKeys = array_keys($buckets);
        sort($bucketKeys);

        return $bucketKeys;
    }

    public static function formatDateBucketLabel(string $bucket, string $granularity): string
    {
        $granularity = self::normalizeGranularity($granularity);

        switch ($granularity) {
            case 'Y':
                return $bucket;

            case 'M':
                $dt = DateTimeImmutable::createFromFormat('Y-m', $bucket);
                if ($dt === false) {
                    return $bucket;
                }
                return $dt->format('F Y');

            case 'W':
                $parts = explode('-', $bucket, 2);
                if (count($parts) !== 2) {
                    return $bucket;
                }
                $year = (int) $parts[0];
                $week = (int) $parts[1];
                if ($year <= 0 || $week <= 0) {
                    return $bucket;
                }
                $start = (new DateTimeImmutable())->setISODate($year, $week, 1);
                $end = $start->modify('+6 days');
                return sprintf(
                    "W %s, %s-%s %s %s",
                    str_pad((string) $week, 2, '0', STR_PAD_LEFT),
                    $start->format('j'),
                    $end->format('j'),
                    $start->format('M'),
                    $start->format('Y')
                );

            case 'D':
                $dt = DateTimeImmutable::createFromFormat('Y-m-d', $bucket);
                if ($dt === false) {
                    return $bucket;
                }
                return $dt->format('d F Y');
        }

        return $bucket;
    }

    /**
     * Split an already sorted unique value list into contiguous buckets.
     * Buckets are contiguous and may be uneven; last bucket can be smaller.
     * Returned bucket count can be less than $numGroups when input cardinality is low.
     *
     * @return array<int, array<int, mixed>>
     */
    public static function splitIntoRangeBuckets(array $sortedUniqueValues, int $numGroups): array
    {
        if ($numGroups <= 0) {
            throw new InvalidArgumentException('numGroups must be greater than zero.');
        }
        if ($sortedUniqueValues === []) {
            return [];
        }

        $chunkSize = (int) ceil(count($sortedUniqueValues) / $numGroups);
        $chunkSize = max(1, $chunkSize);

        return array_chunk($sortedUniqueValues, $chunkSize);
    }

    public static function createRangeLabel($first, $last, int $groupSize, int $truncateAt = 16): string
    {
        $firstText = (string) $first;
        $lastText = (string) $last;

        if ($groupSize <= 1 || $firstText === $lastText) {
            return $firstText;
        }

        $firstText = self::truncateLabel($firstText, $truncateAt);
        $lastText = self::truncateLabel($lastText, $truncateAt);

        return $firstText . ' - ' . $lastText;
    }

    private static function truncateLabel(string $label, int $maxLength): string
    {
        if ($maxLength < 4 || strlen($label) <= $maxLength) {
            return $label;
        }
        return substr($label, 0, $maxLength - 3) . '...';
    }

    private static function normalizeGranularity(string $granularity): string
    {
        $granularity = strtoupper(trim($granularity));
        if (!in_array($granularity, ['Y', 'M', 'W', 'D'], true)) {
            throw new InvalidArgumentException('Granularity must be one of: Y, M, W, D.');
        }
        return $granularity;
    }

    private static function formatDateBucket(DateTimeImmutable $date, string $granularity): string
    {
        switch ($granularity) {
            case 'Y':
                return $date->format('Y');
            case 'M':
                return $date->format('Y-m');
            case 'W':
                return $date->format('o-W');
            case 'D':
                return $date->format('Y-m-d');
        }

        return $date->format('Y-m-d');
    }

    private static function toDateTimeImmutable($value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function normalizeGroupKey($key): string
    {
        if ($key instanceof DateTimeInterface) {
            return $key->format(DateTimeInterface::ATOM);
        }
        if (is_bool($key)) {
            return 'bool:' . ($key ? '1' : '0');
        }
        if (is_int($key)) {
            return 'int:' . $key;
        }
        if (is_float($key)) {
            return 'float:' . $key;
        }
        if (is_string($key)) {
            return $key;
        }
        if ($key === null) {
            return 'null';
        }

        return serialize($key);
    }

    private static function normalizeComparableValue($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return 'dt:' . $value->format(DateTimeInterface::ATOM);
        }
        if (is_bool($value)) {
            return 'bool:' . ($value ? '1' : '0');
        }
        if (is_int($value)) {
            return 'int:' . $value;
        }
        if (is_float($value)) {
            return 'float:' . $value;
        }
        if (is_string($value)) {
            return 'str:' . $value;
        }
        return 'serialized:' . serialize($value);
    }
}
