<?php
namespace App\Database;

use App\Get;


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
 * @package     App
 * @version     1.0.0 SQLite
 */

class SchemaSqlite {
    public string $table;
    public string $last_error = '';
    
    private array $fields = [];
    private array $indices = [];
    private ?array $primary_keys = [];
    private ?string $primary_key = null;  // manteniamo per retrocompatibilità

    private array $differences = [];
    private \App\Database\SQLite $db;

    public function __construct(string $table, ?\App\Database\SQLite $db = null) {
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
        $this->fields = array_merge([$name => $field], $this->fields);
        return $this;
    }

    public function removePrimaryKeys() {
        foreach ($this->primary_keys as $pk) {
            if (isset($this->fields[$pk])) {
                unset($this->fields[$pk]);
            }
        }
        $this->primary_keys = [];
        $this->primary_key = null;
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
        $field->type = 'DATETIME';  
        $field->nullable = $null;
        $field->default = $default;
        $this->fields[$name] = $field;
        return $this;
    }

    public function date(string $name, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'DATE';  
        $field->nullable = $null;
        $field->default = $default;
        $this->fields[$name] = $field;
        return $this;
    }

    public function time(string $name, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'TIME';
        $field->nullable = $null;
        $field->default = $default;
        $this->fields[$name] = $field;
        return $this;
    }

    public function timestamp(string $name, bool $null = true, ?string $default = 'CURRENT_TIMESTAMP', ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'TIMESTAMP';
        $field->nullable = $null;
        $field->default = $default;
        $this->fields[$name] = $field;
        return $this;
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2, bool $null = true, ?float $default = null, ?string $after = null): self {
        $field = new FieldSqlite($name);
        $field->type = 'REAL';  
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
        $this->indices[$name] = new IndexSqlite($this->db, $name, $columns, $unique);
        return $this;
    }

    public function setPrimaryKey(array $fields): self {
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
        $insertFields = [];
        $fields_sql = [];
        foreach ($this->fields as $field) {
            $insertFields[] = $field->toArray();
            $fields_sql[] = "  " . $field->toSqlSqlite();
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
            $index_sql = $index->toSqlSqlite($this->table);
            $this->db->query($index_sql);
            if ($this->db->error) {
                $this->last_error = $this->db->last_error;
                return false;
            }
        }

        $this->differences = ['action' => 'create', 'table' => $this->table, 'fields' => $insertFields];

        return true;
    }

    public function drop(): bool {
        $sql = "DROP TABLE IF EXISTS " . $this->db->qn($this->table);
        $this->db->query($sql);
        $this->differences = ['action' => 'drop', 'table' => $this->table];
        return !$this->db->error;
    }

    /**
     * ALTER TABLE METHODS - Versione migliorata per SQLite
     */
    public function modify($force_update = false): bool {
        // Ottieni la struttura corrente
        $current_fields = $this->getCurrentFields();
        $current_indices = $this->getCurrentIndices();
        $diff = $this->checkDifferencesBetweenFields($current_fields);
        if (empty($diff) && !$force_update) {
            // Nessuna modifica necessaria
            return true;
        }
        $this->differences = ['action' => 'modify', 'fields' => $diff, 'table' => $this->table];
        return $this->reconstructTable($current_fields, $current_indices);
      
    }

