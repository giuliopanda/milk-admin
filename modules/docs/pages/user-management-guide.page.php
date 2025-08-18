<?php
namespace Modules\docs;
/**
 * @title User Management Guide
 * @category System Administration
 * @order 5
 * @tags user-management, users, admin, backend, administration, email, password, permissions
 */

use MilkCore\Route;
use MilkCore\Theme;

!defined('MILK_DIR') && die(); // Avoid direct access

Theme::set('header.breadcrumbs', '<a class="link-action" href="'.Route::url('?page=auth&action=user-list').'">User List</a> Help');
?>

<div class="bg-white p-4">
    <h1>User Management Guide</h1>
    <p>This guide explains how to manage users from the administration panel. This documentation focuses on the backend administration interface, not the programming aspects.</p>
    
    <div class="alert alert-info mb-4">
        <strong>Quick Access:</strong> <a href="<?php echo Route::url('?page=auth&action=user-list'); ?>" class="link-action">Go to User List</a> | 
        <a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/modules-auth.page'); ?>" class="link-action">Technical Documentation</a>
    </div>

    <h2>Important System Characteristics</h2>
    
    <div class="alert alert-warning">
        <h5><i class="bi bi-exclamation-triangle"></i> Key System Behavior</h5>
        <ul>
            <li><strong>Username Uniqueness:</strong> Only usernames must be unique. Multiple users can have the same email address.</li>
            <li><strong>Email Login Limitation:</strong> Since multiple users can share the same email, login via email is not supported. Users must login with their username.</li>
            <li><strong>Deletion Process:</strong> Deleted users are moved to "Trash" and only permanently deleted if removed from trash.</li>
        </ul>
    </div>

    <h2>Accessing User Management</h2>
    <p>To manage users, navigate to <strong>Users</strong> in the main menu or access directly: 
    <a href="<?php echo Route::url('?page=auth&action=user-list'); ?>" class="link-action">User List</a></p>

    <h2>User Status Filters</h2>
    <p>The user list provides several status filters to organize users:</p>
    <ul>
        <li><strong>All:</strong> Shows all users regardless of status</li>
        <li><strong>Active:</strong> Users with status = 1 (can login and use the system)</li>
        <li><strong>Suspended:</strong> Users with status = 0 (cannot login)</li>
        <li><strong>Trash:</strong> Users with status = -1 (marked for deletion)</li>
    </ul>

    <h2>Creating New Users</h2>
    
    <h3>Basic User Creation</h3>
    <ol>
        <li>Click "Add New" button on the user list page</li>
        <li>Fill in the required fields:
            <ul>
                <li><strong>Username:</strong> Must be unique (this is what users will login with)</li>
                <li><strong>Email:</strong> Can be shared by multiple users</li>
                <li><strong>Password:</strong> Optional (see welcome email section below)</li>
                <li><strong>Status:</strong> Active (1) or Suspended (0)</li>
                <li><strong>Administrator:</strong> Check if user should have admin privileges</li>
            </ul>
        </li>
        <li>Configure permissions if not an administrator</li>
        <li>Choose email options (see below)</li>
        <li>Click "Save"</li>
    </ol>

    <h3>Welcome Email vs Manual Password Setting</h3>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5>Send Welcome Email (Recommended)</h5>
                </div>
                <div class="card-body">
                    <p><strong>When to use:</strong> For new user accounts</p>
                    <p><strong>How it works:</strong></p>
                    <ul>
                        <li>Check "Send welcome email" option</li>
                        <li>Leave password field empty</li>
                        <li>System sends email with activation link</li>
                        <li>User clicks link to set their own password</li>
                    </ul>
                    <p><strong>Benefits:</strong> Secure, user-controlled password, email verification</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5>Manual Password Setting</h5>
                </div>
                <div class="card-body">
                    <p><strong>When to use:</strong> For immediate access or internal testing</p>
                    <p><strong>How it works:</strong></p>
                    <ul>
                        <li>Enter password in password field</li>
                        <li>Leave "Send welcome email" unchecked</li>
                        <li>User can immediately login</li>
                        <li>No email is sent</li>
                    </ul>
                    <p><strong>Note:</strong> You must communicate the password to the user separately</p>
                </div>
            </div>
        </div>
    </div>

    <h2>Editing Existing Users</h2>
    
    <h3>Basic User Editing</h3>
    <p>Click the "Edit" action next to any user in the list to modify their details:</p>
    <ul>
        <li>Change username, email, or status</li>
        <li>Modify permissions</li>
        <li>Promote/demote admin status</li>
    </ul>

    <h3>Password Reset via Admin Panel</h3>
    <p>To reset a user's password:</p>
    <ol>
        <li>Edit the user</li>
        <li>Choose one of two methods:
            <div class="mt-2">
                <div class="alert alert-info">
                    <strong>Email Reset (Recommended):</strong>
                    <ul>
                        <li>Check "Send Email to Reset Password"</li>
                        <li>Leave password field empty</li>
                        <li>User receives email with reset link</li>
                        <li>User sets new password securely</li>
                    </ul>
                </div>
                <div class="alert alert-warning">
                    <strong>Manual Password Reset:</strong>
                    <ul>
                        <li>Enter new password in password field</li>
                        <li>Leave "Send Email to Reset Password" unchecked</li>
                        <li>Password is changed immediately</li>
                        <li>No email notification sent</li>
                    </ul>
                </div>
            </div>
        </li>
        <li>Click "Save"</li>
    </ol>

    <h2>User Deletion Process</h2>
    
    <div class="alert alert-danger">
        <h5><i class="bi bi-exclamation-triangle"></i> Two-Stage Deletion Process</h5>
        <p>This system uses a two-stage deletion process for user safety:</p>
    </div>

    <h3>Stage 1: Move to Trash</h3>
    <ul>
        <li>Click "Trash" button when editing a user</li>
        <li>User status changes to -1 (Trash)</li>
        <li>User cannot login but data is preserved</li>
        <li>User appears in "Trash" filter</li>
        <li>Can be restored at any time</li>
    </ul>

    <h3>Stage 2: Permanent Deletion</h3>
    <ul>
        <li>Go to Trash filter to see deleted users</li>
        <li>Click "Definitely Delete" on trashed user</li>
        <li>User is permanently removed from database</li>
        <li><strong>This action cannot be undone!</strong></li>
    </ul>

    <h3>Restoring Users from Trash</h3>
    <p>To restore a user from trash:</p>
    <ol>
        <li>Go to User List and click "Trash" filter</li>
        <li>Select users to restore</li>
        <li>Use bulk action "Restore" or edit individual user and change status to Active</li>
    </ol>

    <h2>Bulk Actions</h2>
    
    <p>The user list supports bulk operations for efficiency:</p>

    <h3>Available Bulk Actions</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Action</th>
                <th>Available For</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Trash</strong></td>
                <td>Active/Suspended users</td>
                <td>Move selected users to trash (soft delete)</td>
            </tr>
            <tr>
                <td><strong>status Active</strong></td>
                <td>Any non-deleted users</td>
                <td>Set status to 1 (activate users)</td>
            </tr>
            <tr>
                <td><strong>status Suspended</strong></td>
                <td>Any non-deleted users</td>
                <td>Set status to 0 (suspend users)</td>
            </tr>
            <tr>
                <td><strong>Restore</strong></td>
                <td>Trash users only</td>
                <td>Restore users from trash to active status</td>
            </tr>
            <tr>
                <td><strong>Delete</strong></td>
                <td>Trash users only</td>
                <td>Permanently delete users (cannot be undone)</td>
            </tr>
        </tbody>
    </table>

    <h3>Using Bulk Actions</h3>
    <ol>
        <li>Select users using checkboxes</li>
        <li>Choose action from dropdown</li>
        <li>Click "Apply" button</li>
        <li>Confirm action when prompted</li>
    </ol>

    <h2>User Permissions</h2>
    
    <h3>Administrator vs Regular Users</h3>
    <ul>
        <li><strong>Administrator:</strong> Full access to all system features</li>
        <li><strong>Regular Users:</strong> Access controlled by permission system</li>
    </ul>

    <h3>Permission Management</h3>
    <p>When creating or editing non-admin users:</p>
    <ol>
        <li>Uncheck "Administrator" option</li>
        <li>Configure specific permissions in the permissions section</li>
        <li>Permissions are organized by modules and functions</li>
        <li>Grant only necessary permissions following principle of least privilege</li>
    </ol>

    <h2>Search and Filtering</h2>
    
    <h3>User Search</h3>
    <p>Use the search box to find users by:</p>
    <ul>
        <li>Username</li>
        <li>Email address</li>
        <li>User ID</li>
    </ul>

    <h3>Status Filtering</h3>
    <p>Click status filters to view specific user groups:</p>
    <ul>
        <li><strong>All:</strong> Complete user list</li>
        <li><strong>Active:</strong> Users who can login</li>
        <li><strong>Suspended:</strong> Users who cannot login</li>
        <li><strong>Trash:</strong> Users marked for deletion</li>
    </ul>

    <h2>Email System Behavior</h2>
    
    <div class="alert alert-info">
        <h5><i class="bi bi-envelope"></i> Email Automation Rules</h5>
        <ul>
            <li><strong>Welcome emails:</strong> Sent only when "Send welcome email" is checked during user creation</li>
            <li><strong>Password reset emails:</strong> Sent only when "Send Email to Reset Password" is checked during editing</li>
            <li><strong>Manual password setting:</strong> Never triggers automatic emails</li>
            <li><strong>Status changes:</strong> No automatic email notifications</li>
        </ul>
    </div>

    <h3>Email Templates</h3>
    <p>The system uses predefined email templates for:</p>
    <ul>
        <li>Welcome emails (new user account activation)</li>
        <li>Password reset emails (admin-initiated)</li>
        <li>User-initiated password reset (forgot password)</li>
    </ul>

    <h2>Security Considerations</h2>
    
    <div class="alert alert-warning">
        <h5><i class="bi bi-shield-exclamation"></i> Important Security Notes</h5>
        <ul>
            <li><strong>Username Policy:</strong> Usernames must be unique - this is enforced by the system</li>
            <li><strong>Email Policy:</strong> Multiple users can share the same email address</li>
            <li><strong>Login Method:</strong> Users must login with username, not email</li>
            <li><strong>Admin Privileges:</strong> Be careful when granting administrator access</li>
            <li><strong>Soft Deletion:</strong> Use trash system to prevent accidental data loss</li>
            <li><strong>Password Security:</strong> Prefer email-based password setting over manual entry</li>
        </ul>
    </div>

    <h2>Common Administrative Tasks</h2>
    
    <h3>Creating a New Administrator</h3>
    <ol>
        <li>Create new user with unique username</li>
        <li>Check "Administrator" option</li>
        <li>Use "Send welcome email" for secure password setup</li>
        <li>Verify admin can login before removing other admin accounts</li>
    </ol>

    <h3>Handling Locked Out Users</h3>
    <ol>
        <li>Check user status (may be suspended)</li>
        <li>Reset password using email method</li>
        <li>Change status to Active if needed</li>
        <li>Inform user to check their email</li>
    </ol>

    <h3>User Account Cleanup</h3>
    <ol>
        <li>Identify inactive users</li>
        <li>Move to trash (soft delete)</li>
        <li>Review trashed users periodically</li>
        <li>Permanently delete only when certain data is no longer needed</li>
    </ol>

    <h2>Troubleshooting</h2>
    
    <h3>Common Issues</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Problem</th>
                <th>Possible Cause</th>
                <th>Solution</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Cannot create user with existing username</td>
                <td>Username already exists</td>
                <td>Choose a different username (usernames must be unique)</td>
            </tr>
            <tr>
                <td>User cannot login with email</td>
                <td>System doesn't support email login</td>
                <td>User must login with username instead</td>
            </tr>
            <tr>
                <td>Welcome email not sent</td>
                <td>"Send welcome email" not checked</td>
                <td>Edit user and use password reset email option</td>
            </tr>
            <tr>
                <td>User disappeared from list</td>
                <td>User may be in trash</td>
                <td>Check "Trash" filter and restore if needed</td>
            </tr>
            <tr>
                <td>Cannot delete user permanently</td>
                <td>User not in trash yet</td>
                <td>Move to trash first, then permanently delete from trash view</td>
            </tr>
        </tbody>
    </table>

    <div class="mt-4 p-3 bg-light border-start border-primary border-5">
        <h5>Need Technical Details?</h5>
        <p>For programming and API documentation, see: <a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/modules-auth.page'); ?>" class="link-action">Auth Class Documentation</a></p>
    </div>
</div>