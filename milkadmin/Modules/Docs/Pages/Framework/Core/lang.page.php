<?php
namespace Modules\Docs\Pages;
/**
 * @title Multi Language support 
 * @guide framework
 * @order 
 * @tags language, translation, localization, multi-language, _pt, _rt, Lang 
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
        <h1>Lang Class Documentation</h1>
        <p>This documentation provides a detailed overview of the Lang class and its functions.</p>

        <h2 class="mt-4">Introduction</h2>
        <p>The Lang class manages string translations within the system. It allows setting and retrieving translations for different areas and loading translation files.</p>
        <p>Language files are loaded from the lang folder. The name of the file to load is saved in the configuration</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$conf['lang'] = 'en';</code></pre>
        <p>The lang class can also be used to override language settings within a module. If a module's translation also modifies translations of other modules, you can limit the scope of intervention. The area is defined by the page query string ($_REQUEST['page']) in the page call</p>

        <h2 class="mt-4">Usage</h2>
        <p>The lang class, like the Sanitize class, is not used directly but generally through the following functions</p>
        <h5>Print a translated string</h5>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"> _pt('Hello World');</code></pre>
        <h5>Print a translated string with variables</h5>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">printf(_rt('Hello %s'), 'World');</code></pre>
        <p>If you need to return the string in a variable, you can use sprintf</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$var = sprintf(_rt('Hello %s'), 'World');</code></pre>

        <div class="alert alert-info">
            For a complete list of functions that handle sanitization and translation, see the documentation for <a href="<?php echo \App\Route::url('?page=docs&action=Framework/Core/sanitize'); ?>">Sanitize</a>
        </div>

<h2 class="mt-4">Functions</h2> 

<h4 class="mt-2">set(string $string, string $translation, $area = 'all')</h4> 
<p>Set a string and its translation.</p> 
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Lang::set('hello', 'hello', 'greetings');</code></pre> 

<h4 class="mt-2">get(string $string, $area = 'all')</h4> 
<p>Gets the translation of a string.</p> 
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$translation = Lang::get('hello', 'greetings');
echo $translation; // Output: hello</code></pre> 

<h4 class="mt-2">loadPhpFile(string $file, $area = 'all')</h4>
<p>Load a PHP translation file.</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$success = Lang::loadPhpFile('path/to/translations.php', 'greetings');</code></pre>
<p>Translation files are PHP files that return an array where keys are English strings and values are translations:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
// translations.php
return [
    "Hello World" => "Ciao Mondo",
    "Save" => "Salva",
    "Cancel" => "Annulla",
];</code></pre>

<h2 class="mt-4">Javascript translation</h2>
<p>Translation can also be done in javascript with <code>__(key, params = {})</code></p>
<p>The translation uses the same PHP files, without the areas. You must wait until the translation file has loaded, otherwise the original text will be returned.</p><pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">waitForTranslations().then(() => {
    alert(__('Hello %s', ['World']));
});</code></pre> 
<p>waitForTranslations() waits for translations to load, but if the translations aren't supposed to appear immediately, but rather later, it can be assumed that the loading has already been successful. If the translations haven't been loaded yet, the original text is still returned.</p>