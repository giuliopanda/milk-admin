<?php
use App\Get;

/**
 * Class ScheduleGridData - Manages schedule grid data and logic
 *
 * This class is responsible for:
 * - Managing events
 * - Row extraction (DISTINCT on row_id field)
 * - Column generation based on period
 * - Cell grouping algorithm
 * - Configuration storage
 */
class ScheduleGridData {
    // Configuration
    private $grid_id = 'schedule_grid';
    private $period_type = 'week';
    private $locale = 'en_US';

    // Period data
    private $month = null;
    private $year = null;
    private $week_number = null;
    private $start_date = null;  // DateTime
    private $end_date = null;    // DateTime

    // Events and structure
    private $events = [];        // Array of events from Builder
    private $rows = [];          // ['row_id' => 'row_label']
    private $columns = [];       // [['id' => 'col_id', 'label' => 'label', 'date' => DateTime, ...]]

    // Configuration
    private $row_id_field = 'resource_id';

    // Header configuration
    private $header_title = 'Schedule';
    private $header_icon = '';
    private $header_color = 'primary';
    private $header_color_class = 'bg-primary';

    // Display options
    private $show_header = true;
    private $show_navigation = true;

    // Date range for navigation
    private $min_year = null;
    private $max_year = null;

    // Custom rendering
    private $custom_cell_renderer = null;

    // Grid styling
    private $grid_attrs = [];

    // Time interval for day view (in minutes)
    private $time_interval = 15;

    // Row header label (corner cell)
    private $row_header_label = '';

    // Column width (CSS value)
    private $column_width = null;

    // Click handlers
    private $on_event_click_url = '';
    private $on_empty_cell_click_url = '';

    // Action URL
    private $action_url = '';

    // Date formatter
    private $dateFormatter;

    /**
     * Constructor
     *
     * @param array $config Configuration array from Builder
     */
    public function __construct(array $config) {
        // Store events
        $this->events = $config['events'] ?? [];

        // Configuration
        $this->grid_id = $config['grid_id'] ?? 'schedule_grid';
        $this->period_type = $config['period_type'] ?? 'week';
        $this->month = $config['month'] ?? null;
        $this->year = $config['year'] ?? null;
        $this->week_number = $config['week_number'] ?? null;
        $this->start_date = $config['start_date'] ?? null;
        $this->end_date = $config['end_date'] ?? null;
        $this->locale = $config['locale'] ?? 'en_US';
        $this->row_id_field = $config['row_id_field'] ?? 'resource_id';

        // Header
        $this->header_title = $config['header_title'] ?? 'Schedule';
        $this->header_icon = $config['header_icon'] ?? '';
        $this->setHeaderColor($config['header_color'] ?? 'primary');

        // Display
        $this->show_header = $config['show_header'] ?? true;
        $this->show_navigation = $config['show_navigation'] ?? true;

        // Date range
        $this->min_year = $config['min_year'] ?? null;
        $this->max_year = $config['max_year'] ?? null;

        // Custom rendering
        $this->custom_cell_renderer = $config['custom_cell_renderer'] ?? null;

        // Styling
        $this->grid_attrs = $config['grid_attrs'] ?? [];

        // Time interval
        $this->time_interval = $config['time_interval'] ?? 15;

        // Row header label
        $this->row_header_label = $config['row_header_label'] ?? '';

        // Column width
        $this->column_width = $config['column_width'] ?? null;

        // Click handlers
        $this->on_event_click_url = $config['on_event_click_url'] ?? '';
        $this->on_empty_cell_click_url = $config['on_empty_cell_click_url'] ?? '';

        // Action URL
        $this->action_url = $_SERVER['REQUEST_URI'] ?? '';

        // Initialize date formatter
        $this->dateFormatter = new IntlDateFormatter(
            $this->locale,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE,
            null,
            null,
            'EEE d'
        );

        // Extract rows from events (DISTINCT)
        $this->extractRows();

        // Generate columns based on period
        $this->generateColumns();
    }

    // ========== GETTERS ==========

    public function getGridId() {
        return $this->grid_id;
    }

