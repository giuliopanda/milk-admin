<?php
namespace Modules\docs;
/**
 * @title Abstract Controller   
 * @category Abstracts Class
 * @order 30
 * @tags AbstractController, controller, module, init, bootstrap, install, update, uninstall, access, permissions, menu_links, page, title, router, model, hooks, modules_loaded, install_execute, install_check_data, install_get_html_modules, install_done, install_update, shell, cli, get_module_name, authorized, public, registered, admin, multiple-tables, sidebar-menu, icon, group, order, URL
 */
use MilkCore\Route;

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Abstract class controller</h1>

    <p>When creating a new module, the first step is to create a folder inside /modules/module-name.
        <br>Once the folder is created, you need to create a file {module-name}.controller.php <br /> The controller file is automatically loaded by Get::load_modules();<br />
        Any PHP code written inside is therefore executed on every page. <br />
        If you want to use the recommended structure, you can create a class inside the controller file that extends AbstractController called {ModuleName}Controller.
    </p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Modules\{ModuleName};
use MilkCore\AbstractController;

!defined('MILK_DIR') && die(); // Avoid direct access

class {ModuleName}Controller extends AbstractController {
    protected $access = 'public'; // public, registered, authorized, admin
    protected $page = 'myModule';
    protected $title = 'My Module';
    protected $menu_links = [
        ['url'=> 'action=page1', 'name'=> 'Page 1', 'icon'=> 'bi bi-people-fill', 'order'=> 80]
    ]; 

    public function bootstrap() {
        $this->model = new {ModuleName}Model();
        $this->router = new {ModuleName}Router();
    }   
}

Hooks::set('modules_loaded', function() {
    new {ModuleName}Controller();
});
</code></pre>

    <p>Once the class is created, we find a series of properties that can be set to configure the module</p>

    <h5 class="mt-3">$access</h5>
    <p>The access property is the access level to the module. Possible values are: <b>public, registered, authorized, admin</b></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected $access = 'authorized';</code></pre>
    <p>authorized: the user must be logged in and from the users module you choose for each user whether they can access the module.</p>

    <h5 class="mt-3">$permissions</h5>
    <p>An array with a single key-value pair to define the authorization name to access the module if access equals 'authorized'</p>

    <h5 class="mt-3">$menu_links</h5>
    <p>The menu_links property is an array of links that are displayed in the sidebar menu. Each link is an array with the following properties:</p>
    <ul>
        <li>url: the link URL</li>
        <li>name: the link name</li>
        <li>icon: the link icon</li>
        <li>group: the link group</li>
        <li>order: the link order</li>
    </ul>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected $menu_links = [
    ['url'=> '', 'name'=> 'home', 'icon'=> '', 'group'=> 'base_module'],
    ['url'=> 'action=page2', 'name'=> 'page2', 'icon'=> '', 'group'=> 'base_module']
];</code></pre>

    <h3 class="mt-3">function Init</h3>
    <p>the init() function is called when the module page is called (?page={module-name}). This way the module initialization happens only when calling functions of the module itself</p>

    <h3 class="mt-3">Other init functions</h3>
    <p>In addition to the init function, there are other functions that are executed automatically:</p>
    <ul>
        <li>jobs_init - Executed when cron jobs are run (see <a href="?page=docs&action=/modules/docs/pages/external-modules/cron.page">Cron Jobs</a>)</li>
        <li>cli_init - Executed when CLI commands are run (see <a href="?page=docs&action=/modules/docs/pages/classes/cli.page">CLI</a>)</li>
        <li>hook_init - Executed when hooks are run (see <a href="?page=docs&action=/modules/docs/pages/classes/hooks.page">Hooks</a>)</li>
        <li>api_init - Executed when API requests are run (see <a href="?page=docs&action=/modules/docs/pages/classes/api.page">API</a>)</li>
    </ul>

    <h5 class="mt-3">$page</h5>
        <p>The page property is the module name. If not set, the module name is derived from the class name</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected $page = 'basemodule';</code></pre>

        <h5 class="mt-3">$title</h5>
        <p>The title property is the module title. If not set, the title is derived from the class name</p>

        <h3 class="mt-4">Shell Management</h3>
        <p>It's possible to add commands to execute from shell by creating a method with the shell_ prefix. If you add $args as a method parameter, the shell command will accept arguments.
        </p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function shell_test() {
    MilkCore\Cli::echo('Shell test');
}</code></pre>
        <p>This function can be executed from shell with the command <code>php cli.php {module-name}:test</code></p>

        <h3>Module Installation</h3>
        <p>To install the module, just upload the folder inside <code>modules/</code>. If the module contains tables (therefore a model associated with the controller) there are default shell commands for managing installation, update or removal of the module.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-bash">php cli.php {module-name}:install
