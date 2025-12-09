<?php

/**
 * Classe WeeklyCalendarRenderer - Visualizzazione settimanale completa
 * 
 * Questa classe implementa una visualizzazione settimanale del calendario
 * con fasce orarie e posizionamento preciso degli appuntamenti.
 */
class WeeklyCalendarRenderer extends CalendarRenderer {

    // Configurazione fasce orarie
    private $hour_start = 0;      // Ora di inizio (0-23)
    private $hour_end = 24;       // Ora di fine (1-24)
    private $hour_height = 60;    // Altezza in px di ogni ora
    private $time_slot_minutes = 30; // Intervallo per le linee guida

    /**
     * Set hour range for display
     *
     * @param int $start Start hour (0-23)
     * @param int $end End hour (1-24)
     * @return static For method chaining
     */
    public function setHourRange($start, $end) {
        $this->hour_start = max(0, min(23, $start));
        $this->hour_end = max(1, min(24, $end));
        return $this;
    }

    /**
     * Set hour height in pixels
     *
     * @param int $height Height in pixels
     * @return static For method chaining
     */
    public function setHourHeight($height) {
        $this->hour_height = max(40, $height);
        return $this;
    }

    /**
     * Render the main calendar content (weekly view)
     *
     * @return string HTML
     */
    protected function renderCalendarContent() {
        $weekDates = $this->getWeekDates();
        
        $html = '<div class="weekly-view">';
        
        // Header con giorni della settimana
        $html .= $this->renderWeeklyHeader($weekDates);
        
        // All-day events area
        $html .= $this->renderAllDayEvents($weekDates);
        
        // Corpo con fasce orarie e appuntamenti
        $html .= $this->renderWeeklyBody($weekDates);
        
        $html .= '</div></div></div>';
        
        return $html;
    }

