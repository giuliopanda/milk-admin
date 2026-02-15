<?php
namespace Builders;

use App\{Get,};
use \Builders\Exceptions\BuilderException;
use App\Config;

!defined('MILK_DIR') && die(); // Prevents direct access


/**
 * ViewTableBuilder - Gestione visualizzazione tabella
 *
 * Estende TableBuilder e fornisce i metodi di output (HTML/response)
 * senza introdurre logica di costruzione query o preparazione dati.
 */
class TableBuilder extends GetDataBuilder
{

    protected $table_id = '';
    protected $footer_data = [];
    protected $table_attrs = [];
    protected ?bool $pagination_override = null;
    protected bool $show_header = true;
   

    /**
     * Get HTML table string
     *
     * @return string Complete HTML table ready for display
     */
    public function render(): string {

        $data = $this->getData();

        if ($this->hasError() && Config::get('debug', false) === true) {
             return $this->getErrorAlertHtml($this->customErrorMessage);
        }
        if ($data['rows'] instanceof \App\Abstracts\AbstractModel) {
            $data['rows']->with();
        }

        $this->applyPaginationVisibilityRules($data['page_info']);

        $tableHtml = Get::themePlugin('table', [
            'info' => $data['info'],
            'rows' => $data['rows'],
            'page_info' => $data['page_info'],
            'table_attrs' => $this->table_attrs,
            'show_header' => $this->show_header
        ]);

        return $tableHtml;
    }

    /**
     * Set custom error message for production mode
     *
     * @param string $message Custom message to display when an error occurs and debug is disabled
     * @return static For method chaining
     *
     * @example ->setErrorMessage('Errore durante il caricamento delle aule. Verificare i permessi.')
     */
    public function setErrorMessage(string $message): static {
        $this->customErrorMessage = $message;
        return $this;
    }

    /**
     * Force pagination visibility for this table.
     * When set, automatic pagination visibility rules are skipped.
     */
    public function setPagination(bool $enabled): static {
        $this->pagination_override = $enabled;
        return $this;
    }

    /**
     * Show or hide table header rendering.
     *
     * @param bool $show Show header when true, skip rendering when false
     * @return static For method chaining
     *
     * @example ->setShowHeader(false)
     */
    public function setShowHeader(bool $show): static {
        $this->show_header = $show;
        return $this;
    }

    /**
     * Reduce font size for all table texts (rows, header, bulk row, pagination).
     *
     * Applies the `table-text-small` class to the table container.
     *
     * @param bool $enabled Enable compact text mode
     * @return static For method chaining
     *
     * @example ->setSmallText(true)
     */
    public function setSmallText(bool $enabled = true): static {
        $this->toggleClassOnAttrElement('container', 'table-text-small', $enabled);
        return $this;
    }

    // Method chaining per configurazione tabella (snake_case)
    public function setTableAttrs(array $attrs): static {
        $this->table_attrs = $attrs;
        return $this;
    }

    public function addTableAttr($element, $key, $value): static {
        $this->table_attrs[$element][$key] = $value;
        return $this;
    }

    // Enhanced class management methods with js- class protection

    public function tableClass($classes): static {
        // Preserva sempre le classi essenziali per funzionalità
        $essential_classes = ['js-table'];
        
        // Aggiungi 'table' se non presente nelle classi fornite
        if (strpos($classes, 'table') === false && strpos($classes, 'table-') === false) {
            $essential_classes[] = 'table';
        }
        
        // Combina classi essenziali con quelle fornite
        $full_classes = implode(' ', $essential_classes) . ' ' . $classes;
        $this->table_attrs['table']['class'] = trim($full_classes);
        return $this;
    }

    public function rowClass($classes, $condition = null): static {
        if ($condition === null) {
            // Per le righe, aggiungi sempre js-table-tr per preservare la funzionalità JavaScript
            $classes_with_js = 'js-table-tr ' . $classes;
            $this->table_attrs['tr']['class'] = $classes_with_js;
        } else {
            // Aggiungi condizione per righe specifiche
            $this->row_conditions[] = [
                'type' => 'condition',
                'classes' => $classes,
                'condition' => $condition
            ];
        }
        return $this;
    }

    public function rowClassAlternate($odd_classes, $even_classes = null): static {
        $this->row_conditions[] = [
            'type' => 'alternate',
            'odd_classes' => $odd_classes,
            'even_classes' => $even_classes ?? ''
        ];
        return $this;
    }

