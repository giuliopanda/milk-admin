<?php
namespace Modules\Docs\Pages;
/**
 * @title CalendarBuilder
 * @guide developer
 * @order 30
 * @tags CalendarBuilder, calendar, events, appointments, scheduling, GetDataBuilder, query, database, AJAX, localization
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>CalendarBuilder</h1>

    <p>The CalendarBuilder provides a powerful, database-driven calendar system with automatic query filtering, event mapping, and extensive customization options. It extends GetDataBuilder, inheriting all query manipulation capabilities.</p>

    <h2>Key Features</h2>
    <ul>
        <li><strong>Database Integration</strong>: Automatically queries events from your database with proper date filtering</li>
        <li><strong>Multiple Views</strong>: Monthly and weekly calendar views with automatic view type tracking</li>
        <li><strong>Query Manipulation</strong>: Full access to GetDataBuilder methods (where, orderBy, join, etc.)</li>
        <li><strong>Field Mapping</strong>: Map your database fields to calendar properties with support for callable functions</li>
        <li><strong>AJAX Support</strong>: Built-in AJAX navigation with automatic type and filter persistence</li>
        <li><strong>Localization</strong>: Multi-language support using PHP's IntlDateFormatter</li>
        <li><strong>Multi-day Events</strong>: Automatic handling of events spanning multiple days</li>
        <li><strong>Responsive Design</strong>: Bootstrap-based responsive calendar grid</li>
        <li><strong>Customization</strong>: Extensive styling and behavior options</li>
    </ul>

    <h2>Quick Start</h2>

    <h3>Basic Calendar</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use Builders\CalendarBuilder;

// Simple calendar with default settings
$calendar = CalendarBuilder::create($eventModel)
    ->setMonthYear()  // Auto-detects from request or uses current month
    ->render();

echo $calendar;</code></pre>

    <h3>Complete Example from Events Module</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($this->model, 'calendar_events')
    ->mapFields([
        'id' => 'id',
        'title' => 'title',
        'start_datetime' => 'start_datetime',
        'end_datetime' => 'end_datetime',
        'class' => function($event, $event_raw) {
            return $event_raw->event_class;
        }
    ])
    ->setMonthYear()
    ->setHeaderTitle('Events Calendar')
    ->setHeaderIcon('bi-calendar-event')
    ->setHeaderColor('primary')
    ->onAppointmentClick('?page=events&action=edit&id=%id%')
    ->onEmptyDateClick('?page=events&action=new&date=%date%')
    ->render();</code></pre>

    <h2>Database Query Manipulation</h2>

    <p>CalendarBuilder extends <code>GetDataBuilder</code>, providing full access to query manipulation methods. The builder automatically filters events for the selected month, but you can add additional conditions.</p>

    <h3>Basic Query Filtering</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    ->where('status = ?', ['active'])
    ->where('user_id = ?', [$userId])
    ->orderBy('start_datetime', 'asc')
    ->setMonthYear()
    ->render();</code></pre>

    <h3>Advanced Query with Joins</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    // Join with users table
    ->join('LEFT JOIN #__users u ON u.id = t.user_id')

    // Filter by category
    ->where('t.category_id = ?', [$categoryId])

    // Only show public or user's own events
    ->where('(t.visibility = ? OR t.user_id = ?)', ['public', $userId], 'OR')

    // Order by priority then date
    ->orderBy('t.priority', 'desc')
    ->orderBy('t.start_datetime', 'asc')

    ->setMonthYear()
    ->render();</code></pre>

    <h3>Query with Search Filter</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$search = $_GET['search'] ?? '';

$calendar = CalendarBuilder::create($eventModel)
    ->setMonthYear();

// Add search filter if provided
if (!empty($search)) {
    $calendar->where(
        '(title LIKE ? OR description LIKE ?)',
        ['%' . $search . '%', '%' . $search . '%']
    );
}

echo $calendar->render();</code></pre>

    <h2>Field Mapping</h2>

    <p>Map your database fields to calendar event properties. Supports both string field names and callable functions for dynamic values.</p>

    <h3>Basic Field Mapping</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    ->mapFields([
        'id' => 'event_id',
        'title' => 'event_name',
        'start_datetime' => 'start_date',
        'end_datetime' => 'end_date',
        'class' => 'css_class'
    ])
    ->setMonthYear()
    ->render();</code></pre>

    <h3>Advanced Mapping with Callable Functions</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    ->mapFields([
        'id' => 'id',
        'title' => function($event, $event_raw) {
            // Custom title with user name
            return $event_raw->title . ' (' . $event_raw->user_name . ')';
        },
        'start_datetime' => 'start_datetime',
        'end_datetime' => 'end_datetime',
        'class' => function($event, $event_raw) {
            // Dynamic CSS class based on priority
            if ($event_raw->priority == 'high') {
                return 'event-danger';
            } elseif ($event_raw->priority == 'medium') {
                return 'event-warning';
            }
            return 'event-primary';
        }
    ])
    ->setMonthYear()
    ->render();</code></pre>

    <h2>Header Customization</h2>

    <h3>Header Styling</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    ->setHeaderTitle('Team Calendar')
    ->setHeaderIcon('bi-calendar-check')
    ->setHeaderColor('success')  // primary, secondary, success, danger, warning, info, light, dark
    ->setMonthYear()
    ->render();</code></pre>

    <h3>Hide Header Completely</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    ->setShowHeader(false)
    ->setCompact(true)
    ->setMonthYear()
    ->render();</code></pre>

    <h2>Graphic Styling</h2>

    <p>CalendarBuilder provides comprehensive CSS class customization methods similar to TableBuilder and ListBuilder. You can customize the appearance of every calendar element.</p>

    <h3>Quick Color Theme</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    ->calendarColor('success')  // Applies coordinated green theme
    ->setMonthYear()
    ->render();

// Available colors: primary, secondary, success, danger, warning, info, light, dark
// Also supports aliases: blue, green, red, yellow, cyan, gray, black, white</code></pre>

    <h3>Individual Element Styling</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    ->containerClass('shadow-lg rounded-4 border border-2 border-success')
    ->gridClass('border-0')
    ->weekdaysClass('bg-success text-white fw-bold')
    ->daysGridClass('gap-2')
    ->dayClass('border border-success bg-light')
    ->dayNumberClass('text-success fw-bold')
    ->appointmentContainerClass('gap-2')
    ->appointmentClass('rounded-pill shadow-sm')
    ->setMonthYear()
    ->render();</code></pre>

    <h3>Custom Attributes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    // Add custom data attributes
    ->addCalendarAttr('container', 'data-theme', 'dark')
    ->addCalendarAttr('container', 'class', 'custom-calendar')
    ->addCalendarAttr('day', 'data-tooltip', 'enabled')
    ->setMonthYear()
    ->render();</code></pre>

    <h3>Combined Theme Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Elegant theme with shadows
$calendar = CalendarBuilder::create($eventModel)
    ->calendarColor('warning')
    ->containerClass('shadow-lg')
    ->weekdaysClass('bg-warning bg-opacity-25 text-dark fw-semibold border-bottom border-warning')
    ->dayClass('shadow-sm')
    ->dayNumberClass('badge bg-warning bg-opacity-10 text-dark')
    ->setMonthYear()
    ->render();

// Dark theme
$calendar = CalendarBuilder::create($eventModel)
    ->calendarColor('dark')
    ->containerClass('border border-secondary')
    ->gridClass('bg-dark')
    ->weekdaysClass('bg-secondary text-light')
    ->daysGridClass('bg-dark')
    ->dayClass('bg-dark text-light border-secondary')
    ->dayNumberClass('text-warning fw-bold')
    ->setMonthYear()
    ->render();

// Minimalista theme
$calendar = CalendarBuilder::create($eventModel)
    ->containerClass('border-0')
    ->gridClass('border-0 shadow-none')
    ->weekdaysClass('bg-white border-bottom text-secondary text-uppercase small')
    ->daysGridClass('gap-1 bg-white')
    ->dayClass('border-0 rounded-3')
    ->dayNumberClass('small text-secondary')
    ->setMonthYear()
    ->render();</code></pre>

    <h3>Using with Plugin Directly</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// You can also pass calendar_attrs directly to the plugin
$calendar = Get::themePlugin('Calendar', [
    'month' => $month,
    'year' => $year,
    'header_color' => 'success',
    'calendar_attrs' => [
        'container' => ['class' => 'shadow-lg rounded-4'],
        'weekdays' => ['class' => 'bg-success text-white fw-bold'],
        'day' => ['class' => 'border border-success'],
        'day-number' => ['class' => 'text-success fw-bold']
    ]
]);</code></pre>

    <h2>Navigation Controls</h2>

    <h3>Customize Navigation Elements</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    // Control which navigation elements are shown
    ->setShowYearMonthSelect(true)   // Show year/month dropdowns
    ->setShowPrevNextButtons(true)   // Show prev/next arrows
    ->setShowTodayButton(false)      // Hide today button
    ->setMonthYear()
    ->render();</code></pre>

    <h3>Set Date Range Limits</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    // Limit navigation from 2024 to 2026
    ->setDateRange(2024, 2026)
    ->setMonthYear()
    ->render();

// Or set specific month ranges
$calendar = CalendarBuilder::create($eventModel)
    // From June 2024 to November 2026
    ->setDateRange(2024, 2026, 6, 11)
    ->setMonthYear()
    ->render();

// Set only minimum date
$calendar = CalendarBuilder::create($eventModel)
    ->setMinDate(2024, 1)  // From January 2024
    ->setMonthYear()
    ->render();

// Set only maximum date
$calendar = CalendarBuilder::create($eventModel)
    ->setMaxDate(2026, 12)  // Until December 2026
    ->setMonthYear()
    ->render();</code></pre>

    <h2>Display Options</h2>

    <h3>Calendar Views</h3>
    <p>CalendarBuilder supports both monthly and weekly views. The view type is automatically tracked and persisted during AJAX navigation.</p>

    <h4>Monthly View (Default)</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    ->useMonthlyView()  // or ->setViewType('monthly')
    ->setMonthYear()
    ->render();</code></pre>

    <h4>Weekly View</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    ->useWeeklyView()  // or ->setViewType('weekly')
    ->setWeeklyHours(8, 20)  // Show hours from 8:00 to 20:00
    ->setWeeklyHourHeight(60)  // 60px per hour
    ->setMonthYear()
    ->render();

// Or use the all-in-one method
$calendar = CalendarBuilder::create($eventModel)
    ->useWeeklyView()
    ->setWeeklyViewSettings(8, 20, 60)  // start, end, height
    ->setMonthYear()
    ->render();</code></pre>

    <h3>Compact Mode</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    ->setCompact(true)  // Smaller calendar, no event details shown
    ->setHighlightDaysWithAppointments(true)  // Add visual indicator for days with events
    ->setMonthYear()
    ->render();</code></pre>

    <h3>Locale Settings</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Italian calendar
$calendar = CalendarBuilder::create($eventModel)
    ->setLocale('it_IT')
    ->setMonthYear()
    ->render();

// French calendar
$calendar = CalendarBuilder::create($eventModel)
    ->setLocale('fr_FR')
    ->setMonthYear()
    ->render();

// German calendar
$calendar = CalendarBuilder::create($eventModel)
    ->setLocale('de_DE')
    ->setMonthYear()
    ->render();</code></pre>

    <h2>Click Handlers</h2>

    <p>Configure URLs for different click events. Use placeholders <code>%id%</code> for event ID and <code>%date%</code> for date in Y-m-d format.</p>

    <h3>Event Click Handler</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    // When user clicks an event, open edit form
    ->onAppointmentClick('?page=events&action=edit&id=%id%')
    ->setMonthYear()
    ->render();</code></pre>

    <h3>Empty Date Click Handler</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    // When user clicks an empty date, open new event form with pre-filled date
    ->onEmptyDateClick('?page=events&action=new&date=%date%')
    ->setMonthYear()
    ->render();</code></pre>

    <h3>Date with Appointments Click Handler</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    // When user clicks a date with events, show list of all events for that day
    ->onDateWithAppointmentsClick('?page=events&action=list&date=%date%', 'fetch')
    ->setMonthYear()
    ->render();

// Use 'link' mode for regular navigation instead of AJAX
$calendar = CalendarBuilder::create($eventModel)
    ->onDateWithAppointmentsClick('?page=events&action=list&date=%date%', 'link')
    ->setMonthYear()
    ->render();</code></pre>

    <h3>Complete Click Handlers Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    ->onAppointmentClick('?page=events&action=edit&id=%id%')
    ->onEmptyDateClick('?page=events&action=new&date=%date%')
    ->onDateWithAppointmentsClick('?page=events&action=daily&date=%date%', 'fetch')
    ->setMonthYear()
    ->render();</code></pre>

    <h2>Custom Cell Renderer</h2>

    <p>For advanced customization, provide a custom function to render each day cell. The renderer receives all day information and returns custom HTML.</p>

    <h3>Custom Cell Renderer Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    ->setCustomCellRenderer(function(
        int $day,
        int $month,
        int $year,
        bool $otherMonth,
        string $date,
        bool $isToday,
        array $appointments,
        $calendar
    ) {
        $classes = ['calendar-day'];

        if ($otherMonth) {
            $classes[] = 'other-month';
        }

        if ($isToday) {
            $classes[] = 'today';
        }

        $html = '&lt;div class="' . implode(' ', $classes) . '"&gt;';

        // Custom day number with badge
        if ($isToday) {
            $html .= '&lt;div class="day-number" style="background: #0d6efd; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold;"&gt;';
        } else {
            $html .= '&lt;div class="day-number"&gt;';
        }
        $html .= $day;
        $html .= '&lt;/div&gt;';

        // Show event count badge
        if (!empty($appointments)) {
            $count = count($appointments);
            $badgeColor = $count > 3 ? 'danger' : ($count > 1 ? 'warning' : 'success');
            $html .= '&lt;span class="badge bg-' . $badgeColor . '" style="font-size: 0.7rem;"&gt;';
            $html .= $count . ' event' . ($count > 1 ? 's' : '');
            $html .= '&lt;/span&gt;';
        }

        $html .= '&lt;/div&gt;';
        return $html;
    })
    ->setMonthYear()
    ->render();</code></pre>

    <h2>AJAX Implementation</h2>

    <p>CalendarBuilder automatically handles AJAX requests for navigation. The calendar automatically tracks and persists the view type (monthly/weekly) and any filters through hidden form fields during AJAX updates.</p>

    <h3>Hidden Form Fields</h3>
    <p>The calendar form includes two hidden fields that are automatically managed:</p>
    <ul>
        <li><code>type</code>: Stores the current view type ('monthly' or 'weekly')</li>
        <li><code>filters</code>: Stores custom filter data as a string</li>
    </ul>

    <h3>Basic AJAX Handler</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// In your controller
#[RequestAction('home')]
public function calendarView()
{
    $calendar_id = 'my_calendar';
    $calendar_params = $_REQUEST[$calendar_id] ?? [];

    $month = (int)($calendar_params['month'] ?? date('n'));
    $year = (int)($calendar_params['year'] ?? date('Y'));
    $type = $calendar_params['type'] ?? 'monthly';  // Get persisted view type

    $calendar = CalendarBuilder::create($this->model, $calendar_id)
        ->setViewType($type)  // Restore view type
        ->setMonthYear($month, $year)
        ->setHeaderTitle('My Calendar')
        ->render();

    // Handle AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

        Response::json([
            'success' => true,
            'html' => $calendar
        ]);
    }

    // Full page render
    Response::render(__DIR__ . '/Views/calendar.php', [
        'calendar' => $calendar
    ]);
}</code></pre>

    <h3>AJAX with Custom Filters</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// AJAX handler with filter persistence
#[RequestAction('home')]
public function calendarView()
{
    $calendar_id = 'my_calendar';
    $calendar_params = $_REQUEST[$calendar_id] ?? [];

    $month = (int)($calendar_params['month'] ?? date('n'));
    $year = (int)($calendar_params['year'] ?? date('Y'));
    $type = $calendar_params['type'] ?? 'monthly';
    $filters = $calendar_params['filters'] ?? '';  // Get persisted filters

    // Parse filters if needed
    $category = $_GET['category'] ?? '';

    $calendar = CalendarBuilder::create($this->model, $calendar_id)
        ->setViewType($type)
        ->setMonthYear($month, $year);

    // Apply filters
    if ($category) {
        $calendar->where('category = ?', [$category])
                 ->setFilters($category);  // Store filter for persistence
    }

    $html = $calendar->render();

    // Handle AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

        Response::json([
            'success' => true,
            'html' => $html
        ]);
    }

    // Full page render
    Response::render(__DIR__ . '/Views/calendar.php', [
        'calendar' => $html
    ]);
}</code></pre>

    <h2>Complete Real-World Example</h2>

    <p>This example shows a fully-featured calendar from the Events module with filtering, custom mapping, and click handlers:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class EventsController extends AbstractController
{
    #[RequestAction('home')]
    public function eventsCalendar()
    {
        $calendar_id = 'calendar_events';
        $calendar_params = $_REQUEST[$calendar_id] ?? [];

        // Get month/year from request
        $month = (int)($calendar_params['month'] ?? $_REQUEST['month'] ?? date('n'));
        $year = (int)($calendar_params['year'] ?? $_REQUEST['year'] ?? date('Y'));

        // Build calendar with all customizations
        $calendar = CalendarBuilder::create($this->model, $calendar_id)
            // Field mapping with callable for dynamic class
            ->mapFields([
                'id' => 'id',
                'title' => 'title',
                'start_datetime' => 'start_datetime',
                'end_datetime' => 'end_datetime',
                'class' => function($event, $event_raw) {
                    return $event_raw->event_class;
                }
            ])

            // Date and locale
            ->setMonthYear($month, $year)
            ->setLocale('it_IT')

            // Header customization
            ->setHeaderTitle('Events Calendar')
            ->setHeaderIcon('bi-calendar-event')
            ->setHeaderColor('primary')

            // Click handlers
            ->onAppointmentClick('?page=' . $this->page . '&action=edit&id=%id%')
            ->onEmptyDateClick('?page=' . $this->page . '&action=edit&date=%date%')

            // Optional: Set date range
            // ->setDateRange(2024, 2026)

            ->render();

        // Handle AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

            Response::json([
                'success' => true,
                'html' => $calendar
            ]);
        }

        // Full page render
        Response::render(__DIR__ . '/Views/calendar_page.php', [
            'calendar' => $calendar,
            'html' => $calendar,
            ...$this->getCommonData()
        ]);
    }
}</code></pre>

    <h2>Method Reference</h2>

    <h3>Query Methods (Inherited from GetDataBuilder)</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>where()</code></td>
                <td>$condition, $params, $logic</td>
                <td>Add WHERE condition to query</td>
            </tr>
            <tr>
                <td><code>orderBy()</code></td>
                <td>$field, $direction</td>
                <td>Add ORDER BY clause</td>
            </tr>
            <tr>
                <td><code>join()</code></td>
                <td>$join_clause</td>
                <td>Add JOIN clause</td>
            </tr>
            <tr>
                <td><code>limit()</code></td>
                <td>$limit</td>
                <td>Set query LIMIT (note: calendar removes limit automatically)</td>
            </tr>
        </tbody>
    </table>

    <h3>Calendar-Specific Methods</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>setMonth()</code></td>
                <td>int $month</td>
                <td>Set display month (1-12)</td>
            </tr>
            <tr>
                <td><code>setYear()</code></td>
                <td>int $year</td>
                <td>Set display year</td>
            </tr>
            <tr>
                <td><code>setMonthYear()</code></td>
                <td>?int $month, ?int $year</td>
                <td>Set both month and year (auto-detects from request if null)</td>
            </tr>
            <tr>
                <td><code>setLocale()</code></td>
                <td>string $locale</td>
                <td>Set locale for date formatting (e.g., 'it_IT', 'fr_FR')</td>
            </tr>
            <tr>
                <td><code>setCalendarId()</code></td>
                <td>string $id</td>
                <td>Set calendar ID for AJAX requests</td>
            </tr>
            <tr>
                <td><code>mapFields()</code></td>
                <td>array $mappings</td>
                <td>Map database fields to event properties</td>
            </tr>
        </tbody>
    </table>

    <h3>View Type Methods</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>setViewType()</code></td>
                <td>string $type</td>
                <td>Set view type: 'monthly' or 'weekly'</td>
            </tr>
            <tr>
                <td><code>useMonthlyView()</code></td>
                <td>-</td>
                <td>Shortcut for setViewType('monthly')</td>
            </tr>
            <tr>
                <td><code>useWeeklyView()</code></td>
                <td>-</td>
                <td>Shortcut for setViewType('weekly')</td>
            </tr>
            <tr>
                <td><code>setWeeklyViewSettings()</code></td>
                <td>int $hourStart, int $hourEnd, int $hourHeight</td>
                <td>Configure weekly view (start hour, end hour, px per hour)</td>
            </tr>
            <tr>
                <td><code>setWeeklyHours()</code></td>
                <td>int $start, int $end</td>
                <td>Set hour range for weekly view (0-23, 1-24)</td>
            </tr>
            <tr>
                <td><code>setWeeklyHourHeight()</code></td>
                <td>int $height</td>
                <td>Set hour cell height in pixels (min 40px)</td>
            </tr>
            <tr>
                <td><code>setFilters()</code></td>
                <td>string $filters</td>
                <td>Set custom filters string (persisted in AJAX)</td>
            </tr>
            <tr>
                <td><code>getViewType()</code></td>
                <td>-</td>
                <td>Get current view type</td>
            </tr>
        </tbody>
    </table>

    <h3>Header Methods</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>setHeaderTitle()</code></td>
                <td>string $title</td>
                <td>Set header title</td>
            </tr>
            <tr>
                <td><code>setHeaderIcon()</code></td>
                <td>string $icon</td>
                <td>Set header icon (Bootstrap Icons class)</td>
            </tr>
            <tr>
                <td><code>setHeaderColor()</code></td>
                <td>string $color</td>
                <td>Set header color (primary, success, danger, etc.)</td>
            </tr>
            <tr>
                <td><code>setShowHeader()</code></td>
                <td>bool $show</td>
                <td>Show/hide header completely</td>
            </tr>
        </tbody>
    </table>

    <h3>Navigation Methods</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>setShowYearMonthSelect()</code></td>
                <td>bool $show</td>
                <td>Show/hide year and month dropdown selectors</td>
            </tr>
            <tr>
                <td><code>setShowPrevNextButtons()</code></td>
                <td>bool $show</td>
                <td>Show/hide previous/next month buttons</td>
            </tr>
            <tr>
                <td><code>setShowTodayButton()</code></td>
                <td>bool $show</td>
                <td>Show/hide today button</td>
            </tr>
            <tr>
                <td><code>setMinDate()</code></td>
                <td>int $year, ?int $month</td>
                <td>Set minimum navigable date</td>
            </tr>
            <tr>
                <td><code>setMaxDate()</code></td>
                <td>int $year, ?int $month</td>
                <td>Set maximum navigable date</td>
            </tr>
            <tr>
                <td><code>setDateRange()</code></td>
                <td>int $minYear, int $maxYear, ?int $minMonth, ?int $maxMonth</td>
                <td>Set both min and max navigable dates</td>
            </tr>
        </tbody>
    </table>

    <h3>Display Methods</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>setCompact()</code></td>
                <td>bool $compact</td>
                <td>Enable/disable compact mode</td>
            </tr>
            <tr>
                <td><code>setHighlightDaysWithAppointments()</code></td>
                <td>bool $highlight</td>
                <td>Add special styling to days with events</td>
            </tr>
            <tr>
                <td><code>setCustomCellRenderer()</code></td>
                <td>callable $renderer</td>
                <td>Set custom function to render day cells</td>
            </tr>
        </tbody>
    </table>

    <h3>Styling Methods</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>calendarColor()</code></td>
                <td>string $color</td>
                <td>Apply coordinated color theme (primary, success, danger, etc.)</td>
            </tr>
            <tr>
                <td><code>containerClass()</code></td>
                <td>string $classes</td>
                <td>Set CSS classes for calendar container</td>
            </tr>
            <tr>
                <td><code>gridClass()</code></td>
                <td>string $classes</td>
                <td>Set CSS classes for calendar grid</td>
            </tr>
            <tr>
                <td><code>weekdaysClass()</code></td>
                <td>string $classes</td>
                <td>Set CSS classes for weekdays header</td>
            </tr>
            <tr>
                <td><code>daysGridClass()</code></td>
                <td>string $classes</td>
                <td>Set CSS classes for days grid container</td>
            </tr>
            <tr>
                <td><code>dayClass()</code></td>
                <td>string $classes</td>
                <td>Set CSS classes for individual day cells</td>
            </tr>
            <tr>
                <td><code>dayNumberClass()</code></td>
                <td>string $classes</td>
                <td>Set CSS classes for day numbers</td>
            </tr>
            <tr>
                <td><code>appointmentContainerClass()</code></td>
                <td>string $classes</td>
                <td>Set CSS classes for appointments container</td>
            </tr>
            <tr>
                <td><code>appointmentClass()</code></td>
                <td>string $classes</td>
                <td>Set CSS classes for appointment items</td>
            </tr>
            <tr>
                <td><code>setCalendarAttrs()</code></td>
                <td>array $attrs</td>
                <td>Set all calendar attributes at once</td>
            </tr>
            <tr>
                <td><code>addCalendarAttr()</code></td>
                <td>string $element, string $key, string $value</td>
                <td>Add single attribute to a calendar element</td>
            </tr>
        </tbody>
    </table>

    <h3>Click Handler Methods</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>onAppointmentClick()</code></td>
                <td>string $url</td>
                <td>Set URL for event clicks (use %id% placeholder)</td>
            </tr>
            <tr>
                <td><code>onEmptyDateClick()</code></td>
                <td>string $url</td>
                <td>Set URL for empty date clicks (use %date% placeholder)</td>
            </tr>
            <tr>
                <td><code>onDateWithAppointmentsClick()</code></td>
                <td>string $url, string $mode</td>
                <td>Set URL and mode ('fetch' or 'link') for dates with events</td>
            </tr>
        </tbody>
    </table>

    <h3>Rendering Methods</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Parameters</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>render()</code></td>
                <td>-</td>
                <td>Generate and return calendar HTML</td>
            </tr>
            <tr>
                <td><code>__toString()</code></td>
                <td>-</td>
                <td>Magic method - allows using calendar as string</td>
            </tr>
        </tbody>
    </table>

    <h2>Best Practices</h2>
    <ul>
        <li><strong>Use Auto-Detection</strong>: Call <code>setMonthYear()</code> without parameters to auto-detect from request</li>
        <li><strong>Set Calendar ID</strong>: Always provide a unique calendar ID when using multiple calendars on the same page</li>
        <li><strong>Map Fields Properly</strong>: Ensure your database has datetime fields for start_datetime and end_datetime</li>
        <li><strong>Call mapFields Before setMonthYear</strong>: <code>setMonthYear()</code> applies the month filter using the mapped date fields, so call <code>mapFields()</code> first if your columns are named differently (e.g. <code>data</code>)</li>
        <li><strong>Use Callable Mapping</strong>: For dynamic event classes or titles, use callable functions in mapFields()</li>
        <li><strong>Filter Efficiently</strong>: Add WHERE conditions before calling setMonthYear() for better performance</li>
        <li><strong>Handle AJAX</strong>: Always check for AJAX requests and return JSON for smooth navigation</li>
        <li><strong>Set Date Ranges</strong>: Limit navigation range to prevent unnecessary database queries</li>
        <li><strong>Localization</strong>: Set locale to match your application's language</li>
        <li><strong>Test Multi-day Events</strong>: Ensure your start_datetime and end_datetime fields can span multiple days</li>
    </ul>

    <h2>Common Patterns</h2>

    <h3>Calendar with User Filter</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$userId = Get::currentUserId();

$calendar = CalendarBuilder::create($eventModel)
    ->where('user_id = ? OR visibility = ?', [$userId, 'public'])
    ->setMonthYear()
    ->setHeaderTitle('My Events')
    ->render();</code></pre>

    <h3>Calendar with Category Filter</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$category = $_GET['category'] ?? null;

$calendar = CalendarBuilder::create($eventModel)
    ->setMonthYear();

if ($category) {
    $calendar->where('category = ?', [$category]);
}

echo $calendar->render();</code></pre>

    <h3>Compact Sidebar Calendar</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$calendar = CalendarBuilder::create($eventModel)
    ->setMonthYear()
    ->setCompact(true)
    ->setHighlightDaysWithAppointments(true)
    ->setShowYearMonthSelect(false)
    ->setShowTodayButton(false)
    ->setHeaderTitle('Quick View')
    ->setHeaderColor('secondary')
    ->onDateWithAppointmentsClick('?page=events&date=%date%', 'link')
    ->render();</code></pre>

</div>
