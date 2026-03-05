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
        public readonly string $id,
        public readonly string $type,          // 'single-table' | 'group'
        public readonly ?ViewTableConfig $table,  // set when type = single-table
        public readonly array $tables,            // set when type = group
        public readonly string $title,
        public readonly string $icon,
        public readonly string $preHtml,
        public readonly string $postHtml
    ) {}
}
