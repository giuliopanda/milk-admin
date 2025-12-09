<?php

/**
 * Classe MonthlyCalendarRenderer - Implementa il rendering mensile del calendario
 * 
 * Questa classe gestisce la visualizzazione del calendario in formato mensile
 * con griglia di giorni e appuntamenti.
 */
class MonthlyCalendarRenderer extends CalendarRenderer {

    /**
     * Render the main calendar content (monthly grid)
     *
     * @return string HTML
     */
    protected function renderCalendarContent() {
        // Get custom attributes for weekdays and days
        $weekdaysAttrs = $this->data->getElementAttrs('weekdays', 'calendar-weekdays');
        $daysAttrs = $this->data->getElementAttrs('days', 'calendar-days');

        $html = '<div' . $weekdaysAttrs . '>';

        // Render weekday headers
        $html .= $this->renderWeekdayHeaders();

        $html .= '</div><div' . $daysAttrs . '>';
        
        // Render days grid
        $html .= $this->renderDaysGrid();
        
        $html .= '</div></div></div>';
        
        return $html;
    }

    /**
     * Render weekday headers based on locale
     *
     * @return string HTML
     */
    protected function renderWeekdayHeaders() {
        // Weekdays using IntlDateFormatter
        $weekdayFormatter = new IntlDateFormatter(
            $this->data->getLocale(),
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            null,
            null,
            'EEE'
        );

        // Get first day of week for locale (0 = Sunday, 1 = Monday)
        $calendar = IntlCalendar::createInstance(null, $this->data->getLocale());
        $firstDayOfWeek = $calendar->getFirstDayOfWeek();

        $html = '';
        
        // Generate weekday headers
        for ($i = 0; $i < 7; $i++) {
            $dayNumber = ($firstDayOfWeek + $i - 1) % 7;
            if ($dayNumber == 0) $dayNumber = 7; // Convert Sunday from 0 to 7

            // Create a date for this weekday (using a known Monday as reference)
            $timestamp = strtotime("2023-01-" . (1 + $dayNumber)); // 2023-01-02 is Monday
            $dayName = $weekdayFormatter->format($timestamp);
            $html .= "<div class='calendar-weekday'>{$dayName}</div>";
        }

        return $html;
    }

    /**
     * Render the complete days grid including previous/next month days
     *
     * @return string HTML
     */
    protected function renderDaysGrid() {
        $html = '';
        
        // First day of month
        $month = $this->data->getMonth();
        $year = $this->data->getYear();
        
        $firstDay = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = date('t', $firstDay);
        
        // Day of week of first day (1 = Monday, 7 = Sunday)
        $dayOfWeek = date('N', $firstDay);
        
        // Get first day of week for locale
        $calendar = IntlCalendar::createInstance(null, $this->data->getLocale());
        $firstDayOfWeek = $calendar->getFirstDayOfWeek();
        
        // Adjust based on locale's first day of week
        if ($firstDayOfWeek == 1) { // Monday first (ISO 8601)
            $offset = $dayOfWeek - 1;
        } else { // Sunday first
            $offset = ($dayOfWeek == 7) ? 0 : $dayOfWeek;
        }
        
        // Days from previous month
        $prevMonth = $month - 1;
        $prevYear = $year;
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
            $html .= $this->renderDay($day, $month, $year, false);
        }
        
