<?php
namespace Builders\Traits\FormBuilder;

use App\{MessagesHandler, ObjectToForm, Route, Config};

/**
 * Trait FormSystemOperationsTrait
 *
 * Handles system operations for FormBuilder
 * Including save, delete, file upload, rendering, and action execution
 *
 * @package Builders\Traits
 */
trait FormSystemOperationsTrait {

    /**
     * Apply FormBuilder field configurations to Model rules
     *
     * This method modifies the Model's validation rules to match the configurations
     * set in FormBuilder (e.g., required, errorMessage). It also adds custom fields
     * that don't exist in the Model with 'sql' => false to prevent database operations.
     *
     * @param object $model The model instance to apply configurations to
     * @return void
     *
     * @example $form_builder->applyFieldConfigToModel($model)
     */
    public function applyFieldConfigToModel(object $model): void {
        // Get current model rules
        $model_rules = $model->getRules();
        // Apply FormBuilder field configurations to model rules
        foreach ($this->fields as $field_name => $field_config) {
            // If field doesn't exist in model, add it as a custom field
            if (!isset($model_rules[$field_name])) {
                // Create a new rule for this custom field
                $model_rules[$field_name] = [
                    'type' => $field_config['type'] ?? 'string',
                    'length' => $field_config['length'] ?? null,
                    'precision' => null,
                    'nullable' => true,
                    'default' => $field_config['default'] ?? null,
                    'primary' => false,
                    'label' => $field_config['label'] ?? $field_name,
                    'options' => $field_config['options'] ?? null,
                    'index' => false,
                    'unique' => false,
                    'list' => true,
                    'edit' => true,
                    'view' => true,
                    'sql' => false,  // Don't save to database (custom field)
                    'form-type' => $field_config['form-type'] ?? null,
                    'form-label' => $field_config['form-label'] ?? null,
                    'form-params' => $field_config['form-params'] ?? [],
                    'timezone_conversion' => false,
                ];
            } else {
                // Field exists in model, just update configurations

                // Apply 'required' configuration
                if (isset($field_config['form-params']['required'])) {
                    if (!isset($model_rules[$field_name]['form-params'])) {
                        $model_rules[$field_name]['form-params'] = [];
                    }
                    $model_rules[$field_name]['form-params']['required'] = $field_config['form-params']['required'];
                }

                // Apply 'invalid-feedback' (custom error message)
                if (isset($field_config['form-params']['invalid-feedback'])) {
                    if (!isset($model_rules[$field_name]['form-params'])) {
                        $model_rules[$field_name]['form-params'] = [];
                    }
                    $model_rules[$field_name]['form-params']['invalid-feedback'] = $field_config['form-params']['invalid-feedback'];
                }
            }
        }

        // Set modified rules back to model
        $model->setRules($model_rules);
    }

