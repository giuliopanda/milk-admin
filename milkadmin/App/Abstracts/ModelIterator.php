<?php
namespace App\Abstracts;

!defined('MILK_DIR') && die();

/**
 * Custom Iterator for AbstractModel
 * Respects the current output mode (raw/formatted/sql) when iterating
 */
class ModelIterator implements \Iterator
{
    /**
     * The Model instance we're iterating
     * @var AbstractModel
     */
    private AbstractModel $model;

    /**
     * Current position in iteration
     * @var int
     */
    private int $position = 0;

    /**
     * Total number of records
     * @var int
     */
    private int $count = 0;

    /**
     * Original position of the model (to restore after iteration)
     * @var int
     */
    private int $original_position = 0;

    /**
     * Constructor
     *
     * @param AbstractModel $model
     */
    public function __construct(AbstractModel $model)
    {
        $this->model = $model;
        $this->original_position = $model->getCurrentIndex();
        $this->count = $model->count();
        $this->position = 0;
    }

    /**
     * Rewind to the first element
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
        if ($this->count > 0) {
            $this->model->moveTo(0);
        }
    }

    /**
     * Return the current element
     * Returns the model at current position (respects output mode via __get)
     *
     * @return AbstractModel|null
     */
    public function current(): mixed
    {
        if ($this->position >= $this->count) {
            return null;
        }

        // Move model to current position and return it
        // When accessing properties, they will use the current output mode
        $this->model->moveTo($this->position);
        return $this->model;
    }

    /**
     * Return the key of the current element
     *
     * @return int
     */
    public function key(): mixed
    {
        return $this->position;
    }

    /**
     * Move forward to next element
     *
     * @return void
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * Check if current position is valid
     *
     * @return bool
     */
    public function valid(): bool
    {
        return $this->position < $this->count;
    }

    /**
     * Destructor - restore original position
     */
    public function __destruct()
    {
        // Restore model to original position after iteration
        if ($this->count > 0 && $this->original_position < $this->count) {
            $this->model->moveTo($this->original_position);
        }
    }
}
