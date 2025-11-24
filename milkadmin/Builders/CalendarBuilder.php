<?php
namespace Builders;

use App\{Get};

!defined('MILK_DIR') && die(); // Prevents direct access

/*
Bozza parziale di documentazione:
private $header_title = 'Calendar';
private $header_icon = '';
private $header_color_class = 'bg-primary';
2. calendar.php:66-116 - Metodi di configurazione
setHeaderTitle($title) - Imposta il titolo dell'header
setHeaderIcon($icon) - Imposta l'icona (Bootstrap Icons)
setHeaderColor($color) - Imposta il colore (accetta nomi predefiniti come 'primary', 'success', 'danger', ecc.)
setHeaderColorClass($class) - Imposta una classe CSS custom
3. calendar.php:296 - Header HTML migliorato
Background colorato con classe dinamica
Padding migliorato (15px 20px)
Icona prima del titolo (opzionale)
Bottoni con sfondo semi-trasparente bianco
Testo bianco su sfondi scuri, testo scuro su sfondi chiari
4. calendar.css:17-53 - Stili CSS
Padding e border-radius
Box-shadow con hover effect
Styling per icone e bottoni
Transizioni smooth
5. CalendarBuilder.php:23-25 - Proprietà builder
protected $header_title = 'Calendar';
protected $header_icon = '';
protected $header_color = 'primary';
6. CalendarBuilder.php:111-153 - Metodi fluent
->setHeaderTitle('Events Calendar')
->setHeaderIcon('bi-calendar-event')
->setHeaderColor('primary')
7. EventsController.php:28-30 - Esempio d'uso
->setHeaderTitle('Events Calendar')
->setHeaderIcon('bi-calendar-event')
->setHeaderColor('primary')
Colori Predefiniti Disponibili
primary (blu)
secondary (grigio)
success (verde)
danger (rosso)
warning (giallo)
info (azzurro)
light (chiaro)
dark (scuro)
Esempio Completo
$calendar = CalendarBuilder::create($model, 'my_calendar')
    ->setHeaderTitle('My Events')
    ->setHeaderIcon('bi-calendar-check')
    ->setHeaderColor('success')  // Header verde
    ->setMonthYear(11, 2025)
    ->render();

*/



/**
 * CalendarBuilder - Fluent interface for creating calendar views
 *
 * Extends GetDataBuilder to provide calendar-specific functionality with
 * automatic month/year filtering and event rendering.
 *
 * @package Builders
 * @author MilkAdmin
 */
class CalendarBuilder extends GetDataBuilder
{
    protected $month = null;
    protected $year = null;
    protected $locale = 'en_US';
    protected $header_title = 'Calendar';
    protected $header_icon = '';
    protected $header_color = 'primary';
    protected $on_appointment_click_url = '';
    protected $on_empty_date_click_url = '';
    protected $on_date_with_appointments_click_url = '';
    protected $on_date_with_appointments_click_mode = 'fetch';

    // Date range configuration for navigation
    protected $min_year = null;
    protected $max_year = null;
    protected $min_month = null;
    protected $max_month = null;

    // Display options
    protected $compact = false;
    protected $highlight_days_with_appointments = false;
    protected $show_header = true;
    protected $show_year_month_select = true;
    protected $show_prev_next_buttons = true;
    protected $show_today_button = true;

    // Custom rendering
    protected $custom_cell_renderer = null;

    // Field mappings for event data
    protected $field_mappings = [
        'id' => 'id',
        'title' => 'title',
        'start_datetime' => 'start_datetime',
        'end_datetime' => 'end_datetime',
        'class' => 'event_class'
    ];

    protected $date_start_field = 'start_datetime';
    protected $date_end_field = 'end_datetime';

    // Calendar styling attributes
    protected $calendar_attrs = [];

    /**
     * Set the month to display (1-12)
     *
     * Automatically applies query filter for events in this month
     *
     * @param int $month Month number (1-12)
     * @return static For method chaining
     *
     * @example ->setMonth(11)
     */
    public function setMonth(int $month): static {
        $this->resetFieldContext();

        if ($month < 1) {
            $month = 1;
        }
        if ($month > 12) {
            $month = 12;
        }

        $this->month = $month;
        $this->applyMonthYearFilter();
        return $this;
    }

    /**
     * Set the year to display
     *
     * Automatically applies query filter for events in this year
     *
     * @param int $year Year (e.g., 2025)
     * @return static For method chaining
     *
     * @example ->setYear(2025)
     */
    public function setYear(int $year): static {
        $this->resetFieldContext();
        $year = (int)$year;
        $this->year = $year;
        $this->applyMonthYearFilter();
        return $this;
    }

