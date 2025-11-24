<?php
/**
 * Plugin per visualizzare i dati come lista di box Bootstrap
 * 
 * @var array $box_attrs gli attributi per i box, es. ['container' => ['class' => 'row'], 'box' => ['class' => 'card mb-3']]
 * @var array $rows le righe di dati, es. [[...], [...], [...]]
 * @var App\Modellist\ListStructure|array $info  le informazioni sui campi, es. ['id' => ['label' => 'ID', 'type' => 'text'], ...]
 * @var \App\Modellist\PageInfo|array $page_info informazioni per paginazione, azioni, ecc.
 */

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Sostituisce i placeholder %campo% nell'URL con i valori della riga
 */
function replaceRowPlaceholders($url, $row, $primary) {
    $url = str_replace('%primary%', _r($row->$primary), $url);
    return preg_replace_callback('/%([a-zA-Z0-9_]+)%/', function($matches) use ($row) {
        $field_name = $matches[1];
        try {
            if (isset($row->$field_name)) {
                return _r($row->$field_name);
            }
        } catch (Exception $e) {}
        return $matches[0];
    }, $url);
}

/**
 * Calcola le classi dinamiche per un box in base alle condizioni
 */
function getDynamicBoxClasses($row, $row_index, $conditions) {
    $classes = [];
    foreach ($conditions as $condition) {
        switch ($condition['type']) {
            case 'alternate':
                if ($row_index % 2 == 1) {
                    $classes[] = $condition['odd_classes'];
                } else {
                    $classes[] = $condition['even_classes'];
                }
                break;
            case 'value':
                $field = $condition['field'];
                $value = getVal($row, $field);
                $target_value = $condition['value'];
                $match = false;
                switch ($condition['comparison']) {
                    case '=': $match = ($value == $target_value); break;
                    case '==': $match = ($value == $target_value); break;
                    case '!=': $match = ($value != $target_value); break;
                    case '>': $match = ($value > $target_value); break;
                    case '<': $match = ($value < $target_value); break;
                    case '>=': $match = ($value >= $target_value); break;
                    case '<=': $match = ($value <= $target_value); break;
                    case 'contains': $match = (strpos($value, $target_value) !== false); break;
                }
                if ($match) {
                    $classes[] = $condition['classes'];
                }
                break;
            case 'condition':
                if (is_callable($condition['condition'])) {
                    if (call_user_func($condition['condition'], $row, $row_index)) {
                        $classes[] = $condition['classes'];
                    }
                }
                break;
        }
    }
    return implode(' ', array_filter($classes));
}

/**
 * Calcola le classi dinamiche per una riga di campo in base alle condizioni
 */
function getDynamicFieldRowClasses($row, $row_index, $field_name, $conditions) {
    $classes = [];

    foreach ($conditions as $condition) {
        // Salta se la condizione non è per questo campo
        if (isset($condition['field']) && $condition['field'] !== $field_name) {
            continue;
        }

        switch ($condition['type']) {
            case 'alternate':
                if ($row_index % 2 == 1) {
                    $classes[] = $condition['odd_classes'];
                } else {
                    $classes[] = $condition['even_classes'];
                }
                break;

            case 'value':
                $check_field = $condition['check_field'] ?? $condition['field'];
                $value = getVal($row, $check_field);
                $target_value = $condition['value'];

                $match = false;
                switch ($condition['comparison'] ?? '==') {
                    case '==':
                    case '=':
                        $match = ($value == $target_value);
                        break;
                    case '!=':
                        $match = ($value != $target_value);
                        break;
                    case '>':
                        $match = ($value > $target_value);
                        break;
                    case '<':
                        $match = ($value < $target_value);
                        break;
                    case '>=':
                        $match = ($value >= $target_value);
                        break;
                    case '<=':
                        $match = ($value <= $target_value);
                        break;
                    case 'contains':
                        $match = (strpos($value, $target_value) !== false);
                        break;
                }

                if ($match) {
                    $classes[] = $condition['classes'];
                }
                break;

            case 'specific_field':
                if ($condition['row_index'] == $row_index) {
                    $classes[] = $condition['classes'];
                }
                break;
        }
    }

    return implode(' ', array_filter($classes));
}

$page_info['ajax'] = $page_info['ajax'] ?? true;