    public function columnClass($column_name, $classes): static {
        $key = 'td.' . str_replace(' ', '_', $column_name);
        
        // Preserva classi js- specifiche per alcune colonne
        $js_class = '';
        if ($column_name === 'id' || $column_name === 'checkbox') {
            $js_class = 'js-td-checkbox ';
        }
        
        $this->table_attrs[$key]['class'] = $js_class . $classes;
        return $this;
    }

    public function columnClassAlternate($column_name, $odd_classes, $even_classes = null): static {
        $this->column_conditions[] = [
            'type' => 'alternate',
            'column' => $column_name,
            'odd_classes' => $odd_classes,
            'even_classes' => $even_classes ?? ''
        ];
        return $this;
    }

    public function rowClassByValue($field, $value, $classes, $comparison = '=='): static {
        $this->row_conditions[] = [
            'type' => 'value',
            'field' => $field,
            'value' => $value,
            'classes' => $classes,
            'comparison' => $comparison
        ];
        return $this;
    }

    public function cellClassByValue($column_name, $field, $value, $classes, $comparison = '=='): static {
        $this->column_conditions[] = [
            'type' => 'value',
            'column' => $column_name,
            'field' => $field,
            'value' => $value,
            'classes' => $classes,
            'comparison' => $comparison
        ];
        return $this;
    }

    public function headerClass($classes): static {
        $this->table_attrs['thead']['class'] = $classes;
        return $this;
    }

    public function bodyClass($classes): static {
        $this->table_attrs['tbody']['class'] = $classes;
        return $this;
    }

    public function headerColumnClass($column_name, $classes): static {
        $key = 'th.' . str_replace(' ', '_', $column_name);
        $this->table_attrs[$key]['class'] = $classes;
        return $this;
    }

    public function cellClass($row_index, $column_name, $classes): static {
        // Gestisce classi per singole celle specifiche
        $this->column_conditions[] = [
            'type' => 'specific_cell',
            'column' => $column_name,
            'row_index' => $row_index, // 1-indexed
            'classes' => $classes
        ];
        return $this;
    }

    public function footerClass($classes): static {
        $this->table_attrs['tfoot']['class'] = $classes;
        return $this;
    }

    public function tableColor($color): static {
        // Mappa i colori semplici alle classi Bootstrap
        $color_map = [
            // Colori base
            'primary' => 'table-primary',
            'secondary' => 'table-secondary', 
            'success' => 'table-success',
            'danger' => 'table-danger',
            'warning' => 'table-warning',
            'info' => 'table-info',
            'light' => 'table-light',
            'dark' => 'table-dark',
            
            // Aliases più semplici
            'blue' => 'table-primary',
            'gray' => 'table-secondary',
            'grey' => 'table-secondary',
            'green' => 'table-success',
            'red' => 'table-danger',
            'yellow' => 'table-warning',
            'cyan' => 'table-info',
            'white' => 'table-light',
            'black' => 'table-dark',
            
            // Colori striped
            'striped' => 'table-striped',
            'striped-primary' => 'table-striped table-primary',
            'striped-success' => 'table-striped table-success',
            'striped-danger' => 'table-striped table-danger',
            'striped-warning' => 'table-striped table-warning',
            'striped-info' => 'table-striped table-info',
            'striped-dark' => 'table-striped table-dark',
            
            // Combinazioni speciali
            'bordered' => 'table-bordered',
            'hover' => 'table-hover',
            'small' => 'table-sm'
        ];
        
        // Se il colore esiste nella mappa, usa le classi Bootstrap
        if (isset($color_map[$color])) {
            $bootstrap_classes = $color_map[$color];
        } else {
            // Se non esiste, prova a costruire la classe assumendo sia un colore Bootstrap valido
            $bootstrap_classes = 'table-' . $color;
        }
        
        // Per i colori (non utility come hover, bordered), aggiungi sempre striped e coordina header
        $utility_colors = ['striped', 'bordered', 'hover', 'small'];
        $is_color = !in_array($color, $utility_colors);
        
        if ($is_color) {
            // Aggiungi striped per i colori e imposta header coordinato
            $bootstrap_classes = 'table-striped ' . $bootstrap_classes;
            $this->headerColor($color);
        }
        
        // Aggiungi sempre la classe base 'table' di Bootstrap
        $full_classes = 'table ' . $bootstrap_classes;
        
        // Applica le classi mantenendo js-table
        $this->tableClass($full_classes);
        
        // Se non sono già state impostate altre configurazioni, applica quelle di default
        if (!isset($this->table_attrs['table']['class']) || strpos($this->table_attrs['table']['class'], 'table-row-selected') === false) {
            // Aggiungi classi di default se mancanti
            $current_classes = $this->table_attrs['table']['class'] ?? '';
            if (strpos($current_classes, 'table-hover') === false) {
                $current_classes = trim($current_classes . ' table-hover');
            }
            if (strpos($current_classes, 'table-row-selected') === false) {
                $current_classes = trim($current_classes . ' table-row-selected');
            }
            $this->table_attrs['table']['class'] = $current_classes;
        }
        
        return $this;
    }

