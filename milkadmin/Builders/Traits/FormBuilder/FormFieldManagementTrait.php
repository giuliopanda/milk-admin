<?php
namespace Builders\Traits\FormBuilder;

/**
 * Trait FormFieldManagementTrait
 *
 * Handles field management, configuration, and UI operations for FormBuilder
 * Including adding, removing, ordering, conditional visibility, actions, and HTML customization
 *
 * @package Builders\Traits
 */
trait FormFieldManagementTrait {

    /**
     * Remove a field from the form
     *
     * @param string $name Field name to remove
     * @return self For method chaining
     *
     * @example ->removeField('created_at')
     */
    public function removeField(?string $name = null): self {
        if ($name == null) {
            $name = $this->requireCurrentField('removeField');
        }
        if (isset($this->fields[$name])) {
            $this->fields_copy[$name] = $this->fields[$name];
        }
        unset($this->fields[$name]);
        // Track removed fields to prevent re-adding them in addFieldsFromObject()
        $this->removed_fields[$name] = true;
        return $this;
    }


    public function hide(): self {
        $field_name = $this->requireCurrentField('hide');
        $this->fields[$field_name]['form-type'] = 'hidden';
        return $this;
    }


    /**
     * Set conditional visibility for a field or container using a milk expression
     *
     * @param string $field_or_expression Field/container id, or expression when using current field
     * @param string|null $expression Milk expression that evaluates to true/false (optional with current field)
     * @return self For method chaining
     */
    public function showIf(string $field_or_expression, ?string $expression = null): self {
        if ($expression === null) {
            $expression = $field_or_expression;
            $field_name = $this->requireCurrentField('showIf');
        } else {
            $field_name = $field_or_expression;
        }

        if (isset($this->fields[$field_name])) {
            if (!isset($this->fields[$field_name]['form-params'])) {
                $this->fields[$field_name]['form-params'] = [];
            }
            $this->fields[$field_name]['form-params']['data-milk-show'] = $expression;
        }
        return $this;
    }

    /**
     * Remove conditional visibility from a field
     *
     * Removes any toggle-field and toggle-value settings from a field,
     * making it always visible.
     *
     * @param string $field_name Field name to remove conditional visibility from
     * @return self For method chaining
     *
     * @example ->removeFieldCondition('activation_date')
     */
    public function removeFieldCondition(string $field_name): self {
        if (isset($this->fields[$field_name]['form-params'])) {
            unset($this->fields[$field_name]['form-params']['data-milk-show']);
        }
        return $this;
    }

    /**
     * Set field order
     *
     * @param array $order Array of field names in desired order
     * @return self For method chaining
     *
     * @example ->fieldOrder(['id', 'title', 'content', 'category'])
     */
    public function fieldOrder(array $order): self {
        $this->field_order = $order;
        return $this;
    }

    /**
     * Add fields from object definition
     * The form data are set here by FormBuilder->setModel
     *
     * @param object $object Object with getRules method (data object, not model)
     * @param string $context Context to filter fields (e.g., 'edit', 'create')
     * @param array $values Current values for fields
     * @return self For method chaining
     *
     * @example ->addFieldsFromObject($data, 'edit', $_POST)
     */
    public function addFieldsFromObject(object $object, string $context = 'edit', array $values = []): self {
        if (method_exists($object, 'getRules')) {
            foreach ($object->getRules($context, true) as $key => $rule) {
                 if (isset($rule["form-type"])) {
                    if ($rule["form-type"] === "checkbox") {
                        if ($rule["type"] === "bool") {
                            $values[$key] = 1;
                        } else {
                            // Use checkbox_checked if defined, otherwise fallback to default or '1'
                            $values[$key] = $rule["checkbox_checked"] ?? $rule["default"] ?? '1';
                        }
                    }
                }
                $value = $values[$key] ?? '';
                // Skip fields that have been explicitly removed
                if (isset($this->removed_fields[$key]) && !isset( $this->fields[$key])) {
                    if (isset( $this->fields_copy[$key]['set_value'])) {
                        $value =  $this->fields_copy[$key]['set_value'];
                        $object->$key = $value;
                    }
                    $merged = array_merge($rule, $this->fields_copy[$key] ?? []);
                    // Set required defaults only if not present
                    $merged['value'] = $value;
                    $merged['row_value'] = $object->$key ?? '';
                    if (!isset($merged['name'])) $merged['name'] = $key;
                    // Always update record_object with current object to ensure relationships are accessible
                    $merged['record_object'] = $object;
                    $merged = $this->applyMilkFormAttributes($merged);
                    $this->fields_copy[$key] = $merged;
                 
                    continue;
                }

                
                if (isset( $this->fields[$key]['set_value'])) {
                    $value =  $this->fields[$key]['set_value'];
                    $object->$key = $value;
                }

                // Merge: start with Model rule, then apply FormBuilder changes (FormBuilder has priority)
                $merged = array_merge($rule, $this->fields[$key] ?? []);

                // Set required defaults only if not present
                $merged['value'] = $value;
                $merged['row_value'] = $object->$key ?? '';
                if (!isset($merged['name'])) $merged['name'] = $key;
                // Always update record_object with current object to ensure relationships are accessible
                $merged['record_object'] = $object;
                $merged = $this->applyMilkFormAttributes($merged);

                $this->fields[$key] = $merged;
                $this->fields_copy[$key] = $merged;
            }
        }

        // Handle custom fields that don't exist in Model (no record_object set)
        foreach ($this->fields as $key => $field) {
            if (!isset($field['record_object'])) {
                // Custom field not in Model, set defaults
                $this->fields[$key] = array_merge([
                    'name' => $key,
                    'type' => '',
                    'value' => '',
                    'row_value' => ''
                ], $field);
                $this->fields[$key] = $this->applyMilkFormAttributes($this->fields[$key]);
                $this->fields_copy[$key] = $this->fields[$key];
            }
        }
     
        return $this;
    }

