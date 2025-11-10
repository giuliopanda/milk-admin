<?php
namespace Builders\Traits;

use App\{MessagesHandler, ObjectToForm, Route};

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

        // Get primary key value
        $id = _absint($request[$this->model->getPrimaryKey()] ?? 0);
        // Get form data for saving
        $obj = $this->model->getByIdAndUpdate($id, $request);
      
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
    }

    /**
     * Generate and return the complete form HTML
     *
     * @return string Complete HTML form
     */
    public function render(): string {
        // Check if any action was triggered before rendering
        $this->ActionExecution();

        // Start form
        $html = ObjectToForm::start($this->page,  $this->current_action, $this->form_attributes, $this->only_json);

        // Add custom HTML before fields
        if (isset($this->custom_html['before_fields'])) {
            foreach ($this->custom_html['before_fields'] as $custom) {
                $html .= $custom;
            }
        }

        // Generate fields
        $ordered_fields = $this->getOrderedFields();
        foreach ($ordered_fields as $field) {
            // Use row_value if exists and not empty, otherwise use default, otherwise empty string
            $value = '';
            if (isset($field['row_value']) && $field['row_value'] !== '' && $field['row_value'] !== null) {
                $value = $field['row_value'];
            } elseif (isset($field['default']) && $field['default'] !== '' && $field['default'] !== null) {
                $value = $field['default'];
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

        // Generate action buttons
        $html .= $this->renderActionButtons();

        // End form
        $html .= ObjectToForm::end();

        return $html;
    }


    /**
     * Check if any action was executed and call its callback
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
                if (is_array($this->function_results) && isset($this->function_results['success']) && isset($this->function_results['message'])) {
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

        $html = '';

        foreach ($this->actions as $key => $action) {
            // Check if there's a showIf condition and evaluate it
            if (isset($action['showIf']) && is_array($action['showIf'])) {
                [$field_name, $operator, $value] = $action['showIf'];

                // Get field value (prefer row_value over default value)
                $field_value = null;
                if (isset($this->fields[$field_name]['row_value'])) {
                    $field_value = $this->fields[$field_name]['row_value'];
                } elseif (isset($this->fields[$field_name]['value'])) {
                    $field_value = $this->fields[$field_name]['value'];
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

            if ($action['type'] === 'submit') {
                $html .= '<button type="submit" name="' . $key . '" value="1" class="' . $action['class'] . '"';

                if (isset($action['confirm'])) {
                    $html .= ' onclick="return confirm(\'' . htmlspecialchars($action['confirm']) . '\')"';
                }

                if (isset($action['onclick'])) {
                    $html .= ' onclick="' . htmlspecialchars($action['onclick']) . '"';
                }

                if (isset($action['validate']) && $action['validate'] === false) {
                    $html .= ' formnovalidate="formnovalidate"';
                }

                $html .= '>' . htmlspecialchars($action['label']) . '</button>';

            } elseif ($action['type'] === 'link') {
                $html .= '<a href="' . htmlspecialchars($action['link']) . '" class="' . $action['class'] . '"';

                if (isset($action['target'])) {
                    $html .= ' target="' . htmlspecialchars($action['target']) . '"';
                }
             
                $html .= '>' . htmlspecialchars($action['label']) . '</a>';
            }
        }

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
