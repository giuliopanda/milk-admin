<?php
namespace Modules\Docs\Pages;
/**
 * @title Sidebar Modules
 * @category Advanced
 * @order 7
 * @tags modules, sidebar, navigation, container, menu
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

<h1>Sidebar Modules</h1>

<p class="lead">
    Create container modules with shared sidebar navigation to group related modules together.
</p>

<hr>

<h2>Overview</h2>

<p>
    Sidebar modules allow you to create a parent container module that provides a shared navigation menu for multiple child modules.
    This pattern is useful when you have several related modules that should be grouped together under a common interface.
</p>

<p>
    <strong>Use Cases:</strong>
</p>
<ul>
    <li>Settings modules (Aule, Causali, Strumenti, etc.)</li>
    <li>Educational modules (Corsi, Lezioni, Frequenze, Eventi)</li>
    <li>Any group of related administrative modules</li>
</ul>

<hr>

<h2>Implementation Steps</h2>

<h3>1. Create the Container Module</h3>

<p>
    First, create a container module that will serve as the main menu entry point:
</p>

<pre><code class="language-php">&lt;?php
namespace Local\Modules\Settings;
use App\Abstracts\AbstractModule;
use App\Attributes\{RequestAction, AccessLevel};
use App\{Response, Theme};

class SettingsModule extends AbstractModule
{
    protected function configure($rule): void {
        $rule->page('settings')
             ->title('Settings')
             ->menu('Settings', '', 'bi bi-gear', 10)
             ->access('registered')
             ->version(251227);
    }

    #[RequestAction('home')]
    public function settingsHome() {
        // Render the main settings page with sidebar
        $contentHtml = '&lt;h1>Settings&lt;/h1>&lt;p>Select a section from the sidebar.&lt;/p>';
        $this->renderWithSidebar($contentHtml);
    }

    private function renderWithSidebar($contentHtml) {
        ob_start();
        require (\App\Get::uriPath(__DIR__."/Views/menu.php"));
        $sidebar = ob_get_clean();

        $combinedContent = '
        &lt;div class="container-fluid px-0">
            &lt;div class="row">
                &lt;div class="col-md-2">
                    ' . $sidebar . '
                &lt;/div>
                &lt;div class="col-md-10">
                    &lt;div class="bg-white p-4">
                        ' . $contentHtml . '
                    &lt;/div>
                &lt;/div>
            &lt;/div>
        &lt;/div>';

        Theme::set('content', $combinedContent);
        Response::themePage('default');
    }
}
</code></pre>

<h3>2. Create the Sidebar Menu</h3>

<p>
    Create a hard-coded menu file at <code>Views/menu.php</code> within your container module:
</p>

<pre><code class="language-php">&lt;?php
namespace Local\Modules\Settings\Views;
use Builders\LinksBuilder;

!defined('MILK_DIR') && die();

$currentPage = $_REQUEST['page'] ?? '';

$menus = [
    ['label' => 'Aule', 'icon' => 'bi bi-door-open', 'url' => '?page=aule', 'active' => ($currentPage === 'aule')],
    ['label' => 'Causali', 'icon' => 'bi bi-list-check', 'url' => '?page=causali', 'active' => ($currentPage === 'causali')],
    ['label' => 'Strumenti', 'icon' => 'bi bi-music-note-list', 'url' => '?page=strumenti', 'active' => ($currentPage === 'strumenti')]
];

$linksBuilder = LinksBuilder::create()->addMany($menus);
echo $linksBuilder->render('sidebar');
</code></pre>

<h3>3. Create a Helper Class</h3>

<p>
    Create a helper class to make the sidebar accessible to child modules:
</p>

<pre><code class="language-php">&lt;?php
namespace Local\Modules\Settings;

class SettingsHelper
{
    public static function getSidebar(): string
    {
        ob_start();
        require(\App\Get::uriPath(__DIR__ . "/Views/menu.php"));
        return ob_get_clean();
    }

    public static function wrapWithSidebar(string $content): string
    {
        $sidebar = self::getSidebar();
        return '
        &lt;div class="container-fluid px-0">
            &lt;div class="row">
                &lt;div class="col-md-2">' . $sidebar . '&lt;/div>
                &lt;div class="col-md-10">' . $content . '&lt;/div>
            &lt;/div>
        &lt;/div>';
    }
}
</code></pre>

<h3>4. Update Child Modules</h3>

<p>
    Update each child module to use the <code>selectMenu()</code> method:
</p>

<pre><code class="language-php">&lt;?php
namespace Local\Modules\Aule;
use App\Abstracts\AbstractModule;
use App\Attributes\{RequestAction, AccessLevel};
use Builders\{TableBuilder, FormBuilder};
use App\Response;

class AuleModule extends AbstractModule
{
    protected function configure($rule): void {
        $rule->page('aule')
             ->title('Aule')
             // Comment out the menu entry - it will be in Settings sidebar
             //->menu('Aule', '', 'bi bi-door-open', 10)
             ->access('registered')
             ->selectMenu('Settings')  // Link to Settings sidebar
             ->version(251222);
    }

    #[RequestAction('home')]
    public function auleList() {
        $tableBuilder = TableBuilder::create($this->model, 'idTableAule')
            ->activeFetch()
            ->field('AULA')->link('?page='.$this->page.'&action=edit&id=%ID%')
            ->setDefaultActions();

        $response = array_merge($this->getCommonData(), $tableBuilder->getResponse());
        $response['title_btns'] = [
            ['label' => 'Aggiungi Nuova', 'link' => '?page='.$this->page.'&action=edit', 'color' => 'primary', 'fetch' => 'get']
        ];

        // Use custom view with sidebar
        Response::render(__DIR__ . '/Views/list_page.php', $response);
    }
}
</code></pre>

<h3>5. Create Custom View for Child Modules</h3>

<p>
    Create a custom <code>Views/list_page.php</code> for each child module that integrates the sidebar:
</p>

<pre><code class="language-php">&lt;?php
namespace Local\Modules\Aule\Views;

use Builders\TitleBuilder;
use Local\Modules\Settings\SettingsHelper;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Variables:
 * $title - string
 * $title_btns - array [label, link, color][]
 * $description - string
 * $html - string
 */

