<?php
namespace MilkCore;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * La classe ListStructure gestisce la struttura delle colonne di una tabella
 * Implementa ArrayAccess per poter accedere alle proprietà come array
 * 
 * @package     MilkCore
 * @subpackage  ModelList
 */

class ListStructure implements \ArrayAccess, \Iterator, \Countable {
    private $properties = [];
    private $position = 0;
    private $keys = [];
    
    /**
     * Costruttore che può accettare un array di struttura iniziale
     */
    public function __construct(array $structure = []) {
        $this->properties = $structure;
        $this->keys = array_keys($this->properties);
    }
    
    /**
     * Implementazione dei metodi richiesti da ArrayAccess
     */
    public function offsetExists($offset): bool {
        return isset($this->properties[$offset]);
    }
    
    public function offsetGet($offset): mixed {
        return $this->properties[$offset] ?? null;
    }
    
    public function offsetSet($offset, $value): void {
        if (is_null($offset)) {
            $this->properties[] = $value;
        } else {
            $this->properties[$offset] = $value;
        }
        $this->keys = array_keys($this->properties);
    }
    
    public function offsetUnset($offset): void {
        if (isset($this->properties[$offset])) {
            unset($this->properties[$offset]);
            $this->keys = array_keys($this->properties);
        }
    }
    
    /**
     * Implementazione dei metodi richiesti da Iterator
     */
    public function rewind(): void {
        $this->position = 0;
    }
    
    public function current(): mixed {
        $key = $this->keys[$this->position] ?? null;
        return $key !== null ? $this->properties[$key] : null;
    }
    
    public function key(): mixed {
        return $this->keys[$this->position] ?? null;
    }
    
    public function next(): void {
        ++$this->position;
    }
    
    public function valid(): bool {
        return isset($this->keys[$this->position]);
    }
    
    /**
     * Implementazione dei metodi richiesti da Countable
     */
    public function count(): int {
        return count($this->properties);
    }
    
    /**
     * Metodi helper per lavorare con la struttura
     */
    
    /**
     * Imposta una colonna nella struttura
     */
    public function set_column($db_name, $label, $type = 'text', $order = true, $primary = false, $options = [], $attributes_title = [], $attributes_data = []) {
        $this->properties[$db_name] = [
            'label' => $label, 
            'type' => $type, 
            'order' => $order, 
            'primary' => $primary, 
            'options' => $options,
            'attributes_title' => $attributes_title,
            'attributes_data' => $attributes_data
        ];
        $this->keys = array_keys($this->properties);
        return $this;
    }

    public function set_action($options = [], $label = 'Action') {
        $this->properties['action'] = [
            'label' => $label, 
            'type' => 'action', 
            'options' => $options
        ];
        $this->keys = array_keys($this->properties);
        return $this;
    }
    
    /**
     * Ottiene una colonna dalla struttura
     */
    public function get_column($db_name) {
        return $this->properties[$db_name] ?? null;
    }

    public function hide_columns($db_names) {
        if (is_array($db_names)) {
            foreach ($db_names as $db_name) {
                $this->hide_column($db_name);
            }
        } else {
            $this->hide_column($db_names);
        }
        return $this;
    }

    public function hide_column($db_name) {
        if (isset($this->properties[$db_name])) {
            $this->properties[$db_name]['type'] = 'hidden';
        }
        return $this;
    }

    public function delete_columns($db_names) { 
        if (is_array($db_names)) {
            foreach ($db_names as $db_name) {
                $this->delete_column($db_name);
            }
        } else {
            $this->delete_column($db_names);
        }
        return $this;
    }

    public function delete_column($db_name) {
        if (isset($this->properties[$db_name])) {
            unset($this->properties[$db_name]);
            $this->keys = array_keys($this->properties);
        }
        return $this;
    }
    
    /**
     * Imposta l'etichetta di una colonna
     */
    public function set_label($db_name, $label) {
        if (isset($this->properties[$db_name])) {
            $this->properties[$db_name]['label'] = $label;
        }
        return $this;
    }
    
