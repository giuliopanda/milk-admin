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

    <p>Form button management: submit, link, custom callbacks and conditional visibility.</p>

    <h2>Available Methods</h2>

    <ul>
        <li><code>addStandardActions($include_delete, $cancel_link)</code> - Pre-configured save/delete/cancel buttons</li>
        <li><code>setActions($actions)</code> - Replaces all actions</li>
        <li><code>addActions($actions)</code> - Adds actions without replacing</li>
    </ul>

    <h2>Standard Actions</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($model)
    ->addFieldsFromObject($data, 'edit')
    ->addStandardActions(true, '?page=posts')
    ->render();</code></pre>

    <p>Automatically creates:</p>
    <ul>
        <li><strong>Save</strong>: blue button for saving</li>
        <li><strong>Delete</strong>: red button with confirmation (only when editing, hidden for new records)</li>
        <li><strong>Cancel</strong>: gray link to return to the list</li>
    </ul>

    <h2>Custom Actions</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->setActions([
    'save' => [
        'label' => 'Save',
        'class' => 'btn btn-primary',
        'action' => \Builders\FormBuilder::saveAction()
    ],
    'publish' => [
        'label' => 'Publish',
        'class' => 'btn btn-success',
        'action' => function($fb, $request) {
            $result = $fb->save($request);
            if ($result['success']) {
                $fb->model->update(['status' => 'published']);
            }
            return $result;
        }
    ]
])</code></pre>

    <h2>Link Buttons</h2>

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
])</code></pre>

    <h2>Conditional Visibility - showIf</h2>

    <p>Show/hide buttons based on field values:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addActions([
    'delete' => [
        'label' => 'Delete',
        'class' => 'btn btn-danger',
        'action' => \Builders\FormBuilder::deleteAction(),
        'confirm' => 'Are you sure?',
        'showIf' => ['id', 'not_empty', 0]  // Only if id is not empty
    ]
])</code></pre>

    <h3>showIf Syntax</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">'showIf' => [$field_name, $operator, $value]</code></pre>

    <h3>Supported Operators</h3>
    <ul>
        <li><code>'empty'</code> - Field is empty, null, 0 or empty string</li>
        <li><code>'not_empty'</code> - Field has a value</li>
        <li><code>'='</code> or <code>'=='</code> - Equal</li>
        <li><code>'!='</code> or <code>'&lt;&gt;'</code> - Not equal</li>
        <li><code>'&gt;'</code> - Greater than</li>
        <li><code>'&lt;'</code> - Less than</li>
        <li><code>'&gt;='</code> - Greater than or equal</li>
        <li><code>'&lt;='</code> - Less than or equal</li>
    </ul>

    <h3>showIf Examples</h3>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Show only for existing records
'showIf' => ['id', 'not_empty', 0]

// Show only for drafts
'showIf' => ['status', '=', 'draft']

// Show only for published
'showIf' => ['status', '=', 'published']</code></pre>

    <h2>Configuration Options</h2>

    <ul>
        <li><code>label</code> (string) - Button text</li>
        <li><code>type</code> (string) - 'submit' or 'link' (default: 'submit')</li>
        <li><code>class</code> (string) - CSS classes (default: 'btn btn-primary')</li>
        <li><code>action</code> (callable) - Callback for submit buttons</li>
        <li><code>link</code> (string) - URL for link-type buttons</li>
        <li><code>validate</code> (bool) - Enable HTML5 validation (default: true)</li>
        <li><code>confirm</code> (string) - Confirmation message</li>
        <li><code>onclick</code> (string) - JavaScript onclick handler</li>
        <li><code>target</code> (string) - Link target (_blank, _self, etc.)</li>
        <li><code>showIf</code> (array) - Conditional visibility [field, operator, value]</li>
    </ul>

    <h2>Static Helpers</h2>

    <ul>
        <li><code>\Builders\FormBuilder::saveAction()</code> - Standard save operation</li>
        <li><code>\Builders\FormBuilder::deleteAction($success_url, $error_url)</code> - Delete operation with redirect</li>
    </ul>

    <h2>Complete Example</h2>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$form = \Builders\FormBuilder::create($this->model, 'posts', '?page=posts')
    ->addFieldsFromObject($post, 'edit')
    ->setActions([
        'save' => [
            'label' => 'Save',
            'class' => 'btn btn-primary',
            'action' => \Builders\FormBuilder::saveAction()
        ],
        'publish' => [
            'label' => 'Publish',
            'class' => 'btn btn-success',
            'action' => function($fb, $request) {
                $result = $fb->save($request);
                if ($result['success']) {
                    $fb->model->update(['status' => 'published']);
                }
                return $result;
            },
            'showIf' => ['status', '=', 'draft']
        ],
        'delete' => [
            'label' => 'Delete',
            'class' => 'btn btn-danger',
            'action' => \Builders\FormBuilder::deleteAction(),
            'validate' => false,
            'confirm' => 'Are you sure?',
            'showIf' => ['id', 'not_empty', 0]
        ],
        'cancel' => [
            'label' => 'Cancel',
            'type' => 'link',
            'class' => 'btn btn-secondary',
            'link' => '?page=posts'
        ]
    ])
    ->render();</code></pre>

</div>