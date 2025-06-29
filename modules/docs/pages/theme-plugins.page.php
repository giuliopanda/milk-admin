<?php
namespace Modules\docs;
/**
 * @title  Plugins
 * @category Theme
 * @order 60
 * @tags 
 */
use MilkCore\Get;
use MilkCore\Route;

!defined('MILK_DIR') && die(); // Avoid direct access

?>
<div class="bg-white p-4">
    <h1>Theme plugins</h1>
    <p>Plugins are HTML elements that will be displayed on the page. Below is how the example plugin works.</p>
    <p>To call a plugin from code you can use the function 
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Get::theme_plugin('example', ['hello' => 'first button', 'alert' => 'Hello World 1']);</code></pre>
    </p>

    <p>You can use various plugins present in the template, then if you want to create new ones here's a brief guide on how to do it.</p>

    <h1>Building a theme plugin</h1>
    <p>
    To create a plugin you need to create a file in the <code>theme/plugins</code> folder of the theme with the plugin name. Inside it a PHP file with the plugin name itself</p>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;div class=&quot;js_example_btn&quot;  data-alert=&quot;&lt;?php _p($alert); ?&gt;&quot;&gt;
    This is an example button:
    &lt;div class=&quot;btn btn-primary js-btn&quot;&gt;&lt;?php _p($hello); ?&gt;&lt;/div&gt;
&lt;/div&gt;</code></pre>

the plugin's JS file is
<pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-javascript">'use strict'
class Example {
    // element container
    el_container = null;
    // alert message
    data_alert_msg = '';

    constructor(el) {
        this.el_container = el
        this.data_alert_msg = el.getAttribute('data-alert')
        this.init()
    }
    /**
     * In the init all the listeners you want to attach to the element should be placed
     * @returns void
     */
    init() {
        // by writing the function as an arrow function I can use the class's this inside the function
        this.el_container.querySelector('.js-btn').addEventListener('click', (ev) => {
                // I prefer to use ev.currentTarget instead of ev.target because I make sure to get the right element, namely the one that has the listener
                this.click(ev.currentTarget)
         })   
    }

    click(el) {
        // I can use el.closest('.my-class') to find the first element above me that has the my-class class
        // I can use el.querySelector('.my-class') to find the first element below me that has the my-class class
        alert(this.data_alert_msg)
    }
}

/**
 * Attach my module to all elements with the js_example class
 */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.js_example_btn').forEach(function(el) {
        new Example(el);
    })
})</code></pre>

<h4>The result:</h4>
<div class="bg-light p-4"> 
<?php
echo Get::theme_plugin('example', ['hello' => 'first button', 'alert' => 'Hello World 1']); 
?><br><?php
echo Get::theme_plugin('example', ['hello' => 'Second button', 'alert' => 'Hello World 2']); 
?>
</div>

<pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">echo Get::theme_plugin('example', ['hello' => 'first button', 'alert' => 'Hello World 1']);
echo Get::theme_plugin('example', ['hello' => 'Second button', 'alert' => 'Hello World 2']);</code></pre>


<h1 class="mt-4">Table example</h1>
<p>Tables are one of the fundamental elements for data visualization, so the system has a specific plugin to handle tables. You can learn more about this topic <a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/modellist-table-p1.page'); ?>">here</a></p>
<?php
 $info = [
    'checkbox' => ['type'=>'checkbox', 'label' => '', 'order' => false],
    'id' => ['type'=>'text', 'label' => 'id', 'primary' => true, 'order' => false],
    'name' => ['type'=>'text', 'label' => _r('Name'), 'order' => false], 
    'email' => ['type'=>'text', 'label' => _r('Email'), 'order' => false],
    'age' => ['type'=>'date', 'label' => _r('Registered'), 'order' => false],
    
];  

$rows = [
    (object)['id' => 1, 'name' => 'John Doe', 'email' => 'john@doe.com', 'age' => '24'],
    (object)['id' => 2, 'name' => 'Alice', 'email' => 'alice@mail.com', 'age' => '30'],
    (object)['id' => 3, 'name' => 'Bob Marley', 'email' => 'Bob@marley.com' , 'age' => '40'],
    (object) ['id' => 4, 'name' => 'Charlie', 'email' => 'Charlie@mail.com', 'age' => '50'],
    (object)['id' => 5, 'name' => 'David', 'email' => 'david@mail.com', 'age' => '60']
];

$page_info = [
    'page' => 1,
    'action' =>'',
    'id' => 'example_table', 
    'limit' => 10,
    'limit_start' => 0,
    'order_field' => '',
    'order_dir' => '',
    'total_record' => count($rows),
    'filters' => '',
    'footer' => false,
    'pagination' => false,
    'json' => false
];

?><h4>Dynamic Table</h4>
<pre><code class="language-php">echo Get::theme_plugin('table', ['info' => $info, 'rows' => $rows, 'page_info' => $page_info]);</code></pre>
<?php
echo Get::theme_plugin('table', ['info' => $info, 'rows' => $rows, 'page_info' => $page_info]);

?><h4>Static table</h4>
<pre><code class="language-php">echo Get::theme_plugin('static-table', ['rows' => $rows]);</code></pre>
<?php
echo Get::theme_plugin('static-table', ['rows' => $rows]);

?>
<h5>The data for the table is:</h5>
<b>Rows</b>
<pre><code class="language-php">$rows = [
    (object)['id' => 1, 'name' => 'John Doe', 'email' => 'john@doe.com', 'age' => '24'],
    (object)['id' => 2, 'name' => 'Alice', 'email' => 'alice@mail.com', 'age' => '30'],
    (object)['id' => 3, 'name' => 'Bob Marley', 'email' => 'Bob@marley.com' , 'age' => '40'],
    (object) ['id' => 4, 'name' => 'Charlie', 'email' => 'Charlie@mail.com', 'age' => '50'],
    (object)['id' => 5, 'name' => 'David', 'email' => 'david@mail.com', 'age' => '60']
];
$info = [
    'checkbox' => ['type'=>'checkbox', 'label' => '', 'order' => false],
    'id' => ['type'=>'text', 'label' => 'id', 'primary' => true, 'order' => false],
    'name' => ['type'=>'text', 'label' => _r('Name'), 'order' => false], 
    'email' => ['type'=>'text', 'label' => _r('Email'), 'order' => false],
    'age' => ['type'=>'date', 'label' => _r('Registered'), 'order' => false],
    
];
$page_info = [
    'page' => 1,
    'action' =>'',
    'id' => 'example_table', 
    'limit' => 10,
    'limit_start' => 0,
    'order_field' => '',
    'order_dir' => '',
    'total_record' => count($rows),
    'filters' => '',
    'footer' => false,
    'pagination' => false,
    'json' => false
];</code></pre>

</div>