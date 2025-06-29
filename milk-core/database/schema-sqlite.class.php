<?php
namespace MilkCore;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Database Schema Management Class for SQLite
 * 
 * This class provides a fluent interface for creating and modifying SQLite tables
 * programmatically. It allows for defining fields, indexes, and table properties
 * in a clean, object-oriented way.
 * 
 * @example
 * ```php
 * // Create a new users table
 * $schema = Get::schema('#__users');
 * $schema->id()
 *     ->string('username', 100)
 *     ->string('email', 255)
 *     ->string('password', 255)
 *     ->int('status', false, 1)
 *     ->timestamp('created_at')
 *     ->index('email', 'email')
 *     ->create();
 * ```
 *
 * @package     MilkCore
 * @version     1.0.0 SQLite
 */

class SchemaSqlite {
    public string $table;
    public ?string $last_error = null;
    
    private array $fields = [];
    private array $indices = [];
    private ?array $primary_keys = [];
    private ?string $primary_key = null;  // manteniamo per retrocompatibilità

    private \MilkCore\SQLite $db;

    public function __construct(string $table, $db = null) {
        $this->table = $table;
        if ($db == null) {
            $this->db = Get::db();
        } else {
            $this->db = $db;
        }
    }

    /**
     * Creates an auto-incrementing primary key field
     * 
     * In SQLite, this creates an INTEGER PRIMARY KEY which is automatically AUTOINCREMENT
     * 
     * @param string $name The name of the primary key field (default: 'id')
     * @return self Returns the Schema instance for method chaining
     */
    public function id(string $name = 'id'): self {
        $field = new FieldSqlite($name);
        $field->type = 'INTEGER';
        $field->auto_increment = true;
        $field->primary_key = true;
        $this->primary_key = $name;  // per retrocompatibilità
        $this->primary_keys = [$name];
        $this->fields[$name] = $field;
        return $this;
    }

    /**
     * Creates an integer field
     * 
     * @param string $name The name of the field
     * @param bool $null Whether the field can be NULL (default: true)
     * @param int|null $default The default value for the field (default: null)
     * @param string|null $after Not used in SQLite (kept for compatibility)
     * @return self Returns the Schema instance for method chaining
     */
    public function int(string $name, bool $null = true, ?int $default = null, ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'INTEGER';
        $field->nullable = $null;
        $field->default = $default;
        $this->fields[$name] = $field;
        return $this;
    }

    /**
     * Creates a tiny integer field
     * 
     * In SQLite, this is mapped to INTEGER
     * 
     * @param string $name The name of the field
     * @param bool $null Whether the field can be NULL (default: true)
     * @param int|null $default The default value for the field (default: null)
     * @param string|null $after Not used in SQLite (kept for compatibility)
     * @return self Returns the Schema instance for method chaining
     */
    public function tinyint(string $name, bool $null = true, ?int $default = null, ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'INTEGER';
        $field->nullable = $null;
        $field->default = $default;
        $this->fields[$name] = $field;
        return $this;
    }

    /**
     * Creates a string (TEXT) field
     * 
     * In SQLite, VARCHAR is mapped to TEXT
     * 
     * @param string $name The name of the field
     * @param int $length Not used in SQLite (kept for compatibility)
     * @param bool $null Whether the field can be NULL (default: true)
     * @param string|null $default The default value for the field (default: null)
     * @param string|null $after Not used in SQLite (kept for compatibility)
     * @return self Returns the Schema instance for method chaining
     */
    public function string(string $name, int $length = 255, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'TEXT';
        $field->nullable = $null;
        $field->default = $default;
        $this->fields[$name] = $field;
        return $this;
    }

    /**
     * Creates a text field
     * 
     * @param string $name The name of the field
     * @param bool $null Whether the field can be NULL (default: true)
     * @param string|null $after Not used in SQLite (kept for compatibility)
     * @return self Returns the Schema instance for method chaining
     */
    public function text(string $name, bool $null = true, ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'TEXT';
        $field->nullable = $null;
        $this->fields[$name] = $field;
        return $this;
    }

