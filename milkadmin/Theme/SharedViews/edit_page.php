<?php
namespace Modules\Posts\Views;
use Builders\TitleBuilder;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Variables:
 * $title - string
 * $title_btns - array [label, link, color][]
 * $description - string
 * $form - string
 */
?>
<div class="card">
    <?php if (isset($title) || isset($title_btns)) : ?>
    <div class="card-header">
        <?php 
            $title_builder = TitleBuilder::create($title );
            if (isset($title_btns) && is_array($title_btns)) {
                foreach ($title_btns as $btn) {
                    $title_builder->addButton($btn['label'], $btn['link'], $btn['color'] ?? 'primary');
                }
            }
            echo $title_builder;
        ?>
    </div>
    <?php endif; ?>
    <div class="card-body">
        <?php if (isset($description)) { ?>
            <p class="text-body-secondary mb-3"><?php _pt($description); ?></p>
        <?php } ?>
        <div class="form-group col-xl-8 col-lg-12">
            <?php echo $form; ?>
        </div>  
    </div>
</div>