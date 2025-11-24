<?php
namespace Modules\Docs\Pages;
/**
 * @title Cli
 * @guide framework
 * @order 
 * @tags cli, command line, shell, console, php cli.php, functions, echo, success, error, Cli::set, Cli::run, Cli::echo, drawTable, shell functions, update-paths, build-version, create-administrator
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Cli Class</h1>
    <p class="text-muted">Revision: 2025-11-11</p>
    <p>Manage command-line functions with exception-based error handling and formatted output.</p>

    <h2 class="mt-4">Usage</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash"># List all available commands
php milkadmin/cli.php

# Execute a specific command
php milkadmin/cli.php function_name arg1 arg2</code></pre>

    <h2 class="mt-4">Registering Commands</h2>

    <p><strong>In modules (extends AbstractModule):</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class Posts extends AbstractModule {
    public function shellTest() {
        Cli::echo("Test command executed");
    }
}
// Automatically registered as: posts:test</code></pre>

    <p><strong>Manual registration:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Cli::set('my:command', function($param1, $param2) {
    Cli::echo("Param1: $param1, Param2: $param2");
});</code></pre>

    <h2 class="mt-4">Exception Handling</h2>
    <p>Throw exceptions in your CLI functions - the framework catches and displays them automatically:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Exceptions\CliException;

Cli::set('user:validate', function($email) {
    if (empty($email)) {
        throw new CliException("Email required");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new CliException("Invalid email: $email");
    }
    Cli::success("Valid email!");
});</code></pre>

    <p><strong>Available exceptions:</strong></p>
    <ul>
        <li><code>CliException</code> - Configuration/validation errors</li>
        <li><code>CliFunctionExecutionException</code> - Runtime errors (auto-wrapped)</li>
    </ul>

    <h2 class="mt-4">Methods</h2>

    <h4 class="text-primary mt-4">set(string $name, callable $function) : void</h4>
    <p>Registers a CLI function.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Cli::set('test', function($arg) {
    Cli::echo("Argument: $arg");
});</code></pre>

    <h4 class="text-primary mt-4">run(array $argv) : bool</h4>
    <p>Executes a registered function. Handles exceptions automatically.</p>

    <h4 class="text-primary mt-4">callFunction(string $name, ...$args) : void</h4>
    <p>Calls a registered function programmatically.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Cli::callFunction('test', 'value1', 'value2');</code></pre>

    <h4 class="text-primary mt-4">getAllFn() : array</h4>
    <p>Returns names of all registered functions.</p>

    <h4 class="text-primary mt-4">isCli() : bool</h4>
    <p>Checks if running from command line.</p>

    <h4 class="text-primary mt-4">echo(string $msg) : void</h4>
    <p>Prints a message.</p>

    <h4 class="text-primary mt-4">success(string $msg) : void</h4>
    <p>Prints a success message in green.</p>

    <h4 class="text-primary mt-4">error(string $msg) : void</h4>
    <p>Prints an error message in red.</p>

    <h4 class="text-primary mt-4">drawTitle(string $title, int $padding = 4, string $color = "\033[1;36m") : void</h4>
    <p>Draws a formatted title box with automatic width.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Cli::drawTitle("My App");
// ╔═══════════╗
// ║  My App   ║
// ╚═══════════╝</code></pre>

    <h4 class="text-primary mt-4">drawSeparator(string $title = '', int $width = 40, string $color = "\033[0;33m") : void</h4>
    <p>Draws a separator line with optional centered title.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Cli::drawSeparator("Settings");
// ━━━━ Settings ━━━━</code></pre>

    <h4 class="text-primary mt-4">drawTable(array $data, array $columns = null) : void</h4>
    <p>Draws a formatted table.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Cli::drawTable([
    ['id' => 1, 'name' => 'John'],
    ['id' => 2, 'name' => 'Jane']
]);</code></pre>

    <h2 class="mt-4">System Commands</h2>

    <h4 class="text-primary mt-4">create-administrator</h4>
    <p>Emergency administrator recovery command. Creates a new admin user with random secure password.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash"># Auto-generate username and email
php milkadmin/cli.php create-administrator

# Custom username
php milkadmin/cli.php create-administrator admin_user

# Custom username and email
php milkadmin/cli.php create-administrator admin_user admin@company.com</code></pre>

    <div class="alert alert-warning">
        <strong><i class="bi bi-exclamation-triangle"></i> Emergency Use Only</strong>
    </div>

    <h4 class="text-primary mt-4">build-version</h4>
    <p>Updates the application version in configuration and database.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">php milkadmin/cli.php build-version</code></pre>

    <h4 class="text-primary mt-4">update-paths</h4>
    <p>Updates directory paths and base URL configuration when moving the installation to a new location or changing the URL.</p>
    <p>This command updates:</p>
    <ul>
        <li><code>public_html/milkadmin.php</code> - Updates MILK_DIR and LOCAL_DIR paths</li>
        <li><code>milkadmin_local/config.php</code> - Updates base_url (when URL parameter is provided)</li>
    </ul>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash"># Update only directory paths (automatic detection)
php milkadmin/cli.php update-paths

# Update paths AND change the base URL
php milkadmin/cli.php update-paths "http://localhost/new-path/public_html/"

# Example: moving to production
php milkadmin/cli.php update-paths "https://www.mysite.com/admin/"</code></pre>

    <div class="alert alert-info">
        <strong><i class="bi bi-info-circle"></i> Use Case</strong>
        <p class="mb-0">Run this command after moving the installation directory or deploying to a different server/domain.</p>
    </div>

    <h2 class="mt-4">Examples</h2>

    <h4 class="mt-4">Complete CLI Command</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Exceptions\CliException;

Cli::set('user:list', function($role = null) {
    Cli::drawTitle("User Listing");

    // Validation with exceptions
    if ($role && !in_array($role, ['admin', 'user'])) {
        throw new CliException("Invalid role. Use: admin, user");
    }

    // Fetch and display data
    $users = [
        ['id' => 1, 'name' => 'John', 'role' => 'admin'],
        ['id' => 2, 'name' => 'Jane', 'role' => 'user']
    ];

    Cli::drawTable($users);
    Cli::success("Done!");
});

// Execute: php milkadmin/cli.php user:list admin
</code></pre>

</div>