    public function longtext(string $name, bool $null = true, ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'TEXT';
        $field->nullable = $null;
        $this->fields[$name] = $field;
        return $this;
    }

    public function datetime(string $name, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'TEXT';  // SQLite memorizza datetime come TEXT
        $field->nullable = $null;
        $field->default = $default;
        $this->fields[$name] = $field;
        return $this;
    }

    public function date(string $name, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'TEXT';  // SQLite memorizza date come TEXT
        $field->nullable = $null;
        $field->default = $default;
        $this->fields[$name] = $field;
        return $this;
    }

    public function time(string $name, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'TEXT';  // SQLite memorizza time come TEXT
        $field->nullable = $null;
        $field->default = $default;
        $this->fields[$name] = $field;
        return $this;
    }

    public function timestamp(string $name, bool $null = true, ?string $default = 'CURRENT_TIMESTAMP', ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'TEXT';  // SQLite memorizza timestamp come TEXT
        $field->nullable = $null;
        $field->default = $default;
        $this->fields[$name] = $field;
        return $this;
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2, bool $null = true, ?float $default = null, ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'REAL';  // SQLite usa REAL per i decimali
        $field->nullable = $null;
        $field->default = $default;
        $this->fields[$name] = $field;
        return $this;
    }

    public function boolean(string $name, bool $null = true, ?bool $default = false, ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'INTEGER';
        $field->nullable = $null;
        $field->default = $default ? 1 : 0;
        $this->fields[$name] = $field;
        return $this;
    }

    public function index(string $name, array $columns = [], bool $unique = false): self {
        // Se columns è vuoto, usa il nome come colonna
        if (empty($columns)) {
            $columns = [$name];
        }
        $this->indices[$name] = new IndexSqlite($name, $columns, $unique);
        return $this;
    }

    public function set_primary_key(array $fields): self {
        foreach ($fields as $field) {
            if (isset($this->fields[$field])) {
                $this->fields[$field]->primary_key = true;
                $this->primary_keys[] = $field;
            }
        }
        return $this;
    }

