<?php
namespace App;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Settings Class - A flexible configuration management system
 * 
 * This class provides a comprehensive solution for managing application settings
 * organized into groups, with automatic file-based persistence using JSON format.
 * 
 * Key features:
 * - Group-based organization: Settings can be organized into logical groups
 * - Lazy loading: Data is loaded from files only when needed
 * - Memory caching: Once loaded, data remains in memory for performance
 * - Automatic persistence: Only modified groups are saved to disk
 * - Search functionality: Search by key names or values across groups
 * - Safe file handling: Group names are sanitized to prevent directory traversal
 * 
 * File structure:
 * - Each group is stored as a separate JSON file in the storage/ directory
 * - Files are named using the sanitized group name with .json extension
 * - Default group is stored as 'default.json'
 * 
 * Usage examples:
 * 
 * Basic operations:
 * Settings::set('database_host', 'localhost');
 * $host = Settings::get('database_host');
 * 
 * Working with groups:
 * Settings::set('smtp_server', 'mail.example.com', 'email');
 * $smtp = Settings::get('smtp_server', 'email');
 * 
 * Searching:
 * $results = Settings::searchByValue('localhost');
 * $keys = Settings::searchByKey('database');
 * 
 * Persistence:
 * Settings::save(); // Saves all modified groups to disk
 * 
 * @package     App
 */
class Settings
{
    /**
     * Array to keep loaded data in memory
     * Structure: ['group_name' => ['key' => 'value', ...], ...]
     * 
     * @var array<string, array<string, mixed>>
     */
    private static array $data = [];
    
    /**
     * Array to track which groups need to be saved
     * Structure: ['group_name' => true, ...]
     * 
     * @var array<string, bool>
     */
    private static array $modified_groups = [];
    
    /**
     * Default group name used when no group is specified
     * 
     * @var string
     */
    private const DEFAULT_GROUP = 'default';

