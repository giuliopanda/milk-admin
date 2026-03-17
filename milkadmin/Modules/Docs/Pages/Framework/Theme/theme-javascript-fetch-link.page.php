<?php
namespace Modules\Docs\Pages;
/**
 * @title  Javascript Data-Fetch Links
 * @guide framework
 * @order 60
 * @tags fetch, ajax-link, data-fetch, get, post, async, link-transform, disabled, json-response, ui-interaction
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
<h2 class="mt-4">data-fetch System</h2>
<p>
This system allows transforming links and containers with the attribute
<code>data-fetch="get"</code> or <code>data-fetch="post"</code>
into asynchronous <code>fetch()</code> calls.
The request is sent automatically (on click for links, on page load for divs),
and the JSON response is handled in a unified way (modals, offcanvas, toasts, HTML replacement...).
</p>

<div class="alert alert-info">
<strong>Two modes available:</strong>
<ul class="mb-0">
<li><strong>Links (<code>&lt;a&gt;</code>):</strong> Fetch triggered on click</li>
<li><strong>Containers (<code>&lt;div&gt;</code>):</strong> Fetch triggered automatically on page load</li>
</ul>
</div>

<hr>

<h2 class="mt-4">1. data-fetch Links</h2>
<p>
Transform any link into an asynchronous fetch call that triggers on click.
</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;a href="/example/url?param=1" data-fetch="get" class="btn btn-primary"&gt;
    Fetch GET example
&lt;/a&gt;

&lt;a href="/example/url?user=42&amp;active=1" data-fetch="post" class="btn btn-secondary"&gt;
    Fetch POST example
&lt;/a&gt;</code></pre>

<div class="card my-4">
    <div class="card-body">
        <h5 class="card-title">Demo Fetch Links</h5>
        <p class="mb-3">Clicking these buttons will trigger a fetch call instead of navigation:</p>
        <a href="/demo/json/success" data-fetch="get" class="btn btn-success me-2">
            Fetch GET
        </a>
        <a href="/demo/json/error" data-fetch="post" class="btn btn-danger">
            Fetch POST
        </a>
    </div>
</div>

<p>
When a link is clicked:
<ul>
<li>It is given the <code>disabled</code> class (Bootstrap-compatible) to prevent double clicks.</li>
<li>A <code>fetch()</code> call is executed (<code>GET</code> or <code>POST</code>).</li>
<li>The response must always be in <strong>JSON</strong> format.</li>
<li>Depending on the returned data, it can open <code>modals</code>, <code>offcanvas</code>, or <code>toasts</code>.</li>
<li>Once the request is completed, the link is re-enabled.</li>
</ul>
</p>

<h4>Example JSON Response</h4>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
    "success": true,
    "msg": "Operation completed successfully",
    "modal": {
        "title": "Success",
        "body": "The operation was completed successfully.",
        "footer": "&lt;button class='btn btn-primary' data-bs-dismiss='modal'&gt;Close&lt;/button&gt;"
    }
}</code></pre>

<p>
If the JSON response contains these properties, they are automatically handled:
</p>

<ul>
    <li><code>msg</code> → shows a toast if <code>window.toasts</code> exists</li>
    <li><code>modal</code> → opens a modal via <code>window.modal.show()</code></li>
    <li><code>offcanvas_end</code> → opens an offcanvas via <code>window.offcanvasEnd.show()</code></li>
</ul>

<br>
<p>This is used in the builder title as an option in addButton</p>
<p class="mt-3">
You can call <code>initFetchLinks()</code> again after a dynamic DOM update 
to reinitialize new links added via AJAX.
</p>

<p>Redirects are also supported:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
    "success": true,
    "redirect": "/example/url"
}</code></pre>

<hr>

<h2 class="mt-5">2. data-fetch Containers (Divs)</h2>

<p>
Containers with <code>data-fetch</code> and <code>data-url</code> attributes automatically load content via fetch when the page loads.
This is perfect for loading tables, dashboards, or any dynamic content without blocking the initial page render.
</p>

<h3>Basic Usage</h3>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;div id="tableContainer"
     data-fetch="post"
     data-url="?page=posts&action=get-table"&gt;
    &lt;!-- Content will be loaded here automatically --&gt;
&lt;/div&gt;</code></pre>

<div class="alert alert-success">
<strong>✅ Advantages:</strong>
<ul class="mb-0">
<li><strong>Faster page load:</strong> The page renders immediately, content loads progressively</li>
<li><strong>Non-blocking:</strong> Multiple containers load sequentially without blocking each other</li>
<li><strong>Automatic:</strong> No JavaScript code needed, just add the attributes</li>
<li><strong>Clean HTML:</strong> Attributes are removed after loading to keep DOM clean</li>
</ul>
</div>

<h3>How it Works</h3>

<ol>
<li>Page finishes loading (DOMContentLoaded)</li>
<li>After 500ms, the first div with <code>data-fetch</code> starts loading</li>
<li>Each subsequent div loads 500ms after the previous one</li>
<li>The response (JSON) is handled by <code>jsonAction()</code> with the div as container</li>
<li>The <code>data-fetch</code> and <code>data-url</code> attributes are removed to prevent re-loading</li>
</ol>

<h3>Sequential Loading</h3>

<p>If you have multiple containers, they load in sequence with 500ms delay between each:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;!-- Loads at 500ms --&gt;
&lt;div data-fetch="post" data-url="?page=stats&action=get-summary"&gt;&lt;/div&gt;

&lt;!-- Loads at 1000ms (1s) --&gt;
&lt;div data-fetch="post" data-url="?page=posts&action=get-table"&gt;&lt;/div&gt;

&lt;!-- Loads at 1500ms (1.5s) --&gt;
&lt;div data-fetch="post" data-url="?page=comments&action=get-recent"&gt;&lt;/div&gt;</code></pre>

<div class="alert alert-warning">
<strong>⏱️ Loading Timeline:</strong>
<ul class="mb-0">
<li>T+0ms: Page loads</li>
<li>T+500ms: First container starts loading</li>
<li>T+1000ms: Second container starts loading</li>
<li>T+1500ms: Third container starts loading</li>
<li>And so on...</li>
</ul>
</div>

<h3>Controller Response</h3>

<p>The controller should return JSON with <code>html</code> to replace the container content:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
#[RequestAction('get-table')]
public function getTable() {
    $table = TableBuilder::create($this->model, 'idTable')
        ->activeFetch()
        ->asLink('title', '?page='.$this->page.'&action=edit&id=%id%')
        ->setDefaultActions();

    if ($table->isInsideRequest()) {
        // When table updates itself (pagination, sorting)
        Response::Json(['html' => $table->render()]);
    } else {
        // When loaded initially or from external fetch
        Response::Json([
            'modal' => [
                'title' => $this->title,
                'body' => $table->render(),
                'size' => 'xl'
            ]
        ]);
    }
}</code></pre>

<h3>Alternative: HTML Replacement</h3>

<p>You can also return HTML that replaces the container directly:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php
Response::Json([
    'html' => '&lt;div class="table-responsive"&gt;...&lt;/div&gt;'
]);</code></pre>

<p>The <code>html</code> property in the response will replace the container's <code>outerHTML</code>.</p>

<h3>Error Handling</h3>

<p>If the fetch fails, the container will show an error message:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;div class="alert alert-danger"&gt;Failed to load content&lt;/div&gt;</code></pre>

<p>The <code>data-fetch</code> and <code>data-url</code> attributes are removed even on error to prevent infinite retry loops.</p>

<h3>Loading Indicator</h3>

<p>If <code>window.plugin_loading</code> is available, it will be shown during the fetch operation and hidden when complete.</p>

<h3>Manual Re-initialization</h3>

<p>If you dynamically add containers after page load, you can manually trigger the initialization:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">// After adding new containers to DOM
window.initFetchDiv();</code></pre>

<h3>Complete Example: Dashboard with Multiple Sections</h3>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;div class="container-fluid"&gt;
    &lt;div class="row"&gt;
        &lt;!-- Stats cards - loads first at 500ms --&gt;
        &lt;div class="col-12 mb-4"
             data-fetch="post"
             data-url="?page=dashboard&action=get-stats"&gt;
            &lt;div class="text-center py-5"&gt;
                &lt;div class="spinner-border" role="status"&gt;&lt;/div&gt;
                &lt;p&gt;Loading statistics...&lt;/p&gt;
            &lt;/div&gt;
        &lt;/div&gt;

        &lt;!-- Recent posts table - loads second at 1000ms --&gt;
        &lt;div class="col-md-6 mb-4"
             data-fetch="post"
             data-url="?page=posts&action=get-recent"&gt;
            &lt;div class="text-center py-5"&gt;
                &lt;div class="spinner-border" role="status"&gt;&lt;/div&gt;
                &lt;p&gt;Loading recent posts...&lt;/p&gt;
            &lt;/div&gt;
        &lt;/div&gt;

        &lt;!-- Activity log - loads third at 1500ms --&gt;
        &lt;div class="col-md-6 mb-4"
             data-fetch="post"
             data-url="?page=activity&action=get-log"&gt;
            &lt;div class="text-center py-5"&gt;
                &lt;div class="spinner-border" role="status"&gt;&lt;/div&gt;
                &lt;p&gt;Loading activity...&lt;/p&gt;
            &lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>

<div class="alert alert-info">
<strong>💡 Pro Tip:</strong> Include loading indicators (spinners) in the initial HTML. They'll be replaced when the content loads, providing better UX.
</div>

<hr>

<h2 class="mt-5">Comparison: Links vs Containers</h2>

<table class="table table-bordered">
<thead class="table-dark">
<tr>
    <th>Feature</th>
    <th>Links (<code>&lt;a data-fetch&gt;</code>)</th>
    <th>Containers (<code>&lt;div data-fetch&gt;</code>)</th>
</tr>
</thead>
<tbody>
<tr>
    <td><strong>Trigger</strong></td>
    <td>Click event</td>
    <td>Automatic on page load</td>
</tr>
<tr>
    <td><strong>URL Source</strong></td>
    <td><code>href</code> attribute</td>
    <td><code>data-url</code> attribute</td>
</tr>
<tr>
    <td><strong>Loading Timing</strong></td>
    <td>Immediate on click</td>
    <td>Sequential (500ms delay between each)</td>
</tr>
<tr>
    <td><strong>Disabled State</strong></td>
    <td>Yes (adds <code>disabled</code> class)</td>
    <td>No</td>
</tr>
<tr>
    <td><strong>Common Use Cases</strong></td>
    <td>Edit buttons, Delete actions, Form submissions</td>
    <td>Tables, Dashboards, Stats, Dynamic content</td>
</tr>
<tr>
    <td><strong>Response Container</strong></td>
    <td><code>link.parentNode</code></td>
    <td>The div itself</td>
</tr>
<tr>
    <td><strong>Attributes Removed</strong></td>
    <td>No</td>
    <td>Yes (after loading)</td>
</tr>
</tbody>
</table>

<hr>

<h2 class="mt-5">Summary</h2>

<div class="alert alert-success">
<strong>✅ Quick Reference:</strong>
<ul class="mb-0">
<li><strong>Links:</strong> <code>&lt;a href="..." data-fetch="post"&gt;</code> - Triggers on click</li>
<li><strong>Containers:</strong> <code>&lt;div data-fetch="post" data-url="..."&gt;</code> - Auto-loads on page load</li>
<li><strong>Methods:</strong> <code>get</code> or <code>post</code></li>
<li><strong>Response:</strong> Always JSON format</li>
<li><strong>Handler:</strong> <code>jsonAction(data, container)</code></li>
<li><strong>Re-init:</strong> <code>window.initFetchLinks()</code> and <code>window.initFetchDiv()</code></li>
</ul>
</div>

</div>

<style>
.alert {
    padding: 1rem;
    margin: 1rem 0;
    border-radius: 0.5rem;
}
.alert-info {
    background: #cfe2ff;
    border-left: 4px solid #0d6efd;
}
.alert-success {
    background: #d1e7dd;
    border-left: 4px solid #198754;
}
.alert-warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}
.table-dark {
    background: #212529;
    color: white;
}
</style>
