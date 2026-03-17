<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title Creating a Fetch-Based Form with Offcanvas
 * @guide developer
 * @order 52
 * @tags fetch, offcanvas, modal, ajax, crud, form, no-refresh, formbuilder
 */

 !defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Creating a Fetch-Based Form with Offcanvas</h1>
    <p class="text-muted">Revision: 2025/12/11</p>
    <p class="lead">Learn how to create forms that work via AJAX fetch requests, opening in offcanvas panels without page reloads.</p>

    <div class="alert alert-info">
        <strong>What You'll Learn:</strong>
        <ul class="mb-0">
            <li>How to use FormBuilder methods for fetch-based forms</li>
            <li>How to open forms in offcanvas panels</li>
            <li>How to automatically reload tables after save</li>
            <li>How to use <code>activeFetch()</code> to enable fetch mode</li>
            <li>Dynamic title handling for new/edit modes</li>
        </ul>
    </div>

    <hr>

    <h2>Overview: The Recipe Module</h2>
    <p>We'll examine a complete working module that demonstrates fetch-based forms. The Recipe module includes a table with add/edit functionality, all working via fetch requests.</p>

    <h3>File Structure</h3>
    <pre class="border p-2 bg-light"><code>milkadmin_local/Modules/Recipe/
├── RecipeModel.php           # Database model
├── RecipeModule.php          # Controller with actions
└── Views/
    └── list_page.php         # View template</code></pre>

    <h3>Database Schema</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Field</th>
                <th>Type</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>id</code></td>
                <td>int</td>
                <td>Primary key</td>
            </tr>
            <tr>
                <td><code>name</code></td>
                <td>varchar(255)</td>
                <td>Recipe name (displayed as title)</td>
            </tr>
            <tr>
                <td><code>ingredients</code></td>
                <td>text</td>
                <td>Recipe ingredients (textarea)</td>
            </tr>
            <tr>
                <td><code>difficulty</code></td>
                <td>varchar(50)</td>
                <td>Select: Easy, Medium, Hard</td>
            </tr>
        </tbody>
    </table>

    <hr>

    <h2>Step 1: Create the Model</h2>
    <p>The model defines the database structure and field types.</p>

    <h3>File: milkadmin_local/Modules/Recipe/RecipeModel.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Local\Modules\Recipe;
use App\Abstracts\AbstractModel;

