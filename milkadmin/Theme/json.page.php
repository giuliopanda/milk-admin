<?php 
namespace Theme;

use App\Theme;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Template per output json
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
// qui non si deve fare il sanitize perché non è una variabile è il contenuto del sito!
echo (Theme::get('content') ?? '[]');