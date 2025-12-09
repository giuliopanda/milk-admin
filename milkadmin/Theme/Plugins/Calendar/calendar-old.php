<?php
use App\Get;
/**
 * Classe Calendar per generare calendari mensili in stile Outlook
 * con supporto per appuntamenti multipli nello stesso orario e multi-giorno
 */
class Calendar {
    private $month;
    private $year;
    private $appointments = [];
    private $locale = 'en_US';
    private $dateFormatter;
    private $calendar_id = 'calendar';
    private $action_url = '';
    private $header_title = 'Calendar';
    private $header_icon = '';
    private $header_color_class = 'bg-primary';
    private $on_appointment_click_url = '';
    private $on_empty_date_click_url = '';
    private $min_year = null;
    private $max_year = null;
    private $min_month = null;
    private $max_month = null;
    private $compact = false;
    private $highlight_days_with_appointments = false;
    private $on_date_with_appointments_click_url = '';
    private $on_date_with_appointments_click_mode = 'fetch';
    private $show_year_month_select = true;
    private $show_prev_next_buttons = true;
    private $show_today_button = true;
    private $show_header = true;
    private $custom_cell_renderer = null;
    private $calendar_attrs = [];

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

    /**
     * Set calendar ID
     *
     * @param string $calendar_id Calendar ID
     * @return static For method chaining
     */
    public function setCalendarId(string $calendar_id) {
        $this->calendar_id = $calendar_id;
        return $this;
    }

    /**
     * Set action URL for form
     *
     * @param string $url Action URL
     * @return static For method chaining
     */
    public function setActionUrl(string $url) {
        $this->action_url = $url;
        return $this;
    }

    /**
     * Set header title
     *
     * @param string $title Header title
     * @return static For method chaining
     */
    public function setHeaderTitle(string $title) {
        $this->header_title = $title;
        return $this;
    }

