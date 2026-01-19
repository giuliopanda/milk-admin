<?php
namespace App\Database;

use App\Get;
use App\Abstracts\AbstractModel;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * SQL Query Builder Class
 * 
 * This class provides a fluent interface for building SQL queries,
 * allowing you to specify components like SELECT, WHERE, ORDER BY, and LIMIT
 * in a simple and intuitive way.
 * 
 * @example
 * ```php
 * // Basic query example
 * $query = new Query('users');
 * $query->select(['id', 'username', 'email'])
 *       ->where(['status = ?'], [1])
 *       ->order('created_at', 'DESC')
 *       ->limit(0, 10);
 * list($sql, $params) = $query->get();
 * 
 * // Join example
 * $query = new Query('orders');
 * $query->select(['orders.id', 'orders.total', 'users.name'])
 *       ->from('LEFT JOIN users ON users.id = orders.user_id')
 *       ->where(['orders.status = ?'], ['completed'])
 *       ->get();
 * ```
 *
 * @package App\Database
 */
class Query
{
    /**
     * The table name for the query
     */
    private string $table;

    /**
     * List of fields to select
     * 
     * @var array<int, string>
     */
    private array $select = [];

    /**
     * Additional FROM or JOIN clauses
     * 
     * @var array<int, string>
     */
    private array $from = [];

    /**
     * WHERE conditions for the query
     * 
     * @var array<int, array{0: string, 1: array<mixed>, 2: string}>
     */
    private array $where = [];

    /**
     * ORDER BY clauses for the query
     * 
     * @var array<int, array{0: string, 1: string}>
     */
    private array $order = [];

    /**
     * LIMIT clause for the query [offset, count]
     * 
     * @var array<int, int>
     */
    private array $limit = [];

    /**
     * GROUP BY clause for the query
     */
    private string $group = '';

    /**
     * Database connection instance
     */
    private ?object $db = null;

    /**
     * HAVING conditions for the query
     * 
     * @var array<int, array{0: string, 1: array<mixed>, 2: string}>
     */
    private array $having = [];

    /**
     * Database type (mysql, sqlite, postgres)
     */
    private string $db_type = '';

    /**
     * Sort field mappings
     * Maps virtual fields to real database fields for ORDER BY
     * 
     * @var array<string, string>
     */
    private array $sort_mappings = [];

    /**
     * Static model instance
     */
    private ?AbstractModel $static_model = null;

    /**
     * Relationships to include in results
     * 
     * @var array<int, string>
     */
    private array $include_relationships = [];

    /**
     * Counter for generating unique join aliases
     */
    private int $join_alias_counter = 0;

    /**
     * Supported JOIN types
     */
    private const JOIN_TYPES = ['INNER', 'LEFT', 'RIGHT', 'FULL', 'CROSS', 'NATURAL', 'STRAIGHT', 'JOIN'];

    /**
     * Query class constructor
     * 
     * Initializes a new Query instance for the specified table.
     * 
     * @example
     * ```php
     * // Basic initialization
     * $query = new Query('users');
     * 
     * // With custom database connection
     * $customDb = new MySql($host, $user, $password, $database);
     * $query = new Query('users', $customDb);
     * ```
     *
     * @param string $table The table name to build the query on
     * @param object|null $db Optional custom database connection
     * @param AbstractModel|null $static_model Optional static model instance
     */
    public function __construct(string $table, ?object $db = null, ?AbstractModel $static_model = null)
    {
        $this->table = $table;
        $this->static_model = $static_model;
        $this->db = $db ?? Get::db();
        $this->db_type = $this->detectDatabaseType();
    }

    /**
     * Detects the database type from the connection class name
     */
    private function detectDatabaseType(): string
    {
        if ($this->db === null) return '';
        $className = get_class($this->db);
        
        return match (true) {
            str_contains($className, 'MySQL') || str_contains($className, 'MySql') => 'mysql',
            str_contains($className, 'SQLite') => 'sqlite',
            str_contains($className, 'ArrayDb') || str_contains($className, 'ArrayEngine') => 'array',
            str_contains($className, 'Postgres') || str_contains($className, 'PostgreSQL') => 'postgres',
            default => 'mysql',
        };
    }

