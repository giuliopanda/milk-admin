<?php
namespace Modules\Install;
use MilkCore\Route;
use MilkCore\MessagesHandler;
use MilkCore\Config;

if (!defined('MILK_DIR')) die();
?>

<div class="bg-white p-4">
    <h1><?php _pt('Module Updates'); ?></h1>
    <?php MessagesHandler::display_messages(); ?>
    
    <div class="mb-4">
        <?php echo $html; ?>
    </div>
    
    <div class="mt-4">
        <a href="<?php echo Route::url('?page=install'); ?>" class="link-action">
            <?php _pt('Install a new version'); ?>
        </a>
    </div>
</div>

