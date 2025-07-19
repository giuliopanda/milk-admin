# MILK ADMIN - A PHP, MySQL/sqlite, Bootstrap Admin Framework

See the site: [https://www.milkadmin.org](https://www.milkadmin.org)

![Milk Admin](https://github.com/giuliopanda/repo/blob/main/milkadmin-img01.jpg)

Welcome to Milk Admin, a ready-to-use admin panel written in PHP and designed to support the work of developers. It manages cron jobs, allows you to create public APIs, send emails, manages users, permissions and CRUD.
Developed with a Bootstrap template and a lightweight and easy-to-learn framework for creating independent systems.
The system offers an excellent level of automatic protection from CSRF and SQL Injection, as well as extensive initial documentation.
Try it, the installation system is very fast and you can customize it for your needs. This way you can create your own webapp. Keeping it updated is even easier, you will only need, once you have finished making changes, a single shell command to create a new update version.


# Develop your personal WEB APP, complete with versions and updates.  
Create your professional administrative interface without additional frameworks. The structure is simple, you can create new modules by writing all the code inside your own folder, prepare your custom package with installation and update system.
Once your project is ready, when clients ask for thousands of modifications, you can customize individual installations through view override or adding hooks inside the customization folder.


# How simple is it?

```php
Route::set('a_cup_of_milk', function() {
    Theme::set('javascript', Route::url().'/modules/a_cup_of_milk/assets/a_cup_of_milk.js');
     Get::theme_page('default', __DIR__ . '/assets/a_cup_of_milk.page.php');
}, '_user.is_authenticated');
```

This creates a page at the address ?page=a_cup_of_milk within the theme with custom javascript. Accessible only to authorized users.

If you want to add a new menu item in the sidebar, you just need to write:

```php 
 Theme::set('sidebar.links', ['url'=> '?page=a_cup_of_milk', 'title'=> 'A cup of milk', 'icon'=> 'bi-cup-hot-fill', 'order'=> 70] );
```

Is it simple enough? 


# Security
I tried to create a system with a high level of protection. The main features are:

Automatic CSRF for logged-in users and POST requests, and manual for other requests with a second level of protection.
SQL injection protection.
XSS protection.
Session timeout - I don't accept "stay logged in" and session controls with IP.
Login blocking after N failed attempts.
Registration is not present because it's designed for business or private use.
Various methods for protecting folders and files.
Public API or with JWT authentication

# Documentation
You can find detailed documentation inside the version you downloaded, within the docs module and a second documentation at [https://milkadmin.org/docs/](https://milkadmin.org/docs/)

You can see a demo at [https://milkadmin.org/demo/](https://milkadmin.org/demo/)

# License
Milk Admin is distributed under MIT license.

# Next Steps
In the demo I have already published some modules I'm working on. I hope to advance them and finish them by early 2026. 
The direction I want to give to this system is related to data monitoring and reporting.

# Changelog

## v1.1 (250700)
- feat: auth - Added hook 'auth.user_list' for modifying the user list and add 'install.copy_files' for skip directories in install process
- improve: Added the ability to set the version in add argument in php cli.php build-version
- improve: Auth module permissions limited to administrator only
- improve: compare_page_url add strict_check argument
- fix: api-registry error query clear logs
- fix: auth session timeout
- fix: module home httpClient
- improve: execute query function in mysql/sqlite
- fix: toast hide/show/hide, date in schema sqlite
- fix: sqlite/mysql create table date
- improve: add errors log in api-registry logs
- fix: homepage link in httpClient
- fix: auth.contract guest user
- doc: improve documentation

## v1.0 (250600)
- initial release