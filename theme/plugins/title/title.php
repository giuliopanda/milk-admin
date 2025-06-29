<?php 
/**
 * I titoli delle pagine
 * 
 * @var string $title_txt  Il titolo della pagina
 * @var array $btns L'elenco dei bottoni da visualizzare accanto al titolo ['title'=>'Add New', 'color'=>'primary', 'click'=>'create_new()']
 */

use MilkCore\Hooks;

$title_text = (!isset($title_text)) ? '' : $title_text;
if (!isset($btns) || !is_array($btns)) {
    $btns = [];
}
$title_txt = Hooks::run('plugin_title.title_txt', $title_txt);
$btns = Hooks::run('plugin_title.btns', $btns, $title_txt);
?>
<div class="module-title mb-3">
    <div class="d-flex justify-content-between">
        <div class="py-2 w-100">
            <div class="d-flex">
                <h2 class="mb-0 me-2"><?php _pt($title_txt); ?></h2>
                <div>
                    <?php 
                    foreach ($btns as $btn) {
                        if (isset($btn['link'])) {
                           ?><a class="btn btn-<?php _p($btn['color']); ?> me-2" href="<?php _p($btn['link']); ?>"><?php _pt($btn['title']); ?></a><?php
                        } else if (isset($btn['click'])) {
                            ?><span class="btn btn-<?php _p($btn['color']); ?> me-2" onclick="<?php _p($btn['click']); ?>"><?php _pt($btn['title']); ?></span><?php
                        }
                    } 
                    ?>
                </div>
            </div>
        </div>
        <?php if (isset($right)): ?>
        <?php $right = Hooks::run('plugin_title.right', $right, $title_txt); ?>
        <div  class=" flex-shrink-1 space-nowrap">
            <?php _ph($right) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php if (isset($description)): ?>
    <?php $description = Hooks::run('plugin_title.description', $description, $title_txt); ?>
    <div class="text-muted mb-3"><?php _pt($description); ?></div>
    <?php endif; ?>
</div>