<?php
namespace Modules\docs;
use MilkCore\Route;
use MilkCore\Get;
/**
* @title Theme
* @category Framework
* @order
* @tags Theme-class, theme-variables, data-storage, theme-configuration, template-data, content-management
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
<h1>Theme</h1>
<p>The theme class stores and returns data. <br>
This data is used to render the theme. <br>
The data has the characteristic of always being stored in arrays so set('a',1) and set('a',2) will return get('a') = [1,2]. To clear the data just pass NULL as value.</p>

<h2>Functions</h2>
<h4>Theme::set($path, $data)</h4>
<p>Stores a new variable inside an array to use in the theme.</p>

<h4>Theme::get($path, $default = null)</h4>
<p>Extracts the last value of the array</p>
<p>If you want to modify the data you can do it with a 'theme_get_{path}' hook.</p>

<h4>Theme::get_all($path)</h4>
<p>Returns all the values ​​stored in a path.</p>
<p>If you want to modify the data you can do it with a 'theme_get_{path}' hook.</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Hooks::set('theme_get_sidebar.links', function($data) { 
$data[0]['title'] = 'User list'; 
return $data;
});</code></pre> 

<h4>Theme::has($path)</h4> 
<p>Checks whether a value exists in a path.</p> 

<h4>Theme::delete($path)</h4> 
<p>Delete a variable.</p> 

<h4>Theme::for($path)</h4> 
<p>Iterates an array of values. A generator returns.</p> 
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">foreach (Theme::for('myvars') as $var) { 
echo $var; 
}</code></pre>

<h4>Theme::check($path, $type)</h4>
<p>Check the data type</p>

<h2>Variables used in the theme</h2>
<p class="alert alert-info">Here are listed the variables used in the theme and how they are used, to set them always use <b>Theme::set()</b></p>
<div class="ms-4">
<h5>Theme::for('content')</h5>
<p>The content of the page.</p>

<h5>Theme::for('styles')</h5>
<p>Contains the complete url of the css files to load.</p>

<h5>Theme::for('javascript')</h5>
<p>Contains the complete url of the js files to load. The scripts are loaded at the bottom of the page</p> 
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Route::set('mypage', function() { 
Theme::set('javascript', Route::url().'/modules/mypage/assets/mypage.js'); 
// ...
});</code></pre> 

<h5>Theme::get('footer.first'); </h5>
<p>The html code to insert before the footer.</p>

<h5>Theme::get('footer.text', '© '.date('Y').' Milk Admin')</h5>
<p>The copyright.</p>

<h5>Theme::get('header.lang','en')</h5>
<p>The language of the page.</p>

<h5>Theme::get('header.charset','utf-8')</h5>
<p>The charset of the page.</p>

<h5>Theme::get('header.title', 'MILK ADMIN')</h5>
<p>The title of the page.</p>

<h5>Theme::get('header.custom')</h5>
<p>The custom code to insert inside of the head.</p> 

<h5>Theme::get('header.links')</h5> 
<p>The horizontal menu. ['url'=>'', 'title'=>'', 'icon'=>'']</p> 

<h5>Theme::get('site.title')</h5> 
<p>The title of the site.</p>
       

<h5>Theme::get('header.breadcrumbs')</h5> 
<p>The text of the breadcrumbs.</p> 

<h5>Theme::get('sidebar.links')</h5> 
<p>The menu items. ['url'=>'', 'title'=>'', 'icon'=>'', 'order'=>10]</p> 
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"> 
Theme::set('sidebar.links', [ 
'url' => Route::url('?page=docs&action=modules/docs/pages/home.page'), 
'title' => 'Guide', 
'icon' =>'bi bi-journals']); 
</code></pre> 
<p>You can find the icons on <a href="https://icons.getbootstrap.com/" target="_blank">https://icons.getbootstrap.com/</a></p> 

<h5>Theme::get('site.title')</h5> 
<p>The site title returns. Default Config::get('site-title')</p> 

<h5>Theme::get('header.title')</h5> 
<p>The page title returns. Default Config::get('site-title')</p> 
</div>


<h2>How to Add a new menu item</h2>

<pre class="pre-scrollable border p-2 text-bg-gray language-php"><code>Theme::set('sidebar.links', 
['url' => Route::url('?page=auth&action=user-list'), 'title' => 'Users', 'icon' => 'bi bi-people-fill']); 
// horizontal menu 
Theme::set('header.links', ['url' => Route::url('?page=auth&action=logout'), 'title' => 'Logout', 'icon' => 'bi bi-box-arrow-right']
);</code></pre>

<h2>How to add an image</h2>
<pre class="pre-scrollable border p-2 text-bg-gray language-php"><code>&lt;img src="&lt;?php echo Get::uri_path(THEME_URL.'/assets/logo-big.webp') ;?&gt;" alt="test" class="resized-logo"&gt;

&lt;img src="&lt;?php echo Get::uri_path(Route::url().'/modules/my-module/assets/img.jpg') ;?&gt;" alt="test" class="resized-logo"&gt;

</code></pre>
<img src="<?php echo Get::uri_path(THEME_URL.'/assets/logo-big.webp') ;?>" alt="test" class="resized-logo">