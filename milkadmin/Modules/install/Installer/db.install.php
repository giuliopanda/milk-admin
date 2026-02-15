<?php
namespace Modules\Install\Installer;

use App\{Form, Hooks, Get, Config};
use App\Database\MySql;
use Modules\Install\Install;

!defined('MILK_DIR') && die(); // Avoid direct access

Hooks::set('install.get_html_modules', function($html, $errors) {
    $errors_mysql = (isset($errors['mysql']) && is_array($errors['mysql'])) ? $errors['mysql'] : [];
    ob_start();
    ?><h3>Install Database</h3>
    <?php Install::printErrors($errors_mysql); ?>
    <?php $options = ['class' => 'mb-3', 'required' => true, 'floating'=>true]; ?>
    <div class="row g-2 mb-3">
        <div class="col-md">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Database Configuration</h5>

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
                    $defaultDb = !empty($dbTypes['sqlite']) ? 'sqlite' : (!empty($dbTypes['mysql']) ? 'mysql' : '');
                    
                    if (!empty($dbTypes)) {
                        Form::select('connectType1', 'connect_type', $dbTypes, $_REQUEST['connectType1'] ?? $defaultDb);
                    } else {
                        echo '<div class="alert alert-danger">No database extensions available. Please install either MySQLi or SQLite3 extension.</div>';
                    }
                    ?>
                    <div id="dbConfig1" class="my-4" data-togglevalue="mysql" data-togglefield="connectType1" style="display: none;">
                        <p class="alert alert-warning">Before installation make sure you have created mysql (or MariaDb) database and a user with Grant permissions to associate with it.</p>
                        <div class="row g-2 mb-3">
                            <div class="col-md">
                            <?php
                            Form::input('text', 'connect_ip', 'IP Address', $_REQUEST['connect_ip'] ?? '127.0.0.1', $options);
                            Form::input('text', 'connect_login', 'Username', $_REQUEST['connect_login'] ?? '', $options);
                            Form::input('text', 'connect_pass', 'Password',  $_REQUEST['connect_pass'] ?? '', $options);
                            Form::input('text', 'connect_dbname', 'Database Name',  $_REQUEST['connect_dbname'] ?? '', $options);
                            Form::input('text', 'prefix', 'Db Prefix', $_REQUEST['prefix'] ?? 'milk', $options);
                            ?>
                            </div>
                            <div class="col-md">
                                <ul>
                                    <li><b>IP Address:</b> The IP address of the MySQL server Es.(127.0.0.1)</li>
                                    <li><b>Username:</b> The username of the MySQL server (admin).</li>
                                    <li><b>Password:</b> The password of the MySQL server.</li>
                                    <li><b>Database Name:</b> The name of the database you want to connect to .</li>
                                    <li><b>Db Prefix:</b> The prefix to use for the tables. It's required and must be alphanumeric, 3-10 characters long with no spaces or special characters.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <script>
                        window.addEventListener('load', function() {
                            manageRequiredFields(document.getElementById('dbConfig1'), false);
                        });
                    </script>
                </div>
            </div>
        </div>
    </div>

    <?php
    $html .= ob_get_clean();
    return $html;
}, 20);

Hooks::set('install.check_data', function($errors, $data) {
    $mysql_errors = [];
    $connectType1 = $data['connectType1'] ?? 'sqlite';
    
    if ($connectType1 === 'mysql') {
        // Validazione per MySQL
        if (empty($data['connect_ip'])) {
            $mysql_errors['connect_ip'] = 'IP Address is required';
        }
        if (empty($data['connect_login'])) {
            $mysql_errors['connect_login'] = 'Username is required';
        }
        if (empty($data['connect_pass'])) {
            $mysql_errors['connect_pass'] = 'Password is required';
        }
        if (empty($data['connect_dbname'])) {
            $mysql_errors['connect_dbname'] = 'Database Name is required';
        }
        if (empty($data['prefix'])) {
            $mysql_errors['prefix'] = 'Prefix is required';
        }
        if (_raz($data['prefix']) != $data['prefix'] || strlen($data['prefix']) < 3 || strlen($data['prefix']) > 10) {
            $mysql_errors['prefix'] = 'Prefix must be alphanumeric, 3-10 characters long';
        }
        
        // Testa la connessione MySQL solo se tutti i campi sono compilati
        if (empty($mysql_errors)) {
            $conn = new MySql($data['prefix']);
            try {
                if (!$conn->connect($data['connect_ip'], $data['connect_login'], $data['connect_pass'], $data['connect_dbname'])) {
                    $mysql_errors['mysql'] = 'Connection failed! Verify database existence, connection data, and database permissions.';
                }
            } catch (\Throwable $e) {
                $mysql_errors['mysql'] = 'Connection failed! Verify database existence, connection data, and database permissions.';
            }
        }
    }
    // Per SQLite non servono validazioni aggiuntive
    $errors = Install::setErrors('mysql', $errors, $mysql_errors);
    return $errors;
});

Hooks::set('install.execute_config', function($data) {
    $connectType1 = $data['connectType1'] ?? 'sqlite';
    if ($connectType1 === 'sqlite') {
        $dbname = substr(md5(time().uniqid('', true)), 0, 10);
        // Configurazione SQLite
        $mysql_data = [
            'db_type' => 'sqlite',
            'connect_dbname' => 'milk_conf_'. $dbname .'.db',
            'prefix' => 'milk'
        ];
        
        // Imposta le configurazioni runtime per SQLite
        Config::set('db_type', 'sqlite');
        Config::set('connect_dbname', 'milk_conf_'. $dbname .'.db');
        Config::set('prefix', 'milk');
        Get::$db = null; 
        Get::db();
    } else {
        // Configurazione MySQL (codice esistente)
        $mysql_data = [
            'db_type' => 'mysql',
            'connect_ip' => _r($data['connect_ip']),
            'connect_login' => _r($data['connect_login']),
            'connect_pass' => _r($data['connect_pass']),
            'connect_dbname' => _r($data['connect_dbname']),
            'prefix' => _r($data['prefix'])
        ];
        
        // Imposta le configurazioni runtime per MySQL
        Config::set('db_type', 'mysql');
        Config::set('connect_ip', _r($data['connect_ip']));
        Config::set('connect_login', _r($data['connect_login']));
        Config::set('connect_pass', _r($data['connect_pass']));
        Config::set('connect_dbname', _r($data['connect_dbname']));
        Config::set('prefix', _r($data['prefix']));
        
        // Connessione al database MySQL
        Get::$db = null; 
        Get::db();

    }
   
    $data = array_merge($data, $mysql_data);
   
    Install::setConfigFile('Database 1 (config)', $mysql_data);
    return $data;
}, 5);
