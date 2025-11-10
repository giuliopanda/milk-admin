<?php
/**
 * Qui definisco la struttura delle variabili per come devono essere impostate nella tabella html
 * 
 * @var $table_attrs gli attributi della tabella es. ['table' => ['class' => 'table table-hover']]
 * @var $rows le righe della tabella es. [[...], [...], [...]]
 * @var $info le informazioni per la tabella es. ['id' => ['label' => 'ID', 'type' => 'text'], ...]
 * @var $page_info le informazioni di pagina.
 */

!defined('MILK_DIR') && die(); // Avoid direct access

$primary = '';
$info = $info ?? [];
if (count($info) == 0){
    $first_row = reset($rows);
    foreach ($first_row  as $key => $value) {
        $info[$key] = ['type'=>'text', 'label' => $key];
    }
}

$default_attrs = array(
    'table' => ['class' => 'table table-hover js-table'],
    'thead' => [],
    'tbody' => ['class' => 'table-group-divider'],
    'tr' => ['class' => 'js-table-tr'],
    'td.id' =>  ['class' => 'js-td-checkbox'],
    'td.action' => ['class' => 'text-nowrap'],
    'th.checkbox' => ['class' => 'th-small'],
);

if (!isset($table_attrs) || !is_array($table_attrs)) {
    $table_attrs = [];
}
$table_attrs = array_merge($default_attrs, $table_attrs);

if (!isset($page_info['id'])) {
    $table_id = 'tableId'.uniqid();
} else {
    $table_id = _raz($page_info['id']);
}

// info è l'array che contiene le informazioni per la tabella

$table_id = $table_id ?? 'tableId'.uniqid();

//

if (is_array($info) && is_array($rows)) : 
    ?>
    <table <?php Theme\Template::addAttrs($table_attrs, 'table'); ?>>
        <thead <?php Theme\Template::addAttrs($table_attrs, 'thead'); ?>>
            <tr>
                <?php foreach ($info as $key => $header) : ?>
                    <?php if ($header['type'] == 'hidden') continue; ?>
                    <th data-attrid="th.<?php _p(str_replace(' ','_', $key)); ?>" 
                        scope="col" <?php Theme\Template::addAttrs($table_attrs, 'th.'.str_replace(' ','_', $key), 'th', 'th'); ?>>
                    <?php if ($header['type'] == 'checkbox') : ?>
                        <input type="checkbox" class="form-check-input js-click-all-checkbox">
                    <?php else : ?>
                            <?php if ($header['type'] == 'action' || $header['type'] == 'checkbox' || !($header['order'] ?? true) ) : ?>
                                <div class="d-flex"><span class="me-2"><?php _p($header['label']); ?> </span></div>
                            <?php else : ?>
                                <div class="d-flex"><span class="me-2 table-head-link"><?php _p($header['label']); ?> </span></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <?php 
        $footer_row = false;
        if (($page_info['footer'] ?? false) && count($rows) > 0) {
            $footer_row = array_pop($rows);
        }
        ?>
        <?php if (count($rows) > 0) : ?>
            <tbody <?php Theme\Template::addAttrs($table_attrs, 'tbody'); ?>>
                <?php foreach ($rows as $row) : ?> 
                    <tr <?php Theme\Template::addAttrs($table_attrs, 'tr'); ?>>
                        <?php foreach ($info as $col_name => $header) : ?>
                            <?php if ($header['type'] == 'hidden') continue; ?>
                           
                            <td <?php Theme\Template::addAttrs($table_attrs, 'td.'.str_replace(' ','_', $col_name), 'td', 'td'); ?>><?php echo getVal($row, $col_name); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        <?php endif; ?>
        <?php if ($page_info['footer'] ?? false && $footer_row > 0) : ?>
            <tfoot <?php Theme\Template::addAttrs($table_attrs, 'tfoot'); ?>>
                <tr <?php Theme\Template::addAttrs($table_attrs, 'tfoot.tr'); ?>>
                    <?php foreach ($info as $col_name => $header) : ?>
                        <?php if ($header['type'] == 'hidden') continue; ?>
                        <td  data-attr="<?php _p('tfoot.td.'.str_replace(' ','_', $col_name)); ?>" <?php Theme\Template::addAttrs($table_attrs, 'tfoot.td.'.str_replace(' ','_', $col_name), null, ['id' =>  _r( $table_id."_".str_replace(' ','_', $col_name) )]); ?>><?php getVal($footer_row, $col_name); ?></td>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
        <?php endif; ?>
    </table>
    <?php 
endif;