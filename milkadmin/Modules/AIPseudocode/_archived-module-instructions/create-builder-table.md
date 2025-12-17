# TableBuilder - Tables

TableBuilder creates traditional HTML tables with sortable columns, actions, and styling options.

## ⚠️ DEFAULT BEHAVIOR: ALWAYS USE FETCH MODE

**IMPORTANT:** Unless explicitly specified otherwise, ALWAYS generate TableBuilder with fetch/modal mode enabled. This is the modern, recommended approach.

### Fetch Mode (DEFAULT - Use This)

```php
#[RequestAction('get-table')]
public function getTable()
{
    $table = TableBuilder::create($this->model, 'idTableUsers')
        ->activeFetch()  // ⭐ ALWAYS ADD THIS (enables fetch mode)
        ->field('id')->label('ID')
        ->field('name')->label('Name')->asLink('?page='.$this->page.'&action=edit&id=%id%')
        ->field('email')->label('Email')
        ->field('created_at')->label('Created')->type('date')
        ->setRequestAction('get-table')
        ->setDefaultActions();

    $response = [];
    if ($table->isInsideRequest()) {
        // Table internal request (pagination, sorting, filters)
        $response['html'] = $table->render();
    } else {
        // External request (after save, from button click)
        $response['modal'] = [
            'title' => $this->title,
            'body' => $table->render(),
            'size' => 'xl'
        ];
    }

    Response::Json($response);
}

#[RequestAction('home')]
public function listPage()
{
    $response = $this->getCommonData();
    $response['table_id'] = 'idTableUsers';
    Response::render(__DIR__ . '/Views/list_page.php', $response);
}
```

**View (list_page.php) - Fetch Mode:**
```php
<div class="card">
    <div class="card-body">
        <a id="tableContainer" data-fetch="post" href="?page=<?php echo $page; ?>&action=get-table">ShowList</a>
    </div>
</div>
```

### Traditional Mode (Only if explicitly requested)

```php
#[RequestAction('home')]
public function listPage()
{
    $response = $this->getCommonData();

    $tableBuilder = TableBuilder::create($this->model, 'idTableUsers')
        // NO activeFetch() - traditional mode
        ->field('id')->label('ID')
        ->field('name')->label('Name')
        ->field('email')->label('Email')
        ->field('created_at')->label('Created')->type('date')
        ->setDefaultActions();

    $response = array_merge($response, $tableBuilder->getResponse());
    Response::render(__DIR__ . '/Views/list_page.php', $response);
}
```

## Table-Specific Methods

### Table Styling

```php
->tableClass('table table-striped table-hover')  // CSS classes for <table>
->headerClass('bg-primary text-white')           // CSS classes for <thead>
->bodyClass('small')                             // CSS classes for <tbody>
->footerClass('fw-bold')                         // CSS classes for <tfoot>
```

### Color Themes

```php
->tableColor('primary')      // Apply Bootstrap color theme
                             // Options: primary, secondary, success, danger, warning, info, light, dark

->headerColor('success')     // Color for table header
```

### Row Styling

```php
->rowClass('align-middle')                       // CSS classes for all rows
->rowClassAlternate('bg-light', 'bg-white')      // Alternate row colors

// Conditional row styling
->rowClassByValue('status', 'active', 'table-success')
->rowClassByValue('stock', 0, 'table-danger', '==')
```

### Column Styling

```php
// Field-first style (requires ->field() first)
->field('price')->class('text-end fw-bold')
->field('status')->class('text-center')
->field('name')->colHeaderClass('text-start')

// Cell conditional styling
->field('status')
    ->cellClassValue('active', 'bg-success text-white')
    ->cellClassValue('inactive', 'bg-secondary text-white')

// Based on another field's value
->field('price')
    ->cellClassOtherValue('status', 'discount', 'text-success fw-bold')

// Alternate cell colors
->field('amount')->classAlternate('bg-light', 'bg-white')
```

### Column-first style (legacy)

```php
->columnClass('price', 'text-end')
->headerColumnClass('price', 'text-end')
->cellClassByValue('status', 'status', 'active', 'bg-success')
->columnClassAlternate('amount', 'bg-light', 'bg-white')
```

### Footer

```php
->setFooter(['', '', 'Total:', '1,234', ''])  // Values for each column
```

### Table Attributes

```php
->setTableAttrs([                       // Set multiple attributes
    'table' => ['class' => 'table-sm'],
    'tr' => ['class' => 'cursor-pointer']
])

->addTableAttr('table', 'data-foo', 'bar')  // Add single attribute
```

## Complete Example

```php
$tableBuilder = TableBuilder::create($this->model, 'idTableProducts')
    // Fields
    ->field('id')->label('ID')->class('text-center')
    ->field('name')->label('Product')->link('?page=products&action=edit&id=%id%')
    ->field('price')->label('Price')->type('currency')->class('text-end')
    ->field('stock')->label('Stock')
        ->cellClassValue(0, 'bg-danger text-white', '==')
        ->cellClassValue(10, 'bg-warning', '<=')
    ->field('status')->label('Status')
        ->options(['active' => 'Active', 'inactive' => 'Inactive'])
    ->field('image')->label('Image')->image(['width' => 50])

    // Query
    ->where('category_id = ?', [5])

    // Styling
    ->tableColor('primary')
    ->rowClassAlternate('', 'bg-light')

    // Actions
    ->setDefaultActions()

    // Footer
    ->setFooter(['', '', 'Total:', '$12,345', '', '', '']);

// IMPORTANT: Use getResponse() for AJAX support
$response = array_merge($response, $tableBuilder->getResponse());
```

## View Template

```php
<?php
namespace Modules\Products\Views;
!defined('MILK_DIR') && die();
?>
<div class="card">
    <div class="card-header">
        <?php ph(TitleBuilder::create($title)); ?>
    </div>
    <div class="card-body">
        <?php ph($html); ?>
    </div>
</div>
```

## AJAX Behavior

When using `getResponse()` and called via AJAX:
- Returns `$response['html']` with table HTML
- Supports table reloads after actions
- Works with `table` key in Response::json()

```php
// In action handler
Response::json([
    'success' => true,
    'message' => 'Item deleted',
    'reload_table' => 'idTableProducts'  // Triggers table reload
]);
```

## See Also

- **[create-list-data.md](create-list-data.md)** - Common methods for all list types
- **[create-builder-list.md](create-builder-list.md)** - List/box-based display
- **[create-builder-calendar.md](create-builder-calendar.md)** - Calendar display
