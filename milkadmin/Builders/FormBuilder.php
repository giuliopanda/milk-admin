<?php
namespace Builders;

use App\{Get, MessagesHandler, ObjectToForm, Route, ExtensionLoader};
use App\Abstracts\Traits\ExtensionManagementTrait;
use Builders\Traits\FormBuilder\{FieldFirstTrait, FormFieldManagementTrait, FormSystemOperationsTrait, FormContainerManagementTrait, FormRelationshipTrait};

!defined('MILK_DIR') && die(); // Prevents direct access

/**
 * FormBuilder - Fluent interface for creating and managing dynamic forms
 *
 * Provides a simplified API that wraps Form and ObjectToForm classes
 * for easier form creation with method chaining.
 *
 * @package Builders
 * @author MilkAdmin
 */
class FormBuilder {
    use FieldFirstTrait;
    use FormFieldManagementTrait;
    use FormSystemOperationsTrait;
    use FormContainerManagementTrait;
    use FormRelationshipTrait;
    use ExtensionManagementTrait;

    private $page = '';
    private $url_success = null;
    private $url_error = null;
    private $current_action = 'edit';
    private $form_attributes = [];
    private $fields = [];
    private $submit_text = 'Save';
    private $submit_attributes = [];
    private $custom_html = [];
    private $field_order = [];
    private $actions = [];
    private $function_results = null;
    private $model = null;
    private $only_json = false;
    private $execute_actions = false;
    private $last_insert_id = 0;
    private $response_type = null;
    private $title_new = null;
    private $title_edit = null;
    private $executed_action = null;
    private $action_success = false;
    private $list_id = null;
    private $dom_id = null;
    private $size = null;
    private $custom_data = [];

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

    protected $model_class;

    /**
     * Constructor - Initialize FormBuilder
     *
     * @param string $url_success URL to redirect to on successful submission. Accept replace url placeholders es: ?page=users&id=%id%
     * @param string $url_error URL to redirect to on error
     */
    public function __construct($model, $page = '', $url_success = null, $url_error = null, $only_json = false) {
        $this->page = ($page != '') ? $page : $_REQUEST['page'] ?? '';
        $this->model_class = $model;
        $this->model = new $model();
        $this->addFieldsFromObject($this->model, 'edit');
        // torna all'elenco
        $this->url_success = (!is_null($url_success)) ? $url_success : '?page=' . $this->page;
        $this->url_error = (!is_null($url_error)) ? $url_error : Route::getQueryString();
        $this->current_action = $_REQUEST['action'] ?? 'edit';
        $this->only_json = $only_json;
        if ($this->only_json === true) {
            $this->url_success = null;
            $this->url_error = null;
        }
        // Initialize default actions
        $this->initializeDefaultActions();
    }

    /**
     * Get the pressed action key
     *
     * @return string The pressed action key
     */
    public function getPressedAction(): string {
        foreach ($this->actions as $action_key => $action_config) {
            if (isset($_POST[$action_key]) || isset($_REQUEST[$action_key])) {
               return $action_key;
            }
        }
        return '';
    }

           
       

    /**
     * Set the page name for action links
     *
     * @param string $page_name Page name to use in action links
     * @return self For method chaining
     *
     * @example ->setPage('users')
     */
    public function setPage(string $page_name): self {
        $this->page = $page_name;
        return $this;
    }

    /**
     * Set the page identifier
     *
     * @param string $page Page identifier
     * @return self For method chaining
     *
     * @example ->page('user_profile')
     */
    public function page(string $page): self {
        $this->page = $page;
        return $this;
    }

    /**
     * Set success redirect URL
     *
     * @param string $url URL to redirect to on successful submission
     * @return self For method chaining
     *
     * @example ->urlSuccess('?page=users&action=list')
     */
    public function urlSuccess(string $url): self {
        $this->url_success = $url;
        return $this;
    }

