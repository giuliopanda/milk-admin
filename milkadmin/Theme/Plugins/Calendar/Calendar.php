<?php

/**
 * Classe Calendar - Facade per la gestione del calendario
 * 
 * Questa classe funge da interfaccia principale per l'uso del calendario.
 * Coordina tra CalendarData (logica) e CalendarRenderer (visualizzazione).
 * 
 * I metodi pubblici mantengono la stessa firma dell'implementazione originale
 * per garantire la retrocompatibilità.
 */
class Calendar {
    private $data;
    private $renderer;

    /**
     * Constructor
     *
     * @param int $month Month (1-12)
     * @param int $year Year
     * @param string $locale Locale string (e.g., 'en_US', 'it_IT', 'fr_FR')
     * @param string $calendar_id Unique calendar ID for AJAX updates
     * @param string $view_type View type: 'monthly', 'weekly', 'daily' (default: 'monthly')
     */
    public function __construct($month = null, $year = null, $locale = 'en_US', $calendar_id = 'calendar', $view_type = 'monthly') {
        // Initialize data
        $this->data = new CalendarData($month, $year, $locale, $calendar_id);
        
        // Initialize renderer based on view type
        $this->setViewType($view_type);
    }

    /**
     * Set the view type and initialize appropriate renderer
     *
     * @param string $view_type View type: 'monthly', 'weekly', 'daily'
     * @return static For method chaining
     */
    public function setViewType($view_type) {
        // Store view type in data
        $this->data->setViewType($view_type);

        switch ($view_type) {
            case 'monthly':
                $this->renderer = new MonthlyCalendarRenderer($this->data);
                break;
            case 'weekly':
                $this->renderer = new WeeklyCalendarRenderer($this->data);
                break;
            case 'daily':
                // To be implemented
                throw new Exception("Daily view not yet implemented");
                break;
            default:
                throw new Exception("Invalid view type: {$view_type}");
        }
        return $this;
    }

    /**
     * Configure weekly view settings (only applies to WeeklyCalendarRenderer)
     *
     * @param int $hourStart Start hour (0-23)
     * @param int $hourEnd End hour (1-24)
     * @param int $hourHeight Height in pixels for each hour
     * @return static For method chaining
     */
    public function setWeeklyViewSettings($hourStart = 0, $hourEnd = 24, $hourHeight = 60) {
        if ($this->renderer instanceof WeeklyCalendarRenderer) {
            $this->renderer->setHourRange($hourStart, $hourEnd);
            $this->renderer->setHourHeight($hourHeight);
        }
        return $this;
    }

    // ========== PUBLIC API - Configuration Methods ==========
    // These methods maintain the same signature as the original implementation

    /**
     * Set calendar ID
     *
     * @param string $calendar_id Calendar ID
     * @return static For method chaining
     */
    public function setCalendarId(string $calendar_id) {
        $this->data->setCalendarId($calendar_id);
        return $this;
    }

    /**
     * Set action URL for form
     *
     * @param string $url Action URL
     * @return static For method chaining
     */
    public function setActionUrl(string $url) {
        $this->data->setActionUrl($url);
        return $this;
    }

    /**
     * Set header title
     *
     * @param string $title Header title
     * @return static For method chaining
     */
    public function setHeaderTitle(string $title) {
        $this->data->setHeaderTitle($title);
        return $this;
    }

    /**
     * Set header icon (Bootstrap Icons class)
     *
     * @param string $icon Icon class (e.g., 'bi-calendar-event')
     * @return static For method chaining
     */
    public function setHeaderIcon(string $icon) {
        $this->data->setHeaderIcon($icon);
        return $this;
    }

    /**
     * Set header color using predefined Bootstrap classes
     *
     * Predefined options: 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'
     *
     * @param string $color Color name (e.g., 'primary', 'success', 'danger')
     * @return static For method chaining
     */
    public function setHeaderColor(string $color) {
        $this->data->setHeaderColor($color);
        return $this;
    }

    /**
     * Set custom header color class (for advanced customization)
     *
     * @param string $class Custom CSS class
     * @return static For method chaining
     */
    public function setHeaderColorClass(string $class) {
        $this->data->setHeaderColorClass($class);
        return $this;
    }

    /**
     * Set URL template for appointment click
     *
     * @param string $url URL template with %id% placeholder
     * @return static For method chaining
     */
    public function setOnAppointmentClickUrl(string $url) {
        $this->data->setOnAppointmentClickUrl($url);
        return $this;
    }

    /**
     * Set URL template for empty date click
     *
     * @param string $url URL template with %date% placeholder
     * @return static For method chaining
     */
    public function setOnEmptyDateClickUrl(string $url) {
        $this->data->setOnEmptyDateClickUrl($url);
        return $this;
    }

    /**
     * Set date range for navigation
     *
     * @param int|null $minYear Minimum year
     * @param int|null $maxYear Maximum year
     * @param int|null $minMonth Minimum month (1-12)
     * @param int|null $maxMonth Maximum month (1-12)
     * @return static For method chaining
     */
    public function setDateRange($minYear = null, $maxYear = null, $minMonth = null, $maxMonth = null) {
        $this->data->setDateRange($minYear, $maxYear, $minMonth, $maxMonth);
        return $this;
    }