    /**
     * Specifies the fields to select
     * 
     * Sets the fields to be selected in the query. Fields can be specified as an array
     * or as a comma-separated string.
     * 
     * @example
     * ```php
     * // Select specific fields
     * $query->select(['id', 'username', 'email']);
     * 
     * // Select with aliases
     * $query->select(['u.id', 'u.username AS user', 'COUNT(*) AS total']);
     * 
     * // Using a string
     * $query->select('id, username, email');
     * ```
     *
     * @param string|array<int, string> $fields Fields to select
     * @return $this Allows method chaining
     */
    public function select(string|array $fields): self
    {
        if (is_array($fields)) {
            $this->select = array_merge($this->select, $fields);
        } else {
            $this->select[] = $fields;
        }
        return $this;
    }

    /**
     * Checks if any fields have been selected
     */
    public function hasSelect(): bool
    {
        return count($this->select) > 0;
    }

    /**
     * Specifies additional FROM or JOIN clauses
     * 
     * Adds JOIN statements to the query. The method automatically detects
     * JOIN types (INNER, LEFT, RIGHT, etc.) from the provided string.
     * 
     * @example
     * ```php
     * // Basic join
     * $query->from('JOIN categories ON products.category_id = categories.id');
     * 
     * // Left join
     * $query->from('LEFT JOIN users ON orders.user_id = users.id');
     * 
     * // Multiple joins
     * $query->from('JOIN users ON orders.user_id = users.id')
     *       ->from('LEFT JOIN products ON order_items.product_id = products.id');
     * ```
     *
     * @param string $from JOIN statement to add
     * @return $this Allows method chaining
     */
    public function from(string $from): self
    {
        $firstWord = strtoupper(explode(' ', trim($from))[0] ?? '');
        
        if (in_array($firstWord, self::JOIN_TYPES, true)) {
            $this->from[] = $from;
        } else {
            $this->from[] = ', ' . $from;
        }
        return $this;
    }

    /**
     * Adds WHERE conditions to the query
     *
     * @param string $where WHERE condition (e.g., 'field1 = ? AND field2 = ?')
     * @param array<mixed>|scalar $params Parameters to bind
     * @param string $operator Logical operator ('AND' or 'OR')
     * @return $this Allows method chaining
     */
    public function where(string $where, array|string|int|float|bool|null $params = [], string $operator = 'AND'): self
    {
        $operator = strtoupper($operator) === 'OR' ? 'OR' : 'AND';
        $paramsArray = is_array($params) ? $params : [$params];
        $this->where[] = [$where, $paramsArray, $operator];

        return $this;
    }

