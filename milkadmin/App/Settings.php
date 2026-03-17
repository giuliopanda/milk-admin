<?php

namespace App;

class Settings
{
    private static array $data = [];
    private static array $modified = [];
    private static bool $shutdownRegistered = false;

    private const DEFAULT_GROUP = 'default';

    private static function registerShutdownHandler(): void
    {
        if (!self::$shutdownRegistered) {
            register_shutdown_function([self::class, 'onShutdown']);
            self::$shutdownRegistered = true;
        }
    }

    public static function onShutdown(): void
    {
        if (empty(self::$modified)) {
            return;
        }

        try {
            self::saveAll(false);
        } catch (\Throwable $e) {
            // In shutdown non possiamo fare molto: log e basta
            error_log('Settings: Failed to save on shutdown: ' . $e->getMessage());
        }
    }


    private static function sanitizeGroupName(string $group): string
    {
       $clean = preg_replace('/[^a-zA-Z0-9_\-]/', '', $group);
       $clean = $clean ?? '';
        return $clean !== '' ? $clean : self::DEFAULT_GROUP;
    }

    private static function getFilePath(string $group): string
    {
        $clean_group = self::sanitizeGroupName($group);
        return STORAGE_DIR . "/" . $clean_group . '.json';
    }

    private static function loadGroupData(string $group): void
    {
        $clean_group = self::sanitizeGroupName($group);

        if (isset(self::$data[$clean_group])) {
            return;
        }

        $file_path = self::getFilePath($group);
        self::$data[$clean_group] = [];

        if (file_exists($file_path)) {
            try {
                $json_content = File::getContents($file_path);
                $decoded = json_decode($json_content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    self::$data[$clean_group] = $decoded;
                } else {
                    self::$data[$clean_group] = [];
                }
            } catch (\App\Exceptions\FileException $e) {
                error_log("Settings: Failed to load group '$clean_group': " . $e->getMessage());
            }
        }
    }

    /**
     * Saves a single group to its JSON file
     * 
     * @throws \App\Exceptions\FileException on write failure
     */
    private static function saveGroupToFile(string $group): void
    {
        $clean_group = self::sanitizeGroupName($group);
        
        if (!isset(self::$data[$clean_group])) {
            return;
        }

        if (!is_dir(STORAGE_DIR)) {
            mkdir(STORAGE_DIR, 0755, true);
        }

        $file_path = self::getFilePath($group);
        $json_content = json_encode(self::$data[$clean_group], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json_content === false) {
            throw new \RuntimeException('json_encode failed: ' . json_last_error_msg());
        }
        // Lascia propagare l'eccezione al chiamante
        File::putContents($file_path, $json_content);
    }

    public static function get(string $key, ?string $group = null): mixed
    {
        $group = $group ?: self::DEFAULT_GROUP;
        self::loadGroupData($group);
        $clean_group = self::sanitizeGroupName($group);

        return self::$data[$clean_group][$key] ?? null;
    }

    public static function set(string $key, mixed $value, ?string $group = null): void
    {
        self::registerShutdownHandler();
        
        $group = $group ?: self::DEFAULT_GROUP;
        $clean_group = self::sanitizeGroupName($group);
        
        self::loadGroupData($group);
        
        self::$data[$clean_group][$key] = $value;
        self::$modified[$clean_group] = true;
    }

    public static function setMultiple(array $values, ?string $group = null): void
    {
        self::registerShutdownHandler();
        
        $group = $group ?: self::DEFAULT_GROUP;
        $clean_group = self::sanitizeGroupName($group);
        
        self::loadGroupData($group);
        
        foreach ($values as $key => $value) {
            self::$data[$clean_group][$key] = $value;
        }
        
        self::$modified[$clean_group] = true;
    }

    /**
     * Saves modified groups to disk
     * 
     * @param string|null $group Specific group to save, or null for all modified
     * @param bool $throw Whether to throw exceptions on failure
     * @throws \App\Exceptions\FileException if $throw is true and save fails
     */
    public static function save(?string $group = null, bool $throw = true): void
    {
        if ($group !== null) {
            $clean_group = self::sanitizeGroupName($group);
            
            if (!isset(self::$modified[$clean_group])) {
                return;
            }
            
            try {
                self::saveGroupToFile($group);
                unset(self::$modified[$clean_group]);
            } catch (\App\Exceptions\FileException $e) {
                error_log("Settings: Failed to save group '$clean_group': " . $e->getMessage());
                if ($throw) {
                    throw $e;
                }
            }
        } else {
            self::saveAll($throw);
        }
    }

