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

**Milk Admin** is a PHP admin panel framework designed for building backoffice administration systems.

It focuses on **explicit control**, **relational CRUD flows**, and **long-term maintainability**, avoiding heavy abstractions and rigid architectures.

Milk Admin provides a stable backend core, modern PHP 8+ code, and minimal dependencies — allowing developers to focus on real business logic.

> **Project Status**  
> Milk Admin is currently in **Beta**.  
> The core architecture is stable and actively evolving.

**Official links:**  
[Website](https://www.milkadmin.org/) ·  
[Documentation](https://milkadmin.org/milk-admin/?page=docs&action=Developer/GettingStarted/introduction) ·  
[Live Demo](https://milkadmin.org/demo/?page=auth&action=login)

---

##  What's New in 0.9.7

- Codebase validated with **PHPStan Level 5**
-  Added **official PHPUnit test suite**
-  Improved internal consistency and validation coverage

---

## Static Analysis (PHPStan Level 5)

Milk Admin is validated using **PHPStan Level 5**, a strict static analysis level that ensures:

- Detection of **invalid types and method calls**
- Validation of **function signatures and return types**
- Prevention of **undefined variables and properties**
- Early detection of **logical inconsistencies**

Level 5 strikes a balance between **strictness and flexibility**, making the codebase more robust without slowing down development.

---

### Official Tests (`tests/`)

Milk Admin includes a structured testing system to ensure reliability and long-term stability.

The official PHPUnit suite validates the **core framework and stable contracts**:

- **App/Core**  Helpers, config, container, hooks, routing, request/response
- **Security**  CSRF, permissions, sanitization, filesystem protection
- **Database** Model layer, query builder, relations, persistence, special fields
- **Expression Parser**  Expression parsing and validation engine
- **Builders**  RuleBuilder, field definitions, TableBuilder
- **Extensions**  Extension loading system
- **Modules / Theme**  Module components and theming system

 These tests guarantee **framework stability and backward compatibility**

---

## When Milk Admin is a good fit

- You are building an **admin panel or internal backoffice**
- Your data model is **relational and nested**
- You want **full control over the admin flow**
- You prefer writing **plain PHP**
- You care about **long-term maintainability**

---

## When it might not be a good fit

- You need a public-facing website
- You want a full frontend framework
- You rely heavily on SPA-style interactivity

---

## Philosophy

Milk Admin embraces **explicit code over configuration**.

- Controllers describe business flows  
- Models enforce validation and integrity  
- Builders handle presentation  

If you stop using Milk Admin, your code should still make sense.

---

## Key Features

### Builder Classes

Create CRUD interfaces using structured builders:

- Tables, forms, lists
- Filters, search, custom views
- Charts and dashboards (in progress)

Minimal boilerplate, full control.

---

### Modular Architecture

- Independent modules
- Install / disable / extend easily
- Encapsulated logic (models, controllers, views)

---

### Extensions System

- Local extensions (module-scoped)
- Global extensions (system-wide)

Clean separation of concerns without over-engineering.

---

### Ready-to-Use Admin UI

Includes:

- Authentication system
- User management
- Navigation and menus
- Access control

Built with **Bootstrap 5 + Vanilla JS**

---

## Technologies

- **Backend**: PHP 8+, Composer
- **Database**: MySQL / SQLite
- **Frontend**: Bootstrap 5
- **Template Engine**: Pure PHP

### Built-in Features

- CSRF protection
- SQL injection prevention
- XSS escaping helpers
- Brute-force protection
- Web installer (WordPress-style)
- API support (Token / JWT)
- Cron jobs
- CLI tools

---

## Quick Start

1. **Clone the repository**
```bash
git clone https://github.com/giuliopanda/milk-admin
```

2. **Install dependencies**

```bash
composer install
```
Or for production:
```bash
composer install --no-dev
```

3. **Open your browser**

```
http://localhost/milk-admin/public_html/
```

4. **Follow the installation wizard** (2 minutes)

5. **Start building your first module**


---

## Example: Personal Recipe Manager

Define a model:

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

Define the module and controller logic:

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

This gives you pagination, full CRUD, search, validation, and automatic persistence.
Add features incrementally as your project grows.

### See it in Action

![Recipe Manager Demo](https://www.milkadmin.org/assets/art-05-anim-recipes03.gif)

The animation above demonstrates the Recipe Manager in action. With just a few lines of code, you get a fully functional admin interface with table display, offcanvas editing forms, instant CRUD operations, and automatic data validation.

---

## Modules

Several ready-made modules are available for download:
[https://www.milkadmin.org/download-modules/](https://www.milkadmin.org/download-modules/)

---

## Security Built-in

Milk Admin includes security features suitable even for personal and internal projects:

* CSRF protection
* SQL injection prevention
* XSS escaping helpers
* Brute-force protection
* Session hardening
* Granular access control

---

## Designed for long-lived admin systems

Milk Admin separates the framework core from project-specific code.

- The core system can be updated independently
- Custom modules, uploads, and configuration remain untouched
- Projects stay maintainable for years

This design is inspired by real-world systems that evolve over time, not throwaway applications.


## Current Status

**Alpha Release**

**What works well:**

* Stable core architecture
* Productive builder system
* Integrated security
* Active documentation

**Ideal for:** Personal projects, experimentation, learning
**Not yet recommended for:** Production or mission-critical systems

---

## Roadmap

Milk Admin aims to become a solid foundation for professional admin interfaces and internal tools.

**Focus areas:**

* Improved stability and test coverage
* Advanced builders (charts, dashboards, reports)
* Enhanced notification and email systems
* Two-factor authentication

**Target:** Stable 1.0 release by mid-2026.

---

## Notes

### Testing
- Unit tests and integration tests are not published in the project's Git, but used in development to ensure code quality.

### Vendor
- the vendor folder is included in git to allow installation even without shell access

## Join the Project

If you enjoy building your own tools and value control over convenience:

* Star the repository
* Report bugs via GitHub Issues
* Share your use cases
* Contribute improvements
* Spread the word

---

## License

Milk Admin is released under the **MIT License**.
Use it freely for personal or commercial projects.

---

## Resources

* Website: [https://www.milkadmin.org/](https://www.milkadmin.org/)
* Documentation: [https://milkadmin.org/milk-admin/?page=docs](https://milkadmin.org/milk-admin/?page=docs&action=Developer/GettingStarted/introduction)
