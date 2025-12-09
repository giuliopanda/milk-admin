<?php
use App\Get;

/**
 * Classe CalendarData - Gestisce la logica e i dati del calendario
 * Questa classe è responsabile di:
 * - Gestione appuntamenti
 * - Calcoli su date
 * - Configurazione del calendario
 * - Logica di validazione
 */
class CalendarData {
    // Date e locale
    private $month;
    private $year;
    private $locale = 'en_US';
    private $dateFormatter;
    
    // Appuntamenti
    private $appointments = [];
    
    // Configurazione base
    private $calendar_id = 'calendar';
    private $action_url = '';
    
    // Configurazione header
    private $header_title = 'Calendar';
    private $header_icon = '';
    private $header_color_class = 'bg-primary';
    
    // URL e comportamenti click
    private $on_appointment_click_url = '';
    private $on_empty_date_click_url = '';
    private $on_date_with_appointments_click_url = '';
    private $on_date_with_appointments_click_mode = 'fetch';
    
    // Range date consentite
    private $min_year = null;
    private $max_year = null;
    private $min_month = null;
    private $max_month = null;
    
    // Opzioni visualizzazione
    private $compact = false;
    private $highlight_days_with_appointments = false;
    private $show_year_month_select = true;
    private $show_prev_next_buttons = true;
    private $show_today_button = true;
    private $show_header = true;
    
    // Custom renderer e attributi
    private $custom_cell_renderer = null;
    private $calendar_attrs = [];

    private $week_number = null;
    private $view_type = 'monthly';
    private $filters = '';
    

    /**
     * Constructor
     *
     * @param int $month Month (1-12)
     * @param int $year Year
     * @param string $locale Locale string (e.g., 'en_US', 'it_IT', 'fr_FR')
     * @param string $calendar_id Unique calendar ID for AJAX updates
     */
    public function __construct($month = null, $year = null, $locale = 'en_US', $calendar_id = 'calendar') {
        $this->month = $month ?? date('n');
        $this->year = $year ?? date('Y');
        $this->locale = $locale;
        $this->calendar_id = $calendar_id;

        // Initialize IntlDateFormatter for month name
        $this->dateFormatter = new IntlDateFormatter(
            $this->locale,
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            null,
            null,
            'MMMM'
        );
    }

    // ========== GETTERS ==========
    
    public function getMonth() {
        return $this->month;
    }

    public function getYear() {
        return $this->year;
    }

    public function getLocale() {
        return $this->locale;
    }

    public function getDateFormatter() {
        return $this->dateFormatter;
    }

    public function getCalendarId() {
        return $this->calendar_id;
    }