    /**
     * Default save operation for forms
     * MessagesHandler for messages errors and success
     *
     * @param array $request Request data (usually $_REQUEST['data'])
     * @return bool
     *
     * @example $form_builder->save($_REQUEST['data'])
     */
    public function save(array $request): bool {
        $this->last_insert_id = 0;
        if (!$this->model) {
            MessagesHandler::addError('No model set for save operation');
            return false;
        }

        // Call beforeSave hook on extensions
        if (method_exists($this, 'callExtensionPipeline')) {
            $hadErrorsBeforePipeline = MessagesHandler::hasErrors();
            $request = $this->callExtensionPipeline('beforeSave', $request);
            if (!$hadErrorsBeforePipeline && MessagesHandler::hasErrors()) {
                return false;
            }
        }

        // Get primary key value
        $id = _absint($request[$this->model->getPrimaryKey()] ?? 0);
        // Get form data for saving
        try {
            // Get or create record and merge with form data
            // DateTime parsing timezone is controlled by Config 'use_user_timezone'
            $obj = $this->model->getByIdAndUpdate($id, $request);

            // Apply FormBuilder field configurations (required, errorMessage, etc.) to Model rules
            $this->applyFieldConfigToModel($obj);

            // Convert all datetime fields from user timezone to UTC before saving to database
            // This only happens if Config 'use_user_timezone' is true
            $obj->convertDatesToUTC();
            // Handle file uploads before saving - stop if file operations fail
            $file_result = $this->moveUploadedFile($obj);
            if (!$file_result['success']) {
                MessagesHandler::addError($file_result['message']);
                return false;
            }

            if ( $obj->validate()) {
                // Save data
                if ($obj->save()) {
                    if ($id === 0) {
                        $id = $obj->getLastInsertId();
                    }
                    $this->last_insert_id = $id;

                    // Call afterSave hook on extensions
                    if (method_exists($this, 'callExtensionHook')) {
                        $this->callExtensionHook('afterSave', [$request]);
                    }

                    //MessagesHandler::addSuccess('Save successful');
                    return true;
                } else {
                    $error = _r("An error occurred while saving the data. ") .$obj->getLastError();
                    MessagesHandler::addError($error);
                    return false;
                }
            } else {
                $errors = $obj->getLastError();
                MessagesHandler::addError($errors);
                return false;
            }
        } catch (\Exception $e) {
            if (Config::get('debug', false)) {
                throw $e;
            } else {
                MessagesHandler::addError($e->getMessage());
                return false;
            }
        }
    }

    /**
     * Handle file uploads and move them from temp to media folder
     *
     * @param object $obj Data object with file fields
     * @return array Result with success status and message
     */
    public function moveUploadedFile($obj) {
        foreach ($obj->getRules('edit', true) as $key => $rule) {
            if ($rule['form-type'] == 'file' || $rule['form-type'] == 'image') {
                // Get upload options from field configuration
                $upload_options = $rule['form-params'] ?? [];

                // Cartella di destinazione (personalizzabile)
                $media_rel_path = $upload_options['upload-dir'] ?? 'media/';
                $media_rel_path = str_replace(['.', '..'], '', $media_rel_path);
                // Ensure trailing slash
                if (substr($media_rel_path, -1) !== '/') {
                    $media_rel_path .= '/';
                }

                $media_folder = LOCAL_DIR . '/' . $media_rel_path;

                // Cartella temporanea dove sono stati caricati i file
                $temp_dir = \App\Get::tempDir();

                // Verifica se ci sono file caricati per questo campo
                $files_key = $key . '_files';

                if (isset($_POST['data'][$files_key]) && is_array($_POST['data'][$files_key])) {
                    $files_data = $_POST['data'][$files_key];
                    $moved_files = [];

                    // Check if destination directory exists and create if necessary
                    if (!file_exists($media_folder)) {
                        if (!mkdir($media_folder, 0755, true)) {
                            return ['success' => false, 'message' => _r('Unable to create upload directory: ') . $media_folder];
                        }
                    }
                    // Check if destination directory is writable
                    if (!is_writable($media_folder)) {
                        return ['success' => false, 'message' => _r('Upload directory is not writable: ') . $media_rel_path];
                    }
                     // Check if temp directory exists and is readable
                    if (!file_exists($temp_dir) || !is_readable($temp_dir)) {
                        return ['success' => false, 'message' => _r('Temporary directory is not accessible: ') . $temp_dir];
                    }

                    foreach ($files_data as $file_index => $file_info) {
                        if (empty($file_info['url']) || empty($file_info['name'])) {
                            continue;
                        }

                        $temp_file_name = $file_info['url'];
                        $original_name = $file_info['name'];
                        $is_existing_file = isset($file_info['existing']) && $file_info['existing'] == '1';

                        // Defensive fallback: if a file is marked as existing but still present in temp
                        // with a plain filename, treat it as a new upload and move it.
                        if ($is_existing_file) {
                            $has_path_separator = strpos($temp_file_name, '/') !== false || strpos($temp_file_name, '\\') !== false;
                            if (!$has_path_separator) {
                                $possible_temp_file = $temp_dir . '/' . $temp_file_name;
                                if (file_exists($possible_temp_file)) {
                                    $is_existing_file = false;
                                }
                            }
                        }

                        if ($is_existing_file) {
                            // File già esistente, non spostarlo - mantieni il percorso attuale
                            $moved_files[$file_index] = [
                                'url' => $temp_file_name,
                                'name' => $original_name
                            ];
                        } else {
                            // Nuovo file caricato - deve essere spostato dalla directory temporanea
                            $temp_file_path = $temp_dir . '/' . $temp_file_name;

                            // Verifica che il file temporaneo esista
                            if (!file_exists($temp_file_path)) {
                                return ['success' => false, 'message' => _r('Uploaded file not found: ') . $temp_file_path];
                            }

                            // Check if temp file is readable
                            if (!is_readable($temp_file_path)) {
                                return ['success' => false, 'message' => _r('Uploaded file is not readable: ') . $original_name];
                            }

                            // Genera un nome univoco per la cartella media
                            $media_file_name = $this->generateUniqueFilename($media_folder, $temp_file_name);
                            $media_file_path = $media_folder . $media_file_name;

                            // Sposta il file da temp a media
                            if (!rename($temp_file_path, $media_file_path)) {
                                return ['success' => false, 'message' => _r('Failed to move uploaded file: ') . $original_name];
                            }

                            // Imposta i permessi corretti
                            if (!chmod($media_file_path, 0644)) {
                                // Log warning but don't fail completely
                                error_log("Warning: Could not set file permissions for: " . $media_file_path);
                            }

                            // Aggiunge il file mosso all'array con la nuova struttura
                            $moved_files[$file_index] = [
                                'url' => $media_rel_path . $media_file_name,
                                'name' => $original_name
                            ];
                        }
                    }

                    // Se sono stati mossi file, aggiorna l'oggetto con il JSON
                    if (!empty($moved_files)) {
                        $obj->$key = json_encode($moved_files);
                    } else {
                        // Se non ci sono file, svuota il campo
                        $obj->$key = null;
                    }
                }
            }
        }

        // Return success if all files were processed successfully
        return ['success' => true, 'message' => 'Files processed successfully'];
    }