    /**
     * Set header icon (Bootstrap Icons class)
     *
     * @param string $icon Icon class (e.g., 'bi-calendar-event')
     * @return static For method chaining
     */
    public function setHeaderIcon(string $icon) {
        $this->header_icon = $icon;
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
        // If it's a predefined color name, add 'bg-' prefix
        if (in_array($color, ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'])) {
            $this->header_color_class = 'bg-' . $color;
        } else {
            // Otherwise use it as custom class
            $this->header_color_class = $color;
        }
        return $this;
    }

    /**
     * Set custom header color class (for advanced customization)
     *
     * @param string $class Custom CSS class
     * @return static For method chaining
     */
    public function setHeaderColorClass(string $class) {
        $this->header_color_class = $class;
        return $this;
    }

    /**
     * Set URL template for appointment click
     *
     * @param string $url URL template with %id% placeholder
     * @return static For method chaining
     */
    public function setOnAppointmentClickUrl(string $url) {
        $this->on_appointment_click_url = $url;
        return $this;
    }

    /**
     * Set URL template for empty date click
     *
     * @param string $url URL template with %date% placeholder
     * @return static For method chaining
     */
    public function setOnEmptyDateClickUrl(string $url) {
        $this->on_empty_date_click_url = $url;
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
        $this->min_year = $minYear;
        $this->max_year = $maxYear;
        $this->min_month = $minMonth;
        $this->max_month = $maxMonth;
        return $this;
    }

    /**
     * Set compact mode for smaller calendar
     *
     * @param bool $compact Enable compact mode (default: false)
     * @return static For method chaining
     */
    public function setCompact(bool $compact) {
        $this->compact = $compact;
        return $this;
    }

    /**
     * Set whether to highlight days with appointments
     *
     * @param bool $highlight Highlight days (default: false)
     * @return static For method chaining
     */
    public function setHighlightDaysWithAppointments(bool $highlight) {
        $this->highlight_days_with_appointments = $highlight;
        return $this;
    }

    /**
     * Set URL template for date with appointments click
     *
     * @param string $url URL template with %date% placeholder
     * @return static For method chaining
     */
    public function setOnDateWithAppointmentsClickUrl(string $url) {
        $this->on_date_with_appointments_click_url = $url;
        return $this;
    }

    /**
     * Set click mode for dates with appointments
     *
     * @param string $mode Click mode: 'fetch' or 'link' (default: 'fetch')
     * @return static For method chaining
     */
    public function setOnDateWithAppointmentsClickMode(string $mode) {
        if (in_array($mode, ['fetch', 'link'])) {
            $this->on_date_with_appointments_click_mode = $mode;
        }
        return $this;
    }

    /**
     * Set visibility of year select dropdown
     *
     * @param bool $show Show year select (default: true)
     * @return static For method chaining
     */
    public function setShowYearMonthSelect(bool $show) {
        $this->show_year_month_select = $show;
        return $this;
    }

    /**
     * Set visibility of prev/next navigation buttons
     *
     * @param bool $show Show prev/next buttons (default: true)
     * @return static For method chaining
     */
    public function setShowPrevNextButtons(bool $show) {
        $this->show_prev_next_buttons = $show;
        return $this;
    }

    /**
     * Set visibility of today button
     *
     * @param bool $show Show today button (default: true)
     * @return static For method chaining
     */
    public function setShowTodayButton(bool $show) {
        $this->show_today_button = $show;
        return $this;
    }

    /**
     * Set visibility of calendar header
     *
     * @param bool $show Show header (default: true)
     * @return static For method chaining
     */
    public function setShowHeader(bool $show) {
        $this->show_header = $show;
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
     * - Calendar $calendar: Calendar instance (for accessing locale, etc.)
     *
     * The callback should return HTML string for the cell
     *
     * @param callable|null $renderer Custom renderer callback
     * @return static For method chaining
     */
    public function setCustomCellRenderer(?callable $renderer) {
        $this->custom_cell_renderer = $renderer;
        return $this;
    }

    /**
     * Set calendar styling attributes
     *
     * @param array $attrs Calendar attributes array
     * @return static For method chaining
     */
    public function setCalendarAttrs(array $attrs) {
        $this->calendar_attrs = $attrs;
        return $this;
    }

    /**
     * Get CSS class for a calendar element from calendar_attrs
     *
     * @param string $element Element name (container, grid, weekdays, etc.)
     * @return string CSS class string
     */
    private function getElementClass($element) {
        return $this->calendar_attrs[$element]['class'] ?? '';
    }

    /**
     * Get all attributes for a calendar element as HTML string
     *
     * @param string $element Element name
     * @param string $defaultClass Default CSS classes to apply
     * @return string HTML attributes string
     */
    private function getElementAttrs($element, $defaultClass = '') {
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
     * Get current locale
     * 
     * @return string Current locale
     */
    public function getLocale() {
        return $this->locale;
    }
    
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
    private function getAppointmentsForDate($date) {
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

                // FIX: Clone BEFORE setTime to avoid modifying the original object
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
                    'is_first_day' =>($currentDate->format('Ymd') == $startDate->format('Ymd')),
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
    
    /**
     * Render the complete calendar
     *
     * @return string Calendar HTML
     */
    public function render() {
        $html = '';

        if ($this->show_header) {
            $html .= $this->renderHeader();
            $html .= $this->renderCalendarGrid();
        } else {
            // When header is hidden, wrap calendar with minimal container
            $compactClass = $this->compact ? ' calendar-compact' : '';
            $formHtml = $this->renderForm();

            // Get custom attributes for container and grid
            $containerAttrs = $this->getElementAttrs('container', 'calendar-container js-calendar-container' . $compactClass);
            $gridAttrs = $this->getElementAttrs('grid', 'calendar-grid');

            $html .= <<<HTML
<div{$containerAttrs} id="{$this->calendar_id}">
{$formHtml}
    <div{$gridAttrs}>

HTML;
            $html .= $this->renderCalendarGrid();
            // renderCalendarGrid() already closes calendar-grid and calendar-container
        }

        return $html;
    }

    /**
     * Render hidden form for AJAX updates
     *
     * @return string Form HTML
     */
    private function renderForm() {
        $action_url = $this->action_url ?: $_SERVER['REQUEST_URI'];

        return <<<HTML
    <form class="js-calendar-form" action="{$action_url}" method="POST" style="display: none;">
        <input type="hidden" class="js-field-calendar-month" name="{$this->calendar_id}[month]" value="{$this->month}">
        <input type="hidden" class="js-field-calendar-year" name="{$this->calendar_id}[year]" value="{$this->year}">
    </form>
HTML;
    }
    
    /**
     * Render calendar header with inline controls
     *
     * @return string HTML
     */
    private function renderHeader() {
        $monthTimestamp = mktime(0, 0, 0, $this->month, 1, $this->year);
        $monthName = $this->dateFormatter->format($monthTimestamp);
        $monthName = ucfirst($monthName);

        // Calculate previous month
        $prevMonth = $this->month - 1;
        $prevYear = $this->year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }

        // Calculate next month
        $nextMonth = $this->month + 1;
        $nextYear = $this->year;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }

        // Check if prev/next buttons should be disabled
        $prevDisabled = false;
        $nextDisabled = false;

        // Check minimum date constraint
        if ($this->min_year !== null) {
            if ($prevYear < $this->min_year) {
                $prevDisabled = true;
            } elseif ($prevYear == $this->min_year && $this->min_month !== null && $prevMonth < $this->min_month) {
                $prevDisabled = true;
            }
        }

        // Check maximum date constraint
        if ($this->max_year !== null) {
            if ($nextYear > $this->max_year) {
                $nextDisabled = true;
            } elseif ($nextYear == $this->max_year && $this->max_month !== null && $nextMonth > $this->max_month) {
                $nextDisabled = true;
            }
        }

        // Generate month options using locale
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

        $monthOptions = '';
        foreach ($months as $num => $name) {
            $selected = ($num == $this->month) ? 'selected' : '';

            // Check if month should be disabled based on min/max constraints
            $disabled = '';
            if ($this->min_month !== null && $this->min_year !== null) {
                if ($this->year == $this->min_year && $num < $this->min_month) {
                    $disabled = 'disabled';
                }
            }
            if ($this->max_month !== null && $this->max_year !== null) {
                if ($this->year == $this->max_year && $num > $this->max_month) {
                    $disabled = 'disabled';
                }
            }

            $monthOptions .= "<option value=\"{$num}\" {$selected} {$disabled}>{$name}</option>";
        }

        // Generate year options (use configured min/max or defaults)
        $startYear = $this->min_year ?? (date('Y') - 2);
        $endYear = $this->max_year ?? (date('Y') + 5);
        $yearOptions = '';
        for ($y = $startYear; $y <= $endYear; $y++) {
            $selected = ($y == $this->year) ? 'selected' : '';
            $yearOptions .= "<option value=\"{$y}\" {$selected}>{$y}</option>";
        }

        $formHtml = $this->renderForm();

        // Build icon HTML if set
        $iconHtml = '';
        if (!empty($this->header_icon)) {
            $iconHtml = '<i class="' . htmlspecialchars($this->header_icon) . ' me-2"></i>';
        }

        // Determine text color based on background (light backgrounds get dark text)
        $textColorClass = in_array($this->header_color_class, ['bg-light', 'bg-warning']) ? 'text-dark' : 'text-white';

        // Build disabled attributes for navigation buttons
        $prevDisabledAttr = $prevDisabled ? 'disabled' : '';
        $nextDisabledAttr = $nextDisabled ? 'disabled' : '';

        // Current month/year for Today button
        $currentMonth = date('n');
        $currentYear = date('Y');

        // Build compact class if enabled
        $compactClass = $this->compact ? ' calendar-compact' : '';

        // Build navigation controls HTML
        $navControlsHtml = '';

        // Previous arrow
        if ($this->show_prev_next_buttons) {
            $navControlsHtml .= <<<HTML
                <!-- Previous arrow -->
                <button type="button"
                        class="btn btn-sm btn-light js-calendar-prev"
                        data-month="{$prevMonth}"
                        data-year="{$prevYear}"
                        title="Previous month"
                        {$prevDisabledAttr}>
                    <i class="bi bi-chevron-left"></i>
                </button>

HTML;
        }

        // Month select
        $display = ($this->show_year_month_select) ? '' : 'style="display: none"';
        $navControlsHtml .= <<<HTML
            <!-- Month select -->
            <select class="form-select form-select-sm js-calendar-month-select"
                    {$display}
                    style="width: auto; min-width: 120px;">
                {$monthOptions}
            </select>
            <!-- Year select -->
            <select class="form-select form-select-sm js-calendar-year-select"
                    {$display}
                    style="width: auto; min-width: 90px;">
                {$yearOptions}
            </select>
HTML;
        

        // Today button
        if ($this->show_today_button) {
            $navControlsHtml .= <<<HTML
                <!-- Today button -->
                <button type="button"
                        class="btn btn-sm btn-light js-calendar-today"
                        data-month="{$currentMonth}"
                        data-year="{$currentYear}"
                        title="Today">
                    <i class="bi bi-calendar-check"></i>
                </button>
HTML;
        }

        // Next arrow
        if ($this->show_prev_next_buttons) {
            $navControlsHtml .= <<<HTML
                <!-- Next arrow -->
                <button type="button"
                        class="btn btn-sm btn-light js-calendar-next"
                        data-month="{$nextMonth}"
                        data-year="{$nextYear}"
                        title="Next month"
                        {$nextDisabledAttr}>
                    <i class="bi bi-chevron-right"></i>
                </button>
HTML;
        }

        // Get custom attributes for container and grid
        $containerAttrs = $this->getElementAttrs('container', 'calendar-container js-calendar-container' . $compactClass);
        $gridAttrs = $this->getElementAttrs('grid', 'calendar-grid');

        return <<<HTML
<div{$containerAttrs} id="{$this->calendar_id}">
{$formHtml}
    <div class="calendar-header-compact {$this->header_color_class} {$textColorClass}">
        <div class="d-flex justify-content-between align-items-center">
            <!-- Title on left -->
            <div>
                <h5 class="mb-0">{$iconHtml}{$this->header_title}</h5>
            </div>

            <!-- Navigation controls on right -->
            <div class="d-flex align-items-center gap-2">
{$navControlsHtml}
            </div>
        </div>
    </div>

    <div{$gridAttrs}>

HTML;
    }
    
    /**
     * Render calendar grid
     *
     * @return string HTML
     */
    private function renderCalendarGrid() {
        // Get custom attributes for weekdays and days
        $weekdaysAttrs = $this->getElementAttrs('weekdays', 'calendar-weekdays');
        $daysAttrs = $this->getElementAttrs('days', 'calendar-days');

        $html = '<div' . $weekdaysAttrs . '>';

        // Weekdays using IntlDateFormatter
        $weekdayFormatter = new IntlDateFormatter(
            $this->locale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            null,
            null,
            'EEE'
        );

        // Get first day of week for locale (0 = Sunday, 1 = Monday)
        $calendar = IntlCalendar::createInstance(null, $this->locale);
        $firstDayOfWeek = $calendar->getFirstDayOfWeek();

        // Generate weekday headers
        for ($i = 0; $i < 7; $i++) {
            $dayNumber = ($firstDayOfWeek + $i - 1) % 7;
            if ($dayNumber == 0) $dayNumber = 7; // Convert Sunday from 0 to 7

            // Create a date for this weekday (using a known Monday as reference)
            $timestamp = strtotime("2023-01-" . (1 + $dayNumber)); // 2023-01-02 is Monday
            $dayName = $weekdayFormatter->format($timestamp);
            $html .= "<div class='calendar-weekday'>{$dayName}</div>";
        }

        $html .= '</div><div' . $daysAttrs . '>';
        
        // First day of month
        $firstDay = mktime(0, 0, 0, $this->month, 1, $this->year);
        $daysInMonth = date('t', $firstDay);
        
        // Day of week of first day (1 = Monday, 7 = Sunday)
        $dayOfWeek = date('N', $firstDay);
        
        // Adjust based on locale's first day of week
        if ($firstDayOfWeek == 1) { // Monday first (ISO 8601)
            $offset = $dayOfWeek - 1;
        } else { // Sunday first
            $offset = ($dayOfWeek == 7) ? 0 : $dayOfWeek;
        }
        
        // Days from previous month
        $prevMonth = $this->month - 1;
        $prevYear = $this->year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }
        $daysInPrevMonth = date('t', mktime(0, 0, 0, $prevMonth, 1, $prevYear));
        
        // Fill previous month days
        for ($i = $offset; $i > 0; $i--) {
            $day = $daysInPrevMonth - $i + 1;
            $html .= $this->renderDay($day, $prevMonth, $prevYear, true);
        }
        
        // Current month days
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $html .= $this->renderDay($day, $this->month, $this->year, false);
        }
        
        // Next month days to complete grid
        $nextMonth = $this->month + 1;
        $nextYear = $this->year;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }
        
