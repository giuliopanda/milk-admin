<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title Abstract Controller   
 * @guide developer
 * @order 40
 * @tags AbstractController, router, routing, action, attributes, Action, handle_routes, set_handle_routes, access, output_table_response, get_request_params, get_modellist_data, call_table_action, default_request_params, ModelList, table, pagination, sorting, filters, JavaScript, AJAX, theme, permissions, JSON, HTML, offcanvas, table-action, registerHook, fetch, dynamic-table, checkboxes, search, filter_search, add_filter, Theme, CSS, assets, getComponent, reload, set_page
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Abstract class router</h1>

    <p>The router class manages the module pages. The choice of the class to display is linked to the action parameter. <code>?page=basemodule&action=my_custom_page</code> will try to call the corresponding method.</p><p> The action parameter must contain only numbers 0-9, lowercase letters a-z and underscore. If action=My Custom-Page, it will be converted to mycustom_page.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
namespace Modules\BaseModule;
!defined('MILK_DIR') && die(); // Avoid direct access

class BaseModuleController extends AbstractController
{
}
</code></pre>

    <h2 class="mt-4">Using Action Attributes</h2>

    <p>The <code>#[RequestAction]</code> attribute is used to define which methods respond to specific URL actions. This provides a clean and declarative way to map URLs to methods.</p>

    <h5 class="mt-3">Basic Action Usage</h5>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Attributes\Action;

class BaseModuleController extends AbstractController
{
    #[RequestAction('home')]
    protected function list() {
        Response::themePage('default', '<h1>Home Page</h1>');
    }

    #[RequestAction('edit')]
    protected function edit() {
        $id = _absint($_REQUEST['id'] ?? 0);
        Response::themePage('default', '<h1>Edit Record ' . $id . '</h1>');
    }

    #[RequestAction('settings')]
    protected function moduleSettings() {
        Response::themePage('default', '<h1>Module Settings</h1>');
    }
}</code></pre>

    <p><strong>URL Mapping:</strong></p>
    <ul>
        <li><code>?page=basemodule</code> or <code>?page=basemodule&action=home</code> → calls <code>list()</code> method</li>
        <li><code>?page=basemodule&action=edit</code> → calls <code>edit()</code> method</li>
        <li><code>?page=basemodule&action=settings</code> → calls <code>moduleSettings()</code> method</li>
    </ul>

    <h5 class="mt-3">Attribute Parameters</h5>
    <p>The <code>#[RequestAction]</code> attribute accepts the following parameters:</p>
    <ul>
        <li><code>action</code>: (string) The action name that will be matched in the URL</li>
        <li><code>url</code>: (string, optional) Custom URL pattern (defaults to action name)</li>
    </ul>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('custom-name', 'different-url')]
protected function myMethod() {
    // This method responds to ?page=module&action=different-url
}</code></pre>

    <h5 class="mt-3">The home page</h5>
    <p>The method with <code>#[RequestAction('home')]</code> attribute is called by default when no action is specified in the URL.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('home')]
protected function home() {
    Response::themePage('default', '<h1>'.$this->title.'</h1>');
}</code></pre>

    <h5 class="mt-3">Building a dynamic table</h5>

    <p>To build a table, create a method with the <code>#[RequestAction]</code> attribute and use the TableBuilder for simplified table creation.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use Builders\TableBuilder;

#[RequestAction('home')]
protected function list() {
    $table_id = 'table_posts';

    // Create table with fluent interface
    $response = TableBuilder::create($this->model, $table_id)
        ->setDefaultActions()                              // Add Edit/Delete actions
        ->asLink('title', '?page=posts&action=edit&id=%id%') // Make title clickable
        ->limit(15)                                        // Set pagination limit
        ->orderBy('created_at', 'desc')                    // Default sorting
        ->getResponse();

    Response::render(__DIR__ . '/Views/list_page.php', $response);
}
    </code></pre>

    <h5 class="mt-3">Advanced TableBuilder Example</h5>

    <p>For more complex tables with custom columns, filters, and styling:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('home')]
