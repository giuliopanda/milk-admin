<?php
namespace MilkCore;
use MilkCore\Field;
use MilkCore\Index;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Database Schema Management Class
 * 
 * This class provides a fluent interface for creating and modifying MySQL or SQLite tables
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
 * @version     1.0.0
 */

class SchemaMysql {
    public string $table;
    public ?string $last_error = null;
    
    private array $fields = [];
    private array $indices = [];
    private string $engine = 'InnoDB';
    private string $charset = 'utf8mb4';
    private string $collate = 'utf8mb4_0900_ai_ci';
    private ?array $primary_keys = [];
    private ?string $primary_key = null;  // manteniamo per retrocompatibilità

    private \MilkCore\MySql | \MilkCore\SQLite $db;

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
     * This method defines a primary key field that automatically increments with each new record.
     * By default, the field is named 'id', but you can specify a different name if needed.
     * 
     * @example
     * ```php
     * // Default primary key
     * $schema->id();
     * 
     * // Custom primary key name
     * $schema->id('user_id');
     * ```
     * 
     * @param string $name The name of the primary key field (default: 'id')
     * @return self Returns the Schema instance for method chaining
     */
    public function id(string $name = 'id'): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'int';
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
     * This method defines an INT field in the database table.
     * 
     * @example
     * ```php
     * // Required integer field
     * $schema->int('age');
     * 
     * // Nullable integer field
     * $schema->int('parent_id', true);
     * 
     * // Integer field with default value
     * $schema->int('status', false, 1);
     * 
     * // Integer field positioned after another field
     * $schema->int('sort_order', false, 0, 'id');
     * ```
     * 
     * @param string $name The name of the field
     * @param bool $null Whether the field can be NULL (default: true)
     * @param int|null $default The default value for the field (default: null)
     * @param string|null $after The field after which this field should be positioned (default: null)
     * @return self Returns the Schema instance for method chaining
     */
    public function int(string $name, bool $null = true, ?int $default = null, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'int';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    /**
     * Creates a tiny integer field
     * 
     * This method defines a TINYINT field in the database table.
     * TINYINT fields can store values from -128 to 127, or 0 to 255 if unsigned.
     * 
     * @example
     * ```php
     * // Boolean-like field (0 or 1)
     * $schema->tinyint('is_active', false, 1);
     * 
     * // Small numeric field
     * $schema->tinyint('priority', false, 5);
     * ```
     * 
     * @param string $name The name of the field
     * @param bool $null Whether the field can be NULL (default: true)
     * @param int|null $default The default value for the field (default: null)
     * @param string|null $after The field after which this field should be positioned (default: null)
     * @return self Returns the Schema instance for method chaining
     */
    public function tinyint(string $name, bool $null = true, ?int $default = null, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'tinyint';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;

        return $this;
    }

    /**
     * Creates a string (VARCHAR) field
     * 
     * This method defines a VARCHAR field in the database table.
     * VARCHAR fields are used for storing variable-length string data.
     * 
     * @example
     * ```php
     * // Standard string field with default length (255)
     * $schema->string('title');
     * 
     * // String field with custom length
     * $schema->string('username', 100);
     * 
     * // Nullable string field
     * $schema->string('middle_name', 50, true);
     * 
     * // String field with default value
     * $schema->string('status', 20, false, 'active');
     * 
     * // String field positioned after another field
     * $schema->string('notes', 200, false, '', 'status');
     * ```
     * 
     * @param string $name The name of the field
     * @param int $length The maximum length of the string (default: 255)
     * @param bool $null Whether the field can be NULL (default: true)
     * @param string|null $default The default value for the field (default: null)
     * @param string|null $after The field after which this field should be positioned (default: null)
     * @return self Returns the Schema instance for method chaining
     */
    public function string(string $name, int $length = 255, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'varchar';
        $field->length = $length;
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    /**
     * Creates a text field
     * 
     * This method defines a TEXT field in the database table.
     * TEXT fields are used for storing large amounts of text data (up to 65,535 characters).
     * 
     * @example
     * ```php
     * // Required text field
     * $schema->text('content');
     * 
     * // Nullable text field
     * $schema->text('description', true);
     * 
     * // Text field positioned after another field
     * $schema->text('notes', false, 'content');
     * ```
     * 
     * @param string $name The name of the field
     * @param bool $null Whether the field can be NULL (default: true)
     * @param string|null $after The field after which this field should be positioned (default: null)
     * @return self Returns the Schema instance for method chaining
     */
    public function text(string $name, bool $null = true, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'text';
        $field->nullable = $null;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function longtext(string $name, bool $null = true, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'longtext';
        $field->nullable = $null;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function datetime(string $name, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'datetime';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function date(string $name, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'date';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function time(string $name, bool $null = true, ?string $default = null, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'time';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function timestamp(string $name, bool $null = true, ?string $default = 'CURRENT_TIMESTAMP', ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'timestamp';
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2, bool $null = true, ?float $default = null, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'decimal';
        $field->precision = $precision;
        $field->scale = $scale;
        $field->nullable = $null;
        $field->default = $default;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function boolean(string $name, bool $null = true, ?bool $default = false, ?string $after = null): self {
        $field = new FieldMysql($name, $this->db);
        $field->type = 'tinyint';
        $field->length = 1;
        $field->nullable = $null;
        $field->default = $default ? 1 : 0;
        $field->after = $after;
        $this->fields[$name] = $field;
        return $this;
    }

    public function index(string $name, array $columns, bool $unique = false): self {
        $this->indices[$name] = new IndexMysql($name, $columns, $unique);
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

    // CREATION/
    public function create(): bool {
        $this->last_error = '';
        if (empty($this->fields)) {
            return false;
        }

        $sql = "CREATE TABLE ". $this->db->qn($this->table) . " (\n";
        // Fields
        $fields_sql = [];
        foreach ($this->fields as $field) {
            $fields_sql[] = "  " . $field->to_sql();
        }

        // Gestione chiavi primarie
        if (!empty($this->primary_keys)) {
            $primary_keys = array_map([$this->db, 'qn'], $this->primary_keys);
            $fields_sql[] = "  PRIMARY KEY (" . implode(", ", $primary_keys) . ")";
        } elseif ($this->primary_key) {
            $fields_sql[] = "  PRIMARY KEY (" . $this->db->qn($this->primary_key) . ")";
        }

        // Indices
        foreach ($this->indices as $index) {
            $fields_sql[] = "  " . $index->to_sql();
        }

        $sql .= implode(",\n", $fields_sql);
        $sql .= "\n) ENGINE={$this->engine} DEFAULT CHARSET={$this->charset} COLLATE={$this->collate};";

        // resetto il prefisso a causa dell'installazione in cui ancora non è settato ??
        //@TODO 2025/06/12 - Secondo me ora funziona anche senza resettare il prefisso
        $this->db->prefix = Config::get('prefix');

        $this->db->query($sql);
        if ($this->db->error) {
            $this->last_error = $this->db->last_error;
        }
        return !$this->db->error; 
    }

    public function drop(): bool {
        return Get::db()->drop_table($this->table);
    }

   /**
     * ALTER TABLE METHODS - Versione migliorata per MySQL
     */
    public function modify($force_update = false): bool {
        // Ottieni la struttura corrente
        $current_fields = $this->get_current_fields();
        $current_indices = $this->get_current_indices();
        // Valida le modifiche prima di procedere
        if (!$this->validate_modifications($current_fields) && !$force_update) {
            return false;
        }

        return $this->alter_table($current_fields, $current_indices);
    }

    /**
     * Complex alter table modificato per gestire meglio il riordinamento
     */
    private function alter_table(array $current_fields, array $current_indices): bool {
        try {
            // Inizia transazione
            $this->db->query("START TRANSACTION");
            
            // 1. Prepara tutti i comandi ALTER
            $alter_commands = $this->prepare_alter_commands($current_fields, $current_indices);
            
            if (empty($alter_commands)) {
                $this->db->query("COMMIT");
                return true; // Nessuna modifica necessaria
            }
            
            // 2. Esegui tutti i comandi ALTER in sequenza
            if (!$this->execute_alter_commands($alter_commands)) {
                throw new \Exception("Errore durante l'esecuzione dei comandi ALTER: ". $this->last_error);
            }
            
            // 3. Opzionale: Seconda passata per riordinamento se necessario
            // (decommentare se si vuole un riordinamento completo dopo l'aggiunta di nuovi campi)
            // if (!$this->reorder_fields_if_needed()) {
            //     throw new \Exception("Errore durante il riordinamento dei campi: ". $this->last_error);
            // }
            
            // 4. Gestisci gli indici
            if (!$this->update_indices($current_indices)) {
                throw new \Exception("Errore durante l'aggiornamento degli indici: ". $this->last_error);
            }
            
            // Commit della transazione
            $this->db->query("COMMIT");
            return true;
            
        } catch (\Exception $e) {
            // Rollback in caso di errore
            $this->db->query("ROLLBACK");
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * FIX PER SCHEMA MYSQL - CORREZIONE DEI PROBLEMI ALTER TABLE
     * 
     * Problemi risolti:
     * 1. Ordine errato dei comandi ALTER (ADD deve venire prima di MODIFY)
     * 2. Generazione di MODIFY non necessari per campi che non cambiano
     * 3. Posizionamento corretto dei nuovi campi con AFTER clause
     * 4. Debug dettagliato per tracciare le operazioni
     * 
     * I metodi corretti da sostituire nella classe SchemaMysql sono:
     * - prepare_alter_commands()
     * - prepare_modify_commands()
     * - execute_alter_commands()
     * - reorder_fields_if_needed()
     * - alter_table()
     */

    /**
     * Prepara tutti i comandi ALTER necessari - VERSIONE CORRETTA CON DEBUG
     */
    private function prepare_alter_commands(array $current_fields, array $current_indices): array {
        $alter_commands = [];
        $debug_info = [];
        
        // DEBUG: Stato iniziale
        $debug_info[] = "=== PREPARE ALTER COMMANDS DEBUG ===";
        $debug_info[] = "Campi correnti: " . implode(', ', array_keys($current_fields));
        $debug_info[] = "Campi definiti: " . implode(', ', array_keys($this->fields));
        
        // 1. Drop indici che verranno ricreati (per evitare conflitti)
        foreach ($current_indices as $name => $index) {
            if (!isset($this->indices[$name])) {
                $alter_commands[] = "DROP INDEX " . $this->db->qn($name);
                $debug_info[] = "DROP INDEX: $name";
            } elseif (isset($this->indices[$name]) && !$index->compare($this->indices[$name])) {
                $alter_commands[] = "DROP INDEX " . $this->db->qn($name);
                $debug_info[] = "DROP INDEX (per modifica): $name";
            }
        }
        
        // 2. Gestione chiave primaria (drop se necessario)
        $current_primary_keys = array_keys(array_filter($current_fields, fn($f) => $f->primary_key));
        $new_primary_keys = !empty($this->primary_keys) ? $this->primary_keys : ($this->primary_key ? [$this->primary_key] : []);
        
        if ($current_primary_keys !== $new_primary_keys) {
            if (!empty($current_primary_keys)) {
                $alter_commands[] = "DROP PRIMARY KEY";
                $debug_info[] = "DROP PRIMARY KEY";
            }
        }
        
        // 3. Drop campi rimossi
        foreach ($current_fields as $name => $field) {
            if (!isset($this->fields[$name]) && !$field->primary_key) {
                $alter_commands[] = "DROP COLUMN " . $this->db->qn($name);
                $debug_info[] = "DROP COLUMN: $name";
            }
        }
        
        // 4. Add nuovi campi con posizionamento corretto
        $current_field_names = array_keys($current_fields);
        $defined_field_order = array_keys($this->fields);
        
        foreach ($this->fields as $name => $field) {
            if (!isset($current_fields[$name])) {
                $debug_info[] = "Nuovo campo da aggiungere: $name";
                
                // Trova dove posizionare il nuovo campo
                $index = array_search($name, $defined_field_order);
                $position_clause = "";
                
                if ($index !== false) {
                    // Cerca il campo precedente che esiste già nella tabella
                    $after_field = null;
                    for ($i = $index - 1; $i >= 0; $i--) {
                        if (isset($current_fields[$defined_field_order[$i]])) {
                            $after_field = $defined_field_order[$i];
                            $debug_info[] = "  - Posizionare dopo: $after_field";
                            break;
                        }
                    }
                    
                    if ($after_field) {
                        $position_clause = " AFTER " . $this->db->qn($after_field);
                    } elseif ($index === 0) {
                        $position_clause = " FIRST";
                        $debug_info[] = "  - Posizionare come FIRST";
                    }
                }
                
                // Salva temporaneamente l'after originale
                $original_after = $field->after;
                $field->after = null; // Rimuovi per evitare doppio AFTER
                
                $sql = "ADD COLUMN " . $field->to_sql() . $position_clause;
                $alter_commands[] = $sql;
                $debug_info[] = "  - SQL: $sql";
                
                // Ripristina l'after originale
                $field->after = $original_after;
            }
        }
        
        // 5. Prepara comandi MODIFY solo per campi che necessitano modifiche
        $modify_commands = $this->prepare_modify_commands($current_fields);
        if (!empty($modify_commands)) {
            $debug_info[] = "MODIFY commands: " . count($modify_commands);
            $alter_commands = array_merge($alter_commands, $modify_commands);
        }
        
        // 6. Ricrea chiave primaria se necessario
        if ($current_primary_keys !== $new_primary_keys && !empty($new_primary_keys)) {
            $alter_commands[] = "ADD PRIMARY KEY (`" . implode("`, `", $new_primary_keys) . "`)";
            $debug_info[] = "ADD PRIMARY KEY: " . implode(', ', $new_primary_keys);
        }
        
        return $alter_commands;
    }

    /**
     * Prepara i comandi MODIFY per gestire modifiche e riordinamento - VERSIONE CORRETTA
     */
    private function prepare_modify_commands(array $current_fields): array {
        $modify_commands = [];
        $debug_info = [];
        
        // Ottieni l'ordine corrente e quello desiderato
        $current_field_order = $this->get_current_field_order();
        $defined_field_order = array_keys($this->fields);
        
        // Determina se è necessario riordinare
        $needs_reordering = $this->needs_field_reordering($current_fields);
        
        $debug_info[] = "=== PREPARE MODIFY COMMANDS DEBUG ===";
        $debug_info[] = "Ordine corrente: " . implode(', ', $current_field_order);
        $debug_info[] = "Ordine desiderato: " . implode(', ', $defined_field_order);
        $debug_info[] = "Riordinamento necessario: " . ($needs_reordering ? 'SI' : 'NO');
        
        // Gestisci modifiche ai campi e riordinamento
        foreach ($this->fields as $field_name => $field) {
            if (!isset($current_fields[$field_name])) {
                continue; // Il campo è nuovo, già aggiunto
            }
            
            $needs_modify = false;
            $position_clause = "";
            
            // Verifica se il campo necessita di modifica del tipo/attributi
            if (!$field->compare($current_fields[$field_name])) {
                $needs_modify = true;
                $debug_info[] = "Campo $field_name: modifica tipo/attributi necessaria";
            }
            
            // Verifica se il campo necessita di riposizionamento SOLO se c'è un riordinamento generale
            if ($needs_reordering) {
                // Trova la posizione corretta per questo campo
                $index = array_search($field_name, $defined_field_order);
                
                if ($index !== false) {
                    // Determina il campo dopo cui posizionare
                    $after_field = null;
                    for ($i = $index - 1; $i >= 0; $i--) {
                        // Cerca il campo precedente che esiste nella tabella corrente
                        if (isset($current_fields[$defined_field_order[$i]])) {
                            $after_field = $defined_field_order[$i];
                            break;
                        }
                    }
                    
                    // Verifica se la posizione attuale è diversa da quella desiderata
                    $current_index = array_search($field_name, $current_field_order);
                    $current_after = $current_index > 0 ? $current_field_order[$current_index - 1] : null;
                    
                    if ($after_field !== $current_after) {
                        $position_clause = $after_field ? " AFTER " . $this->db->qn($after_field) : " FIRST";
                        $needs_modify = true;
                        $debug_info[] = "Campo $field_name: riposizionamento necessario $position_clause";
                    } elseif ($index === 0 && $current_index !== 0) {
                        // Il campo dovrebbe essere primo ma non lo è
                        $position_clause = " FIRST";
                        $needs_modify = true;
                        $debug_info[] = "Campo $field_name: deve essere FIRST";
                    }
                }
            }
            
            // Aggiungi il comando MODIFY solo se necessario
            if ($needs_modify) {
                $sql = "MODIFY COLUMN " . $field->to_sql() . $position_clause;
                $modify_commands[] = $sql;
                $debug_info[] = "  - MODIFY SQL: $sql";
            } else {
                $debug_info[] = "Campo $field_name: nessuna modifica necessaria";
            }
        }

        return $modify_commands;
    }

    /**
     * Esegue i comandi ALTER preparati - CON DEBUG
     */
    private function execute_alter_commands(array $alter_commands): bool {
        if (empty($alter_commands)) {
            return true;
        }
        
        $sql = "ALTER TABLE " . $this->db->qn($this->table) . " \n" . implode(",\n", $alter_commands) . ";";
        
        // Resetta il prefisso per l'installazione
        $this->db->prefix = Config::get('prefix');
        
        $this->db->query($sql);
        if ($this->db->error) {
            if (Config::get('debug')) {
                $this->last_error = "QUERY: " . $sql . "\nERRORE: " . $this->db->last_error;
            } else {
                $this->last_error = $this->db->last_error;
            }
            return false;
        }
        
        return true;
    }

    /**
     * Verifica se è necessario riordinare i campi
     */
    private function needs_field_reordering(array $current_fields): bool {
        $current_order = $this->get_current_field_order();
        $defined_order = array_keys($this->fields);
        
        // Filtra solo i campi che esistono in entrambe le definizioni
        $common_fields_current = array_intersect($current_order, $defined_order);
        $common_fields_defined = array_intersect($defined_order, $current_order);
        
        return $common_fields_current !== $common_fields_defined;
    }

    /**
     * Aggiorna gli indici (per entrambe le modalità)
     */
    private function update_indices(array $current_indices): bool {
        // Aggiungi nuovi indici o ricrea quelli modificati
        foreach ($this->indices as $name => $index) {
            if (!isset($current_indices[$name])) {
                // Nuovo indice
                $sql = "ALTER TABLE " . $this->db->qn($this->table) . " ADD " . $index->to_sql();
                $this->db->query($sql);
                if ($this->db->error) {
                    $this->last_error = $this->db->last_error;
                    return false;
                }
            } elseif (!$index->compare($current_indices[$name])) {
                // Indice modificato (già droppato nei comandi ALTER)
                $sql = "ALTER TABLE " . $this->db->qn($this->table) . " ADD " . $index->to_sql();
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
     * Valida la struttura prima delle modifiche
     */
    private function validate_modifications(array $current_fields): bool {
        // Verifica che i campi obbligatori non vengano rimossi se contengono dati
        foreach ($current_fields as $name => $field) {
            if (!isset($this->fields[$name]) && !$field->nullable && !$field->primary_key) {
                // Verifica se il campo contiene dati non null
                $result = $this->db->query("SELECT COUNT(*) as count FROM " . $this->db->qn($this->table) . " WHERE " . $this->db->qn($name) . " IS NOT NULL LIMIT 1");
                if ($result) {
                    $row = $result->fetch_object();
                    if ($row->count > 0) {
                        $this->last_error = "Impossibile rimuovere il campo '{$name}': contiene dati non null";
                        return false;
                    }
                }
            }
        }
        
        // Verifica compatibilità dei tipi per modifiche
        foreach ($this->fields as $name => $field) {
            if (isset($current_fields[$name])) {
                if (!$this->are_types_compatible($current_fields[$name], $field)) {
                    $this->last_error = "Modifica del campo '{$name}': tipi incompatibili ({$current_fields[$name]->type} -> {$field->type})";
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Verifica se due tipi sono compatibili per una modifica
     */
    private function are_types_compatible(FieldMysql $old_field, FieldMysql $new_field): bool {
        // Mapping di compatibilità dei tipi
        $compatible_types = [
            'tinyint' => ['tinyint', 'smallint', 'int', 'bigint'],
            'smallint' => ['smallint', 'int', 'bigint'],
            'int' => ['int', 'bigint'],
            'bigint' => ['bigint'],
            'varchar' => ['varchar', 'text', 'mediumtext', 'longtext'],
            'text' => ['text', 'mediumtext', 'longtext'],
            'mediumtext' => ['mediumtext', 'longtext'],
            'longtext' => ['longtext'],
            'decimal' => ['decimal', 'double', 'float'],
            'float' => ['float', 'double'],
            'double' => ['double']
        ];
        
        $old_type = strtolower($old_field->type);
        $new_type = strtolower($new_field->type);
        
        // Stesso tipo è sempre compatibile
        if ($old_type === $new_type) {
            return true;
        }
        
        // Verifica compatibilità dal mapping
        if (isset($compatible_types[$old_type])) {
            return in_array($new_type, $compatible_types[$old_type]);
        }
        
        return false;
    }

    public function exists(): bool {
        $result = Get::db()->query("SHOW TABLES LIKE '{$this->table}'");
        return $result->num_rows > 0;
    }

    public function get_fields(): array {
        return $this->fields;
    }

    public function get_last_error() {
        return $this->last_error;   
    }

    private function get_current_fields(): array {
        $fields = [];
        $result = Get::db()->query("SHOW FULL COLUMNS FROM " . $this->db->qn($this->table));
        while ($row = $result->fetch_object()) {
            $field = new FieldMysql($row->Field, $this->db);
            
            // Parse type and length/precision
            if (preg_match('/^(\w+)(?:\((.*?)\))?/', $row->Type, $matches)) {
                $field->type = $matches[1];
                if (isset($matches[2])) {
                    if (strpos($matches[2], ',') !== false) {
                        list($precision, $scale) = explode(',', $matches[2]);
                        $field->precision = (int)$precision;
                        $field->scale = (int)$scale;
                    } else {
                        $field->length = (int)$matches[2];
                    }
                }
            }

            $field->nullable = ($row->Null === 'YES');
            $field->default = $row->Default;
            $field->auto_increment = (strpos($row->Extra, 'auto_increment') !== false);
            $field->primary_key = ($row->Key === 'PRI');

            $fields[$row->Field] = $field;
        }
        return $fields;
    }

    private function get_current_indices(): array {
        $indices = [];
        $result = Get::db()->query("SHOW INDEX FROM " . $this->db->qn($this->table));
        
        while ($row = $result->fetch_object()) {
            if ($row->Key_name === 'PRIMARY') {
                continue;
            }

            if (!isset($indices[$row->Key_name])) {
                $indices[$row->Key_name] = new IndexMysql(
                    $row->Key_name, 
                    [$row->Column_name],
                    ($row->Non_unique == 0)
                );
            } else {
                $indices[$row->Key_name]->columns[] = $row->Column_name;
            }
        }

        return $indices;
    }

    private function get_current_field_order(): array {
        $field_order = [];
        $result = Get::db()->query("SHOW COLUMNS FROM " . $this->db->qn($this->table));
        while ($row = $result->fetch_object()) {
            $field_order[] = $row->Field;
        }
        return $field_order;
    }
}

/**
 * Support class for schema 
 * 
 * @package     MilkCore
 * @ignore
 */

class IndexMysql {
    public string $name;
    public array $columns;
    public bool $unique = false;

    public function __construct(string $name, array $columns, bool $unique = false) {
        $this->name = $name;
        $this->columns = $columns;
        $this->unique = $unique;
    }

    public function to_sql(): string {
        $cols = implode('`,`', $this->columns);
        $type = $this->unique ? "UNIQUE KEY" : "KEY";
        $db = Get::db();
        return "{$type} " . $db->qn($this->name) . " (" . $db->qn($cols) . ")";
    }

    public function compare(IndexMysql $other): bool {
        return ($this->columns === $other->columns &&
                $this->unique === $other->unique);
    }
}



/**
 * Support class for schema 
 * 
 * @package     MilkCore
 * @ignore
 */

 class FieldMysql {
    public string $name;
    public string $type;
    public ?int $length = null;
    public ?int $precision = null;
    public ?int $scale = null;
    public bool $nullable = false;
    public mixed $default = null;
    public bool $auto_increment = false;
    public bool $primary_key = false;
    public ?string $after = null;
    public bool $first = false;
    public $db;

    public function __construct(string $name, $db) {
        $this->name = $name;
        $this->db = $db;
    }

    public function to_sql(): string {
        $sql = $this->db->qn($this->name) . " {$this->type}";

        // Aggiungi length/precision se necessario
        if ($this->length) {
            $sql .= "({$this->length})";
        } elseif ($this->precision !== null) {
            $sql .= "({$this->precision},{$this->scale})";
        }

        // Null/Not Null
        $sql .= $this->nullable ? " DEFAULT NULL" : " NOT NULL";

        // Default value
        if (!$this->nullable && $this->default !== null) {
            if (is_string($this->default) && $this->default !== 'CURRENT_TIMESTAMP') {
                $sql .= " DEFAULT '{$this->default}'";
            } else {
                $sql .= " DEFAULT {$this->default}";
            }
        }

        // Auto Increment
        if ($this->auto_increment) {
            $sql .= " AUTO_INCREMENT";
        }

        // Position (AFTER/FIRST)
        if ($this->after != null && $this->after != '' && $this->after != false && $this->after != '{}') {
            $sql .= " AFTER " . $this->db->qn($this->after);
        } elseif ($this->first) {
            $sql .= " FIRST";
        }

        return $sql;
    }

    public function compare(FieldMysql $other): bool {
        return ($this->type === $other->type &&
                $this->length === $other->length &&
                $this->precision === $other->precision &&
                $this->scale === $other->scale &&
                $this->nullable === $other->nullable &&
                $this->default === $other->default &&
                $this->auto_increment === $other->auto_increment);
    }
}