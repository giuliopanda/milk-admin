<?php
namespace Modules\Auth\Views;

use App\{Token, Route, Config};
use Theme\Template;

!defined('MILK_DIR') && die(); // Avoid direct access

?>
<div class="center-login">
    <div class="text-center mb-3">
        <?php Template::getLogo(); ?>
    </div>
    <?php
    if ($is_authenticated_in) {
        ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <?php _p('You are already logged in.'); ?>
        </div>
        <?php 
        return;
    }
    if ($msg_error != '') :?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <?php _p($msg_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
   
    <?php //AuthService::tmplTitle(); ?>
    <div class="card">
        <div class="card-header text-bg-primary">
            <div class="d-flex">
                <div class="p-1 flex-shrink-1">
                    <i class="bi bi-person-circle" style="font-size:2rem; line-height: 1rem;"></i>
                </div> 
                <div class="p-1 w-100 align-content-center">LOGIN</div>
            </div>
        </div>
        <div class="card-body">
            <form class="js-needs-validation" novalidate method="post">
                <input type="hidden" name="page" value="auth">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="redirect" value="<?php _p($redirect); ?>">
                <?php echo Token::input('login'); ?>
                <div class="mb-3">
                <div class="form-floating">
                        <input type="text" name="username" class="form-control" id="floatingUserName" placeholder="Password" value="<?php _p($username); ?>" required>
                        <label for="floatingUserName">Username</label>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-floating">
                        <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Password" required>
                        <label for="floatingPassword">Password</label>
                    </div>
                </div>
                <?php if (Config::get('auth_remember_me_duration')): ?>
                <div class="form-check mb-3">
                    <input type="checkbox" name="remember_me" id="remember_me" class="form-check-input" value="1">
                    <label for="remember_me" class="form-check-label">
                        <?php _p('Stay signed in for ' . Config::get('auth_remember_me_duration') . ' days'); ?>
                    </label>
                </div>
                <?php endif; ?>
                <button class="btn btn-primary w-100 py-2" type="submit">Sign in</button>
            </form>
        </div>
    </div>
    <a href="<?php _ph(Route::url(['page'=>'auth','action'=>'forgot_password'])); ?>">Forgot your password?</a>
</div>