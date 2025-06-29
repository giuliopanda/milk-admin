<?php 
namespace Theme;
use MilkCore\Theme;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * L'home page quando accedi
 */

Template::get_head();
?>

<?php Template::get_sidebar(); ?>
<div class="main-container">
    <?php Template::get_header(); ?>
    <main class="main">
        <?php 
        foreach (Theme::for('content') as $content) {
            // qui non si deve fare il sanitize perché non è una variabile è il contenuto del sito!
            echo ($content);
        }
        ?>
    </main>
    <?php Template::get_utilities(); ?>
    <?php Template::get_footer(); ?>
</div>
<?php Template::get_close_theme();