    /**
     * Saves all modified groups to disk
     * 
     * @param bool $throw Whether to throw on first failure
     * @throws \App\Exceptions\FileException if $throw is true and save fails
     */
    public static function saveAll(bool $throw = false): void
    {        
        foreach (array_keys(self::$modified) as $group) {
            try {
                self::saveGroupToFile($group);
                unset(self::$modified[$group]);
            } catch (\App\Exceptions\FileException $e) {
                error_log("Settings: Failed to save group '$group': " . $e->getMessage());                
                if ($throw) {
                    throw $e;
                }
            }
        }
    }

    public static function setAndSave(string $key, mixed $value, ?string $group = null): void
    {
        self::set($key, $value, $group);
        self::save($group);
    }

    public static function getAll(?string $group = null): array
    {
        $group = $group ?: self::DEFAULT_GROUP;
        self::loadGroupData($group);
        $clean_group = self::sanitizeGroupName($group);
        
        return self::$data[$clean_group] ?? [];
    }

    public static function removeKey(string $key, ?string $group = null): void
    {
        self::registerShutdownHandler();
        
        $group = $group ?: self::DEFAULT_GROUP;
        $clean_group = self::sanitizeGroupName($group);
        
        self::loadGroupData($group);
        
        if (isset(self::$data[$clean_group][$key])) {
            unset(self::$data[$clean_group][$key]);
            self::$modified[$clean_group] = true;
        }
    }

    public static function clearGroup(?string $group = null): void
    {
        self::registerShutdownHandler();
        
        $group = $group ?: self::DEFAULT_GROUP;
        $clean_group = self::sanitizeGroupName($group);
        
        self::$data[$clean_group] = [];
        self::$modified[$clean_group] = true;
    }

    public static function hasKey(string $key, ?string $group = null): bool
    {
        $group = $group ?: self::DEFAULT_GROUP;
        self::loadGroupData($group);
        $clean_group = self::sanitizeGroupName($group);
        
        return isset(self::$data[$clean_group][$key]);
    }

    public static function hasUnsavedChanges(?string $group = null): bool
    {
        if ($group !== null) {
            $clean_group = self::sanitizeGroupName($group);
            return isset(self::$modified[$clean_group]);
        }
        
        return !empty(self::$modified);
    }

    /**
     * Discards unsaved changes and optionally reloads from disk
     * 
     * @param string|null $group Specific group or null for all
     * @param bool $reload Whether to reload data from disk
     */
    public static function discard(?string $group = null, bool $reload = false): void
    {
        if ($group !== null) {
            $clean_group = self::sanitizeGroupName($group);
            unset(self::$data[$clean_group]);
            unset(self::$modified[$clean_group]);
            
            if ($reload) {
                self::loadGroupData($group);
            }
        } else {
            $groups = array_keys(self::$data);
            self::$data = [];
            self::$modified = [];
            
            if ($reload) {
                foreach ($groups as $g) {
                    self::loadGroupData($g);
                }
            }
        }
    }

    public static function searchByValue(mixed $search_value, ?string $group = null): array
    {
        $results = [];

        if ($group !== null) {
            $clean_group = self::sanitizeGroupName($group);
            self::loadGroupData($group);
            
            foreach (self::$data[$clean_group] ?? [] as $key => $value) {
                if (self::valueContains($value, $search_value)) {
                    $results[$clean_group][$key] = $value;
                }
            }
        } else {
            if (is_dir(STORAGE_DIR)) {
                $files = glob(STORAGE_DIR . '/*.json');
                foreach ($files as $file) {
                    $group_name = basename($file, '.json');
                    self::loadGroupData($group_name);
                    
                    foreach (self::$data[$group_name] ?? [] as $key => $value) {
                        if (self::valueContains($value, $search_value)) {
                            $results[$group_name][$key] = $value;
                        }
                    }
                }
            }
        }

        return $results;
    }

    public static function searchByKey(string $search_key, ?string $group = null): array
    {
        $results = [];

        if ($group !== null) {
            $clean_group = self::sanitizeGroupName($group);
            self::loadGroupData($group);
            
            foreach (self::$data[$clean_group] ?? [] as $key => $value) {
                if (stripos($key, $search_key) !== false) {
                    $results[$clean_group][$key] = $value;
                }
            }
        } else {
            if (is_dir(STORAGE_DIR)) {
                $files = glob(STORAGE_DIR . '/*.json');
                foreach ($files as $file) {
                    $group_name = basename($file, '.json');
                    self::loadGroupData($group_name);
                    
                    foreach (self::$data[$group_name] ?? [] as $key => $value) {
                        if (stripos($key, $search_key) !== false) {
                            $results[$group_name][$key] = $value;
                        }
                    }
                }
            }
        }

        return $results;
    }

    private static function valueContains(mixed $value, mixed $search_value): bool
    {
        if (is_string($value)) {
            return stripos($value, (string)$search_value) !== false;
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                if (self::valueContains($item, $search_value)) {
                    return true;
                }
            }
        } elseif (is_numeric($value)) {
            return strval($value) === strval($search_value);
        }
        return false;
    }
}