php cli.php {module-name}:update
php cli.php {module-name}:uninstall</code></pre>
        <p>If a class with the model has been created and it manages the <code>build_table</code> correctly, then no code needs to be written. Otherwise, the functions that manage installation, update and removal of the module must be written manually and are:<br><code>install_execute</code> <br><code>install_update</code> <br><code>install_uninstall</code></p>
        <p>When a module is uninstalled, the tables created by the module's model are removed but the module folder is NOT deleted. A dot is placed in front of the folder to disable it.</p>
        <p>Before being able to reinstall the module, you need to rename the module itself by removing the dot in front of the folder name.</p>

        <h4 class="mt-3">Disabling CLI Commands</h4>
        <p>If you want to prevent a module from having automatic CLI commands for installation, update, and uninstallation, you can set the <code>$
            cli</code> property to <code>true</code>.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class MyModuleController extends AbstractController {
    protected $disable_cli = true; // Disables CLI commands
    
    // Other properties...
}</code></pre>
        <p>This is useful for modules that don't want to be uninstalled.</p>

       

    <h1 class="mt-4">AbstractController Abstract Class Documentation</h1>
    <p>The <code>AbstractController</code> class is the base class for module management in Ito. It provides a framework for module initialization, menu management, and interaction with the router and model. This document describes the public properties and methods, and specifies their functionality.</p>

    <h2 class="mt-4">Main Properties</h2>
    <ul>
        <li><code>$page</code>: (string, optional) The module name (e.g.: "posts"). If not set, it's derived from the controller class name.</li>
        <li><code>$title</code>: (string, optional) The module title, displayed in the interface. If not set, it's derived from the controller class name.</li>
        <li><code>$menu_links</code>: (array, optional) Array of links to display in the menu, in the format `[['url'=> '', 'name'=> '', 'icon'=> '', 'group'=> '']]`.</li>
        <li><code>$access</code>: (string, optional) The module access level (<code>public</code>, <code>registered</code>, <code>authorized</code>, <code>admin</code>). Default: `registered`.</li>
        <li><code>$router</code>: (string|object, optional) The router class name (e.g.: 'PostsRouter') or a class instance. If not set, it's derived from the controller name (PostsController -> PostsRouter)</li>
        <li><code>$model</code>: (string|object, optional) The model class name (e.g.: 'PostsModel') or a class instance. If not set, it's derived from the controller name (PostsController -> PostsModel)</li>
        <li><code>$version</code>: (number) The module version. It is a number composed of year, month and progressive number es. 250801</li>
        <li><code>$disable_cli</code>: (boolean, optional) When set to true, disables automatic CLI commands for module installation, update, and uninstallation. Default: `false`.</li>
    </ul>

    <h2 class="mt-4">Methods</h2>
    <p class="alert alert-warning">The following methods are used for proper installation management and registration of class functions, so in general they should not be overridden except in special cases</p>

    <h3 class="mt-3"><code>__construct()</code></h3>
    <p>Class constructor. Initializes the module, registers hooks and sets permissions for authorized modules.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Usage example in a child class:
    class PostsController extends \MilkCore\AbstractController {
        protected $page = 'posts';
        protected $title = 'Posts';
        protected $access = 'authorized';
        protected $menu_links = [
            ['url'=> '', 'name'=> 'Posts', 'icon'=> '', 'group'=> 'posts']
        ];
        protected $router = 'PostsRouter';
        protected $model = 'PostsModel';
    }
    new PostsController();
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><em>None</em></li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><em>None</em></li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>init()</code></h3>
    <p>the init() function is called when the module page is called (?page={module-name}). This way the module initialization happens only when calling functions of the module itself</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function init() {
        require_once __DIR__ . '/posts.model.php';
        require_once __DIR__ . '/posts.router.php';
        require_once __DIR__ . '/posts.object.php';
        $this->model = new PostsModel();
        $this->router = new PostsRouter();
        parent::init();
    }</code></pre>

    <h3 class="mt-3"><code>init()</code></h3>
    <p>The init() function is called when the module page is called (?page={module-name}). when the init hook is called</p>

    <h3 class="mt-3"><code>bootstrap()</code></h3>
    <p>The bootstrap function is called when the module is initialized for example in the init function, but also when installing or updating. It is used to initialize the model and router classes. It is called only once for each module</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">public function bootstrap() {
    $this->model = new PostsModel();
    $this->router = new PostsRouter();
    parent::bootstrap();
}</code></pre>

   
    <h3 class="mt-3"><code>install_execute($data = [])</code></h3>
    <p>Executes module installation with the passed data</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
    * @param array $data Installation form data
    * @return void
    */
    public function install_execute($data = []): void;

    // Example
    public function install_execute($data = []) {
        // install table
        $this->model->build_table();
        // save other data passed from the form
        Config::set('my_setting', $data['my_setting']);
    }

    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$data</code>: (array) Array of data from the installation form.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>void</code>: This method does not return any value.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>install_check_data($errors, array $data = [])</code></h3>
    <p>Performs data validation before saving</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
    * @param array $errors
    * @param array $data
    * @return array
    */
    public function install_check_data($errors, array $data = []): array;

    // Example
    public function install_check_data($errors, array $data = []) {
        if ($data['my_setting'] == '') {
            $errors['my_setting'] = 'My setting is required';
        }
        return $errors;
    }
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$errors</code>: (array) Array containing any previous validation errors.</li>
                <li><code>$data</code>: (array) Array of data from the installation form.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>array</code>: The updated array with any validation errors.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>install_get_html_modules($html, $errors)</code></h3>
    <p>Allows modifying the installation page with a custom form. This method is called if the system is not installed or there's a new system version.
        the method returns the generated html, but to insert custom form inputs use the <code>form-custom-input</code> hook
    </p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
    * @param string $html The page html to modify
    * @param array $errors The installation form errors
    * @return string
    */
    public function install_get_html_modules(string $html, $errors);

    // Example
    public function install_get_html_modules(string $html, $errors) {
        $html .= '<div>My custom html</div>';
        return $html;
    }
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$html</code>: (string) The current HTML of the installation page.</li>
                <li><code>$errors</code>: (array) Array of any errors during installation.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>string</code>: The modified HTML of the installation page.</li>
            </ul>
        </li>
    </ul>


    <h3 class="mt-3"><code>install_done($html)</code></h3>
    <p>This method is called after installation and allows modifying the installation completion html.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"> /**
    * @param string $html The html to modify
    * @return string
    */
    public function install_done($html): string;

    // Example
    public function install_done($html) {
        $html .= '<div>Module installed correctly</div>';
        return $html;
    }
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$html</code>: (string) Current HTML of the installation completion page.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>string</code>: Returns the string with modified html.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>install_update($html)</code></h3>
    <p>This method is called after update and allows modifying the update completion html.
        This is called even if the version hasn't changed
    </p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
    * @param string $html The html to modify
    * @return string
    */
    public function install_update($html): string;

    // Example
    public function install_update($html) {
        $html .= '<div>Module updated correctly</div>';
        return $html;
    }
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><code>$html</code>: (string) Current HTML of the update completion page.</li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>string</code>: Returns the string with modified html.</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-3"><code>get_module_name()</code></h3>
    <p>Returns the module name from the class name</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">/**
    * @return string Returns the module name (e.g.: posts)
    */
    protected function get_module_name(): string;

    // Example in child class
    echo $this->get_module_name(); // Output: posts
    </code></pre>
    <ul>
        <li><strong>Input parameters:</strong>
            <ul>
                <li><em>None</em></li>
            </ul>
        </li>
        <li><strong>Return value:</strong>
            <ul>
                <li><code>string</code>: The module name derived from the controller class name.</li>
            </ul>
        </li>
    </ul>


    <h3 class="mt-4">Multiple Tables Management</h3>
    <p>If the module needs multiple tables, it's possible to create multiple models and manage them by overriding the installation, update and uninstallation methods.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">class MyModuleController extends \MilkCore\AbstractController {
        protected $modelReports = null;

        public function bootstrap() {
            $this->model = new MyModuleModel();
            $this->modelReports = new ReportModel();
        }

        public function install_execute($data = []) {
            $this->model->build_table();
            $this->modelReports->build_table();
        }

        public function install_update($html) {
            $this->model->build_table();
            $this->modelReports->build_table();
            return $html;
        }

        public function uninstall_module() {
            if (Cli::is_cli() || Permissions::check('_user.is_admin')) {
                $this->modelReports->drop_table();
            }
            parent::uninstall_module();
        }
    }</code></pre>

    <p>In this example, in addition to the main module model, a second model is created to manage reports. The installation, update and uninstallation functions are overridden to manage both tables.</p>

</div>