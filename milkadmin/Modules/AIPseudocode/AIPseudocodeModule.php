<?php
namespace Modules\AIPseudocode;

use App\Abstracts\AbstractModule;
use App\Attributes\{RequestAction, Shell, AccessLevel};
use App\{Get, Cli, Config, File, Hooks, MessagesHandler, Permissions, Response, Route, Settings, Theme};
use Exception;

!defined('MILK_DIR') && die();

/**
 * Install Module
 * OOrganizes the installation and update process of the system and its modules.
 *
 * @package     Modules
 * @subpackage  Install
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @copyright   2025 Giulio Pandolfelli
 * @license     MIT
 */
class AIPseudocodeModule extends AbstractModule
{
 
 /**
     * Comando CLI per build version
     */
    #[Shell('sql-table-structure', system: true)]
    public function TableStructure($table, $db_name = null)
    {
        if ($db_name == '2') {
            $db = Get::db2();
        } else {
            $db = Get::db();
        }

        // Se la tabella non inizia con il prefisso, prova con #__
        if (strpos($table, '#__') !== 0 && strpos($table, $db->prefix) !== 0) {
            $prefixed_table = '#__' . $table;
        } else {
            $prefixed_table = $table;
        }

        // Verifica se la tabella esiste
        if ($db->type === 'mysql') {
            $result = $db->query("SHOW TABLES LIKE '{$prefixed_table}'");
            if (!$result || $result->num_rows() === 0) {
                Cli::error("Table '{$prefixed_table}' not found");
                return;
            }

            // Ottieni la struttura della tabella
            Cli::drawTitle("Table structure: {$prefixed_table}");

            $result = $db->query("SHOW FULL COLUMNS FROM " . $db->qn($prefixed_table));

            // Raccogli i dati in un array per drawTable
            $data = [];
            while ($row = $result->fetch_object()) {
                $data[] = [
                    'Field' => $row->Field,
                    'Type' => $row->Type,
                    'Null' => $row->Null,
                    'Key' => $row->Key,
                    'Default' => $row->Default ?? 'NULL',
                    'Extra' => $row->Extra
                ];
            }

            Cli::drawTable($data);

        } else {
            // SQLite
            $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$prefixed_table}'");
            if (!$result || !$result->fetch_array()) {
                Cli::error("Table '{$prefixed_table}' not found");
                return;
            }

            // Ottieni la struttura della tabella
            Cli::drawTitle("Table structure: {$prefixed_table}");

            $result = $db->query("PRAGMA table_info(" . $db->qn($prefixed_table) . ")");

            // Raccogli i dati in un array per drawTable
            $data = [];
            while ($row = $result->fetch_array()) {
                $data[] = [
                    'Field' => $row['name'],
                    'Type' => $row['type'],
                    'Null' => $row['notnull'] ? 'NO' : 'YES',
                    'Key' => $row['pk'] ? 'PRI' : '',
                    'Default' => $row['dflt_value'] ?? 'NULL'
                ];
            }

            Cli::drawTable($data);
        }

        Cli::success("Done!");
    }

    /**
     * Comando CLI per testare chiamate ai controller
     *
     * Questo comando simula una richiesta HTTP a un controller permettendo di testare
     * le action senza dover usare il browser. I permessi vengono automaticamente bypassati
     * impostando l'utente come admin durante l'esecuzione.
     *
     * @example
     * ```bash
     * # Chiamata semplice
     * php milkadmin/cli.php test-controller "page=users&action=view"
     *
     * # Con parametri GET
     * php milkadmin/cli.php test-controller "page=users&action=view" --get="id=123&filter=active"
     *
     * # Con parametri POST
     * php milkadmin/cli.php test-controller "page=users&action=save" --post="name=John&email=john@example.com"
     *
     * # Con entrambi
     * php milkadmin/cli.php test-controller "page=posts&action=edit" --get="id=5" --post="title=New Title&content=New Content"
     * ```
     *
     * @param string $route La route da testare in formato "page=...&action=..." oppure "?page=...&action=..."
     * @param string|null $get Parametri GET aggiuntivi in formato "key1=value1&key2=value2"
     * @param string|null $post Parametri POST in formato "key1=value1&key2=value2"
     */
    #[Shell('test-controller', system: true)]
    public function TestController($route, $get = null, $post = null)
    {
        // Parse della route
        $route = trim($route);
        if (substr($route, 0, 1) === '?') {
            $route = substr($route, 1);
        }

        // Parse dei parametri della route
        parse_str($route, $route_params);

        // Verifica che page sia presente
        if (!isset($route_params['page'])) {
            Cli::error("Missing 'page' parameter in route");
            Cli::echo("Usage: php milk test-controller \"page=users&action=view\"");
            return;
        }

        $page = $route_params['page'];
        $page = preg_replace('/[^a-zA-Z0-9-_]/', '', $page);
        $action = $route_params['action'] ?? null;

        // Setup dei parametri GET
        $_GET = $route_params;
        if ($get !== null) {
            parse_str($get, $get_params);
            $_GET = array_merge($_GET, $get_params);
        }

        // Setup dei parametri POST
        $_POST = [];
        if ($post !== null) {
            parse_str($post, $post_params);
            $_POST = $post_params;
        }

        // Setup di $_REQUEST (merge di GET e POST, POST ha precedenza)
        $_REQUEST = array_merge($_GET, $_POST);

        // Salva lo stato originale dei permessi
        $original_permissions = Permissions::$user_permissions;

        // Bypassa i permessi impostando l'utente come admin
        if (!isset(Permissions::$user_permissions['_user'])) {
            Permissions::$user_permissions['_user'] = [];
        }
        Permissions::$user_permissions['_user']['is_admin'] = true;
        Permissions::$user_permissions['_user']['is_guest'] = false;

        // Mostra info sulla chiamata
        Cli::drawTitle("Testing Controller: {$page}" . ($action ? " - Action: {$action}" : ""));
        Cli::echo("Permission check bypassed (running as admin)");

        if (!empty($_GET)) {
            Cli::echo("GET parameters:");
            foreach ($_GET as $key => $value) {
                Cli::echo("  {$key} = {$value}");
            }
        }

        if (!empty($_POST)) {
            Cli::echo("POST parameters:");
            foreach ($_POST as $key => $value) {
                Cli::echo("  {$key} = {$value}");
            }
        }

       

        // Cattura l'output
        ob_start();

        try {
            // Esegui la route
            if (!Route::run($page)) {
                ob_end_clean();
                Cli::error("Route '{$page}' not found");
                // Ripristina i permessi originali
                Permissions::$user_permissions = $original_permissions;
                return;
            }

            // Cattura l'output
            $output = ob_get_clean();

            // Mostra l'output
            if (!empty($output)) {
                Cli::drawTitle("Controller Output:");
                echo $output;
            }

            Cli::success("Controller executed successfully!");

        } catch (Exception $e) {
            ob_end_clean();
            Cli::error("Error executing controller:");
            Cli::echo($e->getMessage());
            Cli::echo("\nStack trace:");
            Cli::echo($e->getTraceAsString());
        } finally {
            // Ripristina i permessi originali
            Permissions::$user_permissions = $original_permissions;
        }
    }
}