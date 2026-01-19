<?php
/**
 * QueryExecutor - Esecutore di query SQL su array
 *
 * Implementazione originale per eseguire query SQL parsate su dati in memoria.
 *
 * @author Custom Implementation
 * @license MIT
 */

namespace App\ArrayQuery;

use App\ArrayQuery\Query\SelectQuery;
use App\ArrayQuery\Query\InsertQuery;
use App\ArrayQuery\Query\UpdateQuery;
use App\ArrayQuery\Query\DeleteQuery;
use App\ArrayQuery\Query\Expression\BinaryOperatorExpression;
use App\ArrayQuery\Query\Expression\ColumnExpression;
use App\ArrayQuery\Query\Expression\UnaryExpression;
use App\ArrayQuery\Query\Expression\FunctionExpression;
use App\ArrayQuery\Query\Expression\InOperatorExpression;
use App\ArrayQuery\Query\Expression\BetweenOperatorExpression;
use App\ArrayQuery\Query\Expression\ConstantExpression;
use App\ArrayQuery\Query\Expression\QuestionMarkPlaceholderExpression;
use App\ArrayQuery\Query\Expression\NamedPlaceholderExpression;

class QueryExecutor
{
    /**
     * @var ArrayEngine
     */
    private $database;

    /**
     * @var ExpressionEvaluator
     */
    private $evaluator;

    /**
     * @var bool
     */
    private bool $wherePushdownEnabled = true;

    public function __construct(ArrayEngine $database)
    {
        $this->database = $database;
        $this->evaluator = new ExpressionEvaluator();
    }

    public function setWherePushdownEnabled(bool $enabled): void
    {
        $this->wherePushdownEnabled = $enabled;
    }

    /**
     * Set query parameters for placeholder evaluation.
     *
     * @param array $params
     * @return void
     */
    public function setParameters(array $params): void
    {
        $this->evaluator->setParameters($params);
    }

    /**
     * Esegue una query SELECT
     *
     * @param SelectQuery $query
     * @return array
     */
    public function executeSelect(SelectQuery $query): array
    {
        // 1. FROM: ottieni le righe dalla tabella principale
        $fromClause = $query->fromClause;
        if (!$fromClause || empty($fromClause->tables)) {
            throw new \Exception('SELECT requires FROM clause');
        }

        // La prima tabella è quella principale
        $mainTable = $fromClause->tables[0];
        $tableName = $mainTable['name'];
        $rows = $this->database->getTable($tableName);

        // 2. JOIN: se ci sono più tabelle, esegui i join
        if (count($fromClause->tables) > 1) {
            $joins = array_slice($fromClause->tables, 1);
            $rows = $this->executeJoins($rows, $joins, $tableName, $query->whereClause);
        }

        // 3. WHERE: filtra le righe
        if ($query->whereClause) {
            $rows = $this->filterRows($rows, $query->whereClause);
        }

        // 4. GROUP BY: raggruppa le righe
        if (!empty($query->groupBy)) {
            $rows = $this->groupRows($rows, $query->groupBy, $query->selectExpressions);
        }
        // Se ci sono aggregazioni ma nessun GROUP BY, tratta tutte le righe come un unico gruppo
        elseif ($this->hasAggregateFunction($query->selectExpressions)) {
            $rows = $this->groupRows($rows, [], $query->selectExpressions);
        }

        // 5. HAVING: filtra i gruppi
        if ($query->havingClause) {
            $rows = $this->filterRows($rows, $query->havingClause);
        }

        // 6. ORDER BY: ordina le righe
        if (!empty($query->orderBy)) {
            $rows = $this->sortRows($rows, $query->orderBy);
        }

        // 7. SELECT: proietta le colonne richieste
        $rows = $this->projectColumns($rows, $query->selectExpressions);

        // 8. LIMIT: limita i risultati
        if ($query->limitClause) {
            $offset = $query->limitClause->offset ? $this->evaluateValue($query->limitClause->offset) : 0;
            $rowCount = $this->evaluateValue($query->limitClause->rowcount);
            $rows = array_slice($rows, $offset, $rowCount);
        }

        return array_values($rows);
    }

