<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title Permissions Class
 * @category Framework
 * @order 
 * @tags permissions, access-control, authorization, user-groups, permission-groups, exclusive-groups, security, role-management, user-permissions, access-levels, authentication, group-management
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
        <h1>Permissions Class Documentation</h1>
        <p>This documentation provides a detailed overview of the Permissions class and its functions.</p>

        <h2>Introduction</h2>
        <p>The Permissions class manages permissions within the system. It allows defining and verifying permissions for different users.</p>
        <p>By default, there is only one group <b>_user</b> which has two permissions assigned: is_admin and is_guest.<br> If is_guest is false, then the user is logged into the site. By default, if the auth module is not enabled, all users are administrators.</p>

        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
if (Permissions::check('_user.is_guest')) {
        // The user is a guest
}
if (Permissions::check('_user.is_authenticated')) {
        // The user is logged in
}
if (Permissions::check('_user.is_admin')) {
        // The user is an administrator
}</code></pre>
        
       
        <h2>Main Functions</h2>

        <h4>set($group, $permissions, $group_name = null, $exclusive = false)</h4>
        <p>Sets permissions for a group. If the group is exclusive, only one permission can be active at a time.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
Permissions::set('auth', [
    'manage' => 'User Management',
    'delete' => 'User Deletion'
]);
        </code></pre>

        <h4>get($group = '')</h4>
        <p>Returns permissions for a specific group or all permissions if no group is specified.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$all_permissions = Permissions::get();
$auth_permissions = Permissions::get('auth');
        </code></pre>

        <h4>set_group_title($group, $title)</h4>
        <p>Sets the title for a permission group. If the title is not set, it won't appear among the permissions to be set in the Auth module. The title can be set directly using the third parameter of set($group, $permissions, $group_name)</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
Permissions::set_group_title('auth', 'Authentication');
        </code></pre>

        <h4>get_group_title($group)</h4>
        <p>Returns the title of a permission group.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$auth_title = Permissions::get_group_title('auth');
        </code></pre>

        <h4>get_groups()</h4>
        <p>Returns the list of permission groups.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$groups = Permissions::get_groups();
        </code></pre>

        <h4>check($permission)</h4>
        <p>Checks if the user has permission for a specific action.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$has_permission = Permissions::check('auth.manage');
        </code></pre>

        <h4>check_json($permission)</h4>
        <p>Checks if the user has permission for a specific action and outputs a JSON response if the user does not have permission.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">Permissions::check_json('auth.manage');
// user has permission to manage users</code></pre>

        <h4>set_user_permissions($group, $permissions)</h4>
        <p>Sets permissions for a user. If the group is exclusive, only one permission can be active at a time.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
Permissions::set_user_permissions('auth', [
    'manage' => true,
    'delete' => false
]);
        </code></pre>

        <h2>Exclusive Groups Management</h2>
        
        <h4>set_exclusive_group($group, $exclusive = true)</h4>
        <p>Sets a group as exclusive or non-exclusive. If a group is exclusive, only one permission within the group can be active at a time. By default, groups are non-exclusive (allowing multiple active permissions simultaneously).</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Set a group as exclusive
Permissions::set_exclusive_group('access_level', true);

// Set a group as non-exclusive (default)
Permissions::set_exclusive_group('features', false);
        </code></pre>

        <h4>is_exclusive_group($group)</h4>
        <p>Checks if a group is exclusive.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
$is_exclusive = Permissions::is_exclusive_group('access_level');
        </code></pre>

        <h2>Usage Examples</h2>

        <h3>Configuring Groups with Exclusive Permissions</h3>
        <p>When a group is set as exclusive, only one permission can be active at a time. This is useful for roles or access levels.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Configure an exclusive permission group for access levels
Permissions::set_group_title('access_level', 'Access Level');
Permissions::set_exclusive_group('access_level', true);
Permissions::set('access_level', [
    'view' => 'View Only', 
    'edit' => 'Edit', 
    'manage' => 'Full Management'
]);

// Assign a specific access level to the user
// Only the first true permission will be considered active
Permissions::set_user_permissions('access_level', [
    'view' => false, 
    'edit' => true, 
    'manage' => true
]);
// Result: only 'edit' will be active, 'manage' will be disabled
        </code></pre>

        <h3>Configuring Groups with Non-Exclusive Permissions (Default)</h3>
        <p>By default, groups are non-exclusive, allowing multiple permissions to be active simultaneously. This is useful for features or modules.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Configure a non-exclusive permission group for features
Permissions::set_group_title('modules', 'Modules');
// No need to call set_exclusive_group since groups are non-exclusive by default
Permissions::set('modules', [
    'users' => 'User Management',
    'content' => 'Content Management',
    'settings' => 'Settings'
]);

// Assign multiple permissions to the user
Permissions::set_user_permissions('modules', [
    'users' => true,
    'content' => true,
    'settings' => false
]);
// Result: both 'users' and 'content' will be active, 'settings' will be disabled
        </code></pre>
</div>