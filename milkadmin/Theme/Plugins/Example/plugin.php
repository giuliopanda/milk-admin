<?php
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Un bottone di esempio
 */
$alert = isset($alert) ? (string) $alert : '';
$hello = isset($hello) ? (string) $hello : '';
?>
<div class="js_example_btn"  data-alert="<?php _pt($alert); ?>">
    This is an example button:
    <div class="btn btn-primary js-btn"><?php _pt($hello); ?></div>
</div>
