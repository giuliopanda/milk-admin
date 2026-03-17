<?php
namespace App\Analytics;

use App\Abstracts\AbstractModel;
use App\Database\Query;
use DateTimeInterface;
use InvalidArgumentException;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Lightweight orchestration wrapper for reporting-oriented data transforms.
 *
 * Supports rows as array<object>|array<array>, field/callable resolvers,
 * grouping (hash or sequential), and aggregate projections.
 */
final class DataSet implements \Countable
{
    /**
     * @var array<int, mixed>
     */
    private array $rows;

    /**
     * @param array<int, mixed> $rows
     */
    private function __construct(array $rows)
    {
        $this->rows = array_values($rows);
    }

    /**
     * @param array<int, mixed> $rows
     */
    public static function fromRows(array $rows): self
    {
        return new self($rows);
    }

    /**
     * @param iterable<mixed> $rows
     */
    public static function fromIterable(iterable $rows): self
    {
        if (is_array($rows)) {
            return new self($rows);
        }

        $buffer = [];
        foreach ($rows as $row) {
            $buffer[] = $row;
        }
        return new self($buffer);
    }

    public static function fromModel(AbstractModel $model, string $type = 'object', bool $allRecords = true): self
    {
        $rows = $model->getRawData($type, $allRecords);
        if (!is_array($rows)) {
            return new self([]);
        }
        return new self($rows);
    }

    public static function fromQuery(Query $query): self
    {
        return self::fromQueryResult($query->getResults());
    }

    /**
     * @param AbstractModel|array<int, mixed>|null|false $queryResult
     */
    public static function fromQueryResult(AbstractModel|array|null|false $queryResult): self
    {
        if ($queryResult instanceof AbstractModel) {
            return self::fromModel($queryResult, 'object', true);
        }
        if (!is_array($queryResult)) {
            return new self([]);
        }
        return new self($queryResult);
    }

    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * @return array<int, mixed>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        return $this->rows;
    }

    public function filter(callable $predicate): self
    {
        return new self(array_values(array_filter($this->rows, $predicate)));
    }

    public function map(callable $mapper): self
    {
        return new self(array_values(array_map($mapper, $this->rows)));
    }

    public function take(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must be >= 0.');
        }
        return new self(array_slice($this->rows, 0, $limit));
    }

    public function sortBy(string|callable $resolver, string $direction = 'asc', int $sortFlags = SORT_REGULAR): self
    {
        $dir = self::normalizeSortDirection($direction);
        $valueResolver = self::toResolver($resolver);
        $rows = $this->rows;

        usort($rows, static function ($a, $b) use ($valueResolver, $dir, $sortFlags): int {
            $left = $valueResolver($a);
            $right = $valueResolver($b);
            $cmp = DataSet::compareValues($left, $right, $sortFlags);
            return $dir === 'desc' ? -$cmp : $cmp;
        });

        return new self($rows);
    }

    /**
     * @return array<int, mixed>
     */
    public function values(string|callable $resolver): array
    {
        $valueResolver = self::toResolver($resolver);
        return array_map($valueResolver, $this->rows);
    }

    public function groupBy(string|callable $keyResolver, ?string $groupField = null): GroupedDataSet
    {
        $resolver = self::toResolver($keyResolver);
        $grouped = GroupingPrimitives::groupBy($this->rows, $resolver);
        $groups = [];

        foreach ($grouped as $normalizedKey => $groupRows) {
            $displayKey = $groupRows === [] ? $normalizedKey : $resolver($groupRows[0]);
            $groups[] = [
                'normalized_key' => $normalizedKey,
                'display_key' => $displayKey,
                'rows' => array_values($groupRows),
                'aggregates' => [],
            ];
        }

        return new GroupedDataSet(
            $groups,
            $groupField ?? self::defaultGroupField($keyResolver)
        );
    }

    public function groupSequential(string|callable $keyResolver, ?string $groupField = null): GroupedDataSet
    {
        $resolver = self::toResolver($keyResolver);
        $sequential = GroupingPrimitives::groupSequential($this->rows, $resolver);
        $groups = [];

        foreach ($sequential as $group) {
            $rows = $group['rows'];
            $displayKey = $rows === [] ? $group['key'] : $resolver($rows[0]);
            $groups[] = [
                'normalized_key' => $group['key'],
                'display_key' => $displayKey,
                'rows' => array_values($rows),
                'aggregates' => [],
            ];
        }

        return new GroupedDataSet(
            $groups,
            $groupField ?? self::defaultGroupField($keyResolver)
        );
    }

    public static function toResolver(string|callable $resolver): callable
    {
        if (is_callable($resolver) && !is_string($resolver)) {
            return $resolver;
        }

        if (trim($resolver) === '') {
            throw new InvalidArgumentException('Resolver must be a callable or a non-empty field path string.');
        }

        $path = trim($resolver);
        return static fn($row) => DataSet::resolveFieldValue($row, $path);
    }

    public static function resolveFieldValue($row, string $path)
    {
        if ($path === '') {
            return null;
        }

        $current = $row;
        foreach (explode('.', $path) as $segment) {
            if (is_array($current)) {
                if (!array_key_exists($segment, $current)) {
                    return null;
                }
                $current = $current[$segment];
                continue;
            }

            if (is_object($current)) {
                if (isset($current->{$segment}) || property_exists($current, $segment) || method_exists($current, '__get')) {
                    $current = $current->{$segment};
                    continue;
                }

                if ($current instanceof \ArrayAccess && isset($current[$segment])) {
                    $current = $current[$segment];
                    continue;
                }
            }

            return null;
        }

        return $current;
    }

    private static function normalizeSortDirection(string $direction): string
    {
        $direction = strtolower(trim($direction));
        if ($direction !== 'asc' && $direction !== 'desc') {
            throw new InvalidArgumentException('Sort direction must be "asc" or "desc".');
        }
        return $direction;
    }

    private static function defaultGroupField(string|callable $keyResolver): string
    {
        if (is_string($keyResolver) && trim($keyResolver) !== '') {
            return str_replace('.', '_', trim($keyResolver));
        }
        return 'group';
    }

    public static function compareValues($left, $right, int $sortFlags = SORT_REGULAR): int
    {
        if ($left === $right) {
            return 0;
        }

        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return ((float) $left) <=> ((float) $right);
        }

        if ($sortFlags === SORT_NATURAL) {
            return strnatcmp((string) $left, (string) $right);
        }

        return strcmp((string) $left, (string) $right);
    }
}

