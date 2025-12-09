<?php
namespace Builders;

use App\Form;
use App\ExtensionLoader;
use App\Abstracts\Traits\ExtensionManagementTrait;

!defined('MILK_DIR') && die(); // Prevents direct access

class SearchBuilder {
    use ExtensionManagementTrait;

    private $table_id;
    private $fields = [];
    private $auto_execute = true;
    private $form_classes = '';
    private $container_classes = '';
    private $wrapper_class = 'd-flex align-items-center gap-2 flex-wrap'; // classe wrapper per layout inline
    private $label_position = 'inline'; // 'inline' | 'none' | 'before'
    private $search_mode = 'onchange'; // 'onchange' or 'submit'
    private $show_search_buttons = false; // se mostrare automaticamente i pulsanti search/clear in modalità submit

    /**
     * List of extension names to load for this builder
     * Format: ['ExtensionName' => ['param1' => 'value1', 'param2' => 'value2']]
     * or simple: ['ExtensionName'] (will be normalized to ['ExtensionName' => []])
     * @var array
     */
    protected array $extensions = [];

    /**
     * Loaded extension instances
     * @var array
     */
    protected array $loaded_extensions = [];

    public function __construct($table_id) {
        $this->table_id = $table_id;

        // Normalize extensions to associative format
        $this->extensions = $this->normalizeExtensions($this->extensions);

        // Load extensions
        $this->loadExtensions();

        // Call configure hook on all extensions
        ExtensionLoader::callHook($this->loaded_extensions, 'configure', [$this]);
    }
    
    /**
     * Adds a search input field
     * 
     * @param string $filter_type Filter type (search, status, action, etc.)
     * @param string $label Label for the field
     * @param array $options Additional options for the input field
     * @return self
     */
    public function addSearch($filter_type = 'search', $label = 'Search', $options = []): self {
        /*
           $this->search_html = \App\Form::input('text', 'search', $label, '', [
            'placeholder' => $placeholder,
            'floating' => false, 
            'class' => 'js-milk-filter-onchange',
            'data-filter-id' => $filter_id,
            'data-filter-type' => 'search',
            'label-attrs-class' => 'p-0 pt-2 me-2'
        ], true);
        */
        $this->fields[] = [
            'type' => 'search',
            'filter_type' => $filter_type,
            'label' => $label,
            'class' => 'js-milk-filter-onchange',
            'options' => array_merge([
                'data-filter-id' => $this->table_id,
                'data-filter-type' => $filter_type,
                'placeholder' => $label . '...',
                'floating' => false
            ], $options)
        ];
        return $this;
    }
    
    /**
     * Adds a select dropdown filter
     * 
     * @param string $filter_type Filter type identifier
     * @param string $label Label for the select
     * @param array $select_options Options for the select
     * @param string $selected Currently selected value
     * @param array $options Additional options
     * @return self
     */
    public function addSelect($filter_type, $label, $select_options, $selected = '', $options = []): self {
        $this->fields[] = [
            'type' => 'select',
            'filter_type' => $filter_type,
            'label' => $label,
            'select_options' => $select_options,
            'selected' => $selected,
            'options' => array_merge([
                'floating' => false
            ], $options)
        ];
        return $this;
    }
    
    /**
     * Adds an action list filter
     * 
     * @param string $filter_type Filter type identifier
     * @param string $label Label for the action list
     * @param array $list_options Options for the action list
     * @param string $selected Currently selected value
     * @param array $options Additional options for container
     * @param array $input_options Additional options for hidden input
     * @return self
     */
    public function addActionList($filter_type, $label, $list_options, $selected = '', $options = [], $input_options = []): self {
        $this->fields[] = [
            'type' => 'action_list',
            'filter_type' => $filter_type,
            'label' => $label,
            'list_options' => $list_options,
            'selected' => $selected,
            'options' => $options,
            'input_options' => $input_options
        ];
        return $this;
    }
    
