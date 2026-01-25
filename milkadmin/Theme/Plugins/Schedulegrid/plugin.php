<?php

// Create ScheduleGrid instance with passed variables
$scheduleGrid = new ScheduleGrid([
    'events' => $events ?? [],
    'grid_id' => $grid_id ?? 'schedule_grid',
    'period_type' => $period_type ?? 'week',
    'month' => $month ?? date('n'),
    'year' => $year ?? date('Y'),
    'week_number' => $week_number ?? date('W'),
    'start_date' => $start_date ?? null,
    'end_date' => $end_date ?? null,
    'locale' => $locale ?? 'en_US',
    'header_title' => $header_title ?? 'Schedule',
    'header_icon' => $header_icon ?? '',
    'header_color' => $header_color ?? 'primary',
    'min_year' => $min_year ?? null,
    'max_year' => $max_year ?? null,
    'show_header' => $show_header ?? true,
    'show_navigation' => $show_navigation ?? true,
    'custom_cell_renderer' => $custom_cell_renderer ?? null,
    'grid_attrs' => $grid_attrs ?? [],
    'row_id_field' => $row_id_field ?? 'resource_id',
    'time_interval' => $time_interval ?? 15,
    'row_header_label' => $row_header_label ?? '',
    'column_width' => $column_width ?? null,
    'on_event_click_url' => $on_event_click_url ?? '',
    'on_empty_cell_click_url' => $on_empty_cell_click_url ?? '',
]);

// Render the schedule grid
echo $scheduleGrid->render();
?>
