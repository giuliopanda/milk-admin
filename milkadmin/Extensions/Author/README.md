# Author Extension

Automatically tracks who created each record by adding a `created_by` field to your models.

## Features

- ✅ Automatically adds `created_by` field to models
- ✅ Auto-populates with current user ID on record creation
- ✅ Configurable display: username or email
- ✅ Safe installation: preserves existing data on updates
- ✅ Lightweight and efficient with user data caching
- ✅ **NEW**: Permission-based access control - users can manage only their own records

## Usage

### Basic Usage (default: shows username)

```php
protected function configure(ModuleRuleBuilder $rule): void
{
    $rule->extensions(['Author']);
}
```

### Show Email Instead of Username

```php
protected function configure(ModuleRuleBuilder $rule): void
{
    $rule->extensions(['Author' => ['show_email' => true]]);
}
```

### Show Username (explicit)

```php
protected function configure(ModuleRuleBuilder $rule): void
{
    $rule->extensions(['Author' => ['show_username' => true]]);
}
```

### Hide from List Views

```php
protected function configure(ModuleRuleBuilder $rule): void
{
    $rule->extensions(['Author' => ['show_in_list' => false]]);
}
```

### Combined Configuration

```php
protected function configure(ModuleRuleBuilder $rule): void
{
    $rule->extensions([
        'Author' => [
            'show_email' => true,
            'show_in_list' => true
        ]
    ]);
}
```

## Configuration Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `show_username` | bool | `true` | Display username of the creator |
| `show_email` | bool | `false` | Display email instead of username |
| `show_in_list` | bool | `true` | Show author column in list views |

**Note**: If both `show_username` and `show_email` are true, `show_email` takes precedence.

## Database Schema

The extension adds the following column to your table:

```sql
created_by INT(11) NULL DEFAULT 0
```

## Installation

The extension automatically:
1. Adds the `created_by` column during module installation
2. Verifies/adds the column during module updates
3. Preserves the column and data during uninstallation (safe by default)

## How It Works

1. **On Record Creation**: Automatically sets `created_by` to the current user's ID
2. **On Record Update**: Preserves the original `created_by` value
3. **Display**: Fetches username/email from the users table and caches it for performance

## Example Output

When viewing records:
- With `show_username=true`: "john_doe"
- With `show_email=true`: "john@example.com"
- If user not found: "User #123"
- If no creator set: "-"

## Permissions

The extension adds the following permission:

### `manage_own_only` - Manage Only Own Records

When this permission is granted to a user:

1. **List/Table Views**: User sees only records they created
2. **Form Access**: User can only edit/delete their own records
3. **Access Control**: Attempting to access others' records redirects to deny page

### How to Use Permissions

1. **Grant the Permission**: Go to the Auth module and assign `[module].manage_own_only` permission to users/groups who should only manage their own records

2. **Example**: For a Posts module with Author extension:
   - Permission: `posts.manage_own_only`
   - Users with this permission will only see and edit posts they created
   - Users without this permission (but with other access) can manage all posts

### Permission Behavior

```php
// Administrator (has "_user.is_admin" permission):
// - ALWAYS sees all records (filter is not applied)
// - Can edit/delete all records
// - Administrators bypass the "manage_own_only" restriction

// User with "posts.manage_own_only" permission (non-admin):
// - Sees only posts where created_by = their user ID
// - Can edit/delete only their own posts
// - Cannot access edit forms for other users' posts

// User with "posts.access" but NOT "posts.manage_own_only":
// - Sees all posts
// - Can edit/delete all posts (if they have edit/delete permissions)
```

**Important**: Administrators (`_user.is_admin`) always bypass the `manage_own_only` filter and can view/edit all records, regardless of who created them.

## Requirements

- Requires a `#__users` table with `id`, `username`, and `email` columns
- Requires authentication system (`Get::make('Auth')`)
- Requires permission system (`Permissions::check()`)

## Notes

- The `created_by` field is nullable to support existing records
- User data is cached per request for optimal performance
- The column is preserved on uninstall to prevent accidental data loss
- Permission checks are performed on both list views (GetDataBuilder) and form access (FormBuilder)
- Access control is enforced server-side for security
