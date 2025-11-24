<?php 
namespace Modules\Auth\Views;

use App\{Sanitize, Token};
use Theme\Template;

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="center-login">
    <div class="text-center mb-3">
        <?php Template::getLogo(); ?>
    </div>
    <?php
    if ($msg_error != '') :?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <?php echo \App\Sanitize::html($msg_error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php 
    elseif ($success == true) :?>
        <div class="card">
        
            <div class="card-body">
                <div class="alert alert-info fade show" role="alert">
                    <p><?php _pt('If the username you entered is from a registered user, an e-mail with the instructions to reset password has been sent.');  ?></p>
                </div>
                <a href="<?php echo \App\Route::url(['page'=>'auth','action'=>'login']); ?>"><?php _pt('Return to Login'); ?></a>
            </div>
        
        </div>
        <?php return;
    endif; ?>
   
    <div class="card">
        <div class="card-header text-bg-secondary">
            <div class="d-flex">
                <div class="p-1 flex-shrink-1">
                    <i class="bi bi-key-fill" style="font-size:2rem; line-height: 1rem;"></i>
                </div> 
                <div class="p-1 w-100 align-content-center"><?php _pt('PASSWORD RESET'); ?></div>
            </div>
        </div>
        <div class="card-body">
            <p><?php _pt('Insert your username to receive the instructions to reset your password.'); ?></p>
            <form class="js-needs-validation" novalidate method="post">
                <input type="hidden" name="page" value="auth">
                <input type="hidden" name="action" value="forgot_password">
                <?php echo \App\Token::input('forgot_password'); ?>
                <div class="mb-3">
                    <div class="form-floating">
                        <input type="text" name="username" class="form-control" id="floatingInput" placeholder="usernames" required>
                        <label for="floatingInput"><?php _pt('Username'); ?></label>
                    </div>
                </div>
                <button class="btn btn-primary w-100 py-2" type="submit">
                    <i class="bi bi-envelope-fill"></i>
                    <span  style="margin-left: 5px; display: inline-block;"><?php _pt('Send password reset email'); ?></span>
                </button>
            </form>
        </div>
    </div>
    <a href="<?php echo \App\Route::url(['page'=>'auth','action'=>'login']); ?>"><?php _pt('Login'); ?></a>
</div>