    /**
     * Sanitizes group name using regular expression
     * Allows only letters, numbers, underscores and hyphens
     * 
     * This method prevents directory traversal attacks and ensures
     * valid filenames across different operating systems.
     * 
     * Examples:
     * - 'user-settings' → 'user-settings' (valid)
     * - 'admin_config' → 'admin_config' (valid)
     * - '../../../etc/passwd' → 'etcpasswd' (sanitized)
     * - 'config@#$%' → 'config' (special chars removed)
     * 
     * @param string $group The group name to sanitize
     * @return string The sanitized group name
     */
    private static function sanitizeGroupName(string $group): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $group);
    }
    
    /**
     * Gets the complete file path for a group's JSON file
     * 
     * Combines the storage path with the sanitized group name
     * and adds the .json extension.
     * 
     * Examples:
     * - getFilePath('database') → 'storage/database.json'
     * - getFilePath('user-prefs') → 'storage/user-prefs.json'
     * - getFilePath('admin@config') → 'storage/adminconfig.json'
     * 
     * @param string $group The group name
     * @return string The complete file path
     */
    private static function getFilePath(string $group): string
    {
        $clean_group = self::sanitizeGroupName($group);
        return STORAGE_DIR ."/". $clean_group . '.json';
    }
    
    /**
     * Loads data from file if not already in memory
     * 
     * This method implements lazy loading - data is only loaded when needed.
     * If the JSON file exists, it's parsed and stored in memory. If parsing
     * fails or the file doesn't exist, an empty array is initialized.
     * 
     * The method handles various scenarios:
     * - File exists and contains valid JSON: Data is loaded
     * - File exists but contains invalid JSON: Empty array is initialized
     * - File doesn't exist: Empty array is initialized
     * - Data already in memory: No action taken (performance optimization)
     * 
     * @param string $group The group name to load
     * @return void
     */
    private static function loadGroupData(string $group): void
    {
        $clean_group = self::sanitizeGroupName($group);
        
        // If data is already in memory, don't reload
        if (isset(self::$data[$clean_group])) {
            return;
        }
        
        $file_path = self::getFilePath($group);
        
        // If file exists, load the data
        if (file_exists($file_path)) {
            $json_content = File::getContents($file_path);
            $data = json_decode($json_content, true);
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                // If there's a JSON parsing error, initialize with empty array
                self::$data[$clean_group] = [];
            } else {
                self::$data[$clean_group] = $data ?: [];
            }
        } else {
            // If file doesn't exist, initialize with empty array
            self::$data[$clean_group] = [];
        }
    }
    
    /**
     * Gets a value from the specified group
     * 
     * Retrieves a setting value by key from a specific group.
     * If the group is not specified, uses the default group.
     * Returns null if the key doesn't exist.
     * 
     * Examples:
     * - Settings::get('app_name') → 'My Application' (from default group)
     * - Settings::get('smtp_host', 'email') → 'smtp.gmail.com' (from email group)
     * - Settings::get('nonexistent') → null (key doesn't exist)
     * 
     * @param string $key The setting key to retrieve
     * @param string|null $group The group name (uses default if null)
     * @return mixed|null The setting value or null if not found
     */
    public static function get(string $key, ?string $group = null): mixed
    {
        $group = $group ?: self::DEFAULT_GROUP;
        self::loadGroupData($group);
        
        $clean_group = self::sanitizeGroupName($group);
        
        return isset(self::$data[$clean_group][$key]) 
            ? self::$data[$clean_group][$key] 
            : null;
    }
    
    /**
     * Sets a value in the specified group
     * 
     * Stores a setting value with the given key in the specified group.
     * If the group is not specified, uses the default group.
     * Marks the group as modified for later persistence.
     * 
     * Examples:
     * - Settings::set('app_name', 'My App') → stores in default group
     * - Settings::set('smtp_port', 587, 'email') → stores in email group
     * - Settings::set('config', ['key' => 'value']) → stores array
     * - Settings::set('enabled', true) → stores boolean
     * 
     * @param string $key The setting key
     * @param mixed $value The setting value (can be any JSON-serializable type)
     * @param string|null $group The group name (uses default if null)
     * @return void
     */
    public static function set(string $key, mixed $value, ?string $group = null): void
    {
        $group = $group ?: self::DEFAULT_GROUP;
        self::loadGroupData($group);
        
        $clean_group = self::sanitizeGroupName($group);
        
        // Set the value
        self::$data[$clean_group][$key] = $value;
        
        // Mark the group as modified (to be saved)
        self::$modified_groups[$clean_group] = true;
    }
    
    /**
     * Gets all data from a group
     * 
     * Returns all key-value pairs from the specified group as an associative array.
     * If the group is not specified, uses the default group.
     * Returns an empty array if the group has no data.
     * 
     * Examples:
     * - Settings::getAll() → ['app_name' => 'My App', 'version' => '1.0']
     * - Settings::getAll('email') → ['smtp_host' => 'smtp.gmail.com', 'port' => 587]
     * - Settings::getAll('nonexistent') → []
     * 
     * @param string|null $group The group name (uses default if null)
     * @return array<string, mixed> All key-value pairs from the group
     */
    public static function getAll(?string $group = null): array
    {
        $group = $group ?: self::DEFAULT_GROUP;
        self::loadGroupData($group);
        
        $clean_group = self::sanitizeGroupName($group);
        
        return self::$data[$clean_group] ?: [];
    }
    
    /**
     * Searches for a value in a specific group or all groups
     * 
     * Performs a case-insensitive search for the given value across settings.
     * Can search within a specific group or across all available groups.
     * Handles different data types: strings (substring match), arrays (recursive search),
     * and numbers (exact match).
     * 
     * When searching all groups, it loads all available JSON files from storage.
     * 
     * Examples:
     * - Settings::searchByValue('gmail') → finds all settings containing 'gmail'
     * - Settings::searchByValue('localhost', 'database') → searches only in database group
     * - Settings::searchByValue(587) → finds exact numeric matches
     * 
     * Return format:
     * [
     *     'group_name' => [
     *         'setting_key' => 'setting_value',
     *         ...
     *     ],
     *     ...
     * ]
     * 
     * @param mixed $search_value The value to search for
     * @param string|null $group The group to search in (searches all if null)
     * @return array<string, array<string, mixed>> Search results organized by group
     */
    public static function searchByValue(mixed $search_value, ?string $group = null): array
    {
        $results = [];
        
        if ($group !== null) {
            // Search only in the specified group
            self::loadGroupData($group);
            $clean_group = self::sanitizeGroupName($group);
            
            if (isset(self::$data[$clean_group])) {
                foreach (self::$data[$clean_group] as $key => $value) {
                    if (self::valueContains($value, $search_value)) {
                        $results[$clean_group][$key] = $value;
                    }
                }
            }
        } else {
            // Search in all groups
            // First load all existing files in the storage folder
            if (is_dir(STORAGE_DIR)) {
                $files = glob(STORAGE_DIR . '/*.json');
                foreach ($files as $file) {
                    $group_name = basename($file, '.json');
                    self::loadGroupData($group_name);
                }
            }
            
            // Now search in all loaded data
            foreach (self::$data as $group_name => $group_data) {
                foreach ($group_data as $key => $value) {
                    if (self::valueContains($value, $search_value)) {
                        $results[$group_name][$key] = $value;
                    }
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Searches for a specific key in a group or all groups
     * 
     * Performs a case-insensitive substring search on setting keys.
     * Can search within a specific group or across all available groups.
     * Useful for finding related settings or discovering available configuration options.
     * 
     * Examples:
     * - Settings::searchByKey('database') → finds 'database_host', 'database_port', etc.
     * - Settings::searchByKey('smtp', 'email') → finds SMTP-related keys in email group
     * - Settings::searchByKey('_timeout') → finds all timeout-related settings
     * 
     * Return format:
     * [
     *     'group_name' => [
     *         'matching_key' => 'value',
     *         ...
     *     ],
     *     ...
     * ]
     * 
     * @param string $search_key The key pattern to search for
     * @param string|null $group The group to search in (searches all if null)
     * @return array<string, array<string, mixed>> Search results organized by group
     */
    public static function searchByKey(string $search_key, ?string $group = null): array
    {
        $results = [];
        
        if ($group !== null) {
            // Search only in the specified group
            self::loadGroupData($group);
            $clean_group = self::sanitizeGroupName($group);
            
            if (isset(self::$data[$clean_group])) {
                foreach (self::$data[$clean_group] as $key => $value) {
                    if (stripos($key, $search_key) !== false) {
                        $results[$clean_group][$key] = $value;
                    }
                }
            }
        } else {
            // Search in all groups
            if (is_dir(STORAGE_DIR)) {
                $files = glob(STORAGE_DIR . '/*.json');
                foreach ($files as $file) {
                    $group_name = basename($file, '.json');
                    self::loadGroupData($group_name);
                }
            }
            
            foreach (self::$data as $group_name => $group_data) {
                foreach ($group_data as $key => $value) {
                    if (stripos($key, $search_key) !== false) {
                        $results[$group_name][$key] = $value;
                    }
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Helper function to recursively search within a value
     * 
     * This method handles different data types for value searching:
     * - Strings: Case-insensitive substring search
     * - Arrays: Recursive search through all elements
     * - Numbers: Exact match comparison (converted to string)
     * - Other types: No match (returns false)
     * 
     * Examples:
     * - valueContains('hello world', 'WORLD') → true (case-insensitive)
     * - valueContains(['a', 'b', 'test'], 'TEST') → true (found in array)
     * - valueContains(123, '123') → true (numeric match)
     * - valueContains(['nested' => ['deep' => 'value']], 'value') → true (recursive)
     * 
     * @param mixed $value The value to search within
     * @param mixed $search_value The value to search for
     * @return bool True if the search value is found, false otherwise
     */
    private static function valueContains(mixed $value, mixed $search_value): bool
    {
        if (is_string($value)) {
            return stripos($value, $search_value) !== false;
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                if (self::valueContains($item, $search_value)) {
                    return true;
                }
            }
        } elseif (is_numeric($value)) {
            return strval($value) == strval($search_value);
        }
        
        return false;
    }
    
    /**
     * Saves all groups marked as modified to their respective JSON files
     * 
     * This method implements efficient persistence by only saving groups that have
     * been modified since the last save. It creates the storage directory if it
     * doesn't exist and writes JSON files with pretty formatting and Unicode support.
     * 
     * After successful saving, the modified flags are cleared to prevent unnecessary
     * future saves until the data changes again.
     * 
     * JSON formatting options:
     * - JSON_PRETTY_PRINT: Makes files human-readable
     * - JSON_UNESCAPED_UNICODE: Preserves Unicode characters
     * 
     * Examples:
     * - After Settings::set('key', 'value'), call Settings::save() to persist
     * - Typically called at the end of request processing
     * - Can be called multiple times safely (only saves what's modified)
     * 
     * @return void
     */
    public static function save(): void
    {
        // Create storage folder if it doesn't exist
        if (!is_dir(STORAGE_DIR)) {
            mkdir(STORAGE_DIR, 0755, true);
        }
        
        // Save only groups that have been modified
        foreach (self::$modified_groups as $group => $is_modified) {
            if ($is_modified && isset(self::$data[$group])) {
                $file_path = self::getFilePath($group);
                $json_content = json_encode(self::$data[$group], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                if (File::putContents($file_path, $json_content) !== false) {
                    // Remove the modified flag after successful save
                    unset(self::$modified_groups[$group]);
                }
            }
        }
    }
    
    /**
     * Removes a key from a group
     * 
     * Deletes a specific setting key and its value from the specified group.
     * If the group is not specified, uses the default group.
     * Marks the group as modified if the key existed.
     * 
     * Examples:
     * - Settings::removeKey('old_setting') → removes from default group
     * - Settings::removeKey('temp_config', 'cache') → removes from cache group
     * - Settings::removeKey('nonexistent') → no effect, doesn't error
     * 
     * @param string $key The key to remove
     * @param string|null $group The group name (uses default if null)
     * @return void
     */
    public static function removeKey(string $key, ?string $group = null): void
    {
        $group = $group ?: self::DEFAULT_GROUP;
        self::loadGroupData($group);
        
        $clean_group = self::sanitizeGroupName($group);
        
        if (isset(self::$data[$clean_group][$key])) {
            unset(self::$data[$clean_group][$key]);
            self::$modified_groups[$clean_group] = true;
        }
    }
    
    /**
     * Completely empties a group
     * 
     * Removes all settings from the specified group, effectively resetting it
     * to an empty state. If the group is not specified, clears the default group.
     * Marks the group as modified for persistence.
     * 
     * Examples:
     * - Settings::clearGroup() → clears default group
     * - Settings::clearGroup('cache') → clears all cache settings
     * - Settings::clearGroup('temp') → removes all temporary settings
     * 
     * @param string|null $group The group name (uses default if null)
     * @return void
     */
    public static function clearGroup(?string $group = null): void
    {
        $group = $group ?: self::DEFAULT_GROUP;
        $clean_group = self::sanitizeGroupName($group);
        
        self::$data[$clean_group] = [];
        self::$modified_groups[$clean_group] = true;
    }
    
    /**
     * Checks if a key exists in a group
     * 
     * Verifies whether a specific setting key exists in the given group.
     * If the group is not specified, checks in the default group.
     * Useful for conditional logic before getting or setting values.
     * 
     * Examples:
     * - Settings::hasKey('app_name') → true if exists in default group
     * - Settings::hasKey('smtp_host', 'email') → true if exists in email group
     * - Settings::hasKey('nonexistent') → false
     * 
     * @param string $key The key to check for
     * @param string|null $group The group name (uses default if null)
     * @return bool True if the key exists, false otherwise
     */
    public static function hasKey(string $key, ?string $group = null): bool
    {
        $group = $group ?: self::DEFAULT_GROUP;
        self::loadGroupData($group);
        
        $clean_group = self::sanitizeGroupName($group);
        
        return isset(self::$data[$clean_group][$key]);
    }
}