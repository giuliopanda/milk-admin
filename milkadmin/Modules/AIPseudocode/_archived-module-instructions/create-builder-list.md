# ListBuilder - Card/Box Lists

ListBuilder creates card-based lists with flexible layouts. Perfect for displaying items with rich content, images, and custom styling.

## ⚠️ DEFAULT BEHAVIOR: ALWAYS USE FETCH MODE

**IMPORTANT:** Unless explicitly specified otherwise, ALWAYS generate ListBuilder with fetch/modal mode enabled. This is the modern, recommended approach.

### Fetch Mode (DEFAULT - Use This)

```php
#[RequestAction('get-list')]
public function getList()
{
    $list = ListBuilder::create($this->model, 'idListProducts')
        ->activeFetch()  // ⭐ ALWAYS ADD THIS (enables fetch mode)
        ->field('name')->label('Product')
        ->field('description')->label('Description')->truncate(100)
        ->field('price')->label('Price')->type('currency')
        ->field('image')->image(['width' => '100%'])
        ->gridColumns(3)
        ->setRequestAction('get-list')
        ->setDefaultActions();

    $response = [];
    if ($list->isInsideRequest()) {
        // List internal request (pagination, filters)
        $response['html'] = $list->render();
    } else {
        // External request (after save, from button click)
        $response['modal'] = [
            'title' => $this->title,
            'body' => $list->render(),
            'size' => 'xl'
        ];
    }

    Response::Json($response);
}

#[RequestAction('home')]
public function listPage()
{
    $response = $this->getCommonData();
    $response['list_id'] = 'idListProducts';
    Response::render(__DIR__ . '/Views/list_page.php', $response);
}
```

**View (list_page.php) - Fetch Mode:**
```php
<div class="card">
    <div class="card-body">
        <a id="listContainer" data-fetch="post" href="?page=<?php echo $page; ?>&action=get-list">ShowList</a>
    </div>
</div>
```

### Traditional Mode (Only if explicitly requested)

```php
#[RequestAction('home')]
public function listPage()
{
    $response = $this->getCommonData();

    $listBuilder = ListBuilder::create($this->model, 'idListProducts')
        // NO activeFetch() - traditional mode
        ->field('name')->label('Product')
        ->field('description')->label('Description')->truncate(100)
        ->field('price')->label('Price')->type('currency')
        ->field('image')->image(['width' => '100%'])
        ->gridColumns(3)
        ->setDefaultActions();

    $response = array_merge($response, $listBuilder->getResponse());
    Response::render(__DIR__ . '/Views/list_page.php', $response);
}
```

## List-Specific Methods

### Grid Layout

```php
->gridColumns(3)            // Number of columns (1-6)
                           // Responsive: auto-adjusts for mobile
```

### Container & Grid Styling

```php
->containerClass('p-3')              // Container wrapper
->colClass('col-md-4 col-12')        // Column grid classes
```

### Box/Card Styling

```php
->boxClass('card shadow-sm')                    // CSS classes for each box
->boxHeaderClass('card-header bg-primary')      // Box header
->boxBodyClass('card-body')                     // Box body
->boxFooterClass('card-footer text-muted')      // Box footer
```

### Box Color Themes

```php
->boxColor('primary')       // Apply Bootstrap color theme
                           // Options: primary, secondary, success, danger, warning, info, light, dark
```

### Conditional Box Styling

```php
// Alternate box colors
->boxClassAlternate('border-primary', 'border-secondary')

// Based on field value
->boxClassByValue('status', 'active', 'border-success border-3')
->boxClassByValue('stock', 0, 'opacity-50', '==')
```

### Field Styling

```php
// Field-first style (requires ->field() first)
->field('name')->class('fw-bold fs-5')
->field('price')->class('text-end text-primary')

// Field row/label/value styling
->fieldRowClass('mb-2')              // Container for field label+value
->fieldLabelClass('text-muted')      // Field labels
->fieldValueClass('fw-bold')         // Field values

// Specific field styling
->fieldClass('price', 'text-success fs-4')
```

### Conditional Field Styling

```php
// Based on field's own value
->field('status')
    ->classValue('active', 'text-success fw-bold')
    ->classValue('inactive', 'text-muted')

// Based on another field's value
->field('price')
    ->classOtherValue('status', 'discount', 'text-danger fw-bold')
```

### Checkbox Styling

```php
->checkboxWrapperClass('position-absolute top-0 end-0 p-2')
```

### Custom Box Template

```php
->setBoxTemplate(__DIR__ . '/Views/custom-box.php')  // Use custom box layout
```

### Box Attributes

```php
->setBoxAttrs([                          // Set multiple attributes
    'box' => ['class' => 'card'],
    'container' => ['class' => 'row g-3']
])

->addBoxAttr('box', 'data-foo', 'bar')   // Add single attribute
```

## Complete Example

```php
$listBuilder = ListBuilder::create($this->model, 'idListProducts')
    // Fields
    ->field('image')->image(['width' => '100%', 'height' => '200px'])
    ->field('name')->label('Product')->class('fw-bold fs-5')
    ->field('category')->label('Category')->class('badge bg-secondary')
    ->field('price')->label('Price')->type('currency')->class('text-primary fs-4')
    ->field('description')->label('Description')->truncate(150)
    ->field('stock')->label('Stock')
        ->classValue(0, 'text-danger', '==')
        ->classValue(10, 'text-warning', '<=')
    ->field('status')
        ->options(['active' => 'Active', 'inactive' => 'Inactive'])
        ->classValue('active', 'text-success')

    // Query
    ->where('featured = ?', [1])

    // Layout
    ->gridColumns(3)
    ->boxColor('light')
    ->boxClass('card shadow hover-shadow-lg transition')

    // Conditional styling
    ->boxClassByValue('stock', 0, 'opacity-75')

    // Field styling
    ->fieldLabelClass('text-muted small')
    ->fieldValueClass('mb-2')

    // Actions
    ->setDefaultActions();

// IMPORTANT: Use getResponse() for AJAX support
$response = array_merge($response, $listBuilder->getResponse());
```

## Custom Box Template

Create `custom-box.php` in your Views folder:

```php
<?php
!defined('MILK_DIR') && die();
// Variables available: $row, $fields, $actions, $checkbox
?>
<div class="custom-box <?php _p($box_class ?? ''); ?>">
    <div class="box-image">
        <?php ph($row->image ?? ''); ?>
    </div>
    <div class="box-content">
        <h3><?php ph($row->name); ?></h3>
        <p><?php ph($row->description); ?></p>
    </div>
    <div class="box-footer">
        <?php ph($actions ?? ''); ?>
    </div>
</div>
```

Use it:
```php
->setBoxTemplate(__DIR__ . '/Views/custom-box.php')
```

## AJAX Behavior

When using `getResponse()` and called via AJAX:
- Returns `$response['html']` with list HTML
- Supports list reloads after actions
- Works with `table` key in Response::json() (table applies to lists too)

```php
// In action handler
Response::json([
    'success' => true,
    'message' => 'Item updated',
    'reload_table' => 'idListProducts'  // Triggers list reload
]);
```

## See Also

- **[create-list-data.md](create-list-data.md)** - Common methods for all list types
- **[create-builder-table.md](create-builder-table.md)** - Table-based display
- **[create-builder-calendar.md](create-builder-calendar.md)** - Calendar display
