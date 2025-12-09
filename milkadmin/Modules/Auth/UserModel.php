<?php
namespace Modules\Auth;
use App\Permissions;
use App\Abstracts\AbstractModel;
use App\Attributes\ToDatabaseValue;


class UserModel extends AbstractModel {
    protected string $table = '#__users';
    protected string $primary_key = 'id';

    protected function configure($rule): void {
        $languages = \App\Config::get('available_locales', []);
        $default_language = \App\Config::get('locale', 'en_US');
        $rule->table($this->table)
            ->id('id')
            ->string('username', 255)->required()->label('Username')
            ->string('email', 255)->required()->label('Email')->formType('email')
            ->string('password', 255)->required()->label('Password')->formType('password')
            ->datetime('registered')->nullable()->label('Registration Date')
            ->datetime('last_login')->nullable()->label('Last Login')
            ->string('activation_key', 255)->default('')->label('Activation Key')
            ->int('status')->default(0)->label('Status')
                ->formType('list')
                ->formParams(['options' => [
                    0 => 'Inactive',
                    1 => 'Active'
                ]])
            ->boolean('is_admin')->default(0)->label('Administrator')
            ->text('permissions')->default('{}')->label('Permissions')
            ->string('timezone', 64)->default('UTC')->label('Timezone')
            ->select('locale', $languages)->default($default_language)->label('Language');
    }

    #[ToDatabaseValue('password')]
    public function sqlPassword($current_record_obj) {
        return password_hash($current_record_obj->password, PASSWORD_DEFAULT);
    }

    #[ToDatabaseValue('permissions')]
    public function sqlPermissions($current_record_obj) {
        $permissions = $current_record_obj->permissions;
        $permissions_groups = Permissions::getGroups();
        foreach ($permissions_groups as $group => $_) {
            $list_of_permissions = Permissions::get($group);
            foreach ($list_of_permissions as $permission_name => $_) {
                if (!isset($permissions[$group][$permission_name])) {
                    $save_permissions[$group][$permission_name] = 0;
                } else {
                    $save_permissions[$group][$permission_name] = ($permissions[$group][$permission_name] == 1) ? 1 : 0;
                }
            }
        }
        // add any $permissions not present in $permissions_groups
        if (is_array($permissions)) {
            foreach ($permissions as $group => $permissions_group) {
                if (!isset($save_permissions[$group])) {
                    $save_permissions[$group] = [];
                }
                foreach ($permissions_group as $permission_name => $permission_value) {
                    $save_permissions[$group][$permission_name] = (int)$permission_value;
                }
            }
        }
        return json_encode($save_permissions);
    }
}
