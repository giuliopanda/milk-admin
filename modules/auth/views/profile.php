<?php 
namespace Modules\Auth;
use MilkCore\Get;
use MilkCore\Route;

!defined('MILK_DIR') && die(); // Avoid direct access

$user = Get::make('auth')->get_user();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h4><i class="bi bi-person-circle"></i> User Profile</h4>
                </div>
                <div class="card-body">
                    <form id="profileForm">
                        <input type="hidden" name="page" value="auth">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user->username); ?>" readonly>
                                    <div class="form-text">Username cannot be changed for security reasons.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user->email); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5>Change Password</h5>
                        <p class="text-muted">Leave password fields empty if you don't want to change your password.</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                    <div class="form-text">Required only if changing password.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                    <div class="form-text">Minimum 6 characters recommended.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <strong>Security Note:</strong> After changing your password, you will remain logged in on this device but will be logged out from all other devices.
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                <i class="bi bi-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" onclick="updateProfile()">
                                <i class="bi bi-check-lg"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5>Account Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>User ID:</strong> <?php echo $user->id; ?></p>
                            <p><strong>Account Status:</strong> 
                                <?php if ($user->status == 1): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Inactive</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Registration Date:</strong> <?php echo date('F j, Y', strtotime($user->registered)); ?></p>
                            <p><strong>Last Login:</strong> 
                                <?php if ($user->last_login): ?>
                                    <?php echo date('F j, Y g:i A', strtotime($user->last_login)); ?>
                                <?php else: ?>
                                    Never
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($user->is_admin == 1): ?>
                        <div class="alert alert-warning mt-3">
                            <i class="bi bi-shield-check"></i> <strong>Administrator Account:</strong> You have administrative privileges on this system.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateProfile() {
    const form = document.getElementById('profileForm');
    const formData = new FormData(form);
    
    // Validate password fields if new password is provided
    const newPassword = formData.get('new_password');
    const confirmPassword = formData.get('confirm_password');
    const currentPassword = formData.get('current_password');
    
    if (newPassword) {
        if (!currentPassword) {
            alert('Current password is required to change your password.');
            document.getElementById('current_password').focus();
            return;
        }
        
        if (newPassword !== confirmPassword) {
            alert('New password and confirm password do not match.');
            document.getElementById('confirm_password').focus();
            return;
        }
        
        if (newPassword.length < 6) {
            alert('New password must be at least 6 characters long.');
            document.getElementById('new_password').focus();
            return;
        }
    }
    
    // Convert FormData to URLSearchParams for POST request
    const params = new URLSearchParams();
    for (const [key, value] of formData.entries()) {
        params.append(key, value);
    }
    
    fetch('<?php echo Route::url(); ?>/', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Profile updated successfully!');
            if (newPassword) {
                alert('Password changed successfully. You will remain logged in on this device.');
            }
            // Clear password fields
            document.getElementById('current_password').value = '';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
        } else {
            alert('Error: ' + (data.msg || 'Failed to update profile'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating your profile.');
    });
}
</script>