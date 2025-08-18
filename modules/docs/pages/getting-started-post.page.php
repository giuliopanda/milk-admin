<?php
namespace Modules\docs;
/**
 * @title Create complete Module
 * @category Getting started
 * @order 40
 * @tags Posts, Module, Tutorial, Post, controller, router, model, object, database, list, modellist, install
 */
use MilkCore\Route;
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Creating Posts Module in Milk Admin - Step by Step Tutorial</h1>
    <p>This guide will walk you through the progressive creation of a "Posts" module in Milk Admin, a development environment for administrative systems. We'll start from the basics and work our way up to a complete module with database management.</p>

    <h2>Step 1: Minimal Module - Posts</h2>
    <p>Let's start again by creating a minimal module that only shows "Hello World". Create the <code>posts</code> folder inside <code>/modules</code> with just two files:</p>
    <ul>
        <li><code>posts.controller.php</code></li>
        <li><code>posts.router.php</code></li>
    </ul>

    <h3>File <code>posts.controller.php</code></h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use MilkCore\AbstractController;

!defined('MILK_DIR') && die(); // Prevents direct access

class Posts extends AbstractController
{
    protected $page = 'posts';
    protected $title = 'Posts';
    protected $access = 'registered';
    protected $menu_links = [
        ['url'=> '', 'name'=> 'Posts']
    ];
   
    public function bootstrap() {
        $this->model = new PostsModel();
        $this->router = new PostsRouter();
    }
}
Hooks::set('modules_loaded', function() {
new Posts();
});
</code></pre>

    <h3>File <code>posts.router.php</code></h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use MilkCore\AbstractRouter;
use MilkCore\Get;

!defined('MILK_DIR') && die(); // Prevents direct access

class PostsRouter extends AbstractRouter
{
    protected function action_home() {
        Get::theme_page('default', '&lt;h1&gt;Hello World from Posts module!&lt;/h1&gt;');
    }
}
</code></pre>

    <p><strong>Explanation:</strong></p>
    <ul>
        <li>The controller defines the module with name, title and menu item</li>
        <li><code>bootstrap()</code> loads the router</li>
        <li>The router handles pages: <code>action_home()</code> is called by default when you go to <code>?page=posts</code></li>
        <li>If you add <code>&action=test</code>, the router will look for <code>action_test()</code></li>
    </ul>

    <p>Now navigate to <code>/?page=posts</code> and you'll see "Hello World from Posts module!"</p>

    <h2>Step 2: Adding Multiple Pages</h2>
    <p>Let's add other pages to the router to understand the action mechanism:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use MilkCore\AbstractRouter;
use MilkCore\Get;

!defined('MILK_DIR') && die(); // Prevents direct access

class PostsRouter extends AbstractRouter
{
    protected function action_home() {
        $html = '&lt;h1&gt;Posts List&lt;/h1&gt;';
        $html .= '&lt;p&gt;&lt;a href="?page=posts&action=create" class="btn btn-primary"&gt;Create New Post&lt;/a&gt;&lt;/p&gt;';
        $html .= '&lt;p&gt;&lt;a href="?page=posts&action=about"&gt;About&lt;/a&gt;&lt;/p&gt;';
        
        Get::theme_page('default', $html);
    }
    
    protected function action_create() {
        $html = '&lt;h1&gt;Create New Post&lt;/h1&gt;';
        $html .= '&lt;p&gt;The form will be here...&lt;/p&gt;';
        $html .= '&lt;p&gt;&lt;a href="?page=posts"&gt;Back to list&lt;/a&gt;&lt;/p&gt;';
        
        Get::theme_page('default', $html);
    }
    
    protected function action_about() {
        Get::theme_page('default', '&lt;h1&gt;About Posts Module&lt;/h1&gt;&lt;p&gt;Version 1.0&lt;/p&gt;');
    }
}
</code></pre>

    <p>Now you have three pages:</p>
    <ul>
        <li><code>?page=posts</code> - List (action_home)</li>
        <li><code>?page=posts&action=create</code> - Creation form</li>
        <li><code>?page=posts&action=about</code> - About page</li>
    </ul>

    <h2>Step 3: Using External Views</h2>
    <p>Instead of inline HTML, let's use view files. Create the <code>views</code> folder in the module:</p>

    <h3>File <code>views/home.page.php</code></h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
