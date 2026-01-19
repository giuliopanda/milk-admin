<?php
/**
 * ArrayEngine - Database in memoria basato su array
 *
 * Classe originale scritta per eseguire query SQL su array PHP in memoria.
 * Utilizza il parser SQL estratto da vimeo/php-mysql-engine per il parsing,
 * ma l'esecuzione è completamente riscritta.
 *
 * @author Custom Implementation
 * @license MIT
 */

namespace App\ArrayQuery;

use App\ArrayQuery\Parser\SQLParser;
use App\ArrayQuery\Query\SelectQuery;
use App\ArrayQuery\Query\InsertQuery;
use App\ArrayQuery\Query\UpdateQuery;
use App\ArrayQuery\Query\DeleteQuery;

class ArrayEngine
{
    /**
     * Storage delle tabelle: ['table_name' => [row1, row2, ...]]
     * @var array<string, array<int, array>>
     */
    private $tables = [];

    /**
     * Contatori auto-increment per tabella
     * @var array<string, int>
     */
    private $autoIncrementCounters = [];

    /**
     * Colonne con auto-increment per tabella
     * @var array<string, string>
     */
    private $autoIncrementColumns = [];

    /**
     * Executor per le query
     * @var QueryExecutor
     */
    private $executor;

    public function __construct()
    {
        $this->executor = new QueryExecutor($this);
    }

    public function setWherePushdownEnabled(bool $enabled): void
    {
        $this->executor->setWherePushdownEnabled($enabled);
    }

    /**
     * Aggiunge una tabella al database
     *
     * @param string $tableName Nome della tabella
     * @param array $data Array di righe (ogni riga è un array associativo)
     * @param string|null $autoIncrementColumn Colonna auto-increment (opzionale)
     * @return self
     *
     * @example
     * $db->addTable('users', [
     *     ['id' => 1, 'name' => 'Mario', 'age' => 30],
     *     ['id' => 2, 'name' => 'Luigi', 'age' => 25]
     * ], 'id');
     */
    public function addTable(string $tableName, array $data = [], ?string $autoIncrementColumn = null): self
    {
        $this->tables[$tableName] = $data;

        if ($autoIncrementColumn !== null) {
            $this->autoIncrementColumns[$tableName] = $autoIncrementColumn;

            // Trova il valore massimo esistente
            $maxId = 0;
            foreach ($data as $row) {
                if (isset($row[$autoIncrementColumn]) && $row[$autoIncrementColumn] > $maxId) {
                    $maxId = (int)$row[$autoIncrementColumn];
                }
            }
            $this->autoIncrementCounters[$tableName] = $maxId;
        }

        return $this;
    }

    /**
     * Esegue una query SQL su array in memoria
     *
     * @param string $sql Query SQL da eseguire
     * @return array Risultati della query
     * @throws \Exception Se la query non è valida
     *
     * @example
     * $results = $db->query('SELECT name, age FROM users WHERE age > 25');
     */
    public function query(string $sql, ?array $params = null): array
    {
        $this->executor->setParameters($params ?? []);

        // Parse la query
        $parsedQuery = SQLParser::parse($sql);

        // Esegui in base al tipo
        if ($parsedQuery instanceof SelectQuery) {
            return $this->executor->executeSelect($parsedQuery);
        } elseif ($parsedQuery instanceof InsertQuery) {
            return $this->executor->executeInsert($parsedQuery);
        } elseif ($parsedQuery instanceof UpdateQuery) {
            return $this->executor->executeUpdate($parsedQuery);
        } elseif ($parsedQuery instanceof DeleteQuery) {
            return $this->executor->executeDelete($parsedQuery);
        }

        throw new \Exception('Query type not supported');
    }

    /**
     * Ottiene i dati di una tabella
     *
     * @param string $tableName Nome della tabella
     * @return array
     */
    public function getTable(string $tableName): array
    {
        if (!isset($this->tables[$tableName])) {
            throw new \Exception("Table '$tableName' does not exist");
        }
        return $this->tables[$tableName];
    }

    /**
     * Imposta i dati di una tabella
     *
     * @param string $tableName Nome della tabella
     * @param array $data Nuovi dati
     * @return void
     */
    public function setTable(string $tableName, array $data): void
    {
        $this->tables[$tableName] = $data;
    }

    /**
     * Remove a table from the database.
     *
     * @param string $tableName
     * @return void
     */
    public function removeTable(string $tableName): void
    {
        unset($this->tables[$tableName], $this->autoIncrementCounters[$tableName], $this->autoIncrementColumns[$tableName]);
    }

    /**
     * Verifica se una tabella esiste
     *
     * @param string $tableName Nome della tabella
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        return isset($this->tables[$tableName]);
    }

    /**
     * Ottiene tutte le tabelle
     *
     * @return array<string, array>
     */
    public function getAllTables(): array
    {
        return $this->tables;
    }

    /**
     * Genera il prossimo valore auto-increment per una tabella
     *
     * @param string $tableName Nome della tabella
     * @return int|null
     */
    public function getNextAutoIncrement(string $tableName): ?int
    {
        if (!isset($this->autoIncrementColumns[$tableName])) {
            return null;
        }

        $this->autoIncrementCounters[$tableName]++;
        return $this->autoIncrementCounters[$tableName];
    }

    /**
     * Ottiene la colonna auto-increment di una tabella
     *
     * @param string $tableName Nome della tabella
     * @return string|null
     */
    public function getAutoIncrementColumn(string $tableName): ?string
    {
        return $this->autoIncrementColumns[$tableName] ?? null;
    }

    /**
     * Resetta il database (rimuove tutte le tabelle)
     *
     * @return void
     */
    public function reset(): void
    {
        $this->tables = [];
        $this->autoIncrementCounters = [];
        $this->autoIncrementColumns = [];
    }

    /**
     * Ottiene il numero di righe in una tabella
     *
     * @param string $tableName Nome della tabella
     * @return int
     */
    public function count(string $tableName): int
    {
        return count($this->getTable($tableName));
    }

    /**
     * Dump del database (utile per debug)
     *
     * @return array
     */
    public function dump(): array
    {
        return [
            'tables' => $this->tables,
            'auto_increment_counters' => $this->autoIncrementCounters,
            'auto_increment_columns' => $this->autoIncrementColumns,
        ];
    }
}
