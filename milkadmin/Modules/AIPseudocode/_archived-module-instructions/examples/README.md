# Code Examples

Real code snippets from existing modules.

## Module Examples

**module-basic.php** - Module configuration patterns:
- Simple module with single menu
- Public access module with JS
- Module with multiple models

**module-with-relationships.php** - Complete module with relationships:
- Module with multiple models (CORRECT addModels usage)
- belongsTo relationship (CORRECT placement)
- hasMany relationship
- Common mistakes to avoid
- Controller usage examples

## Controller Examples

**Controller-listPage.php** - List page pattern:
- TableBuilder usage
- SearchBuilder integration

**Controller-editPage.php** - Edit page pattern:
- FormBuilder usage

**Controller-modelMethods.php** - Model methods reference (IMPORTANT!):
- ✅ CORRECT methods: getAll(), getById(), query()->total()
- ❌ WRONG methods to avoid: all(), find(), count([])
- Getting, filtering, counting, saving, deleting
- Working with relationships
- Complete examples for all operations

## Model Examples

**model-basic-fields.php** - Basic field types:
- string, title, text, int, boolean, datetime, decimal

**model-list-enum.php** - List and enum fields:
- Static options
- Dynamic options from another model
- Event class example

**model-extensions.php** - Extensions usage:
- Audit, SoftDelete, Author
- Extension combinations
- Extension options

**model-validation.php** - Field validation:
- Title minimum length
- URL validation

**model-attributes.php** - PHP attributes usage:
- Validate, BeforeSave, ToDisplayValue, ToDatabaseValue

**model-complete.php** - Complete real models:
- PostsModel - extensions
- EventsModel - datetime and lists
- LinksDataModel - dynamic lists
- UserModel - complex fields
- PostsCommentModel - foreign keys

All examples are from actual working modules in milkadmin.
