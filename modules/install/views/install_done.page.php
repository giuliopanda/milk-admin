<?php
namespace Modules\Install;
use MilkCore\Route;
use MilkCore\Hooks;
use Theme\Template;
use MilkCore\MessagesHandler;
if (!defined('MILK_DIR')) die();
?>
<div class="text-center m-3">
    <?php Template::get_logo(); ?>
</div>
<div class="bg-white p-4 " style="width: 48rem; margin:2rem auto">
    <h1><?php _pt('installation complete'); ?></h1>
    <p><?php _pt('Great, the installation is complete!');?>
    <?php _pt(MessagesHandler::get_error_alert()); ?>
    <br>
    <?php _pt('You can now log in with the following credentials:'); ?></p>
    <p><?php _ph('<b>User:</b> admin'); ?><br>
    <?php _ph('<b>Password:</b> admin'); ?></p>
    <p><a href="<?php _p(Route::url('?page=auth&action=login'))?>" class="btn btn-primary"><?php _pt('Go to the login page'); ?></a></p>
    <?php  _pt(Hooks::run('install.done', '')); ?>
</div>