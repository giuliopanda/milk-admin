<?php
namespace Modules\docs;
/**
 * @title Cli
 * @category Framework
 * @order 
 * @tags 
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Cli Class</h1>
    
    <p>The Cli class is used to register and manage functions to be called from the command line. It provides methods to check if the code is running from the command line, execute registered functions, and print messages to the console.</p>

    <p>To execute a function from the command line, after opening the console and navigating to the project folder, type:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">php cli.php</code></pre>
    <p>The list of available functions will appear.</p>
    <p>To execute a specific function type:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">php cli.php function_name</code></pre>

     <h4 class="mt-4">Registering a function</h4>
    
    <p>If you are inside a module class that extends AbstractController, you just need to write a function that starts with shell_ and the subsequent part of the function will be a new command registered in cli.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class Posts extends AbstractController {
    ...
    public function shell_test() {
        Cli::echo("Test");
    }
    ...
}</code></pre>

<p>Otherwise you can register an external function making sure to include the correct namespace:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">function test_echo_fn(...$data) {
    Cli::success("Params: ". json_encode($data));
}
Cli::set('posts-test', 'Modules\Posts\test_echo_fn');</code></pre>
    
    <h2 class="mt-4">Main Functions</h2>
    <p>To see the complete list of functions you can either look at the Cli class or the gist with the API documentation</p>

    <h4 class="mt-4">set($name, $function)</h4>
    <p>Registers a function.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Cli::set('function_name', 'callback_name');</code></pre>
    <p>The function that is registered can have multiple parameters that will be passed from the command line.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Cli::set('test', 'fn_test');
function fn_test($param1, $param2) {
    Cli::echo("Param1: $param1");
    Cli::echo("Param2: $param2");
}</code></pre>
    <p>If the function returns false it is possible to see the error by calling Cli::last_error;</p>
     <h4 class="mt-4">run($argv)</h4>
    <p>Executes the function passed as argument.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Cli::run($argv);</code></pre>

    <h4 class="mt-4">echo($msg), success($msg), error($msg)</h4>
    <p>Prints a message to the console.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Cli::echo('Message');
Cli::success('Success message');
Cli::error('Error message');</code></pre>

    <h2 class="mt-4">Examples</h2>

    <h4 class="mt-4">Register and execute a function</h4>
    <p>This example shows how to register a function and how to execute it from the command line.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">function test_echo_fn($data) {
    Cli::success("Params:");
    var_dump($data);
}
Cli::set('test_echo', 'test_echo_fn');

// Execute from command line
// $ php cli.php test_echo foo bar
</code></pre>

    <h4 class="mt-4">Draw a table</h4>
    <p>This example shows how to draw a table on the console.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">function tabletest() {
    Cli::drawTable([
        ['id' => 1, 'name' => 'foo'],
        ['id' => 2, 'name' => 'bar'],
        ['id' => 3, 'name' => 'baz'],
    ]);
}
Cli::set('table_test', 'Modules\TestCli\tabletest');

// Execute from command line
// $ php cli.php table_test
</code></pre>

</div>