    /**
     * Adds WHERE IN clause to the query
     *
     * @param string $field Field name for the IN condition
     * @param array<mixed> $values Array of values for the IN clause
     * @param string $operator Logical operator ('AND' or 'OR')
     * @return $this Allows method chaining
     */
    public function whereIn(string $field, array $values, string $operator = 'AND'): self
    {
        if (empty($values)) {
            return $this;
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $whereClause = $this->db->qn($field) . " IN ($placeholders)";

        return $this->where($whereClause, $values, $operator);
    }

    /**
     * Checks if any WHERE conditions have been added
     */
    public function hasWhere(): bool
    {
        return count($this->where) > 0;
    }

    /**
     * WHERE EXISTS with subquery for relationship filtering
     * Filters main table based on existence of related records
     *
     * @param string $relationAlias Relationship alias defined in model
     * @param string $condition WHERE condition for the subquery (e.g., 'year > ?')
     * @param array<mixed> $params Parameters for the condition
     * @param string $operator Logical operator ('AND' or 'OR')
     * @return $this Allows method chaining
     * @throws \Exception If no model instance is set or relationship not found
     *
     * @example
     * ```php
     * // Find actors that have films after 1990
     * $query->whereHas('films', 'year > ?', [1990]);
     * ```
     */
    public function whereHas(string $relationAlias, string $condition, array $params = [], string $operator = 'AND'): self
    {
        $relationship = $this->getRelationshipConfig($relationAlias);
        $relatedTable = $this->getRelatedTable($relationship);

        $subquery = $this->buildExistsSubquery($relationship, $relatedTable, $condition);

        return $this->where($subquery, $params, $operator);
    }

    /**
     * Gets the relationship configuration from the model
     * 
     * @throws \Exception If model is not set or relationship not found
     * @return array<string, mixed> Relationship configuration
     */
    private function getRelationshipConfig(string $relationAlias): array
    {
        if ($this->static_model === null) {
            throw new \Exception("whereHas()/orderHas() requires a model instance. Use query() from a model.");
        }

        $rules = $this->static_model->getRules();

        foreach ($rules as $rule) {
            if (isset($rule['relationship']) && $rule['relationship']['alias'] === $relationAlias) {
                return $rule['relationship'];
            }
        }

        throw new \Exception("Relationship '$relationAlias' not found in model");
    }

    /**
     * Gets the related table name from relationship config
     * 
     * @param array<string, mixed> $relationship
     */
    private function getRelatedTable(array $relationship): string
    {
        $relatedClass = $relationship['related_model'];
        $relatedModel = new $relatedClass();
        return $relatedModel->getRuleBuilder()->getTable();
    }

    /**
     * Builds EXISTS subquery for whereHas
     * 
     * @param array<string, mixed> $relationship
     * @throws \Exception If relationship type is not supported
     */
    private function buildExistsSubquery(array $relationship, string $relatedTable, string $condition): string
    {
        $type = $relationship['type'];

        if ($type === 'hasOne' || $type === 'hasMany') {
            return sprintf(
                "EXISTS (SELECT 1 FROM %s WHERE %s.%s = %s.%s AND (%s))",
                $this->db->qn($relatedTable),
                $this->db->qn($relatedTable),
                $this->db->qn($relationship['foreign_key']),
                $this->db->qn($this->table),
                $this->db->qn($relationship['local_key']),
                $condition
            );
        }

        if ($type === 'belongsTo') {
            return sprintf(
                "EXISTS (SELECT 1 FROM %s WHERE %s.%s = %s.%s AND (%s))",
                $this->db->qn($relatedTable),
                $this->db->qn($relatedTable),
                $this->db->qn($relationship['related_key']),
                $this->db->qn($this->table),
                $this->db->qn($relationship['foreign_key']),
                $condition
            );
        }

        throw new \Exception("Unsupported relationship type: $type");
    }

    /**
     * ORDER BY with LEFT JOIN for relationship ordering
     * Orders main table based on a field from a related table
     *
     * @param string $relationAlias Relationship alias defined in model
     * @param string $orderField Field from related table to order by
     * @param string $direction Order direction ('ASC' or 'DESC')
     * @return $this Allows method chaining
     * @throws \Exception If no model instance is set or relationship not found
     *
     * @example
     * ```php
     * // Order actors by their category name
     * $query->orderHas('category', 'name', 'DESC');
     * ```
     */
    public function orderHas(string $relationAlias, string $orderField, string $direction = 'ASC'): self
    {
        $relationship = $this->getRelationshipConfig($relationAlias);
        $relatedTable = $this->getRelatedTable($relationship);
       
        $this->join_alias_counter++;
        $alias = 'orderhas_alias_' . $this->join_alias_counter;

        $joinClause = $this->buildOrderHasJoin($relationship, $relatedTable, $alias);
        $this->from($joinClause);
        $this->order($alias . '.' . $orderField, $direction);

        return $this;
    }

    /**
     * Builds LEFT JOIN clause for orderHas
     * 
     * @param array<string, mixed> $relationship
     * @throws \Exception If relationship type is not supported
     */
    private function buildOrderHasJoin(array $relationship, string $relatedTable, string $alias): string
    {
        $type = $relationship['type'];

        if ($type === 'hasOne' || $type === 'hasMany') {
            return sprintf(
                "LEFT JOIN %s AS %s ON %s.%s = %s.%s",
                $this->db->qn($relatedTable),
                $this->db->qn($alias),
                $this->db->qn($alias),
                $this->db->qn($relationship['foreign_key']),
                $this->db->qn($this->table),
                $this->db->qn($relationship['local_key'])
            );
        }

        if ($type === 'belongsTo') {
            return sprintf(
                "LEFT JOIN %s AS %s ON %s.%s = %s.%s",
                $this->db->qn($relatedTable),
                $this->db->qn($alias),
                $this->db->qn($alias),
                $this->db->qn($relationship['related_key']),
                $this->db->qn($this->table),
                $this->db->qn($relationship['foreign_key'])
            );
        }

        throw new \Exception("Unsupported relationship type: $type");
    }

    /**
     * Specifies the ordering of results
     * 
     * @param string|array<int, string> $field Field name or array of field names
     * @param string|array<int, string> $dir Direction ('ASC' or 'DESC') or array of directions
     * @return $this Allows method chaining
     */
    public function order(string|array $field = '', string|array $dir = 'ASC'): self
    {
        if ($field === '' || (is_array($field) && empty($field))) {
            return $this;
        }

        if (is_array($field)) {
            foreach ($field as $i => $f) {
                $direction = is_array($dir) ? ($dir[$i] ?? 'ASC') : $dir;
                $this->order[] = [$f, $direction];
            }
        } else {
            $this->order[] = [$field, is_string($dir) ? $dir : 'ASC'];
        }
        
        return $this;
    }

    /**
     * Checks if any ORDER BY clauses have been added
     */
    public function hasOrder(): bool
    {
        return count($this->order) > 0;
    }

    /**
     * Sets sort field mapping for ORDER BY clause
     * Maps virtual field names to real database field names
     * 
     * @param string $virtual_field Virtual field name (e.g., 'title_original')
     * @param string $real_field Real database field name (e.g., 'title')
     * @return $this Allows method chaining
     */
    public function setSortMapping(string $virtual_field, string $real_field): self
    {
        $this->sort_mappings[$virtual_field] = $real_field;
        return $this;
    }

    /**
     * Sets the result limit
     * 
     * @param int $start First record offset
     * @param int $limit Number of records to return
     * @return $this Allows method chaining
     */
    public function limit(int $start, int $limit): self
    {
        $this->limit = [_absint($start), _absint($limit)];
        return $this;
    }

    /**
     * Checks if a LIMIT clause has been set
     */
    public function hasLimit(): bool
    {
        return count($this->limit) > 0;
    }

    /**
     * Sets the GROUP BY clause
     */
    public function group(string $group): self
    {
        $this->group = $group;
        return $this;
    }

    /**
     * Checks if a GROUP BY clause has been set
     */
    public function hasGroup(): bool
    {
        return $this->group !== '';
    }

    /**
     * Adds HAVING conditions to the query
     * 
     * @param string $having HAVING condition (e.g., 'COUNT(*) > ?')
     * @param array<mixed>|scalar $params Parameters to bind
     * @param string $operator Logical operator ('AND' or 'OR')
     * @return $this Allows method chaining
     */
    public function having(string $having, array|string|int|float|bool|null $params = [], string $operator = 'AND'): self
    {
        $operator = strtoupper($operator) === 'OR' ? 'OR' : 'AND';
        $paramsArray = is_array($params) ? $params : [$params];
        $this->having[] = [$having, $paramsArray, $operator];
        return $this;
    }
        
    /**
     * Checks if any HAVING conditions have been added
     */
    public function hasHaving(): bool
    {
        return count($this->having) > 0;
    }

    /**
     * Builds and returns the SQL query and parameters
     * 
     * @param bool $convert Whether to convert the query for the specific database type
     * @return array{0: string, 1: array<mixed>} Array containing SQL string and parameters
     */
    public function get(bool $convert = true): array
    {
        $params = [];
        
        $selectFields = empty($this->select) ? ['*'] : $this->select;
        $sql = 'SELECT ' . implode(',', $selectFields) . ' FROM ' . $this->db->qn($this->table);
        
        if (!empty($this->from)) {
            $sql .= ' ' . implode(' ', $this->from);
        }
        
        [$whereClause, $params] = $this->buildWhere();
        $sql .= $whereClause;

        if ($this->group !== '') {
            $sql .= ' GROUP BY ' . $this->db->qn($this->group);
        }

        [$havingClause, $havingParams] = $this->buildHaving();
        $sql .= $havingClause;
        $params = array_merge($params, $havingParams);

        $sql .= $this->buildOrderBy();
        $sql .= $this->buildLimit();

        if ($this->db_type !== '' && $convert) {
            $converter = new QueryConverter($this->db_type);
            [$sql, $params] = $converter->convert($sql, $params);
        }
        
        return [$sql, $params];
    }

    /**
     * Builds the HAVING clause
     * 
     * @return array{0: string, 1: array<mixed>}
     */
    private function buildHaving(): array
    {
        if (empty($this->having)) {
            return ['', []];
        }

        $params = [];
        $clauses = [];
        
        foreach ($this->having as $index => $having) {
            $condition = str_replace(';', '', $having[0]);
            $operator = strtoupper($having[2]) === 'OR' ? 'OR' : 'AND';
            
            if ($index === 0) {
                $clauses[] = "($condition)";
            } else {
                $clauses[] = "$operator ($condition)";
            }
            
            $params = array_merge($params, $having[1]);
        }

        return [' HAVING ' . implode(' ', $clauses), $params];
    }

    /**
     * Builds the ORDER BY clause
     */
    private function buildOrderBy(): string
    {
        if (empty($this->order)) {
            return '';
        }

        $orderParts = [];
        foreach ($this->order as $orderItem) {
            $field = $orderItem[0];
            $direction = strtolower($orderItem[1]) === 'asc' ? 'ASC' : 'DESC';
            
            if (isset($this->sort_mappings[$field])) {
                $field = $this->sort_mappings[$field];
            }
            
            $orderParts[] = $this->db->qn($field) . ' ' . $direction;
        }
        
        return ' ORDER BY ' . implode(', ', $orderParts);
    }

    /**
     * Builds the LIMIT clause
     */
    private function buildLimit(): string
    {
        if (empty($this->limit)) {
            return '';
        }
        
        return ' LIMIT ' . _absint($this->limit[0]) . ',' . _absint($this->limit[1]);
    }

    /**
     * Get the SQL query string with parameters replaced (for debug purposes only)
     * 
     * WARNING: This method should ONLY be used for debugging and logging,
     * never for actual query execution.
     * 
     * @return string The SQL query with parameters substituted
     */
    public function toSql(): string
    {
        [$sql, $params] = $this->get();
        
        $quotedParams = array_map(fn($param) => $this->quoteParam($param), $params);
        
        $sql = str_replace('?', '%s', $sql);
        return vsprintf($sql, $quotedParams);
    }

    /**
     * Quotes a parameter value for SQL display
     */
    private function quoteParam(mixed $param): string
    {
        return match (true) {
            is_null($param) => 'NULL',
            is_bool($param) => $param ? '1' : '0',
            is_int($param), is_float($param) => (string) $param,
            is_string($param) => method_exists($this->db, 'quote') 
                ? $this->db->quote($param) 
                : "'" . addslashes($param) . "'",
            default => "'" . addslashes(serialize($param)) . "'",
        };
    }

    /**
     * Builds and returns the SQL query to calculate the total number of records
     *
     * @return array{0: string, 1: array<mixed>} Array containing SQL string and parameters
     */
    public function getTotal(): array
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->db->qn($this->table);

        // Include FROM/JOIN clauses (like get() method does)
        if (!empty($this->from)) {
            $sql .= ' ' . implode(' ', $this->from);
        }

        [$whereClause, $params] = $this->buildWhere();
        $sql .= $whereClause;

        // Include GROUP BY if present (for complex queries)
        if ($this->group !== '') {
            $sql .= ' GROUP BY ' . $this->db->qn($this->group);
        }

        // Include HAVING if present (for complex queries)
        $havingParams = [];
        if (!empty($this->having)) {
            [$havingClause, $havingParams] = $this->buildHaving();
            $sql .= $havingClause;
            $params = array_merge($params, $havingParams);
        }

        return [$sql, $params];
    }

