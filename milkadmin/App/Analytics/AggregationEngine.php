<?php
namespace App\Analytics;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Stateful aggregation engine built on top of AggregationPrimitives.
 * It centralizes metric dispatch and cumulative fields management.
 */
final class AggregationEngine
{
    /**
     * @var array<string, float|int>
     */
    private array $cumulativeSums = [];

    /**
     * @var array<string, int>
     */
    private array $cumulativeCounts = [];

    public function reset(): void
    {
        $this->cumulativeSums = [];
        $this->cumulativeCounts = [];
    }

    /**
     * @return array<string, float|int>
     */
    public function getCumulativeSums(): array
    {
        return $this->cumulativeSums;
    }

    /**
     * @return array<string, int>
     */
    public function getCumulativeCounts(): array
    {
        return $this->cumulativeCounts;
    }

    /**
     * @param array<int, mixed> $values
     * @param array{
     *   field_type?: string|null,
     *   field_name?: string,
     *   percentile?: float|int|string
     * } $options
     */
    public function aggregate(string $type, array $values, array $options = []): mixed
    {
        $metric = strtolower(trim($type));
        $fieldType = strtolower((string) ($options['field_type'] ?? ''));
        $fieldName = (string) ($options['field_name'] ?? '');
        $isDateField = in_array($fieldType, ['date', 'datetime'], true);

        if ($metric === '' || $metric === 'none') {
            return $this->firstNonEmptyFormatted($values, $fieldType);
        }

        if ($isDateField) {
            return $this->aggregateDateMetric($metric, $values, $fieldType);
        }

        return $this->aggregateNumericOrMixedMetric($metric, $values, $fieldName, $options);
    }

    /**
     * @param array<int, mixed> $values
     */
    private function aggregateDateMetric(string $metric, array $values, string $fieldType): mixed
    {
        return match ($metric) {
            'min' => $this->formatDateForField(AggregationPrimitives::minDate($values), $fieldType),
            'max' => $this->formatDateForField(AggregationPrimitives::maxDate($values), $fieldType),
            'count', 'count_not_empty' => AggregationPrimitives::countNonEmpty($values),
            'count_empty' => AggregationPrimitives::countEmpty($values),
            'count_unique' => AggregationPrimitives::countUniqueNonEmpty($values),
            'count_not_unique' => AggregationPrimitives::countNotUniqueNonEmpty($values),
            'mode' => AggregationPrimitives::modeNonEmpty($values),
            'last_value' => $this->lastNonEmptyFormatted($values, $fieldType),
            default => $this->firstNonEmptyFormatted($values, $fieldType),
        };
    }

    /**
     * @param array<int, mixed> $values
     * @param array{
     *   field_type?: string|null,
     *   field_name?: string,
     *   percentile?: float|int|string
     * } $options
     */
    private function aggregateNumericOrMixedMetric(string $metric, array $values, string $fieldName, array $options): mixed
    {
        return match ($metric) {
            'sum' => AggregationPrimitives::sum($values, false),
            'sum_no_outliers' => AggregationPrimitives::sum($values, true),
            'avg' => AggregationPrimitives::average($values, false),
            'avg_no_outliers' => AggregationPrimitives::average($values, true),
            'min' => AggregationPrimitives::minNumber($values),
            'max' => AggregationPrimitives::maxNumber($values),
            'count' => AggregationPrimitives::countNonEmpty($values),
            'count_unique' => AggregationPrimitives::countUniqueNonEmpty($values),
            'count_empty' => AggregationPrimitives::countEmpty($values),
            'count_not_empty' => AggregationPrimitives::countNonEmpty($values),
            'count_not_unique' => AggregationPrimitives::countNotUniqueNonEmpty($values),
            'mode' => AggregationPrimitives::modeNonEmpty($values),
            'std_dev' => AggregationPrimitives::stdDevSample($values, false),
            'std_dev_no_outliers' => AggregationPrimitives::stdDevSample($values, true),
            'variance' => AggregationPrimitives::varianceSample($values, false),
            'variance_no_outliers' => AggregationPrimitives::varianceSample($values, true),
            'range' => AggregationPrimitives::range($values, false),
            'range_no_outliers' => AggregationPrimitives::range($values, true),
            'growth_rate' => AggregationPrimitives::growthRate($values, false),
            'growth_rate_no_outliers' => AggregationPrimitives::growthRate($values, true),
            'percentile_50' => AggregationPrimitives::percentile($values, 50),
            'percentile_25' => AggregationPrimitives::percentile($values, 25),
            'percentile_75' => AggregationPrimitives::percentile($values, 75),
            'percentile' => $this->percentileFromOptions($values, $options),
            'last_value' => $this->lastNonEmptyValue($values),
            'cumsum' => $this->cumulativeSum($fieldName, $values),
            'cumcount' => $this->cumulativeCount($fieldName, $values),
            'percent_total' => $this->percentTotalBase($fieldName, $values),
            default => $this->firstNonEmptyValue($values),
        };
    }

