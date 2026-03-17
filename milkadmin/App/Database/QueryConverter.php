<?php
namespace App\Database;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * QueryConverter - Converte query SQL tra diversi dialetti di database
 * 
 * Questa classe gestisce la conversione di query SQL tra MySQL, PostgreSQL e SQLite,
 * adattando le differenze di sintassi più comuni tra i vari database.
 * 
 * @example
 * ```php
 * $converter = new QueryConverter('postgres');
 * list($postgresQuery, $postgresParams) = $converter->convert($mysqlQuery, $params);
 * 
 * // O usandola con la classe Query esistente
 * $query = new Query('users');
 * list($sql, $params) = $query->get();
 * $converter = new QueryConverter($dbType);
 * list($convertedSql, $convertedParams) = $converter->convert($sql, $params);
 * ```
 * 
 * @package     App
 */
class QueryConverter {
    /**
     * Tipo di database target ('mysql', 'sqlite', 'postgres')
     * @var string
     */
    private $target_db;
    
    /**
     * Tipo di database sorgente (default 'mysql')
     * @var string
     */
    private $source_db;

    private function replace(string $pattern, string $replacement, string $subject): string
    {
        $result = preg_replace($pattern, $replacement, $subject);
        return is_string($result) ? $result : $subject;
    }

    /**
     * @param callable(array<array-key, string>):string $callback
     */
    private function replaceCallback(string $pattern, callable $callback, string $subject): string
    {
        $result = preg_replace_callback($pattern, $callback, $subject);
        return is_string($result) ? $result : $subject;
    }
    
    /**
     * Constructor
     * 
     * @param string $target_db Tipo di database target ('mysql', 'sqlite', 'postgres')
     * @param string $source_db Tipo di database sorgente (default 'mysql')
     */
    public function __construct($target_db, $source_db = 'mysql') {
        $this->target_db = strtolower($target_db);
        $this->source_db = strtolower($source_db);
    }
    
    /**
     * Converte una query SQL nel formato del database target
     * 
     * @param string $sql Query SQL da convertire
     * @param array $params Parametri della query originali
     * @return array Array con [sql convertito, parametri convertiti]
     */
    public function convert($sql, $params = []) {
        // Se source e target sono uguali, non serve conversione
        if ($this->source_db === $this->target_db) {
            return [$sql, $params];
        }
        
        // Copia i parametri per non modificare l'array originale
        $converted_params = $params;
        
        // Applica le conversioni in base al database target
        switch ($this->target_db) {
            case 'postgres':
                $converted_sql = $this->convertToPostgres($sql, $converted_params);
                break;
            case 'sqlite':
                $converted_sql = $this->convertToSqlite($sql, $converted_params);
                break;
            case 'mysql':
                $converted_sql = $this->convertToMysql($sql, $converted_params);
                break;
            default:
                $converted_sql = $sql;
                break;
        }
        
        return [$converted_sql, $converted_params];
    }
    
    /**
     * Converte una query nel formato PostgreSQL
     * 
     * @param string $sql Query SQL
     * @param array &$params Parametri
     * @return string Query convertita
     */
    private function convertToPostgres($sql, &$params) {
        // 1. Converti i backtick in virgolette doppie
        $sql = $this->convertQuotes($sql, 'postgres');
        
        // 2. Converti i placeholder ? in $1, $2, ecc.
        $sql = $this->convertPlaceholders($sql, 'postgres', $params);
        
        // 3. Converti LIMIT x,y in LIMIT y OFFSET x
        $sql = $this->convertLimit($sql, 'postgres');
        
        // 4. Converti le funzioni di data/ora
        $sql = $this->convertDateFunctions($sql, 'postgres');
        
        // 5. Converti le funzioni di stringa
        $sql = $this->convertStringFunctions($sql, 'postgres');
        
        // 6. Gestisci AUTO_INCREMENT -> SERIAL
        $sql = $this->convertAutoIncrement($sql, 'postgres');
        
        // 7. Converti GROUP BY con colonne non aggregate (PostgreSQL è più restrittivo)
        $sql = $this->handleGroupBy($sql, 'postgres');
        
        return $sql;
    }
    
    /**
     * Converte una query nel formato SQLite
     * 
     * @param string $sql Query SQL
     * @param array &$params Parametri
     * @return string Query convertita
     */
    private function convertToSqlite($sql, &$params) {
        // 1. Converti i backtick in virgolette doppie
        $sql = $this->convertQuotes($sql, 'sqlite');
        
        // 2. I placeholder ? vanno bene per SQLite
        
        // 3. LIMIT è già nel formato corretto per SQLite
        
        // 4. Converti le funzioni di data/ora
        $sql = $this->convertDateFunctions($sql, 'sqlite');
        
        // 5. Converti le funzioni di stringa
        $sql = $this->convertStringFunctions($sql, 'sqlite');
        
        // 6. SQLite non ha un vero tipo BOOLEAN
        $sql = $this->convertBooleans($sql, 'sqlite');
        
        return $sql;
    }
    
