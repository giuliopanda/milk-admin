<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
* @title Make your first API
* @order 30
* @tags API, Get::make, Hooks::set, Get make, Hooks set, jobs-init, HttpClient, REST
*/

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
<h1>Make your first API</h1>
<p class="text-muted">Revision: 2025/09/20</p>

<p>Create a new file in Modules/MyTestModule.php</p>
<p>Files ending in Module.php or _module.php are loaded automatically.</p>
<div class="alert alert-info">By convention, if you use Pascal Mode for classes, the file name must be the same.<br>
Use Snake Mode for files that don't contain classes and shouldn't be handled by the autoloader.</div>

<p>To activate a function as an API, you can use attributes. This system makes the code easily readable.</p>

<p>The following example shows three different API types:</p>

<ul>
<li>api-test/hello-world: GET, unauthenticated</li>
<li>api-test/hello-name: POST, authenticated in JWT</li>
<li>api-test/test-token: POST, with a fixed token.</li>
</ul>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Modules\MyTestModule;

use App\{Hooks, Get};
use App\Abstracts\{AbstractModule};
use App\Attributes\{ApiEndpoint};

class MyTestModule extends AbstractModule { 

#[ApiEndpoint('api-test/hello-world')] 
public function helloWorld() { 
return $this->success(['message' => 'Hello World']); 
} 

#[ApiEndpoint('api-test/hello-name', 'POST', ['auth' => true])] 
public function helloName() { 
$user = Get::make('Auth')->getUser(); 
return $this->success(['message' => 'Hello ' . $user->username]); 
} 

#[ApiEndpoint('api-test/test-token', 'POST', ['permissions' => 'token'])] 
public function testToken() { 
return $this->success(['message' => 'Token is valid']); 
}
}
</code></pre>

<p>API calls return an array with three elements:</p>
<ul>
<li>success: boolean</li>
<li>message: string</li>
<li>data: array</li>
</ul>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">{
"success": true,
"message": "Success",
"data": {
"message": "Hello World"
}
}</code></pre>

<p>You can call the first endpoint simply by accessing the link <?php echo Route::url(); ?>/api.php?page=api-test/hello-world</p>

<p>To call more complex endpoints, you can use the HttpClient class in the framework.
You can also download and use this class externally because it has no dependencies.

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">require __DIR__ . '/App/HttpClient.php';
use App\API;
$api_token = "your token here"; // find token in config.php
$uri = explode('?', $_SERVER['REQUEST_URI']);
$link_complete = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($uri[0])."/";
$response = HttpClient::post($link_complete . 'api.php?page=api-test/hello-world',
['body' => ['token' => $api_token ]]);
</code></pre>

<p>To handle authentication, the system uses the JWT system.
For the first call, a username and password must be passed. This first call returns a token.
The token will then be passed in the header to call the authenticated APIs.</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">require __DIR__ . '/App/HttpClient.php';
use App\API;
$api_token = "your token here"; // find token in config.php
$uri = explode('?', $_SERVER['REQUEST_URI']);
$link_complete = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($uri[0])."/";
// Example of access with JWT
$link = $link_complete. 'api.php?page=auth/login';
//print "<p>LINK: ".$link."</p>";
$response = HttpClient::post($link,
['headers' => ['Authorization' => 'Basic' . base64_encode('admin:admin')]]);
print "<h2>Test Bearer page=api-test/hello-name: </h2>\n";
if (@$response['status_code'] == 200 && ($response['body']['success'] ?? false)) { 
$token = $response['body']['data']['token']; 
print "<p>TOKEN: ".$token."</p>\n"; 

$response = HttpClient::post($link_complete . 'api.php?page=api-test/hello-name', 
['headers' => ['Authorization' => 'Bearer ' . $token]]); 

print "<pre>"; 
var_dump ($response['body']); 
print "</pre>";

} else { 
if ($response['status_code'] != 200) { 
print "<h2>ERROR : Status code: " . $response['status_code'] . "</h2>\n"; 
} 
if (($response['body']['success'] ?? false) == false) { 
print "<h2>ERROR : " . $response['body']['message'] . "</h2>\n"; 
}
}
</code></pre>

<p>The Auth module manages 3 endpoints:</p>
<ul>
<li>auth/login: to obtain a token</li>
<li>auth/verify: to verify a token</li>
<li>auth/refresh: to renew a token</li>
</ul>

<div class="alert alert-info">In this first example, we used the module to manage the endpoints.
However, it is possible create a new file in the module folder (you must create a folder to hold the module files) that has the same name as the MyTestApi.php module.</div>

<h2 class="mt-4">API Documentation with #[ApiDoc]</h2>

<p><strong>It is highly recommended to document your APIs</strong> using the <code>#[ApiDoc]</code> attribute. This allows automatic documentation generation and better code maintainability.</p>

<p>Add the <code>#[ApiDoc]</code> attribute right after <code>#[ApiEndpoint]</code>:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Attributes\{ApiEndpoint, ApiDoc};

class MyTestModule extends AbstractModule {

#[ApiEndpoint('api-test/hello-world')]
#[ApiDoc(
    'Returns a simple hello world message',
    [],
    ['message' => 'string']
)]
public function helloWorld() {
    return $this->success(['message' => 'Hello World']);
}

#[ApiEndpoint('api-test/hello-name', 'POST', ['auth' => true])]
#[ApiDoc(
    'Returns a personalized greeting for the authenticated user',
    ['body' => ['name' => 'string']],
    ['message' => 'string', 'user' => 'string']
)]
public function helloName() {
    $user = Get::make('Auth')->getUser();
    return $this->success(['message' => 'Hello ' . $user->username]);
}
}
</code></pre>

<p><strong>ApiDoc parameters:</strong></p>
<ul>
<li><strong>$description</strong>: Brief description of what the API does</li>
<li><strong>$parameters</strong>: Array describing input parameters (can be nested)</li>
<li><strong>$response</strong>: Array describing the response structure (can be nested)</li>
</ul>

<p>Documentation can be accessed programmatically:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\API;

// Get documentation for a specific endpoint
$doc = API::getDocumentation('api-test/hello-world');

// List all endpoints with their documentation
$endpoints = API::listEndpoints();
</code></pre>