// Get Settings sidebar
$sidebar = SettingsHelper::getSidebar();
?>
&lt;div class="container-fluid px-0">
    &lt;div class="row">
        &lt;div class="col-md-2">
            &lt;?= $sidebar ?>
        &lt;/div>
        &lt;div class="col-md-10">
            &lt;div class="card">
                &lt;?php if (isset($title)) : ?>
                &lt;div class="card-header">
                &lt;?php
                    $title_builder = TitleBuilder::create($title);
                    if (isset($title_btns) && is_array($title_btns)) {
                        foreach ($title_btns as $btn) {
                            $title_builder->addButton($btn['label'], $btn['link'], $btn['color'] ?? 'primary', $btn['class'] ?? '', $btn['fetch'] ?? null);
                        }
                    }
                    echo (isset($search_html)) ? $title_builder->addRightContent($search_html) : $title_builder->addSearch($table_id, 'Search...', 'Search');
                ?>
                &lt;/div>
                &lt;?php endif; ?>
                &lt;div class="card-body">
                    &lt;?php if (isset($description)) { ?>
                        &lt;p class="text-body-secondary mb-3">&lt;?php _pt($description); ?>&lt;/p>
                    &lt;?php } ?>
                    &lt;?php _ph($html); ?>
                &lt;/div>
            &lt;/div>
        &lt;/div>
    &lt;/div>
&lt;/div>
</code></pre>

<hr>

<h2>How selectMenu() Works</h2>

<p>
    The <code>selectMenu()</code> method is a chainable method in <code>ModuleRuleBuilder</code> that automatically sets the active sidebar when the module is loaded.
</p>

<p>
    <strong>Implementation in AbstractModule:</strong>
</p>

<pre><code class="language-php">// In AbstractModule constructor
if ((isset($_REQUEST['page']) && $_REQUEST['page'] == $this->page)) {
    $this->loadLang();
    Hooks::set('after_modules_loaded', [$this, 'init'], 10);
    Hooks::set('after_modules_loaded', [$this, 'setStylesAndScripts'], 15);
    Hooks::set('after_modules_loaded', [$this, 'afterInit'], 11);

    // Set selected menu if configured
    $selected_menu = $this->rule_builder->getSelectedMenu();
    if ($selected_menu !== null) {
        Theme::set('sidebar.selected', $selected_menu);
    }
}
</code></pre>

<p>
    This ensures that <code>Theme::set('sidebar.selected', 'Settings')</code> is only applied when you're actually viewing that module's page.
</p>

<hr>

<h2>Best Practices</h2>

<ul>
    <li><strong>Hard-coded menus:</strong> Use static menu arrays instead of dynamic page-based routing for better control.</li>
    <li><strong>Narrow sidebar:</strong> Use <code>col-md-2</code> for sidebar and <code>col-md-10</code> for content to maintain a clean layout.</li>
    <li><strong>Consistent structure:</strong> Keep the same file structure across all child modules for maintainability.</li>
    <li><strong>Comment out menu entries:</strong> When moving modules under a sidebar container, comment out their original <code>->menu()</code> entries instead of deleting them.</li>
    <li><strong>Helper classes:</strong> Use helper classes to centralize sidebar generation logic.</li>
</ul>

<hr>

<h2>Complete Example</h2>

<p>
    A complete working example can be found in:
</p>

<ul>
    <li><code>milkadmin_local/Modules/Settings/</code> - Container module for configuration modules</li>
    <li><code>milkadmin_local/Modules/Didattica/</code> - Container module for educational modules</li>
    <li><code>milkadmin_local/Modules/Aule/</code> - Example child module with sidebar integration</li>
</ul>

</div>
