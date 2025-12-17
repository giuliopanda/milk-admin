# Data Lists - Overview

MilkAdmin provides three types of data lists, all sharing the same base class `GetDataBuilder`:

1. **Tables** - Traditional HTML tables with sortable columns
2. **Lists** - Card/box-based lists with flexible layouts
3. **Calendars** - Monthly/weekly calendar views with events

All three share common methods for data fetching, filtering, and actions.

## Common Pattern

All list types follow this pattern:

```php
#[RequestAction('home')]
public function listPage()
{
    $response = $this->getCommonData();

    // Build the list/table/calendar
    $builder = TableBuilder::create($this->model, 'tableId')
        ->field('name')->label('Name')
        ->field('email')->label('Email')
        ->setDefaultActions();

    // IMPORTANT: Merge response and use getResponse()
    $response = array_merge($response, $builder->getResponse());

    Response::render(__DIR__ . '/Views/list_page.php', $response);
}
```

### Why use `getResponse()`?

The `getResponse()` method returns:
- `$response['html']` - The rendered HTML for the list
- Additional keys when called via AJAX (table reloads, action results, etc.)

**This enables AJAX functionality** - when the page is requested via JSON, it returns the data structure instead of rendering the full page.

## GetDataBuilder - Common Methods

All three builder types extend `GetDataBuilder` and share these methods:

### Field Configuration

```php
->field('column_name')           // Select field for configuration (required before other field methods)
->label('Label Text')            // Set column label
->type('text|html|date|...')     // Set display type
->options(['key' => 'value'])    // Set options for select fields
->fn(callable)                   // Custom formatter function
->hide()                         // Hide column
->noSort()                       // Disable sorting
->sortBy('real_field')           // Map sort to different field
```

### Field Display Types

```php
->field('image')->image()                // Display as image thumbnail
->field('file')->file()                  // Display as file download link
->field('url')->link('?page=x&id=%id%')  // Display as clickable link
->field('description')->truncate(100)    // Truncate long text
```

### Query Methods

```php
->select(['field1', 'field2'])              // Select specific columns
->where('field = ?', ['value'])             // Add WHERE condition
->whereIn('status', ['active', 'pending'])  // WHERE IN condition
->whereLike('name', 'search', 'both')       // LIKE condition (start|end|both)
->whereBetween('price', 10, 100)            // BETWEEN condition
->join('table', 'condition', 'INNER')       // Add JOIN
->leftJoin('table', 'condition')            // Add LEFT JOIN
```

### Actions

```php
->setDefaultActions()                    // Add Edit and Delete actions
->addAction('custom', [                  // Add custom row action
    'label' => 'View',
    'link' => '?page=x&action=view&id=%id%',
    'class' => 'btn btn-sm btn-primary'
])
->setBulkActions([                       // Add bulk actions (checkbox selection)
    [
        'label' => 'Delete Selected',
        'action' => [$this, 'bulkDelete']
    ]
])
```

### Extensions

```php
->extensions([                           // Add GetDataBuilder extensions
    'MyExtension' => ['option' => 'value']
])
```

### Response Methods

```php
->getResponse()     // Get response array with 'html' key and AJAX support
->render()          // Get HTML string only (for direct output)
->getData()         // Get raw data array (rows, info, page_info)
```

## Choosing the Right Type

| Type | Use When | Example |
|------|----------|---------|
| **Table** | Displaying tabular data with many columns | Users list, Products catalog |
| **List** | Displaying items as cards with rich content | Blog posts, Image gallery, Events |
| **Calendar** | Displaying time-based events | Appointments, Deadlines, Schedule |

## Detailed Documentation

See specific documentation for each type:

- **[create-builder-table.md](create-builder-table.md)** - Table-specific methods and styling
- **[create-builder-list.md](create-builder-list.md)** - List/box-specific methods and layouts
- **[create-builder-calendar.md](create-builder-calendar.md)** - Calendar-specific methods and events

## Example Modules

See real implementations in:
- **Tables**: `milkadmin/Modules/Users/`, `milkadmin/Modules/Posts/`
- **Lists**: Check ListBuilder examples
- **Calendars**: `milkadmin/Modules/Events/`
