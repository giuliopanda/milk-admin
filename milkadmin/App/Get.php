<?php

namespace App;

use App\Database\{MySql, SQLite, ArrayDb, SchemaMysql, SchemaSqlite};
use App\ArrayQuery\ArrayEngine;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Facade Class for Core System Functionality
 *
 * This class serves as a facade for the core system, providing static methods to facilitate access
 * and management of key functionalities such as database connections, email sending,
 * module class loading, and theme page and module management.
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
 * Get::loadModules();
 * 
 * // Load a theme page
 * Response::themePage('home', 'Main content', ['title' => 'Home Page']);
 * 
 * // Load a theme module
 * Get::themePlugin('sidebar', ['active' => 'home']);
 * ```
 *
 * @package     App
 */

class Get
{
    /**
     * Reference to the mail handling class instance
     *
     * Stores the singleton instance of the Mail class for sending emails.
     *
     * @var null|Mail
     */
    static $mail_class = null;
    /**
     * Reference to the primary database connection instance
     *
     * Stores the singleton instance of the database class (MySql or SQLite) for database operations.
     *
     * @var null|MySql|SQLite|ArrayDb
     */
    static $db = null;

    /**
     * Reference to the secondary database connection instance
     *
     * Stores the singleton instance of the secondary database connection (MySql or SQLite) for data operations.
     *
     * @var null|MySql|SQLite|ArrayDb
     */
    static $db2 = null;

    /**
     * Reference to the in-memory array database adapter instance
     *
     * @var null|ArrayDb
     */
    static $array_db = null;


    /**
     * User timezone for date conversions
     *
     * @var string|null
     */
    static $user_timezone = null;

    /**
     * User language for translations
     *
     * @var string|null
     */
    static $user_locale = null;

    /**
     * Get the primary database connection
     *
     * Returns the default database connection managed by DatabaseManager.
     *
     * @example
     * ```php
     * // Get database connection and run a query
     * $db = Get::db();
     * $results = $db->query("SELECT * FROM users WHERE active = 1");
     * ```
     *
     * @return MySql|SQLite|ArrayDb|false Instance of the database connection
     */
    public static function db(): MySql|SQLite|ArrayDb|null
    {
        if (self::$db == null) {
            self::$db = DatabaseManager::connection('db');
        }
        return self::$db;
    }

    /**
     * Get the secondary database connection
     *
     * Returns the secondary database connection managed by DatabaseManager.
     *
     * @example
     * ```php
     * // Get secondary database connection and run a query
     * $db2 = Get::db2();
     * $results = $db2->query("SELECT * FROM data_table WHERE status = 'active'");
     * ```
     *
     * @return MySql|SQLite|ArrayDb|null Instance of the secondary database connection
     */
    public static function db2(): MySql|SQLite|ArrayDb|null
    {
        if (self::$db2 == null) {
            self::$db2 = DatabaseManager::connection('db2');
        }
        return self::$db2;
    }

    /**
     * Get a named database connection
     *
     * Returns any configured database connection by name.
     * Supports unlimited connections configured in Config or added at runtime.
     *
     * @example
     * ```php
     * // Get named connection
     * $analytics = Get::dbConnection('analytics');
     *
     * // Same as db2()
     * $db2 = Get::dbConnection('db2');
     * ```
     *
     * @param string $name Connection name
     * @return MySql|SQLite|ArrayDb Instance of the database connection
     */
    public static function dbConnection(string $name): MySql|SQLite|ArrayDb
    {
        return DatabaseManager::connection($name);
    }

    /**
     * Get the in-memory array database adapter (singleton)
     *
     * @return ArrayDb
     */
    public static function arrayDb(): ArrayDb
    {
        if (self::$array_db === null) {
            $adapter = new ArrayDb();
            $adapter->connect(new ArrayEngine());
            self::$array_db = $adapter;
        }

        return self::$array_db;
    }

    public static function schema($table, $db = null)
    {
        if ($db == null) {
            $db = self::db();
        }
      
        if ($db->getType() == 'mysql') {
            return new SchemaMysql($table, $db);
        } else  if ($db->getType() == 'sqlite') {
            return new SchemaSqlite($table, $db);
        } else {
            return null;
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
     * @return \App\Mail Instance of the mail service
     */
    public static function mail()
    {
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
            self::$mail_class->from(Config::get('mail_from', 'no-reply@' . self::getDomainForEmail()), Config::get('mail_from_name', 'Milk Admin'));
        }
        return self::$mail_class;
    }
    /**
     * Loads all module  and theme functions.
     * 
     * This method initializes all the module and theme functions
     * required for the application to work properly.
     * 
     * @example
     * ```php
     * Get::loadModules();
     * ```
     * 
     * @return void
     */
    public static function loadModules() {
        require_once(self::dirPath(LOCAL_DIR . '/functions.php'));
        
        $file_loaded = [];
        $module_loaded = []; // Nuovo array per tracciare i moduli con path completo
        
        // Carico prima i moduli da MILK_DIR (hanno priorità)
        self::loadModulesFromDir(MILK_DIR . '/Modules', $file_loaded, $module_loaded);
        
        // Carico i moduli da LOCAL_DIR solo se non sono già stati caricati da MILK_DIR
        self::loadModulesFromDir(LOCAL_DIR . '/Modules', $file_loaded, $module_loaded);
        
        Hooks::run('module_file_loaded');
        Hooks::run('modules_loaded');
        
        // Carico anche gli init.php dei plugins
        $php_files = glob(THEME_DIR.'/Plugins/*/init.php');
        foreach ($php_files as $php) {
            require $php;
        }
        
        Hooks::run('after_modules_loaded');
    }


    // Helper function to load modules from a directory
    private static function loadModulesFromDir($baseDir, &$module_loaded)
    {
        $patterns = [
            $baseDir . '/*Module.php',           // Direct Module files
            $baseDir . '/*_module.php',          // Direct _module files  
            $baseDir . '/*/*Module.php',         // Subdirectory Module files
            $baseDir . '/*/*_module.php'         // Subdirectory _module files
        ];

        // Determina il namespace base in base alla directory
       

        foreach ($patterns as $pattern) {
        
            foreach (glob($pattern) as $filename) {
                // Skip hidden files/directories
                $dir = dirname($filename);
                if (
                    substr(basename($filename), 0, 1) === '.' ||
                    (strpos($pattern, '/*/*') !== false && substr(basename($dir), 0, 1) === '.')
                ) {
                    continue;
                }

                $basename = basename($filename);

                // Crea un identificatore unico per il modulo
                // Per file in subdirectory: "SubDir/ModuleName.php"
                // Per file diretti: "ModuleName.php"
                if (strpos($pattern, '/*/*') !== false) {
                    $moduleName = basename($dir);
                    $moduleIdentifier = $moduleName . '/' . $basename;
                } else {
                    $moduleIdentifier = $basename;
                }

                // Skip if module already loaded
                if (in_array($moduleIdentifier, $module_loaded)) {
                    continue;
                }

                // Segna il modulo come caricato
                $module_loaded[] = $moduleIdentifier;

                $real_path =  self::dirPath($filename);
                $isLocalDir = (strpos($real_path, LOCAL_DIR) !== false);
                $namespacePrefix = $isLocalDir ? 'Local\\Modules\\' : 'Modules\\';

                require_once $real_path;
                // Only instantiate Module classes (not _module files)
                if (strpos($basename, 'Module.php') !== false) {
                    $class = basename($filename, '.php');

                    // Determine namespace based on directory structure
                    if (strpos($pattern, '/*/*') !== false) {
                        // Subdirectory file
                        $moduleName = basename($dir);
                        $fullClass = $namespacePrefix . $moduleName . '\\' . $class;
                    } else {
                        // Direct file
                        $fullClass = $namespacePrefix . $class;
                    }  

                    Hooks::set('module_file_loaded', function () use ($fullClass, $class) {
                        if (class_exists($fullClass)) {
                            new $fullClass();
                        } elseif (class_exists($class)) {
                            // Fallback for classes without namespace
                            new $class();
                        } else {
                            //die('Module not found: ' . $class);
                        }
                    });
                }
            }
        }
    }

    /**
     * Loads a theme plugin with the necessary variables
     * 
     * This method loads the requested theme plugin and passes the required variables to it.
     * 
     * @example
     * ```php
     * Get::themePlugin('sidebar', ['active' => 'home']);
     * ```
     * 
     * @param string $module Name of the module to load
     * @param array $variables Variables to pass to the module
     * @return string Content of the loaded module
     */
    public static function themePlugin(string $module, array $variables = [])
    {
        $module = str_replace('.php', '', $module);
        $module = str_replace('..', '', $module);
        $module_path = explode('/', $module);
        $folder = THEME_DIR . '/Plugins/' . ucfirst(reset($module_path));
        if (count($module_path) > 1) {
            $module = strtolower(end($module_path));
        } else {
            $module = 'plugin';
        }
        ob_start();
        $file = self::dirPath($folder . '/' . $module . '.php');
        if (is_file($file)) {
            extract($variables);
            require $file;
        } else {
            require self::dirPath(THEME_DIR . '/Plugins/PluginNotFound/plugin.php');
        }
        return ob_get_clean();
    }

    /**
     * Returns the secure path of a file
     * 
     * This method protects against path traversal attacks by ensuring paths
     * remain within the site directory. It also checks for milkadmin_local files
     * with the same name in the milkadmin_local directory.
     * 
     * @example
     * ```php
     * // Get secure path to a theme file
     * $require Get::dirPath(THEME_DIR.'/template_parts/sidebar.php');
     * ```
     * 
     * @param string $file Absolute path including MILK_DIR or THEME_DIR of the file to check
     * @return string Path of the file to load
     */
    public static function dirPath(string $file): string
    {
        $filename = basename($file);

        // block trivial traversal or null-byte attempts
        if (strpos($file, "\0") !== false) return '';
        if ($filename === '') return '';

        $localRoot = realpath(LOCAL_DIR);
        $milkRoot  = realpath(MILK_DIR);

        if (!$localRoot || !$milkRoot) {
            return ''; // invalid roots, security first
        }

        // Normalize the input directory
        $dir = realpath(dirname($file));  

        // If dirname is invalid, consider only filename
        if ($dir === false) {
            $relative = $filename;
        } else {
            // get relative path from root
            if (strpos($dir, $localRoot) === 0) {
                $relative = ltrim(substr($dir, strlen($localRoot)), "/\\") . "/" . $filename;
            } elseif (strpos($dir, $milkRoot) === 0) {
                $relative = ltrim(substr($dir, strlen($milkRoot)), "/\\") . "/" . $filename;
            } else {
                return ''; // out of roots ⇒ block
            }
        }

        // Build final candidates
        $localFile = realpath($localRoot . "/" . $relative);
        $milkFile  = realpath($milkRoot  . "/" . $relative);

        // Prefer LOCAL
        if ($localFile && is_file($localFile) && strpos($localFile, $localRoot) === 0) {
            return $localFile;
        }

        if ($milkFile && is_file($milkFile) && strpos($milkFile, $milkRoot) === 0) {
            return $milkFile;
        }

        return '';
    }

    /**
     * Returns the URI path for resources like JS or CSS files
     * 
     * This method generates the correct URI path for web resources,
     * handling theme milkadmin_locals and proper URL formatting.
     * 
     * @example
     * ```php
     * <img src="<?php echo Get::uriPath($path); ?>" alt="Logo">
     * ```
     * 
     * @param string $file The file to load
     * @return string The absolute URI path of the file
     */
    public static function uriPath(string $file): string
    {
        $file_without_root = str_replace(THEME_URL, '', $file);
        if ($file == $file_without_root) {
            return $file;
        } 
        // aggiungo la cartella milkadmin_local 
        $theme_dir = Config::get('theme_dir', 'default');

        if (is_file(MILK_DIR . '/milkadmin_local/' . $theme_dir  . $file_without_root)) {
            $file = Config::get('base_url') . '/milkadmin_local/' . $theme_dir  . $file_without_root;
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
     * $path = Get::tempDir().$file;
     * ```
     * 
     * @return string The path of the temporary directory with trailing slash
     */
    public static function tempDir(): mixed
    {
        $temp = Config::get('temp_dir', LOCAL_DIR . "/temp/");
        if (!is_dir($temp)) {
            mkdir($temp, 0755, true);
        }
        return realpath($temp);
    }

    /**
     * Returns the current date and time based on configured timezone
     * 
     * Creates a DateTime object using the timezone settings from the configuration file.
     * 
     * @example
     * ```php
     * $now = Get::dateTimeZone();
     * echo $now->format('Y-m-d H:i:s');
     * ```
     * 
     * @return \DateTime DateTime object with the configured timezone
     */
    public static function dateTimeZone()
    {
        $now = new \DateTime();
        $now->setTimezone(new \DateTimeZone(Get::userTimezone()));
        return new \DateTime($now->format('Y-m-d H:i:s'));
    }


     /**
     * Get user timezone for date conversions
     * Falls back to authenticated user timezone, then global config
     *
     * @return string Timezone identifier
     */
    public static function userTimezone(): string
    {
        if (!Config::get('use_user_timezone', false)) {
            return 'UTC';
        }
        // 1. Check if explicitly set on this model instance
        if (self::$user_timezone !== null) {
            return self::$user_timezone;
        }
        
        // 2. Try to get from authenticated user
        try {
            $auth = Get::make('Auth');
            $user = $auth->getUser();
            if ($user && isset($user->timezone)) {
                self::$user_timezone = $user->timezone;
                return $user->timezone;
            }
        } catch (\Exception $e) {
            // Auth service not available or user not logged in
        }
        
        // 3. Fallback to global config
        return 'UTC';
    }

     /**
     * Set user timezone for date conversions
     *
     * @param string|null $timezone Timezone identifier (e.g., 'Europe/Rome', 'America/New_York')
     */
    public static function setUserTimezone(?string $timezone) {
        self::$user_timezone = $timezone;
    }

    public static function userLocale(): string
    {
        if (!Config::get('available_locales', false)) {
            return Config::get('locale', 'en_US');
        }
        // 1. Check if explicitly set on this model instance
        if (self::$user_locale !== null) {
            return self::$user_locale;
        }
        
        // 2. Try to get from authenticated user
        try {
            $auth = Get::make('Auth');
            $user = $auth->getUser();
            if ($user && isset($user->locale)) {
                self::$user_locale = $user->locale;
                return $user->locale;
            }
        } catch (\Exception $e) {
            // Auth service not available or user not logged in
        }
        
        // 3. Fallback to global config
        return Config::get('locale', 'en_US');
    }

    public static function setUserLocale(?string $locale) {
        self::$user_locale = $locale;
    }


    /**
     * Format a date based on the system settings using locale
     *
     * Converts a date string to the specified format according to system locale configuration.
     * Uses DateTime::format with a locale-aware format mapping.
     *
     * @example
     * ```php
     * $formatted_date = Get::formatDate('2021-01-01', 'date');
     * // With locale 'it_IT': "01/11/2021"
     * // With locale 'en_US': "11/01/2021"
     * ```
     *
     * @param string|null|\DateTime $date The date to format (in MySQL format or DateTime object)
     * @param string $format The format to use: 'date' (only date), 'time' (only time), or 'datetime' (both)
     * @param bool $timezone Whether to convert to user timezone
     * @return string The formatted date
     */
    public static function formatDate(string|null|\DateTime $date, string $format = 'date', bool $timezone = false): string {
        if ($date === null) {
            return '';
        }

        if ($date === '0000-00-00 00:00:00' || $date === '0000-00-00' || $date === '00:00:00' || $date === '00:00' || $date === '0000-00-00 00:00') {
            return '';
        }

        // Convert string to DateTime if needed
        if (is_string($date)) {
            $cleaned_date = strip_tags($date);
            try {
                $date = new \DateTime($cleaned_date);
            } catch (\Exception $e) {
                // If DateTime creation fails, return the original string
                return $date;
            }
        }

        // At this point, $date should be a DateTime object
        if (!($date instanceof \DateTime)) {
            return is_string($date) ? $date : '';
        }

        // Clone to avoid modifying the original
        $dateClone = clone $date;

        // Apply timezone conversion if requested
        if ($timezone) {
            $dateClone->setTimezone(new \DateTimeZone(Get::userTimezone()));
        }

        // Get locale from config
        $locale = Config::get('locale', 'en_US');
        $localeKey = preg_split('/[.@]/', $locale)[0] ?? $locale;

        $dateFormat = 'Y-m-d';
        $timeFormat = 'H:i';

        $dateFormats = [
            'en_US' => 'm/d/Y',
            'en_GB' => 'd/m/Y',
            'it_IT' => 'd/m/Y',
            'fr_FR' => 'd/m/Y',
            'es_ES' => 'd/m/Y',
            'pt_PT' => 'd/m/Y',
            'pt_BR' => 'd/m/Y',
            'de_DE' => 'd.m.Y',
        ];

        $timeFormats = [
            'en_US' => 'h:i A',
            'en_GB' => 'H:i',
            'it_IT' => 'H:i',
            'fr_FR' => 'H:i',
            'es_ES' => 'H:i',
            'pt_PT' => 'H:i',
            'pt_BR' => 'H:i',
            'de_DE' => 'H:i',
        ];

        if (isset($dateFormats[$localeKey])) {
            $dateFormat = $dateFormats[$localeKey];
        }

        if (isset($timeFormats[$localeKey])) {
            $timeFormat = $timeFormats[$localeKey];
        }

        if ($format == 'date') {
            return $dateClone->format($dateFormat);
        }

        if ($format == 'time') {
            return $dateClone->format($timeFormat);
        }

        return $dateClone->format($dateFormat . ' ' . $timeFormat);
    }

    /**
     * Return user object by ID or current user if ID is 0
     */
    public static function user($id = 0) {
        if ($id == 0) {
            return Get::make('Auth')->getUser();
        } else {
            return Get::make('Auth')->getUser($id);
        }
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

    public static function bind($service_name, $implementation,  $singleton = false, $arguments = [])
    {
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
     * $config = Get::make('config', []);
     * $logger = Get::make('logger', ['app.log']);
     * ```
     * 
     * @param string $name Name of the service to instantiate
     * @param array $arguments Arguments to pass to the service constructor/factory
     * @return mixed The requested service instance or null if not found
     */
    public static function make($name, $arguments = [])
    {
        $service = DependencyContainer::get($name, $arguments);

        // Allow both objects and strings (strings are static class names)
        if ($service) {
            return $service;
        }

        // Service not found
        throw new \Exception("Service not found: $name");
    }

    /**
     * Checks if a service is registered in the container
     * 
     * Example:
     * ```php
     * if (Get::has('auth')) {
     *     $db = Get::make('Auth');
     * } else {
     *     // Handle missing service
     * }
     * ```
     * 
     * @param string $name Name of the service to check
     * @return bool True if the service is registered, false otherwise
     */
    public static function has($name)
    {
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
    public static function clientIp($trust_proxy_headers = false)
    {
        if (Cli::isCli()) return 'CLI';
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
                        if (self::validateIp($ip)) {
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
        if (!self::validateIp($ip_address)) {
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
    private static function validateIp($ip)
    {
        // Rimuovi eventuali porte
        if (strpos($ip, ':') !== false && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ip = explode(':', $ip)[0];
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    }

    private static function getDomainForEmail()
    {
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
            return $parts[$count - 2] . '.' . $parts[$count - 1];
        }

        return $domain;
    }

    public static function closeConnections() {
        if (self::$db !== null) {
            self::$db->close();
            self::$db = null;
        }
        if (self::$db2 !== null) {
            self::$db2->close();
            self::$db2 = null;
        }
    }
}
