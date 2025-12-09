# MilkAdmin Extensions System

## Overview

The Extensions system allows you to extend the functionality of Abstract classes (Model, Controller, Module, etc.) in a modular and reusable way. Extensions are organized in separate folders and can add fields, methods, and behaviors to your models and other components.

## Directory Structure

```
milkadmin/Extensions/
├── ExtensionName/
│   ├── Model.php           # Extends AbstractModel
│   ├── Controller.php      # Extends AbstractController (optional)
│   ├── Module.php          # Extends AbstractModule (optional)
│   └── README.md           # Extension documentation
```

## Creating an Extension

### 1. Create Extension Folder

Create a folder in `milkadmin/Extensions/` with your extension name:

```bash
mkdir milkadmin/Extensions/YourExtension
```

### 2. Create Model Extension

Create `Model.php` that implements `ModelExtensionInterface`:

```php
<?php
namespace Extensions\YourExtension;

use App\Abstracts\{AbstractModel, RuleBuilder};
use App\Interfaces\ModelExtensionInterface;

class Model implements ModelExtensionInterface
{
    protected AbstractModel $model;

    public function __construct(AbstractModel $model)
    {
        $this->model = $model;
    }

    public function onConfigureBefore(RuleBuilder $rule_builder): void
    {
        // Called before Model's configure() method
        // Use this to prepare prerequisites
    }

    public function onConfigureAfter(RuleBuilder $rule_builder): void
    {
        // Called after Model's configure() method
        // Use this to add fields or modify configuration

        $rule_builder->string('your_field')
            ->label('Your Field')
            ->required();
    }

    public function onAttributeMethodsScanned(): void
    {
        // Called after attribute methods are scanned
        // Use this to register custom handlers

        $this->model->registerMethodHandler('your_field', 'set_value', function($record, $value) {
            return strtoupper($value);
        });
    }
}
```

## Using Extensions

### In Your Model

Add the extension to the `$extensions` array:

```php
<?php
namespace Modules\Posts;
use App\Abstracts\AbstractModel;

class PostsModel extends AbstractModel
{
    // Activate extensions
    protected array $extensions = ['Audit', 'SoftDelete', 'Taggable'];

    protected function configure($rule): void
    {
        $rule->table('#__posts')
            ->id()
            ->title()->index()
            ->text('content');

        // Extensions automatically add their fields
    }
}
```

## Extension Hooks

### onConfigureBefore()

Called **before** the Model's `configure()` method. Use this to:
- Set up prerequisites
- Initialize extension state
- Prepare configuration

### onConfigureAfter()

Called **after** the Model's `configure()` method. Use this to:
- Add fields to the model
- Modify existing rules
- Extend configuration

### onAttributeMethodsScanned()

Called **after** attribute methods are scanned and cached. Use this to:
- Register custom method handlers using `registerMethodHandler()`
- Add validation, formatting, or value transformation logic
- Override default behaviors

## Method Handlers

Extensions can register handlers for different operations:

### set_value
Transforms a value before it's set in the record:

```php
$this->model->registerMethodHandler('field_name', 'set_value', function($record, $value) {
    return strtoupper($value);
});
```

### get_formatted
Formats a value for display:

```php
$this->model->registerMethodHandler('field_name', 'get_formatted', function($record, $value) {
    return date('d/m/Y', strtotime($value));
});
```

### validate
Custom validation logic:

```php
$this->model->registerMethodHandler('field_name', 'validate', function($record, $value) {
    if (strlen($value) < 5) {
        return 'Value must be at least 5 characters';
    }
    return ''; // Empty string = validation passed
});
```

## Available Extensions

### Audit Extension

Adds audit trail fields: `created_by`, `created_at`, `updated_by`, `updated_at`

See [Audit/README.md](Audit/README.md) for details.

## Best Practices

1. **Keep extensions focused**: Each extension should do one thing well
2. **Document your extensions**: Include a README.md with usage examples
3. **Use meaningful names**: Extension names should describe their purpose
4. **Test your extensions**: Ensure they work with different models
5. **Handle edge cases**: Check for null values, missing fields, etc.

## Error Handling

If an extension is not found, the system will throw an exception:

```
Exception: Extension 'ExtensionName' not found. Expected class: Extensions\ExtensionName\Model
```

To fix this:
1. Verify the extension folder exists in `milkadmin/Extensions/`
2. Verify the file is named correctly (`Model.php`, `Controller.php`, etc.)
3. Verify the class namespace matches: `namespace Extensions\ExtensionName;`
4. Run `composer dump-autoload` to regenerate autoloader

## Future Extensions

You can extend the extension system to support:
- Controller extensions
- Module extensions
- API extensions
- Shell command extensions
- Hook extensions

The same pattern applies: create the interface, modify the Abstract class, and implement extension loading.