    public function getPeriodType() {
        return $this->period_type;
    }

    public function getLocale() {
        return $this->locale;
    }

    public function getRows() {
        return $this->rows;
    }

    public function getColumns() {
        return $this->columns;
    }

    public function getHeaderTitle() {
        return $this->header_title;
    }

    public function getHeaderIcon() {
        return $this->header_icon;
    }

    public function getHeaderColorClass() {
        return $this->header_color_class;
    }

    public function getRowHeaderLabel() {
        return $this->row_header_label;
    }

    public function getColumnWidth() {
        return $this->column_width;
    }

    public function getOnEventClickUrl() {
        return $this->on_event_click_url;
    }

    public function getOnEmptyCellClickUrl() {
        return $this->on_empty_cell_click_url;
    }

    public function getStartDate() {
        return $this->start_date;
    }

    public function getEndDate() {
        return $this->end_date;
    }

    public function shouldShowHeader() {
        return $this->show_header;
    }

    public function shouldShowNavigation() {
        return $this->show_navigation;
    }

    public function getMinYear() {
        return $this->min_year;
    }

    public function getMaxYear() {
        return $this->max_year;
    }

    public function getActionUrl() {
        return $this->action_url;
    }

    public function hasCustomCellRenderer() {
        return $this->custom_cell_renderer !== null;
    }

    public function getDateFormatter() {
        return $this->dateFormatter;
    }

    // ========== SETTERS ==========

