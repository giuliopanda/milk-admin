# CalendarBuilder - Calendars

CalendarBuilder creates monthly or weekly calendar views with events. Perfect for appointments, schedules, and time-based data.

## Basic Usage

```php
#[RequestAction('home')]
public function calendarPage()
{
    $response = $this->getCommonData();
    $calendar_id = 'calendar_events';

    $calendarBuilder = CalendarBuilder::create($this->model, $calendar_id)
        ->setCalendarId($calendar_id)
        ->setMonthYear()  // Auto-detect from request or use current
        ->setHeaderTitle('Events Calendar')
        ->setHeaderIcon('bi-calendar-event')
        ->onAppointmentClick('?page=events&action=edit&id=%id%')
        ->onEmptyDateClick('?page=events&action=edit&date=%date%')
        ->setHeaderColor('primary');

    $html = $calendarBuilder->render();

    // For AJAX requests (month/year navigation)
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        Response::json([
            'success' => true,
            'html' => $html
        ]);
    }

    $response['calendar'] = $calendarBuilder;
    $response['html'] = $html;
    Response::render(__DIR__ . '/Views/calendar_page.php', $response);
}
```

**View (calendar_page.php):**
```php
<div class="card">
    <div class="card-body">
        <?php ph($html); ?>
    </div>
</div>
```

## Calendar-Specific Methods

### Field Mapping

```php
->mapFields([
    'id' => 'event_id',                    // Primary key field
    'title' => 'event_name',               // Event title field
    'start_datetime' => 'start_date',      // Start datetime field
    'end_datetime' => 'end_date',          // End datetime field
    'class' => 'css_class'                 // CSS class field
])

// Using callable for dynamic values
->mapFields([
    'class' => function($event, $event_raw) {
        return $event_raw->status === 'confirmed' ? 'event-success' : 'event-warning';
    }
])
```

### View Type

```php
->setViewType('monthly')      // Monthly or weekly view
->useMonthlyView()            // Shortcut for monthly
->useWeeklyView()             // Shortcut for weekly
```

### Weekly View Settings

```php
->setWeeklyHours(8, 20)                    // Show hours 8:00-20:00
->setWeeklyHourHeight(60)                  // Height in pixels per hour
->setWeeklyViewSettings(8, 20, 60)         // All in one call
->setWeekNumber(2)                         // Set specific week number
```

### Date Configuration

```php
->setMonth(11)                             // Set month (1-12)
->setYear(2025)                            // Set year
->setMonthYear(11, 2025)                   // Set both
->setMonthYear()                           // Auto-detect from request
```

### Date Range Limits

```php
->setMinDate(2020, 6)                      // Minimum date (June 2020)
->setMaxDate(2030, 12)                     // Maximum date (December 2030)
->setDateRange(2020, 2030, 6, 11)          // Min and max at once
```

### Header Configuration

```php
->setHeaderTitle('My Calendar')            // Header title
->setHeaderIcon('bi-calendar-event')       // Bootstrap icon
->setHeaderColor('primary')                // Header color theme
->setShowHeader(true)                      // Show/hide header
->setShowYearMonthSelect(true)             // Show/hide dropdowns
->setShowPrevNextButtons(true)             // Show/hide nav buttons
->setShowTodayButton(true)                 // Show/hide today button
```

### Click Handlers

```php
// When clicking an appointment
->onAppointmentClick('?page=events&action=edit&id=%id%')

// When clicking empty date
->onEmptyDateClick('?page=events&action=new&date=%date%')

// When clicking date with appointments
->onDateWithAppointmentsClick('?page=events&action=list&date=%date%', 'fetch')
// Mode: 'fetch' (AJAX) or 'link' (navigation)
```

### Display Options

```php
->setCompact(true)                         // Compact mode (no event details)
->setHighlightDaysWithAppointments(true)   // Highlight days with events
```

### Locale

```php
->setLocale('it_IT')                       // Set locale for day/month names
```

