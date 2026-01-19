<?php
namespace App\Database;

/**
 * Result wrapper for in-memory ArrayEngine queries.
 */
class ArrayResult implements ResultInterface, \IteratorAggregate
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $rows = [];

    /**
     * @var array<int, string>
     */
    private array $column_names = [];

    private int $current_position = 0;
    private int $cached_row_count = 0;
    private bool $at_end = false;

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->cached_row_count = count($rows);
        $this->at_end = ($this->cached_row_count === 0);

        if ($this->cached_row_count > 0) {
            $this->column_names = array_keys($rows[0]);
        }
    }

    public function column_name(int $column): string|false
    {
        return $this->column_names[$column] ?? false;
    }

    public function column_type(int $column): int|false
    {
        return isset($this->column_names[$column]) ? 3 : false; // 3 = SQLITE3_TEXT
    }

    public function fetch_array(): array|false
    {
        if ($this->current_position >= $this->cached_row_count) {
            $this->at_end = true;
            return false;
        }

        $result = $this->rows[$this->current_position];
        $this->current_position++;
        $this->at_end = ($this->current_position >= $this->cached_row_count);

        return $result;
    }

    public function fetch_assoc(): array|false
    {
        return $this->fetch_array();
    }

    public function fetch_object(): object|false
    {
        $row = $this->fetch_array();

        if ($row === false) {
            return false;
        }

        return (object) $row;
    }

    public function num_columns(): int
    {
        return count($this->column_names);
    }

    public function num_rows(): int
    {
        return $this->cached_row_count;
    }

    public function reset(): bool
    {
        $this->current_position = 0;
        $this->at_end = ($this->cached_row_count === 0);
        return true;
    }

    public function data_seek(int $offset): bool
    {
        if ($offset < 0 || $offset >= $this->cached_row_count) {
            return false;
        }

        $this->current_position = $offset;
        $this->at_end = ($offset >= $this->cached_row_count);
        return true;
    }

    public function finalize(): true
    {
        $this->rows = [];
        $this->column_names = [];
        $this->current_position = 0;
        $this->cached_row_count = 0;
        $this->at_end = true;

        return true;
    }

    public function get_fields(): array
    {
        return $this->column_names;
    }

    public function getIterator(): \Traversable
    {
        $savedPosition = $this->current_position;
        $this->current_position = 0;

        foreach ($this->rows as $row) {
            yield (object) $row;
        }

        $this->current_position = $savedPosition;
    }
}
