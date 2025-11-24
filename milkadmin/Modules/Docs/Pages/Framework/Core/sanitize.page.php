<?php
namespace Modules\Docs\Pages;
/**
 * @title Sanitize
 * @guide framework
 * @order 
 * @tags sanitization, XSS-protection, security, input-validation, HTML-sanitization, JavaScript-sanitization, CSRF-protection, data-security, output-encoding, safe-output, code-injection-prevention, HTML-entities
 */
!defined('MILK_DIR') && die(); // Avoid direct access 
?>
<div class="bg-white p-4">
    <h1>Data Sanitization</h1>
    <div class="alert alert-info">
        To protect your application from attacks, all variables to be displayed must be sanitized. MilkCore provides a series of helper functions in the <code>functions.php</code> file that internally use the <code>Sanitize</code> class.
    </div>

    <div class="alert alert-danger">
        <strong>A TIP:</strong> Don't sanitize data when saving to the database, but only when displaying it! Store the original data in the database and use prepared statements for security.
    </div>

    <h2>Function Naming Convention</h2>
    <p>The functions follow an intuitive naming convention:</p>
    <ul>
        <li><strong>_r...</strong> - Return: returns the sanitized variable</li>
        <li><strong>_p...</strong> - Print: directly prints the sanitized variable</li>
    </ul>

    <h2>Attack Protection</h2>
    <p>The sanitization functions mainly protect against:</p>
    <ul>
        <li><strong>XSS (Cross-Site Scripting)</strong>: prevents injection and execution of malicious JavaScript code</li>
        <li><strong>Code Injection</strong>: prevents insertion of PHP or other language code</li>
        <li><strong>HTML Injection</strong>: prevents insertion of malicious HTML tags</li>
    </ul>

    <h2>Main Functions</h2>

    <div class="card mb-4">
        <div class="card-header bg-light">
            <h3>For standard HTML content (text on page)</h3>
        </div>
        <div class="card-body">
            <h4>_p($var) - Print sanitized text</h4>
            <p>To be used for any text displayed on the page:</p>
            <pre class="border p-2 bg-light"><code class="language-php"><?php ob_start(); ?>// Example: Show a message entered by the user
echo "Message: "; 
_p($message); // Converts characters like < > " ' to HTML entities
// Dangerous (NEVER DO): 
echo $message; // Could execute malicious JavaScript code!
<?php _p(ob_get_clean()); ?> </code></pre>

            <h4>_r($var) - Return sanitized text</h4>
            <p>Use this function when you need the sanitized value for further operations:</p>
            <pre class="border p-2 bg-light"><code class="language-php"><?php ob_start(); ?>// Example: Create a data attribute with sanitized value
$sanitized = _r($userData); 
echo '<div data-user="' . $sanitized . '">';
// Useful for concatenations or subsequent operations:
$fullName = _r($firstName) . ' ' . _r($lastName); <?php _p(ob_get_clean()); ?></code></pre>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-light">
            <h3>For content in HTML attributes</h3>
        </div>
        <div class="card-body">
            <p>HTML attributes are particularly vulnerable to attacks. Always use sanitization:</p>
            <pre class="border p-2 bg-light"><code class="language-php"><?php ob_start(); ?>// CORRECT: 
echo '<input type="text" value="' . _r($userInput) . '">';
echo '<a href="profile.php?id=' . _r($userId) . '">Profile</a>';
// DANGEROUS (NEVER DO):
echo '<input type="text" value="' . $userInput . '">'; // Could inject attributes or close the tag
<?php _p(ob_get_clean()); ?></code></pre>

            <div class="alert alert-warning">
                <strong>Warning!</strong> An attack like <code>value="x" onclick="alert('hack')"</code> can only be prevented with sanitization!
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-light">
            <h3>For JavaScript (_pjs, _rjs)</h3>
        </div>
        <div class="card-body">
            <h4>_pjs($var) - Print safely inside JavaScript</h4>
            <p>This function is <strong>fundamental</strong> when inserting data in JavaScript. It uses <code>json_encode()</code> internally, which is the only safe way to insert data in JS:</p>

            <pre class="border p-2 bg-light"><code class="language-php"><?php ob_start(); ?>
// CORRECT:
<script> 
    var userName = <?php echo '<?php _pjs($userName); ?>;'; ?> 
    // Output: var userName = "Mario Rossi"; 
    var userConfig = <?php echo '<?php _pjs($configArray); ?>;'; ?>; 
    // Also handles arrays and objects! 
</script>
// DANGEROUS (NEVER DO):
<script> var userName = <?php echo "<?php echo $userName; ?>"; ?>; // Vulnerable to JS injection! </script>
        
<?php _p(ob_get_clean()); ?></code></pre>

            <div class="alert alert-danger">
                <strong>Important warning!</strong> htmlspecialchars() is NOT safe in JavaScript context! An attack like <code>"; alert('XSS'); //</code> would still work. Always use _pjs()!
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-light">
            <h3>For rich HTML (_ph, _rh)</h3>
        </div>
        <div class="card-body">
            <h4>_ph($var) - Print partially sanitized HTML</h4>
            <p>To be used when you want to allow some HTML tags but remove scripts and other dangerous elements:</p>

            <pre class="border p-2 bg-light"><code class="language-php"><?php ob_start(); ?>
// Example: Show a comment with basic HTML formatting allowed 
// The input might be: 
"<p>Hello <strong>world</strong></p><script>alert('hack');</script>"
_ph($commentWithHtml); // Output: "<p>Hello <strong>world</strong></p>"
<?php _p(ob_get_clean()); ?></code></pre>

            <div class="alert alert-warning">
                <strong>Warning!</strong> This function allows some HTML tags. Use it only when it's necessary to display formatted content.
            </div>

            <h4>_rh($var) - Return partially sanitized HTML</h4>
            <p>"Return" version of the previous function:</p>

            <pre class="border p-2 bg-light"><code class="language-php"><?php ob_start(); ?>
