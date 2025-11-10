<?php
namespace Modules\Install\Views;

use App\{Hooks, MessagesHandler, Route};
use Theme\Template;

if (!defined('MILK_DIR')) die();
?>
<div class="text-center m-3">
    <?php Template::getLogo(); ?>
</div>
<div class="bg-white p-4 " style="width: 48rem; margin:2rem auto">
    <h1><?php _pt('installation complete'); ?></h1>

    <p><?php 
    if (MessagesHandler::hasErrors()) {
        _pt('Installation failed! Please check the error message below:');
        _pt(MessagesHandler::getErrorAlert());
    } else {
        _pt('Great, the installation is complete!');
    }
    ?>
    <br>
    <?php if (MessagesHandler::hasErrors()) : ?>
        <p><?php _pt('If you would like to continue, you can log in with the following credentials:'); ?></p>
    <?php else: ?>
        <p><?php _pt('You can now log in with the following credentials:'); ?></p>
    <?php endif; ?>
        <p><?php _ph('<b>User:</b> admin'); ?><br>
        <?php _ph('<b>Password:</b> admin'); ?></p>
        <p><a href="<?php _p(Route::url('?page=auth&action=login'))?>" class="btn btn-primary"><?php _pt('Go to the login page'); ?></a></p>
  
        <p><?php _pt('Please check the error message below:'); ?></p>
        <p><?php _pt(MessagesHandler::getErrorAlert()); ?></p>

    <?php  _pt(Hooks::run('install.done', '')); ?>
</div>