    /**
     * Clears the query parameters
     *
     * @param string $single Specific part to clear ('select', 'from', 'where', 'order', 'limit', 'having', 'group')
     *                       or empty string to clear all
     */
    public function clean(string $single = ''): self
    {
        $parts = [
            'select' => [],
            'from' => [],
            'where' => [],
            'order' => [],
            'limit' => [],
            'having' => [],
            'group' => '',
        ];

        if ($single !== '' && array_key_exists($single, $parts)) {
            $this->{$single} = $parts[$single];
        } elseif ($single === '') {
            foreach ($parts as $part => $default) {
                $this->{$part} = $default;
            }
        }
        
        return $this;
    }

    /**
     * Builds the WHERE clause of the query
     *
     * @return array{0: string, 1: array<mixed>} Array containing WHERE string and parameters
     */
    private function buildWhere(): array
    {
        if (empty($this->where)) {
            return ['', []];
        }

        $params = [];
        $clauses = [];
        
        foreach ($this->where as $index => $where) {
            $condition = str_replace(';', '', $where[0]);
            $operator = strtoupper($where[2]) === 'OR' ? 'OR' : 'AND';
            
            if ($index === 0) {
                $clauses[] = "($condition)";
            } else {
                $clauses[] = "$operator ($condition)";
            }
            
            $params = array_merge($params, $where[1]);
        }

        return [' WHERE ' . implode(' ', $clauses), $params];
    }