protected function list() {
    $table_id = 'table_posts';

    $response = TableBuilder::create($this->model, $table_id)
        // Query customization
        ->where('status != ?', ['deleted'])
        ->orderBy('title', 'asc')
        ->limit(20)

        // Column customization
        ->asLink('title', '?page=posts&action=edit&id=%id%')
        ->setLabel('created_at', 'Published Date')
        ->setType('status', 'select')
        ->setOptions('status', ['draft' => 'Draft', 'published' => 'Published'])
        ->hideColumn('updated_at')

        // Add custom column with processing function
        ->column('actions_custom', 'Quick Actions', 'html', [], function($row, $key) {
            return '<a href="?page=posts&action=view&id=' . $row->id . '" class="btn btn-sm btn-outline-primary">View</a>';
        })

        // Filters
        ->filterEquals('status', 'status')
        ->filterLike('search', 'title')

        // Actions
        ->setDefaultActions([
            'duplicate' => [
                'label' => 'Duplicate',
                'action' => [$this, 'duplicatePost'],
                'confirm' => 'Duplicate this post?'
            ]
        ])

        // Styling
        ->tableColor('striped-primary')
        ->headerColor('primary')

        ->getResponse();

    Response::render(__DIR__ . '/Views/list_page.php', $response);
}

public function duplicatePost($ids, $request) {
    foreach ($ids as $id) {
        $post = $this->model->getById($id);
        if ($post) {
            $post->title = $post->title . ' (Copy)';
            $post->status = 'draft';
            unset($post->id);
            $this->model->store((array)$post);
        }
    }
    return true;
}
    </code></pre>

    <h5 class="mt-3">TableBuilder Benefits</h5>

    <p>The TableBuilder provides several advantages over manual table creation:</p>

    <ul>
        <li><strong>Fluent Interface:</strong> Chain methods for readable, maintainable code</li>
        <li><strong>Automatic AJAX Support:</strong> Handles JSON responses automatically</li>
        <li><strong>Built-in Actions:</strong> Easy setup for edit, delete, and custom actions</li>
        <li><strong>Advanced Filtering:</strong> Simple filter setup with built-in search</li>
        <li><strong>Styling System:</strong> Bootstrap-compatible color and class management</li>
        <li><strong>Column Management:</strong> Hide, reorder, and customize columns easily</li>
    </ul>

    <h4 class="mt-3">Adding js or css </h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function init() {
        Theme::set('javascript', Route::url().'/modules/base-module/assets/base-module.js');
    }</code></pre>

    <h4 class="mt-3">Adding footer and header graphics</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">     $modellist_data['page_info']['footer'] = true;
        $modellist_data['table_attrs'] = ['tfoot' => ['class' => 'table-footer-gray'], 'tfoot.td.title' => ['class' => 'text-end']];
        $modellist_data['rows'][] = (object)['title' => 'Footer'];
        // modify the graphics of the head
        $modellist_data['table_attrs'] = ['thead' => ['class' => 'table-header-yellow'], 'th.title' => ['class' => 'th-title']];</code></pre>

    <h4 class="mt-3">Adding a link for an action</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">     $modellist_data['row_info']['action'] = ['type' => 'action', 'label' => 'Action', 'options' => [$table_id.'-view' => 'View']];</code></pre>
    <p>Now you need to add the javascript to handle the action.</p>
    <p>Here's an ajax example to show an offcanvas with record details.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">registerHook('table-action-{$table_id-view}', function (id, sendform) {
        window.offcanvasEnd.show()
    window.offcanvasEnd.loading_show()
    fetch(milk_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: getFormData('?id=' + id + '&action=single_view&page=dynamic_table_example')
    }).then((response) => {
        window.offcanvasEnd.loading_hide()
        return response.json()
    }).then((data) => {
        window.offcanvasEnd.body(data.html)
        window.offcanvasEnd.title(data.title)
    })
    // does not update the table
    return false;
})</code></pre>
    <p>Or an example of a link to go to the detail page. In the javascript insert:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">registerHook('table-action-{$table_id-view}', function (id, sendform) {
        window.location.href = milk_url + '?page=basemodule&action=single_view&id=' + id + '';
    return false;
})</code></pre>
    <p>In the router insert:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('single_view')]