    /**
     * Imposta il tipo di una colonna
     */
    public function set_type($db_name, $type) {
        if (isset($this->properties[$db_name])) {
            $this->properties[$db_name]['type'] = $type;
        }
        return $this;
    }
    
    /**
     * Imposta se la colonna è ordinabile
     */
    public function set_order($db_name, $orderable) {
        if (isset($this->properties[$db_name])) {
            $this->properties[$db_name]['order'] = (bool)$orderable;
        }
        return $this;
    }
    
    /**
     * Imposta una colonna come chiave primaria
     */
    public function set_primary($db_name, $primary = true) {
        if (isset($this->properties[$db_name])) {
            $this->properties[$db_name]['primary'] = $primary;
        }
        return $this;
    }
    
    /**
     * Imposta le opzioni per una colonna di tipo select
     */
    public function set_options($db_name, $options) {
        if (isset($this->properties[$db_name])) {
            $this->properties[$db_name]['options'] = $options;
        }
        return $this;
    }
  
    /**
     * Aggiunge un singolo attributo HTML al titolo di una colonna
     * 
     * @param string $db_name Il nome della colonna
     * @param string $attr_name Il nome dell'attributo
     * @param string $attr_value Il valore dell'attributo
     * @return $this
     */
    public function set_attribute_title($db_name, $attr_name, $attr_value) {
        if (isset($this->properties[$db_name])) {
            if (!isset($this->properties[$db_name]['attributes_title'])) {
                $this->properties[$db_name]['attributes_title'] = [];
            }
            $this->properties[$db_name]['attributes_title'][$attr_name] = $attr_value;
        }
        return $this;
    }

    /**
     * Aggiunge un singolo attributo HTML alle righe di una colonna
     * 
     * @param string $db_name Il nome della colonna
     * @param string $attr_name Il nome dell'attributo
     * @param string $attr_value Il valore dell'attributo
     * @return $this
     */
    public function set_attribute_data($db_name, $attr_name, $attr_value) {
        if (isset($this->properties[$db_name])) {
            if (!isset($this->properties[$db_name]['attributes_data'])) {
                $this->properties[$db_name]['attributes_data'] = [];
            }
            $this->properties[$db_name]['attributes_data'][$attr_name] = $attr_value;
        }
        return $this;
    }
    
    /**
     * Ottiene gli attributi HTML del titolo di una colonna
     * 
     * @param string $db_name Il nome della colonna
     * @return array|null Gli attributi della colonna o null se la colonna non esiste
     */
    public function get_attributes_title($db_name) {
        return $this->properties[$db_name]['attributes_title'] ?? [];
    }
    
    /**
     * Ottiene gli attributi HTML di una colonna
     * 
     * @param string $db_name Il nome della colonna
     * @return array|null Gli attributi della colonna o null se la colonna non esiste
     */
    public function get_attributes_data($db_name) {
        return $this->properties[$db_name]['attributes_data'] ?? [];
    }
    

    /**
     * Disabilita l'ordinamento per tutte le colonne
     */
    public function disable_all_order() {
        foreach ($this->properties as $key => $value) {
            $this->properties[$key]['order'] = false;
        }
        return $this;
    }
    
    /**
     * Abilita l'ordinamento per tutte le colonne
     */
    public function enable_all_order() {
        foreach ($this->properties as $key => $value) {
            $this->properties[$key]['order'] = true;
        }
        return $this;
    }
    
    /**
     * Converte la struttura in un array
     */
    public function to_array() {
        return $this->properties;
    }
    
    /**
     * Applica una funzione di callback a ogni elemento della struttura NON DEI DATI!
     * Può servire ad esempio per modificare le etichette delle colonne
     * Simula il comportamento di array_map ma per l'oggetto ListStructure
     * 
     * @param callable $callback La funzione da applicare a ogni elemento
     * @return $this Ritorna l'istanza corrente per permettere il method chaining
     */
    public function map(callable $callback) {
        foreach ($this->properties as $key => $value) {
            $this->properties[$key] = $callback($value);
        }
        return $this;
    }
}
