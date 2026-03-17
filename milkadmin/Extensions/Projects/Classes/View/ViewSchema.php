<?php
namespace Extensions\Projects\Classes\View;

!defined('MILK_DIR') && die();

/**
 * Value object representing a parsed view layout.
 */
class ViewSchema
{
    /** @param ViewCardConfig[] $cards */
    public function __construct(
        public string $version,
        public array $cards
    ) {}

    /**
     * Collect all unique form names referenced in this schema.
     *
     * @return string[]
     */
    public function getAllFormNames(): array
    {
        $names = [];
        foreach ($this->cards as $card) {
            $tables = $card->type === 'single-table' && $card->table !== null
                ? [$card->table]
                : $card->tables;

            foreach ($tables as $table) {
                $names[$table->name] = true;
            }
        }
        return array_keys($names);
    }

    /**
     * Get the first card's primary table name (typically the root form displayed as fields).
     */
    public function getMainFormName(): ?string
    {
        if (empty($this->cards)) {
            return null;
        }
        $first = $this->cards[0];
        if ($first->type === 'single-table' && $first->table !== null) {
            return $first->table->name;
        }
        return null;
    }
}
