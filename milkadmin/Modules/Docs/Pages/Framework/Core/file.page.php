<?php
namespace Modules\Docs\Pages;
/**
 * @title File Class
 * @guide framework
 * @order 60
 * @tags file_get_contents, File::get_contents, file_put_contents, File::put_contents, file_append_contents, File::append_contents, wait_lock, File::wait_lock, put_contents, get_contents, fopen, fclose, flock, LOCK
 */
?>
<div class="bg-white p-4">
    <h1>File Class</h1>
    <p class="text-muted">Revision: 2025-11-11</p>
    <p>Thread-safe file operations with mandatory locking and exception-based error handling.</p>

    <h2 class="mt-4">Exception Handling</h2>
    <p><strong>All methods throw exceptions on errors:</strong></p>
    <ul>
        <li><code>FileException</code> - File access, permission, or I/O errors</li>
     </ul>

    <h2 class="mt-4">Lock Management</h2>
    <ul>
        <li><strong>Shared locks (LOCK_SH):</strong> Read operations, allow concurrent reads</li>
        <li><strong>Exclusive locks (LOCK_EX):</strong> Write operations, block all access</li>
        <li><strong>Retry mechanism:</strong> 200 attempts × 50ms = 10-second timeout</li>
    </ul>

    <h2 class="mt-4">Methods</h2>

    <h4 class="text-primary mt-4">getContents(string $file_path) : string</h4>
    <p>Reads file content with shared lock.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">try {
    $content = File::getContents('config.json');
    $config = json_decode($content, true);
} catch (\App\Exceptions\FileException $e) {
    echo "Read failed: " . $e->getMessage();
}</code></pre>

    <h4 class="text-primary mt-4">putContents(string $file_path, string $data) : void</h4>
    <p>Writes data with exclusive lock, creating or overwriting file.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">try {
    $data = ['user' => 'john', 'time' => time()];
    File::putContents('data.json', json_encode($data));
} catch (\App\Exceptions\FileException $e) {
    echo "Write failed: " . $e->getMessage();
}</code></pre>

    <h4 class="text-primary mt-4">appendContents(string $file_path, string $data) : void</h4>
    <p>Appends data with exclusive lock.</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">try {
    $log = date('Y-m-d H:i:s') . " - User logged in\n";
    File::appendContents('app.log', $log);
} catch (\App\Exceptions\FileException $e) {
    error_log("Log failed: " . $e->getMessage());
}</code></pre>

    <h2 class="mt-4">Examples</h2>

    <h4 class="mt-4">Thread-Safe Configuration Manager</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use App\Exceptions\FileException;

class ConfigManager {
    private string $file;

    public function __construct(string $file) {
        $this->file = $file;
    }

    public function load(): array {
        try {
            $json = File::getContents($this->file);
            return json_decode($json, true) ?: [];
        } catch (FileException $e) {
            // Return empty config on error
            return [];
        }
    }

    public function update(string $key, mixed $value): void {
        $config = $this->load();
        $config[$key] = $value;
        $json = json_encode($config, JSON_PRETTY_PRINT);
        File::putContents($this->file, $json);
    }
}

// Usage
$config = new ConfigManager('app_config.json');

try {
    $config->update('last_updated', time());
    $config->update('version', '2.0');
    echo "Configuration updated";
} catch (FileException $e) {
    echo "Update failed: " . $e->getMessage();
}</code></pre>

</div>