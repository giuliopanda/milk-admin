<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title Storage custom Settings
 * @category Framework
 * @order 
 * @tags 
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Settings Class Documentation</h1>
        
    <h2>Description</h2>
    <p>The <code>Settings</code> class provides a comprehensive system for managing application configurations organized by groups, with automatic file persistence using JSON format.</p>
   
    <p><strong>Main features:</strong></p>
    <ul>
        <li>Organization by logical groups</li>
        <li>Lazy loading (only when needed)</li>
        <li>In-memory cache for performance</li>
        <li>Automatic persistence of modified data</li>
        <li>Advanced search functionality</li>
    </ul>

    <h2>Main Public Methods</h2>
    
     <h4 class="mt-4">set($key, $value, $group = null)</h4>
    <p>Sets a value in the specified group.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function set(string $key, mixed $value, ?string $group = null): void</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$key</code> - <code>String</code> - The setting key.</li>
        <li><code>$value</code> - <code>mixed</code> - The value to store (any JSON-serializable type).</li>
        <li><code>$group</code> - <code>String</code> (optional) - The group name (uses 'default' if null).</li>
    </ul>
    <p><strong>Examples:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Save in default group
Settings::set('app_name', 'My Application');

// Save in 'email' group
Settings::set('smtp_host', 'smtp.gmail.com', 'email');

// Save arrays or objects
Settings::set('database_config', ['host' => 'localhost', 'port' => 3306]);</code></pre>
    
     <h4 class="mt-4">get($key, $group = null)</h4>
    <p>Retrieves a value from the specified group.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function get(string $key, ?string $group = null): mixed</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$key</code> - <code>String</code> - The setting key to retrieve.</li>
        <li><code>$group</code> - <code>String</code> (optional) - The group name (uses 'default' if null).</li>
    </ul>
    <p><strong>Returns:</strong> <code>mixed|null</code> - The setting value or <code>null</code> if not found.</p>
    <p><strong>Examples:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Retrieve from default group
$app_name = Settings::get('app_name');

// Retrieve from 'email' group
$smtp_host = Settings::get('smtp_host', 'email');

// Handle non-existent value
$setting = Settings::get('nonexistent') ?? 'default_value';</code></pre>
    
     <h4 class="mt-4">get_all($group = null)</h4>
    <p>Retrieves all data from a group as an associative array.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function get_all(?string $group = null): array</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$group</code> - <code>String</code> (optional) - The group name (uses 'default' if null).</li>
    </ul>
    <p><strong>Returns:</strong> <code>Array</code> - All key-value pairs from the group.</p>
    <p><strong>Examples:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Retrieve all settings from default group
$default_settings = Settings::get_all();

// Retrieve all email settings
$email_config = Settings::get_all('email');</code></pre>

     <h4 class="mt-4">has_key($key, $group = null)</h4>
    <p>Checks if a key exists in the specified group.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function has_key(string $key, ?string $group = null): bool</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$key</code> - <code>String</code> - The key to check.</li>
        <li><code>$group</code> - <code>String</code> (optional) - The group name (uses 'default' if null).</li>
    </ul>
    <p><strong>Returns:</strong> <code>Boolean</code> - <code>true</code> if the key exists, <code>false</code> otherwise.</p>
    <p><strong>Examples:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Check existence in default group
if (Settings::has_key('app_name')) {
    echo "App name is configured";
}

// Check existence in a specific group
if (Settings::has_key('smtp_host', 'email')) {
    $host = Settings::get('smtp_host', 'email');
}</code></pre>
    
     <h4 class="mt-4">save()</h4>
    <p>Saves all modified groups to their respective JSON files.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function save(): void</code></pre>
    <p>This method implements efficient persistence by saving only groups that have been modified since the last save.</p>
    <p class="alert alert-info">Saving is handled automatically at the end of code execution, so there is no need to rewrite it</p>
    <p><strong>Examples:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Set some configurations
Settings::set('app_name', 'My App');
Settings::set('smtp_host', 'smtp.gmail.com', 'email');

