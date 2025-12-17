# FormBuilder - Advanced Topics

This document covers advanced FormBuilder features. Only read the sections you need for your specific requirements.

## Topics Covered

1. **Form Containers** - Organize fields in Bootstrap grid columns
2. **Autocomplete Search** - Add searchable relationship fields for large datasets
3. **Custom Validation** - Client-side and server-side validation
4. **Pre-loaded Select with Relationships** - Dropdown fields with relationship data

---

## 1. Form Containers

Organize form fields using Bootstrap grid layouts with multi-column containers.

### Basic Usage

```php
->addContainer(
    'contact_info',                           // Container ID
    ['name', 'email', 'phone'],              // Fields to include
    3,                                        // Number of columns (or array for custom sizes)
    'status',                                 // Insert before this field (empty = append)
    'Contact Information',                    // Title (optional)
    ['class' => 'border rounded p-3 mb-4']   // HTML attributes
)
```

### Equal Columns

```php
// 3 equal columns (col-md-4 each)
->addContainer('user_info', ['first_name', 'last_name', 'email'], 3)
```

### Custom Column Sizes

```php
// Custom widths: 4/12, 5/12, 3/12
->addContainer('address', ['street', 'city', 'zip'], [4, 5, 3])
```

### Automatic Wrapping

Fields automatically wrap to new rows when exceeding column count:
```php
// 5 fields in 3 columns = 2 rows (3 + 2)
->addContainer('details', ['field1', 'field2', 'field3', 'field4', 'field5'], 3)
```

**From:** `FormContainerManagementTrait`

---

## 2. Autocomplete Search (Large Datasets)

Add searchable fields for relationships with hundreds/thousands of records (users, products, customers).

### Model Configuration

```php
// In Model configure()
->int('user_id')
    ->nullable()
    ->label('User')
    ->belongsTo('user', \Modules\Auth\UserModel::class, 'id')
    ->formType('milkSelect')
    ->apiUrl('?page=projects&action=related-search-field&f=user_id', 'username')
```

### Two Approaches

**Option A: Automatic (Recommended)**
Uses built-in `related-search-field` action. No controller code needed.

```php
->apiUrl('?page=projects&action=related-search-field&f=user_id', 'username')
```

**Option B: Custom Controller Method**
For advanced filtering or business logic.

```php
// Model
->apiUrl('?page=projects&action=search-users', 'username')

// Controller
#[RequestAction('search-users')]
public function searchUsers() {
    $search = $_REQUEST['q'] ?? '';
    $options = $this->model->searchRelated($search, 'user_id');

    // Optional: custom filtering here

    Response::json([
        'success' => 'ok',
        'options' => $options
    ]);
}
```

### Display in Table

```php
// Use dot notation to show username instead of ID
->column('user.username', 'Owner', 'text')
```

**When to use:** Large datasets (100+ items) that need AJAX search.

---

## 3. Custom Validation

Add custom client-side and server-side validation for complex business logic.

### Client-Side Validation

Place in your module's JavaScript file:

```javascript
// For dynamic forms (AJAX/modal)
document.addEventListener('updateContainer', function(event) {
    const end_datetime = document.querySelector('[name="data[end_datetime]"]')
    const start_datetime = document.querySelector('[name="data[start_datetime]"]')

    if (end_datetime && start_datetime) {
        end_datetime.addEventListener('fieldValidation', function(e) {
            if (end_datetime.value < start_datetime.value) {
                end_datetime.setCustomValidity('End datetime must be greater than start datetime')
            } else {
                end_datetime.setCustomValidity('')  // Clear error when valid
            }
        });
    }
})
```

**For static forms:** Use `DOMContentLoaded` instead of `updateContainer`.

### Validation Events

- `fieldValidation` - Validates on submit, then on each field change after first submit
- `customValidation` - Form-level validation before checking validity
- `beforeFormSubmit` - After validation passes, before actual submit

### Server-Side Validation

```php
public function saveItem($form_builder, $request) {
    // Custom validation (optional - if you need additional checks)
    if (!empty($request['start_datetime']) && !empty($request['end_datetime'])) {
        $start = strtotime($request['start_datetime']);
        $end = strtotime($request['end_datetime']);

        if ($start > $end) {
            return [
                'success' => false,
                'message' => 'Start date cannot be later than end date'
            ];
        }
    }

    // ✅ CORRECT: save() handles fill() and validate() internally
    // Returns ['success' => bool, 'error' => string]
    $result = $this->model->save();

    if ($result['success']) {
        return [
            'success' => true,
            'message' => 'Saved successfully',
            'modal' => ['action' => 'hide'],
            'reload_table' => 'idTableItems'
        ];
    } else {
        return [
            'success' => false,
            'message' => $result['error']
        ];
    }
}
```

**Note:** Always validate on both client (UX) and server (security).

---

## 4. Pre-loaded Select with Relationships

Dropdown fields with all options loaded at page load. Best for small datasets (up to ~100 items): categories, departments, roles, statuses.

### Create Secondary Model

```php
// CategoryModel.php
class CategoryModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__employee_categories')
            ->id()
            ->string('name', 100)->required()
            ->text('description')->nullable();
    }

    // Essential method - returns [id => name]
    public function getList()
    {
        $list = $this->query()->order('name')->getResults();
        $array_return = [];
        foreach ($list as $value) {
            $array_return[$value->id] = $value->name;
        }
        return $array_return;
    }
}
```

### Register Secondary Model

```php
// In Module configure()
->addModels(['category' => CategoryModel::class])
```

### Single Select Field

```php
// In main Model configure()
->int('category_id')
    ->belongsTo('category', CategoryModel::class, 'id')
    ->formType('milkSelect')
    ->options((new CategoryModel())->getList())
    ->label('Category')
    ->nullable()
```

### Multiple Select Field

```php
->array('skill_ids')
    ->options((new SkillModel())->getList())
    ->label('Skills')
    ->formType('milkSelect')
    ->formParams(['type' => 'multiple'])
    ->nullable()
```

### Display in Table

```php
// Single value - use dot notation
->column('category.name', 'Category', 'text')

// Multiple values - use callback
->column('skill_ids', 'Skills', 'callback', function($value, $record) {
    if (empty($value)) return '-';
    $skillModel = new SkillModel();
    $skills = [];
    foreach ($value as $skillId) {
        $skill = $skillModel->find($skillId);
        if ($skill) $skills[] = $skill->name;
    }
    return implode(', ', $skills);
})
```

### Update Database

```php
php milkadmin/cli.php yourmodule:update
```

This creates secondary tables and adds relationship columns.

### Comparison

| Feature | Pre-loaded | Autocomplete |
|---------|-----------|--------------|
| Loading | All at page load | AJAX on typing |
| Config | `->options(...)` | `->apiUrl(...)` |
| Best For | Small lists (10-100) | Large lists (100+) |
| Field Type | `->int()` or `->array()` | `->int()` |

---

## See Also

- **Main form documentation**: `create-builder-form.md`
- **Model configuration**: `create-model.md`
- **Controller patterns**: `create-view.md`