/**
 * Grouped projection of a DataSet.
 */
final class GroupedDataSet implements \Countable
{
    /**
     * @var array<int, array{
     *   normalized_key: string,
     *   display_key: mixed,
     *   rows: array<int, mixed>,
     *   aggregates: array<string, mixed>
     * }>
     */
    private array $groups;

    private string $groupField;
    private ?string $rawRowsField = null;

    /**
     * @param array<int, array{
     *   normalized_key: string,
     *   display_key: mixed,
     *   rows: array<int, mixed>,
     *   aggregates: array<string, mixed>
     * }> $groups
     */
    public function __construct(array $groups, string $groupField)
    {
        $this->groups = array_values($groups);
        $this->groupField = $groupField !== '' ? $groupField : 'group';
    }

    public function count(): int
    {
        return count($this->groups);
    }

    public function aggregate(string|callable $valueResolver, string $aggregation, ?string $as = null, array $options = []): self
    {
        $resolver = DataSet::toResolver($valueResolver);
        $metric = strtolower(trim($aggregation));
        if ($metric === '') {
            throw new InvalidArgumentException('Aggregation name cannot be empty.');
        }

        $alias = $as ?? $this->defaultAggregateAlias($valueResolver, $metric);
        $excludeOutliers = (bool) ($options['exclude_outliers'] ?? false);

        foreach ($this->groups as $index => $group) {
            $values = array_map($resolver, $group['rows']);
            $this->groups[$index]['aggregates'][$alias] = $this->computeAggregate(
                $metric,
                $values,
                $excludeOutliers,
                $options
            );
        }

        return $this;
    }

    public function countRows(string $as = '___count___'): self
    {
        foreach ($this->groups as $index => $group) {
            $this->groups[$index]['aggregates'][$as] = count($group['rows']);
        }
        return $this;
    }

    public function keepRawRows(string $as = '___raw_data___'): self
    {
        $this->rawRowsField = $as;
        return $this;
    }

    public function sortBy(string|callable $resolver, string $direction = 'asc', int $sortFlags = SORT_REGULAR): self
    {
        $dir = strtolower(trim($direction));
        if ($dir !== 'asc' && $dir !== 'desc') {
            throw new InvalidArgumentException('Sort direction must be "asc" or "desc".');
        }

        usort($this->groups, function (array $left, array $right) use ($resolver, $dir, $sortFlags): int {
            if (is_callable($resolver) && !is_string($resolver)) {
                $leftValue = $resolver((object) $this->buildOutputRowArray($left));
                $rightValue = $resolver((object) $this->buildOutputRowArray($right));
            } else {
                $field = (string) $resolver;
                $leftValue = $this->extractSortValue($left, $field);
                $rightValue = $this->extractSortValue($right, $field);
            }

            $cmp = DataSet::compareValues($leftValue, $rightValue, $sortFlags);
            return $dir === 'desc' ? -$cmp : $cmp;
        });

        return $this;
    }

