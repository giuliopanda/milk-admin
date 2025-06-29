<?php
namespace MilkCore;
use MilkCore\Cli;
use MilkCore\DependencyContainer;
use MilkCore\SQLite;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Facade Class for Core System Functionality
 *
 * This class serves as a facade for the core system, providing static methods to facilitate access
 * and management of key functionalities such as database connections, email sending,
 * module and controller loading, and theme page and module management.
 *
 * @example
 * ```php
 * // Database connection
 * $db = Get::db();
 * $results = $db->query("SELECT * FROM users");
 * 
 * // Send an email
 * Get::mail()
 *     ->to('recipient@example.com')
 *     ->from('sender@example.com')
 *     ->subject('Hello')
 *     ->message('This is a test email')
 *     ->send();
 * 
 * // Load modules and theme functions
 * Get::load_modules();
 * 
 * // Load a theme page
 * Get::theme_page('home', 'Main content', ['title' => 'Home Page']);
 * 
 * // Load a theme module
 * Get::theme_plugin('sidebar', ['active' => 'home']);
 * ```
 *
 * @package     MilkCore
 */

class Get 
{
    /**
     * Reference to the mail handling class instance
     *
     * Stores the singleton instance of the Mail class for sending emails.
     *
     * @var null|\MilkCore\Mail
     */
    static $mail_class = null;
    /**
     * Reference to the primary database connection instance
     *
     * Stores the singleton instance of the database class (MySql or SQLite) for database operations.
     *
     * @var null|MySql|SQLite
     */
    static $db = null;

    /**
     * Reference to the secondary database connection instance
     *
     * Stores the singleton instance of the secondary database connection (MySql or SQLite) for data operations.
     *
     * @var null|MySql|SQLite
     */
    static $db2 = null;

    /**
     * Indicates whether the MathParser class has been loaded
     *
     * Flag to track if the mathematical parser library has been initialized.
     *
     * @var bool
     */
    static $math_parser_loaded = false;
    /**
     * Creates or returns an instance of the primary database connection
     * 
     * This method implements the singleton pattern for database connections.
     * It creates a new connection if one doesn't exist, or returns the existing one.
     * The database type (MySQL or SQLite) is determined by the 'db_type' configuration.
     * 
     * @example
     * ```php
     * // Get database connection and run a query
     * $db = Get::db();
     * $results = $db->query("SELECT * FROM users WHERE active = 1");
     * ```
     *
     * @return \MilkCore\MySql|\MilkCore\SQLite Instance of the database connection
     */
    public static function db() {
        if (self::$db == null) {
            $db_type = Config::get('db_type', 'mysql');
            if ($db_type === 'sqlite') {
                // SQLite connection
                self::$db = new SQLite(Config::get('prefix'));
                $dbname = Config::get('connect_dbname');
                if ($dbname != NULL) {
                    // SQLite connect method requires all 4 parameters for compatibility
                    // but only uses the dbname parameter
                    if (!self::$db->connect($dbname)) {
                        die("SQLite connection error: " . self::$db->last_error . "\n");
                    }
                }
            } else {
                // MySQL connection (default)
                self::$db = new MySql(Config::get('prefix'));
                if (Config::get('connect_ip') != NULL) {
                    if (!self::$db->connect(Config::get('connect_ip'), Config::get('connect_login'), Config::get('connect_pass'), Config::get('connect_dbname'))) {
                        die("MySQL connection error: " . self::$db->last_error . "\n");
                    }
                }
            }
        }
        return self::$db;
    }

    /**
     * Creates or returns an instance of the secondary database connection
     * 
     * The system can use two databases: one for configuration and one for data.
     * This method handles the connection to the secondary database (for data).
     * Currently, the secondary database is always MySQL regardless of the primary db_type.
     * 
     * @example
     * ```php
     * // Get secondary database connection and run a query
     * $db2 = Get::db2();
     * $results = $db2->query("SELECT * FROM data_table WHERE status = 'active'");
     * ```
     *
     * @return \MySql|SQLite Instance of the secondary database connection
     */
    public static function db2(): MySql|SQLite {
        if (self::$db2 == null) {
            $db_type = Config::get('db_type2', 'mysql');
            
            if ($db_type === 'sqlite') {
                // SQLite connection
                self::$db2 = new SQLite(Config::get('prefix2'));
                $dbname = Config::get('connect_dbname2');
                if ($dbname != NULL) {
                    // SQLite connect method requires all 4 parameters for compatibility
                    // but only uses the dbname parameter
                    if (!self::$db2->connect($dbname)) {
                        die("SQLite connection error\n");
                    }
                }
            } else {
                // MySQL connection (default)
                self::$db2 = new MySql(Config::get('prefix2'));
                if (Config::get('connect_ip2') != NULL) {
                    if (!self::$db2->connect(Config::get('connect_ip2'), Config::get('connect_login2'), Config::get('connect_pass2'), Config::get('connect_dbname2'))) {
                        die("MySQL connection error\n");
                    }
                }
            }
        }
        return self::$db2;
    }


