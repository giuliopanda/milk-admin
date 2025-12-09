<?php
/**
 * Plugin per visualizzare i dati come lista di box Bootstrap
 *
 * @var array $box_attrs gli attributi per i box, es. ['container' => ['class' => 'row'], 'box' => ['class' => 'card mb-3']]
 * @var array $rows le righe di dati, es. [[...], [...], [...]]
 * @var App\Modellist\ListStructure|array $info  le informazioni sui campi, es. ['id' => ['label' => 'ID', 'type' => 'text'], ...]
 * @var \App\Modellist\PageInfo|array $page_info informazioni per paginazione, azioni, ecc.
 *
 * MIGRATION NOTE: This version uses data-* attributes instead of hidden form fields.
 * The JavaScript List class now manages state internally and builds FormData dynamically.
 */

!defined('MILK_DIR') && die(); // Avoid direct access

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
    'container' => ['class' => 'card-body-overflow js-list-container'],
    'box-container' => ['class' => 'row g-3 js-box-container'],
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
            id="<?php _p($list_id); ?>"
            <?php Theme\Template::addAttrs($box_attrs, 'container'); ?>
            <?php _p(($page_info['auto-scroll'] ?? true) ? '' : ' data-no-auto-scroll="1"'); ?>
            data-action-url="<?php echo App\Route::url(); ?>"
            data-token="<?php _p(App\Token::get($list_id)); ?>"
            data-page="<?php _p($page_info['page']); ?>"
            data-action="<?php _p($page_info['action']); ?>"
            data-list-id="<?php _p($list_id); ?>"
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
    <div class="alert alert-danger js-list-alert" style="display: none;"></div>

    <?php if (count($page_info['bulk_actions'] ?? []) > 0) { ?>
        <div class="my-3 js-row-bulk-actions invisible">
            <span class="me-2"><span class="js-count-selected"></span> <?php _pt('box selected'); ?> </span>
            <?php foreach ($page_info['bulk_actions'] as $key => $val) { ?>
                <span class="link-action js-list-bulk-action" data-list-action="<?php _p($key); ?>"><?php _p($val); ?></span>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if (is_countable($rows) && count($rows) > 0) { ?>
        <div <?php Theme\Template::addAttrs($box_attrs, 'box-container'); ?>>
            <?php foreach ($rows as $row_index => $row) {
                // Calcola classi dinamiche per il box
                $dynamic_box_classes = '';
                if (!empty($box_conditions)) {
                    $dynamic_box_classes = ListService::getDynamicBoxClasses($row, $row_index + 1, $box_conditions);
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
                                                $link_url = ListService::replaceRowPlaceholders($val_opt['link'], $row, $primary);
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
                        } else if ($header['type'] == 'html') {
                            $formatted_value = $value;
                        } else {
                            $formatted_value = $value;
                        }
                    }

                    // Calcola classi dinamiche per la riga del campo
                    $dynamic_field_classes = '';
                    if (!empty($field_conditions)) {
                        $dynamic_field_classes = ListService::getDynamicFieldRowClasses($row, $row_index + 1, $col_name, $field_conditions);
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
                    $field_row_attrs_html = ob_get_clean();

                    $fields_data[$col_name] = (object) [
                        'label' => $header['label'],
                        'value' => $formatted_value,
                        'type' => $header['type'],
                        'classes' => $dynamic_field_classes,
                        'attrs' => $field_row_attrs_html
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