    /**
     * Set error redirect URL
     *
     * @param string $url URL to redirect to on error
     * @return self For method chaining
     *
     * @example ->urlError('?page=users&action=edit&error=1')
     */
    public function urlError(string $url): self {
        $this->url_error = $url;
        return $this;
    }

    /**
     * Set the current action (detected from request)
     *
     * @param string $current_action Current action being processed
     * @return self For method chaining
     *
     * @example ->currentAction('edit')
     */
    public function currentAction(string $current_action): self {
        $this->current_action = $current_action;
        return $this;
    }

    /**
     * Set form attributes
     *
     * @param array $attributes Form element attributes
     * @return self For method chaining
     *
     * @example ->formAttributes(['class' => 'custom-form', 'enctype' => 'multipart/form-data'])
     */
    public function formAttributes(array $attributes): self {
        $this->form_attributes = array_merge($this->form_attributes, $attributes);
        return $this;
    }

    /**
     * Set form ID
     *
     * @param string $formId Form ID attribute
     * @return self For method chaining
     *
     * @example ->setId('myForm')
     */
    public function setId($formId) {
        $this->form_attributes['id'] = $formId;
        return $this;
    }

    /**
     * Set model for save/delete operations
     *
     * @param object $model Model instance
     * @return self For method chaining
     *
     * @example ->setModel($this->model)
     */
    private function setModel(object $model): self {
       
        $data = Route::getSessionData();
        $id = $model->getPrimaryKey();
        $model->with();

        // Apply FormBuilder field configurations (including custom fields) to Model
        // This must be done BEFORE getByIdForEdit() so custom fields can be populated
      
        $this->applyFieldConfigToModel($model);
        $this->model = $model->getByIdForEdit(_absint($_REQUEST[$id] ?? 0), ($data['data'] ?? []));
       
        $this->addFieldsFromObject($this->model, 'edit');
      
        
        // Convert datetime fields to user timezone for form display
        // This only happens if Config 'use_user_timezone' is true
        $this->model->convertDatesToUserTimezone();
     
       
  
        return $this;
    }

    /**
     * Get model instance
     *
     * @return object|null Model instance
     */
    public function getModel(): ?object {
        return $this->model;
    }

    /**
     * Create a save action callback
     *
     * @param string|null $redirect_success Success redirect URL
     * @param string|null $redirect_error Error redirect URL
     * @return callable Callback function for save action
     *
     * @example 'action' => FormBuilder::saveAction()
     */
    public static function saveAction(): callable {
        return function(FormBuilder $form_builder, array $request): array {
            $redirect_success = $form_builder->url_success;
            $redirect_error = $form_builder->url_error;
            if ($form_builder->save($request)) {
                if ($form_builder->only_json === true) {
                    return ['success' => true, 'message' => _r('Save successful')];
                } else {
                    $redirect_success = Route::replaceUrlPlaceholders($redirect_success, [...$request, 'id' =>  $form_builder->last_insert_id, $form_builder->model->getPrimaryKey() =>  $form_builder->last_insert_id]);
                    if ($redirect_success != '') {
                        Route::redirectSuccess($redirect_success, _r('Save successful'));
                    }
                    return [];
                }
            } 

            if ($form_builder->only_json === true) {
                return ['success' => false, 'message' => _r('Save failed')];
            } else {  
                $redirect_error = Route::replaceUrlPlaceholders($redirect_error, [...$request]);
                if ($redirect_error != '') {
                    Route::redirectError($redirect_error, _r('Save failed'));
                }
                return [];
            }
            return ['success' => false, 'message' => _r('Save failed')];
        };
    }

    /**
     * Create a delete action callback
     *
     * @param string|null $redirect_success Success redirect URL
     * @param string|null $redirect_error Error redirect URL
     * @return callable Callback function for delete action
     *
     * @example 'action' => FormBuilder::deleteAction('?page=list')
     */
    public static function deleteAction(?string $redirect_success = null, ?string $redirect_error = null): callable {
        return function(FormBuilder $form_builder, array $request) use ($redirect_success, $redirect_error): array {
            return $form_builder->delete($request, $redirect_success, $redirect_error);
        };
    }

