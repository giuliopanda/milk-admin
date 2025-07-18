<?php
namespace Modules\docs;
/**
 * @title  Introduction
 * @category Theme
 * @order 10
 * @tags theme, template-system, Bootstrap, UI-framework, theme-customization, header, sidebar, menu-management, breadcrumbs, menu, template, toast, modal, offcanvas, javascript, hooks, permissions, theme_page, theme_plugin, GET, Theme::set, Get::theme_page
 */
use MilkCore\Get;
use MilkCore\Route;
!defined('MILK_DIR') && die(); // Avoid direct access

$data = json_decode('{"labels":[0,1,2,3],"datasets":[{"data":["78","66","42","77"],"label":"age","type":"bar"},{"data":["60","70","67","80"],"label":"weight","type":"bar"}]}', true);

?>
<div class="bg-white p-4">
    <h1>Introduction</h1>

    <p>The graphic theme of this administrative system is based on bootstrap.</p>
    <p>The base system is very simple - the Route registers a function for each page and inside the function you can print a page within the theme by writing:</p>

    <pre class="bg-light p-2"><code class="language-php">Get::theme_page('default', __DIR__ . '/assets/my.page.php', ['title' => 'My Page' ...]);</code></pre>

    <p>This function loads the theme and prints the page indicated as the second parameter as content.</p>
    <p>The third parameter is an array of variables that are passed to the page.</p>

    <h2>Edit other theme elements</h2>
    <p>To modify other theme elements you can use the Theme::set() function.</p>
    <p>For example:</p>
    <pre class="bg-light p-2"><code class="language-php">Theme::set('header.title', 'My Page');</code></pre>
    <p>This function allows you to modify the header title.</p>

    <h5 class="mt-2">Adding a custom header link</h5>
    <p>For example:</p>
    <pre class="bg-light p-2"><code class="language-php">Theme::set('header.links', [ 'title' => 'My custom link', 'url' => Route::url('?page=my-custom-page&action=my-custom-action'), 'icon' => 'bi bi-cup-hot-fill', 'order' => 30]);</code></pre>
  
    <p>This function allows you to add a custom link to the header.</p>
    <div class="border p-1" style="max-width:500px">
        <img src="<?php echo BASE_URL ?>/modules/docs/assets/theme-header-link.jpg" alt="Header link" class="border w-100">
    </div>

    <h5 class="mt-2">Adding a sidebar menu link</h5>
    <div class="d-flex align-items-center">
        <div class="flex-shrink-0 me-3">
            <div class="border p-1" style="max-width:80px">
                <img src="<?php echo BASE_URL ?>/modules/docs/assets/theme-menu-sidebar.jpg" alt="Header link" class="border w-100">
            </div>
        </div>
        <div class="flex-grow-1">
            <pre class="bg-light p-2"><code class="language-php">Hooks::set('init', function($page) {
    if (Permissions::check('_user.is_admin')) {
        Theme::set('sidebar.links', [
            'url' => Route::url('?page=a-cup-of-milk'), 
            'title' => 'A cup of milk?', 
            'icon' => 'bi bi-cup-hot-fill', 
            'order' => 1
        ]);
    }
});</code></pre>
        </div>
    </div>

    <h5>breadcrumb</h5>
    <p>The space at the top left is called breadcrumb and is an html space that you can modify like this:</p>
    <pre class="bg-light p-2"><code class="language-php">Theme::set('header.breadcrumbs', 'my cup of milk > <a class="link-action" href="'.Route::url('?page=sugar').'">Sugar</a>');</code></pre>

    <h2 class="mt-2">Plugins</h2>
    <p>The theme has a plugins folder in which there are some HTML/JavaScript elements that can be used to extend the theme's functionality.</p>
    
    <p>For example the preloader:</p>
    <pre class="bg-light p-2"><code class="language-php">&lt;?php echo Get::theme_plugin('loading', ['id' => 'loading']); ?&gt;
&lt;script&gt;
document.addEventListener('DOMContentLoaded', function() {
    new Loading('.js-ito-loading').show();
});
&lt;/script&gt;</code></pre>
    <div style="width:100%; height:100px; position: relative;">
        <?php echo Get::theme_plugin('loading', ['id' => 'loading']); ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                new Loading('.js-ito-loading').show();
            });
        </script>
    </div>

    <h2 class="mt-2">Bootstrap components</h2>

    <p>The use of various bootstrap components has been simplified with JavaScript classes that activate their functionality</p>

    <ul>
    <li><a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/theme-modal.page'); ?>">Modal</a></li>
    <li><a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/theme-offcanvas.page'); ?>">Offcanvas</a></li>

    <li><a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/theme-toast.page'); ?>">Toasts</a></li>
    </ul>

    <h2 class="mt-2">Load theme page</h2>
    <p>Remember that once you've prepared the variables to load on a template to integrate everything into a theme page, you'll just need to write</p>
    
    <pre class="bg-light p-2"><code class="language-php">Get::theme_page('default', __DIR__ . '/cup-module/assets/milk.page.php', ['my_var'=>'drink']);</code></pre>

     <p>Inside your page you can call the my_var variable and print it simply by writing <code>_p($my_var);</code>. The _p function prints variables while sanitizing them. In general, always try to avoid printing using echo.</p>


    <h2 class="mt-2">Vanilla Javascript</h2>
    <p> <a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/theme-javascript.page'); ?>">The theme doesn't use JavaScript frameworks, but has a series of utilities to simplify development</a>, but if desired it's possible to extend the system with any JavaScript framework you want</p>
</div>