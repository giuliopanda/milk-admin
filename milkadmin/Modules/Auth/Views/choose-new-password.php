<?php
namespace Modules\Auth\Views;

use App\{Route, Token};
use Modules\Auth\AuthService;

!defined('MILK_DIR') && die(); // Avoid direct access

?>
<div class="center-login">
    <?php
    if ($success == true) :?>
        <?php AuthService::tmplTitle(); ?>
        <div class="card">
            <div class="card-body">
                <div class="alert alert-info fade show" role="alert">
                    <p>Your password has been changed successfully.</p>
                </div>
                <a href="<?php echo Route::url(['page'=>'auth','action'=>'login']); ?>">Return to Login</a>
            </div>  
        </div>
        <?php return;
    elseif ($msg_error != '') :?>
        <?php AuthService::tmplTitle(); ?>
        <div class="card"> 
        <div class="card-body">
            <div class="alert alert-info fade show" role="alert">
                <p><?php _p($msg_error); ?></p>
            </div>
            <a href="<?php echo Route::url(['page'=>'auth','action'=>'login']); ?>">Return to Login</a>
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
                <div class="p-1 w-100 align-content-center">Choose new password</div>
            </div>
        </div>
        <div class="card-body">
            <p> Insert your new password and confirm it. </p>
            <form id="chooseNewPwdValidation" novalidate method="post">
                <input type="hidden" name="key" value="<?php echo $key; ?>">
                <input type="hidden" name="page" value="auth">
                <input type="hidden" name="action" value="new-password">
                <?php echo Token::input('new_password'); ?>
                <div class="mb-3">
                    <div class="form-floating">
                        <input name="password" type="password" class="form-control" id="newPassword" placeholder="Password" required>
                        <label for="floatingPassword">Password</label>
                        <div class="invalid-feedback">
                        Password must be at least 8 characters long, contain at least one uppercase letter, one lowercase letter, one number and one special character.
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-floating">
                        <input type="password" class="form-control" id="newPasswordConfirm" placeholder="Confirm Password">
                        <label for="floatingPassword">Repeat Password</label>
                        <div class="invalid-feedback">
                            Passwords do not match.
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary w-100 py-2" type="submit">
                    Save new password
                </button>
                
            </form>
        </div>
    </div>
    <a href="<?php echo Route::url(['page'=>'auth','action'=>'login']); ?>">Login</a>
</div>