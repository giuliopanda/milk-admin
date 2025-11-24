# Milk Admin

![Milk Admin](https://github.com/giuliopanda/repo/raw/main/milkadmin-img01.jpg)

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8%2B-purple.svg)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-supported-blue.svg)](https://www.mysql.com/)
[![SQLite](https://img.shields.io/badge/SQLite-supported-blue.svg)](https://www.sqlite.org/)
[![Status](https://img.shields.io/badge/Status-Alpha-orange.svg)](https://github.com/giuliopanda/milk-admin)
[![Documentation](https://img.shields.io/badge/Docs-Online-green.svg)](https://milkadmin.org/milk-admin-v251100/?page=docs&action=Developer/GettingStarted/introduction)

**Milk Admin** is a lightweight PHP framework designed to create administration interfaces that complement existing systems. Think of it as a way to quickly build custom admin panels that integrate with your applications - similar to how WordPress provides an admin interface, but lighter and more flexible.

**However**, we're currently in Alpha. While the goal is to power professional admin interfaces, right now it's perfect for something else: **building tools for yourself**.

 **[Official Website](https://www.milkadmin.org/)** |  **[Documentation](https://milkadmin.org/milk-admin-v251100/?page=docs&action=Developer/GettingStarted/introduction)** |  **[Live Demo](https://milkadmin.org/demo/?page=auth&action=login)**

---

## Build Tools for Yourself

Milk Admin is perfect for **PHP developers** who want to create their own systems. Some ideas:

**Personal data** - Library catalogs, recipe databases, media collections with your notes  
**Knowledge management** - Your own Notion/Evernote alternative, code snippets, research notes  
**Life tracking** - Expense tracker, workout log, habit tracker without sharing data  
**Projects** - Side project dashboard, client portal for freelancers, learning progress tracker  

**The key advantage**: You own your data, customize everything, no subscriptions, complete privacy.

> **Reality check**: This is in Alpha. It's perfect for personal projects and experimentation, but not ready for production or client work. That's actually a feature - you can learn and break things without consequences.

---

## Key Features

* **PHP 8+ Framework** - Clean, modern code that's easy to read and modify
* **Ready-to-use Interface** - Complete admin template, just add your modules
* **Web-based Setup** - Install like WordPress, manage via browser or CLI
* **Dual Database** - MySQL for power, SQLite for simplicity
* **Builder Classes** - Generate tables, forms, and searches in seconds
* **Built-in Security** - CSRF, SQL Injection, XSS, brute force protection
* **User Management** - Login, permissions, session handling included
* **Public API** - Token or JWT authentication ready
* **Cron Jobs** - Schedule automated tasks

## Technologies

- **Backend**: PHP 8+, Composer (minimal dependencies)
- **Database**: MySQL / SQLite  
- **Frontend**: Bootstrap 5, Vanilla JavaScript (no jQuery, no framework)
- **Template**: Pure PHP (no template engine overhead)

---

## Quick Start

1. **Clone and go**
```bash
git clone https://github.com/giuliopanda/milk-admin
```

2. **Open browser** at `http://localhost/milk-admin/public_html/`

3. **Follow installation wizard** (2 minutes)

4. **Start building** your first module

---

## Example: Personal Recipe Manager

Build a complete recipe management system in minutes:

```php
class RecipesModel extends AbstractModel
{
   protected function configure($rule): void
   {
        $rule->table('#__recipes')
            ->id()
            ->title('name')->index()
            ->text('ingredients')->formType('textarea')
            ->text('instructions')->formType('editor')
            ->integer('prep_time')->label('Prep Time (min)')
            ->integer('cook_time')->label('Cook Time (min)')
            ->select('difficulty')->options(['Easy', 'Medium', 'Hard'])
            ->text('tags')
            ->integer('rating')
            ->created_at()->hideFromEdit()
            ->datetime('last_cooked')->nullable();
   }
}
```

```php
class RecipesModule extends AbstractModule
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
        $response = ['page' => $this->page, 'title' => $this->title];
        $response['html'] = TableBuilder::create($this->model, 'idTableRecipes')
            ->asLink('name', '?page='.$this->page.'&action=edit&id=%id%')
            ->setDefaultActions()
            ->render();
        Response::render(__DIR__ . '/Views/list_page.php', $response);
    }

    #[RequestAction('edit')]
    public function recipeEdit() {
        $response = ['page' => $this->page, 'title' => $this->title];
        $response['form'] = FormBuilder::create($this->model, $this->page)->getForm();
        Response::render(__DIR__ . '/Views/edit_page.php', $response);
    }
}
```

This gives you: paginated list, full CRUD, search, validation, and automatic save. **Add features as you need them.**

---

## Security Built-in

Personal projects need security too:

- **CSRF Protection** - Automatic tokens for all forms
- **SQL Injection** - Parameterized queries throughout
- **XSS Prevention** - Output escaping functions
- **Brute Force** - Login attempt limiting
- **Session Security** - Timeout, IP validation
- **Access Control** - Granular permissions per module

---

## Current Status

 **Alpha Release** - Functional and usable, but evolving

**What works well:**
- Core framework is stable
- Builders accelerate development significantly  
- Security features implemented and tested
- Documentation covers the basics

**What needs work:**
- Error handling could be more elegant
- Test coverage is incomplete
- Some edge cases need refinement
- Documentation still expanding

**Perfect for**: Personal projects, learning, experimentation  
**Not ready for**: Production systems, client projects, mission-critical apps

---

## What's Coming

I'm currently working on several modules that will be available in the demo by early 2026. The development direction is focused on **data monitoring and reporting** - turning Milk Admin into a tool for building dashboards and analytics interfaces alongside CRUD operations.

---

## Roadmap

### The Bigger Vision

Milk Admin is being built to create **professional admin interfaces** that integrate with existing systems - CRMs, e-commerce platforms, custom applications. The kind of tool you'd use to build a backend panel for your clients or your company's internal tools.

**But we're not there yet.** The Alpha status means some rough edges, incomplete test coverage, and evolving documentation.

That's why we're starting with **personal projects**. They're the perfect testing ground: real use cases, but with the freedom to experiment. As developers build their own tools and report issues, the framework matures.

**The path forward:**
1. **Now (Alpha)**: Personal projects, experimentation, learning
2. **2025-2026**: Stability improvements, complete testing, professional features
3. **1.0 Release**: Ready for client work and production systems

###  Core Improvements (2025)
- [ ] **Robust error handling** with helpful messages
- [ ] **Comprehensive test suite** for confidence
- [ ] **Enhanced builders**:
  - [ ] List view (non-tabular layouts)
  - [ ] Calendar widget
  - [ ] Chart components for dashboards
- [ ] **Advanced email system** with templates
- [ ] **Two-factor authentication**

###  Future Explorations
Features driven by real use cases. Priority TBD based on community feedback:
- Data import/export tools
- File upload management
- Advanced reporting
- Complex form builders
- Mobile-responsive enhancements
- Plugin architecture

**Goal**: Stable 1.0 release by mid-2026

---

## Join the Project

If you like building your own tools:

- **Star the repo** to show interest and help it grow
- **Report bugs** via [GitHub Issues](https://github.com/giuliopanda/milk-admin/issues)
- **Share your use case** - what are you building?
- **Contribute** improvements (when you're ready)
- **Spread the word** to other developers who value ownership

---

## License

Milk Admin is distributed under the [MIT License](LICENSE). Build whatever you want.

---

## Changelog

### v251102 (Current)
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

## Resources

-  [Official Website](https://www.milkadmin.org/)
-  [Documentation](https://milkadmin.org/milk-admin-v251100/?page=docs&action=Developer/GettingStarted/introduction)
-  [Live Demo](https://milkadmin.org/demo/)
-  [GitHub Repository](https://github.com/giuliopanda/milk-admin)

---

## Ready to Start?


Pick one thing you're currently tracking in a spreadsheet or paying a subscription for. Build your own version this weekend.
