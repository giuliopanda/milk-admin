<?php
namespace Builders;

use App\Get;
use App\Route;
use App\Token;

!defined('MILK_DIR') && die(); // Prevents direct access

/**
 * ChartBuilder - Query-driven chart/table builder
 *
 * Uses GetDataBuilder query/filters but does not apply pagination or limits.
 */
class ChartBuilder extends GetDataBuilder
{
    protected array $structure = [];
    protected array $options = [];
    protected string $chart_type = 'bar';
    protected ?array $cached_chart_data = null;
    protected ?string $canvas_id = null;

    /**
     * Set chart structure for labels/datasets.
     *
     * @param array $structure ['field' => ['label' => 'X', 'axis' => 'x|y', 'type' => 'bar|line']]
     */
    public function structure(array $structure): static
    {
        $this->resetFieldContext();
        $this->structure = $structure;
        $this->cached_chart_data = null;
        return $this;
    }

    /**
     * Set chart type (bar|line|table).
     */
    public function type(string $type): static
    {
        $this->resetFieldContext();
        $this->chart_type = $type;
        return $this;
    }

    /**
     * Replace chart options.
     */
    public function options(array $options): static
    {
        $this->resetFieldContext();
        $this->options = $options;
        return $this;
    }

    /**
     * Set a single chart option.
     */
    public function option(string $key, mixed $value): static
    {
        $this->resetFieldContext();
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * Build chart data from the current query and structure.
     */
    public function getChartData(): array
    {
        if ($this->cached_chart_data !== null) {
            return $this->cached_chart_data;
        }

        if (empty($this->structure)) {
            return ['labels' => [], 'datasets' => []];
        }

        $query = $this->context->getQuery();
        $modelList = $this->context->getModelList();

        $modelList->applyFilters($query, $_REQUEST[$this->context->getTableId()] ?? []);

        // Charts should not paginate or limit result sets.
        $query->clean('limit');

        $result = $this->context->getModel()->get($query);
        if (!is_object($result)) {
            return ['labels' => [], 'datasets' => []];
        }

        $rows = $result->getRawData();
        $this->cached_chart_data = $modelList->getDataChart($rows, $this->structure);
        return $this->cached_chart_data;
    }

    /**
     * Render chart/table HTML via theme plugin.
     */
    public function render(): string
    {
        $wrapper_id = $this->context->getTableId();
        $canvas_id = $this->getCanvasId();
        $chart_data = $this->getChartData();
        $chart_data_encoded = base64_encode(json_encode($chart_data));
        $options_payload = empty($this->options) ? (object) [] : $this->options;
        $chart_options_encoded = base64_encode(json_encode($options_payload));

        $attrs = [
            'id' => $wrapper_id,
            'class' => 'chart-wrapper js-chart-container',
            'data-action-url' => Route::url(),
            'data-page' => $this->context->getPage(),
            'data-action' => $this->context->getRequestAction(),
            'data-chart-id' => $canvas_id,
            'data-chart-type' => $this->chart_type,
            'data-token' => Token::get($wrapper_id),
            'data-filters' => $this->getFiltersForAttributes(),
            'data-chart-data' => $chart_data_encoded,
            'data-chart-options' => $chart_options_encoded,
        ];

        $custom_data = $this->context->getCustomData();
        if (!empty($custom_data)) {
            $attrs['data-custom'] = json_encode($custom_data);
        }

        $attr_html = '';
        foreach ($attrs as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $attr_html .= ' ' . _r($key) . '="' . _r($value) . '"';
        }

        $chart_html = Get::themePlugin('chart', [
            'id' => $canvas_id,
            'type' => $this->chart_type,
            'data' => $chart_data,
            'options' => $options_payload,
        ]);
        return '<div' . $attr_html . '>' . $chart_html . '</div>';
    }

    /**
     * Disable limit usage for charts.
     */
    public function limit(int $limit): static
    {
        $this->resetFieldContext();
        $this->context->getQuery()->clean('limit');
        return $this;
    }

    /**
     * Override orderBy to avoid pagination refresh logic.
     */
    public function orderBy(string $field, string $direction = 'ASC'): static
    {
        $this->resetFieldContext();
        $this->context->getQuery()->order($field, $direction);
        return $this;
    }

    public function getResponse(): array
    {
        $response = $this->actions->getActionResults();
        $response['html'] = $this->render();
        $response['chart'] = [
            'id' => $this->getCanvasId(),
            'type' => $this->chart_type,
            'data' => $this->getChartData(),
            'options' => empty($this->options) ? (object) [] : $this->options,
        ];
        return $response;
    }

    public function setCanvasId(string $canvas_id): static
    {
        $this->resetFieldContext();
        $this->canvas_id = $canvas_id;
        return $this;
    }

    private function getCanvasId(): string
    {
        if ($this->canvas_id !== null && $this->canvas_id !== '') {
            return $this->canvas_id;
        }
        return $this->context->getTableId() . '_chart';
    }

    private function getFiltersForAttributes(): string
    {
        $filters = $this->context->getRequest()['filters'] ?? '';
        if ($filters !== '') {
            return $filters;
        }

        $defaults = $this->context->getFilterDefaults();
        if (empty($defaults)) {
            return '';
        }

        $filter_array = [];
        foreach ($defaults as $name => $value) {
            if ($value !== '' && $value !== null) {
                $filter_array[] = $name . ':' . $value;
            }
        }

        return empty($filter_array) ? '' : json_encode($filter_array);
    }
}