    /**
     * Map model expression rules to MilkForm data-* attributes.
     *
     * @param array $field
     * @return array
     */
    protected function applyMilkFormAttributes(array $field): array
    {
        $form_params = $field['form-params'] ?? [];

        if (isset($field['calc_expr']) && (!isset($form_params['data-milk-expr']) || $form_params['data-milk-expr'] === '')) {
            $form_params['data-milk-expr'] = $field['calc_expr'];
        }
        if (isset($field['validate_expr']) && (!isset($form_params['data-milk-validate-expr']) || $form_params['data-milk-validate-expr'] === '')) {
            $form_params['data-milk-validate-expr'] = $field['validate_expr'];
        }
        if (isset($field['required_expr']) && (!isset($form_params['data-milk-required-if']) || $form_params['data-milk-required-if'] === '')) {
            $form_params['data-milk-required-if'] = $field['required_expr'];
        }
        if (isset($field['precision']) && (!isset($form_params['data-milk-precision']) || $form_params['data-milk-precision'] === '')) {
            $form_params['data-milk-precision'] = $field['precision'];
        }
        if (isset($form_params['invalid-feedback']) && (!isset($form_params['data-milk-message']) || $form_params['data-milk-message'] === '')) {
            $form_params['data-milk-message'] = $form_params['invalid-feedback'];
        }

        $field['form-params'] = $form_params;
        return $field;
    }

    /**
     * Add a single field
     *
     * @param string $field_name Field name
     * @param string $type Field type
     * @param array $options Field options
     * @return self For method chaining
     *
     * @example ->addField('name', 'string', ['label' => 'Name'])
     * @example ->addField('name', 'string', ['label' => 'Name'])->before('email')
     */
    public function addField($field_name, $type, $options = []): self {
        $newField = array_merge($options, ['type' => $type, 'name' => $field_name]);
        $this->current_field = $field_name;
        if (!isset($this->fields[$field_name])) {
            $this->fields[$field_name] = $newField;
        }
        // Preserve existing fields_copy; only add/update the single field.
        $this->fields_copy[$field_name] = array_merge($this->fields_copy[$field_name] ?? [], $newField);
        if (isset($this->removed_fields[$field_name])) {
            unset($this->removed_fields[$field_name]);
        }
      
        return $this;
    }

    /**
     * Modify an existing field
     *
     * Allows you to update properties of an existing field by merging
     * the provided options with the current field configuration.
     * Optionally repositions the field before another specified field.
     *
     * @param string $field_name Field name to modify
     * @param array $options Field options to merge with existing ones
     * @param string $position_before Optional field name to position this field before
     * @return self For method chaining
     *
     * @example ->modifyField('email', ['label' => 'Email Address', 'required' => true])
     * @example ->modifyField('status', ['form-type' => 'select', 'options' => ['active', 'inactive']])
     * @example ->modifyField('description', ['label' => 'Description'], 'category')
     */
    public function modifyField(string $field_name, array $options, string $position_before = ''): self {
        if (isset($this->fields[$field_name])) {
            // Merge options with existing field
            $this->fields[$field_name] = array_merge($this->fields[$field_name], $options);

            // Reposition field if position_before is specified
            if ($position_before !== '') {
                $modifiedField = $this->fields[$field_name];
                $newFields = [];

                foreach ($this->fields as $name => $field) {
                    // Skip the field being modified in its original position
                    if ($name === $field_name) {
                        continue;
                    }

                    // Insert modified field before the specified position
                    if ($name === $position_before) {
                        $newFields[$field_name] = $modifiedField;
                    }

                    $newFields[$name] = $field;
                }

                // If position_before not found, add modified field at the end
                if (!array_key_exists($field_name, $newFields)) {
                    $newFields[$field_name] = $modifiedField;
                }

                $this->fields = $newFields;
            }
            $this->fields_copy[$field_name] = $this->fields[$field_name];
        }
        return $this;
    }