    public function headerColor($color): static {
        // Mappa i colori per header e selezione
        $header_color_map = [
            // Colori base
            'primary' => 'table-header-primary',
            'secondary' => 'table-header-secondary', 
            'success' => 'table-header-success',
            'danger' => 'table-header-danger',
            'warning' => 'table-header-warning',
            'info' => 'table-header-info',
            'light' => 'table-header-light',
            'dark' => 'table-header-dark',
            
            // Aliases semplici
            'blue' => 'table-header-primary',
            'gray' => 'table-header-secondary',
            'grey' => 'table-header-secondary',
            'green' => 'table-header-success',
            'red' => 'table-header-danger',
            'yellow' => 'table-header-warning',
            'cyan' => 'table-header-info',
            'white' => 'table-header-light',
            'black' => 'table-header-dark'
        ];
        
        // Se il colore esiste nella mappa, usa la classe custom
        if (isset($header_color_map[$color])) {
            $header_class = $header_color_map[$color];
        } else {
            // Se non esiste, prova a costruire la classe 
            $header_class = 'table-header-' . $color;
        }
        
        // Applica le classi all'header e alla tabella per la selezione
        $this->headerClass($header_class);
        $this->addTableAttr('table', 'data-header-color', $color); // Per CSS targeting
        
        // Se non sono già state impostate classi per la tabella, applica quelle di default
        if (!isset($this->table_attrs['table']['class'])) {
            $this->tableClass('table table-hover table-row-selected');
        }
        
        return $this;
    }

    /**
     * Set footer data for the table
     *
     * @param array $footer_data Array of footer values corresponding to each column
     * @return static For method chaining
     *
     * @example ->setFooter(['', '', 'Total', '99999', ''])
     */
    public function setFooter(array $footer_data): static {
        $this->footer_data = $footer_data;
        return $this;
    }

    // ========================================================================
    // FIELD-FIRST STYLE - TABLE GRAPHIC METHODS
    // ========================================================================

    /**
     * Set CSS class for current field column (Field-first style)
     * Requires field() to be called first
     *
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     *
     * @throws BuilderException if field() was not called first
     * @example ->field('status')->class('text-center fw-bold')
     */
    public function class(string $classes): static {
        if ($this->current_field === null) {
            throw BuilderException::noCurrentField('class');
        }

        $column_name = $this->current_field;
        $key = 'td.' . str_replace(' ', '_', $column_name);

        // Preserva classi js- specifiche per alcune colonne
        $js_class = '';
        if ($column_name === 'id' || $column_name === 'checkbox') {
            $js_class = 'js-td-checkbox ';
        }

        $this->table_attrs[$key]['class'] = $js_class . $classes;
        return $this;
    }

