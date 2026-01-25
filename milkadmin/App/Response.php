<?php
namespace App;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Class Response
 * 
 * Response's feature is that it closes the database connection, saves the settings and closes the page.
 * 
 */

class Response {
    private static bool $capture_enabled = false;
    private static string $captured_output = '';
    private static string $captured_type = 'html';

    public static function beginCapture(): void {
        self::$capture_enabled = true;
        self::$captured_output = '';
        self::$captured_type = 'html';
    }

    public static function endCapture(): void {
        self::$capture_enabled = false;
    }

    public static function getCapturedOutput(): string {
        return self::$captured_output;
    }

    public static function getCapturedType(): string {
        return self::$captured_type;
    }

    private static function isCaptureEnabled(): bool {
        return self::$capture_enabled;
    }

    /**
     * Renders the specified HTML content with the specified theme and view
     * 
     * This method renders the specified HTML content with the specified theme and view.
     * 
     * @example
     * ```php
     * Response::render( 'home', ['html' => 'Main content', 'success' => true, 'msg' => 'Message'],, 'default');
     * ```
     * 
     * @param array $response Response to be rendered
     * @param string $view View to be loaded
     * @param string $theme Theme to be used
     * @return void
     */
    public static function render( string $view, array $response = [], string $theme = 'default'): void {
        if (Response::isJson()) {
            Response::htmlJson($response);
        } else {
            Response::themePage($theme, $view, $response);
        }
    }

    /**
     * Loads a theme page with the specified content and variables
     * 
     * This method loads the requested theme page and passes the required variables to it.
     * 
     * @example
     * ```php
     * Response::themePage('home', 'Main content', ['title' => 'Home Page']);
     * ```
     * 
     * @param string $page Name of the page to load
     * @param string|null $content Path to the content file or string content
     * @param array|string $variables Variables to pass to the page
     * @return void
     */
    public static function themePage(string $page, string|null $content = null, array|string $variables = []): void {
        $___page = $page;
        if (is_string($content) && is_file($content)) {
            // converte l'array di variabili in variabili locali
            extract($variables);
            ob_start();
            require Get::dirPath($content);
            Theme::set('content', ob_get_clean());
       
        } elseif (($content == "" || is_null($content)) && is_scalar($variables)) {
            // questo per accettare questa particolare sintassi Response::themePage('theme_page', '', 'Es: 404 - Page not found');
            Theme::set('content', $variables);
        } else if (($content == "" || is_null($content)) && is_array($variables)) {
            // sintassi Response::themePage('theme_page', '', ['content'=>'Es: 404 - Page not found', 'success' => false]);
            // pagine in cui gli passi direttamente le variabili ad esempio per le pagine json
            extract($variables);
        } else if (is_string($content) && $content != '') {
            Theme::set('content', $content);
        }
        $page = str_replace(['.page', '.php', '..', '/', '\\'], '', $___page);
        $page = Get::dirPath(THEME_DIR . '/' .$page . ".page.php");
        ob_start();
        if (is_file ( $page )) {
            require $page;
        } else {
            require Get::dirPath(THEME_DIR.'/empty.page.php');
        }
        $theme = ob_get_clean();
        $theme = Hooks::run('render-theme', $theme, $page);
        if (self::isCaptureEnabled()) {
            self::$captured_output = $theme;
            self::$captured_type = 'html';
            return;
        }
        if ($theme != '') {
            echo $theme;
        }
        Hooks::run('end-page'); 
        Settings::save();
        Get::closeConnections();
        exit;
    }

    /**
     * Responds with JSON data and terminates the application
     * 
     * This method sends a JSON response to the client and ends the application execution.
     * 
     * @example
     * ```php
     * Response::json(['status' => 'success', 'data' => $result]);
     * ```
     * 
     * @param array $data Data to be converted to JSON and sent as response
     * @return void This function terminates execution
     */
    public static function json(array $data): void {
        if (self::isCaptureEnabled()) {
            self::$captured_output = self::jsonEncode($data);
            self::$captured_type = 'json';
            return;
        }
        header('Content-Type: application/json');
        $data = self::utf8ize($data);
        echo self::jsonEncode($data);
     
        Settings::save();
        Get::closeConnections();
        exit;
    }