    /**
     * Set both month and year at once
     *
     * @param int|null $month Month number (1-12), null to auto-detect from REQUEST
     * @param int|null $year Year (e.g., 2025), null to auto-detect from REQUEST
     * @return static For method chaining
     *
     * @example ->setMonthYear(11, 2025)
     * @example ->setMonthYear() // Auto-detects from $_REQUEST or uses current date
     */
    public function setMonthYear(?int $month = null, ?int $year = null): static {
        // If month/year not provided, try to get from $_REQUEST using calendar ID
        if ($month === null || $year === null) {
            $calendar_id = $this->getId();
            $calendar_params = $_REQUEST[$calendar_id] ?? [];

            if ($month === null) {
                $month = (int)($calendar_params['month'] ?? date('n'));
            }
            if ($year === null) {
                $year = (int)($calendar_params['year'] ?? date('Y'));
            }
        }

        $this->setMonth($month);
        $this->setYear($year);
        return $this;
    }

    /**
     * Set locale for calendar rendering
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
     * @example ->setHeaderTitle('My Events')
     */
    public function setHeaderTitle(string $title): static {
        $this->resetFieldContext();
        $this->header_title = $title;
        return $this;
    }

    /**
     * Set header icon (Bootstrap Icons class)
     *
     * @param string $icon Icon class (e.g., 'bi-calendar-event')
     * @return static For method chaining
     *
     * @example ->setHeaderIcon('bi-calendar-event')
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
     * Set URL template for appointment click
     *
     * Use %id% placeholder for appointment ID
     *
     * @param string $url URL template (e.g., '?page=events&action=edit&id=%id%')
     * @return static For method chaining
     *
     * @example ->onAppointmentClick('?page=events&action=edit&id=%id%')
     */
    public function onAppointmentClick(string $url): static {
        $this->resetFieldContext();
        $this->on_appointment_click_url = $url;
        return $this;
    }

    /**
     * Set URL template for empty date click
     *
     * Use %date% placeholder for date in Y-m-d format
     *
     * @param string $url URL template (e.g., '?page=events&action=new&date=%date%')
     * @return static For method chaining
     *
     * @example ->onEmptyDateClick('?page=events&action=new&date=%date%')
     */
    public function onEmptyDateClick(string $url): static {
        $this->resetFieldContext();
        $this->on_empty_date_click_url = $url;
        return $this;
    }

    /**
     * Set URL template for date with appointments click
     *
     * Use %date% placeholder for date in Y-m-d format
     *
     * @param string $url URL template (e.g., '?page=events&action=list&date=%date%')
     * @param string $mode Click mode: 'fetch' (AJAX) or 'link' (navigation)
     * @return static For method chaining
     *
     * @example ->onDateWithAppointmentsClick('?page=events&action=list&date=%date%', 'fetch')
     */
    public function onDateWithAppointmentsClick(string $url, string $mode = 'fetch'): static {
        $this->resetFieldContext();
        $this->on_date_with_appointments_click_url = $url;
        $this->on_date_with_appointments_click_mode = $mode;
        return $this;
    }

    /**
     * Enable or disable compact mode
     *
     * Compact mode shows a smaller calendar with no event details displayed
     *
     * @param bool $compact Enable compact mode
     * @return static For method chaining
     *
     * @example ->setCompact(true)
     */
    public function setCompact(bool $compact): static {
        $this->resetFieldContext();
        $this->compact = $compact;
        return $this;
    }

    /**
     * Enable or disable highlighting of days with appointments
     *
     * When enabled, days with appointments will have special styling
     *
     * @param bool $highlight Enable highlighting
     * @return static For method chaining
     *
     * @example ->setHighlightDaysWithAppointments(true)
     */
    public function setHighlightDaysWithAppointments(bool $highlight): static {
        $this->resetFieldContext();
        $this->highlight_days_with_appointments = $highlight;
        return $this;
    }

    /**
     * Show or hide the calendar header
     *
     * When hidden, only the calendar grid is displayed
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
     * Show or hide year/month dropdown selectors
     *
     * @param bool $show Show selectors
     * @return static For method chaining
     *
     * @example ->setShowYearMonthSelect(false)
     */
    public function setShowYearMonthSelect(bool $show): static {
        $this->resetFieldContext();
        $this->show_year_month_select = $show;
        return $this;
    }