$safeHtml = _rh($richTextContent); // You can use $safeHtml in other operations or variables 
<?php _p(ob_get_clean()); ?></code></pre>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-light">
            <h3>Specialized Functions</h3>
        </div>
        <div class="card-body">
            <h4>_raz($var) / _paz($var) - Alphanumeric sanitization</h4>
            <p>Removes all non-alphanumeric characters, useful for IDs, filenames, etc:</p>

            <pre class="border p-2 bg-light"><code class="language-php"><?php ob_start(); ?>
// Example: Create a safe ID from user input
$userId = _raz("user-123!@#$%"); // Result: "user123"

// If it starts with a number, adds a random letter
$fileId = _raz("123document"); // Might result in: "a123document"

// Print version
echo "Generated ID: "; 
_paz("user-123!@#$%"); // Prints "user123"
<?php _p(ob_get_clean()); ?></code></pre>

            <h4>_absint($var) - Positive integers</h4>
            <p>Ensures the value is a positive integer:</p>

            <pre class="border p-2 bg-light"><code class="language-php"><?php ob_start(); ?>
// Example: Sanitization of a numeric ID
$pageId = _absint($_GET['id']); // Converts to positive integer
$query = "SELECT * FROM pages WHERE id = " . $pageId;<?php _p(ob_get_clean()); ?></code></pre>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-light">
            <h3>Functions with translation</h3>
        </div>
        <div class="card-body">
            <h4>_pt($var, ...args) / _rt($var, ...args) - Sanitization with translation</h4>
            <p>Sanitizes and translates text using the localization system. The ...args are passed to the translation function:</p>

            <pre class="border p-2 bg-light"><code class="language-php"><?php ob_start(); ?>
// Print a translated and sanitized string
_pt('welcome_message'); // Looks for the translation of 'welcome_message' and prints it sanitized
_pt('Hello %s', 'World'); // Uses translation with substitution
// Return version
$translatedText = _rt('welcome_message');
<?php _p(ob_get_clean()); ?></code></pre>
        </div>
    </div>

    <h2>Summary Table</h2>

    <table class="table table-bordered table-striped">
        <thead class="thead-dark">
            <tr>
                <th>Function</th>
                <th>Usage Context</th>
                <th>Protection</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>_p($var)</code> / <code>_r($var)</code></td>
                <td>Normal text, HTML attributes</td>
                <td>XSS, Code Injection</td>
            </tr>
            <tr>
                <td><code>_ph($var)</code> / <code>_rh($var)</code></td>
                <td>Rich HTML content</td>
                <td>Malicious scripts, iframes, JS events</td>
            </tr>
            <tr>
                <td><code>_pjs($var)</code></td>
                <td>Values in JavaScript</td>
                <td>JavaScript Injection</td>
            </tr>
            <tr>
                <td><code>_pt($var)</code> / <code>_rt($var)</code></td>
                <td>Text to be translated</td>
                <td>XSS + Translation</td>
            </tr>
            <tr>
                <td><code>_raz($var)</code> / <code>_paz($var)</code></td>
                <td>IDs, filenames, references</td>
                <td>Non-alphanumeric characters</td>
            </tr>
            <tr>
                <td><code>_absint($var)</code></td>
                <td>Numeric IDs, quantities</td>
                <td>Non-numeric or negative values</td>
            </tr>
        </tbody>
    </table>

    <h2>Special Cases and Warnings</h2>

    <div class="alert alert-info">
        <h4>Sanitization in loops</h4>
        <p>When working with arrays or loops, sanitize each element:</p>
        <pre class="border p-2 bg-light"><code class="language-php"><?php ob_start(); ?>
// CORRECT:
foreach($users as $user) {
    echo '<li>' . _r($user['name']) . '</li>';
}

// WRONG:
foreach($users as $user) {
    echo '<li>' . $user['name'] . '</li>'; // Not sanitized!
}
<?php _p(ob_get_clean()); ?></code></pre>
    </div>

    <div class="alert alert-warning">
        <h4>Don't apply sanitization multiple times</h4>
        <p>Applying sanitization functions multiple times can cause "double encoding" and display characters like <code>&amp;amp;lt;</code> instead of <code>&lt;</code>:</p>
        <pre class="border p-2 bg-light"><code class="language-php"><?php ob_start(); ?>
// CORRECT:
$safe = _r($input);
echo $safe;

// WRONG (double sanitization):
$safe = _r($input);
echo _r($safe); // Double encoding!
<?php _p(ob_get_clean()); ?></code></pre>
    </div>

    <div class="alert alert-danger">
        <h4>Never concatenate unsanitized strings!</h4>
        <p>Always sanitize before concatenating:</p>
        <pre class="border p-2 bg-light"><code class="language-php"><?php ob_start(); ?>
// CORRECT:
echo "Welcome " . _r($userName);

// WRONG:
echo "Welcome " . $userName; // Dangerous!
<?php _p(ob_get_clean()); ?></code></pre>
    </div>

    <h2>Summary</h2>

    <ol>
        <li><strong>ALWAYS</strong> sanitize data before displaying it</li>
        <li>Choose the appropriate function based on context</li>
        <li>Use _pjs() for JavaScript - it's the only safe way</li>
        <li>Don't sanitize the same data multiple times, the best practice is to sanitize when you print.</li>
        <li>Don't sanitize data when saving it to the database</li>
    </ol>
</div>