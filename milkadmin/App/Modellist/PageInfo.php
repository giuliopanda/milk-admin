<?php
namespace App\Modellist;

!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * La classe PageInfo gestisce le informazioni di pagina per la tabella
 * Implementa ArrayAccess per poter accedere alle proprietà come array
 * 
 * @package     App
 * @subpackage  ModelList
 */

class PageInfo implements \ArrayAccess, \Iterator, \Countable {
    private $properties = [
        'page' => '',
        'action' => '',
        'id' => '',
        'limit' => 10,
        'default_limit' => 10,
        'limit_start' => 0,
        'order_field' => '',
        'order_dir' => 'desc',
        'total_record' => 0,
        'filters' => '',
        'footer' => false,
        'ajax' => true,
        'pagination' => true,
        'json' => false,
        'auto-scroll' => true,
        'pag-total-show' => true,
        'pag-number-show' => true,
        'pag-goto-show' => true,
        'pag-elperpage-show' => true,
        'pagination-limit' => 14,
        'bulk_actions' => []
    ];
    
    private $position = 0;
    private $keys = [];
    
    /**
     * Costruttore che può accettare un array di configurazione iniziale
     */
    public function __construct(array $config = []) {
        $this->properties = array_merge($this->properties, $config);
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
     * Metodi helper per lavorare con le informazioni di pagina
     */
    
    /**
     * Imposta l'ID della tabella
     */
    public function setId($id) {
        $this->properties['id'] = $id;
        return $this;
    }
    
    /**
     * Imposta la pagina corrente
     */
    public function setPage($page) {
        $this->properties['page'] = $page;
        return $this;
    }
    
    /**
     * Imposta l'azione
     */
    public function setAction($action) {
        $this->properties['action'] = $action;
        return $this;
    }

    /**
     * Imposta la primary key
     */
    public function setPrimaryKey($primaryKey) {
        $this->properties['primary_key'] = $primaryKey;
        return $this;
    }
    
    /**
     * Imposta il limite di righe per pagina
     */
    public function setLimit($limit) {
        $this->properties['limit'] = (int)$limit;
        return $this;
    }

    public function setDefaultLimit($limit) {
        $this->properties['default_limit'] = (int)$limit;
        return $this;
    }
    
    /**
     * Imposta l'indice di partenza per il limite
     */
    public function setLimitStart($limitStart) {
        $this->properties['limit_start'] = (int)$limitStart;
        return $this;
    }
    
    /**
     * Imposta il campo di ordinamento
     */
    public function setOrderField($field) {
        $this->properties['order_field'] = $field;
        return $this;
    }
    
    /**
     * Imposta la direzione di ordinamento
     */
    public function setOrderDir($dir) {
        $this->properties['order_dir'] = $dir;
        return $this;
    }
    
    /**
     * Imposta il numero totale di record
     */
    public function setTotalRecord($total) {
        $this->properties['total_record'] = (int)$total;
        return $this;
    }
    
    /**
     * Imposta i filtri
     */
    public function setFilters($filters) {
        $this->properties['filters'] = $filters;
        return $this;
    }
    
    /**
     * Abilita/disabilita il footer
     */
    public function setFooter($enabled) {
        $this->properties['footer'] = (bool)$enabled;
        return $this;
    }
    
    /**
     * Abilita/disabilita ajax
     */
    public function setAjax($enabled) {
        $this->properties['ajax'] = (bool)$enabled;
        return $this;
    }
    
    /**
     * Abilita/disabilita la paginazione
     */
    public function setPagination($enabled) {
        $this->properties['pagination'] = (bool)$enabled;
        return $this;
    }
    
    /**
     * Imposta le azioni bulk
     */
    public function setBulkActions($actions) {
       
        $this->properties['bulk_actions'] = $actions;
        return $this;
    }
    
    /**
     * Aggiunge un'azione bulk
     */
    public function addBulkAction($key, $label) {
        $this->properties['bulk_actions'][$key] = $label;
        return $this;
    }
    
    /**
     * Abilita/disabilita lo scrolling automatico
     */
    public function setAutoScroll($enabled) {
        $this->properties['auto-scroll'] = (bool)$enabled;
        return $this;
    }
    
    /**
     * Abilita/disabilita la visualizzazione del totale nella paginazione
     */
    public function setPagTotalShow($enabled) {
        $this->properties['pag-total-show'] = (bool)$enabled;
        return $this;
    }
    
    /**
     * Abilita/disabilita la visualizzazione dei numeri di pagina nella paginazione
     */
    public function setPagNumberShow($enabled) {
        $this->properties['pag-number-show'] = (bool)$enabled;
        return $this;
    }
    
    /**
     * Abilita/disabilita la visualizzazione del selettore "vai alla pagina" nella paginazione
     */
    public function setPagGotoShow($enabled) {
        $this->properties['pag-goto-show'] = (bool)$enabled;
        return $this;
    }
    
    /**
     * Abilita/disabilita la visualizzazione del selettore "elementi per pagina" nella paginazione
     */
    public function setPagElPerPageShow($enabled) {
        $this->properties['pag-elperpage-show'] = (bool)$enabled;
        return $this;
    }
    
    /**
     * Imposta il limite di pagine da mostrare nella paginazione
     */
    public function setPaginationLimit($limit) {
        $this->properties['pagination-limit'] = (int)$limit;
        return $this;
    }
    
    public function setInputHidden($html) {
        $this->properties['form_html_input_hidden'] = $html;
        return $this;
    }

    /**
     * @param string $key example: 'form', 'table', 'thead', 'tbody', 'tr', 'td.id', 'td.action', 'th.checkbox'
     * @param array $attrs array of attributes to set example: ['class' => 'card-body-overflow js-table-form']
     */
    public function setTableAttrs($key, $attrs) {
        $this->properties['table_attrs'][$key] = $attrs;
        return $this;
    }
        
    public function setJson($enabled) {
        $this->properties['json'] = (bool)$enabled;
        return $this;
    }   
    
    /**
     * Converte le informazioni di pagina in un array
     */
    public function toArray() {
        return $this->properties;
    }
}