# Milk Admin

![Milk Admin](https://github.com/giuliopanda/repo/raw/main/milkadmin-img01.jpg)

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8%2B-purple.svg)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-supported-blue.svg)](https://www.mysql.com/)
[![SQLite](https://img.shields.io/badge/SQLite-supported-blue.svg)](https://www.sqlite.org/)
[![Status](https://img.shields.io/badge/Status-Beta-yellow.svg)](https://github.com/giuliopanda/milk-admin)
[![Documentation](https://img.shields.io/badge/Docs-Online-green.svg)](https://milkadmin.org/milk-admin-v251100/?page=docs&action=Developer/GettingStarted/introduction)

---

**Milk Admin** is designed to build **complex admin panels and backoffice software**.

It focuses on **explicit control**, **relational CRUD flows**, and **long-term maintainability**, avoiding heavy abstractions, and rigid resource-based architectures.

Milk Admin provides a stable and structured backend core, modern PHP 8+ code, and minimal dependencies — allowing developers to focus on real business logic instead of framework workarounds.

> **Project Status**  
> Milk Admin is currently in **Beta**.  
> The core architecture is stable, and the system is now being used in a real project.

**Official links:**  
[Website](https://www.milkadmin.org/) ·  
[Documentation](https://milkadmin.org/milk-admin-v251100/?page=docs&action=Developer/GettingStarted/introduction) ·  
[Live Demo](https://milkadmin.org/demo/?page=auth&action=login)

---

## When Milk Admin is a good fit

- You are building an **admin panel or internal backoffice**
- Your data model is **relational and nested**
- You want **full control over the admin flow**
- You prefer writing **plain PHP** over configuring resources
- You care about **long-term maintainability**

## When it might not be a good fit

- You need a public-facing website
- You want a full-stack frontend framework
- You rely heavily on Single Page Application - style interactivity

---

## Philosophy

Milk Admin embraces **explicit code over configuration**.

- Controllers describe real business flows  
- Models handle validation and data integrity  
- Builders handle presentation  

If you stop using Milk Admin tomorrow, your code should still make sense.

---

## Key Features

### Builder Classes

Dedicated builder classes simplify the creation of common CRUD components such as **tables, forms, lists, charts, searches, filters, and custom views**.

They provide a consistent and structured way to build admin interfaces with minimal boilerplate, while remaining fully customizable.

---

### Modular Architecture

Features are organized into **independent modules**, each containing its own models, controllers, and views.

Modules can be installed, disabled, updated, or extended individually.  
Ready-made downloadable modules are available to speed up development.

---

### Extensions System

Milk Admin includes an extension system that enhances existing modules with additional behavior.

Extensions can be:
- **Local**: scoped to a single module
- **Global**: reusable across the entire system

This allows clean separation of concerns without over-engineering.

---

### Ready-to-Use Admin UI

A complete Bootstrap-based admin interface is provided out of the box, including:

- Authentication and login pages
- User profile management
- Navigation and menu system
- Integrated access control

You can focus on backend logic without worrying about UI scaffolding.

---

## Technologies

- **Backend**: PHP 8+, Composer (minimal dependencies)
- **Database**: MySQL / SQLite
- **Frontend**: Bootstrap 5, Vanilla JavaScript
- **Template Engine**: Pure PHP (no template engine overhead)

### Built-in Features

- CSRF protection
- SQL injection prevention
- XSS escaping helpers
- Brute-force login protection
- Web-based installer (WordPress-style)
- Public API support (Token / JWT)
- Cron jobs and scheduled tasks
- CLI tools and shell commands


---

## Quick Start

1. **Clone the repository**
```bash
git clone https://github.com/giuliopanda/milk-admin
````

2. **Open your browser**

```
http://localhost/milk-admin/public_html/
```

3. **Follow the installation wizard** (2 minutes)

4. **Start building your first module**

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
**Beta Release (v0.9.0)**

If you're dealing with legacy code or evolving workflows that started in Excel, but backend development isn't your main expertise.

---

## Roadmap

Milk Admin aims to become a solid foundation for professional admin interfaces and internal tools.
-
**Focus areas:**

* Improved stability and test coverage 
* Advanced builders (charts, dashboards, reports) - Nearly finished
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
* Documentation: [https://milkadmin.org/milk-admin-v251100/](https://milkadmin.org/milk-admin-v251100/)
