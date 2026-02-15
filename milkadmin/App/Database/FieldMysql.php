<?php
namespace App\Database;

/**
 * Support class for schema 
 * 
 * @package     App
 * @ignore
 */

 class FieldMysql {
    public string $name;
    public string $type;
    public ?int $length = null;
    public ?int $precision = null;
    public ?int $scale = null;
    public bool $unsigned = false;
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

    public function toSql(): string {
        $sql = $this->db->qn($this->name) . " {$this->type}";

        // Aggiungi length/precision se necessario
        if ($this->length) {
            $sql .= "({$this->length})";
        } elseif ($this->precision !== null) {
            $sql .= "({$this->precision},{$this->scale})";
        }

        if ($this->unsigned) {
            $numeric_types = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double', 'real', 'numeric'];
            if (in_array(strtolower($this->type), $numeric_types, true)) {
                $sql .= " UNSIGNED";
            }
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
                $this->unsigned === $other->unsigned &&
                $this->nullable === $other->nullable &&
                $this->default === $other->default &&
                $this->auto_increment === $other->auto_increment);
    }

    public function toArray(): array {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'unsigned' => $this->unsigned,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'auto_increment' => $this->auto_increment,
            'primary_key' => $this->primary_key,
            'after' => $this->after,
            'first' => $this->first,
        ];
    }
}
