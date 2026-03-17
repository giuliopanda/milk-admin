<?php
namespace Modules\Install\Installer;

use App\Database\MySql;
use App\{Config, Form, Get, Hooks};
use Modules\Install\Install;

!defined('MILK_DIR') && die(); // Avoid direct access

Hooks::set('install.get_html_modules', function($html, $errors) {
    $errors_mysql = (isset($errors['mysql2']) && is_array($errors['mysql2'])) ? $errors['mysql2'] : [];
    $db2_selected = $_REQUEST['db2_active'] ?? [];
    if (!is_array($db2_selected) && $db2_selected !== '') {
        $db2_selected = [$db2_selected];
    }
    ob_start();

    Form::checkboxes('db2_active',
    ['db2_active' => 'Use Second Database'], 
    $db2_selected, 
    false, 
    [ 'form-check-class'=>'form-switch'], ['onchange' => "toggleEl(document.getElementById('db2Config'))"]
);
?>
<div id="db2Config" style="display: none;">

    <h3>The database where the data to be analyzed is stored.</h3>
    <p>This system can use two database connections, one for configurations (sqlite is suggested) and one for data. You can also not activate the second connection if not necessary.</p>
            
    <?php Install::printErrors($errors_mysql); ?>
    <?php $options = ['class' => 'mb-3', 'required' => true, 'floating'=>true]; ?>
    <div class="mb-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Second Database Configuration</h5>
                
                <?php 
                // Check available database extensions
                $dbTypes = [];
                if (extension_loaded('mysqli')) {
                    $dbTypes['mysql'] = 'MySQL';
                }
                if (extension_loaded('sqlite3')) {
                    $dbTypes['sqlite'] = 'SQLite';
                }
                
                // Set default based on available extensions
                $defaultDb = !empty($dbTypes['mysql']) ? 'mysql' : (!empty($dbTypes['sqlite']) ? 'sqlite' : '');
                
                if (!empty($dbTypes)) {
                    Form::select('connectType2', 'connect_type2', $dbTypes, $_REQUEST['connectType2'] ?? $defaultDb, 
                        ['onchange' => "toggleEl(document.getElementById('db2Fields'))"]);
                    
                    echo '<div id="db2Fields" class="mt-3">';
                    echo '<div data-togglevalue="mysql" data-togglefield="connectType2">';

                    ?>      <p class="alert alert-warning">Before installation make sure you have created mysql (or MariaDb) database and a user with Grant permissions to associate with it.</p>
                    <?php
                    Form::input('text', 'connect_ip2', 'IP Address', $_REQUEST['connect_ip2'] ?? '127.0.0.1', $options);
                    Form::input('text', 'connect_login2', 'Username', $_REQUEST['connect_login2'] ?? '', $options);
                    Form::input('password', 'connect_pass2', 'Password', $_REQUEST['connect_pass2'] ?? '', $options);
                    Form::input('text', 'connect_dbname2', 'Database Name', $_REQUEST['connect_dbname2'] ?? '', $options);
                    echo '</div>';
                    echo '</div>';
                    
                    ?>
                    <script>
                      window.addEventListener('load', function() {
                        manageRequiredFields(document.getElementById('db2Fields'), false);
                    });

                    </script>
                    <?php
                  
                } else {
                    echo '<div class="alert alert-danger">No database extensions available. Please install either MySQLi or SQLite3 extension.</div>';
                }
                ?>

            </div>
        </div>
    </div>
</div>
<script>
    window.addEventListener('load', function() {
        var db2Toggle = document.querySelector('input[name="db2_active[]"]');
        if (db2Toggle) {
            toggleEl(document.getElementById('db2Config'), db2Toggle, db2Toggle.value);
        }
    });
</script>
    <?php
    $html .= ob_get_clean();
    return $html;
}, 20);

