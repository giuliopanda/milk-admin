<?php
namespace MilkCore;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Static file handling class with mandatory file locking
 * 
 * This class provides thread-safe file operations using exclusive locks.
 * All operations are atomic and prevent file corruption during concurrent access.
 * 
 * @example
 * // Write data safely
 * if (File::put_contents('data.txt', 'Hello World')) {
 *     echo "File written successfully";
 * } else {
 *     echo "Error: " . File::get_last_error();
 * }
 * 
 * // Read data safely
 * $content = File::get_contents('data.txt');
 * if ($content !== false) {
 *     echo "Content: " . $content;
 * } else {
 *     echo "Error: " . File::get_last_error();
 * }
 */
class File {
    
    /**
     * Appends data to a file with mandatory exclusive locking
     * 
     * This method appends the provided data to the end of an existing file,
     * or creates a new file if it doesn't exist. It uses exclusive locking
     * to prevent concurrent writes and data corruption during append operations.
     * 
     * @param string $file_path The path to the file to append to
     * @param string $data The data to append to the file
     * 
     * @return bool True on successful append, false on failure
     * 
     * @example
     * // Logging to a file
     * $log_entry = date('Y-m-d H:i:s') . " - User logged in\n";
     * if (File::append_contents('app.log', $log_entry)) {
     *     echo "Log entry added successfully";
     * } else {
     *     echo "Failed to write log: " . File::get_last_error();
     * }
     * 
     * @example
     * // Adding items to a list file
     * $new_item = "Item #" . rand(1, 1000) . "\n";
     * if (File::append_contents('items.txt', $new_item)) {
     *     echo "Item added to list";
     * }
     * 
     * @example
     * // CSV data appending
     * $csv_row = "John,Doe,30,Engineer\n";
     * if (File::append_contents('users.csv', $csv_row)) {
     *     echo "User data appended to CSV";
     * }
     * 
     * @example
     * // Multiple appends in sequence
     * $messages = [
     *     "Message 1\n",
     *     "Message 2\n", 
     *     "Message 3\n"
     * ];
     * foreach ($messages as $message) {
     *     File::append_contents('messages.txt', $message);
     * }
     * 
     */
    public static function append_contents($file_path, $data) {
        self::$last_error = '';
        
        $fp = fopen($file_path, 'c');
        if (!$fp) {
            self::$last_error = "Cannot open file for appending: $file_path";
            return false;
        }
        
        if (!self::wait_lock($fp, true)) {
            fclose($fp);
            self::$last_error = "Timeout acquiring append lock: $file_path";
            return false;
        }
        
        // Move to end of file for appending
        fseek($fp, 0, SEEK_END);
        $result = fwrite($fp, $data);
        flock($fp, LOCK_UN);
        fclose($fp);
        
        if ($result === false) {
            self::$last_error = "Error appending file content: $file_path";
            return false;
        }
        
        return true;
    }
    
    /**
     * Stores the last error message that occurred during file operations
     * 
     * @var string
     */
    private static $last_error = '';
    
    /**
     * Reads the entire file content with mandatory file locking
     * 
     * This method opens the file with a shared lock to ensure data consistency
     * during read operations. It waits up to 10 seconds to acquire the lock.
     * 
     * @param string $file_path The path to the file to read
     * 
     * @return string|false The file content as string on success, false on failure
     * 
     * @example
     * $content = File::get_contents('/path/to/file.txt');
     * if ($content !== false) {
     *     echo "File content: " . $content;
     * } else {
     *     echo "Read failed: " . File::get_last_error();
     * }
     * 
     * @example
     * // Reading a JSON configuration file
     * $config_json = File::get_contents('config.json');
     * if ($config_json !== false) {
     *     $config = json_decode($config_json, true);
     * }
     * 
     */
    public static function get_contents($file_path) {
        self::$last_error = '';
        
        if (!file_exists($file_path)) {
            self::$last_error = "File does not exist: $file_path";
            return false;
        }
        
        $fp = fopen($file_path, 'r');
        if (!$fp) {
            self::$last_error = "Cannot open file for reading: $file_path";
            return false;
        }
        
        if (!self::wait_lock($fp, false)) {
            fclose($fp);
            self::$last_error = "Timeout acquiring read lock: $file_path";
            return false;
        }
        
        $content = fread($fp, filesize($file_path));
        flock($fp, LOCK_UN);
        fclose($fp);
        
        if ($content === false) {
            self::$last_error = "Error reading file content: $file_path";
        }
        
        return $content;
    }
    