    public static function schema($table, $db = null) {
        if ($db == null) {
            $db = self::$db;
        }
        if (Config::get('db_type') == 'mysql') {
            return new SchemaMysql($table, $db);
        } else  if (Config::get('db_type') == 'sqlite') {
            return new SchemaSqlite($table, $db);
        } else {
            die('Unsupported database type');
        }
    }

   
    /**
     * Creates or returns an instance of the Mail class for sending emails
     * 
     * This method implements the singleton pattern for the mail service.
     * It creates a new instance if one doesn't exist, or returns the existing one.
     * 
     * @example
     * ```php
     * // Get mail instance and send an email
     * Get::mail()
     *     ->to('recipient@example.com')
     *     ->from('sender@example.com')
     *     ->subject('Hello')
     *     ->message('This is a test email')
     *     ->send();
     * ```
     *
     * @return \MilkCore\Mail Instance of the mail service
     */
    public static function mail() {
        if (self::$mail_class == null) {
            self::$mail_class = new Mail();
            $username = Config::get('smtp_mail_username', '');
            $password = Config::get('smtp_mail_password', '');
            $host = Config::get('smtp_mail_host', 'localhost');
            $port = Config::get('smtp_mail_port', 465);
            if ($username == '' || $password == '' || $host == '') {
                self::$mail_class->config(false);
            } else {
                self::$mail_class->config(true, $username, $password, $host, $port);
            }
            self::$mail_class->from(Config::get('mail_from', 'no-reply@'.self::get_domain_for_email()), Config::get('mail_from_name', 'Milk Admin'));
        }
        return self::$mail_class;
    }
    /**
     * Loads all module controllers and theme functions
     * 
     * This method initializes all the module controllers and theme functions
     * required for the application to work properly.
     * 
     * @example
     * ```php
     * Get::load_modules();
     * ```
     * 
     * @return void
     */
    public static function load_modules() {
        
        require_once self::dir_path(MILK_DIR . '/functions.php');

        foreach (glob(MILK_DIR . '/modules/*.controller.php') as $filename) {
            if (substr(basename($filename), 0, 1) != '.') {
                require_once self::dir_path($filename);
            }
        }
       
        foreach (glob(MILK_DIR . '/modules/*/*.controller.php') as $filename) {
            $dir = dirname($filename);
            if (substr(basename($dir), 0, 1) != '.') {
                require_once self::dir_path($filename);
            }
        }

        // If there are classes inside the theme plugins I load them immediately (*.class.php)
        $theme_class = glob(THEME_DIR . '/plugins/*/*.class.php');
        foreach ($theme_class as $filename) {
            require_once self::dir_path($filename);
        }

        // All modules controllers have been loaded
        Hooks::run('modules_loaded');
        Hooks::run('after_modules_loaded');
    }

    /**
     * Loads a theme page with the specified content and variables
     * 
     * This method loads the requested theme page and passes the required variables to it.
     * 
     * @example
     * ```php
     * Get::theme_page('home', 'Main content', ['title' => 'Home Page']);
     * ```
     * 
     * @param string $page Name of the page to load
     * @param string|null $content Path to the content file or string content
     * @param array $variables Variables to pass to the page
     * @return void
     */
    public static function theme_page($page, $content = null, $variables = []) {
        $___page = $page;
        if (is_string($content) && is_file($content)) {
            // converte l'array di variabili in variabili locali
            extract($variables);
            ob_start();
            require self::dir_path($content);
            Theme::set('content', ob_get_clean());
       
        } elseif (($content == "" || is_null($content)) && is_scalar($variables)) {
            // questo per accettare questa particolare sintassi Get::theme_page('theme_page', '', 'Es: 404 - Page not found');
            Theme::set('content', $variables);
        } else if (($content == "" || is_null($content)) && is_array($variables)) {
            // sintassi Get::theme_page('theme_page', '', ['content'=>'Es: 404 - Page not found', 'success' => false]);
            // pagine in cui gli passi direttamente le variabili ad esempio per le pagine json
            extract($variables);
        } else if (is_string($content) && $content != '' && $variables == []) {
            Theme::set('content', $content);
        }
        $page = str_replace(['.page', '.php', '..', '/', '\\'], '', $___page);
        $page = self::dir_path(THEME_DIR . '/' .$page . ".page.php");
        ob_start();
        if (is_file ( $page )) {
            require $page;
        } else {
            require self::dir_path(THEME_DIR.'/empty.page.php');
        }
        $theme = ob_get_clean();
        $theme = Hooks::run('render-theme', $theme, $page);
        if ($theme != '') {
            echo $theme;
        }
    }

