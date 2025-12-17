<?php
namespace Modules\Docs\Pages;

/**
 * @title Action Management
 * @guide developer
 * @order 42
 * @tags FormBuilder, actions, buttons, submit, link, custom-actions, showIf, conditional-buttons, delete, save, cancel, form-actions
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>FormBuilder - Action Management</h1>

    <p>FormBuilder actions allow you to manage form buttons (save, delete, cancel, etc.) with advanced features such as custom callbacks, conditional visibility, and validation.</p>

    <h2>Available Methods</h2>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th style="width: 35%">Method</th>
                <th style="width: 40%">Description</th>
                <th style="width: 25%">Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>addStandardActions(bool $include_delete, string $cancel_link)</code></td>
                <td>Adds pre-configured Save/Delete/Cancel buttons</td>
                <td><code>->addStandardActions(true, '?page=posts')</code></td>
            </tr>
            <tr>
                <td><code>setActions(array $actions)</code></td>
                <td>Replaces all existing actions</td>
                <td><code>->setActions([...])</code></td>
            </tr>
            <tr>
                <td><code>addActions(array $actions)</code></td>
                <td>Adds actions without replacing existing ones</td>
                <td><code>->addActions([...])</code></td>
            </tr>
        </tbody>
    </table>

    <h2>Basic Usage</h2>

    <p>The simplest way to add buttons to the form is to use <code>addStandardActions()</code>:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = FormBuilder::create($model, $this->page)
    ->field('title')->label('Title')->required()
    ->field('content')->formType('textarea')
    ->addStandardActions(false, '?page=posts')  // Only Save and Cancel
    ->getForm();
</code></pre>

    <p>To include the Delete button as well:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addStandardActions(true, '?page=posts')  // Save, Delete and Cancel
</code></pre>

    <h2>Action Parameters</h2>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Parameter</th>
                <th>Type</th>
                <th>Default</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>label</code></td>
                <td>string</td>
                <td>Capitalized key</td>
                <td>Button text</td>
            </tr>
            <tr>
                <td><code>type</code></td>
                <td>string</td>
                <td>'submit'</td>
                <td>'submit', 'button' or 'link'</td>
            </tr>
            <tr>
                <td><code>class</code></td>
                <td>string</td>
                <td>'btn btn-primary'</td>
                <td>CSS classes for styling</td>
            </tr>
            <tr>
                <td><code>action</code></td>
                <td>callable</td>
                <td>-</td>
                <td>Callback function: <code>function($fb, $request): array</code><br>Returns: <code>['success' => bool, 'message' => string]</code></td>
            </tr>
            <tr>
                <td><code>link</code></td>
                <td>string</td>
                <td>-</td>
                <td>URL for link-type buttons</td>
            </tr>
            <tr>
                <td><code>validate</code></td>
                <td>bool</td>
                <td>true</td>
                <td>Enable/disable HTML5 validation</td>
            </tr>
            <tr>
                <td><code>confirm</code></td>
                <td>string</td>
                <td>-</td>
                <td>Confirmation message before submit</td>
            </tr>
            <tr>
                <td><code>onclick</code></td>
                <td>string</td>
                <td>-</td>
                <td>Custom JavaScript onclick handler</td>
            </tr>
            <tr>
                <td><code>target</code></td>
                <td>string</td>
                <td>-</td>
                <td>Link target: _blank, _self, etc.</td>
            </tr>
            <tr>
                <td><code>attributes</code></td>
                <td>array</td>
                <td>[]</td>
                <td>Custom HTML attributes as key=>value pairs (e.g., ['data-id' => '123', 'title' => 'Click me'])</td>
            </tr>
            <tr>
                <td><code>showIf</code></td>
                <td>array</td>
                <td>-</td>
                <td>Conditional visibility: <code>[$field, $operator, $value]</code></td>
            </tr>
        </tbody>
    </table>

    <h2>showIf Operators</h2>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Operator</th>
                <th>Description</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>'empty'</code></td>
                <td>Field is empty, null, 0 or empty string</td>
                <td><code>['status', 'empty', 0]</code></td>
            </tr>
            <tr>
                <td><code>'not_empty'</code></td>
                <td>Field has a value</td>
                <td><code>['id', 'not_empty', 0]</code></td>
            </tr>
            <tr>
                <td><code>'='</code> or <code>'=='</code></td>
                <td>Equal to value</td>
                <td><code>['status', '=', 'draft']</code></td>
            </tr>
            <tr>
                <td><code>'!='</code> or <code>'&lt;&gt;'</code></td>
                <td>Not equal to value</td>
                <td><code>['status', '!=', 'published']</code></td>
            </tr>
            <tr>
                <td><code>'&gt;'</code>, <code>'&lt;'</code>, <code>'&gt;='</code>, <code>'&lt;='</code></td>
                <td>Comparison operators</td>
                <td><code>['price', '&gt;', 100]</code></td>
            </tr>
        </tbody>
    </table>

    <h2>Static Helpers</h2>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Helper</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>\Builders\FormBuilder::saveAction()</code></td>
                <td>Standard save operation with redirect</td>
            </tr>
            <tr>
                <td><code>\Builders\FormBuilder::deleteAction($url_success, $url_error)</code></td>
                <td>Delete operation with custom redirect URLs</td>
            </tr>
        </tbody>
    </table>

    <h2>Examples by Action Type</h2>

    <h3>Basic Submit Action</h3>
    <p>A submit button with standard save action:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->setActions([
    'save' => [
        'label' => 'Save',
        'class' => 'btn btn-primary',
        'action' => \Builders\FormBuilder::saveAction()
    ]
])
</code></pre>

    <h3>Action with Custom Callback</h3>
    <p>A button that executes a custom callback before or after saving:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addActions([
    'publish' => [
        'label' => 'Publish',
        'class' => 'btn btn-success',
        'action' => function($fb, $request) {
            // Save the data
            $result = $fb->save($request);

            // If save is successful, update the status
            if ($result['success']) {
                $fb->getModel()->update(['status' => 'published']);
                $result['message'] = 'Post published successfully!';
            }

            return $result;
        }
    ]
])
</code></pre>

    <h3>Link Action</h3>
    <p>A button that works as a link (does not submit):</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addActions([
    'cancel' => [
        'label' => 'Cancel',
        'type' => 'link',
        'class' => 'btn btn-secondary',
        'link' => '?page=posts'
    ],
    'preview' => [
        'label' => 'Preview',
        'type' => 'link',
        'class' => 'btn btn-outline-primary',
        'link' => '?page=posts&action=view&id=' . $id,
        'target' => '_blank'
    ]
])
</code></pre>

    <h3>Action with Confirmation</h3>
    <p>Button that requires confirmation before execution:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addActions([
    'delete' => [
        'label' => 'Delete',
        'class' => 'btn btn-danger',
        'action' => \Builders\FormBuilder::deleteAction(),
        'validate' => false,
        'confirm' => 'Are you sure you want to delete this item?'
    ]
])
</code></pre>

    <h3>Action with Conditional Visibility</h3>
    <p>Buttons that appear only when specific conditions are met:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addActions([
    'delete' => [
        'label' => 'Delete',
        'class' => 'btn btn-danger',
        'action' => \Builders\FormBuilder::deleteAction(),
        'showIf' => ['id', 'not_empty', 0]  // Show only if ID exists
    ],
    'publish' => [
        'label' => 'Publish',
        'class' => 'btn btn-success',
        'action' => function($fb, $request) { /* ... */ },
        'showIf' => ['status', '=', 'draft']  // Show only if status is draft
    ],
    'archive' => [
        'label' => 'Archive',
        'class' => 'btn btn-warning',
        'action' => function($fb, $request) { /* ... */ },
        'showIf' => ['status', '=', 'published']  // Show only if published
    ]
])
</code></pre>

    <h3>Action with Custom Attributes</h3>
    <p>Buttons with custom HTML attributes for custom JavaScript:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addActions([
    'export' => [
        'label' => 'Export',
        'type' => 'button',
        'class' => 'btn btn-info',
        'onclick' => 'exportData()',
        'attributes' => [
            'data-action' => 'export',
            'data-format' => 'csv',
            'data-id' => $model->id,
            'title' => 'Export to CSV'
        ]
    ]
])
</code></pre>

    <h2>Difference between setActions() and addActions()</h2>

    <ul>
        <li><code>setActions()</code>: Completely replaces all existing actions. Useful when you want total control over the buttons.</li>
        <li><code>addActions()</code>: Adds new actions to existing ones. Useful for adding custom buttons after using <code>addStandardActions()</code>.</li>
    </ul>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Use addStandardActions for base buttons
