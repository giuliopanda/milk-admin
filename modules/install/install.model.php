<?php
namespace Modules\Install;
use MilkCore\Hooks;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * 
 */
class InstallModel
{
    var $errors = [];
    public function __construct() {

    }
    /**
     * Prende tutti gli html dei moduli da installare e li restituisce per la stampa
     */
    public  function get_html_modules() {
        $html = '';
        return Hooks::run('install.get_html_modules', $html, $this->errors);
       
    }
    /**
     * Verifico i dati
     */
    public function check_data($data) {
        $errors = [];
        $this->errors = Hooks::run('install.check_data', $errors, $data);
        if (is_null($this->errors)) return true;
        return (is_countable($this->errors) && count($this->errors) == 0); 
    }

    /**
     * Salvo i dati. data è $_REQUEST
     * 
     */
    public function execute_install($data) { 
         Hooks::run('install.execute', $data);
    }

    /**
     * eseguo l'update se la data della versione già esiste
     * sarà poi compito dei singoli moduli verificare la data e cosa bisogna aggiornare
     */
    public function execute_update() {
        Hooks::run('install.update');
    }

}