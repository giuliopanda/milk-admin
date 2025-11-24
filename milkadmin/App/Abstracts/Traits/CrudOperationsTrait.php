<?php
namespace App\Abstracts\Traits;

use App\{Logs, MessagesHandler, Database\Query};


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
        if ($use_cache && isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $query = $this->query();
        $new_model = new static();

        // Propagate include_relationships from current model to new model
        if (!empty($this->include_relationships)) {
            $new_model->with($this->include_relationships);
        }

        $query->setModelClass($new_model);
        return  $query->where($this->primary_key . ' = ?', [$id])->limit(0, 1)->getRow();

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
            $id_array = array_map('_absint', explode(',', $ids));
        } else {
            $id_array = $ids;
        }
        $id_array = array_filter($id_array);

        if (empty($id_array)) {
            return null;
        }
        $id_name = $this->primary_key;

        // Build WHERE clause for multiple IDs
        $placeholders = str_repeat('?,', count($id_array) - 1) . '?';
        $query = $this->query();
        $new_model = new static();
        // Propagate include_relationships from current model to new model
        if (!empty($this->include_relationships)) {
            $new_model->with($this->include_relationships);
        }

        $query->setModelClass($new_model);
        $query->where($this->db->qn($id_name) . ' IN (' . $placeholders . ')', $id_array);

        return $query->getResults();
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
            $id_array = array_map('_absint', explode(',', $ids));
        } else {
            $id_array = $ids;
        }
        $id_array = array_filter($id_array);

        if (empty($id_array)) {
            return false;
        }
        $id_name = $this->primary_key;

        // Build WHERE clause for multiple IDs
        $placeholders = str_repeat('?,', count($id_array) - 1) . '?';
        $query = $this->query();
        $query->where($this->db->qn($id_name) . ' IN (' . $placeholders . ')', $id_array);
        $results = $query->getResults();
        $ris = $results->getRawData();
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
                if ($v['default'] != '') {
                    unset($rules[$k]['default']);
                }
            }
            $model->setRules($rules);
        }
        return $model;
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
        $model = new static();
        // Create an empty record in records_array
        $model->fill($data);
        return $model;
    }

    /**
     * Apply a query to the model and return the results
     *
     * @param Query $query The query to apply
     * @param array $params Optional parameters for the query
     * @return static Model instance with results
     */
    public function get(Query $query, ?array $params = []) : \App\Abstracts\AbstractModel|array|null|false {
        if ($query instanceof Query) {
            $query->setModelClass((new static()));
            return $query->getResults();
        } else {
            $model = new static();
            $results = $this->db->query($query, $params);
            $model->setResult($results);
            return $model;
        }


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
     * @param bool $cascade If true, saves hasOne relationships before saving parent record
     * @param bool $reset_save_result If true, reset save results after commit
     * @return bool True se tutte le operazioni hanno successo, false altrimenti
     */
    public function save(bool $cascade = true, $reset_save_result = true): bool
    {
        $this->error = false;
        $this->last_error = '';
        $this->save_results = [];
        $this->last_stored_record_id = null;
        // Se non c'è records_array, non ci sono modifiche da salvare
        if ($this->records_array === null) {
            return true;
        }
        $this->cleanEmptyRecords();

        try {
            // 1. Processa le eliminazioni
            foreach ($this->deleted_primary_keys as $pk_value) {
                // First, handle cascade delete for hasOne relationships
                if ($cascade && method_exists($this, 'processCascadeDelete')) {
                    $cascade_delete_result = $this->processCascadeDelete($pk_value);
                    if (!$cascade_delete_result) {
                        // Error occurred during cascade delete
                        $this->save_results[] = [
                            'id' => $pk_value,
                            'action' => 'delete',
                            'result' => false,
                            'last_error' => $this->last_error
                        ];
                        Logs::set('errors', 'ERROR', 'Cascade Delete Error: ' . $this->last_error);
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
                    Logs::set('errors', 'ERROR', 'Delete Error: ' . $this->last_error);
                    return false;
                }

                $this->save_results[] = [
                    'id' => $pk_value,
                    'action' => 'delete',
                    'result' => true,
                    'last_error' => ''
                ];
            }
          
            // 2. Processa le creazioni e modifiche
            foreach ($this->records_array as $index => $record) {
                // Salta i record originali non modificati
                if ($record['___action'] === null) {
                    continue;
                }
             
                // Process cascade relationships if enabled
                if ($cascade && method_exists($this, 'processCascadeRelationships')) {
                    $record = $this->processCascadeRelationships($index, $record); 
                    if ($record === null) {
                        // Error occurred during cascade save
                        $this->save_results[] = [
                            'id' => $record[$this->primary_key] ?? null,
                            'action' => $record['___action'],
                            'result' => false,
                            'last_error' => $this->last_error
                        ];
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
                $data = $this->prepareData($data);

                if ($record['___action'] === 'insert') {
                    // INSERT
                    // Se c'è una primary key e non è auto-increment, mantienila
                    if (isset($data[$this->primary_key]) && ($data[$this->primary_key] === null || $data[$this->primary_key] === '' || $data[$this->primary_key] === 0)) {
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
                        Logs::set('errors', 'ERROR', 'Insert Error: ' . $this->last_error);
                        return false;
                    }

                    // Aggiorna il record con l'ID generato
                    $insert_id = $this->db->insertId();
                    if ($insert_id) {
                        if (isset($this->cache[$insert_id])) {
                            unset($this->cache[$insert_id]);
                        }
                        $this->records_array[$index][$this->primary_key] = $insert_id;
                    }

                    $result_entry = [
                        'id' => $insert_id ?: null,
                        'action' => 'insert',
                        'result' => true,
                        'last_error' => ''
                    ];

                    // Add cascade results from belongsTo (processed before parent save)
                    if ($cascade_results !== null) {
                        foreach ($cascade_results as $rel_alias => $rel_result) {
                            $result_entry[$rel_alias] = $rel_result;
                        }
                    }

                    // Process hasOne relationships AFTER parent is saved (so we have the ID)
                    if ($cascade && method_exists($this, 'processHasOneRelationships')) {
                        $hasone_results = $this->processHasOneRelationships($index, $this->records_array[$index]);
                        foreach ($hasone_results as $rel_alias => $rel_result) {
                            $result_entry[$rel_alias] = $rel_result;
                        }
                    }

                    $this->save_results[] = $result_entry;

                } elseif ($record['___action'] === 'edit') {
                    // UPDATE
                    $pk_value = $record[$this->primary_key] ?? null;

                    if ($pk_value === null) {
                        $this->error = true;
                        $this->last_error = "Cannot update record at index {$index}: missing primary key";
                        $this->save_results[] = [
                            'id' => null,
                            'action' => 'edit',
                            'result' => false,
                            'last_error' => $this->last_error
                        ];
                        Logs::set('errors', 'ERROR', 'Update Error: ' . $this->last_error);
                        return false;
                    }

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
                        Logs::set('errors', 'ERROR', 'Update Error: ' . $this->last_error);
                        return false;
                    }

                    $result_entry = [
                        'id' => $pk_value,
                        'action' => 'edit',
                        'result' => true,
                        'last_error' => ''
                    ];

                    // Add cascade results from belongsTo (processed before parent save)
                    if ($cascade_results !== null) {
                        foreach ($cascade_results as $rel_alias => $rel_result) {
                            $result_entry[$rel_alias] = $rel_result;
                        }
                    }

                    // Process hasOne relationships AFTER parent is updated (so we have the ID)
                    if ($cascade && method_exists($this, 'processHasOneRelationships')) {
                        $hasone_results = $this->processHasOneRelationships($index, $this->records_array[$index]);
                        foreach ($hasone_results as $rel_alias => $rel_result) {
                            $result_entry[$rel_alias] = $rel_result;
                        }
                    }

                    $this->save_results[] = $result_entry;

                    // Rimuovi cache
                    if (isset($this->cache[$pk_value])) {
                        unset($this->cache[$pk_value]);
                    }
                }

                // Resetta l'action a null (record ora è "original")
                $this->records_array[$index]['___action'] = null;
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
            Logs::set('errors', 'ERROR', 'Save Error: ' . $this->last_error);
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
        return (int)$this->db->insertId();
    }

    /**
     * Delete a record from the database
     * This method deletes the record immediately with cascade delete for hasOne relationships
     *
     * IMPORTANT: This method automatically applies cascade delete for hasOne relationships:
     * - hasOne: Child records are deleted
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
     * @param mixed $id Primary key of the record to delete
     * @return bool True if deletion was successful, false otherwise
     */
    public function delete($id): bool
    {
        $this->error = false;
        $this->last_error = '';

        try {
            // First, handle cascade delete for hasOne relationships
            if (method_exists($this, 'processCascadeDelete')) {
                $cascade_delete_result = $this->processCascadeDelete($id);
                if (!$cascade_delete_result) {
                    // Error occurred during cascade delete
                    return false;
                }
            }

            // Then delete the parent record
            $success = $this->db->delete(
                $this->table,
                [$this->primary_key => $id]
            );

            if ($success) {
                unset($this->cache[$id]);
            }
            // rimuovo il record eliminato dall'array
            if (is_array($this->records_array)) {
                foreach ($this->records_array as $index => $record) {
                    if ($record[$this->primary_key] === $id) {
                        unset($this->records_array[$index]);
                    }
                }
            }
            return (bool)$success;
        } catch (\Exception $e) {
            $this->error = true;
            $this->last_error = $e->getMessage();
            return false;
        }
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
    protected function prepareData(array $data): array
    {
        $prepared = [];
        $rules = $this->getRules('sql');
        foreach ($rules as $field_name => $rule) {
            $value = $data[$field_name] ?? null;

            // Use common prepareSingleFieldValue logic from DataFormattingTrait
            $prepared[$field_name] = $this->prepareSingleFieldValue($field_name, $value, $rule);
        }

        return $prepared;
    }

    /**
     * Filter data based on object properties
     *
     * This method can be overridden in child classes to filter data
     *
     * @param array $data The data to filter
     * @return array The filtered data
     */
    protected function filterData(array $data): array {
         // da sovrascrivere nelle classi figlie
        return $data;
    }
}