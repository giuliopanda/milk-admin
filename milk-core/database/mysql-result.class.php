<?php
namespace MilkCore;

/**
 * Wrapper per mysqli_result per standardizzare le funzioni di fetch
 */
class MySQLResult
{
    private $mysqli_result;
    private $column_names_cache = null;
    private $column_types_cache = null;
    
    public function __construct($mysqli_result)
    {
        $this->mysqli_result = $mysqli_result;
    }
    
    /**
     * Restituisce il nome di una colonna per indice (SQLite3-like)
     * 
     * @param int $column Indice della colonna (0-based)
     * @return string|false Nome della colonna o false se non trovata
     */
    public function column_name(int $column): string|false
    {
        if ($this->column_names_cache === null) {
            $this->load_column_metadata();
        }
        
        return $this->column_names_cache[$column] ?? false;
    }
    
    /**
     * Restituisce il tipo di una colonna per indice (SQLite3-like)
     * 
     * @param int $column Indice della colonna (0-based)
     * @return int|false Tipo della colonna o false se non trovata
     */
    public function column_type(int $column): int|false
    {
        if ($this->column_types_cache === null) {
            $this->load_column_metadata();
        }
        
        return $this->column_types_cache[$column] ?? false;
    }
    
    /**
     * Fetch di un array 
     * 
     * @return array|false Array dei dati o false se non ci sono più righe
     */
    public function fetch_array(): array|null|false
    {
         return $this->mysqli_result->fetch_assoc();
    }

    /**
     * Fetch di un array associativo
     * 
     * @return array|false Array dei dati o false se non ci sono più righe
     */
    public function fetch_assoc(): array|null|false
    {
        return $this->mysqli_result->fetch_assoc();
    }


    public function fetch_object(): object|null|false
    {
        return $this->mysqli_result->fetch_object();
    }
    
    /**
     * Restituisce il numero di colonne nel result set
     * 
     * @return int Numero di colonne
     */
    public function num_columns(): int
    {
        return $this->mysqli_result->field_count;
    }
    
    public function num_rows(): int
    {
        return $this->mysqli_result->num_rows;
    }
    

    /**
     * Reset del result pointer alla prima riga
     * 
     * @return bool True in caso di successo
     */
    public function reset(): bool
    {
        return $this->mysqli_result->data_seek(0);
    }

    public function data_seek(int $offset): bool
    {
        return $this->mysqli_result->data_seek($offset);
    }
    
    /**
     * Libera la memoria del result set
     * 
     * @return true
     */
    public function finalize(): true
    {
        if ($this->mysqli_result) {
            $this->mysqli_result->free();
        }
        return true;
    }
    
    /**
     * Carica i metadata delle colonne una sola volta (lazy loading)
     */
    private function load_column_metadata(): void
    {
        $this->column_names_cache = [];
        $this->column_types_cache = [];
        
        $fields = $this->mysqli_result->fetch_fields();
        
        foreach ($fields as $index => $field) {
            $this->column_names_cache[$index] = $field->name;
            $this->column_types_cache[$index] = $this->map_mysql_type_to_sqlite($field->type);
        }
    }
    
    /**
     * Mappa i tipi MySQL ai tipi SQLite3 per compatibilità
     * 
     * @param int $mysql_type Tipo MySQL (costanti MYSQLI_TYPE_*)
     * @return int Tipo mappato compatibile con SQLite3
     */
    private function map_mysql_type_to_sqlite(int $mysql_type): int
    {
        // Mapping approssimativo tra tipi MySQL e SQLite3
        switch ($mysql_type) {
            case MYSQLI_TYPE_TINY:
            case MYSQLI_TYPE_SHORT:
            case MYSQLI_TYPE_LONG:
            case MYSQLI_TYPE_LONGLONG:
            case MYSQLI_TYPE_INT24:
                return 1; // SQLITE3_INTEGER
                
            case MYSQLI_TYPE_FLOAT:
            case MYSQLI_TYPE_DOUBLE:
            case MYSQLI_TYPE_DECIMAL:
            case MYSQLI_TYPE_NEWDECIMAL:
                return 2; // SQLITE3_FLOAT
                
            case MYSQLI_TYPE_NULL:
                return 5; // SQLITE3_NULL
                
            default:
                return 3; // SQLITE3_TEXT
        }
    }
    
    // Proxy per tutti gli altri metodi di mysqli_result
    public function __call($method, $args)
    {
        if (method_exists($this->mysqli_result, $method)) {
            return call_user_func_array([$this->mysqli_result, $method], $args);
        }
        
        throw new \BadMethodCallException("Method {$method} does not exist");
    }
    
    // Proxy per le proprietà
    public function __get($property)
    {
        return $this->mysqli_result->$property ?? null;
    }
}
