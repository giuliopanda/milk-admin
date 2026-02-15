<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title Introduction 
 * @guide developer
 * @order 10
 * @tags Introduction, Getting Started, Framework, MVC, MilkCore, hello-world, Route::set, _pt, module, theme, Response::theme_page, modules, milkadmin_local, lang, media, storage, config.php, functions.php, WordPress, Laravel, Bootstrap, admin-panel, PHP, CSRF, SQL-injection, cron-jobs, APIs, email, users, permissions, CRUD, webapp, installation, update, hasMeta, relationships, EAV, metadata
 */

 !defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Welcome to Milk Admin,</h1>
    <p class="text-muted">Revision: 2026/02/05</p>
    <p >a ready-to-use admin panel written in PHP and designed to support the work of developers. It manages cron jobs, allows you to create public APIs, emails, manages users, permissions and CRUD.<br>
Developed with a Bootstrap template and a lightweight and easy-to-learn framework for creating independent systems.<br>
The system offers an excellent level of automatic protection from CSRF and SQL Injection, as well as extensive initial documentation.<br>
Try it, the installation system is very fast and you can customize it for your needs. This way you can create your own webapp. Keeping it updated is even easier, you will only need, once you have finished making changes, a single shell command to create a new update version.
    </p>

    <h2>Key Concepts</h2>

    <h3>Modules: The Building Blocks</h3>
    <p>MilkAdmin is organized around <strong>Modules</strong> - self-contained components that handle specific functionality. Each module can include:</p>
    <ul>
        <li><strong>AbstractModule</strong> - The main class that configures the module (page name, menu, permissions, assets)</li>
        <li><strong>AbstractController</strong> - Handles routing and actions (optional, for complex modules)</li>
        <li><strong>AbstractModel</strong> - Manages database operations and schema definitions</li>
        <li><strong>Views</strong> - Template files for rendering HTML</li>
        <li><strong>Assets</strong> - CSS, JavaScript, and other resources</li>
    </ul>
    <p>The module system follows the MVC pattern, keeping your code organized and maintainable. See <a href="?page=docs&action=Developer/AbstractsClass/abstract-router">Abstract Module</a> for complete documentation.</p>

    <h3>Models: Your Database Layer</h3>
    <p>The <strong>Model</strong> system provides a powerful ORM-like interface for database operations:</p>
    <ul>
        <li><strong>Schema Definition</strong> - Define tables using fluent syntax: <code>$rule->string('name')->required()</code></li>
        <li><strong>CRUD Operations</strong> - Built-in methods (examples): <code>save()</code>, <code>fill()</code>, <code>getEmpty()</code>, <code>store()</code>, <code>getById()</code>, <code>delete()</code>, <code>getAll()</code> and many more</li>
        <li><strong>Query Builder</strong> - Chain conditions (examples): <code>where()->order()->limit()</code> and many more</li>
        <li><strong>Relationships</strong> - Support for <code>hasOne()</code>, <code>hasMany()</code>, <code>belongsTo()</code>, <code>hasMeta()</code></li>
        <li><strong>Flexible Metadata</strong> - EAV-style key/value fields via <code>hasMeta()</code> with automatic sync on <code>save()</code>/<code>delete()</code></li>
        <li><strong>Validation</strong> - Automatic validation based on schema rules</li>
    </ul>
    <p>Models handle table creation, updates, and migrations automatically. See <a href="?page=docs&action=Developer/GettingStarted/getting-started-model">Getting Started - Model</a> for a complete tutorial and <a href="?page=docs&action=Developer/Model/abstract-model-relationships">Model Relationships</a> for advanced relationship patterns including metadata.</p>

    <h3>Builders: Your Productivity Tools</h3>
    <p>MilkAdmin includes a set of <strong>Builder</strong> classes that speed up development by providing fluent, chainable interfaces for common tasks:</p>
    <ul>
        <li><strong><a href="?page=docs&action=Developer/Form/builders-form">FormBuilder</a></strong> - Create complete forms automatically from your Models with validation and actions</li>
        <li><strong><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder</a></strong> - Generate dynamic tables with sorting, filtering, pagination, and custom columns</li>
        <li><strong><a href="?page=docs&action=Developer/Builders/builders-links">LinksBuilder</a></strong> - Build navigation elements (navbar, breadcrumbs, sidebars, tabs) with automatic active state</li>
        <li><strong><a href="?page=docs&action=Developer/Builders/builders-title">TitleBuilder</a></strong> - Create consistent page headers with titles, buttons, and search functionality</li>
    </ul>
    <p>Builders replace verbose manual code with clean, readable method chains. For example, instead of manually creating form HTML and validation logic, you can use <code>FormBuilder::create($model)->getForm()</code> to get a complete working form instantly.</p>

    <h2>Install modules</h2>
    <p>First, you can try downloading new modules from the official repository <a href="https://www.milkadmin.org/download-modules/" target="_blank">https://www.milkadmin.org/download-modules/</a>. Once downloaded, you can install it by uploading the module directly from the administrative interface in the "installation" section.</p>

    <p>If you are creating a module and want to create or update the module tables, you can use</p>
     <pre><code class="language-shell">php milkadmin/cli.php module:install
