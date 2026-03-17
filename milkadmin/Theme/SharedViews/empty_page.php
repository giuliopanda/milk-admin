<?php
namespace Modules\Posts\Views;

use Builders\TitleBuilder;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Variables:
 * $html - string
 */

$html = isset($html) ? (string) $html : '';
echo $html; 