// Inizializza le condizioni per classi dinamiche
$box_conditions = $page_info['box_conditions'] ?? [];
$field_conditions = $page_info['field_conditions'] ?? [];

// Template personalizzato o default
$box_template = $page_info['box_template'] ?? __DIR__ . '/box-item.php';

$primary = '';
$info = $info ?? [];

foreach ($info as $key => $i) {
    if (isset($i['primary']) && $i['primary'] == true) {
        $primary = $key;
    }
}

// Attributi predefiniti per la struttura dei box
$default_attrs = array(
    'form' => ['class' => 'card-body-overflow js-list-form container-fluid'],
    'container' => ['class' => 'row g-3 js-box-container'],
    'col' => ['class' => 'col-12 col-md-6 col-lg-4'],
    'box' => ['class' => 'card h-100 js-box-item'],
    'box.header' => ['class' => 'card-header d-flex justify-content-between align-items-center'],
    'box.body' => ['class' => 'card-body'],
    'box.footer' => ['class' => 'card-footer'],
    'field.row' => ['class' => 'row mb-2 border-bottom pb-2'],
    'field.label' => ['class' => 'col-5 fw-bold text-muted'],
    'field.value' => ['class' => 'col-7'],
    'checkbox.wrapper' => ['class' => 'form-check'],
);

if (!isset($box_attrs) || !is_array($box_attrs)) {
    $box_attrs = [];
}

$box_attrs = array_merge($default_attrs, $box_attrs);

if ($page_info['box_attrs'] ?? false) {
    $box_attrs = array_merge($box_attrs, $page_info['box_attrs']);
}

$checkbox_field = '';
if ($info instanceof App\Modellist\ListStructure) {
    foreach ($info as $key => $header) {
        if ($header['type'] == 'checkbox') {
            $checkbox_field = $key;
        }
        $attr_field_structure = $info->getAttributesData($key);
        if ($attr_field_structure) {
            $field_key = 'field.'.str_replace(' ','_', $key);
            if (($box_attrs[$field_key] ?? false)) { 
               
                $box_attrs[$field_key] = array_merge($box_attrs[$field_key], $attr_field_structure);
            } else {
                $box_attrs[$field_key] = $attr_field_structure;
            }
        }
    }
    if ($checkbox_field == '' && count($page_info['bulk_actions'] ?? []) > 0) {
        $info->setColumn('checkbox', '', 'checkbox')->reorderColumns('checkbox');
    }
} else {
    foreach ($info as $key => $header) {
        if ($header['type'] == 'checkbox') {
            $checkbox_field = $key;
        }
    }
    if ($checkbox_field == '' && count($page_info['bulk_actions'] ?? []) > 0) {
        array_unshift($info, ['type' => 'checkbox']);
    }
}

if (!isset($page_info['id'])) {
    $list_id = 'listId'.uniqid();
} else {
    $list_id = _r($page_info['id']);
}

$list_id = $list_id ?? 'listId'.uniqid();

$order_field = $page_info['order_field'] ?? '';
$order_dir = $page_info['order_dir'] ?? '';
$actual_page = ceil($page_info['limit_start'] / $page_info['limit']) + 1;

