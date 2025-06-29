<?php
namespace Modules\docs;
/**
 * @title Introduction to the MilkCore Framework
 * @category Getting started
 * @order 10
 * @tags Introduction, Getting Started, Framework, MVC, MilkCore
 */

use MilkCore\Route;

 !defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Welcome to Milk Admin,</h1>
    <p >a ready-to-use admin panel written in PHP and designed to support the work of developers. It manages cron jobs, allows you to create public APIs, emails, manages users, permissions and CRUD.<br>
Developed with a Bootstrap template and a lightweight and easy-to-learn framework for creating independent systems.<br>
The system offers an excellent level of automatic protection from CSRF and SQL Injection, as well as extensive initial documentation.<br>
Try it, the installation system is very fast and you can customize it for your needs. This way you can create your own webapp. Keeping it updated is even easier, you will only need, once you have finished making changes, a single shell command to create a new update version.
    </p>
     
<p>It's a basic management system, easy to learn for those who already know Laravel or WordPress.</p>
    
    <h2>Hello World</h2>
    
    <p>Create a file modules/hello-world.controller.php 
    <pre><code class="language-php">use MilkCore\Route;
Route::set('hello-world', function() {
   _pt('Hello World');
});</code></pre>
<p>
<code>Route::set</code> registers a function that is executed when a page is called with the parameter page=hello-world<br>
<code>_pt</code> (print and translate) is a global function that allows you to print sanitized and translated text.</p>

<p>Now access <a href="<?php echo Route::url('?page=hello-world'); ?>">hello-world</a></p>

<p><b>You have created your first controller!</b></p>

<p>To create a controller, just create a file with {name}.controller.php extension and register it through the <code>Route::set</code> function.</p>
<p class="alert alert-info">You can create modules inside subfolders of modules. More advanced modules extend the abstractController and abstractRouter classes to automatically register or call methods within classes.</p>
   
<h2>The theme</h2>

<p>To call the theme you can use the Get::theme_page($page, $content = null, $variables = []) function. Let's modify our first module:</p>

<pre><code class="language-php">use MilkCore\Route;
use MilkCore\Get;

Route::set('hello-world', function() {
    Get::theme_page('default', '<h2>hello-world</h2>');
});
</code></pre>
    

<h2>System structure</h2>
    
<pre><code class="language-text">
Milk Admin/
├── customizations/ # Space dedicated to customizations of the distributed system
├── external-library/ # Downloaded libraries     
├── lang/
├── media/
├── milk-core/ # The framework
├── modules/ # Here you find all the system code development
├── storage/ # Sqlite if used and other storage data
├── theme/ # All the graphics and html of the system 
├── config.php # System parameters
    </code></pre>

<p class="alert alert-info">For WordPress lovers, inside customizations you will find functions.php which is the equivalent of functions.php in WordPress themes.</p>

</div>