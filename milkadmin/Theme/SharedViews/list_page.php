<?php
namespace Modules\Posts\Views;

use Builders\TitleBuilder;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Variables:
 * $title - string
 * $title_btns - array [label, link, color][]
 * $description - string
 * $html - string
 */

?>
<div class="card">
    <div class="card-header">
    <?php
    $title_builder = TitleBuilder::create($title);
    if (isset($title_btns) && is_array($title_btns)) {
        foreach ($title_btns as $btn) {
            $title_builder->addButton($btn['label'], $btn['link'], $btn['color'] ?? 'primary');
        }
    }
    echo (isset($search_html)) ? $title_builder->addRightContent($search_html) : $title_builder->addSearch($table_id, 'Search...', 'Search');
    ?>
    </div>
    <div class="card-body">
        <?php if (isset($description)) { ?>
            <p class="text-body-secondary mb-3"><?php _pt($description); ?></p>
        <?php } ?>
        <?php _ph($html); ?>
    </div>
</div>