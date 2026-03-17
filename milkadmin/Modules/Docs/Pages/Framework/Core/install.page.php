<?php
namespace Modules\Docs\Pages;
/**
 * @title Install Module Documentation
 * @guide framework
 * @order 2
 * @tags Install, update, uninstall, version, build-version, update-paths, cli, modules
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Installation / Update</h1>
    <p>This documentation provides a description of the installation and update system.</p>

    <h2>Initial Tutorial</h2>
    <p>If you want to customize the system for your project, you can release your versions and install them on new machines or update already installed versions for your clients</p>
    <p>In this short tutorial we see how to create a new version of the system</p>
    <p>1. First of all we need to make a modification. Go inside /Modules/posts/posts.object.php and add a field</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$this->rule('new_field', [
            'type' => 'text',
            'label' => 'New Field'
        ]);
</code></pre>

    <h4>Now we need to update the module</h4>
    <p>Go to the module and modify or add the version property. If you update the version it must be greater than the previous one. I suggest for this system dates AAMMGG (year, month, day), but you can also put a progressive number and that's it. The system does not manage classic versions like 1.0, 1.0.1, 1.3.23.2 etc...<p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"> protected $version = 251001;</code></pre>
    <p>A this point go to the admin and click the menu on the left <b>installation</b>. The module will update automatically.<br> Alternatively from shell you can run:
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">php milkadmin/cli.php {module_name}:update</code></pre>
   
    <h4>Alternatively you can create a new version of the entire system.</h4>
    <p>1. Now from the shell going to the project directory type:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">php milkadmin/cli.php build-version</code></pre>

    <p class="alert alert-info mt-3">
        <strong>Quick ZIP Package:</strong> You can use the <code>zip</code> parameter to automatically create a compressed package ready for deployment:
    </p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">php milkadmin/cli.php build-version zip</code></pre>
    <p>This command will:</p>
    <ul>
        <li>Create a new version folder with all the necessary files</li>
        <li>Generate a ZIP archive containing milkadmin, milkadmin_local, and public_html folders</li>
        <li>Add an <code>install_from_zip.php</code> script for easy server deployment</li>
        <li>Clean up the original folders, leaving only the ZIP and install script</li>
    </ul>
    <p>The resulting package can be uploaded to your server. Simply upload both the ZIP file and <code>install_from_zip.php</code> to your server directory, then access <code>install_from_zip.php</code> via browser. It will automatically extract the ZIP, redirect to the installation page, and self-delete.</p>

    <h3 class="mt-4">Updating Paths and URL After Moving Installation</h3>
    <p>When you move your installation to a new directory or deploy to a different server/domain, you need to update the configuration paths and base URL. Use the <code>update-paths</code> CLI command:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash"># Update only directory paths (automatic detection)
php milkadmin/cli.php update-paths

# Update paths AND change the base URL
php milkadmin/cli.php update-paths "http://localhost/new-path/public_html/"

# Example: deploying to production
php milkadmin/cli.php update-paths "https://www.mysite.com/admin/"</code></pre>

    <p>This command updates:</p>
    <ul>
        <li><code>public_html/milkadmin.php</code> - MILK_DIR and LOCAL_DIR paths</li>
        <li><code>milkadmin_local/config.php</code> - base_url configuration</li>
    </ul>

    <div class="alert alert-info">
        <strong><i class="bi bi-info-circle"></i> When to use update-paths</strong>
        <ul class="mb-0">
            <li>After moving the installation directory on the same server</li>
            <li>After deploying to a new server or domain</li>
            <li>After changing the public URL structure</li>
            <li>After copying the installation to a different location</li>
        </ul>
    </div>

    <p>2. Alternatively, create a zip manually:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">zip -r new_version.zip new_version_xxxxx</code></pre>

    <p>3. Open the page ?page=install and upload the zip file. The installation procedure will be executed. Verify that the new column has been created in the database and that the installation procedure now shows the new version.</p>

    <h2>Introduction</h2>
    <p>Installation, like updating, is designed for the entire system, not for individual modules. 
        The idea is that, once a package is created with custom modules and configurations, it follows its own update path.
    </p>
    <p class="alert alert-info">
        If individual modules have been written respecting the structure of abstract classes, it is possible 
        to install, update and remove individual modules via shell. 
    </p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">php milkadmin/cli.php {module_name}:install 
php milkadmin/cli.php {module_name}:update
php milkadmin/cli.php {module_name}:uninstall
</code></pre>

    <p class="mt-3">During development after creating a new version, you can execute the command:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">php milkadmin/cli.php build-version</code></pre>
<p>This command creates a folder inside the root directory with a copy of the system ready for installation. You can pass a parameter to set the version manually. Just check the permissions and owner of the files. The following instructions should be approximate</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">sudo chown -R www-data:www-data {new_version_xxxxx}
    mv {new_version_xxxxx}/ ../  
    cd .. 
    </code></pre>

    <hr>

    <h2 class="mt-3">Versions </h2>
    <p>Versions are indicated in the configuration file and are composed of 6 characters: AAMMGG where AA is the year e.g. 24, MM the month e.g. 01, GG the day e.g. 09.</p>
    <p>Inside <b>ito_class/setup.php</b> the version number of the new installation is set.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">define ('NEW_VERSION', '240901');</code></pre>
    <p>This is important because inside the various modules you can check the current version and the new version to understand whether you need to load the file with the hooks for the install or not</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('init', function($page) {
// If there is no version it means that the system has yet to be installed
if (Config::get('version') == null || NEW_VERSION > Config::get('version')) {
   require_once (__DIR__.'/auth.install.php');
}
});</code></pre>

    <h2 class="mt-3">The Module</h2>
    <p>The installation module manages the various processes, but it is the responsibility of individual modules to install and update their tables and configurations.</p>
    <p>For example, the Auth module installs and updates multiple tables. In this case it was necessary to rewrite the installation/update function.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function installExecute() {
    if (!$this->model->buildTable()) {
        // error
    }
    $model2 = new SessionModel();
    if (!$model2->buildTable()) {
        // error
    }
    $model3 = new LoginAttemptsModel();
    if (!$model3->buildTable()) {
       // error
    }
    $user_id = Get::make('Auth')->saveUser(0, 'admin', 'admin@admin.com','admin', 1, 1 );
}</code></pre>

    <p>Inside the abstractModel there is the build_table function that creates the table if it doesn't exist or updates it if it exists. The table structure is set according to the abstractObject.<br>For example a very simple table could be structured like this</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class PageModel extends AbstractModel {
    public function initRules() {
        $this->rule('id', [
            'type' => 'int',
            'primary' => true,
        ]);
        
        $this->rule('title', [
            'type' => 'string',
            'length' => 255,
            'nullable' => false,
            'label' => 'Title'
        ]);
       
    }
}</code></pre>

    <h2 class="mt-3">Config.php</h2>
    <p>the config file is rewritten by the installation procedure, however an initial config file must be created for the installation. This is generated by build-version by copying the example inside Assets/installation_config_example.php</p>

    <h2 class="mt-3">Installation Management</h2>
    Installation is managed through a series of hooks.

    <h4 class="mt-3"> Hooks::run('install.get_html_modules', $html, $this->errors);</h4>
    <p>Here all modules can add their html to print. For example, data is requested to connect to the database. If there are errors they are passed as a parameter.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('install.get_html_modules', function($html, $errors) {
    ob_start();
    Form::input('text', 'connect_ip', 'IP Address', $_REQUEST['connect_ip'] ?? '127.0.0.1', $options);
    return ob_get_clean();
}</code></pre>

    <h4 class="mt-3"> Hooks::run('install.check_data', $errors, $data);</h4>
    <p>After the data is sent from the form, it is verified. If there are errors they are passed as a parameter. otherwise we proceed to execute the installation.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('install.check_data', function($errors, $data) {
    $mysql_errors = [];
    if (empty($data['connect_ip'])) {
        $mysql_errors['connect_ip'] = 'IP Address is required';
    }
    return $mysql_errors;
}</code></pre>

<h4> Hooks::run('install.execute_config', $data);</h4>
<p>Here modules can execute their installation code. For example create database tables or save data in the configuration</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use Modules\Install\Install;
    Hooks::set('install.execute_config', function($data) {
    $default_data = [
        'base_url' =>$data['connect_ip']
    ];
    Install::setConfigFile('', $default_data);
    return $data;
}</code></pre>

<h4> Hooks::run('install.done', $html);</h4>
<p>Here modules can add code to print at the end of the installation. For example a welcome message.</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('install.done', function($html) {
    $html .= "Add new text here<br>';
    return $html;
}, 30);</code></pre>

<h4> Hooks::run('install.update', $data);</h4>
<p>For the update there is a specific hook. Here modules can update database tables or do other necessary operations.</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('install.update', function() {
    // modify the config file
    $config = file_get_contents(MILK_DIR."/config.php");
    $version = Config::get('version'); 
    $config = str_replace($version, NEW_VERSION, $config);
    File::putContents(MILK_DIR."/config.php", $config);
}, 100);</code></pre>

    <br>
    <h2>Install.class.php </h2>
    <p>To help the installation phases there is an install.class.php class that contains a series of utility functions.</p>

    <h4>setConfigFile($title, $configs)</h4>
    <p>Sets the parameters of the config file. The title is saved as a comment in the config file.</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('install.execute', function($data) {
    $default_data = [
        'base_url' =>$data['connect_ip']
    ];
    Install::setConfigFile('', $default_data);
}</code></pre>
    <p>If you want to save a variable as a comment just name it with three underscores before its name</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('install.execute', function($data) {
    $my_data = [
        '___my_data' => 'This is a comment variable'
    ];
});</code></pre>
    
    <p> This if the variable is a number or boolean or you want to add a comment next to the variable</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('install.execute', function($data) {
    $auth_data = [
    'auth_expires_session' => ['value'=>'60*24*30','type'=>'number','comment' => 'Session duration in minutes'],
];
    Install::setConfigFile('', $auth_data);
}</code></pre>

    <h4>executeSqlFile($file)</h4>
    <p>Executes an sql file. $file is the absolute path of the sql file to execute.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('install.execute', function($data) {
        Install::executeSqlFile(__DIR__.'/assets/mysql-install.sql');
        return $data;
    }, 20);</code></pre>

    <h3 class="mt-4">Adding a config parameter during installation</h3>
    <p>If you want to add a parameter to config.php during installation you can insert it by default inside modules/install/installer/default.install.php inside <b>Hooks::set('install.execute', function($data)</b></p>
    <p>If the parameter is only used in one module you can create a module.install.php file and insert
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('install.execute', function($data) {
        Config::set('myparam', 'myvalue');
    });</code></pre>    

    <p>Then inside the module insert</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('init', function($page) {
        // If there is no version it means that the system has yet to be installed
        if (Config::get('version') == null || NEW_VERSION > Config::get('version')) {
            require_once (__DIR__.'/module.install.php');
        }
    }</code></pre>
    
    <h2>CLI Commands for Modules</h2>
    <p>Individual modules can register their own CLI commands using the CLI hooks system. This allows modules to provide install, uninstall, and custom commands that can be executed from the command line.</p>
    
    <h4>Setting up CLI hooks</h4>
    <p>To enable CLI commands for your module, register the setup function in your module:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Set up CLI commands
Hooks::set('cli-init', 'my_module_setup_cli_hooks', 90);</code></pre>
    
    <h4>Implementing the CLI setup function</h4>
    <p>Create a function that registers your CLI commands:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">function myModuleSetupCliHooks() {
    // Register CLI commands
    Cli::set("my_module:install", 'my_module_shell_install');
    Cli::set("my_module:uninstall", 'my_module_shell_uninstall');
    Cli::set("my_module:my_command", 'my_module_shell_my_command');
}</code></pre>
    
    <h4>Implementing CLI command functions</h4>
    <p>Create the actual functions that will be executed when the CLI commands are called:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">function myModuleShellInstall() {
    if (Cli::isCli()) {
        Cli::echo("Installing module: My Module");
        Cli::success('Module My Module install command executed');
        return true;
    }
}

function myModuleShellUninstall() {
    if (Cli::isCli()) {
        Cli::echo("Uninstalling module: My Module");
        Cli::success('Module My Module uninstall command executed');
        return true;
    }
}

function myModuleShellMyCommand() {
    if (Cli::isCli()) {
        Cli::echo("My custom command executed successfully!");
        Cli::success("Command completed");
    }
}</code></pre>
    
    <h4>Using CLI commands</h4>
    <p>Once registered, you can execute the commands from the shell:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">php milkadmin/cli.php my_module:install
php milkadmin/cli.php my_module:uninstall
php milkadmin/cli.php my_module:my_command</code></pre>
    
    <p class="alert alert-info">
        Always check if the script is running in CLI mode using <code>Cli::isCli()</code> before executing CLI-specific code.
        Use <code>Cli::echo()</code> and <code>Cli::success()</code> for proper CLI output formatting.
    </p>

    <h2>Updating Modules or the System</h2>
    <p>Updating the system calls the update hook for all modules. If you update just one module, only that module's update hook is called.</p>
    <p>If you're using the AbstractModule, simply change the $version variable to notify the system that the module needs to be updated. If you're not using the AbstractModule, you need to add the module version to the configuration. You can do this dynamically by adding the following code:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Config::append('module_version', [$this->page => ['version'=>$this->version, 'folder'=>$folder]]);</code></pre>
    <p>folder is the folder or the filename if the module consists of a single file. You must enter the relative path to the modules folder.</p>

    <h2>Update</h2>
    <p>To update the system it is possible to do it using various ways.</p>
    <p>Go to the installation page ?page=install. The system will check for a new update and install it if necessary.</p>
    <p>inside ?page=install you find the page to upload the zip with the new updated version</p>
    <p>You can however also use git and update the files directly. In this case when you reload the page ?page=install it will notice that a new update has been loaded and will execute the various update procedures of the individual modules so as to update any tables or other.</p>
    <p>Be careful because if during the update the structure of a table is changed, it will be modified without asking for confirmation. In this case it is therefore the duty of individual modules to verify that any variations or removals of columns do not generate data loss</p>
    
</div>
