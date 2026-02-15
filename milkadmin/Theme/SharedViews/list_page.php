<?php
namespace Modules\Posts\Views;

use Builders\TitleBuilder;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Variables:
 * $title - string
 * $title_btns - array [label, link, color][]
 * $description - string
 * $bottom_content - string
 * $html - string
 */

?>
<div class="card">
    <?php if (isset($title)) : ?>
    <div class="card-header">
    <?php
        $title_builder = TitleBuilder::create($title);
        if (isset($title_heading_size) && is_string($title_heading_size) && trim($title_heading_size) !== '') {
            $heading_size = strtolower(trim($title_heading_size));
            if (in_array($heading_size, ['h2', 'h3', 'h4', 'h5'], true)) {
                $title_builder->headingSize($heading_size);
            }
        }
        if (isset($title_class) && is_string($title_class) && trim($title_class) !== '') {
            $title_builder->titleClass(trim($title_class));
        }
        if (isset($title_container_class) && is_string($title_container_class) && trim($title_container_class) !== '') {
            $title_builder->containerClass(trim($title_container_class));
        }
        if (!empty($title_small_buttons)) {
            $title_builder->smallButtons();
        }
        $title_id = '';
        if (isset($table_id) && is_string($table_id) && trim($table_id) !== '') {
            $title_id = trim($table_id) . 'Title';
            $title_builder->setId($title_id);
        }
        if (isset($title_btns) && is_array($title_btns)) {
            foreach ($title_btns as $btn) {
                if (!is_array($btn)) {
                    continue;
                }
                $label = (string) ($btn['label'] ?? '');
                if ($label === '') {
                    continue;
                }
                $click = (string) ($btn['click'] ?? '');
                $btnSmall = !empty($btn['small']);
                if ($click !== '') {
                    $title_builder->addClickButton($label, $click, $btn['color'] ?? 'primary', $btn['class'] ?? '', $btnSmall);
                    continue;
                }
                $link = (string) ($btn['link'] ?? '');
                if ($link === '') {
                    continue;
                }
                $title_builder->addButton($label, $link, $btn['color'] ?? 'primary', $btn['class'] ?? '', $btn['fetch'] ?? null, '', $btnSmall);
            }
        }
        $show_search = !isset($show_search) || (bool) $show_search;
        if (isset($search_html)) {
            $title_builder->addRightContent($search_html);
        } else if ($show_search && isset($table_id) && is_string($table_id) && trim($table_id) !== '') {
            $title_builder->addSearch($table_id, 'Search...', 'Search');
        }
        if (isset($bottom_content)) {
            $title_builder->addBottomContent($bottom_content);
        }
        echo $title_builder;
    ?>
    </div>
    <?php endif; ?>
    <div class="card-body">
        <?php if (isset($top_content)) : ?>
            <?php _ph($top_content); ?>
        <?php endif; ?>
        <?php if (isset($description)) { ?>
            <p class="text-body-secondary mb-3"><?php _pt($description); ?></p>
        <?php } ?>
        <?php _ph($html); ?>
    </div>
</div>