    /**
     * Adds a generic input field
     * 
     * @param string $input_type HTML input type (text, email, number, etc.)
     * @param string $filter_type Filter type identifier
     * @param string $label Label for the field
     * @param string $value Default value
     * @param array $options Additional options
     * @return self
     */
    public function addInput($input_type, $filter_type, $label, $value = '', $options = []): self {
        $this->fields[] = [
            'type' => 'input',
            'input_type' => $input_type,
            'filter_type' => $filter_type,
            'label' => $label,
            'value' => $value,
            'options' => array_merge([
                'floating' => false
            ], $options)
        ];
        return $this;
    }
    
    /**
     * Adds a search button for manual execution
     * 
     * @param string $label Button label
     * @param array $options Additional options
     * @return self
     */
    public function addSearchButton($label = 'Search', $options = []): self {
        $this->auto_execute = false;
        $this->fields[] = [
            'type' => 'search_button',
            'label' => $label,
            'options' => array_merge([
                'class' => 'btn btn-primary'
            ], $options)
        ];
        return $this;
    }
    
    /**
     * Sets whether filters should execute automatically on change
     * 
     * @param bool $auto_execute
     * @return self
     */
    public function setAutoExecute($auto_execute = true): self {
        $this->auto_execute = $auto_execute;
        return $this;
    }
    
    /**
     * Sets additional CSS classes for form elements
     * 
     * @param string $classes CSS classes
     * @return self
     */
    public function setFormClasses($classes): self {
        $this->form_classes = $classes;
        return $this;
    }
    
    /**
     * Sets additional CSS classes for the container
     * 
     * @param string $classes CSS classes
     * @return self
     */
    public function setContainerClasses($classes): self {
        $this->container_classes = $classes;
        return $this;
    }
    
    /**
     * Sets the wrapper class for inline layout
     *
     * @param string $class CSS classes for the wrapper (default: 'd-flex align-items-center gap-2 flex-wrap')
     * @return self
     */
    public function setWrapperClass($class = 'd-flex align-items-center gap-2 flex-wrap'): self {
        $this->wrapper_class = $class;
        return $this;
    }

    /**
     * Sets the label position for fields
     *
     * @param string $position 'inline' for label inside field, 'none' for no label, 'before' for label before field
     * @return self
     */
    public function setLabelPosition($position = 'inline'): self {
        $this->label_position = in_array($position, ['inline', 'none', 'before']) ? $position : 'inline';
        return $this;
    }
    
    /**
     * Sets the search mode (onchange or submit)
     * 
     * @param string $mode 'onchange' for automatic search, 'submit' for manual search with buttons
     * @param bool $auto_buttons If true in submit mode, automatically adds Search and Clear buttons
     * @return self
     */
    public function setSearchMode($mode = 'onchange', $auto_buttons = true): self {
        $this->search_mode = in_array($mode, ['onchange', 'submit']) ? $mode : 'onchange';
        $this->auto_execute = ($this->search_mode === 'onchange');
        $this->show_search_buttons = ($this->search_mode === 'submit' && $auto_buttons);
        return $this;
    }
    
    /**
     * Adds Search and Clear buttons (useful in submit mode)
     * 
     * @param string $search_label Label for search button
     * @param string $clear_label Label for clear button  
     * @return self
     */
    public function addSearchButtons($search_label = 'Search', $clear_label = 'Clear'): self {
        $this->addSearchButton($search_label);
        $this->addClearButton($clear_label);
        return $this;
    }
    
    /**
     * Adds a clear button that resets all filters
     * 
     * @param string $label Button label
     * @param array $options Additional options
     * @return self
     */
    public function addClearButton($label = 'Clear', $options = []): self {
        $this->fields[] = [
            'type' => 'clear_button',
            'label' => $label,
            'options' => array_merge([
                'class' => 'btn btn-secondary'
            ], $options)
        ];
        return $this;
    }
    
