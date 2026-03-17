<?php
namespace Extensions\Projects\Classes\View;

!defined('MILK_DIR') && die();

/**
 * A single card block in the view layout.
 */
class ViewCardConfig
{
    /** @param ViewTableConfig[] $tables */
    public function __construct(
        public string $id,
        public string $type,          // 'single-table' | 'group'
        public ?ViewTableConfig $table,  // set when type = single-table
        public array $tables,            // set when type = group
        public string $title,
        public string $icon,
        public string $preHtml,
        public string $postHtml
    ) {}
}
