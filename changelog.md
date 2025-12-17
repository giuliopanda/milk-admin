## Changelog

### v251217 
- Better handling of related tables
- Builder Improvements
- Compatibility with PHP 8.5
- medium bug fixes
- Improved documentation

### v251210 
- New: Ability to create modules in milkadmin_local with use Local/Modules.
- New: Extension management (example dev: Audit, Author, SoftDelete)
- Refactoring of the builderTable
- Minor and medium bug fixes
- Improved documentation


### v251124 
- Refactored error handling in framework classes
- Date formatting system refactor
- Implementation of the timezone field in users
- Language system refactor
- Introduction of the locate concept
- Ability to configure locate per user
- Development of the display system for lists as well as tables (ListBuilder)
- Development of the CalendarBuilder.
- Improved the invalid field handling system.
- Various bug fixes.


### v251101
Major rewrite introducing modern PHP practices and professional architecture:

**Core Changes:**
- Complete rewrite of abstract classes using PHP 8 attributes
- Added model relationships: HasOne, BelongsTo, HasMany
- Introduced Builder classes for rapid development (TableBuilder, FormBuilder)
- Added JSON-based JavaScript action handling for fetch calls
- Restructured folders: `public_html` separated from protected code/data
- Added Composer support
- Implemented i18n for JavaScript text

**Features:**
- "Remember me" option on login
- User profile page for logged-in users
- Force logout on all devices
- Page view logging for users
- Improved mobile responsiveness

**Breaking Changes:** Not backward compatible with previous versions due to architectural changes.

### v250801
- Module management: Hide modules that must stay active (install, auth)
- Install/update modules from admin interface
- Enable/disable modules without uninstalling
- Removed cron and api_registry from core (now installable separately)
- Improved CLI command display
- Added default sorting for tables
- Fixed: MySQL/SQLite installation, search filters, path resolution

### v250700
- Added hooks: `auth.user_list`, `install.copy_files`
- Version setting via CLI: `php cli.php build-version`
- Enhanced auth module (admin-only permissions)
- Improved query execution for MySQL/SQLite
- Multiple bug fixes (session timeout, toast notifications, date handling)
- Documentation improvements

### v250600
- Initial release

---