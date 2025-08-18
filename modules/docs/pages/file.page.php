<?php
namespace Modules\docs;
use MilkCore\Route;
/**
 * @title File Class
 * @category Framework
 * @order 60
 * @tags file_get_contents, File::get_contents, file_put_contents, File::put_contents, file_append_contents, File::append_contents, wait_lock, File::wait_lock, put_contents, get_contents, fopen, fclose, flock, LOCK
 */
?>
<div class="bg-white p-4">
    <h1>File Class</h1>
    
    <p>The File class provides thread-safe file operations using mandatory exclusive and shared locks. All operations are atomic and prevent file corruption during concurrent access by avoiding file lock conflicts through a sophisticated timeout and retry mechanism.</p>

    <h2 class="mt-4">Key Features</h2>
    <ul>
        <li><strong>Thread-safe operations:</strong> All file operations use mandatory locking to prevent data corruption</li>
        <li><strong>Lock conflict prevention:</strong> Implements a retry mechanism with timeouts to avoid deadlocks</li>
        <li><strong>Atomic operations:</strong> All read/write operations are completed fully or fail entirely</li>
        <li><strong>Error handling:</strong> Comprehensive error reporting for failed operations</li>
        <li><strong>Shared vs Exclusive locks:</strong> Uses appropriate lock types for read and write operations</li>
        <li><strong>Timeout management:</strong> 10-second timeout prevents infinite waiting for locks</li>
    </ul>

    <h2 class="mt-4">Lock Management</h2>
    <p>The File class prevents file lock conflicts by implementing a sophisticated waiting mechanism:</p>
    <ul>
        <li><strong>Shared locks (LOCK_SH):</strong> Used for read operations, allow multiple readers but block writers</li>
        <li><strong>Exclusive locks (LOCK_EX):</strong> Used for write operations, block all other access</li>
        <li><strong>Non-blocking approach:</strong> Uses LOCK_NB flag to avoid infinite waiting</li>
        <li><strong>Retry mechanism:</strong> Attempts to acquire locks up to 200 times with 50ms intervals</li>
        <li><strong>Timeout protection:</strong> Total timeout of 10 seconds prevents system freeze</li>
    </ul>

    <h2 class="mt-4">Main Functions</h2>

    <h4 class="mt-4">get_contents($file_path)</h4>
    <p>Reads the entire file content with mandatory shared locking. Uses shared locks to allow concurrent reads while preventing writes during the operation.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Basic file reading
$content = File::get_contents('/path/to/file.txt');
if ($content !== false) {
    echo "File content: " . $content;
} else {
    echo "Read failed: " . File::get_last_error();
}

// Reading JSON configuration
$config_json = File::get_contents('config.json');
if ($config_json !== false) {
    $config = json_decode($config_json, true);
    echo "Config loaded successfully";
}</code></pre>

    <h4 class="mt-4">put_contents($file_path, $data)</h4>
    <p>Writes data to a file with mandatory exclusive locking. Creates or overwrites the file with the provided data, using exclusive locks to prevent concurrent access.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Basic file writing
$log_entry = "Error occurred at " . date('Y-m-d H:i:s');
if (File::put_contents('log.txt', $log_entry)) {
    echo "Log entry written successfully";
} else {
    echo "Failed to write log: " . File::get_last_error();
}

// Writing JSON data
$data = ['user' => 'john', 'timestamp' => time()];
$json = json_encode($data, JSON_PRETTY_PRINT);
if (File::put_contents('user_data.json', $json)) {
    echo "User data saved";
}</code></pre>

    <h4 class="mt-4">append_contents($file_path, $data)</h4>
    <p>Appends data to a file with mandatory exclusive locking. Adds data to the end of an existing file or creates a new file if it doesn't exist.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Logging to a file
$log_entry = date('Y-m-d H:i:s') . " - User logged in\n";
if (File::append_contents('app.log', $log_entry)) {
    echo "Log entry added successfully";
} else {
    echo "Failed to write log: " . File::get_last_error();
}

// Adding items to a list
$new_item = "Item #" . rand(1, 1000) . "\n";
if (File::append_contents('items.txt', $new_item)) {
    echo "Item added to list";
}

// CSV data appending
$csv_row = "John,Doe,30,Engineer\n";
if (File::append_contents('users.csv', $csv_row)) {
    echo "User data appended to CSV";
}</code></pre>

    <h4 class="mt-4">wait_lock($fp, $exclusive = true)</h4>
    <p>Waits to acquire a lock on a file handle. This is the core method that prevents lock conflicts by implementing the retry mechanism.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Manual exclusive lock for writing
$fp = fopen('data.txt', 'c');
if (File::wait_lock($fp, true)) {
    fwrite($fp, 'Exclusive write access');
    flock($fp, LOCK_UN);
    echo "Write completed with exclusive lock";
} else {
    echo "Could not acquire exclusive lock";
}
fclose($fp);

// Manual shared lock for reading
$fp = fopen('data.txt', 'r');
if (File::wait_lock($fp, false)) {
    $content = fread($fp, filesize('data.txt'));
    flock($fp, LOCK_UN);
    echo "Read completed with shared lock";
}
fclose($fp);</code></pre>

    <h4 class="mt-4">get_last_error()</h4>
    <p>Retrieves the last error message from the most recent file operation. Error messages are automatically cleared when a new operation starts.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Error handling after failed operation
