<?php
namespace Modules\docs;
/**
 * @title Hooks Class
 * @category Framework
 * @order 
 * @tags install, theme, cli, jobs, route, render-theme, end-page, cron, modules_loaded, init, cli-init, jobs-init, route_before_run, route_after_run
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Hooks</h1>
    <h2>Functions</h2>
    <h4>Hooks::set($name, $function, $order = int)</h4>
    <p>Registers a function to be called at page startup. The order defaults to 10 and determines the sequence of function calls. If a number is high, the function will be executed later; if it's low, it will be executed earlier.</p>

    <h4>Hooks::run($name, ...args)</h4>
    <p>Executes the function registered for the name</p>
    <p>Returns the value returned by the function if there is a hook, otherwise returns the first argument value</p>

    <h2 class="mt-4">Active Hooks</h2>

    <h3 class="mt-4">At Various Page Execution Points</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::run('modules_loaded'); </code></pre>
    <p>In <code>get.class.php</code> is called after all controllers have been loaded, before loading the template function and before the init hook. It's used to set, for example, whether the user is logged in or not.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::run('init'); </code></pre>
    <p>In <code>index.php</code>: is called after all controllers have been loaded and the modules_loaded hook (which sets the user) has been executed. It runs immediately before loading the theme function</p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::run('cli-init'); </code></pre>
    <p>In <code>cli.php</code>: is called immediately after the init hook when executing code from the command line</p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::run('jobs-init'); </code></pre>
    <p>In <code>jobs.php</code>: is called immediately after the init hook when executing code from the command line</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$name = Hooks::run('route_before_run', $name); </code></pre>
    <p>In <code>route.class.php</code> Is called before executing the route and returns the page to execute</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$name = Hooks::run('route_after_run', $name);</code></pre>
    <p>In <code>route.class.php</code></p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$theme = Hooks::run('render-theme', $theme, $page);</code></pre>
    <p>In <code>get.class.php</code></p>
    <p>Is called after the page has been rendered.
        <br><b>$theme</b> is the rendered page HTML<br>
        <b>$page</b> The theme page that has been rendered<br>
    </p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::run('end-page');</code></pre>
    <p>In <code>index.php</code></p>
    <p>Is called just before closing the database connection, after the page has been rendered and printed</p>

    
    <h3 class="mt-4">CRON</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::run('cron');</code></pre>
    <p>In <code>cron.php</code></p>
    <p>Is called every minute if the code is running from command line.<br>
    For cron, there's nothing else by default, how it's handled is up to the various modules.</p>

    <h3 class="mt-4">THEME HOOKS</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$query_string = Hooks::run('route_url', $query_string);</code></pre>
    <p>In <code>route.class.php</code></p>
    <p>Is called before generating any URL</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$data = Hooks::run('theme_set_'.$path, $data);</code></pre>
    <p>In <code>theme.class.php</code></p>
    <p>Is called every time data is set for the theme</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">return Hooks::run('theme_get_'.$path, $return, 'string' | 'array');</code></pre>
    <p>In <code>theme.class.php</code></p>
    <p>Viene chiamato prima di restituire i dati per il tema</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$file = Hooks::run('load_ini_file', $file);</code></pre>
    <p>In <code>lang.class.php</code></p>
    <p>Viene chiamato prima di caricare un file di lingua</p>
    
    <h3 class="mt-4">Plugin Table</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::run('table_actions_row', $header['options'], $row, $table_id);</code></pre>
    <p>In <code>table.class.php</code></p>
    <p>Viene chiamato prima di renderizzare le azioni di una riga di una tabella</p>

    <h3 class="mt-4">Plugin Title</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$title_txt = Hooks::run('plugin_title.title_txt', $title_txt);</code></pre>
    <p>In <code>title.php</code></p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$btns = Hooks::run('plugin_title.btns', $btns, $title_txt);</code></pre>
    <p>In <code>title.php</code></p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$right = Hooks::run('plugin_title.right', $right, $title_txt);</code></pre>
    <p>In <code>title.php</code></p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$description = Hooks::run('plugin_title.description', $description, $title_txt);</code></pre>
    <p>In <code>title.php</code></p>

    <h3 class="mt-4">Plugin FORM UPLOAD FILE</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$max_size = Hooks::run("upload_maxsize_".$name, 10000000);</code></pre>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$accept = Hooks::run("upload_accept_".$name, '');</code></pre>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$error_msg = Hooks::run("upload_check_".$name, '', $_FILES['file']);</code></pre>
    <p>Send the error message. If an error message is compiled, the upload is interrupted</p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$temp_dir = Hooks::run('upload_save_dir_'.$name,  $temp_dir);</code></pre>
    <p>The directory where the file is saved can be changed with a hook</p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$file_name = Hooks::run('upload_file_name_'.$name,  $file_name, $_FILES['file']);</code></pre>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$permission = Hooks::run('upload_permission_file_'.$name, 0666);</code></pre>

    <h3 class="mt-4">FORM</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$field = Hooks::run('form_input', $field, $type, $name, $label, $value, $options);</code></pre>
    <p>In <code>form.class.php</code></p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$field = Hooks::run('form_textarea', $field, $name, $label, $value, $rows, $options);</code></pre>
    <p>In <code>form.class.php</code></p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$field = Hooks::run('form_checkbox', $field, $name, $label, $value, $options);</code></pre>
    <p>In <code>form.class.php</code></p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$field = Hooks::run('form_checkboxes', $field, $name, $list_of_radio, $selected_value,  $inline,  $options_group, $options_field);</code></pre>
    <p>In <code>form.class.php</code></p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$field = Hooks::run('form_radio', $field, $name, $label, $value, $options);</code></pre>
    <p>In <code>form.class.php</code></p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$field = Hooks::run('form_radios', $field, $name, $list_of_radio, $selected_value,  $inline,  $options_group, $options_field);</code></pre>
    <p>In <code>form.class.php</code></p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$field = Hooks::run('form_select', $field, $name, $label, $options, $selected);</code></pre>
    <p>In <code>form.class.php</code></p>
    
    <h3 class="mt-4">Installation</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">return Hooks::run('install.get_html_modules', $html, $this->errors);</code></pre>
    <p>In <code>install.model.php</code></p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$this->errors = Hooks::run('install.check_data', $errors, $data);</code></pre>
    <p>In <code>install.model.php</code></p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::run('install.execute', $data);</code></pre>
    <p>In <code>install.model.php</code></p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">echo Hooks::run('install.done', $html);</code></pre>
    <p>In <code>install_done.page.php</code></p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">return Hooks::run('install.update', $data);</code></pre>
    <p>In <code>install.model.php</code></p>

</div>