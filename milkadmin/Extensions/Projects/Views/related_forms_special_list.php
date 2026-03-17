<?php
use Builders\TitleBuilder;

!defined('MILK_DIR') && die(); // Avoid direct access


$titleHtml = '';
if (isset($title) && is_string($title) && trim($title) !== '') {
    $headingSize = strtolower(trim((string) ($title_heading_size ?? 'h5')));
    if (!in_array($headingSize, ['h2', 'h3', 'h4', 'h5'], true)) {
        $headingSize = 'h5';
    }
    $title_builder = TitleBuilder::create($title)
        ->headingSize($headingSize)
        ->includeMessages(false);
    if (isset($title_class) && is_string($title_class) && trim($title_class) !== '') {
        $title_builder->titleClass(trim($title_class));
    }
    if (isset($title_container_class) && is_string($title_container_class) && trim($title_container_class) !== '') {
        $title_builder->containerClass(trim($title_container_class));
    }
    $useSmallButtons = in_array(
        strtolower(trim((string) ($title_small_buttons ?? ''))),
        ['1', 'true', 'yes', 'on'],
        true
    ) || (!empty($title_small_buttons) && $title_small_buttons === true);
    if ($useSmallButtons) {
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
            $btnSmall = in_array(
                strtolower(trim((string) ($btn['small'] ?? ''))),
                ['1', 'true', 'yes', 'on'],
                true
            ) || (!empty($btn['small']) && $btn['small'] === true);
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
    if (isset($search_html) && is_string($search_html) && trim($search_html) !== '') {
        $title_builder->addRightContent($search_html);
    } elseif ($show_search && isset($table_id) && is_string($table_id) && trim($table_id) !== '') {
        $title_builder->addSearch($table_id, 'Search...', 'Search');
    }
    if (isset($bottom_content) && is_string($bottom_content) && trim($bottom_content) !== '') {
        $title_builder->addBottomContent($bottom_content);
    }
    $titleHtml = $title_builder->render();
}
?>

<div class="row py-2 border-bottom g-3">
    <div class="col-lg-4 fw-semibold">
        <?php echo $titleHtml; ?>
    </div>
    <div class="col-lg-8">
        <?php if (isset($top_content)) : ?>
            <?php _ph($top_content); ?>
        <?php endif; ?>
        <?php if (isset($description) && trim((string) $description) !== '') : ?>
            <p class="text-body-secondary mb-3"><?php _pt($description); ?></p>
        <?php endif; ?>
        <?php _ph((string) ($html ?? '')); ?>
    </div>
</div>
