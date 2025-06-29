<?php
namespace MilkCore;
// ==============================================
// LAZY MAPPING - Self-building cache
// Updated to use FileAnalyzer
// ==============================================

class LazyAutoloader
{
    private string $cacheFile;
    private array $classMap;
    private \MilkCore\FileAnalyzer $analyzer;
    
    public function __construct(string $cacheFile = 'storage/autoload_cache.php') {
        $this->cacheFile = $cacheFile;
        $this->analyzer = new \MilkCore\FileAnalyzer();
        $this->load_cache();
    }
    
    /**
     * Load existing cache
     */
    private function load_cache(): void {
        if (file_exists($this->cacheFile)) {
            $this->classMap = require $this->cacheFile;
        } else {
            $this->classMap = [];
        }
    }
    
    /**
     * Save updated cache
     */
    private function save_cache(): void {
        $php = "<?php\n// Auto-generated cache on " . date('Y-m-d H:i:s') . "\n";
        $php .= "return " . var_export($this->classMap, true) . ";";
        // if writable
        if (is_writable(dirname($this->cacheFile))) {
            file_put_contents($this->cacheFile, $php);
        } else {
            AutoloadLogger::log("[ERROR] Cannot write cache to " . $this->cacheFile . "\n<br>");
        }
    }
    
    /**
     * Find a class in directories using FileAnalyzer
     */
    private function find_class(string $className, string $dir): ?string {
        $dirs = [$dir, MILK_DIR."/milk-core",  MILK_DIR."/modules", MILK_DIR."/customizations"];
        $dirs = array_unique($dirs);
        foreach ($dirs as $dir) {
            $filePath = $this->search_in_directory($className, $dir);
            if ($filePath) {
                return $this->analyzer->to_relative_path($filePath);
            }
        }
        
        $filePath = $this->convert_to_psr4_path($className);
        if ($filePath) {
            return $this->analyzer->to_relative_path($filePath);
        }
        
        return null;
    }
    
    /**
     * Search recursively in a directory using FileAnalyzer
     */
    private function search_in_directory(string $className, string $dir): ?string { 
        // Search recursively in directory and all subdirectories
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach($iterator as $fullFileName => $fileSPLObject) {
            if ($fileSPLObject->getExtension() === 'php') {
                // Use FileAnalyzer to search for class in file
                $class_info = $this->analyzer->find_class_in_file($fileSPLObject->getPathname(), $className);
                if ($class_info !== null) {
                    return $fileSPLObject->getPathname();
                }
            }
        }
        
        return null;
    }
   