    /**
     * Renders the complete search form
     * 
     * @param array $container_options Options for the main container
     * @param bool $return If true, returns HTML instead of echoing
     * @return string|void
     */
    public function render($container_options = [], $return = false) {
        if (empty($this->fields)) {
            return $return ? '' : null;
        }
        
        $container_class = 'search-builder-container';
        if ($this->container_classes) {
            $container_class .= ' ' . $this->container_classes;
        }
        if (isset($container_options['class'])) {
            $container_class .= ' ' . $container_options['class'];
            unset($container_options['class']);
        }
        
        $html = '<div class="' . $container_class . '"';
        
        // Add other container attributes
        foreach ($container_options as $key => $value) {
            $html .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
        }
        
        $html .= '>';
        
        // Aggiungi automaticamente i pulsanti Search/Clear se necessario
        if ($this->show_search_buttons) {
            $this->addSearchButtons();
        }

        $html .= $this->renderFieldsInline();

        $html .= '</div>';
        /*
        // Reinitialize action lists if any were rendered
        $html .= '<script>';
        $html .= 'if (typeof initActionLists === \'function\') { ';
        $html .= '  setTimeout(function() { initActionLists(); }, 10); ';
        $html .= '} else if (typeof setupActionList === \'function\') { ';
        $html .= '  setTimeout(function() { ';
        $html .= '    document.querySelectorAll(\'.search-builder-container .js-action-list\').forEach(function(container) { ';
        $html .= '      if (!container._action_list_initialized) { ';
        $html .= '        setupActionList(container); ';
        $html .= '        container._action_list_initialized = true; ';
        $html .= '      } ';
        $html .= '    }); ';
        $html .= '  }, 10); ';
        $html .= '}';
        $html .= '</script>';
        */
        if ($return) {
            return $html;
        } else {
            echo $html;
        }
    }
    
    /**
     * Renders fields in inline layout
     *
     * @return string
     */
    private function renderFieldsInline() {
        $html = '<div class="' . $this->wrapper_class . '">';

        foreach ($this->fields as $field) {
            $html .= $this->renderField($field);
        }

        $html .= '</div>';
        return $html;
    }
    
    /**
     * Renders individual search fields in inline layout
     *
     * @param array $field Field configuration
     * @return string
     */
    private function renderField($field) {
        $filter_class = $this->auto_execute ? 'js-milk-filter-onchange' : 'js-milk-filter';

        switch ($field['type']) {
            case 'search':
                return $this->renderSearchField($field, $filter_class);

            case 'select':
                return $this->renderSelectField($field, $filter_class);

            case 'action_list':
                return $this->renderActionListField($field, $filter_class);

            case 'input':
                return $this->renderInputField($field, $filter_class);

            case 'search_button':
                return $this->renderSearchButton($field);

            case 'clear_button':
                return $this->renderClearButton($field);

            default:
                return '';
        }
    }

