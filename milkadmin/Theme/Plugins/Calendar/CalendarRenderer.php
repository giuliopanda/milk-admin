<?php

/**
 * Classe astratta CalendarRenderer - Definisce l'interfaccia per il rendering del calendario
 * 
 * Questa classe astratta permette di creare diversi tipi di visualizzazione:
 * - MonthlyCalendarRenderer: visualizzazione mensile
 * - WeeklyCalendarRenderer: visualizzazione settimanale (da implementare)
 * - DailyCalendarRenderer: visualizzazione giornaliera (da implementare)
 */
abstract class CalendarRenderer {
    protected $data;

    /**
     * Constructor
     *
     * @param CalendarData $data Dati del calendario
     */
    public function __construct(CalendarData $data) {
        $this->data = $data;
    }

    /**
     * Render the complete calendar
     * Metodo principale che orchestra il rendering completo
     *
     * @return string Calendar HTML
     */
    public function render() {
        $html = '';

        if ($this->data->shouldShowHeader()) {
            $html .= $this->renderHeader();
            $html .= $this->renderCalendarContent();
        } else {
            // When header is hidden, wrap calendar with minimal container
            $compactClass = $this->data->isCompact() ? ' calendar-compact' : '';
            $formHtml = $this->renderForm();

            // Get custom attributes for container
            $containerAttrs = $this->data->getElementAttrs('container', 'calendar-container js-calendar-container' . $compactClass);
            $gridAttrs = $this->data->getElementAttrs('grid', 'calendar-grid');

            $html .= <<<HTML
<div{$containerAttrs} id="{$this->data->getCalendarId()}">
{$formHtml}
    <div{$gridAttrs}>

HTML;
            $html .= $this->renderCalendarContent();
            // renderCalendarContent() should close grid and container
        }

        return $html;
    }

    /**
     * Render hidden form for AJAX updates
     * Questo metodo è comune a tutti i tipi di visualizzazione
     *
     * @return string Form HTML
     */
    protected function renderForm() {
        $action_url = $this->data->getActionUrl() ?: $_SERVER['REQUEST_URI'];
        $calendar_id = $this->data->getCalendarId();
        $month = $this->data->getMonth();
        $year = $this->data->getYear();
        $view_type = $this->data->getViewType();
        $filters = $this->data->getFilters();

        return <<<HTML
    <form class="js-calendar-form" action="{$action_url}" method="POST" style="display: none;">
        <input type="hidden" class="js-field-calendar-month" name="{$calendar_id}[month]" value="{$month}">
        <input type="hidden" class="js-field-calendar-year" name="{$calendar_id}[year]" value="{$year}">
        <input type="hidden" class="js-field-calendar-type" name="{$calendar_id}[type]" value="{$view_type}">
        <input type="hidden" class="js-field-calendar-filters" name="{$calendar_id}[filters]" value="{$filters}">
    </form>
HTML;
    }

    /**
     * Render calendar header with inline controls
     * Questo metodo è comune a tutti i tipi di visualizzazione
     *
     * @return string HTML
     */
    protected function renderHeader() {
        $monthName = $this->data->getMonthName();
        $year = $this->data->getYear();

        // Calculate previous and next periods
        $prev = $this->data->getPreviousMonthYear();
        $next = $this->data->getNextMonthYear();

        // Check if prev/next buttons should be disabled
        $prevDisabled = $this->data->isPreviousMonthDisabled();
        $nextDisabled = $this->data->isNextMonthDisabled();

        // Get all month names
        $months = $this->data->getAllMonthNames();

        // Build month options
        $monthOptions = '';
        foreach ($months as $num => $name) {
            $selected = ($num == $this->data->getMonth()) ? 'selected' : '';
            $disabled = $this->data->isMonthDisabled($num) ? 'disabled' : '';
            $monthOptions .= "<option value=\"{$num}\" {$selected} {$disabled}>{$name}</option>";
        }

        // Generate year options
        $yearRange = $this->data->getYearRange();
        $yearOptions = '';
        for ($y = $yearRange['start']; $y <= $yearRange['end']; $y++) {
            $selected = ($y == $this->data->getYear()) ? 'selected' : '';
            $yearOptions .= "<option value=\"{$y}\" {$selected}>{$y}</option>";
        }

        $formHtml = $this->renderForm();

        // Build icon HTML if set
        $iconHtml = '';
        if (!empty($this->data->getHeaderIcon())) {
            $iconHtml = '<i class="' . htmlspecialchars($this->data->getHeaderIcon()) . ' me-2"></i>';
        }

        // Determine text color based on background
        $headerColorClass = $this->data->getHeaderColorClass();
        $textColorClass = in_array($headerColorClass, ['bg-light', 'bg-warning']) ? 'text-dark' : 'text-white';

        // Build disabled attributes for navigation buttons
        $prevDisabledAttr = $prevDisabled ? 'disabled' : '';
        $nextDisabledAttr = $nextDisabled ? 'disabled' : '';

        // Current month/year for Today button
        $currentMonth = date('n');
        $currentYear = date('Y');

        // Build compact class if enabled
        $compactClass = $this->data->isCompact() ? ' calendar-compact' : '';

        // Build navigation controls HTML
        $navControlsHtml = $this->buildNavigationControls(
            $prev['month'], $prev['year'],
            $next['month'], $next['year'],
            $prevDisabledAttr, $nextDisabledAttr,
            $currentMonth, $currentYear,
            $monthOptions, $yearOptions
        );

        // Get custom attributes for container and grid
        $containerAttrs = $this->data->getElementAttrs('container', 'calendar-container js-calendar-container' . $compactClass);
        $gridAttrs = $this->data->getElementAttrs('grid', 'calendar-grid');

        return <<<HTML
<div{$containerAttrs} id="{$this->data->getCalendarId()}">
{$formHtml}
    <div class="calendar-header-compact {$headerColorClass} {$textColorClass}">
        <div class="d-flex justify-content-between align-items-center">
            <!-- Title on left -->
            <div>
                <h5 class="mb-0">{$iconHtml}{$this->data->getHeaderTitle()}</h5>
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
     * Build navigation controls HTML
     *
     * @param int $prevMonth Previous month
     * @param int $prevYear Previous year
     * @param int $nextMonth Next month
     * @param int $nextYear Next year
     * @param string $prevDisabledAttr Previous button disabled attribute
     * @param string $nextDisabledAttr Next button disabled attribute
     * @param int $currentMonth Current month for Today button
     * @param int $currentYear Current year for Today button
     * @param string $monthOptions Month select options HTML
     * @param string $yearOptions Year select options HTML
     * @return string Navigation controls HTML
     */
    protected function buildNavigationControls(
        $prevMonth, $prevYear,
        $nextMonth, $nextYear,
        $prevDisabledAttr, $nextDisabledAttr,
        $currentMonth, $currentYear,
        $monthOptions, $yearOptions
    ) {
        $navControlsHtml = '';

        // Previous arrow
        if ($this->data->shouldShowPrevNextButtons()) {
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

        // Month and Year selects
        $display = ($this->data->shouldShowYearMonthSelect()) ? '' : 'style="display: none"';
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
        if ($this->data->shouldShowTodayButton()) {
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
        if ($this->data->shouldShowPrevNextButtons()) {
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

        return $navControlsHtml;
    }

    /**
     * Render the main calendar content (grid, days, appointments, etc.)
     * Questo metodo deve essere implementato dalle classi concrete
     *
     * @return string HTML
     */
    abstract protected function renderCalendarContent();
}