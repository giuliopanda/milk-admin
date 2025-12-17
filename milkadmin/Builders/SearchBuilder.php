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
    private $current_field = null; // chiave del campo corrente in $fields
    private $auto_execute = true;
    private $form_classes = '';
    private $container_classes = '';
    private $wrapper_class = 'd-flex align-items-center gap-2 flex-wrap'; // classe wrapper per layout inline
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
    
    // ==================== CREATOR METHODS ====================

    /**
     * Creates a search input field
     */
    public function search($filter_type = 'search'): self {
        $this->fields[] = [
            'type' => 'search',
            'filter_type' => $filter_type,
            'label' => '',
            'placeholder' => '',
            'class' => '',
            'layout' => 'inline',
            'floating' => false,
            'options' => []
        ];
        $this->current_field = array_key_last($this->fields);
        return $this;
    }

    /**
     * Creates a select dropdown filter
     */
    public function select($filter_type): self {
        $this->fields[] = [
            'type' => 'select',
            'filter_type' => $filter_type,
            'label' => '',
            'class' => '',
            'layout' => 'inline',
            'floating' => false,
            'select_options' => [],
            'selected' => '',
            'options' => []
        ];
        $this->current_field = array_key_last($this->fields);
        return $this;
    }

    /**
     * Creates an action list filter
     */
    public function actionList($filter_type): self {
        $this->fields[] = [
            'type' => 'action_list',
            'filter_type' => $filter_type,
            'label' => '',
            'class' => '',
            'layout' => 'inline',
            'list_options' => [],
            'selected' => '',
            'options' => [],
            'input_options' => []
        ];
        $this->current_field = array_key_last($this->fields);
        return $this;
    }

    /**
     * Creates a generic input field
     */
    public function input($type, $filter_type): self {
        $this->fields[] = [
            'type' => 'input',
            'input_type' => $type,
            'filter_type' => $filter_type,
            'label' => '',
            'placeholder' => '',
            'class' => '',
            'layout' => 'inline',
            'floating' => false,
            'value' => '',
            'options' => []
        ];
        $this->current_field = array_key_last($this->fields);
        return $this;
    }

    /**
     * Creates a search button for manual execution
     */
    public function searchButton(): self {
        $this->auto_execute = false;
        $this->fields[] = [
            'type' => 'search_button',
            'label' => 'Search',
            'class' => 'btn btn-primary',
            'options' => []
        ];
        $this->current_field = array_key_last($this->fields);
        return $this;
    }

    /**
     * Creates a clear button that resets all filters
     */
    public function clearButton(): self {
        $this->fields[] = [
            'type' => 'clear_button',
            'label' => 'Clear',
            'class' => 'btn btn-secondary',
            'options' => []
        ];
        $this->current_field = array_key_last($this->fields);
        return $this;
    }

    // ==================== MODIFIER METHODS ====================

    /**
     * Sets the label for the current field
     */
    public function label(string $label): self {
        if ($this->current_field !== null) {
            $this->fields[$this->current_field]['label'] = $label;
        }
        return $this;
    }

    /**
     * Sets the placeholder for the current field
     */
    public function placeholder(string $placeholder): self {
        if ($this->current_field !== null) {
            $this->fields[$this->current_field]['placeholder'] = $placeholder;
        }
        return $this;
    }

    /**
     * Sets floating label mode for the current field
     */
    public function floating(bool $enabled = true): self {
        if ($this->current_field !== null) {
            $this->fields[$this->current_field]['floating'] = $enabled;
        }
        return $this;
    }

    /**
     * Sets custom CSS class for the current field container
     */
    public function class(string $class): self {
        if ($this->current_field !== null) {
            $this->fields[$this->current_field]['class'] = $class;
        }
        return $this;
    }

    /**
     * Sets layout for the current field (inline|stacked|full-width)
     */
    public function layout(string $layout): self {
        if ($this->current_field !== null && in_array($layout, ['inline', 'stacked', 'full-width'])) {
            $this->fields[$this->current_field]['layout'] = $layout;
        }
        return $this;
    }

    /**
     * Sets options for select or action list
     * @param array $options ['value' => 'label']
     */
    public function options(array $options): self {
        if ($this->current_field !== null) {
            $field_type = $this->fields[$this->current_field]['type'];
            if ($field_type === 'select') {
                $this->fields[$this->current_field]['select_options'] = $options;
            } elseif ($field_type === 'action_list') {
                $this->fields[$this->current_field]['list_options'] = $options;
            }
        }
        return $this;
    }

    /**
     * Sets the default value for input fields
     */
    public function value(mixed $value): self {
        if ($this->current_field !== null) {
            $this->fields[$this->current_field]['value'] = $value;
        }
        return $this;
    }

    /**
     * Sets the selected value for select fields
     */
    public function selected($selected): self {
        if ($this->current_field !== null) {
            $field_type = $this->fields[$this->current_field]['type'];
            if (in_array($field_type, ['select', 'action_list'])) {
                $this->fields[$this->current_field]['selected'] = $selected;
            }
        }
        return $this;
    }
    
    /**
     * Sets whether filters should execute automatically on change
     * 
     * @param bool $auto_execute
     * @return self
     */
    public function setAutoExecute(bool $auto_execute = true): self {
        $this->auto_execute = $auto_execute;
        return $this;
    }
    
    /**
     * Sets additional CSS classes for form elements
     * 
     * @param string $classes CSS classes
     * @return self
     */
    public function setFormClasses(string $classes): self {
        $this->form_classes = $classes;
        return $this;
    }
    
    /**
     * Sets additional CSS classes for the container
     * 
     * @param string $classes CSS classes
     * @return self
     */
    public function setContainerClasses(string $classes): self {
        $this->container_classes = $classes;
        return $this;
    }
    
    /**
     * Sets the wrapper class for inline layout
     *
     * @param string $class CSS classes for the wrapper (default: 'd-flex align-items-center gap-2 flex-wrap')
     * @return self
     */
    public function setWrapperClass(string $class = 'd-flex align-items-center gap-2 flex-wrap'): self {
        $this->wrapper_class = $class;
        return $this;
    }

    /**
     * Sets the search mode (onchange or submit)
     *
     * @param string $mode 'onchange' for automatic search, 'submit' for manual search with buttons
     * @param bool $auto_buttons If true in submit mode, automatically adds Search and Clear buttons
     * @return self
     */
    public function setSearchMode(string $mode = 'onchange', bool $auto_buttons = true): self {
        $this->search_mode = in_array($mode, ['onchange', 'submit']) ? $mode : 'onchange';
        $this->auto_execute = ($this->search_mode === 'onchange');
        $this->show_search_buttons = ($this->search_mode === 'submit' && $auto_buttons);
        return $this;
    }
    
    /**
     * Renders the complete search form
     * 
     * @param array $container_options Options for the main container
     * @return string|void
     */
    public function render(array $container_options = []): string {
        if (empty($this->fields)) {
            return '';
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
            $this->searchButton();
            $this->clearButton();
        }

        $html .= $this->renderFieldsInline();

        $html .= '</div>';
           
        return $html;
    
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
    private function renderField(array $field) {
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
    private function renderSearchField(array $field, string $filter_class): string {
        $options = $this->prepareFieldOptions($field, $filter_class);
        $placeholder = $field['placeholder'] ?: $field['label'];
        $layout = $field['layout'] ?? 'inline';
        $custom_class = $field['class'] ?? '';

        $id = $options['id'];

        // Container classes based on layout
        $container_classes = 'search-field-container';
        if ($custom_class) {
            $container_classes .= ' ' . $custom_class;
        }

        switch ($layout) {
            case 'stacked':
                $container_classes .= ' d-flex flex-column';
                $input_group_classes = 'input-group w-100';
                break;
            case 'full-width':
                $container_classes .= ' d-block w-100';
                $input_group_classes = 'input-group w-100';
                break;
            case 'inline':
            default:
                $container_classes .= ' d-inline-flex align-middle';
                $input_group_classes = 'input-group w-auto';
                break;
        }

        $html = '<div class="' . $container_classes . '">';

        // Label if present
        if (!empty($field['label'])) {
            if ($layout === 'stacked' || $layout === 'full-width') {
                $html .= '<label for="' . htmlspecialchars($id) . '" class="form-label">' . htmlspecialchars($field['label']) . '</label>';
            } else {
                $html .= '<span class="text-body-secondary me-2">' . htmlspecialchars($field['label']) . ':</span>';
            }
        }

        // Input group
        $html .= '<div class="' . $input_group_classes . '">';
        $html .= '<input class="' . htmlspecialchars($options['class']) . ' form-control search-builder-search" type="search"';
        $html .= ' name="search"';
        $html .= ' id="' . htmlspecialchars($id) . '"';
        $html .= ' placeholder="' . htmlspecialchars($placeholder) . '"';
        $html .= ' data-filter-id="' . htmlspecialchars($this->table_id) . '"';
        $html .= ' data-filter-type="' . htmlspecialchars($field['filter_type']) . '"';
        $html .= '>';

        // Add clear button
        $html .= '<button class="btn btn-outline-secondary js-milk-clear-search" type="button" data-id="' . htmlspecialchars($id) . '">';
        $html .= '<i class="bi bi-x-lg"></i>';
        $html .= '</button>';

        $html .= '</div>'; // input-group
        $html .= '</div>'; // container

        return $html;
    }

    /**
     * Renders a select field
     *
     * @param array $field Field configuration
     * @param string $filter_class CSS filter class
     * @return string
     */
    private function renderSelectField(array $field, string $filter_class): string {
        $options = $this->prepareFieldOptions($field, $filter_class);
        $options['floating'] = $field['floating'] ?? false;
        $layout = $field['layout'] ?? 'inline';
        $custom_class = $field['class'] ?? '';

        // Container classes based on layout
        $container_classes = 'select-field-container';
        if ($custom_class) {
            $container_classes .= ' ' . $custom_class;
        }

        switch ($layout) {
            case 'stacked':
                $container_classes .= ' d-flex flex-column';
                break;
            case 'full-width':
                $container_classes .= ' d-block w-100';
                break;
            case 'inline':
            default:
                $container_classes .= ' d-inline-block w-auto';
                break;
        }

        $html = '<div class="' . $container_classes . '">';

        // Label if present
        if (!empty($field['label'])) {
            if ($layout === 'stacked' || $layout === 'full-width') {
                $html .= '<label for="' . htmlspecialchars($options['id']) . '" class="form-label">' . htmlspecialchars($field['label']) . '</label>';
            } else {
                $html .= '<span class="text-body-secondary me-2">' . htmlspecialchars($field['label']) . ':</span>';
            }
        }

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
    private function renderActionListField(array $field, string $filter_class): string {
        $input_options = $this->prepareActionListInputOptions($field, $filter_class);
        $input_id = $this->table_id . 'SearchForm' . ucfirst($field['filter_type']);
        $layout = $field['layout'] ?? 'inline';
        $custom_class = $field['class'] ?? '';

        // Container classes based on layout
        $container_classes = 'action-list-field-container';
        if ($custom_class) {
            $container_classes .= ' ' . $custom_class;
        }

        switch ($layout) {
            case 'stacked':
                $container_classes .= ' d-flex flex-column';
                $action_list_classes = 'js-action-list action-list-container d-flex align-items-center gap-2';
                break;
            case 'full-width':
                $container_classes .= ' d-block w-100';
                $action_list_classes = 'js-action-list action-list-container d-flex align-items-center gap-2 w-100';
                break;
            case 'inline':
            default:
                $container_classes .= ' d-inline-block';
                $action_list_classes = 'js-action-list action-list-container d-inline-flex align-items-center gap-2';
                break;
        }

        $html = '<div class="' . $container_classes . '">';

        // Label if present
        if (!empty($field['label'])) {
            if ($layout === 'stacked' || $layout === 'full-width') {
                $html .= '<label for="' . htmlspecialchars($input_id) . '" class="form-label">' . htmlspecialchars($field['label']) . '</label>';
            } else {
                $html .= '<span class="text-body-secondary me-2">' . htmlspecialchars($field['label']) . ':</span>';
            }
        }

        // Hidden input for the filter value
        $html .= '<input type="hidden"';
        $html .= ' id="' . htmlspecialchars($input_id) . '"';
        $html .= ' name="filter_' . htmlspecialchars($field['filter_type']) . '"';
        $html .= ' value="' . htmlspecialchars($field['selected']) . '"';
        $html .= ' data-filter-id="' . htmlspecialchars($this->table_id) . '"';
        $html .= ' data-filter-type="' . htmlspecialchars($field['filter_type']) . '"';
        $html .= ' class="' . htmlspecialchars($input_options['class']) . '"';
        $html .= '>';

        // Action list container with standard js-action-list class
        $html .= '<div class="' . $action_list_classes . '" data-target-input="' . htmlspecialchars($input_id) . '">';

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

        $html .= '</div>'; // action-list
        $html .= '</div>'; // container

        return $html;
    }

    /**
     * Renders a generic input field
     *
     * @param array $field Field configuration
     * @param string $filter_class CSS filter class
     * @return string
     */
    private function renderInputField(array $field, string $filter_class): string {
        $options = $this->prepareFieldOptions($field, $filter_class);
        $options['floating'] = $field['floating'] ?? false;
        $options['placeholder'] = $field['placeholder'] ?? '';
        $layout = $field['layout'] ?? 'inline';
        $custom_class = $field['class'] ?? '';

        // Container classes based on layout
        $container_classes = 'input-field-container';
        if ($custom_class) {
            $container_classes .= ' ' . $custom_class;
        }

        switch ($layout) {
            case 'stacked':
                $container_classes .= ' d-flex flex-column';
                break;
            case 'full-width':
                $container_classes .= ' d-block w-100';
                break;
            case 'inline':
            default:
                $container_classes .= ' d-inline-block w-auto';
                break;
        }

        $html = '<div class="' . $container_classes . '">';

        // Label if present
        if (!empty($field['label'])) {
            if ($layout === 'stacked' || $layout === 'full-width') {
                $html .= '<label for="' . htmlspecialchars($options['id']) . '" class="form-label">' . htmlspecialchars($field['label']) . '</label>';
            } else {
                $html .= '<span class="text-body-secondary me-2">' . htmlspecialchars($field['label']) . ':</span>';
            }
        }

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
    private function renderSearchButton(array $field): string {
        $class = $field['class'] . ' js-milk-filter-onclick';
        $options = array_merge($field['options'], [
            'class' => $class,
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
    private function renderClearButton(array $field): string {
        $class = $field['class'] . ' js-milk-filter-clear';
        $options = array_merge($field['options'], [
            'class' => $class,
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
    private function prepareFieldOptions(array $field, string $filter_class): array {
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
    private function prepareActionListOptions(array $field): string {
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
    private function prepareActionListInputOptions(array $field, string $filter_class): string {
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
    public static function create(string $table_id): self {
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

      /**
     * Magic method to render calendar when object is cast to string
     *
     * @return string Complete HTML calendar ready for display
     */
    public function __toString(): string {
        return $this->render() ?? '';
    }

}