    /**
     * Loads a theme plugin with the necessary variables
     * 
     * This method loads the requested theme plugin and passes the required variables to it.
     * 
     * @example
     * ```php
     * Get::theme_plugin('sidebar', ['active' => 'home']);
     * ```
     * 
     * @param string $module Name of the module to load
     * @param array $variables Variables to pass to the module
     * @return string Content of the loaded module
     */
    public static function theme_plugin(string $module, array $variables = []) {
        $module = str_replace('.php', '', $module);
        $module = str_replace('..', '', $module);
        $module_path = explode('/', $module);

        $folder = THEME_DIR.'/plugins/'. reset($module_path);
        $module = end($module_path);
        ob_start();
            $file = self::dir_path($folder . '/' . $module . '.php');
            if (is_file ($file)) {
                extract($variables);
                require $file;
            } else {
                require self::dir_path(THEME_DIR.'/plugins/plugin-not-found/plugin-not-found.php');
            }
        return ob_get_clean();
    }

    /**
     * Responds with JSON data and terminates the application
     * 
     * This method sends a JSON response to the client and ends the application execution.
     * 
     * @example
     * ```php
     * Get::response_json(['status' => 'success', 'data' => $result]);
     * ```
     * 
     * @param array $data Data to be converted to JSON and sent as response
     * @return void This function terminates execution
     */
    public static function response_json(array $data): void {
        header('Content-Type: application/json');
        echo json_encode($data);
        if (self::$db != null) {
            self::$db->close();
        }
        if (self::$db2 != null) {
            self::$db2->close();
        }
        Settings::save();
        exit;
    }
    
    /**
     * Returns the secure path of a file
     * 
     * This method protects against path traversal attacks by ensuring paths
     * remain within the site directory. It also checks for customizations files
     * with the same name in the customizations directory.
     * 
     * @example
     * ```php
     * // Get secure path to a theme file
     * $require Get::dir_path(THEME_DIR.'/template_parts/sidebar.php');
     * ```
     * 
     * @param string $file Absolute path including MILK_DIR or THEME_DIR of the file to check
     * @return string Path of the file to load
     */
    public static function dir_path(string $file): string {
        $filename = basename($file);
        $dir = dirname($file);
        $dir = realpath($dir);
        $dir_without_root = str_replace(MILK_DIR, '', $dir);
        if ($dir == $dir_without_root) {
            // non è una cartella del sistema!
            return '';
        }
        // aggiungo la cartella customizations 
        if (is_file(MILK_DIR . '/customizations' . $dir_without_root . '/' . $filename)) {
            $file = MILK_DIR . '/customizations' . $dir_without_root . '/' . $filename;
        }
        return $file;
        
    }

    /**
     * Returns the URI path for resources like JS or CSS files
     * 
     * This method generates the correct URI path for web resources,
     * handling theme customizationss and proper URL formatting.
     * 
     * @example
     * ```php
     * <img src="<?php echo Get::uri_path($path); ?>" alt="Logo">
     * ```
     * 
     * @param string $file The file to load
     * @return string The absolute URI path of the file
     */
    public static function uri_path(string $file): string {
        $file_without_root = str_replace(THEME_URL, '', $file);
        if ($file == $file_without_root) {
            return $file;
        }
        // aggiungo la cartella customizations 
        $theme_dir = Config::get('theme_dir', 'default');
     
        if (is_file(MILK_DIR . '/customizations/' . $theme_dir  . $file_without_root  )) {
            $file = Config::get('base_url') . '/customizations/' . $theme_dir  . $file_without_root ;
        }
        return $file;
        
    }

    /**
     * Returns the path of the temporary directory
     * 
     * Provides the path to the system's temporary directory with a trailing slash.
     * 
     * @example
     * ```php
     * $path = Get::temp_dir().$file;
     * ```
     * 
     * @return string The path of the temporary directory with trailing slash
     */
    public static function temp_dir(): mixed {
        $temp = Config::get('temp_dir', sys_get_temp_dir()."/milkcore/");
        if (!is_dir($temp)) {
            mkdir($temp, 0755, true);
        }
        return $temp;
    }

