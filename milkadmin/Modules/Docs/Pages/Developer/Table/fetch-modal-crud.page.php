<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title Creating a Fetch-Based CRUD with Modal
 * @guide developer
 * @order 52
 * @tags fetch, modal, ajax, crud, table, form, no-refresh, spa
 */

 !defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Creating a Fetch-Based CRUD with Modal</h1>
    <p class="text-muted">Revision: 2025/10/28</p>
    <p class="lead">Learn how to create a complete CRUD (Create, Read, Update, Delete) interface that works entirely via AJAX fetch requests, with forms opening in modals, without ever refreshing the page.</p>

    <div class="alert alert-info">
        <strong>What You'll Learn:</strong>
        <ul class="mb-0">
            <li>How to create a table that loads via fetch</li>
            <li>How to make links and actions work with fetch (no page refresh)</li>
            <li>How to open forms in modals</li>
            <li>How to save forms and update the table without page reload</li>
            <li>How to use <code>activeFetch()</code> to enable fetch mode globally</li>
        </ul>
    </div>

    <div class="alert alert-success">
        <strong>✅ Benefits of Fetch-Based CRUD:</strong>
        <ul class="mb-0">
            <li><strong>Faster:</strong> No full page reloads, only updates what changed</li>
            <li><strong>Better UX:</strong> Smooth transitions, no flickering</li>
            <li><strong>Modern:</strong> SPA-like experience (Single Page Application)</li>
            <li><strong>Less bandwidth:</strong> Only JSON data is transferred</li>
        </ul>
    </div>

    <hr>

    <h2>Complete Working Example</h2>
    <p>We'll create a simple "Posts" module with ID and Title fields that works entirely with fetch and modals.</p>

    <hr>

    <h2>Step 1: Create the Model</h2>
    <p>Create a simple model with just ID and Title fields.</p>

    <h3>File: milkadmin/Modules/Posts/PostsModel.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Modules\Posts;
use App\Abstracts\AbstractModel;

class PostsModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table(\'#__posts\')
            ->id()
            ->string(\'title\', 255)->required()->label(\'Title\')
            ->timestamp(\'created_at\')->hideFromEdit()->saveValue(time())
            ->timestamp(\'updated_at\')->hideFromEdit();
    }
}'); ?></code></pre>

    <hr>

    <h2>Step 2: Create the Controller</h2>
    <p>This is the heart of the fetch-based CRUD. Notice how we use <code>activeFetch()</code> and return JSON with modal configuration.</p>

    <h3>File: milkadmin/Modules/Posts/PostsController.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Modules\Posts;
use App\Abstracts\AbstractController;
use App\{Response, MessagesHandler};
use App\Attributes\RequestAction;
use Builders\{TableBuilder, FormBuilder};

class PostsController extends AbstractController
{
    #[RequestAction(\'home\')]
    public function PostsList() {
        $response = [
            \'page\' => $this->page,
            \'title\' => $this->title,
            \'table_id\' => \'idTablePosts\'
        ];
        Response::render(__DIR__ . \'/Views/list_page.php\', $response);
    }

    #[RequestAction(\'get-table\')]
    public function getTable() {
        $response = [];

        // ================================================
        // KEY: activeFetch() enables fetch mode globally
        // ================================================
        $table = TableBuilder::create($this->model, \'idTablePosts\')
            ->activeFetch()  // All links and actions become fetch-based
            ->asLink(\'title\', \'?page=\'.$this->page.\'&action=edit&id=%id%\')
            ->setRequestAction(\'get-table\')
            ->setDefaultActions();  // Edit and Delete become fetch actions

        if ($table->isInsideRequest()) {
            // When table updates itself (pagination, sorting, filters)
            $response[\'html\'] = $table->render();
        } else {
            // When opened from external fetch (e.g., after save)
            $response[\'modal\'] = [
                \'title\' => $this->title,
                \'body\' =>  $table->render(),
                \'size\' => \'xl\'
            ];
        }

        Response::Json($response);
    }

    #[RequestAction(\'edit\')]
    public function postEdit() {
        $response = [\'page\' => $this->page, \'title\' => $this->title];

        $form = FormBuilder::create($this->model, $this->page)
            ->activeFetch()  // Enable fetch mode for form
            ->setActions([
                \'save\' => [
                    \'label\' => \'Save\',
                    \'type\' => \'submit\',
                    \'class\' => \'btn btn-primary\',
                    \'action\' => function($form_builder, $request) {
                        if (!$form_builder->save($request)) {
                            Response::Json([
                                \'success\' => false,
                                \'msg\' => MessagesHandler::errorsToString(),
                            ]);
                        } else {
                            // After save, reload the table
                            $this->getTable();
                        }
                    }
                ],
                \'cancel\' => [
                    \'label\' => \'Cancel\',
                    \'type\' => \'submit\',
                    \'class\' => \'btn btn-secondary ms-2\',
                    \'validate\' => false,
                    \'action\' => function($form_builder, $request) {
                        // On cancel, reload the table
                        $this->getTable();
                    }
                ]
            ])->ActionExecution();

        // ================================================
        // KEY: Return modal with form inside
        // ================================================
        $response[\'modal\'] = [
            \'title\' => \'Edit \' . $this->title,
            \'body\' =>  $form->getForm(),
            \'size\' => \'lg\'
        ];

        Response::Json($response);
    }
}'); ?></code></pre>

    <div class="alert alert-success">
        <strong>✅ Key Concepts in the Controller:</strong>
        <ul class="mb-0">
            <li><code>->activeFetch()</code>: Enables fetch mode - all links and actions become AJAX</li>
            <li><code>Response::Json()</code>: Returns JSON instead of HTML</li>
            <li><code>$response['modal']</code>: Tells JavaScript to open a modal</li>
            <li><code>$response['html']</code>: Updates table content without page reload</li>
            <li><code>$table->isInsideRequest()</code>: Detects if it's a table internal request (pagination, etc.)</li>
        </ul>
    </div>

    <hr>

    <h2>Step 3: Create the View</h2>
    <p>The view contains a container for the table and uses <code>data-fetch</code> to load it.</p>

    <h3>File: milkadmin/Modules/Posts/Views/list_page.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
!defined(\'MILK_DIR\') && die(); // Avoid direct access
use App\\Get;
?>

<div class="container-fluid p-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0"><?php echo $title; ?></h3>
                    <a href="?page=<?php echo $page; ?>&action=edit"
                       class="btn btn-primary"
                       data-fetch="post">
                        <i class="bi bi-plus-circle me-2"></i>Add New
                    </a>
                </div>
                <div class="card-body">
                  <a id="tableContainer" data-fetch="post" href="?page=<?php echo $page; ?>&action=get-table">ShowList</a>
                </div>
            </div>
        </div>
    </div>
</div>'); ?></code></pre>

    <div class="alert alert-info">
        <strong>💡 How data-fetch Works:</strong>
        <ul class="mb-0">
            <li><code>data-fetch="post"</code>: Tells the system to load this via AJAX POST</li>
            <li><code>data-url="..."</code>: The URL to fetch the content from</li>
            <li>On page load, JavaScript automatically fetches and inserts the table</li>
            <li>All links inside with <code>data-fetch</code> will also work via AJAX</li>
        </ul>
    </div>

    <hr>

    <h2>Step 4: Create the Module Configuration</h2>

    <h3>File: milkadmin/Modules/Posts/PostsModule.php</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlspecialchars('<?php
namespace Modules\Posts;
use App\Abstracts\AbstractModule;

class PostsModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page(\'posts\')
            ->title(\'Posts\')
            ->menu(\'Posts\', \'\', \'bi bi-file-text-fill\', 30);
    }
}'); ?></code></pre>

    <hr>

    <h2>Step 5: Install and Test</h2>

    <h3>1. Create the database table</h3>
    <pre class="border p-2 bg-dark text-light"><code>php milkadmin/cli.php posts:update</code></pre>

    <h3>2. Access the module</h3>
    <p>Navigate to: <code>?page=posts</code></p>

    <h3>3. Test the functionality</h3>
    <ul>
        <li><strong>Click "Add New"</strong> → Form opens in modal</li>
        <li><strong>Fill and save</strong> → Modal closes, table updates automatically</li>
        <li><strong>Click on a title</strong> → Edit form opens in modal</li>
        <li><strong>Edit and save</strong> → Modal closes, table updates</li>
        <li><strong>Click "Delete"</strong> → Confirmation, then record deleted and table updates</li>
        <li><strong>Pagination/Sorting</strong> → Table updates without page reload</li>
    </ul>

    <div class="alert alert-success">
        <strong>✅ Notice:</strong> At no point does the page refresh! Everything happens via fetch.
    </div>

    <hr>

    <h2>Understanding the Data Flow</h2>

    <h3>When you click "Add New" or "Edit":</h3>
    <pre class="border p-2 bg-light"><code>1. Click link with data-fetch="post"
2. JavaScript sends POST request to controller
3. Controller returns JSON:
   {
     "modal": {
       "title": "Edit Post",
       "body": "&lt;form&gt;...&lt;/form&gt;",
       "size": "lg"
     }
   }
4. JavaScript opens modal with the form inside
</code></pre>

    <h3>When you save the form:</h3>
    <pre class="border p-2 bg-light"><code>1. Submit form (with activeFetch enabled)
2. Controller validates and saves
3. Controller calls getTable() which returns:
   {
     "modal": {
       "title": "Posts",
       "body": "&lt;table&gt;...&lt;/table&gt;",
       "size": "xl"
     }
   }
4. JavaScript replaces modal content with updated table
</code></pre>

    <h3>When you delete a record:</h3>
    <pre class="border p-2 bg-light"><code>1. Click delete button
2. Confirmation dialog
3. POST request with table_action=delete&table_ids=123
4. Controller deletes record
5. Table automatically updates via fetch
</code></pre>

    <hr>

    <h2>The Magic of activeFetch()</h2>

    <p>The <code>activeFetch()</code> method is the key that makes everything work seamlessly:</p>

    <h3>For TableBuilder:</h3>
    <pre class="border p-2 bg-light"><code class="language-php"><?php echo htmlspecialchars('$table = TableBuilder::create($this->model, \'idTablePosts\')
    ->activeFetch()  // ⭐ This line is magic!
    ->asLink(\'title\', \'?page=posts&action=edit&id=%id%\')  // Becomes fetch link
    ->setDefaultActions();  // Edit and Delete become fetch actions'); ?></code></pre>

    <p><strong>What activeFetch() does:</strong></p>
    <ul>
        <li>Converts <code>asLink()</code> → adds <code>data-fetch="post"</code> automatically</li>
        <li>Converts all actions → adds <code>data-fetch="post"</code> automatically</li>
        <li>No need to manually specify fetch on every link/action</li>
    </ul>

    <h3>For FormBuilder:</h3>
    <pre class="border p-2 bg-light"><code class="language-php"><?php echo htmlspecialchars('$form = FormBuilder::create($this->model, $this->page)
    ->activeFetch()  // ⭐ Enables fetch mode
    ->setActions([...]);'); ?></code></pre>

    <p><strong>What activeFetch() does:</strong></p>
    <ul>
        <li>Form submission becomes AJAX (no page reload)</li>
        <li>Responses must be JSON</li>
        <li>Perfect for modal forms</li>
    </ul>

    <hr>

    <h2>Customizing Modal Size</h2>

    <p>You can control modal size with the <code>size</code> parameter:</p>

    <pre class="border p-2 bg-light"><code class="language-php"><?php echo htmlspecialchars('$response[\'modal\'] = [
    \'title\' => \'Title\',
    \'body\' => $content,
    \'size\' => \'sm\'   // sm, md, lg, xl, or omit for default
];'); ?></code></pre>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Size</th>
                <th>Bootstrap Class</th>
                <th>Width</th>
                <th>Best For</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>sm</code></td>
                <td><code>modal-sm</code></td>
                <td>300px</td>
                <td>Confirmations, small forms</td>
            </tr>
            <tr>
                <td><code>md</code> (default)</td>
                <td><code>-</code></td>
                <td>500px</td>
                <td>Standard forms</td>
            </tr>
            <tr>
                <td><code>lg</code></td>
                <td><code>modal-lg</code></td>
                <td>800px</td>
                <td>Forms with multiple fields</td>
            </tr>
            <tr>
                <td><code>xl</code></td>
                <td><code>modal-xl</code></td>
                <td>1140px</td>
                <td>Tables, complex forms, editors</td>
            </tr>
        </tbody>
    </table>

    <hr>

    <h2>Advanced: Closing Modal and Updating External Elements</h2>

    <p>You can return multiple actions in the JSON response:</p>

    <pre class="border p-2 bg-light"><code class="language-php"><?php echo htmlspecialchars('Response::Json([
    \'success\' => true,
    \'msg\' => \'Record saved successfully!\',
    \'modal\' => [\'close\' => true],  // Close the modal
    \'element\' => [                    // Update an element outside modal
        \'selector\' => \'#statsCard\',
        \'innerHTML\' => \'<div>Updated stats</div>\'
    ]
]);'); ?></code></pre>

    <hr>

    <h2>Troubleshooting</h2>

    <h3>Problem: Form submits but page refreshes</h3>
    <p><strong>Solution:</strong> Make sure you called <code>->activeFetch()</code> on the FormBuilder</p>

    <h3>Problem: Links don't open in modal</h3>
    <p><strong>Solutions:</strong></p>
    <ul>
        <li>Check that <code>->activeFetch()</code> is called on TableBuilder</li>
        <li>Verify controller returns <code>$response['modal']</code> with title, body, size</li>
        <li>Check browser console for JavaScript errors</li>
    </ul>

    <h3>Problem: Modal opens but table doesn't update after save</h3>
    <p><strong>Solutions:</strong></p>
    <ul>
        <li>Ensure save action calls <code>$this->getTable()</code> on success</li>
        <li>Check that <code>getTable()</code> returns modal with updated table</li>
        <li>Verify <code>Response::Json()</code> is called, not <code>Response::render()</code></li>
    </ul>

    <h3>Problem: Delete action refreshes page</h3>
    <p><strong>Solution:</strong> Call <code>->activeFetch()</code> before <code>->setDefaultActions()</code></p>

    <hr>

    <h2>Comparison: Traditional vs Fetch-Based</h2>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Feature</th>
                <th>Traditional</th>
                <th>Fetch-Based (This Guide)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Page Reload</strong></td>
                <td>Every action</td>
                <td>Never</td>
            </tr>
            <tr>
                <td><strong>Form Display</strong></td>
                <td>New page</td>
                <td>Modal overlay</td>
            </tr>
            <tr>
                <td><strong>Data Transfer</strong></td>
                <td>Full HTML</td>
                <td>JSON only</td>
            </tr>
            <tr>
                <td><strong>User Experience</strong></td>
                <td>Page flicker</td>
                <td>Smooth transitions</td>
            </tr>
            <tr>
                <td><strong>Speed</strong></td>
                <td>Slower</td>
                <td>Faster</td>
            </tr>
            <tr>
                <td><strong>Bandwidth</strong></td>
                <td>More</td>
                <td>Less</td>
            </tr>
            <tr>
                <td><strong>Code Complexity</strong></td>
                <td>Simple</td>
                <td>Slightly more (but worth it!)</td>
            </tr>
        </tbody>
    </table>

    <hr>

    <h2>Summary: Quick Checklist</h2>

    <div class="alert alert-success">
        <strong>✅ To create a fetch-based CRUD with modal:</strong>
        <ol class="mb-0">
            <li>Model: Create as usual</li>
            <li>Controller: Use <code>->activeFetch()</code> on both Table and Form</li>
            <li>Controller: Return <code>Response::Json(['modal' => [...]])</code></li>
            <li>View: Use <code>data-fetch="post"</code> on container and buttons</li>
            <li>Actions: Call <code>$this->getTable()</code> after save/cancel</li>
        </ol>
    </div>

    <hr>

    <h2>Related Documentation</h2>
    <ul>
        <li><a href="?page=docs&action=Framework/Theme/theme-javascript-fetch-link">JavaScript Fetch Links - Complete Reference</a></li>
        <li><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder - Table Management</a></li>
        <li><a href="?page=docs&action=Developer/Form/builders-form">FormBuilder - Form Management</a></li>
        <li><a href="?page=docs&action=Developer/GettingStarted/getting-started-post">Getting Started - Creating a Module</a></li>
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
.alert-success {
    background: #d1e7dd;
    border-left: 4px solid #198754;
}
.alert-warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}
.alert-danger {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
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
