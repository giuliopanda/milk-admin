<?php
// List and Enum Fields

// List with static options
->int('status')->default(0)
    ->formType('list')
    ->formParams(['options' => [
        0 => 'Inactive',
        1 => 'Active'
    ]])

// List with dynamic options from another model
$categories_model = new LinkCategoryModel();
$categories_data = $categories_model->getAll()->getFormattedData();
$categories = [];
foreach ($categories_data as $category) {
    $categories[$category->id] = $category->title;
}
$rule->list('category_id', $categories)->label('Category')

// List with array options (Events)
->list('event_class', [
    'event-primary' => 'Primary',
    'event-success' => 'Success',
    'event-warning' => 'Warning',
    'event-danger' => 'Danger',
    'event-info' => 'Info',
    'event-secondary' => 'Secondary'
])->default('event-primary')

// Select (alias for list)
->select('locale', $languages)->default($default_language)
