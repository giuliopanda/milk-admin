<?php

class ListService {

    /**
     * Sostituisce i placeholder %campo% nell'URL con i valori della riga
     * @param string $url L'URL con placeholder
     * @param object $row La riga dei dati
     * @param string $primary Il nome del campo primary key
     * @return string L'URL con i placeholder sostituiti
     */
    static function replaceRowPlaceholders($url, $row, $primary) {
        // Sostituisce %primary% con il valore della primary key sanitizzato
        $url = str_replace('%primary%', _r($row->$primary), $url);

        // Sostituisce tutti i placeholder %campo% con i valori della riga sanitizzati
        return preg_replace_callback('/%([a-zA-Z0-9_]+)%/', function($matches) use ($row) {
            $field_name = $matches[1];

            // Prova ad accedere al campo tramite magic method o proprietà diretta
            try {
                if (isset($row->$field_name)) {
                    return _r($row->$field_name);
                }
            } catch (Exception $e) {
                // Silenzioso in produzione
            }

            return $matches[0]; // Ritorna il placeholder non sostituito se il campo non esiste
        }, $url);
    }

    /**
     * Calcola le classi dinamiche per un box in base alle condizioni
     */
    static function getDynamicBoxClasses($row, $row_index, $conditions) {
        $classes = [];

        foreach ($conditions as $condition) {
            switch ($condition['type']) {
                case 'alternate':
                    if ($row_index % 2 == 1) { // Righe dispari (1-indexed)
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

                case 'condition':
                    // Supporto per callable condition
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
    static function getDynamicFieldRowClasses($row, $row_index, $field_name, $conditions) {
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
}