    /**
     * Render weekly header with day names and dates
     *
     * @param array $weekDates Array of DateTime objects
     * @return string HTML
     */
    protected function renderWeeklyHeader($weekDates) {
        $html = '<div class="weekly-header">';
        
        // Colonna vuota per le fasce orarie
        $html .= '<div class="weekly-time-column-header">
        </div>';
        
        // Render each day header
        foreach ($weekDates as $date) {
            $dayName = $this->getDayName($date);
            $dayNumber = $date->format('j');
            $monthName = $this->getShortMonthName($date);
            $isToday = $date->format('Y-m-d') === date('Y-m-d');
            $todayClass = $isToday ? ' weekly-today' : '';
            $dateStr = $date->format('Y-m-d');
            
            // Count appointments for this day
            $appointments = $this->data->getAppointmentsForDate($dateStr);
            $appointmentCount = count($appointments);
            $countBadge = $appointmentCount > 0 ? "<span class='weekly-count-badge'>{$appointmentCount}</span>" : '';
            
            $html .= <<<HTML
<div class="weekly-day-header{$todayClass}" data-date="{$dateStr}">
    <div class="weekly-day-name">{$dayName}</div>
    <div class="weekly-day-number">{$dayNumber} {$monthName}{$countBadge}</div>
</div>
HTML;
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render all-day events section
     *
     * @param array $weekDates Array of DateTime objects
     * @return string HTML
     */
    protected function renderAllDayEvents($weekDates) {
        $hasAllDayEvents = false;
        $allDayAppointments = [];
        
        // Collect all-day events for each day
        foreach ($weekDates as $date) {
            $dateStr = $date->format('Y-m-d');
            $dayAppointments = $this->data->getAppointmentsForDate($dateStr);
            
            foreach ($dayAppointments as $apt) {
                // Consider as all-day if it spans from 00:00 to 23:59 or is a multi-day event
                if ($apt['start_time'] === '00:00' && $apt['end_time'] === '23:59') {
                    $hasAllDayEvents = true;
                    if (!isset($allDayAppointments[$dateStr])) {
                        $allDayAppointments[$dateStr] = [];
                    }
                    $allDayAppointments[$dateStr][] = $apt;
                }
            }
        }
        
        if (!$hasAllDayEvents) {
            return '';
        }
        
        $html = '<div class="weekly-allday-container">';
        $html .= '<div class="weekly-allday-label">All Day</div>';
        
        foreach ($weekDates as $date) {
            $dateStr = $date->format('Y-m-d');
            $html .= '<div class="weekly-allday-cell" data-date="' . $dateStr . '">';
            
            if (isset($allDayAppointments[$dateStr])) {
                foreach ($allDayAppointments[$dateStr] as $apt) {
                    $html .= $this->renderAllDayAppointment($apt);
                }
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render an all-day appointment
     *
     * @param array $appointment Appointment data
     * @return string HTML
     */
    protected function renderAllDayAppointment($appointment) {
        // Build multiday classes like monthly view
        $multidayClass = isset($appointment['is_multiday']) && $appointment['is_multiday'] ? ' appointment-multiday' : '';
        $firstDayClass = isset($appointment['is_first_day']) && $appointment['is_first_day'] ? ' appointment-first-day' : '';
        $lastDayClass = isset($appointment['is_last_day']) && $appointment['is_last_day'] ? ' appointment-last-day' : '';

        // Build data-fetch attributes if URL is configured
        $dataFetchAttr = '';
        $clickableClass = '';
        if (!empty($this->data->getOnAppointmentClickUrl())) {
            $clickUrl = str_replace('%id%', $appointment['id'], $this->data->getOnAppointmentClickUrl());
            $dataFetchAttr = ' data-fetch="post" data-url="' . htmlspecialchars($clickUrl) . '"';
            $clickableClass = ' appointment-clickable';
        }

        // Apply custom appointment classes like monthly view
        $defaultAppointmentClass = "weekly-allday-appointment js-appointment {$appointment['class']}{$multidayClass}{$firstDayClass}{$lastDayClass}{$clickableClass}";
        $appointmentAttrs = $this->data->getElementAttrs('appointment', $defaultAppointmentClass);

        return <<<HTML
<div{$appointmentAttrs}
     data-appointment-id="{$appointment['id']}"
     {$dataFetchAttr}>
    <span class="weekly-appointment-title">{$appointment['title']}</span>
</div>
HTML;
    }

    /**
     * Render weekly body with time slots and appointments
     *
     * @param array $weekDates Array of DateTime objects
     * @return string HTML
     */
    protected function renderWeeklyBody($weekDates) {
        $html = '<div class="weekly-body-container">';
        $html .= '<div class="weekly-body">';
        
        // Render time slots
        for ($hour = $this->hour_start; $hour < $this->hour_end; $hour++) {
            $html .= $this->renderTimeRow($hour, $weekDates);
        }
        
        $html .= '</div>';
        
        // Render appointments overlay
        $html .= $this->renderAppointmentsOverlay($weekDates);
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render a single time row
     *
     * @param int $hour Hour (0-23)
     * @param array $weekDates Array of DateTime objects
     * @return string HTML
     */
    protected function renderTimeRow($hour, $weekDates) {
        $timeLabel = sprintf('%02d:00', $hour);
        $rowHeight = $this->hour_height . 'px';
        
        $html = "<div class=\"weekly-time-row\" style=\"height: {$rowHeight};\">";
        
        // Time label column
        $html .= "<div class=\"weekly-time-label\">{$timeLabel}</div>";
        
        // Day columns with half-hour guide
        foreach ($weekDates as $date) {
            $dateStr = $date->format('Y-m-d');
            $html .= $this->renderTimeSlot($dateStr, $hour);
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render a single time slot
     *
     * @param string $date Date in Y-m-d format
     * @param int $hour Hour (0-23)
     * @return string HTML
     */
    protected function renderTimeSlot($date, $hour) {
        // Handle click events for empty time slots
        $clickAttr = '';
        if (!empty($this->data->getOnEmptyDateClickUrl())) {
            $dateTime = $date . ' ' . sprintf('%02d:00:00', $hour);
            $clickUrl = str_replace('%date%', $dateTime, $this->data->getOnEmptyDateClickUrl());
            $clickAttr = ' data-fetch="post" data-url="' . htmlspecialchars($clickUrl) . '"';
        }
        
        $html = "<div class=\"weekly-time-slot\" data-date=\"{$date}\" data-hour=\"{$hour}\"{$clickAttr}>";
        
        // Half-hour guide line
        if ($this->time_slot_minutes <= 30) {
            $html .= '<div class="weekly-half-hour-guide"></div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render appointments overlay
     *
     * @param array $weekDates Array of DateTime objects
     * @return string HTML
     */
    protected function renderAppointmentsOverlay($weekDates) {
        $html = '<div class="weekly-appointments-overlay">';
        
        foreach ($weekDates as $dayIndex => $date) {
            $dateStr = $date->format('Y-m-d');
            $appointments = $this->data->getAppointmentsForDate($dateStr);
            
            // Filter out all-day events
            $timedAppointments = array_filter($appointments, function($apt) {
                return !($apt['start_time'] === '00:00' && $apt['end_time'] === '23:59');
            });
            
            if (empty($timedAppointments)) {
                continue;
            }
            
            // Group overlapping appointments
            $appointmentGroups = $this->groupOverlappingAppointments($timedAppointments);
            
            foreach ($appointmentGroups as $group) {
                foreach ($group['appointments'] as $colIndex => $apt) {
                    $html .= $this->renderWeeklyAppointment($apt, $dayIndex, $group['total_columns'], $colIndex);
                }
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Group overlapping appointments for proper display
     *
     * @param array $appointments Array of appointments
     * @return array Array of appointment groups
     */
    protected function groupOverlappingAppointments($appointments) {
        if (empty($appointments)) {
            return [];
        }
        
        $groups = [];
        $currentGroup = null;
        
        foreach ($appointments as $apt) {
            $aptStart = $this->timeToMinutes($apt['start_time']);
            $aptEnd = $this->timeToMinutes($apt['end_time']);
            
            // Find if this appointment overlaps with current group
            $overlaps = false;
            if ($currentGroup !== null) {
                foreach ($currentGroup['appointments'] as $groupApt) {
                    $groupStart = $this->timeToMinutes($groupApt['start_time']);
                    $groupEnd = $this->timeToMinutes($groupApt['end_time']);
                    
                    if ($aptStart < $groupEnd && $aptEnd > $groupStart) {
                        $overlaps = true;
                        break;
                    }
                }
            }
            
            if ($overlaps) {
                // Add to current group
                $currentGroup['appointments'][] = $apt;
                $currentGroup['end_time'] = max($currentGroup['end_time'], $aptEnd);
            } else {
                // Start new group
                if ($currentGroup !== null) {
                    $currentGroup['total_columns'] = count($currentGroup['appointments']);
                    $groups[] = $currentGroup;
                }
                $currentGroup = [
                    'appointments' => [$apt],
                    'start_time' => $aptStart,
                    'end_time' => $aptEnd
                ];
            }
        }
        
        // Add last group
        if ($currentGroup !== null) {
            $currentGroup['total_columns'] = count($currentGroup['appointments']);
            $groups[] = $currentGroup;
        }
        
        return $groups;
    }

    /**
     * Convert time string to minutes since midnight
     *
     * @param string $time Time in H:i format
     * @return int Minutes
     */
    protected function timeToMinutes($time) {
        list($hours, $minutes) = explode(':', $time);
        return (int)$hours * 60 + (int)$minutes;
    }

    /**
     * Render a weekly appointment with precise positioning
     *
     * @param array $appointment Appointment data
     * @param int $dayIndex Day index (0-6)
     * @param int $totalColumns Total overlapping columns
     * @param int $columnIndex Column index for this appointment
     * @return string HTML
     */
    protected function renderWeeklyAppointment($appointment, $dayIndex, $totalColumns, $columnIndex) {
        $startMinutes = $this->timeToMinutes($appointment['start_time']);
        $endMinutes = $this->timeToMinutes($appointment['end_time']);

        // Calculate view boundaries
        $viewStartMinutes = $this->hour_start * 60;
        $viewEndMinutes = $this->hour_end * 60;
        $totalViewHeightPx = ($this->hour_end - $this->hour_start) * $this->hour_height;

        // Clamp appointment times to visible range
        $clampedStartMinutes = max($startMinutes, $viewStartMinutes);
        $clampedEndMinutes = min($endMinutes, $viewEndMinutes);

        // Calculate position from hour_start in PIXELS (always >= 0)
        $offsetMinutes = $clampedStartMinutes - $viewStartMinutes;
        $topPx = ($offsetMinutes / 60) * $this->hour_height;

        // Calculate height in PIXELS (clamped to visible area)
        $durationMinutes = $clampedEndMinutes - $clampedStartMinutes;
        $heightPx = (($durationMinutes / 60) * $this->hour_height) -4;

        // Calculate max height (cannot exceed bottom of view)
        $maxHeight = $totalViewHeightPx - $topPx;
        $maxHeightPx =  min($maxHeight, $heightPx * 2) - 8;
        if ($heightPx > $maxHeightPx) {
            $heightPx = $maxHeightPx;
        }
        // Calculate horizontal positioning for overlapping events (still in %)
        $leftPercent = ($dayIndex * 100 / 7) + ($columnIndex * (100 / 7 / $totalColumns));
        $widthPercent = (100 / 7 / $totalColumns);

        // Adjust for small gaps between days and overlapping events
        $leftPercent += 0.2;
        $widthPercent -= 0.4;

        if ($totalColumns > 1) {
            $widthPercent -= 0.5; // Add gap between overlapping events
        }
        $topPx = $topPx + 2;
        $style = "top: {$topPx}px; min-height: {$heightPx}px; max-height: {$maxHeightPx}px; left: {$leftPercent}%; width: {$widthPercent}%;";
        
        // Build multiday classes like monthly view
        $multidayClass = isset($appointment['is_multiday']) && $appointment['is_multiday'] ? ' appointment-multiday' : '';
        $firstDayClass = isset($appointment['is_first_day']) && $appointment['is_first_day'] ? ' appointment-first-day' : '';
        $lastDayClass = isset($appointment['is_last_day']) && $appointment['is_last_day'] ? ' appointment-last-day' : '';

        // Build data-fetch attributes if URL is configured
        $dataFetchAttr = '';
        $clickableClass = '';
        if (!empty($this->data->getOnAppointmentClickUrl())) {
            $clickUrl = str_replace('%id%', $appointment['id'], $this->data->getOnAppointmentClickUrl());
            $dataFetchAttr = ' data-fetch="post" data-url="' . htmlspecialchars($clickUrl) . '"';
            $clickableClass = ' appointment-clickable';
        }

        // Determine if we should show full details or compact view
        $isShort = $durationMinutes < 30;
        $compactClass = $isShort ? ' weekly-appointment-compact' : '';

        // Apply custom appointment classes like monthly view
        $defaultAppointmentClass = "weekly-appointment js-appointment {$appointment['class']}{$multidayClass}{$firstDayClass}{$lastDayClass}{$clickableClass}{$compactClass}";
        $appointmentAttrs = $this->data->getElementAttrs('appointment', $defaultAppointmentClass);

        $timeDisplay = $appointment['start_time'];
        if (!$isShort) {
            $timeDisplay .= ' - ' . $appointment['end_time'];
        }

        return <<<HTML
<div{$appointmentAttrs}
     style="{$style}"
     data-appointment-id="{$appointment['id']}"
     {$dataFetchAttr}>
    <div class="weekly-appointment-time">{$timeDisplay}</div>
    <div class="weekly-appointment-title">{$appointment['title']}</div>
</div>
HTML;
    }

    /**
     * Get dates for the current week
     *
     * @return array Array of DateTime objects
     */
    protected function getWeekDates() {
        $month = $this->data->getMonth();
        $year = $this->data->getYear();
        $weekNumber = $this->data->getWeekNumber() ?? 1;
        
        // Primo giorno del mese
        $firstDayOfMonth = new DateTime("{$year}-{$month}-01");
        
        // Trova il lunedì della prima settimana del mese
        $dayOfWeek = (int)$firstDayOfMonth->format('N');
        $firstMonday = clone $firstDayOfMonth;
        if ($dayOfWeek > 1) {
            $firstMonday->modify('-' . ($dayOfWeek - 1) . ' days');
        }
        
        // Salta alla settimana richiesta
        $firstMonday->modify('+' . (($weekNumber - 1) * 7) . ' days');
        
        // Genera i 7 giorni della settimana
        $weekDates = [];
        $current = clone $firstMonday;
        for ($i = 0; $i < 7; $i++) {
            $weekDates[] = clone $current;
            $current->modify('+1 day');
        }
        
        return $weekDates;
    }

    /**
     * Get day name for a date
     *
     * @param DateTime $date Date
     * @return string Day name
     */
    protected function getDayName($date) {
        $formatter = new IntlDateFormatter(
            $this->data->getLocale(),
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            null,
            null,
            'EEE'
        );
        
        return $formatter->format($date);
    }

    /**
     * Get short month name for a date
     *
     * @param DateTime $date Date
     * @return string Short month name
     */
    protected function getShortMonthName($date) {
        $formatter = new IntlDateFormatter(
            $this->data->getLocale(),
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            null,
            null,
            'MMM'
        );
        
        return $formatter->format($date);
    }

    /**
     * Override navigation to work with weeks
     */
    protected function buildNavigationControls(
        $prevMonth, $prevYear,
        $nextMonth, $nextYear,
        $prevDisabledAttr, $nextDisabledAttr,
        $currentMonth, $currentYear,
        $monthOptions, $yearOptions
    ) {
        $navControlsHtml = '';

        // Previous week button
        if ($this->data->shouldShowPrevNextButtons()) {
            $navControlsHtml .= <<<HTML
                <button type="button"
                        class="btn btn-sm btn-light js-calendar-prev"
                        data-month="{$prevMonth}"
                        data-year="{$prevYear}"
                        title="Previous week"
                        {$prevDisabledAttr}>
                    <i class="bi bi-chevron-left"></i>
                </button>

HTML;
        }

       

        // For  Per vista settimanale, mostrare select settimana invece di mese/anno
        if ($this->data->shouldShowYearMonthSelect()) {
            $weekDates = $this->getWeekDates();
            // Genera opzioni settimane per il mese corrente
            $weekOptions = $this->generateWeekOptions($this->data->getMonth(), $this->data->getYear());
            
            $navControlsHtml .= <<<HTML
                <!-- Week select -->
                <select class="form-select form-select-sm js-calendar-week-select" 
                        style="width: auto; min-width: 200px;">
                    {$weekOptions}
                </select>
                
                <!-- Month select (hidden field for backend) -->
                <input type="hidden" class="js-calendar-month-value" value="{$this->data->getMonth()}">
                
                <!-- Year select -->
                <select class="form-select form-select-sm js-calendar-year-select"
                        style="width: auto; min-width: 90px;">
                    {$yearOptions}
                </select>
        HTML;
        }

        // Today button
        if ($this->data->shouldShowTodayButton()) {
            $navControlsHtml .= <<<HTML
                <button type="button"
                        class="btn btn-sm btn-light js-calendar-today"
                        data-month="{$currentMonth}"
                        data-year="{$currentYear}"
                        title="This week">
                    <i class="bi bi-calendar-check"></i>
                </button>
HTML;
        }

        // Next week button
        if ($this->data->shouldShowPrevNextButtons()) {
            $navControlsHtml .= <<<HTML
                <button type="button"
                        class="btn btn-sm btn-light js-calendar-next"
                        data-month="{$nextMonth}"
                        data-year="{$nextYear}"
                        title="Next week"
                        {$nextDisabledAttr}>
                    <i class="bi bi-chevron-right"></i>
                </button>
HTML;
        }

        return $navControlsHtml;
    }

    /**
     * Generate week options for current month
     */
    protected function generateWeekOptions($month, $year) {
        $weeks = $this->getWeeksInMonth($month, $year);
        $currentWeek = $this->getCurrentWeekNumber();
        $options = '';
        
        foreach ($weeks as $weekNum => $weekData) {
            $selected = ($weekNum == $currentWeek) ? 'selected' : '';
            $label = $weekData['label'];
            $options .= "<option value=\"{$weekNum}\" {$selected}>{$label}</option>";
        }
        
        return $options;
    }

    /**
     * Get weeks in a month with start/end dates
     */
    protected function getWeeksInMonth($month, $year) {
        $weeks = [];
        $firstDay = new DateTime("{$year}-{$month}-01");
        $lastDay = new DateTime("{$year}-{$month}-" . date('t', mktime(0, 0, 0, $month, 1, $year)));
        
        // Trova primo lunedì del mese o precedente
        $current = clone $firstDay;
        $dayOfWeek = (int)$current->format('N');
        if ($dayOfWeek > 1) {
            $current->modify('-' . ($dayOfWeek - 1) . ' days');
        }
        
        $weekNum = 1;
        $monthFormatter = new IntlDateFormatter($this->data->getLocale(), IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMM');
        
        while ($current <= $lastDay || $weekNum == 1) {
            $weekStart = clone $current;
            $weekEnd = clone $current;
            $weekEnd->modify('+6 days');
            
            $startMonth = $monthFormatter->format($weekStart);
            $endMonth = $monthFormatter->format($weekEnd);
            
            $label = $weekStart->format('j') . ' ' . $startMonth;
            if ($startMonth != $endMonth) {
                $label .= ' - ' . $weekEnd->format('j') . ' ' . $endMonth;
            } else {
                $label .= ' - ' . $weekEnd->format('j');
            }
            
            $weeks[$weekNum] = [
                'start' => $weekStart,
                'end' => $weekEnd,
                'label' => $label
            ];
            
            $current->modify('+7 days');
            $weekNum++;
            
            if ($weekNum > 6) break; // Max 6 settimane
        }
        
        return $weeks;
    }

    /**
     * Get current week number based on first day of month
     */
    protected function getCurrentWeekNumber() {
        // Se week_number è già impostato, usalo
        $weekNumber = $this->data->getWeekNumber();
        if ($weekNumber !== null) {
            return $weekNumber;
        }
        
        // Altrimenti calcola dalla data corrente
        $weekDates = $this->getWeekDates();
        $firstDayOfWeek = $weekDates[0];
        
        $month = $this->data->getMonth();
        $year = $this->data->getYear();
        $firstDayOfMonth = new DateTime("{$year}-{$month}-01");
        
        $diff = $firstDayOfWeek->diff($firstDayOfMonth);
        $weekNum = floor($diff->days / 7) + 1;
        
        return max(1, $weekNum);
    }
}