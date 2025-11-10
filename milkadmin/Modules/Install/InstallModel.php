<?php
namespace Modules\Install;

use App\Hooks;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Install model
 */
class InstallModel
{
    var $errors = [];
    public function __construct() {

    }
    /**
     * Get all html modules to install and return them for printing
     */
    public  function getHtmlModules() {
        $html = '';
        return Hooks::run('install.get_html_modules', $html, $this->errors);
       
    }
    /**
     * Verify data
     */
    public function checkData($data) {
        $errors = [];
        $this->errors = Hooks::run('install.check_data', $errors, $data);
        if (is_null($this->errors)) return true;
        return (is_countable($this->errors) && count($this->errors) == 0); 
    }

    /**
     * First phase of installation. Saving the data. data is $_REQUEST
     */
    public function executeInstallConfig($data) { 
        Hooks::run('install.execute_config', $data);
   }


    /**
     * I run the installation after the configuration has been saved.
     * You now have access to the database.
     * 
     */
    public function executeInstall() { 
        $data =  $_SESSION['installation_params'];
        Hooks::run('install.execute', $data);
    }

    /**
     * I run the update if the version date already exists
     * It will then be the responsibility of the individual modules to verify the date and what needs to be updated
     */
    public function executeUpdate() {
        Hooks::run('install.update');
    }

}