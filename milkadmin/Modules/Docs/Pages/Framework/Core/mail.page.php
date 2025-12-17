<?php
namespace Modules\Docs\Pages;
/**
 * @title Mails
 * @guide framework
 * @order 
 * @tags send, mail, email, smtp, templates, PHPMailer, attachments 
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Mail Class</h1>
    
    <p>The Mail class is a wrapper for PHPMailer that simplifies sending emails using PHP templates.</p>

    <h2 class="mt-4">Main Usage</h2>
    <p>The main method for sending emails is through the use of templates:</p>
    
    <pre><code class="language-html language-php">&lt;?php
        // Send email with template
Get::mail()->loadTemplate(MILK_DIR.'/Modules/my_module/mails/email_template.php', [
    'name' => 'John Doe',
    'user' => $user_object,
    'url' => 'https://example.com/reset-password'
])->to('destinatario@example.com')->send();

// Error handling
if (Get::mail()->getLastError()) {
    echo Get::mail()->getLastError();
}?&gt;</code></pre>

    <h2 class="mt-4">Template Structure</h2>
    <p>Email templates must be built following this structure:</p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlentities("<?php\n!defined('MILK_DIR') && die(); // Avoid direct access\nob_start();\n// Email content\n?>\n<h1>Benvenuto <?php echo \$name; ?>!</h1>\n<p>Questo è un esempio di template email.</p>\n<p>Puoi utilizzare variabili PHP: <?php echo \$custom_var; ?></p>\n<?php\n\n\$this->mail->Body    = ob_get_clean();\n\$this->mail->AltBody = strip_tags(\$this->mail->Body);\n\$this->mail->Subject = 'Oggetto Email';"); ?>

<?php echo htmlentities("// Additional PHPMailer parameters (optional)\n\$this->mail->Priority = 1; // 1 = High, 3 = Normal, 5 = Low\n\$this->mail->CharSet = 'UTF-8';"); ?>
</code></pre>

    <h2 class="mt-4">Public Methods</h2>

    <h3 class="mt-4">loadTemplate($path, $vars = [])</h3>
    <p>Loads a PHP template for the email. Automatically searches in the milkadmin_local path before the original path.</p>
    <ul>
        <li><code>$path</code>: template file path</li>
        <li><code>$vars</code>: associative array of variables to pass to the template</li>
    </ul>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlentities("Get::mail()->loadTemplate('modules/user/mails/welcome.php', [\n    'username' => 'Mario Rossi',\n    'activation_link' => 'https://example.com/activate?token=123'\n]);"); ?></code></pre>

    <h3 class="mt-4">config($is_smtp, $username, $password, $host, $port, $smtpSecure)</h3>
    <p>Configures SMTP or mail() settings.</p>
    <ul>
        <li><code>$is_smtp</code>: boolean - use SMTP (true) or mail() (false)</li>
        <li><code>$username</code>: SMTP username</li>
        <li><code>$password</code>: SMTP password</li>
        <li><code>$host</code>: SMTP host</li>
        <li><code>$port</code>: SMTP port (default: 465)</li>
        <li><code>$smtpSecure</code>: encryption type (default: PHPMailer::ENCRYPTION_SMTPS)</li>
    </ul>

    <h3 class="mt-4">to(string $address, string $name = '')</h3>
    <p>Sets the email recipient. Supports multiple addresses separated by comma or semicolon.</p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlentities("Get::mail()->to('utente@example.com');\nGet::mail()->to('utente@example.com', 'Nome Utente');\nGet::mail()->to('utente1@example.com, utente2@example.com');"); ?></code></pre>

    <h3 class="mt-4">cc(string $address, string $name = '')</h3>
    <p>Adds carbon copy (CC) recipients.</p>

    <h3 class="mt-4">bcc(string $address, string $name = '')</h3>
    <p>Adds blind carbon copy (BCC) recipients.</p>

    <h3 class="mt-4">from($from, $from_name = '')</h3>
    <p>Sets the email sender.</p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlentities("\Get::mail()->from('from@example.com', 'Name');"); ?></code></pre>

    <h3 class="mt-4">replyTo($reply_to, $reply_to_name = '')</h3>
    <p>Sets the reply-to address.</p>

    <h3 class="mt-4">subject($subject)</h3>
    <p>Sets the email subject (not recommended with load_template, use in template instead).</p>

    <h3 class="mt-4">message($message)</h3>
    <p>Sets the email body (not recommended with load_template, use in template instead).</p>

    <h3 class="mt-4">isHTML($is_html = false)</h3>
    <p>Sets whether the email is HTML or plain text.</p>

    <h3 class="mt-4">addAttachment($path, $name = '')</h3>
    <p>Adds an attachment to the email.</p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Get::mail()->addAttachment('/path/to/file.pdf', 'document.pdf');</code></pre>

    <h3 class="mt-4">send()</h3>
    <p>Sends the email. Returns true if sending is successful, false otherwise.</p>

    <h3 class="mt-4">hasError()</h3>
    <p>Checks if there were any errors during sending.</p>

    <h3 class="mt-4">getError()</h3>
    <p>Returns the last error message.</p>

    <h2 class="mt-4">Practical Examples</h2>

    <h3 class="mt-4">Email with Attachment</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlentities("// Template: modules/reports/mails/monthly_report.php\n<?php\n!defined('MILK_DIR') && die();\nob_start();\n?>\n<p>Dear <?php echo \$recipient_name; ?>,</p>
<p>Please find attached the monthly report for <?php echo \$month; ?>.</p>
<p>Best regards,<br>The Team</p>\n<?php\n\$this->mail->Body = ob_get_clean();\n\$this->mail->AltBody = strip_tags(\$this->mail->Body);\n\$this->mail->Subject = 'Monthly Report - ' . \$month;\n\n// Send with attachment\n\$mail = Get::mail();\n\$mail->loadTemplate('modules/reports/mails/monthly_report.php', [\n    'recipient_name' => 'Manager',\n    'month' => 'October 2024'\n])\n->to('manager@example.com')\n->addAttachment('/path/to/report.pdf', 'Report_October_2024.pdf')\n->send();"); ?></code></pre>

    <h2 class="mt-4">Customizations System</h2>
    <p>The <code>load_template</code> function automatically searches for templates in the milkadmin_local directory first:</p>
    
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php"><?php echo htmlentities("// Original path:\n// modules/user/mails/welcome.php\n\n// Customizations path (has priority if exists):\n// milkadmin_local/Modules/user/mails/welcome.php"); ?></code></pre>

    <h2 class="mt-4">Best Practices</h2>
    <ul>
        <li>Always use <code>load_template</code> for emails with complex layouts</li>
        <li>Set Subject, Body and AltBody in the template itself</li>
        <li>Use output buffering (ob_start/ob_get_clean) to capture HTML</li>
        <li>Always generate a text version with strip_tags for AltBody</li>
        <li>Always handle errors with getLastError()</li>
    </ul>

    <h2 class="mt-4">Important Notes</h2>
    <ul>
        <li>Templates have access to the PHPMailer instance via <code>$this->mail</code></li>
        <li>Variables passed to load_template are available in the template</li>
        <li>After send() all recipients and attachments are automatically removed</li>
        <li>Methods can be chained for fluent syntax</li>
    </ul>
</div>