!defined('MILK_DIR') && die();
?&gt;
&lt;h1&gt;Posts Management&lt;/h1&gt;

&lt;div class="mb-3"&gt;
    &lt;a href="?page=posts&action=create" class="btn btn-primary"&gt;
        &lt;i class="bi bi-plus"&gt;&lt;/i&gt; New Post
    &lt;/a&gt;
&lt;/div&gt;

&lt;div class="alert alert-info"&gt;
    No posts present. Create your first post!
&lt;/div&gt;
</code></pre>

    <p>Update the router to use the view:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function action_home() {
    Get::theme_page('default', __DIR__ . '/views/home.page.php');
}</code></pre>

    <h2>Step 4: Define Data Structure with Object</h2>
    <p>Before adding the database, let's define the post structure. Create <code>posts.object.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use MilkCore\AbstractObject;

!defined('MILK_DIR') && die(); // Prevents direct access

class PostsObject extends AbstractObject
{
    public function init_rules() {
        // Primary auto-incrementing ID field
        $this->rule('id', [
            'type' => 'id'
        ]);
        
        // Post title
        $this->rule('title', [
            'type' => 'string', 
            'length' => 100, 
            'label' => 'Title',
            'form-params' => ['required' => true]
        ]);
        
        // Content
        $this->rule('content', [
            'type' => 'text', 
            'label' => 'Content'
        ]);
        
        // Automatic dates
        $this->rule('created_at', [
            'type' => 'datetime', 
            'label' => 'Creation date'
        ]);
        
        $this->rule('updated_at', [
            'type' => 'datetime', 
            'list' => false  // Don't show in list
        ]);
    }
}
</code></pre>

    <h2>Step 5: Add the Model for Database</h2>
    <p>Now let's add database management. Create <code>posts.model.php</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use MilkCore\AbstractModel;

!defined('MILK_DIR') && die(); // Prevents direct access

class PostsModel extends AbstractModel
{
    public string $table = '#__posts';
    public string $object_class = 'PostsObject';
}
</code></pre>

    <p>Update the controller to load model and object:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function bootstrap() {
    require_once __DIR__ . '/posts.model.php';
    require_once __DIR__ . '/posts.router.php';
    require_once __DIR__ . '/posts.object.php';
    $this->model = new PostsModel();
    $this->router = new PostsRouter();
}</code></pre>

    <h2>Step 6: Create Dynamic List</h2>
    <p>Let's update the router to show a dynamic table:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function action_home() {
    $table_id = 'table_posts';

    // Retrieve parameters from request (pagination, sorting, filters)
    $request = $this->get_request_params($table_id);
    
    // Set parameters in model query
    $this->model->set_query_params($request);
    
    // Get formatted data for table
    $modellist_data = $this->get_modellist_data($table_id, $fn_filter_applier);
    
    // Display table
    $this->output_table_response(__DIR__.'/views/list.page.php', $modellist_data);
}</code></pre>

    <h3>File <code>views/list.page.php</code></h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use MilkCore\Get;
use MilkCore\MessagesHandler;

!defined('MILK_DIR') && die();

echo Get::theme_plugin('title', [
    'title_txt' => "Posts", 
    'description' => 'Article management.',  
    'btns' => [['title'=>'New Post', 'color'=>'primary', 'link'=>'?page='.$page.'&action=edit']]
]);

MessagesHandler::display_messages();

