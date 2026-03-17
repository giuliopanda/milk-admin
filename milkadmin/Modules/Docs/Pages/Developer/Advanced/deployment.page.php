<?php
namespace Modules\Docs\Pages;
/**
 * @title Server Deployment
 * @category Advanced
 * @order 3
 * @tags deployment, server, installation, zip, security
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

<h1>Server Deployment</h1>

<p class="lead">
    Deploy MilkAdmin to a web server using a packaged ZIP file and secure folder structure.
</p>

<hr>

<h2>Creating a Deployment Package</h2>

<p>
    Generate a ZIP package for faster server transfer. This creates a compressed archive ready for deployment.
</p>

<pre><code class="language-php">php milkadmin/cli.php build-version zip</code></pre>

<p>This command creates:</p>
<ul>
    <li>A new version folder with all necessary files</li>
    <li>A ZIP archive containing <code>milkadmin</code>, <code>milkadmin_local</code>, and <code>public_html</code> folders</li>
    <li>An <code>install_from_zip.php</code> helper script for server deployment</li>
</ul>

<p>See the <a href="?page=docs&action=Framework/Core/install">Installation / Update</a> documentation for details on version building.</p>

<hr>

<h2>Folder Structure for Security</h2>

<p>
    For security, only the <code>public_html</code> folder should be accessible from the web. The <code>milkadmin</code> and <code>milkadmin_local</code> folders must be placed outside the web server root.
</p>

<h3>Recommended Structure</h3>

<pre><code>/var/www/
├── milkadmin/           # Core framework (outside web root)
├── milkadmin_local/     # Local configurations (outside web root)
└── html/                # Web server root
    └── public_html/     # Only this folder is web-accessible
        ├── index.php
        └── milkadmin.php</code></pre>

<div class="alert alert-warning">
    <strong>Security Note:</strong> Never place <code>milkadmin</code> or <code>milkadmin_local</code> folders inside the web server root. This prevents direct access to core files and configurations.
</div>

<hr>

<h2>Path Configuration</h2>

<p>
    The <code>public_html/milkadmin.php</code> file contains path constants that reference the correct locations of <code>milkadmin</code> and <code>milkadmin_local</code> folders.
</p>

<h3>Available Constants</h3>

<div class="table-responsive">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Constant</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>MILK_DIR</code></td>
                <td>Absolute path to the <code>milkadmin</code> folder</td>
            </tr>
            <tr>
                <td><code>LOCAL_DIR</code></td>
                <td>Absolute path to the <code>milkadmin_local</code> folder</td>
            </tr>
        </tbody>
    </table>
</div>

<h3>Example Configuration</h3>

<pre><code class="language-php">// public_html/milkadmin.php
if (!defined('MILK_DIR')) {
    define('MILK_DIR', realpath(__DIR__."/../milkadmin"));
}

if (!defined('LOCAL_DIR')) {
    define('LOCAL_DIR', realpath(__DIR__."/../milkadmin_local"));
}</code></pre>

<p>
    After uploading to your server, verify and adjust these paths in <code>milkadmin.php</code> to match your server's directory structure.
</p>

<hr>

<h2>Deployment Steps</h2>

<h3>1. Upload Files</h3>

<p>Transfer the ZIP package and install script to your server:</p>
<ul>
    <li>Upload the generated ZIP file</li>
    <li>Upload <code>install_from_zip.php</code></li>
</ul>

<h3>2. Extract Archive</h3>

<p>Access <code>install_from_zip.php</code> via browser. The script will:</p>
<ul>
    <li>Automatically extract the ZIP archive</li>
    <li>Redirect to the installation page</li>
    <li>Delete itself for security</li>
</ul>

<p>Alternatively, manually extract the ZIP and ensure correct folder placement.</p>

<h3>3. Configure Paths</h3>

<p>Edit <code>public_html/milkadmin.php</code> to set correct paths for your server environment.</p>

<h3>4. Install System</h3>

<p>Navigate to <code>public_html/index.php</code> in your browser. The installation process will:</p>
<ul>
    <li>Detect that the system needs installation</li>
    <li>Display the installation form</li>
    <li>Configure database and create necessary tables</li>
    <li>Complete the initial setup</li>
</ul>

<hr>

<h2>Post-Installation</h2>

<h3>Backend Updates</h3>

<p>
    Once installed, you can manage system updates and module installations from the backend admin panel at <code>?page=install</code>.
</p>

<h3>Shell Updates (Modules Only)</h3>

<p>Individual modules can be installed or updated via shell commands:</p>

<pre><code class="language-php">php milkadmin/cli.php {module_name}:install
php milkadmin/cli.php {module_name}:update</code></pre>

<div class="alert alert-info">
    <strong>Note:</strong> Full system installation must be done via browser. Only module-level operations are available via shell.
</div>


<div class="alert alert-warning">The url of the site is written both in milkadmin/config.php and in public_html/milkadmin.php. 
    If you need to change it, you must remember to change it in both places!
</div>

<hr>


<h2>See Also</h2>

<ul>
    <li><a href="?page=docs&action=Framework/Core/install">Installation / Update</a> - Complete installation system reference</li>
</ul>

</div>
