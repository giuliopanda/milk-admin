<?php
use App\Config;

!defined('MILK_DIR') && die(); // Avoid direct access
ob_start();
// Here is the email content
?>
Ciao <?php echo $user->username; ?>,

You have received this password reset link from the administrator of <?php echo Config::get('site-title', 'Milk Admin'); ?>:  
<a href="<?php echo $url; ?>">Reset Password</a>
<?php

$this->mail->Body    = ob_get_clean();
$this->mail->AltBody = strip_tags($this->mail->Body);
// Here is the email subject
$this->mail->Subject = 'Password Reset';

// You can manage other parameters this->mail is an instance of PHPMailer

