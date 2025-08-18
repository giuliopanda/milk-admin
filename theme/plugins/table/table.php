<?php
use MilkCore\Route;
use MilkCore\Token;
use MilkCore\Get;
use Theme\Template;
use MilkCore\Hooks;

/**
 * Qui definisco la struttura delle variabili per come devono essere impostate nella tabella html
 * 
 * @var $table_attrs gli attributi della tabella es. ['table' => ['class' => 'table table-hover']]
 * @var $rows le righe della tabella es. [[...], [...], [...]]
 * @var $info le informazioni per la tabella es. ['id' => ['label' => 'ID', 'type' => 'text'], ...]
 * @var $page_info le informazioni di pagina per i campi action,  la paginazione ecc.. 
 * Structure: [
 *     'page' => 'string','action' => 'string', 'limit' => 'int', 'limit_start' => 'int', 'order_field' => 'string', 'order_dir' => 'string', 'filters' => 'string', 'json' => 'bool', 'bulk_actions' => 'array', 'id' => 'string' ]
 * @example: 

 * $rows = [
 *            (object) ['id' => '1', 'name' => 'Mark Jacob', 'action' => $actions],
 *            (object) ['id' => '2', 'name' => 'Otto Thornton',  'action' => $actions] ];
 * 
 * $table_attrs[td] sono gli attributi delle celle di default.
 * $table_attrs[td.id] sono gli attributi delle celle con chiave 'id'.
 */

!defined('MILK_DIR') && die(); // Avoid direct access

$page_info['ajax'] = $page_info['ajax'] ?? true;

$primary = '';
$info = $info ?? [];

