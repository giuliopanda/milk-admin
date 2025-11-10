<?php
namespace App\Abstracts\Traits;

!defined('MILK_DIR') && die();

/**
 * Collection Trait
 * Implements ArrayAccess, Iterator, Countable interfaces and provides
 * navigation methods for working with result sets that may have non-sequential keys
 */
trait CollectionTrait
{
    /**
     * Cache for array keys to avoid recalculating on every operation
     * @var array|null
     */
    private ?array $keys_cache = null;

    /**
     * Get valid keys from records_array with caching
     * 
     * @return array
     */
    private function getValidKeys(): array
    {
        if ($this->keys_cache === null) {
            $this->keys_cache = $this->records_array !== null 
                ? array_keys($this->records_array) 
                : [];
        }
        return $this->keys_cache;
    }

    /**
     * Invalidate keys cache (call this when records_array changes)
     * 
     * @return void
     */
    private function invalidateKeysCache(): void
    {
        $this->keys_cache = null;
    }

    /**
     * Find the next valid key after current_index
     * 
     * @return int|null Next valid key or null if not found
     */
    private function findNextKey(): ?int
    {
        $keys = $this->getValidKeys();
        $current_pos = array_search($this->current_index, $keys, true);
        
        if ($current_pos !== false && isset($keys[$current_pos + 1])) {
            return $keys[$current_pos + 1];
        }
        
        return null;
    }

    /**
     * Find the previous valid key before current_index
     * 
     * @return int|null Previous valid key or null if not found
     */
    private function findPrevKey(): ?int
    {
        $keys = $this->getValidKeys();
        $current_pos = array_search($this->current_index, $keys, true);
        
        if ($current_pos !== false && isset($keys[$current_pos - 1])) {
            return $keys[$current_pos - 1];
        }
        
        return null;
    }

    // ===== ArrayAccess Implementation =====

    /**
     * Check if offset exists (ArrayAccess)
     * 
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->records_array !== null 
            && is_int($offset) 
            && isset($this->records_array[$offset]);
    }

    /**
     * Get value at offset (ArrayAccess)
     * Positions the cursor at the specified index and returns $this
     * 
     * @param mixed $offset
     * @return static
     */
    public function offsetGet(mixed $offset): mixed
    {
        if ($this->offsetExists($offset)) {
            $this->current_index = $offset;
        }
        return $this;
    }

    /**
     * Set value at offset (ArrayAccess) - Not supported
     * 
     * @param mixed $offset
     * @param mixed $value
     * @return void
     * @throws \RuntimeException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \RuntimeException('Cannot set values via array access');
    }

    /**
     * Unset offset (ArrayAccess) - Not supported
     * 
     * @param mixed $offset
     * @return void
     * @throws \RuntimeException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException('Cannot unset values via array access');
    }

    // ===== Iterator Implementation =====

    /**
     * Rewind the iterator to the first record
     * 
     * @return void
     */
    public function rewind(): void
    {
        $this->first();
    }

    /**
     * Return the current record (returns $this for chaining)
     * 
     * @return static
     */
    public function current(): static
    {
        return $this;
    }

    /**
     * Return the current index as key
     * 
     * @return int|null
     */
    public function key(): ?int
    {
        return $this->current_index;
    }

    /**
     * Move to the next record (Iterator interface)
     * 
     * @return void
     */
    public function next(): void
    {
        $next_key = $this->findNextKey();
        $this->current_index = $next_key ?? -1;
    }

    /**
     * Check if current position is valid
     * 
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->records_array[$this->current_index]);
    }

    // ===== Countable Implementation =====

    /**
     * Get the total number of records in the result set
     * 
     * @return int Number of records
     */
    public function count(): int
    {
        return $this->records_array !== null ? count($this->records_array) : 0;
    }

    // ===== Navigation Methods =====

    /**
     * Move to the first record in the result set
     * 
     * @return static|null Returns $this for chaining, or null if no records
     */
    public function first(): ?static
    {
        $keys = $this->getValidKeys();
        
        if (empty($keys)) {
            $this->current_index = -1;
            return null;
        }

        $this->current_index = min($keys);
        return $this;
    }

    /**
     * Move to the last record in the result set
     * 
     * @return static|null Returns $this for chaining, or null if no records
     */
    public function last(): ?static
    {
        $keys = $this->getValidKeys();
        
        if (empty($keys)) {
            $this->current_index = -1;
            return null;
        }

        $this->current_index = max($keys);
        return $this;
    }

    /**
     * Move to the next record in the result set
     * 
     * @return static|null Returns $this for chaining, or null if no next record
     */
    public function moveNext(): ?static
    {
        $next_key = $this->findNextKey();
        
        if ($next_key !== null) {
            $this->current_index = $next_key;
            return $this;
        }
        
        return null;
    }

    /**
     * Move to the previous record in the result set
     * 
     * @return static|null Returns $this for chaining, or null if no previous record
     */
    public function prev(): ?static
    {
        $prev_key = $this->findPrevKey();
        
        if ($prev_key !== null) {
            $this->current_index = $prev_key;
            return $this;
        }
        
        return null;
    }

    /**
     * Move to a specific index in the result set
     * 
     * @param int $index Zero-based index to move to
     * @return static|null Returns $this for chaining, or null if index doesn't exist
     */
    public function moveTo(int $index): ?static
    {
        if (isset($this->records_array[$index])) {
            $this->current_index = $index;
            return $this;
        }
        
        return null;
    }

    /**
     * Get the current index
     * 
     * @return int Current zero-based index
     */
    public function getCurrentIndex(): int
    {
        return $this->current_index;
    }

    /**
     * Check if there is a next record
     * 
     * @return bool True if there is a next record
     */
    public function hasNext(): bool
    {
        return $this->findNextKey() !== null;
    }

    /**
     * Check if there is a previous record
     * 
     * @return bool True if there is a previous record
     */
    public function hasPrev(): bool
    {
        return $this->findPrevKey() !== null;
    }

    /**
     * Get the current row as an associative array
     *
     * @param int|null $index Specific index to get, or null for current index
     * @return array|null Current row data or null if no current row
     */
    public function toArray($index = null): ?array
    {
        $data = $this->records_array[$index ?? $this->current_index] ?? null;

        if ($data === null) {
            return null;
        }

        // Include relationships if requested
        if (method_exists($this, 'getIncludedRelationshipsData') && !empty($this->include_relationships)) {
            // Temporarily move to the requested index if different from current
            $original_index = $this->current_index;
            if ($index !== null && $index !== $this->current_index) {
                $this->current_index = $index;
            }

            // Use current output mode for relationships
            $output_mode = $this->output_mode ?? 'raw';
            $relationships_data = $this->getIncludedRelationshipsData($output_mode);
            foreach ($relationships_data as $alias => $rel_data) {
                $data[$alias] = $rel_data;
            }

            // Restore original index if changed
            if ($index !== null && $index !== $original_index) {
                $this->current_index = $original_index;
            }
        }

        return $data;
    }

    /**
     * Save the current record from result set
     * 
     * @return bool True if save succeeded, false otherwise
     */
    public function saveCurrentRecord(): bool
    {
        $row = $this->records_array[$this->current_index] ?? null;
        
        if ($row === null) {
            return false;
        }

        $id = $row[$this->primary_key] ?? null;
        return $this->save($row, $id);
    }
}