// The $table_html variable contains the automatically generated table
echo $table_html;
</code></pre>

    <h2>Step 7: Edit Form</h2>
    <p>Let's add form handling. In the router:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function action_edit() {
    $id = _absint($_REQUEST['id'] ?? 0);
    
    // Retrieve record or create empty one
    $data = $this->model->get_by_id_for_edit($id); 
    
    Get::theme_page('default', __DIR__ . '/views/edit.page.php', [
        'id' => $id,
        'data' => $data,
        'page' => $this->page,
        'url_success' => '?page='.$this->page,
        'action_save' => 'save'
    ]);
}</code></pre>

    <h3>File <code>views/edit.page.php</code></h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
namespace Modules\Posts;
use MilkCore\Get;
use MilkCore\ObjectToForm;
use MilkCore\MessagesHandler;

!defined('MILK_DIR') && die();

$title = $id > 0 ? 'Edit Post' : 'New Post';
echo Get::theme_plugin('title', ['title_txt' => $title]);
MessagesHandler::display_messages();

?&gt;
&lt;div class="card"&gt;
    &lt;div class="card-body"&gt;
        &lt;?php 
        // Start form
        echo ObjectToForm::start($page, $url_success, '', $action_save);
        
        // Automatically generate fields from object
        foreach ($data->get_rules('edit', true) as $key => $rule) {
            echo ObjectToForm::row($rule, $data->$key);
        }
        
        // Submit button
        echo ObjectToForm::submit();
        echo ObjectToForm::end();
        ?&gt;
    &lt;/div&gt;
&lt;/div&gt;
</code></pre>

    <h2>Step 8: Data Saving</h2>
    <p>Add the save action in the router:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function action_save() {
    $id = _absint($_REQUEST['id'] ?? 0);
    
    // Create object with form data
    $obj = $this->model->get_empty($_REQUEST);
    $array_to_save = $obj->to_mysql_array();
    
    // Validate and save
    if ($this->model->validate($array_to_save)) {
        if ($this->model->save($array_to_save, $id)) {
            Route::redirect_success($_REQUEST['url_success'], 'Save completed');
        } else {
            Route::redirect_error($_REQUEST['url_error'], $this->model->get_last_error());
        }
    }
    
    Route::redirect_handler_errors($_REQUEST['url_error'], $array_to_save);
}</code></pre>

    <h2>Step 9: Table Actions</h2>
    <p>To add action buttons (edit, delete) in the table:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function action_home() {
    $table_id = 'table_posts';
    $request = $this->get_request_params($table_id);
    
    // Handle bulk delete action
    $this->call_table_action($table_id, 'delete', 'table_action_delete');
    
    $this->model->set_query_params($request);
    $modellist_data = $this->get_modellist_data($table_id, $fn_filter_applier);
    
    // Add actions column
    $modellist_data['info']->set_action([
        $table_id.'-edit' => 'Edit',
        $table_id.'-delete' => 'Delete'
    ]);
    
    $this->output_table_response(__DIR__.'/views/list.page.php', $modellist_data);
}

// Method to delete
protected function table_action_delete($id, $request) {
    return $this->model->delete($id);
}</code></pre>

    <p>Create <code>assets/posts.js</code> to handle clicks:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">// Click on edit
registerHook('table-action-table_posts-edit', function (id) {
    window.location.href = milk_url + '?page=posts&action=edit&id=' + id;
    return false;
});

// Click on delete with confirmation
registerHook('table-action-table_posts-delete', function (id) {
    return confirm('Delete this post?');
});</code></pre>

    <p>Remember to load the JS in the controller:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function init() {
    Theme::set('javascript', Route::url().'/modules/posts/assets/posts.js');
    parent::init();
}</code></pre>

    <h2>Step 10: Installation</h2>
    <p>To install the module and create the table:</p>
    <pre><code>php cli.php posts:install</code></pre>

    <p>The module is now complete with:</p>
    <ul>
        <li>List with automatic pagination and sorting</li>
        <li>Automatically generated creation/edit form</li>
        <li>Validation and saving</li>
        <li>Deletion with confirmation</li>
        <li>Clean and extensible MVC structure</li>
    </ul>

    <p>To update the structure after object modifications:</p>
    <pre><code>php cli.php posts:update</code></pre>

</div>
