<?php
namespace Modules\Deny;

use App\Route;

!defined('MILK_DIR') && die(); // Avoid direct access

?>
<div style="margin:0 auto; text-align:center">
<div class="alert alert-danger" role="alert"><?php _p('You do not have permission to access the page'); ?></div>
    <a href="<?php echo Route::url(); ?>"><?php _p('Back to home'); ?></a>
</div>