    private static function jsonEncode($data) {
        $data = self::utf8ize($data);
        $ris =  json_encode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return json_encode(["error" => "Errore: " . json_last_error_msg(), 'success' => false]);
        } else {
            return $ris;
        }
    }

    private static function utf8ize($mixed) {
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = self::utf8ize($value);
        }
    } elseif (is_string($mixed)) {
        return mb_convert_encoding($mixed, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }
    return $mixed;
}


    /**
     * Standard json response for html content
     * 
     * @param array $response Response to be sent as JSON
     * @return void This function terminates execution
     */
    public static function htmlJson(array $response): void {
        if (!isset($response['success'])) {
            $response['success'] = !MessagesHandler::hasErrors();
        }
        if (!isset($response['msg'])) {
            if (MessagesHandler::hasErrors()) {
                $response['msg'] = MessagesHandler::errorsToString();
            } else {
                $response['msg'] = MessagesHandler::successToString();
            }
        }
        if (!isset($response['html'])) {
            $response['html'] = '';
        }
        self::json($response);
    }

    /**
     * Responds with CSV data and terminates the application
     * 
     * This method sends a CSV response to the client and ends the application execution.
     * 
     * @param array $data Data to be converted to CSV and sent as response
     * @param string $filename Name of the file to be downloaded
     * @return void This function terminates execution
     */

    public static function csv(array $data, string $filename = 'export'): void {
        $filename = trim(str_replace('.csv', '', $filename)) . '.csv';
        if (self::isCaptureEnabled()) {
            ob_start();
        } else {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        }
        
        $output = fopen('php://output', 'w');
       
        $first_row = self::csvConvertToArray(reset($data));
        fputcsv($output, array_keys($first_row), ',', '"', "\\");
        
        foreach ($data as $row) {
            $row = self::csvConvertToArray($row);
            if (count($first_row) != count($row)) {
                continue;
            }
            fputcsv($output, $row, ',', '"', "\\");
        }
        fclose($output);

        if (self::isCaptureEnabled()) {
            self::$captured_output = ob_get_clean();
            self::$captured_type = 'csv';
            return;
        }

        Settings::save();
        Get::closeConnections();
        exit;
    }

    /**
    * Check if the response should be in JSON format
    */
    public static function isJson(): bool {
        $outputType = 'html';
        if (isset($_REQUEST['page-output'])) {
            $outputType = $_REQUEST['page-output'];
        } else {
            $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (strpos($acceptHeader, 'application/json') !== false) {
                $outputType = 'json';
            }
        }
        return $outputType === 'json';
    }

    /**
     * Converts an object or array to a CSV-convertible array
     * 
     * This method handles the conversion of objects and arrays to a format that can be written to a CSV file.
     * It supports DateTime objects and converts them to a string format, and it also handles nested objects and arrays.
     * 
     * @param mixed $row The object or array to convert
     * @return array The converted array
     */
    private static function csvConvertToArray(mixed $row): array {
        if (is_object($row) && method_exists($row, 'getFormattedData')) {
            $row = $row->getFormattedData('array');
        } else if (is_null($row) || !is_array($row)) {
            return [];
        } else if (is_countable($row)) {
            foreach ($row as &$value) {
                if (is_a($value, 'DateTime')) {
                    $value = $value->format('Y-m-d H:i:s');
                } else if (is_object($value) || is_array($value)) {
                    $value = self::jsonEncode($value);
                } 
            }
        }
        return $row;
    }


    public static function denyAccess(): void {
        if (self::isJson()) {
            self::json([
                'success' => false,
                'msg' => _r('Permission denied')
            ]);
        }
        $queryString = Route::getQueryString();
        Route::redirect('?page=deny&redirect=' . Route::urlsafeB64Encode($queryString));
    }


    public static function error(string $msg): void {
        if (self::isJson()) {
            self::json([
                'success' => false,
                'msg' => $msg
            ]);
               Response::htmlJson(['success' => false, 'msg' => $msg]);
        } else {
            $msg = '<div class="alert alert-danger">' . _r($msg) . '</div>';
            Response::themePage('default', $msg);
        }
    }

    public static function success(string $msg): void {
        if (self::isJson()) {
            self::json([
                'success' => true,
                'msg' => $msg
            ]);
        } else {
            $msg = '<div class="alert alert-success">' . _r($msg) . '</div>';
            Response::themePage('default', $msg);
        }
    }

}
