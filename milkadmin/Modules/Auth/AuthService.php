<?php
namespace Modules\Auth;

use App\{Config, Get, Hooks, MessagesHandler, Permissions, Response, Route, Sanitize, Theme, Token};

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Funzione per la visualizzazione del titolo
 */

class AuthService {

    static public function tmplTitle() {
        $title = Theme::get('site.title', (Config::get('site-title', '')));
        echo '<div class="login-title">'.$title.'</div>';
    }

    static public function login() {
        if (isset($_REQUEST['username']) && isset($_REQUEST['password'])) {
            if (Token::check('login') === false) {
                $msg_error = 'User or password incorrect.';
                $is_authenticated_in = false;
            } else {
                $is_authenticated_in = Get::make('Auth')->login($_REQUEST['username'], $_REQUEST['password']);
                if (!$is_authenticated_in) {
                    if (MessagesHandler::hasErrors()) { 
                        $msg_error = MessagesHandler::errorsToString();
                    } else {
                        $msg_error = 'User or password incorrect.';
                    }
                }
            }
        } else {
            $is_authenticated_in = Get::make('Auth')->login();
        }
        
        $home = Config::get('home_page', '?page=home');
        if (isset($_REQUEST['redirect']) && $_REQUEST['redirect'] != '') {
            $home = Route::urlsafeB64Decode($_REQUEST['redirect']);
        }
        if ($is_authenticated_in && $home != '') {
            Route::redirect($home);
        }
        $username = $_REQUEST['username'] ?? '';
        $msg_error =  $msg_error ?? '';
        
        Response::themePage('empty', __DIR__ . '/Views/login.php', ['msg_error' => $msg_error, 'username' => $username, 'is_authenticated_in' => $is_authenticated_in, 'redirect' => ($_REQUEST['redirect'] ?? '')]);
    }

    static public function logout() {
        Get::make('Auth')->logout();
        Route::redirect(['page'=>'auth']);
    }

    static public function forgotPassword() {
        $msg_error = '';
        $success = false;
        if (isset($_POST['username'])) {
            if (Token::check('forgot_password') === false) {
                Response::themePage('nologin-center', __DIR__ . '/Views/reset-password.php', ['msg_error' => 'Invalid token', 'success' => false]);
                return;
            } 
            $username = Sanitize::input(trim($_POST['username']), 'username');
            $find_user = Get::db()->getRow('SELECT * FROM `#__users` WHERE username = ?', [$username]);
            if ($find_user) {
                if ($find_user->status == 0) {
                    $msg_error = 'User not active. Contact the administrator to activate the account.';
                    $success = false;
                } else {
                    if ($find_user->activation_key != '') {
                        if (Get::make('Auth')->checkExpiresActivationKey($find_user->activation_key, 10, '>') === false) {
                            $msg_error = 'An email has already been sent to reset the password. Check your inbox or wait 10 minutes to request a new one.';
                            $success = false;
                            Response::themePage('nologin-center', __DIR__ . '/Views/reset-password.php', ['msg_error' => $msg_error , 'success' => $success]);
                            // STOP
                            return;
                        }
                    } 

                    $user_id = $find_user->id;
                    $activation_key = Get::make('Auth')->createActivationKey($user_id);
                    // genero l'url per il reset della password
                    $url = Route::url(['page'=>'auth', 'action'=>'new-password', 'key'=>$activation_key]);
                    // invio l'email
                    Get::mail()->loadTemplate(__DIR__ . '/Mails/user-reset-password.php', ['user' => $find_user, 'id' => $user_id, 'url' => $url]);
                    Get::mail()->to($find_user->email)->send();
                    $success = true;
                }
            } else {
                // non esiste l'utente, ma invio un messaggio generico di successo per non dare informazioni sull'esistenza o meno dell'utente
                $success = true;
            }

            Response::themePage('nologin-center', __DIR__ . '/Views/reset-password.php', ['msg_error' => $msg_error , 'success' => $success]);
            return;
        }
        Response::themePage('nologin-center', __DIR__ . '/Views/reset-password.php', ['msg_error' => $msg_error , 'success' => $success]);
    }