php milkadmin/cli.php module:update </code></pre>
    <h2>Create a Hello World module</h2>

    <p>Create a file <code>milkadmin/Modules/HelloWorld/HelloWorldModule.php</code>:</p>
    <pre><code class="language-php">&lt;?php
namespace Modules\HelloWorld;

use App\Abstracts\AbstractModule;
use App\Attributes\RequestAction;
use App\Response;

class HelloWorldModule extends AbstractModule {

    protected function configure($rule): void
    {
        $rule->page('hello-world')
             ->title('Hello World')
             ->menu('Hello World', '', 'bi bi-bluesky', 10)
             ->access('registered');
    }

    #[RequestAction('home')]
    public function helloWorld() {
        Response::render('Hello World');
    }
}

// ⚠️ DO NOT ADD THIS LINE IN REAL MODULES!
// This is only for standalone example modules
new HelloWorldModule();
</code></pre>

<div class="alert alert-warning mt-3">
    <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Important: Module Initialization</h5>
    <p><strong>DO NOT add <code>new HelloWorldModule()</code> in your real modules!</strong></p>
    <p>The framework automatically loads and initializes all modules. Manual initialization causes errors like:</p>
    <ul class="mb-0">
        <li>Duplicate menu entries</li>
        <li>Duplicate routes</li>
        <li>Performance issues</li>
    </ul>
    <p class="mt-2 mb-0"><strong>Only use <code>new ModuleName()</code> for:</strong></p>
    <ul class="mb-0">
        <li>Standalone test modules (like this HelloWorld example)</li>
        <li>Modules that need to be loaded outside the standard flow</li>
    </ul>
</div>

<h4 class="mt-4">How does it work?</h4>

<p><strong>The configure() method</strong> uses a fluent interface to set up your module:</p>
<ul>
    <li><code>page('hello-world')</code> - Sets the URL parameter (?page=hello-world)</li>
    <li><code>title('Hello World')</code> - Sets the module title</li>
    <li><code>menu('Hello World', '', 'bi bi-bluesky', 10)</code> - Creates a sidebar menu entry with an icon and order</li>
    <li><code>access('registered')</code> - Requires registered users (other options: 'public', 'authorized', 'admin')</li>
</ul>

<p><strong>RequestAction attributes</strong> define which methods handle which URLs:</p>
<ul>
    <li><code>#[RequestAction('home')]</code> makes the method handle <code>?page=hello-world</code> (default action)</li>
    <li>The <code>Response</code> class renders the page content using the active theme</li>
</ul>

<p>For example, if you wanted to create a second page:</p>
<pre><code class="language-php">#[RequestAction('second-page')]
public function myCustomSecondPage() {
    Response::render('This is my second page');
}</code></pre>
<p>This would handle the URL <code>?page=hello-world&action=second-page</code>.</p>

<p class="alert alert-success">
    <strong>Learn More:</strong> See the complete guide at <a href="?page=docs&action=Developer/GettingStarted/creating-pages-and-links">Creating Pages and Links in Modules</a>
    to understand RequestAction, configure(), and how to structure complex modules.
</p>


<h2 class="mt-4">System structure</h2>
<h4 class="mt-4">Milk Admin</h4>
<p>Contains the core, modules and template of the project.</p>
<pre><code class="language-text">Milk Admin/
├── milkadmin_local/ # Space dedicated to customization of the distributed system  
├── app/ # The framework
├── modules/ # Here you find all the system code development
├── storage/ # Sqlite if used and other storage data
├── theme/ # All the graphics and html of the system 

</code></pre>
<h4 class="mt-4">milkadmin_local</h4>
<p>Contains the modifications for a specific installation.
It contains configuration files, media files, and possibly module override files.</p>
<pre><code class="language-text">milkadmin_local/
├── config.php # System parameters
├── functions.php # Functions
├── storage/ # Storage data (sqlite, json, keys, etc.)
├── media/ # Media
</code></pre>

<h4 class="mt-4">public_html</h4>
<p>Contains the site access files. The site must point to public_html/index.php and the API to public_html/api.php. This folder should not be modified in a normal project.</p>

<p class="alert alert-info">For WordPress lovers, inside milkadmin_local you will find functions.php which is the equivalent of functions.php in WordPress themes.</p>

</div>
