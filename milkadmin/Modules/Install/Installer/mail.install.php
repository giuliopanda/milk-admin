<?php
namespace Modules\Install\Installer;

use App\{Form, Hooks};
use Modules\Install\Install;

!defined('MILK_DIR') && die(); // Avoid direct access

Hooks::set('install.get_html_modules', function($html, $errors) {
    $errors_smtp = (isset($errors['smtp']) && is_array($errors['smtp'])) ? $errors['smtp'] : [];
    $mail_selected = $_REQUEST['mail_type'] ?? [];
    if (!is_array($mail_selected) && $mail_selected !== '') {
        $mail_selected = [$mail_selected];
    }
    ob_start();
    ?><h3 class="mt-4">Mail</h3>
    <?php Install::printErrors($errors_smtp); ?>
    <?php $options = ['class' => 'mb-3', 'required' => false, 'floating'=>true]; ?>
    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <?php Form::input('email', 'mail_from', 'From email', $_REQUEST['mail_from'] ?? ($_REQUEST['admin-email'] ?? 'example@example.com'), $options); ?>
        </div>
        <div class="col-md-6">
            <?php Form::input('text', 'mail_from_name', 'From name', $_REQUEST['mail_from_name'] ?? ($_REQUEST['site-title'] ?? 'Milk Admin'), $options); ?>
        </div>
    </div>
    <?php
        Form::checkboxes('mail_type',
        ['smtp' => 'Use SMTP Mail'], 
        $mail_selected, 
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
                        Form::input('text', 'smtp_mail_port', 'PORT',  $_REQUEST['smtp_mail_port'] ?? '465', $options);
                        Form::input('text', 'smtp_mail_username', 'Username', $_REQUEST['smtp_mail_username'] ?? '', $options);
                        Form::input('password', 'smtp_mail_password', 'Password', $_REQUEST['smtp_mail_password'] ?? '', $options);
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
    <script>
        window.addEventListener('load', function() {
            var smtpToggle = document.querySelector('input[name="mail_type[]"]');
            if (smtpToggle) {
                toggleEl(document.getElementById('smtpConfig'), smtpToggle, smtpToggle.value);
            }
        });
    </script>
    <?php
    $html .= ob_get_clean();
    return $html;
}, 30);

Hooks::set('install.check_data', function($errors, $data) {
    $smtp_errors = [];
    if (empty($data['mail_from'])) {
        $smtp_errors['mail_from'] = 'mail_from is required';
    } elseif (!filter_var($data['mail_from'], FILTER_VALIDATE_EMAIL)) {
        $smtp_errors['mail_from'] = 'mail_from must be a valid email';
    }

    $mail_type = $data['mail_type'] ?? [];
    if (!is_array($mail_type) && $mail_type !== '') {
        $mail_type = [$mail_type];
    }
    if (in_array('smtp', $mail_type, true)) {
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
    $mail_from = trim((string) ($data['mail_from'] ?? 'example@example.com'));
    $mail_from_name = trim((string) ($data['mail_from_name'] ?? 'Milk Admin'));
    $mail_type = $data['mail_type'] ?? [];
    if (!is_array($mail_type) && $mail_type !== '') {
        $mail_type = [$mail_type];
    }
    if (in_array('smtp', $mail_type, true)) {
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

    $data['mail_from'] = $mail_from;
    $data['mail_from_name'] = $mail_from_name !== '' ? $mail_from_name : 'Milk Admin';
    Install::setConfigFile('SETTING EMAIL', $data);
    return $data;
});
