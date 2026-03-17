<?php
namespace Builders;

use App\{Get};

!defined('MILK_DIR') && die(); // Prevents direct access

/**
 * ScheduleGridBuilder - Fluent interface for creating schedule grid views
 *
 * Extends GetDataBuilder to provide schedule-specific functionality with
 * automatic period filtering and cell grouping for resource planning.
 *
 * Pattern: Same as CalendarBuilder but with dynamic rows from data
 *
 * @package Builders
 * @author MilkAdmin
 */
class ScheduleGridBuilder extends GetDataBuilder
{
    // Period configuration
    protected $period_type = 'week'; // week|month|day|custom
    protected $month = null;
    protected $year = null;
    protected $week_number = null;
    protected $start_date = null;  // DateTime
    protected $end_date = null;    // DateTime

    // Locale (null = auto-detect from user)
    protected $locale = null;

    // Field mappings (like CalendarBuilder + row_id)
    protected $field_mappings = [
        'row_id' => 'resource_id',
        'id' => 'id',
        'start_datetime' => 'start_datetime',
        'end_datetime' => 'end_datetime',
        'label' => 'label',
        'class' => 'cell_class',
        'color' => 'color',
    ];

    protected $date_start_field = 'start_datetime';
    protected $date_end_field = 'end_datetime';

    // Header customization
    protected $header_title = 'Schedule';
    protected $header_icon = '';
    protected $header_color = 'primary';

    // Display options
    protected $show_header = true;
    protected $show_navigation = true;

    // Date range configuration for navigation
    protected $min_year = null;
    protected $max_year = null;

    // Custom rendering
    protected $custom_cell_renderer = null;

    // Grid styling attributes
    protected $grid_attrs = [];

    // Time interval for day view (in minutes: 10, 15, 30, 60)
    protected $time_interval = 15;

    // Row header label (for the corner cell)
    protected $row_header_label = '';

    // Column width (CSS value: '60px', '5rem', '1fr', etc. null = auto 1fr)
    protected $column_width = null;

    // Click handlers
    protected $on_event_click_url = '';
    protected $on_empty_cell_click_url = '';

    // ========================================================================
    // PERIOD METHODS
    // ========================================================================

    /**
     * Set the period type
     *
     * @param string $type Period type: 'week', 'month', 'day', 'custom'
     * @return static For method chaining
     *
     * @example ->setPeriod('week')
     */
    public function setPeriod(string $type): static {
        $this->resetFieldContext();
        if (in_array($type, ['week', 'month', 'day', 'custom'])) {
            $this->period_type = $type;
        }
        return $this;
    }

    /**
     * Set week and year for weekly view
     *
     * @param int $week Week number (1-53)
     * @param int $year Year
     * @return static For method chaining
     *
     * @example ->setWeek(3, 2025)
     */
    public function setWeek(int $week, int $year): static {
        $this->resetFieldContext();
        $this->period_type = 'week';
        $this->week_number = $week;
        $this->year = $year;
        $this->calculateWeekDates();
        // Filter will be applied in render() after field mapping
        return $this;
    }

    /**
     * Set month and year for monthly view
     *
     * @param int $month Month (1-12)
     * @param int $year Year
     * @return static For method chaining
     *
     * @example ->setMonth(1, 2025)
     */
    public function setMonth(int $month, int $year): static {
        $this->resetFieldContext();
        $this->period_type = 'month';
        $this->month = max(1, min(12, $month));
        $this->year = $year;
        $this->calculateMonthDates();
        // Filter will be applied in render() after field mapping
        return $this;
    }

    /**
     * Set custom date range
     *
     * @param \DateTime $start Start date
     * @param \DateTime $end End date
     * @return static For method chaining
     *
     * @example ->setDateRange(new DateTime('2025-01-20'), new DateTime('2025-01-27'))
     */
    public function setDateRange(\DateTime $start, \DateTime $end): static {
        $this->resetFieldContext();
        $this->period_type = 'custom';
        $this->start_date = clone $start;
        $this->end_date = clone $end;
        // Filter will be applied in render() after field mapping
        return $this;
    }

