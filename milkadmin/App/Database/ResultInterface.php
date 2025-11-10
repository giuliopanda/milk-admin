<?php
namespace App\Database;

/**
 * Common interface for result sets (MySQL, SQLite, etc.)
 * Ensures method consistency regardless of driver
 */
interface ResultInterface extends \IteratorAggregate
{
    /**
     * Returns the name of a column by index
     */
    public function column_name(int $column): string|false;

    /**
     * Returns the type of a column by index
     */
    public function column_type(int $column): int|false;

    /**
     * Fetch an array (behavior like fetch_assoc for MySQL, fetchArray for SQLite)
     */
    public function fetch_array(): array|null|false;

    /**
     * Fetch an associative array
     */
    public function fetch_assoc(): array|null|false;

    /**
     * Fetch an object
     */
    public function fetch_object(): object|null|false;

    /**
     * Returns the number of columns
     */
    public function num_columns(): int;

    /**
     * Returns the number of rows
     */
    public function num_rows(): int;

    /**
     * Reset the data pointer
     */
    public function reset(): bool;

    /**
     * Positions the pointer to a specific row
     */
    public function data_seek(int $offset): bool;

    /**
     * Releases the result set resources
     */
    public function finalize(): true;

    /**
     * Returns the list of fields
     */
    public function get_fields(): array;
}
