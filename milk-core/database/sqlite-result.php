<?php
namespace MilkCore;

/**
 * Wrapper per SQLite3Result che converte i metodi in snake_case
 * per consistenza con lo stile del framework
 */
class SQLiteResult
{
    private $sqlite_result;
    private int $current_position = 0;
    private ?int $cached_row_count = null;
    private bool $at_end = false;

    public function __construct($sqlite_result)
    {
        $this->sqlite_result = $sqlite_result;
    }

    /**
     * Restituisce il nome di una colonna per indice
     * 
     * @param int $column Indice della colonna (0-based)
     * @return string|false Nome della colonna o false se non trovata
     */
    public function column_name(int $column): string|false
    {
        return $this->sqlite_result->columnName($column);
    }

    /**
     * Restituisce il tipo di una colonna per indice
     * 
     * @param int $column Indice della colonna (0-based)
     * @return int|false Tipo della colonna o false se non trovata
     */
    public function column_type(int $column): int|false
    {
        return $this->sqlite_result->columnType($column);
    }

    /**
     * Fetch di un array con modalità specificata
     * 
     * @param int $mode SQLITE3_BOTH, SQLITE3_ASSOC, or SQLITE3_NUM
     * @return array|false Array dei dati o false se non ci sono più righe
     */
    public function fetch_array(): array|false
    {
        $result = $this->sqlite_result->fetchArray(SQLITE3_ASSOC);
        
        if ($result !== false) {
            $this->current_position++;
            $this->at_end = false;
        } else {
            $this->at_end = true;
        }
        
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
     * Restituisce il numero di colonne nel result set
     * 
     * @return int Numero di colonne
     */
    public function num_columns(): int
    {
        return $this->sqlite_result->numColumns();
    }

    /**
     * Restituisce il numero di righe nel result set
     * Utilizza cache e tracking della posizione per evitare loop infiniti
     * 
     * @return int Numero di righe
     */
    public function num_rows(): int
    {
        // Se abbiamo già il conteggio in cache, lo restituiamo
        if ($this->cached_row_count !== null) {
            return $this->cached_row_count;
        }
        
        // Salviamo lo stato corrente
        $saved_position = $this->current_position;
        $was_at_end = $this->at_end;
        
        // Se siamo già nel mezzo del result set, contiamo da dove siamo
        $count = $this->current_position;
        
        // Se non siamo già alla fine, contiamo le righe rimanenti
        if (!$this->at_end) {
            while ($this->sqlite_result->fetchArray(SQLITE3_NUM) !== false) {
                $count++;
            }
        }
        
        // Salviamo il conteggio totale in cache
        $this->cached_row_count = $count;
        
        // Reset al primo record
        $this->sqlite_result->reset();
        $this->current_position = 0;
        $this->at_end = false;
        
        // Ripristiniamo la posizione originale
        if ($saved_position > 0) {
            // IMPORTANTE: Dobbiamo posizionarci PRIMA della riga saved_position
            // perché il prossimo fetch_array nel while esterno leggerà quella riga
            $target_position = $was_at_end ? $saved_position : $saved_position ;
            
            // Avanziamo fino alla posizione target
            for ($i = 0; $i < $target_position; $i++) {
                if ($this->sqlite_result->fetchArray(SQLITE3_NUM) === false) {
                    break;
                }
            }
            
            // Ripristiniamo lo stato
            $this->current_position = $saved_position;
            $this->at_end = $was_at_end;
        }
        
        return $this->cached_row_count;
    }

    /**
     * Reset del result pointer alla prima riga
     * 
     * @return bool True in caso di successo
     */
    public function reset(): bool
    {
        $result = $this->sqlite_result->reset();
        if ($result) {
            $this->current_position = 0;
            $this->at_end = false;
            // Non resettiamo la cache perché il numero di righe non cambia
        }
        return $result;
    }

    /**
     * Reset del result pointer alla riga specificata
     * 
     * @param int $offset Indice della riga (0-based)
     * @return bool True in caso di successo
     */
    public function data_seek(int $offset): bool
    {
        // Verifica rapida se abbiamo la cache e l'offset è fuori range
        if ($this->cached_row_count !== null && $offset >= $this->cached_row_count) {
            return false;
        }
        
        // Reset del result set all'inizio
        $this->sqlite_result->reset();
        $this->current_position = 0;
        $this->at_end = false;
        
        if ($offset > 0) {
            // Salta le righe fino a raggiungere l'offset desiderato
            for ($i = 0; $i < $offset; $i++) {
                $row = $this->sqlite_result->fetchArray();
                if ($row === false) {
                    // Se non ci sono abbastanza righe, ritorna false
                    $this->at_end = true;
                    return false;
                }
                $this->current_position++;
            }
        }
        
        return true;
    }

    /**
     * Libera la memoria del result set
     * 
     * @return true
     */
    public function finalize(): true
    {
        // Reset delle variabili interne
        $this->current_position = 0;
        $this->cached_row_count = null;
        $this->at_end = false;
        
        return $this->sqlite_result->finalize();
    }

    public function free(): true
    {
        return $this->finalize();
    }

    // Proxy per accesso diretto ai metodi originali se necessario
    public function __call($method, $args)
    {
        if (method_exists($this->sqlite_result, $method)) {
            return call_user_func_array([$this->sqlite_result, $method], $args);
        }

        throw new \BadMethodCallException("Method {$method} does not exist");
    }

    // Proxy per le proprietà
    public function __get($property)
    {
        return $this->sqlite_result->$property ?? null;
    }
}