    /**
     * Auto-detect period from REQUEST parameters
     * Uses grid_id to fetch parameters from $_REQUEST[$grid_id]
     *
     * @return static For method chaining
     */
    public function detectPeriodFromRequest(): static {
        $grid_id = $this->getId();
        $grid_params = $_REQUEST[$grid_id] ?? [];

        $period_type = $grid_params['period_type'] ?? 'week';

        if ($period_type === 'week') {
            $week = (int)($grid_params['week'] ?? date('W'));
            $year = (int)($grid_params['year'] ?? date('Y'));
            $this->setWeek($week, $year);
        } elseif ($period_type === 'month') {
            $month = (int)($grid_params['month'] ?? date('n'));
            $year = (int)($grid_params['year'] ?? date('Y'));
            $this->setMonth($month, $year);
        } elseif ($period_type === 'day') {
            // Day view: expects 'date' parameter (Y-m-d format)
            $date_str = $grid_params['date'] ?? date('Y-m-d');
            try {
                $date = new \DateTime($date_str);
                $start = (clone $date)->setTime(0, 0, 0);
                $end = (clone $date)->setTime(23, 59, 59);
                $this->setDateRange($start, $end);
                $this->period_type = 'day'; // Override 'custom' set by setDateRange
            } catch (\Exception $e) {
                error_log("detectPeriodFromRequest: Invalid date '{$date_str}', using today");
                $this->setDateRange(new \DateTime('today'), new \DateTime('today 23:59:59'));
                $this->period_type = 'day';
            }
        } elseif ($period_type === 'custom') {
            // Custom range: expects 'start_date' and 'end_date' parameters
            $start_str = $grid_params['start_date'] ?? date('Y-m-d');
            $end_str = $grid_params['end_date'] ?? date('Y-m-d');
            try {
                $start = new \DateTime($start_str);
                $end = new \DateTime($end_str);
                $this->setDateRange($start, $end);
            } catch (\Exception $e) {
                error_log("detectPeriodFromRequest: Invalid date range, using today");
                $this->setDateRange(new \DateTime('today'), new \DateTime('today'));
            }
        }

        return $this;
    }

    // ========================================================================
    // FIELD MAPPING METHODS
    // ========================================================================

    /**
     * Map database fields to schedule grid properties
     *
     * @param array $mappings Field mappings [property => db_field or callable]
     * @return static For method chaining
     *
     * @example ->mapFields([
     *   'row_id' => 'teacher_id',
     *   'id' => 'booking_id',
     *   'start_datetime' => 'booking_start',
     *   'end_datetime' => 'booking_end',
     *   'label' => 'course_name',
     *   'class' => fn($row) => 'booking-' . $row->status
     * ])
     */
    public function mapFields(array $mappings): static {
        $this->resetFieldContext();
        $this->field_mappings = array_merge($this->field_mappings, $mappings);

        // Update start/end field references
        if (isset($mappings['start_datetime'])) {
            $this->date_start_field = $mappings['start_datetime'];
        }
        if (isset($mappings['end_datetime'])) {
            $this->date_end_field = $mappings['end_datetime'];
        }

        return $this;
    }

    // ========================================================================
    // DISPLAY CONFIGURATION METHODS
    // ========================================================================

    /**
     * Set locale for grid rendering
     *
     * @param string $locale Locale string (e.g., 'en_US', 'it_IT', 'fr_FR')
     * @return static For method chaining
     *
     * @example ->setLocale('it_IT')
     */
    public function setLocale(string $locale): static {
        $this->resetFieldContext();
        $this->locale = $locale;
        return $this;
    }

