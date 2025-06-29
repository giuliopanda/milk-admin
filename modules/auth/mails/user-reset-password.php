<?php
use MilkCore\Config;
!defined('MILK_DIR') && die(); // Avoid direct access
ob_start();
// Here is the email content
?>
Hello <?php echo $user->username; ?>,

You have requested to reset your password.  
To reset your password, please click the following link:  
<a href="<?php echo $url; ?>">Reset Password</a>

<?php

$this->mail->Body    = ob_get_clean();
$this->mail->AltBody = strip_tags($this->mail->Body);
// Here is the email subject
$this->mail->Subject = 'Reset Password';

// You can manage other parameters this->mail is an instance of PHPMailer