    /**
     * Create a condition callback that checks if primary key exists and is greater than 0
     *
     * This is useful for showing buttons only when editing existing records
     *
     * @param object $model The model instance to check primary key against
     * @return callable Callback function that returns true if primary key exists and > 0
     *
     * @example 'condition' => FormBuilder::hasExistingRecord($model)
     */
    public static function hasExistingRecord(object $model): callable {
        return function(array $fields) use ($model): bool {
            $primary_key = $model->getPrimaryKey();
            return isset($fields[$primary_key]['row_value']) &&
                   !empty($fields[$primary_key]['row_value']) &&
                   $fields[$primary_key]['row_value'] > 0;
        };
    }

    /**
     * Create a generic condition callback that checks a field value against an operator
     *
     * Supported operators: '=', '!=', '>', '<', '>=', '<=', 'in', 'not_in'
     *
     * @param string $field_name Field name to check
     * @param string $operator Operator to use
     * @param mixed $value Value to compare against (can be array for 'in' and 'not_in')
     * @return callable Callback function that returns true if condition is met
     *
     * @example 'condition' => FormBuilder::fieldCondition('status', '=', 'active')
     * @example 'condition' => FormBuilder::fieldCondition('id', '!=', 0)
     * @example 'condition' => FormBuilder::fieldCondition('type', 'in', ['admin', 'moderator'])
     */
    public static function fieldCondition(string $field_name, string $operator, $value): callable {
        return function(array $fields) use ($field_name, $operator, $value): bool {
            // Get field value (prefer row_value over default value)
            $field_value = null;
            if (isset($fields[$field_name]['row_value'])) {
                $field_value = $fields[$field_name]['row_value'];
            } elseif (isset($fields[$field_name]['value'])) {
                $field_value = $fields[$field_name]['value'];
            }
           
            // If field doesn't exist, condition fails
            if ($field_value === null && !isset($fields[$field_name])) {
                return false;
            }

            // Evaluate condition based on operator
            switch ($operator) {
                case 'empty':
                   return ($field_value === null ||   $field_value === '' || $field_value === 0 || is_null($field_value) )  ;
                case 'not_empty':
                    return ($field_value !== null &&   $field_value !== '' && $field_value !== 0 && !is_null($field_value) )  ;
                case '=':
                case '==':
                    return $field_value == $value;

                case '!=':
                case '<>':
                    return $field_value != $value;

                case '>':
                    return $field_value > $value;

                case '<':
                    return $field_value < $value;

                case '>=':
                    return $field_value >= $value;

                case '<=':
                    return $field_value <= $value;

                case 'in':
                    return is_array($value) && in_array($field_value, $value);

                case 'not_in':
                    return is_array($value) && !in_array($field_value, $value);

                default:
                    // Unknown operator, condition fails
                    return false;
            }
        };
    }

    /**
     * Set submit button text and attributes (deprecated - use setActions instead)
     *
     * @param string $text Button text
     * @param array $attributes Button attributes
     * @return self For method chaining
     *
     * @example ->submit('Update Profile', ['class' => 'btn btn-success'])
     */
    public function submit(string $text = 'Save', array $attributes = []): self {
        $this->submit_text = $text;
        $this->submit_attributes = $attributes;
        return $this;
    }

    /**
     * Get HTML (alias for render)
     *
     * @return string Complete HTML form
     */
    public function getHtml(): string {
        return $this->render();
    }

    /**
     * Get form HTML for passing to view functions
     *
     * This method is designed to be used in module edit functions
     * to generate form HTML that can be passed to view templates.
     *
     * @return string Complete HTML form
     *
     * @example
     * // In module edit function:
     * $form_builder = FormBuilder::create($this->model)->getForm();
     * Response::render('Assets/view.php', ['form' => $form_builder->getForm()], 'default');
     */
    public function getForm(): string {
        return $this->render();
    }

