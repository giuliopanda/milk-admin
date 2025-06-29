<?php
namespace MilkCore;

/**
 * Function provider for JSON and serialization operations
 *
 * @package     MilkCore
 * @subpackage  MathParser
 * @ignore
 */

class SerializationFunctions extends FunctionProvider {
    private $data_row_manager;
    
    /**
     * Constructor
     * 
     * @param DataRowManager $manager Optional DataRowManager instance
     * @return void
     */
    public function __construct($manager = null) {
        $this->data_row_manager = $manager;
    }
    
    /**
     * Returns all implemented functions
     * 
     * @return array Associative array of functions (name => callback)
     */
    public function get_functions() {
        return [
            'JSON_ENCODE' => [$this, 'func_json_encode'],
            'JSON_STRINGIFY' => [$this, 'func_json_encode'], // Alias
            'JSON_DECODE' => [$this, 'func_json_decode'],
            'JSON_PARSE' => [$this, 'func_json_decode'],  // Alias
            'PHP_SERIALIZE' => [$this, 'func_php_serialize'],
            'PHP_UNSERIALIZE' => [$this, 'func_php_unserialize'],
            'CSV_ENCODE' => [$this, 'func_csv_encode'],
            'CSV_STRINGIFY' => [$this, 'func_csv_encode'], // Alias
            'CSV_DECODE' => [$this, 'func_csv_decode'], 
            'CSV_PARSE' => [$this, 'func_csv_decode'],   // Alias
        ];
    }
    
    /**
     * Converts a PHP value to a JSON string
     * 
     * @param mixed $value Value to convert to JSON
     * @param bool $pretty If true, formats JSON with spaces and indentation
     * @return string JSON string
     */
    public function func_json_encode($value, $pretty = false) {
        $options = 0;
        
        if ($pretty) {
            $options = JSON_PRETTY_PRINT;
        }
        
        // Correctly handles non-ASCII character encoding
        $options |= JSON_UNESCAPED_UNICODE;
        
        try {
            $json = json_encode($value, $options);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("JSON encoding error: " . json_last_error_msg());
            }
            
            return $json;
        } catch (\Exception $e) {
            throw new \Exception("Unable to encode to JSON: " . $e->getMessage());
        }
    }
    
    /**
     * Converts a JSON string to a PHP value
     * 
     * @param string $json JSON string to decode
     * @param bool $assoc If true, returns associative arrays instead of objects
     * @return mixed Decoded PHP value
     */
    public function func_json_decode($json, $assoc = true) {
        if (!is_string($json)) {
            throw new \Exception("The first parameter must be a JSON string");
        }
        
        try {
            // Remove any backslash escapes before quotes
            $json = str_replace('\"', '"', $json);
            
            $value = json_decode($json, $assoc);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("JSON decoding error: " . json_last_error_msg());
            }
            
            return $value;
        } catch (\Exception $e) {
            throw new \Exception("Unable to decode JSON: " . $e->getMessage());
        }
    }

    /**
     * Serializes a PHP value to a string
     * 
     * @param mixed $value Value to serialize
     * @return string Serialized string
     */
    public function func_php_serialize($value) {
        try {
            $serialized = serialize($value);
            return $serialized;
        } catch (\Exception $e) {
            throw new \Exception("Unable to serialize the value: " . $e->getMessage());
        }
    }

    /**
     * Deserializes a string into a PHP value
     * 
     * @param string $serialized Serialized string to deserialize
     * @param bool $allow_classes If true, allows deserialization of classes (default: false for security)
     * @return mixed Deserialized PHP value
     */
    public function func_php_unserialize($serialized, $allow_classes = false) {
        if (!is_string($serialized)) {
            throw new \Exception("The first parameter must be a serialized string");
        }
        
        try {
            $options = ['allowed_classes' => $allow_classes];
            $value = unserialize($serialized, $options);
            
            if ($value === false && $serialized !== 'b:0;') { // 'b:0;' è la serializzazione di false
                throw new \Exception("Deserialization error");
            }
            
            return $value;
        } catch (\Exception $e) {
            throw new \Exception("Unable to deserialize the string: " . $e->getMessage());
        }
    }

    /**
     * Converts an array to a CSV string
     * 
     * @param array $data Array of data to convert to CSV
     * @param string $delimiter Delimiter character (default: comma)
     * @param string $enclosure Enclosure character (default: double quotes)
     * @param string $escape_char Escape character (default: backslash)
     * @return string CSV string
     */
    public function func_csv_encode($data, $delimiter = ',', $enclosure = '"', $escape_char = '\\') {
        if (!is_array($data)) {
            throw new \Exception("The first parameter must be an array");
        }
        
        try {
            $output = fopen('php://temp', 'r+');
            
            // Handles both array of arrays and single array
            if (!empty($data) && !is_array(reset($data))) {
                // Simple array (single row)
                fputcsv($output, $data, $delimiter, $enclosure, $escape_char);
            } else {
                // Array of arrays (multiple rows)
                foreach ($data as $row) {
                    if (is_array($row)) {
                        fputcsv($output, $row, $delimiter, $enclosure, $escape_char);
                    } else {
                        fputcsv($output, [$row], $delimiter, $enclosure, $escape_char);
                    }
                }
            }
            
            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);
            
            return $csv;
        } catch (\Exception $e) {
            throw new \Exception("Error during CSV encoding: " . $e->getMessage());
        }
    }

    /**
     * Converts a CSV string to an array
     * 
     * @param string $csv CSV string to convert
     * @param string $delimiter Delimiter character (default: auto-detect)
     * @param string $enclosure Enclosure character (default: double quotes)
     * @param string $escape_char Escape character (default: backslash)
     * @param bool $has_header If true, the first row is used as keys (default: false)
     * @return array Array of data from CSV
     */
    public function func_csv_decode($csv, $delimiter = null, $enclosure = '"', $escape_char = '\\', $has_header = false) {
        if (!is_string($csv)) {
            throw new \Exception("The first parameter must be a CSV string");
        }
        
        try {
            // Auto-detect delimiter if not specified
            if ($delimiter === null) {
                // Check the most common delimiters
                $possible_delimiters = [',', ';', "\t", '|'];
                $counts = [];
                
                foreach ($possible_delimiters as $del) {
                    $counts[$del] = substr_count($csv, $del);
                }
                
                // Use the delimiter that appears most frequently
                $delimiter = array_search(max($counts), $counts);
            }
            
            $data = [];
            $rows = str_getcsv($csv, "\n"); // Divide per righe
            
            // If the string is empty or contains no rows, return an empty array
            if (empty($rows) || (count($rows) === 1 && empty($rows[0]))) {
                return $data;
            }
            
            $headers = [];
            
            foreach ($rows as $index => $row) {
                if (empty($row)) continue;
                
                $fields = str_getcsv($row, $delimiter, $enclosure, $escape_char);
                
                // If it's the first row and has_header is true, use as headers
                if ($index === 0 && $has_header) {
                    $headers = $fields;
                    continue;
                }
                
                // If there are headers, create an associative array
                if ($has_header && !empty($headers)) {
                    $row_data = [];
                    foreach ($fields as $i => $field) {
                        if (isset($headers[$i])) {
                            $row_data[$headers[$i]] = $field;
                        } else {
                            $row_data[] = $field; // For any fields without a header
                        }
                    }
                    $data[] = $row_data;
                } else {
                    $data[] = $fields;
                }
            }
            
            return $data;
        } catch (\Exception $e) {
            throw new \Exception("Error during CSV decoding: " . $e->getMessage());
        }
    }
}