    /**
     * Genera un nome file univoco nella cartella di destinazione
     *
     * @param string $folder Destination folder path
     * @param string $filename Original filename
     * @return string Unique filename
     */
    private function generateUniqueFilename($folder, $filename) {
        $name_without_ext = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $counter = 0;

        $new_filename = $filename;
        while (file_exists($folder . $new_filename)) {
            $counter++;
            $new_filename = $name_without_ext . '_' . str_pad($counter, 3, '0', STR_PAD_LEFT) . '.' . $extension;
        }

        return $new_filename;
    }

    /**
     * Default delete operation for forms
     *
     * @param array $request Request data (usually $_REQUEST)
     * @param string|null $redirect_success Success redirect URL
     * @param string|null $redirect_error Error redirect URL
     * @return array Result array with success status and message
     *
     * @example $form_builder->delete($_REQUEST['data'], '?page=list')
     */
    public function delete(array $request, ?string $redirect_success = null, ?string $redirect_error = null): array {
        if (!$this->model) {
            return ['success' => false, 'message' => 'No model set for delete operation'];
        }

        $redirect_success = $redirect_success ?? ($request['url_success'] ?? $this->url_success);
        $redirect_error = $redirect_error ?? ($request['url_error'] ?? $this->url_error);

        // Get primary key value
        $id = _absint($request[$this->model->getPrimaryKey()] ?? 0);

        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid ID for delete operation'];
        }