    /**
     * Set header title
     *
     * @param string $title Header title
     * @return static For method chaining
     *
     * @example ->setHeaderTitle('Teacher Schedule')
     */
    public function setHeaderTitle(string $title): static {
        $this->resetFieldContext();
        $this->header_title = $title;
        return $this;
    }

    /**
     * Set header icon (Bootstrap Icons class)
     *
     * @param string $icon Icon class (e.g., 'bi-calendar3')
     * @return static For method chaining
     *
     * @example ->setHeaderIcon('bi-calendar3')
     */
    public function setHeaderIcon(string $icon): static {
        $this->resetFieldContext();
        $this->header_icon = $icon;
        return $this;
    }

    /**
     * Set header color
     *
     * Predefined options: 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'
     *
     * @param string $color Color name or custom class
     * @return static For method chaining
     *
     * @example ->setHeaderColor('success')
     */
    public function setHeaderColor(string $color): static {
        $this->resetFieldContext();
        $this->header_color = $color;
        return $this;
    }

    /**
     * Show or hide the grid header
     *
     * @param bool $show Show header
     * @return static For method chaining
     *
     * @example ->setShowHeader(false)
     */
    public function setShowHeader(bool $show): static {
        $this->resetFieldContext();
        $this->show_header = $show;
        return $this;
    }

    /**
     * Show or hide navigation controls
     *
     * @param bool $show Show navigation
     * @return static For method chaining
     *
     * @example ->setShowNavigation(true)
     */
    public function setShowNavigation(bool $show): static {
        $this->resetFieldContext();
        $this->show_navigation = $show;
        return $this;
    }

    /**
     * Set minimum navigable year
     *
     * @param int $year Minimum year
     * @return static For method chaining
     *
     * @example ->setMinYear(2020)
     */
    public function setMinYear(int $year): static {
        $this->resetFieldContext();
        $this->min_year = $year;
        return $this;
    }

    /**
     * Set maximum navigable year
     *
     * @param int $year Maximum year
     * @return static For method chaining
     *
     * @example ->setMaxYear(2030)
     */
    public function setMaxYear(int $year): static {
        $this->resetFieldContext();
        $this->max_year = $year;
        return $this;
    }

    /**
     * Set date range for grid navigation
     *
     * @param int $minYear Minimum year
     * @param int $maxYear Maximum year
     * @return static For method chaining
     *
     * @example ->setYearRange(2020, 2030)
     */
    public function setYearRange(int $minYear, int $maxYear): static {
        $this->resetFieldContext();
        $this->min_year = $minYear;
        $this->max_year = $maxYear;
        return $this;
    }

    /**
     * Set custom cell renderer for cell override
     *
     * The renderer receives these parameters:
     * - string $row_id: Row identifier
     * - string $col_id: Column identifier
     * - array|null $event: Event data or null for empty cell
     * - bool $is_grouped: Whether this cell is part of a group
     * - int $colspan: Number of columns spanned
     *
     * @param callable $renderer Custom renderer function
     * @return static For method chaining
     *
     * @example ->setCustomCellRenderer(function($row_id, $col_id, $event, $is_grouped, $colspan) {
     *     return '<div>Custom HTML</div>';
     * })
     */
    public function setCustomCellRenderer(callable $renderer): static {
        $this->resetFieldContext();
        $this->custom_cell_renderer = $renderer;
        return $this;
    }

    // ========================================================================
    // GRID STYLING METHODS
    // ========================================================================

    /**
     * Set grid attributes for any element
     *
     * @param array $attrs Attributes array
     * @return static For method chaining
     */
    public function setGridAttrs(array $attrs): static {
        $this->grid_attrs = $attrs;
        return $this;
    }

    /**
     * Add a single attribute to a grid element
     *
     * @param string $element Element name (container, grid, row-header, etc.)
     * @param string $key Attribute key (usually 'class')
     * @param string $value Attribute value
     * @return static For method chaining
     */
    public function addGridAttr($element, $key, $value): static {
        $this->grid_attrs[$element][$key] = $value;
        return $this;
    }