    /**
     * Converte una query nel formato MySQL
     * 
     * @param string $sql Query SQL
     * @param array &$params Parametri
     * @return string Query convertita
     */
    private function convertToMysql($sql, &$params) {
        // 1. Converti le virgolette doppie in backtick
        $sql = $this->convertQuotes($sql, 'mysql');
        
        // 2. Converti i placeholder numerati $1, $2 in ?
        $sql = $this->convertPlaceholders($sql, 'mysql', $params);
        
        // 3. Converti LIMIT y OFFSET x in LIMIT x,y
        $sql = $this->convertLimit($sql, 'mysql');
        
        // 4. Converti le funzioni di data/ora
        $sql = $this->convertDateFunctions($sql, 'mysql');
        
        // 5. Converti le funzioni di stringa
        $sql = $this->convertStringFunctions($sql, 'mysql');
        
        return $sql;
    }
    
    /**
     * Converte le quote degli identificatori
     * 
     * @param string $sql Query SQL
     * @param string $target_db Database target
     * @return string Query con quote convertite
     */
    private function convertQuotes($sql, $target_db): string {
        if ($target_db === 'mysql') {
            // Converti virgolette doppie in backtick
            $sql = $this->replace('/"([^"]+)"/', '`$1`', (string) $sql);
        } else {
            // Converti backtick in virgolette doppie per PostgreSQL e SQLite
            $sql = $this->replace('/`([^`]+)`/', '"$1"', (string) $sql);
        }
        return (string) $sql;
    }
    
    /**
     * Converte i placeholder per i parametri
     * 
     * @param string $sql Query SQL
     * @param string $target_db Database target
     * @param array &$params Parametri
     * @return string Query con placeholder convertiti
     */
    private function convertPlaceholders($sql, $target_db, &$params): string {
        if ($target_db === 'postgres') {
            // Converti ? in $1, $2, ecc.
            $count = 0;
            $sql = $this->replaceCallback('/\?/', function(array $_matches) use (&$count): string {
                $count++;
                return '$' . $count;
            }, (string) $sql);
        } elseif ($target_db === 'mysql' || $target_db === 'sqlite') {
            // Converti $1, $2 in ?
            $sql = $this->replace('/\$\d+/', '?', (string) $sql);
        }
        return (string) $sql;
    }
    
    /**
     * Converte la sintassi LIMIT
     * 
     * @param string $sql Query SQL
     * @param string $target_db Database target
     * @return string Query con LIMIT convertito
     */
    private function convertLimit($sql, $target_db): string {
        if ($target_db === 'postgres') {
            // Converti LIMIT x,y in LIMIT y OFFSET x
            $sql = $this->replace('/LIMIT\s+(\d+)\s*,\s*(\d+)/i', 'LIMIT $2 OFFSET $1', (string) $sql);
        } elseif ($target_db === 'mysql' || $target_db === 'sqlite') {
            // Converti LIMIT y OFFSET x in LIMIT x,y
            $sql = $this->replace('/LIMIT\s+(\d+)\s+OFFSET\s+(\d+)/i', 'LIMIT $2,$1', (string) $sql);
        }
        return (string) $sql;
    }
    
    /**
     * Converte le funzioni di data/ora
     * 
     * @param string $sql Query SQL
     * @param string $target_db Database target
     * @return string Query con funzioni data convertite
     */
    private function convertDateFunctions($sql, $target_db): string {
        switch ($target_db) {
            case 'postgres':
                // NOW() -> CURRENT_TIMESTAMP
                $sql = $this->replace('/\bNOW\(\)/i', 'CURRENT_TIMESTAMP', (string) $sql);
                // CURDATE() -> CURRENT_DATE
                $sql = $this->replace('/\bCURDATE\(\)/i', 'CURRENT_DATE', (string) $sql);
                // DATE_FORMAT -> TO_CHAR
                $sql = $this->replaceCallback(
                    '/DATE_FORMAT\s*\(\s*([^,]+)\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/i',
                    function(array $matches): string {
                        $field = $matches[1];
                        $format = $this->convertDateFormatToPostgres($matches[2]);
                        return "TO_CHAR($field, '$format')";
                    },
                    (string) $sql
                );
                break;
                
            case 'sqlite':
                // NOW() -> datetime('now')
                $sql = $this->replace('/\bNOW\(\)/i', "datetime('now')", (string) $sql);
                // CURDATE() -> date('now')
                $sql = $this->replace('/\bCURDATE\(\)/i', "date('now')", (string) $sql);
                // DATE_FORMAT -> strftime
                $sql = $this->replaceCallback(
                    '/DATE_FORMAT\s*\(\s*([^,]+)\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/i',
                    function(array $matches): string {
                        $field = $matches[1];
                        $format = $this->convertDateFormatToSqlite($matches[2]);
                        return "strftime('$format', $field)";
                    },
                    (string) $sql
                );
                break;
                
            case 'mysql':
                // CURRENT_TIMESTAMP -> NOW()
                $sql = $this->replace('/\bCURRENT_TIMESTAMP\b/i', 'NOW()', (string) $sql);
                // TO_CHAR -> DATE_FORMAT (conversione base)
                $sql = $this->replaceCallback(
                    '/TO_CHAR\s*\(\s*([^,]+)\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/i',
                    function(array $matches): string {
                        $field = $matches[1];
                        $format = $this->convertDateFormatToMysql($matches[2]);
                        return "DATE_FORMAT($field, '$format')";
                    },
                    (string) $sql
                );
                break;
        }
        return (string) $sql;
    }
    