if (($info instanceof App\Modellist\ListStructure || is_array($info))  && ($page_info instanceof App\Modellist\PageInfo || is_array($page_info))) { 

    if (!$page_info['json']) {
        ?><div class="list-container js-list-container<?php _p(($page_info['auto-scroll'] ?? true) ? '' : ' js-no-auto-scroll'); ?>" id="<?php _p($list_id); ?>">
    <?php
    }

    echo App\Get::themePlugin('loading'); 
    ?>
    <div class="alert alert-danger js-list-alert" style="display: none;"></div>
    <form method="post" <?php Theme\Template::addAttrs($box_attrs, 'form'); ?> action="<?php echo App\Route::url('rand='.rand()); ?>">
        <input type="hidden" name="page" value="<?php _p($page_info['page']); ?>">
        <input type="hidden" name="action" value="<?php _p($page_info['action']); ?>">
        <input type="hidden" name="page-output" value="json">
        <input type="hidden" name="is-inside-request" value="1">
        <input type="hidden" name="list_id" value="<?php _p($list_id); ?>">
        <input type="hidden" title="list_action" name="<?php _p($list_id); ?>[list_action]" class="js-field-list-action">
        <?php echo App\Token::input($list_id); ?>
        <input type="hidden"  title="ids" name="<?php _p($list_id); ?>[list_ids]" class="js-field-list-ids">
        <input type="hidden"  title="limit page" name="<?php _p($list_id); ?>[page]" class="js-field-list-page" value="<?php _p($actual_page, 'int'); ?>">
        <input type="hidden"  title="limit start" name="<?php _p($list_id); ?>[limit]" class="js-field-list-limit" value="<?php _p($page_info['limit'], 'int'); ?>">
        <input type="hidden"  title="order_field" name="<?php _p($list_id); ?>[order_field]" class="js-field-list-order-field"  value="<?php _p($order_field); ?>">
        <input type="hidden"  title="order_dir" name="<?php _p($list_id); ?>[order_dir]" class="js-field-list-order-dir" value="<?php _p($order_dir); ?>">
        <input type="hidden"  title="filter" name="<?php _p($list_id); ?>[filters]" class="js-field-list-filters" value="<?php _p($page_info['filters']); ?>">
        <?php 
        if (isset($page_info['form_html_input_hidden'])) {
            echo $page_info['form_html_input_hidden'];
        }
        ?>
        <?php if (count($page_info['bulk_actions'] ?? []) > 0) { ?>
            <div class="my-3 js-row-bulk-actions invisible">
                <span class="me-2"><span class="js-count-selected"></span> <?php _pt('box selected'); ?> </span> 
                <?php foreach ($page_info['bulk_actions'] as $key => $val) { ?>
                    <span class="link-action js-list-bulk-action" data-list-action="<?php _p($key); ?>"><?php _p($val); ?></span>
                <?php } ?>
            </div>
        <?php } ?>
        
        <?php if (is_countable($rows) && count($rows) > 0) { ?>
            <div <?php Theme\Template::addAttrs($box_attrs, 'container'); ?>>
                <?php foreach ($rows as $row_index => $row) {
                    // Calcola classi dinamiche per il box
                    $dynamic_box_classes = '';
                    if (!empty($box_conditions)) {
                        $dynamic_box_classes = getDynamicBoxClasses($row, $row_index + 1, $box_conditions);
                    }
                    
                    // Merge con le classi esistenti del box
                    $box_item_attrs = $box_attrs['box'] ?? [];
                    if ($dynamic_box_classes) {
                        $existing_class = $box_item_attrs['class'] ?? '';
                        $box_item_attrs['class'] = trim($existing_class . ' ' . $dynamic_box_classes);
                    }
                    
                    // Prepara variabili per checkbox e actions
                    $checkbox = '';
                    $actions = '';
                    
                    // Header del box con checkbox e azioni
                    $has_checkbox = false;
                    $has_actions = false;
                    foreach ($info as $col_name => $header) {
                        if ($header['type'] == 'checkbox') $has_checkbox = true;
                        if ($header['type'] == 'action') $has_actions = true;
                    }
                    
                    // Genera checkbox
                    if ($has_checkbox && $primary !== '') {
                        ob_start();
                        ?>
                        <div <?php Theme\Template::addAttrs($box_attrs, 'checkbox.wrapper'); ?>>
                            <input type="checkbox" class="form-check-input js-col-checkbox" value="<?php _p($row->$primary); ?>">
                        </div>
                        <?php
                        $checkbox = ob_get_clean();
                    }
                    
                    // Genera actions
                    if ($has_actions) {
                        ob_start();
                        ?>
                        <div class="box-actions">
                            <?php 
                            foreach ($info as $col_name => $header) {
                                if ($header['type'] == 'action') {
                                    if ($primary === '') {
                                        echo '<span class="text-danger">'._r('Primary key missing').'</span>';
                                        continue;
                                    }
                                    
                                    $options = App\Hooks::run('list_actions_box', $header['options'], $row, $list_id);
                                    if (is_array($options)) {
                                        foreach ($options as $key_opt => $val_opt) {
                                            if (is_array($val_opt)) {
                                                $label = $val_opt['label'] ?? $key_opt;
                                                if (isset($val_opt['link'])) {
                                                    $link_url = replaceRowPlaceholders($val_opt['link'], $row, $primary);
                                                    $class = $val_opt['class'] ?? 'link-action';
                                                    $fetch = isset($val_opt['fetch']) ? ' data-fetch="'._r($val_opt['fetch']).'"' : '';
                                                    $target = isset($val_opt['target']) ? ' target="' . _r($val_opt['target']) . '"' : '';
                                                    $confirm_attr = isset($val_opt['confirm']) ? ' data-confirm="' . _r($val_opt['confirm']) . '"' : '';
                                                    $additional_class = isset($val_opt['confirm']) ? ' js-link-confirm' : '';
                                                    echo '<a href="' . _r($link_url) . '" class="' . _r($class) . $additional_class . '"' . $fetch . $target . $confirm_attr . '>' . _r($label) . '</a> ';
                                                } else {
                                                    $class = $val_opt['class'] ?? 'link-action js-single-action';
                                                    $confirm_attr = isset($val_opt['confirm']) ? ' data-confirm="' . _r($val_opt['confirm']) . '"' : '';
                                                    echo '<span class="' . _r($class) . '" data-list-action="' . _r($key_opt) . '" data-list-id="' . _r($row->$primary) . '"' . $confirm_attr . '>' . _r($label) . '</span> ';
                                                }
                                            } else {
                                                echo '<span class="link-action js-single-action" data-list-action="' . _r($key_opt) . '" data-list-id="' . _r($row->$primary) . '">'._r($val_opt).'</span> ';
                                            }
                                        }
                                    }
                                }
                            }
                            ?>
                        </div>
                        <?php
                        $actions = ob_get_clean();
                    }
                    
                    // Prepara i dati dei campi come array di oggetti
                    $fields_data = [];
                    foreach ($info as $col_name => $header) {
                        if ($header['type'] == 'hidden' || $header['type'] == 'checkbox' || $header['type'] == 'action') {
                            continue;
                        }
                        
                        $value = getVal($row, $col_name);
                        
                        // Formatta il valore in base al tipo
                        $formatted_value = '';
                        if ($header['type'] == 'select') {
                            $formatted_value = array_key_exists($value, $header['options']) ? $header['options'][$value] : $value;
                        } else {
                            if (is_a($value, \DateTime::class)) {
                                if (in_array($header['type'], ['date', 'datetime', 'time'])) { 
                                    $formatted_value = App\Get::formatDate($value, $header['type']);
                                } else {
                                    $formatted_value = App\Get::formatDate($value);
                                }
                            } else {
                                $formatted_value = $value;
                            }
                        }
                        
                        // Calcola classi dinamiche per la riga del campo
                        $dynamic_field_classes = '';
                        if (!empty($field_conditions)) {
                            $dynamic_field_classes = getDynamicFieldRowClasses($row, $row_index + 1, $col_name, $field_conditions);
                        }

                        $field_row_key = 'field.row';
                        $field_row_attrs = $box_attrs[$field_row_key] ?? [];
                        $field_name = 'field.'.str_replace(' ', '_', $col_name);
                        if (($box_attrs[$field_name] ?? false)) {
                            $field_row_attrs = array_merge($field_row_attrs, $box_attrs[$field_name]);
                        }
                        if ($dynamic_field_classes) {
                            $existing_class = $field_row_attrs['class'] ?? '';
                            $field_row_attrs['class'] = trim($existing_class . ' ' . $dynamic_field_classes);
                        }
                        ob_start();
                        Theme\Template::addAttrs([$field_row_key => $field_row_attrs], $field_row_key);
                        $field_row_attrs = ob_get_clean();
                        
                        $fields_data[$col_name] = (object) [
                            'label' => $header['label'],
                            'value' => $formatted_value,
                            'type' => $header['type'],
                            'classes' => $dynamic_field_classes,
                            'attrs' => $field_row_attrs
                        ];
                    }
                    // Include il template del box (personalizzato o default)
                    require $box_template;

                } ?>
            </div>
        <?php } else { ?>
            <div class="alert alert-info">
                <?php _pt('No records found'); ?>
            </div>
        <?php } ?>
       
    </form>
    <?php
    if (($page_info['pagination'] ?? true) && $page_info['total_record'] > 0) {
        echo App\Get::themePlugin('list/pagination', ['page_info' => $page_info]);
    }
    
    if (!$page_info['json']) {
    ?>
    </div>
    <?php
    }
}