    /**
     * Esegue una query INSERT
     *
     * @param InsertQuery $query
     * @return array Info sull'inserimento
     */
    public function executeInsert(InsertQuery $query): array
    {
        $tableName = $query->table;
        $table = $this->database->getTable($tableName);
        $columns = $query->insertColumns ?? [];
        $values = $query->values ?? [];

        $insertedRows = 0;
        $autoIncrementCol = $this->database->getAutoIncrementColumn($tableName);

        foreach ($values as $valueSet) {
            $row = [];

            // Costruisci la riga
            foreach ($columns as $index => $column) {
                $value = $valueSet[$index] ?? null;
                $row[$column] = $this->evaluateValue($value);
            }

            // Auto-increment
            if ($autoIncrementCol && !isset($row[$autoIncrementCol])) {
                $row[$autoIncrementCol] = $this->database->getNextAutoIncrement($tableName);
            }

            $table[] = $row;
            $insertedRows++;
        }

        $this->database->setTable($tableName, $table);

        return [
            'inserted' => $insertedRows,
            'table' => $tableName
        ];
    }

    /**
     * Esegue una query UPDATE
     *
     * @param UpdateQuery $query
     * @return array Info sull'aggiornamento
     */
    public function executeUpdate(UpdateQuery $query): array
    {
        $tableName = $query->tableName;
        $table = $this->database->getTable($tableName);
        $updatedRows = 0;

        foreach ($table as $index => $row) {
            // Verifica WHERE
            if ($query->whereClause) {
                if (!$this->evaluator->evaluate($query->whereClause, $row)) {
                    continue;
                }
            }

            // Applica le modifiche
            // setClause è un array di BinaryOperatorExpression con operator '='
            foreach ($query->setClause as $assignment) {
                $column = $assignment->left->columnName;
                $value = $this->evaluator->evaluate($assignment->right, $row);
                $row[$column] = $value;
            }

            $table[$index] = $row;
            $updatedRows++;
        }

        $this->database->setTable($tableName, $table);

        return [
            'updated' => $updatedRows,
            'table' => $tableName
        ];
    }

    /**
     * Esegue una query DELETE
     *
     * @param DeleteQuery $query
     * @return array Info sulla cancellazione
     */
    public function executeDelete(DeleteQuery $query): array
    {
        $tableName = $query->fromClause['name'];
        $table = $this->database->getTable($tableName);
        $deletedRows = 0;
        $newTable = [];

        foreach ($table as $row) {
            // Verifica WHERE
            $shouldDelete = false;
            if ($query->whereClause) {
                if ($this->evaluator->evaluate($query->whereClause, $row)) {
                    $shouldDelete = true;
                }
            } else {
                // DELETE senza WHERE cancella tutto
                $shouldDelete = true;
            }

            if ($shouldDelete) {
                $deletedRows++;
            } else {
                $newTable[] = $row;
            }
        }

        $this->database->setTable($tableName, $newTable);

        return [
            'deleted' => $deletedRows,
            'table' => $tableName
        ];
    }

    /**
     * Esegue i JOIN
     */
    private function executeJoins(array $leftRows, array $joins, string $leftTableName, $whereClause = null): array
    {
        $result = $leftRows;

        foreach ($joins as $join) {
            $rightTableName = $join['name'];
            $rightTableAlias = $join['alias'] ?? null;
            $rightRows = $this->database->getTable($rightTableName);
            $joinType = $join['join_type'] ?? 'INNER';
            $joinCondition = $join['join_expression'] ?? null;
            $newResult = [];

            $rightTables = [$rightTableName];
            if ($rightTableAlias && $rightTableAlias !== $rightTableName) {
                $rightTables[] = $rightTableAlias;
            }

            if ($this->wherePushdownEnabled && $whereClause && $joinType !== 'LEFT' && $joinType !== 'RIGHT') {
                $rightPredicates = $this->extractRightPredicates($whereClause, $rightTables);
                if (!empty($rightPredicates)) {
                    $rightRows = $this->filterRowsByPredicates($rightRows, $rightPredicates);
                }
            }

            $joinPairs = null;
            if ($joinCondition) {
                $joinPairs = $this->extractEquiJoinPairs($joinCondition, $rightTables);
            }

            if ($joinCondition && $joinPairs !== null && count($joinPairs) > 0 && $joinType !== 'CROSS') {
                $newResult = $this->executeHashJoin(
                    $result,
                    $rightRows,
                    $joinPairs,
                    $joinCondition,
                    $leftTableName,
                    $rightTableName,
                    $rightTableAlias,
                    $joinType
                );
            } else {
                foreach ($result as $leftRow) {
                    $matched = false;

                    foreach ($rightRows as $rightRow) {
                        $combinedRow = $this->combineRows($leftRow, $rightRow, $leftTableName, $rightTableName, $rightTableAlias);

                        if ($joinCondition) {
                            if ($this->evaluator->evaluate($joinCondition, $combinedRow)) {
                                $newResult[] = $combinedRow;
                                $matched = true;
                            }
                        } else {
                            // CROSS JOIN
                            $newResult[] = $combinedRow;
                            $matched = true;
                        }
                    }

                    // LEFT JOIN: mantieni la riga sinistra anche se non ha match
                    if (!$matched && $joinType === 'LEFT') {
                        $newResult[] = $leftRow;
                    }
                }
            }

            $result = $newResult;
            $leftTableName = $rightTableName; // Per join multipli successivi
        }

        return $result;
    }

