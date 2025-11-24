<?php
namespace Modules\Docs\Pages;
use App\Get;
/**
 * @title Calendar
 * @guide framework
 * @order 105
 * @tags calendar, events, appointments, scheduling, multi-day-events, AJAX, localization, date-picker, monthly-view, theme-plugin
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Calendar Plugin</h1>

    <p>The Calendar plugin provides a flexible, AJAX-enabled monthly calendar with support for multi-day events, localization, and extensive customization options.</p>

    <h2>Basic Usage</h2>

    <h3>Simple Calendar with Events</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Define events
$events = [
    (object)[
        'id' => 1,
        'title' => 'Team Meeting',
        'start_datetime' => new \DateTime('2025-11-22 10:00:00'),
        'end_datetime' => new \DateTime('2025-11-22 11:30:00'),
        'class' => 'event-primary'
    ],
    (object)[
        'id' => 2,
        'title' => 'Client Call',
        'start_datetime' => new \DateTime('2025-11-25 14:00:00'),
        'end_datetime' => new \DateTime('2025-11-25 15:00:00'),
        'class' => 'event-info'
    ]
];

// Generate calendar
$calendar_html = Get::themePlugin('Calendar', [
    'month' => date('n'),
    'year' => date('Y'),
    'locale' => 'it_IT',
    'calendar_id' => 'my_calendar',
    'events' => $events
]);

echo $calendar_html;
    </code></pre>

    <h2>Event Structure</h2>
    <p>Events must be objects with the following properties:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
(object)[
    'id' => 1,                                                // Unique event ID
    'title' => 'Event Title',                                 // Event title
    'start_datetime' => new \DateTime('2025-11-22 10:00:00'), // Start date and time
    'end_datetime' => new \DateTime('2025-11-22 11:30:00'),   // End date and time
    'class' => 'event-primary'                                // CSS class for styling
]
    </code></pre>

    <p><strong>Available event classes:</strong> <code>event-primary</code>, <code>event-secondary</code>, <code>event-success</code>, <code>event-danger</code>, <code>event-warning</code>, <code>event-info</code>, <code>event-light</code>, <code>event-dark</code></p>

    <h2>Configuration Options</h2>

    <h3>Basic Options</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-light">
            <tr>
                <th style="width: 25%;">Option</th>
                <th style="width: 15%;">Type</th>
                <th style="width: 15%;">Default</th>
                <th style="width: 45%;">Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>month</code></td>
                <td>int</td>
                <td>Current month</td>
                <td>Month to display (1-12)</td>
            </tr>
            <tr>
                <td><code>year</code></td>
                <td>int</td>
                <td>Current year</td>
                <td>Year to display</td>
            </tr>
            <tr>
                <td><code>locale</code></td>
                <td>string</td>
                <td>'en_US'</td>
                <td>Locale for date formatting (e.g., 'it_IT', 'fr_FR')</td>
            </tr>
            <tr>
                <td><code>calendar_id</code></td>
                <td>string</td>
                <td>'calendar'</td>
                <td>Unique ID for the calendar (required for AJAX)</td>
            </tr>
            <tr>
                <td><code>events</code></td>
                <td>array</td>
                <td>[]</td>
                <td>Array of event objects</td>
            </tr>
        </tbody>
    </table>

    <h3>Header Customization</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-light">
            <tr>
                <th style="width: 25%;">Option</th>
                <th style="width: 15%;">Type</th>
                <th style="width: 15%;">Default</th>
                <th style="width: 45%;">Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>header_title</code></td>
                <td>string</td>
                <td>'Calendar'</td>
                <td>Title displayed in header</td>
            </tr>
            <tr>
                <td><code>header_icon</code></td>
                <td>string</td>
                <td>''</td>
                <td>Bootstrap Icons class (e.g., 'bi-calendar-event')</td>
            </tr>
            <tr>
                <td><code>header_color</code></td>
                <td>string</td>
                <td>'primary'</td>
                <td>Header color: primary, secondary, success, danger, warning, info, light, dark</td>
            </tr>
            <tr>
                <td><code>show_header</code></td>
                <td>bool</td>
                <td>true</td>
                <td>Show/hide header completely</td>
            </tr>
        </tbody>
    </table>

    <h3>Navigation Controls</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-light">
            <tr>
                <th style="width: 25%;">Option</th>
                <th style="width: 15%;">Type</th>
                <th style="width: 15%;">Default</th>
                <th style="width: 45%;">Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>show_year_month_select</code></td>
                <td>bool</td>
                <td>true</td>
                <td>Show year/month dropdown selectors</td>
            </tr>
            <tr>
                <td><code>show_prev_next_buttons</code></td>
                <td>bool</td>
                <td>true</td>
                <td>Show previous/next month buttons</td>
            </tr>
            <tr>
                <td><code>show_today_button</code></td>
                <td>bool</td>
                <td>true</td>
                <td>Show "today" button</td>
            </tr>
            <tr>
                <td><code>min_year</code></td>
                <td>int|null</td>
                <td>null</td>
                <td>Minimum year for navigation</td>
            </tr>
            <tr>
                <td><code>max_year</code></td>
                <td>int|null</td>
                <td>null</td>
                <td>Maximum year for navigation</td>
            </tr>
            <tr>
                <td><code>min_month</code></td>
                <td>int|null</td>
                <td>null</td>
                <td>Minimum month for navigation (1-12)</td>
            </tr>
            <tr>
                <td><code>max_month</code></td>
                <td>int|null</td>
                <td>null</td>
                <td>Maximum month for navigation (1-12)</td>
            </tr>
        </tbody>
    </table>

    <h3>Display Options</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-light">
            <tr>
                <th style="width: 25%;">Option</th>
                <th style="width: 15%;">Type</th>
                <th style="width: 15%;">Default</th>
                <th style="width: 45%;">Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>compact</code></td>
                <td>bool</td>
                <td>false</td>
                <td>Enable compact mode (smaller calendar, no event details shown)</td>
            </tr>
            <tr>
                <td><code>highlight_days_with_appointments</code></td>
                <td>bool</td>
                <td>false</td>
                <td>Add special styling to days with events</td>
            </tr>
        </tbody>
    </table>

    <h3>Click Handlers</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-light">
            <tr>
                <th style="width: 30%;">Option</th>
                <th style="width: 15%;">Type</th>
                <th style="width: 15%;">Default</th>
                <th style="width: 40%;">Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>on_appointment_click_url</code></td>
                <td>string</td>
                <td>''</td>
                <td>URL for event clicks. Use <code>%id%</code> placeholder</td>
            </tr>
            <tr>
                <td><code>on_empty_date_click_url</code></td>
                <td>string</td>
                <td>''</td>
                <td>URL for empty date clicks. Use <code>%date%</code> placeholder</td>
            </tr>
            <tr>
                <td><code>on_date_with_appointments_click_url</code></td>
                <td>string</td>
                <td>''</td>
                <td>URL for dates with events. Use <code>%date%</code> placeholder</td>
            </tr>
            <tr>
                <td><code>on_date_with_appointments_click_mode</code></td>
                <td>string</td>
                <td>'fetch'</td>
                <td>Click mode: 'fetch' (AJAX) or 'link' (navigation)</td>
            </tr>
        </tbody>
    </table>

    <h3>Advanced Customization</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-light">
            <tr>
                <th style="width: 30%;">Option</th>
                <th style="width: 15%;">Type</th>
                <th style="width: 15%;">Default</th>
                <th style="width: 40%;">Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>custom_cell_renderer</code></td>
                <td>callable</td>
                <td>null</td>
                <td>Custom function to render day cells</td>
            </tr>
        </tbody>
    </table>

    <h2>Examples</h2>

    <h3>Compact Calendar</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$calendar_html = Get::themePlugin('Calendar', [
    'month' => date('n'),
    'year' => date('Y'),
    'locale' => 'it_IT',
    'calendar_id' => 'compact_calendar',
    'events' => $events,
    'header_title' => 'My Calendar',
    'header_color' => 'success',
    'compact' => true,
    'highlight_days_with_appointments' => true,
    'show_year_month_select' => false
]);
    </code></pre>

    <h3>Calendar Without Header</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$calendar_html = Get::themePlugin('Calendar', [
    'month' => date('n'),
    'year' => date('Y'),
    'locale' => 'it_IT',
    'calendar_id' => 'no_header_cal',
    'events' => $events,
    'show_header' => false,
    'compact' => true
]);
    </code></pre>

    <h3>Calendar with Click Handlers</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$calendar_html = Get::themePlugin('Calendar', [
    'month' => date('n'),
    'year' => date('Y'),
    'locale' => 'it_IT',
    'calendar_id' => 'interactive_cal',
    'events' => $events,
    'on_appointment_click_url' => '?page=events&action=view&id=%id%',
    'on_empty_date_click_url' => '?page=events&action=new&date=%date%'
]);
    </code></pre>

    <h3>Calendar with Date Range Limits</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$calendar_html = Get::themePlugin('Calendar', [
    'month' => date('n'),
    'year' => date('Y'),
    'locale' => 'it_IT',
    'calendar_id' => 'limited_cal',
    'events' => $events,
    'min_year' => 2024,
    'max_year' => 2026
]);
    </code></pre>

    <h3>Custom Cell Renderer</h3>
    <p>For advanced customization, you can provide a custom function to render each day cell:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
function customCellRenderer(
    int $day,
    int $month,
    int $year,
    bool $otherMonth,
    string $date,
    bool $isToday,
    array $appointments,
    $calendar
): string {
    $classes = ['calendar-day', 'custom-cell'];

    if ($otherMonth) $classes[] = 'other-month';
    if ($isToday) $classes[] = 'today';

    $html = '&lt;div class="' . implode(' ', $classes) . '"&gt;';
    $html .= '&lt;div class="day-number"&gt;' . $day . '&lt;/div&gt;';

    if (!empty($appointments)) {
        $count = count($appointments);
        $html .= '&lt;span class="badge bg-primary"&gt;' . $count . '&lt;/span&gt;';
    }

    $html .= '&lt;/div&gt;';
    return $html;
}

$calendar_html = Get::themePlugin('Calendar', [
    'month' => date('n'),
    'year' => date('Y'),
    'calendar_id' => 'custom_cal',
    'events' => $events,
    'custom_cell_renderer' => 'customCellRenderer'
]);
    </code></pre>

    <h2>AJAX Implementation</h2>
    <p>To enable AJAX calendar updates, handle requests in your controller:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
#[RequestAction('home')]
public function calendar()
{
    $events = $this->getEvents();
    $calendar_id = 'my_calendar';

    // Check for AJAX request
    if (isset($_REQUEST[$calendar_id])) {
        $html = $this->buildCalendarHtml($calendar_id, $events);
        Response::render($html, ['html' => $html]);
        return;
    }

    // Full page render
    $html = $this->buildCalendarHtml($calendar_id, $events);
    Response::render($html, ['html' => $html]);
}

private function buildCalendarHtml(string $id, array $events): string
{
    $req = $_REQUEST[$id] ?? [];
    $month = (int)($req['month'] ?? date('n'));
    $year = (int)($req['year'] ?? date('Y'));

    return Get::themePlugin('Calendar', [
        'month' => $month,
        'year' => $year,
        'locale' => 'it_IT',
        'calendar_id' => $id,
        'events' => $events
    ]);
}
    </code></pre>

    <h2>JavaScript Events</h2>
    <p>The calendar dispatches custom events that you can listen to:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">
// Listen to appointment clicks
document.addEventListener('calendar:appointmentClick', function(e) {
    console.log('Appointment clicked:', e.detail);
    // Prevent default behavior if needed
    // e.preventDefault();
});

// Listen to "more appointments" clicks
document.addEventListener('calendar:moreClick', function(e) {
    console.log('More clicked for date:', e.detail.date);
});
    </code></pre>

    <h2>Multi-day Events</h2>
    <p>The calendar automatically supports events spanning multiple days:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$events = [
    (object)[
        'id' => 1,
        'title' => 'Conference',
        'start_datetime' => new \DateTime('2025-11-20 09:00:00'),
        'end_datetime' => new \DateTime('2025-11-22 17:00:00'),
        'class' => 'event-warning'
    ]
];
    </code></pre>
    <p>The event will appear on all days from November 20 to November 22, with appropriate time ranges shown for each day.</p>

    <h2>CalendarBuilder - Database Integration</h2>
    <p>For database-driven calendars, use the <code>CalendarBuilder</code> class which provides automatic query filtering and event mapping:</p>

    <h3>Basic CalendarBuilder Usage</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
use Builders\CalendarBuilder;

// Assuming you have an Event model
$calendar = CalendarBuilder::create($eventModel)
    ->setMonthYear()  // Auto-detects from request or uses current month/year
    ->setLocale('it_IT')
    ->setHeaderTitle('My Events')
    ->setHeaderIcon('bi-calendar-event')
    ->setHeaderColor('primary')
    ->render();

echo $calendar;
    </code></pre>

    <h3>Field Mapping</h3>
    <p>Map your database fields to calendar properties:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$calendar = CalendarBuilder::create($eventModel)
    ->mapFields([
        'id' => 'event_id',
        'title' => 'event_name',
        'start_datetime' => 'start_date',
        'end_datetime' => 'end_date',
        'class' => 'css_class'
    ])
    ->setMonthYear()
    ->render();
    </code></pre>

    <h3>Complete CalendarBuilder Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$calendar = CalendarBuilder::create($eventModel, 'events_calendar')
    ->setMonthYear(11, 2025)
    ->setLocale('it_IT')
    ->setHeaderTitle('Team Events')
    ->setHeaderIcon('bi-calendar-check')
    ->setHeaderColor('success')
    ->setCompact(false)
    ->setHighlightDaysWithAppointments(true)
    ->setShowYearMonthSelect(true)
    ->setShowPrevNextButtons(true)
    ->setShowTodayButton(true)
    ->onAppointmentClick('?page=events&action=edit&id=%id%')
    ->onEmptyDateClick('?page=events&action=new&date=%date%')
    ->onDateWithAppointmentsClick('?page=events&action=list&date=%date%', 'fetch')
    ->setDateRange(2024, 2026)
    ->render();
    </code></pre>

    <h3>CalendarBuilder with Custom Cell Renderer</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$calendar = CalendarBuilder::create($eventModel)
    ->setMonthYear()
    ->setCustomCellRenderer(function($day, $month, $year, $otherMonth, $date, $isToday, $appointments, $cal) {
        $html = '&lt;div class="custom-day"&gt;';
        $html .= '&lt;div class="day-num"&gt;' . $day . '&lt;/div&gt;';

        if (!empty($appointments)) {
            $html .= '&lt;span class="badge"&gt;' . count($appointments) . '&lt;/span&gt;';
        }

        $html .= '&lt;/div&gt;';
        return $html;
    })
    ->render();
    </code></pre>

    <h3>CalendarBuilder Available Methods</h3>
    <ul class="list-unstyled">
        <li><code>setMonth(int $month)</code> - Set display month (1-12)</li>
        <li><code>setYear(int $year)</code> - Set display year</li>
        <li><code>setMonthYear(?int $month = null, ?int $year = null)</code> - Set both (auto-detects from request if null)</li>
        <li><code>setLocale(string $locale)</code> - Set locale for formatting</li>
        <li><code>setHeaderTitle(string $title)</code> - Set header title</li>
        <li><code>setHeaderIcon(string $icon)</code> - Set header icon (Bootstrap Icons)</li>
        <li><code>setHeaderColor(string $color)</code> - Set header color</li>
        <li><code>setCompact(bool $compact)</code> - Enable/disable compact mode</li>
        <li><code>setHighlightDaysWithAppointments(bool $highlight)</code> - Highlight days with events</li>
        <li><code>setShowHeader(bool $show)</code> - Show/hide header</li>
        <li><code>setShowYearMonthSelect(bool $show)</code> - Show/hide year/month selectors</li>
        <li><code>setShowPrevNextButtons(bool $show)</code> - Show/hide navigation buttons</li>
        <li><code>setShowTodayButton(bool $show)</code> - Show/hide today button</li>
        <li><code>onAppointmentClick(string $url)</code> - Set appointment click URL</li>
        <li><code>onEmptyDateClick(string $url)</code> - Set empty date click URL</li>
        <li><code>onDateWithAppointmentsClick(string $url, string $mode = 'fetch')</code> - Set date with appointments click handler</li>
        <li><code>setMinDate(int $year, ?int $month = null)</code> - Set minimum navigable date</li>
        <li><code>setMaxDate(int $year, ?int $month = null)</code> - Set maximum navigable date</li>
        <li><code>setDateRange(int $minYear, int $maxYear, ?int $minMonth = null, ?int $maxMonth = null)</code> - Set date range</li>
        <li><code>setCustomCellRenderer(callable $renderer)</code> - Set custom cell renderer</li>
        <li><code>mapFields(array $mappings)</code> - Map database fields to event properties</li>
    </ul>
</div>
