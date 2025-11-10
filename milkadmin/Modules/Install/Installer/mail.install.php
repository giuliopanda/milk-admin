<?php
namespace Modules\Install\Installer;

use App\{Form, Hooks};
use Modules\Install\Install;

!defined('MILK_DIR') && die(); // Avoid direct access

Hooks::set('install.get_html_modules', function($html, $errors) {
    $errors_smtp = (isset($errors['smtp']) && is_array($errors['smtp'])) ? $errors['smtp'] : [];
    ob_start();
    ?><h3 class="mt-4">Mail</h3>
    <?php Install::printErrors($errors_smtp); ?>
    <?php $options = ['class' => 'mb-3', 'required' => false, 'floating'=>true]; ?>
    <?php
        Form::checkboxes('mail_type',
        ['smtp' => 'Use SMTP Mail'], 
        '', 
        false, 
        [ 'form-check-class'=>'form-switch'], ['onchange' => "toggleEl(document.getElementById('smtpConfig'))"]
    );
    ?>
    <div id="smtpConfig" style="display: none;">
        <div class="row g-2 mb-3">
            <div class="col-md">
                <div class="card" style="max-width: 400px;">
                    <div class="card-body">
                    <h5 class="card-title">SMTP Configuration</h5>
                        <?php 
                        Form::input('text', 'smtp_mail_host', 'SMTP Host',  $_REQUEST['smtp_mail_host'] ?? '', $options);
                        Form::input('text', 'smtp_mail_port', 'PORT',  $_REQUEST['connect_dbname'] ?? '465', $options);
                        Form::input('text', 'smtp_mail_username', 'Username', $_REQUEST['smtp_mail_username'] ?? '', $options);
                        Form::input('text', 'smtp_mail_password', 'Password', $_REQUEST['smtp_mail_password'] ?? '', $options);
                        ?>
                    </div>
                </div>
            </div>
            <div class="col-md px-4">
                <p>SMTP EMAIL is a protocol used for sending e-mail messages between servers. Most e-mail systems that send mail over the Internet use SMTP to send messages from one server to another; the messages can then be retrieved with an e-mail client using either POP or IMAP.</p>
                
                <p><ul>
                    <li>smtp_mail_host: The IP address of the SMTP server Es. (smtp.gmail.com)</li>
                    <li>smtp_mail_port: The port of the SMTP server (465).</li>
                    <li>smtp_mail_username: The username of the SMTP server (admin).</li>
                    <li>smtp_mail_password: The password of the SMTP server.</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
    $html .= ob_get_clean();
    return $html;
}, 30);

Hooks::set('install.check_data', function($errors, $data) {
    $smtp_errors = [];
    if ($data['mail_type'] ?? '' == 'smtp') {
        if (empty($data['smtp_mail_host'])) {
            $smtp_errors['smtp_mail_host'] = 'smtp_mail_host is required';
        }
        if (empty($data['smtp_mail_port'])) {
            $smtp_errors['smtp_mail_port'] = 'smtp_mail_port is required';
        }
        if (empty($data['smtp_mail_username'])) {
            $smtp_errors['smtp_mail_username'] = 'smtp_mail_username is required';
        }
        if (empty($data['smtp_mail_password'])) {
            $smtp_errors['smtp_mail_password'] = 'smtp_mail_password is required';
        }  
    }
    $errors = Install::setErrors('smtp', $errors, $smtp_errors);
    return $errors;
});

Hooks::set('install.execute_config', function($data) {
    if ($data['mail_type'] ?? '' == 'smtp') {
        $data = [
            'smtp_mail' => 'true',
            'smtp_mail_host' => $data['smtp_mail_host'],
            'smtp_mail_port' => $data['smtp_mail_port'],
            'smtp_mail_username' => $data['smtp_mail_username'],
            'smtp_mail_password' => $data['smtp_mail_password'],
        ];
    } else {
        $data = [
            'smtp_mail' => 'false'
        ];
    }
    $data['__mail_from'] =  'example@example.com';
    $data['__mail_from_name'] = 'Example';
    Install::setConfigFile('SETTING EMAIL', $data);
    return $data;
});