<?php

/**
 * Class GridRenderer - Concrete implementation of schedule grid rendering
 *
 * This class renders a schedule grid using CSS Grid layout with:
 * - Dynamic rows from data
 * - Dynamic columns from period
 * - Cell grouping with colspan support
 */
class GridRenderer extends ScheduleGridRenderer {

    /**
     * Render the grid content
     *
     * @return string HTML
     */
    protected function renderGridContent() {
        $rows = $this->data->getRows();
        $columns = $this->data->getColumns();

        if (empty($rows) || empty($columns)) {
            return $this->renderEmptyState();
        }

        // Calculate grid template columns (row header + data columns)
        $colCount = count($columns);
        $columnWidth = $this->data->getColumnWidth() ?: '1fr';
        $gridTemplateColumns = "auto repeat({$colCount}, {$columnWidth})";

        $gridAttrs = $this->data->getElementAttrs('grid', 'schedule-grid');

        // For day view: no gap, with border. For week/month: gap with background
        $periodType = $this->data->getPeriodType();
        if ($periodType === 'day') {
            $gridStyle = "display: grid; grid-template-columns: {$gridTemplateColumns}; gap: 0; border: 1px solid #dee2e6;";
        } else {
            $gridStyle = "display: grid; grid-template-columns: {$gridTemplateColumns}; gap: 1px; background-color: #dee2e6;";
        }

        $html = <<<HTML
    <div{$gridAttrs} style="{$gridStyle}">

HTML;

        // Render header row
        $html .= $this->renderHeaderRow($columns);

        // Render data rows
        foreach ($rows as $row_id => $row_label) {
            $html .= $this->renderDataRow($row_id, $row_label, $columns);
        }

        $html .= "    </div>\n";

        // Close container if header was shown
        if ($this->data->shouldShowHeader()) {
            $html .= "</div>\n";
        }

        return $html;
    }

    /**
     * Render header row with column labels
     *
     * @param array $columns Column definitions
     * @return string HTML
     */
    protected function renderHeaderRow($columns) {
        $html = '';

        $periodType = $this->data->getPeriodType();

        // For day view, render grouped hour headers
        if ($periodType === 'day') {
            return $this->renderDayHeaderRows($columns);
        }

        // For week/month view, render standard single header row
        $rowHeaderLabel = $this->data->getRowHeaderLabel();
        $cornerContent = $rowHeaderLabel ?: '&nbsp;';

        // First cell: corner with label
        $html .= "        <div class=\"schedule-grid-corner bg-light border fw-bold text-center p-2\">{$cornerContent}</div>\n";

        // Column headers
        foreach ($columns as $col) {
            $col_label = $col['label'] ?? '';
            $sub_label = $col['sub_label'] ?? '';

            $isToday = false;
            if (isset($col['date'])) {
                $isToday = $col['date']->format('Y-m-d') === date('Y-m-d');
            }

            $todayClass = $isToday ? ' schedule-col-today bg-primary-subtle' : '';

            $html .= "        <div class=\"schedule-grid-col-header bg-light border text-center p-2{$todayClass}\">\n";
            $html .= "            <div class=\"fw-bold\">{$col_label}</div>\n";

            if ($sub_label) {
                $html .= "            <div class=\"small text-muted\">{$sub_label}</div>\n";
            }

            $html .= "        </div>\n";
        }

        return $html;
    }

    /**
     * Render grouped header rows for day view
     *
     * @param array $columns Column definitions
     * @return string HTML
     */
    protected function renderDayHeaderRows($columns) {
        $html = '';

        // Group columns by hour
        $hourGroups = $this->groupColumnsByHour($columns);

        $rowHeaderLabel = $this->data->getRowHeaderLabel();
        $cornerContent = $rowHeaderLabel ?: '&nbsp;';

        // Corner cell
        $html .= "        <div class=\"schedule-grid-corner bg-light border-bottom fw-bold text-center p-2\">{$cornerContent}</div>\n";

        // Hour headers (spanning multiple columns) - with left and bottom border
        foreach ($hourGroups as $hourGroup) {
            $hour = $hourGroup['hour'];
            $colspan = $hourGroup['colspan'];

            $html .= "        <div class=\"schedule-grid-col-header bg-light border-start border-bottom text-center p-2 fw-bold\" style=\"grid-column: span {$colspan};\">\n";
            $html .= "            {$hour}:00\n";
            $html .= "        </div>\n";
        }

        return $html;
    }

