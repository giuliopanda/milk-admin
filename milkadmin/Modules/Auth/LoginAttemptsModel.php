<?php
namespace Modules\Auth;

use App\Abstracts\AbstractModel;

class LoginAttemptsModel extends AbstractModel {

    protected function configure($rule): void {
        $rule->table('#__login_attempts')
            ->id()
            ->string('username_email', 255)->nullable(false)->label('Username/Email')
            ->string('ip_address', 64)->nullable(false)->label('IP Address')
            ->string('session_id', 128)->nullable(false)->label('Session ID')
            ->datetime('attempt_time')->nullable(false)->label('Attempt Time');
    }
}