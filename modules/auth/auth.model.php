<?php
namespace Modules\Auth;
use MilkCore\AbstractModel;

class UserModel extends AbstractModel {
    protected string $table = '#__users';
    protected string $primary_key = 'id';
    protected string $object_class = 'UserObject';
}

class SessionModel extends AbstractModel {
    protected string $table = '#__sessions';
    protected string $primary_key = 'id';
    protected string $object_class = 'SessionObject';
}

class LoginAttemptsModel extends AbstractModel {
    protected string $table = '#__login_attempts';
    protected string $primary_key = 'id';
    protected string $object_class = 'LoginAttemptsObject';
}