<?php
namespace App\Database;

/**
 * Wrapper per mysqli_result con eager fetching per uniformità con SQLiteResult
 * Tutti i risultati vengono caricati in memoria immediatamente per:
 * - Liberare subito il buffer MySQL
 * - Comportamento uniforme con SQLiteResult
 * - Prevenire problemi con result set aperti
 */
class MySQLResult implements ResultInterface, \IteratorAggregate
{
    private $mysqli_result;
    private $column_names_cache = null;
    private $column_types_cache = null;
    private int $current_position = 0;
    private int $cached_row_count = 0;
    private array $cached_rows = [];
    private bool $at_end = false;

    public function __construct($mysqli_result)
    {
        $this->mysqli_result = $mysqli_result;

        // Eager fetch: load all rows immediately and free MySQL buffer
        $this->fetchAllRows();
    }

    /**
     * Fetch all rows into memory and free the native result set
     * This ensures consistent behavior with SQLiteResult
     */
    private function fetchAllRows(): void
    {
        // Load column metadata first
        $this->loadColumnMetadata();

        // Fetch all rows into memory
        while ($row = $this->mysqli_result->fetch_assoc()) {
            $this->cached_rows[] = $row;
        }

        $this->cached_row_count = count($this->cached_rows);
        $this->at_end = ($this->cached_row_count === 0);

        // Free the native result set to release MySQL buffer
        $this->mysqli_result->free();
    }
    
    /**
     * Restituisce il nome di una colonna per indice (SQLite3-like)
     *
     * @param int $column Indice della colonna (0-based)
     * @return string|false Nome della colonna o false se non trovata
     */
    public function column_name(int $column): string|false
    {
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
        return $this->column_types_cache[$column] ?? false;
    }
    
    /**
     * Fetch di un array
     *
     * @return array|false Array dei dati o false se non ci sono più righe
     */
    public function fetch_array(): array|false
    {
        if ($this->current_position >= $this->cached_row_count) {
            $this->at_end = true;
            return false;
        }

        $result = $this->cached_rows[$this->current_position];
        $this->current_position++;
        $this->at_end = ($this->current_position >= $this->cached_row_count);

        return $result;
    }

    /**
     * Fetch di un array associativo
     *
     * @return array|false Array dei dati o false se non ci sono più righe
     */
    public function fetch_assoc(): array|false
    {
        return $this->fetch_array();
    }

    /**
     * Fetch di un oggetto
     *
     * @return object|false Oggetto con i dati o false se non ci sono più righe
     */
    public function fetch_object(): object|false
    {
        $row = $this->fetch_array();

        if ($row === false) {
            return false;
        }

        return (object) $row;
    }
    
    /**
     * Restituisce il numero di colonne nel result set
     *
     * @return int Numero di colonne
     */
    public function num_columns(): int
    {
        return count($this->column_names_cache);
    }

    /**
     * Restituisce il numero di righe nel result set
     * Since rows are eagerly cached, this is instant
     *
     * @return int Numero di righe
     */
    public function num_rows(): int
    {
        return $this->cached_row_count;
    }

    /**
     * Check if cursor is at the end of the result set
     *
     * @return bool True if at end, false otherwise
     */
    public function is_at_end(): bool
    {
        return $this->at_end;
    }

    /**
     * Reset del result pointer alla prima riga
     *
     * @return bool True in caso di successo
     */
    public function reset(): bool
    {
        $this->current_position = 0;
        $this->at_end = ($this->cached_row_count === 0);
        return true;
    }

    /**
     * Sposta il cursore a una specifica riga
     *
     * @param int $offset Indice della riga (0-based)
     * @return bool True in caso di successo
     */
    public function data_seek(int $offset): bool
    {
        if ($offset < 0 || $offset >= $this->cached_row_count) {
            return false;
        }

        $this->current_position = $offset;
        $this->at_end = ($offset >= $this->cached_row_count);
        return true;
    }
    
    /**
     * Libera la memoria del result set
     * Since we already freed the native result in constructor, just cleanup
     *
     * @return true
     */
    public function finalize(): true
    {
        // Clear cached data
        $this->cached_rows = [];
        $this->column_names_cache = [];
        $this->column_types_cache = [];
        $this->current_position = 0;
        $this->cached_row_count = 0;
        $this->at_end = true;

        return true;
    }

    /**
     * Alias per finalize() (compatibilità MySQLi)
     *
     * @return true
     */
    public function free(): true
    {
        return $this->finalize();
    }
    
    /**
     * Carica i metadata delle colonne una sola volta (lazy loading)
     */
    private function loadColumnMetadata(): void
    {
        $this->column_names_cache = [];
        $this->column_types_cache = [];
        
        $fields = $this->mysqli_result->fetch_fields();
        
        foreach ($fields as $index => $field) {
            $this->column_names_cache[$index] = $field->name;
            $this->column_types_cache[$index] = $this->mapMysqlTypeToSqlite($field->type);
        }
    }
    
    /**
     * Mappa i tipi MySQL ai tipi SQLite3 per compatibilità
     * 
     * @param int $mysql_type Tipo MySQL (costanti MYSQLI_TYPE_*)
     * @return int Tipo mappato compatibile con SQLite3
     */
    private function mapMysqlTypeToSqlite(int $mysql_type): int
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

    /**
     * Ottiene i nomi dei campi come array
     *
     * @return array Array di nomi di colonne
     */
    public function get_fields(): array
    {
        return $this->column_names_cache;
    }

    // Proxy methods removed - native result is already freed

    /**
     * Rende l'oggetto iterabile con foreach
     */
    public function getIterator(): \Traversable
    {
        // Reset position to beginning for iteration
        $savedPosition = $this->current_position;
        $this->current_position = 0;

        foreach ($this->cached_rows as $row) {
            yield (object)$row;
        }

        // Restore position after iteration
        $this->current_position = $savedPosition;
    }
}
