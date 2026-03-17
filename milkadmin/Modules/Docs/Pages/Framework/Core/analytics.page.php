<?php
namespace Modules\Docs\Pages;
/**
 * @title Analytics
 * @guide framework
 * @order 85
 * @tags analytics, dataset, grouped dataset, data series, aggregation primitives, grouping primitives, aggregation engine, reporting
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Analytics Namespace</h1>

    <p>The <code>App\Analytics</code> namespace provides reusable building blocks for reporting and chart pipelines. It is designed to work with arrays, objects, model data, and query results with a consistent API.</p>

    <h2 class="mt-4">Core Classes</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Purpose</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>DataSet</code></td>
                    <td>Collection wrapper for rows with filter/map/sort/group operations.</td>
                </tr>
                <tr>
                    <td><code>GroupedDataSet</code></td>
                    <td>Grouped projection returned by <code>DataSet::groupBy()</code> or <code>groupSequential()</code>.</td>
                </tr>
                <tr>
                    <td><code>AggregationPrimitives</code></td>
                    <td>Pure numeric/date/statistical functions (sum, average, percentile, variance, etc.).</td>
                </tr>
                <tr>
                    <td><code>GroupingPrimitives</code></td>
                    <td>Pure grouping/bucketing helpers (group keys, date buckets, range buckets).</td>
                </tr>
                <tr>
                    <td><code>DataSeries</code></td>
                    <td>Time-series utilities (fill gaps, resample, rolling average/sum, period diff/change).</td>
                </tr>
                <tr>
                    <td><code>AggregationEngine</code></td>
                    <td>Stateful metric dispatcher for report aggregations and cumulative fields.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">DataSet Input Sources</h2>
    <p>You can build a dataset from plain rows, model data, or a query object.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Analytics\DataSet;

// 1) From rows (array of arrays/objects)
$ds = DataSet::fromRows($rows);

// 2) From model raw data
$ds = DataSet::fromModel(new SalesModel(), 'object', true);

// 3) From Query
$ds = DataSet::fromQuery($query);</code></pre>

    <h2 class="mt-4">DataSet Public Methods</h2>
    <p><code>DataSet</code> is the main row collection wrapper. Resolvers can be field paths (for example <code>customer.region</code>) or callbacks (<code>fn($row) =&gt; ...</code>).</p>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Parameters</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>fromRows()</code></td>
                    <td><code>array $rows</code></td>
                    <td>Builds a dataset from an array of rows (objects or associative arrays).</td>
                </tr>
                <tr>
                    <td><code>fromIterable()</code></td>
                    <td><code>iterable $rows</code></td>
                    <td>Builds a dataset from any iterable source; generators are consumed into an internal array.</td>
                </tr>
                <tr>
                    <td><code>fromModel()</code></td>
                    <td><code>AbstractModel $model, string $type = 'object', bool $allRecords = true</code></td>
                    <td>Loads raw rows from a model through <code>getRawData()</code>. Use <code>'object'</code> or <code>'array'</code> output style.</td>
                </tr>
                <tr>
                    <td><code>fromQuery()</code></td>
                    <td><code>Query $query</code></td>
                    <td>Builds a dataset from a query result via <code>$query-&gt;getResults()</code>.</td>
                </tr>
                <tr>
                    <td><code>fromQueryResult()</code></td>
                    <td><code>AbstractModel|array|null|false $queryResult</code></td>
                    <td>Normalizes query return values into a dataset. Non-array/false/null inputs become an empty dataset.</td>
                </tr>
                <tr>
                    <td><code>count()</code></td>
                    <td><code>none</code></td>
                    <td>Returns the number of rows in the dataset.</td>
                </tr>
                <tr>
                    <td><code>rows()</code></td>
                    <td><code>none</code></td>
                    <td>Returns current rows as an indexed array.</td>
                </tr>
                <tr>
                    <td><code>toArray()</code></td>
                    <td><code>none</code></td>
                    <td>Alias of <code>rows()</code> for array export.</td>
                </tr>
                <tr>
                    <td><code>filter()</code></td>
                    <td><code>callable $predicate</code></td>
                    <td>Returns a new dataset with rows where predicate returns true.</td>
                </tr>
                <tr>
                    <td><code>map()</code></td>
                    <td><code>callable $mapper</code></td>
                    <td>Returns a new dataset with transformed rows.</td>
                </tr>
                <tr>
                    <td><code>take()</code></td>
                    <td><code>int $limit</code></td>
                    <td>Returns first N rows. Throws an exception for negative limits.</td>
                </tr>
                <tr>
                    <td><code>sortBy()</code></td>
                    <td><code>string|callable $resolver, string $direction = 'asc', int $sortFlags = SORT_REGULAR</code></td>
                    <td>Sorts by resolver output. Direction must be <code>asc</code> or <code>desc</code>.</td>
                </tr>
                <tr>
                    <td><code>values()</code></td>
                    <td><code>string|callable $resolver</code></td>
                    <td>Returns a value list extracted from each row through resolver/path.</td>
                </tr>
                <tr>
                    <td><code>groupBy()</code></td>
                    <td><code>string|callable $keyResolver, ?string $groupField = null</code></td>
                    <td>Hash-style grouping by key. Output is a <code>GroupedDataSet</code>.</td>
                </tr>
                <tr>
                    <td><code>groupSequential()</code></td>
                    <td><code>string|callable $keyResolver, ?string $groupField = null</code></td>
                    <td>Run-length grouping for already sorted streams (new group only when key changes).</td>
                </tr>
                <tr>
                    <td><code>toResolver()</code></td>
                    <td><code>string|callable $resolver</code></td>
                    <td>Converts a field path into a callable resolver. Useful when building custom pipelines.</td>
                </tr>
                <tr>
                    <td><code>resolveFieldValue()</code></td>
                    <td><code>mixed $row, string $path</code></td>
                    <td>Resolves nested values from array/object rows using dot notation.</td>
                </tr>
                <tr>
                    <td><code>compareValues()</code></td>
                    <td><code>mixed $left, mixed $right, int $sortFlags = SORT_REGULAR</code></td>
                    <td>Utility comparator used by sorting methods (numeric-safe and null-aware).</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">GroupedDataSet Public Methods</h2>
    <p><code>GroupedDataSet</code> is returned by grouping methods and is focused on aggregate projection.</p>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Parameters</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>count()</code></td>
                    <td><code>none</code></td>
                    <td>Returns number of groups.</td>
                </tr>
                <tr>
                    <td><code>aggregate()</code></td>
                    <td><code>string|callable $valueResolver, string $aggregation, ?string $as = null, array $options = []</code></td>
                    <td>
                        Adds an aggregate field to each group.
                        Supported aggregation names:
                        <code>sum</code>,
                        <code>avg</code>/<code>average</code>/<code>mean</code>,
                        <code>min</code>/<code>minnumber</code>,
                        <code>max</code>/<code>maxnumber</code>,
                        <code>mindate</code>,
                        <code>maxdate</code>,
                        <code>count</code>,
                        <code>count_non_empty</code>,
                        <code>count_empty</code>,
                        <code>count_unique_non_empty</code>,
                        <code>count_not_unique_non_empty</code>,
                        <code>mode</code>,
                        <code>variance</code>/<code>variance_sample</code>,
                        <code>stddev</code>/<code>std_dev</code>/<code>stddev_sample</code>,
                        <code>range</code>,
                        <code>growth</code>/<code>growth_rate</code>,
                        <code>median</code>,
                        <code>percentile</code>.
                        <br>
                        Options:
                        <code>exclude_outliers</code> (bool, where supported) and <code>percentile</code> (number, required when metric is <code>percentile</code>).
                    </td>
                </tr>
                <tr>
                    <td><code>countRows()</code></td>
                    <td><code>string $as = '___count___'</code></td>
                    <td>Adds the number of original rows per group.</td>
                </tr>
                <tr>
                    <td><code>keepRawRows()</code></td>
                    <td><code>string $as = '___raw_data___'</code></td>
                    <td>Includes original group rows in output under a technical field.</td>
                </tr>
                <tr>
                    <td><code>sortBy()</code></td>
                    <td><code>string|callable $resolver, string $direction = 'asc', int $sortFlags = SORT_REGULAR</code></td>
                    <td>Sorts groups by group key or aggregate fields.</td>
                </tr>
                <tr>
                    <td><code>take()</code></td>
                    <td><code>int $limit</code></td>
                    <td>Keeps first N groups.</td>
                </tr>
                <tr>
                    <td><code>filterGroups()</code></td>
                    <td><code>callable $predicate</code></td>
                    <td>Filters groups using both projected row and raw grouped rows.</td>
                </tr>
                <tr>
                    <td><code>toRows()</code></td>
                    <td><code>bool $asObjects = true</code></td>
                    <td>Exports grouped output as object rows (default) or associative arrays.</td>
                </tr>
                <tr>
                    <td><code>toArray()</code></td>
                    <td><code>bool $asObjects = true</code></td>
                    <td>Alias for <code>toRows()</code>.</td>
                </tr>
                <tr>
                    <td><code>toDataSet()</code></td>
                    <td><code>bool $asObjects = true</code></td>
                    <td>Converts grouped output back to a regular <code>DataSet</code>.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">DataSeries Public Methods</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Parameters</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>resample()</code></td>
                    <td><code>array $series, string $fromGranularity, string $toGranularity, callable $aggregator</code></td>
                    <td>Aggregates a time series from finer to coarser granularity (<code>D -&gt; W/M/Y</code>, <code>W -&gt; M/Y</code>, <code>M -&gt; Y</code>).</td>
                </tr>
                <tr>
                    <td><code>fillGaps()</code></td>
                    <td><code>array $series, string $granularity, mixed $fillValue = null</code></td>
                    <td>Fills missing buckets between first and last bucket.</td>
                </tr>
                <tr>
                    <td><code>rollingAverage()</code></td>
                    <td><code>array $series, int $window</code></td>
                    <td>Calculates moving average; first <code>window-1</code> points are null.</td>
                </tr>
                <tr>
                    <td><code>rollingSum()</code></td>
                    <td><code>array $series, int $window</code></td>
                    <td>Calculates moving sum; first <code>window-1</code> points are null.</td>
                </tr>
                <tr>
                    <td><code>diff()</code></td>
                    <td><code>array $series</code></td>
                    <td>Period-over-period absolute delta.</td>
                </tr>
                <tr>
                    <td><code>percentChangePeriod()</code></td>
                    <td><code>array $series</code></td>
                    <td>Period-over-period percentage change.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">AggregationPrimitives Public Methods</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Parameters</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>nonEmptyValues()</code></td><td><code>array $values</code></td><td>Filters out <code>null</code>, <code>false</code>, and empty strings.</td></tr>
                <tr><td><code>numericValues()</code></td><td><code>array $values</code></td><td>Converts numeric values to finite floats.</td></tr>
                <tr><td><code>dateTimeValues()</code></td><td><code>array $values</code></td><td>Converts valid values to <code>DateTimeImmutable</code>.</td></tr>
                <tr><td><code>sum()</code></td><td><code>array $values, bool $excludeOutliers = false</code></td><td>Numeric sum.</td></tr>
                <tr><td><code>average()</code></td><td><code>array $values, bool $excludeOutliers = false</code></td><td>Arithmetic mean.</td></tr>
                <tr><td><code>minNumber()</code></td><td><code>array $values</code></td><td>Minimum numeric value.</td></tr>
                <tr><td><code>maxNumber()</code></td><td><code>array $values</code></td><td>Maximum numeric value.</td></tr>
                <tr><td><code>minDate()</code></td><td><code>array $values</code></td><td>Earliest date.</td></tr>
                <tr><td><code>maxDate()</code></td><td><code>array $values</code></td><td>Latest date.</td></tr>
                <tr><td><code>countNonEmpty()</code></td><td><code>array $values</code></td><td>Count of non-empty values.</td></tr>
                <tr><td><code>countEmpty()</code></td><td><code>array $values</code></td><td>Count of empty values (<code>null</code>, <code>false</code>, <code>''</code>).</td></tr>
                <tr><td><code>countUniqueNonEmpty()</code></td><td><code>array $values</code></td><td>Distinct non-empty count.</td></tr>
                <tr><td><code>countNotUniqueNonEmpty()</code></td><td><code>array $values</code></td><td>Number of duplicate non-empty entries.</td></tr>
                <tr><td><code>modeNonEmpty()</code></td><td><code>array $values</code></td><td>Most frequent non-empty value (first in tie order).</td></tr>
                <tr><td><code>varianceSample()</code></td><td><code>array $values, bool $excludeOutliers = false</code></td><td>Sample variance (<code>n-1</code>).</td></tr>
                <tr><td><code>stdDevSample()</code></td><td><code>array $values, bool $excludeOutliers = false</code></td><td>Sample standard deviation.</td></tr>
                <tr><td><code>range()</code></td><td><code>array $values, bool $excludeOutliers = false</code></td><td>Max-min numeric spread.</td></tr>
                <tr><td><code>growthRate()</code></td><td><code>array $values, bool $excludeOutliers = false</code></td><td>Growth percentage from first to last value.</td></tr>
                <tr><td><code>percentile()</code></td><td><code>array $values, float $percentile</code></td><td>Interpolated percentile in range 0..100.</td></tr>
                <tr><td><code>median()</code></td><td><code>array $values</code></td><td>Alias for percentile 50.</td></tr>
                <tr><td><code>removeOutliersIqr()</code></td><td><code>array $values, float $threshold = 1.5</code></td><td>IQR-based outlier removal.</td></tr>
                <tr><td><code>cumulativeSum()</code></td><td><code>array $values</code></td><td>Running sum sequence.</td></tr>
                <tr><td><code>cumulativeCount()</code></td><td><code>array $values, bool $nonEmptyOnly = true</code></td><td>Running count sequence.</td></tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">GroupingPrimitives Public Methods</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Parameters</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>groupKey()</code></td><td><code>mixed $key</code></td><td>Normalizes a value to a stable group key string.</td></tr>
                <tr><td><code>comparableKey()</code></td><td><code>mixed $value</code></td><td>Normalizes a value for uniqueness/comparison maps.</td></tr>
                <tr><td><code>dateBucketKey()</code></td><td><code>mixed $value, string $granularity</code></td><td>Returns normalized date bucket key (<code>Y/M/W/D</code>).</td></tr>
                <tr><td><code>groupBy()</code></td><td><code>array $rows, callable $keyResolver</code></td><td>Groups rows by normalized key preserving insertion order.</td></tr>
                <tr><td><code>groupSequential()</code></td><td><code>array $rows, callable $keyResolver</code></td><td>Groups by consecutive key changes only.</td></tr>
                <tr><td><code>uniqueSortedValues()</code></td><td><code>array $rows, callable $valueResolver, int $sortFlags = SORT_REGULAR</code></td><td>Distinct values sorted ascending.</td></tr>
                <tr><td><code>uniqueDateBuckets()</code></td><td><code>array $rows, callable $dateResolver, string $granularity</code></td><td>Distinct date buckets sorted ascending.</td></tr>
                <tr><td><code>formatDateBucketLabel()</code></td><td><code>string $bucket, string $granularity</code></td><td>User-facing label formatting for bucket keys.</td></tr>
                <tr><td><code>splitIntoRangeBuckets()</code></td><td><code>array $sortedUniqueValues, int $numGroups</code></td><td>Splits sorted values into contiguous range buckets.</td></tr>
                <tr><td><code>createRangeLabel()</code></td><td><code>mixed $first, mixed $last, int $groupSize, int $truncateAt = 16</code></td><td>Builds readable label for range groups.</td></tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">AggregationEngine Public Methods</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Parameters</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>reset()</code></td>
                    <td><code>none</code></td>
                    <td>Clears internal cumulative counters.</td>
                </tr>
                <tr>
                    <td><code>getCumulativeSums()</code></td>
                    <td><code>none</code></td>
                    <td>Returns current cumulative sum map by field name.</td>
                </tr>
                <tr>
                    <td><code>getCumulativeCounts()</code></td>
                    <td><code>none</code></td>
                    <td>Returns current cumulative count map by field name.</td>
                </tr>
                <tr>
                    <td><code>aggregate()</code></td>
                    <td><code>string $type, array $values, array $options = []</code></td>
                    <td>
                        Dispatches metric calculation based on <code>$type</code>.
                        Date-aware and cumulative metrics are supported.
                        Main options: <code>field_type</code>, <code>field_name</code>, <code>percentile</code>.
                        Metrics include:
                        <code>sum</code>, <code>sum_no_outliers</code>, <code>avg</code>, <code>avg_no_outliers</code>, <code>min</code>, <code>max</code>, <code>count</code>, <code>count_unique</code>, <code>count_empty</code>, <code>count_not_empty</code>, <code>count_not_unique</code>, <code>mode</code>, <code>std_dev</code>, <code>std_dev_no_outliers</code>, <code>variance</code>, <code>variance_no_outliers</code>, <code>range</code>, <code>range_no_outliers</code>, <code>growth_rate</code>, <code>growth_rate_no_outliers</code>, <code>percentile_25</code>, <code>percentile_50</code>, <code>percentile_75</code>, <code>percentile</code>, <code>last_value</code>, <code>cumsum</code>, <code>cumcount</code>, <code>percent_total</code>.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">Grouping + Aggregation Example</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Analytics\DataSet;

$topChannels = DataSet::fromRows($rows)
    ->groupBy('channel', 'channel')
    ->aggregate('net_revenue', 'sum', 'net_revenue')
    ->aggregate('orders', 'sum', 'orders')
    ->sortBy('net_revenue', 'desc')
    ->take(5)
    ->toRows();</code></pre>

    <h2 class="mt-4">Time Series Example</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Analytics\DataSeries;

$daily = [
    ['bucket' => '2025-01-01', 'value' => 1200],
    ['bucket' => '2025-01-03', 'value' => 980], // missing day
];

$filled = DataSeries::fillGaps($daily, 'D', 0);
$monthly = DataSeries::resample(
    $filled,
    'D',
    'M',
    fn(array $values): float => array_sum(array_map('floatval', $values))
);
$rolling = DataSeries::rollingAverage($monthly, 3);</code></pre>

    <h2 class="mt-4">Primitives and Engine</h2>
    <p>Use primitives for direct calculations or <code>AggregationEngine</code> for metric dispatch with cumulative state.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Analytics\AggregationPrimitives;
use App\Analytics\AggregationEngine;

$p95 = AggregationPrimitives::percentile($latencyValues, 95);

$engine = new AggregationEngine();
$sum = $engine->aggregate('sum', $values);
$running = $engine->aggregate('cumsum', $values, ['field_name' => 'revenue']);</code></pre>

    <h2 class="mt-4">Notes</h2>
    <ul>
        <li><code>DataSet</code> supports array rows and object rows, including dot notation field paths.</li>
        <li><code>groupSequential()</code> is optimized for streams already ordered by group key.</li>
        <li><code>DataSeries::resample()</code> allows only fine-to-coarse directions (<code>D -&gt; W/M/Y</code>, <code>M -&gt; Y</code>, etc.).</li>
        <li>These classes are used by the Reports module internals and can be reused directly in custom modules.</li>
    </ul>
</div>
