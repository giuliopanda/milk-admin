<?php
namespace MilkCore;

/**
 * Analyzes individual PHP files to extract classes with correct namespace
 */
class FileAnalyzer
{
    /**
     * Analyzes a PHP file and returns all necessary information
     * 
     * @param string $file_path Absolute path of the file
     * @return array Array with namespace, use_statements and found classes
     */
    public function analyze_file(string $file_path): array
    {
        $content = file_get_contents($file_path);
        if (empty($content)) {
            return [
                'namespace' => '',
                'use_statements' => [],
                'classes' => []
            ];
        }
        
        return $this->extract_file_info($content, $file_path);
    }
    
    /**
     * Finds a specific class in a file
     * 
     * @param string $file_path Path of the file
     * @param string $class_name Name of the class to search for (with or without namespace)
     * @return array|null Class information if found, null otherwise
     */
    public function find_class_in_file(string $file_path, string $class_name): ?array
    {
        $file_info = $this->analyze_file($file_path);
        
        // Extract only the class name (without namespace)
        $short_class_name = basename(str_replace('\\', '/', $class_name));
        
        foreach ($file_info['classes'] as $class_info) {
            // Compare both the full name and the short name
            if ($class_info['full_name'] === $class_name || 
                $class_info['short_name'] === $short_class_name) {
                return $class_info;
            }
        }
        
        return null;
    }
    
    /**
     * Converts an absolute path to a relative path with respect to MILK_DIR
     * 
     * @param string $absolute_path Absolute path
     * @return string Relative path
     */
    public function to_relative_path(string $absolute_path): string
    {
        $real_path = realpath($absolute_path);
        if ($real_path === false) {
            return $absolute_path;
        }
        
        return str_replace(MILK_DIR . DIRECTORY_SEPARATOR, '', $real_path);
    }
    
    /**
     * Converts a relative path to an absolute path with respect to MILK_DIR
     * 
     * @param string $relative_path Relative path
     * @return string Absolute path
     */
    public function to_absolute_path(string $relative_path): string
    {
        // If it's already absolute, return as is
        if (strpos($relative_path, MILK_DIR) === 0) {
            return $relative_path;
        }
        
        // If it starts with / or \, remove it
        $relative_path = ltrim($relative_path, '/\\');
        
        return MILK_DIR . DIRECTORY_SEPARATOR . $relative_path;
    }
    
    /**
     * Extracts complete information from the file (namespace, use statements, classes)
     * 
     * @param string $content File content
     * @param string $file_path File path
     * @return array Extracted information
     */
    public function extract_file_info(string $content, string $file_path): array
    {
        $info = [
            'namespace' => '',
            'use_statements' => [],
            'classes' => []
        ];
        
        try {
            $tokens = token_get_all($content);
        } catch (\ParseError $e) {
            return $info;
        }
        
        $namespace = '';
        $use_statements = [];
        $expecting_name = false;
        $in_use_statement = false;
        $current_use = '';
        $in_class = false;
        $brace_level = 0;
        
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            
            // Skip all types of comments
            if (is_array($token)) {
                $token_type = $token[0];
                if (in_array($token_type, [T_COMMENT, T_DOC_COMMENT])) {
                    continue;
                }
            }
            
            // Handle braces to track when we're inside a class
            if (!is_array($token)) {
                if ($token === '{') {
                    $brace_level++;
                    if ($expecting_name) {
                        $in_class = true;
                    }
                } elseif ($token === '}') {
                    $brace_level--;
                    if ($brace_level === 0) {
                        $in_class = false;
                    }
                }
                
                if ($in_use_statement && $token === ';') {
                    if ($current_use) {
                        $use_statements[] = trim($current_use);
                        $current_use = '';
                    }
                    $in_use_statement = false;
                }
                continue;
            }
            
            $token_type = $token[0];
            $token_value = $token[1];
            
            // Salta spazi bianchi e newline quando necessario
            if (in_array($token_type, [T_WHITESPACE, T_CONSTANT_ENCAPSED_STRING])) {
                if ($in_use_statement && $token_type === T_WHITESPACE) {
                    continue;
                }
                if (!$in_use_statement) {
                    continue;
                }
            }
            
            // Find namespace - can be anywhere in the file before classes
            if ($token_type === T_NAMESPACE && !$in_class) {
                $namespace = $this->extract_namespace_from_tokens($tokens, $i);
                $info['namespace'] = $namespace;
                continue;
            }
            
            // Find use statements - only at global level
            // Trova use statements - solo al livello globale
            if ($token_type === T_USE && !$expecting_name && !$in_class && $brace_level === 0) {
                $in_use_statement = true;
                $current_use = '';
                continue;
            }
            
            // Accumulate the content of use statements
            if ($in_use_statement && in_array($token_type, [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                $current_use .= $token_value;
                continue;
            }
            
            // Find class/interface/trait/enum declarations - only at top level
            if (!$in_class && $brace_level === 0 && in_array($token_type, [T_CLASS, T_INTERFACE, T_TRAIT])) {
                $expecting_name = true;
                continue;
            }
            
            // PHP 8.1+ enum support
            if (!$in_class && $brace_level === 0 && defined('T_ENUM') && $token_type === T_ENUM) {
                $expecting_name = true;
                continue;
            }
            
            // Find the name of the class/interface/trait/enum
            if ($expecting_name && $token_type === T_STRING && !$in_class) {
                $class_name = $token_value;
                
                if (!$this->is_keyword($class_name)) {
                    $full_class_name = $namespace ? $namespace . '\\' . $class_name : $class_name;
                    
                    $info['classes'][] = [
                        'short_name' => $class_name,
                        'full_name' => $full_class_name,
                        'file_path' => $file_path,
                        'relative_path' => $this->to_relative_path($file_path)
                    ];
                    
                    $expecting_name = false;
                }
            }
        }
        
        $info['use_statements'] = array_unique($use_statements);
        
        return $info;
    }
    
    /**
     * Extracts the namespace from tokens starting from the current position
     * THIS FUNCTION IS TAKEN DIRECTLY FROM CacheRebuilder AND NOT MODIFIED
     * 
     * @param array $tokens Token array
     * @param int $start_index Starting index
     * @return string Extracted namespace
     */
    private function extract_namespace_from_tokens(array $tokens, int $start_index): string
    {
        $namespace = '';
        
        for ($i = $start_index + 1; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            
            if (!is_array($token)) {
                if ($token === ';' || $token === '{') {
                    break;
                }
                continue;
            }
            
            $token_type = $token[0];
            $token_value = $token[1];
            
            // Skip comments and whitespace
            if (in_array($token_type, [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE])) {
                continue;
            }
            
            if (in_array($token_type, [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                $namespace .= $token_value;
            }
        }
        
        return $namespace;
    }
    
    /**
     * Checks if a string is a PHP keyword
     * 
     * @param string $word Word to check
     * @return bool True if it's a keyword, false otherwise
     */
    private function is_keyword(string $word): bool
    {
        $keywords = [
            'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 
            'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 
            'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 
            'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final', 
            'finally', 'for', 'foreach', 'function', 'global', 'goto', 'if', 
            'implements', 'include', 'include_once', 'instanceof', 'insteadof', 
            'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 
            'private', 'protected', 'public', 'require', 'require_once', 'return', 
            'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 
            'while', 'xor', 'yield', 'enum', 'match', 'readonly'
        ];
        
        return in_array(strtolower($word), $keywords);
    }
}