    /**
     * Writes data to a file with mandatory exclusive locking
     * 
     * This method creates or overwrites the file with the provided data.
     * It uses exclusive locking to prevent concurrent writes and data corruption.
     * The file is truncated before writing to ensure clean content.
     * 
     * @param string $file_path The path to the file to write
     * @param string $data The data to write to the file
     * 
     * @return bool True on successful write, false on failure
     * 
     * @example
     * if (File::put_contents('log.txt', "Error occurred at " . date('Y-m-d H:i:s'))) {
     *     echo "Log entry written successfully";
     * } else {
     *     echo "Failed to write log: " . File::get_last_error();
     * }
     * 
     * @example
     * // Writing JSON data
     * $data = ['user' => 'john', 'timestamp' => time()];
     * $json = json_encode($data);
     * if (File::put_contents('user_data.json', $json)) {
     *     echo "User data saved";
     * }
     * 
     * @example
     * // Appending to a file (manual approach)
     * $existing = File::get_contents('messages.txt');
     * $new_content = $existing . "\nNew message";
     * File::put_contents('messages.txt', $new_content);
     * 
     */
    public static function put_contents($file_path, $data) {
        self::$last_error = '';
        
        $fp = fopen($file_path, 'c');
        if (!$fp) {
            self::$last_error = "Cannot open file for writing: $file_path";
            return false;
        }
        
        if (!self::wait_lock($fp, true)) {
            fclose($fp);
            self::$last_error = "Timeout acquiring write lock: $file_path";
            return false;
        }
        
        ftruncate($fp, 0);
        rewind($fp);
        $result = fwrite($fp, $data);
        flock($fp, LOCK_UN);
        fclose($fp);
        
        if ($result === false) {
            self::$last_error = "Error writing file content: $file_path";
            return false;
        }
        
        return true;
    }
    
    /**
     * Waits to acquire a lock on a file handle
     * 
     * This method attempts to acquire either an exclusive lock (LOCK_EX) for writing
     * or a shared lock (LOCK_SH) for reading on the given file handle using 
     * non-blocking calls. It retries up to 200 times with 50ms intervals, 
     * providing a total timeout of 10 seconds.
     * 
     * Shared locks allow multiple readers but block writers.
     * Exclusive locks block all other access (readers and writers).
     * 
     * @param resource $fp The file handle to lock
     * @param bool $exclusive True for exclusive lock (LOCK_EX), false for shared lock (LOCK_SH)
     * 
     * @return bool True if lock was successfully acquired, false on timeout
     * 
     * @example
     * // Exclusive lock for writing
     * $fp = fopen('data.txt', 'c');
     * if (File::wait_lock($fp, true)) {
     *     fwrite($fp, 'Exclusive write access');
     *     flock($fp, LOCK_UN);
     * }
     * fclose($fp);
     * 
     * @example
     * // Shared lock for reading
     * $fp = fopen('data.txt', 'r');
     * if (File::wait_lock($fp, false)) {
     *     $content = fread($fp, filesize('data.txt'));
     *     flock($fp, LOCK_UN);
     * }
     * fclose($fp);
     * 
     * @see flock() For more information about file locking
     * @see LOCK_EX For exclusive locks
     * @see LOCK_SH For shared locks
     */
    public static function wait_lock($fp, $exclusive = true) {
        $lock_type = $exclusive ? LOCK_EX : LOCK_SH;
        
        for ($x = 0; $x < 200; $x++) { 
            if (flock($fp, $lock_type | LOCK_NB)) {
                return true;
            }
            usleep(50000); // 50ms delay between attempts
        }
        return false;
    }
    
    /**
     * Retrieves the last error message
     * 
     * Returns the error message from the most recent file operation that failed.
     * The error message is automatically cleared when a new operation starts.
     * 
     * @return string The last error message, empty string if no error occurred
     * 
     * @example
     * if (!File::put_contents('readonly.txt', 'data')) {
     *     $error = File::get_last_error();
     *     error_log("File operation failed: $error");
     *     echo "Operation failed. Check logs for details.";
     * }
     * 
     * @example
     * // Error handling in a loop
     * $files = ['file1.txt', 'file2.txt', 'file3.txt'];
     * foreach ($files as $file) {
     *     if (!File::put_contents($file, 'test data')) {
     *         echo "Failed to write $file: " . File::get_last_error() . "\n";
     *     }
     * }
     * 
     */
    public static function get_last_error() {
        return self::$last_error;
    }
}