    // CREATION
    public function create(): bool {
        if (empty($this->fields)) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS " . $this->db->qn($this->table) . " (\n";
        
        // Fields
        $fields_sql = [];
        foreach ($this->fields as $field) {
            $fields_sql[] = "  " . $field->to_sql_sqlite();
        }

        // Gestione chiavi primarie composite
        if (!empty($this->primary_keys) && count($this->primary_keys) > 1) {
            $fields_sql[] = "  PRIMARY KEY (`" . implode("`, `", $this->primary_keys) . "`)";
        }

        $sql .= implode(",\n", $fields_sql);
        $sql .= "\n);";

        // Esegui la creazione tabella
        $this->db->query($sql);
        if ($this->db->error) {
            $this->last_error = $this->db->last_error;
            return false;
        }

        // Crea gli indici separatamente (SQLite richiede CREATE INDEX separati)
        foreach ($this->indices as $index) {
            $index_sql = $index->to_sql_sqlite($this->table);
            $this->db->query($index_sql);
            if ($this->db->error) {
                $this->last_error = $this->db->last_error;
                return false;
            }
        }

        return true;
    }

    public function drop(): bool {
        $sql = "DROP TABLE IF EXISTS " . $this->db->qn($this->table);
        $this->db->query($sql);
        return !$this->db->error;
    }

    /**
     * ALTER TABLE METHODS - Versione migliorata per SQLite
     */
    public function modify($force_update = false): bool {
        // Ottieni la struttura corrente
        $current_fields = $this->get_current_fields();
        $current_indices = $this->get_current_indices();
        
        //@TODO: missing check:  if (!$this->validate_modifications($current_fields) && !$force_update) {
       
        // Determina se sono necessarie modifiche complesse che richiedono ricostruzione tabella
        $needs_reconstruction = $this->needs_table_reconstruction($current_fields);
        
        if ($needs_reconstruction) {
            return $this->reconstruct_table($current_fields, $current_indices);
        } else {
            return $this->simple_alter_table($current_fields, $current_indices);
        }
    }

    /**
     * Determina se la tabella necessita di ricostruzione completa
     */
    private function needs_table_reconstruction(array $current_fields): bool {
        // Controlla se ci sono campi da rimuovere
        foreach ($current_fields as $name => $field) {
            if (!isset($this->fields[$name])) {
                return true;
            }
        }
        
        // Controlla se ci sono campi da modificare
        foreach ($this->fields as $name => $field) {
            if (isset($current_fields[$name])) {
                if (!$field->compare($current_fields[$name])) {
                    return true;
                }
            }
        }
        
        // Controlla se l'ordine dei campi è cambiato
        $current_order = array_keys($current_fields);
        $new_order = array_keys($this->fields);
        if ($current_order !== $new_order) {
            return true;
        }
        
        // Controlla modifiche alla chiave primaria
        $current_primary_keys = array_keys(array_filter($current_fields, fn($f) => $f->primary_key));
        $new_primary_keys = !empty($this->primary_keys) ? $this->primary_keys : ($this->primary_key ? [$this->primary_key] : []);
        if ($current_primary_keys !== $new_primary_keys) {
            return true;
        }
        
        return false;
    }

    /**
     * Gestisce modifiche semplici che non richiedono ricostruzione
     */
    private function simple_alter_table(array $current_fields, array $current_indices): bool {
        // Aggiungi nuovi campi
        foreach ($this->fields as $name => $field) {
            if (!isset($current_fields[$name])) {
                $sql = "ALTER TABLE " . $this->db->qn($this->table) . " ADD COLUMN " . $field->to_sql_sqlite();
                $this->db->query($sql);
                if ($this->db->error) {
                    $this->last_error = $this->db->last_error;
                    return false;
                }
            }
        }
        
        // Gestisci indici
        return $this->update_indices($current_indices);
    }

    /**
     * Ricostruisce completamente la tabella per modifiche complesse
     */
    private function reconstruct_table(array $current_fields, array $current_indices): bool {
        $temp_table = $this->table . '_temp_' . uniqid();
        
        try {
            // 1. Crea tabella temporanea con la nuova struttura
            if (!$this->create_temp_table($temp_table)) {
                throw new \Exception("Impossibile creare tabella temporanea");
            }

            // 2. Copia i dati dalla tabella originale alla temporanea
            if (!$this->copy_data_to_temp($temp_table, $current_fields)) {
                throw new \Exception("Errore durante la copia dei dati: ". $this->db->last_error);
            }
            
            // 3. Elimina la tabella originale
            $this->db->query("DROP TABLE " . $this->db->qn($this->table));
            if ($this->db->error) {
                throw new \Exception("Errore durante l'eliminazione della tabella originale");
            }
            
            // 4. Rinomina la tabella temporanea
            $this->db->query("ALTER TABLE " . $this->db->qn($temp_table) . " RENAME TO " . $this->db->qn($this->table));
            if ($this->db->error) {
                throw new \Exception("Errore durante il rename della tabella");
            }
            
            // 5. Ricrea gli indici
            foreach ($this->indices as $index) {
                $sql = $index->to_sql_sqlite($this->table);
                $this->db->query($sql);
                if ($this->db->error) {
                    $this->last_error = $this->db->last_error;
                    return false;
                }
            }
            
            return true;
            
        } catch (\Exception $e) {
            // Cleanup in caso di errore
            $this->db->query("DROP TABLE IF EXISTS " . $this->db->qn($temp_table));
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Crea una tabella temporanea con la nuova struttura
     */
    private function create_temp_table(string $temp_table): bool {
        if (empty($this->fields)) {
            return false;
        }

        $sql = "CREATE TABLE " . $this->db->qn($temp_table) . " (\n";
        
        // Fields
        $fields_sql = [];
        foreach ($this->fields as $field) {
            $fields_sql[] = "  " . $field->to_sql_sqlite();
        }

        // Gestione chiavi primarie composite
        if (!empty($this->primary_keys) && count($this->primary_keys) > 1) {
            $fields_sql[] = "  PRIMARY KEY (`" . implode("`, `", $this->primary_keys) . "`)";
        }

        $sql .= implode(",\n", $fields_sql);
        $sql .= "\n);";

        $this->db->query($sql);
        return !$this->db->error;
    }

    /**
 * Copia i dati dalla tabella originale alla temporanea
 * Gestisce tutti i casi critici di migrazione dati
 */
private function copy_data_to_temp(string $temp_table, array $current_fields): bool {
    // 1. Analizza le differenze tra struttura vecchia e nuova
    $migration_plan = $this->analyze_field_changes($current_fields);
    
    // 2. Se non ci sono campi da copiare, la tabella sarà vuota ma valida
    if (empty($migration_plan['copy_fields']) && empty($migration_plan['new_fields'])) {
        return true;
    }
    
    // 3. Verifica se ci sono dati da migrare
    if (!$this->has_data()) {
        return true; // Tabella vuota, nessuna migrazione necessaria
    }
    
    // 4. Costruisci la query di inserimento
    $insert_query = $this->build_migration_query($temp_table, $migration_plan, $current_fields);
    
    // 5. Esegui la migrazione
    $this->db->query($insert_query);
    
    if ($this->db->error) {
        $this->last_error = "Errore durante la migrazione dati: " . $this->db->last_error;
        return false;
    }
    
    return true;
}

/**
 * Analizza le differenze tra struttura vecchia e nuova
 */
private function analyze_field_changes(array $current_fields): array {
    $plan = [
        'copy_fields' => [],      // Campi che esistono in entrambe le tabelle
        'new_fields' => [],       // Campi nuovi nella nuova struttura
        'removed_fields' => [],   // Campi rimossi dalla vecchia struttura
        'changed_fields' => []    // Campi modificati (tipo/proprietà)
    ];
    
    // Trova campi che esistono in entrambe le strutture
    foreach ($current_fields as $name => $old_field) {
        if (isset($this->fields[$name])) {
            $new_field = $this->fields[$name];
            
            // Verifica se il campo è stato modificato
            if (!$new_field->compare($old_field)) {
                $plan['changed_fields'][$name] = [
                    'old' => $old_field,
                    'new' => $new_field
                ];
            }
            
            $plan['copy_fields'][$name] = [
                'old' => $old_field,
                'new' => $new_field
            ];
        } else {
            $plan['removed_fields'][$name] = $old_field;
        }
    }
    
    // Trova campi nuovi
    foreach ($this->fields as $name => $new_field) {
        if (!isset($current_fields[$name])) {
            $plan['new_fields'][$name] = $new_field;
        }
    }
    
    return $plan;
}

/**
 * Costruisce la query di migrazione dei dati
 */
private function build_migration_query(string $temp_table, array $migration_plan, array $current_fields): string {
    $select_parts = [];
    $all_new_fields = array_keys($this->fields);
    
    // 1. Gestisci campi da copiare (esistenti in entrambe le tabelle)
    foreach ($migration_plan['copy_fields'] as $name => $field_info) {
        $old_field = $field_info['old'];
        $new_field = $field_info['new'];
        
        // Gestisci conversioni di tipo se necessario
        $select_parts[$name] = $this->build_field_conversion($name, $old_field, $new_field);
    }
    
    // 2. Gestisci campi nuovi (che non esistevano nella vecchia struttura)
    foreach ($migration_plan['new_fields'] as $name => $new_field) {
        $select_parts[$name] = $this->build_default_value($new_field);
    }
    
    // 3. Costruisci la query finale
    $field_names = [];
    $field_values = [];
    
    foreach ($all_new_fields as $name) {
        if (isset($select_parts[$name])) {
            $field_names[] = $this->db->qn($name);
            $field_values[] = $select_parts[$name];
        }
    }
    
    if (empty($field_names)) {
        return ""; // Nessun campo da inserire
    }
    
    $fields_list = implode(', ', $field_names);
    $values_list = implode(', ', $field_values);
    
    return "INSERT INTO " . $this->db->qn($temp_table) . 
           " ({$fields_list}) SELECT {$values_list} FROM " . $this->db->qn($this->table);
}

/**
 * Gestisce la conversione di un campo tra vecchia e nuova struttura
 */
private function build_field_conversion(string $name, FieldSqlite $old_field, FieldSqlite $new_field): string {
    $quoted_name = $this->db->qn($name);
    
    // Se i tipi sono compatibili, copia direttamente
    if ($old_field->normalize_type($old_field->type) === $new_field->normalize_type($new_field->type)) {
        return $quoted_name;
    }
    
    // Gestisci conversioni specifiche
    $old_type = $old_field->normalize_type($old_field->type);
    $new_type = $new_field->normalize_type($new_field->type);
    
    switch ($new_type) {
        case 'INTEGER':
            if ($old_type === 'TEXT') {
                // Converti testo in numero, usa 0 se non è numerico
                return "CASE WHEN {$quoted_name} GLOB '*[0-9]*' THEN CAST({$quoted_name} AS INTEGER) ELSE 0 END";
            } elseif ($old_type === 'REAL') {
                return "CAST({$quoted_name} AS INTEGER)";
            }
            break;
            
        case 'REAL':
            if ($old_type === 'TEXT') {
                return "CASE WHEN {$quoted_name} GLOB '*[0-9.]*' THEN CAST({$quoted_name} AS REAL) ELSE 0.0 END";
            } elseif ($old_type === 'INTEGER') {
                return "CAST({$quoted_name} AS REAL)";
            }
            break;
            
        case 'TEXT':
            // Qualsiasi tipo può essere convertito in testo
            return "CAST({$quoted_name} AS TEXT)";
    }
    
    // Fallback: copia il valore così com'è
    return $quoted_name;
}

/**
 * Genera un valore di default per un nuovo campo
 */
private function build_default_value(FieldSqlite $field): string {
    // Se il campo ha un default esplicito, usalo
    if ($field->default !== null) {
        if (is_string($field->default)) {
            if ($field->default === 'CURRENT_TIMESTAMP') {
                return "CURRENT_TIMESTAMP";
            } else {
                return "'" . str_replace("'", "''", $field->default) . "'";
            }
        } elseif (is_bool($field->default)) {
            return $field->default ? '1' : '0';
        } else {
            return (string)$field->default;
        }
    }
    
    // Se il campo può essere NULL, usa NULL
    if ($field->nullable) {
        return 'NULL';
    }
    
    // Altrimenti genera un default appropriato per il tipo
    switch ($field->normalize_type($field->type)) {
        case 'INTEGER':
            return '0';
        case 'REAL':
            return '0.0';
        case 'TEXT':
            return "''";
        default:
            return 'NULL';
    }
}

/**
 * Normalizza i tipi per il confronto in SQLite
 * Spostato in FieldSqlite per consistenza
 */
private function normalize_type(string $type): string {
    return (new FieldSqlite('temp'))->normalize_type($type);
}

    /**
     * Aggiorna solo gli indici (per modifiche semplici)
     */
    private function update_indices(array $current_indices): bool {
        // Drop indici rimossi
        foreach ($current_indices as $name => $index) {
            if (!isset($this->indices[$name])) {
                $sql = "DROP INDEX IF EXISTS " . $this->db->qn($name);
                $this->db->query($sql);
                if ($this->db->error) {
                    $this->last_error = $this->db->last_error;
                    return false;
                }
            }
        }

        // Aggiungi nuovi indici o modifica esistenti
        foreach ($this->indices as $name => $index) {
            if (!isset($current_indices[$name]) || !$index->compare($current_indices[$name])) {
                // Se l'indice esiste ma è diverso, eliminalo prima
                if (isset($current_indices[$name])) {
                    $sql = "DROP INDEX IF EXISTS " . $this->db->qn($name);
                    $this->db->query($sql);
                }
                
                // Crea il nuovo indice
                $sql = $index->to_sql_sqlite($this->table);
                $this->db->query($sql);
                if ($this->db->error) {
                    $this->last_error = $this->db->last_error;
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Verifica se la tabella ha dati
     */
    private function has_data(): bool {
        $result = $this->db->query("SELECT COUNT(*) as count FROM " . $this->db->qn($this->table));
        if ($result) {
            $row = $result->fetchArray();
            return ($row['count'] ?? 0) > 0;
        }
        return false;
    }

    public function exists(): bool {
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='{$this->table}'";
        $result = $this->db->query($sql);

        return $result && $result->fetchArray() !== false;
    }

    public function get_fields(): array {
        return $this->fields;
    }

    public function get_last_error() {
        return $this->last_error;   
    }

    private function get_current_fields(): array {
        $fields = [];
        $result = $this->db->query("PRAGMA table_info(" . $this->db->qn($this->table) . ")");
        
        while ($row = $result->fetchArray()) {
            $field = new FieldSqlite($row['name']);
            $field->type = strtoupper($row['type']);
            $field->nullable = ($row['notnull'] == 0);
            $field->default = $row['dflt_value'];
            $field->primary_key = ($row['pk'] == 1);
            
            $fields[$row['name']] = $field;
        }
        
        return $fields;
    }

    private function get_current_indices(): array {
        $indices = [];
        $result = $this->db->query("PRAGMA index_list(" . $this->db->qn($this->table) . ")");
        
        while ($row = $result->fetchArray()) {
            $index_name = $row['name'];
            
            // Salta gli indici automatici di SQLite
            if (strpos($index_name, 'sqlite_autoindex_') === 0) {
                continue;
            }
            
            // Ottieni info sull'indice
            $info_result = $this->db->query("PRAGMA index_info(" . $this->db->qn($index_name) . ")");
            $columns = [];
            
            while ($info = $info_result->fetchArray()) {
                $columns[] = $info['name'];
            }
            
            $indices[$index_name] = new IndexSqlite(
                $index_name,
                $columns,
                ($row['unique'] == 1)
            );
        }
        
        return $indices;
    }
}



/**
 * Support class for schema - SQLite version
 * 
 * @package     MilkCore
 * @ignore
 */

 class FieldSqlite {
    public string $name;
    public string $type;
    public ?int $length = null;
    public ?int $precision = null;
    public ?int $scale = null;
    public bool $nullable = false;
    public mixed $default = null;
    public bool $auto_increment = false;
    public bool $primary_key = false;
    public ?string $after = null;  // Mantenuto per compatibilità ma non usato in SQLite
    public bool $first = false;     // Mantenuto per compatibilità ma non usato in SQLite

    public function __construct(string $name) {
        $this->name = $name;
    }

    /**
     * Converte il campo in SQL per SQLite
     */
    public function to_sql_sqlite(): string {
        $db = Get::db();
        $sql = $db->qn($this->name) . " {$this->type}";

        // Primary key (per campi singoli)
        if ($this->primary_key && $this->type === 'INTEGER') {
            $sql .= " PRIMARY KEY";
            if ($this->auto_increment) {
                $sql .= " AUTOINCREMENT";
            }
        }

        // Not Null
        if (!$this->nullable && !$this->primary_key) {
            $sql .= " NOT NULL";
        }

        // Default value
        if ($this->default !== null) {
            if (is_string($this->default)) {
                if ($this->default === 'CURRENT_TIMESTAMP') {
                    $sql .= " DEFAULT CURRENT_TIMESTAMP";
                } else {
                    $sql .= " DEFAULT '{$this->default}'";
                }
            } elseif (is_bool($this->default)) {
                $sql .= " DEFAULT " . ($this->default ? 1 : 0);
            } else {
                $sql .= " DEFAULT {$this->default}";
            }
        }
        return $sql;
    }

    /**
     * Mantiene il metodo originale per compatibilità
     */
    public function to_sql(): string {
        return $this->to_sql_sqlite();
    }

    /**
     * Confronta due campi per verificare se sono uguali
     */
    public function compare(FieldSqlite $other): bool {
        // Per SQLite, ignoriamo length, precision e scale nel confronto
        // perché SQLite è type-affinity based
        return ($this->normalize_type($this->type) === $this->normalize_type($other->type) &&
                $this->nullable === $other->nullable &&
                $this->default === $other->default &&
                $this->auto_increment === $other->auto_increment &&
                $this->primary_key === $other->primary_key);
    }

    /**
     * Normalizza i tipi per il confronto in SQLite
     */
    public function normalize_type(string $type): string {
        $type = strtoupper($type);
        
        // Mappatura dei tipi MySQL ai tipi SQLite
        $type_map = [
            'INT' => 'INTEGER',
            'TINYINT' => 'INTEGER',
            'SMALLINT' => 'INTEGER',
            'MEDIUMINT' => 'INTEGER',
            'BIGINT' => 'INTEGER',
            'VARCHAR' => 'TEXT',
            'CHAR' => 'TEXT',
            'TINYTEXT' => 'TEXT',
            'MEDIUMTEXT' => 'TEXT',
            'LONGTEXT' => 'TEXT',
            'DATETIME' => 'TEXT',
            'DATE' => 'TEXT',
            'TIME' => 'TEXT',
            'TIMESTAMP' => 'TEXT',
            'DECIMAL' => 'REAL',
            'NUMERIC' => 'REAL',
            'FLOAT' => 'REAL',
            'DOUBLE' => 'REAL',
            'BOOLEAN' => 'INTEGER',
            'BOOL' => 'INTEGER'
        ];

        return $type_map[$type] ?? $type;
    }
}



/**
 * Support class for schema - SQLite version
 * 
 * @package     MilkCore
 * @ignore
 */

 class IndexSqlite {
    public string $name;
    public array $columns;
    public bool $unique = false;

    public function __construct(string $name, array $columns, bool $unique = false) {
        $this->name = $name;
        $this->columns = $columns;
        $this->unique = $unique;
    }

    /**
     * Genera l'SQL per creare l'indice in SQLite
     * 
     * @param string $table_name Nome della tabella su cui creare l'indice
     * @return string SQL per CREATE INDEX
     */
    public function to_sql_sqlite(string $table_name): string {
        $db = Get::db();
        $cols = implode('`,`', $this->columns);
        $type = $this->unique ? "CREATE UNIQUE INDEX" : "CREATE INDEX";
        return "{$type} IF NOT EXISTS " . $db->qn($this->name) . " ON " . $db->qn($table_name) . " (`{$cols}`)";
    }

    /**
     * Mantiene il metodo originale per compatibilità (usato all'interno di CREATE TABLE)
     * 
     * @return string SQL per definizione indice inline (non supportato in SQLite)
     */
    public function to_sql(): string {
        // Questo formato è per MySQL inline, SQLite non lo supporta
        // ma lo manteniamo per compatibilità dell'interfaccia
        $cols = implode('`,`', $this->columns);
        $type = $this->unique ? "UNIQUE" : "INDEX";
        $db = Get::db();
        return "{$type} " . $db->qn($this->name) . " (`{$cols}`)";
    }

    /**
     * Confronta due indici per verificare se sono uguali
     */
    public function compare(IndexSqlite $other): bool {
        return ($this->columns === $other->columns &&
                $this->unique === $other->unique);
    }
}