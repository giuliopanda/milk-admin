<?php 
namespace Theme;

use App\Theme;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Template empty
 */

Template::getHead();
?>

<main class="main">
    <?php 
    foreach (Theme::for('content') as $content) {
         // qui non si deve fare il sanitize perché non è una variabile è il contenuto del sito!
         echo ($content);
    }
    ?>
</main>
<?php 
Template::getUtilities(); 
Template::getCloseTheme();