<?php
use MilkCore\Get;
use MilkCore\Form;
use MilkCore\MessagesHandler;

/**
 * @title Form Validation
 * @category Forms
 * @order 20
 * @tags 
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">


    <h2>Validation</h2>

    <p>The system follows the Bootstrap standard for form validation.</p>

    <form class="was-validated">

    <div class="mb-3">
        <?php Form::textarea('myValidTextarea', 'Label', '', 4, ['required'=>true, 'invalid-feedback'=>'Please enter a message in the textarea.']); ?>
    </div>

    <?php Form::checkboxes('myValidCheckboxes', ['1' => 'Check this checkbox'], '', false, ['invalid-feedback'=>'Example invalid feedback text', 'form-group-class'=>'mb-3'],['required'=>true]); ?>

    <?php Form::radios('myValidRadios', ['1' => 'Toggle this radio', '2' => 'Or toggle this other radio'],  '', false, ['label'=>'Select fields', 'invalid-feedback'=>'Please select a value', 'form-group-class'=>'mb-3'],['required'=>true]); ?>

    <div class="mb-3">
        <?php Form::select('mySelect', 'Label', [''=>'Open this select menu', '1' => 'One', '2' => 'Two', '3' => 'Three'], '', ['required'=>true, 'floating'=>true, 'invalid-feedback'=>'Please select a value']); ?>
    </div>

    <div class="mb-3">
    <?php echo Get::theme_plugin('upload-files',['name'=>'filet', 'label'=>'File', 'value'=>'', 'options'=>['multiple'=>true, 'required'=>true, 'invalid-feedback'=>'Please upload a file'], 'upload_name' => 'my_upload3'] ); ?>
    </div>

    <div class="mb-3">
        <button class="btn btn-primary" type="submit" disabled>Submit form</button>
    </div>
    </form>

    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;form class=&quot;was-validated&quot;&gt;
        &lt;div class=&quot;mb-3&quot;&gt;
        &lt;?php Form::textarea(&#39;myValidTextarea&#39;, &#39;Label&#39;, &#39;&#39;, 4, [&#39;required&#39;=&gt;true, &#39;invalid-feedback&#39;=&gt;&#39;Please enter a message in the textarea.&#39;]); ?&gt;
        &lt;/div&gt;
        &lt;?php 
        Form::checkboxes(&#39;myValidCheckboxes&#39;, [&#39;1&#39; =&gt; &#39;Check this checkbox&#39;], &#39;&#39;, false, [&#39;invalid-feedback&#39;=&gt;&#39;Example invalid feedback text&#39;, &#39;form-group-class&#39;=&gt;&#39;mb-3&#39;],[&#39;required&#39;=&gt;true]);
        Form::radios(&#39;myValidRadios&#39;, [&#39;1&#39; =&gt; &#39;Toggle this radio&#39;, &#39;2&#39; =&gt; &#39;Or toggle this other radio&#39;],  &#39;&#39;, false, [&#39;label&#39;=&gt;&#39;Select fields&#39;, &#39;invalid-feedback&#39;=&gt;&#39;Please select a value&#39;, &#39;form-group-class&#39;=&gt;&#39;mb-3&#39;],[&#39;required&#39;=&gt;true]); 
        ?&gt;
        &lt;div class=&quot;mb-3&quot;&gt;
        &lt;?php Form::select(&#39;mySelect&#39;, &#39;Label&#39;, [&#39;&#39;=&gt;&#39;Open this select menu&#39;, &#39;1&#39; =&gt; &#39;One&#39;, &#39;2&#39; =&gt; &#39;Two&#39;, &#39;3&#39; =&gt; &#39;Three&#39;], &#39;&#39;, [&#39;required&#39;=&gt;true, &#39;floating&#39;=&gt;true, &#39;invalid-feedback&#39;=&gt;Please select a value&#39;]); ?&gt;
        &lt;/div&gt;
        &lt;div class=&quot;mb-3&quot;&gt;
        &lt;?php Form::input(&#39;file&#39;, &#39;file&#39;, &#39;&#39;, &#39;&#39;, [&#39;required&#39;=&gt;true, &#39;invalid-feedback&#39;=&gt;&#39;Example invalid form file feedback&#39;]); ?&gt;
        &lt;/div&gt;
        &lt;div class=&quot;mb-3&quot;&gt;
        &lt;button class=&quot;btn btn-primary&quot; type=&quot;submit&quot; disabled&gt;Submit form&lt;/button&gt;
        &lt;/div&gt;
    &lt;/form&gt;
    </code></pre>

    <p>To make validation work, add the following script:</p>
    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-js">form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
            event.preventDefault()
            event.stopPropagation()
        }

        form.classList.add('was-validated')
    }, false)</code></pre>

    <br><br>
    <h4>Invalid form via PHP</h4>
    <p>If the form is submitted, but some fields are wrong and an alert with error messages must be shown, you can use the <code>MessagesHandler</code> class.</p>

    <?php
    MessagesHandler::add_error('This is a test name error message', 'test-name');
    echo MessagesHandler::get_error_alert();
    ?>
    <div class="bg-light p-2">
            <div class="form-group col-xl-6">
            <?php Form::input('text', 'test-name', 'Name', '', ['id'=>'my-custom-test-name-id', 'invalid-feedback'=>'Please enter a test name.']); ?>
        </div>
    </div>

    <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;?php
        MessagesHandler::add_error('This is a test name error message', 'test-name');
        echo MessagesHandler::get_error_alert(); 
    ?&gt;

    &lt;div class=&quot;bg-light p-2&quot;&gt;
        &lt;div class=&quot;form-group col-xl-6&quot;&gt;
            &lt;?php Form::input('text', 'test-name', 'Name', '', ['id'=&gt;'my-custom-test-name-id', 'invalid-feedback'=&gt;'Please enter a test name.']); ?&gt;
        &lt;/div&gt;
    &lt;/div&gt;</code></pre>


    <p>From PHP you may want to specify which fields were not validated during form submission through the <code>MessagesHandler</code> class.</p>
    <p>The functions are:</p>
    <h6 class="fw-bold"> MessagesHandler::add_error($msg, $field);</h6>
    <p>Adds an error message for a field</p>
    <h6 class="fw-bold"> MessagesHandler::add_field_error($field);</h6>
    <p>Adds a field as invalid</p>
    <h6 class="fw-bold"> MessagesHandler::get_error_alert($field_name);</h6>
    <p>Returns an alert with error messages</p>
   
    
</div>