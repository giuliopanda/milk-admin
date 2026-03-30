<?php
namespace Modules\Docs\Pages;
/**
 * @title Request
 * @guide framework
  * @order 40
 * @tags request, http-request, input, query-params, get-params, post-data, body, headers, cookies, json-body, files-upload, sanitization, validation, client-ip, request-method
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Request Class</h1>

    <p>The <code>App\Request</code> class is a lightweight HTTP request helper for reading input data, headers, files, cookies, and server metadata. It also provides typed accessors and sanitization utilities.</p>

    <h2 class="mt-4">Method Schema</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm">
            <thead>
                <tr>
                    <th>Visibility</th>
                    <th>Method</th>
                    <th>Return</th>
                    <th>Purpose</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>public</td><td><code>__construct(?array $query = null, ?array $request = null, ?array $files = null, ?array $server = null, ?array $cookies = null)</code></td><td>void</td><td>Create a request instance from explicit arrays or PHP superglobals.</td></tr>
                <tr><td>public static</td><td><code>capture()</code></td><td>static</td><td>Create a new instance using current superglobals.</td></tr>
                <tr><td>public</td><td><code>all()</code></td><td>array</td><td>Get merged GET + POST data (POST wins on key collisions).</td></tr>
                <tr><td>public</td><td><code>input(?string $key = null, mixed $default = null)</code></td><td>mixed</td><td>Read merged input (GET + POST + JSON when content type is JSON).</td></tr>
                <tr><td>public</td><td><code>query(?string $key = null, mixed $default = null)</code></td><td>mixed</td><td>Read GET values.</td></tr>
                <tr><td>public</td><td><code>post(?string $key = null, mixed $default = null)</code></td><td>mixed</td><td>Read POST values.</td></tr>
                <tr><td>public</td><td><code>file(?string $key = null, mixed $default = null)</code></td><td>mixed</td><td>Read uploaded file metadata.</td></tr>
                <tr><td>public</td><td><code>cookie(?string $key = null, mixed $default = null)</code></td><td>mixed</td><td>Read cookie values.</td></tr>
                <tr><td>public</td><td><code>has(string|array $keys)</code></td><td>bool</td><td>Check that keys exist and are not <code>null</code>.</td></tr>
                <tr><td>public</td><td><code>filled(string|array $keys)</code></td><td>bool</td><td>Check that keys are present and not empty strings.</td></tr>
                <tr><td>public</td><td><code>missing(string|array $keys)</code></td><td>bool</td><td>Inverse of <code>has()</code>.</td></tr>
                <tr><td>public</td><td><code>only(array $keys)</code></td><td>array</td><td>Return only selected keys (supports dot notation).</td></tr>
                <tr><td>public</td><td><code>except(array $keys)</code></td><td>array</td><td>Return all input except selected keys.</td></tr>
                <tr><td>public</td><td><code>string(string $key, string $default = '')</code></td><td>string</td><td>Read and trim a string value.</td></tr>
                <tr><td>public</td><td><code>integer(string $key, int $default = 0)</code></td><td>int</td><td>Read and validate an integer value.</td></tr>
                <tr><td>public</td><td><code>float(string $key, float $default = 0.0)</code></td><td>float</td><td>Read and validate a float value.</td></tr>
                <tr><td>public</td><td><code>boolean(string $key, bool $default = false)</code></td><td>bool</td><td>Read and normalize boolean-like values.</td></tr>
                <tr><td>public</td><td><code>array(string $key, array $default = [])</code></td><td>array</td><td>Read an array value.</td></tr>
                <tr><td>public</td><td><code>date(string $key, ?string $format = null, ?DateTime $default = null)</code></td><td>?DateTime</td><td>Read and parse date/time input.</td></tr>
                <tr><td>public</td><td><code>method()</code></td><td>string</td><td>Get request method in uppercase.</td></tr>
                <tr><td>public</td><td><code>isMethod(string $method)</code></td><td>bool</td><td>Check request method equality.</td></tr>
                <tr><td>public</td><td><code>isGet()</code></td><td>bool</td><td>Shortcut for <code>isMethod('GET')</code>.</td></tr>
                <tr><td>public</td><td><code>isPost()</code></td><td>bool</td><td>Shortcut for <code>isMethod('POST')</code>.</td></tr>
                <tr><td>public</td><td><code>header(string $key, mixed $default = null)</code></td><td>mixed</td><td>Read HTTP headers from server data.</td></tr>
                <tr><td>public</td><td><code>bearerToken()</code></td><td>?string</td><td>Extract Bearer token from Authorization header.</td></tr>
                <tr><td>public</td><td><code>ip()</code></td><td>?string</td><td>Get client IP from <code>REMOTE_ADDR</code>.</td></tr>
                <tr><td>public</td><td><code>userAgent()</code></td><td>?string</td><td>Get client user agent string.</td></tr>
                <tr><td>public</td><td><code>isJson()</code></td><td>bool</td><td>Check if request content type includes JSON.</td></tr>
                <tr><td>public</td><td><code>json()</code></td><td>array</td><td>Decode and cache JSON body from <code>php://input</code>.</td></tr>
                <tr><td>public</td><td><code>sanitizeString(string $key, string $default = '')</code></td><td>string</td><td>Read and sanitize string content.</td></tr>
                <tr><td>public</td><td><code>sanitizeEmail(string $key, string $default = '')</code></td><td>string</td><td>Read and sanitize email-like input.</td></tr>
                <tr><td>public</td><td><code>sanitizeUrl(string $key, string $default = '')</code></td><td>string</td><td>Read and sanitize URL-like input.</td></tr>
                <tr><td>protected</td><td><code>isEmptyString(mixed $value)</code></td><td>bool</td><td>Internal helper used by <code>filled()</code>.</td></tr>
                <tr><td>protected</td><td><code>dataGet(array $data, string $key, mixed $default = null)</code></td><td>mixed</td><td>Internal dot-notation reader.</td></tr>
                <tr><td>protected</td><td><code>dataSet(array &$data, string $key, mixed $value)</code></td><td>void</td><td>Internal dot-notation writer.</td></tr>
                <tr><td>protected</td><td><code>dataForget(array &$data, string $key)</code></td><td>void</td><td>Internal dot-notation remover.</td></tr>
            </tbody>
        </table>
    </div>

    <h2 class="mt-4">Instantiation</h2>
    <p>Use explicit arrays for deterministic behavior in tests, or use <code>Request::capture()</code> in runtime code.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Request;

// Runtime
$request = Request::capture();

// Testing
$request = new Request(
    ['page' => 1],                         // query
    ['name' => 'Alice'],                   // post
    ['avatar' => ['name' => 'a.png']],     // files
    ['REQUEST_METHOD' => 'POST'],          // server
    ['session' => 'abc']                   // cookies
);</code></pre>

    <h2 class="mt-4">Reading Input Data</h2>
    <p><code>input()</code> merges GET and POST; if the request is JSON, decoded JSON payload is merged too.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$all = $request->input();
$name = $request->input('user.name', 'Guest');

$only = $request->only(['user.name', 'user.email']);
$withoutSecrets = $request->except(['password', 'token']);</code></pre>

    <h2 class="mt-4">Validation Helpers</h2>
    <p>Use <code>has()</code>, <code>filled()</code>, and <code>missing()</code> for quick presence checks on one or many keys.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if ($request->filled(['email', 'password'])) {
    // proceed
}

if ($request->missing('csrf_token')) {
    // reject request
}</code></pre>

    <h2 class="mt-4">Typed Accessors</h2>
    <p>Typed methods provide normalized values and safe defaults.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$name = $request->string('name');
$page = $request->integer('page', 1);
$price = $request->float('price', 0.0);
$enabled = $request->boolean('enabled', false);
$tags = $request->array('tags', []);
$publishedAt = $request->date('published_at');</code></pre>

    <h2 class="mt-4">HTTP Metadata</h2>
    <p>Request metadata methods expose method, headers, bearer token, IP, and user-agent information.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if ($request->isPost()) {
    $token = $request->bearerToken();
    $contentType = $request->header('Content-Type', 'text/plain');
    $ip = $request->ip();
}</code></pre>

    <h2 class="mt-4">JSON Body</h2>
    <p><code>json()</code> reads <code>php://input</code>, decodes to array, and caches the result for repeated calls.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">if ($request->isJson()) {
    $payload = $request->json();
}</code></pre>

    <h2 class="mt-4">Sanitization</h2>
    <p>Use sanitization helpers when you need a quick cleanup step before validation or persistence.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$safeName = $request->sanitizeString('name');
$safeEmail = $request->sanitizeEmail('email');
$safeUrl = $request->sanitizeUrl('website');</code></pre>

    <h2 class="mt-4">Notes</h2>
    <ul>
        <li>Dot notation is supported in <code>input()</code>, <code>query()</code>, <code>post()</code>, <code>file()</code>, <code>cookie()</code>, <code>only()</code>, and <code>except()</code>.</li>
        <li>For overlapping keys, precedence is: <code>query &lt; post &lt; json</code> (when content type is JSON).</li>
        <li>The protected methods are internal utilities and are not intended as the public API.</li>
    </ul>
</div>
