<?php
namespace App\Database;

use App\Get;


/**
 * Support class for schema 
 * 
 * @package     App
 * @ignore
 */

class IndexMysql {
    public string $name;
    public array $columns;
    public bool $unique = false;
    public \App\Database\MySql $db;

    public function __construct(\App\Database\MySql $db, string $name, array $columns, bool $unique = false) {
        $this->name = $name;
        $this->columns = $columns;
        $this->unique = $unique;
        $this->db = $db;
    }

    public function toSql(): string {
       
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = $this->db->qn($column);
        }
        $cols = implode(',', $columns);
        $type = $this->unique ? "UNIQUE KEY" : "KEY";
       
        return "{$type} " . $this->db->qn($this->name) . " (" .  $cols . ")";
    }

    public function compare(IndexMysql $other): bool {
        return ($this->columns === $other->columns &&
                $this->unique === $other->unique);
    }
}