    /**
     * Get fields in the correct order
     *
     * @return array Ordered fields
     */
    private function getOrderedFields(): array {
        if (empty($this->field_order)) {
            return $this->fields;
        }

        // First pass: identify all fields inside containers
        $containerized_fields = [];
        foreach ($this->fields as $name => $field) {
            if (isset($field['form-params']['in-container']) &&
                $field['form-params']['in-container'] === true &&
                !str_starts_with($name, 'HCNT')) {
                $containerized_fields[$name] = true;
            }
        }

        // Remove containerized fields from field_order to prevent breaking container structure
        $effective_field_order = array_filter($this->field_order, function($name) use ($containerized_fields) {
            return !isset($containerized_fields[$name]);
        });

        $ordered = [];
        // Add fields in specified order (excluding containerized ones)
        foreach ($effective_field_order as $name) {
            if (isset($this->fields[$name])) {
                $ordered[$name] = $this->fields[$name];
            }
        }

        // Add any remaining fields not in order (including container structures)
        foreach ($this->fields as $name => $field) {
            if (!isset($ordered[$name])) {
                $ordered[$name] = $field;
            }
        }

        return $ordered;
    }

    /**
     * Add custom HTML content
     *
     * @param string $html Custom HTML content
     * @param string $position Position in form ('before_fields', 'after_fields', 'before_submit')
     * @return self For method chaining
     *
     * @example ->addHtml('<div class="alert alert-info">Please fill all required fields</div>', 'before_fields')
     */
    public function addHtmlBeforeFields(string $html): self {
        if (!isset($this->custom_html['before_fields'])) {
            $this->custom_html['before_fields'] = [];
        }
        $this->custom_html['before_fields'][] = $html;
        return $this;
    }

    public function addHtmlAfterFields(string $html): self {
        if (!isset($this->custom_html['after_fields'])) {
            $this->custom_html['after_fields'] = [];
        }
        $this->custom_html['after_fields'][] = $html;
        return $this;
    }

    public function addHtmlBeforeSubmit(string $html): self {
        if (!isset($this->custom_html['before_submit'])) {
            $this->custom_html['before_submit'] = [];
        }
        $this->custom_html['before_submit'][] = $html;
        return $this;
    }

    /**
     * Add custom HTML content
     *
     * @param string $html Custom HTML content
     * @param string $field_name Optional field name for the HTML block
     * @return self For method chaining
     *
     * @example ->addHtml('<div class="alert alert-info">Please fill all required fields</div>', 'custom_html')
     * @example ->addHtml('<div class="alert alert-info">Please fill all required fields</div>')->before('email')
     */
    public function addHtml($html, $field_name = ''): self {
        if ($field_name === '') {
            $field_name = "H1";
            $count_field_name = 1;
            while (isset($this->fields[$field_name])) {
                $field_name = "H" . str_pad($count_field_name, 3, '0', STR_PAD_LEFT);
                $count_field_name++;
            }
        } else {
            $base_name = $field_name;
            $suffix = 1;
            while (isset($this->fields[$field_name])) {
                $field_name = $base_name . '_' . $suffix;
                $suffix++;
            }
        }

        $this->fields[$field_name] = [
            'type' => 'html',
            'value' => '',
            'html' => $html,
            'name' => $field_name,
        ];
        $this->current_field = $field_name;

        return $this;
    }
        

    /**
     * Set form actions (buttons)
     *
     * Replaces all existing actions with the provided ones
     *
     * @param array $actions Array of actions configuration 
     * [label, type, class, action, validate, confirm, onclick, target, showIf]
     * action: callback function(FormBuilder $form_builder, array $request): array
     * 
     * @return self For method chaining
     *
     * @example ->setActions([
     *   'save' => ['label' => 'Save Changes', 'class' => 'btn btn-primary', 'action' => [$this, 'saveCallback']],
     *   'cancel' => ['label' => 'Cancel', 'class' => 'btn btn-secondary', 'type' => 'link', 'link' => '?page=list']
     * ])
     */
    public function setActions(array $actions): self {
        $this->actions = [];
        return $this->addActions($actions);
    }