    /**
     * Executes the query and returns the results
     *
     * @return AbstractModel|array<int, array<string, mixed>>|null|false 
     *         The results of the query or false if the query fails
     */
    public function getResults(): AbstractModel|array|null|false
    {
        if ($this->db === null)  return null;
        
        if ($this->static_model !== null) {
            $result = $this->db->getResults(...$this->get());
            
            if ($result !== null) {
                $this->static_model->setResults($result);
            }
            
            $this->static_model->setQueryColumns($this->db->getQueryColumns());
            $this->applyRelationships();
          
            return $this->static_model;
        }
        
        return $this->db->getResults(...$this->get());
    }

    /**
     * Executes the query and returns a single row
     *
     * @return AbstractModel|array<string, mixed>|null|false 
     *         A row or false if the query fails
     */
    public function getRow(): AbstractModel|array|null|false
    {
        if ($this->db === null)  return null;
        
        $this->limit(0, 1);
        
        if ($this->static_model !== null) {
            $this->static_model->setRow($this->db->getRow(...$this->get()));
            $this->static_model->setQueryColumns($this->db->getQueryColumns());
            $this->applyRelationships();
            
            return $this->static_model;
        }
        
        return $this->db->getRow(...$this->get());
    }

    /**
     * Applies include_relationships to the model if set
     */
    private function applyRelationships(): void
    {
        if (!empty($this->include_relationships) && method_exists($this->static_model, 'with')) {
            $this->static_model->with($this->include_relationships);
        }
    }

