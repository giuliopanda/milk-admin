<?php
namespace Modules\Docs\Pages;
use App\Form;
/**
 * @title  Javascipt Toggle
 * @guide framework
 * @order 50
 * @tags toggle-functionality, show-hide, elHide, elShow, conditional-display, UI-interactions, form-controls, toggle, hide-show, elRemove, toggleEl
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
<h2 class="mt-4">elHide / elShow / elRemove</h2>
    <p>These two functions allow you to hide or show a DOM element.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">elHide(el, () => { 
    // Callback function. This is the hidden element.
})</code></pre>
    <div class="card">
        <div class="card-body">
        <h5 class="card-title">Show/Hide Example</h5>
            <div class="btn btn-primary" onclick="elHide(document.getElementById('idel1'))">Hide</div>
            <div class="btn btn-primary" onclick="elShow(document.getElementById('idel1'))">Show</div>
            <p>Lorem ipsum<br>
            <div style="background: #f0f0f0; padding: 10px; margin-top: 10px;" id="idel1">
                <div id="elHide">Element to hide</div>
            </div>
            dolor sit amet.</p>
        </div>
    </div>
    <p>elRemove hides and then directly removes the element</p>

    <p>The js function toggleEl(el, el_form, value) shows or hides el.<br>
    If no other values are passed besides the element to show or hide, each time it's called it alternates
    the element's state. If a second element and a value are passed, then it shows or hides the element    
    based on the value of a field indicated as the second element</p>

    <div class="bg-light p-2"> 
        <div class="form-group col-xl-6">
            <div class="form-check">
                <?php Form::checkbox('toggleCheckbox', 'Etichetta', '1'); ?>
                <div class="my-2" id="test1NotChecked">Checkbox not selected</div>
                <div class="my-2" id="test1Checked">Checkbox selected</div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('[name="toggleCheckbox"]').addEventListener('input', () => { ck1() });
            ck1();
        });
        function ck1() {
            const checkbox = document.querySelector('[name="toggleCheckbox"]');
            toggleEl(document.getElementById('test1NotChecked'), checkbox, null);
            toggleEl(document.getElementById('test1Checked'));
        }
    </script>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;div class=&quot;form-group col-xl-6&quot;&gt;
        &lt;div class=&quot;form-check&quot;&gt;
            &lt;?php Form::checkbox('toggleCheckbox', 'Etichetta', '1'); ?&gt;
            &lt;div class=&quot;my-2&quot; id=&quot;test1NotChecked&quot;&gt;Checkbox not selected&lt;/div&gt;
            &lt;div class=&quot;my-2&quot; id=&quot;test1Checked&quot;&gt;Checkbox selected&lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;</code></pre>
    <p>The javascript script</p>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-js">document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('[name="toggleCheckbox"]').addEventListener('input', () => { ck1() });
        ck1();
    });
    function ck1() {
        const checkbox = document.querySelector('[name="toggleCheckbox"]');
        toggleEl(document.getElementById('test1NotChecked'), checkbox, null);
        toggleEl(document.getElementById('test1Checked'));
    }</code></pre>
    <br>

    <h4>Toggle 2</h4>
    <p>This example with a select instead uses datasets to automatically configure toggleEl so
        you don't have to call javascript</p>

    <div class="bg-light p-2"> 
        <div class="form-group col-xl-6">
            <?php Form::select('toggleSelect', 'Etichetta', ['1' => 'Uno', '2' => 'Due', '3' => 'Tre'], '2'); ?>
            <div class="my-2" data-togglevalue="1" data-togglefield="toggleSelect">Select One</div>
            <div class="my-2" data-togglevalue="2" data-togglefield="toggleSelect">Select Two</div>
        </div>
    </div>

    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;div class=&quot;form-group col-xl-6&quot;&gt;
        &lt;?php Form::select('toggleSelect', 'Etichetta', ['1' =&gt; 'Uno', '2' =&gt; 'Due', '3' =&gt; 'Tre'], '2'); ?&gt;
        &lt;div class=&quot;my-2&quot; data-togglevalue=&quot;1&quot; data-togglefield=&quot;toggleSelect&quot;&gt;Select One&lt;/div&gt;
        &lt;div class=&quot;my-2&quot; data-togglevalue=&quot;2&quot; data-togglefield=&quot;toggleSelect&quot;&gt;Select Two&lt;/div&gt;
    &lt;/div&gt;</code></pre>
    <br>

    <h2 class="mt-4">toggleEls</h2>
    <p>If an element has the data-togglefield attribute then a toggleEl is created for the field 
    with the name indicated in data-togglefield for example:
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;input type="text" name="field1" &gt;
    &lt;div data-togglefield="field1" data-togglevalue="1"&gt;Show when value = 1&lt;/div&gt;</code></pre>
        <div class="card">
            <div class="card-body">
            <input type="text" name="field1" >
            <div data-togglefield="field1" data-togglevalue="1">Show when value = 1</div>
            </div>
        </div>
    <p>This way elements are shown or hidden without calling javascript</p>

</div>