    public function getActionUrl() {
        return $this->action_url;
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

    public function getOnAppointmentClickUrl() {
        return $this->on_appointment_click_url;
    }

    public function getOnEmptyDateClickUrl() {
        return $this->on_empty_date_click_url;
    }

    public function getOnDateWithAppointmentsClickUrl() {
        return $this->on_date_with_appointments_click_url;
    }

    public function getOnDateWithAppointmentsClickMode() {
        return $this->on_date_with_appointments_click_mode;
    }

    public function getMinYear() {
        return $this->min_year;
    }

    public function getMaxYear() {
        return $this->max_year;
    }

    public function getMinMonth() {
        return $this->min_month;
    }

    public function getMaxMonth() {
        return $this->max_month;
    }

    public function isCompact() {
        return $this->compact;
    }

    public function shouldHighlightDaysWithAppointments() {
        return $this->highlight_days_with_appointments;
    }

    public function shouldShowYearMonthSelect() {
        return $this->show_year_month_select;
    }

    public function shouldShowPrevNextButtons() {
        return $this->show_prev_next_buttons;
    }

    public function shouldShowTodayButton() {
        return $this->show_today_button;
    }

    public function shouldShowHeader() {
        return $this->show_header;
    }

    public function getCustomCellRenderer() {
        return $this->custom_cell_renderer;
    }

    public function getCalendarAttrs() {
        return $this->calendar_attrs;
    }

    // ========== SETTERS ==========

    public function setCalendarId(string $calendar_id) {
        $this->calendar_id = $calendar_id;
        return $this;
    }

    public function setActionUrl(string $url) {
        $this->action_url = $url;
        return $this;
    }

    public function setHeaderTitle(string $title) {
        $this->header_title = $title;
        return $this;
    }

    public function setHeaderIcon(string $icon) {
        $this->header_icon = $icon;
        return $this;
    }

    public function setHeaderColor(string $color) {
        if (in_array($color, ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'])) {
            $this->header_color_class = 'bg-' . $color;
        } else {
            $this->header_color_class = $color;
        }
        return $this;
    }

    public function setHeaderColorClass(string $class) {
        $this->header_color_class = $class;
        return $this;
    }

    public function setOnAppointmentClickUrl(string $url) {
        $this->on_appointment_click_url = $url;
        return $this;
    }

    public function setOnEmptyDateClickUrl(string $url) {
        $this->on_empty_date_click_url = $url;
        return $this;
    }

    public function setDateRange($minYear = null, $maxYear = null, $minMonth = null, $maxMonth = null) {
        $this->min_year = $minYear;
        $this->max_year = $maxYear;
        $this->min_month = $minMonth;
        $this->max_month = $maxMonth;
        return $this;
    }

    public function setCompact(bool $compact) {
        $this->compact = $compact;
        return $this;
    }

    public function setHighlightDaysWithAppointments(bool $highlight) {
        $this->highlight_days_with_appointments = $highlight;
        return $this;
    }

    public function setOnDateWithAppointmentsClickUrl(string $url) {
        $this->on_date_with_appointments_click_url = $url;
        return $this;
    }

    public function setOnDateWithAppointmentsClickMode(string $mode) {
        if (in_array($mode, ['fetch', 'link'])) {
            $this->on_date_with_appointments_click_mode = $mode;
        }
        return $this;
    }

    public function setShowYearMonthSelect(bool $show) {
        $this->show_year_month_select = $show;
        return $this;
    }

    public function setShowPrevNextButtons(bool $show) {
        $this->show_prev_next_buttons = $show;
        return $this;
    }

    public function setShowTodayButton(bool $show) {
        $this->show_today_button = $show;
        return $this;
    }

    public function setShowHeader(bool $show) {
        $this->show_header = $show;
        return $this;
    }

    public function setCustomCellRenderer(?callable $renderer) {
        $this->custom_cell_renderer = $renderer;
        return $this;
    }

    public function setCalendarAttrs(array $attrs) {
        $this->calendar_attrs = $attrs;
        return $this;
    }

    // ========== APPOINTMENT MANAGEMENT ==========

    /**
     * Add an appointment to the calendar
     * 
     * @param int|string $id Unique appointment ID
     * @param DateTime $startDate Start date and time
     * @param DateTime $endDate End date and time
     * @param string $title Appointment title
     * @param string $class CSS class for styling
     */
    public function addAppointment($id, DateTime $startDate, DateTime $endDate, $title, $class = '') {
        $this->appointments[] = [
            'id' => $id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'title' => $title,
            'class' => $class
        ];
    }

    /**
     * Get appointments for a specific date
     * Includes multi-day appointments that fall on this date
     * 
     * @param string $date Date in Y-m-d format
     * @return array Array of appointments sorted by time
     */
    public function getAppointmentsForDate($date) {
        $currentDate = new DateTime($date);
        $currentDate->setTime(0, 0, 0);

        $dayAppointments = [];

        foreach ($this->appointments as $apt) {
            $startDate = clone $apt['start_date'];
            $startDate->setTime(0, 0, 0);

            $endDate = clone $apt['end_date'];
            $endDate->setTime(23, 59, 59);

            // Check if current date falls within appointment range
            if ($currentDate >= $startDate && $currentDate <= $endDate) {
                // Determine start and end time for this specific day
                $displayStartTime = ($currentDate->format('Ymd') == $startDate->format('Ymd'))
                    ? $apt['start_date']->format('H:i')
                    : '00:00';

                // Clone BEFORE setTime to avoid modifying the original object
                $endDateMidnight = clone $apt['end_date'];
                $endDateMidnight->setTime(0, 0, 0);

                $displayEndTime = ($currentDate->format('Ymd') == $endDateMidnight->format('Ymd'))
                    ? $apt['end_date']->format('H:i')
                    : '23:59';

                $dayAppointments[] = [
                    'id' => $apt['id'],
                    'title' => $apt['title'],
                    'class' => $apt['class'],
                    'start_time' => $displayStartTime,
                    'end_time' => $displayEndTime,
                    'start_date' => $apt['start_date'],
                    'end_date' => $apt['end_date'],
                    'full_start_date' => Get::formatDate($apt['start_date'], 'date'),
                    'full_end_date' => Get::formatDate($apt['end_date'], 'date'),
                    'is_multiday' => $apt['start_date']->format('Y-m-d') !== $apt['end_date']->format('Y-m-d'),
                    'is_first_day' => ($currentDate->format('Ymd') == $startDate->format('Ymd')),
                    'is_last_day' => ($currentDate->format('Ymd') == $endDateMidnight->format('Ymd'))
                ];
            }
        }
        
        // Sort by start time
        usort($dayAppointments, function($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });
        
        return $dayAppointments;
    }

    // ========== CALENDAR LOGIC METHODS ==========

    /**
     * Get CSS class for a calendar element from calendar_attrs
     *
     * @param string $element Element name (container, grid, weekdays, etc.)
     * @return string CSS class string
     */
    public function getElementClass($element) {
        return $this->calendar_attrs[$element]['class'] ?? '';
    }

    /**
     * Get all attributes for a calendar element as HTML string
     *
     * @param string $element Element name
     * @param string $defaultClass Default CSS classes to apply
     * @return string HTML attributes string
     */
    public function getElementAttrs($element, $defaultClass = '') {
        $attrs = [];

        // Get custom class from calendar_attrs
        $customClass = $this->getElementClass($element);

        // Merge default and custom classes
        $classes = trim($defaultClass . ' ' . $customClass);
        if (!empty($classes)) {
            $attrs[] = 'class="' . htmlspecialchars($classes) . '"';
        }

        // Get other custom attributes (excluding class)
        if (isset($this->calendar_attrs[$element])) {
            foreach ($this->calendar_attrs[$element] as $key => $value) {
                if ($key !== 'class') {
                    $attrs[] = htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                }
            }
        }

        return !empty($attrs) ? ' ' . implode(' ', $attrs) : '';
    }

    /**
     * Calculate previous month/year
     *
     * @return array ['month' => int, 'year' => int]
     */
    public function getPreviousMonthYear() {
        $prevMonth = $this->month - 1;
        $prevYear = $this->year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }
        return ['month' => $prevMonth, 'year' => $prevYear];
    }

    /**
     * Calculate next month/year
     *
     * @return array ['month' => int, 'year' => int]
     */
    public function getNextMonthYear() {
        $nextMonth = $this->month + 1;
        $nextYear = $this->year;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }
        return ['month' => $nextMonth, 'year' => $nextYear];
    }