public function singleView() {
    Response::themePage('default', __DIR__ . '/Views/edit.page.php',  ['id' => _absint($_REQUEST['id'] ?? 0)]);
}</code></pre>
    <p>Create the <code>edit.page.php</code> file in the module's views folder.</p>

    <h4 class="mt-3">Removing checkboxes</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">unset($modellist_data['info']['checkbox']);</code></pre>

    <h4 class="mt-3">Adding free text search</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html"><?php echo htmlspecialchars('<div class="my-4 row">
    <div class="col">
        <input class="form-control ms-2 d-inline-block" type="search" placeholder="Search" aria-label="Search" spellcheck="false" data-ms-editor="true" id="table_id_search" style="width:200px">
        <span class="btn btn-outline-primary" onClick="tableIdSearch()">Search</span>
    </div>
</div>
'); ?>
</code></pre>
    <p>The javascript for search:</p>
    <pre class="pre-scroll table border p-2 text-bg-gray"><code class="language-javascript">function tableIdSearch() {
    var comp_table = getComponent('table_id');
    if (comp_table == null) return;
    let val =  document.getElementById('table_id_search').value;
    comp_table.filter_remove_start('search:');
    if (val != '') {
        comp_table.filter_add('search:' + val);
    }
    comp_table.setPage(1);
    comp_table.reload();
}</code></pre>
   
<p>The code in this case is already preconfigured to search in all columns, but assuming we want to search in a single column inside the model we should write a <code>filter_{filter_name}</code> function</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function filterSearch($search) {
        $query = $this->getCurrentQuery();
        $query->where('`title` LIKE ? ', ['%'.$search.'%']);
    }</code></pre>

<p>It is possible not to use a filter_{filter_name} function, but to set a custom function using the <code>add_filter</code> function</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"> $this->addFilter('search', [$this, 'filtersearch']);</code></pre>

<h1>AbstractController Abstract Class Documentation</h1>
<p>The <code>AbstractController</code> class is the base class for managing module routing in Ito. This class handles module actions and provides methods for managing queries and data. This document describes in detail all public methods and their specifications.</p>

<h2 class="mt-4">Main Properties</h2>
<ul>
    <li><code>$page</code>: (string) The module name.</li>
    <li><code>$access</code>: (string) The module access level (<code>public</code>, <code>registered</code>, <code>authorized</code>, <code>admin</code>).</li>
    <li><code>$title</code>: (string) The module title.</li>
    <li><code>$model</code>: (string|object) The name or instance of the associated model class.</li>

</ul>

<h2 class="mt-4">Public Methods</h2>

<h3 class="mt-3"><code>__construct()</code></h3>
<p>Class constructor. Registers the init hook</p>
   <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Usage example in a child class:
class PostsController extends \App\Abstract\AbstractController {
    public function init() {
        Theme::set('javascript', Route::url().'/modules/posts/assets/posts.js');
    }
}
new PostsController();
</code></pre>
            <ul>
               <li><strong>Input parameters:</strong>
                    <ul>
                         <li><em>None</em></li>
                    </ul>
               </li>
                 <li><strong>Return value:</strong>
                     <ul>
                       <li><em>None</em></li>
                  </ul>
              </li>
            </ul>
            
             <h3 class="mt-3"><code>init()</code></h3>
            <p>Function to override for loading functions, javascript, css or initializing anything.</p>
           <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * This method must be overridden to initialize the router.
 * This method is called by the 'init' hook.
 * @return void
 */
public function init();

// Usage example in a child class:
class PostsController extends \App\Abstract\AbstractController {
    public function init() {
        Theme::set('javascript', Route::url().'/Modules/Posts/Assets/posts.js');
        Theme::set('styles', Route::url().'/Modules/Posts/Assets/posts.css');
    }
}
</code></pre>
            <ul>
                 <li><strong>Input parameters:</strong>
                      <ul>
                          <li><em>None</em></li>
                      </ul>
                </li>
                <li><strong>Return value:</strong>
                      <ul>
                        <li><code>void</code>: This method does not return any value.</li>
                     </ul>
                </li>
            </ul>

            <h3 class="mt-3"><code>setHandleRoutes($module)</code></h3>
            <p>Sets the variables for handling page routing, and creates the route that points to the handle_routes method</p>
            <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param object $module The module object
 * @return void
 */
public function setHandleRoutes($module);