    static public function chooseNewPassword() {
        $key = $_GET['key'] ?? '';
        $success = false;
        $msg_error = '';
        if (!isset($_GET['key'])) {
            $msg_error = 'The link you entered does not appear to be valid. Select the entire link from the email you received and try again.';
        
        } else if (isset($_POST['password'])) {
            if (\App\Token::check('new_password') === false) {
                $msg_error = 'Invalid token';
            } else {
                if (!Get::make('Auth')->checkExpiresActivationKey($key, 60*24)) {
                    $msg_error = 'The link you entered has expired. Select the entire link from the email you received and try again.';
                } else {
                    $success = Get::db()->update('#__users', ['password' =>Get::make('Auth')->hashPassword($_POST['password']),  'activation_key' => ''], ['activation_key' => $key], 1);
                    if (!$success) {
                        $msg_error = 'Error setting new password';
                    }
                }
            
            }
        } else if (!isset($_GET['key'])) {
            $msg_error = 'The link you entered does not appear to be valid. Select the entire link from the email you received and try again.';
        
        } else {
            $find_user = Get::db()->getRow('SELECT * FROM `#__users` WHERE activation_key = ?', [$key]);
            if (!$find_user) {
                $msg_error = 'The link you entered does not appear to be valid. Select the entire link from the email you received and try again.';
            } else {
                if (Get::make('Auth')->checkExpiresActivationKey($find_user->activation_key, 60*24) === false) {
                    $msg_error = 'The link you entered has expired. Select the entire link from the email you received and try again.';
                }
            }
        }
        Response::themePage('nologin-center', __DIR__ . '/Views/choose-new-password.php', ['msg_error' => $msg_error , 'success' => $success, 'key' => $key]);
    }

    static public function editForm() {
        if (!Permissions::check('auth.manage')) {
            echo json_encode(['html' => _r('Permission denied'), 'title' => 'Error']);
            return;
        }
        $current_user = Get::make('Auth')->getUser();
        ob_start();
        $id = _absint($_REQUEST['id'] ?? 0);
        $user = (object) ['id' => 0, 'username' => '', 'email' => '', 'status' => 1, 'is_admin' => 0, 'permissions' => []];
        if ($id > 0) {
            $user = Get::make('Auth')->getUser($id);
        }
        if ($user === null) {
            $title = "Error";
            ?>
            <div class="alert alert-danger">User not found</div>
            <?php
        } else {
            ob_start();
            echo ($id > 0) ? 'Edit ': 'New User ';
            if ($id == 0) { ?>
            <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="saveUser()"><i class="bi bi-pencil"></i> <?php _pt('Save'); ?></button>
            <?php } elseif ($id > 0 && isset($user->status) && $user->status != -1) { ?>
                <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="saveUser()"><i class="bi bi-pencil"></i> <?php _pt('Save'); ?></button>
                <?php if ($current_user->id != $user->id) { ?>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php ($user->status == -1) ? 'true' : 'false'; ?>)"><i class="bi bi-trash"></i> <?php _pt('Delete'); ?></button>
                <?php } ?>
            <?php } elseif ($id > 0 && isset($user->status) && $user->status == -1) { ?>
                <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="saveUser()">Restore User from Trash</button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteUser(true)">Definitely Delete</button>
            <?php } 

            $title = ob_get_clean();
            require Get::dirPath(__DIR__ . '/Views/edit-form.php');
        }
        