Hooks::set('install.check_data', function($errors, $data) {
    $mysql_errors = [];
    
    // Controlla se il secondo database è attivo
    $db2_active = isset($data['db2_active']) && !empty($data['db2_active']);
    
    if ($db2_active) {
        // Se db2 è attivo, controlla il tipo di connessione
        $connectType2 = $data['connectType2'] ?? '';
        
        if ($connectType2 === 'mysql') {
            // Validazione per MySQL
            if (empty($data['connect_ip2'])) {
                $mysql_errors['connect_ip2'] = 'IP Address is required';
            }
            if (empty($data['connect_login2'])) {
                $mysql_errors['connect_login2'] = 'Username is required';
            }
            if (empty($data['connect_pass2'])) {
                $mysql_errors['connect_pass2'] = 'Password is required';
            }
            if (empty($data['connect_dbname2'])) {
                $mysql_errors['connect_dbname2'] = 'Database Name is required';
            }
            
            // Testa la connessione MySQL solo se tutti i campi sono compilati
            if (empty($mysql_errors)) {
                $conn = new MySql('');
                try {
                    if (!$conn->connect($data['connect_ip2'], $data['connect_login2'], $data['connect_pass2'], $data['connect_dbname2'])) {
                        $mysql_errors['mysql2'] = 'Connection failed! Verify database existence, connection data, and database permissions.';
                    }
                } catch (\Throwable $e) {
                    $mysql_errors['mysql2'] = 'Connection failed! Verify database existence, connection data, and database permissions.';
                }
            }
        }
        // Per SQLite non servono validazioni aggiuntive, il file viene creato automaticamente
    }
    
    $errors = Install::setErrors('mysql2', $errors, $mysql_errors);
    return $errors;
});

Hooks::set('install.execute_config', function($data) {
    // Controlla se il secondo database è attivo
    $db2_active = isset($data['db2_active']) && !empty($data['db2_active']);
    $data['prefix'] = $data['prefix'] ?? 'milk';
    if (!$db2_active) {
        // Scenario 1: db2_active non selezionato - usa le impostazioni del primo database
        $connectType1 = $data['connectType1'] ?? 'sqlite';
        if ($connectType1 == 'mysql') {
            $db_config = [
                'db_type2' => 'mysql',
                'connect_ip2' => $data['connect_ip'] ?? '127.0.0.1',
                'connect_login2' => $data['connect_login'] ?? '',
                'connect_pass2' => $data['connect_pass'] ?? '',
                'connect_dbname2' => $data['connect_dbname'] ?? '',
                'prefix2' =>  _r($data['prefix']),
            ];
        } else { 
            $db_config = [
                'db_type2' => 'sqlite',
                'connect_dbname2' => $data['connect_dbname'] ?? '',
                'prefix2' =>  _r($data['prefix']),
            ];
        }

    } else {
        // db2_active è selezionato
        $connectType2 = $data['connectType2'] ?? 'sqlite';
        
        if ($connectType2 == 'sqlite') {
            $dbname = substr(md5(time().uniqid('', true)), 0, 10);
            // Scenario 2: SQLite
            $db_config = [
                'db_type2' => 'sqlite',
                'connect_dbname2' => 'milk_data_'. $dbname .'.db',
                'prefix2' =>  _r($data['prefix'])
            ];
        } else {
            // Scenario 3: MySQL
            $db_config = [
                'db_type2' => 'mysql',
                'connect_ip2' => _r($data['connect_ip2']),
                'connect_login2' => _r($data['connect_login2']),
                'connect_pass2' => _r($data['connect_pass2']),
                'connect_dbname2' => _r($data['connect_dbname2']),
                'prefix2' =>  _r($data['prefix'])
            ];
        
        }
    }
    if ($db_config['db_type2'] == 'mysql') {
        // Per SQLite, il file viene creato automaticamente se non esiste
        Config::set('db_type2', 'mysql');
        Config::set('connect_ip2', _r($db_config['connect_ip2']));
        Config::set('connect_login2', _r($db_config['connect_login2']));
        Config::set('connect_pass2', _r($db_config['connect_pass2']));
        Config::set('connect_dbname2', _r($db_config['connect_dbname2']));
        Config::set('prefix2',  _r($data['prefix']));
    } else {
        Config::set('db_type2', 'sqlite');
        Config::set('connect_dbname2', _r($db_config['connect_dbname2']));
        Config::set('prefix2',  _r($data['prefix']));
    }
    Get::$db2 = null; 
    Get::db2();
    // Imposta la configurazione
    Install::setConfigFile('Database 2 (data)', $db_config);
    
    $data = array_merge($data, $db_config);
    // Imposta le configurazioni runtime
    foreach ($db_config as $key => $value) {
        Config::set($key, $value);
    }
    
    return $data;
}, 10);