    /**
     * Set grid color theme
     *
     * Applies a coordinated color scheme to the grid with predefined Bootstrap colors.
     *
     * @param string $color Color name: 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'
     * @return static For method chaining
     *
     * @example ->gridColor('primary')
     */
    public function gridColor($color): static {
        $this->setHeaderColor($color);
        $this->addGridAttr('container', 'data-grid-color', $color);
        return $this;
    }

    /**
     * Set time interval for day view columns
     *
     * @param int $minutes Interval in minutes (10, 15, 30, or 60)
     * @return static For method chaining
     *
     * @example ->setTimeInterval(30) // 30-minute intervals
     */
    public function setTimeInterval(int $minutes): static {
        if (in_array($minutes, [10, 15, 30, 60])) {
            $this->time_interval = $minutes;
        }
        return $this;
    }

    /**
     * Set label for row header column (corner cell)
     *
     * @param string $label Label text
     * @return static For method chaining
     *
     * @example ->setRowHeaderLabel('Aule')
     */
    public function setRowHeaderLabel(string $label): static {
        $this->row_header_label = $label;
        return $this;
    }

    /**
     * Set column width for data columns
     *
     * @param string|null $width CSS width value ('60px', '5rem', '100px', etc.) or null for auto (1fr)
     * @return static For method chaining
     *
     * @example ->setColumnWidth('60px')  // Fixed 60px columns
     * @example ->setColumnWidth('5rem')   // Fixed 5rem columns
     * @example ->setColumnWidth(null)     // Auto width (default 1fr)
     */
    public function setColumnWidth(?string $width): static {
        $this->column_width = $width;
        return $this;
    }

    /**
     * Set URL template for event click
     *
     * Available placeholders:
     * - %id% = event ID
     * - %row_id% = row identifier (resource/room)
     * - %col_id% = column identifier (date or time)
     *
     * @param string $url URL template
     * @return static For method chaining
     *
     * @example ->onEventClick('?page=lessons&action=edit&id=%id%')
     * @example ->onEventClick('?page=bookings&room=%row_id%&time=%col_id%&id=%id%')
     */
    public function onEventClick(string $url): static {
        $this->on_event_click_url = $url;
        return $this;
    }

    /**
     * Set URL template for empty cell click
     *
     * Available placeholders:
     * - %row_id% = row identifier (resource/room)
     * - %col_id% = column identifier (date or time)
     * - %date% = date in Y-m-d format (for week/month views)
     * - %time% = time in H:i format (for day view)
     *
     * @param string $url URL template
     * @return static For method chaining
     *
     * @example ->onEmptyCellClick('?page=lessons&action=new&room=%row_id%&time=%col_id%')
     * @example ->onEmptyCellClick('?page=bookings&action=create&room=%row_id%&date=%date%')
     */
    public function onEmptyCellClick(string $url): static {
        $this->on_empty_cell_click_url = $url;
        return $this;
    }

    // ========================================================================
    // INTERNAL METHODS
    // ========================================================================

    /**
     * Calculate start and end dates for week period
     */
    protected function calculateWeekDates(): void {
        if ($this->week_number === null || $this->year === null) {
            return;
        }

        $dto = new \DateTime();
        $dto->setISODate($this->year, $this->week_number);
        $dto->setTime(0, 0, 0);  // Ensure start of day
        $this->start_date = clone $dto;

        $dto->modify('+6 days');
        $dto->setTime(23, 59, 59);  // Ensure end of day
        $this->end_date = $dto;
    }

    /**
     * Calculate start and end dates for month period
     */
    protected function calculateMonthDates(): void {
        if ($this->month === null || $this->year === null) {
            return;
        }

        $this->start_date = new \DateTime(sprintf('%04d-%02d-01 00:00:00', $this->year, $this->month));
        $this->end_date = new \DateTime(date('Y-m-t 23:59:59', mktime(0, 0, 0, $this->month, 1, $this->year)));
    }

