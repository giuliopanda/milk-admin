## Changelog

### v0.9.8 - 26/03/31
- New: Introduced a complete password reset flow in `Modules/Auth` (reset attempts tracking, dedicated reset views, and related service/install updates).
- Improved: Refactored `Extensions/Projects` permission and manifest pipeline (`ProjectPermissionService`, manifest parser/index, route config) and list/view renderers.
- Improved: Enhanced `Modules/Projects` access-control behavior (`ProjectAdditionalAccessService`, unauthorized page flow, menu/catalog/table updates).
- Improved: Added explicit environment configuration support (`development | production`) with installer/theme integration updates.
- Security: Hardened redirects in `App/Route` with target sanitization (open-redirect/CRLF protections, host allowlist for absolute URLs) and stricter `urlsafeB64Decode()` handling.
- Security: Strengthened remember-me authentication in `Modules/Auth` with token rotation on login, replay-theft invalidation strategy, session ID regeneration on auto-login, and normalized `#__remember_tokens` queries.
- Improved: Refined login throttling in `LoginAttemptsModel` so username/session counters restart from last successful login time while IP limits remain time-window based.
- Improved: Simplified installer redirect in `install_from_zip.php` to a local relative target (`public_html`) to avoid host-header based redirect risks.
- Improved: Increased SQLite migration resilience (`SQLite` + `SchemaSqlite`) with safer error handling for `ALTER TABLE ... RENAME TO`, collision-resistant temp table names, and defensive table-existence checks.
- Improved: Updated Projects schema safeguards in `ProjectTableService` to allow only numeric-family cross-conversions (`string/int/decimal`) and log allowed/blocked transitions.
- Improved: Updated Projects draft review save flow (`review_form_fields_draft.page.php` + `ProjectsModule`) to submit via POST (`module/ref/draft`), with safer request resolution and submit-button lock on send.
- Fix: Resolved auth menu regression and corrected `buildTable` integer default handling in validation/schema flow.
- Updated: Aligned MySQL/SQLite schema boolean/default and field-diff comparison behavior used during schema update checks.
- Updated: Expanded framework/admin documentation (core docs, deployment/config notes, and UserRights guides).

### v0.9.7 - 26/03/17
- New: Added a new analytics layer in `milkadmin/App/Analytics` (`DataSet`, `DataSeries`, `AggregationEngine`, `AggregationPrimitives`, `GroupingPrimitives`) and a new `Request` class API.
- Improved: Refactored the Model/ORM save flow (`AbstractModel`, `CrudOperationsTrait`, `DataFormattingTrait`, `SchemaAndValidationTrait`, `QueryBuilderTrait`) with the new `RecordStateTrait` and explicit per-record `dirty/stale/original` tracking.
- Improved: `getEmpty()` now initializes pristine records (defaults are visible but not marked dirty); `save()` now recomputes `___action` deterministically (`insert`/`edit`/`null`) and realigns record state after persistence.
- Improved: `prepareData()` now persists only dirty/stale fields, insert defaults when needed, and always-managed special fields (`save_value`, `created_at`, `updated_at`, `created_by`, `updated_by`).
- Fix: Corrected nullable/default persistence behavior in the save flow (prevents unintended update overwrites, preserves explicit `null`, avoids unintended inserts for untouched fields).
- Fix: Corrected stale `calc_expr` handling for DB-loaded records (stale computed fields are marked and persisted as `edit`).
- Fix: Improved `getLastInsertId()` reliability by preferring IDs tracked in `save_results`.
- Updated: Added dedicated save-flow documentation in `Developer/Model/abstract-model-save-flow` (revision 2026/03/12).
- Improved: Updated the Builder/Form pipeline (`Form.php`, `ObjectToForm`, `FormBuilder`, `GetDataBuilder`, `TableBuilder`, FormBuilder traits) and theme plugins (MilkSelect/Table/List/Upload, BeautySelect cleanup).
- Improved: Hardened core behavior in `Sanitize`, `File`, `CSRFProtection`, `Database/*`, SQL/Expression parsers, and `NamespacedFunctionAliases`.
- Updated: Refined core extensions (`Projects`, `Audit`, `Author`, `Comments`, `SoftDelete`) and removed legacy files.
- Tests: Added and consolidated the official PHPUnit suite in `tests/`, covering core framework behavior (App layer), Model/ORM and database save flows, Builders, Extensions, Theme plugins, and key integration paths. Test result: `php vendor/bin/phpunit -c tests/phpunit.xml tests/Unit` -> **OK (461 tests, 1867 assertions)**.
- PHPStan: Codebase refactoring was aligned with static-analysis standards at **PHPStan level 5**, with stricter typing/flow consistency, safer nullability handling, and cleaner contracts across core classes and test code. Static analysis result: `php vendor/bin/phpstan analyse --configuration=phpstan.neon` -> **No errors**.