->addStandardActions(true, '?page=posts')

// Then add custom buttons with addActions
->addActions([
    'publish' => [
        'label' => 'Publish',
        'class' => 'btn btn-success',
        'action' => function($fb, $request) { /* ... */ },
        'showIf' => ['status', '=', 'draft']
    ]
])
</code></pre>

    <h2>Complete Example</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Modules\Posts;

use App\Abstracts\AbstractController;
use App\{Response, Route};
use Builders\FormBuilder;

class PostsController extends AbstractController
{
    public function edit() {
        $response = $this->getCommonData();

        $response['form'] = FormBuilder::create($this->model, $this->page)
            // Field configuration
            ->field('title')
                ->label('Post Title')
                ->required()

            ->field('content')
                ->formType('editor')
                ->label('Content')
                ->required()

            ->field('status')
                ->formType('select')
                ->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'archived' => 'Archived'
                ])
                ->value('draft')

            // Standard buttons
            ->addStandardActions(true, '?page=posts')

            // Additional custom buttons
            ->addActions([
                // Publish button - visible only for draft
                'publish' => [
                    'label' => 'Publish',
                    'class' => 'btn btn-success',
                    'action' => function($fb, $request) {
                        $result = $fb->save($request);
                        if ($result['success']) {
                            $fb->getModel()->update(['status' => 'published']);
                            $result['message'] = 'Post published successfully!';
                        }
                        return $result;
                    },
                    'showIf' => ['status', '=', 'draft']
                ],

                // Archive button - visible only for published
                'archive' => [
                    'label' => 'Archive',
                    'class' => 'btn btn-warning',
                    'action' => function($fb, $request) {
                        $result = $fb->save($request);
                        if ($result['success']) {
                            $fb->getModel()->update(['status' => 'archived']);
                        }
                        return $result;
                    },
                    'confirm' => 'Archive this post?',
                    'showIf' => ['status', '=', 'published']
                ],

                // Preview link in new window
                'preview' => [
                    'label' => 'Preview',
                    'type' => 'link',
                    'class' => 'btn btn-outline-primary',
                    'link' => '?page=posts&action=view&id=' . ($this->model->id ?? ''),
                    'target' => '_blank',
                    'showIf' => ['id', 'not_empty', 0]
                ]
            ])

            ->getForm();

        $response['title'] = 'Edit Post';
        Response::render(__DIR__ . '/Views/edit_page.php', $response);
    }
}
</code></pre>

</div>
