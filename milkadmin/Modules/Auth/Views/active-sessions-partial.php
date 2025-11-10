<?php
namespace Modules\Auth\Views;

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<!-- Active Users Summary Card -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-people-fill me-2"></i>
                    Active Users
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($active_users_data)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        No active sessions or remember me tokens found.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th class="text-center">Active Sessions</th>
                                    <th class="text-center">Active Remember Me</th>
                                    <th>Last Activity</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_users_data as $user): ?>
                                    <tr id="user-row-<?php echo (int)$user['user_id']; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></strong>
                                        </td>
                                        <td>
                                            <?php if (!empty($user['email']) && $user['email'] !== $user['username']): ?>
                                                <small class="text-body-secondary"><?php echo htmlspecialchars($user['email']); ?></small>
                                            <?php else: ?>
                                                <small class="text-body-secondary">—</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($user['sessions_count'] > 0): ?>
                                                <span class="badge bg-success"><?php echo (int)$user['sessions_count']; ?></span>
                                            <?php else: ?>
                                                <span class="text-body-secondary">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($user['tokens_count'] > 0): ?>
                                                <span class="badge bg-info"><?php echo (int)$user['tokens_count']; ?></span>
                                            <?php else: ?>
                                                <span class="text-body-secondary">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($user['last_activity'])): ?>
                                                <small><?php echo htmlspecialchars($user['last_activity']); ?></small>
                                            <?php else: ?>
                                                <small class="text-body-secondary">—</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="?page=auth&action=logout-all-devices&user_id=<?php echo (int)$user['user_id']; ?>"
                                               data-fetch="post"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Are you sure you want to logout user \'<?php echo htmlspecialchars($user['username'] ?? ''); ?>\' from all devices?\n\nThis will:\n- Terminate all active sessions\n- Revoke all remember me tokens');"
                                               title="Logout from all devices">
                                                <i class="bi bi-box-arrow-right me-1"></i>
                                                Logout All Devices
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <small class="text-body-secondary">
                    <i class="bi bi-info-circle me-1"></i>
                    Showing users with active sessions or remember me tokens only
                </small>
            </div>
        </div>
    </div>
</div>