    /**
     * Factory method to create FormBuilder instance
     *
     * @param object $model Model instance
     * @param string $page Page identifier
     * @param ?string $url_success Success redirect URL or false to return json
     * @param ?string $url_error Error redirect URL
     * @return self New FormBuilder instance
     *
     * @example FormBuilder::create($this->model, $this->page, '?page=mymodule', '?page=mymodule&action=edit&id=%id%')
     */
    public static function create($model, $page = '', $url_success_or_json = null, $url_error = null): self {
        if ($url_success_or_json === false) {
            $only_json = true;
            $url_success = null;
        } else {
            $only_json = false;
            $url_success = $url_success_or_json;
        }
        return new self($model, $page, $url_success, $url_error, $only_json);
    }

    /**
     * set Url
     */
    public function url($url_success_or_json = null, $url_error = null) {
        $this->url_success =  $url_success_or_json;
        $this->url_error = $url_error;
    }

    /**
     * Active fetch mode
     * 
     */
    public function activeFetch() {
        $this->only_json = true;
        $this->url_success = null;
        $this->url_error = null;
        if (isset($this->actions['cancel']['type']) && $this->actions['cancel']['type'] == 'link') {
            unset($this->actions['cancel']['link']);
            $this->actions['cancel']['type'] = 'submit';
                
        }
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
        return $this;
    }

    public function getPage(): string {
        return $this->page;
    }

    /**
     * Set response type to offcanvas
     *
     * @return self For method chaining
     */
    public function asOffcanvas(): self {
        $this->response_type = 'offcanvas';
        return $this;
    }

    /**
     * Set response type to modal
     *
     * @return self For method chaining
     */
    public function asModal(): self {
        $this->response_type = 'modal';
        return $this;
    }

    /**
     * Set response type to DOM element
     *
     * @param string $id The ID of the DOM element
     * @return self For method chaining
     */
    public function asDom(string $id): self {
        $this->response_type = 'dom';
        $this->dom_id = $id;
        return $this;
    }

    /**
     * Set titles for new and edit modes
     *
     * @param string $new Title for new record
     * @param string|null $edit Title for edit record (if null, uses $new for both)
     * @return self For method chaining
     */
    public function setTitle(string $new, ?string $edit = null): self {
        $this->title_new = $new;
        $this->title_edit = $edit ?? $new;
        return $this;
    }

    /**
     * Set the list/table ID for reload functionality
     *
     * @param string $id The ID of the list/table to reload
     * @return self For method chaining
     */
    public function dataListId(string $id): self {
        $this->list_id = $id;
        return $this;
    }

    /**
     * Set the size for modal/offcanvas
     *
     * @param string $size Size option: 'sm', 'lg', 'xl', 'fullscreen'
     * @return self For method chaining
     */
    public function size(string $size): self {
        $this->size = $size;
        return $this;
    }

    /**
     * Add custom hidden field data to the form
     *
     * This method allows adding custom hidden fields that will be included in the form.
     * If a field with the same name already exists (like 'page' or 'action'),
     * the custom value will override the default value.
     *
     * @param string $key Field name
     * @param mixed $value Field value
     * @return self For method chaining
     *
     * @example ->customData('post_id', $post_id)
     */
    public function customData(string $key, $value): self {
        $this->custom_data[$key] = $value;
        return $this;
    }

