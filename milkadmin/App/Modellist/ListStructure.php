<?php
namespace App\Modellist;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * The ListStructure class manages the column structure of a table.
 * Implements ArrayAccess to access properties as arrays.
 * 
 * @package     App
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
        return $key !== null ? ($this->properties[$key] ?? null) : null;
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
    public function setColumn($db_name, $label, $type = 'text', $order = true, $primary = false, $options = [], $attributes_title = [], $attributes_data = []) {
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

    public function setAction($options = [], $label = 'Action') {
        // Rimuovi 'action' se esiste già (per spostarlo alla fine)
        if (isset($this->properties['action'])) {
            unset($this->properties['action']);
        }

        // Ri-aggiungi 'action' alla fine
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
    public function getColumn($db_name) {
        return $this->properties[$db_name] ?? null;
    }

    public function hideColumns($db_names) {
        if (is_array($db_names)) {
            foreach ($db_names as $db_name) {
                $this->hideColumn($db_name);
            }
        } else {
            $this->hideColumn($db_names);
        }
        return $this;
    }

    public function hideColumn($db_name) {
        if (isset($this->properties[$db_name])) {
            $this->properties[$db_name]['type'] = 'hidden';
        }
        return $this;
    }

    public function deleteColumns($db_names) { 
        if (is_array($db_names)) {
            foreach ($db_names as $db_name) {
                $this->deleteColumn($db_name);
            }
        } else {
            $this->deleteColumn($db_names);
        }
        return $this;
    }

    public function deleteColumn($db_name) {
        if (isset($this->properties[$db_name])) {
            unset($this->properties[$db_name]);
            $this->keys = array_keys($this->properties);
        }
        return $this;
    }
    
    /**
     * Imposta l'etichetta di una colonna
     */
    public function setLabel($db_name, $label) {
        if (isset($this->properties[$db_name])) {
            $this->properties[$db_name]['label'] = $label;
        }
        return $this;
    }

    public function reorderColumns($db_names) {
        if (!is_array($db_names)) {
            $db_names = [$db_names];
        }
        $new_properties = $this->properties;
        $this->properties = [];
       
        foreach ($db_names as $db_name) {
            if (isset($new_properties[$db_name])) {
                $this->properties[$db_name] = $new_properties[$db_name];
            }
        }
        // aggiungo le colonne non presenti
        foreach ($new_properties as $db_name => $property) {
            if (!isset($this->properties[$db_name])) {
                $this->properties[$db_name] = $property;
            }
        }
        $this->keys = array_keys($this->properties);
        return $this;
    }
    
    /**
     * Imposta il tipo di una colonna
     */
    public function setType($db_name, $type) {
        if (isset($this->properties[$db_name])) {
            $this->properties[$db_name]['type'] = $type;
        }
        return $this;
    }
    
    /**
     * Imposta se la colonna è ordinabile
     */
    public function setOrder($db_name, $orderable) {
        if (isset($this->properties[$db_name])) {
            $this->properties[$db_name]['order'] = (bool)$orderable;
        }
        return $this;
    }
    
    /**
     * Imposta una colonna come chiave primaria
     */
    public function setPrimary($db_name, $primary = true) {
        if (isset($this->properties[$db_name])) {
            $this->properties[$db_name]['primary'] = $primary;
        }
        return $this;
    }
    
    /**
     * Imposta le opzioni per una colonna di tipo select
     */
    public function setOptions($db_name, $options) {
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
    public function setAttributeTitle($db_name, $attr_name, $attr_value) {
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
    public function setAttributeData($db_name, $attr_name, $attr_value) {
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
    public function getAttributesTitle($db_name) {
        return $this->properties[$db_name]['attributes_title'] ?? [];
    }
    
    /**
     * Ottiene gli attributi HTML di una colonna
     * 
     * @param string $db_name Il nome della colonna
     * @return array|null Gli attributi della colonna o null se la colonna non esiste
     */
    public function getAttributesData($db_name) {
        return $this->properties[$db_name]['attributes_data'] ?? [];
    }

    /**
     * Disabilita l'ordinamento per tutte le colonne
     */
    public function disableAllOrder() {
        foreach ($this->properties as $key => $value) {
            $this->properties[$key]['order'] = false;
        }
        return $this;
    }
    
    /**
     * Abilita l'ordinamento per tutte le colonne
     */
    public function enableAllOrder() {
        foreach ($this->properties as $key => $value) {
            $this->properties[$key]['order'] = true;
        }
        return $this;
    }
    
    /**
     * Converte la struttura in un array
     */
    public function toArray() {
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