    /**
     * Add form actions (buttons)
     *
     * Adds actions to existing ones without replacing
     * [label, type, class, action, validate, confirm, onclick, target, showIf]
     * action: callback function(FormBuilder $form_builder, array $request): array
     * 
     * @param array $actions Array of actions configuration
     * @return self For method chaining
     *
     * @example ->addActions([
     *   'custom' => ['label' => 'Custom Action', 'class' => 'btn btn-info', 'action' => [$this, 'customCallback']],
     *   'delete' => ['label' => 'Delete', 'class' => 'btn btn-danger', 'showIf' => ['id', 'not_empty', 0]]
     * ])
     */
    public function addActions(array $actions): self {
        foreach ($actions as $key => $action_data) {
            if (is_array($action_data)) {
                $action_config = [
                    'label' => $action_data['label'] ?? ucfirst($key),
                    'type' => $action_data['type'] ?? 'submit',
                    'class' => $action_data['class'] ?? 'btn btn-primary',
                    'validate' => $action_data['validate'] ?? true,
                    'attributes' => $action_data['attributes'] ?? []
                ];

                // Handle different action types
                if (isset($action_data['link'])) {
                    $action_config['link'] = $action_data['link'];
                    $action_config['type'] = 'link';
                }

                // Add optional attributes
                $optional_attrs = ['confirm', 'onclick', 'target'];
                foreach ($optional_attrs as $attr) {
                    if (isset($action_data[$attr])) {
                        $action_config[$attr] = $action_data[$attr];
                    }
                }

                $this->actions[$key] = $action_config;

                // Store callback function if provided
                if (isset($action_data['action']) && is_callable($action_data['action'])) {
                    $this->actions[$key]['callback'] = $action_data['action'];
                }

                // Store showIf parameters if provided
                if (isset($action_data['showIf']) && is_array($action_data['showIf'])) {
                    $this->actions[$key]['showIf'] = $action_data['showIf'];
                }
            }
        }

        return $this;
    }

    /**
     * Quick helper to add standard actions (save, delete, cancel)
     *
     * @param bool $include_delete Whether to include delete button
     * @param string $cancel_link Cancel link URL
     * @return self For method chaining
     *
     * @example ->addStandardActions(true, '?page=list')
     */
    public function addStandardActions(bool $include_delete = false, ?string $cancel_link = null): self {
        $cancel_link = $cancel_link ?? '?page=' . $this->page;
        $actions = [
            'save' => [
                'label' => 'Save',
                'class' => 'btn btn-primary',
                'action' => self::saveAction()
            ]
        ];

        // Se only_json = true, il cancel deve essere un submit button che ritorna risultati JSON
        if ($this->only_json) {
            $actions['cancel'] = [
                'label' => 'Cancel',
                'type' => 'submit',
                'class' => 'btn btn-secondary ms-2',
                'validate' => false,
                'action' => function($form_builder, $request) {
                    return ['success' => true, 'message' => '', 'cancelled' => true];
                }
            ];
        } else {
            // Altrimenti è un link normale
            $actions['cancel'] = [
                'label' => 'Cancel',
                'type' => 'link',
                'class' => 'btn btn-secondary ms-2',
                'link' => $cancel_link
            ];
        }

        if ($include_delete) {
            // Get primary key name from model
            $primary_key = $this->model ? $this->model->getPrimaryKey() : 'id';

            $actions['delete'] = [
                'label' => 'Delete',
                'class' => 'btn btn-danger ms-2',
                'action' => self::deleteAction(),
                'validate' => false,
                'confirm' => 'Are you sure you want to delete this item?',
                // Show delete button only if primary key is not empty (editing existing record)
                'showIf' => [$primary_key, 'not_empty', 0]
            ];
        }

        return $this->setActions($actions);
    }

    /**
     * Initialize default form actions (save and cancel buttons)
     */
    private function initializeDefaultActions(): void {
        
        $this->actions = [
            'save' => [
                'label' => 'Save',
                'type' => 'submit',
                'class' => 'btn btn-primary',
                'callback' => $this->saveAction()
            ],
            'cancel' => [
                'label' => 'Cancel',
                'type' => 'link',
                'class' => 'btn btn-secondary ms-2',
                'link' => '?page=' . $this->page
            ]
        ];
    }
}
