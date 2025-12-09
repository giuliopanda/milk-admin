<?php
/**
 * The structure of the variables that need to be passed to the plugin table:
 * 
 * @var array $table_attrs the table attributes, es. ['table' => ['class' => 'table table-hover']]
 * @var array $rows the table rows, es. [[...], [...], [...]]
 * @var App\Modellist\ListStructure|array $info  the table information, es. ['id' => ['label' => 'ID', 'type' => 'text'], ...]
 * @var \App\Modellist\PageInfo|array $page_info the page information for the action,  pagination ecc.. 
 * Structure: [
 *     'page' => 'string','action' => 'string', 'limit' => 'int', 'limit_start' => 'int', 'order_field' => 'string', 'order_dir' => 'string', 'filters' => 'string', 'json' => 'bool', 'bulk_actions' => 'array', 'id' => 'string' ]
 * @example: 

 * $rows = [
 *            (object) ['id' => '1', 'name' => 'Mark Jacob', 'action' => $actions],
 *            (object) ['id' => '2', 'name' => 'Otto Thornton',  'action' => $actions] ];
 * 
 * $table_attrs[td] are the default cell attributes.
 * $table_attrs[td.id] are the cell attributes with key 'id'.
 * 
 * MIGRATION NOTE: This version uses data-* attributes instead of hidden form fields.
 * The JavaScript Table class now manages state internally and builds FormData dynamically.
 */

!defined('MILK_DIR') && die(); // Avoid direct access


$page_info['ajax'] = $page_info['ajax'] ?? true;

// Inizializza le condizioni per classi dinamiche
$row_conditions = $page_info['row_conditions'] ?? [];
$column_conditions = $page_info['column_conditions'] ?? [];

$primary = '';
$info = $info ?? [];

foreach ($info as $key => $i) {
    if (isset($i['primary']) && $i['primary'] == true) {
        $primary = $key;
    }
}