class RecipeModel extends AbstractModel
{
   protected function configure($rule): void
   {
        $rule->table(\'#__recipes\')
            ->id()
            ->title(\'name\')->index()
            ->text(\'ingredients\')->formType(\'textarea\')
            ->select(\'difficulty\', [\'Easy\', \'Medium\', \'Hard\']);
   }
}'); ?></code></pre>

    <p><strong>Field Explanations:</strong></p>
    <ul>
        <li><code>->id()</code> - Auto-increment primary key</li>
        <li><code>->title('name')</code> - String field with automatic title display</li>
        <li><code>->text('ingredients')->formType('textarea')</code> - Text field rendered as textarea</li>
        <li><code>->select('difficulty', [...])</code> - Dropdown select field</li>
    </ul>

    <hr>

    <h2>Step 2: Create the Module (Controller)</h2>
    <p>The module contains two actions: one for displaying the table, and one for the edit form.</p>

    <h3>File: milkadmin_local/Modules/Recipe/RecipeModule.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Local\Modules\Recipe;
use App\Abstracts\AbstractModule;
use App\Attributes\{RequestAction, AccessLevel};
use Builders\{TableBuilder, FormBuilder};
use App\Response;

class RecipeModule extends AbstractModule
{
   protected function configure($rule): void {
        $rule->page(\'recipes\')
             ->title(\'My Recipes\')
             ->menu(\'Recipes\', \'\', \'bi bi-book\', 10)
             ->access(\'registered\')
             ->version(251101);
   }

   #[RequestAction(\'home\')]
    public function recipesList() {
        $tableBuilder = TableBuilder::create($this->model, \'idTableRecipes\')
            ->activeFetch()
            ->field(\'name\')->link(\'?page=\'.$this->page.\'&action=edit&id=%id%\')
            ->setDefaultActions();
        $response = array_merge($this->getCommonData(), $tableBuilder->getResponse());
        Response::render(__DIR__ . \'/Views/list_page.php\', $response);
    }

    #[RequestAction(\'edit\')]
    public function recipeEdit() {
        $response = [\'page\' => $this->page, \'title\' => $this->title];

        $response = array_merge($response, FormBuilder::create($this->model, $this->page)
            ->activeFetch()
            ->asOffcanvas()
            ->setTitle(\'New Recipe\', \'Edit Recipe\')
            ->dataListId(\'idTableRecipes\')
            ->getResponse());

        Response::json($response);
    }
}'); ?></code></pre>

    <h3>Action 1: recipesList() - Display the Table</h3>
    <p>This action creates a table with fetch-based interactions:</p>
    <ul>
        <li><code>TableBuilder::create($this->model, 'idTableRecipes')</code> - Create table builder</li>
        <li><code>->activeFetch()</code> - Converts all table action buttons and links to fetch calls</li>
        <li><code>->field('name')->link(...)</code> - Makes the name field clickable</li>
        <li><code>->setDefaultActions()</code> - Adds edit and delete buttons</li>
    </ul>

    <h3>Action 2: recipeEdit() - Show Form in Offcanvas</h3>
    <p>This action demonstrates the FormBuilder method chain:</p>

    <pre class="border p-2 bg-light"><code class="language-php">FormBuilder::create($this->model, $this->page)
    ->activeFetch()                              // Enable fetch mode
    ->asOffcanvas()                              // Display in offcanvas panel
    ->setTitle('New Recipe', 'Edit Recipe')      // Set titles for new/edit
    ->dataListId('idTableRecipes')               // Auto-reload table on success
    ->getResponse()                              // Generate JSON response</code></pre>

    <p>Each method is explained in detail in the next section.</p>

    <hr>

    <h2>Step 3: Create the View</h2>
    <p>The view contains a card with the table and an "Add New" button.</p>

    <h3>File: milkadmin_local/Modules/Recipe/Views/list_page.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Modules\Posts\Views;
use Builders\TitleBuilder;

!defined(\'MILK_DIR\') && die(); // Avoid direct access
?>
<div class="card">
    <div class="card-header">
    <?php
    $title = TitleBuilder::create($title)->addButton(\'Add New\', \'?page=\'.$page.\'&action=edit\', \'primary\', \'\', \'post\');
    echo (isset($search_html)) ? $title->addRightContent($search_html) : $title->addSearch(\'idTableRecipes\', \'Search...\', \'Search\');
    ?>
    </div>
    <div class="card-body">
        <p class="text-body-secondary mb-3"><?php _pt(\'This is a sample module to show how to create a basic module on Milk Admin. Go to the modules/posts folder to see the code.\') ?></p>
        <?php _ph($html); ?>
    </div>
</div>'); ?></code></pre>

    <h3>TitleBuilder with "Add New" Button</h3>
    <p>The <code>addButton()</code> method creates a button that triggers a fetch request:</p>
    <pre class="border p-2 bg-light"><code class="language-php">->addButton('Add New', '?page='.$page.'&action=edit', 'primary', '', 'post')</code></pre>

    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>'Add New'</code> - Button text</li>
        <li><code>'?page=...'</code> - URL to call</li>
        <li><code>'primary'</code> - Bootstrap button style</li>
        <li><code>''</code> - Icon (empty in this case)</li>
        <li><code>'post'</code> - Converts the link to a fetch POST request</li>
    </ul>

    <hr>

    <h2>FormBuilder Methods for Fetch-Based Forms</h2>
    <p>These methods enable fetch-based form handling with automatic offcanvas/modal display and table reloading.</p>

    <h3>activeFetch()</h3>
    <p>Enables fetch mode for the form. This method converts form submission and action buttons into fetch calls:</p>
    <ul>
        <li>Form submission becomes an AJAX request (no page reload)</li>
        <li>Submit buttons trigger fetch calls instead of traditional form submission</li>
        <li>The response must be JSON</li>
    </ul>

    <pre class="border p-2 bg-light"><code class="language-php">->activeFetch()</code></pre>

    <div class="alert alert-warning">
        <strong>Note:</strong> Without <code>activeFetch()</code>, the form will still open in the offcanvas, but the page will reload when you save or cancel.
    </div>

    <h3>asOffcanvas()</h3>
    <p>Sets the response type to offcanvas panel. The form will appear in a sliding panel from the right side of the screen.</p>

    <pre class="border p-2 bg-light"><code class="language-php">->asOffcanvas()</code></pre>

    <h3>asModal()</h3>
    <p>Alternative to <code>asOffcanvas()</code>. Displays the form in a centered modal dialog instead of a side panel.</p>

    <pre class="border p-2 bg-light"><code class="language-php">->asModal()</code></pre>

    <h3>asDom($id)</h3>
    <p>Alternative display method. Renders the form directly in a DOM element with the specified ID.</p>

    <pre class="border p-2 bg-light"><code class="language-php">->asDom('contentWrapper')</code></pre>

    <h3>setTitle($new, $edit = null)</h3>
    <p>Sets dynamic titles for new and edit modes. The system automatically determines which title to use based on whether the record has an ID.</p>

    <pre class="border p-2 bg-light"><code class="language-php">->setTitle('New Recipe', 'Edit Recipe')</code></pre>

    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$new</code> - Title when creating a new record</li>
        <li><code>$edit</code> - Title when editing existing record (optional, defaults to <code>$new</code>)</li>
    </ul>

    <h3>dataListId($id)</h3>
    <p>Enables automatic table reload on successful save or delete operations.</p>

    <pre class="border p-2 bg-light"><code class="language-php">->dataListId('idTableRecipes')</code></pre>

    <p>When set, after a successful save:</p>
    <ul>
        <li>The table with the specified ID is automatically reloaded</li>
        <li>The offcanvas/modal is automatically closed</li>
    </ul>

    <h3>size($size)</h3>
    <p>Sets the size of the offcanvas or modal. Available options:</p>

    <pre class="border p-2 bg-light"><code class="language-php">->size('lg')  // or 'sm', 'xl', 'fullscreen'</code></pre>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Size</th>
                <th>Width</th>
                <th>Best For</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>sm</code></td>
                <td>Small</td>
                <td>Simple forms, confirmations</td>
            </tr>
            <tr>
                <td><code>lg</code></td>
                <td>Large</td>
                <td>Forms with multiple fields</td>
            </tr>
            <tr>
                <td><code>xl</code></td>
                <td>Extra large</td>
                <td>Complex forms, editors</td>
            </tr>
            <tr>
                <td><code>fullscreen</code></td>
                <td>Full screen</td>
                <td>Very complex forms</td>
            </tr>
        </tbody>
    </table>

    <h3>getResponse()</h3>
    <p>Generates the complete JSON response array based on the configured response type (offcanvas, modal, or dom).</p>

    <pre class="border p-2 bg-light"><code class="language-php">->getResponse()</code></pre>

    <p>Returns an array ready to be sent as JSON, containing:</p>
    <ul>
        <li>Action tracking (executed_action, action_success)</li>
        <li>List reload instructions (if dataListId is set)</li>
        <li>Offcanvas/modal/dom configuration</li>
    </ul>

    <hr>

    <h2>Understanding the JSON Response</h2>
    <p>The <code>getResponse()</code> method generates a JSON response with this structure:</p>

    <pre class="border p-2 bg-light"><code class="language-json">{
    "executed_action": "save",        // or null if no action
    "action_success": true,            // or false
    "list": {                          // only if dataListId is set and success
        "id": "idTableRecipes",
        "action": "reload"
    },
    "offcanvas_end": {                 // or "modal" or "element"
        "title": "Edit Recipe",
        "action": "show",              // or "hide" if success
        "body": "<form>...</form>",
        "size": "lg"                   // optional
    }
}</code></pre>

    <p><strong>Response Keys:</strong></p>
    <ul>
        <li><code>executed_action</code> - Name of the action that was executed (save, delete, etc.)</li>
        <li><code>action_success</code> - Whether the action completed successfully</li>
        <li><code>list</code> - Instructions to reload a table (only appears if <code>dataListId</code> is set and action succeeded)</li>
        <li><code>offcanvas_end</code> / <code>modal</code> / <code>element</code> - Display configuration based on response type</li>
    </ul>

    <p>For complete documentation on JSON response handling, see: <a href="?page=docs&action=Framework/Theme/theme-json-actions">JSON Actions (MilkActions)</a></p>

    <hr>

    <h2>Page Reload vs Fetch-Based Approach</h2>
    <p>MilkAdmin supports two approaches for handling forms.</p>

    <h3>Page Reload Approach</h3>
    <p>Traditional approach where each action triggers a full page refresh.</p>
    <ul>
        <li>The form is displayed on a dedicated page</li>
        <li>Submit redirects to a success or error page</li>
        <li>Entire page HTML is transferred on each request</li>
        <li>Better for complex pages with multiple sections and heavy context</li>
    </ul>

    <h3>Fetch-Based Approach (This Tutorial)</h3>
    <p>Modern approach using AJAX fetch requests with no page reloads.</p>
    <ul>
        <li>Forms open in offcanvas/modal overlays</li>
        <li>Only JSON data is transferred</li>
        <li>Tables auto-reload after save/delete</li>
        <li>Smoother user experience with no page flicker</li>
        <li>Better for simple forms and CRUD operations</li>
    </ul>

    <h3>Code Comparison</h3>

    <h4>Page Reload Approach</h4>
    <pre class="border p-2 bg-light"><code class="language-php">public function recipeEdit() {
    $form = FormBuilder::create($this->model, $this->page)->getForm();
    Response::render(__DIR__ . '/Views/edit_page.php', ['form' => $form]);
}</code></pre>

    <h4>Fetch-Based Approach</h4>
    <pre class="border p-2 bg-light"><code class="language-php">public function recipeEdit() {
    $response = ['page' => $this->page, 'title' => $this->title];

    $response = array_merge($response, FormBuilder::create($this->model, $this->page)
        ->activeFetch()
        ->asOffcanvas()
        ->setTitle('New Recipe', 'Edit Recipe')
        ->dataListId('idTableRecipes')
        ->getResponse());

    Response::json($response);
}</code></pre>

    <hr>

    <h2>Installation and Testing</h2>

    <h3>Create the database table</h3>
    <pre class="border p-2"><code>php milkadmin/cli.php recipes:update</code></pre>

    <h3>Access the module</h3>
    <p>Navigate to: <code>?page=recipes</code></p>

    <h3>Expected behavior</h3>
    <ul>
        <li>Click "Add New" → Form opens in offcanvas panel</li>
        <li>Fill and save → Offcanvas closes, table reloads with new record</li>
        <li>Click on a recipe name → Edit form opens in offcanvas</li>
        <li>Edit and save → Offcanvas closes, table updates</li>
        <li>No page reloads occur at any point</li>
    </ul>

    <hr>

    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Framework/Theme/theme-json-actions">JSON Actions (MilkActions)</a> - Complete reference for JSON responses</li>
        <li><a href="?page=docs&action=Framework/Theme/theme-javascript-fetch-link">JavaScript Fetch Links</a> - How fetch links work</li>
        <li><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder</a> - Table management</li>
        <li><a href="?page=docs&action=Developer/Form/builders-form">FormBuilder</a> - Form management</li>
    </ul>

</div>

<style>
pre {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}
.alert {
    padding: 1rem;
    margin: 1rem 0;
    border-radius: 0.5rem;
}
.alert-info {
    background: #cfe2ff;
    border-left: 4px solid #0d6efd;
}
.alert-warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}
.table-dark {
    background: #212529;
    color: white;
}
code {
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.9em;
}
</style>
