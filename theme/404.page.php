<?php 
namespace Theme;
use MilkCore\Theme;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Template 404
 */

Theme::set('header.title', '404 Page not found');


$container_class = implode(" ", Theme::get_all('container-class', []));
$main_class = implode(" ", Theme::get_all('main-class', ['container-fluid']));

header("HTTP/1.0 404 Not Found");

Template::get_head();
?>
<div class="center-container <?php _p($container_class); ?>"> 
        <?php 
        foreach (Theme::for('content') as $content) {
              // qui non si deve fare il sanitize perché non è una variabile è il contenuto del sito!
              echo ($content);
        }
        ?>


</div>
<?php
Template::get_close_theme();