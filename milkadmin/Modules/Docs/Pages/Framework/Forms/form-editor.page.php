<?php
namespace Modules\Docs\Pages;
use App\Get;
/**
 * @title Editor Text
 * @guide framework
 * @order 40
 * @tags editor, trix, wysiwyg, rich text, insertText, insertHTML, getValue, setValue
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

    <div class="alert alert-primary">
        <h5 class="alert-heading">Quick Form Creation with FormBuilder</h5>
        <p class="mb-0">
            This is a manual, artisanal system for creating editor fields. If you need to create forms quickly from your Models,
            we recommend using the <strong>FormBuilder</strong> which can generate complete forms in minutes:
            <br><br>
            <a href="?page=docs&action=Developer/Form/builders-form" class="alert-link">
                <strong>→ Getting Started - Forms with FormBuilder</strong>
            </a>
        </p>
    </div>

    <h1>Editor</h1>

    <p class="alert alert-info">The editor automatically saves content in HTML format within a textarea with the field name set. To get the value you can still use <code>window.editor.getValue('editorID')</code></p>

    <h3>1. Simple Editor</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php echo Get::themePlugin('editor', [
    'id' => 'simpleEditor',
    'label' => 'Description',
    'placeholder' => 'Enter text...',
    'value' => '&lt;p&gt;Initial content&lt;/p&gt;'
]); ?&gt;</code></pre>
    <div class="demo-section col-xl-8">
        <?php echo Get::themePlugin('editor', [
            'id' => 'simpleEditor',
            'label' => 'Description',
            'placeholder' => 'Enter text...',
            'value' => '<p>Initial content</p>'
        ]); ?>
    </div>

    <h3>2. Editor with Events</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php echo Get::themePlugin('editor', [
    'id' => 'eventEditor',
    'label' => 'Content with Events',
    'onChange' => '(value, event) => {
        document.getElementById("charCount").textContent = "HTML chars: " + value.length;
        document.getElementById("plainTextCount").textContent = "Text chars: " + window.editor.getPlainText("eventEditor").length;
    }',
    'onFocus' => '(event) => {
        document.getElementById("focusStatus").textContent = "Editor active";
    }',
    'onBlur' => '(value, event) => {
        document.getElementById("focusStatus").textContent = "Editor inactive";
    }'
]); ?&gt;</code></pre>
    <div class="demo-section col-xl-8">
        <?php echo Get::themePlugin('editor', [
            'id' => 'eventEditor',
            'label' => 'Content with Events',
            'onChange' => '(value, event) => {
                document.getElementById("charCount").textContent = "HTML chars: " + value.length;
                document.getElementById("plainTextCount").textContent = "Text chars: " + window.editor.getPlainText("eventEditor").length;
            }',
            'onFocus' => '(event) => {
                document.getElementById("focusStatus").textContent = "Editor active";
            }',
            'onBlur' => '(value, event) => {
                document.getElementById("focusStatus").textContent = "Editor inactive";
            }'
        ]); ?>
        <div class="mt-3">
            <div id="charCount" class="text-body-secondary">HTML chars: 0</div>
            <div id="plainTextCount" class="text-body-secondary">Text chars: 0</div>
            <div id="focusStatus" class="text-info">Editor inactive</div>
        </div>
    </div>

    <h3>3. Content Manipulation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php echo Get::themePlugin('editor', [
    'id' => 'manipulationEditor',
    'label' => 'Editor for Manipulation',
    'value' => '&lt;p&gt;Initial text for manipulation test&lt;/p&gt;'
]); ?&gt;</code></pre>
    <div class="demo-section col-xl-8">
        <?php echo Get::themePlugin('editor', [
            'id' => 'manipulationEditor',
            'label' => 'Editor for Manipulation',
            'value' => '<p>Initial text for manipulation test</p>'
        ]); ?>
        
        <div class="mt-3">
            <div class="btn-group" role="group">
                <button class="btn btn-sm btn-outline-primary" onclick="insertTextDemo()">Insert Text</button>
                <button class="btn btn-sm btn-outline-primary" onclick="insertHtmlDemo()">Insert HTML</button>
                <button class="btn btn-sm btn-outline-primary" onclick="setValueDemo()">Replace Content</button>
                <button class="btn btn-sm btn-outline-primary" onclick="clearDemo()">Clear</button>
                <button class="btn btn-sm btn-outline-primary" onclick="toggleEnabled()">Enable/Disable</button>
            </div>
        </div>
        
        <script>
            let isEnabled = true;
            
            function insertTextDemo() {
                window.editor.insertText('manipulationEditor', ' [INSERTED TEXT] ');
            }
            
            function insertHtmlDemo() {
                window.editor.insertHTML('manipulationEditor', '<strong style="color: red;"> [INSERTED HTML] </strong>');
            }
            
            function setValueDemo() {
                window.editor.setValue('manipulationEditor', '<h3>New Content</h3><p>This content has replaced all previous content.</p>');
            }
            
            function clearDemo() {
                window.editor.clear('manipulationEditor');
            }
            
            function toggleEnabled() {
                isEnabled = !isEnabled;
                window.editor.setEnabled('manipulationEditor', isEnabled);
                event.target.textContent = isEnabled ? 'Disable' : 'Enable';
            }
        </script>
    </div>
    <h2 class="mt-5">JavaScript API Documentation</h2>
    <p>The editor plugin exposes its functionality through the global <code>window.editor</code> object.</p>

    <h3>Main Methods</h3>
    
    <table class="table table-bordered mt-3">
        <thead>
            <tr>
                <th>Method</th>
                <th>Description</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>getValue(containerId)</code></td>
                <td>Gets the current HTML content of the editor</td>
                <td><code>window.editor.getValue('myEditor')</code></td>
            </tr>
            <tr>
                <td><code>getPlainText(containerId)</code></td>
                <td>Gets content as plain text (without HTML)</td>
                <td><code>window.editor.getPlainText('myEditor')</code></td>
            </tr>
            <tr>
                <td><code>setValue(containerId, value)</code></td>
                <td>Sets the editor content</td>
                <td><code>window.editor.setValue('myEditor', '&lt;p&gt;New content&lt;/p&gt;')</code></td>
            </tr>
            <tr>
                <td><code>insertText(containerId, text)</code></td>
                <td>Inserts text at cursor position</td>
                <td><code>window.editor.insertText('myEditor', 'Text to insert')</code></td>
            </tr>
            <tr>
                <td><code>insertHTML(containerId, html)</code></td>
                <td>Inserts HTML at cursor position</td>
                <td><code>window.editor.insertHTML('myEditor', '&lt;strong&gt;Bold text&lt;/strong&gt;')</code></td>
            </tr>
            <tr>
                <td><code>clear(containerId)</code></td>
                <td>Clears the editor completely</td>
                <td><code>window.editor.clear('myEditor')</code></td>
            </tr>
            <tr>
                <td><code>setEnabled(containerId, enabled)</code></td>
                <td>Enables or disables the editor</td>
                <td><code>window.editor.setEnabled('myEditor', false)</code></td>
            </tr>
        </tbody>
    </table>

    <h3 class="mt-4">Complete Example</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">// Create editor via PHP
// &lt;?php echo Get::themePlugin('editor', ['id' => 'myEditor']); ?&gt;

// Get the value
const content = window.editor.getValue('myEditor');

// Insert content
window.editor.insertHTML('myEditor', '&lt;em&gt;Emphasized text&lt;/em&gt;');

// Handle events
document.getElementById('saveBtn').onclick = () => {
    const htmlContent = window.editor.getValue('myEditor');
    const textContent = window.editor.getPlainText('myEditor');
    
    console.log('HTML:', htmlContent);
    console.log('Text:', textContent);
    
    // Save to server...
};</code></pre>

    <p>WYSIWYG editor based on Trix for formatted content management.</p>
</div>