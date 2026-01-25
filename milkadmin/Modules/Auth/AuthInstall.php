<?php
namespace Modules\Auth;
use App\Abstracts\AbstractInstall;
use App\{Cli, MessagesHandler, Get};
use Modules\Install\Install;

class AuthInstall extends AbstractInstall {

    public function installExecuteConfig($data = []) {
        $auth_data = [
            'auth_expires_session' => ['value'=>'120','type'=>'number','comment' => 'Session duration in minutes']
        ];
        
        Install::setConfigFile('AUTH', $auth_data);
    
        return $data;
    }

    public function installExecute($data = []) {
        $this->installAuth();
        $username = trim($data['admin-username'] ?? '');
        $email = trim($data['admin-email'] ?? '');
        $password = $data['admin-password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {
            $message = 'Install Auth: admin username, email, and password are required.';
            if (Cli::isCli()) {
                Cli::error($message);
            } else {
                MessagesHandler::addError($message);
            }
            return;
        }

        $result = $this->model->store([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'status' => 1,
            'is_admin' => 1,
            'permissions' => []
        ]);
        if (!$result) {
            if (Cli::isCli()) {
                Cli::error("Install Auth: SAVE USER ERROR: ". $this->model->getLastError());
            } else {
                MessagesHandler::addError("Install Auth: SAVE USER ERROR: ". $this->model->getLastError());
            }
        }
    }

    public function installUpdate($html) {
        $this->installAuth();
        return $html;
    }

    public function shellInstallModule() {
        $this->installAuth();
    }
    public function shellUpdateModule() {
        $this->installAuth();
    }

    private function installAuth() {
        $this->module->_bootstrap();
        $success = true;
        $errors = [];

        // Create UserModel table
        if (Cli::isCli()) {
            Cli::echo("Creating users table...");
        }

        $result = $this->model->buildTable();
        if (!$result) {
            $error = $this->model->getLastError();
            $success = false;
            $errors[] = "Users table: " . ($error ?: 'Unknown error - buildTable returned false');
        }
      

        $model2 = new SessionModel();
        $result = $model2->buildTable();
        if (!$result) {
            $error = $model2->getLastError();
            $success = false;
            $errors[] = "Sessions table: " . ($error ?: 'Unknown error - buildTable returned false');
        } 
      
        $model3 = new LoginAttemptsModel();
        $result = $model3->buildTable();
        if (!$result) {
            $error = $model3->getLastError();
            $success = false;
            $errors[] = "Login attempts table: " . ($error ?: 'Unknown error - buildTable returned false');

        } 
      

        $model4 = new AccessLogModel();
        $result = $model4->buildTable();
        if (!$result) {
            $error = $model4->getLastError();
            $success = false;
            $errors[] = "Install Auth: Access logs table: " . ($error ?: 'Unknown error - buildTable returned false');
        }

        $model5 = new RememberTokenModel();
        $result = $model5->buildTable();
        if (!$result) {
            $error = $model5->getLastError();
            $success = false;
            $errors[] = "Remember tokens table: " . ($error ?: 'Unknown error - buildTable returned false');
        }

        if ($success && Cli::isCli()) {
            Cli::success("All Auth tables created successfully");
        } else if (!$success && Cli::isCli()) {
            Cli::echo("");
            Cli::echo("=== Installation failed with the following errors ===");
            foreach ($errors as $err) {
                Cli::echo("- " . $err);
            }
        } else if (!$success && !Cli::isCli()) {
            MessagesHandler::addError("Auth installation encountered errors: " . implode(";\n ", $errors));
        }

        return $success;
    }

}