    /**
     * Group columns by hour for day view header
     *
     * @param array $columns Column definitions
     * @return array Array of hour groups with hour and colspan
     */
    protected function groupColumnsByHour($columns) {
        $groups = [];
        $currentHour = null;
        $currentCount = 0;

        foreach ($columns as $col) {
            $col_id = $col['id'] ?? '';

            // Extract hour from time string (e.g., "09:15" -> "09")
            if (preg_match('/^(\d{1,2}):\d{2}$/', $col_id, $matches)) {
                $hour = $matches[1];

                if ($currentHour === null) {
                    // Start first group
                    $currentHour = $hour;
                    $currentCount = 1;
                } elseif ($hour === $currentHour) {
                    // Continue current group
                    $currentCount++;
                } else {
                    // Save previous group and start new one
                    $groups[] = [
                        'hour' => $currentHour,
                        'colspan' => $currentCount
                    ];
                    $currentHour = $hour;
                    $currentCount = 1;
                }
            }
        }

        // Add final group
        if ($currentHour !== null) {
            $groups[] = [
                'hour' => $currentHour,
                'colspan' => $currentCount
            ];
        }

        return $groups;
    }

    /**
     * Render a single data row
     *
     * @param string|int $row_id Row identifier
     * @param string $row_label Row label
     * @param array $columns Column definitions
     * @return string HTML
     */
    protected function renderDataRow($row_id, $row_label, $columns) {
        // Get grouped cells for this row
        $grouped_cells = $this->data->getGroupedCellsForRow($row_id);

        $html = '';

        // Row header - border-top for day view
        $periodType = $this->data->getPeriodType();
        $borderClass = ($periodType === 'day') ? 'border-top' : 'border';

        $html .= "        <div class=\"schedule-grid-row-header bg-light {$borderClass} p-2 fw-bold\">{$row_label}</div>\n";

        // Render cells with grouping
        $html .= $this->renderRowCells($row_id, $grouped_cells, $columns);

        return $html;
    }

    /**
     * Render cells for a row, handling grouping
     *
     * @param string|int $row_id Row identifier
     * @param array $grouped_cells Grouped cells from data
     * @param array $columns Column definitions
     * @return string HTML
     */
    protected function renderRowCells($row_id, $grouped_cells, $columns) {
        $html = '';
        $col_index = 0;
        $grouped_index = 0;
        $periodType = $this->data->getPeriodType();

        foreach ($columns as $col) {
            $col_id = $col['id'];

            // Check if this column starts a new hour (for day view borders)
            $isHourStart = false;
            if ($periodType === 'day' && preg_match('/^\d{1,2}:00$/', $col_id)) {
                $isHourStart = true;
            }

            // Check if we have a grouped cell starting here
            if (isset($grouped_cells[$grouped_index]) &&
                $grouped_cells[$grouped_index]['start_col'] === $col_id) {

                // Render grouped cell
                $grouped = $grouped_cells[$grouped_index];
                $html .= $this->renderCell($row_id, $col_id, $grouped, $isHourStart);

                // Skip columns covered by this group
                $col_index += $grouped['colspan'];
                $grouped_index++;
            } elseif ($this->isColumnCoveredByGroup($col_id, $grouped_cells, $grouped_index)) {
                // Column is covered by a previous group, skip
                $col_index++;
                continue;
            } else {
                // Render empty cell
                $html .= $this->renderEmptyCell($row_id, $col_id, $isHourStart);
                $col_index++;
            }
        }

        return $html;
    }

    /**
     * Check if a column is covered by a grouped cell
     *
     * @param string $col_id Column ID
     * @param array $grouped_cells Grouped cells
     * @param int $current_index Current group index
     * @return bool
     */
    protected function isColumnCoveredByGroup($col_id, $grouped_cells, $current_index) {
        if ($current_index > 0 && isset($grouped_cells[$current_index - 1])) {
            $prev_group = $grouped_cells[$current_index - 1];
            $columns = $this->data->getColumns();

            // Find indices
            $start_idx = null;
            $end_idx = null;
            $col_idx = null;

            foreach ($columns as $idx => $col) {
                if ($col['id'] === $prev_group['start_col']) $start_idx = $idx;
                if ($col['id'] === $prev_group['end_col']) $end_idx = $idx;
                if ($col['id'] === $col_id) $col_idx = $idx;
            }

            if ($start_idx !== null && $end_idx !== null && $col_idx !== null) {
                return $col_idx > $start_idx && $col_idx <= $end_idx;
            }
        }

        return false;
    }

