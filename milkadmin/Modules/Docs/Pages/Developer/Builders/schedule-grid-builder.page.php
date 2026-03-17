<?php
namespace Modules\Docs\Pages;
/**
 * @title ScheduleGridBuilder
 * @guide developer
 * @order 35
 * @tags ScheduleGridBuilder, schedule, grid, resources, planning, time slots, GetDataBuilder, query, database, AJAX, weekly, monthly, daily
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>ScheduleGridBuilder</h1>

    <p>The ScheduleGridBuilder provides a powerful grid system for visualizing resource schedules across time periods. It extends GetDataBuilder and creates dynamic grids where rows represent resources (rooms, teachers, equipment, etc.) and columns represent time slots (days, hours, etc.).</p>

    <h2>Key Features</h2>
    <ul>
        <li><strong>Database Integration</strong>: Automatically queries and filters events from your database</li>
        <li><strong>Multiple Period Types</strong>: Week, month, day, or custom date range views</li>
        <li><strong>Dynamic Rows</strong>: Rows are generated dynamically from your data (unlike CalendarBuilder's fixed date-based structure)</li>
        <li><strong>Time Intervals</strong>: Configurable time intervals for day view (10, 15, 30, or 60 minutes)</li>
        <li><strong>Field Mapping</strong>: Map database fields to grid properties with support for callable functions</li>
        <li><strong>Cell Grouping</strong>: Events spanning multiple time slots are automatically merged</li>
        <li><strong>AJAX Support</strong>: Built-in AJAX navigation with automatic period tracking</li>
        <li><strong>Click Handlers</strong>: Configure URLs for event clicks and empty cell clicks</li>
        <li><strong>Custom Rendering</strong>: Full control over cell appearance with custom renderers</li>
        <li><strong>Localization</strong>: Multi-language support using PHP's IntlDateFormatter</li>
    </ul>

    <h2>Quick Start</h2>

    <h3>Basic Weekly Schedule</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use Builders\ScheduleGridBuilder;

// Simple weekly resource schedule
$schedule = ScheduleGridBuilder::create($bookingModel, 'weekly_schedule')
    ->setPeriod('week')
    ->detectPeriodFromRequest()
    ->setHeaderTitle('Room Schedule')
    ->render();

echo $schedule;</code></pre>

    <h3>Complete Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($bookingModel, 'resource_schedule')
    ->setPeriod('week')
    ->detectPeriodFromRequest()
    ->mapFields([
        'row_id' => 'resource_name',
        'id' => 'booking_id',
        'start_datetime' => 'start_time',
        'end_datetime' => 'end_time',
        'label' => 'activity_name',
        'class' => function($row, $raw_row) {
            return 'booking-' . $raw_row->status;
        },
        'color' => 'booking_color'
    ])
    ->setHeaderTitle('Weekly Resource Schedule')
    ->setHeaderIcon('bi-calendar3')
    ->setHeaderColor('primary')
    ->setRowHeaderLabel('Resources')
    ->onEventClick('?page=bookings&action=edit&id=%id%')
    ->onEmptyCellClick('?page=bookings&action=new&resource=%row_id%&date=%date%')
    ->render();</code></pre>

    <h2>Live Example</h2>

    <div class="alert alert-info">
        <strong>Interactive Demo:</strong> Below is a working example of ScheduleGridBuilder with sample data. Try navigating between weeks to see the AJAX functionality in action.
    </div>

    <?php
    use Modules\Docs\ArrayDbDocsService;

    // Render the schedule grid demo with AJAX support
    echo ArrayDbDocsService::renderScheduleGrid();
    ?>

    <div class="alert alert-success mt-3">
        <strong>What you see above:</strong>
        <ul class="mb-0">
            <li>A weekly schedule grid with 5 different conference rooms</li>
            <li>20 randomly generated bookings for the current week</li>
            <li>Events spanning multiple hours are automatically grouped</li>
            <li>Full AJAX navigation - click the arrows to change weeks</li>
            <li>Data is generated using ArrayDB (no database required)</li>
        </ul>
    </div>

    <h2>Period Configuration</h2>

    <p>ScheduleGridBuilder supports four period types: week, month, day, and custom date ranges.</p>

    <h3>Weekly View</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Current week (auto-detected)
$schedule = ScheduleGridBuilder::create($model, 'weekly_grid')
    ->setPeriod('week')
    ->detectPeriodFromRequest()
    ->render();

// Specific week
$schedule = ScheduleGridBuilder::create($model, 'weekly_grid')
    ->setWeek(15, 2025)  // Week 15 of 2025
    ->render();</code></pre>

    <h3>Monthly View</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Current month (auto-detected)
$schedule = ScheduleGridBuilder::create($model, 'monthly_grid')
    ->setPeriod('month')
    ->detectPeriodFromRequest()
    ->render();

// Specific month
$schedule = ScheduleGridBuilder::create($model, 'monthly_grid')
    ->setMonth(3, 2025)  // March 2025
    ->render();</code></pre>

    <h3>Daily View with Time Intervals</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Day view with 15-minute intervals
$baseDate = new DateTime('2025-03-15');

$schedule = ScheduleGridBuilder::create($model, 'daily_grid')
    ->setDateRange(
        (clone $baseDate)->setTime(8, 0),
        (clone $baseDate)->setTime(18, 0)
    )
    ->setPeriod('day')  // MUST be after setDateRange()
    ->setTimeInterval(15)  // 15-minute intervals
    ->setColumnWidth('3rem')  // Fixed column width
    ->render();

// Different time intervals
$schedule = ScheduleGridBuilder::create($model, 'daily_grid')
    ->setDateRange($startDate, $endDate)
    ->setPeriod('day')
    ->setTimeInterval(30)  // Options: 10, 15, 30, or 60 minutes
    ->render();</code></pre>

    <h3>Custom Date Range</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$start = new DateTime('2025-03-01 08:00:00');
$end = new DateTime('2025-03-07 20:00:00');

$schedule = ScheduleGridBuilder::create($model, 'custom_grid')
    ->setDateRange($start, $end)
    ->render();</code></pre>

    <h2>Field Mapping</h2>

    <p>Map your database fields to schedule grid properties. The <code>row_id</code> field is unique to ScheduleGridBuilder and determines which row each event appears in.</p>

    <h3>Basic Field Mapping</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->mapFields([
        'row_id' => 'room_name',          // Required: determines the row
        'id' => 'booking_id',             // Event identifier
        'start_datetime' => 'start_time', // Start date/time
        'end_datetime' => 'end_time',     // End date/time
        'label' => 'event_name',          // Display text
        'class' => 'css_class',           // CSS class
        'color' => 'bg_color'             // Background color
    ])
    ->detectPeriodFromRequest()
    ->render();</code></pre>

    <h3>Advanced Mapping with Callable Functions</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->mapFields([
        'row_id' => function($row, $raw_row) {
            // Dynamic row assignment
            return $raw_row->department . ' - ' . $raw_row->room;
        },
        'id' => 'id',
        'start_datetime' => 'booking_start',
        'end_datetime' => 'booking_end',
        'label' => function($row, $raw_row) {
            // Custom label with instructor
            $name = $raw_row->course_name ?? 'Event';
            $instructor = $raw_row->instructor_name ?? '';
            return $instructor ? "$name ($instructor)" : $name;
        },
        'class' => function($row, $raw_row) {
            // Dynamic CSS based on status
            return match($raw_row->status) {
                'confirmed' => 'booking-confirmed',
                'pending' => 'booking-pending',
                'cancelled' => 'booking-cancelled',
                default => 'booking-default'
            };
        },
        'color' => function($row, $raw_row) {
            // Dynamic color based on priority
            return match($raw_row->priority) {
                'high' => '#F44336',
                'medium' => '#FF9800',
                'low' => '#4CAF50',
                default => '#2196F3'
            };
        }
    ])
    ->detectPeriodFromRequest()
    ->render();</code></pre>

    <h3>Mapping with Related Data</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// When using joins or related objects
$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->join('LEFT JOIN #__resources r ON r.id = t.resource_id')
    ->mapFields([
        'row_id' => function($row, $raw_row) {
            // Access joined data
            return $raw_row->resource->name ?? 'Unknown Resource';
        },
        'id' => 'id',
        'start_datetime' => 'start_datetime',
        'end_datetime' => 'end_datetime',
        'label' => function($row, $raw_row) {
            return $raw_row->title . ' - ' . $raw_row->resource->location;
        }
    ])
    ->detectPeriodFromRequest()
    ->render();</code></pre>

    <h2>Database Query Manipulation</h2>

    <p>ScheduleGridBuilder extends GetDataBuilder, providing full access to query manipulation methods.</p>

    <h3>Basic Query Filtering</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->where('status = ?', ['active'])
    ->where('department_id = ?', [$deptId])
    ->orderBy('start_datetime', 'asc')
    ->detectPeriodFromRequest()
    ->render();</code></pre>

    <h3>Advanced Query with Joins</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($model, 'schedule')
    // Join with resources and users
    ->join('LEFT JOIN #__resources r ON r.id = t.resource_id')
    ->join('LEFT JOIN #__users u ON u.id = t.user_id')

    // Filter by building
    ->where('r.building_id = ?', [$buildingId])

    // Only confirmed or pending bookings
    ->where('t.status IN (?, ?)', ['confirmed', 'pending'])

    // Order by resource then time
    ->orderBy('r.name', 'asc')
    ->orderBy('t.start_datetime', 'asc')

    ->detectPeriodFromRequest()
    ->render();</code></pre>

    <h2>Display Configuration</h2>

    <h3>Header Customization</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->setHeaderTitle('Conference Room Schedule')
    ->setHeaderIcon('bi-calendar3')
    ->setHeaderColor('success')  // primary, secondary, success, danger, warning, info
    ->setShowHeader(true)
    ->setShowNavigation(true)
    ->detectPeriodFromRequest()
    ->render();

// Hide header completely
$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->setShowHeader(false)
    ->detectPeriodFromRequest()
    ->render();</code></pre>

    <h3>Row Header Label</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Set label for the top-left corner cell
$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->setRowHeaderLabel('Rooms')  // or 'Teachers', 'Equipment', etc.
    ->detectPeriodFromRequest()
    ->render();</code></pre>

    <h3>Column Width Configuration</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Fixed width columns
$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->setColumnWidth('60px')  // Fixed 60px columns
    ->detectPeriodFromRequest()
    ->render();

// Flexible columns (default)
$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->setColumnWidth(null)  // Auto width (1fr)
    ->detectPeriodFromRequest()
    ->render();</code></pre>

    <h3>Navigation Limits</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Limit navigable date range
$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->setMinYear(2024)
    ->setMaxYear(2026)
    ->detectPeriodFromRequest()
    ->render();

// Or use combined method
$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->setYearRange(2024, 2026)
    ->detectPeriodFromRequest()
    ->render();</code></pre>

    <h3>Locale Settings</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Italian locale
$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->setLocale('it_IT')
    ->detectPeriodFromRequest()
    ->render();

// French locale
$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->setLocale('fr_FR')
    ->detectPeriodFromRequest()
    ->render();</code></pre>

    <h2>Styling and Appearance</h2>

    <h3>Quick Color Theme</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->gridColor('primary')  // Applies coordinated theme
    ->detectPeriodFromRequest()
    ->render();

// Available colors: primary, secondary, success, danger, warning, info, light, dark</code></pre>

    <h3>Custom Grid Attributes</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->addGridAttr('container', 'class', 'shadow-lg rounded-3')
    ->addGridAttr('grid', 'class', 'border-primary')
    ->addGridAttr('row-header', 'class', 'bg-light fw-bold')
    ->detectPeriodFromRequest()
    ->render();

// Set all attributes at once
$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->setGridAttrs([
        'container' => ['class' => 'shadow-lg'],
        'grid' => ['class' => 'custom-grid'],
        'row-header' => ['class' => 'bg-primary text-white']
    ])
    ->detectPeriodFromRequest()
    ->render();</code></pre>

    <h2>Click Handlers</h2>

    <p>Configure URLs for event clicks and empty cell clicks. Placeholders are automatically replaced with actual values.</p>

    <h3>Event Click Handler</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->onEventClick('?page=bookings&action=edit&id=%id%')
    ->detectPeriodFromRequest()
    ->render();

// Available placeholders for event clicks:
// %id% = event ID
// %row_id% = row identifier (resource name/id)
// %col_id% = column identifier (date or time)</code></pre>

    <h3>Empty Cell Click Handler</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->onEmptyCellClick('?page=bookings&action=new&resource=%row_id%&date=%date%')
    ->detectPeriodFromRequest()
    ->render();

// Available placeholders for empty cell clicks:
// %row_id% = row identifier (resource name/id)
// %col_id% = column identifier (date or time)
// %date% = date in Y-m-d format (for week/month views)
// %time% = time in H:i format (for day view)</code></pre>

    <h3>Combined Click Handlers</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->onEventClick('?page=bookings&action=view&id=%id%&resource=%row_id%')
    ->onEmptyCellClick('?page=bookings&action=create&resource=%row_id%&time=%col_id%')
    ->detectPeriodFromRequest()
    ->render();</code></pre>

    <h2>Custom Cell Renderer</h2>

    <p>For advanced customization, provide a custom function to render each cell. The renderer receives all cell information and returns custom HTML.</p>

    <h3>Custom Renderer Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($model, 'schedule')
    ->setCustomCellRenderer(function($row_id, $col_id, $event, $is_grouped, $colspan) {
        // Empty cell
        if (!$event) {
            return '&lt;div class="schedule-cell empty"&gt;&nbsp;&lt;/div&gt;';
        }

        $label = $event['label'] ?? '';
        $status = $event['class'] ?? '';
        $color = $event['color'] ?? '#ccc';

        // Status badge
        $badge = match($status) {
            'confirmed' => '&lt;span class="badge bg-success"&gt;✓&lt;/span&gt;',
            'pending' => '&lt;span class="badge bg-warning"&gt;⏳&lt;/span&gt;',
            'cancelled' => '&lt;span class="badge bg-danger"&gt;✗&lt;/span&gt;',
            default => ''
        };

        // Multi-column span
        $style = "grid-column: span {$colspan}; background-color: {$color};";
        $duration = $is_grouped ? " ({$colspan} slots)" : "";

        return &lt;&lt;&lt;HTML
        &lt;div class="schedule-cell {$status}" style="{$style}"&gt;
            &lt;div class="p-2"&gt;
                &lt;strong&gt;{$label}{$duration}&lt;/strong&gt;
                {$badge}
            &lt;/div&gt;
        &lt;/div&gt;
HTML;
    })
    ->detectPeriodFromRequest()
    ->render();</code></pre>

    <h3>Custom Renderer Parameters</h3>
    <ul>
        <li><code>$row_id</code>: Row identifier (resource name/id)</li>
        <li><code>$col_id</code>: Column identifier (date or time slot)</li>
        <li><code>$event</code>: Event data array or null for empty cells</li>
        <li><code>$is_grouped</code>: Boolean indicating if cell is part of a multi-column group</li>
        <li><code>$colspan</code>: Number of columns spanned by this cell</li>
    </ul>

    <h2>AJAX Implementation</h2>

    <p>ScheduleGridBuilder automatically handles AJAX requests for navigation.</p>

    <h3>Basic AJAX Handler</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// In your controller
#[RequestAction('home')]
public function scheduleView()
{
    $schedule = ScheduleGridBuilder::create($this->model, 'resource_schedule')
        ->detectPeriodFromRequest()
        ->setHeaderTitle('Resource Schedule')
        ->render();

    // Handle AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

        Response::json([
            'success' => true,
            'html' => $schedule
        ]);
    }

    // Full page render
    Response::render(__DIR__ . '/Views/schedule.php', [
        'schedule' => $schedule
    ]);
}</code></pre>

    <h3>AJAX with Filters</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('home')]
public function scheduleView()
{
    $gridId = 'resource_schedule';
    $department = $_GET['department'] ?? '';

    $schedule = ScheduleGridBuilder::create($this->model, $gridId)
        ->detectPeriodFromRequest();

    // Apply filter if provided
    if ($department) {
        $schedule->where('department = ?', [$department]);
    }

    $html = $schedule->render();

    // Handle AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

        Response::json([
            'success' => true,
            'html' => $html
        ]);
    }

    // Full page render
    Response::render(__DIR__ . '/Views/schedule.php', [
        'schedule' => $html
    ]);
}</code></pre>

    <h2>Complete Real-World Examples</h2>

    <h3>Example 1: Weekly Room Schedule</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class RoomScheduleController extends AbstractController
{
    #[RequestAction('home')]
    public function weeklyRoomSchedule()
    {
        $schedule = ScheduleGridBuilder::create($this->model, 'room_schedule')
            ->setPeriod('week')
            ->detectPeriodFromRequest()
            ->mapFields([
                'row_id' => 'room_name',
                'id' => 'booking_id',
                'start_datetime' => 'start_time',
                'end_datetime' => 'end_time',
                'label' => function($row, $raw_row) {
                    return $raw_row->event_name . ' - ' . $raw_row->organizer;
                },
                'class' => function($row, $raw_row) {
                    return 'booking-' . $raw_row->status;
                },
                'color' => 'event_color'
            ])
            ->setHeaderTitle('Weekly Room Schedule')
            ->setHeaderIcon('bi-door-open')
            ->setHeaderColor('primary')
            ->setRowHeaderLabel('Rooms')
            ->onEventClick('?page=bookings&action=edit&id=%id%')
            ->onEmptyCellClick('?page=bookings&action=new&room=%row_id%&date=%date%')
            ->gridColor('primary')
            ->render();

        // Handle AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            Response::json(['success' => true, 'html' => $schedule]);
        }

        Response::render(__DIR__ . '/Views/schedule.php', [
            'schedule' => $schedule
        ]);
    }
}</code></pre>

    <h3>Example 2: Daily Equipment Schedule with Time Slots</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class EquipmentScheduleController extends AbstractController
{
    #[RequestAction('home')]
    public function dailyEquipmentSchedule()
    {
        $date = $_GET['date'] ?? date('Y-m-d');
        $baseDate = new DateTime($date);

        $schedule = ScheduleGridBuilder::create($this->model, 'equipment_schedule')
            ->setDateRange(
                (clone $baseDate)->setTime(6, 0),
                (clone $baseDate)->setTime(22, 0)
            )
            ->setPeriod('day')  // Must be after setDateRange
            ->setTimeInterval(30)  // 30-minute slots
            ->setColumnWidth('4rem')
            ->mapFields([
                'row_id' => function($row, $raw_row) {
                    return $raw_row->equipment_type . ' - ' . $raw_row->equipment_id;
                },
                'id' => 'reservation_id',
                'start_datetime' => 'checkout_time',
                'end_datetime' => 'return_time',
                'label' => function($row, $raw_row) {
                    return $raw_row->project_name . '\n' . $raw_row->user_name;
                },
                'class' => function($row, $raw_row) {
                    return $raw_row->is_overdue ? 'reservation-overdue' : 'reservation-active';
                },
                'color' => function($row, $raw_row) {
                    return $raw_row->is_overdue ? '#F44336' : '#4CAF50';
                }
            ])
            ->setHeaderTitle('Equipment Schedule - ' . $baseDate->format('F j, Y'))
            ->setHeaderIcon('bi-tools')
            ->setHeaderColor('info')
            ->setRowHeaderLabel('Equipment')
            ->setShowNavigation(true)
            ->onEventClick('?page=reservations&action=view&id=%id%')
            ->onEmptyCellClick('?page=reservations&action=new&equipment=%row_id%&time=%time%')
            ->render();

        // Handle AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            Response::json(['success' => true, 'html' => $schedule]);
        }

        Response::render(__DIR__ . '/Views/schedule.php', [
            'schedule' => $schedule
        ]);
    }
}</code></pre>

    <h3>Example 3: Monthly Staff Schedule</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class StaffScheduleController extends AbstractController
{
    #[RequestAction('home')]
    public function monthlyStaffSchedule()
    {
        $schedule = ScheduleGridBuilder::create($this->model, 'staff_schedule')
            ->setPeriod('month')
            ->detectPeriodFromRequest()
            ->join('LEFT JOIN #__departments d ON d.id = t.department_id')
            ->mapFields([
                'row_id' => function($row, $raw_row) {
                    return $raw_row->department->name . ' - ' . $raw_row->staff_name;
                },
                'id' => 'shift_id',
                'start_datetime' => 'shift_start',
                'end_datetime' => 'shift_end',
                'label' => function($row, $raw_row) {
                    $shift = $raw_row->shift_type; // morning, afternoon, night
                    $location = $raw_row->location;
                    return "$shift - $location";
                },
                'class' => function($row, $raw_row) {
                    return match($raw_row->shift_type) {
                        'morning' => 'shift-morning',
                        'afternoon' => 'shift-afternoon',
                        'night' => 'shift-night',
                        default => 'shift-default'
                    };
                },
                'color' => function($row, $raw_row) {
                    return match($raw_raw->shift_type) {
                        'morning' => '#FFC107',
                        'afternoon' => '#2196F3',
                        'night' => '#673AB7',
                        default => '#9E9E9E'
                    };
                }
            ])
            ->setHeaderTitle('Monthly Staff Schedule')
            ->setHeaderIcon('bi-people')
            ->setHeaderColor('success')
            ->setRowHeaderLabel('Staff')
            ->setYearRange(2024, 2026)
            ->onEventClick('?page=shifts&action=edit&id=%id%')
            ->onEmptyCellClick('?page=shifts&action=assign&staff=%row_id%&date=%date%')
            ->gridColor('success')
            ->render();

        // Handle AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            Response::json(['success' => true, 'html' => $schedule]);
        }

        Response::render(__DIR__ . '/Views/schedule.php', [
            'schedule' => $schedule
        ]);
    }
}</code></pre>

    <h2>Method Reference</h2>

    <h3>Period Configuration Methods</h3>
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
                <td><code>setPeriod()</code></td>
                <td>string $type</td>
                <td>Set period type: 'week', 'month', 'day', or 'custom'</td>
            </tr>
            <tr>
                <td><code>setWeek()</code></td>
                <td>int $week, int $year</td>
                <td>Set specific week (1-53) and year for weekly view</td>
            </tr>
            <tr>
                <td><code>setMonth()</code></td>
                <td>int $month, int $year</td>
                <td>Set specific month (1-12) and year for monthly view</td>
            </tr>
            <tr>
                <td><code>setDateRange()</code></td>
                <td>DateTime $start, DateTime $end</td>
                <td>Set custom date range (used for day view and custom periods)</td>
            </tr>
            <tr>
                <td><code>detectPeriodFromRequest()</code></td>
                <td>-</td>
                <td>Auto-detect period from REQUEST parameters based on grid_id</td>
            </tr>
        </tbody>
    </table>

    <h3>Field Mapping Methods</h3>
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
                <td><code>mapFields()</code></td>
                <td>array $mappings</td>
                <td>Map database fields to grid properties. Supports string field names or callable functions.<br>
                    Required keys: 'row_id', 'id', 'start_datetime', 'end_datetime'<br>
                    Optional keys: 'label', 'class', 'color'</td>
            </tr>
        </tbody>
    </table>

    <h3>Display Configuration Methods</h3>
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
                <td><code>setLocale()</code></td>
                <td>string $locale</td>
                <td>Set locale for date formatting (e.g., 'en_US', 'it_IT', 'fr_FR')</td>
            </tr>
            <tr>
                <td><code>setHeaderTitle()</code></td>
                <td>string $title</td>
                <td>Set header title text</td>
            </tr>
            <tr>
                <td><code>setHeaderIcon()</code></td>
                <td>string $icon</td>
                <td>Set header icon (Bootstrap Icons class, e.g., 'bi-calendar3')</td>
            </tr>
            <tr>
                <td><code>setHeaderColor()</code></td>
                <td>string $color</td>
                <td>Set header color: 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'</td>
            </tr>
            <tr>
                <td><code>setShowHeader()</code></td>
                <td>bool $show</td>
                <td>Show or hide the grid header</td>
            </tr>
            <tr>
                <td><code>setShowNavigation()</code></td>
                <td>bool $show</td>
                <td>Show or hide navigation controls</td>
            </tr>
            <tr>
                <td><code>setMinYear()</code></td>
                <td>int $year</td>
                <td>Set minimum navigable year</td>
            </tr>
            <tr>
                <td><code>setMaxYear()</code></td>
                <td>int $year</td>
                <td>Set maximum navigable year</td>
            </tr>
            <tr>
                <td><code>setYearRange()</code></td>
                <td>int $minYear, int $maxYear</td>
                <td>Set both minimum and maximum navigable years</td>
            </tr>
            <tr>
                <td><code>setTimeInterval()</code></td>
                <td>int $minutes</td>
                <td>Set time interval for day view columns (10, 15, 30, or 60 minutes)</td>
            </tr>
            <tr>
                <td><code>setRowHeaderLabel()</code></td>
                <td>string $label</td>
                <td>Set label for row header column (top-left corner cell)</td>
            </tr>
            <tr>
                <td><code>setColumnWidth()</code></td>
                <td>?string $width</td>
                <td>Set column width (CSS value: '60px', '5rem', etc. or null for auto 1fr)</td>
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
                <td><code>gridColor()</code></td>
                <td>string $color</td>
                <td>Apply coordinated color theme to grid (primary, secondary, success, danger, warning, info, light, dark)</td>
            </tr>
            <tr>
                <td><code>setGridAttrs()</code></td>
                <td>array $attrs</td>
                <td>Set all grid attributes at once</td>
            </tr>
            <tr>
                <td><code>addGridAttr()</code></td>
                <td>string $element, string $key, string $value</td>
                <td>Add single attribute to a grid element (container, grid, row-header, etc.)</td>
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
                <td><code>onEventClick()</code></td>
                <td>string $url</td>
                <td>Set URL template for event clicks<br>
                    Placeholders: %id%, %row_id%, %col_id%</td>
            </tr>
            <tr>
                <td><code>onEmptyCellClick()</code></td>
                <td>string $url</td>
                <td>Set URL template for empty cell clicks<br>
                    Placeholders: %row_id%, %col_id%, %date%, %time%</td>
            </tr>
        </tbody>
    </table>

    <h3>Custom Rendering Methods</h3>
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
                <td><code>setCustomCellRenderer()</code></td>
                <td>callable $renderer</td>
                <td>Set custom function to render cells<br>
                    Function receives: ($row_id, $col_id, $event, $is_grouped, $colspan)<br>
                    Returns: HTML string</td>
            </tr>
        </tbody>
    </table>

    <h3>Grid Identification Methods</h3>
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
                <td><code>setGridId()</code></td>
                <td>string $grid_id</td>
                <td>Set grid ID for AJAX requests and HTML element identification</td>
            </tr>
            <tr>
                <td><code>getId()</code></td>
                <td>-</td>
                <td>Get grid ID (auto-generates if not set)</td>
            </tr>
        </tbody>
    </table>

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
                <td>Set query LIMIT (note: grid removes limit automatically)</td>
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
                <td>Generate and return grid HTML</td>
            </tr>
            <tr>
                <td><code>__toString()</code></td>
                <td>-</td>
                <td>Magic method - allows using grid as string</td>
            </tr>
        </tbody>
    </table>

    <h2>Best Practices</h2>
    <ul>
        <li><strong>Set Period After DateRange</strong>: When using day view, call <code>setPeriod('day')</code> AFTER <code>setDateRange()</code> to override the 'custom' period type</li>
        <li><strong>Use Auto-Detection</strong>: Call <code>detectPeriodFromRequest()</code> to automatically handle period navigation from request parameters</li>
        <li><strong>Set Grid ID</strong>: Always provide a unique grid ID when creating the builder (second parameter of create())</li>
        <li><strong>Map row_id Correctly</strong>: The 'row_id' field determines which row each event appears in - ensure it returns consistent values</li>
        <li><strong>Use Callable Mapping</strong>: For dynamic values or complex logic, use callable functions in mapFields()</li>
        <li><strong>Filter Efficiently</strong>: Add WHERE conditions before calling render() for better performance</li>
        <li><strong>Handle AJAX</strong>: Always check for AJAX requests and return JSON for smooth navigation</li>
        <li><strong>Set Time Intervals</strong>: For day view, choose appropriate time intervals based on your needs (10, 15, 30, or 60 minutes)</li>
        <li><strong>Test Multi-day Events</strong>: Ensure your events can span multiple columns/days and are rendered correctly</li>
        <li><strong>Localization</strong>: Set locale to match your application's language for proper date formatting</li>
    </ul>

    <h2>Differences from CalendarBuilder</h2>
    <ul>
        <li><strong>Dynamic Rows</strong>: ScheduleGridBuilder generates rows from data (resources), while CalendarBuilder has fixed date-based structure</li>
        <li><strong>row_id Field</strong>: ScheduleGridBuilder requires a 'row_id' mapping to determine which row each event belongs to</li>
        <li><strong>Time Intervals</strong>: Day view supports configurable time intervals (10, 15, 30, 60 minutes)</li>
        <li><strong>Cell Grouping</strong>: Events spanning multiple time slots are automatically merged into grouped cells</li>
        <li><strong>Pattern</strong>: Same base pattern as CalendarBuilder but with resource-based rows instead of fixed dates</li>
    </ul>

    <h2>Common Use Cases</h2>

    <h3>Room Booking System</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($bookingModel, 'room_schedule')
    ->setPeriod('week')
    ->detectPeriodFromRequest()
    ->where('building_id = ?', [$buildingId])
    ->mapFields([
        'row_id' => 'room_name',
        'id' => 'booking_id',
        'start_datetime' => 'start_time',
        'end_datetime' => 'end_time',
        'label' => 'event_name'
    ])
    ->setHeaderTitle('Room Bookings')
    ->setRowHeaderLabel('Rooms')
    ->render();</code></pre>

    <h3>Teacher/Instructor Schedule</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schedule = ScheduleGridBuilder::create($lessonModel, 'teacher_schedule')
    ->setPeriod('week')
    ->detectPeriodFromRequest()
    ->mapFields([
        'row_id' => 'teacher_name',
        'id' => 'lesson_id',
        'start_datetime' => 'lesson_start',
        'end_datetime' => 'lesson_end',
        'label' => 'course_name'
    ])
    ->setHeaderTitle('Teacher Schedule')
    ->setRowHeaderLabel('Teachers')
    ->render();</code></pre>

    <h3>Equipment Reservation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$today = new DateTime();
$schedule = ScheduleGridBuilder::create($reservationModel, 'equipment_schedule')
    ->setDateRange(
        (clone $today)->setTime(8, 0),
        (clone $today)->setTime(18, 0)
    )
    ->setPeriod('day')
    ->setTimeInterval(30)
    ->mapFields([
        'row_id' => 'equipment_name',
        'id' => 'reservation_id',
        'start_datetime' => 'checkout_time',
        'end_datetime' => 'return_time',
        'label' => 'project_name'
    ])
    ->setHeaderTitle('Equipment Reservations')
    ->setRowHeaderLabel('Equipment')
    ->render();</code></pre>

</div>
