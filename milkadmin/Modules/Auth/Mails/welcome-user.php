<?php
use App\Config;
!defined('MILK_DIR') && die(); // Avoid direct access
$user = is_object($user ?? null) ? $user : (object) ['username' => ''];
$url = isset($url) ? (string) $url : '';
$mail = (isset($this) && isset($this->mail)) ? $this->mail : null;
if ($mail === null) {
    return;
}
ob_start();
// Here is the email content
?>
Hello <?php echo $user->username; ?>,

You have been registered on the website <?php echo Config::get('site-title', 'Milk Admin'); ?>.

To set your password, please click the following link:
<a href="<?php echo $url; ?>">Set Password</a>

<?php

$mail->Body = ob_get_clean();
$mail->AltBody = strip_tags((string) $mail->Body);
// Here is the email subject
$mail->Subject = 'Welcome to ' . Config::get('site-title', 'Milk Admin');

// You can manage other parameters this->mail is an instance of PHPMailer
