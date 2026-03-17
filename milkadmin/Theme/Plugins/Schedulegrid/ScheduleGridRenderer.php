<?php

/**
 * Abstract class ScheduleGridRenderer - Defines the interface for schedule grid rendering
 *
 * This abstract class allows creating different types of visualizations
 * Base class with common functionality for headers, navigation, and forms
 */
abstract class ScheduleGridRenderer {
    protected $data;

    /**
     * Constructor
     *
     * @param ScheduleGridData $data Grid data
     */
    public function __construct(ScheduleGridData $data) {
        $this->data = $data;
    }

    /**
     * Render the complete grid
     * Main method that orchestrates complete rendering
     *
     * @return string Grid HTML
     */
    public function render() {
        $html = '';

        if ($this->data->shouldShowHeader()) {
            $html .= $this->renderHeader();
            $html .= $this->renderGridContent();
        } else {
            // When header is hidden, wrap grid with minimal container
            $formHtml = $this->renderForm();

            $containerAttrs = $this->data->getElementAttrs('container', 'schedule-grid-container js-schedulegrid-container');

            $html .= <<<HTML
<div{$containerAttrs} id="{$this->data->getGridId()}">
{$formHtml}
HTML;
            $html .= $this->renderGridContent();
        }

        return $html;
    }

    /**
     * Render hidden form for AJAX updates
     * This method is common to all visualization types
     *
     * @return string Form HTML
     */
    protected function renderForm() {
        $action_url = $this->data->getActionUrl();
        $grid_id = $this->data->getGridId();
        $period_type = $this->data->getPeriodType();

        // Build period-specific inputs
        $periodInputs = $this->buildPeriodInputs();

        return <<<HTML
    <form class="js-schedulegrid-form" action="{$action_url}" method="POST" style="display: none;">
        <input type="hidden" class="js-field-grid-period-type" name="{$grid_id}[period_type]" value="{$period_type}">
{$periodInputs}
    </form>
HTML;
    }

    /**
     * Build period-specific inputs for form
     *
     * @return string HTML for inputs
     */
    protected function buildPeriodInputs() {
        $grid_id = $this->data->getGridId();
        $period_type = $this->data->getPeriodType();
        $html = '';

        if ($period_type === 'week') {
            // Week and year inputs
            $week = $this->data->getColumns()[0]['date']->format('W') ?? date('W');
            $year = $this->data->getColumns()[0]['date']->format('Y') ?? date('Y');

            $html .= "        <input type=\"hidden\" class=\"js-field-grid-week\" name=\"{$grid_id}[week]\" value=\"{$week}\">\n";
            $html .= "        <input type=\"hidden\" class=\"js-field-grid-year\" name=\"{$grid_id}[year]\" value=\"{$year}\">\n";
        } elseif ($period_type === 'month') {
            // Month and year inputs
            $month = $this->data->getColumns()[0]['date']->format('n') ?? date('n');
            $year = $this->data->getColumns()[0]['date']->format('Y') ?? date('Y');

            $html .= "        <input type=\"hidden\" class=\"js-field-grid-month\" name=\"{$grid_id}[month]\" value=\"{$month}\">\n";
            $html .= "        <input type=\"hidden\" class=\"js-field-grid-year\" name=\"{$grid_id}[year]\" value=\"{$year}\">\n";
        }

        return $html;
    }

    /**
     * Render grid header with inline controls
     * This method is common to all visualization types
     *
     * @return string HTML
     */
    protected function renderHeader() {
        $formHtml = $this->renderForm();

        // Build icon HTML if set
        $iconHtml = '';
        if (!empty($this->data->getHeaderIcon())) {
            $iconHtml = '<i class="' . htmlspecialchars($this->data->getHeaderIcon()) . ' me-2"></i>';
        }

        // Determine text color based on background
        $headerColorClass = $this->data->getHeaderColorClass();
        $textColorClass = in_array($headerColorClass, ['bg-light', 'bg-warning']) ? 'text-dark' : 'text-white';

        // Build navigation controls HTML
        $navControlsHtml = '';
        if ($this->data->shouldShowNavigation()) {
            $navControlsHtml = $this->buildNavigationControls();
        }

        // Get custom attributes for container
        $containerAttrs = $this->data->getElementAttrs('container', 'schedule-grid-container js-schedulegrid-container');

        // Period title
        $periodTitle = $this->data->getPeriodTitle();

        return <<<HTML
<div{$containerAttrs} id="{$this->data->getGridId()}">
{$formHtml}
    <div class="schedule-grid-header {$headerColorClass} {$textColorClass} p-3 rounded-top">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">{$iconHtml}{$this->data->getHeaderTitle()}</h5>
                <small>{$periodTitle}</small>
            </div>
            <div class="d-flex align-items-center gap-2">
{$navControlsHtml}
            </div>
        </div>
    </div>

HTML;
    }

    /**
     * Build navigation controls (prev/next buttons)
     *
     * @return string HTML for navigation controls
     */
    protected function buildNavigationControls() {
        $prev = $this->data->getPreviousPeriod();
        $next = $this->data->getNextPeriod();

        $prevDisabled = $this->data->isPreviousPeriodDisabled() ? 'disabled' : '';
        $nextDisabled = $this->data->isNextPeriodDisabled() ? 'disabled' : '';

        // Build data attributes for prev button
        $prevDataAttrs = '';
        if (!empty($prev)) {
            $prevDataAttrs = 'data-period="' . htmlspecialchars(json_encode($prev), ENT_QUOTES) . '"';
        }

        // Build data attributes for next button
        $nextDataAttrs = '';
        if (!empty($next)) {
            $nextDataAttrs = 'data-period="' . htmlspecialchars(json_encode($next), ENT_QUOTES) . '"';
        }

        return <<<HTML
                <button type="button" class="btn btn-sm btn-light js-schedulegrid-prev" {$prevDisabled} {$prevDataAttrs}>
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button type="button" class="btn btn-sm btn-light js-schedulegrid-next" {$nextDisabled} {$nextDataAttrs}>
                    <i class="bi bi-chevron-right"></i>
                </button>
HTML;
    }

    /**
     * Render the grid content
     * Must be implemented by concrete renderers
     *
     * @return string HTML
     */
    abstract protected function renderGridContent();
}
