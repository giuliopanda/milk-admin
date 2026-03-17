<?php
namespace App\Abstracts\Traits;

use App\{Logs, MessagesHandler, Database\Query};
use App\{Get, Config, ExtensionLoader};
use App\Abstracts\AbstractModel;


!defined('MILK_DIR') && die();

/**
 * CRUD Operations Trait
 * Handles Create, Read, Update, Delete operations and related functionality
 */
trait CrudOperationsTrait
{
    /**
     * Query results cache
     * @var array
     */
    protected array $cache = [];

    /**
     * Get a record by its primary key
     *
     * Retrieves a record from the database using its primary key value.
     * Returns a Model instance with ResultInterface containing the record.
     *
     * @example
     * ```php
     * $model = $this->getById(123);
     * if ($model) {
     *     echo $model->title;
     * }
     * ```
     *
     * @param mixed $id The primary key value
     * @param bool $use_cache Whether to use cache for data retrieval
     * @return static|null Model instance or null if not found
     */
    public function getById($id, bool $use_cache = true): ?static
    {
        $this->dates_in_user_timezone = false;
        $this->error = false;
        $this->last_error = '';
        if ($use_cache && $id !== null && isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        if ($id === null || $id === '') {
            return null;
        }

        $query = $this->newQuery();
        if ($query === null) {
            return null;
        }
        $new_model = new static();
        $new_model->setRules($this->getRules());
        // Propagate include_relationships from current model to new model
        if (!empty($this->include_relationships)) {
            $new_model->with($this->include_relationships);
        }

        $query->setModelClass($new_model);

        $ris = $query->where($this->primary_key . ' = ?', [$id])->getRow();
        $this->current_query = null;
        return ($ris instanceof static) ? $ris : null;

    }

    /**
     * Get records by their primary key
     *
     * Retrieves records from the database using their primary key values.
     * Returns a Model instance with ResultInterface containing the records.
     *
     * @example
     * ```php
     * $model = $this->model->getByIds('1,2,3');
     * while ($model->hasNext()) {
     *     echo $model->title;
     *     $model->next();
     * }
     * ```
     *
     * @param string|array $ids Comma-separated list of primary key values or array of IDs
     * @return static|null Model instance with ResultInterface or null if no records found
     */
    public function getByIds(string|array $ids): ?static
    {
        $this->dates_in_user_timezone = false;
        $this->error = false;
        $this->last_error = '';
        if (is_string($ids)) {
            $id_array = array_map(static fn($id): int => abs((int) $id), explode(',', $ids));
        } else {
            $id_array = $ids;
        }
        $id_array = array_filter($id_array);

        if (empty($id_array)) {
            return null;
        }
        if ($this->db === null) {
            return null;
        }
        $id_name = $this->primary_key;

        // Build WHERE clause for multiple IDs
        $placeholders = str_repeat('?,', count($id_array) - 1) . '?';
        $query = $this->newQuery();
        if ($query === null) {
            return null;
        }
        $new_model = new static();
        $new_model->setRules($this->getRules());
        // Propagate include_relationships from current model to new model
        if (!empty($this->include_relationships)) {
            $new_model->with($this->include_relationships);
        }

        $query->setModelClass($new_model);
        $query->where($this->db->qn($id_name) . ' IN (' . $placeholders . ')', $id_array);

        $ris = $query->getResults();
        $this->current_query = null;
        return ($ris instanceof static) ? $ris : null;
    }

    public function getRow(): mixed  {
        if ($this->current_query === null) {
            return null;
        }
        $ris = $this->current_query->getRow();
        $this->current_query = null;
        return $ris;
    }

    public function getResults(): mixed {
        if ($this->current_query === null) {
            return null;
        }
        $ris = $this->current_query->getResults();
        $this->current_query = null;
        return $ris;
    }

    public function getVar(?string $value = null) : mixed {
        if ($this->current_query === null) {
            return null;
        }
        $ris = $this->current_query->getVar($value);
        $this->current_query = null;
        return $ris;
    }

    /**
     * Generic search for autocomplete based on relationship configuration
     * Used only in MilkSelect single on BelongsTo relationship
     * Please note that it queries another model to get the results.
     *
     * @param string $search Search term
     * @param string $field_name Field name that has the apiUrl (e.g., 'doctor_id')
     * @return array Array of options matching the search
     */
    public function searchRelated(string $search, string $field_name): array
    {
        $rules = $this->getRules();

        // Get the relationship configuration
        if (!isset($rules[$field_name]['relationship']) || $rules[$field_name]['relationship']['type'] !== 'belongsTo') {
            return [];
        }

        if (!isset($rules[$field_name]['relationship']['related_model']) || !isset($rules[$field_name]['relationship']['related_key']) || !isset($rules[$field_name]['api_display_field']) ) {
            return [];
        }

        $relationship = $rules[$field_name]['relationship'];
        $related_model_class = $relationship['related_model'];
        $related_key = $relationship['related_key'];
        $display_field = $rules[$field_name]['api_display_field'];

        // Instantiate related model
        $relatedModel = new $related_model_class();
        $query = $relatedModel->query();

        // Filter by search term on display field
        if (!empty($search)) {
            $query->where("$display_field LIKE ?", '%' . $search . '%');
        }

        // Apply custom where condition if defined in relationship
        $where_config = $relationship['where'] ?? null;
        if ($where_config !== null) {
            $query->where($where_config['condition'], $where_config['params']);
        }

        $results = $query->limit(0, 20)->getResults();
        $options = [];

        foreach ($results as $result) {
            $options[$result->$related_key] = $result->$display_field;
        }

        return $options;
    }


    public function setResultsByIds(string|array $ids): bool
    {
        $this->error = false;
        $this->last_error = '';
        if (is_string($ids)) {
            $id_array = array_map(static fn($id): int => abs((int) $id), explode(',', $ids));
        } else {
            $id_array = $ids;
        }
        $id_array = array_filter($id_array);

        if (empty($id_array)) {
            return false;
        }
        if ($this->db === null) {
            return false;
        }
        $id_name = $this->primary_key;

        // Build WHERE clause for multiple IDs
        $placeholders = str_repeat('?,', count($id_array) - 1) . '?';
        $query = $this->query();
        if ($query === null) {
            return false;
        }
        $query->where($this->db->qn($id_name) . ' IN (' . $placeholders . ')', $id_array);
        $results = $query->getResults();
        if (!is_object($results)) {
            return false;
        }
        $ris = $results->getRawData();
        if (!is_array($ris)) {
            return false;
        }
        $this->dates_in_user_timezone = false;
        $this->setResults($ris);
        return true;
    }

    /**
     * Get a record by ID or return an empty Model instance
     *
     * Returns a record by its primary key, or an empty Model instance if not found
     *
     * @example
     * ```php
     * $post = $this->model->getByIdAndUpdate(123);
     * echo $post->title; // No need to check if $post exists
     * ```
     *
     * @param mixed $id The primary key value
     * @param array $merge_data Optional data to merge with the record
     * @return static Model instance with record or empty
     */
    public function getByIdAndUpdate($id, array $merge_data = []): static {
        $this->dates_in_user_timezone = false;
        $model = $this->getById($id);
        if ($model === null) {
            $model = $this->getEmpty($merge_data);
        }
        
        if ($model->isEmpty()) {
            // Empty model - fill with merge_data
            // Dates in merge_data are in user timezone (from form submission)
            $model->fill($merge_data);
        } else {
            // Existing model loaded from DB (dates in UTC)
            // Set flag to true before merging data from form (dates in user timezone)
            $model->dates_in_user_timezone = true;
            foreach ($merge_data as $k=>$d) {
                $model->setValueWithConversion($k, $d);
                
            }
        }
        if ($merge_data != [] || !$model->isEmpty()) {
            // rimuovo il parametro default dai rules
            $rules = $model->getRules();
            foreach ($rules as $k=>$v) {
                if (($v['default'] ?? '') != '') {
                    unset($rules[$k]['default']);
                }
            }
            $model->setRules($rules);
        }

       $model->rebuildActions();

        return $model;
    }


    private function rebuildActions(): void
    {
        $primary_key = $this->getPrimaryKey();
        if ($primary_key != "" && is_array($this->records_objects)) {
            foreach ($this->records_objects as $index => $record) {
                if (!is_array($record)) {
                    continue;
                }

                if ($this->hasRecordState($index)) {
                    $this->syncRecordActionFromState($index);
                    continue;
                }

                $current_action = $record['___action'] ?? null;
                if ($current_action !== null) {
                    $this->records_objects[$index]['___action'] = $this->normalizeRecordActionForPersistence($record, null, $index);
                }
            }
        }
    }

    private function normalizeRecordActionForPersistence(array $record, ?array $prepared_data = null, ?int $index = null): ?string
    {
        $current_action = $record['___action'] ?? null;
        if ($current_action === null) {
            return null;
        }

        $pk_value = $record[$this->primary_key] ?? ($prepared_data[$this->primary_key] ?? null);
        $has_primary_key = !(
            $pk_value === null
            || $pk_value === ''
            || $pk_value === 0
            || $pk_value === '0'
        );

        if ($current_action === 'edit' && !$has_primary_key) {
            return 'insert';
        }

        if (
            $current_action === 'insert'
            && $has_primary_key
            && ($index === null || !$this->isNewRecordIndex($index))
        ) {
            return 'edit';
        }

        return $current_action;
    }

   
        

    /**
     * Get a record for editing
     *
     * Retrieves a record for editing, applying edit rules
     *
     * @example
     * ```php
     * $data = $this->model->getByIdForEdit($id, Route::getSessionData());
     * if ($data === null) {
     *     Route::redirectError('?page='.$this->page."&action=list", 'Invalid id');
     * }
     * ```
     *
     * @param mixed $id The primary key value
     * @param array $merge_data Additional data to merge with the record
     * @return static Model instance with record
     */
    function getByIdForEdit($id, array $merge_data = []): static {
        $model = $this->getByIdAndUpdate($id, $merge_data);
        $model->convertDatesToUserTimezone();
        return $model;
    }


    /**
     * Get an empty Model instance with optional initial data
     *
     * Returns a new Model instance with an empty record that can be populated
     *
     * @example
     * ```php
     * $new_post = $this->model->getEmpty();
     * $new_post->title = "New Title";
     * $new_post->save();
     * ```
     *
     * @param array $data Data to initialize the empty record with
     * @return static Empty Model instance
     */
    public function getEmpty(array $data = []): static {
        // Create a new Model instance
        $new_model = new static();
        $new_model->setRules($this->getRules());
        $new_model->initializePristineNewRecord();
        $new_model->applyDefaultValuesForCurrentRecord();

        if ($data !== []) {
            $data = $new_model->extractRelationshipData($data);
            $data = $new_model->filterData($data);

            foreach ($data as $key => $value) {
                if ($value instanceof AbstractModel) {
                    continue;
                }
                $new_model->setValueWithConversion($key, $value);
            }
        }

        return $new_model;
    }

    /**
     * Apply a query to the model and return the results
     *
     * @param Query $query The query to apply
     * @param array $params Optional parameters for the query
     * @return static Model instance with results
     */
    public function get(Query $query, ?array $params = []) : static {
        $new_model = new static();
        $new_model->setRules($this->getRules());
        $query->setModelClass($new_model);
        $result = $query->getResults();
        return ($result instanceof static) ? $result : $new_model;
    }

    

    /**
     * store - Salva direttamente un record nel database
     * Metodo originale per salvare/aggiornare singoli record
     * Pubblico per permettere chiamate dirette quando necessario (bypassa il batch system)
     *
     * @param array $data Data to save
     * @param mixed $id Primary key for update, If null the primary key will be used from the data array
     * @return bool True if save was successful, false otherwise
     */
    public function store(array $data, $id = null): bool
    {
        $this->last_stored_record_id = null;
        $this->error = false;
        $this->last_error = '';
        if ($this->primary_key != null && $id != null ) {
            $data[$this->primary_key] = $id;
        }
        $new_class = new static();
        $new_class->setRules($this->getRules());
        $new_class->fill($data);
        if ($new_class->validate()) {
            $new_class->save(true, false);
           
            $this->error = $new_class->error;
            $this->last_error = $new_class->last_error;
            $this->save_results = $new_class->save_results;
            $this->last_stored_record_id = $new_class->getLastInsertId();
            return !$new_class->error;
        } else {
            $this->error = true;
            $this->last_error = $new_class->last_error;
            return false;
        }   
    }

    /**
     * Array that stores save results for each operation
     * Structure: [['id' => int, 'action' => string, 'result' => bool, 'last_error' => string], ...]
     * @var array
     */
    protected array $save_results = [];

    /**
     * Processa tutte le modifiche, creazioni ed eliminazioni tracciate
     * Esegue DELETE per i record in deleted_primary_keys
     * Esegue INSERT per i record con ___action = 'insert'
     * Esegue UPDATE per i record con ___action = 'edit'
     *
     * @param bool $cascade If true, saves hasOne and hasMany relationships after saving parent record
     * @param bool $reset_save_result If true, reset save results after commit
     * @return bool True se tutte le operazioni hanno successo, false altrimenti
     */
    public function save(bool $cascade = false, $reset_save_result = true): bool
    {
        $this->error = false;
        $this->last_error = '';
        $this->save_results = [];
        $this->last_stored_record_id = null;
        // Se non c'è records_objects, non ci sono modifiche da salvare
        if ($this->records_objects === null) {
            return true;
        }
        if ($this->db === null) {
            $this->error = true;
            $this->last_error = 'No database connection';
            return false;
        }
        $this->cleanEmptyRecords();
        $add = true;
        if (ExtensionLoader::preventRecursion('beforeSave')) {
            $add = ExtensionLoader::callReturnHook($this->loaded_extensions, 'beforeSave', [true, $this->records_objects]);
        }
        ExtensionLoader::freeRecursion('beforeSave');
        if (!$add) return true;

        // Call beforeSave method if it exists in the model
        if (method_exists($this, 'beforeSave')) {
            $result = $this->beforeSave($this->records_objects);
            // If beforeSave returns false, stop the save operation
            if ($result === false) {
                return false;
            }
        }

        try {
            // 1. Processa le eliminazioni
            // Hook beforeDelete (chiamato prima di cancellare i record)
            if (!empty($this->deleted_primary_keys)) {
                $add = true;
                if (ExtensionLoader::preventRecursion('beforeDelete')) {
                    $add = ExtensionLoader::callReturnHook($this->loaded_extensions, 'beforeDelete', [true, $this->deleted_primary_keys]);
                } 
                ExtensionLoader::freeRecursion('beforeDelete');
                if (!$add) return true;
              
            }

            foreach ($this->deleted_primary_keys as $pk_value) {
                // First, handle cascade delete for hasOne and hasMany relationships
                if ($cascade) {
                    $cascade_delete_result = $this->processCascadeDelete($pk_value);
                    if (!$cascade_delete_result) {
                        // Error occurred during cascade delete
                        $this->save_results[] = [
                            'id' => $pk_value,
                            'action' => 'delete',
                            'result' => false,
                            'last_error' => $this->last_error
                        ];
                        Logs::set('DATABASE', 'Cascade Delete Error: ' . $this->last_error, 'ERROR');
                        return false;
                    }
                }

                // Then delete the parent record
                $success = $this->db->delete(
                    $this->table,
                    [$this->primary_key => $pk_value]
                );

                if (!$success) {
                    $this->error = true;
                    $this->last_error = "Failed to delete record with {$this->primary_key} = {$pk_value}: " . $this->db->last_error;
                    $this->save_results[] = [
                        'id' => $pk_value,
                        'action' => 'delete',
                        'result' => false,
                        'last_error' => $this->last_error
                    ];
                    Logs::set('DATABASE', 'Delete Error: ' . $this->last_error, 'ERROR');
                    return false;
                }

                $this->save_results[] = [
                    'id' => $pk_value,
                    'action' => 'delete',
                    'result' => true,
                    'last_error' => ''
                ];
            }

            // Hook afterDelete (chiamato dopo aver cancellato i record)
            if (!empty($this->deleted_primary_keys)) {
                ExtensionLoader::callHook($this->loaded_extensions, 'afterDelete', [$this->deleted_primary_keys]);

                // Call afterDelete method if it exists in the model
                $this->afterDelete($this->deleted_primary_keys);
            }

            $after_save_data = [];
            // 2. Processa le creazioni e modifiche
            foreach ($this->records_objects as $index => $record) {
                if ($this->hasRecordState($index)) {
                    $record['___action'] = $this->syncRecordActionFromState($index);
                } else {
                    $record['___action'] = $this->normalizeRecordActionForPersistence($record, null, $index);
                }

                if ($record['___action'] === null && $this->isNewRecordIndex($index) && $this->hasPersistableInsertDefaults()) {
                    $record['___action'] = 'insert';
                }

                $this->records_objects[$index]['___action'] = $record['___action'];
             
                // Process cascade relationships if enabled
                if ($record['___action'] !== null && $cascade) {
                   
                    $record = $this->processCascadeRelationships($index, $record); 
                    if ($record === null) {
                        // Error occurred during cascade save
                        $this->save_results[] = [
                            'id' =>  null,
                            'action' => '',
                            'result' => false,
                            'last_error' => $this->last_error
                        ];
                        Logs::set('DATABASE', 'Cascade Save Error: ' . $this->last_error, 'ERROR');
                        return false;
                    }
                }
                
                // Extract cascade results if present
                $cascade_results = $record['___cascade_results'] ?? null;
                unset($record['___cascade_results']);

                // Converti l'oggetto in array (escludendo i metadata)
                $data = [];
                foreach ($record as $key => $value) {
                    if ($key !== '___action') {
                        $data[$key] = $value;
                    }
                }
             
                // Prepara i dati
               
                $data = $this->prepareData($data, $index, $record['___action']);

                // Safety rule: normalize insert/edit again after data preparation.
                $record['___action'] = $this->normalizeRecordActionForPersistence($record, $data, $index);
                if ($record['___action'] === null && $this->isNewRecordIndex($index) && $data !== []) {
                    $record['___action'] = 'insert';
                }
                $this->records_objects[$index]['___action'] = $record['___action'];

                // Salta i record originali non modificati
                if ($record['___action'] === null) {
                    continue;
                }
                
                if ($record['___action'] === 'insert') {
                    // INSERT
                    // Se c'è una primary key e non è auto-increment, mantienila
                    if (isset($data[$this->primary_key]) && ($data[$this->primary_key] === '' || $data[$this->primary_key] === 0)) {
                        unset($data[$this->primary_key]);
                    }

                    if (!$this->db->insert($this->table, $data)) {
                        $this->error = true;
                        $this->last_error = "Failed to insert record at index {$index}: " . $this->db->last_error;
                        $this->save_results[] = [
                            'id' => null,
                            'action' => 'insert',
                            'result' => false,
                            'last_error' => $this->last_error
                        ];
                        Logs::set('DATABASE', 'Insert Error: ' . $this->last_error, 'ERROR');
                        return false;
                    }

                    // Aggiorna il record con l'ID generato
                    $insert_id = $this->db->insertId();
                    if ($insert_id) {
                        if (isset($this->cache[$insert_id])) {
                            unset($this->cache[$insert_id]);
                        }
                        $this->records_objects[$index][$this->primary_key] = $insert_id;
                    }

                    $result_entry = [
                        'id' => $insert_id ?: null,
                        'action' => 'insert',
                        'result' => true,
                        'last_error' => ''
                    ];

                    // Add cascade results (currently empty as belongsTo is no longer saved)
                    if ($cascade_results !== null) {
                        foreach ($cascade_results as $rel_alias => $rel_result) {
                            $result_entry[$rel_alias] = $rel_result;
                        }
                    }

                    // Process hasOne and hasMany relationships AFTER parent is saved (so we have the ID)
                    if ($cascade && isset($this->records_objects[$index]) && is_array($this->records_objects[$index])) {
                        $hasone_results = $this->processHasOneRelationships($index, $this->records_objects[$index]);
                        foreach ($hasone_results as $rel_alias => $rel_result) {
                            $result_entry[$rel_alias] = $rel_result;
                        }
                    }

                    $this->save_results[] = $result_entry;
                    $this->markRecordAsNew($index, false);
                    $this->refreshRecordStateAfterSuccessfulSave($index);
                    if ($insert_id) {
                        $data['___action'] = 'insert';
                        $data[$this->primary_key] = $insert_id;
                        $after_save_data[] = $data;
                    }
                } elseif ($record['___action'] === 'edit') {
                    // UPDATE
                    $pk_value = $record[$this->primary_key] ?? null;
                     
                    if ($pk_value === null || $pk_value === '' || $pk_value === 0 || $pk_value === '0') {
                        $this->error = true;
                        $this->last_error = "Cannot update record at index {$index}: missing primary key";
                        $this->save_results[] = [
                            'id' => null,
                            'action' => 'edit',
                            'result' => false,
                            'last_error' => $this->last_error
                        ];
                        Logs::set('DATABASE', 'Update Error: ' . $this->last_error, 'ERROR');
                        //return false;
                    } else {
                   
                        $success = $this->db->update(
                            $this->table,
                            $data,
                            [$this->primary_key => $pk_value],
                            1
                        );

                        if (!$success) {
                            $this->error = true;
                            $this->last_error = "Failed to update record with {$this->primary_key} = {$pk_value}: " . $this->db->last_error;
                            $this->save_results[] = [
                                'id' => $pk_value,
                                'action' => 'edit',
                                'result' => false,
                                'last_error' => $this->last_error
                            ];
                            Logs::set('DATABASE', 'Update Error: ' . $this->last_error, 'ERROR');
                          
                        } else {

                            $result_entry = [
                                'id' => $pk_value,
                                'action' => 'edit',
                                'result' => true,
                                'last_error' => ''
                            ];

                            // Add cascade results (currently empty as belongsTo is no longer saved)
                            if ($cascade_results !== null) {
                                foreach ($cascade_results as $rel_alias => $rel_result) {
                                    $result_entry[$rel_alias] = $rel_result;
                                }
                            }

                            // Process hasOne and hasMany relationships AFTER parent is updated (so we have the ID)
                            if ($cascade && isset($this->records_objects[$index]) && is_array($this->records_objects[$index])) {
                                $hasone_results = $this->processHasOneRelationships($index, $this->records_objects[$index]);
                                foreach ($hasone_results as $rel_alias => $rel_result) {
                                    $result_entry[$rel_alias] = $rel_result;
                                }
                            }

                            $this->save_results[] = $result_entry;
                            $this->markRecordAsNew($index, false);
                            $this->refreshRecordStateAfterSuccessfulSave($index);
                            $data['___action'] = 'edit';
                            $after_save_data[] = $data;
                        }
                        // Rimuovi cache
                        if (isset($this->cache[$pk_value])) {
                            unset($this->cache[$pk_value]);
                        }
                    }
                }
            }



            // Azzera le action ora che abbiamo fatto la copia
            if (is_array($this->records_objects)) {
                foreach ($this->records_objects as $index => $record) {
                    $this->records_objects[$index]['___action'] = null;
                }
            }

            // Pulisci l'array dei deleted
            $this->deleted_primary_keys = [];

            // Se non ci sono errori, resetta il save_results
            if ($this->last_error === '' && $reset_save_result) {
                // Setta i risultati
                $ids = [];
                foreach ($this->save_results as $result) {
                    $ids[] = $result['id'];
                }
                $ids = array_unique($ids);
                $this->setResultsByIds($ids);

                ExtensionLoader::callHook($this->loaded_extensions, 'afterSave', [$after_save_data, $this->save_results]);

                // Call afterSave method if it exists in the model
                $this->afterSave($after_save_data, $this->save_results);

            }

            return $this->last_error === '';

        } catch (\Exception $e) {
            $this->error = true;
            $this->last_error = $e->getMessage();
            $this->save_results[] = [
                'id' => null,
                'action' => 'exception',
                'result' => false,
                'last_error' => $this->last_error
            ];
            Logs::set('DATABASE', 'Save Error: ' . $this->last_error, 'ERROR');
            return false;
        }
    }

    /**
     * Get save results from last commit() call
     *
     * Returns an array with details about each operation performed
     *
     * @example
     * ```php
     * $posts->save();
     * $results = $posts->getCommitResults();
     * foreach ($results as $result) {
     *     echo "ID: {$result['id']}, Action: {$result['action']}, Result: " . ($result['result'] ? 'success' : 'failed') . "\n";
     *     if (!$result['result']) {
     *         echo "Error: {$result['last_error']}\n";
     *     }
     * }
     * ```
     *
     * @return array Array of operations: [['id' => int|null, 'action' => string, 'result' => bool, 'last_error' => string], ...]
     */
    public function getCommitResults(): array
    {
        return $this->save_results;
    }


    public function getLastInsertIds(): array {
        $ids = [];
        foreach ($this->save_results as $result) {
            if ($result['action'] === 'insert') {
                $ids[] = $result['id'];
            }
        }
        return $ids;
    }

    /**
     * Get the last insert ID save or store operation
     *
     * @return int The last insert ID
     */
    public function getLastInsertId(): int {
        if ($this->last_stored_record_id !== null) {
            return (int)$this->last_stored_record_id;
        }

        // Prefer IDs captured during save() operations.
        // This is more reliable than db->insertId() when additional INSERTs
        // (meta/relations/extensions) happen after the main record save.
        if (!empty($this->save_results)) {
            for ($i = count($this->save_results) - 1; $i >= 0; $i--) {
                $result = $this->save_results[$i] ?? null;
                if (!is_array($result)) {
                    continue;
                }
                $id = (int) ($result['id'] ?? 0);
                if ($id > 0) {
                    return $id;
                }
            }
        }

        if ($this->db === null) {
            return 0;
        }
        return (int)$this->db->insertId();
    }

    /**
     * Delete a record from the database
     * This method deletes the record immediately with cascade delete for hasOne and hasMany relationships
     *
     * IMPORTANT: This method automatically applies cascade delete for hasOne and hasMany relationships:
     * - hasOne/hasMany: Child records are deleted
     * - belongsTo: Related records are NOT deleted (only the parent)
     *
     * @example
     * ```php
     * if ($this->model->delete($id)) {
     *     return true;
     * } else {
     *     MessagesHandler::addError($this->model->getLastError());
     *     return false;
     * }
     * ```
     *
     * @param mixed|null $id Primary key of the record to delete. If null, deletes the first stored record
     * @return bool True if deletion was successful, false otherwise
     */
    public function delete($id = null): bool
    {
        $this->error = false;
        $this->last_error = '';
        if ($this->db === null) {
            $this->error = true;
            $this->last_error = 'No database connection';
            return false;
        }

        if ($id === null || $id === '') {
            if (!is_array($this->records_objects)) {
                throw new \Exception('Delete without id requires exactly one loaded record in records_objects.');
            }
            $record_count = count($this->records_objects);
            if ($record_count !== 1) {
                throw new \Exception('Delete without id requires exactly one loaded record in records_objects; found ' . $record_count . '.');
            }
            $record = reset($this->records_objects);
            if (!is_array($record) || !array_key_exists($this->primary_key, $record)) {
                throw new \Exception('Delete without id requires a loaded record with a valid primary key.');
            }
            $candidate_id = $record[$this->primary_key];
            if ($candidate_id === null || $candidate_id === '' || $candidate_id === 0) {
                throw new \Exception('Delete without id requires a loaded record with a valid primary key.');
            }
            $id = $candidate_id;
        }

        // Hook beforeDelete (chiamato prima di cancellare il record)
        $ris = true;
        if (ExtensionLoader::preventRecursion('beforeDelete')) {
            $ris = ExtensionLoader::callReturnHook($this->loaded_extensions, 'beforeDelete',  [true, [$id]]);
        } 
        ExtensionLoader::freeRecursion('beforeDelete');
        if (!$ris) return true;
        
        try {
            // First, handle cascade delete for hasOne and hasMany relationships
            $cascade_delete_result = $this->processCascadeDelete($id);
            if (!$cascade_delete_result) {
                // Error occurred during cascade delete
                return false;
            }

            // Then delete the parent record
            $success = $this->db->delete(
                $this->table,
                [$this->primary_key => $id]
            );

            if ($success) {
                unset($this->cache[$id]);

                // rimuovo il record eliminato dall'array
                if (is_array($this->records_objects)) {
                    foreach ($this->records_objects as $index => $record) {
                        if ($record[$this->primary_key] === $id) {
                            unset($this->records_objects[$index]);
                        }
                    }
                }

                // Hook afterDelete (chiamato dopo aver cancellato il record)
                if (ExtensionLoader::preventRecursion('afterDelete')) {
                    ExtensionLoader::callReturnHook($this->loaded_extensions, 'afterDelete',  [[$id]]);
                } 
                ExtensionLoader::freeRecursion('afterDelete');

                // Call afterDelete method if it exists in the model
                $this->afterDelete([$id]);
               
            }

            return $success;
        } catch (\Exception $e) {
            $this->error = true;
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Delete all stored records from the database
     *
     * @return bool True if all deletions were successful, false otherwise
     */
    public function deleteAll(): bool
    {
        $this->error = false;
        $this->last_error = '';

        if (!is_array($this->records_objects) || empty($this->records_objects)) {
            return true;
        }

        $ids = [];
        foreach ($this->records_objects as $record) {
            if (!is_array($record) || !array_key_exists($this->primary_key, $record)) {
                continue;
            }
            $candidate_id = $record[$this->primary_key];
            if ($candidate_id === null || $candidate_id === '' || $candidate_id === 0) {
                continue;
            }
            $ids[] = $candidate_id;
        }

        if (empty($ids)) {
            $this->error = true;
            $this->last_error = 'No record ids available for deletion.';
            return false;
        }

        foreach ($ids as $delete_id) {
            if (!$this->delete($delete_id)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clear the results cache
     *
     * Empties the cache of query results
     *
     * @example
     * ```php
     * $this->model->clearCache();
     * ```
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Prepare data before saving
     * Converts data to SQL format (DateTime to MySQL format, arrays to JSON)
     *
     * This method can be overridden in child classes to modify data before saving
     *
     * @param array $data The data to prepare
     * @return array The prepared data
     */
    protected function prepareData(array $data, ?int $recordIndex = null, ?string $action = null): array
    {
        $prepared = [];
        $rules = $this->getRules('sql');
        $dirtyFields = $recordIndex !== null ? $this->getRecordDirtyFields($recordIndex) : [];
        $staleFields = $recordIndex !== null ? $this->getRecordStaleFields($recordIndex) : [];
        $isInsert = $action === 'insert';

        foreach ($rules as $field_name => $rule) {
            $field_name = (string) $field_name;
            $includeField = isset($dirtyFields[$field_name]) || isset($staleFields[$field_name]);

            if (!$includeField && $isInsert && array_key_exists('default', $rule) && $rule['default'] !== null) {
                $includeField = true;
            }

            if (!$includeField && $this->isAlwaysPreparedSpecialField($field_name, $rule)) {
                $includeField = true;
            }

            if (!$includeField) {
                continue;
            }

            $value = array_key_exists($field_name, $data)
                ? $data[$field_name]
                : ($rule['default'] ?? null);

            // Use common prepareSingleFieldValue logic from DataFormattingTrait
            $prepared[$field_name] = $this->prepareSingleFieldValue($field_name, $value, $rule);
        }

        return $prepared;
    }

    private function isAlwaysPreparedSpecialField(string $fieldName, array $rule): bool
    {
        return isset($rule['save_value'])
            || (($rule['_auto_created_at'] ?? false) === true)
            || (($rule['_auto_updated_at'] ?? false) === true)
            || (($rule['_auto_created_by'] ?? false) === true)
            || (($rule['_auto_updated_by'] ?? false) === true)
            || strtolower(trim($fieldName)) === 'created_by'
            || strtolower(trim($fieldName)) === 'updated_by';
    }

    private function hasPersistableInsertDefaults(): bool
    {
        foreach ($this->getRules('sql') as $fieldName => $rule) {
            if (!is_array($rule) || $this->getRelationshipDefinitionService()->hasRelationship($this->getRuleBuilder(), (string) $fieldName)) {
                continue;
            }

            if (($rule['sql'] ?? true) === false) {
                continue;
            }

            if (array_key_exists('default', $rule) && $rule['default'] !== null) {
                return true;
            }
        }

        return false;
    }

}