    /**
     * For classes inside external-library
     */
    private function convert_to_psr4_path(string $className): ?string {
        // App\Models\User -> src/Models/User.php
        if (strpos($className, 'App\\') === 0) {
            $relativePath = str_replace(['App\\', '\\'], ['', '/'], $className);
            $fullPath = MILK_DIR . '/' . $relativePath . '.php';
            return file_exists($fullPath) ? $fullPath : null;
        }
        
        // Automatic conversion for all external libraries
        // Vendor\Package\Class -> external-library/vendor/src/Package/Class.php
        if (strpos($className, '\\') !== false) {
            $parts = explode('\\', $className);
            $vendor = strtolower($parts[0]); // PHPMailer -> phpmailer
            $restOfPath = implode('/', array_slice($parts, 1)); // PHPMailer\Class -> PHPMailer/Class
            
            $possiblePaths = [
                MILK_DIR . '/customizations/external-library/' . $vendor . '/src/' . $restOfPath . '.php',
                MILK_DIR . '/customizations/external-library/' . $vendor . '/' . $restOfPath . '.php',
                MILK_DIR . '/external-library/' . $vendor . '/src/' . $restOfPath . '.php',
                MILK_DIR . '/external-library/' . $vendor . '/' . $restOfPath . '.php',
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Main autoloader
     */
    public function autoload(string $className): void {
        // 1. CACHE HIT - if in cache, load immediately
        if (isset($this->classMap[$className])) {
            $relativePath = $this->classMap[$className];
            
            // Convert relative path to absolute
            $absolutePath = $this->analyzer->to_absolute_path($relativePath);
            
            // Check if file exists
            if (file_exists($absolutePath)) {
                try {
                    require_once $absolutePath;
                    return;
                } catch (\Exception $e) {
                    AutoloadLogger::log("[ERROR] Error during require: " . $e->getMessage() . "\n<br>");
                }
            } else {
                AutoloadLogger::log("[ERROR] Cached file no longer exists: $absolutePath\n<br>");
                // Remove from cache if file no longer exists
                unset($this->classMap[$className]);
            }
        }
        
        // 2. CACHE MISS - search for class
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        if (isset($backtrace[0]['file'])) {
            $file = $backtrace[0]['file'];
            $dir = dirname($file);
        } else {
            $dir = MILK_DIR."/modules";
        }
        
        $relativePath = $this->find_class($className, $dir);
        
        if ($relativePath) {
            // 3. UPDATE CACHE with relative path and load
            $this->classMap[$className] = $relativePath;
            $this->save_cache();
            
            // Convert to absolute for require
            $absolutePath = $this->analyzer->to_absolute_path($relativePath);
            require_once $absolutePath;
        } else {
            AutoloadLogger::log("[ERROR] Unable to find class $className\n<br>");
        }
    }
    
    /**
     * Register the autoloader
     */
    public function register(): void {
        spl_autoload_register([$this, 'autoload']);
    }
    
    /**
     * Show cache statistics
     */
    public function showStats(): void {
        AutoloadLogger::log("\n<br>=== CACHE STATISTICS ===\n<br>");
        AutoloadLogger::log("Cache file: {$this->cacheFile}\n<br>");
        AutoloadLogger::log("Classes in cache: " . count($this->classMap) . "\n<br>");
     
        if (!empty($this->classMap)) {
            AutoloadLogger::log("\n<br>Classes in cache:\n<br>");
            foreach ($this->classMap as $class => $file) {
                AutoloadLogger::log("  $class => " . basename($file) . " (" . $file . ")\n<br>");
            }
        }
        AutoloadLogger::log("========================\n<br>\n<br>");
    }
    
    /**
     * Analyze a specific file and show found classes
     */
    public function analyze_file(string $filePath): void {
        AutoloadLogger::log("\n<br>=== FILE ANALYSIS: $filePath ===\n<br>");
        
        if (!file_exists($filePath)) {
            AutoloadLogger::log("❌ File not found!\n<br>");
            return;
        }
        
        $fileInfo = $this->analyzer->analyze_file($filePath);
        
        AutoloadLogger::log("Namespace: " . ($fileInfo['namespace'] ?: 'None') . "\n<br>");
        AutoloadLogger::log("Use statements: " . count($fileInfo['use_statements']) . "\n<br>");
        AutoloadLogger::log("Classes found: " . count($fileInfo['classes']) . "\n<br>");
        
        if (!empty($fileInfo['classes'])) {
            AutoloadLogger::log("\n<br>Class details:\n<br>");
            foreach ($fileInfo['classes'] as $classInfo) {
                AutoloadLogger::log("  - Full name: {$classInfo['full_name']}\n<br>");
                AutoloadLogger::log("    Short name: {$classInfo['short_name']}\n<br>");
                AutoloadLogger::log("    Relative path: {$classInfo['relative_path']}\n<br>");
                AutoloadLogger::log("\n<br>");
            }
        }
        
        if (!empty($fileInfo['use_statements'])) {
            AutoloadLogger::log("Use statements:\n<br>");
            foreach ($fileInfo['use_statements'] as $use) {
                AutoloadLogger::log("  - use $use;\n<br>");
            }
        }
        
        AutoloadLogger::log("===========================================\n<br>");
    }
    
    /**
     * Force rebuild of a single class
     */
    public function rebuild_class(string $className): bool {
        // Remove from existing cache
        if (isset($this->classMap[$className])) {
            unset($this->classMap[$className]);
        }
        
        // Search for class again
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $file = $backtrace[0]['file'];
        $dir = dirname($file);
        
        $relativePath = $this->find_class($className, $dir);
        
        if ($relativePath) {
            $this->classMap[$className] = $relativePath;
            $this->save_cache();
            return true;
        } else {
            AutoloadLogger::log("[ERROR] Unable to rebuild $className\n<br>");
            return false;
        }
    }
}

$autoloader = new LazyAutoloader();
$autoloader->register();