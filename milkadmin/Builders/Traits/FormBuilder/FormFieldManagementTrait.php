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
    public function removeField(string $name): self {
        unset($this->fields[$name]);
        return $this;
    }

    /**
     * Set conditional visibility for a field
     *
     * Makes a field visible only when another field has a specific value.
     * When the condition is not met, the field is automatically hidden.
     *
     * @param string $field_name Field name to apply conditional visibility to
     * @param string $toggle_field Field name to watch for changes
     * @param string $toggle_value Value that will make the field visible
     * @return self For method chaining
     *
     * @example
     * // Show 'activation_date' only when 'status' field equals 'active'
     * ->showFieldWhen('activation_date', 'status', 'active')
     *
     * @example
     * // Show 'shipping_address' only when 'has_shipping' checkbox is checked
     * ->showFieldWhen('shipping_address', 'has_shipping', '1')
     *
     * @example
     * // Multiple fields with same condition
     * ->showFieldWhen('field1', 'status', 'active')
     * ->showFieldWhen('field2', 'status', 'active')
     * ->showFieldWhen('field3', 'status', 'inactive')
     */
    public function showFieldWhen(string $field_name, string $toggle_field, string $toggle_value): self {
        if (isset($this->fields[$field_name])) {
            if (!isset($this->fields[$field_name]['form-params'])) {
                $this->fields[$field_name]['form-params'] = [];
            }
            $this->fields[$field_name]['form-params']['toggle-field'] = $toggle_field;
            $this->fields[$field_name]['form-params']['toggle-value'] = $toggle_value;
        }
        return $this;
    }

    /**
     * Set conditional visibility for multiple fields with the same condition
     *
     * Makes multiple fields visible only when another field has a specific value.
     * This is a convenience method to avoid repeating the same toggle condition.
     *
     * @param array $field_names Array of field names to apply conditional visibility to
     * @param string $toggle_field Field name to watch for changes
     * @param string $toggle_value Value that will make the fields visible
     * @return self For method chaining
     *
     * @example
     * // Show multiple fields only when 'status' equals 'active'
     * ->showFieldsWhen(['activation_date', 'activated_by', 'notes'], 'status', 'active')
     *
     * @example
     * // Show company-specific fields only when 'user_type' equals 'company'
     * ->showFieldsWhen(['company_name', 'vat_number', 'registration_number'], 'user_type', 'company')
     */
    public function showFieldsWhen(array $field_names, string $toggle_field, string $toggle_value): self {
        foreach ($field_names as $field_name) {
            $this->showFieldWhen($field_name, $toggle_field, $toggle_value);
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
            unset($this->fields[$field_name]['form-params']['toggle-field']);
            unset($this->fields[$field_name]['form-params']['toggle-value']);
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
                            $values[$key] = $rule["value"];
                        }
                    }
                }
                $value = $values[$key] ?? '';
                if (isset( $this->fields[$key]['set_value'])) {
                    $value =  $this->fields[$key]['set_value'];
                    $object->$key = $value;
                }
                $this->fields[$key] = array_merge($rule, [
                    'name' => $key,
                    'value' => $value, // il valore di default del campo
                    'row_value' =>  $object->$key ?? '', // il valore del record
                    'record_object' => $object // l'oggetto completo del record per accedere ai relationships
                ]);
            }
        }

        return $this;
    }

    /**
     * Add a single field
     *
     * @param string $field_name Field name
     * @param string $type Field type
     * @param array $options Field options
     * @param string $position_before Field position before another field
     * @return self For method chaining
     *
     * @example ->addField('name', 'string', ['label' => 'Name'])
     */
    public function addField($field_name, $type, $options = [], $position_before = ''): self {
        $newFields = [];
        $newField = array_merge($options, ['type' => $type, 'name' => $field_name]);

        foreach ($this->fields as $name => $field) {
            if ($name === $position_before) {
                // Inserisci il nuovo campo prima di quello indicato
                $newFields[$field_name] = $newField;
            }
            $newFields[$name] = $field;
        }

        // Se $position_before non è stato trovato, aggiungi il nuovo campo in fondo
        if (!array_key_exists($field_name, $newFields)) {
            $newFields[$field_name] = $newField;
        }

        $this->fields = $newFields;

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

        $ordered = [];
        // Add fields in specified order
        foreach ($this->field_order as $name) {
            if (isset($this->fields[$name])) {
                $ordered[$name] = $this->fields[$name];
            }
        }

        // Add any remaining fields not in order
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
     * @param string $position_before Position before which to add the HTML
     * @return self For method chaining
     *
     * @example ->addHtml('<div class="alert alert-info">Please fill all required fields</div>', 'before_fields')
     */
    public function addHtml($html, $position_before = ''): self {
         $field_name = "H1";
         $count_field_name = 1;
        while (isset($this->fields[$field_name])) {
            $field_name = "H" . str_pad($count_field_name, 3, '0', STR_PAD_LEFT);
            $count_field_name++;
        }
        $newFields = [];
        $newField = ['type' => 'html', 'value' => '', 'html' => $html, 'name' => $field_name];

        foreach ($this->fields as $name => $field) {
            if ($name === $position_before) {
                // Inserisci il nuovo campo prima di quello indicato
                $newFields[$field_name] = $newField;
            }
            $newFields[$name] = $field;
        }

        // Se $position_before non è stato trovato, aggiungi il nuovo campo in fondo
        if (!array_key_exists($field_name, $newFields)) {
            $newFields[$field_name] = $newField;
        }

        $this->fields = $newFields;

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
                    return ['success' => true, 'message' => 'Operation cancelled', 'cancelled' => true];
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

