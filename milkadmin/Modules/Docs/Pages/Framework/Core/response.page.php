<?php
namespace Modules\Docs\Pages;
/**
 * @title Response
 * @guide framework
 * @order 
 * @tags Response, theme_page, json, csv, output, theme, response
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>Response Class</h1>
    
    <p>The Response Class handles application output and termination. It provides methods for rendering theme pages, sending JSON responses, CSV exports, and properly closing database connections and saving settings before terminating execution.</p>


    <h2 class="mt-4">render($view, $response = [], $theme = 'default')</h2>
    <p><strong>The recommended method for modern applications.</strong> Automatically handles both HTML and JSON responses based on the request type. This method detects whether the client expects JSON (via Accept header or page-output parameter) and responds accordingly.</p>
    <ul>
        <li><code>$view</code> (string): The view or HTML content to render.</li>
        <li><code>$response</code> (array): An associative array containing response data such as 'html', 'success', 'msg', etc. If is JSON request, it will be automatically converted to JSON format.</li>
        <li><code>$theme</code> (string): The theme to use for rendering the page 'default'|'public'|'empty' ... .</li>
    </ul>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Modern pattern with TableBuilder and TitleBuilder
$response = (new TableBuilder($model, 'table_id'))
    ->setDefaultActions()
    ->getResponse();

$html = (new TitleBuilder('Page Title'))
    ->addButton('Add New', '?page=example&action=edit', 'primary')
    ->addSearch('search_id', 'Search...', 'Search')
    ->render() . '<br>' . $response['html'];

// Automatically handles JSON for AJAX requests or HTML for regular requests
Response::render($html, $response);

// Custom response data
Response::render('Custom content', [
    'success' => true,
    'msg' => 'Operation completed successfully',
    'additional_data' => ['key' => 'value']
]);</code></pre>

    <h5 class="mt-3">Required Response Parameters</h5>
    <p>The <code>$response</code> array should contain these key parameters:</p>
    <ul>
        <li><strong>html</strong> (string): The HTML content to display</li>
        <li><strong>success</strong> (boolean): Indicates if the operation was successful</li>
        <li><strong>msg</strong> (string): Message to display to the user</li>
    </ul>
    <p>If these parameters are not provided, the system will auto-generate them from MessagesHandler.</p>

    <h5 class="mt-3">Automatic JSON Detection</h5>
    <p>The render method automatically detects JSON requests through:</p>
    <ul>
        <li><strong>Accept Header:</strong> Checks for 'application/json' in HTTP_ACCEPT</li>
        <li><strong>Request Parameter:</strong> Looks for <code>$_REQUEST['page-output'] = 'json'</code></li>
    </ul>

    <h5 class="mt-3">Response Examples</h5>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// HTML Response (regular page request)
// URL: ?page=posts
$response = ['html' => '<table>...</table>', 'success' => true, 'msg' => 'Data loaded'];
Response::render($response['html'], $response);
// Output: Full HTML page with theme

// JSON Response (AJAX request)
// URL: ?page=posts&page-output=json
// Or with Accept: application/json header
$response = ['html' => '<table>...</table>', 'success' => true, 'msg' => 'Data loaded'];
Response::render($response['html'], $response);
// Output: {"html":"<table>...</table>","success":true,"msg":"Data loaded"}

// Practical AJAX example with TableBuilder
$response = (new TableBuilder($model, 'posts_table'))
    ->setDefaultActions()
    ->getResponse();

$html = (new TitleBuilder('Posts'))
    ->render() . '<br>' . $response['html'];

// For regular requests: shows full page
// For AJAX requests: returns JSON with updated HTML
Response::render($html, $response);</code></pre>


    <h2  class="mt-4">denyAccess()</h2>
    <p>Terminates the application and redirects to the deny page or responds with JSON. This method checks if the request expects a JSON response and handles it accordingly.</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Deny access example
if (!Permissions::check('auth.manage')) {
    Response::denyAccess();
}</code></pre>
  

    <h2 class="mt-4">themePage($page, $content, $variables)</h2>
      <p>Legacy Theme Management</p>
    <p>Loads a theme page with the specified content and variables. This method loads the requested theme page, passes the required variables to it, and terminates the application after rendering. The page name is mandatory and generally prints the content passed in Theme::set('content').</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Three possible ways to call theme_page
// 1. pass only the theme page name (will load page_name.page.php file)
Theme::set('content', '...');
Response::themePage('page_name');

// 2. pass the page name and content
Response::themePage('page_name', '', 'content');

// 3. pass the page name, template to load and variables for the template
Response::themePage('theme_page', __DIR__ . '/assets/modules_page.php', ['my_vars' => '...']);</code></pre>



    <h2 class="mt-4">json(array $data)</h2>
    <p>Responds with JSON data and terminates the application. This method sends a JSON response to the client, saves settings, closes database connections, and ends execution.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Response::json(['status' => 'success', 'data' => $result]);</code></pre>

    <h2 class="mt-4">csv(array $data, string $filename = 'export')</h2>
    <p>Responds with CSV data and terminates the application. This method sends a CSV response to the client for file download, properly handles DateTime objects and nested data structures, saves settings, closes database connections, and ends execution.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Export data as CSV
$data = [
    ['name' => 'John', 'email' => 'john@example.com', 'created_at' => new DateTime()],
    ['name' => 'Jane', 'email' => 'jane@example.com', 'created_at' => new DateTime()]
];
Response::csv($data, 'users_export');</code></pre>

    <h2 class="mt-4">Method Reference</h2>
    
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Method</th>
                <th>Description</th>
                <th>Use Case</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>render($view, $response, $theme)</code></td>
                <td>Modern method for automatic JSON/HTML handling</td>
                <td>Recommended for all new applications</td>
            </tr>
            <tr>
                <td><code>themePage($page, $content, $variables)</code></td>
                <td>Legacy method for theme rendering</td>
                <td>Direct theme control, legacy compatibility</td>
            </tr>
            <tr>
                <td><code>json($data)</code></td>
                <td>Direct JSON response</td>
                <td>API endpoints, AJAX responses</td>
            </tr>
            <tr>
                <td><code>htmlJson($response)</code></td>
                <td>JSON with HTML content and standardized format</td>
                <td>Internal use by render() method</td>
            </tr>
            <tr>
                <td><code>csv($data, $filename)</code></td>
                <td>CSV file download</td>
                <td>Data export functionality</td>
            </tr>
            <tr>
                <td><code>isJson()</code></td>
                <td>Check if request expects JSON</td>
                <td>Internal method for automatic detection</td>
            </tr>
        </tbody>
    </table>

    <h2 class="mt-4">Important Notes</h2>

    <p><strong>Application Termination:</strong> All Response methods terminate the application execution using <code>exit</code> after completing their tasks. This ensures proper cleanup by:</p>
    <ul>
        <li>Saving system settings with <code>Settings::save()</code></li>
        <li>Closing primary database connection with <code>Get::db()->close()</code></li>
        <li>Closing secondary database connection with <code>Get::db2()->close()</code></li>
        <li>Running end-page hooks (for theme_page method)</li>
    </ul>

    <p><strong>Security:</strong> The theme_page method includes path sanitization to prevent directory traversal attacks and supports theme customizations through the customizations directory.</p>

</div>