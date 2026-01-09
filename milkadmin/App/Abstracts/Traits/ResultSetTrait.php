<?php
namespace App\Abstracts\Traits;

!defined('MILK_DIR') && die();

/**
 * ResultSet Trait
 * Provides navigation methods for ResultInterface-based Models
 * Uses current_index and cached_row from AbstractModel
 */
trait ResultSetTrait
{
    /**
     * Move to the next record in the result set
     * Updates the current index and reloads the cached row
     * Salta i record rimossi se si usa records_objects
     *
     * @return static|null Returns $this for chaining, or null if no next record
     */
    protected function moveNext(): ?static
    {
        // Se usiamo records_objects
        if ($this->records_objects !== null) {
            $max_index = empty($this->records_objects) ? -1 : max(array_keys($this->records_objects));
            $next_index = $this->current_index + 1;

            // Cerca il prossimo indice valido (non rimosso)
            while ($next_index <= $max_index) {
                if (isset($this->records_objects[$next_index])) {
                    $this->current_index = $next_index;
                    $this->loadCurrentRow();
                    return $this;
                }
                $next_index++;
            }
            return null;
        }

        // Comportamento originale con mysqli_result
        if ($this->result === null) {
            return null;
        }

        if ($this->current_index < $this->result->num_rows() - 1) {
            $this->current_index++;
            $this->loadCurrentRow();
            return $this;
        }

        return null;
    }

    /**
     * Move to the previous record in the result set
     * Updates the current index and reloads the cached row
     * Salta i record rimossi se si usa records_objects
     *
     * @return static|null Returns $this for chaining, or null if no previous record
     */
    public function prev(): ?static
    {
        // Se usiamo records_objects
        if ($this->records_objects !== null) {
            $prev_index = $this->current_index - 1;

            // Cerca il precedente indice valido (non rimosso)
            while ($prev_index >= 0) {
                if (isset($this->records_objects[$prev_index])) {
                    $this->current_index = $prev_index;
                    $this->loadCurrentRow();
                    return $this;
                }
                $prev_index--;
            }
            return null;
        }

        // Comportamento originale con mysqli_result
        if ($this->result === null) {
            return null;
        }

        if ($this->current_index > 0) {
            $this->current_index--;
            $this->loadCurrentRow();
            return $this;
        }

        return null;
    }

    /**
     * Move to the first record in the result set
     * Resets the index to 0 and reloads the cached row
     * Salta i record rimossi se si usa records_objects
     *
     * @return static|null Returns $this for chaining, or null if no records
     */
    public function first(): ?static
    {
        // Se usiamo records_objects
        if ($this->records_objects !== null) {
            if (empty($this->records_objects)) {
                $this->cached_row = null;
                return null;
            }

            // Trova il primo indice valido
            $indices = array_keys($this->records_objects);
            sort($indices);
            $this->current_index = $indices[0];
            $this->loadCurrentRow();
            return $this;
        }

        // Comportamento originale con mysqli_result
        if ($this->result === null || $this->result->num_rows() === 0) {
            $this->cached_row = null;
            return null;
        }

        $this->current_index = 0;
        $this->loadCurrentRow();
        return $this;
    }

    /**
     * Move to the last record in the result set
     * Sets the index to the last record and reloads the cached row
     * Salta i record rimossi se si usa records_objects
     *
     * @return static|null Returns $this for chaining, or null if no records
     */
    public function last(): ?static
    {
        // Se usiamo records_objects
        if ($this->records_objects !== null) {
            if (empty($this->records_objects)) {
                return null;
            }

            // Trova l'ultimo indice valido
            $indices = array_keys($this->records_objects);
            rsort($indices);
            $this->current_index = $indices[0];
            $this->loadCurrentRow();
            return $this;
        }

        // Comportamento originale con mysqli_result
        if ($this->result === null || $this->result->num_rows() === 0) {
            return null;
        }

        $this->current_index = $this->result->num_rows() - 1;
        $this->loadCurrentRow();
        return $this;
    }

    /**
     * Move to a specific index in the result set
     * Verifica che l'indice esista (non sia stato rimosso) se si usa records_objects
     *
     * @param int $index Zero-based index to move to
     * @return static|null Returns $this for chaining, or null if index out of bounds
     */
    public function moveTo(int $index): ?static
    {
        // Se usiamo records_objects
        if ($this->records_objects !== null) {
            if (isset($this->records_objects[$index])) {
                $this->current_index = $index;
                $this->loadCurrentRow();
                return $this;
            }
            return null;
        }

        // Comportamento originale con mysqli_result
        if ($this->result === null) {
            return null;
        }

        if ($index >= 0 && $index < $this->result->num_rows()) {
            $this->current_index = $index;
            $this->loadCurrentRow();
            return $this;
        }

        return null;
    }

    /**
     * Get the total number of records in the result set
     * Conta solo i record non rimossi se si usa records_objects
     *
     * @return int Number of records
     */
    public function count(): int
    {
        // Se usiamo records_objects, conta solo i record esistenti (non rimossi)
        if ($this->records_objects !== null) {
            return count($this->records_objects);
        }

        // Comportamento originale con mysqli_result
        if ($this->result === null) {
            return 0;
        }
        return $this->result->num_rows();
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
     * Salta i record rimossi se si usa records_objects
     *
     * @return bool True if there is a next record
     */
    public function hasNext(): bool
    {
        // Se usiamo records_objects
        if ($this->records_objects !== null) {
            if (empty($this->records_objects)) {
                return false;
            }

            $max_index = max(array_keys($this->records_objects));
            $next_index = $this->current_index + 1;

            // Verifica se esiste almeno un indice valido successivo
            while ($next_index <= $max_index) {
                if (isset($this->records_objects[$next_index])) {
                    return true;
                }
                $next_index++;
            }
            return false;
        }

        // Comportamento originale con mysqli_result
        if ($this->result === null) {
            return false;
        }
        return $this->current_index < $this->result->num_rows() - 1;
    }

    /**
     * Check if there is a previous record
     * Salta i record rimossi se si usa records_objects
     *
     * @return bool True if there is a previous record
     */
    public function hasPrev(): bool
    {
        // Se usiamo records_objects
        if ($this->records_objects !== null) {
            $prev_index = $this->current_index - 1;

            // Verifica se esiste almeno un indice valido precedente
            while ($prev_index >= 0) {
                if (isset($this->records_objects[$prev_index])) {
                    return true;
                }
                $prev_index--;
            }
            return false;
        }

        // Comportamento originale con mysqli_result
        return $this->current_index > 0;
    }

    /**
     * Get the current row as an associative array
     *
     * @return array|null Current row data or null if no current row
     */
    public function toArray(): ?array {
        return $this->cached_row;
    }

    /**
     * Save the current record from result set
     * Uses the cached row data to save to database
     *
     * @example
     * ```php
     * $model = Post::where('id = ?', [1])->get();
     * $model->title = "New Title";
     * $model->saveCurrentRecord();
     * ```
     *
     * @return bool True if save succeeded, false otherwise
     */
    public function saveCurrentRecord(): bool
    {
        if ($this->cached_row === null) {
            $this->error = true;
            $this->last_error = 'No current record to save';
            return false;
        }

        // Get the ID from cached row
        $id = $this->cached_row[$this->primary_key] ?? null;

        // Save using the cached row data
        return $this->save($this->cached_row, $id);
    }
}
