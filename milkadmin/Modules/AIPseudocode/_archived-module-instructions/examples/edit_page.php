<?php
namespace Modules\LinksData\Views;
use Builders\TitleBuilder;

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="card">
    <div class="card-header">
        <?php _ph(TitleBuilder::create($title)); ?>
    </div>
    <div class="card-body">
        <div class="form-group col-xl-6">
            <?php _ph($form); ?>
        </div>
    </div>
</div>