    /**
     * Apply period filter to the query
     *
     * Filters events that overlap with the current period
     */
    protected function applyPeriodFilter(): void {
        if ($this->start_date === null || $this->end_date === null) {
            error_log("applyPeriodFilter: start_date or end_date is null, skipping filter");
            return;
        }

        // Skip filter if field mappings are callable (data will be filtered post-query)
        if (is_callable($this->date_start_field) || is_callable($this->date_end_field)) {
            error_log("applyPeriodFilter: Field mappings are callable, skipping SQL filter");
            return;
        }

        // Preserve time only for custom ranges; other periods use full-day bounds.
        if ($this->period_type === 'custom') {
            $startStr = $this->start_date->format('Y-m-d H:i:s');
            $endStr = $this->end_date->format('Y-m-d H:i:s');
        } else {
            $startStr = $this->start_date->format('Y-m-d 00:00:00');
            $endStr = $this->end_date->format('Y-m-d 23:59:59');
        }
        error_log("applyPeriodFilter: Filtering from {$startStr} to {$endStr}");

        $db = $this->model->getDb();
        $startField = $db->qn($this->date_start_field);
        $endField = $db->qn($this->date_end_field);
        error_log("applyPeriodFilter: Using fields {$startField} and {$endField}");

        // Include records that overlap with period
        $where = "
            (({$startField} >= ? AND {$startField} <= ?) OR
             ({$endField} >= ? AND {$endField} <= ?) OR
             ({$startField} < ? AND {$endField} > ?))
        ";

        $params = [
            $startStr, $endStr,
            $startStr, $endStr,
            $startStr, $endStr
        ];

        $this->query->where($where, $params);
    }

    /**
     * Get grid HTML string
     *
     * @return string Complete HTML grid ready for display
     */
    public function render(): string {
        // Auto-detect period from REQUEST if not set
        if ($this->start_date === null || $this->end_date === null) {
            $this->detectPeriodFromRequest();
        }

        // Use locale from config if not set
        if (empty($this->locale)) {
            $this->locale = Get::userLocale() ?: 'en_US';
        }

        // Apply period filter now that field mappings are set
        $this->applyPeriodFilter();

        // Apply custom filters from grid request
        $grid_params = $_REQUEST[$this->getId()] ?? [];
        $this->modellist_service->applyFilters($this->query, $grid_params);

        // Remove pagination (like ChartBuilder)
        $this->query->clean('limit');

        // Get events data (processed rows)
        $rows = $this->getRowsData();
        error_log("ScheduleGridBuilder: Got " . count($rows) . " rows from getRowsData()");
        $events = [];

        // Get raw rows from DataProcessor
        $rows_raw = $this->dataProcessor->getRawRows();
        error_log("ScheduleGridBuilder: Got " . count($rows_raw) . " raw rows");

        // PRE-PROCESS: Assign track indices to raw_rows for overlapping events
        $this->assignTrackIndices($rows, $rows_raw);

        // Process rows into events array (like CalendarBuilder)
        foreach ($rows as $index => $row) {
            $raw_row = $rows_raw[$index] ?? null;

            if (!$raw_row) {
                continue;
            }

            // Get track index from raw_row (added by assignTrackIndices)
            $track_index = $raw_row->_track_index ?? 0;

            // Get datetime using getEventProperty (handles both string fields and callables)
            $start_value = $this->getEventProperty($row, $raw_row, 'start_datetime', null, $track_index);
            $end_value = $this->getEventProperty($row, $raw_row, 'end_datetime', null, $track_index);

            if (!$start_value || !$end_value) {
                continue;
            }

            // Parse DateTime
            if ($start_value instanceof \DateTime) {
                $start = $start_value;
            } else {
                try {
                    $start = new \DateTime($start_value);
                } catch (\Exception $e) {
                    continue;
                }
            }

            if ($end_value instanceof \DateTime) {
                $end = $end_value;
            } else {
                try {
                    $end = new \DateTime($end_value);
                } catch (\Exception $e) {
                    $end = $start;
                }
            }

            // Build event array
            $event = [
                'row_id' => $this->getEventProperty($row, $raw_row, 'row_id', null, $track_index),
                'id' => $this->getEventProperty($row, $raw_row, 'id', null, $track_index),
                'start_datetime' => $start,
                'end_datetime' => $end,
                'label' => $this->getEventProperty($row, $raw_row, 'label', '', $track_index),
                'class' => $this->getEventProperty($row, $raw_row, 'class', '', $track_index),
                'color' => $this->getEventProperty($row, $raw_row, 'color', '', $track_index),
                '_track_index' => $track_index, // Make track index available in event array
            ];
            $events[] = $event;
        }

        error_log("ScheduleGridBuilder: Processed " . count($events) . " events");
        if (count($events) > 0) {
            error_log("ScheduleGridBuilder: First event - " . $events[0]['label'] . " from " . $events[0]['start_datetime']->format('Y-m-d H:i'));
        }

        // Generate grid HTML
        return $this->generateGridHtml($events);
    }

