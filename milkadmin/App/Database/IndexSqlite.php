<?php
namespace App\Database;

use App\Get;
use \App\Database\SQLite;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Support class for schema - SQLite version
 * 
 * @package     App
 * @ignore
 */

 class IndexSqlite {
    public string $name;
    public array $columns;
    public bool $unique = false;
    public SQLite $db;

    public function __construct(SQLite $db, string $name, array $columns, bool $unique = false) {
        $this->name = $name;
        $this->columns = $columns;
        $this->unique = $unique;
        $this->db = $db;
    }

    /**
     * Genera l'SQL per creare l'indice in SQLite
     * 
     * @param string $table_name Nome della tabella su cui creare l'indice
     * @return string SQL per CREATE INDEX
     */
    public function toSqlSqlite(string $table_name): string {
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = $this->db->qn($column);
        }
        $cols = implode(',', $columns);
        $type = $this->unique ? "CREATE UNIQUE INDEX" : "CREATE INDEX";
        return "{$type} IF NOT EXISTS " . $this->db->qn($this->name) . " ON " . $this->db->qn($table_name) . " (". $cols. ")";
    }

    /**
     * Mantiene il metodo originale per compatibilità (usato all'interno di CREATE TABLE)
     * 
     * @return string SQL per definizione indice inline (non supportato in SQLite)
     */
    public function toSql(): string {
        // Questo formato è per MySQL inline, SQLite non lo supporta
        // ma lo manteniamo per compatibilità dell'interfaccia
        $db = Get::db();
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = $db->qn($column);
        }
        $cols = implode('`,`', $columns);
        $type = $this->unique ? "UNIQUE" : "INDEX";
        return "{$type} " . $db->qn($this->name) . " (" . $cols .")";
    }

    /**
     * Confronta due indici per verificare se sono uguali
     */
    public function compare(IndexSqlite $other): bool {
        return ($this->columns === $other->columns &&
                $this->unique === $other->unique);
    }
}