    /**
     * Set CSS class for current field header (Field-first style)
     * Requires field() to be called first
     *
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     *
     * @throws BuilderException if field() was not called first
     * @example ->field('price')->colHeaderClass('text-end')
     */
    public function colHeaderClass(string $classes): static {
        if ($this->current_field === null) {
            throw BuilderException::noCurrentField('colHeaderClass');
        }

        $column_name = $this->current_field;
        $key = 'th.' . str_replace(' ', '_', $column_name);
        $this->table_attrs[$key]['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS class based on field value for cells (Field-first style)
     * Requires field() to be called first
     *
     * @param mixed $value Value to compare
     * @param string $classes CSS classes to apply when condition matches
     * @param string $comparison Comparison operator (==, !=, >, <, >=, <=, contains)
     * @return static For method chaining
     *
     * @throws BuilderException if field() was not called first
     * @example ->field('status')->cellClassValue('active', 'bg-success text-white')
     */
    public function cellClassValue($value, string $classes, string $comparison = '=='): static {
        if ($this->current_field === null) {
            throw BuilderException::noCurrentField('cellClassValue');
        }

        $column_name = $this->current_field;
        $this->column_conditions[] = [
            'type' => 'value',
            'column' => $column_name,
            'field' => $column_name, // Controlla lo stesso campo
            'value' => $value,
            'classes' => $classes,
            'comparison' => $comparison
        ];
        return $this;
    }

    /**
     * Set CSS class for cells based on another field's value (Field-first style)
     * Requires field() to be called first
     *
     * @param string $check_field Field to check for value
     * @param mixed $value Value to compare
     * @param string $classes CSS classes to apply when condition matches
     * @param string $comparison Comparison operator (==, !=, >, <, >=, <=, contains)
     * @return static For method chaining
     *
     * @throws BuilderException if field() was not called first
     * @example ->field('price')->cellClassOtherValue('status', 'discount', 'text-success fw-bold')
     */
    public function cellClassOtherValue(string $check_field, $value, string $classes, string $comparison = '=='): static {
        if ($this->current_field === null) {
            throw BuilderException::noCurrentField('cellClassOtherValue');
        }

        $column_name = $this->current_field;
        $this->column_conditions[] = [
            'type' => 'value',
            'column' => $column_name,
            'field' => $check_field,
            'value' => $value,
            'classes' => $classes,
            'comparison' => $comparison
        ];
        return $this;
    }

    /**
     * Alternate CSS class for column cells between odd and even rows (Field-first style)
     * Requires field() to be called first
     *
     * @param string $odd_classes CSS classes for odd rows
     * @param string|null $even_classes CSS classes for even rows
     * @return static For method chaining
     *
     * @throws BuilderException if field() was not called first
     * @example ->field('price')->classAlternate('bg-light', 'bg-white')
     */
    public function classAlternate(string $odd_classes, ?string $even_classes = null): static {
        if ($this->current_field === null) {
            throw BuilderException::noCurrentField('classAlternate');
        }

        $column_name = $this->current_field;
        $this->column_conditions[] = [
            'type' => 'alternate',
            'column' => $column_name,
            'odd_classes' => $odd_classes,
            'even_classes' => $even_classes ?? ''
        ];
        return $this;
    }

    /**
     * Add or remove a CSS class from a table attribute element without clobbering existing classes.
     */
    private function toggleClassOnAttrElement(string $element, string $class_name, bool $enabled): void
    {
        $current = trim((string) ($this->table_attrs[$element]['class'] ?? ''));
        $classes = $current === '' ? [] : preg_split('/\s+/', $current);
        $classes = array_values(array_filter(is_array($classes) ? $classes : [], static function ($value) {
            return is_string($value) && $value !== '';
        }));

        if ($enabled) {
            if (!in_array($class_name, $classes, true)) {
                $classes[] = $class_name;
            }
        } else {
            $classes = array_values(array_filter($classes, static function ($value) use ($class_name) {
                return $value !== $class_name;
            }));
        }

        if (empty($classes)) {
            unset($this->table_attrs[$element]['class']);
            return;
        }

        $this->table_attrs[$element]['class'] = implode(' ', $classes);
    }

    /**
     * Apply default pagination visibility rules unless explicitly overridden.
     */
    private function applyPaginationVisibilityRules($page_info): void
    {
        if (!(is_array($page_info) || $page_info instanceof \ArrayAccess)) {
            return;
        }

        if ($this->pagination_override !== null) {
            $page_info['pagination'] = $this->pagination_override;
            return;
        }

        if ($this->isLimitExplicitlyProvidedInRequest()) {
            return;
        }

        $limit = (int) ($page_info['limit'] ?? 0);
        $total = (int) ($page_info['total_record'] ?? 0);

        if ($limit < 1 || $total < 0) {
            return;
        }

        $current_page = $this->resolveCurrentPage($page_info, $limit);

        if ($current_page === 1 && $total < $limit) {
            $page_info['pagination'] = false;
        }
    }

    private function isLimitExplicitlyProvidedInRequest(): bool
    {
        $request = $this->getRequest();

        return array_key_exists('limit', $request) && $request['limit'] !== '' && $request['limit'] !== null;
    }

    private function resolveCurrentPage($page_info, int $limit): int
    {
        $limit_start = (int) ($page_info['limit_start'] ?? 0);
        $limit_start = max(0, $limit_start);

        return (int) floor($limit_start / $limit) + 1;
    }

    // ========================================================================
    // END FIELD-FIRST STYLE - TABLE GRAPHIC METHODS
    // ========================================================================
}