        // Delete record
        try {
            if ($this->model->delete($id)) {
                if ($redirect_success) {
                    Route::redirectSuccess($redirect_success, _r('Delete successful'));
                }
                return ['success' => true, 'message' => 'Delete successful', 'id' => $id];
            } else {
                $error = _r("An error occurred while deleting the data. ") . $this->model->getLastError();
                if ($redirect_error) {
                    Route::redirectError($redirect_error, $error);
                }
                return ['success' => false, 'message' => $error];
            }
        } catch (\Exception $e) {
            if (Config::get('debug', false)) {
                throw $e;
            } else {
                MessagesHandler::addError($e->getMessage());
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }
    }


    /**
     * Add custom model data
     *
     * @param \App\Abstracts\AbstractModel $model Model instance
     * @return self For method chaining
     *
     * @example ->setData($this->model)
     */
    public function setData(\App\Abstracts\AbstractModel $model): self {
        $this->custom_model_data = $model;
        return $this;
    }

    public function setIdRequest($id) {
        $this->id_request = $id;
        return $this;
    }

    /**
     * Generate and return the complete form HTML
     *
     * @return string Complete HTML form
     */
    public function render(): string {
        if (isset($_REQUEST['reload'])) {
            $this->markReload();
        }

        $this->setModel($this->model_class);
        
        // Check if any action was triggered before rendering
        $this->ActionExecution();

        // Call beforeRender hook on extensions
        if (method_exists($this, 'callExtensionPipeline')) {
            $this->fields = $this->callExtensionPipeline('beforeRender', $this->fields);
        }

        // Start form
        $html = ObjectToForm::start($this->page,  $this->current_action, $this->form_attributes, $this->only_json, $this->custom_data);

        // Add custom HTML before fields
        if (isset($this->custom_html['before_fields'])) {
            foreach ($this->custom_html['before_fields'] as $custom) {
                $html .= $custom;
            }
        }

        // Generate fields
        $ordered_fields = $this->getOrderedFields();
        foreach ($ordered_fields as $field) {

            // Check if there's a showIf condition and evaluate it
            if (isset($field['showIf']) && is_array($field['showIf'])) {
                [$field_name, $operator, $expected_value] = $field['showIf'];

                // Get field value (prefer row_value over default value)
                $field_value = null;
                if (isset($this->fields[$field_name]['row_value'])) {
                    $field_value = $this->fields[$field_name]['row_value'];
                } elseif (isset($this->fields[$field_name]['value'])) {
                    $field_value = $this->fields[$field_name]['value'];
                } elseif (isset($this->fields[$field_name]['default'])) {
                    $field_value = $this->fields[$field_name]['default'];
                }

                // Evaluate condition based on operator
                $should_show = false;
                switch ($operator) {
                    case 'empty':
                        $should_show = ($field_value === null || $field_value === '' || $field_value === 0 || is_null($field_value));
                        break;
                    case 'not_empty':
                        $should_show = ($field_value !== null && $field_value !== '' && $field_value !== 0 && !is_null($field_value));
                        break;
                    case '=':
                    case '==':
                        $should_show = $field_value == $expected_value;
                        break;
                    case '!=':
                    case '<>':
                        $should_show = $field_value != $expected_value;
                        break;
                    case '>':
                        $should_show = $field_value > $expected_value;
                        break;
                    case '<':
                        $should_show = $field_value < $expected_value;
                        break;
                    case '>=':
                        $should_show = $field_value >= $expected_value;
                        break;
                    case '<=':
                        $should_show = $field_value <= $expected_value;
                        break;
                    case 'in':
                        $should_show = is_array($expected_value) && in_array($field_value, $expected_value);
                        break;
                    case 'not_in':
                        $should_show = is_array($expected_value) && !in_array($field_value, $expected_value);
                        break;
                }

                if (!$should_show) {
                    continue; // Skip this field
                }
            }

            // Use row_value if exists and not empty, otherwise use default, otherwise empty string
            $value = '';
            if (isset($field['row_value']) && $field['row_value'] !== '' && $field['row_value'] !== null) {
                $value = $field['row_value'];
            } elseif (isset($field['default']) && $field['default'] !== '' && $field['default'] !== null) {
                $value = $this->resolveDefaultValue($field['default']);
            }
            $html .= ObjectToForm::row($field, $value);
        }

        // Add custom HTML after fields
        if (isset($this->custom_html['after_fields'])) {
            foreach ($this->custom_html['after_fields'] as $custom) {
                $html .= $custom;
            }
        }

        // Add custom HTML before submit
        if (isset($this->custom_html['before_submit'])) {
            foreach ($this->custom_html['before_submit'] as $custom) {
                $html .= $custom;
            }
        }

        $buttons_html = $this->renderActionButtons();
        $html .= $buttons_html;

        // End form
        $html .= ObjectToForm::end();
        return $html;
    }

