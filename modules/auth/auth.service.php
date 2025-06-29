<?php
namespace Modules\Auth;
use MilkCore\Theme;
use MilkCore\Route;
use MilkCore\Get;
use MilkCore\Token;
use MilkCore\Sanitize;
use MilkCore\Permissions;
use MilkCore\Config;
use MilkCore\MessagesHandler;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Funzione per la visualizzazione del titolo
 */

class AuthService {
    

    static public function tmpl_title() {
        $title = Theme::get('site.title', (Config::get('site-title', '')));
        echo '<div class="login-title">'.$title.'</div>';
    }

    static public function login() {
        if (isset($_REQUEST['username']) && isset($_REQUEST['password'])) {
            if (Token::check('login') === false) {
                $msg_error = 'User or password incorrect.';
                $is_authenticated_in = false;
            } else {
                $is_authenticated_in = Get::make('auth')->login($_REQUEST['username'], $_REQUEST['password']);
                if (!$is_authenticated_in) {
                    if (MessagesHandler::has_errors()) { 
                        $msg_error = MessagesHandler::errors_to_string();
                    } else {
                        $msg_error = 'User or password incorrect.';
                    }
                }
            }
        } else {
            $is_authenticated_in = Get::make('auth')->login();
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
        
        Get::theme_page('empty', __DIR__ . '/views/login.php', ['msg_error' => $msg_error, 'username' => $username, 'is_authenticated_in' => $is_authenticated_in, 'redirect' => ($_REQUEST['redirect'] ?? '')]);
    }

    static public function logout() {
        Get::make('auth')->logout();
        Route::redirect(['page'=>'auth']);
    }

    static public function forgot_password() {
        $msg_error = '';
        $success = false;
        if (isset($_POST['username'])) {
            if (Token::check('forgot_password') === false) {
                Get::theme_page('nologin-center', __DIR__ . '/views/reset-password.php', ['msg_error' => 'Invalid token', 'success' => false]);
                return;
            } 
            $username = Sanitize::input(trim($_POST['username']), 'username');
            $find_user = Get::db()->get_row('SELECT * FROM `#__users` WHERE username = ?', [$username]);
            if ($find_user) {
                if ($find_user->status == 0) {
                    $msg_error = 'User not active. Contact the administrator to activate the account.';
                    $success = false;
                } else {
                    if ($find_user->activation_key != '') {
                        if (Get::make('auth')->check_expires_activation_key($find_user->activation_key, 10, '>') === false) {
                            $msg_error = 'An email has already been sent to reset the password. Check your inbox or wait 10 minutes to request a new one.';
                            $success = false;
                            Get::theme_page('nologin-center', __DIR__ . '/views/reset-password.php', ['msg_error' => $msg_error , 'success' => $success]);
                            // STOP
                            return;
                        }
                    } 

                    $user_id = $find_user->id;
                    $activation_key = Get::make('auth')->create_activation_key($user_id);
                    // genero l'url per il reset della password
                    $url = Route::url(['page'=>'auth', 'action'=>'new-password', 'key'=>$activation_key]);
                    // invio l'email
                    Get::mail()->load_template(__DIR__ . '/mails/user-reset-password.php', ['user' => $find_user, 'id' => $user_id, 'url' => $url]);
                    Get::mail()->to($find_user->email)->send();
                    $success = true;
                }
            } else {
                // non esiste l'utente, ma invio un messaggio generico di successo per non dare informazioni sull'esistenza o meno dell'utente
                $success = true;
            }

            Get::theme_page('nologin-center', __DIR__ . '/views/reset-password.php', ['msg_error' => $msg_error , 'success' => $success]);
            return;
        }
        Get::theme_page('nologin-center', __DIR__ . '/views/reset-password.php', ['msg_error' => $msg_error , 'success' => $success]);
    }


    static public function choose_new_password() {
        $key = $_GET['key'] ?? '';
        $success = false;
        $msg_error = '';
        if (!isset($_GET['key'])) {
            $msg_error = 'The link you entered does not appear to be valid. Select the entire link from the email you received and try again.';
        
        } else if (isset($_POST['password'])) {
            if (\MilkCore\Token::check('new_password') === false) {
                $msg_error = 'Invalid token';
            } else {
                if (!Get::make('auth')->check_expires_activation_key($key, 60*24)) {
                    $msg_error = 'The link you entered has expired. Select the entire link from the email you received and try again.';
                } else {
                    $success = Get::db()->update('#__users', ['password' =>Get::make('auth')->hash_password($_POST['password']),  'activation_key' => ''], ['activation_key' => $key], 1);
                    if (!$success) {
                        $msg_error = 'Error setting new password';
                    }
                }
            
            }
        } else if (!isset($_GET['key'])) {
            $msg_error = 'The link you entered does not appear to be valid. Select the entire link from the email you received and try again.';
        
        } else {
            $find_user = Get::db()->get_row('SELECT * FROM `#__users` WHERE activation_key = ?', [$key]);
            if (!$find_user) {
                $msg_error = 'The link you entered does not appear to be valid. Select the entire link from the email you received and try again.';
            } else {
                if (Get::make('auth')->check_expires_activation_key($find_user->activation_key, 60*24) === false) {
                    $msg_error = 'The link you entered has expired. Select the entire link from the email you received and try again.';
                }
            }
        }
        Get::theme_page('nologin-center', __DIR__ . '/views/choose-new-password.php', ['msg_error' => $msg_error , 'success' => $success, 'key' => $key]);
    }

    static public function edit_form() {
        if (!Permissions::check('auth.manage')) {
            echo json_encode(['html' => _r('Permission denied'), 'title' => 'Error']);
            return;
        }
        $current_user = Get::make('auth')->get_user();
        ob_start();
        $id = _absint($_REQUEST['id'] ?? 0);
        $user = (object) ['id' => 0, 'username' => '', 'email' => '', 'status' => 1, 'is_admin' => 0, 'permissions' => []];
        if ($id > 0) {
            $user = Get::make('auth')->get_user($id);
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
            require Get::dir_path(__DIR__ . '/views/edit-form.php');
        }
        
        Get::response_json(['html' => ob_get_clean(), 'title' => $title]);
    }

    static public function save_user() {
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
    
        $current_user = Get::make('auth')->get_user();
        if ($current_user->is_admin == 0) {
            $is_admin = 0;
        } 
       

        $status = _absint($_REQUEST['status'] ?? 1);
        $msg = '';
        $success = false;
        // verifico che la username non sia già presente
        $find_user = Get::db()->get_row('SELECT * FROM `#__users` WHERE username = ? AND id != ?', [$username, $id]);
        if ($find_user) {
            $msg = 'Username already exists';
            $success = false;

        } else if ($id > 0) {
            $find_user = Get::db()->get_row('SELECT * FROM `#__users` WHERE id = ?', [$id]);
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
                Get::make('auth')->save_user($id, $username, $email, $password, $status, $is_admin, $permissions);

                $user = Get::make('auth')->get_user($id);
                $activation_key = Get::make('auth')->create_activation_key($id);
                $url = Route::url(['page'=>'auth', 'action'=>'new-password', 'key'=>$activation_key]);

                Get::mail()->load_template(__DIR__ . '/mails/admin-reset-password.php', ['user' => $user, 'id' => $id, 'url' => $url]);
                $success = true;
            }
        } else {
            Get::make('auth')->save_user($id, $username, $email, $password, $status, $is_admin, $permissions);
            $id = Get::make('auth')->get_last_insert_id();

            $user = Get::make('auth')->get_user($id);
            $activation_key = Get::make('auth')->create_activation_key($id);
            $url = Route::url(['page'=>'auth', 'action'=>'new-password', 'key'=>$activation_key]);

            Get::mail()->load_template(__DIR__ . '/mails/welcome-user.php', ['user' => $user, 'id' => $id, 'url' => $url]);
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
    static public function edit_delete_user($id) {
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
        $find_user = Get::db()->get_row('SELECT * FROM `#__users` WHERE id = ?', [$id]);
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


    static public function user_list() {
        $current_user = Get::make('auth')->get_user();
       
        if (!Permissions::check('auth.manage')) {
            if (($_REQUEST['page-output'] ?? '') == 'json') {
                Get::response_json(['permission_denied'=> true, 'html' => '', 'msg' => _r('Please login again'), 'success' => false ]);
            } else {
                $queryString = Route::get_query_string();
                Route::redirect('?page=deny&redirect='.Route::urlsafeB64Encode($queryString));
            }
        } else {
            [$msg_json, $success] = self::user_list_actions();
        }
    
       
    
        $model = new \MilkCore\ModelList('#__users', 'userList');
        $model->add_filter('search', function($query, $search) use ($model) {
            $query->where('`username` LIKE ? OR `email` LIKE ? ', ['%'.$search.'%', '%'.$search.'%']);
        });
        $model->add_filter('status', function($query, $status) use ($model) {
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
        $query = $model->query_from_request();
        if (!isset($model->page_info['filter_status']) || $model->page_info['filter_status'] == 'all') {
            $query->where('`status` != -1');
        }

        // Recupero dei dati
        $trows = Get::db()->get_results(...$query->get());
        // Conteggio totale dei record
        $total = Get::db()->get_var(...$query->get_total());

        $rows_info = $model->get_list_structure()
            ->set_label('username', _r('Name'))
            ->set_label('email', _r('Email'))
            ->set_column('registered', 'Registered', 'date')
            ->set_column('status', 'Status', 'select', true, false, ['0' => _r('Suspended'), '1' => _r('Active'), '-1' => _r('Trash')])
            ->set_column('is_admin', 'Is Admin', 'select', true, false, ['0' => _r('No'), '1' => _r('Yes')])
            ->hide_column('id')
            ->set_primary('id')
            ->delete_columns(['password','activation_key', 'permissions']);

        if ($model->page_info['filter_status'] == 'trash') {
            $rows_info->set_action(['restore' => _r('Restore'), 'delete' => _r('Delete')], _r('Action'));
        } else {
            $rows_info->set_action(['edit' => _r('Edit'),  'trash' => _r('Trash')], _r('Action'));
        }

        $page_info = $model->get_page_info($total);

        if ($model->page_info['filter_status'] == 'trash') {
            $page_info->set_bulk_actions(['restore' => _r('Restore'), 'delete' => _r('Delete')]);
        } else {
            $page_info->set_bulk_actions(['trash' => _r('Trash'), 'suspended' => _r('status Suspended'),  'active' => _r('status Active')],);
        }

        if (($_REQUEST['page-output'] ?? '') == 'json') {
            $content =  Get::theme_plugin('table', ['info' => $rows_info, 'rows' => $trows, 'page_info' => $page_info]); 
            header('Content-Type: application/json');
            echo json_encode(['html' => $content, 'msg' => $msg_json, 'success' => $success]);
            return;
        } else {
            Get::theme_page('default', __DIR__ . '/views/list-edit.php',  ['info' => $rows_info, 'rows' => $trows, 'page_info' => $page_info]);
        }
    }


    /**
     * Azioni per la lista degli utenti tipo trash, delete, restore
     * Edit invece è gestito separatamente. Queste sono le azioni che vengono inviate direttamente
     * dal sendform della lista utenti.
     */
    static private function user_list_actions() {
        $msg_json = '';
        $success = false;
        $table_id = $_REQUEST['table_id'] ?? 'userList';
        if (($_REQUEST['page-output'] ?? '') == 'json') {
            $user_list_actions = $_REQUEST[$table_id]['table_action'] ?? '';
            $your_user = Get::make('auth')->get_user();
            if ($user_list_actions == 'trash' || $user_list_actions == 'delete' || $user_list_actions == 'delete') {
                if (Token::check('table') === false) {
                    $msg_json = _r('Invalid token');
                    $success = false;
                } else {
                    $ids = $_REQUEST[$table_id]['table_ids'] ?? '';
                    if ($ids != '') {
                        $ids = explode(',', $ids);
                        foreach ($ids as $id) {
                            $find_user = Get::db()->get_row('SELECT * FROM `#__users` WHERE id = ?', [$id]);
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
                if (Token::check('table') === false) {
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
                            $find_user = Get::db()->get_row('SELECT * FROM `#__users` WHERE id = ?', [$id]);
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
}