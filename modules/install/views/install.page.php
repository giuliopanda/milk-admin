<?php
namespace Modules\Install;
use Theme\Template;
if (!defined('MILK_DIR')) die();

// System requirements check
$requirements = [
    // Requisiti bloccanti
    'php_version' => [
        'required' => '8.0.0',
        'current' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'error' => 'PHP 8.0.0 or higher is required.',
        'blocking' => true
    ],
    'database' => [
        'required' => 'MySQLi or SQLite3',
        'current' => extension_loaded('mysqli') ? 'MySQLi enabled' : (extension_loaded('sqlite3') ? 'SQLite3 enabled' : 'None found'),
        'status' => extension_loaded('mysqli') || extension_loaded('sqlite3'),
        'error' => 'At least one database extension (MySQLi or SQLite3) is required.',
        'blocking' => true
    ],
    'json' => [
        'required' => 'Enabled',
        'current' => extension_loaded('json') ? 'Enabled' : 'Not enabled',
        'status' => extension_loaded('json'),
        'error' => 'JSON extension is required.',
        'blocking' => true
    ],
    
    // Informational only (non-blocking) requirements
    'memory_limit' => [
        'required' => '128M (suggested)',
        'current' => ini_get('memory_limit'),
        'status' => convertToBytes(ini_get('memory_limit')) >= convertToBytes('128M'),
        'error' => 'For better performance, consider increasing memory_limit to at least 128M',
        'blocking' => false
    ],
    'max_execution_time' => [
        'required' => '60 seconds (suggested)',
        'current' => ini_get('max_execution_time') . ' seconds',
        'status' => (int)ini_get('max_execution_time') >= 60 || ini_get('max_execution_time') == 0,
        'error' => 'For better performance, consider setting max_execution_time to at least 60 seconds or more',
        'blocking' => false
    ],
  
    'mysqli_extension' => [
        'required' => 'For MySQL database',
        'current' => extension_loaded('mysqli') ? 'Enabled' : 'Not enabled',
        'status' => true, // Sempre true perché non è bloccante
        'error' => 'MySQLi extension is required only if you plan to use MySQL database',
        'blocking' => false
    ],
    'sqlite3_extension' => [
        'required' => 'For SQLite database',
        'current' => extension_loaded('sqlite3') ? 'Enabled' : 'Not enabled',
        'status' => true, // Sempre true perché non è bloccante
        'error' => 'SQLite3 extension is required only if you plan to use SQLite database',
        'blocking' => false
    ],
    'mbstring' => [
        'required' => 'Suggested',
        'current' => extension_loaded('mbstring') ? 'Enabled' : 'Not enabled',
        'status' => extension_loaded('mbstring'),
        'error' => 'Multibyte String extension is recommended for better character encoding support',
        'blocking' => false
    ],
    'curl_extension' => [
        'required' => 'Enabled',
        'current' => extension_loaded('curl') ? 'Enabled' : 'Not enabled',
        'status' => extension_loaded('curl'),
        'error' => 'cURL extension is required.',
        'blocking' => true
    ]
];

// Helper function to convert memory string to bytes
function convertToBytes($memoryString) {
    $units = ['B' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824];
    $memoryString = trim($memoryString);
    $unit = strtoupper(substr($memoryString, -1));
    $value = (float)substr($memoryString, 0, -1);
    
    if (!isset($units[$unit])) {
        // No unit found, assume bytes
        return (int)$memoryString;
    }
    
    return (int)($value * $units[$unit]);
}

// Check directory permissions (unificato)
$directories = [
    'root' => [
        'path' => MILK_DIR,
        'name' => 'Root Directory',
        'blocking' => true
    ],
    'storage' => [
        'path' => MILK_DIR . '/storage',
        'name' => 'Storage Directory',
        'blocking' => true
    ],
    'modules' => [
        'path' => MILK_DIR . '/modules',
        'name' => 'Modules Directory',
        'blocking' => true
    ],
    'config' => [
        'path' => MILK_DIR . '/config.php',
        'name' => 'Config File',
        'blocking' => true
    ]
];

$permissions = [];
foreach ($directories as $key => $dir) {
    $exists = file_exists($dir['path']);
    $writable = $exists ? is_writable($dir['path']) : false;
    
    $permissions[$key] = [
        'name' => $dir['name'],
        'path' => $dir['path'],
        'exists' => $exists,
        'writable' => $writable,
        'status' => $exists && $writable,
        'blocking' => $dir['blocking'],
        'error' => !$exists ? 'Path does not exist' : (!$writable ? 'Not writable' : '')
    ];
}

