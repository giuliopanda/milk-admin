<?php

namespace Modules\Auth;

use App\Abstracts\AbstractModel;

/**
 * RememberToken Model
 *
 * Defines the schema for persistent "remember me" authentication tokens.
 * Tokens are stored with bcrypt hashing for security.
 */
class RememberTokenModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__remember_tokens')
            ->id()
            ->int('user_id')->nullable(false)->label('User ID')
            ->string('token_hash', 255)->nullable(false)->label('Token Hash')
            ->string('selector', 64)->nullable(false)->label('Selector')
            ->string('device_fingerprint', 255)->nullable()->label('Device Fingerprint')
            ->string('ip_address', 64)->nullable(false)->label('IP Address')
            ->string('user_agent', 512)->nullable()->label('User Agent')
            ->datetime('created_at')->nullable(false)->label('Created At')
            ->datetime('expires_at')->nullable(false)->label('Expires At')
            ->datetime('last_used_at')->nullable()->label('Last Used At')
            ->int('is_revoked')->default(0)->label('Is Revoked');
    }

    public function revokeAllTokensByUserId(int $user_id): void {
        $this->db->delete($this->table, ['user_id' => $user_id]);
    }
}
