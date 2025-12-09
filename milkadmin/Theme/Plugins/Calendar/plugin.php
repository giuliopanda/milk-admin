<?php

// Crea un'istanza del calendario con le variabili passate
$calendar = new Calendar(
    $month ?? date('n'),           // Use passed month or current month
    $year ?? date('Y'),            // Use passed year or current year
    $locale ?? 'en_US',            // Use passed locale or default
    $calendar_id ?? 'calendar',    // Use passed calendar_id or default
    $view_type ?? 'monthly'        // Use passed view_type or default to monthly
);
$calendar->setActionUrl($_SERVER['REQUEST_URI']);

// Set weekly view settings if provided
if (isset($view_type) && $view_type === 'weekly') {
    if (isset($weekly_hour_start) || isset($weekly_hour_end) || isset($weekly_hour_height)) {
        $calendar->setWeeklyViewSettings(
            $weekly_hour_start ?? 0,
            $weekly_hour_end ?? 24,
            $weekly_hour_height ?? 60
        );
    }
}
if (isset($week_number)) {
    $calendar->getData()->setWeekNumber($week_number);
}


// Set header customizations if provided
if (isset($header_title)) {
    $calendar->setHeaderTitle($header_title);
}
if (isset($header_icon)) {
    $calendar->setHeaderIcon($header_icon);
}
if (isset($header_color)) {
    $calendar->setHeaderColor($header_color);
}

// Set click URL handlers if provided
if (isset($on_appointment_click_url)) {
    $calendar->setOnAppointmentClickUrl($on_appointment_click_url);
}
if (isset($on_empty_date_click_url)) {
    $calendar->setOnEmptyDateClickUrl($on_empty_date_click_url);
}

// Set date range if provided
if (isset($min_year) || isset($max_year) || isset($min_month) || isset($max_month)) {
    $calendar->setDateRange(
        $min_year ?? null,
        $max_year ?? null,
        $min_month ?? null,
        $max_month ?? null
    );
}

// Set compact mode
if (isset($compact)) {
    $calendar->setCompact((bool)$compact);
}

// Set highlight days with appointments
if (isset($highlight_days_with_appointments)) {
    $calendar->setHighlightDaysWithAppointments((bool)$highlight_days_with_appointments);
}

// Set click URL for dates with appointments
if (isset($on_date_with_appointments_click_url)) {
    $calendar->setOnDateWithAppointmentsClickUrl($on_date_with_appointments_click_url);
}

// Set click mode for dates with appointments
if (isset($on_date_with_appointments_click_mode)) {
    $calendar->setOnDateWithAppointmentsClickMode($on_date_with_appointments_click_mode);
}

// Set visibility of navigation controls
if (isset($show_year_month_select)) {
    $calendar->setShowYearMonthSelect((bool)$show_year_month_select);
}

if (isset($show_prev_next_buttons)) {
    $calendar->setShowPrevNextButtons((bool)$show_prev_next_buttons);
}

if (isset($show_today_button)) {
    $calendar->setShowTodayButton((bool)$show_today_button);
}

// Set header visibility
if (isset($show_header)) {
    $calendar->setShowHeader((bool)$show_header);
}

// Set custom cell renderer if provided
if (isset($custom_cell_renderer) && is_callable($custom_cell_renderer)) {
    $calendar->setCustomCellRenderer($custom_cell_renderer);
}

// Set calendar styling attributes if provided
if (isset($calendar_attrs) && is_array($calendar_attrs)) {
    $calendar->setCalendarAttrs($calendar_attrs);
}

if (isset($events) && is_array($events)) {
    // Get user timezone from config, default to UTC if not set
    $userTimezone = new DateTimeZone(App\Config::get('time_zone', 'UTC'));

    foreach ($events as $event) {
        $id = $event->id;
        $title = $event->title;
        $start = $event->start_datetime;
        $end = $event->end_datetime;
        $class = $event->class ?? '';

        // Ensure we have DateTime objects
        if (!$start instanceof DateTime) {
            $start = new DateTime($start ?? 'now');
        }
        if (!$end instanceof DateTime) {
            $end = new DateTime($end ?? 'now');
        }

        // Convert to user's timezone
        $start->setTimezone($userTimezone);
        $end->setTimezone($userTimezone);

        $calendar->addAppointment($id, $start, $end, $title, $class);
    }
}
// Renderizza il calendario
echo $calendar->render();
?>

<!-- Modal per dettagli appuntamento -->
<div class="modal fade" id="appointmentModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Dettagli Appuntamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Contenuto dinamico inserito da JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal per tutti gli appuntamenti del giorno -->
<div class="modal fade" id="dayAppointmentsModal" tabindex="-1" aria-labelledby="dayModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dayModalTitle">Appuntamenti del Giorno</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="dayModalBody">
                <!-- Contenuto dinamico inserito da JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<!-- Esempio di personalizzazione degli eventi -->
<script>
// Esempio: Intercetta il click su un appuntamento prima del comportamento di default
document.addEventListener('calendar:appointmentClick', function(e) {
    console.log('Appuntamento cliccato:', e.detail);
    
    // Per prevenire il modal di default e gestire tu stesso l'evento:
    // e.preventDefault();
    // ... il tuo codice personalizzato
});

// Esempio: Intercetta il click su "altri appuntamenti"
document.addEventListener('calendar:moreClick', function(e) {
    console.log('Click su "altri" per la data:', e.detail.date);
});
</script>