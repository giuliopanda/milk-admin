<?php
namespace Modules\docs;
use MilkCore\Route;
/** 
* @title Make your first API 
* @category Getting started 
* @order 30
* @tags API, Get::make, Hooks::set, Get make, Hooks set, jobs-init, HttpClient, REST
*/

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
<h1>Make your first API</h1>

<p>To register an API, open customizations/functions.php and add the following code:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use MilkCore\API;

!defined('MILK_DIR') && die(); // Avoid direct access
API::set('test/hello', function($request) { 
return 'Hello World';
});
</code></pre>

<p>To call the API open your browser and go to http://localhost/api/?page=test/hello</p>

<p>To call the api from Milk Admin you can write:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use MilkCore\API;

!defined('MILK_DIR') && die(); // Avoid direct access
$response = HttpClient::get('<?php echo Route::url(); ?>/api.php?page=test/hello'); 
if ($response['status_code'] == 200) {
$article = $response['body'];
}
</code></pre>

<p>If you want to handle a call with a user's authorization, you can write:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use MilkCore\API;

!defined('MILK_DIR') && die(); // Avoid direct access
API::set('test/hello', function($request) {
return 'Hello World';
}, '_user.is_authenticated');
</code></pre>

<p>This is a minimal example with authentication:</p>
<p>Create a new file in the root directory called test-api.php. (In production do not create new files in the root directory, this is just for testing).</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace MilkCore;

define('MILK_DIR', __DIR__);
require __DIR__ . '/milk-core/autoload.php';

!defined('MILK_DIR') && die(); // Avoid direct access
// user and password
$response = HttpClient::post(Route::url() . '/api.php?page=auth/login', 
['headers' => ['Authorization' => 'Basic' . base64_encode( 'admin:admin' )]]);
if (@$response['status_code'] == 200 && ($response['body']['success'] ?? false)) { 
    $token = $response['body']['data']['token'];
    print "TOKEN: ".$token."\n";

    $response = HttpClient::post(Route::url() . '/api.php?page=test/hello',
    ['headers' => ['Authorization' => 'Bearer ' . $token]]);
    var_dump ($response['body']);

} else {
    print "<pre>";
    var_dump($response);
    print "</pre>";
    die();
}

</code></pre>

<p>In the api documentation page you can download a class to handle authentication from any external php app.</p>