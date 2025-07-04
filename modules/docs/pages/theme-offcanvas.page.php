<?php
namespace Modules\docs;
use MilkCore\Route;
/** 
* @title Offcanvas 
* @category Theme 
* @order 30
* @tags Offcanvas, javascript, window.offcanvasEnd, window.offcanvas, show, hide, title, body, size
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4"> 
<h1>Template: Offcanvas</h1> 
<p>An offcanvas is a sidebar that opens to the right of the page. This sidebar is managed by javascript.</p>
<p>The contents shown are like element details or form edits.</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;button class="btn btn-primary" onclick="offcanvasTest1()"&gt;Show Offcanvas&lt;/button&gt;
&lt;script&gt;
function offcanvasTest1() {
window.offcanvasEnd.show()
window.offcanvasEnd.title('Offcanvas Test')
window.offcanvasEnd.body(`&lt;button class="btn btn-primary" onclick="window.offcanvasEnd.hide()"&gt;Hide Offcanvas&lt;/button&gt;`);
}
&lt;/script&gt;</code></pre> 
<div class="card"> 
<div class="card-body"> 
<button class="btn btn-primary" onclick="offcanvasTest1()">Show Offcanvas</button> 
</div> 
</div> 


<script> 
function offcanvasTest1() { 
window.offcanvasEnd.show() 
window.offcanvasEnd.title('Offcanvas Test') 
window.offcanvasEnd.body(`<button class="btn btn-primary" onclick="window.offcanvasEnd.hide()">Hide Offcanvas</button>`); 
}
</script>

<br><br>
<h2>Properties</h2>
<p>The <code>Offcanvas</code> class is instantiated in <code>window.offcanvasEnd</code> and provides the following methods:</p>
<ul>
<li><code>show()</code> - shows the sidebar</li>
<li><code>hide()</code> - hides the sidebar</li>
<li><code>title()</code> - sets the title of the sidebar</li>
<li><code>body()</code> - sets the content of the sidebar</li>
<li><code>size()</code> - sets the size of the sidebar xl | empty</li>
</ul>

</div>