foreach ($info as $key => $i) {
    if (isset($i['primary']) && $i['primary'] == true) {
        $primary = $key;
    }
}
// form => ['class' => 'table-form-overflow js-table-form'],
$default_attrs = array(
    'form' => ['class' => 'card-body-overflow js-table-form'],
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

if ($page_info['table_attrs'] ?? false) {
    $table_attrs = array_merge($table_attrs, $page_info['table_attrs']);
}


if ($info instanceof \MilkCore\ListStructure) {
    foreach ($info as $key => $header) {
        $attr_title_structure = $info->get_attributes_title($key);
        if ($attr_title_structure) {
            if (($table_attrs['th.'.str_replace(' ','_', $key)] ?? false) ) {
                $table_attrs['th.'.str_replace(' ','_', $key)] = array_merge($table_attrs['th.'.str_replace(' ','_', $key)], $attr_title_structure);
            } else {
                $table_attrs['th.'.str_replace(' ','_', $key)] = $attr_title_structure;
            }
        }
        $attr_data_structure = $info->get_attributes_data($key);
        if ($attr_data_structure) {
            if (($table_attrs['td.'.str_replace(' ','_', $key)] ?? false)) { 
                $table_attrs['td.'.str_replace(' ','_', $key)] = array_merge($table_attrs['td.'.str_replace(' ','_', $key)], $attr_data_structure);
            } else {
                $table_attrs['td.'.str_replace(' ','_', $key)] = $attr_data_structure;
            }
        }
    }
}


if (!isset($page_info['id'])) {
    $table_id = 'tableId'.uniqid();
} else {
    $table_id = _r($page_info['id']);
}




// info è l'array che contiene le informazioni per la tabella

$table_id = $table_id ?? 'tableId'.uniqid();


$order_field = $page_info['order_field'] ?? '';
$order_dir = $page_info['order_dir'] ?? '';
$actual_page = ceil($page_info['limit_start'] / $page_info['limit']) + 1;

//

if (($info instanceof \MilkCore\ListStructure || is_array($info))  && ($page_info instanceof \MilkCore\PageInfo || is_array($page_info))) : 

    if (!$page_info['json']) : 
    ?><div class="table-container js-table-container<?php _p(($page_info['auto-scroll'] ?? true) ? '' : ' js-no-auto-scroll'); ?>" id="<?php _p( $table_id ); ?>">
    <?php 
    endif;


    echo Get::theme_plugin('loading'); 

    ?>

    <div class="alert alert-danger js-table-alert" style="display: none;"></div>
    <form method="post" <?php Template::add_attrs($table_attrs, 'form'); ?> action="<?php echo Route::url('rand='.rand()); ?>">
        <input type="hidden" name="page" value="<?php _p($page_info['page']); ?>">
        <input type="hidden" name="action" value="<?php _p($page_info['action']); ?>">
        <input type="hidden" name="page-output" value="json">
        <input type="hidden" name="table_id" value="<?php _p($table_id); ?>">
        <input type="hidden" title="table_action" name="<?php _p($table_id); ?>[table_action]" class="js-field-table-action">
        <?php echo Token::input('table'); ?>
        <input type="hidden"  title="ids" name="<?php _p($table_id); ?>[table_ids]" class="js-field-table-ids">
        <input type="hidden"  title="limit page" name="<?php _p($table_id); ?>[page]" class="js-field-table-page" value="<?php _p($actual_page, 'int'); ?>">
        <input type="hidden"  title="limit start" name="<?php _p($table_id); ?>[limit]" class="js-field-table-limit" value="<?php _p($page_info['limit'], 'int'); ?>">
        <input type="hidden"  title="order_field" name="<?php _p($table_id); ?>[order_field]" class="js-field-table-order-field"  value="<?php _p($order_field); ?>">
        <input type="hidden"  title="order_dir" name="<?php _p($table_id); ?>[order_dir]" class="js-field-table-order-dir" value="<?php _p($order_dir); ?>">
        <input type="hidden"  title="filter" name="<?php _p($table_id); ?>[filters]" class="js-field-table-filters" value="<?php _p($page_info['filters']); ?>">
        <?php 
        if (isset($page_info['form_html_input_hidden'])) {
            echo $page_info['form_html_input_hidden'];
        }
        ?>
        <?php if (count($page_info['bulk_actions'] ?? []) > 0) : ?>
            <div class="my-1 js-row-bulk-actions invisible">
                <span class="me-2"><span class="js-count-selected"></span> <?php  _pt('row selected'); ?> </span> 
                <?php foreach ($page_info['bulk_actions'] as $key => $val) : ?>
                    <span class="link-action js-table-bulk-action" data-table-action="<?php _p($key); ?>"><?php _p($val); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <table <?php Template::add_attrs($table_attrs, 'table'); ?>>
            <thead <?php Template::add_attrs($table_attrs, 'thead'); ?>>
                <tr>
                    <?php foreach ($info as $key => $header) : ?>
                        <?php if ($header['type'] == 'hidden') continue; ?>
                        <th data-attrid="th.<?php _p(str_replace(' ','_', $key)); ?>" 
                         scope="col" <?php 
                            Template::add_attrs($table_attrs, 'th.'.str_replace(' ','_', $key), 'th', 'th'); 
                         ?>>
                        <?php if ($header['type'] == 'checkbox' && $primary != '') : ?>
                            <input type="checkbox" class="form-check-input js-click-all-checkbox">
                        <?php else : ?>
                                <?php if ($header['type'] == 'action' || $header['type'] == 'checkbox' || !($header['order'] ?? true) && $primary != '') : ?>
                                    <div class="d-flex"><span class="me-2"><?php _pt($header['label']); ?> </span></div>
                                <?php else : ?>
                                    <?php if ($order_field == $key) :  ?>
                                        <div class="d-flex table-order-selected link-action js-table-change-order" data-table-field="<?php _p($key); ?>" data-table-dir="<?php echo (($order_dir == 'desc') ? 'asc' : 'desc'); ?>"><span class="me-2 table-black-link"><?php _pt($header['label']); ?> </span> <i class="bi bi-<?php echo ($order_dir == 'desc') ? 'sort-up' : 'sort-down-alt'; ?>"></i> </div>
                                    <?php else : ?>
                                        <div class="d-flex link-action js-table-change-order" data-table-field="<?php _p($key); ?>" data-table-dir="asc"><span class="me-2 table-black-link"><?php _pt($header['label']); ?> </span> <i class="bi bi-filter-left"></i> </div>
                                    <?php endif; ?>
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
            <?php if (is_array($rows) && count($rows) > 0) : ?>
                <tbody <?php Template::add_attrs($table_attrs, 'tbody'); ?>>
                    <?php foreach ($rows as $row) : ?> 
                        <tr <?php Template::add_attrs($table_attrs, 'tr'); ?>>
                            <?php foreach ($info as $col_name => $header) : ?>
                                <?php if ($header['type'] == 'hidden') continue; ?>
                                <?php $value = get_val($row, $col_name); ?>
                
                                <?php if ($header['type'] == 'checkbox' && $primary != '') : ?>
                                    <td <?php 
                                      
                                        Template::add_attrs($table_attrs, 'td.'.str_replace(' ','_', $col_name), 'td', 'td'); 
                                    ?>>
                                        <input type="checkbox" class="form-check-input js-col-checkbox" value="<?php _p($row->$primary); ?>">
                                        <span class="js-col-row"><?php echo ($value); ?></span>
                                    </td>
                                <?php elseif ($header['type'] == 'action' && $primary != '') : ?>
                                    <td <?php 
                                      
                                        Template::add_attrs($table_attrs, 'td.'.str_replace(' ','_', $col_name), 'td', 'td'); 
                                    ?>>
                                        <?php
                                        $options = Hooks::run('table_actions_row', $header['options'], $row, $table_id);
                                        if (is_array($options)) {
                                            foreach ($options as $key_opt => $val_opt) {
                                                echo '<span class="link-action js-single-action" data-table-action="' . _r($key_opt) . '" data-table-id="' . _r($row->$primary) . '">'._r($val_opt).'</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                <?php elseif ($header['type'] == 'select') : ?>
                                    <td <?php 
                                      
                                        Template::add_attrs($table_attrs, 'td.'.str_replace(' ','_', $col_name), 'td', 'td'); 
                                    ?>>
                                        <?php  _p(array_key_exists($value, $header['options']) ? $header['options'][$value] : $value); ?>
                                    </td>
                                <?php else : ?>
                                    <td <?php Template::add_attrs($table_attrs, 'td.'.str_replace(' ','_', $col_name), 'td', 'td'); 
                                    ?>><?php
                                    if ($header['type'] == 'date'){ 
                                        _p(Get::format_date($value, 'date'));
                                    } else if ($header['type'] == 'datetime') {
                                        _p(Get::format_date($value, 'datetime'));
                                    }  else if ($header['type'] == 'html') {
                                        _ph($value);
                                    } else {
                                        _p($value);
                                    } 
                                    ?></td>
                                <?php endif; ?>
                            
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            <?php endif; ?>
            <?php if ($page_info['footer'] ?? false && $footer_row > 0) : ?>
                <tfoot <?php Template::add_attrs($table_attrs, 'tfoot'); ?>>
                    <tr <?php Template::add_attrs($table_attrs, 'tfoot.tr'); ?>>
                        <?php foreach ($info as $col_name => $header) : ?>
                            <?php if ($header['type'] == 'hidden') continue; ?>
                            <td  data-attr="<?php _p('tfoot.td.'.str_replace(' ','_', $col_name)); ?>" <?php Template::add_attrs($table_attrs, 'tfoot.td.'.str_replace(' ','_', $col_name), null, ['id' =>  _r( $table_id."_".str_replace(' ','_', $col_name) )]); ?>><?php _p(get_val($footer_row, $col_name)); ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
       
       
    </form>
    <?php 
    if (($page_info['pagination'] ?? true) && $page_info['total_record'] > 0) {
        echo Get::theme_plugin('table/pagination', [ 'page_info' => $page_info]);
    }
    ?>
    <?php if (!$page_info['json']) : 
    ?> </div>
  
    <?php 
    endif;
endif;
