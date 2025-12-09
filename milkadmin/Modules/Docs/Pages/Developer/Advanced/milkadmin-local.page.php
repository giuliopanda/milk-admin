<?php
namespace Modules\Docs\Pages;
/**
 * @title Milkadmin Local Directory
 * @category Advanced
 * @order 4
 * @tags customization, override, local, namespace, configuration
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

<h1>Milkadmin Local Directory</h1>

<p class="lead">
    The <code>milkadmin_local</code> directory is the customization layer for your MilkAdmin installation. It allows you to override core functionality, add custom modules, and manage installation-specific settings.
</p>

<hr>

<h2>Overview</h2>

<p>
    The <code>milkadmin_local</code> directory serves as the customization and configuration layer that defines what makes each installation unique. It handles:
</p>

<ul>
    <li>Language files and translations</li>
    <li>Media files and uploads</li>
    <li>Storage (including SQLite databases)</li>
    <li>Configuration files</li>
    <li>Custom code and overrides</li>
</ul>

<div class="alert alert-info">
    <strong>Key Concept:</strong> This directory is kept separate from the core <code>milkadmin</code> framework, making it easy to update the system without losing your customizations.
</div>

<hr>

<h2>Overriding Core Files</h2>

<p>
    You can override virtually any file from the core framework by replicating its folder structure inside <code>milkadmin_local</code>. This includes:
</p>

<ul>
    <li>Views (templates)</li>
    <li>Email templates</li>
    <li>Assets (CSS, JS, images)</li>
    <li>PHP files from modules</li>
    <li>Theme files</li>
</ul>

<h3>How It Works</h3>

<p>
    Simply maintain the same folder structure in <code>milkadmin_local</code> as exists in <code>milkadmin</code>. When the system loads a file, it checks <code>milkadmin_local</code> first, then falls back to the core <code>milkadmin</code> directory.
</p>

<h3>Example: Overriding a Module View</h3>

<pre><code>Core file:
milkadmin/Modules/Posts/Views/list.php

Override file:
milkadmin_local/Modules/Posts/Views/list.php</code></pre>

<hr>

<h2>Overriding PHP Files and Namespaces</h2>

<p>
    When overriding PHP files from modules, you must update the namespace by adding <code>Local\</code> as a prefix.
</p>

<h3>Example: Overriding a Controller</h3>

<p>Core module controller:</p>
<pre><code class="language-php">// milkadmin/Modules/Posts/PostsController.php
namespace Modules\Posts;

class PostsController {
    // ...
}</code></pre>

<p>Override in local:</p>
<pre><code class="language-php">// milkadmin_local/Modules/Posts/PostsController.php
namespace Local\Modules\Posts;

class PostsController {
    // Your customized version
}</code></pre>

<div class="alert alert-warning">
    <strong>Important:</strong> The <code>Local\</code> namespace prefix is required for all PHP overrides. Without it, the autoloader won't find your custom classes.
</div>

<hr>

<h2>Custom Modules</h2>

<p>
    You can create installation-specific modules inside <code>milkadmin_local</code> to keep them separate from the core system. This is useful for:
</p>

<ul>
    <li>Site-specific functionality</li>
    <li>Custom integrations</li>
    <li>Experimental features</li>
    <li>Client-specific requirements</li>
</ul>

<h3>Creating a Custom Module</h3>

<pre><code>milkadmin_local/Modules/CustomFeature/
├── CustomFeatureController.php
├── Views/
│   └── index.php
└── plugin.php</code></pre>

<pre><code class="language-php">// milkadmin_local/Modules/CustomFeature/CustomFeatureController.php
namespace Local\Modules\CustomFeature;

class CustomFeatureController {
    // Your custom module logic
}</code></pre>

<p>
    Remember to use the <code>Local\</code> namespace prefix for all custom modules as well.
</p>

<hr>

<h2>Functions.php - Custom Code</h2>

<p>
    The <code>milkadmin_local/functions.php</code> file is loaded at system startup, similar to WordPress's functions.php. Use it to add custom code without modifying core files.
</p>

<h3>Example Usage</h3>

<pre><code class="language-php">// milkadmin_local/functions.php

// Add custom utility functions
function my_custom_helper($data) {
    return strtoupper($data);
}

// Register custom hooks
add_action('after_login', function() {
    // Custom logic after user login
});

// Modify system behavior
add_filter('post_content', function($content) {
    return $content . ' - Custom Footer';
});</code></pre>

<div class="alert alert-success">
    <strong>Best Practice:</strong> Use <code>functions.php</code> for small customizations and utilities. For larger features, create a proper custom module instead.
</div>

<hr>

<h2>Directory Structure</h2>

<p>Typical <code>milkadmin_local</code> structure:</p>

<pre><code>milkadmin_local/
├── config.php              # Installation-specific configuration
├── functions.php           # Custom code loaded at startup
├── Languages/              # Translation files
├── Media/                  # Uploaded images and files
├── storage/                # SQLite databases and storage
│   └── milk_conf_*.db     # Configuration database
├── Modules/                # Override or custom modules
│   └── Posts/
│       └── PostsController.php
└── Theme/                  # Theme overrides
    └── Plugins/
        └── CustomPlugin/</code></pre>

<hr>

<h2>Configuration Files</h2>

<p>
    The <code>config.php</code> file in <code>milkadmin_local</code> contains installation-specific settings like database credentials, site URL, and custom constants.
</p>

<h2>Storage Directory</h2>

<p>
    The <code>storage/</code> folder inside <code>milkadmin_local</code> contains:
</p>

<ul>
    <li>SQLite database files (when using SQLite)</li>
    <li>Cached data</li>
    <li>Temporary files</li>
    <li>Session data</li>
</ul>

<div class="alert alert-warning">
    <strong>Security Note:</strong> Ensure the <code>milkadmin_local</code> directory is placed outside the web root, as it contains sensitive configuration and database files.
</div>

<hr>

<h2>Best Practices</h2>

<h3>1. Version Control</h3>
<p>
    Add <code>milkadmin_local</code> to your version control system to track customizations separately from core framework updates.
</p>

<h3>2. Namespace Consistency</h3>
<p>
    Always use <code>Local\</code> prefix for namespaces in PHP files within <code>milkadmin_local</code>.
</p>

<h3>3. Override Sparingly</h3>
<p>
    Only override files when necessary. Excessive overrides can make system updates more complex.
</p>

<h3>4. Document Customizations</h3>
<p>
    Keep notes about what you've customized and why, especially for complex overrides.
</p>

<hr>

<h2>See Also</h2>

<ul>
    <li><a href="?page=docs&action=Developer/Advanced/deployment">Server Deployment</a> - Learn about proper folder structure and security</li>
    <li><a href="?page=docs&action=Developer/Modules/create">Creating Modules</a> - Module development basics</li>
</ul>

</div>