### v0.9.6 - 26/03/13
- Removed: Vendor directory.

### v0.9.5 - 26/03/05
- New: Added a new visual management layer for milkadmin/Extensions/Projects, making the Projects extension easier to configure and control from the UI.
- Updated: Documentation pages across Developer/Framework/User sections (Projects guides, DynamicTable, hooks/lang/mail, links, cron, timezone/sidebar notes)
- Improved: Install module update workflow refinements in `InstallService`

### v0.9.4 - 26/03/02
- Improved: Core form pipeline (`Form.php`, `ObjectToForm`, `FormFieldManagementTrait`, `SchemaAndValidationTrait`) and editor/theme JS integration
- Improved: Theme plugins (MilkSelect, Table, List, UploadFiles, UploadImages, Editor) and related CSS/JS behavior
- Improved: `InstallationTrait`, `GetDataBuilder` action processing, and list/table response builders
- Fix: Query/data formatting edge cases (`Query.php`, `DataFormattingTrait`) and project model parsing compatibility

### v0.9.3 - 26/02/23
- New: Audit field types `updated_at()`, `created_by()`, `updated_by()` in RuleBuilder with automatic population; new `USERID()` expression function (PHP + JS)
- New: Module instance registry (`AbstractModule::getInstance()`, `getAllInstances()`, `getAllModels()`)
- New: UploadFiles/UploadImages plugins: drag-and-drop sortable order (`sortable()`) and download button (`downloadLink()`)
- New: Multiple selection support for list/select fields (JSON array storage) and `allow_empty` option for deselection
- New: `where()` condition support on `belongsTo` relationship search
- New: sidebar layout support in `list_page.php`
- New: "Update core only" option in Install module (updates only `milkadmin/` by default, optionally includes `milkadmin_local/`, `vendor/`, `public_html/`)
- Improved: CSRF hardening - token binds to user/session ID, client-side injection at submit time, failure preserves page context
- Improved: Upload plugins guard against re-initialization, support `updateContainer` event; FormSystemOperationsTrait handles edge cases for temp files
- Improved: ObjectToForm auto-switches to milkSelect for lists with 25+ options; FieldFirstTrait enhanced file/image display in tables
- Fix: New records with calculated fields were incorrectly saved as edit instead of insert (`rebuildActions()` in CrudOperationsTrait)
- Fix: Uninitialized `$save_permissions` in UserModel; unreachable code in Route.php; stale `console.log` in image uploader
- Security fix: `milkadmin_local/storage/.htaccess` added with `Require all denied` to block direct browser access to storage files

### v0.9.2 - 26/02/15
- New: ExpressionParser refactored into separate classes (Lexer, Parser, Evaluator, BuiltinFunctions, TokenType, ValueHelper)
- New: `hasMeta` relationship type in RelationshipsTrait with EAV pattern support and batched queries
- New: MetaSaveTrait - automatic saving of hasMeta fields integrated into model save workflow
- Improved: RelationshipsTrait and RelationshipDataHandlerTrait with hasMeta batched query support
- Improved: TitleBuilder with fetch title support in JSON
- Improved: TableBuilder and GetDataBuilder (DataProcessor, ColumnManager, FieldFirstTrait)
- Improved: FormBuilder, FormFieldManagementTrait, FormSystemOperationsTrait
- Improved: RuleBuilder with extended rules support
- Improved: RouteControllerTrait with major refactoring
- Improved: AbstractModel, AbstractModule, AbstractController
- Improved: SchemaMysql with major refactoring
- Improved: Form.php and ObjectToForm
- Improved: Theme assets (form.js, milk-form.js, ajax-handler.js, theme.css, theme.js)
- Improved: MilkSelect plugin and Table plugin
- Fix: Cache bug in ExtensionLoader
- Updated: Documentation - AbstractModel fully documented (abstract-model, crud, queries, relationships, attributes, rulebuilder, controller)
- Updated: Documentation - Form docs (builders-form, fields, validation, conditional-visibility)
- Updated: Documentation - builders-table, theme-json-actions, expressions (syntax, examples, API)

### v0.9.1 - 26/02/03
- New: Expression system (PHP + JS) with `ExpressionParser` and frontend parser, plus docs (overview, syntax, API, examples)
- New: FormBuilder expression helpers (calcExpr, defaultExpr, validateExpr, requireIf, showIf) with `milk-form.js`
- New: ModelValidator for expression-based required/validation rules
- Improved: RuleBuilder/ObjectToForm/FormBuilder traits for expression mapping and conditional visibility
- Improved: AbstractModel / QueryBuilder / ArrayDb refinements and documentation
- Improved: ScheduleGrid and MilkSelect plugins plus related docs
- bug fix: schema database update
- bug fix: small bugs in save data
- Updated: Documentation

### v0.9.0 - 26/01/25
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