    private function combineRows(
        array $leftRow,
        array $rightRow,
        string $leftTableName,
        string $rightTableName,
        ?string $rightTableAlias
    ): array {
        $combinedRow = [];

        // Aggiungi colonne della tabella sinistra
        foreach ($leftRow as $col => $val) {
            $combinedRow[$col] = $val;
            // Se non è già qualificato, aggiungi qualificazione
            if (strpos($col, '.') === false) {
                $combinedRow[$leftTableName . '.' . $col] = $val;
            }
        }

        // Aggiungi colonne della tabella destra
        foreach ($rightRow as $col => $val) {
            // Non sovrascrivere se esiste già (per evitare conflitti)
            if (!isset($combinedRow[$col])) {
                $combinedRow[$col] = $val;
            }
            // Aggiungi sempre con nome qualificato
            $combinedRow[$rightTableName . '.' . $col] = $val;
            if ($rightTableAlias && $rightTableAlias !== $rightTableName) {
                $combinedRow[$rightTableAlias . '.' . $col] = $val;
            }
        }

        return $combinedRow;
    }

    private function executeHashJoin(
        array $leftRows,
        array $rightRows,
        array $joinPairs,
        $joinCondition,
        string $leftTableName,
        string $rightTableName,
        ?string $rightTableAlias,
        string $joinType
    ): array {
        $index = [];

        foreach ($rightRows as $rightRow) {
            $key = $this->buildJoinKey($rightRow, $joinPairs, 'right');
            if (!array_key_exists($key, $index)) {
                $index[$key] = [];
            }
            $index[$key][] = $rightRow;
        }

        $result = [];
        foreach ($leftRows as $leftRow) {
            $matched = false;
            $key = $this->buildJoinKey($leftRow, $joinPairs, 'left');

            if (array_key_exists($key, $index)) {
                foreach ($index[$key] as $rightRow) {
                    $combinedRow = $this->combineRows($leftRow, $rightRow, $leftTableName, $rightTableName, $rightTableAlias);
                    if ($this->evaluator->evaluate($joinCondition, $combinedRow)) {
                        $result[] = $combinedRow;
                        $matched = true;
                    }
                }
            }

            if (!$matched && $joinType === 'LEFT') {
                $result[] = $leftRow;
            }
        }

        return $result;
    }

    private function buildJoinKey(array $row, array $joinPairs, string $side): string
    {
        $values = [];

        foreach ($joinPairs as $pair) {
            $expr = $side === 'right' ? $pair['right'] : $pair['left'];
            $values[] = $this->evaluator->evaluate($expr, $row);
        }

        $key = json_encode($values);
        return $key === false ? '' : $key;
    }

    private function extractEquiJoinPairs($expression, array $rightTables): ?array
    {
        if (!$expression instanceof BinaryOperatorExpression) {
            return [];
        }
        if ($expression->negated) {
            return null;
        }

        $operator = strtoupper($expression->operator);
        if ($operator === 'OR') {
            return null;
        }
        if ($operator === 'AND') {
            $leftPairs = $this->extractEquiJoinPairs($expression->left, $rightTables);
            if ($leftPairs === null) {
                return null;
            }
            $rightPairs = $this->extractEquiJoinPairs($expression->right, $rightTables);
            if ($rightPairs === null) {
                return null;
            }
            return array_merge($leftPairs, $rightPairs);
        }
        if ($operator !== '=') {
            return [];
        }

        if (!$expression->left instanceof ColumnExpression || !$expression->right instanceof ColumnExpression) {
            return [];
        }

        $leftTable = $expression->left->tableName ?? null;
        $rightTable = $expression->right->tableName ?? null;

        if ($leftTable === null || $rightTable === null) {
            return [];
        }

        $leftIsRight = in_array($leftTable, $rightTables, true);
        $rightIsRight = in_array($rightTable, $rightTables, true);

        if ($leftIsRight && !$rightIsRight) {
            return [['left' => $expression->right, 'right' => $expression->left]];
        }
        if ($rightIsRight && !$leftIsRight) {
            return [['left' => $expression->left, 'right' => $expression->right]];
        }

        return [];
    }