    /**
     * Set compact mode for smaller calendar
     *
     * @param bool $compact Enable compact mode (default: false)
     * @return static For method chaining
     */
    public function setCompact(bool $compact) {
        $this->data->setCompact($compact);
        return $this;
    }

    /**
     * Set whether to highlight days with appointments
     *
     * @param bool $highlight Highlight days (default: false)
     * @return static For method chaining
     */
    public function setHighlightDaysWithAppointments(bool $highlight) {
        $this->data->setHighlightDaysWithAppointments($highlight);
        return $this;
    }

    /**
     * Set URL template for date with appointments click
     *
     * @param string $url URL template with %date% placeholder
     * @return static For method chaining
     */
    public function setOnDateWithAppointmentsClickUrl(string $url) {
        $this->data->setOnDateWithAppointmentsClickUrl($url);
        return $this;
    }

    /**
     * Set click mode for dates with appointments
     *
     * @param string $mode Click mode: 'fetch' or 'link' (default: 'fetch')
     * @return static For method chaining
     */
    public function setOnDateWithAppointmentsClickMode(string $mode) {
        $this->data->setOnDateWithAppointmentsClickMode($mode);
        return $this;
    }

    /**
     * Set visibility of year/month select dropdown
     *
     * @param bool $show Show year/month select (default: true)
     * @return static For method chaining
     */
    public function setShowYearMonthSelect(bool $show) {
        $this->data->setShowYearMonthSelect($show);
        return $this;
    }

    /**
     * Set visibility of prev/next navigation buttons
     *
     * @param bool $show Show prev/next buttons (default: true)
     * @return static For method chaining
     */
    public function setShowPrevNextButtons(bool $show) {
        $this->data->setShowPrevNextButtons($show);
        return $this;
    }

    /**
     * Set visibility of today button
     *
     * @param bool $show Show today button (default: true)
     * @return static For method chaining
     */
    public function setShowTodayButton(bool $show) {
        $this->data->setShowTodayButton($show);
        return $this;
    }

    /**
     * Set visibility of calendar header
     *
     * @param bool $show Show header (default: true)
     * @return static For method chaining
     */
    public function setShowHeader(bool $show) {
        $this->data->setShowHeader($show);
        return $this;
    }

    /**
     * Set custom cell renderer callback for full cell override
     *
     * The callback receives:
     * - int $day: Day number
     * - int $month: Month number
     * - int $year: Year number
     * - bool $otherMonth: Whether it belongs to another month
     * - string $date: Date in Y-m-d format
     * - bool $isToday: Whether it's today
     * - array $appointments: Array of appointments for this day
     * - CalendarData $data: Calendar data instance (for accessing locale, etc.)
     *
     * The callback should return HTML string for the cell
     *
     * @param callable|null $renderer Custom renderer callback
     * @return static For method chaining
     */
    public function setCustomCellRenderer(?callable $renderer) {
        $this->data->setCustomCellRenderer($renderer);
        return $this;
    }

    /**
     * Set calendar styling attributes
     *
     * @param array $attrs Calendar attributes array
     * @return static For method chaining
     */
    public function setCalendarAttrs(array $attrs) {
        $this->data->setCalendarAttrs($attrs);
        return $this;
    }

    /**
     * Get current locale
     * 
     * @return string Current locale
     */
    public function getLocale() {
        return $this->data->getLocale();
    }

    // ========== PUBLIC API - Appointment Methods ==========

    /**
     * Add an appointment to the calendar
     * 
     * @param int|string $id Unique appointment ID
     * @param DateTime $startDate Start date and time
     * @param DateTime $endDate End date and time
     * @param string $title Appointment title
     * @param string $class CSS class for styling
     * @return static For method chaining
     */
    public function addAppointment($id, DateTime $startDate, DateTime $endDate, $title, $class = '') {
        $this->data->addAppointment($id, $startDate, $endDate, $title, $class);
        return $this;
    }

    // ========== PUBLIC API - Rendering ==========

    /**
     * Render the complete calendar
     *
     * @return string Calendar HTML
     */
    public function render() {
        return $this->renderer->render();
    }

    // ========== PUBLIC API - Data Access (for advanced usage) ==========

    /**
     * Get the calendar data object
     * For advanced usage or custom renderers
     *
     * @return CalendarData
     */
    public function getData() {
        return $this->data;
    }

    /**
     * Get the calendar renderer object
     * For advanced usage or customization
     *
     * @return CalendarRenderer
     */
    public function getRenderer() {
        return $this->renderer;
    }

    /**
     * Set a custom renderer instance
     * For advanced usage when you want to use a completely custom renderer
     *
     * @param CalendarRenderer $renderer Custom renderer instance
     * @return static For method chaining
     */
    public function setRenderer(CalendarRenderer $renderer) {
        $this->renderer = $renderer;
        return $this;
    }

    /**
     * Set filters for the calendar
     *
     * @param string $filters Filters string
     * @return static For method chaining
     */
    public function setFilters(string $filters) {
        $this->data->setFilters($filters);
        return $this;
    }

    /**
     * Get current view type
     *
     * @return string View type
     */
    public function getViewType() {
        return $this->data->getViewType();
    }
}