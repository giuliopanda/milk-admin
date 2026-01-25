## Changelog

### v260125
- New: ScheduleGridBuilder - Advanced grid system for schedule and planning visualization
- New: ScheduleGrid Theme Plugin with GridRenderer and interactive JavaScript components
- New: Version normalization system supporting both semver and legacy numeric versions
- New: CrudOperationsTrait methods: delete() without id (uses loaded record), deleteAll() for batch deletions
- New: RuleBuilder support for field-based min/max validation (e.g., end_date > start_date)
- Improved: RuleBuilder with enhanced min/max handling for strings (length) vs numbers
- Improved: RuleBuilder decimal() now uses correct 'number' formType
- Improved: RuleBuilder formParams() auto-syncs with pattern, minlength, maxlength validation rules
- Improved: SchemaAndValidationTrait with better nullable field handling (empty strings → null for numeric/date types)
- Improved: InstallationTrait now tracks and reports schema changes during module updates
- Improved: CalendarBuilder and ScheduleGridBuilder enhancements
- Improved: LinksBuilder, SearchBuilder, TitleBuilder refinements
- Improved: QueryBuilderTrait and Query.php optimizations
- Updated: AbstractModule and ModuleRuleBuilder to use normalized version strings
- Updated: Documentation for CrudOperationsTrait, AbstractModel, Builders, and Multi-builder system
- Updated: Processes Module documentation with comprehensive guide
- Fix: DataFormattingTrait nullable handling for numeric and date fields
- Fix: AbstractController query total calculation

### v260119
- New: ArrayDb - In-memory database system with SQL parser and query executor
- New: SQL Parser supporting SELECT, INSERT, UPDATE, DELETE with complex expressions (BETWEEN, CASE, CAST, EXISTS, IN)
- New: Query executor with JOIN support, aggregations, and subqueries
- New: VirtualTableTrait for handling virtual tables in models
- New: Events Module with calendar integration
- New: Multi-builder dynamic updates system (Documentation in `multi-builder-dynamic-updates.page.php`)
- New: ArrayDb documentation (`arraydb-models-builders.page.php`)
- Improved: GetDataBuilder with enhanced data processing
- Improved: TableBuilder, ListBuilder, CalendarBuilder, SearchBuilder, TitleBuilder
- Improved: Query system (InstallationTrait, QueryBuilderTrait, Query.php, Get.php)
- Updated: Database SQL and Get system documentation (`db-sql.page.php`, `get.page.php`)
- Updated: FakeCharts documentation and examples
- Removed: Obsolete .Recipe module and example extensions
- Fix: CrudOperationsTrait and various minor fixes in builders

### v260109
- New: Chart System (ChartBuilder) (Complete documentation in `chart-builder.page.php`)
- New: Scope and Query Attributes System (Documentation in `abstract-model-attributes.page.php`)
- New: DatabaseManager for Multi-Database (Updated complete documentation in `multi-database-support.page.php`)
- New: New attributes: `Query`, `DefaultQuery`
- Improved: AbstractModel and Relationships (Documentation `abstract-model-relationships.page.php` )
- Improved: RuleBuilder and Validation (Documentation in `abstract-model-rulebuilder.page.php`)
- Improved: Query Builder and Database (Updated documentation in `schema.page.php` )
- Improved: FormBuilder
- Improved: SearchBuilder Enhancements with filters on multiple dataBuilders
- Refactored filters and columns in TableBuilder and GetDataBuilder
- PHPMailer moved to vendor
- Improved: THEME AND UI
- Improvements to PHP attributes `Validate`, `SetValue`, `BeforeSave`
- Various fixes to `CrudOperationsTrait.php`
- Fix transaction handling in `Query.php`
- Fix filters in `SearchBuilder.php`
- Fix error handling in `DatabaseException.php`

### v251222
- SecurityBug: validateSecurePath()
- FixBug Transaction in Mysql
- FixBug extension installation
- FixBug User/Access Logs filter error
- Remove Deprecated Methods
- Add Comments Extensions


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