    private function extractRightPredicates($whereClause, array $rightTables): array
    {
        if ($whereClause === null) {
            return [];
        }

        $predicates = $this->extractConjunctivePredicates($whereClause);
        $rightOnly = [];

        foreach ($predicates as $predicate) {
            if ($this->isRightOnlyPredicate($predicate, $rightTables)) {
                $rightOnly[] = $predicate;
            }
        }

        return $rightOnly;
    }

    private function extractConjunctivePredicates($expression): array
    {
        if (!$expression instanceof BinaryOperatorExpression) {
            return $expression ? [$expression] : [];
        }

        $operator = strtoupper($expression->operator);
        if ($operator === 'AND' && !$expression->negated) {
            return array_merge(
                $this->extractConjunctivePredicates($expression->left),
                $this->extractConjunctivePredicates($expression->right)
            );
        }

        return [$expression];
    }

    private function isRightOnlyPredicate($expression, array $rightTables): bool
    {
        $tables = [];
        $hasUnqualified = false;
        $hasUnsupported = false;

        $this->collectExpressionTables($expression, $tables, $hasUnqualified, $hasUnsupported);

        if ($hasUnsupported || $hasUnqualified || empty($tables)) {
            return false;
        }

        foreach (array_keys($tables) as $table) {
            if (!in_array($table, $rightTables, true)) {
                return false;
            }
        }

        return true;
    }

    private function collectExpressionTables($expression, array &$tables, bool &$hasUnqualified, bool &$hasUnsupported): void
    {
        if ($expression === null || is_scalar($expression)) {
            return;
        }

        if ($expression instanceof ColumnExpression) {
            $table = $expression->tableName ?? null;
            if ($table) {
                $tables[$table] = true;
            } else {
                $hasUnqualified = true;
            }
            return;
        }

        if ($expression instanceof BinaryOperatorExpression) {
            $this->collectExpressionTables($expression->left, $tables, $hasUnqualified, $hasUnsupported);
            if ($expression->right !== null) {
                $this->collectExpressionTables($expression->right, $tables, $hasUnqualified, $hasUnsupported);
            }
            return;
        }

        if ($expression instanceof UnaryExpression) {
            $this->collectExpressionTables($expression->subject, $tables, $hasUnqualified, $hasUnsupported);
            return;
        }

        if ($expression instanceof FunctionExpression) {
            $args = $expression->args ?? [];
            foreach ($args as $arg) {
                $this->collectExpressionTables($arg, $tables, $hasUnqualified, $hasUnsupported);
            }
            return;
        }

        if ($expression instanceof InOperatorExpression) {
            $this->collectExpressionTables($expression->left, $tables, $hasUnqualified, $hasUnsupported);
            foreach ($expression->inList as $item) {
                $this->collectExpressionTables($item, $tables, $hasUnqualified, $hasUnsupported);
            }
            return;
        }

        if ($expression instanceof BetweenOperatorExpression) {
            $this->collectExpressionTables($expression->left, $tables, $hasUnqualified, $hasUnsupported);
            $this->collectExpressionTables($expression->beginning, $tables, $hasUnqualified, $hasUnsupported);
            $this->collectExpressionTables($expression->end, $tables, $hasUnqualified, $hasUnsupported);
            return;
        }

        if ($expression instanceof ConstantExpression
            || $expression instanceof QuestionMarkPlaceholderExpression
            || $expression instanceof NamedPlaceholderExpression
        ) {
            return;
        }

        $hasUnsupported = true;
    }

