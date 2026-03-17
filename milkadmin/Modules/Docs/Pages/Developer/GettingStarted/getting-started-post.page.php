<?php
namespace Modules\Docs\Pages;
/**
 * @title Create complete Module
 * @guide developer
 * @order 40
 * @tags Posts, Module, Tutorial, Post, module, model, database, list, CRUD, install
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Creating Posts Module in Milk Admin - Complete Tutorial</h1>
    <p class="text-muted">Revision: 2025/12/15</p>
    <p>This guide will walk you through creating a complete "Posts" module in Milk Admin. This is a minimal CRUD module that demonstrates all the necessary steps to create a fully functional module with database management, list view, and edit forms using the shared view architecture.</p>

    <h2>Module Structure</h2>
    <p>The Posts module is located in <code>milkadmin/Modules/Posts/</code> and consists of the following files:</p>
    <ul>
        <li><code>PostsModule.php</code> - Main module configuration</li>
        <li><code>PostsModel.php</code> - Database model and validation</li>
        <li><code>PostsController.php</code> - Controller for handling actions and routing</li>
    </ul>

    <div class="alert alert-info mt-3">
        <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Shared Views</h5>
        <p class="mb-0">The module uses shared views located in <code>milkadmin/Theme/SharedViews/</code> for list and edit pages. This promotes code reuse and consistency across all modules. You can still create custom views in your module's <code>Views/</code> folder when needed.</p>
    </div>

    <h2>Step 1: Create the Module File</h2>
    <p>Create <code>milkadmin/Modules/Posts/PostsModule.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use App\Abstracts\AbstractModule;

class PostsModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('posts')
             ->title('Posts')
             ->menu('Posts', '', 'bi bi-file-earmark-post-fill', 10)
             ->version(250901);
    }

}
</code></pre>

    <p><strong>Explanation:</strong></p>
    <ul>
        <li><code>page('posts')</code> - Defines the URL parameter (?page=posts)</li>
        <li><code>title('Posts')</code> - Sets the module title</li>
        <li><code>menu(...)</code> - Creates a menu item with label, URL, Bootstrap icon, and position</li>
        <li><code>version()</code> - Module version number (format: YYMMDD, year-month-day)</li>
    </ul>

    <div class="alert alert-info mt-3">
        <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Automatic Controller Loading</h5>
        <p class="mb-0">The framework automatically loads the <code>PostsController</code> class if it follows the naming convention (<code>PostsModule</code> → <code>PostsController</code>) and is in the same namespace. No manual registration needed!</p>
    </div>

    <h2>Step 2: Create the Model</h2>
    <p>Create <code>milkadmin/Modules/Posts/PostsModel.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use App\Attributes\{Validate};
use App\Abstracts\AbstractModel;

class PostsModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__posts')
            ->id()
            ->title()
            ->text('content')->formType('editor')
            ->created_at()
            ->datetime('updated_at')->hideFromEdit()->saveValue(date('Y-m-d H:i:s'));
    }

    #[Validate('title')]
    public function validateTitle($current_record_obj): string {
        $value = $current_record_obj->title;
        if (strlen($value) < 5) {
            return 'Title must be at least 5 characters long';
        }
        return '';
    }

    protected function afterCreateTable(): void {
        $sql =  "INSERT INTO `".$this->table."` (`id`, `title`, `content`, `created_at`, `updated_at`) VALUES
    (1, 'Post Title 1', 'Content of post 1', '2024-11-01 10:00:00',  '2024-11-01 10:00:00'),
    (2, 'Post Title 2', 'Content of post 2', '2024-11-02 11:30:00', '2024-11-02 11:30:00'),
    (3, 'Post Title 3', 'Content of post 3', '2024-12-03 15:45:00', '2024-12-03 15:45:00');";
            $this->db->query($sql);
    }
}
</code></pre>

    <p><strong>Explanation:</strong></p>
    <ul>
        <li><code>table('#__posts')</code> - Defines the database table name (prefix will be replaced automatically)</li>
        <li><code>id()</code> - Creates an auto-increment ID field</li>
        <li><code>title()</code> - Creates a title field (predefined type)</li>
        <li><code>text('content')->formType('editor')</code> - Creates a text field with rich text editor</li>
        <li><code>created_at()</code> - Auto-populated creation timestamp</li>
        <li><code>datetime('updated_at')->hideFromEdit()->saveValue(...)</code> - Updated timestamp, hidden from form, auto-updated</li>
        <li><code>validateTitle()</code> - Custom validation using the Validate attribute</li>
        <li><code>afterCreateTable()</code> - Insert sample data after table creation</li>
    </ul>

    <h2>Step 3: Database Installation and Updates</h2>
    <p>After creating the model, you need to create the database table.</p>

    <h3>First Installation</h3>
    <p>To install the module and create the database table with sample data, run:</p>
    <pre><code>php milkadmin/cli.php posts:install</code></pre>

    <p>This command will:</p>
    <ul>
        <li>Create the <code>#__posts</code> table in the database</li>
        <li>Add all fields defined in the model (id, title, content, created_at, updated_at)</li>
        <li>Insert the sample data defined in <code>afterCreateTable()</code></li>
    </ul>

    <h3>Updating the Database Structure</h3>
    <p>When you modify the model configuration (add/remove/change fields), update the database structure with:</p>
    <pre><code>php milkadmin/cli.php posts:update</code></pre>

    <p><strong>Note:</strong> The <code>update</code> command only modifies the table structure. It does <strong>not</strong> run <code>afterCreateTable()</code>, so sample data won't be re-inserted.</p>

    <div class="alert alert-info mt-3">
        <h5 class="alert-heading"><i class="bi bi-info-circle"></i> CLI Commands Quick Reference</h5>
        <table class="table table-sm table-borderless mb-0">
            <tbody>
                <tr>
                    <td><code>modulename:install</code></td>
                    <td>First installation - creates table and runs <code>afterCreateTable()</code></td>
                </tr>
                <tr>
                    <td><code>modulename:update</code></td>
                    <td>Updates table structure after model changes (no sample data)</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2>Step 4: Create the Controller</h2>
    <p>Create <code>milkadmin/Modules/Posts/PostsController.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use App\Abstracts\AbstractController;
use App\{Response};
use App\Attributes\RequestAction;
use Builders\{TableBuilder, FormBuilder};

class PostsController extends AbstractController
{
    #[RequestAction('home')]
    public function postsList() {
        $response = $this->getCommonData(); // Sets 'page' and 'title' from module config
        $response['table_id'] = 'idTablePosts';
        $response['link_action_edit'] = 'edit';
        $response['html'] = TableBuilder::create($this->model, 'idTablePosts')
            ->asLink('title', '?page='.$this->page.'&amp;action=edit&amp;id=%id%')
            ->setDefaultActions() // edit | delete
            ->render();
        $response['title_btns'] = [['label' => 'Add New', 'link' => '?page='.$this->page.'&amp;action=edit']];
        Response::render(MILK_DIR . '/Theme/SharedViews/list_page.php', $response);
    }

    #[RequestAction('edit')]
    public function postEdit() {
        $response = $this->getCommonData(); // Sets 'page' and 'title' from module config
        $response['form'] = FormBuilder::create($this->model, $this->page)->getForm();
        Response::render(MILK_DIR . '/Theme/SharedViews/edit_page.php', $response);
    }
}
</code></pre>

    <p><strong>Explanation:</strong></p>
    <ul>
        <li><strong>Extends AbstractController</strong> - Provides base routing functionality and access to <code>$this->page</code>, <code>$this->title</code>, and <code>$this->model</code></li>
        <li><strong>#[RequestAction('home')]</strong> - Attribute-based routing: this method is called when no action parameter is specified (default action)</li>
        <li><strong>#[RequestAction('edit')]</strong> - This method is called when <code>?action=edit</code> is in the URL</li>
        <li><strong>$this->getCommonData()</strong> - Helper method that returns an array with <code>page</code> and <code>title</code> from the module configuration. This replaces manually setting these values.</li>
        <li><strong>postsList()</strong> - Builds the table using TableBuilder:
            <ul>
                <li><code>TableBuilder::create($this->model, 'idTablePosts')</code> - Creates table from model</li>
                <li><code>asLink('title', '...')</code> - Makes the title column clickable, linking to edit page</li>
                <li><code>setDefaultActions()</code> - Adds edit and delete action buttons</li>
                <li><code>render()</code> - Generates the HTML table</li>
                <li><code>$response['title_btns']</code> - Array of buttons to display in the page header (e.g., "Add New")</li>
            </ul>
        </li>
        <li><strong>postEdit()</strong> - Builds the form using FormBuilder:
            <ul>
                <li><code>FormBuilder::create($this->model, $this->page)</code> - Auto-generates form from model fields</li>
                <li><code>getForm()</code> - Returns the HTML form with validation</li>
            </ul>
        </li>
        <li><strong>Response::render(MILK_DIR . '/Theme/SharedViews/...')</strong> - Renders the shared view template located in the Theme/SharedViews folder. This promotes code reuse across all modules.</li>
    </ul>

    <div class="alert alert-info mt-3">
        <h5 class="alert-heading"><i class="bi bi-info-circle"></i> How Controller Properties Work</h5>
        <p>The AbstractController provides automatic setup through the <code>setHandleRoutes()</code> method:</p>
        <ul class="mb-0">
            <li><code>$this->page</code> - Set automatically from the module's page configuration</li>
            <li><code>$this->title</code> - Set automatically from the module's title configuration</li>
            <li><code>$this->model</code> - Set automatically from the module's model instance</li>
            <li><code>$this->module</code> - Reference to the parent AbstractModule instance</li>
            <li><code>$this->getCommonData()</code> - Helper method that returns <code>['page' => $this->page, 'title' => $this->title]</code></li>
        </ul>
        <p class="mt-2 mb-0">These properties are populated when the module is initialized, so you don't need to manually set them in your controller. Use <code>getCommonData()</code> as a starting point for your <code>$response</code> array.</p>
    </div>

    <h2>Step 5: Understanding Shared Views</h2>
    <p>The module uses <strong>shared views</strong> located in <code>milkadmin/Theme/SharedViews/</code>. These views are reusable across all modules and automatically handle the display of lists and forms.</p>

    <div class="alert alert-success">
        <h5 class="alert-heading"><i class="bi bi-check-circle"></i> No View Creation Needed!</h5>
        <p class="mb-0">You <strong>don't need to create view files</strong> for basic CRUD operations. The framework provides shared views that work with the data from your controller's <code>$response</code> array.</p>
    </div>

    <h3>Shared List View (<code>list_page.php</code>)</h3>
    <p>The shared list view automatically displays:</p>
    <ul>
        <li><strong>Title</strong> from <code>$response['title']</code></li>
        <li><strong>Action buttons</strong> from <code>$response['title_btns']</code> array (e.g., "Add New")</li>
        <li><strong>Search box</strong> for the table (using <code>$response['table_id']</code>)</li>
        <li><strong>Table HTML</strong> from <code>$response['html']</code> (generated by TableBuilder)</li>
        <li><strong>Optional description</strong> from <code>$response['description']</code></li>
    </ul>

    <h3>Shared Form View (<code>edit_page.php</code>)</h3>
    <p>The shared form view automatically displays:</p>
    <ul>
        <li><strong>Title</strong> from <code>$response['title']</code></li>
        <li><strong>Form HTML</strong> from <code>$response['form']</code> (generated by FormBuilder)</li>
        <li>Automatically wraps the form in a card layout</li>
    </ul>

    <h3>When to Create Custom Views</h3>
    <p>You only need to create custom views in your module's <code>Views/</code> folder when:</p>
    <ul>
        <li>You need a completely different layout (e.g., gallery view, card-based layout)</li>
        <li>You have complex UI requirements not covered by TableBuilder/FormBuilder</li>
        <li>You need custom JavaScript or CSS specific to that view</li>
    </ul>

    <p><strong>Example of custom view:</strong> The Lessons module uses a custom <code>list_page.php</code> because it displays courses as cards instead of a table.</p>

    <p>Once you have completed all the steps and run the installation command, you can access the Posts module from the sidebar menu.</p>

</div>
