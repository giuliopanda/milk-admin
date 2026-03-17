# Official Test Suite

This folder contains only the official, publishable test suite from the repository.

The reference command is this:

```bash
php vendor/bin/phpunit -c tests/phpunit.xml tests/Unit
```

## Contents of the `tests` folder

Only the files required by the official suite are kept here:
- `tests/Unit/`: Official PHPUnit tests
- `tests/phpunit.xml`: Suite configuration
- `tests/bootstrap.php`: Shared test bootstrap
- `tests/README.md`: Suite documentation

## What the official suite covers

### `tests/Unit/App`
Covers the framework core and the behavior of the main application classes.

Includes tests on:
- global helpers and namespaced aliases
- config, dependency container, hook, route, request, response
- csrf, permissions, sanitization, logs, mail, language, settings, themes
- filesystem and file security
- database manager and connection management
- expression parser and expression-based validation
- model layer: queries, relations, `getByIdAndUpdate()`, `save()`, calculated fields, special fields, and persistence edge cases

### `tests/Unit/Builders`
Covers the framework's builders.

Includes tests on:
- `RuleBuilder`
- field definitions
- relations
- rules and metadata configuration
- parts of `TableBuilder`

### `tests/Unit/Extensions`
Covers extension loading and behaviors related to the extension loading system.

### `tests/Unit/Models`
Contains targeted tests on supporting models and stable documentation scenarios, such as timezone and `buildTable()`.

### `tests/Unit/Modules`
Covers services and components of the project's application modules.

### `tests/Unit/Theme`
Covers the behavior of the theme system.

## Purpose of the Suite

The official suite is used to verify the framework's stable contracts:
- Core public APIs
- Model and query builder behavior
- Record relationships and persistence
- Data validation and transformations
- Shared infrastructure components

## Material moved out of the official suite

All material not included in the official command has been moved to:

```text
workbench-tests/
```

Specifically, the following have been moved out of `tests/`:
- `GuidaModels/`
- `Validation/`
- `arrayQueryTests/`
- `expression_parser/`
- `query/`
- `relationships/`
- `schema/`
- Manual scripts such as `run-httpclient-basic.php` and `run-specific-test.php`

This area remains useful for exploratory testing, executable guides, countertests, debugging, and work materials, but is not part of the official publishable suite.