    /**
     * Check if previous month navigation is disabled
     *
     * @return bool
     */
    public function isPreviousMonthDisabled() {
        $prev = $this->getPreviousMonthYear();
        $prevMonth = $prev['month'];
        $prevYear = $prev['year'];

        if ($this->min_year !== null) {
            if ($prevYear < $this->min_year) {
                return true;
            } elseif ($prevYear == $this->min_year && $this->min_month !== null && $prevMonth < $this->min_month) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if next month navigation is disabled
     *
     * @return bool
     */
    public function isNextMonthDisabled() {
        $next = $this->getNextMonthYear();
        $nextMonth = $next['month'];
        $nextYear = $next['year'];

        if ($this->max_year !== null) {
            if ($nextYear > $this->max_year) {
                return true;
            } elseif ($nextYear == $this->max_year && $this->max_month !== null && $nextMonth > $this->max_month) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get localized month name
     *
     * @return string
     */
    public function getMonthName() {
        $monthTimestamp = mktime(0, 0, 0, $this->month, 1, $this->year);
        $monthName = $this->dateFormatter->format($monthTimestamp);
        return ucfirst($monthName);
    }

    /**
     * Get all localized month names
     *
     * @return array Array with month numbers as keys and names as values
     */
    public function getAllMonthNames() {
        $monthNameFormatter = new IntlDateFormatter(
            $this->locale,
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            null,
            null,
            'MMMM'
        );

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $timestamp = mktime(0, 0, 0, $m, 1, 2024);
            $months[$m] = ucfirst($monthNameFormatter->format($timestamp));
        }
        return $months;
    }

    /**
     * Check if a month should be disabled in the month selector
     *
     * @param int $monthNum Month number (1-12)
     * @return bool
     */
    public function isMonthDisabled($monthNum) {
        if ($this->min_month !== null && $this->min_year !== null) {
            if ($this->year == $this->min_year && $monthNum < $this->min_month) {
                return true;
            }
        }
        if ($this->max_month !== null && $this->max_year !== null) {
            if ($this->year == $this->max_year && $monthNum > $this->max_month) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get year range for selector
     *
     * @return array ['start' => int, 'end' => int]
     */
    public function getYearRange() {
        $startYear = $this->min_year ?? (date('Y') - 2);
        $endYear = $this->max_year ?? (date('Y') + 5);
        return ['start' => $startYear, 'end' => $endYear];
    }

    public function setWeekNumber($week) {
        $this->week_number = $week;
        return $this;
    }

    public function getWeekNumber() {
        return $this->week_number;
    }

    public function setViewType(string $type) {
        $this->view_type = $type;
        return $this;
    }

    public function getViewType() {
        return $this->view_type;
    }

    public function setFilters(string $filters) {
        $this->filters = $filters;
        return $this;
    }

    public function getFilters() {
        return $this->filters;
    }
}