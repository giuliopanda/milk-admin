<?php 
namespace Theme;

use App\Theme;
use Theme\Template;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * L'home page quando accedi
 */

Template::getHead();
?>

<?php Template::getSidebar(); ?>
<div class="main-container">
    <?php Template::getHeader(); ?>
    <main class="main">
        <?php 
        foreach (Theme::for('content') as $content) {
            // qui non si deve fare il sanitize perché non è una variabile è il contenuto del sito!
            echo ($content);
        }
        ?>
    </main>
    <?php Template::getUtilities(); ?>
    <?php Template::getFooter(); ?>
</div>
<?php Template::getCloseTheme();