    /**
     * Returns the current date and time based on configured timezone
     * 
     * Creates a DateTime object using the timezone settings from the configuration file.
     * 
     * @example
     * ```php
     * $now = Get::date_time_zone();
     * echo $now->format('Y-m-d H:i:s');
     * ```
     * 
     * @return \DateTime DateTime object with the configured timezone
     */
    public static function date_time_zone() {
        $now = new \DateTime();
        
        if (Config::get('time_zone') == '') {
            return $now;
        }
        $now->setTimezone(new \DateTimeZone(Config::get('time_zone')));
        return new \DateTime($now->format('Y-m-d H:i:s'));
    }

    /**
    * Format a date based on the system settings
     *
     * Converts a date string to the specified format according to system configuration.
     *
     * @example
     * ```php
     * $formatted_date = Get::format_date('2021-01-01', 'date');
     * ```
     *
     * @param string|null $date The date to format (in MySQL format)
     * @param string $format The format to use: 'date' (only date), 'time' (only time), or 'datetime' (both)
     * @return string The formatted date
     */

    public static function format_date(string|null|\DateTime $date, string $format = 'date'): string {
        if ($date === null) {
            return '';
        }
        if ($date === '0000-00-00 00:00:00' || $date === '0000-00-00' || $date === '00:00:00' || $date === '00:00' || $date === '0000-00-00 00:00') {
            return '';
        }
        if (is_a($date, \DateTime::class)) {
            $format_date = Config::get('date-format', 'Y-m-d');
            $format_time = Config::get('time-format', 'H:i:s');
            if (Config::get('time_zone') != '') {
                $date->setTimezone(new \DateTimeZone(Config::get('time_zone')));
            }
            if ($format == 'date') {
                return $date->format($format_date);
            } else if ($format == 'time') {
                return $date->format($format_time);
            } else {
                return $date->format($format_date . ' ' . $format_time);
            }
        }
        
        // Clean the input string by removing HTML tags and extracting the first date if multiple exist
        $cleaned_date = strip_tags($date);
        
        try {
            $now = new \DateTime($cleaned_date);
            
            $format_date = Config::get('date-format', 'Y-m-d');
            $format_time = Config::get('time-format', 'H:i:s');
            if (Config::get('time_zone') != '') {
                $now->setTimezone(new \DateTimeZone(Config::get('time_zone')));
            }
            
            if ($format == 'date') {
                return $now->format($format_date);
            } else if ($format == 'time') {
                return $now->format($format_time);
            } else {
                return $now->format($format_date . ' ' . $format_time);
            }
        } catch (\Exception $e) {
            // If DateTime creation fails, return the original string or empty string
            return '';
        }
    }

    /**
     * Returns an instance of the advanced mathematical parser
     * 
     * Creates and returns a configured instance of the mathematical expression parser.
     * This function is experimental and has only been unit tested.
     * 
     * @example
     * ```php
     * $parser = Get::parser($data);
     * $result = $parser->evaluate('x + y', ['x' => 5, 'y' => 3]);
     * ```
     * 
     * @param mixed $data Optional data to be used by the parser
     * @return \MilkCore\MathParser|null The configured parser instance
     * @experimental This was not used in production due to performance issues with large datasets
     */
    public static function parser($data = null): ?MathParser {
        if (self::$math_parser_loaded == false) {
            require_once __DIR__ . '/mathparser/autoload.php';
            self::$math_parser_loaded = true;
        } 
        $parser = new MathParser();
        if ($data != null) {
            $row_manager = new  DataRowManager();
            $row_manager->set_data($data);
            $parser->set_data_row_manager($row_manager);
        }
        return $parser;
    }

    /**
     * Bind a service to the dependency container
     * 
     * Example:
     * ```php
     * // Bind a regular class
     * Get::bind('database', DatabaseConnection::class);
     * 
     * // Bind a singleton service
     * Get::bind('config', AppConfig::class, true);
     * 
     * // Bind a factory function with initialization arguments
     * Get::bind('logger', function($filename) {
     *     return new FileLogger($filename);
     * }, false, ['app.log']);
     * ```
     * 
     * @param string $service_name Nome del servizio
     * @param mixed $implementation Classe o funzione da bindare
     * @param bool $singleton Se true, il servizio sarà istanziato una sola volta
     * @param array $arguments Argomenti per l'inizializzazione se singleton
     */

