<?php
namespace Modules\Docs\Pages;
/**
 * @title CLI Commands
 * @guide developer
 * @order 25
 * @tags CLI, command-line, install, installation, update, migrations, database, terminal, shell, commands, install table, update table, db
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>CLI Commands</h1>
    <p class="text-muted">Revision: 2025/10/21</p>
    <p class="lead">MilkAdmin provides command-line tools to manage module installation, database schema updates, and other administrative tasks.</p>

    <div class="alert alert-success">
        <h5 class="alert-heading"><i class="bi bi-terminal"></i> Why use CLI commands?</h5>
        <ul class="mb-0">
            <li><strong>Safe database migrations:</strong> Update schema without data loss</li>
            <li><strong>Automated installation:</strong> Create tables and seed initial data</li>
            <li><strong>Fast deployment:</strong> Run commands via SSH for quick updates</li>
            <li><strong>Consistent workflow:</strong> Same commands for all modules</li>
        </ul>
    </div>

    <h2 class="mt-4">Command Syntax</h2>

    <p>All CLI commands follow this pattern:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">php milkadmin/cli.php moduleName:command</code></pre>

    <div class="alert alert-warning">
        <p class="mb-0"><strong>⚠️ Important:</strong> Replace <code>moduleName</code> with your actual module name (case-sensitive). For example, if your module class is <code>PostsModule</code>, use <code>posts</code>.</p>
    </div>

    <h2 class="mt-4">Available Commands</h2>

    <h3>1. Install Module</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">php milkadmin/cli.php moduleName:install</code></pre>

    <p><strong>What it does:</strong></p>
    <ul>
        <li>Creates all database tables defined in the module's Model(s)</li>
        <li>Executes <code>afterCreateTable()</code> method to insert initial/demo data</li>
        <li>Sets up indexes and foreign keys</li>
    </ul>

    <p><strong>When to use:</strong></p>
    <ul>
        <li>First installation of a new module</li>
        <li>After downloading a module from the repository</li>
        <li>When you need to reset the module (drop + recreate tables)</li>
    </ul>

    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash"># Install the Posts module
php milkadmin/cli.php posts:install
</code></pre>

    <h3>2. Update Module Schema</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">php milkadmin/cli.php moduleName:update</code></pre>

    <p><strong>What it does:</strong></p>
    <ul>
        <li>Compares the current database schema with the Model definition</li>
        <li>Adds new columns without losing existing data</li>
        <li>Modifies column types if changed in the Model</li>
        <li><strong>Does NOT execute</strong> <code>afterCreateTable()</code></li>
    </ul>

    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash"># Update the Posts module schema
php milkadmin/cli.php posts:update
</code></pre>

    <h3>3. List Available Commands</h3>
    <p>If you don't know what commands are available, you can list them all with:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">php milkadmin/cli.php</code></pre>

    <p>If you lost the password of the administrator, you can create a new one with:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">php milkadmin/cli.php create-administrator</code></pre>
    
</div>