    /**
     * Show or hide previous/next month navigation buttons
     *
     * @param bool $show Show buttons
     * @return static For method chaining
     *
     * @example ->setShowPrevNextButtons(true)
     */
    public function setShowPrevNextButtons(bool $show): static {
        $this->resetFieldContext();
        $this->show_prev_next_buttons = $show;
        return $this;
    }

    /**
     * Show or hide today button
     *
     * @param bool $show Show today button
     * @return static For method chaining
     *
     * @example ->setShowTodayButton(false)
     */
    public function setShowTodayButton(bool $show): static {
        $this->resetFieldContext();
        $this->show_today_button = $show;
        return $this;
    }

    /**
     * Set custom cell renderer for day cells
     *
     * The renderer receives these parameters:
     * - int $day: Day number
     * - int $month: Month number
     * - int $year: Year number
     * - bool $otherMonth: Whether it belongs to another month
     * - string $date: Date in Y-m-d format
     * - bool $isToday: Whether it's today
     * - array $appointments: Array of appointments for this day
     * - Calendar $calendar: Calendar instance
     *
     * @param callable $renderer Custom renderer function
     * @return static For method chaining
     *
     * @example ->setCustomCellRenderer(function($day, $month, $year, $otherMonth, $date, $isToday, $appointments, $calendar) {
     *     return '<div class="custom-day">' . $day . '</div>';
     * })
     */
    public function setCustomCellRenderer(callable $renderer): static {
        $this->resetFieldContext();
        $this->custom_cell_renderer = $renderer;
        return $this;
    }

    /**
     * Set minimum navigable date (year and optional month)
     *
     * Restricts calendar navigation to dates on or after this date.
     * If not set, defaults to (current year - 2).
     *
     * @param int $year Minimum year
     * @param int|null $month Minimum month (1-12), optional
     * @return static For method chaining
     *
     * @example ->setMinDate(2020) // From January 2020
     * @example ->setMinDate(2020, 6) // From June 2020
     */
    public function setMinDate(int $year, ?int $month = null): static {
        $this->resetFieldContext();
        $this->min_year = $year;
        $this->min_month = $month;
        return $this;
    }

    /**
     * Set maximum navigable date (year and optional month)
     *
     * Restricts calendar navigation to dates on or before this date.
     * If not set, defaults to (current year + 5).
     *
     * @param int $year Maximum year
     * @param int|null $month Maximum month (1-12), optional
     * @return static For method chaining
     *
     * @example ->setMaxDate(2030) // Until December 2030
     * @example ->setMaxDate(2030, 6) // Until June 2030
     */
    public function setMaxDate(int $year, ?int $month = null): static {
        $this->resetFieldContext();
        $this->max_year = $year;
        $this->max_month = $month;
        return $this;
    }

    /**
     * Set date range for calendar navigation
     *
     * Convenience method to set both min and max dates at once.
     *
     * @param int $minYear Minimum year
     * @param int $maxYear Maximum year
     * @param int|null $minMonth Minimum month (1-12), optional
     * @param int|null $maxMonth Maximum month (1-12), optional
     * @return static For method chaining
     *
     * @example ->setDateRange(2020, 2030) // From Jan 2020 to Dec 2030
     * @example ->setDateRange(2020, 2030, 6, 11) // From June 2020 to November 2030
     */
    public function setDateRange(int $minYear, int $maxYear, ?int $minMonth = null, ?int $maxMonth = null): static {
        $this->resetFieldContext();
        $this->min_year = $minYear;
        $this->max_year = $maxYear;
        $this->min_month = $minMonth;
        $this->max_month = $maxMonth;
        return $this;
    }

    /**
     * Map database fields to calendar event properties
     *
     * @param array $mappings Field mappings [event_property => db_field]
     * @return static For method chaining
     *
     * @example ->mapFields([
     *   'id' => 'event_id',
     *   'title' => 'event_name',
     *   'start_datetime' => 'start_date',
     *   'end_datetime' => 'end_date',
     *   'class' => 'css_class'
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
    // CALENDAR STYLING METHODS
    // ========================================================================

    /**
     * Set calendar attributes for any element
     *
     * @param array $attrs Attributes array
     * @return static For method chaining
     */
    public function setCalendarAttrs(array $attrs): static {
        $this->calendar_attrs = $attrs;
        return $this;
    }