    /**
     * Resolve default value with optional ExpressionParser evaluation.
     * Falls back to literal default when parsing/execution fails.
     */
    private function resolveDefaultValue(mixed $defaultValue): mixed
    {
        if (!is_string($defaultValue)) {
            return $defaultValue;
        }

        $expression = trim($defaultValue);
        if ($expression === '' || !$this->looksLikeExpression($expression)) {
            return $defaultValue;
        }

        try {
            $parser = new \App\ExpressionParser();
            $params = [];
            if (is_object($this->model) && method_exists($this->model, 'getRawData')) {
                $rawData = $this->model->getRawData('array', false);
                if (is_array($rawData)) {
                    $params = $rawData;
                } elseif (is_object($rawData)) {
                    $params = (array) $rawData;
                }
            }

            if (!empty($params)) {
                $parser->setParameters($params);
            }

            $analysis = $parser->analyze($expression, true);
            if (!empty($analysis['error']) || !array_key_exists('result', $analysis)) {
                return $defaultValue;
            }

            return $analysis['result'];
        } catch (\Throwable) {
            return $defaultValue;
        }
    }

    /**
     * Fast guard to avoid evaluating plain literals as expressions.
     */
    private function looksLikeExpression(string $value): bool
    {
        if (preg_match('/\\[[^\\]]+\\]/', $value) === 1) {
            return true;
        }
        if (preg_match('/(?:==|!=|<>|<=|>=|&&|\\|\\||[+\\-*\\/\\^%<>!=])/', $value) === 1) {
            return true;
        }
        if (preg_match('/\\b(?:IF|THEN|ELSE|ENDIF|AND|OR|NOT)\\b/i', $value) === 1) {
            return true;
        }
        if (preg_match('/[A-Za-z_][A-Za-z0-9_]*\\s*\\(/', $value) === 1) {
            return true;
        }

        return false;
    }


    /**
     * Execute the action associated with the pressed button
     * 
     */
    public function ActionExecution(): self {
        if ($this->execute_actions) return $this;
        $this->execute_actions = true;
        // Check which button was pressed

        foreach ($this->actions as $action_key => $action_config) {
            // Look for the button press in various formats
            $button_pressed = false;

            if (isset($_POST[$action_key]) || isset($_REQUEST[$action_key])) {
                $button_pressed = true;
            }
           
            if ($button_pressed && isset($action_config['callback']) && is_callable($action_config['callback'])) {

                $this->function_results = call_user_func($action_config['callback'], $this, $_REQUEST['data']);

                // Track which action was executed
                $this->executed_action = $action_key;

                if (is_array($this->function_results) && isset($this->function_results['success']) && isset($this->function_results['message'])) {
                    // Track if action was successful
                    $this->action_success = $this->function_results['success'];

                    if ($this->function_results['success']) {
                        MessagesHandler::addSuccess($this->function_results['message']);
                    } else {
                        MessagesHandler::addError($this->function_results['message']);
                    }
                }
                break;
            }
        }
        return $this;
    }

