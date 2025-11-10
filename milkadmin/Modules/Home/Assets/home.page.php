<?php
namespace Modules\Home\Assets;

use App\{Config, Get, Permissions, Route};

$phpVersion = phpversion();
$milkVersion = Config::get('version', '1.0.0');
$newVersion = defined('NEW_VERSION') ? NEW_VERSION : null;

!defined('MILK_DIR') && die(); // Avoid direct access

// Get database information
$dbInfo = [];

$dbs = ['db' =>  'db1', 'db2' => 'db2'];

foreach ($dbs as $dbKey => $db) {
    if ($db == 'db1') {
        $db = Get::db();
    } else {
        $db = Get::db2();
    }
    if ($db) {
        $dbVersion = '';
        $dbServerVersion = '';
        
        if ($db->type == 'mysql') {
            // Get MySQL/MariaDB version and type
            $version = $db->getRow('SELECT VERSION() as version');
            $versionStr = $version ? $version->version : 'Unknown';
            $isMariaDB = stripos($versionStr, 'mariadb') !== false;
            $dbServerVersion = $isMariaDB ? 'MariaDB ' : 'MySQL ';
            $dbServerVersion .= $versionStr;
            
            // Get storage engine information
            $storageEngine = $db->getRow("SHOW VARIABLES LIKE 'storage_engine'");
            $dbVersion = $storageEngine ? $storageEngine->Value : 'Unknown';
        } else {
            // SQLite version
            $version = $db->getRow('SELECT sqlite_version() as version');
            $dbServerVersion = $version ? 'SQLite ' . $version->version : 'SQLite (Unknown Version)';
            $dbVersion = 'SQLite';
        }
        
        $dbInfo[$dbKey] = [
            'type' => $db->type,
            'version' => $dbVersion,
            'server_version' => $dbServerVersion,
            'name' => $db->dbname ?? 'N/A'
        ];
    }
}
?>

<?php if (Permissions::check('_user.is_admin')) : ?>
    <!-- Due Box in Colonne -->
    <div class="row">
        <!-- Box "Lo Sapevi" -->
        <div class="col-md-6">
            <div class="card">
                <?php _pt($main_article); ?>    
            </div>
        </div>
        
        <!-- Box Informazioni Installazione -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header info-box">
                    <h3 class="mb-0">
                        <i class="fas fa-server me-2"></i>
                        <?php _pt('Installation Information'); ?>
                    </h3>
                </div>
                
                <div class="card-body">
                    <?php
                    $phpVersion = phpversion();
                    $milkVersion = Config::get('version', '1.0.0');
                    $newVersion = defined('NEW_VERSION') ? NEW_VERSION : null;
                    ?>
                    
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <strong><?php _pt('PHP Version'); ?>:</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="badge bg-primary"><?php echo htmlspecialchars($phpVersion); ?></span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <strong><?php _pt('Web Server'); ?>:</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-dark"><?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <strong><?php _pt('Milk Admin Version'); ?>:</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-dark">
                                <?php echo htmlspecialchars($milkVersion); ?>
                                <?php if ($newVersion && version_compare($newVersion, $milkVersion, '>')): ?>
                                    <span class="badge bg-warning ms-2"><?php _pt('Update available'); ?>: <?php echo htmlspecialchars($newVersion); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <h5><?php _pt('Database'); ?></h5>
                    <?php $dbInfo = array_unique($dbInfo, SORT_REGULAR); ?>
                    <?php foreach ($dbInfo as $dbKey => $dbData): ?>
                    <div class="row mb-3">
                        <div class="col-sm-12">
                            <div class="ms-3">
                                <div class="row mb-2">
                                    <div class="col-sm-5"><?php _pt('Server Version'); ?>:</div>
                                    <div class="col-sm-7"><?php echo htmlspecialchars($dbData['server_version']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-sm-5"><?php _pt('Database Name'); ?>:</div>
                                    <div class="col-sm-7"><?php echo htmlspecialchars($dbData['name']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="mb-3">
                    <a href="<?php echo Route::url('?page=install'); ?>" class="link-action"><?php _pt('Install a new version'); ?></a>
                    </div>
                </div>

            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <?php _pt($main_article); ?>    
    </div>
<?php endif; ?>