// Example in the module class is called:
$this->router->setHandleRoutes($this);
</code></pre>
            <ul>
                <li><strong>Input parameters:</strong>
                  <ul>
                      <li><code>$module</code>: (object) The module object.</li>
                    </ul>
                 </li>
                  <li><strong>Return value:</strong>
                       <ul>
                        <li><code>void</code>: This method does not return any value.</li>
                     </ul>
                 </li>
            </ul>

            <h3 class="mt-3"><code>handleRoutes()</code></h3>
            <p>Handles requests to the page. Based on the action parameter, it calls the specific method with the corresponding #[RequestAction] attribute.</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @return void
 */
public function handleRoutes();

// Usage example in the child class:
class PostsController extends \App\Abstract\AbstractController {
  public function handleRoutes() {
        if (!$this->access()) {
            Route::redirect('?page=deny');
            return;
        }
        Theme::set('header.title', Theme::get('site.title')." - ". $this->title);

        // The parent class automatically handles routing to methods
        // with #[RequestAction] attributes based on the 'action' parameter
        parent::handleRoutes();
  }
}
</code></pre>
                <ul>
                     <li><strong>Input parameters:</strong>
                        <ul>
                             <li><em>None</em></li>
                        </ul>
                    </li>
                     <li><strong>Return value:</strong>
                          <ul>
                             <li><code>void</code>: This method does not return any value.</li>
                         </ul>
                     </li>
                 </ul>

            <h3 class="mt-3">Action Methods with Attributes</h3>
            <p>Methods with <code>#[RequestAction]</code> attributes handle specific URL actions. The method with <code>#[RequestAction('home')]</code> is called when no action parameter is passed.</p>
 <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
use App\Attributes\RequestAction;

// Usage example in a child class:
class PostsController extends \App\Abstract\AbstractController {
    #[RequestAction('home')]
    protected function home() {
        Response::themePage('default', '<h1>'.$this->title.'</h1>');
    }

    #[RequestAction('edit')]
    protected function edit() {
        $id = _absint($_REQUEST['id'] ?? 0);
        Response::themePage('default', '<h1>Edit Post ' . $id . '</h1>');
    }
}
</code></pre>
           <ul>
                <li><strong>Input parameters:</strong>
                     <ul>
                       <li><em>None</em></li>
                    </ul>
               </li>
                <li><strong>Return value:</strong>
                   <ul>
                       <li><code>void</code>: This method does not return any value.</li>
                    </ul>
              </li>
           </ul>

            <h3 class="mt-3"><code>access()</code></h3>
             <p>Checks if the user has permissions to access the module based on the <code>$access</code> property</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @return bool
 */
protected function access(): bool;

// Usage example in the child class
if ($this->access()) {
    // the user has permissions to access
} else {
   Route::redirect('?page=deny');
}
</code></pre>
                <ul>
                    <li><strong>Input parameters:</strong>
                       <ul>
                           <li><em>None</em></li>
                         </ul>
                   </li>
                     <li><strong>Return value:</strong>
                        <ul>
                             <li><code>bool</code>: Returns <code>true</code> if the user has permissions, <code>false</code> otherwise.</li>
                        </ul>
                   </li>
                </ul>

               <h3 class="mt-3"><code>defaultRequestParams()</code></h3>
                <p>Returns the default parameters for a table request.</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @return array
 */
protected function defaultRequestParams(): array;

// Usage example in a child class
protected function getRequestParams($table_id) {
     $default = $this->defaultRequestParams();
    return $default;
}
</code></pre>
                 <ul>
                     <li><strong>Input parameters:</strong>
                         <ul>
                           <li><em>None</em></li>
                         </ul>
                     </li>
                    <li><strong>Return value:</strong>
                        <ul>
                           <li><code>array</code>: An associative array with default parameters (order_field, order_dir and limit).</li>
                       </ul>
                     </li>
                </ul>

            <h3 class="mt-3"><code>getRequestParams($table_id)</code></h3>
            <p>Retrieves and sanitizes table parameters from the request, also adds default parameters</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string $table_id The table id
 * @return array Returns the sanitized parameters
 */
protected function getRequestParams($table_id): array;

