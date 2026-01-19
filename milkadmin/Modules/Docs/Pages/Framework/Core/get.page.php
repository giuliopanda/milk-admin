<?php
namespace Modules\Docs\Pages;
/**
 * @title Get
 * @guide framework
 * @order 
 * @tags Get, db, db2, dbConnection, arrayDb, mail, schema, theme, dependency injection, container, facade, load_modules, theme_plugin, dir_path, uri_path, temp_dir, date_time_zone, format_date, user_timezone, set_user_timezone, user_locale, set_user_locale, timezone, bind, make, has, client_ip, close_connections
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Get Class</h1>
    
    <p>The Get Class is a facade class to facilitate access and management of core system functionalities such as database connections, email sending, module and module loading, and theme page and module management.</p>
    <p>Below is a quick summary of the available methods, followed by detailed sections.</p>

    <h2 class="mt-4">Method Summary</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Parameters</th>
                    <th>Return</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>db()</code></td>
                    <td>-</td>
                    <td>MySql|SQLite|ArrayDb|null</td>
                    <td>Returns the primary database connection (singleton).</td>
                </tr>
                <tr>
                    <td><code>db2()</code></td>
                    <td>-</td>
                    <td>MySql|SQLite|ArrayDb|null</td>
                    <td>Returns the secondary database connection (singleton).</td>
                </tr>
                <tr>
                    <td><code>dbConnection(string $name)</code></td>
                    <td>connection name</td>
                    <td>MySql|SQLite|ArrayDb</td>
                    <td>Returns a named database connection.</td>
                </tr>
                <tr>
                    <td><code>arrayDb()</code></td>
                    <td>-</td>
                    <td>ArrayDb</td>
                    <td>Returns the in-memory ArrayDB adapter (singleton).</td>
                </tr>
                <tr>
                    <td><code>schema($table, $db = null)</code></td>
                    <td>table, db</td>
                    <td>SchemaMysql|SchemaSqlite</td>
                    <td>Returns a schema helper for a table.</td>
                </tr>
                <tr>
                    <td><code>mail()</code></td>
                    <td>-</td>
                    <td>Mail</td>
                    <td>Returns the configured mailer instance.</td>
                </tr>
                <tr>
                    <td><code>loadModules()</code></td>
                    <td>-</td>
                    <td>void</td>
                    <td>Loads modules and theme plugins.</td>
                </tr>
                <tr>
                    <td><code>themePlugin(string $module, array $variables = [])</code></td>
                    <td>module, variables</td>
                    <td>string</td>
                    <td>Loads a theme plugin and returns its output.</td>
                </tr>
                <tr>
                    <td><code>dirPath(string $file)</code></td>
                    <td>absolute path</td>
                    <td>string</td>
                    <td>Resolves a safe file path inside project roots.</td>
                </tr>
                <tr>
                    <td><code>uriPath(string $file)</code></td>
                    <td>file path</td>
                    <td>string</td>
                    <td>Builds the public URI for a file.</td>
                </tr>
                <tr>
                    <td><code>tempDir()</code></td>
                    <td>-</td>
                    <td>string</td>
                    <td>Returns the temp directory path.</td>
                </tr>
                <tr>
                    <td><code>dateTimeZone()</code></td>
                    <td>-</td>
                    <td>DateTime</td>
                    <td>Returns a DateTime in the configured timezone.</td>
                </tr>
                <tr>
                    <td><code>formatDate($date, string $format = 'date', bool $timezone = false)</code></td>
                    <td>date, format, timezone</td>
                    <td>string</td>
                    <td>Formats dates with locale-aware patterns.</td>
                </tr>
                <tr>
                    <td><code>userTimezone()</code></td>
                    <td>-</td>
                    <td>string</td>
                    <td>Returns the current user timezone.</td>
                </tr>
                <tr>
                    <td><code>setUserTimezone(?string $timezone)</code></td>
                    <td>timezone</td>
                    <td>void</td>
                    <td>Overrides timezone for the current request.</td>
                </tr>
                <tr>
                    <td><code>userLocale()</code></td>
                    <td>-</td>
                    <td>string</td>
                    <td>Returns the current user locale.</td>
                </tr>
                <tr>
                    <td><code>setUserLocale(?string $locale)</code></td>
                    <td>locale</td>
                    <td>void</td>
                    <td>Overrides locale for the current request.</td>
                </tr>
                <tr>
                    <td><code>bind($service, $implementation, bool $singleton = false, array $arguments = [])</code></td>
                    <td>service, implementation, singleton, args</td>
                    <td>void</td>
                    <td>Registers a service in the container.</td>
                </tr>
                <tr>
                    <td><code>make($name, array $arguments = [])</code></td>
                    <td>name, args</td>
                    <td>mixed</td>
                    <td>Creates a service from the container.</td>
                </tr>
                <tr>
                    <td><code>has($name)</code></td>
                    <td>name</td>
                    <td>bool</td>
                    <td>Checks if a service is registered.</td>
                </tr>
                <tr>
                    <td><code>clientIp(bool $trust_proxy_headers = false)</code></td>
                    <td>trust proxy</td>
                    <td>string</td>
                    <td>Returns the client IP address.</td>
                </tr>
                <tr>
                    <td><code>closeConnections()</code></td>
                    <td>-</td>
                    <td>void</td>
                    <td>Closes db/db2 connections and resets the singletons.</td>
                </tr>
            </tbody>
        </table>
    </div>

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

    <h4 class="mt-4">dbConnection(string $name)</h4>
    <p>Returns a named database connection from the configuration or runtime additions. Useful when you define more than two connections.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get a custom connection
$analytics = Get::dbConnection('analytics');</code></pre>

    <h4 class="mt-4">arrayDb()</h4>
    <p>Returns the ArrayDB adapter (in-memory database) as a singleton. It is initialized with an <code>ArrayEngine</code> and can be populated via <code>addTable()</code> or <code>connect()</code>.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// In-memory database
$arrayDb = Get::arrayDb();
$arrayDb->addTable('users', [
    ['id' => 1, 'name' => 'Mario'],
    ['id' => 2, 'name' => 'Laura'],
], 'id');</code></pre>

    <h4 class="mt-4">schema($table, $db = null)</h4>
    <p>Returns a schema instance for the specified table. Supports both MySQL and SQLite and uses the appropriate class based on the configured database type.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$schema = Get::schema('users');
// Or with specific database
$schema = Get::schema('users', Get::db2());</code></pre>

    <h4 class="mt-4">closeConnections()</h4>
    <p>Closes the primary and secondary connections and resets their singletons. This is useful in long-running scripts or CLI tasks.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Get::closeConnections();</code></pre>

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

    <h4 class="mt-4">loadModules()</h4>
    <p>Loads all module modules and theme functions. This method initializes all module modules and theme functions required for the application to work properly.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Get::loadModules();</code></pre>

    <h4 class="mt-4">themePlugin($module, $variables)</h4>
    <p>Loads the requested theme plugin passing the necessary variables. Returns the content of the loaded module.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$content = Get::themePlugin('sidebar', ['active' => 'home']);</code></pre>

    <h2 class="mt-4">Path and URI Management</h2>

    <h4 class="mt-4">dirPath($file)</h4>
    <p>Returns the secure path of a file. Protects against path traversal attacks by ensuring paths remain within the site directory. Also checks for customization files with the same name in the milkadmin_local directory.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$file = Get::dirPath(THEME_DIR.'/template_parts/sidebar.php');</code></pre>

    <h4 class="mt-4">uriPath($file)</h4>
    <p>Returns the URI path for resources like JS or CSS files. Handles theme milkadmin_local and proper URL formatting.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;img src=&quot;&lt;?php echo Get::uriPath($path); ?&gt;&quot; alt=&quot;Logo&quot;&gt;</code></pre>

    <h4 class="mt-4">tempDir()</h4>
    <p>Returns the path of the temporary directory with trailing slash. Provides the path to the system's temporary directory.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$path = Get::tempDir() . $file;</code></pre>

    <h2 class="mt-4">Date and Time Management</h2>

    <h4 class="mt-4">dateTimeZone()</h4>
    <p>Returns the current date and time based on configured timezone. Creates a DateTime object using the timezone settings from the configuration file.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$now = Get::dateTimeZone();
echo $now->format('Y-m-d H:i:s');</code></pre>

    <h4 class="mt-4">formatDate($date, $format = 'date', $timezone = false)</h4>
    <p>Formats a date based on the system settings. Converts a date string to the specified format according to system configuration.<br>
    <strong>$date</strong>: the date to format (in MySQL format) or DateTime object<br>
    <strong>$format</strong>: the format to use: 'date' (only date), 'time' (only time), or 'datetime' (both)<br>
    <strong>$timezone</strong>: optional timezone to convert the date to (e.g., 'Europe/Rome', 'America/New_York')</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$formatted_date = Get::formatDate('2021-01-01', 'date');
$formatted_datetime = Get::formatDate('2021-01-01 14:30:00', 'datetime');

// Format date in user's timezone
$userTimezone = Get::userTimezone();
$formatted_user_date = Get::formatDate('2021-01-01 14:30:00', 'datetime', $userTimezone);</code></pre>

    <h4 class="mt-4">userTimezone()</h4>
    <p>Returns the current user's timezone identifier. The timezone is determined based on the following priority: explicitly set timezone using <code>setUserTimezone()</code>, authenticated user's timezone from their profile, or UTC as fallback. Requires <code>use_user_timezone</code> configuration to be enabled; otherwise always returns 'UTC'.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Get current user's timezone
$timezone = Get::userTimezone();  // Returns 'Europe/Rome', 'America/New_York', or 'UTC'

// Use with formatDate to display dates in user's timezone
$displayDate = Get::formatDate($date, 'datetime', Get::userTimezone());

// Create DateTime in user's timezone
$now = new DateTime('now', new DateTimeZone(Get::userTimezone()));</code></pre>

    <h4 class="mt-4">setUserTimezone($timezone)</h4>
    <p>Explicitly sets the timezone for the current request. This overrides the authenticated user's timezone.</p>

    <h2 class="mt-4">Locale Management</h2>

    <h4 class="mt-4">userLocale()</h4>
    <p>Returns the current user locale. It checks the explicitly set locale, then the authenticated user's locale, and falls back to the configured default.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$locale = Get::userLocale(); // e.g. 'it_IT'</code></pre>

    <h4 class="mt-4">setUserLocale($locale)</h4>
    <p>Explicitly sets the locale for the current request. This is used by <code>formatDate()</code> and other locale-aware helpers.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Get::setUserLocale('en_US');</code></pre>

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
</code></pre>

    <h4 class="mt-4">has($name)</h4>
    <p>Checks if a service is registered in the dependency container.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if (Get::has('Auth')) {
    $auth = Get::make('Auth');
}</code></pre>

    <h2 class="mt-4">Client IP Management</h2>

    <h4 class="mt-4">clientIp($trust_proxy_headers = false)</h4>
    <p>Gets the client IP address. This method detects and returns the client's IP address, handling various scenarios including proxy headers and IPv6 to IPv4 conversion for localhost.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Standard IP
$ip = Get::clientIp();

// IP considering proxy headers (useful with Cloudflare, load balancers, etc.)
$ip = Get::clientIp(true);</code></pre>

</div>
