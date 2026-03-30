<?php
namespace Modules\Auth\Views;

use App\{Token, Route, Config, Hooks};
use Modules\Auth\RememberMeService;
use Theme\Template;

!defined('MILK_DIR') && die(); // Avoid direct access
$is_authenticated_in = (bool) ($is_authenticated_in ?? false);
$msg_error = isset($msg_error) ? (string) $msg_error : '';
$redirect = isset($redirect) ? (string) $redirect : '';
$username = isset($username) ? (string) $username : '';

?>
<style>
.mk-hp-field {
    position: absolute !important;
    left: -10000px !important;
    top: auto !important;
    width: 1px !important;
    height: 1px !important;
    overflow: hidden !important;
}
</style>
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
        <?php _pt($msg_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
   
    <?php //AuthService::tmplTitle(); ?>
    <div class="card">
        <?php Hooks::run('auth.login.before_form'); ?>
        <div class="card-header text-bg-primary">
            <div class="d-flex">
                <div class="p-1 flex-shrink-1">
                    <i class="bi bi-person-circle" style="font-size:2rem; line-height: 1rem;"></i>
                </div> 
                <div class="p-1 w-100 align-content-center"><?php _pt('LOGIN'); ?></div>
            </div>
        </div>
        <div class="card-body">
          
            <form class="js-needs-validation" novalidate method="post">
                <input type="hidden" name="page" value="auth">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="redirect" value="<?php _p($redirect); ?>">
                <?php echo Token::input('login'); ?>
                <div class="mk-hp-field" aria-hidden="true">
                    <label for="username_contact"><?php _pt('Username'); ?></label>
                    <input type="text" name="username_contact" id="username_contact" tabindex="-1" autocomplete="off">
                </div>
                <div class="mk-hp-field" aria-hidden="true">
                    <label for="password_repeat"><?php _pt('Password'); ?></label>
                    <input type="text" name="password_repeat" id="password_repeat" tabindex="-1" autocomplete="off">
                </div>
                <div class="mb-3">
                <div class="form-floating">
                        <input type="text" name="username" class="form-control" id="floatingUserName" placeholder="Password" value="<?php _p($username); ?>" required>
                        <label for="floatingUserName"><?php _pt('Username'); ?></label>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-floating">
                        <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Password" required>
                        <label for="floatingPassword"><?php _pt('Password'); ?></label>
                    </div>
                </div>
                <?php if (RememberMeService::isAvailable()): ?>
                <div class="form-check mb-3">
                    <input type="checkbox" name="remember_me" id="remember_me" class="form-check-input" value="1">
                    <label for="remember_me" class="form-check-label">
                        <?php $n = Config::get('auth_remember_me_duration'); ?>
                        <?php _pt('Stay signed in for %s', $n . ' ' . ($n == 1 ? _rt('day') : _rt('days'))); ?>
                    </label>
                </div>
                <?php endif; ?>
                <button class="btn btn-primary w-100 py-2" type="submit"><?php _pt('Sign in'); ?></button>
            </form>
        </div>
    </div>
    <a href="<?php _ph(Route::url(['page'=>'auth','action'=>'forgot_password'])); ?>"><?php _pt('Forgot your password?'); ?></a>
    <?php Hooks::run('auth.login.after_form'); ?>
</div>