    private function filterRowsByPredicates(array $rows, array $predicates): array
    {
        if (empty($predicates)) {
            return $rows;
        }

        return array_filter($rows, function ($row) use ($predicates) {
            foreach ($predicates as $predicate) {
                if (!$this->evaluator->evaluate($predicate, $row)) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Filtra le righe in base a una condizione WHERE/HAVING
     */
    private function filterRows(array $rows, $whereClause): array
    {
        return array_filter($rows, function ($row) use ($whereClause) {
            return $this->evaluator->evaluate($whereClause, $row);
        });
    }

    /**
     * Raggruppa le righe per GROUP BY
     */
    private function groupRows(array $rows, array $groupBy, array $selectExpressions): array
    {
        $groups = [];

        // Raggruppa le righe
        foreach ($rows as $row) {
            $key = [];
            foreach ($groupBy as $expr) {
                $key[] = $this->evaluateGroupExpression($expr, $row);
            }
            $keyString = json_encode($key);

            if (!isset($groups[$keyString])) {
                $groups[$keyString] = [];
            }
            $groups[$keyString][] = $row;
        }

        // Calcola le aggregazioni per ogni gruppo
        $result = [];
        foreach ($groups as $groupRows) {
            $result[] = $this->computeAggregations($groupRows, $selectExpressions, $groupBy);
        }

        return $result;
    }

    /**
     * Calcola le aggregazioni (COUNT, SUM, AVG, ecc.)
     */
    private function computeAggregations(array $groupRows, array $selectExpressions, array $groupBy): array
    {
        $result = [];

        // Mantieni le colonne di GROUP BY
        foreach ($groupBy as $index => $expr) {
            $key = $this->getGroupExpressionKey($expr, $index);
            $result[$key] = $this->evaluateGroupExpression($expr, $groupRows[0]);
        }

        // Calcola le aggregazioni
        foreach ($selectExpressions as $expr) {
            if (isset($expr->name)) {
                $alias = $expr->name;

                // Verifica se è una funzione di aggregazione
                if (isset($expr->functionName)) {
                    $func = strtoupper($expr->functionName);
                    $args = $expr->args ?? [];

                    switch ($func) {
                        case 'COUNT':
                            // COUNT(*) o COUNT(colonna) - conta sempre le righe
                            $result[$alias] = count($groupRows);
                            break;
                        case 'SUM':
                            $sum = 0;
                            if (!empty($args)) {
                                // Estrai il nome della colonna dall'espressione
                                $columnExpr = $args[0];
                                $columnName = $columnExpr->columnName ?? $columnExpr->name ?? null;
                                if ($columnName) {
                                    foreach ($groupRows as $row) {
                                        $sum += $row[$columnName] ?? 0;
                                    }
                                }
                            }
                            $result[$alias] = $sum;
                            break;
                        case 'AVG':
                            $sum = 0;
                            $count = count($groupRows);
                            if (!empty($args) && $count > 0) {
                                $columnExpr = $args[0];
                                $columnName = $columnExpr->columnName ?? $columnExpr->name ?? null;
                                if ($columnName) {
                                    foreach ($groupRows as $row) {
                                        $sum += $row[$columnName] ?? 0;
                                    }
                                }
                            }
                            $result[$alias] = $count > 0 ? $sum / $count : 0;
                            break;
                        case 'MAX':
                            $max = null;
                            if (!empty($args)) {
                                $columnExpr = $args[0];
                                $columnName = $columnExpr->columnName ?? $columnExpr->name ?? null;
                                if ($columnName) {
                                    foreach ($groupRows as $row) {
                                        $val = $row[$columnName] ?? null;
                                        if ($max === null || $val > $max) {
                                            $max = $val;
                                        }
                                    }
                                }
                            }
                            $result[$alias] = $max;
                            break;
                        case 'MIN':
                            $min = null;
                            if (!empty($args)) {
                                $columnExpr = $args[0];
                                $columnName = $columnExpr->columnName ?? $columnExpr->name ?? null;
                                if ($columnName) {
                                    foreach ($groupRows as $row) {
                                        $val = $row[$columnName] ?? null;
                                        if ($min === null || $val < $min) {
                                            $min = $val;
                                        }
                                    }
                                }
                            }
                            $result[$alias] = $min;
                            break;
                    }
                }
            }
        }

        return $result;
    }

    private function evaluateGroupExpression($expr, array $row)
    {
        if (is_object($expr)) {
            return $this->evaluator->evaluate($expr, $row);
        }

        return $row[$expr] ?? null;
    }

    private function getGroupExpressionKey($expr, int $index): string
    {
        if (is_object($expr)) {
            if (isset($expr->name) && $expr->name !== '') {
                return (string) $expr->name;
            }
            if (isset($expr->columnName) && $expr->columnName !== '') {
                return (string) $expr->columnName;
            }
        }

        if (is_string($expr) && $expr !== '') {
            return $expr;
        }

        return 'group_' . $index;
    }

    /**
     * Ordina le righe per ORDER BY
     */
    private function sortRows(array $rows, array $orderBy): array
    {
        usort($rows, function ($a, $b) use ($orderBy) {
            foreach ($orderBy as $order) {
                // Estrai il nome della colonna dall'espressione
                $expression = $order['expression'];
                $column = $expression->columnName ?? $expression->name;
                $direction = $order['direction'] ?? 'ASC';

                $qualified = null;
                if (isset($expression->tableName) && $expression->tableName) {
                    $qualified = $expression->tableName . '.' . $column;
                }

                $valA = $qualified !== null && array_key_exists($qualified, $a) ? $a[$qualified] : ($a[$column] ?? null);
                $valB = $qualified !== null && array_key_exists($qualified, $b) ? $b[$qualified] : ($b[$column] ?? null);

                if ($valA != $valB) {
                    if ($direction === 'ASC') {
                        return $valA <=> $valB;
                    } else {
                        return $valB <=> $valA;
                    }
                }
            }
            return 0;
        });

        return $rows;
    }

    /**
     * Proietta le colonne richieste nel SELECT
     */
    private function projectColumns(array $rows, array $selectExpressions): array
    {
        // Se è SELECT *, ritorna tutto (ma rimuovi colonne qualificate per tabella)
        if (count($selectExpressions) === 1
            && $selectExpressions[0] instanceof \App\ArrayQuery\Query\Expression\ColumnExpression
            && $selectExpressions[0]->columnName === '*') {
            // Rimuovi le colonne qualificate con il nome della tabella (es. "users.id")
            // Mantieni solo le colonne senza punto
            $result = [];
            foreach ($rows as $row) {
                $cleanRow = [];
                foreach ($row as $key => $value) {
                    if (strpos($key, '.') === false) {
                        $cleanRow[$key] = $value;
                    }
                }
                $result[] = $cleanRow;
            }
            return $result;
        }

        $result = [];
        foreach ($rows as $row) {
            $projectedRow = [];
            foreach ($selectExpressions as $expr) {
                $exprClass = get_class($expr);

                // ColumnExpression - colonna semplice
                if ($exprClass === 'App\ArrayQuery\Query\Expression\ColumnExpression') {
                    $column = $expr->columnName;
                    $alias = $expr->name ?? $column;

                    // Se c'è un nome di tabella qualificato, cerca prima con qualificazione
                    $value = null;
                    if (isset($expr->tableName) && $expr->tableName) {
                        $qualifiedName = $expr->tableName . '.' . $column;
                        if (array_key_exists($qualifiedName, $row)) {
                            $value = $row[$qualifiedName];
                        }
                    }

                    // Fallback: cerca solo il nome della colonna
                    if ($value === null) {
                        $value = $row[$column] ?? null;
                    }

                    $projectedRow[$alias] = $value;
                }
                // Controlla se è già un valore calcolato nel row (da GROUP BY)
                elseif (isset($expr->name) && isset($row[$expr->name])) {
                    $projectedRow[$expr->name] = $row[$expr->name];
                }
                // Altrimenti valuta l'espressione
                elseif (isset($expr->name)) {
                    $value = $this->evaluator->evaluate($expr, $row);
                    $projectedRow[$expr->name] = $value;
                }
            }
            $result[] = $projectedRow;
        }

        return $result;
    }

    /**
     * Valuta un valore (stringa, numero, espressione)
     */
    private function evaluateValue($value)
    {
        if (is_object($value)) {
            // È un'espressione, valutiamola
            return $this->evaluator->evaluate($value, []);
        }
        return $value;
    }

    /**
     * Verifica se ci sono funzioni di aggregazione nelle espressioni SELECT
     */
    private function hasAggregateFunction(array $selectExpressions): bool
    {
        foreach ($selectExpressions as $expr) {
            if (isset($expr->functionName)) {
                $func = strtoupper($expr->functionName);
                if (in_array($func, ['COUNT', 'SUM', 'AVG', 'MAX', 'MIN'])) {
                    return true;
                }
            }
        }
        return false;
    }
}