// Usage example in a child class
#[RequestAction('home')]
protected function home() {
     $table_id = 'table_posts';
     $request = $this->getRequestParams($table_id);
    //...
}
</code></pre>
              <ul>
                   <li><strong>Input parameters:</strong>
                        <ul>
                           <li><code>$table_id</code>: (string) The unique table identifier.</li>
                        </ul>
                   </li>
                    <li><strong>Return value:</strong>
                        <ul>
                             <li><code>array</code>: An associative array containing the table request parameters (<code>order_field</code>, <code>order_dir</code>, <code>limit</code>, <code>page</code>).</li>
                        </ul>
                   </li>
                </ul>

            <h3 class="mt-3"><code>getModellistData($table_id, $fn_filter_applier, $fn_query_applier)</code></h3>
                <p>Retrieves the data and structure of the table for display. You can see an example of how to use it in <a href="<?php echo Route::url('?page=docs&action=Framework/DynamicTable/modellist-table-p2'); ?>">Dynamic Table > Add query filters</a></p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string $table_id The table id
 * @param callable $fn_filter_applier A callback function to apply filters to the modellist
 * @param callable $fn_query_applier A callback function to modify the query
 * @return array
 */
protected function getModellistData($table_id, $fn_filter_applier, $fn_query_applier): array;

// Usage example in a child class
#[RequestAction('home')]
protected function home() {
    $table_id = 'table_posts';
    $modellist_data = $this->getModellistData($table_id, $fn_filter_applier, $fn_query_applier);
    $outputType = Response::isJson() ? 'json' : 'html';
             
    $table_html = Get::themePlugin('table', $modellist_data); 
    $theme_path = realpath(__DIR__.'/Views/list.page.php');

    if ($outputType === 'json') {
        Response::json([
            'html' => $table_html,
            'success' => !MessagesHandler::hasErrors(),
            'msg' => MessagesHandler::errorsToString()
        ]);
    } else {
        Response::themePage('default',  $theme_path, [
            'table_html' => $table_html,
            'table_id' => $table_id,
            'page' => $this->page
        ]);
    }
}
</code></pre>
                <ul>
                     <li><strong>Input parameters:</strong>
                        <ul>
                           <li><code>$table_id</code>: (string) The unique table identifier.</li>
                           <li><code>$fn_filter_applier</code>: (callable) A callback function to apply filters to the modellist.</li>
                           <li><code>$fn_query_applier</code>: (callable) A callback function to modify the query.</li>
                         </ul>
                     </li>
                     <li><strong>Return value:</strong>
                         <ul>
                             <li><code>array</code>: Array containing the data to display (<code>rows</code>, <code>info</code>, <code>page_info</code>).</li>
                        </ul>
                   </li>
                </ul>
    
                <h3 class="mt-3"><code>callTableAction($table_id, $action, $function)</code></h3>
            <p>Handles table group actions, such as deleting multiple records simultaneously</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string $table_id The table id
 * @param string $action The action to execute
 * @param string $function The function to call
 * @return void
 */
protected function callTableAction($table_id, $action, $function);

// Usage example in a child class
#[RequestAction('home')]
protected function home() {
     $table_id = 'table_posts';
    $request = $this->getRequestParams($table_id);
    $this->callTableAction($table_id, 'delete', 'table_action_delete');
    // ...
}

// Method to call dynamically
protected function tableActionDelete($id, $request) {
    $this->model->delete($id);
    return true;
}
</code></pre>
        <ul>
            <li><strong>Input parameters:</strong>
                <ul>
                    <li><code>$table_id</code>: (string) The table identifier.</li>
                    <li><code>$action</code>: (string) The action to execute (eg: "delete").</li>
                    <li><code>$function</code>: (string) The name of the function that must be called dynamically.</li>
                </ul>
            </li>
            <li><strong>Return value:</strong>
                <ul>
                    <li><code>void</code>: This method does not return any value.</li>
                </ul>
            </li>
        </ul>

    <h2 class="mt-4">Dynamic Calls</h2>
    <p>The following methods make dynamic calls to functions defined in the child class:</p>
    <ul>
        <li><code>handleRoutes()</code>: Calls the method with the corresponding #[RequestAction] attribute for the requested action.</li>
        <li><code>callTableAction()</code>: Dynamically calls the <code>$function</code> function passed as a parameter.</li>
    </ul>
</div>