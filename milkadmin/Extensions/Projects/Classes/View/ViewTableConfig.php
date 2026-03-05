<?php
namespace Extensions\Projects\Classes\View;

!defined('MILK_DIR') && die();

/**
 * Configuration for one table inside a card.
 */
class ViewTableConfig
{
    public function __construct(
        public readonly string $name,
        public readonly string $displayAs,     // 'fields' | 'icon' | 'table'
        public readonly string $title,
        public readonly string $icon,
        public readonly bool $hideSideTitle,
        public readonly string $preHtml,
        public readonly string $postHtml
    ) {}
}
