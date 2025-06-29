<?php
namespace Modules\docs;
/**
 * @title Get
 * @category Framework
 * @order 
 * @tags 
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Get Class</h1>
    
    <p>The Get Class is a facade class to facilitate access and management of core system functionalities such as database connections, email sending, module and controller loading, and theme page and module management.</p>

    <h2 class="mt-4">Database Management</h2>

    <h4 class="mt-4">db()</h4>
    <p>Creates or returns an instance of the primary system database connection. Implements the singleton pattern and supports both MySQL and SQLite. The database type is determined by the 'db_type' configuration.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get database connection and run a query
$db = Get::db();
$results = $db->query("SELECT * FROM users WHERE active = 1");</code></pre>

    <h4 class="mt-4">db2()</h4>
    <p>Creates or returns an instance of the secondary database connection. The system can use two databases: one for configuration and one for data. This method handles the connection to the secondary database (for data).</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get secondary database connection
$db2 = Get::db2();
$results = $db2->query("SELECT * FROM data_table WHERE status = 'active'");</code></pre>

    <h4 class="mt-4">schema($table, $db = null)</h4>
    <p>Returns a schema instance for the specified table. Supports both MySQL and SQLite and uses the appropriate class based on the configured database type.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('users');
// Or with specific database
$schema = Get::schema('users', Get::db2());</code></pre>

    <h2 class="mt-4">Email Management</h2>

    <h4 class="mt-4">mail()</h4>
    <p>Creates or returns an instance of the Mail class for sending emails. Implements the singleton pattern and automatically configures itself with SMTP settings from the configuration file.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Send an email
Get::mail()
    ->to('recipient@example.com')
    ->from('sender@example.com')
    ->subject('Hello')
    ->message('This is a test email')
    ->send();</code></pre>

    <h2 class="mt-4">Module and Theme Management</h2>

    <h4 class="mt-4">load_modules()</h4>
    <p>Loads all module controllers and theme functions. This method initializes all module controllers and theme functions required for the application to work properly.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Get::load_modules();</code></pre>

    <h4 class="mt-4">theme_page($page, $content, $variables)</h4>
    <p>Loads the theme page and passes any module content. Module content receives variables passed as the third parameter and are optional. The page name is mandatory and generally prints the content passed in Theme::set('content').</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Three possible ways to call theme_page
// 1. pass only the theme page name (will load page_name.page.php file)
Theme::set('content', '...');
Get::theme_page('page_name');

// 2. pass the page name and content
Get::theme_page('page_name', '', 'content');

// 3. pass the page name, template to load and variables for the template
Get::theme_page('theme_page', __DIR__ . '/assets/modules_page.php', ['my_vars' => '...']);</code></pre>

    <h4 class="mt-4">theme_plugin($module, $variables)</h4>
    <p>Loads the requested theme plugin passing the necessary variables. Returns the content of the loaded module.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$content = Get::theme_plugin('sidebar', ['active' => 'home']);</code></pre>

    <h2 class="mt-4">Path and URI Management</h2>

    <h4 class="mt-4">dir_path($file)</h4>
    <p>Returns the secure path of a file. Protects against path traversal attacks by ensuring paths remain within the site directory. Also checks for customization files with the same name in the customizations directory.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$file = Get::dir_path(THEME_DIR.'/template_parts/sidebar.php');</code></pre>

    <h4 class="mt-4">uri_path($file)</h4>
    <p>Returns the URI path for resources like JS or CSS files. Handles theme customizations and proper URL formatting.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;img src=&quot;&lt;?php echo Get::uri_path($path); ?&gt;&quot; alt=&quot;Logo&quot;&gt;</code></pre>

    <h4 class="mt-4">temp_dir()</h4>
    <p>Returns the path of the temporary directory with trailing slash. Provides the path to the system's temporary directory.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$path = Get::temp_dir() . $file;</code></pre>

    <h2 class="mt-4">Date and Time Management</h2>

    <h4 class="mt-4">date_time_zone()</h4>
    <p>Returns the current date and time based on configured timezone. Creates a DateTime object using the timezone settings from the configuration file.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$now = Get::date_time_zone();
echo $now->format('Y-m-d H:i:s');</code></pre>

    <h4 class="mt-4">format_date($date, $format = 'date')</h4>
    <p>Formats a date based on the system settings. Converts a date string to the specified format according to system configuration.<br>
    <strong>$date</strong>: the date to format (in MySQL format) or DateTime object<br>
    <strong>$format</strong>: the format to use: 'date' (only date), 'time' (only time), or 'datetime' (both)</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$formatted_date = Get::format_date('2021-01-01', 'date');
$formatted_datetime = Get::format_date('2021-01-01 14:30:00', 'datetime');</code></pre>

    <h2 class="mt-4">Response Management</h2>

    <h4 class="mt-4">response_json(array $data)</h4>
    <p>Sends a JSON response to the client and terminates application execution. This method also closes database connections and saves settings.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Get::response_json(['status' => 'success', 'data' => $result]);</code></pre>

    <h2 class="mt-4">Mathematical Parser</h2>

    <h4 class="mt-4">parser($data = null)</h4>
    <p>Returns an instance of the advanced mathematical parser. Creates and returns a configured instance of the mathematical expression parser. <strong>Note:</strong> This function is experimental and has only been unit tested.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$parser = Get::parser($data);
$result = $parser->evaluate('x + y', ['x' => 5, 'y' => 3]);</code></pre>

    <h2 class="mt-4">Dependency Container</h2>
    
    <p>The dependency container system allows managing contractor classes, specifically designed to identify and manage classes within modules that are intended for external use. This facilitates integration between modules and enables a more modular and testable architecture.</p>

    <h4 class="mt-4">bind($service_name, $implementation, $singleton = false, $arguments = [])</h4>
    <p>Registers a service in the dependency container. You can register a regular class, a singleton service, or a factory function. Registered services can then be instantiated using <code>make()</code>.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Register a regular class
Get::bind('database', DatabaseConnection::class);

// Register a singleton service
Get::bind('config', AppConfig::class, true);

// Register a factory function with initialization arguments
Get::bind('logger', function($filename) {
    return new FileLogger($filename);
}, false, ['app.log']);</code></pre>

    <h4 class="mt-4">make($name, $arguments = [])</h4>
    <p>Creates an instance of a registered service from the dependency container. Services must first be registered using the <code>bind()</code> method. This method is used together with <code>bind()</code> to manage module contractor classes.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// First register services
Get::bind('database', DatabaseConnection::class);
Get::bind('config', AppConfig::class, true);
Get::bind('logger', LoggerFactory::class);

// Then create service instances
$db = Get::make('database', ['localhost', 'user', 'pass']);
$config = Get::make('config', []);
$logger = Get::make('logger', ['app.log']);</code></pre>

    <h4 class="mt-4">has($name)</h4>
    <p>Checks if a service is registered in the container. Useful for checking service availability before attempting to instantiate it.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if (Get::has('database')) {
    $db = Get::make('database', ['localhost', 'user', 'pass']);
} else {
    // Handle missing service
}</code></pre>

    <h2 class="mt-4">Client IP Management</h2>

    <h4 class="mt-4">client_ip($trust_proxy_headers = false)</h4>
    <p>Gets the client IP address. This method detects and returns the client's IP address, handling various scenarios including proxy headers and IPv6 to IPv4 conversion for localhost.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Standard IP
$ip = Get::client_ip();

// IP considering proxy headers (useful with Cloudflare, load balancers, etc.)
$ip = Get::client_ip(true);</code></pre>

</div>
