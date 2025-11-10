<?php
namespace Modules\Docs\Pages;
use App\Route;
/**
 * @title Fetch-Based Modules
 * @category Advanced
 * @order 1
 * @tags ajax, fetch, spa, modules, dynamic forms
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

<h1>Fetch-Based Modules</h1>

<p class="lead">
    Create modules that use AJAX/Fetch for dynamic content updates without page reloads.
</p>

<hr>

<h2>Overview</h2>

<p>
    MilkAdmin supports modules with forms that submit via fetch instead of traditional page reload.
    This pattern is used for forms displayed in offcanvas, modals, or inline editing.
</p>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <strong>Key Mechanism:</strong> The third parameter of <code>FormBuilder::create()</code> controls the behavior:
    <code>false</code> enables fetch mode (JSON responses), while <code>true</code> or omitting it uses page reload.
</div>

<hr>

<h2>FormBuilder Configuration</h2>

<p>The third parameter of <code>FormBuilder::create()</code> controls the submission behavior:</p>

<pre><code class="language-php">// Fetch mode: returns JSON, no page reload
$form_builder = FormBuilder::create($this->model, 'tasks', false);

// Page reload mode: redirects after save
$form_builder = FormBuilder::create($this->model);</code></pre>

<div class="table-responsive">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Parameter</th>
                <th>Type</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>$model</code></td>
                <td>AbstractModel</td>
                <td>The model instance</td>
            </tr>
            <tr>
                <td><code>$page</code></td>
                <td>string</td>
                <td>Page identifier (optional)</td>
            </tr>
            <tr>
                <td><code>$url_success_or_json</code></td>
                <td>string|bool</td>
                <td><code>false</code> = fetch mode (returns JSON), string = success redirect URL</td>
            </tr>
            <tr>
                <td><code>$url_error</code></td>
                <td>string</td>
                <td>Error redirect URL (optional)</td>
            </tr>
        </tbody>
    </table>
</div>

<hr>

<h2>Controller Implementation</h2>

<p>A fetch-based controller uses <code>Response::htmlJson()</code> to return JSON responses that control the UI:</p>

<pre><code class="language-php">#[RequestAction('edit')]
public function taskEdit() {
    $response = ['page' => $this->page, 'title' => $this->title];

    // Third parameter = false enables fetch mode
    $form_builder = FormBuilder::create($this->model, 'tasks', false)
        ->addStandardActions(true); // Adds save, delete, cancel buttons

    $form_html = $form_builder->getForm();
    $action = $form_builder->getPressedAction();

    if ($action == 'save' || $action == 'delete') {
        // Form submitted: check for errors
        if (!MessagesHandler::hasErrors()) {
            // Success: hide offcanvas and reload table
            $response['offcanvas_end'] = ["action" => "hide"];
            $response['table'] = ["id" => "idTableTasks", "action" => "reload"];
        } else {
            // Errors: re-display form with error messages
            $response['offcanvas_end'] = [
                "title" => "Edit Task",
                "body" => $form_html,
                "action" => "show"
            ];
        }
    } elseif ($action == 'cancel') {
        // Cancel: just close offcanvas
        $response['offcanvas_end'] = ["action" => "hide"];
    } else {
        // Initial load: show form
        $response['offcanvas_end'] = [
            "title" => "Edit Task",
            "body" => $form_html,
            "action" => "show"
        ];
    }

    // Trigger JavaScript hook for form initialization
    $response['hook'] = ["name" => "update-form"];

    Response::htmlJson($response);
}</code></pre>

<hr>

<h2>TableBuilder Integration</h2>

<p>Configure table actions to trigger fetch requests:</p>

<pre><code class="language-php">TableBuilder::create($this->model, 'idTableTasks')
    // fetchLink: makes column clickable with fetch request
    ->fetchLink('title', '?page='.$this->page.'&action=edit&id=%id%')

    // setActions: configure row actions
    ->setActions([
        'edit' => [
            'label' => 'Edit',
            'link' => '?page='.$this->page.'&action=edit&id=%id%',
            'fetch' => true  // Triggers fetch request instead of navigation
        ],
        'delete' => [
            'label' => 'Delete',
            'action' => [$this, 'actionDelete'],
            'confirm' => 'Are you sure?'
        ]
    ])
    ->render();</code></pre>

<hr>

<h2>JSON Response Structure</h2>

<p><code>Response::htmlJson()</code> accepts an array that controls various UI elements:</p>

<div class="table-responsive">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Key</th>
                <th>Description</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>offcanvas_end</code></td>
                <td>Control offcanvas sidebar</td>
                <td><code>['action' => 'show', 'title' => 'Edit', 'body' => $html]</code></td>
            </tr>
            <tr>
                <td><code>table</code></td>
                <td>Reload table</td>
                <td><code>['id' => 'tableId', 'action' => 'reload']</code></td>
            </tr>
            <tr>
                <td><code>hook</code></td>
                <td>Trigger JavaScript hook</td>
                <td><code>['name' => 'update-form']</code></td>
            </tr>
            <tr>
                <td><code>toast</code></td>
                <td>Show notification</td>
                <td><code>['message' => 'Success', 'type' => 'success']</code></td>
            </tr>
            <tr>
                <td><code>redirect</code></td>
                <td>Navigate to URL</td>
                <td><code>'?page=tasks'</code></td>
            </tr>
        </tbody>
    </table>
</div>

<hr>

<h2>JavaScript Hooks</h2>

<p>Use JavaScript hooks to add custom client-side behavior when forms are loaded or updated.</p>

<h3>Setup</h3>

<p><strong>1. Create JavaScript file</strong> in <code>Modules/Tasks/Assets/task.js</code>:</p>

<pre><code class="language-javascript">registerHook('update-form', function() {
    const title = document.querySelector('[name="title"]');

    if (title) {
        title.addEventListener('fieldValidation', function(e) {
            const field = e.detail.field;

            if (field.value.length < 3) {
                field.setCustomValidity('Title must be at least 3 characters');
            } else {
                field.setCustomValidity('');
            }
        });
    }
});</code></pre>

<p><strong>2. Register in module</strong> configuration:</p>

<pre><code class="language-php">protected function configure($rule): void {
    $rule->page('tasks')
         ->setJs('Assets/task.js')
         ->version(20251021);
}</code></pre>

<p><strong>3. Trigger in controller response</strong>:</p>

<pre><code class="language-php">$response['hook'] = ["name" => "update-form"];
Response::htmlJson($response);</code></pre>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    Include the hook in <strong>every response</strong> that contains form HTML to ensure it runs on both initial load and after validation errors.
</div>

<hr>

<h2>Key Concepts Summary</h2>

<ul>
    <li><strong>FormBuilder third parameter:</strong> <code>false</code> = fetch mode, returns JSON instead of redirecting</li>
    <li><strong>getPressedAction():</strong> Returns which button was clicked (<code>'save'</code>, <code>'delete'</code>, <code>'cancel'</code>)</li>
    <li><strong>addStandardActions(true):</strong> Automatically adds save, delete, and cancel buttons</li>
    <li><strong>Response::htmlJson():</strong> Returns JSON to control UI elements (offcanvas, tables, hooks)</li>
    <li><strong>fetchLink():</strong> Makes table columns trigger fetch requests instead of navigation</li>
    <li><strong>fetch: true:</strong> Makes table row actions use fetch instead of page reload</li>
    <li><strong>hook:</strong> Triggers JavaScript code registered with <code>registerHook()</code></li>
</ul>

<hr>

<h2>See Also</h2>

<ul>
    <li><a href="?page=docs&action=Developer/GettingStarted/getting-started-post">Creating Modules (Posts Example)</a> - Module basics without fetch</li>
    <li><a href="?page=docs&action=Developer/Form/builders-form">FormBuilder Documentation</a> - Complete form builder reference</li>
    <li><a href="?page=docs&action=Developer/Table/builders-table">TableBuilder Documentation</a> - Complete table builder reference</li>
</ul>

</div>