        Response::json(['html' => ob_get_clean(), 'title' => $title]);
    }

    static public function saveUser() {
        header('Content-Type: application/json');
        if (!Permissions::check('auth.manage')) {
            echo json_encode(['msg' => _r('Permission denied'), 'success' => false]);
            return;
        }
        $id = _absint($_REQUEST['id'] ?? 0);
        if (Token::check('edit-form-'.$id) === false) {
            echo json_encode(['msg' => _r('Invalid token'), 'success' => false]);
            return;
        }

        $username = $_REQUEST['username'] ?? '';
        $email = $_REQUEST['email'] ?? '';
        $password = trim($_REQUEST['password'] ?? '');
        $is_admin = _absint($_REQUEST['is_admin'] ?? 0);
        $permissions = $_REQUEST['permissions'] ?? [];
        // if you are not an administrator, you cannot change the user management permissions
        if (!Permissions::check('_user.is_admin') && $id > 0) {
            $user_to_save = Get::make('Auth')->getUser($id);
            if (isset($user_to_save->permissions['auth']['manage']) && $user_to_save->permissions['auth']['manage'] == 1) {
                $permissions['auth']['manage'] = "1";
            } else if (isset($user_to_save->permissions['auth']['manage'])) {
               unset($permissions['auth']['manage']);
            }
        }
       
        $current_user = Get::make('Auth')->getUser();
        if ($current_user->is_admin == 0) {
            $is_admin = 0;
        } 

        $status = _absint($_REQUEST['status'] ?? 1);
        $msg = '';
        $success = false;
        // verifico che la username non sia già presente
        $find_user = Get::db()->getRow('SELECT * FROM `#__users` WHERE username = ? AND id != ?', [$username, $id]);
        if ($find_user) {
            $msg = 'Username already exists';
            $success = false;

        } else if ($id > 0) {
            $find_user = Get::db()->getRow('SELECT * FROM `#__users` WHERE id = ?', [$id]);
            if (!$find_user) {
                $msg = 'Something wrong! User not found. Please reload the page.';
            } else {
                if ($current_user->is_admin == 1 && $current_user->id == $id) {
                    $is_admin = 1;
                }
                if ($find_user->is_admin == 1 && !Permissions::check('_user.is_admin')) {
                    echo json_encode(['msg' => _r('Permission denied: You cannot edit an admin user'), 'success' => false]);
                    return;
                }
                Get::make('Auth')->saveUser($id, $username, $email, $password, $status, $is_admin, $permissions);

                $user = Get::make('Auth')->getUser($id);
                $activation_key = Get::make('Auth')->createActivationKey($id);
                $url = Route::url(['page'=>'auth', 'action'=>'new-password', 'key'=>$activation_key]);

                Get::mail()->loadTemplate(__DIR__ . '/Mails/admin-reset-password.php', ['user' => $user, 'id' => $id, 'url' => $url]);
                $success = true;
            }
        } else {
            Get::make('Auth')->saveUser($id, $username, $email, $password, $status, $is_admin, $permissions);
            $id = Get::make('Auth')->getLastInsertId();

            $user = Get::make('Auth')->getUser($id);
            $activation_key = Get::make('Auth')->createActivationKey($id);
            $url = Route::url(['page'=>'auth', 'action'=>'new-password', 'key'=>$activation_key]);

            Get::mail()->loadTemplate(__DIR__ . '/Mails/welcome-user.php', ['user' => $user, 'id' => $id, 'url' => $url]);
            $success = true;
        }
        if ($success) {
            // verifico se si deve inviare un'email
            if (isset($_REQUEST['send_email']) && $_REQUEST['send_email'] == 1) {
                if ($status == 1) {
                    if ($id > 0) {
                        Get::mail()->to($user->email)->send();
                        if (Get::mail()->last_error != '') {
                            $success = true;
                            $msg = 'User saved, but email not sent: '.Get::mail()->last_error;
                        } else {
                            $success = true;
                            $msg = 'User saved, email sent';
                        }
                    }
                } else {
                    $success  = false;
                    $msg = 'User saved, but email not sent because user is not active';
                }
            } else {
                $success = true;
                $msg = 'User saved';
            }
        }
        echo json_encode(['msg' => $msg, 'success' => $success]);
    }

    /**
     * Sè in status trash lo elimino definitivamente
     * altrimenti lo metto in trash
     * @param int $id
     */
    static public function editDeleteUser($id) {
        header('Content-Type: application/json');
        if (!Permissions::check('auth.manage')) {
            echo json_encode(['msg' => _r('Permission denied'), 'success' => false]);
            return;
        }
        if ($id == 0) {
            echo json_encode(['msg' => _r('Something wrong! User not found. Please reload the page.'), 'success' => false]);
            return;
        }
        if (Token::check('edit-form-'.$id) === false) {
            echo json_encode(['msg' => _r('Invalid token'), 'success' => false]);
            return;
        }
        $find_user = Get::db()->getRow('SELECT * FROM `#__users` WHERE id = ?', [$id]);
        if (!$find_user) {
            echo json_encode(['msg' => _r('Something wrong! User not found. Please reload the page.'), 'success' => false]);
            return;
        }
        if ($find_user->is_admin == 1 && !Permissions::check('_user.is_admin')) {
            echo json_encode(['msg' => _r('Permission denied: You cannot edit an admin user'), 'success' => false]);
            return;
        }
        if ($find_user->status == '-1') {
            Get::db()->delete('#__users', ['id' => $id], 1);
            echo json_encode(['msg' =>  _r('User deleted'), 'success' => true]);
            return;
        } else {
            Get::db()->update('#__users', ['status' => '-1'], ['id' => $id], 1);
            echo json_encode(['msg' => _r('User trashed'), 'success' => true]);
            return;
        }
    }

    static public function userList() {
        $current_user = Get::make('Auth')->getUser();
        if (!Permissions::check('auth.manage')) {
            if (($_REQUEST['page-output'] ?? '') == 'json') {
                Response::json(['permission_denied'=> true, 'html' => '', 'msg' => _r('Please login again'), 'success' => false ]);
            } else {
                $queryString = Route::getQueryString();
                Route::redirect('?page=deny&redirect='.Route::urlsafeB64Encode($queryString));
            }
        } else {
            [$msg_json, $success] = self::userListActions();
        }

        $model = new \App\Modellist\ModelList('#__users', 'userList');
        $model->addFilter('search', function($query, $search) use ($model) {
            $query->where('`username` LIKE ? OR `email` LIKE ? ', ['%'.$search.'%', '%'.$search.'%']);
        });
        $model->addFilter('status', function($query, $status) use ($model) {
            $model->page_info['filter_status'] = $status;
            switch ($status) {
                case 'active':
                    $query->where('`status` = 1');
                    break;
            case 'suspended':
                $query->where('`status` = 0');
                break;
            case 'trash':
                $query->where('`status` = -1');
                break;
        }
        });
        $query = $model->queryFromRequest();
        if (!isset($model->page_info['filter_status']) || $model->page_info['filter_status'] == 'all') {
            $query->where('`status` != -1');
        }

        // Recupero dei dati
        $trows = Get::db()->getResults(...$query->get());
        // Conteggio totale dei record
        $total = Get::db()->getVar(...$query->getTotal());

        $rows_info = $model->getListStructure()
            ->setLabel('username', _r('Name'))
            ->setLabel('email', _r('Email'))
            ->setColumn('registered', 'Registered', 'date')
            ->setColumn('status', 'Status', 'select', true, false, ['0' => _r('Suspended'), '1' => _r('Active'), '-1' => _r('Trash')])
            ->setColumn('is_admin', 'Is Admin', 'select', true, false, ['0' => _r('No'), '1' => _r('Yes')])
            ->hideColumn('id')
            ->setPrimary('id')
            ->deleteColumns(['password','activation_key', 'permissions']);

        if ($model->page_info['filter_status'] == 'trash') {
            $rows_info->setAction(['restore' => _r('Restore'), 'delete' => _r('Delete')], _r('Action'));
        } else {
            $rows_info->setAction(['edit' => _r('Edit'),  'trash' => _r('Trash')], _r('Action'));
        }

        $page_info = $model->getPageInfo($total);
        $page_info->setId('userList');
        if ($model->page_info['filter_status'] == 'trash') {
            $page_info->setBulkActions(['restore' => _r('Restore'), 'delete' => _r('Delete')]);
        } else {
            $page_info->setBulkActions(['trash' => _r('Trash'), 'suspended' => _r('status Suspended'),  'active' => _r('status Active')]);
        }
        ['rows_info' => $rows_info, 'trows' => $trows, 'page_info' => $page_info] = Hooks::run('auth.user_list', ['rows_info' => $rows_info, 'trows' => $trows, 'page_info' => $page_info]);
        if (($_REQUEST['page-output'] ?? '') == 'json') {
            $content =  Get::themePlugin('table', ['info' => $rows_info, 'rows' => $trows, 'page_info' => $page_info]); 
            header('Content-Type: application/json');
            echo json_encode(['html' => $content, 'msg' => $msg_json, 'success' => $success]);
            return;
        } else {
            Response::themePage('default', __DIR__ . '/Views/list-edit.php',  ['info' => $rows_info, 'rows' => $trows, 'page_info' => $page_info]);
        }
    }

    /**
     * Azioni per la lista degli utenti tipo trash, delete, restore
     * Edit invece è gestito separatamente. Queste sono le azioni che vengono inviate direttamente
     * dal sendform della lista utenti.
     */
    static private function userListActions() {
        $msg_json = '';
        $success = false;
        $table_id = 'userList';
        if (($_REQUEST['page-output'] ?? '') == 'json') {
            $user_list_actions = $_REQUEST[$table_id]['table_action'] ?? '';
            $user_list_actions = str_replace($table_id . '-', '', $user_list_actions);
            $your_user = Get::make('Auth')->getUser();
            if ($user_list_actions == 'trash' || $user_list_actions == 'delete' || $user_list_actions == 'delete') {
                if (Token::check($table_id) === false) {
                    $msg_json = _r('Invalid token');
                    $success = false;
                } else {
                    $ids = $_REQUEST[$table_id]['table_ids'] ?? '';
                    if ($ids != '') {
                        $ids = explode(',', $ids);
                        foreach ($ids as $id) {
                            $find_user = Get::db()->getRow('SELECT * FROM `#__users` WHERE id = ?', [$id]);
                            if ($find_user == null) {
                                $success = false;
                                $msg_json = _r('Something wrong! User (id: '.$id.') not found. Please reload the page.');
                            }
                            if ($your_user->id == $id) {
                                $success = false;
                                $msg_json = _r('Permission denied: You cannot change the status of your own user');
                            } elseif ($find_user->is_admin == 1 && !Permissions::check('_user.is_admin')) {
                                $success = false;
                                $msg_json = _r('Permission denied: You cannot edit an admin user');
                            } elseif ($find_user->status == '-1' && $user_list_actions == 'delete') {
                                Get::db()->delete('#__users', ['id' => $id], 1);
                                $msg_json = _r('User deleted');
                                $success = true;
                            } else {
                                Get::db()->update('#__users', ['status' => '-1'], ['id' => $id], 1);
                                $msg_json = _r('User trashed');
                                $success = true;
                            }                                
                        }
                    }
                }
            }

            if (in_array($user_list_actions, ['suspended', 'active', 'restore'])) {
                if (Token::check($table_id) === false) {
                    $msg_json = _r('Invalid token');
                    $success = false;
                } else {
                    $ids = $_REQUEST[$table_id]['table_ids'] ?? '';
                    if ($ids != '') {
                        $ids = explode(',', $ids);
                        switch ($user_list_actions) {
                            case 'suspended':
                                $status = '0';
                                $msg_json = _r('User suspended');
                                break;
                            case 'active':
                                $status = '1';
                                $msg_json = _r('User activated');
                                break;
                            case 'restore':
                                $status = '1';
                                $msg_json = _r('User restored');
                                break;
                        }
                        foreach ($ids as $id) {
                            $find_user = Get::db()->getRow('SELECT * FROM `#__users` WHERE id = ?', [$id]);
                            if ($find_user == null) {
                                $success = false;
                                $msg_json = _r('Something wrong! User (id: '.$id.') not found. Please reload the page.');
                            } elseif ($your_user->id == $id) {
                                $success = false;
                                $msg_json = _r('Permission denied: You cannot change the status of your own user');
                            } elseif ($find_user->is_admin == 1 && !Permissions::check('_user.is_admin')) {
                                $success = false;
                                $msg_json = _r('Permission denied: You cannot edit an admin user');
                            } else {
                                Get::db()->update('#__users', ['status' => $status], ['id' => $id], 1);
                                $success = true;
                            }
                        }
                    }
                }
            }
        }
        return [$msg_json, $success];
    }

    /**
     * Display user profile page
     */
    static public function profile() {
        $current_user = Get::make('Auth')->getUser();
        
        if (Permissions::check('_user.is_guest')) {
            Route::redirect('?page=auth&action=login');
            return;
        }

        // Direct echo to bypass Theme system issues
        Response::themePage('default', __DIR__ . '/Views/profile.php', ['user' => $current_user]);
    }

    /**
     * Update user profile (email and/or password)
     */
    static public function updateProfile() {
        header('Content-Type: application/json');
        
        $current_user = Get::make('Auth')->getUser();
        
        if (Permissions::check('_user.is_guest')) {
            echo json_encode(['msg' => _r('Please login again'), 'success' => false]);
            return;
        }

       // if (!\App\Token::check()) {
       //     echo json_encode(['msg' => _r('Invalid token'), 'success' => false]);
       //     return;
       // }

        $email = trim($_POST['email'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        // Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['msg' => _r('Please provide a valid email address'), 'success' => false]);
            return;
        }

        $success = true;
        $msg = '';

        // Handle password change
        if (!empty($new_password)) {
          
            // Validate new password
            if (strlen($new_password) < 6) {
                echo json_encode(['msg' => _r('New password must be at least 6 characters long'), 'success' => false]);
                return;
            }

            if ($new_password !== $confirm_password) {
                echo json_encode(['msg' => _r('New password and confirm password do not match'), 'success' => false]);
                return;
            }

            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_result = Get::db()->update('#__users', [
                'email' => $email,
                'password' => $hashed_password
            ], ['id' => $current_user->id]);

            if ($update_result === false) {
                echo json_encode(['msg' => _r('Failed to update profile: ') . Get::db()->last_error, 'success' => false]);
                return;
            }

            // remove sessions except current
            $session_model = new \Modules\Auth\SessionModel();
            $session_model->deleteByUserIdExceptCurrent($current_user->id);
            // remove all remember me tokens
            $remember_me_model = new \Modules\Auth\RememberTokenModel();
            $remember_me_model->revokeAllTokensByUserId($current_user->id);

            $msg = _r('Profile and password updated successfully');
        } else {
            // Update only email
            $update_result = Get::db()->update('#__users', [
                'email' => $email
            ], ['id' => $current_user->id]);

            if ($update_result === false) {
                echo json_encode(['msg' => _r('Failed to update profile: ') . Get::db()->last_error, 'success' => false]);
                return;
            }

            $msg = _r('Profile updated successfully');
        }

        echo json_encode(['msg' => $msg, 'success' => true]);
    }
}