    public function setHeaderColor(string $color) {
        if (in_array($color, ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'])) {
            $this->header_color_class = 'bg-' . $color;
        } else {
            $this->header_color_class = $color;
        }
        return $this;
    }

    // ========== ROW EXTRACTION (DISTINCT) ==========

    /**
     * Extract unique rows from events using DISTINCT on row_id field
     * With support for multiple rows when events overlap in the same resource
     * Uses _track_index already assigned by the Builder
     */
    protected function extractRows(): void {
        // Group events by original row_id and track their track_index
        $grouped = [];
        $max_tracks = []; // Track maximum track index for each row_id

        foreach ($this->events as &$event) {
            $row_id = $event['row_id'] ?? null;
            $track_index = $event['_track_index'] ?? 0;

            // Extract original row_id (remove track suffix if present)
            $original_row_id = preg_replace('/#\d+$/', '', $row_id);

            if ($original_row_id !== null) {
                // Update max track index for this row_id
                if (!isset($max_tracks[$original_row_id])) {
                    $max_tracks[$original_row_id] = 0;
                }
                $max_tracks[$original_row_id] = max($max_tracks[$original_row_id], $track_index);

                // Update event row_id with track suffix
                if ($track_index === 0) {
                    $event['row_id'] = $original_row_id;
                } else {
                    $event['row_id'] = $original_row_id . '#' . $track_index;
                }
            }
        }

        $rows = [];

        // Create rows based on max track indices
        foreach ($max_tracks as $original_row_id => $max_track) {
            for ($i = 0; $i <= $max_track; $i++) {
                if ($i === 0) {
                    // First track uses the original row_id
                    $rows[$original_row_id] = $original_row_id;
                } else {
                    // Additional tracks: empty label (continuation of previous row)
                    $virtual_row_id = $original_row_id . '#' . $i;
                    $rows[$virtual_row_id] = ''; // Empty label for continuation rows
                }
            }
        }

        $this->rows = $rows;
    }

    // ========== COLUMN GENERATION ==========

    /**
     * Generate columns based on period type
     * Similar to CalendarData generating days
     */
    protected function generateColumns(): void {
        error_log("generateColumns: period_type = '{$this->period_type}'");

        if ($this->period_type === 'week') {
            $this->generateWeekColumns();
        } elseif ($this->period_type === 'month') {
            $this->generateMonthColumns();
        } elseif ($this->period_type === 'day') {
            error_log("generateColumns: Calling generateDayColumns()");
            $this->generateDayColumns();
        } elseif ($this->period_type === 'custom' && $this->start_date && $this->end_date) {
            $this->generateCustomColumns();
        } else {
            error_log("generateColumns: No matching period type, columns count: " . count($this->columns));
        }
    }

    /**
     * Generate columns for week view (7 days)
     */
    protected function generateWeekColumns(): void {
        $columns = [];

        if (!$this->start_date || !$this->end_date) {
            $this->columns = $columns;
            return;
        }

        $current = clone $this->start_date;
        $end = clone $this->end_date;

        $dayFormatter = new IntlDateFormatter(
            $this->locale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            null,
            null,
            'EEE'  // Short day name
        );

        while ($current <= $end) {
            $date_str = $current->format('Y-m-d');
            $day_name = $dayFormatter->format($current);
            $day_number = $current->format('j');

            $columns[] = [
                'id' => $date_str,
                'label' => ucfirst($day_name),
                'sub_label' => $day_number,
                'date' => clone $current,
                'date_start' => (clone $current)->setTime(0, 0, 0),
                'date_end' => (clone $current)->setTime(23, 59, 59),
            ];

            $current->modify('+1 day');
        }

        $this->columns = $columns;
    }

    /**
     * Generate columns for month view (28-31 days)
     */
    protected function generateMonthColumns(): void {
        $columns = [];

        if (!$this->start_date || !$this->end_date) {
            $this->columns = $columns;
            return;
        }

        $current = clone $this->start_date;
        $end = clone $this->end_date;

        $dayFormatter = new IntlDateFormatter(
            $this->locale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            null,
            null,
            'EEE'  // Short day name
        );

        while ($current <= $end) {
            $date_str = $current->format('Y-m-d');
            $day_name = $dayFormatter->format($current);
            $day_number = $current->format('j');

            $columns[] = [
                'id' => $date_str,
                'label' => $day_number,
                'sub_label' => ucfirst($day_name),
                'date' => clone $current,
                'date_start' => (clone $current)->setTime(0, 0, 0),
                'date_end' => (clone $current)->setTime(23, 59, 59),
            ];

            $current->modify('+1 day');
        }

        $this->columns = $columns;
    }

    /**
     * Generate columns for day view (hourly columns with configurable intervals)
     */
    protected function generateDayColumns(): void {
        $columns = [];

        if (!$this->start_date || !$this->end_date) {
            error_log("generateDayColumns: Missing start_date or end_date");
            $this->columns = $columns;
            return;
        }

        error_log("generateDayColumns: Generating columns from " . $this->start_date->format('Y-m-d H:i') . " to " . $this->end_date->format('Y-m-d H:i'));

        // Generate columns based on configured time interval
        $current = clone $this->start_date;
        $end = clone $this->end_date;
        $interval = "+{$this->time_interval} minutes";

        while ($current < $end) {
            $time_str = $current->format('H:i');
            $hour = $current->format('H');
            $minute = $current->format('i');

            // Show label only on full hours or at configured intervals
            if ($minute === '00') {
                $label = $hour . ':00';
            } elseif ($this->time_interval >= 30 && $minute === '30') {
                $label = $time_str;
            } else {
                $label = '';
            }

            $sub_label = '';

            $columns[] = [
                'id' => $time_str,
                'label' => $label,
                'sub_label' => $sub_label,
                'date' => clone $current,
                'date_start' => clone $current,
                'date_end' => (clone $current)->modify($interval),
            ];

            $current->modify($interval);
        }

        error_log("generateDayColumns: Generated " . count($columns) . " columns");
        $this->columns = $columns;
    }

    /**
     * Generate columns for custom date range
     */
    protected function generateCustomColumns(): void {
        // Use same logic as week columns
        $this->generateWeekColumns();
    }

    // ========== CELL GROUPING ALGORITHM ==========

    /**
     * Get events for a specific row
     * Similar to CalendarData::getAppointmentsForDate
     *
     * @param string|int $row_id Row identifier
     * @return array Array of events for this row
     */
    public function getEventsForRow($row_id) {
        return array_filter($this->events, function($event) use ($row_id) {
            return ($event['row_id'] ?? null) == $row_id;
        });
    }

    /**
     * Get cell info for specific row and column
     * Checks if there's an event that covers this column
     * For day view: column represents a time slot, for other views: a date
     *
     * @param string|int $row_id Row identifier
     * @param string $col_date Column date (Y-m-d) or time slot (H:i)
     * @return array|null Cell data or null if empty
     */
    public function getCellForRowColumn($row_id, $col_date) {
        $events = $this->getEventsForRow($row_id);

        // For day view, col_date is a time (H:i), we need to check time overlap
        if ($this->period_type === 'day') {
            return $this->getCellForRowTime($events, $col_date);
        }

        // For other views (week, month), use date comparison
        foreach ($events as $event) {
            $start_date = $event['start_datetime']->format('Y-m-d');
            $end_date = $event['end_datetime']->format('Y-m-d');

            // Check if event covers this date (like CalendarData)
            if ($col_date >= $start_date && $col_date <= $end_date) {
                return [
                    'event' => $event,
                    'is_start' => ($col_date == $start_date),
                    'is_end' => ($col_date == $end_date),
                ];
            }
        }

        return null; // Empty cell
    }

    /**
     * Get cell for day view (time-based columns)
     *
     * @param array $events Events for this row
     * @param string $col_time Time slot (H:i)
     * @return array|null Cell data or null if empty
     */
    protected function getCellForRowTime($events, $col_time) {
        foreach ($events as $event) {
            $start_time = $event['start_datetime']->format('H:i');
            $end_time = $event['end_datetime']->format('H:i');

            // Check if event starts at or before this time slot and ends after
            if ($start_time <= $col_time && $end_time > $col_time) {
                return [
                    'event' => $event,
                    'is_start' => ($start_time == $col_time),
                    'is_end' => false, // Will be determined by colspan
                ];
            }
        }

        return null;
    }

    /**
     * Group consecutive cells for a row
     * Similar to how CalendarData handles multi-day events
     *
     * @param string|int $row_id Row identifier
     * @return array Array of grouped cells
     */
    public function getGroupedCellsForRow($row_id) {
        $grouped = [];
        $current_group = null;

        foreach ($this->columns as $col) {
            // For day view, use time (H:i), otherwise use date (Y-m-d)
            $col_date = ($this->period_type === 'day')
                ? $col['id']  // Time slot like "09:00"
                : $col['date']->format('Y-m-d');  // Date like "2026-01-23"

            $cell = $this->getCellForRowColumn($row_id, $col_date);

            if (!$cell) {
                // Empty cell: save current group and reset
                if ($current_group) {
                    $grouped[] = $current_group;
                    $current_group = null;
                }
                continue;
            }

            if (!$current_group) {
                // Start new group
                $current_group = [
                    'event' => $cell['event'],
                    'start_col' => $col['id'],
                    'end_col' => $col['id'],
                    'colspan' => 1,
                ];
            } elseif ($this->isSameEvent($current_group['event'], $cell['event'])) {
                // Same event: extend group
                $current_group['end_col'] = $col['id'];
                $current_group['colspan']++;
            } else {
                // Different event: save current and start new group
                $grouped[] = $current_group;
                $current_group = [
                    'event' => $cell['event'],
                    'start_col' => $col['id'],
                    'end_col' => $col['id'],
                    'colspan' => 1,
                ];
            }
        }

        // Add last group
        if ($current_group) {
            $grouped[] = $current_group;
        }

        return $grouped;
    }

    /**
     * Check if two events are the same
     *
     * @param array $event1 First event
     * @param array $event2 Second event
     * @return bool True if same event
     */
    protected function isSameEvent($event1, $event2): bool {
        return ($event1['id'] ?? null) === ($event2['id'] ?? null);
    }

    /**
     * Render custom cell if renderer is defined
     *
     * @param string $row_id Row identifier
     * @param string $col_id Column identifier
     * @param array|null $event Event data
     * @param bool $is_grouped Whether cell is grouped
     * @param int $colspan Colspan value
     * @return string|null Custom HTML or null
     */
    public function renderCustomCell($row_id, $col_id, $event, $is_grouped, $colspan) {
        if ($this->custom_cell_renderer === null) {
            return null;
        }

        return call_user_func(
            $this->custom_cell_renderer,
            $row_id,
            $col_id,
            $event,
            $is_grouped,
            $colspan
        );
    }

    // ========== PERIOD NAVIGATION ==========

    /**
     * Get previous period parameters
     *
     * @return array Period parameters
     */
    public function getPreviousPeriod() {
        if ($this->period_type === 'week') {
            $week = $this->week_number - 1;
            $year = $this->year;

            if ($week < 1) {
                $week = 52;  // Approximate
                $year--;
            }

            return ['period_type' => 'week', 'week' => $week, 'year' => $year];
        } elseif ($this->period_type === 'month') {
            $month = $this->month - 1;
            $year = $this->year;

            if ($month < 1) {
                $month = 12;
                $year--;
            }

            return ['period_type' => 'month', 'month' => $month, 'year' => $year];
        }

        return [];
    }

    /**
     * Get next period parameters
     *
     * @return array Period parameters
     */
    public function getNextPeriod() {
        if ($this->period_type === 'week') {
            $week = $this->week_number + 1;
            $year = $this->year;

            if ($week > 52) {
                $week = 1;
                $year++;
            }

            return ['period_type' => 'week', 'week' => $week, 'year' => $year];
        } elseif ($this->period_type === 'month') {
            $month = $this->month + 1;
            $year = $this->year;

            if ($month > 12) {
                $month = 1;
                $year++;
            }

            return ['period_type' => 'month', 'month' => $month, 'year' => $year];
        }

        return [];
    }

    /**
     * Check if previous period navigation is disabled
     *
     * @return bool
     */
    public function isPreviousPeriodDisabled() {
        $prev = $this->getPreviousPeriod();
        $prev_year = $prev['year'] ?? null;

        if ($this->min_year !== null && $prev_year !== null) {
            return $prev_year < $this->min_year;
        }

        return false;
    }

    /**
     * Check if next period navigation is disabled
     *
     * @return bool
     */
    public function isNextPeriodDisabled() {
        $next = $this->getNextPeriod();
        $next_year = $next['year'] ?? null;

        if ($this->max_year !== null && $next_year !== null) {
            return $next_year > $this->max_year;
        }

        return false;
    }

    /**
     * Get period title for display
     *
     * @return string Period title
     */
    public function getPeriodTitle() {
        if ($this->period_type === 'week') {
            return "Week {$this->week_number}, {$this->year}";
        } elseif ($this->period_type === 'month') {
            $monthFormatter = new IntlDateFormatter(
                $this->locale,
                IntlDateFormatter::LONG,
                IntlDateFormatter::NONE,
                null,
                null,
                'MMMM yyyy'
            );

            $timestamp = mktime(0, 0, 0, $this->month, 1, $this->year);
            return ucfirst($monthFormatter->format($timestamp));
        }

        return '';
    }

    // ========== STYLING ==========

    /**
     * Get CSS class for a grid element from grid_attrs
     *
     * @param string $element Element name
     * @return string CSS class string
     */
    public function getElementClass($element) {
        return $this->grid_attrs[$element]['class'] ?? '';
    }

    /**
     * Get all attributes for a grid element as HTML string
     *
     * @param string $element Element name
     * @param string $defaultClass Default CSS classes to apply
     * @return string HTML attributes string
     */
    public function getElementAttrs($element, $defaultClass = '') {
        $attrs = [];

        // Get custom class from grid_attrs
        $customClass = $this->getElementClass($element);

        // Merge default and custom classes
        $classes = trim($defaultClass . ' ' . $customClass);
        if (!empty($classes)) {
            $attrs[] = 'class="' . htmlspecialchars($classes) . '"';
        }

        // Get other custom attributes (excluding class)
        if (isset($this->grid_attrs[$element])) {
            foreach ($this->grid_attrs[$element] as $key => $value) {
                if ($key !== 'class') {
                    $attrs[] = htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                }
            }
        }

        return !empty($attrs) ? ' ' . implode(' ', $attrs) : '';
    }
}