    /**
     * Add a single attribute to a calendar element
     *
     * @param string $element Element name (container, grid, weekdays, etc.)
     * @param string $key Attribute key (usually 'class')
     * @param string $value Attribute value
     * @return static For method chaining
     */
    public function addCalendarAttr($element, $key, $value): static {
        $this->calendar_attrs[$element][$key] = $value;
        return $this;
    }

    /**
     * Set CSS classes for the calendar container
     *
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     *
     * @example ->containerClass('shadow-lg rounded')
     */
    public function containerClass($classes): static {
        $this->calendar_attrs['container']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for the calendar grid
     *
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     *
     * @example ->gridClass('border-0 shadow-sm')
     */
    public function gridClass($classes): static {
        $this->calendar_attrs['grid']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for the weekdays header
     *
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     *
     * @example ->weekdaysClass('bg-light text-uppercase')
     */
    public function weekdaysClass($classes): static {
        $this->calendar_attrs['weekdays']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for the days grid
     *
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     *
     * @example ->daysGridClass('gap-2')
     */
    public function daysGridClass($classes): static {
        $this->calendar_attrs['days']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for day cells
     *
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     *
     * @example ->dayClass('border rounded p-2')
     */
    public function dayClass($classes): static {
        $this->calendar_attrs['day']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for day numbers
     *
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     *
     * @example ->dayNumberClass('fw-bold fs-5')
     */
    public function dayNumberClass($classes): static {
        $this->calendar_attrs['day-number']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for appointments container
     *
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     *
     * @example ->appointmentContainerClass('d-flex flex-column gap-1')
     */
    public function appointmentContainerClass($classes): static {
        $this->calendar_attrs['appointments-container']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for appointment items
     *
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     *
     * @example ->appointmentClass('rounded-pill shadow-sm')
     */
    public function appointmentClass($classes): static {
        $this->calendar_attrs['appointment']['class'] = $classes;
        return $this;
    }

    /**
     * Set calendar color theme
     *
     * Applies a coordinated color scheme to the calendar with predefined Bootstrap colors.
     * Automatically styles the header, today marker, and appointments.
     *
     * @param string $color Color name: 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'
     * @return static For method chaining
     *
     * @example ->calendarColor('primary') // Blue theme
     * @example ->calendarColor('success') // Green theme
     */
    public function calendarColor($color): static {
        // Mappa i colori alle classi Bootstrap
        $color_map = [
            // Colori base
            'primary' => ['bg' => 'bg-primary', 'text' => 'text-white', 'border' => 'border-primary'],
            'secondary' => ['bg' => 'bg-secondary', 'text' => 'text-white', 'border' => 'border-secondary'],
            'success' => ['bg' => 'bg-success', 'text' => 'text-white', 'border' => 'border-success'],
            'danger' => ['bg' => 'bg-danger', 'text' => 'text-white', 'border' => 'border-danger'],
            'warning' => ['bg' => 'bg-warning', 'text' => 'text-dark', 'border' => 'border-warning'],
            'info' => ['bg' => 'bg-info', 'text' => 'text-white', 'border' => 'border-info'],
            'light' => ['bg' => 'bg-light', 'text' => 'text-dark', 'border' => 'border-light'],
            'dark' => ['bg' => 'bg-dark', 'text' => 'text-white', 'border' => 'border-dark'],

            // Aliases
            'blue' => ['bg' => 'bg-primary', 'text' => 'text-white', 'border' => 'border-primary'],
            'gray' => ['bg' => 'bg-secondary', 'text' => 'text-white', 'border' => 'border-secondary'],
            'grey' => ['bg' => 'bg-secondary', 'text' => 'text-white', 'border' => 'border-secondary'],
            'green' => ['bg' => 'bg-success', 'text' => 'text-white', 'border' => 'border-success'],
            'red' => ['bg' => 'bg-danger', 'text' => 'text-white', 'border' => 'border-danger'],
            'yellow' => ['bg' => 'bg-warning', 'text' => 'text-dark', 'border' => 'border-warning'],
            'cyan' => ['bg' => 'bg-info', 'text' => 'text-white', 'border' => 'border-info'],
            'white' => ['bg' => 'bg-light', 'text' => 'text-dark', 'border' => 'border-light'],
            'black' => ['bg' => 'bg-dark', 'text' => 'text-white', 'border' => 'border-dark'],
        ];

        // Se il colore esiste nella mappa, applica lo schema coordinato
        if (isset($color_map[$color])) {
            $scheme = $color_map[$color];

            // Imposta colore header (usa il metodo esistente)
            $this->setHeaderColor($color);

            // Aggiungi data attribute per CSS targeting avanzato
            $this->addCalendarAttr('container', 'data-calendar-color', $color);

        } else {
            // Se il colore non esiste, prova comunque ad applicarlo all'header
            $this->setHeaderColor($color);
        }

        return $this;
    }

    // ========================================================================
    // END CALENDAR STYLING METHODS
    // ========================================================================

    /**
     * Apply month/year filter to the query
     *
     * Filters events that:
     * - Start during the month, OR
     * - End during the month, OR
     * - Span across the entire month
     *
     * @return void
     */
    protected function applyMonthYearFilter(): void {
        if ($this->month === null || $this->year === null) {
            return;
        }

        // Calculate first and last day of the month
        $firstDay = date('Y-m-d 00:00:00', mktime(0, 0, 0, $this->month, 1, $this->year));
        $lastDay = date('Y-m-t 23:59:59', mktime(0, 0, 0, $this->month, 1, $this->year));

        $db = $this->model->getDb();
        $startField = $db->qn($this->date_start_field);
        $endField = $db->qn($this->date_end_field);

        // Build WHERE clause for events visible in this month
        $where = "
            (({$startField} >= ? AND {$startField} <= ?) OR
             ({$endField} >= ? AND {$endField} <= ?) OR
             ({$startField} < ? AND {$endField} > ?))
        ";

        $params = [
            $firstDay, $lastDay,  // Start date in range
            $firstDay, $lastDay,  // End date in range
            $firstDay, $lastDay   // Spans entire month
        ];

        $this->query->where($where, $params);
    }

    /**
     * Validate and adjust month/year to be within allowed range
     *
     * If the date is out of range, adjusts it to the nearest valid date.
     * If min/max dates are not set, defaults to (current year - 2) and (current year + 5).
     *
     * @return void
     */
    protected function validateAndAdjustDate(): void {
        // Apply default values if min/max dates not set
        $minYear = $this->min_year ?? (date('Y') - 2);
        $maxYear = $this->max_year ?? (date('Y') + 5);
        $minMonth = $this->min_month;
        $maxMonth = $this->max_month;

        // Check if year is below minimum
        if ($this->year < $minYear) {
            $this->year = $minYear;
            $this->month = $minMonth ?? 1;
        }
        // Check if year is above maximum
        elseif ($this->year > $maxYear) {
            $this->year = $maxYear;
            $this->month = $maxMonth ?? 12;
        }
        // Check if month is out of range in min/max year
        elseif ($this->year == $minYear && $minMonth !== null && $this->month < $minMonth) {
            $this->month = $minMonth;
        }
        elseif ($this->year == $maxYear && $maxMonth !== null && $this->month > $maxMonth) {
            $this->month = $maxMonth;
        }
    }

    /**
     * Get calendar HTML string
     *
     * @return string Complete HTML calendar ready for display
     */
    public function render(): string {
        // Auto-detect month/year from REQUEST if not set
        if ($this->month === null || $this->year === null) {
            $calendar_id = $this->getId();
            $calendar_params = $_REQUEST[$calendar_id] ?? [];

            if ($this->month === null) {
                $this->month = (int)($calendar_params['month'] ?? date('n'));
            }
            if ($this->year === null) {
                $this->year = (int)($calendar_params['year'] ?? date('Y'));
            }
        }

        // Validate and adjust date to be within allowed range
        $this->validateAndAdjustDate();

        // Use locale from config if not set
        if (empty($this->locale)) {
            $this->locale = Get::userLocale();
        }

        // Apply filter before getting data
        $this->applyMonthYearFilter();
        // remove limit filter
        $this->query->clean('limit');
        // print query
        //print "<p>".$this->query."</p>";
        // Get events data (processed rows)
        $rows = $this->getRowsData();
        $events = [];

        // Use rows_raw for getting original datetime from database (MySQL format)
        foreach ($rows as $index => $row) {
            $raw_row = $this->rows_raw[$index] ?? null;

            if (!$raw_row) {
                continue;
            }

            // Get datetime fields from raw data (MySQL format: Y-m-d H:i:s)
            $start_field = $this->field_mappings['start_datetime'];
            $end_field = $this->field_mappings['end_datetime'];

            $start_string = $raw_row->$start_field ?? null;
            $end_string = $raw_row->$end_field ?? null;

            if (!$start_string || !$end_string) {
                continue;
            }

            // Parse MySQL datetime format directly (no format guessing needed)
            // Check if already DateTime object, otherwise skip this record
            if ($start_string instanceof \DateTime) {
                $start = $start_string;
            } else {
                continue; // Skip this record if start date is not a DateTime object
            }

            // Handle end date: if not DateTime, use start date
            if ($end_string instanceof \DateTime) {
                $end = $end_string;
            } else {
                $end = $start; // Use start date if end date is not a DateTime object
            }

            // Use processed row data for other fields (might have been modified by hooks)
            $events[] = (object)[
                'id' => $this->getEventProperty($row, $raw_row, 'id'),
                'title' => $this->getEventProperty($row, $raw_row, 'title'),
                'start_datetime' => $start,
                'end_datetime' => $end,
                'class' => $this->getEventProperty($row, $raw_row, 'class', 'event-primary')
            ];
        }

        // Generate calendar HTML
        return $this->generateCalendarHtml($events);
    }

    /**
     * Set calendar ID for AJAX requests and HTML element identification
     *
     * If not set, defaults to 'calendar_' + unique ID
     *
     * @param string $calendar_id Calendar identifier
     * @return static For method chaining
     *
     * @example ->setCalendarId('my_calendar')
     */
    public function setCalendarId(string $calendar_id): static {
        $this->table_id = $calendar_id;
        return $this;
    }

    /**
     * Get calendar ID
     *
     * Returns the calendar ID, generating a default one if not set
     *
     * @return string Calendar identifier
     */
    public function getId(): string {
        if (empty($this->table_id)) {
            $this->table_id = 'calendar_' . uniqid();
        }
        return $this->table_id;
    }

    /**
     * Magic method to render calendar when object is cast to string
     *
     * @return string Complete HTML calendar ready for display
     */
    public function __toString(): string {
        return $this->render();
    }

    /**
     * Generate calendar HTML with events
     *
     * @param array $events Event data from database
     * @return string Calendar HTML
     */
    protected function generateCalendarHtml($events): string {
        // Apply default values if min/max dates not set
        $minYear = $this->min_year ?? (date('Y') - 2);
        $maxYear = $this->max_year ?? (date('Y') + 5);

        // Load Calendar class and pass all necessary variables
        return Get::themePlugin('calendar', [
            'events' => $events,
            'calendar_id' => $this->getId(),
            'month' => $this->month,
            'year' => $this->year,
            'locale' => $this->locale,
            'header_title' => $this->header_title,
            'header_icon' => $this->header_icon,
            'header_color' => $this->header_color,
            'on_appointment_click_url' => $this->on_appointment_click_url,
            'on_empty_date_click_url' => $this->on_empty_date_click_url,
            'on_date_with_appointments_click_url' => $this->on_date_with_appointments_click_url,
            'on_date_with_appointments_click_mode' => $this->on_date_with_appointments_click_mode,
            'min_year' => $minYear,
            'max_year' => $maxYear,
            'min_month' => $this->min_month,
            'max_month' => $this->max_month,
            'compact' => $this->compact,
            'highlight_days_with_appointments' => $this->highlight_days_with_appointments,
            'show_header' => $this->show_header,
            'show_year_month_select' => $this->show_year_month_select,
            'show_prev_next_buttons' => $this->show_prev_next_buttons,
            'show_today_button' => $this->show_today_button,
            'custom_cell_renderer' => $this->custom_cell_renderer,
            'calendar_attrs' => $this->calendar_attrs
        ]);
    }


    /**
     * Get event property using field mappings
     *
     * Supports both string field names and callable functions:
     * - String: Returns the property value from the event object
     * - Callable: Executes the function passing the event object and returns the result
     *
     * @param object $event Event data object
     * @param string $property Property name (id, title, start_datetime, etc.)
     * @param mixed $default Default value if property not found
     * @return mixed Property value or callable result
     */
    protected function getEventProperty($event, $event_raw, string $property, $default = null) {
        $mapping = $this->field_mappings[$property] ?? $property;

        // Check if the mapping is a callable (function/closure)
        if (is_callable($mapping)) {
            return $mapping($event, $event_raw);
        }

        // Otherwise treat it as a field name string
        $field = $mapping;
        return $event->$field ?? $default;
    }

    /**
     * Get month currently set
     *
     * @return int|null Month (1-12) or null if not set
     */
    public function getMonth(): ?int {
        return $this->month;
    }

    /**
     * Get year currently set
     *
     * @return int|null Year or null if not set
     */
    public function getYear(): ?int {
        return $this->year;
    }

  
}
