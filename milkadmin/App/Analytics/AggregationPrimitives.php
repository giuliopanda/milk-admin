<?php
namespace App\Analytics;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Pure aggregation/statistics primitives reusable across modules.
 */
final class AggregationPrimitives
{
    /**
     * Keep values that are not null/false/empty string.
     * Note: "0" and 0 are considered non-empty.
     */
    public static function nonEmptyValues(array $values): array
    {
        return array_values(array_filter($values, static function ($value): bool {
            return $value !== null && $value !== false && $value !== '';
        }));
    }

    /**
     * Convert numeric values to float and discard everything else.
     */
    public static function numericValues(array $values): array
    {
        $numeric = [];
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $floatValue = (float) $value;
            if (is_finite($floatValue)) {
                $numeric[] = $floatValue;
            }
        }
        return $numeric;
    }

    /**
     * Convert values to DateTimeImmutable where possible.
     */
    public static function dateTimeValues(array $values): array
    {
        $dates = [];
        foreach ($values as $value) {
            if ($value instanceof DateTimeInterface) {
                $dates[] = DateTimeImmutable::createFromInterface($value);
                continue;
            }
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            try {
                $dates[] = new DateTimeImmutable($value);
            } catch (\Exception $e) {
                // Skip invalid dates.
            }
        }
        return $dates;
    }

    public static function sum(array $values, bool $excludeOutliers = false): ?float
    {
        $numeric = self::numericValues($values);
        if ($excludeOutliers) {
            $numeric = self::removeOutliersIqrOnNumericValues($numeric);
        }
        if ($numeric === []) {
            return null;
        }
        return array_sum($numeric);
    }

    public static function average(array $values, bool $excludeOutliers = false): ?float
    {
        $numeric = self::numericValues($values);
        if ($excludeOutliers) {
            $numeric = self::removeOutliersIqrOnNumericValues($numeric);
        }
        $count = count($numeric);
        if ($count === 0) {
            return null;
        }
        return array_sum($numeric) / $count;
    }

    public static function minNumber(array $values): ?float
    {
        $numeric = self::numericValues($values);
        if ($numeric === []) {
            return null;
        }
        return min($numeric);
    }

    public static function maxNumber(array $values): ?float
    {
        $numeric = self::numericValues($values);
        if ($numeric === []) {
            return null;
        }
        return max($numeric);
    }

    public static function minDate(array $values): ?DateTimeImmutable
    {
        $dates = self::dateTimeValues($values);
        if ($dates === []) {
            return null;
        }
        usort($dates, static fn(DateTimeImmutable $a, DateTimeImmutable $b): int => $a <=> $b);
        return $dates[0];
    }

    public static function maxDate(array $values): ?DateTimeImmutable
    {
        $dates = self::dateTimeValues($values);
        if ($dates === []) {
            return null;
        }
        usort($dates, static fn(DateTimeImmutable $a, DateTimeImmutable $b): int => $a <=> $b);
        return $dates[count($dates) - 1];
    }

    public static function countNonEmpty(array $values): int
    {
        return count(self::nonEmptyValues($values));
    }

    public static function countEmpty(array $values): int
    {
        return count(array_filter($values, static function ($value): bool {
            return $value === null || $value === false || $value === '';
        }));
    }

    public static function countUniqueNonEmpty(array $values): int
    {
        $filtered = self::nonEmptyValues($values);
        if ($filtered === []) {
            return 0;
        }

        return count(self::buildFrequencyMap($filtered));
    }

    public static function countNotUniqueNonEmpty(array $values): int
    {
        $filtered = self::nonEmptyValues($values);
        if ($filtered === []) {
            return 0;
        }

        $frequencies = self::buildFrequencyMap($filtered);
        $uniqueCount = count($frequencies);

        return count($filtered) - $uniqueCount;
    }

    /**
     * Returns the first mode encountered in case of ties.
     */
    public static function modeNonEmpty(array $values)
    {
        $filtered = self::nonEmptyValues($values);
        if ($filtered === []) {
            return null;
        }

        $counts = [];
        $originalValues = [];

        foreach ($filtered as $value) {
            $key = self::normalizeComparableValue($value);
            if (!array_key_exists($key, $counts)) {
                $counts[$key] = 0;
                $originalValues[$key] = $value;
            }
            $counts[$key]++;
        }

        arsort($counts);
        $modeKey = array_key_first($counts);

        return $originalValues[$modeKey] ?? null;
    }

    /**
     * Sample variance (n-1).
     */
    public static function varianceSample(array $values, bool $excludeOutliers = false): ?float
    {
        $numeric = self::numericValues($values);
        if ($excludeOutliers) {
            $numeric = self::removeOutliersIqrOnNumericValues($numeric);
        }

        $n = count($numeric);
        if ($n < 2) {
            return null;
        }

        $mean = array_sum($numeric) / $n;
        $sumSquares = array_sum(array_map(static function (float $value) use ($mean): float {
            return ($value - $mean) ** 2;
        }, $numeric));

        return $sumSquares / ($n - 1);
    }

    public static function stdDevSample(array $values, bool $excludeOutliers = false): ?float
    {
        $variance = self::varianceSample($values, $excludeOutliers);
        if ($variance === null) {
            return null;
        }
        return sqrt($variance);
    }

    public static function range(array $values, bool $excludeOutliers = false): ?float
    {
        $numeric = self::numericValues($values);
        if ($excludeOutliers) {
            $numeric = self::removeOutliersIqrOnNumericValues($numeric);
        }
        if ($numeric === []) {
            return null;
        }
        return max($numeric) - min($numeric);
    }

    /**
     * Growth rate in percent: ((last - first) / abs(first)) * 100.
     */
    public static function growthRate(array $values, bool $excludeOutliers = false): ?float
    {
        $numeric = array_values(self::numericValues($values));
        if ($excludeOutliers) {
            $numeric = self::removeOutliersIqrOnNumericValues($numeric);
        }

        $n = count($numeric);
        if ($n < 2 || abs($numeric[0]) < PHP_FLOAT_EPSILON) {
            return null;
        }

        return (($numeric[$n - 1] - $numeric[0]) / abs($numeric[0])) * 100;
    }

    /**
     * Interpolated percentile (0..100), inclusive bounds.
     */
    public static function percentile(array $values, float $percentile): ?float
    {
        if ($percentile < 0 || $percentile > 100) {
            throw new InvalidArgumentException('Percentile must be between 0 and 100.');
        }

        $numeric = self::numericValues($values);
        if ($numeric === []) {
            return null;
        }

        sort($numeric);
        return self::percentileFromSortedNumeric($numeric, $percentile);
    }

    public static function median(array $values): ?float
    {
        return self::percentile($values, 50);
    }

    /**
     * Remove outliers using IQR method.
     */
    public static function removeOutliersIqr(array $values, float $threshold = 1.5): array
    {
        if ($threshold <= 0) {
            throw new InvalidArgumentException('IQR threshold must be greater than zero.');
        }

        $numeric = self::numericValues($values);
        return self::removeOutliersIqrOnNumericValues($numeric, $threshold);
    }

    /**
     * @param array<int, float> $numeric
     * @return array<int, float>
     */
    private static function removeOutliersIqrOnNumericValues(array $numeric, float $threshold = 1.5): array
    {
        $count = count($numeric);
        if ($count <= 3) {
            return $numeric;
        }

        sort($numeric);
        $q1 = self::percentileFromSortedNumeric($numeric, 25);
        $q3 = self::percentileFromSortedNumeric($numeric, 75);
        if ($q1 === null || $q3 === null) {
            return $numeric;
        }

        $iqr = $q3 - $q1;

        $lowerBound = $q1 - ($iqr * $threshold);
        $upperBound = $q3 + ($iqr * $threshold);

        return array_values(array_filter($numeric, static function (float $value) use ($lowerBound, $upperBound): bool {
            return $value >= $lowerBound && $value <= $upperBound;
        }));
    }

    public static function cumulativeSum(array $values): array
    {
        $result = [];
        $running = 0.0;
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                $result[] = $running;
                continue;
            }
            $running += (float) $value;
            $result[] = $running;
        }
        return $result;
    }

    public static function cumulativeCount(array $values, bool $nonEmptyOnly = true): array
    {
        $result = [];
        $running = 0;
        foreach ($values as $value) {
            if ($nonEmptyOnly) {
                if ($value !== null && $value !== false && $value !== '') {
                    $running++;
                }
            } else {
                $running++;
            }
            $result[] = $running;
        }
        return $result;
    }

    private static function buildFrequencyMap(array $values): array
    {
        $map = [];
        foreach ($values as $value) {
            $key = self::normalizeComparableValue($value);
            $map[$key] = ($map[$key] ?? 0) + 1;
        }
        return $map;
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

    private static function percentileFromSortedNumeric(array $sortedNumeric, float $percentile): ?float
    {
        $count = count($sortedNumeric);
        if ($count === 0) {
            return null;
        }

        $position = ($count - 1) * ($percentile / 100);
        $base = (int) floor($position);
        $fraction = $position - $base;

        if ($base + 1 < $count) {
            return $sortedNumeric[$base] + $fraction * ($sortedNumeric[$base + 1] - $sortedNumeric[$base]);
        }

        return $sortedNumeric[$base];
    }
}
