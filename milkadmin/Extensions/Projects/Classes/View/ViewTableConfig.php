<?php
namespace Extensions\Projects\Classes\View;

!defined('MILK_DIR') && die();

/**
 * Configuration for one table inside a card.
 */
class ViewTableConfig
{
    public function __construct(
        public string $name,
        public string $displayAs,     // 'fields' | 'icon' | 'table'
        public string $title,
        public string $icon,
        public bool $hideSideTitle,
        public string $preHtml,
        public string $postHtml
    ) {}
}
