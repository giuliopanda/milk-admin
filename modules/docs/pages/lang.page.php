<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title Multi Language support 
 * @category Framework
 * @order 
 * @tags language, translation, localization, multi-language, _pt, _rt, Lang 
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
        <h1>Lang Class Documentation</h1>
        <p>This documentation provides a detailed overview of the Lang class and its functions.</p>

        <h2>Introduction</h2>
        <p>The Lang class manages string translations within the system. It allows setting and retrieving translations for different areas and loading translation files.</p>
        <p>Language files are loaded from the lang folder. The name of the file to load is saved in the configuration</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$conf['lang'] = 'en';</code></pre>
        <p>The lang class can also be used to override language settings within a module. If a module's translation also modifies translations of other modules, you can limit the scope of intervention. The area is defined by the page query string ($_REQUEST['page']) in the page call</p>

        <h2>Usage</h2>
        <p>The lang class, like the Sanitize class, is not used directly but generally through the following functions</p>
        <h5>Print a translated string</h5>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"> _pt('Hello World');</code></pre>
        <h5>Print a translated string with variables</h5>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">printf(_rt('Hello %s'), 'World');</code></pre>

        <div class="alert alert-info">
            For a complete list of functions that handle sanitization and translation, see the documentation for <a href="<?php echo Route::url('?page=docs&action=modules/docs/pages/sanitize.page'); ?>">Sanitize</a>
        </div>

<br>
        <h2>Funzioni</h2>

        <h4>set(string $string, string $translation, $area = 'all')</h4>
        <p>Setta una stringa e la sua traduzione.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Lang::set('hello', 'ciao', 'greetings');</code></pre>

        <h4>get(string $string, $area = 'all')</h4>
        <p>Ottiene la traduzione di una stringa.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$translation = Lang::get('hello', 'greetings');
echo $translation; // Output: ciao</code></pre>

        <h4>load_ini_file(string $file, $area = 'all')</h4>
        <p>Carica un file di traduzione.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$success = Lang::load_ini_file('path/to/translations.ini', 'greetings');</code></pre>
    </div>

