<?php
namespace Modules\docs;
/**
 * @title Abstract Router   
 * @category Abstracts Class
 * @order 40
 * @tags AbstractRouter, router, routing, action, handle_routes, action_home, set_handle_routes, access, output_table_response, get_request_params, get_modellist_data, call_table_action, default_request_params, ModelList, table, pagination, sorting, filters, JavaScript, AJAX, theme, permissions, JSON, HTML, offcanvas, table-action, registerHook, fetch, dynamic-table, checkboxes, search, filter_search, add_filter, Theme, CSS, assets, getComponent, reload, set_page
 */
use MilkCore\Route;
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Abstract class router</h1>
    
    <p>The router class manages the module pages. The choice of the class to display is linked to the action parameter. <code>?page=basemodule&action=my_custom_page</code> will try to call the action_my_custom_page method.</p><p> The action parameter must contain only numbers 0-9, lowercase letters a-z and underscore. If action=My Custom-Page, the action_mycustom_page method will not be called.</p>
    </p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
namespace Modules\BaseModule;
use MilkCore\AbstractRouter;
use MilkCore\Theme;
use MilkCore\Get;

!defined('MILK_DIR') && die(); // Avoid direct access

class BaseModuleRouter extends AbstractRouter
{
}
</code></pre>

    <h5 class="mt-3">The home page</h5>
    <p>The action_home method is the method that is called by default. If no method is set, action_home is called.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function action_home() {
        Get::theme_page('default', '<h1>'.$this->title.'</h1>');
    }</code></pre>


    <h5 class="mt-3">Building a dynamic table</h5>

    <p>To build a table you can use the <code>output_table_response</code> method which takes as parameters the path of the file to include and the data to pass to the file.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    protected function action_home() {
        // dynamic tables must have a unique id
        $table_id = 'table_id';
        // Tables are enclosed by a form that has various hidden inputs. 
        // These inputs are used for pagination, sorting and search 
        // and are all collected in a single array $_REQUEST['table_id']
        $request = $this->get_request_params($table_id);
        // Set the parameters in the query
        $this->model->set_query_params($request);
        // Execute the query and get the data and structure of the output table
        // here all the parameters necessary for displaying the table are collected
        $modellist_data = $this->get_modellist_data($table_id, $fn_filter_applier);
        // show the table with data on the page.
        $this->output_table_response(__DIR__.'/views/list.page.php', $modellist_data);
    }
    </code></pre>

    <p>Same result in a more extended way becomes:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
    protected function action_home() {
        $request = $this->get_request_params('table_id');
        $trows      =  $this->model->limit($request['limit_start'], $request['limit'])
                                       ->order($request['order_field'], $request['order_dir'])
                                       ->get();
        $total      = $this->model->total();

        $model_list = new MilkCore\ModelList($this->model->table, 'table_id');
        $page_info  = $model_list->get_page_info($total, $request['limit'], $request['page'], $request['order_field'], $request['order_dir']);
        $info       = $model_list->get_list_structure();
        $list_data  =  ['info' => $info, 'rows' => $trows, 'page_info' => $page_info];
        $table_html = Get::theme_plugin('table', $list_data); 
           
        if (($_REQUEST['page-output'] ?? '') == 'json') {
            Get::theme_page('json', '',  json_encode(['html' => $table_html, 'success' => 'true', 'msg'=>'']));
        } else {
            Get::theme_page('default', __DIR__ . '/views/list.page.php',  ['table_html' => $table_html, 'table_id' => 'table_id', 'msg' => '']);
        }
    }
    </code></pre>

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
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function action_single_view() {
        Get::theme_page('default', __DIR__ . '/views/edit.page.php',  ['id' => _absint($_REQUEST['id'] ?? 0)]);
    }</code></pre>
    <p>Create the <code>edit.page.php</code> file in the module's views folder.</p>


    <h4 class="mt-3">Removing checkboxes</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">unset($modellist_data['info']['checkbox']);</code></pre>
    
    
    <h4 class="mt-3">Adding free text search</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html"><?php echo htmlspecialchars('<div class="my-4 row">
    <div class="col">
        <input class="form-control ms-2 d-inline-block" type="search" placeholder="Search" aria-label="Search" spellcheck="false" data-ms-editor="true" id="table_id_search" style="width:200px">
        <span class="btn btn-outline-primary" onClick="table_id_search()">Search</span>
    </div>