    /**
     * Render action buttons HTML
     *
     * @return string HTML for action buttons
     */
    private function renderActionButtons(): string {
      
        if (empty($this->actions)) {
            // Fallback to old submit button if no actions defined
            return ObjectToForm::submit($this->submit_text, $this->submit_attributes);
        }

        $html = '<div class="d-flex gap-2">';

        $button_count = 0;

        foreach ($this->actions as $key => $action) {
           
            // Check if there's a showIf condition and evaluate it
            if (isset($action['showIf']) && is_array($action['showIf']) ) {
                if (count($action['showIf']) == 3) {
                    [$field_name, $operator, $value] = $action['showIf'];
                    $should_show = false;
                } elseif (count($action['showIf']) == 2) {
                    [$field_name, $operator] = $action['showIf'];
                    $value = null;
                    $should_show = false;
                } else {
                    $should_show = true;
                }

                // Get field value (prefer row_value over default value)
                $field_value = null;
                if (isset($this->fields[$field_name]['row_value'])) {
                    $field_value = $this->fields[$field_name]['row_value'];
                } elseif (isset($this->fields[$field_name]['value'])) {
                    $field_value = $this->fields[$field_name]['value'];
                }

                // Evaluate condition based on operator
                switch ($operator) {
                    case 'empty':
                        $should_show = ($field_value === null || $field_value === '' || $field_value === 0 || is_null($field_value));
                        break;
                    case 'not_empty':
                        $should_show = ($field_value !== null && $field_value !== '' && $field_value !== 0 && !is_null($field_value));
                        break;
                    case '=':
                    case '==':
                        $should_show = $field_value == $value;
                        break;
                    case '!=':
                    case '<>':
                        $should_show = $field_value != $value;
                        break;
                    case '>':
                        $should_show = $field_value > $value;
                        break;
                    case '<':
                        $should_show = $field_value < $value;
                        break;
                    case '>=':
                        $should_show = $field_value >= $value;
                        break;
                    case '<=':
                        $should_show = $field_value <= $value;
                        break;
                }

                if (!$should_show) {
                    continue; // Skip this button
                }
            }

            $button_class = $action['class'];
                     
            if ($action['type'] === 'submit' || $action['type'] === 'button') {
                $html .= '<button type="' . _r($action['type']) . '" name="' . _r($key) . '" value="1" class="' . _r(trim($button_class)) . '"';

                if (isset($action['confirm'])) {
                    $html .= ' onclick="return confirm(\'' . _r($action['confirm']) . '\')"';
                }

                if (isset($action['onclick'])) {
                    $html .= ' onclick="' . _r($action['onclick']) . '"';
                }

                if (isset($action['validate']) && $action['validate'] === false) {
                    $html .= ' formnovalidate="formnovalidate"';
                }

                if (isset($action['attributes']) && is_array($action['attributes'])) {
                    foreach ($action['attributes'] as $attribute => $value) {
                        $html .= ' ' . _r($attribute) . '="' . _r($value) . '"';
                    }
                }

                $html .= '>' . _rt($action['label']) . '</button>';

            } elseif ($action['type'] === 'link') {
                $html .= '<a href="' . _r($action['link']) . '" class="' . _r(trim($button_class)) . '"';

                if (isset($action['target'])) {
                    $html .= ' target="' . _r($action['target']) . '"';
                }

                if (isset($action['attributes']) && is_array($action['attributes'])) {
                    foreach ($action['attributes'] as $attribute => $value) {
                        $html .= ' ' . _r($attribute) . '="' . _r($value) . '"';
                    }
                }

                $html .= '>' . _rt($action['label']) . '</a>';
            }

            $button_count++;
          
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Get results from executed action functions
     *
     * @return array|null Results from action callbacks, null if no actions executed
     */
    public function getFunctionResults(): ?array {
        return $this->function_results;
    }
}
