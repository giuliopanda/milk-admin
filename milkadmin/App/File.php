<?php
namespace App;

use App\Exceptions\FileException;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Static file handling class with mandatory file locking
 *
 * This class provides thread-safe file operations using exclusive locks.
 * All operations are atomic and prevent file corruption during concurrent access.
 *
 * @example
 * // Write data safely
 * try {
 *     File::putContents('data.txt', 'Hello World');
 *     echo "File written successfully";
 * } catch (FileException $e) {
 *     echo "Error: " . $e->getMessage();
 * }
 *
 * // Read data safely
 * try {
 *     $content = File::getContents('data.txt');
 *     echo "Content: " . $content;
 * } catch (FileException $e) {
 *     echo "Error: " . $e->getMessage();
 * }
 *
 * @package App
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
     * @return void
     * @throws FileException If file cannot be opened or written or If lock cannot be acquired within timeout
     *
     * @example
     * // Logging to a file
     * $log_entry = date('Y-m-d H:i:s') . " - User logged in\n";
     * try {
     *     File::appendContents('app.log', $log_entry);
     *     echo "Log entry added successfully";
     * } catch (FileException $e) {
     *     echo "Failed to write log: " . $e->getMessage();
     * }
     *
     * @example
     * // Adding items to a list file
     * $new_item = "Item #" . rand(1, 1000) . "\n";
     * File::appendContents('items.txt', $new_item);
     *
     * @example
     * // CSV data appending
     * $csv_row = "John,Doe,30,Engineer\n";
     * File::appendContents('users.csv', $csv_row);
     */
    public static function appendContents(string $file_path, string $data): void {
        $fp = fopen($file_path, 'c');
        if (!$fp) {
            throw new FileException("Cannot open file for appending: $file_path");
        }

        if (!self::waitLock($fp, true)) {
            fclose($fp);
            throw new FileException("Timeout acquiring append lock: $file_path");
        }

        // Move to end of file for appending
        fseek($fp, 0, SEEK_END);
        $result = fwrite($fp, $data);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($result === false) {
            throw new FileException("Error appending file content: $file_path");
        }
    }

    /**
     * Reads the entire file content with mandatory file locking
     *
     * This method opens the file with a shared lock to ensure data consistency
     * during read operations. It waits up to 10 seconds to acquire the lock.
     *
     * @param string $file_path The path to the file to read
     * @return string The file content
     * @throws FileException If file does not exist, cannot be opened, or read fails
     *
     * @example
     * try {
     *     $content = File::getContents('/path/to/file.txt');
     *     echo "File content: " . $content;
     * } catch (FileException $e) {
     *     echo "Read failed: " . $e->getMessage();
     * }
     *
     * @example
     * // Reading a JSON configuration file
     * try {
     *     $config_json = File::getContents('config.json');
     *     $config = json_decode($config_json, true);
     * } catch (FileException $e) {
     *     $config = []; // Use default config
     * }
     */
    public static function getContents(string $file_path): string {
        if (!file_exists($file_path)) {
            throw new FileException("File does not exist: $file_path");
        }

        $fp = fopen($file_path, 'r');
        if (!$fp) {
            throw new FileException("Cannot open file for reading: $file_path");
        }

        if (!self::waitLock($fp, false)) {
            fclose($fp);
            throw new FileException("Timeout acquiring read lock: $file_path");
        }

        $content = fread($fp, filesize($file_path));
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($content === false) {
            throw new FileException("Error reading file content: $file_path");
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
     * @return void
     * @throws FileException If directory is not writable or file cannot be opened/written
     *
     * @example
     * try {
     *     File::putContents('log.txt', "Error occurred at " . date('Y-m-d H:i:s'));
     *     echo "Log entry written successfully";
     * } catch (FileException $e) {
     *     echo "Failed to write log: " . $e->getMessage();
     * }
     *
     * @example
     * // Writing JSON data
     * $data = ['user' => 'john', 'timestamp' => time()];
     * $json = json_encode($data);
     * File::putContents('user_data.json', $json);
     *
     * @example
     * // Replacing entire file content
     * $existing = File::getContents('messages.txt');
     * $new_content = $existing . "\nNew message";
     * File::putContents('messages.txt', $new_content);
     */
    public static function putContents(string $file_path, string $data): void {
        // Check directory permissions first
        $dir = dirname($file_path);
        if (!is_writable($dir)) {
            \App\MessagesHandler::addError("Permission denied writing to: " . basename($file_path), 'file_permissions');
            throw new FileException("Directory not writable: $dir. Please check permissions.");
        }

        $fp = fopen($file_path, 'c');
        if (!$fp) {
            \App\MessagesHandler::addError("Failed to open file: " . basename($file_path) . ". Check file permissions.", 'file_permissions');
            throw new FileException("Cannot open file for writing: $file_path");
        }

        if (!self::waitLock($fp, true)) {
            fclose($fp);
            throw new FileException("Timeout acquiring write lock: $file_path");
        }

        ftruncate($fp, 0);
        rewind($fp);
        $result = fwrite($fp, $data);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($result === false) {
            throw new FileException("Error writing file content: $file_path");
        }
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
     * @return bool True if lock was successfully acquired, false on timeout
     *
     * @example
     * // Exclusive lock for writing
     * $fp = fopen('data.txt', 'c');
     * if (File::waitLock($fp, true)) {
     *     fwrite($fp, 'Exclusive write access');
     *     flock($fp, LOCK_UN);
     * }
     * fclose($fp);
     *
     * @example
     * // Shared lock for reading
     * $fp = fopen('data.txt', 'r');
     * if (File::waitLock($fp, false)) {
     *     $content = fread($fp, filesize('data.txt'));
     *     flock($fp, LOCK_UN);
     * }
     * fclose($fp);
     *
     * @see flock() For more information about file locking
     * @see LOCK_EX For exclusive locks
     * @see LOCK_SH For shared locks
     */
    private static function waitLock($fp, bool $exclusive = true): bool {
        $lock_type = $exclusive ? LOCK_EX : LOCK_SH;

        for ($x = 0; $x < 200; $x++) {
            if (flock($fp, $lock_type | LOCK_NB)) {
                return true;
            }
            usleep(50000); // 50ms delay between attempts
        }
        return false;
    }
}
