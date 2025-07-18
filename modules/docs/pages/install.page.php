<?php
namespace Modules\docs;
/**
 * @title Install Module Documentation
 * @category Modules
 * @order 2
 * @tags Install, update, uninstall, version, build-version, cli, modules
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Installation / Update</h1>
    <p>This documentation provides a description of the installation and update system.</p>

    <h2>Initial Tutorial</h2>
    <p>If you want to customize the system for your project, you can release your versions and install them on new machines or update already installed versions for your clients</p>
    <p>In this short tutorial we see how to create a new version of the system</p>
    <p>1. First of all we need to make a modification. Go inside /modules/posts/posts.object.php and add a field</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$this->rule('new_field', [
            'type' => 'text',
            'label' => 'New Field'
        ]);
</code></pre>
    <p>2. Now from the shell going to the project directory type:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">php cli.php build-version</code></pre>
    <p>3. Now create a zip</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">zip -r new_version.zip new_version_xxxxx</code></pre>

    <p>4. Open the page ?page=install and upload the zip file. The installation procedure will be executed. Verify that the new column has been created in the database and that the installation procedure now shows the new version.</p>

    <h2>Introduction</h2>
    <p>Installation, like updating, is designed for the entire system, not for individual modules. 
        The idea is that, once a package is created with custom modules and configurations, it follows its own update path.
    </p>
    <p class="alert alert-info">
        If individual modules have been written respecting the structure of abstract classes, it is possible 
        to install, update and remove individual modules via shell. 
    </p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">php cli.php {module_name}:install 
php cli.php {module_name}:update
php cli.php {module_name}:uninstall
</code></pre>

    <p class="mt-3">During development after creating a new version, you can execute the command:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">php cli.php build-version</code></pre>
<p>This command creates a folder inside the root directory with a copy of the system ready for installation. You can pass a parameter to set the version manually. Just check the permissions and owner of the files. The following instructions should be approximate</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">sudo chown -R www-data:www-data {new_version_xxxxx}
    mv {new_version_xxxxx}/ ../  
    cd .. 
    </code></pre>

    <hr>

    <h2 class="mt-3">Versions</h2>
    <p>Versions are indicated in the configuration file and are composed of 6 characters: AAMMXX where AA is the year e.g. 24, MM the month e.g. 01, XX a progressive number that expresses how many versions are made in that year of that month.</p>
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

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function install_execute($data = []) {
    if (!$this->model->build_table()) {
        // error
    }
    $model2 = new SessionModel();
    if (!$model2->build_table()) {
        // error
    }
    $model3 = new LoginAttemptsModel();
    if (!$model3->build_table()) {
       // error
    }
    $user_id = Get::make('auth')->save_user(0, 'admin', 'admin@admin.com','admin', 1, 1 );
   
    return $data;
}</code></pre>

    <p>Inside the abstractModel there is the build_table function that creates the table if it doesn't exist or updates it if it exists. The table structure is set according to the abstractObject.<br>For example a very simple table could be structured like this</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class PageModel extends AbstractModel {
    public function init_rules() {
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
    <p>the config file is rewritten by the installation procedure, however an initial config file must be created for the installation. This is generated by build-version by copying the example inside install/installation-config.example.php</p>

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

<h4> Hooks::run('install.execute', $data);</h4>
<p>Here modules can execute their installation code. For example create database tables or save data in the configuration</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use Modules\Install\Install;
    Hooks::set('install.execute', function($data) {
    $default_data = [
        'base_url' =>$data['connect_ip']
    ];
    Install::set_config_file('', $default_data);
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
    File::put_contents(MILK_DIR."/config.php", $config);
}, 100);</code></pre>

    <br>
    <h2>Install.class.php </h2>
    <p>To help the installation phases there is an install.class.php class that contains a series of utility functions.</p>

    <h4>set_config_file($title, $configs)</h4>
    <p>Sets the parameters of the config file. The title is saved as a comment in the config file.</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('install.execute', function($data) {
    $default_data = [
        'base_url' =>$data['connect_ip']
    ];
    Install::set_config_file('', $default_data);
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
    Install::set_config_file('', $auth_data);
}</code></pre>

    <h4>execute_sql_file($file)</h4>
    <p>Executes an sql file. $file is the absolute path of the sql file to execute.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('install.execute', function($data) {
        Install::execute_sql_file(__DIR__.'/assets/mysql-install.sql');
        return $data;
    }, 20);</code></pre>

    <h3 class="mt-4">Adding a config parameter during installation</h3>
    <p>If you want to add a parameter to config.php during installation you can insert it by default inside modules/install/installer/default.install.php inside <b>Hooks::set('install.execute', function($data)</b></p>
    <p>If the parameter is only used in one module you can create a module.install.php file and insert
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('install.execute', function($data) {
        Config::set('myparam', 'myvalue');
    });</code></pre>    

    <p>Then inside the controller insert</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('init', function($page) {
        // If there is no version it means that the system has yet to be installed
        if (Config::get('version') == null || NEW_VERSION > Config::get('version')) {
            require_once (__DIR__.'/module.install.php');
        }
    }</code></pre>
    
    <h2>Update</h2>
    <p>To update the system it is possible to do it using various ways.</p>
    <p>inside ?page=install you find the page to upload the zip with the new updated version</p>
    <p>You can however also use git and update the files directly. In this case when you reload the page ?page=install it will notice that a new update has been loaded and will execute the various update procedures of the individual modules so as to update any tables or other.</p>
    <p>Be careful because if during the update the structure of a table is changed, it will be modified without asking for confirmation. In this case it is therefore the duty of individual modules to verify that any variations or removals of columns do not generate data loss</p>
    
</div>