    /**
     * Get response array for offcanvas or other response types
     *
     * @return array Response array based on response_type
     */
    public function getResponse(): array {
        // Determine if we are in new or edit mode
        $is_new = true;
        $primary_key = $this->model->getPrimaryKey();

        if (isset($this->fields[$primary_key]['row_value']) && !empty($this->fields[$primary_key]['row_value'])) {
            $is_new = false;
        }

        // Determine title
        $title = $is_new ? $this->title_new : $this->title_edit;

        // Generate form HTML
        $form_html = $this->render();

        // Build base response with action tracking
        $response = [
            'executed_action' => $this->executed_action ?? '',
            'success' => $this->action_success ?? true
        ];
        if (($this->action_success ?? true)) {
            $msg = MessagesHandler::getSuccesses();
        } else {
            $msg = MessagesHandler::getErrors(true);
        }
        if (is_array($msg) && count($msg) > 0) {
             $response['msg'] = implode("\n<br>", $msg);
        }



        // Add list reload if list_id is set and action was successful
        if ($this->list_id !== null && $this->action_success) {
            $response['list'] = [
                'id' => $this->list_id,
                'action' => 'reload'
            ];
        }

        // Build response based on response_type
        if ($this->response_type === 'offcanvas') {
            // Determine offcanvas action: hide if list_id is set and action was successful, otherwise show
            $offcanvas_action = ($this->list_id !== null && $this->action_success) ? 'hide' : 'show';

            $response['offcanvas_end'] = [
                'title' => $title,
                'action' => $offcanvas_action,
                'body' => $form_html
            ];

            // Add size if set
            if ($this->size !== null) {
                $response['offcanvas_end']['size'] = $this->size;
            }

            return $response;
        }

        if ($this->response_type === 'modal') {
            // Determine modal action: hide if list_id is set and action was successful, otherwise show
            $modal_action = ($this->list_id !== null && $this->action_success) ? 'hide' : 'show';

            $response['modal'] = [
                'title' => $title,
                'action' => $modal_action,
                'body' => $form_html
            ];

            // Add size if set
            if ($this->size !== null) {
                $response['modal']['size'] = $this->size;
            }

            return $response;
        }

        if ($this->response_type === 'dom') {
            // Determine DOM action: hide if list_id is set and action was successful, otherwise show
            $dom_action = ($this->list_id !== null && $this->action_success) ? 'hide' : 'show';

            // Prepend title to form HTML
            $innerHTML = '<h2 class="mb-0 me-3">' . $title . '</h2>' . $form_html;

            $response['element'] = [
                'selector' => $this->dom_id,
                'action' => $dom_action,
                'innerHTML' => $innerHTML
            ];

            return $response;
        }

        // Default response (if no response_type is set)
        $response['form'] = $form_html;
        return $response;
    }

    /**
     * Magic method to output HTML when object is used as string
     *
     * @return string Complete HTML form
     */
    public function __toString(): string {
        return $this->render();
    }

   
    // ========================================================================
    // EXTENSION MANAGEMENT
    // ========================================================================

    /**
     * Initialize extensions defined in $this->extensions array
     *
     * @return void
     */
    protected function initializeExtensions(): void
    {
        $this->extensions = $this->normalizeExtensions($this->extensions);
        $this->loadExtensions();
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

        $this->loaded_extensions = ExtensionLoader::load($this->extensions, 'FormBuilder', $this);
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
        $this->callExtensionHook('configure');

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
     * Call a hook on all loaded extensions
     *
     * @param string $hook Hook name
     * @param array $params Additional parameters to pass to the hook
     * @return void
     */
    protected function callExtensionHook(string $hook, array $params = []): void
    {
        ExtensionLoader::callHook($this->loaded_extensions, $hook, array_merge([$this], $params));
    }

    /**
     * Call a hook on all loaded extensions as a pipeline
     * Each extension can modify and return the data
     *
     * @param string $hook Hook name
     * @param mixed $data Data to process
     * @return mixed Modified data
     */
    protected function callExtensionPipeline(string $hook, mixed $data): mixed
    {
        foreach ($this->loaded_extensions as $extension) {
            if (method_exists($extension, $hook)) {
                $data = $extension->$hook($data);
            }
        }

        return $data;
    }

    /**
     * Get all fields (used by extensions)
     *
     * @return array Fields array
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