        $totalCells = $offset + $daysInMonth;
        $remainingCells = (7 - ($totalCells % 7)) % 7;
        
        for ($day = 1; $day <= $remainingCells; $day++) {
            $html .= $this->renderDay($day, $nextMonth, $nextYear, true);
        }
        
        $html .= '</div></div></div>';
        
        return $html;
    }
    
    /**
     * Render a single day cell
     *
     * @param int $day Day number
     * @param int $month Month
     * @param int $year Year
     * @param bool $otherMonth Whether it belongs to another month
     * @return string HTML
     */
    private function renderDay($day, $month, $year, $otherMonth = false) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $today = date('Y-m-d');
        $isToday = ($date === $today);

        $appointments = $this->getAppointmentsForDate($date);
        $hasAppointments = !empty($appointments);

        // If custom cell renderer is set, use it
        if ($this->custom_cell_renderer !== null) {
            return call_user_func(
                $this->custom_cell_renderer,
                $day,
                $month,
                $year,
                $otherMonth,
                $date,
                $isToday,
                $appointments,
                $this
            );
        }

        // Default rendering
        $classes = ['calendar-day'];
        if ($otherMonth) {
            $classes[] = 'other-month';
        }
        if ($isToday) {
            $classes[] = 'today';
        }

        // Add class if day has appointments and highlighting is enabled
        if ($hasAppointments && $this->highlight_days_with_appointments) {
            $classes[] = 'has-appointments';
        }

        // Apply custom day classes from calendar_attrs
        $dayAttrs = $this->getElementAttrs('day', implode(' ', $classes));
        $html = '<div' . $dayAttrs . '>';

        // Build day number HTML with appropriate click behavior
        $dayNumberHtml = '';

        // Get custom day-number class
        $customDayNumberClass = $this->getElementClass('day-number');

        // Priority 1: If day has appointments and specific URL is configured
        if ($hasAppointments && !empty($this->on_date_with_appointments_click_url)) {
            $clickUrl = str_replace('%date%', $date, $this->on_date_with_appointments_click_url);
            $dayNumberClasses = trim('day-number day-number-clickable day-number-with-appointments ' . $customDayNumberClass);

            if ($this->on_date_with_appointments_click_mode === 'fetch') {
                $dayNumberHtml = '<div class="' . $dayNumberClasses . '" data-fetch="post" data-url="' . _r($clickUrl) . '">' . _r($day) . '</div>';
            } else {
                // Link mode
                $dayNumberHtml = '<a href="' . _r($clickUrl) . '" class="' . $dayNumberClasses . '">' . _r($day) . '</a>';
            }
        }
        // Priority 2: If empty date click URL is configured (fallback for days without appointments)
        elseif (!empty($this->on_empty_date_click_url)) {
            $clickUrl = str_replace('%date%', $date, $this->on_empty_date_click_url);
            $dayNumberClasses = trim('day-number day-number-clickable ' . $customDayNumberClass);
            if (!$hasAppointments) {
                $dayNumberClasses .= ' day-number-empty';
            }
            $dayNumberHtml = '<div class="' . $dayNumberClasses . '" data-fetch="post" data-url="' . _r($clickUrl) . '">' . _r($day) . '</div>';
        }
        // Priority 3: Default non-clickable day number
        else {
            $dayNumberClasses = trim('day-number ' . $customDayNumberClass);
            $dayNumberHtml = '<div class="' . $dayNumberClasses . '">' . _r($day) . '</div>';
        }

        $html .= $dayNumberHtml;

        // Show appointments only if enabled, appointments exist, and not in compact mode
        if ($hasAppointments && !$this->compact) {
            // Apply custom appointments-container classes
            $appointmentsContainerAttrs = $this->getElementAttrs('appointments-container', 'appointments-container');
            $html .= '<div' . $appointmentsContainerAttrs . '>';

            // Show ALL appointments - no grouping, no limit
            foreach ($appointments as $apt) {
                $html .= $this->renderAppointment($apt);
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
    
    /**
     * Render a single appointment
     *
     * @param array $appointment Appointment data
     * @return string HTML
     */
    private function renderAppointment($appointment) {
        $multidayClass = $appointment['is_multiday'] ? ' appointment-multiday' : '';
        $firstDayClass = $appointment['is_first_day'] ? ' appointment-first-day' : '';
        $lastDayClass = $appointment['is_last_day'] ? ' appointment-last-day' : '';

        // Format dates based on locale
        $dateFormatter = new IntlDateFormatter(
            $this->locale,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::SHORT
        );

        $startDateFormatted = $dateFormatter->format($appointment['start_date']);
        $endDateFormatted = $dateFormatter->format($appointment['end_date']);

        // Time display - Mostra sempre start e end time
        $timeDisplay = $appointment['start_time'] . ' - ' . $appointment['end_time'];

        // Date range display for multi-day events
        $dateRangeDisplay = '';
        if ($appointment['is_multiday']) {
            $dateRangeDisplay = "<span class='appointment-dates'>{$startDateFormatted} - {$endDateFormatted}</span>";
        }

        // Build data-fetch attributes and clickable class if URL is configured
        $dataFetchAttr = '';
        $clickableClass = '';
        if (!empty($this->on_appointment_click_url)) {
            $clickUrl = str_replace('%id%', $appointment['id'], $this->on_appointment_click_url);
            $dataFetchAttr = ' data-fetch="post" data-url="' . htmlspecialchars($clickUrl) . '"';
            $clickableClass = ' appointment-clickable';
        }

        // Apply custom appointment classes
        $defaultAppointmentClass = "appointment js-appointment {$appointment['class']}{$multidayClass}{$firstDayClass}{$lastDayClass}{$clickableClass}";
        $appointmentAttrs = $this->getElementAttrs('appointment', $defaultAppointmentClass);

        return <<<HTML
<div{$appointmentAttrs} data-appointment-id="{$appointment['id']}"{$dataFetchAttr}>
    <span class="appointment-time">{$timeDisplay}</span>
    {$dateRangeDisplay}
    <span class="appointment-title">{$appointment['title']}</span>
</div>

HTML;
    }
}