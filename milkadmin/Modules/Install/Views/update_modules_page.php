<?php
namespace Modules\Install\Views;

use App\{MessagesHandler, Route};

if (!defined('MILK_DIR')) die();
?>

<div class="bg-white p-4">
    <h1><?php _pt('Module Updates'); ?></h1>
    <?php MessagesHandler::displayMessages(); ?>
     <p><?php _pt('Download and test additional modules from %s', '<a href="https://www.milkadmin.org/download-modules/" target="_blank">https://www.milkadmin.org/download-modules/</a>'); ?></p> 
    <div class="mb-4">
        <?php echo $html; ?>
    </div>
    
    <div class="mt-4">
        <a href="<?php echo Route::url('?page=install'); ?>" class="link-action">
            <?php _pt('Install a new version'); ?>
        </a>
    </div>
</div>

