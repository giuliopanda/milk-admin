<?php
namespace Modules\Install;
use MilkCore\Hooks;
use MilkCore\Form;
use MilkCore\MySql;
use MilkCore\Config;
use Modules\Install\Install;
!defined('MILK_DIR') && die(); // Avoid direct access

Hooks::set('install.get_html_modules', function($html, $errors) {
    $errors_default = (isset($errors['default']) && is_array($errors['default'])) ? $errors['default'] : [];
    ob_start();

    // Verifico se il database è accessibile
    ?>
    <?php Install::print_errors($errors_default); ?>
    <?php $options = ['class' => 'mb-3', 'required' => true, 'floating'=>true]; ?>
    <div class="row g-2 mb-3">
        <div class="card" style="max-width: 960px;">
            <div class="card-body">
            <h5 class="card-title">Site</h5>
                <?php 
                $http  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';
                $base_url = $_REQUEST['base_url'] ?? $http . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']).'/';
                Form::input('text', 'base_url', 'Base Url', $base_url, $options);
                Form::input('text', 'site-title', 'Title',  $_REQUEST['site-title'] ?? 'Milk Admin', $options);
                Form::input('email', 'admin-email', 'Admin email',  $_REQUEST['admin-email'] ?? '', $options);
                ?>
            </div>
        </div>
    </div>
<?php
    $html .= ob_get_clean();
    return $html;
});

/**
 * Setto le impostazioni di default
 * Se non esiste la versione allora è una nuova installazione, altrimenti è un aggiornamento
 */
Hooks::set('install.execute', function($data) {
    $default_data = [
        'base_url' => _r($data['base_url']),
        'site-title' => _r($data['site-title']),
        'admin-email' => _r($data['admin-email']),
        'from-email' => _r($data['admin-email']),
        'debug' => 'false',
        'home_page' => '?page=home',
        'page_not_found' => '404',
        'secret_key' => uniqid('', true),
        'token_key' => "t".substr(md5(uniqid()), 0, 4),
        'lang' => 'en',
        'theme_dir' => 'theme',
        'storage_dir' => 'storage',
        'version' => NEW_VERSION,
        'time_zone' => 'Europe/Rome',
        'date-format' => 'd/m/Y',
        'time-format' => 'H:i'
    ];
    Install::set_config_file('', $default_data);
    return $data;
}, 3);