    /**
     * Render a cell (with optional grouping)
     *
     * @param string|int $row_id Row identifier
     * @param string $col_id Column identifier
     * @param array $grouped Grouped cell data
     * @param bool $isHourStart Whether this cell starts a new hour (for day view)
     * @return string HTML
     */
    protected function renderCell($row_id, $col_id, $grouped, $isHourStart = false) {
        $event = $grouped['event'] ?? null;
        $colspan = $grouped['colspan'] ?? 1;
        $is_grouped = ($colspan > 1);

        // Check for custom cell renderer
        if ($this->data->hasCustomCellRenderer()) {
            $customHtml = $this->data->renderCustomCell($row_id, $col_id, $event, $is_grouped, $colspan);
            if ($customHtml !== null) {
                return $customHtml;
            }
        }

        // Default rendering
        if (!$event) {
            return $this->renderEmptyCell($row_id, $col_id, $isHourStart);
        }

        $label = $event['label'] ?? '';
        $class = $event['class'] ?? '';
        $color = $event['color'] ?? '';
        $event_id = $event['id'] ?? '';

        $style = "grid-column: span {$colspan};";

        if ($color) {
            $style .= " background-color: {$color};";
        }

        // Build border classes based on period type
        $periodType = $this->data->getPeriodType();
        if ($periodType === 'day') {
            // Day view: horizontal borders for all cells, vertical borders at hour starts
            $borderClass = 'border-top';
            if ($isHourStart) {
                $borderClass .= ' border-start';
            }
        } else {
            // Week/month view: full borders
            $borderClass = 'border';
        }

        // Build cell class with optional clickable cursor
        $cellClass = "schedule-grid-cell {$borderClass} p-1 " . $class;

        // Add data attributes for click handling
        $dataAttrs = '';
        $dataAttrs .= ' data-event-id="' . htmlspecialchars($event_id, ENT_QUOTES) . '"';
        $dataAttrs .= ' data-row-id="' . htmlspecialchars($row_id, ENT_QUOTES) . '"';
        $dataAttrs .= ' data-col-id="' . htmlspecialchars($col_id, ENT_QUOTES) . '"';

        // Add data-fetch attributes if click URL is configured
        $onEventClickUrl = $this->data->getOnEventClickUrl();
        if ($onEventClickUrl) {
            // Substitute placeholders
            $clickUrl = str_replace(
                ['%id%', '%row_id%', '%col_id%'],
                [urlencode($event_id), urlencode($row_id), urlencode($col_id)],
                $onEventClickUrl
            );
            $dataAttrs .= ' data-fetch="post" data-url="' . htmlspecialchars($clickUrl, ENT_QUOTES) . '"';
            $cellClass .= ' cursor-pointer';
        }

        return <<<HTML
        <div class="{$cellClass}" style="{$style}"{$dataAttrs}>
            <div class="cell-content small">{$label}</div>
        </div>

HTML;
    }

    /**
     * Render an empty cell
     *
     * @param string|int $row_id Row identifier
     * @param string $col_id Column identifier
     * @param bool $isHourStart Whether this cell starts a new hour (for day view)
     * @return string HTML
     */
    protected function renderEmptyCell($row_id, $col_id, $isHourStart = false) {
        // Build border classes based on period type
        $periodType = $this->data->getPeriodType();
        if ($periodType === 'day') {
            // Day view: horizontal borders for all cells, vertical borders at hour starts
            $borderClass = 'border-top';
            if ($isHourStart) {
                $borderClass .= ' border-start';
            }
        } else {
            // Week/month view: full borders
            $borderClass = 'border';
        }

        // Add data attributes for click handling
        $dataAttrs = '';
        $dataAttrs .= ' data-row-id="' . htmlspecialchars($row_id, ENT_QUOTES) . '"';
        $dataAttrs .= ' data-col-id="' . htmlspecialchars($col_id, ENT_QUOTES) . '"';

        // Extract date and time from column ID for different period types
        $date = '';
        $time = '';

        if ($periodType === 'day') {
            // For day view, col_id is time like "09:15"
            $time = $col_id;
            // Get the date from the period
            $start_date = $this->data->getStartDate();
            if ($start_date) {
                $date = $start_date->format('Y-m-d');
            }
        } else {
            // For week/month view, col_id is date like "2024-01-15"
            $date = $col_id;
        }

        if ($date) {
            $dataAttrs .= ' data-date="' . htmlspecialchars($date, ENT_QUOTES) . '"';
        }
        if ($time) {
            $dataAttrs .= ' data-time="' . htmlspecialchars($time, ENT_QUOTES) . '"';
        }

        // Add data-fetch attributes if click URL is configured
        $cellClass = "schedule-grid-cell schedule-grid-cell-empty bg-white {$borderClass}";
        $onEmptyCellClickUrl = $this->data->getOnEmptyCellClickUrl();
        if ($onEmptyCellClickUrl) {
            // Substitute placeholders
            $clickUrl = str_replace(
                ['%row_id%', '%col_id%', '%date%', '%time%'],
                [urlencode($row_id), urlencode($col_id), urlencode($date), urlencode($time)],
                $onEmptyCellClickUrl
            );
            $dataAttrs .= ' data-fetch="post" data-url="' . htmlspecialchars($clickUrl, ENT_QUOTES) . '"';
            $cellClass .= ' cursor-pointer';
        }

        return "        <div class=\"{$cellClass}\"{$dataAttrs}>&nbsp;</div>\n";
    }

    /**
     * Render empty state when no data
     *
     * @return string HTML
     */
    protected function renderEmptyState() {
        $html = <<<HTML
    <div class="alert alert-info m-3">
        <i class="bi bi-info-circle me-2"></i>
        No data available for this period.
    </div>

HTML;

        // Close container if header was shown
        if ($this->data->shouldShowHeader()) {
            $html .= "</div>\n";
        }

        return $html;
    }
}
