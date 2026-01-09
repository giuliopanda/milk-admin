<?php
namespace Modules\Docs\Pages;
/**
 * @title Creating Pages and Links in Modules
 * @guide developer
 * @order 20
 * @tags modules, pages, links, RequestAction, bootstrap, module, routing, menu, navigation, MyPages, tutorial, configure, english
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Creating Pages and Links in Modules</h1>
    <p class="text-muted">Revision: 2025/10/16</p>
    <p class="text-muted">How to use the MyPages module to create custom pages with menus and navigation</p>

    <p>This guide shows how to create pages and navigation systems within MilkAdmin modules using the practical example of the <code>MyPages</code> module.</p>

    <h2>Basic Module Structure</h2>

    <p>A MilkAdmin module is composed of:</p>
    <ul>
        <li><strong>Module</strong>: Handles the logic and actions of the module</li>
        <li><strong>Controller</strong> (optional): For complex routing</li>
        <li><strong>Views</strong>: Templates for visualization</li>
        <li><strong>Assets</strong> (optional): CSS, JS, images</li>
    </ul>

    <h2>Step 1: Basic Module with RequestAction</h2>

    <p>Let's start by creating a simple module without a separate router. The directory must be structured like this:</p>

    <pre><code class="language-text">milkadmin/Modules/MyPages/
├── MyPagesModule.php
└── views/
    └── my_view_page.php</code></pre>

    <h3>Basic Module</h3>

    <p>The module <code>MyPagesModule.php</code> shows how to use <code>RequestAction</code> attributes to define pages and the new <code>configure()</code> method:</p>

    <pre><code class="language-php">&lt;?php
namespace Modules\MyPages;

use App\Abstracts\AbstractModule;
use App\Attributes\RequestAction;
use App\Response;
use Builders\LinksBuilder;

class MyPagesModule extends AbstractModule {

    protected function configure($rule): void
    {
        $rule->page('mypages')
             ->title('My Custom Pages')
             ->menu('My Custom Pages', '', 'bi bi-file-earmark-post-fill', 10)
             ->access('registered')
    }

     #[RequestAction('home')]
    public function my_home_page() {
        Response::render(__DIR__ . '/views/my_post_page.php', ['title' => 'My Home Page']);
    }
}</code></pre>

    <h3>Key Concepts</h3>

    <h4>The configure() Method</h4>
    <p>The <code>configure($rule)</code> method uses a fluent interface to set up the module:</p>
    <ul>
        <li><code>page('mypages')</code>: Unique identifier in the URL</li>
        <li><code>title('My Custom Pages')</code>: Module title</li>
        <li><code>menu()</code>: Adds entries to the sidebar menu</li>
        <li><code>access('registered')</code>: Sets access level (registered, admin, public)</li>
    </ul>

    <h4>The RequestAction Attribute</h4>
    <p>The <code>#[RequestAction('action_name')]</code> attribute defines actions accessible via URL:</p>
    <ul>
        <li>Syntax: <code>#[RequestAction('page1')]</code></li>
        <li>Resulting URL: <code>?page=mypages&action=page1</code></li>
        <li>When no separate Controller is present, this attribute allows defining pages directly in the module</li>
    </ul>

    <h4>The bootstrap() Function</h4>
    <p>The <code>bootstrap()</code> function is fundamental for configuring common elements:</p>
    <ul>
        <li>Called <strong>every time</strong> the module is loaded</li>
        <li>Used for configurations common to all pages</li>
        <li>Ideal for loading custom CSS/JS (though you can also use <code>setJs()</code> and <code>setCss()</code> in configure())</li>
        <li>JavaScript and CSS files, header links are now configured automatically via configure()</li>
    </ul>

    <h2>Basic View</h2>

    <p>Create <code>views/my_view_page.php</code> to display pages:</p>

    <pre><code class="language-php">&lt;?php !defined('MILK_DIR') && die(); // Prevent direct access ?&gt;
&lt;div class="container mt-4"&gt;
    &lt;div class="card"&gt;
        &lt;div class="card-header"&gt;
            &lt;h3&gt;&lt;?php _p($title); ?&gt;&lt;/h3&gt;
        &lt;/div&gt;
        &lt;div class="card-body"&gt;
            &lt;p&gt;This is an example page from the MyPages module.&lt;/p&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>

    <h2>Step 2: Adding a Controller for Complex Pages</h2>

    <p>The module is already functional. However, now let's create another group of pages with a sidebar menu.
        First, let's create another file called <code>MyPagesController.php</code></p>
    <p><u>Now we need to move the Functions from Module to Controller</u></p>

    <pre><code class="language-php">&lt;?php
namespace Modules\MyPages;

use App\Abstracts\AbstractController;
use App\Attributes\RequestAction;
use App\Response;
use Builders\LinksBuilder;

class MyPagesController extends AbstractController {
     #[RequestAction('home')]
    public function my_home_page() {
        Response::render(__DIR__ . '/views/my_post_page.php', ['title' => 'My Home Page']);
    }

    #[RequestAction('other-page')]
    public function my_page_2() {
        Response::render(__DIR__ . '/views/vertical_menu.php', ['title' => 'My other-page', 'links' => $this->links()]);
    }

    #[RequestAction('page3')]
    public function p3() {
        Response::render(__DIR__ . '/views/vertical_menu.php', ['title' => 'My Page 3', 'links' => $this->links()]);
    }

    private function links() {
        $navbar = LinksBuilder::create()
            ->addGroup('Menu', 'My Menu')
                ->add('Other Pages', '?page='.$this->page.'&action=other-page')
                ->add('Page 3', '?page='.$this->page.'&action=page3')
            ->render('sidebar');
        return $navbar;
    }
}</code></pre>

    <p>The Module becomes simpler, remove the functions with <code>RequestAction</code>. The router will be loaded automatically:</p>

    <pre><code class="language-php">&lt;?php
namespace Modules\MyPages;

use App\Abstracts\AbstractModule;

class MyPagesModule extends AbstractModule {

    /**
     * Configure the module
     */
    protected function configure($rule): void
    {
        $rule->page('mypages')
             ->title('My Custom Pages')
             ->menu('My Custom Pages', '', 'bi bi-file-earmark-post-fill', 10)
             ->access('registered')
             ->addHeaderLink('Home', '?page=mypages', 'bi bi-house-fill')
             ->addHeaderLink('Other Pages', '?page=mypages&action=other-page', 'bi bi-gear-fill');
    }

}
</code></pre>
    <p><code>addHeaderLink</code> is used to add links to the header. The first parameter is the link text, the second is the link URL, and the third is the link icon.</p>

    <p>Then create <code>views/vertical_menu.php</code> for the layout:</p>

    <pre><code class="language-php">&lt;?php