</div>
'); ?>
</code></pre>
    <p>The javascript for search:</p>
    <pre class="pre-scroll table border p-2 text-bg-gray"><code class="language-javascript">function table_id_search() {
    var comp_table = getComponent('table_id');
    if (comp_table == null) return;
    let val =  document.getElementById('table_id_search').value;
    comp_table.filter_remove_start('search:');
    if (val != '') {
        comp_table.filter_add('search:' + val);
    }
    comp_table.set_page(1);
    comp_table.reload();
}</code></pre>
   
<p>The code in this case is already preconfigured to search in all columns, but assuming we want to search in a single column inside the model we should write a <code>filter_{filter_name}</code> function</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function filter_search($search) {
        $query = $this->get_current_query();
        $query->where('`title` LIKE ? ', ['%'.$search.'%']);
    }</code></pre>

<p>It is possible not to use a filter_{filter_name} function, but to set a custom function using the <code>add_filter</code> function</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"> $this->add_filter('search', [$this, 'filter_search']);</code></pre>


<h1>AbstractRouter Abstract Class Documentation</h1>
<p>The <code>AbstractRouter</code> class is the base class for managing module routing in Ito. This class handles module actions and provides methods for managing queries and data. This document describes in detail all public methods and their specifications.</p>

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
class PostsRouter extends \MilkCore\AbstractRouter {
    public function init() {
        Theme::set('javascript', Route::url().'/modules/posts/assets/posts.js');
    }
}
new PostsRouter();
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
class PostsRouter extends \MilkCore\AbstractRouter {
    public function init() {
        Theme::set('javascript', Route::url().'/modules/posts/assets/posts.js');
        Theme::set('styles', Route::url().'/modules/posts/assets/posts.css');
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

            <h3 class="mt-3"><code>set_handle_routes($controller)</code></h3>
            <p>Sets the variables for handling page routing, and creates the route that points to the handle_routes method</p>
            <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param object $controller The controller object
 * @return void
 */
public function set_handle_routes($controller);

// Example in the controller class is called:
$this->router->set_handle_routes($this);
</code></pre>
            <ul>
                <li><strong>Input parameters:</strong>
                  <ul>
                      <li><code>$controller</code>: (object) The controller object.</li>
                    </ul>
                 </li>
                  <li><strong>Return value:</strong>
                       <ul>
                        <li><code>void</code>: This method does not return any value.</li>
                     </ul>
                 </li>
            </ul>


            <h3 class="mt-3"><code>handle_routes()</code></h3>
            <p>Handles requests to the page. Based on the action parameter, it calls the specific method eg: action_my_function</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @return void
 */
public function handle_routes();

// Usage example in the child class:
class PostsRouter extends \MilkCore\AbstractRouter {
  public function handle_routes() {
        if (!$this->access()) {
            Route::redirect('?page=deny');
            return;
        }
        Theme::set('header.title', Theme::get('site.title')." - ". $this->title);
        $action = $_REQUEST['action'] ?? null;
        if (isset($action) && !empty($action)) {
            $action = strtolower(str_replace("-","_", _raz($action)));
            $function = 'action_' . $action;
            if (method_exists($this, $function)) {
                $this->$function();
            } else {
                 Route::redirect('?page=404');
                return;
            }
        } else {
            $this->action_home();
        }
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


            <h3 class="mt-3"><code>action_home()</code></h3>
            <p>Handles the display of the module's home page. It is the method that is called when no action parameter is passed</p>
 <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @return void
 */
protected function action_home();

// Usage example in a child class:
class PostsRouter extends \MilkCore\AbstractRouter {
    protected function action_home() {
        Get::theme_page('default', '<h1>'.$this->title.'</h1>');
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
            
            <h3 class="mt-3"><code>output_table_response(string $theme_path, $model_list_data, ?string $outputType = null)</code></h3>
            <p>Handles table display and output in json format</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string $theme_path The path of the file to load (eg views/list.page.php)
 * @param array $model_list_data The data to pass to the view
 * @param string|null $outputType The output type (json or html)
 * @return void
 */
protected function output_table_response(string $theme_path, $model_list_data, ?string $outputType = null): void;

// Usage example in a child class
protected function action_home() {
    $table_id = 'table_posts';

    $this->call_table_action($table_id, 'delete', 'table_action_delete');

    $modellist_data = $this->get_modellist_data($table_id, $fn_filter_applier);
    $modellist_data['info']['action'] = ['type' => 'action', 'label' => 'Action', 'options' => [$table_id.'-edit' => 'Edit', $table_id.'-delete' => 'Delete']];

    $this->output_table_response(__DIR__.'/views/list.page.php', $modellist_data);
}
</code></pre>
                 <ul>
                     <li><strong>Input parameters:</strong>
                         <ul>
                            <li><code>$theme_path</code>: (string) The path of the theme file to load (eg. <code>__DIR__.'/views/list.page.php'</code>).</li>
                            <li><code>$model_list_data</code>: (array) Array of data to display in the table. Usually this is the value returned by get_modellist_data.</li>
                            <li><code>$outputType</code>: (string, optional) The desired output type ('json' or html, by default looks for the page-output parameter),
                         </ul>
                     </li>
                    <li><strong>Return value:</strong>
                         <ul>
                             <li><code>void</code>: This method does not return any value. Output is printed directly.</li>
                         </ul>
                    </li>
                </ul>


               <h3 class="mt-3"><code>default_request_params()</code></h3>
                <p>Returns the default parameters for a table request.</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @return array
 */
protected function default_request_params(): array;

// Usage example in a child class
protected function get_request_params($table_id) {
     $default = $this->default_request_params();
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

            <h3 class="mt-3"><code>get_request_params($table_id)</code></h3>
            <p>Retrieves and sanitizes table parameters from the request, also adds default parameters</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string $table_id The table id
 * @return array Returns the sanitized parameters
 */
protected function get_request_params($table_id): array;

// Usage example in a child class
protected function action_home() {
     $table_id = 'table_posts';
     $request = $this->get_request_params($table_id);
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


            <h3 class="mt-3"><code>get_modellist_data($table_id, $fn_filter_applier)</code></h3>
                <p>Retrieves the data and structure of the table for display</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string $table_id The table id
 * @param callable $fn_filter_applier A callback function to apply filters to the modellist
 * @return array
 */
protected function get_modellist_data($table_id, $fn_filter_applier): array;

// Usage example in a child class
protected function action_home() {
    $table_id = 'table_posts';
    $modellist_data = $this->get_modellist_data($table_id, $fn_filter_applier);
    $this->output_table_response(__DIR__.'/views/list.page.php', $modellist_data);
}
</code></pre>
                <ul>
                     <li><strong>Input parameters:</strong>
                        <ul>
                           <li><code>$table_id</code>: (string) The unique table identifier.</li>
                           <li><code>$request</code>: (array) The parameters for configuring the table query.</li>
                         </ul>
                     </li>
                     <li><strong>Return value:</strong>
                         <ul>
                             <li><code>array</code>: Array containing the data to display (<code>rows</code>, <code>info</code>, <code>page_info</code>).</li>
                        </ul>
                   </li>
                </ul>
    
                <h3 class="mt-3"><code>call_table_action($table_id, $action, $function)</code></h3>
            <p>Handles table group actions, such as deleting multiple records simultaneously</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
/**
 * @param string $table_id The table id
 * @param string $action The action to execute
 * @param string $function The function to call
 * @return void
 */
protected function call_table_action($table_id, $action, $function);

// Usage example in a child class
protected function action_home() {
     $table_id = 'table_posts';
    $request = $this->get_request_params($table_id);
    $this->call_table_action($table_id, 'delete', 'table_action_delete');
    // ...
}

// Method to call dynamically
protected function table_action_delete($id, $request) {
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
        <li><code>handle_routes()</code>: Calls the function `action_` + action_name (eg: `action_my_action`) if it exists.</li>
        <li><code>call_table_action()</code>: Dynamically calls the <code>$function</code> function passed as a parameter.</li>
    </ul>
</div>