    public static function bind($service_name, $implementation,  $singleton = false, $arguments = []) {
        if (method_exists(self::class, $service_name)) {
            throw new \Exception("Not allowed to bind, method already exists: $service_name");
        }
        DependencyContainer::bind($service_name, $implementation, $singleton, $arguments);
    }

    /**
     * Creates an instance of a registered service
     * 
     * This method instantiates and returns a service from the dependency container.
     * Services must be registered first using the bind() method before they can
     * be instantiated.
     * 
     * Example:
     * ```php
     * // Register services
     * Get::bind('database', DatabaseConnection::class);
     * Get::bind('config', AppConfig::class, true);
     * Get::bind('logger', LoggerFactory::class);
     * 
     * // Create service instances
     * $db = Get::make('database', ['localhost', 'user', 'pass']);
     * $config = Get::make('config', []);
     * $logger = Get::make('logger', ['app.log']);
     * ```
     * 
     * @param string $name Name of the service to instantiate
     * @param array $arguments Arguments to pass to the service constructor/factory
     * @return mixed The requested service instance or null if not found
     */
    public static function make($name, $arguments = []) {
        $service = DependencyContainer::get($name, $arguments);
        if ($service) {
            return $service;
        }
        return null;
    }

    /**
     * Checks if a service is registered in the container
     * 
     * Example:
     * ```php
     * if (Get::has('database')) {
     *     $db = Get::make('database', ['localhost', 'user', 'pass']);
     * } else {
     *     // Handle missing service
     * }
     * ```
     * 
     * @param string $name Name of the service to check
     * @return bool True if the service is registered, false otherwise
     */
    public static function has($name) {
        return DependencyContainer::has($name);
    }


    /** 
     * Gets the client IP address
     * 
     * This method detects and returns the client's IP address, handling various scenarios
     * including proxy headers and IPv6 to IPv4 conversion for localhost.
     * 
     * @param bool $trust_proxy_headers Whether to trust proxy headers like X-Forwarded-For
     * @return string The client's IP address or 'CLI' if running in command line
     */
    public static function client_ip($trust_proxy_headers = false) {
        if (Cli::is_cli()) return 'CLI';
        $ip_address = null;
        
        // Se configurato per fidarsi dei proxy headers
        if ($trust_proxy_headers) {
            $proxy_headers = [
                'HTTP_CF_CONNECTING_IP',     // Cloudflare
                'HTTP_CLIENT_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED'
            ];
            
            foreach ($proxy_headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $ips = explode(',', $_SERVER[$header]);
                    // Prendi il primo IP valido dalla lista
                    foreach ($ips as $ip) {
                        $ip = trim($ip);
                        if (self::validate_ip($ip)) {
                            $ip_address = $ip;
                            break 2;
                        }
                    }
                }
            }
        }
        
        // Fallback a REMOTE_ADDR (sempre presente e affidabile)
        if (empty($ip_address) && !empty($_SERVER['REMOTE_ADDR'])) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }
        
        // Converti IPv6 localhost in IPv4
        if ($ip_address === '::1') {
            $ip_address = '127.0.0.1';
        }
        
        // Valida l'IP finale
        if (!self::validate_ip($ip_address)) {
            $ip_address = 'UNKNOWN';
        }
        
        // Troncalo alla lunghezza di 64 caratteri
        return substr($ip_address, 0, 64);
    }
    
    /**
     * Validates if a string is a valid IP address
     * 
     * Checks if the provided string is a valid IPv4 or IPv6 address,
     * handling cases where port numbers might be included.
     * 
     * @param string $ip The IP address to validate
     * @return bool True if valid IP address, false otherwise
     */
    private static function validate_ip($ip) {
        // Rimuovi eventuali porte
        if (strpos($ip, ':') !== false && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ip = explode(':', $ip)[0];
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    }


    private static function get_domain_for_email() {
        $domain = $config_domain = Config::get('base_url');
        if ($config_domain) {
            if (!preg_match('/^https?:\/\//', $config_domain)) {
                $config_domain = 'http://' . $config_domain;
            }
            $domain = parse_url($config_domain, PHP_URL_HOST);
            if (strpos($domain, 'www.') === 0) {
                $domain = substr($domain, 4);
            }
        }
        $host = $_SERVER['HTTP_HOST'] ?? $domain;
        $domain = explode(':', $host)[0];
        $parts = explode('.', $domain);
        $count = count($parts);
        
        if ($count >= 2) {
            return $parts[$count-2] . '.' . $parts[$count-1];
        }
        
        return $domain;
    }
}