### Calendar Styling

```php
->calendarColor('primary')                 // Apply color theme
->containerClass('shadow-lg')              // Container wrapper
->gridClass('border-0')                    // Calendar grid
->weekdaysClass('bg-light')                // Weekday header
->daysGridClass('gap-2')                   // Days container
->dayClass('border rounded')               // Individual day cells
->dayNumberClass('fw-bold')                // Day numbers
->appointmentContainerClass('mt-2')        // Appointments container
->appointmentClass('rounded-pill')         // Individual appointments
```

### Calendar Attributes

```php
->setCalendarAttrs([                       // Set multiple attributes
    'container' => ['class' => 'shadow'],
    'day' => ['class' => 'hover-bg-light']
])

->addCalendarAttr('grid', 'data-foo', 'bar')  // Add single attribute
```

### Custom Cell Renderer

```php
->setCustomCellRenderer(function($day, $month, $year, $otherMonth, $date, $isToday, $appointments, $calendar) {
    $html = '<div class="custom-day">';
    $html .= '<span class="day-number">' . $day . '</span>';

    if (!empty($appointments)) {
        $html .= '<span class="badge">' . count($appointments) . '</span>';
    }

    $html .= '</div>';
    return $html;
})
```

## Complete Example

See `milkadmin/Modules/Events/EventsController.php`:

```php
$calendar_id = 'calendar_events';
$calendar_params = $_REQUEST[$calendar_id] ?? [];
$month = (int)($calendar_params['month'] ?? date('n'));
$year = (int)($calendar_params['year'] ?? date('Y'));

$calendar = CalendarBuilder::create($this->model, $calendar_id)
    ->mapFields([
        'id' => 'id',
        'title' => 'title',
        'start_datetime' => 'start_datetime',
        'end_datetime' => 'end_datetime',
        'class' => function($event, $event_raw) {
            return $event_raw->event_class ?? 'event-primary';
        }
    ])
    ->setCalendarId($calendar_id)
    ->setMonthYear($month, $year)
    ->setHeaderTitle('Events Calendar')
    ->setHeaderIcon('bi-calendar-event')
    ->onAppointmentClick('?page=events&action=edit&id=%id%')
    ->onEmptyDateClick('?page=events&action=edit&date=%date%')
    ->setHeaderColor('primary')
    ->setDateRange(2020, 2030);  // Optional: limit date range

$html = $calendar->render();

// Handle AJAX requests for month navigation
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    Response::json([
        'success' => true,
        'html' => $html
    ]);
}

$response = ['calendar' => $calendar, 'html' => $html, ...$this->getCommonData()];
Response::render(__DIR__ . '/Views/calendar_page.php', $response);
```

## AJAX Behavior

Calendar navigation (month/year change) works via AJAX:
- Month/year selectors trigger AJAX request
- Server returns new HTML via `Response::json(['html' => ...])`
- Calendar updates without page reload

**For calendar reloads after actions:**

```php
// In save/delete action
Response::json([
    'success' => true,
    'message' => 'Event saved',
    'calendar' => [
        'id' => 'calendar_events',
        'action' => 'reload'
    ],
    'modal' => ['action' => 'hide']
]);
```

## Weekly View Example

```php
$calendar = CalendarBuilder::create($this->model, 'calendar_weekly')
    ->useWeeklyView()
    ->setWeeklyHours(8, 20)        // Office hours
    ->setWeeklyHourHeight(80)       // Taller cells
    ->setWeekNumber($week_number)
    ->setHeaderTitle('Week Schedule')
    // ... other configuration
```

## See Also

- **[create-list-data.md](create-list-data.md)** - Common methods for all list types
- **[create-builder-table.md](create-builder-table.md)** - Table-based display
- **[create-builder-list.md](create-builder-list.md)** - List/box-based display
- **Example**: `milkadmin/Modules/Events/` - Complete calendar implementation