!defined('MILK_DIR') && die(); // Prevent direct access
?&gt;
&lt;div class="container-fluid px-0"&gt;
&lt;div class="row"&gt;
    &lt;div class="col-md-2 bg-light"&gt;
        &lt;?php _ph($links); ?&gt;
    &lt;/div&gt;
    &lt;div class="col-md-10 bg-white"&gt;
        &lt;div class="card mt-4"&gt;
            &lt;div class="card-header"&gt;
                &lt;h5&gt;&lt;?php _p($title); ?&gt;&lt;/h5&gt;
            &lt;/div&gt;
            &lt;div class="card-body"&gt;
                Page content goes here...
            &lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;
&lt;/div&gt;</code></pre>

    <p><strong>Note:</strong> <code>addGroup()</code> optimizes the menu for mobile devices by grouping entries.</p>

    <h2>How Response::render Works</h2>

    <p><code>Response::render</code> is the main method for displaying pages. It can accept different formats:</p>

    <h4>Render with View File</h4>
    <pre><code class="language-php">// Pass variables to the view
Response::render(__DIR__ . '/views/my_view_page.php', [
    'title' => 'My Page Title',
    'content' => 'Page content here'
]);</code></pre>

    <p><strong>Helper Functions for Views:</strong></p>
    <ul>
        <li><code>_p($var)</code>: Prints the variable safely (HTML escape)</li>
        <li><code>_ph($var)</code>: Prints HTML without escape (for trusted HTML content)</li>
    </ul>

</div>