    /**
     * Set grid ID for AJAX requests and HTML element identification
     *
     * @param string $grid_id Grid identifier
     * @return static For method chaining
     *
     * @example ->setGridId('my_grid')
     */
    public function setGridId(string $grid_id): static {
        $this->table_id = $grid_id;
        return $this;
    }

    /**
     * Get grid ID
     *
     * Returns the grid ID, generating a default one if not set
     *
     * @return string Grid identifier
     */
    public function getId(): string {
        if (empty($this->table_id)) {
            $this->table_id = 'schedule_grid_' . uniqid();
        }
        return $this->table_id;
    }

    /**
     * Generate grid HTML with events
     *
     * @param array $events Event data from database
     * @return string Grid HTML
     */
    protected function generateGridHtml($events): string {
        // Apply default values if min/max years not set
        $minYear = $this->min_year ?? (date('Y') - 2);
        $maxYear = $this->max_year ?? (date('Y') + 5);

        // Determine row_id field
        $row_id_field = is_string($this->field_mappings['row_id'])
            ? $this->field_mappings['row_id']
            : 'resource_id';

        // Load ScheduleGrid plugin and pass all necessary variables
        return Get::themePlugin('schedulegrid', [
            'events' => $events,
            'grid_id' => $this->getId(),
            'period_type' => $this->period_type,
            'month' => $this->month,
            'year' => $this->year,
            'week_number' => $this->week_number,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'locale' => $this->locale,
            'header_title' => $this->header_title,
            'header_icon' => $this->header_icon,
            'header_color' => $this->header_color,
            'min_year' => $minYear,
            'max_year' => $maxYear,
            'show_header' => $this->show_header,
            'show_navigation' => $this->show_navigation,
            'custom_cell_renderer' => $this->custom_cell_renderer,
            'grid_attrs' => $this->grid_attrs,
            'row_id_field' => $row_id_field,
            'time_interval' => $this->time_interval,
            'row_header_label' => $this->row_header_label,
            'column_width' => $this->column_width,
            'on_event_click_url' => $this->on_event_click_url,
            'on_empty_cell_click_url' => $this->on_empty_cell_click_url,
        ]);
    }

    /**
     * Get event property using field mappings
     *
     * Supports both string field names and callable functions
     *
     * @param object $event Event data object
     * @param object $event_raw Raw event object
     * @param string $property Property name (id, row_id, label, etc.)
     * @param mixed $default Default value if property not found
     * @param int $track_index Track index for overlapping events (0 = first row, 1 = second row, etc.)
     * @return mixed Property value or callable result
     */
    protected function getEventProperty($event, $event_raw, string $property, $default = null, int $track_index = 0) {
        $mapping = $this->field_mappings[$property] ?? $property;

        // Check if the mapping is a callable
        if (is_callable($mapping)) {
            // Call with three parameters: ($event, $event_raw, $track_index)
            // The third parameter is optional for backward compatibility
            return $mapping($event, $event_raw, $track_index);
        }

        // Otherwise treat it as a field name string
        $field = $mapping;
        return $event_raw->$field ?? $default;
    }

