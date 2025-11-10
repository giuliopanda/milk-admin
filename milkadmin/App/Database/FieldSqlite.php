<?php
namespace App\Database;

use App\Get;


/**
 * Support class for schema - SQLite version
 * 
 * @package     App
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
    public function toSqlSqlite(): string {
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
    public function toSql(): string {
        return $this->toSqlSqlite();
    }

    /**
     * Confronta due campi per verificare se sono uguali
     */
    public function compare(FieldSqlite $other): bool {
        // Per SQLite, ignoriamo length, precision e scale nel confronto
        // perché SQLite è type-affinity based
        return ($this->normalizeType($this->type) === $this->normalizeType($other->type) &&
                $this->nullable === $other->nullable &&
                $this->default === $other->default &&
                $this->auto_increment === $other->auto_increment &&
                $this->primary_key === $other->primary_key);
    }

    /**
     * Normalizza i tipi per il confronto in SQLite
     */
    public function normalizeType(string $type): string {
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

    public function toArray() {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'auto_increment' => $this->auto_increment,
            'primary_key' => $this->primary_key,
            'after' => $this->after,
            'first' => $this->first
        ];
    }
}