if (!File::put_contents('readonly.txt', 'data')) {
    $error = File::get_last_error();
    error_log("File operation failed: $error");
    echo "Operation failed. Check logs for details.";
}

// Error handling in a loop
$files = ['file1.txt', 'file2.txt', 'file3.txt'];
foreach ($files as $file) {
    if (!File::put_contents($file, 'test data')) {
        echo "Failed to write $file: " . File::get_last_error() . "\n";
    }
}</code></pre>

    <h2 class="mt-4">Lock Conflict Prevention</h2>
    <p>The File class specifically addresses the common problem of file lock conflicts in concurrent environments:</p>
    
    <h4 class="mt-4">How Lock Conflicts Are Avoided</h4>
    <ul>
        <li><strong>Non-blocking locks:</strong> Uses LOCK_NB flag to prevent indefinite waiting</li>
        <li><strong>Retry strategy:</strong> Attempts lock acquisition up to 200 times with 50ms delays</li>
        <li><strong>Timeout mechanism:</strong> Total timeout of 10 seconds prevents system hangs</li>
        <li><strong>Proper lock types:</strong> Uses shared locks for reads, exclusive locks for writes</li>
        <li><strong>Automatic cleanup:</strong> Always releases locks and closes file handles</li>
    </ul>

    <h4 class="mt-4">Benefits of This Approach</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Multiple processes can read simultaneously
// Process A
$content_a = File::get_contents('shared_data.txt'); // Shared lock

// Process B (simultaneous)
$content_b = File::get_contents('shared_data.txt'); // Shared lock (allowed)

// Process C wants to write
if (File::put_contents('shared_data.txt', 'new data')) {
    // Exclusive lock acquired after readers finish
    echo "Write successful";
}</code></pre>

    <h2 class="mt-4">Examples</h2>

    <h4 class="mt-4">Thread-Safe Logging</h4>
    <p>This example shows how multiple processes can safely append to the same log file.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Multiple processes can safely log simultaneously
function safe_log($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    
    if (File::append_contents('app.log', $log_entry)) {
        return true;
    } else {
        error_log("Logging failed: " . File::get_last_error());
        return false;
    }
}

// Usage from different processes
safe_log("User login: john@example.com");
safe_log("Database connection established");
safe_log("Cache cleared by admin");</code></pre>

    <h4 class="mt-4">Configuration File Management</h4>
    <p>This example demonstrates safe reading and writing of configuration files.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Safe configuration reading
function load_config($config_file) {
    $json = File::get_contents($config_file);
    if ($json === false) {
        echo "Config load failed: " . File::get_last_error();
        return [];
    }
    
    $config = json_decode($json, true);
    return $config ?: [];
}

// Safe configuration writing
function save_config($config_file, $config) {
    $json = json_encode($config, JSON_PRETTY_PRINT);
    if (File::put_contents($config_file, $json)) {
        echo "Configuration saved successfully";
        return true;
    } else {
        echo "Config save failed: " . File::get_last_error();
        return false;
    }
}

// Usage
$config = load_config('app_config.json');
$config['last_updated'] = time();
save_config('app_config.json', $config);</code></pre>

    <h4 class="mt-4">Data Collection and Processing</h4>
    <p>This example shows how to safely collect data from multiple sources.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Multiple data collectors can append safely
function collect_user_data($user_id, $action) {
    $data = [
        'timestamp' => time(),
        'user_id' => $user_id,
        'action' => $action,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    $csv_line = implode(',', $data) . "\n";
    
    if (File::append_contents('user_tracking.csv', $csv_line)) {
        return true;
    } else {
        error_log("Tracking failed: " . File::get_last_error());
        return false;
    }
}

// Process tracking data safely
function process_tracking_data() {
    $csv_data = File::get_contents('user_tracking.csv');
    if ($csv_data !== false) {
        $lines = explode("\n", trim($csv_data));
        echo "Processing " . count($lines) . " tracking entries";
        // Process each line...
    }
}

// Usage
collect_user_data(123, 'login');
collect_user_data(456, 'purchase');
process_tracking_data();</code></pre>

    <h4 class="mt-4">Batch Operations with Error Handling</h4>
    <p>This example demonstrates handling multiple file operations with proper error management.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Batch file operations with detailed error reporting
function batch_file_operations() {
    $operations = [
        ['write', 'file1.txt', 'Content for file 1'],
        ['write', 'file2.txt', 'Content for file 2'],
        ['append', 'log.txt', "Batch operation started\n"],
        ['write', 'config.json', json_encode(['version' => '1.0'])]
    ];
    
    $success_count = 0;
    $errors = [];
    
    foreach ($operations as $op) {
        [$action, $file, $data] = $op;
        
        if ($action === 'write') {
            $result = File::put_contents($file, $data);
        } else {
            $result = File::append_contents($file, $data);
        }
        
        if ($result) {
            $success_count++;
        } else {
            $errors[] = "$action on $file: " . File::get_last_error();
        }
    }
    
    echo "Operations completed: $success_count successful\n";
    if (!empty($errors)) {
        echo "Errors encountered:\n";
        foreach ($errors as $error) {
            echo "- $error\n";
        }
    }
}

batch_file_operations();</code></pre>

</div>