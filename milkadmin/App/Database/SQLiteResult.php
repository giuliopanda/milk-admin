<?php
namespace App\Database;

/**
 * Wrapper for SQLite3 Result that converts methods to snake case and implements eager fetching
 * to prevent database locks. All rows are fetched immediately and cached in memory.
 */
class SQLiteResult implements ResultInterface, \IteratorAggregate
{
    private $sqlite_result;
    private int $current_position = 0;
    private ?int $cached_row_count = null;
    private array $cached_rows = [];
    private array $column_names = [];
    private bool $at_end = false;

    public function __construct($sqlite_result)
    {
       
        $this->sqlite_result = $sqlite_result;

        // Eager fetch: load all rows immediately to release database lock
        $this->fetchAllRows();
    }

    /**
     * Fetch all rows into memory and close the native result set
     * This prevents database locking issues
     */
    private function fetchAllRows(): void
    {
        // Get column names first
        $numColumns = $this->sqlite_result->numColumns();
        for ($i = 0; $i < $numColumns; $i++) {
            $this->column_names[] = $this->sqlite_result->columnName($i);
        }
      
        // Fetch all rows into memory
        while ($row = $this->sqlite_result->fetchArray(SQLITE3_ASSOC)) {
            $this->cached_rows[] = $row;
        }

        $this->cached_row_count = count($this->cached_rows);

        // Close the native result set to release database lock
        $this->sqlite_result->finalize();
    }

    /**
     * Returns the name of a column by index
     *
     * @param int $column Index of the column (0-based)
     * @return string|false Column name or false if not found
     */
    public function column_name(int $column): string|false
    {
        return $this->column_names[$column] ?? false;
    }

    /**
     * Returns the type of a column by index
     *
     * @param int $column Index of the column (0-based)
     * @return int|false Column type or false if not found (always returns SQLITE3_TEXT for cached results)
     */
    public function column_type(int $column): int|false
    {
        // For cached results, we can't determine exact type, return TEXT as default
        return isset($this->column_names[$column]) ? SQLITE3_TEXT : false;
    }

    /**
     * Fetch an array with specified mode
     *
     * @param int $mode SQLITE3_BOTH, SQLITE3_ASSOC, or SQLITE3_NUM
     * @return array|false Array of data or false if no more rows
     */
    public function fetch_array(): array|false
    {
        if ($this->current_position >= $this->cached_row_count) {
            $this->at_end = true;
            return false;
        }

        $result = $this->cached_rows[$this->current_position];
        $this->current_position++;
        $this->at_end = ($this->current_position >= $this->cached_row_count);

        return $result;
    }

    /**
     * Fetch an associative array
     * 
     * @return array|false Array of data or false if no more rows
     */
    public function fetch_assoc(): array|false
    {
        return $this->fetch_array();
    }

    public function fetch_object(): object|null|false
    {
        $row = $this->fetch_array();

        if ($row === false) {
            return false; // nessun'altra riga
        }

        // Converti array associativo in stdClass
        return (object) $row;
    }

    /**
     * Returns the number of columns in the result set
     *
     * @return int Number of columns
     */
    public function num_columns(): int
    {
        return count($this->column_names);
    }

    /**
     * Returns the number of rows in the result set
     * Since rows are eagerly cached, this is instant
     *
     * @return int Number of rows
     */
    public function num_rows(): int
    {
        return $this->cached_row_count;
    }

    /**
     * Check if cursor is at the end of the result set
     *
     * @return bool True if at end, false otherwise
     */
    public function is_at_end(): bool
    {
        return $this->at_end;
    }

    /**
     * Reset the result pointer to the first row
     *
     * @return bool True on success
     */
    public function reset(): bool
    {
        $this->current_position = 0;
        $this->at_end = ($this->cached_row_count === 0);
        return true;
    }

    /**
     * Reset the result pointer to the specified row
     *
     * @param int $offset Index of the row (0-based)
     * @return bool True on success
     */
    public function data_seek(int $offset): bool
    {
        if ($offset < 0 || $offset >= $this->cached_row_count) {
            return false;
        }

        $this->current_position = $offset;
        $this->at_end = ($offset >= $this->cached_row_count);
        return true;
    }

    /**
     * Free the result set
     * Since we already finalized the native result in constructor, just cleanup
     *
     * @return true
     */
    public function finalize(): true
    {
        // Clear cached data
        $this->cached_rows = [];
        $this->column_names = [];
        $this->current_position = 0;
        $this->cached_row_count = 0;
        $this->at_end = true;

        return true;
    }

    public function free(): true
    {
        return $this->finalize();
    }

    public function get_fields(): array
    {
        return $this->column_names;
    }

    // Proxy methods removed - native result is already finalized

    /**
     * Rende l'oggetto iterabile con foreach
     */
    public function getIterator(): \Traversable
    {
        // Reset position to beginning for iteration
        $savedPosition = $this->current_position;
        $this->current_position = 0;

        foreach ($this->cached_rows as $row) {
            yield (object)$row;
        }

        // Restore position after iteration
        $this->current_position = $savedPosition;
    }
}