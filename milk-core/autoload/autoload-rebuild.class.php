<?php
namespace MilkCore;
!defined('MILK_DIR') && die(); // Avoid direct access

// ==============================================
// COMPLETE CACHE REBUILD - WITH NAMESPACE SUPPORT
// Updated to use FileAnalyzer
// ==============================================

class CacheRebuilder
{
    private string $cache_file = 'storage/autoload_cache.php';
    private array $class_map = [];
    private array $exclude_dirs = ['.git', 'vendor', 'node_modules', 'cache', 'storage', 'tmp'];
    private array $exclude_files = ['autoload.php', 'cache.php'];
    private int $scanned_files = 0;
    private int $found_classes = 0;
    private array $customization_overrides = [];
    private array $name_conflicts = [];
    private FileAnalyzer $analyzer;
    
    public function __construct()
    {
        $this->analyzer = new FileAnalyzer();
    }
    
    /**
     * Completely rebuilds the cache from MILK_DIR
     */
    public function rebuild_cache(): bool
    {
        $start_time = microtime(true);
        
        $this->class_map = [];
        $this->customization_overrides = [];
        $this->name_conflicts = [];
        $this->scanned_files = 0;
        $this->found_classes = 0;
        
        // PHASE 1: Scan EVERYTHING except customizations
        AutoloadLogger::log("\n=== PHASE 1: PROJECT SCANNING ===\n<br>");
        $this->scan_directory(MILK_DIR, false, true);
        
        AutoloadLogger::log("\n=== PHASE 2: OVERRIDE CUSTOMIZATIONS ===\n<br>");
        
        // PHASE 2: Scan customizations LAST (override)
        $customizations_path = MILK_DIR . '/customizations';
        if (is_dir($customizations_path)) {
            AutoloadLogger::log("Override scanning: customizations\n<br>");
            $this->scan_directory($customizations_path, true, false);
        } else {
            AutoloadLogger::log("Customizations folder not found\n<br>");
        }
        
        // Save the cache
        $success = $this->save_cache();
        
        $elapsed = microtime(true) - $start_time;
        
        AutoloadLogger::log("\n=== STATISTICS ===\n<br>");
        AutoloadLogger::log("Files scanned: {$this->scanned_files}\n<br>");
        AutoloadLogger::log("Classes found: {$this->found_classes}\n<br>");
        AutoloadLogger::log("Overrides applied: " . count($this->customization_overrides) . "\n<br>");
        AutoloadLogger::log("Name conflicts: " . count($this->name_conflicts) . "\n<br>");
        AutoloadLogger::log("Time taken: " . round($elapsed, 2) . " seconds\n<br>");
        AutoloadLogger::log("Cache saved in: " . $this->cache_file . "\n<br>");
        
        if (!empty($this->customization_overrides)) {
            AutoloadLogger::log("\n🔄 APPLIED OVERRIDES:\n<br>");
            foreach ($this->customization_overrides as $class => $info) {
                AutoloadLogger::log("   $class: " . basename($info['original']) . " → " . basename($info['override']) . "\n<br>");
            }
        }
        
        if (!empty($this->name_conflicts)) {
            AutoloadLogger::log("\n⚠️ NAME CONFLICTS DETECTED:\n<br>");
            foreach ($this->name_conflicts as $short_name => $full_names) {
                AutoloadLogger::log("   $short_name found in:\n<br>");
                foreach ($full_names as $full_name) {
                    AutoloadLogger::log("      - $full_name\n<br>");
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Recursively scans a directory
     */
    private function scan_directory(string $dir, bool $is_customization = false, bool $skip_customizations = false): void
    {
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($skip_customizations && $this->is_customizations_path($file->getPathname())) {
                    continue;
                }
                // Skip milk-admin-v* directories
                if (preg_match('/milk-admin-v.*$/', $file)) {
                    continue;
                }
                
                if ($file->isFile() && $file->getExtension() === 'php') {
                    if ($this->should_scan_file($file->getPathname())) {
                        $this->analyze_php_file($file->getPathname(), $is_customization);
                    }
                }
            }
        } catch (\Exception $e) {
            AutoloadLogger::log("⚠️ Error scanning $dir: " . $e->getMessage() . "\n<br>");
        }
    }
    
    /**
     * Checks if the path belongs to customizations
     */
    private function is_customizations_path(string $file_path): bool
    {
        $customizations_path = MILK_DIR . '/customizations';
        return strpos($file_path, $customizations_path) === 0;
    }
    
    /**
     * Checks if the file should be scanned
     */
    private function should_scan_file(string $file_path): bool
    {
        $path_parts = explode(DIRECTORY_SEPARATOR, $file_path);
        
        foreach ($path_parts as $part) {
            if (str_starts_with($part, '.') && $part !== '.' && $part !== '..') {
                return false;
            }
        }
        
        foreach ($this->exclude_dirs as $exclude_dir) {
            if (strpos($file_path, DIRECTORY_SEPARATOR . $exclude_dir . DIRECTORY_SEPARATOR) !== false) {
                return false;
            }
        }
        
        $file_name = basename($file_path);
        if (in_array($file_name, $this->exclude_files)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Analyzes a PHP file using FileAnalyzer
     */
    private function analyze_php_file(string $file_path, bool $is_customization = false): void
    {
        $this->scanned_files++;
        
        // Use FileAnalyzer to analyze the file
        $file_info = $this->analyzer->analyze_file($file_path);
        
        foreach ($file_info['classes'] as $class_info) {
            $full_class_name = $class_info['full_name'];
            $relative_path = $class_info['relative_path'];
            
            // Track name conflicts
            if (!isset($this->name_conflicts[$full_class_name])) {
                $this->name_conflicts[$full_class_name] = [];
            }
            $this->name_conflicts[$full_class_name][] = $full_class_name;
            
            if ($is_customization && isset($this->class_map[$full_class_name])) {
                $this->customization_overrides[$full_class_name] = [
                    'original' => $this->class_map[$full_class_name],
                    'override' => $relative_path
                ];
                AutoloadLogger::log(" Override: $full_class_name\n<br>");
            } else {
                AutoloadLogger::log("  ✓ Found: $full_class_name");
                if ($file_info['namespace']) {
                    AutoloadLogger::log(" (namespace: {$file_info['namespace']})");
                }
                AutoloadLogger::log("\n<br>");
            }
            
            // Always save the relative path in the cache
            $this->class_map[$full_class_name] = $relative_path;
            
            if (!$is_customization) {
                $this->found_classes++;
            }
        }
    }
    
    /**
     * Saves the cache in PHP format with complete information
     */
    private function save_cache(): bool
    {
        // Remove conflicts with only one entry
        foreach ($this->name_conflicts as $short_name => $full_names) {
            if (count($full_names) <= 1) {
                unset($this->name_conflicts[$short_name]);
            }
        }
        
        $php = "<?php\n";
        $php .= "// Cache generated automatically on " . date('Y-m-d H:i:s') . "\n";
        $php .= "// Files scanned: {$this->scanned_files}\n";
        $php .= "// Classes found: {$this->found_classes}\n";
        $php .= "// Overrides applied: " . count($this->customization_overrides) . "\n";
        $php .= "// Name conflicts: " . count($this->name_conflicts) . "\n\n";
        
        // For compatibility with the old format, save only the class map
        $php .= "return [\n";
        
        // Sort for readability
        ksort($this->class_map);
        
        foreach ($this->class_map as $class_name => $file_path) {
            $php .= "    " . var_export($class_name, true) . " => " . var_export($file_path, true) . ",\n";
        }
        
        $php .= "];\n";
        // DON'T USE FILE::PUT_CONTENTS!!!
        $result = file_put_contents($this->cache_file, $php);
        
        if ($result === false) {
            throw new \Exception("Save cache failed");
        }
        
        return true;
    }
    
    /**
     * Shows a preview of the cache
     */
    public function show_cache_preview(int $limit = 10): void
    {
        AutoloadLogger::log("\n=== CACHE PREVIEW ===\n<br>");
        
        $count = 0;
        foreach ($this->class_map as $class_name => $file_path) {
            if (++$count > $limit) break;
            
            AutoloadLogger::log(sprintf("%-60s => %s\n", $class_name, $file_path));
        }
        
        if (count($this->class_map) > $limit) {
            $remaining = count($this->class_map) - $limit;
            AutoloadLogger::log("... and $remaining more classes\n");
        }
    }
    
    /**
     * Verifies cache integrity
     */
    public function verify_cache(): array
    {
        AutoloadLogger::log("\n=== CACHE INTEGRITY VERIFICATION ===\n<br>");
        
        if (!file_exists($this->cache_file)) {
            AutoloadLogger::log("❌ Cache file not found!\n<br>");
            return ['valid' => false, 'errors' => ['Cache file not found']];
        }
        
        $cache_data = require $this->cache_file;
        $cache = isset($cache_data['class_map']) ? $cache_data['class_map'] : $cache_data;
        
        $errors = [];
        $valid_count = 0;
        
        foreach ($cache as $class_name => $file_path) {
            // Convert relative path to absolute for verification
            $absolute_path = $this->analyzer->to_absolute_path($file_path);
            
            if (!file_exists($absolute_path)) {
                $errors[] = "File not found for $class_name: $absolute_path";
            } else {
                $valid_count++;
            }
        }
        
        AutoloadLogger::log("Classes verified: " . count($cache) . "\n<br>");
        AutoloadLogger::log("Valid files: $valid_count\n<br>");
        AutoloadLogger::log("Errors found: " . count($errors) . "\n<br>");
        
        if (!empty($errors)) {
            AutoloadLogger::log("\n❌ ERRORS:\n<br>");
            foreach ($errors as $error) {
                AutoloadLogger::log("  - $error\n<br>");
            }
        } else {
            AutoloadLogger::log("✅ Cache integrity verified!\n<br>");
        }
        
        return [
            'valid' => empty($errors),
            'total' => count($cache),
            'valid_count' => $valid_count,
            'errors' => $errors
        ];
    }
    
    /**
     * Statistics on the types of classes found
     */
    public function get_class_statistics(): array
    {
        $stats = [
            'total' => count($this->class_map),
            'with_namespace' => 0,
            'without_namespace' => 0,
            'overrides' => count($this->customization_overrides),
            'conflicts' => count($this->name_conflicts),
            'namespaces' => [],
            'directories' => []
        ];
        
        foreach ($this->class_map as $class_name => $file_path) {
            // Count namespaces
            if (strpos($class_name, '\\') !== false) {
                $stats['with_namespace']++;
                
                // Extract the namespace
                $namespace = substr($class_name, 0, strrpos($class_name, '\\'));
                $stats['namespaces'][$namespace] = ($stats['namespaces'][$namespace] ?? 0) + 1;
            } else {
                $stats['without_namespace']++;
            }
            
            // Count by directory
            $top_dir = explode('/', $file_path)[0];
            $stats['directories'][$top_dir] = ($stats['directories'][$top_dir] ?? 0) + 1;
        }
        
        return $stats;
    }
}

Hooks::set('cli-init', function() {
    function rebuild_autoload_cache($data) {
        $cache = new CacheRebuilder();
        $cache->rebuild_cache();
    }
    Cli::set('rebuild-autoload-cache', 'MilkCore\rebuild_autoload_cache');
});