// Save all changes to files
Settings::save();</code></pre>

    <h2>Search Methods</h2>
    
     <h4 class="mt-4">search_by_value($search_value, $group = null)</h4>
    <p>Searches for a value in a specific group or all groups.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function search_by_value(mixed $search_value, ?string $group = null): array</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$search_value</code> - <code>mixed</code> - The value to search for.</li>
        <li><code>$group</code> - <code>String</code> (optional) - The group to search in (searches all if null).</li>
    </ul>
    <p><strong>Returns:</strong> <code>Array</code> - Results organized by group.</p>
    <p><strong>Examples:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Search for 'gmail' in all groups
$results = Settings::search_by_value('gmail');

// Search only in 'email' group
$email_results = Settings::search_by_value('localhost', 'email');</code></pre>
    
     <h4 class="mt-4">search_by_key($search_key, $group = null)</h4>
    <p>Searches for keys that contain a specific string.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function search_by_key(string $search_key, ?string $group = null): array</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$search_key</code> - <code>String</code> - The string to search for in keys.</li>
        <li><code>$group</code> - <code>String</code> (optional) - The group to search in (searches all if null).</li>
    </ul>
    <p><strong>Returns:</strong> <code>Array</code> - Results organized by group.</p>
    <p><strong>Examples:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Find all keys containing 'database'
$db_settings = Settings::search_by_key('database');

// Find SMTP keys in email group
$smtp_settings = Settings::search_by_key('smtp', 'email');</code></pre>

    <h2>Management Methods</h2>
    
     <h4 class="mt-4">remove_key($key, $group = null)</h4>
    <p>Removes a key from a group.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function remove_key(string $key, ?string $group = null): void</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$key</code> - <code>String</code> - The key to remove.</li>
        <li><code>$group</code> - <code>String</code> (optional) - The group name (uses 'default' if null).</li>
    </ul>
    <p><strong>Examples:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Remove from default group
Settings::remove_key('old_setting');

// Remove from specific group
Settings::remove_key('temp_config', 'cache');</code></pre>
    
     <h4 class="mt-4">clear_group($group = null)</h4>
    <p>Completely empties a group by removing all settings.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public static function clear_group(?string $group = null): void</code></pre>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>$group</code> - <code>String</code> (optional) - The group name (uses 'default' if null).</li>
    </ul>
    <p><strong>Examples:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Clear default group
Settings::clear_group();

// Clear cache group
Settings::clear_group('cache');</code></pre>

    <h2>Complete Usage Example</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Application configuration
Settings::set('app_name', 'My CMS');
Settings::set('app_version', '1.0.0');
Settings::set('debug_mode', true);

// Email configuration
Settings::set('smtp_host', 'smtp.gmail.com', 'email');
Settings::set('smtp_port', 587, 'email');
Settings::set('smtp_username', 'user@example.com', 'email');

// Database configuration
Settings::set('host', 'localhost', 'database');
Settings::set('port', 3306, 'database');
Settings::set('charset', 'utf8mb4', 'database');

// Retrieving configurations
$app_name = Settings::get('app_name');
$email_config = Settings::get_all('email');
$db_host = Settings::get('host', 'database');

// Check existence
if (Settings::has_key('debug_mode')) {
    $debug = Settings::get('debug_mode');
}

// Search
$smtp_settings = Settings::search_by_key('smtp', 'email');
$localhost_configs = Settings::search_by_value('localhost');

// Save changes
Settings::save();</code></pre>

    <p><strong>Important Notes:</strong></p>
    <ul>
        <li>Configuration files are saved in the <code>storage/</code> directory as JSON files</li>
        <li>Each group is saved in a separate file (e.g., <code>email.json</code>, <code>database.json</code>)</li>
        <li>Loading is lazy: data is loaded only when needed</li>
        <li>In-memory cache improves performance for repeated accesses</li>
        <li>Only modified groups are saved to disk</li>
        <li>Group names are sanitized to prevent directory traversal attacks</li>
    </ul>
</div>