    /**
     * Renders a search input field with inline buttons
     *
     * @param array $field Field configuration
     * @param string $filter_class CSS filter class
     * @return string
     */
    private function renderSearchField($field, $filter_class) {
        $options = $this->prepareFieldOptions($field, $filter_class);
        $options['placeholder'] = $field['options']['placeholder'] ?? $field['label'];

        // Remove floating label for inline layout
        $options['floating'] = false;

        $id = $options['id'];
        $html = '<div class="input-group d-inline-flex" style="width: auto; vertical-align: middle;">';
        $html .= '<input class="js-milk-filter-onchange form-control search-builder-search" type="search"';
        $html .= ' name="search"';
        $html .= ' id="' . htmlspecialchars($id) . '"';
        $html .= ' placeholder="' . htmlspecialchars($options['placeholder']) . '"';
        $html .= ' data-filter-id="' . htmlspecialchars($this->table_id) . '"';
        $html .= ' data-filter-type="' . htmlspecialchars($field['filter_type']) . '"';
        $html .= ' class="' . htmlspecialchars($options['class']) . '"';
        $html .= '>';

        // Add clear button
        $html .= '<button class="btn btn-outline-secondary js-milk-clear-search" type="button" data-id="' . _r($id) . '">';
        $html .= '<i class="bi bi-x-lg"></i>';
        $html .= '</button>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Renders a select field for inline layout
     *
     * @param array $field Field configuration
     * @param string $filter_class CSS filter class
     * @return string
     */
    private function renderSelectField($field, $filter_class) {
        $options = $this->prepareFieldOptions($field, $filter_class);
        $options['floating'] = false;

        $html = '';

        // Add label before if needed
        if ($this->label_position === 'before' && !empty($field['label'])) {
            $html .= '<span class="text-body-secondary me-2">' . htmlspecialchars($field['label']) . ':</span>';
        }

        $html .= '<div class="d-inline-block" style="width: auto;">';
        $html .= Form::select('filter_' . $field['filter_type'], '', $field['select_options'], $field['selected'], $options, true);
        $html .= '</div>';

        return $html;
    }

    /**
     * Renders an action list as inline span links using the standard action-list system
     *
     * @param array $field Field configuration
     * @param string $filter_class CSS filter class
     * @return string
     */
    private function renderActionListField($field, $filter_class) {
        $input_options = $this->prepareActionListInputOptions($field, $filter_class);
        $input_id = $this->table_id . 'SearchForm' . ucfirst($field['filter_type']);

        $html = '';

        // Add label if provided
        if (!empty($field['label'])) {
            $html .= '<label for="' . htmlspecialchars($input_id) . '" class="text-body-secondary me-2">' . htmlspecialchars($field['label']) . '</label>';
        }

        // Hidden input for the filter value (BEFORE the container as per Form::actionList)
        $html .= '<input type="hidden"';
        $html .= ' id="' . htmlspecialchars($input_id) . '"';
        $html .= ' name="filter_' . htmlspecialchars($field['filter_type']) . '"';
        $html .= ' value="' . htmlspecialchars($field['selected']) . '"';
        $html .= ' data-filter-id="' . htmlspecialchars($this->table_id) . '"';
        $html .= ' data-filter-type="' . htmlspecialchars($field['filter_type']) . '"';
        $html .= ' class="' . htmlspecialchars($input_options['class']) . '"';
        $html .= '>';

        // Action list container with standard js-action-list class
        $html .= '<div class="js-action-list action-list-container d-inline-flex align-items-center gap-2" data-target-input="' . htmlspecialchars($input_id) . '">';

        // Render action items with standard js-action-item class
        foreach ($field['list_options'] as $value => $label) {
            $isActive = ($value == $field['selected']);
            $html .= '<span class="link-action js-action-item';
            if ($isActive) {
                $html .= ' active-action-list';
            }
            $html .= '" data-value="' . htmlspecialchars($value) . '">';
            $html .= htmlspecialchars($label);
            $html .= '</span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Renders a generic input field for inline layout
     *
     * @param array $field Field configuration
     * @param string $filter_class CSS filter class
     * @return string
     */
    private function renderInputField($field, $filter_class) {
        $options = $this->prepareFieldOptions($field, $filter_class);
        $options['floating'] = false;

        $html = '';

        // Add label before if needed
        if ($this->label_position === 'before' && !empty($field['label'])) {
            $html .= '<span class="text-body-secondary me-2">' . htmlspecialchars($field['label']) . ':</span>';
        }

        $html .= '<div class="d-inline-block" style="width: auto;">';
        $html .= Form::input($field['input_type'], 'filter_' . $field['filter_type'], '', $field['value'], $options, true);
        $html .= '</div>';

        return $html;
    }

    /**
     * Renders a search button
     *
     * @param array $field Field configuration
     * @return string
     */
    private function renderSearchButton($field) {
        $options = array_merge($field['options'], [
            'class' => $field['options']['class'] . ' js-milk-filter-onclick',
            'data-filter-id' => $this->table_id
        ]);
        return '<button type="button"' . Form::attr($options) . '>' . htmlspecialchars($field['label']) . '</button>';
    }

    /**
     * Renders a clear button
     *
     * @param array $field Field configuration
     * @return string
     */
    private function renderClearButton($field) {
        $options = array_merge($field['options'], [
            'class' => $field['options']['class'] . ' js-milk-filter-clear',
            'data-filter-id' => $this->table_id
        ]);
        return '<button type="button"' . Form::attr($options) . '>' . htmlspecialchars($field['label']) . '</button>';
    }
    /**
     * Prepares field options with required data attributes
     * 
     * @param array $field Field configuration
     * @param string $filter_class CSS class for filter behavior
     * @return array
     */
    private function prepareFieldOptions($field, $filter_class) {
        $options = $field['options'] ?? [];
        
        // Generate unique ID based on table ID and filter type
        $options['id'] = $this->table_id . 'SearchForm' . ucfirst($field['filter_type']);
        
        // Add required data attributes
        $options['data-filter-id'] = $this->table_id;
        $options['data-filter-type'] = $field['filter_type'];
        
        // Add filter class
        if (isset($options['class'])) {
            $options['class'] .= ' ' . $filter_class;
        } else {
            $options['class'] = $filter_class;
        }
        
        // Add form classes if set
        if ($this->form_classes) {
            $options['class'] .= ' ' . $this->form_classes;
        }
        
        return $options;
    }
    
    /**
     * Prepares action list options with required data attributes
     *
     * @param array $field Field configuration
     * @return array
     */
    private function prepareActionListOptions($field) {
        $options = $field['options'] ?? [];

        // Generate unique ID based on table ID and filter type for action lists too
        if (!isset($options['id'])) {
            $options['id'] = $this->table_id . 'SearchForm' . ucfirst($field['filter_type']);
        }

        return $options;
    }
    
    /**
     * Prepares action list input options with required data attributes
     * 
     * @param array $field Field configuration
     * @param string $filter_class CSS class for filter behavior
     * @return array
     */
    private function prepareActionListInputOptions($field, $filter_class) {
        $input_options = $field['input_options'] ?? [];
        
        // Add required data attributes to the hidden input
        $input_options['data-filter-id'] = $this->table_id;
        $input_options['data-filter-type'] = $field['filter_type'];
        
        // Add filter class to the hidden input
        if (isset($input_options['class'])) {
            $input_options['class'] .= ' ' . $filter_class;
        } else {
            $input_options['class'] = $filter_class;
        }
        
        return $input_options;
    }
    
    /**
     * Static factory method
     *
     * @param string $table_id Table ID for filter connection
     * @return self
     */
    public static function create($table_id): self {
        return new self($table_id);
    }

    /**
     * Load extensions defined in $this->extensions array
     *
     * @return void
     * @throws \Exception If extension is not found
     */
    protected function loadExtensions(): void
    {
        if (empty($this->extensions)) {
            return;
        }

        $this->loaded_extensions = ExtensionLoader::load($this->extensions, 'SearchBuilder', $this);
    }

    /**
     * Set extensions to load for this builder (method chaining)
     *
     * @param array $extensions Extensions array
     * @return self For method chaining
     *
     * @example ->extensions(['SoftDelete' => ['auto_filter' => true]])
     * @example ->extensions(['SoftDelete'])
     */
    public function extensions(array $extensions): self
    {
        // Normalize and merge with existing extensions
        $normalized = $this->normalizeExtensions($extensions);
        $this->extensions = $this->mergeExtensions($this->extensions, $normalized);

        // Reload extensions
        $this->loadExtensions();

        // Call configure hook on newly loaded extensions
        ExtensionLoader::callHook($this->loaded_extensions, 'configure', [$this]);

        return $this;
    }

    /**
     * Get loaded extension by name
     *
     * @param string $extension_name Extension name
     * @return object|null Extension instance or null if not found
     */
    public function getLoadedExtension(string $extension_name): ?object
    {
        return $this->loaded_extensions[$extension_name] ?? null;
    }
}