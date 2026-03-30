<?php
namespace Modules\Docs\Pages;

use App\Route;

/**
 * @title UserRights Module Guide
 * @guide user
 * @order 7
 * @tags user-rights, roles, permissions, projects, administration, hooks, reports, pdf-export, advanced-permissions
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>UserRights: Advanced Project Permissions</h1>

    <p>
        <strong>UserRights</strong> is an optional module for advanced, project-based permission management.
        It is designed for scenarios where standard Auth permissions are not enough and you need granular access
        per project and per role.
    </p>

    <div class="alert alert-info">
        <strong>Important:</strong> UserRights is an additional module and is <strong>not installed by default</strong>.
        You can run the platform without it, but enabling it gives you much finer control over permissions.
    </div>

    <h2>What UserRights Does</h2>
    <ul>
        <li>Manages roles per project (<code>manifest.json -&gt; id</code>).</li>
        <li>Assigns users to one role per project.</li>
        <li>Stores permissions in nested JSON inside <code>#__project_roles</code>.</li>
        <li>Allows modules to inject extra custom permissions through hooks.</li>
    </ul>

    <p>
        Main UI entry point:
        <a href="<?php echo Route::url('?page=user-rights'); ?>" class="link-action">?page=user-rights</a>
    </p>

    <h2>When To Use It</h2>
    <p>Use UserRights when you need rules like:</p>
    <ul>
        <li>User can work only on project <code>9832</code>, not on other projects.</li>
        <li>User can view reports but cannot manage dataset settings.</li>
        <li>User can download PDF for one project but not for another.</li>
    </ul>

    <h2>How It Integrates With Projects</h2>
    <p>
        UserRights works on modules generated with the <strong>Projects</strong> extension. Permissions are resolved
        by project id, role assignment, and role permission JSON.
    </p>

    <h3>Permission Sources</h3>
    <ol>
        <li><strong>Standard Auth permissions</strong> (global, module-level).</li>
        <li><strong>Project-scoped permissions</strong> from UserRights role JSON.</li>
        <li><strong>Module hooks</strong> that add and evaluate custom project permissions.</li>
    </ol>

    <h2>How Modules Extend UserRights</h2>
    <p>
        UserRights exposes an extension mechanism so other modules can add custom project permissions without
        modifying UserRights core code.
    </p>

    <h3>1) Add Custom Permission Keys</h3>
    <p>
        Hook naming pattern:
        <code>&lt;module-page&gt;.additional-permissions</code>
    </p>

    <p>Example:</p>
    <pre><code>Hooks::set('classic-database.additional-permissions', function ($items, $modulePage, $projectId) {
    $items = is_array($items) ? $items : [];
    $items[] = ['key' =&gt; 'download_pdf', 'label' =&gt; 'Download PDF', 'default' =&gt; true];
    return $items;
});</code></pre>

    <h3>2) Check Custom Permissions At Runtime</h3>
    <p>
        At runtime, modules can ask UserRights if a project-scoped permission is granted.
        Common hook used by project modules:
        <code>projects.check_special_permission</code>.
    </p>

    <h2>Current Integrations</h2>

    <h3>Reports Module</h3>
    <p><code>Modules/Reports</code> injects and uses these custom permissions:</p>
    <ul>
        <li><code>reports_view</code>: user can open/view report dashboards for that project.</li>
        <li><code>reports_edit</code>: user can create/update/delete dashboards for that project.</li>
    </ul>

    <p>Runtime notes:</p>
    <ul>
        <li>If global Auth permissions (<code>reports.view</code>, <code>reports.manage</code>) are missing, Reports falls back to UserRights project permissions.</li>
        <li>Reports resolves project context also from <code>report_id</code> and <code>dashboard_id</code>, not only from <code>project_id</code>.</li>
        <li><code>reports_edit</code> enables dashboard editing flow; dataset/settings management remains tied to global manage permissions.</li>
    </ul>

    <h3>ProjectsPdfExport Module</h3>
    <p><code>Modules/ProjectsPdfExport</code> injects and uses:</p>
    <ul>
        <li><code>download_pdf</code>: user can use the <em>Download PDF</em> action for that project.</li>
    </ul>

    <p>
        If the role does not have this permission for the current project, the PDF export action is denied.
    </p>

    <h2>Practical Flow For Administrators</h2>
    <ol>
        <li>Install and enable UserRights module.</li>
        <li>Open UserRights and select the target project/module.</li>
        <li>Create role (for example: <code>report_editor</code>).</li>
        <li>Enable only required custom permissions (for example: <code>reports_view</code> and <code>reports_edit</code>).</li>
        <li>Assign users to the role.</li>
        <li>Test with a non-admin user account.</li>
    </ol>

    <h2>Why This Is Useful</h2>
    <ul>
        <li>Granular access per project instead of broad global permissions.</li>
        <li>Cleaner separation between "can view", "can edit dashboards", and "can manage full module".</li>
        <li>Extensible architecture: each module can declare its own additional permissions via hooks.</li>
    </ul>
</div>