    /**
     * Assign track indices to raw rows for handling overlapping events
     * This allows multiple rows for the same resource when events overlap
     *
     * @param array $rows Processed rows
     * @param array $rows_raw Raw rows (passed by reference, will be modified)
     * @return void
     */
    protected function assignTrackIndices(array $rows, array &$rows_raw): void {
        // Group by row_id
        $grouped = [];

        foreach ($rows as $index => $row) {
            $raw_row = $rows_raw[$index] ?? null;
            if (!$raw_row) continue;

            // Get row_id (use temporary track_index 0 for this call)
            $row_id_value = $this->getEventProperty($row, $raw_row, 'row_id', null, 0);

            if ($row_id_value === null) continue;

            // Extract base row_id (remove any existing #N suffix)
            $base_row_id = preg_replace('/#\d+$/', '', $row_id_value);

            if (!isset($grouped[$base_row_id])) {
                $grouped[$base_row_id] = [];
            }

            $grouped[$base_row_id][] = [
                'index' => $index,
                'row' => $row,
                'raw_row' => &$rows_raw[$index]
            ];
        }

        // For each group, detect overlaps and assign tracks
        foreach ($grouped as $base_row_id => $items) {
            // Extract datetimes for all items in this group
            $time_data = [];
            foreach ($items as $item) {
                $start_val = $this->getEventProperty($item['row'], $item['raw_row'], 'start_datetime', null, 0);
                $end_val = $this->getEventProperty($item['row'], $item['raw_row'], 'end_datetime', null, 0);

                // Parse to DateTime
                $start = null;
                $end = null;

                if ($start_val instanceof \DateTime) {
                    $start = $start_val;
                } elseif ($start_val) {
                    try {
                        $start = new \DateTime($start_val);
                    } catch (\Exception $e) {}
                }

                if ($end_val instanceof \DateTime) {
                    $end = $end_val;
                } elseif ($end_val) {
                    try {
                        $end = new \DateTime($end_val);
                    } catch (\Exception $e) {}
                }

                $time_data[] = [
                    'item' => $item,
                    'start' => $start,
                    'end' => $end
                ];
            }

            // Sort by start time
            usort($time_data, function($a, $b) {
                if (!$a['start'] || !$b['start']) return 0;
                return $a['start'] <=> $b['start'];
            });

            // Assign to tracks using greedy algorithm
            $tracks = [];
            foreach ($time_data as $data) {
                $item = $data['item'];
                $start = $data['start'];
                $end = $data['end'];

                if (!$start || !$end) {
                    // No valid time, assign to track 0
                    $item['raw_row']->_track_index = 0;
                    continue;
                }

                $assigned = false;
                // Try to place in existing track
                foreach ($tracks as $track_idx => $track_items) {
                    $can_place = true;
                    foreach ($track_items as $existing) {
                        // Check overlap: start1 < end2 AND start2 < end1
                        if ($start < $existing['end'] && $existing['start'] < $end) {
                            $can_place = false;
                            break;
                        }
                    }

                    if ($can_place) {
                        $tracks[$track_idx][] = ['start' => $start, 'end' => $end];
                        $item['raw_row']->_track_index = $track_idx;
                        $assigned = true;
                        break;
                    }
                }

                if (!$assigned) {
                    // Create new track
                    $track_idx = count($tracks);
                    $tracks[$track_idx] = [['start' => $start, 'end' => $end]];
                    $item['raw_row']->_track_index = $track_idx;
                }
            }
        }
    }
}
