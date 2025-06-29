<?php 
namespace Modules\Auth;
use MilkCore\Route;
use MilkCore\Token;
use Theme\Template;


!defined('MILK_DIR') && die(); // Avoid direct access

?>
<div class="center-login">
    <div class="text-center mb-3">
        <?php Template::get_logo(); ?>
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
   
    <?php //AuthService::tmpl_title(); ?>
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
                <button class="btn btn-primary w-100 py-2" type="submit">Sign in</button>
            </form>
        </div>
    </div>
    <a href="<?php _ph(Route::url(['page'=>'auth','action'=>'forgot_password'])); ?>">Forgot your password?</a>
</div>