// Check if there are any blocking requirements that failed
$blockingRequirementsFailed = false;
$showTableRequirements = false;
foreach ($requirements as $requirement) {
    if (!$requirement['status']) {
        $showTableRequirements = true;
    }
    if (isset($requirement['blocking']) && $requirement['blocking'] && !$requirement['status']) {
        $blockingRequirementsFailed = true;
        break;
    }
}

// Check if there are any blocking permissions that failed
$blockingPermissionsFailed = false;
$showTablePermissions = false;
foreach ($permissions as $permission) {
    if (!$permission['status']) {
        $showTablePermissions = true;
    }
    if ($permission['blocking'] && !$permission['status']) {
        $blockingPermissionsFailed = true;
        break;
    }
}

// Set overall status
$allRequirementsMet = !$blockingRequirementsFailed && !$blockingPermissionsFailed;
?>
<div class="text-center m-3">
    <?php Template::get_logo(); ?>
</div>
<div class="bg-white p-4 " style="width: 48rem; margin:2rem auto">
    <h1><?php _p('Welcome to Milk Admin,'); ?></h1>
    <p><?php _ph('the PHP administrative system designed to support developers\' work. It manages cron jobs, allows you to create and manage public APIs or APIs with JWT authentication, and handles user and permission management.<br>
Built with a Bootstrap template and a lightweight, easy-to-learn framework for creating your own independent systems.'); ?></p>
    
    <?php if ($showTableRequirements || $showTablePermissions): ?>
    <div class="mb-4">
        <h4>System Requirements Check</h4>
        
        <?php 
        // Filter out successful requirements
        $failedRequirements = array_filter($requirements, function($item) {
            return !$item['status'];
        });
        
        if (!empty($failedRequirements)): ?>
        <div class="mb-3">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Requirement</th>
                        <th>Current</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requirements as $name => $requirement): 
                        if (!$requirement['status']): 
                            $isBlocking = isset($requirement['blocking']) && $requirement['blocking'];
                            ?>
                            <tr class="<?php echo $isBlocking ? 'table-danger' : 'table-warning'; ?>">
                                <td>
                                    <strong><?php echo ucfirst(str_replace('_', ' ', $name)); ?></strong>
                                    <div class="text-muted small"><?php echo $requirement['required']; ?></div>
                                </td>
                                <td><?php echo $requirement['current']; ?></td>
                                <td>
                                    <?php if ($isBlocking): ?>
                                        <div class="text-danger">
                                            <?php echo $requirement['error']; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted">
                                            <?php echo $requirement['error']; ?>
                                            <div class="small mt-1">
                                                To update, edit your php.ini or contact your hosting provider.
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                        endif; 
                    endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php 
        // Filter out successful permissions
        $failedPermissions = array_filter($permissions, function($item) {
            return !$item['status'];
        });
        
        if (!empty($failedPermissions)): ?>
        <div class="mb-3">
            <h5>Directory Permissions</h5>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Directory/File</th>
                        <th>Path</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($failedPermissions as $name => $permission): ?>
                    <tr class="<?php echo $permission['blocking'] ? 'table-danger' : 'table-warning'; ?>">
                        <td><?php echo $permission['name']; ?></td>
                        <td><code><?php echo $permission['path']; ?></code></td>
                        <td>
                            <span class="badge <?php echo $permission['blocking'] ? 'bg-danger' : 'bg-warning'; ?>">
                                <?php echo $permission['error']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($blockingPermissionsFailed): ?>
            <div class="alert alert-info">
                <h6><i class="fas fa-terminal me-2"></i>Fix on Linux:</h6>
                <code class="d-block">sudo chown -R www-data:www-data <?php echo MILK_DIR; ?></code>
                <code class="d-block">sudo find <?php echo MILK_DIR; ?> -type d -exec chmod 755 {} \;</code>
                <code class="d-block">sudo find <?php echo MILK_DIR; ?> -type f -exec chmod 644 {} \;</code>
                <small class="text-muted">Replace 'www-data' with your web server user if different.</small>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($blockingRequirementsFailed || $blockingPermissionsFailed): ?>
            <div class="alert alert-danger">
                <h5>Installation cannot proceed</h5>
                <p>Please fix the required issues (marked in red) before continuing with the installation.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <form class="js-needs-validation" id="installForm" novalidate method="post" <?php echo !$allRequirementsMet ? 'style="opacity: 0.5; pointer-events: none;"' : ''; ?>>
        <?php 
        // eventuali script vanno caricati esternamente e questo dovrebbe essere sanitizzato
        echo $html;
         ?>
        <input type="hidden" name="page" value="install">
        <input type="hidden" name="action" value="save-config">
        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary btn-lg" <?php echo !$allRequirementsMet ? 'disabled' : ''; ?>>
                <i class="fas fa-download me-2"></i> Proceed with Installation
            </button>
        </div>
    </form>
</div>

