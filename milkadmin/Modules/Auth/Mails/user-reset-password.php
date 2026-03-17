<?php
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

You have requested to reset your password.  
To reset your password, please click the following link:  
<a href="<?php echo $url; ?>">Reset Password</a>

<?php

$mail->Body = ob_get_clean();
$mail->AltBody = strip_tags((string) $mail->Body);
// Here is the email subject
$mail->Subject = 'Reset Password';

// You can manage other parameters this->mail is an instance of PHPMailer
