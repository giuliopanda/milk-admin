# Audit Extension

## Description
The Audit extension automatically tracks all changes to your model records in a separate audit table. It creates a complete audit trail storing full record snapshots for every insert, edit, and delete operation, along with user and timestamp information.

## How It Works

The extension creates a separate audit table (e.g., `posts_audit` for a `posts` table) that stores:
- Complete snapshots of your records on every change
- `audit_id`: Primary key for the audit table
- `audit_record_id`: ID of the original record
- `audit_action`: Type of action (insert, edit, delete)
- `audit_timestamp`: When the action occurred
- `audit_user_id`: User who performed the action
- All fields from the original record at the time of the action

## Usage

In your Model class, add the extension to the `$extensions` array:

```php
<?php
namespace Modules\Posts;
use App\Abstracts\AbstractModel;

class PostsModel extends AbstractModel
{
    protected array $extensions = ['Audit'];

    protected function configure($rule): void
    {
        $rule->table('#__posts')
            ->id()
            ->title()->index()
            ->text('content')->formType('editor');

        // The Audit extension will automatically:
        // - Create a posts_audit table
        // - Track all insert, edit, and delete operations
        // - Store complete record snapshots with user and timestamp
    }
}
```

## Features

### Automatic Change Tracking
- Every insert, edit, or delete operation creates a new audit record
- Complete record data is stored (not just changes)
- User ID and timestamp are automatically captured

### Smart Duplicate Detection
- Consecutive identical saves are automatically deduplicated
- Session-based consolidation removes intermediate edits within a time window
- Keeps only meaningful changes in the audit trail

### Automatic Audit Trail Cleanup
The extension includes an automatic cleanup system that keeps only the most recent N **EDIT** records per record_id:

- **Configurable Limit**: Set max EDIT records to keep (default: unlimited)
- **Automatic Cleanup**: Old EDIT records are deleted automatically after each save
- **Per-Record Basis**: Each record_id has its own independent audit history
- **Database Optimization**: Prevents audit tables from growing too large
- **INSERT Preservation**: The initial INSERT record is **ALWAYS** preserved and **NOT** counted in the limit

#### Configuration

In your `Module.php` file's `bootstrap()` method, configure the limit:

```php
public function bootstrap(): void
{
    // ... other code ...

    // Keep only last 10 audit records per record_id
    \Extensions\Audit\Model::setMaxAuditRecords(10);
}
```

**Configuration Options:**
```php
// Unlimited audit history (default)
\Extensions\Audit\Model::setMaxAuditRecords(0);

// Keep only last 5 versions
\Extensions\Audit\Model::setMaxAuditRecords(5);

// Keep only last 10 versions (recommended for most use cases)
\Extensions\Audit\Model::setMaxAuditRecords(10);

// Keep only last 50 versions
\Extensions\Audit\Model::setMaxAuditRecords(50);
```

#### How It Works

1. Every save/edit/delete operation creates a new audit record
2. After creating the record, cleanup runs automatically
3. If the total **EDIT** records for that record_id exceed the limit, the oldest EDIT records are deleted
4. Only the most recent N EDIT versions are kept
5. The initial INSERT record is **NEVER** deleted and **NOT** counted in the limit

**Example with limit = 10:**
- Record #5 is edited for the 11th time
- A new audit record is created (total: 1 INSERT + 11 EDIT)
- Cleanup deletes the oldest EDIT record
- Result: 1 INSERT (always preserved) + 10 most recent EDIT records remain

#### Recommended Limits

**Important**: The limit applies only to EDIT records. The INSERT record is always kept.

| Use Case | Limit | What You Get | Reason |
|----------|-------|--------------|---------|
| High-traffic systems | 10-20 | INSERT + 10-20 EDITs | Balance history and performance |
| Medium-traffic | 20-50 | INSERT + 20-50 EDITs | More history retention |
| Critical data | 0 (unlimited) | All records | Keep complete audit trail |
| Testing/Development | 5-10 | INSERT + 5-10 EDITs | Easy to verify cleanup |

#### Testing the Cleanup

Run the test script to verify the cleanup works:

```bash
php test_audit_cleanup.php
```

Or test manually:
1. Set a limit (e.g., 5 records)
2. Edit a record multiple times (more than 5)
3. Check the Audit Trail page - only the last 5 versions will appear

## Customization

To customize the extension behavior, you can:

1. Override the handlers in your Model after construction
2. Modify the extension file directly for global changes
3. Create a new extension based on this one with different behavior

## Database Schema

The extension automatically creates the audit table with your model's schema plus audit-specific fields. **No changes are needed to your main table** - all audit data is stored separately.

The audit table structure:
```sql
-- Example for a posts table -> posts_audit
audit_id INT PRIMARY KEY AUTO_INCREMENT,
audit_record_id INT NOT NULL,           -- ID of the original record
audit_action VARCHAR(10) NOT NULL,      -- 'insert', 'edit', 'delete'
audit_timestamp TIMESTAMP NOT NULL,     -- When the action occurred
audit_user_id INT,                      -- User who performed the action
-- ... all fields from the original posts table ...
```

## Viewing Audit History

Access audit trail via the controller actions:
- `?page=yourpage&action=audit` - View complete audit log
- `?page=yourpage&action=audit-view&id=123` - View history for a specific record
- Restore previous versions with a single click
