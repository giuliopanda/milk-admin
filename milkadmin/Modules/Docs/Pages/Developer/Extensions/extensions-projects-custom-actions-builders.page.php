<?php
namespace Modules\Docs\Pages;
/**
 * @title Projects: Custom Actions + Preconfigured Builders
 * @order 42
 * @tags extensions, projects, requestaction, getLoadedExtensions, tablebuilder, formbuilder, list, edit
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Projects Extension: Custom Actions with Preconfigured Builders</h1>
    <p class="text-muted">Revision: 2026/02/27</p>

    <p>This page analyzes the demo module <code>milkadmin_local/Modules/ProjectPostsDemo/ProjectPostsDemoModule.php</code>: you use custom actions in the Module while leveraging auto-configured builders from <code>Extensions/Projects</code>.</p>

    <h2 class="mt-4">Complete Demo Module (Reference)</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Local\Modules\ProjectPostsDemo;

use App\Abstracts\AbstractModule;
use App\Attributes\RequestAction;
use App\Response;
use App\Route;
use Modules\Projects\ProjectMenuService;

class ProjectPostsDemoModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('project-posts-demo')
            ->title('Project Posts Demo')
            ->menu('Project Posts Demo', '', 'bi bi-journal-richtext', 220)
            ->access('authorized')
            ->extensions(['Projects'])
            ->version('1.0.0');

        ProjectMenuService::applyFromManifest($rule, __DIR__);
    }

    #[RequestAction('home')]
    public function home(): void
    {
        $projectsExtension = $this->getLoadedExtensions('Projects');
        if (is_object($projectsExtension) && method_exists($projectsExtension, 'getPrimaryFormLink')) {
            $links = $projectsExtension->getPrimaryFormLink();
            $firstLink = is_array($links[0] ?? null) ? $links[0] : [];
            $action = (string) ($firstLink['action'] ?? '');
            if ($action !== '') {
                Route::redirect('?page=' . $this->page . '&action=' . _r((string) $action));
                return;
            }
        }
        Route::redirect('?page=' . $this->page . '&action=project-posts-demo-list');
    }

    #[RequestAction('project-posts-demo-list')]
    public function list(): void
    {
        $projectsExtension = $this->getLoadedExtensions('Projects');
        $response = array_merge($this->getCommonData(), $projectsExtension->getAutoListTableBuilder()->getResponse());
        $response['title_btns'] = [ ['label' => 'Add New', 'link' => '?page=' . $this->page . '&action=project-posts-demo-edit' ]];
        $response['search_html'] = $projectsExtension->getAutoListSearchBuilder()->render();

        Response::render(MILK_DIR . '/Theme/SharedViews/list_page.php', $response);
    }

    #[RequestAction('project-posts-demo-edit')]
    public function edit(): void
    {
        $projectsExtension = $this->getLoadedExtensions('Projects');

        $response = $this->getCommonData();
        $response['title'] = (_absint($_REQUEST['id'] ?? 0) > 0 ? 'Edit ' : 'New ') . $this->title;
        $response['form'] = $projectsExtension->getAutoEditFormBuilder()->getForm();
        Response::render(MILK_DIR . '/Theme/SharedViews/edit_page.php', $response);
    }
}</code></pre>

    <h2 class="mt-4">Method-by-Method Explanation (ProjectPostsDemoModule)</h2>
    <h3><code>configure($rule)</code></h3>
    <ul>
        <li>registers module metadata: <code>page</code>, <code>title</code>, menu, ACL, version</li>
        <li>enables the extension with <code>->extensions(['Projects'])</code>: this starts Projects automatic bootstrap</li>
        <li><code>ProjectMenuService::applyFromManifest($rule, __DIR__)</code> re-reads <code>Project/manifest.json</code> to apply overrides to <code>menu</code>, <code>menuIcon</code>, <code>selectMenu</code></li>
    </ul>

    <h3><code>home()</code></h3>
    <ul>
        <li>retrieves the extension instance with <code>$this->getLoadedExtensions('Projects')</code></li>
        <li>if available, reads <code>getPrimaryFormLink()</code>: the root action list computed by Projects from the manifest</li>
        <li>redirects to the first root action found; explicit fallback to <code>project-posts-demo-list</code></li>
    </ul>
    <p>Analysis: this method does not define structure or permissions, it only performs initial routing. Action selection remains driven by the extension-processed manifest.</p>

    <h3><code>list()</code></h3>
    <ul>
        <li><code>getAutoListTableBuilder()</code> returns an already configured <code>TableBuilder</code> (model, columns, FK chain, filters, display mode)</li>
        <li>with <code>->getResponse()</code> you get an array ready for the standard list view</li>
        <li><code>title_btns</code> adds custom buttons (here: "Add New") above the table</li>
        <li><code>getAutoListSearchBuilder()</code> builds a search form aligned with <code>search_filters.json</code> (if present) and current query params</li>
        <li>final rendering to <code>Theme/SharedViews/list_page.php</code></li>
    </ul>
    <p>Analysis: here you customize the page container (buttons, layout, extra blocks) without manually rebuilding data and search logic.</p>

    <h3><code>edit()</code></h3>
    <ul>
        <li>computes a dynamic title from request <code>id</code>: "New ..." or "Edit ..."</li>
        <li><code>getAutoEditFormBuilder()</code> returns a <code>FormBuilder</code> preconfigured from JSON schema, model rules, and action context</li>
        <li><code>->getForm()</code> produces the form HTML for the standard edit view</li>
        <li>final rendering to <code>Theme/SharedViews/edit_page.php</code></li>
    </ul>
    <p>Analysis: the module defines only presentation and title; field composition and form behavior remain in the Projects layer.</p>

    <h2 class="mt-4">Projects Methods Involved (API Used by the Module)</h2>
    <ul>
        <li><code>getPrimaryFormLink()</code>: returns root links/actions registered during bootstrap</li>
        <li><code>getAutoListTableBuilder(array $options = [])</code>: auto-configured list builder for the current action</li>
        <li><code>getAutoListSearchBuilder(array $options = [])</code>: auto-configured search builder for the current action</li>
        <li><code>getAutoEditFormBuilder(array $options = [])</code>: auto-configured edit builder for the current action</li>
    </ul>

    <h2 class="mt-4">Internal Flow Analysis (Extensions/Projects/Module.php)</h2>
    <ol>
        <li><code>configure()</code> reads the manifest, resolves model classes for each form, and registers models in the module rule builder.</li>
        <li><code>bootstrap()</code> initializes registry/renderer and calls <code>registerFromManifest()</code>.</li>
        <li><code>registerFromManifest()</code> builds form contexts and, for each node, registers automatic actions (<code>*-list</code>, <code>*-edit</code>, etc.).</li>
        <li><code>registerFormRoutes()</code> maps action -&gt; renderer and stores context in <code>ActionContextRegistry</code>.</li>
        <li>when you call <code>getAuto*Builder()</code> in the Module, renderers read current context and produce builders consistent with root/child forms, FK chain, and manifest options.</li>
    </ol>

    <div class="alert alert-info mt-4 mb-0">
        In this pattern the Module stays lightweight: it defines routing and presentation, while structural logic (manifest, model mapping, action contexts, preconfigured builders) remains centralized in the Projects extension.
    </div>
</div>
