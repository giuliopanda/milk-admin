<?php
namespace Modules\Docs\Pages;
/** 
* @title Toasts 
* @order 30 
* @tags toasts, notifications, alerts, window.toasts, user-feedback, success-messages, error-messages
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Template: toasts</h1>
    <p>The toast is a small box that appears in the center of the page to send a message of successful
        saving or some type of error (it could also be about fetch response problems)
    </p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">&lt;button class="btn btn-primary" onclick="toastsTest1()"&gt;Show Toast&lt;/button&gt;
&lt;script&gt;
    function toastsTest1() {
        window.toasts.show(`Row saved`, 'success');
    }
&lt;/script&gt;</code></pre>
    <div class="card">
        <div class="card-body">
        <button class="btn btn-primary" onclick="toastsTest1()">Show Toast</button>
        </div>
    </div>

    <script>
        function toastsTest1() {
            window.toasts.show()
            window.toasts.body(`Row saved`, 'success');
        }
    </script>

    <br><br>
    <h2>Properties</h2>
    <p>The <code>Toasts</code> class is instantiated in <code>window.toasts</code> and provides the following methods:</p>
    <ul>
        <li><code>show()</code> - shows the message</li>
        <li><code>hide()</code> - hides the message</li>
        <li><code>body(html, type)</code> - sets the content and color of the box (success,danger,warning,primary)</li>
    </ul>

</div>