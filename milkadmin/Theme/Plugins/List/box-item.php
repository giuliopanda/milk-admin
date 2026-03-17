<?php
/**
 * Template per il singolo box item della lista
 * 
 * Variabili disponibili:
 * @var array $box_attrs Gli attributi per il box
 * @var array $box_item_attrs Gli attributi specifici per questo box (con classi dinamiche)
 * @var array $fields_data Array di oggetti con i dati dei campi [col_name => (object) ['label' => '...', 'value' => '...', 'type' => '...', 'classes' => '...']]
 * @var string $checkbox Il codice HTML per la checkbox (se presente)
 * @var string $actions Il codice HTML per le azioni (se presenti)
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div <?php Theme\Template::addAttrs($box_attrs, 'col'); ?>>
    <div <?php Theme\Template::addAttrs(['box' => $box_item_attrs], 'box'); ?>>
        <?php if ($checkbox || $actions) { ?>
            <div <?php Theme\Template::addAttrs($box_attrs, 'box.header'); ?>>
                <?php echo $checkbox; ?>
                <?php echo $actions; ?>
            </div>
        <?php } ?>
        
        <div <?php Theme\Template::addAttrs($box_attrs, 'box.body'); ?>>
            <?php foreach ($fields_data as $col_name => $field) : ?>
                <div <?php echo $field->attrs; ?>>
                    <div <?php Theme\Template::addAttrs($box_attrs, 'field.label'); ?>>
                        <?php _pt($field->label); ?>:
                    </div>
                    <div <?php Theme\Template::addAttrs($box_attrs, 'field.value'); ?>>
                        <?php 
                        if ($field->type == 'html') {
                            _ph($field->value);
                        } else {
                            _p($field->value);
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>