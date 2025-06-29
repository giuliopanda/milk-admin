<?php
namespace Modules\Jobs;
!defined('MILK_DIR') && die(); // Avoid direct access
ob_start();
// Contenuto dell'email
?>
<h1>Error Job</h1>
<p>Date: <?php echo date('Y-m-d H:i:s'); ?></p>
<p>Site Title: <?php echo $site_title; ?></p>
<p>Base URL: <?php echo $base_url; ?></p>
<hr>
<p>Message: <?php echo $message; ?></p>
<?php

$this->mail->Body    = ob_get_clean();
$this->mail->AltBody = strip_tags($this->mail->Body);
$this->mail->Subject = 'Error Job';