    public function take(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must be >= 0.');
        }
        $this->groups = array_slice($this->groups, 0, $limit);
        return $this;
    }

    public function filterGroups(callable $predicate): self
    {
        $this->groups = array_values(array_filter($this->groups, function (array $group) use ($predicate): bool {
            return (bool) $predicate((object) $this->buildOutputRowArray($group), $group['rows']);
        }));
        return $this;
    }

    /**
     * @return array<int, object|array<string, mixed>>
     */
    public function toRows(bool $asObjects = true): array
    {
        $rows = [];
        foreach ($this->groups as $group) {
            $row = $this->buildOutputRowArray($group);
            $rows[] = $asObjects ? (object) $row : $row;
        }
        return $rows;
    }

    /**
     * @return array<int, object|array<string, mixed>>
     */
    public function toArray(bool $asObjects = true): array
    {
        return $this->toRows($asObjects);
    }

    public function toDataSet(bool $asObjects = true): DataSet
    {
        return DataSet::fromRows($this->toRows($asObjects));
    }

    /**
     * @param array<int, mixed> $values
     */
    private function computeAggregate(string $metric, array $values, bool $excludeOutliers, array $options)
    {
        return match ($metric) {
            'sum' => AggregationPrimitives::sum($values, $excludeOutliers),
            'avg', 'average', 'mean' => AggregationPrimitives::average($values, $excludeOutliers),
            'min', 'minnumber' => AggregationPrimitives::minNumber($values),
            'max', 'maxnumber' => AggregationPrimitives::maxNumber($values),
            'mindate' => $this->normalizeDateOutput(AggregationPrimitives::minDate($values)),
            'maxdate' => $this->normalizeDateOutput(AggregationPrimitives::maxDate($values)),
            'count' => count($values),
            'count_non_empty' => AggregationPrimitives::countNonEmpty($values),
            'count_empty' => AggregationPrimitives::countEmpty($values),
            'count_unique_non_empty' => AggregationPrimitives::countUniqueNonEmpty($values),
            'count_not_unique_non_empty' => AggregationPrimitives::countNotUniqueNonEmpty($values),
            'mode' => AggregationPrimitives::modeNonEmpty($values),
            'variance', 'variance_sample' => AggregationPrimitives::varianceSample($values, $excludeOutliers),
            'stddev', 'std_dev', 'stddev_sample' => AggregationPrimitives::stdDevSample($values, $excludeOutliers),
            'range' => AggregationPrimitives::range($values, $excludeOutliers),
            'growth', 'growth_rate' => AggregationPrimitives::growthRate($values, $excludeOutliers),
            'median' => AggregationPrimitives::median($values),
            'percentile' => $this->computePercentile($values, $options),
            default => throw new InvalidArgumentException('Unsupported aggregation "' . $metric . '".'),
        };
    }

    private function computePercentile(array $values, array $options): ?float
    {
        $percentile = $options['percentile'] ?? null;
        if (!is_numeric($percentile)) {
            throw new InvalidArgumentException('Percentile aggregation requires numeric option "percentile".');
        }
        return AggregationPrimitives::percentile($values, (float) $percentile);
    }

    private function normalizeDateOutput(?DateTimeInterface $date): ?string
    {
        return $date?->format(DateTimeInterface::ATOM);
    }

    /**
     * @param array{
     *   normalized_key: string,
     *   display_key: mixed,
     *   rows: array<int, mixed>,
     *   aggregates: array<string, mixed>
     * } $group
     * @return array<string, mixed>
     */
    private function buildOutputRowArray(array $group): array
    {
        $row = [$this->groupField => $group['display_key']];

        foreach ($group['aggregates'] as $name => $value) {
            $row[$name] = $value;
        }

        if ($this->rawRowsField !== null) {
            $row[$this->rawRowsField] = $group['rows'];
        }

        return $row;
    }

    /**
     * @param array{
     *   normalized_key: string,
     *   display_key: mixed,
     *   rows: array<int, mixed>,
     *   aggregates: array<string, mixed>
     * } $group
     */
    private function extractSortValue(array $group, string $field)
    {
        if ($field === $this->groupField) {
            return $group['display_key'];
        }
        return $group['aggregates'][$field] ?? null;
    }

    private function defaultAggregateAlias(string|callable $valueResolver, string $metric): string
    {
        $base = is_string($valueResolver)
            ? str_replace('.', '_', trim($valueResolver))
            : 'value';

        return $base . '_' . $metric;
    }
}
