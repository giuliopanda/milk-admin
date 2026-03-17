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
Ciao <?php echo $user->username; ?>,

You have received this password reset link from the administrator of <?php echo Config::get('site-title', 'Milk Admin'); ?>:  
<a href="<?php echo $url; ?>">Reset Password</a>
<?php

$mail->Body = ob_get_clean();
$mail->AltBody = strip_tags((string) $mail->Body);
// Here is the email subject
$mail->Subject = 'Password Reset';

// You can manage other parameters this->mail is an instance of PHPMailer
