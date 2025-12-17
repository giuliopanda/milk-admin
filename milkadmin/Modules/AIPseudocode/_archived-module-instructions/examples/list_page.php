<?php
namespace Modules\Posts\Views;

use Builders\TitleBuilder;

!defined('MILK_DIR') && die(); // Avoid direct access

?>
<div class="card">
    <div class="card-header">
    <?php
    $title = TitleBuilder::create($title)->addButton('Add New', '?page='.$page.'&action='.$link_action_edit, 'primary');
    _ph((isset($search_html)) ? $title->addRightContent($search_html) : $title->addSearch($table_id, 'Search...', 'Search'));
    ?>
    </div>
    <div class="card-body">
        <?php _ph($html); ?>
    </div>
</div>