    /**
     * Ricostruisce completamente la tabella per modifiche complesse
     */
    private function reconstructTable(array $current_fields, array $current_indices): bool {
        $temp_table = $this->table . '_temp_' . uniqid();
        if (empty($this->fields)) {
            return true;
        }
            
            
        $this->last_error = "";
        try {
            // 1. Crea tabella temporanea con la nuova struttura
            if (!$this->createTempTable($temp_table)) {
                throw new \Exception("Impossibile creare tabella temporanea: ".$temp_table. " - " . $this->last_error);
            }

            // 2. Copia i dati dalla tabella originale alla temporanea
            if (!$this->copyDataToTemp($temp_table, $current_fields)) {
                throw new \Exception("Errore durante la copia dei dati: " . $this->last_error);
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
                $sql = $index->toSqlSqlite($this->table);
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
    private function createTempTable(string $temp_table): bool {
        if (empty($this->fields)) {
            $this->last_error = "The table has no fields";
            return false;
        }

        $sql = "CREATE TABLE " . $this->db->qn($temp_table) . " (\n";
        
        // Fields
        $fields_sql = [];
        foreach ($this->fields as $field) {
            $fields_sql[] = "  " . $field->toSqlSqlite();
        }

        // Gestione chiavi primarie composite
        if (!empty($this->primary_keys) && count($this->primary_keys) > 1) {
            $fields_sql[] = "  PRIMARY KEY (`" . implode("`, `", $this->primary_keys) . "`)";
        }

        $sql .= implode(",\n", $fields_sql);
        $sql .= "\n);";

        $this->db->query($sql);
        if ($this->db->error) {
            $this->last_error = $this->db->last_error;
            return false;
        }
        return true;
    }

    /**
     * Copia i dati dalla tabella originale alla temporanea
     * Gestisce tutti i casi critici di migrazione dati
     */
    private function copyDataToTemp(string $temp_table, array $current_fields): bool {
        // 1. Analizza le differenze tra struttura vecchia e nuova
        $migration_plan = $this->analyzeFieldChanges($current_fields);
        
        // 2. Se non ci sono campi da copiare, la tabella sarà vuota ma valida
        if (empty($migration_plan['copy_fields']) && empty($migration_plan['new_fields'])) {
            return true;
        }
        
        // 3. Verifica se ci sono dati da migrare
        if (!$this->hasData()) {
            return true; // Tabella vuota, nessuna migrazione necessaria
        }
        
        // 4. Costruisci la query di inserimento
        $insert_query = $this->buildMigrationQuery($temp_table, $migration_plan, $current_fields);
        
        // 5. Esegui la migrazione
        $this->db->query($insert_query);
        
        if ($this->db->error) {
            $this->last_error = "Errore durante la migrazione dati: " . $this->db->last_error;
            return false;
        }
        
        return true;
    }

    /**
     * Analyzes differences between old and new structure
     * @param array $current_fields The current fields in the database
     * 
     * @return array Returns an array of differences
     */
    private function checkDifferencesBetweenFields(array $current_fields): array {
        $results = [];
        
        // Find fields that exist in both structures
        foreach ($current_fields as $name => $old_field) {
            if (isset($this->fields[$name])) {
                $new_field = $this->fields[$name];
                $add = [];
                
                // Verifica se il campo è stato modificato
                if (!$new_field->compare($old_field)) {
                    foreach ($new_field as $key=>$value) {
                        if ($old_field->$key != $value) {
                            $add['old'][$key] = $old_field->$key;
                            $add['new'][$key] = $value;
                        }
                        $add['action'] = 'update';
                }
                }
            
            } else {
                $add['action'] = $old_field;
            }
            if ($add != []) {
                $results[$name] = $add;
            }
        }
        
        // Trova campi nuovi
        foreach ($this->fields as $name => $new_field) {
            if (!isset($current_fields[$name])) {
                $results['new_fields'][$name] = $new_field;
            }
        }
       
        return $results;
    }

    /**
     * Returns the differences analyzed during the last operation
     */
    public function getFieldDifferences(): array {
        return $this->differences;
    }

    /**
     * Analizza le differenze tra struttura vecchia e nuova
     */
    private function analyzeFieldChanges(array $current_fields): array {
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
    private function buildMigrationQuery(string $temp_table, array $migration_plan, array $current_fields): string {
        $select_parts = [];
        $all_new_fields = array_keys($this->fields);
        
        // 1. Gestisci campi da copiare (esistenti in entrambe le tabelle)
        foreach ($migration_plan['copy_fields'] as $name => $field_info) {
            $old_field = $field_info['old'];
            $new_field = $field_info['new'];
            
            // Gestisci conversioni di tipo se necessario
            $select_parts[$name] = $this->buildFieldConversion($name, $old_field, $new_field);
        }
        
        // 2. Gestisci campi nuovi (che non esistevano nella vecchia struttura)
        foreach ($migration_plan['new_fields'] as $name => $new_field) {
            $select_parts[$name] = $this->buildDefaultValue($new_field);
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
    private function buildFieldConversion(string $name, FieldSqlite $old_field, FieldSqlite $new_field): string {
        $quoted_name = $this->db->qn($name);
        
        // Se i tipi sono compatibili, copia direttamente
        if ($old_field->normalizeType($old_field->type) === $new_field->normalizeType($new_field->type)) {
            return $quoted_name;
        }
        
        // Gestisci conversioni specifiche
        $old_type = $old_field->normalizeType($old_field->type);
        $new_type = $new_field->normalizeType($new_field->type);
        
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
    private function buildDefaultValue(FieldSqlite $field): string {
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
        switch ($field->normalizeType($field->type)) {
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
     * Verifica se la tabella ha dati
     */
    private function hasData(): bool {
        $result = $this->db->query("SELECT COUNT(*) as count FROM " . $this->db->qn($this->table));
        if ($result) {
            $row = $result->fetch_array();
            return ($row['count'] ?? 0) > 0;
        }
        return false;
    }

    public function exists(): bool {
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='{$this->table}'";
        $result = $this->db->query($sql);

        return $result && $result->fetch_array() !== false;
    }

    public function getFields(): array {
        return $this->fields;
    }

    public function getLastError() {
        return $this->last_error;   
    }

   private function getCurrentFields(): array {
        $fields = [];
        $sql = "PRAGMA table_info(" . $this->db->qn($this->table) . ")";
        $result = $this->db->query($sql);

        // Raccogliamo prima tutte le righe per analizzare la chiave primaria
        $all_rows = [];
        if ($result) {
            // Ripristiniamo il puntatore nel caso in cui fetch_array() lo consumi
            $result_data = [];
            while($row_data = $result->fetch_array()) {
                $result_data[] = $row_data;
            }
            $all_rows = $result_data;
        }
        
        // Contiamo i campi che compongono la chiave primaria
        $pk_field_count = 0;
        foreach ($all_rows as $row) {
            if (isset($row['pk']) && $row['pk'] > 0) {
                $pk_field_count++;
            }
        }

        foreach ($all_rows as $row) {
            $field = new FieldSqlite($row['name']);
            $field->type = strtoupper($row['type']);
            $field->primary_key = (isset($row['pk']) && $row['pk'] > 0);

            // --- INIZIO CORREZIONE ---

            // 1. Gestione Nullability
            // Un campo PRIMARY KEY non è MAI nullable, anche se PRAGMA dice notnull=0
            if ($field->primary_key) {
                $field->nullable = false;
            } else {
                $field->nullable = (isset($row['notnull']) && $row['notnull'] == 0);
            }
            
            // 2. Gestione Auto Increment
            // In SQLite, è auto_increment se è INTEGER, PK e la PK è composta da un solo campo.
            if ($field->primary_key && $field->type === 'INTEGER' && $pk_field_count === 1) {
                $field->auto_increment = true;
            }
            
            // 3. Gestione Valore di Default
            // PRAGMA restituisce i default stringa/data con apici. Li rimuoviamo per un confronto corretto.
            if (is_string($row['dflt_value'])) {
                $field->default = trim($row['dflt_value'], "'\"");
            } else {
                $field->default = $row['dflt_value'];
            }
            
            // --- FINE CORREZIONE ---
            
            $fields[$row['name']] = $field;
        }
        
        return $fields;
    }

    private function getCurrentIndices(): array {
        $indices = [];
        $result = $this->db->query("PRAGMA index_list(" . $this->db->qn($this->table) . ")");
        
        while ($row = $result->fetch_array()) {
            $index_name = $row['name'];
            
            // Salta gli indici automatici di SQLite
            if (strpos($index_name, 'sqlite_autoindex_') === 0) {
                continue;
            }
            
            // Ottieni info sull'indice
            $info_result = $this->db->query("PRAGMA index_info(" . $this->db->qn($index_name) . ")");
            $columns = [];
            
            while ($info = $info_result->fetch_array()) {
                $columns[] = $info['name'];
            }
            
            $indices[$index_name] = new IndexSqlite(
                $this->db,
                $index_name,
                $columns,
                ($row['unique'] == 1)
            );
        }
        
        return $indices;
    }
}