    /**
     * Converte le funzioni di stringa
     * 
     * @param string $sql Query SQL
     * @param string $target_db Database target
     * @return string Query con funzioni stringa convertite
     */
    private function convertStringFunctions($sql, $target_db): string {
        switch ($target_db) {
            case 'postgres':
                // CONCAT -> ||
                $sql = $this->replaceCallback(
                    '/CONCAT\s*\(([^)]+)\)/i',
                    function(array $matches): string {
                        $parts = explode(',', $matches[1]);
                        return implode(' || ', $parts);
                    },
                    (string) $sql
                );
                // SUBSTRING -> SUBSTR
                $sql = $this->replace('/\bSUBSTRING\(/i', 'SUBSTR(', (string) $sql);
                break;
                
            case 'sqlite':
                // CONCAT non esiste in SQLite, usa ||
                $sql = $this->replaceCallback(
                    '/CONCAT\s*\(([^)]+)\)/i',
                    function(array $matches): string {
                        $parts = explode(',', $matches[1]);
                        return implode(' || ', $parts);
                    },
                    (string) $sql
                );
                break;
                
            case 'mysql':
                // || -> CONCAT (se non in modalità PIPES_AS_CONCAT)
                $sql = $this->replaceCallback(
                    '/(\w+)\s*\|\|\s*(\w+)/',
                    function(array $matches): string {
                        return "CONCAT({$matches[1]}, {$matches[2]})";
                    },
                    (string) $sql
                );
                break;
        }
        return (string) $sql;
    }
    
    /**
     * Converte AUTO_INCREMENT
     * 
     * @param string $sql Query SQL
     * @param string $target_db Database target
     * @return string Query convertita
     */
    private function convertAutoIncrement($sql, $target_db): string {
        if ($target_db === 'postgres') {
            // AUTO_INCREMENT -> SERIAL
            $sql = $this->replace('/\bAUTO_INCREMENT\b/i', 'SERIAL', (string) $sql);
        }
        return (string) $sql;
    }
    
    /**
     * Gestisce le differenze di GROUP BY
     * 
     * @param string $sql Query SQL
     * @param string $target_db Database target
     * @return string Query convertita
     */
    private function handleGroupBy($sql, $target_db): string {
        // PostgreSQL richiede che tutte le colonne non aggregate siano nel GROUP BY
        // Questa è una conversione complessa che richiederebbe parsing completo
        // Per ora lasciamo la query com'è
        return (string) $sql;
    }
    
    /**
     * Converte i valori booleani
     * 
     * @param string $sql Query SQL
     * @param string $target_db Database target
     * @return string Query convertita
     */
    private function convertBooleans($sql, $target_db): string {
        if ($target_db === 'sqlite') {
            // TRUE -> 1, FALSE -> 0
            $sql = $this->replace('/\bTRUE\b/i', '1', (string) $sql);
            $sql = $this->replace('/\bFALSE\b/i', '0', (string) $sql);
        }
        return (string) $sql;
    }
    
    /**
     * Converte il formato data da MySQL a PostgreSQL
     * 
     * @param string $format Formato MySQL
     * @return string Formato PostgreSQL
     */
    private function convertDateFormatToPostgres($format) {
        $conversions = [
            '%Y' => 'YYYY',
            '%y' => 'YY',
            '%m' => 'MM',
            '%d' => 'DD',
            '%H' => 'HH24',
            '%i' => 'MI',
            '%s' => 'SS'
        ];
        return strtr($format, $conversions);
    }
    
    /**
     * Converte il formato data da MySQL a SQLite
     * 
     * @param string $format Formato MySQL
     * @return string Formato SQLite
     */
    private function convertDateFormatToSqlite($format) {
        // SQLite usa il formato strftime
        // Questa è una conversione semplificata
        return str_replace(['%i', '%s'], ['%M', '%S'], $format);
    }
    
    /**
     * Converte il formato data da PostgreSQL a MySQL
     * 
     * @param string $format Formato PostgreSQL
     * @return string Formato MySQL
     */
    private function convertDateFormatToMysql($format) {
        $conversions = [
            'YYYY' => '%Y',
            'YY' => '%y',
            'MM' => '%m',
            'DD' => '%d',
            'HH24' => '%H',
            'MI' => '%i',
            'SS' => '%s'
        ];
        return strtr($format, $conversions);
    }
}
