<?php 
namespace Modules\Auth;
use MilkCore\Token;
use MilkCore\Route;
use MilkCore\Permissions;
use MilkCore\Hooks;

!defined('MILK_DIR') && die(); // Avoid direct access
$id = $_REQUEST['id'] ?? 0;
$user = $user ?? new \stdClass();
?>
<form id="editUserForm" class="js-needs-validation mb-3" novalidate method="post" action="<?php echo Route::url(); ?>">
    <input type="hidden" name="page" value="auth">
    <input type="hidden" name="action" value="save-user" id="actionUser">
    <input type="hidden" name="id" value="<?php echo _absint($id); ?>">
    <?php echo Token::input('edit-form-'._absint($id)); ?>
    <div class="mb-3">
        <div class="form-floating">
            <input type="text" name="username" class="form-control" id="floatingUserName" placeholder="Password" value="<?php _p($user->username); ?>" required>
            <label for="floatingUserName"><?php _p('Username'); ?></label>
            <div class="invalid-feedback">
                <?php _p('Please enter an unique username'); ?>
            </div>
        </div>
    </div>
    <div class="mb-3">
        <div class="form-floating">
            <input type="email" name="email" class="form-control" id="floatingUserEmail" placeholder="Password" value="<?php _p($user->email); ?>" required>
            <label for="floatingUserEmail"><?php _p('Email'); ?></label>
            <div class="invalid-feedback">
                <?php _p('Please enter a valid email address.'); ?>
            </div>
        </div>
    </div>


    <div class="mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" name="send_email" id="sendEmail">
            <label class="form-check-label" for="sendEmail">
                <?php echo ($id > 0) ? _p('Sand Email to Reset Password') : _p('Send welcome email') ; ?>
            </label>
        </div>
    </div>

    <div class="mb-3">
        <div class="form-floating">
            <input type="password" name="password" class="form-control" id="changePassword" placeholder="<?php echo ($user->id > 0) ? 'Change password' : 'Password'; ?>" <?php echo ($user->id > 0) ? '' : 'required'; ?>>
            <label for="changePassword"><?php echo ($user->id > 0) ? 'Change password' : 'Password'; ?></label>
        </div>
    </div>
  
    <div class="mb-3">
        <div class="form-floating">
            <select class="form-select" name="status" id="selectStatus">
                <?php foreach ([0=>_r('Suspended'), 1=>_r('Active')] as $key=>$val) : ?>
                    <option value="<?php echo _absint($key); ?>" <?php echo ($user->status == $key) ? 'selected' : ''; ?>><?php echo $val; ?></option>
                <?php endforeach; ?>
            </select>
            <label for="selectStatus"><?php _p('Status'); ?></label>
        </div>
    </div>
    
    <h5><?php _p('Permissions'); ?></h5>
    <?php 
    if ($current_user->is_admin == 1 && $id != $current_user->id) {
        ?>
       
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" name="is_admin" id="isAdmin" <?php echo  ($user->is_admin == 1) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="isAdmin">
                    <?php  _p('Super Administrator'); ?>
                </label>
            </div>
        </div>
       
        <?php 
    } else if ($current_user->is_admin == 1 && $id == $current_user->id) { ?>
        <div class="mb-3">
            <?php _p('You are Super Administrator'); ?>
        </div>
        <?php 
    } else { ?>
          <div class="mb-3">
            <?php if ($user->is_admin == 1) {
                _p('This user is Super Administrator');
            } else {
                _p('This user is not Super Administrator');
            } ?>
        </div>
    <?php } ?>
    <?php if (!($current_user->is_admin == 1 && $id == $current_user->id)) : ?>
        <div id="permissionsBlock">
            <hr>
            <?php
            $permissions = Hooks::run('active_custom_user_permissions');
            $groups = Permissions::get_groups(); 
            foreach ($groups as $group => $group_title) {
                $permissions = Permissions::get($group);
                ?>
                <div class="mb-3">
                    <?php 
                    $is_exclusive = Permissions::is_exclusive_group($group);
                    if (count($permissions) > 1) {
                        ?> 
                        <div class="d-flex align-items-center gap-2">
                            <b><?php _pt($group_title); ?></b>
                            <?php if ($is_exclusive): ?>
                                <span class="badge bg-gray text-dark" title="<?php _p('Only one permission can be active at a time'); ?>"><?php _p('Exclusive'); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                    ?>
                    <div class="row">
                        <?php 
                        if (count($permissions) == 1) {
                            ?> 
                            <div class="col-4">
                                <div class="d-flex align-items-center gap-2">
                                    <b><?php _pt($group_title); ?></b>
                                    <?php if ($is_exclusive): ?>
                                        <span class="badge bg-secondary-subtle text-dark" title="<?php _p('Only one permission can be active at a time'); ?>"><?php _p('Exclusive'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div> 
                            <?php
                        }
                        foreach ($permissions as $permission_name => $permission_title) : ?>
                            <div class="col-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input <?php _p($is_exclusive ? 'exclusive-permission' : ''); ?>" 
                                           type="checkbox" 
                                           role="switch" 
                                           value="1" 
                                           data-group="<?php _p($group); ?>" 
                                           name="permissions[<?php _p($group); ?>][<?php _p($permission_name); ?>]" 
                                           id="permission-<?php _p($group); ?>-<?php _p($permission_name); ?>" 
                                           <?php _p(($user->permissions[$group][$permission_name] ?? 0 == 1) ? 'checked' : ''); ?>>
                                    <label class="form-check form-check-label" for="permission-<?php _p($group); ?>-<?php _p($permission_name); ?>">
                                        <?php _pt($permission_title); ?>
                                    </label> 
                                </div>
                            </div>
                            <?php 
                        endforeach; ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    <?php endif; ?>
</form>

<hr>
<div class="mb-3">
    <div class="d-flex justify-content-between">
        <button class="btn btn-primary  py-2" type="submit" onclick="saveUser()"><?php _p('Save'); ?></button>

        <?php if ($id > 0 && $user->is_admin != 1 && $id != $current_user->id) : ?>
            <button class="btn btn-danger  py-2" type="submit" onclick="deleteUser(<?php ($user->status == -1) ? 'true' : 'false'; ?>)"><?php ($user->status == -1) ? _p('Definitely Delete') : _p('Trash'); ?></button>
        <?php endif; ?>
    </div>
</div>