$default_attrs = array(
    'container' => ['class' => 'card-body-overflow js-table-container'],
    'table' => ['class' => 'table table-hover js-table table-row-selected'],
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

$checkbox_field = '';
if ($info instanceof App\Modellist\ListStructure) {
   
    foreach ($info as $key => $header) {
        if ($header['type'] == 'checkbox') {
            $checkbox_field = $key;
        }
        $attr_title_structure = $info->getAttributesTitle($key);
        if ($attr_title_structure) {
            if (($table_attrs['th.'.str_replace(' ','_', $key)] ?? false) ) {
                $table_attrs['th.'.str_replace(' ','_', $key)] = array_merge($table_attrs['th.'.str_replace(' ','_', $key)], $attr_title_structure);
            } else {
                $table_attrs['th.'.str_replace(' ','_', $key)] = $attr_title_structure;
            }
        }
        $attr_data_structure = $info->getAttributesData($key);
        if ($attr_data_structure) {
            if (($table_attrs['td.'.str_replace(' ','_', $key)] ?? false)) { 
                $table_attrs['td.'.str_replace(' ','_', $key)] = array_merge($table_attrs['td.'.str_replace(' ','_', $key)], $attr_data_structure);
            } else {
                $table_attrs['td.'.str_replace(' ','_', $key)] = $attr_data_structure;
            }
        }
    }
    if ($checkbox_field == '' && count($page_info['bulk_actions'] ?? []) > 0) {
        // aggiungo la colonna checkbox come prima colonna
        $info->setColumn('checkbox', '', 'checkbox')->reorderColumns('checkbox');
    }
} else {
    foreach ($info as $key => $header) {
        if ($header['type'] == 'checkbox') {
            $checkbox_field = $key;
        }
    }
    if ($checkbox_field == '' && count($page_info['bulk_actions'] ?? []) > 0) {
        // aggiungo la colonna checkbox come prima colonna
        array_unshift($info, ['type' => 'checkbox']);
    }
}

if (!isset($page_info['id'])) {
    $table_id = 'tableId'.uniqid();
} else {
    $table_id = _r($page_info['id']);
}

$table_id = $table_id ?? 'tableId'.uniqid();

$order_field = $page_info['order_field'] ?? '';
$order_dir = $page_info['order_dir'] ?? '';
$actual_page = ceil($page_info['limit_start'] / $page_info['limit']) + 1;

// Prepare custom data (replaces form_html_input_hidden)
$custom_data = [];
if (isset($page_info['custom_data']) && is_array($page_info['custom_data'])) {
    $custom_data = $page_info['custom_data'];
}
// Legacy support: convert form_html_input_hidden to custom_data if present
if (isset($page_info['form_html_input_hidden']) && !empty($page_info['form_html_input_hidden'])) {
    // Parse hidden inputs and convert to custom_data
    // This is for backward compatibility - new code should use custom_data directly
    preg_match_all('/name=["\']([^"\']+)["\'].*?value=["\']([^"\']*)["\']/', $page_info['form_html_input_hidden'], $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $custom_data[$match[1]] = $match[2];
    }
}

if (($info instanceof App\Modellist\ListStructure || is_array($info))  && ($page_info instanceof App\Modellist\PageInfo || is_array($page_info))) { 

    if (!$page_info['json']) {
        ?>
        <div 
            id="<?php _p($table_id); ?>"
            <?php Theme\Template::addAttrs($table_attrs, 'container'); ?>
            <?php _p(($page_info['auto-scroll'] ?? true) ? '' : ' data-no-auto-scroll="1"'); ?>
            data-action-url="<?php echo App\Route::url(); ?>"
            data-token="<?php _p(App\Token::get($table_id)); ?>"
            data-page="<?php _p($page_info['page']); ?>"
            data-action="<?php _p($page_info['action']); ?>"
            data-table-id="<?php _p($table_id); ?>"
            data-current-page="<?php _p($actual_page, 'int'); ?>"
            data-limit="<?php _p($page_info['limit'], 'int'); ?>"
            data-order-field="<?php _p($order_field); ?>"
            data-order-dir="<?php _p($order_dir); ?>"
            data-filters="<?php _p($page_info['filters']); ?>"
            <?php if (!empty($custom_data)) { ?>
            data-custom="<?php _p(json_encode($custom_data)); ?>"
            <?php } ?>
        >
    <?php
    }

    echo App\Get::themePlugin('loading'); 
    ?>
    <div class="alert alert-danger js-table-alert" style="display: none;"></div>
    
    <?php if (count($page_info['bulk_actions'] ?? []) > 0) { ?>
        <div class="my-1 js-row-bulk-actions invisible">
            <span class="me-2"><span class="js-count-selected"></span> <?php  _pt('rows selected'); ?> </span> 
            <?php foreach ($page_info['bulk_actions'] as $key => $val) { ?>
                <span class="link-action js-table-bulk-action" data-table-action="<?php _p($key); ?>"><?php _p($val); ?></span>
            <?php } ?>
        </div>
    <?php } ?>
    
    <div class="table-responsive">
    <table <?php Theme\Template::addAttrs($table_attrs, 'table'); ?>>
        <thead <?php Theme\Template::addAttrs($table_attrs, 'thead'); ?>>
            <tr>
                <?php foreach ($info as $key => $header) { ?>
                    <?php if ($header['type'] == 'hidden') continue; ?>
                    <th data-attrid="th.<?php _p(str_replace(' ','_', $key)); ?>" 
                     scope="col" <?php  Theme\Template::addAttrs($table_attrs, 'th.'.str_replace(' ','_', $key), 'th', 'th');   ?>>
                    <?php if ($header['type'] == 'checkbox') { ?>
                        <input type="checkbox" class="form-check-input js-click-all-checkbox">
                    <?php } else { ?>
                            <?php if ($header['type'] == 'action' || $header['type'] == 'checkbox' || !($header['order'] ?? true) ) { ?>
                                <div class="d-flex"><span class="me-2"><?php _pt($header['label']); ?> </span></div>
                            <?php } else { ?>
                                <?php if ($order_field == $key) { ?>
                                    <div class="d-flex table-order-selected link-action js-table-change-order" data-table-field="<?php _p($key); ?>" data-table-dir="<?php echo (($order_dir == 'desc') ? 'asc' : 'desc'); ?>"><span class="me-2 table-head-link"><?php _pt($header['label']); ?> </span> <i class="bi bi-<?php echo ($order_dir == 'desc') ? 'sort-up' : 'sort-down-alt'; ?> bi-head-sort"></i> </div>
                                <?php } else { ?>
                                    <div class="d-flex link-action js-table-change-order" data-table-field="<?php _p($key); ?>" data-table-dir="asc"><span class="me-2 table-head-link"><?php _pt($header['label']); ?> </span> <i class="bi bi-filter-left bi-head-sort"></i> </div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    </th>
                <?php } ?>
            </tr>
        </thead>
        <?php 
        $footer_row = false;
        if (($page_info['footer'] ?? false) && count($rows) > 0) {
            $footer_row = array_pop($rows);
        }
        ?>
        <?php if (is_countable($rows) && count($rows) > 0) { ?>
            <tbody <?php Theme\Template::addAttrs($table_attrs, 'tbody'); ?>>
                <?php foreach ($rows as $row_index => $row) {
                    // Calcola classi dinamiche per la riga
                    $dynamic_row_classes = '';
                    if (!empty($row_conditions)) {
                        $dynamic_row_classes = TableService::getDynamicRowClasses($row, $row_index + 1, $row_conditions);
                    }
                    
                    // Merge con le classi esistenti
                    $row_attrs = $table_attrs['tr'] ?? [];
                    if ($dynamic_row_classes) {
                        $existing_class = $row_attrs['class'] ?? '';
                        $row_attrs['class'] = trim($existing_class . ' ' . $dynamic_row_classes);
                    }
                    ?>
                    <tr <?php Theme\Template::addAttrs(['tr' => $row_attrs], 'tr'); ?>><?php 
                        foreach ($info as $col_name => $header) { 
                            if ($header['type'] == 'hidden') continue;
                            if ($header['type'] == 'action' && $primary === '') {
                                ?><td><span class="text-danger"><?php _p('Primary key missing'); ?></span></td><?php
                                continue;
                            }
                            if ($header['type'] == 'checkbox' && $primary === '') {
                                ?><td><span class="text-danger"><?php _p('Primary key missing'); ?></span></td><?php
                                continue;
                            }
                            $value = getVal($row, $col_name);  

                            // Calcola classi dinamiche per la cella
                            $dynamic_cell_classes = '';
                            if (!empty($column_conditions)) {
                                $dynamic_cell_classes = TableService::getDynamicCellClasses($row, $row_index + 1, $col_name, $column_conditions);
                            }
                            // Merge con le classi esistenti
                            $cell_key = 'td.'.str_replace(' ','_', $col_name);
                            $cell_attrs = $table_attrs[$cell_key] ?? $table_attrs['td'] ?? [];
                            if ($dynamic_cell_classes) {
                                $existing_class = $cell_attrs['class'] ?? '';
                                $cell_attrs['class'] = trim($existing_class . ' ' . $dynamic_cell_classes);
                            }
                            ?>
                            
                            <td <?php Theme\Template::addAttrs([$cell_key => $cell_attrs], $cell_key, 'td', 'td'); ?>>
                            
                            <?php 
                           
                            if ($header['type'] == 'checkbox' ) { ?>
                                <input type="checkbox" class="form-check-input js-col-checkbox" value="<?php _p($row->$primary); ?>">
                                <?php
                            } elseif ($header['type'] == 'action') {
                                $options = App\Hooks::run('table_actions_row', $header['options'], $row, $table_id);
                                if (is_array($options)) {
                                    foreach ($options as $key_opt => $val_opt) {
                                        // Verifica se $val_opt è un array con configurazione (per supportare i link)
                                        if (is_array($val_opt)) {
                                            $label = $val_opt['label'] ?? $key_opt;
                                            if (isset($val_opt['link'])) {
                                                // È un link - sostituisce i placeholder %campo% con i valori della riga
                                                $link_url = TableService::replaceRowPlaceholders($val_opt['link'], $row, $primary);
                                                $class = $val_opt['class'] ?? 'link-action';
                                                $fetch = isset($val_opt['fetch']) ? ' data-fetch="'._r($val_opt['fetch']).'"' : '';
                                                $target = isset($val_opt['target']) ? ' target="' . _r($val_opt['target']) . '"' : '';
                                                $confirm_attr = isset($val_opt['confirm']) ? ' data-confirm="' . _r($val_opt['confirm']) . '"' : '';
                                                $additional_class = isset($val_opt['confirm']) ? ' js-link-confirm' : '';
                                                echo '<a href="' . _r($link_url) . '" class="' . _r($class) . $additional_class . '"' . $fetch . $target . $confirm_attr . '>' . _r($label) . '</a> ';
                                            } else {
                                                // È un'action normale
                                                $class = $val_opt['class'] ?? 'link-action js-single-action';
                                                $confirm_attr = isset($val_opt['confirm']) ? ' data-confirm="' . _r($val_opt['confirm']) . '"' : '';
                                                echo '<span class="' . _r($class) . '" data-table-action="' . _r($key_opt) . '" data-table-id="' . _r($row->$primary) . '"' . $confirm_attr . '>' . _r($label) . '</span> ';
                                            }
                                        } else {
                                            // Formato legacy - solo label
                                            echo '<span class="link-action js-single-action" data-table-action="' . _r($key_opt) . '" data-table-id="' . _r($row->$primary) . '">'._r($val_opt).'</span>';
                                        }
                                    }
                                }
                                 
                            } elseif ($header['type'] == 'select') {
                               _p(array_key_exists($value, $header['options']) ? $header['options'][$value] : $value); 
                            } else {
                                if (is_a($value, \DateTime::class)) {
                                    if (in_array($header['type'], ['date', 'datetime', 'time'])) { 
                                       _p(App\Get::formatDate($value, $header['type']));
                                    } else {
                                        _p(App\Get::formatDate($value));
                                    }
                                }  else if ($header['type'] == 'html') {
                                    _ph($value);
                                }  else {
                                    _p($value);
                                } 
                            } ?>
                            </td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            </tbody>
        <?php } ?>
        <?php if ($page_info['footer'] ?? false && $footer_row > 0) { ?>
            <tfoot <?php Theme\Template::addAttrs($table_attrs, 'tfoot'); ?>>
                <tr <?php Theme\Template::addAttrs($table_attrs, 'tfoot.tr'); ?>>
                    <?php foreach ($info as $col_name => $header) { ?>
                        <?php if ($header['type'] == 'hidden') continue; ?>
                        <td data-attr="<?php _p('tfoot.td.'.str_replace(' ','_', $col_name)); ?>" <?php Theme\Template::addAttrs($table_attrs, 'tfoot.td.'.str_replace(' ','_', $col_name), null, ['id' =>  _r( $table_id."_".str_replace(' ','_', $col_name) )]); ?>><?php _p(getVal($footer_row, $col_name)); ?></td>
                    <?php } ?>
                </tr>
            </tfoot>
        <?php } ?>
    </table>
    </div>
   
    <?php 
    if (($page_info['pagination'] ?? true) && $page_info['total_record'] > 0) {
        echo App\Get::themePlugin('table/pagination', [ 'page_info' => $page_info]);
    }
  
    if (!$page_info['json']) {
    ?>
    </div>

    <?php
    }
}