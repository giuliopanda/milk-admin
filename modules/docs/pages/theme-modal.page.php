<?php
namespace Modules\docs;
/** 
* @title Modal 
* @category Theme 
* @order 30
* @tags modal, javascript, window.modal, Template, show, hide, title
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4"> 
<h1>Template: modal</h1> 
<p>Manages the template modal.</p> 

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">&lt;button class="btn btn-primary" onclick="exShowModal()"&gt;Show Modal&lt;/button&gt;
&lt;script&gt; 
function exShowModal() { 
window.modal.show('Show modal', 'This is the body of the modal', 'footer'); 
}
&lt;/script&gt;</code></pre> 
<div class="card"> 
<div class="card-body"> 
<button class="btn btn-primary" onclick="exShowModal()">Show Modal</button> 
</div> 
</div> 


<script> 
function exShowModal() { 
window.modal.show('Show modal', 'This is the body of the modal', 'footer'); 
} 
</script>

The close button in the footer is written like this: 
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;button type=&quot;button&quot; class=&quot;btn btn-secondary&quot; data-bs-dismiss=&quot;modal&quot;&gt;Close&lt;/button&gt;</code></pre>

<br><br>
<h2>Properties</h2>
<p>The <code>Modal</code> class is instantiated in <code>window.modal</code> and provides the following methods:</p>
<ul>
<li><code>show(title, body, footer)</code> - shows the modal</li>
<li><code>hide()</code> - hides the message</li>
<li><code>title(html)</code> - sets the title of the modal</li>
<li><code>body(html)</code> - sets the content of the modal</li>
<li><code>footer(html)</code> - sets the footer of the modal</li>
</ul>

</div>