    /**
     * @param array<int, mixed> $values
     * @param array{
     *   field_type?: string|null,
     *   field_name?: string,
     *   percentile?: float|int|string
     * } $options
     */
    private function percentileFromOptions(array $values, array $options): ?float
    {
        $percentile = $options['percentile'] ?? null;
        if (!is_numeric($percentile)) {
            throw new InvalidArgumentException('Percentile metric requires numeric option "percentile".');
        }
        return AggregationPrimitives::percentile($values, (float) $percentile);
    }

    /**
     * @param array<int, mixed> $values
     */
    private function cumulativeSum(string $fieldName, array $values): float
    {
        $sum = AggregationPrimitives::sum($values, false) ?? 0.0;
        if ($fieldName === '') {
            return (float) $sum;
        }

        $this->cumulativeSums[$fieldName] = ($this->cumulativeSums[$fieldName] ?? 0) + $sum;
        return (float) $this->cumulativeSums[$fieldName];
    }

    /**
     * @param array<int, mixed> $values
     */
    private function cumulativeCount(string $fieldName, array $values): int
    {
        $count = AggregationPrimitives::countNonEmpty($values);
        if ($fieldName === '') {
            return $count;
        }

        $this->cumulativeCounts[$fieldName] = ($this->cumulativeCounts[$fieldName] ?? 0) + $count;
        return $this->cumulativeCounts[$fieldName];
    }

    /**
     * @param array<int, mixed> $values
     */
    private function percentTotalBase(string $fieldName, array $values): int
    {
        $count = AggregationPrimitives::countNonEmpty($values);
        if ($fieldName === '') {
            return $count;
        }

        $this->cumulativeSums[$fieldName] = ($this->cumulativeSums[$fieldName] ?? 0) + $count;
        return $count;
    }

    /**
     * @param array<int, mixed> $values
     */
    private function firstNonEmptyValue(array $values): mixed
    {
        $filtered = AggregationPrimitives::nonEmptyValues($values);
        if ($filtered === []) {
            return null;
        }
        return reset($filtered);
    }

    /**
     * @param array<int, mixed> $values
     */
    private function lastNonEmptyValue(array $values): mixed
    {
        $filtered = AggregationPrimitives::nonEmptyValues($values);
        if ($filtered === []) {
            return null;
        }
        return $filtered[count($filtered) - 1];
    }

    /**
     * @param array<int, mixed> $values
     */
    private function firstNonEmptyFormatted(array $values, string $fieldType): mixed
    {
        $value = $this->firstNonEmptyValue($values);
        return $this->formatScalarOrDateValue($value, $fieldType);
    }

    /**
     * @param array<int, mixed> $values
     */
    private function lastNonEmptyFormatted(array $values, string $fieldType): mixed
    {
        $value = $this->lastNonEmptyValue($values);
        return $this->formatScalarOrDateValue($value, $fieldType);
    }

    private function formatScalarOrDateValue(mixed $value, string $fieldType): mixed
    {
        if (!in_array($fieldType, ['date', 'datetime'], true)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $this->formatDateForField(DateTimeImmutable::createFromInterface($value), $fieldType);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return $this->formatDateForField(new DateTimeImmutable($value), $fieldType);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    private function formatDateForField(?DateTimeImmutable $date, string $fieldType): ?string
    {
        if ($date === null) {
            return null;
        }
        return $fieldType === 'datetime'
            ? $date->format('Y-m-d H:i:s')
            : $date->format('Y-m-d');
    }
}
