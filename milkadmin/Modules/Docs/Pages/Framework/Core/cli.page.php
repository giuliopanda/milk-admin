<?php
namespace Modules\Docs\Pages;
/**
 * @title Cli
 * @guide framework
 * @order 
 * @tags cli, command line, shell, console, php cli.php, functions, echo, success, error, Cli::set, Cli::run, Cli::echo, drawTable, shell functions
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
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">php milkadmin/cli.php function_name</code></pre>

     <h4 class="mt-4">Registering a function</h4>
    
    <p>If you are inside a module class that extends AbstractModule, you just need to write a function that starts with shell_ and the subsequent part of the function will be a new command registered in cli.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class Posts extends AbstractModule {
    ...
    public function shellTest() {
        Cli::echo("Test");
    }
    ...
}</code></pre>

<p>Otherwise you can register an external function making sure to include the correct namespace:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">function testEchoFn(...$data) {
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
function fnTest($param1, $param2) {
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

    <h2 class="mt-4">System Commands</h2>

    <h4 class="mt-4">Administrator Recovery</h4>
    <p>The system includes a built-in CLI command for emergency administrator recovery. This command creates a new administrator user when access is lost.</p>
    
    <div class="alert alert-warning">
        <strong><i class="bi bi-exclamation-triangle"></i> Emergency Use Only:</strong> This command should only be used when administrator access is lost and recovery is needed.
    </div>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash"># Create admin with automatic username and email
php milkadmin/cli.php create-administrator

# Create admin with custom username
php milkadmin/cli.php create-administrator recovery_admin

# Create admin with custom username and email
php milkadmin/cli.php create-administrator recovery_admin admin@company.com</code></pre>

    <p>The command will generate a secure random password and display the complete credentials:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-text">=== ADMINISTRATOR CREDENTIALS ===
Username: emergency_admin_20250811_142530
Password: 8hF$2kLm9nP!
Email: admin@localhost.com
User ID: 15
=================================

IMPORTANT: Save these credentials securely!
The password will not be displayed again.</code></pre>

    <p><strong>Security Features:</strong></p>
    <ul>
        <li>Can only be executed from command line (not via web)</li>
        <li>Generates 12-character secure passwords with mixed case, numbers, and symbols</li>
        <li>Checks for username uniqueness to prevent conflicts</li>
        <li>Creates users with full administrator privileges</li>
    </ul>

    <h2 class="mt-4">Examples</h2>

    <h4 class="mt-4">Register and execute a function</h4>
    <p>This example shows how to register a function and how to execute it from the command line.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">function testEchoFn($data) {
    Cli::success("Params:");
    var_dump($data);
}
Cli::set('test_echo', 'test_echo_fn');

// Execute from command line
// $ php milkadmin/cli.php test_echo foo bar
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
// $ php milkadmin/cli.php table_test
</code></pre>

</div>