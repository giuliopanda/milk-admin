# Milk Admin

![Milk Admin](https://github.com/giuliopanda/repo/raw/main/milkadmin-img01.jpg)

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8%2B-purple.svg)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-supported-blue.svg)](https://www.mysql.com/)
[![SQLite](https://img.shields.io/badge/SQLite-supported-blue.svg)](https://www.sqlite.org/)
[![Status](https://img.shields.io/badge/Status-Beta-orange.svg)](https://github.com/giuliopanda/milk-admin)
[![Tests](https://img.shields.io/badge/Tests-PHPUnit-green.svg)]()
[![Static Analysis](https://img.shields.io/badge/PHPStan-Level%205-blue.svg)]()
[![Documentation](https://img.shields.io/badge/Docs-Online-green.svg)](https://milkadmin.org/milk-admin/?page=docs&action=Developer/GettingStarted/introduction)

---

**Milk Admin** is a PHP framework for building backoffice and internal management tools — without framework lock-in, SaaS dependencies, or vendor tie-ins.

> **No Laravel. No framework required. Runs on any shared hosting with PHP 8+ and MySQL or SQLite.**

Unlike Filament or Laravel Nova, Milk Admin is framework-agnostic: install it anywhere, extend it with plain PHP, and keep full control over your data and your code.

> **Project Status: Beta**
> The core architecture is stable. Actively developed toward a 1.0 release in mid-2026.

**Official links:**
[Website](https://www.milkadmin.org/) ·
[Documentation](https://milkadmin.org/milk-admin/?page=docs&action=Developer/GettingStarted/introduction) ·
[Live Demo](https://milkadmin.org/demo/?page=auth&action=login)

---

## Why Milk Admin?

Most PHP admin frameworks are either tied to a specific framework (Laravel, Symfony) or generate runtime interfaces from database-stored configuration. Milk Admin takes a different approach:

- **Framework-agnostic** — no Laravel, no Symfony required
- **Generates real PHP code** — the visual form builder creates actual PHP modules, not database configs
- **Relational data, not EAV** — every field maps to a real database column, keeping queries simple and data portable
- **Explicit over magic** — controllers describe business flows in plain PHP you can read, modify, and own

> *If you stop using Milk Admin, your code should still make sense.*

---

## The Form Builder That Generates Code

Most form builders store their configuration in the database and generate forms at runtime. Milk Admin is different.

The **Projects module** lets you design modules visually — tables, forms, field types, relations — and generates real PHP modules that you own completely.

![Form Builder Demo](https://www.milkadmin.org/assets/art-09-095.gif)

**How it works:**

- The builder generates PHP modules extended by JSON configuration files
- The JSON layer handles the builder's configuration updates
- The PHP code is always the final authority — modify, extend, or rewrite freely
- Both layers coexist: you can start with the builder and continue with code

This means you can **prototype rapidly** without losing the ability to add custom business logic later.

---

## Real Columns, Not EAV

Many admin frameworks and form builders use the EAV (Entity-Attribute-Value) pattern to store form data:

| entity_id | field | value |
|-----------|-------|-------|
| 10 | name | Mario |
| 10 | age | 42 |

This is flexible but creates complex queries, poor performance at scale, and difficult integrations.

Milk Admin stores each field as a **real database column**:

| id | name | age |
|----|------|-----|
| 10 | Mario | 42 |

The result: simpler SQL, better performance, and data you can query and export without decoding.

---

## When Milk Admin is a good fit

- You are building an **internal backoffice or management tool**
- Your data model is **relational and nested**
- You want **full control** over every part of the admin flow
- You need something that runs on **shared hosting** without a full framework stack
- You care about **long-term maintainability**

## When it might not be a good fit

- You need a public-facing website
- You want a full SPA-style frontend
- You are already deep in a Laravel or Symfony project (use Filament or EasyAdmin instead)

---

## Key Features

### Builder Classes

Generate CRUD interfaces from a single model definition:

- Tables with pagination, sorting, search
- Forms with validation and automatic persistence
- Filters, custom views, offcanvas editing
- Charts and dashboards *(in progress)*

### Visual Module Generator

Design and scaffold complete modules from the UI. Edit the generated PHP freely — the builder and your code coexist without conflicts.

### Modular Architecture

- Independent, encapsulated modules
- Install, disable, or extend easily
- Each module owns its models, controllers, and views

### Extensions System

- Local extensions (module-scoped)
- Global extensions (system-wide)
- Clean separation of concerns without over-engineering

### Ready-to-Use Admin UI

Built with **Bootstrap 5 + Vanilla JS**:

- Authentication system with brute-force protection
- User management and access control
- Role-based permissions with DAG support
- Session management and access logs
- Navigation and menus

---

## Technologies

- **Backend**: PHP 8+, Composer
- **Database**: MySQL / SQLite
- **Frontend**: Bootstrap 5, Vanilla JS
- **Template Engine**: Pure PHP

### Built-in Security

- CSRF protection
- SQL injection prevention
- XSS escaping helpers
- Brute-force protection
- Session hardening
- Granular access control
- API support (Token / JWT)

---

## Quick Start

**1. Clone the repository**
```bash
git clone https://github.com/giuliopanda/milk-admin
```

**2. Install dependencies**
```bash
composer install
```

**3. Open your browser**
```
http://localhost/milk-admin/public_html/
```

**4. Follow the installation wizard** — takes about 2 minutes

**5. Start building your first module**

---

## Example: Recipe Manager

Define the data model:

```php
namespace Local\Modules\Recipe;
use App\Abstracts\AbstractModel;

class RecipeModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__recipes')
            ->id()
            ->title('name')->index()
            ->text('ingredients')->formType('textarea')
            ->select('difficulty', ['Easy', 'Medium', 'Hard']);
    }
}
```

Define the module and controllers:

```php
namespace Local\Modules\Recipe;
use App\Abstracts\AbstractModule;
use App\Attributes\RequestAction;
use Builders\{TableBuilder, FormBuilder};
use App\Response;

class RecipeModule extends AbstractModule
{
    protected function configure($rule): void {
        $rule->page('recipes')
             ->title('My Recipes')
             ->menu('Recipes', '', 'bi bi-book', 10)
             ->access('registered')
             ->version(251101);
    }

    #[RequestAction('home')]
    public function recipesList() {
        $tableBuilder = TableBuilder::create($this->model, 'idTableRecipes')
            ->activeFetch()
            ->field('name')->link('?page='.$this->page.'&action=edit&id=%id%')
            ->setDefaultActions();

        $response = array_merge($this->getCommonData(), $tableBuilder->getResponse());
        Response::render(__DIR__ . '/Views/list_page.php', $response);
    }

    #[RequestAction('edit')]
    public function recipeEdit() {
        $response = array_merge(
            $this->getCommonData(),
            FormBuilder::create($this->model, $this->page)
                ->asOffcanvas()
                ->setTitle('New Recipe', 'Edit Recipe')
                ->dataListId('idTableRecipes')
                ->getResponse()
        );

        Response::json($response);
    }
}
```

This gives you a paginated table, full CRUD, search, validation, and automatic persistence — with the database table created and updated automatically.

### See it in Action

![Recipe Manager Demo](https://www.milkadmin.org/assets/art-05-anim-recipes03.gif)

---

## Code Quality

Milk Admin is validated with **PHPStan Level 5** and includes a structured **PHPUnit test suite** covering:

- Core: helpers, config, container, hooks, routing, request/response
- Security: CSRF, permissions, sanitization, filesystem protection
- Database: model layer, query builder, relations, persistence
- Builders: RuleBuilder, field definitions, TableBuilder
- Extensions: extension loading system
- Modules and theming

---

## Ready-Made Modules

Several modules are available for download:
[milkadmin.org/download-modules/](https://www.milkadmin.org/download-modules/)

---

## Designed for Long-Lived Systems

Milk Admin separates the framework core from your project code.

- The core updates independently
- Your modules, uploads, and configuration remain untouched
- Projects stay maintainable for years without rewrites

---

## Roadmap

**Target: stable 1.0 release by mid-2026**

- Improved stability and test coverage
- Advanced builders: charts, dashboards, reports
- Enhanced notification and email systems
- Two-factor authentication

See [changelog.md](./changelog.md) for recent changes.

---

## Join the Project

If you value control over convenience:

- ⭐ Star the repository
- Report bugs via [GitHub Issues](https://github.com/giuliopanda/milk-admin/issues)
- Share your use case
- Contribute improvements

---

## License

Released under the **MIT License** — free for personal and commercial use.

---

## Resources

- Website: [milkadmin.org](https://www.milkadmin.org/)
- Documentation: [milkadmin.org/milk-admin](https://milkadmin.org/milk-admin/?page=docs&action=Developer/GettingStarted/introduction)
- Live Demo: [milkadmin.org/demo](https://milkadmin.org/demo/?page=auth&action=login)
