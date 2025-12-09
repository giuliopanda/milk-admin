<?php
namespace Extensions\Audit\Views;

use Builders\TitleBuilder;

!defined('MILK_DIR') && die(); // Avoid direct access

?>
<div class="card">
    <div class="card-header">
    <?php
    $title = TitleBuilder::create($title);
    echo (isset($search_html)) ? $title->addRightContent($search_html) : $title->addSearch($table_id, 'Search...', 'Search');
    ?>
    </div>
    <div class="card-body">
        <p class="text-body-secondary mb-3"><?php _pt('Complete audit trail of all database operations. This table shows all inserts, updates, and deletes performed on the system.') ?></p>
        <?php _ph($html); ?>
    </div>
</div>