        // Next month days to complete grid
        $nextMonth = $month + 1;
        $nextYear = $year;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }
        
        $totalCells = $offset + $daysInMonth;
        $remainingCells = (7 - ($totalCells % 7)) % 7;
        
        for ($day = 1; $day <= $remainingCells; $day++) {
            $html .= $this->renderDay($day, $nextMonth, $nextYear, true);
        }

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
    protected function renderDay($day, $month, $year, $otherMonth = false) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $today = date('Y-m-d');
        $isToday = ($date === $today);

        $appointments = $this->data->getAppointmentsForDate($date);
        $hasAppointments = !empty($appointments);

        // If custom cell renderer is set, use it
        $customRenderer = $this->data->getCustomCellRenderer();
        if ($customRenderer !== null) {
            return call_user_func(
                $customRenderer,
                $day,
                $month,
                $year,
                $otherMonth,
                $date,
                $isToday,
                $appointments,
                $this->data
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
        if ($hasAppointments && $this->data->shouldHighlightDaysWithAppointments()) {
            $classes[] = 'has-appointments';
        }

        // Apply custom day classes from calendar_attrs
        $dayAttrs = $this->data->getElementAttrs('day', implode(' ', $classes));
        $html = '<div' . $dayAttrs . '>';

        // Build day number HTML with appropriate click behavior
        $html .= $this->renderDayNumber($day, $date, $hasAppointments);

        // Show appointments only if enabled, appointments exist, and not in compact mode
        if ($hasAppointments && !$this->data->isCompact()) {
            // Apply custom appointments-container classes
            $appointmentsContainerAttrs = $this->data->getElementAttrs('appointments-container', 'appointments-container');
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
     * Render day number with appropriate click behavior
     *
     * @param int $day Day number
     * @param string $date Date in Y-m-d format
     * @param bool $hasAppointments Whether the day has appointments
     * @return string HTML
     */
    protected function renderDayNumber($day, $date, $hasAppointments) {
        $dayNumberHtml = '';

        // Get custom day-number class
        $customDayNumberClass = $this->data->getElementClass('day-number');

        // Priority 1: If day has appointments and specific URL is configured
        if ($hasAppointments && !empty($this->data->getOnDateWithAppointmentsClickUrl())) {
            $clickUrl = str_replace('%date%', $date, $this->data->getOnDateWithAppointmentsClickUrl());
            $dayNumberClasses = trim('day-number day-number-clickable day-number-with-appointments ' . $customDayNumberClass);

            if ($this->data->getOnDateWithAppointmentsClickMode() === 'fetch') {
                $dayNumberHtml = '<div class="' . $dayNumberClasses . '" data-fetch="post" data-url="' . _r($clickUrl) . '">' . _r($day) . '</div>';
            } else {
                // Link mode
                $dayNumberHtml = '<a href="' . _r($clickUrl) . '" class="' . $dayNumberClasses . '">' . _r($day) . '</a>';
            }
        }
        // Priority 2: If empty date click URL is configured (fallback for days without appointments)
        elseif (!empty($this->data->getOnEmptyDateClickUrl())) {
            $clickUrl = str_replace('%date%', $date, $this->data->getOnEmptyDateClickUrl());
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

        return $dayNumberHtml;
    }

    /**
     * Render a single appointment
     *
     * @param array $appointment Appointment data
     * @return string HTML
     */
    protected function renderAppointment($appointment) {
        $multidayClass = $appointment['is_multiday'] ? ' appointment-multiday' : '';
        $firstDayClass = $appointment['is_first_day'] ? ' appointment-first-day' : '';
        $lastDayClass = $appointment['is_last_day'] ? ' appointment-last-day' : '';

        // Format dates based on locale
        $dateFormatter = new IntlDateFormatter(
            $this->data->getLocale(),
            IntlDateFormatter::SHORT,
            IntlDateFormatter::SHORT
        );

        $startDateFormatted = $dateFormatter->format($appointment['start_date']);
        $endDateFormatted = $dateFormatter->format($appointment['end_date']);

        // Time display - Always show start and end time
        $timeDisplay = $appointment['start_time'] . ' - ' . $appointment['end_time'];

        // Date range display for multi-day events
        $dateRangeDisplay = '';
        if ($appointment['is_multiday']) {
            $dateRangeDisplay = "<span class='appointment-dates'>{$startDateFormatted} - {$endDateFormatted}</span>";
        }

        // Build data-fetch attributes and clickable class if URL is configured
        $dataFetchAttr = '';
        $clickableClass = '';
        if (!empty($this->data->getOnAppointmentClickUrl())) {
            $clickUrl = str_replace('%id%', $appointment['id'], $this->data->getOnAppointmentClickUrl());
            $dataFetchAttr = ' data-fetch="post" data-url="' . htmlspecialchars($clickUrl) . '"';
            $clickableClass = ' appointment-clickable';
        }

        // Apply custom appointment classes
        $defaultAppointmentClass = "appointment js-appointment {$appointment['class']}{$multidayClass}{$firstDayClass}{$lastDayClass}{$clickableClass}";
        $appointmentAttrs = $this->data->getElementAttrs('appointment', $defaultAppointmentClass);

        return <<<HTML
<div{$appointmentAttrs} data-appointment-id="{$appointment['id']}"{$dataFetchAttr}>
    <span class="appointment-time">{$timeDisplay}</span>
    {$dateRangeDisplay}
    <span class="appointment-title">{$appointment['title']}</span>
</div>

HTML;
    }
}