    /**
     * Executes the query and returns a single value
     *
     * @param string|null $value Column name to retrieve, or null for first column
     * @return mixed A single value or false if the query fails
     */
    public function getVar(?string $value = null): mixed
    {
        if ($this->db === null)  return null;
        $this->limit(0, 1);
        
        if ($value === null) {
            return $this->db->getVar(...$this->get());
        }
        
        $result = $this->db->getRow(...$this->get());
      
        if ($result === false) {
            return false;
        }
         if (is_object($result) && property_exists($result, $value)) {
            return $result->$value;
        } else  if (is_object($result)) {
            if (isset($result->$value)) {
                return $result->$value;
            } else {
                return null;
            }
        }
          
        if (is_array($result) && array_key_exists($value, $result)) {
            return $result[$value];
        } else if (is_array($result)) {
            return reset($result);
        }
        
        return $result;
    }

    /**
     * Set the static model
     *
     * @param AbstractModel $model The static model
     */
    public function setModelClass(AbstractModel $model): void
    {
        $this->static_model = $model;

        if (method_exists($model, 'getIncludeRelationships')) {
            $relationships = $model->getIncludeRelationships();
            if (!empty($relationships)) {
                $this->include_relationships = $relationships;
            }
        }
    }

    /**
     * Returns the SQL query for string conversion
     */
    public function __toString(): string
    {
        return $this->toSql();
    }

    /**
     * Gets the table name
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Gets the database connection
     */
    public function getDb(): ?object
    {
        return $this->db;
    }

    /**
     * Gets the database type
     */
    public function getDbType(): string
    {
        return $this->db_type;
    }
}
