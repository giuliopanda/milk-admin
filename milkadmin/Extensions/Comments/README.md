# Comments Extension

## Overview

The Comments extension adds a complete commenting system to any module in MilkAdmin. It provides a generic, reusable way to attach comments to any entity (recipes, posts, products, etc.) without writing custom code.

## Features

- **Generic and Reusable**: Works with any module and entity type
- **Dynamic Table Naming**: Automatically creates `{entity_table}_comments` tables
- **User Tracking**: Automatically tracks who created and last updated each comment
- **Timestamps**: Tracks creation and update timestamps
- **Rich UI**: Provides offcanvas view, sortable table, and modal edit form
- **Easy Integration**: Just add `->extensions(['Comments'])` to your module/model

## Installation

No installation needed - the extension is ready to use!

## Usage

### 1. Add Extension to Module

```php
class MyModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('mymodule')
            ->title('My Module')
            ->extensions(['Comments']);  // Add this line
    }
}
```

### 2. Add Extension to Model

```php
class MyModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__my_entities')
            ->id()
            ->string('name', 255)
            ->extensions(['Comments']);  // Add this line
    }
}
```

### 3. Add Comments Column to List View

```php
#[RequestAction('home')]
public function myList()
{
    $tableBuilder = TableBuilder::create($this->model, 'idTableMyEntities')
        ->activeFetch()
        ->field('name')
        ->extensions(['Comments'])  // Add this line - adds comments column
        ->setDefaultActions();

    Response::render(__DIR__ . '/Views/list_page.php', $tableBuilder->getResponse());
}
```

That's it! The Comments extension will automatically:
- Create a `#__my_entities_comments` table structure
- Add a "Comments" column with count and link
- Provide comment management UI (view, add, edit, delete)

## Database Schema

The extension automatically creates a comments table with this structure:

```sql
CREATE TABLE `{parent_table}_comments` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `entity_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Configuration Parameters

You can customize the extension behavior by passing parameters:

```php
// In Model
->addExtension('Comments', [
    'foreign_key' => 'entity_id',      // FK field name (default: entity_id)
    'entity_label' => 'Recipe',         // Label for the entity (default: Entity)
    'comment_field' => 'comment'        // Comment text field name (default: comment)
])
```

## UI Components

### Comments Column in List View

Shows the number of comments with a clickable link:
```
5 💬
```

Clicking opens the comments offcanvas.

### Comments Offcanvas

Displays:
- Entity name as title
- "New Comment" button
- Search bar
- Comments table with:
  - Comment text (truncated to 100 chars)
  - Created by user
  - Created at timestamp
  - Updated by user
  - Updated at timestamp
  - Edit/Delete actions

### Comment Edit Modal

Simple form with:
- Entity ID (readonly, hidden)
- Comment text (required textarea)
- Save button

## Automatic User Tracking

The extension automatically tracks:

- **created_by**: Set on first insert to current logged-in user
- **created_at**: Set on first insert to current timestamp
- **updated_by**: Updated on every save to current logged-in user
- **updated_at**: Updated on every save to current timestamp

No manual code needed!

## API Endpoints

The extension provides these actions:

- `?page={module}&action=comments&entity_id={id}` - View comments offcanvas
- `?page={module}&action=update-comment-table&entity_id={id}` - Refresh comments table
- `?page={module}&action=comment-edit&entity_id={id}[&id={comment_id}]` - Edit/create comment

## Example: Recipe Module

```php
// RecipeModule.php
class RecipeModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('recipes')
            ->title('My Recipes')
            ->extensions(['Comments']);
    }

    #[RequestAction('home')]
    public function recipesList()
    {
        $tableBuilder = TableBuilder::create($this->model, 'idTableRecipes')
            ->activeFetch()
            ->field('name')
            ->extensions(['Comments'])
            ->setDefaultActions();

        Response::render(__DIR__ . '/Views/list_page.php', $tableBuilder->getResponse());
    }
}

// RecipeModel.php
class RecipeModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__recipes')
            ->id()
            ->string('name', 255)
            ->text('ingredients')
            ->extensions(['Comments']);
    }
}
```

This creates a `#__recipes_comments` table and adds full comment functionality!

## Components

The extension consists of:

- **CommentsModel.php** - Generic model with dynamic table configuration
- **Model.php** - Model extension adding hasMany relationship
- **Module.php** - Module extension registering the CommentsModel
- **Controller.php** - Actions for viewing/editing comments
- **GetDataBuilder.php** - Adds comments column to list views
- **Service.php** - Shared logic for UI building

## Architecture

The extension uses a dynamic hook-based system similar to the Audit extension:

1. When `CommentsModel` is instantiated, it triggers `CommentsModel.configure` hook
2. The Model extension catches this hook and configures the table based on parent model
3. Table name is automatically: `{parent_table}_comments`
4. Foreign key links to parent entity via `entity_id`

This allows one generic CommentsModel to work with any parent entity!

## Best Practices

1. **Always add extension to both Module and Model** for full functionality
2. **Add `->extensions(['Comments'])` to TableBuilder** to show comments column
3. **Use descriptive entity_label** when customizing for better UX
4. **Comments are permanently deleted** - implement soft delete if needed

## Troubleshooting

**Comments column not showing?**
- Make sure you added `->extensions(['Comments'])` to TableBuilder

**Comments table not created?**
- The table is created dynamically when first accessed
- Check database permissions

**Can't see user names?**
- Requires UserModel with belongsTo relationship
- Check Auth system is configured

## Related Extensions

- **Author** - Similar tracking for created_by only
- **Audit** - Complete audit trail with history
- **SoftDelete** - Soft delete functionality

## Version History

- **v1.0.0** (2024-12-21) - Initial release
  - Generic comments system
  - Automatic user tracking
  - Dynamic table naming
  - Full CRUD UI
