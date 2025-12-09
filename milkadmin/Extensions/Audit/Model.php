<?php
namespace Extensions\Audit;

use App\Abstracts\{AbstractModelExtension, RuleBuilder};
use App\{Get, Hooks};

!defined('MILK_DIR') && die();

/**
 * Audit Model Extension
 *
 * Automatically tracks all changes to model records in a separate audit table.
 * Creates a complete audit trail with:
 * - Full record snapshots on insert/edit/delete
 * - User who performed the action
 * - Timestamp of the action
 * - Action type (insert, edit, delete)
 *
 * @package Extensions\Audit
 */
class Model extends AbstractModelExtension
{
    /**
     * Maximum number of audit records to keep per record_id
     * Set to 0 for unlimited audit history
     *
     * @var int
     */
    protected static $maxAuditRecords = 0;

    /**
     * Session time window in seconds for consolidating audit records
     * If the same user modifies the same record multiple times within this window,
     * intermediate versions will be removed (keeping only the most recent)
     * Default: 5 hours (18000 seconds)
     *
     * @var int
     */
    protected static $sessionTimeWindow = 18000; // 5 hours in seconds

    /**
     * Set the maximum number of audit records to keep per record
     *
     * @param int $limit Maximum number of records (0 = unlimited)
     * @return void
     */
    public static function setMaxAuditRecords(int $limit): void
    {
        self::$maxAuditRecords = max(0, $limit);
    }

    /**
     * Get the current maximum audit records limit
     *
     * @return int
     */
    public static function getMaxAuditRecords(): int
    {
        return self::$maxAuditRecords;
    }

    /**
     * Set the session time window for consolidating audit records
     *
     * @param int $seconds Time window in seconds (default: 18000 = 5 hours)
     * @return void
     */
    public static function setSessionTimeWindow(int $seconds): void
    {
        self::$sessionTimeWindow = max(0, $seconds);
    }

    /**
     * Get the current session time window
     *
     * @return int
     */
    public static function getSessionTimeWindow(): int
    {
        return self::$sessionTimeWindow;
    }

    /**
     * Hook called after the Model's configure() method
     * Extension point for future audit-related configurations
     *
     * @param RuleBuilder $rule_builder The rule builder instance
     * @return void
     */
    public function configure(RuleBuilder $rule_builder): void
    {
        // No additional fields needed - audit data is stored in audit table only
    }


    /**
     * Hook chiamato dopo il salvataggio dei dati (INSERT e EDIT)
     * Salva i dati nell'audit trail
     *
     * @param array $records_array Array dei record salvati
     * @param array $save_results Array con i risultati del salvataggio (id, action, result)
     * @return void
     */
    public function afterSave($records_array, $save_results)
    {
        Hooks::remove('AuditModel.configure');
        Hooks::set('AuditModel.configure', [$this, 'configureModelRule']);

        $parent_model   = $this->model->get();
        $primary_key    = $parent_model->getPrimaryKey();
        $table_audit    = $parent_model->getTable() . "_audit";
        $auditModel     = new AuditModel();
        $currentUserId  = Get::make('Auth')->getUser()->id ?? 0;

        foreach ($save_results as $result) {

            if (!in_array($result['action'], ['insert', 'edit'])) {
                continue;
            }

            $recordId = $result['id'];

            // Trova i dati del record effettivamente salvato
            $recordData = null;
            foreach ($records_array as $record) {
                if (($record[$primary_key] ?? null) == $recordId) {
                    $recordData = $record;
                    break;
                }
            }
            if ($recordData === null) {
                continue;
            }

            // Prepara il record per l'audit
            $audit_record = $recordData;

            unset($audit_record[$primary_key]);
            unset($audit_record['___action']);

            $audit_record['audit_action']     = $result['action'];
            $audit_record['audit_record_id']  = $recordId;
            $audit_record['audit_timestamp']  = time();
            $audit_record['audit_user_id']    = $currentUserId;

            // ----- STEP 1: carica A e B -----
            $lastTwo = $auditModel
                ->where('audit_record_id = ?', [$recordId])
                ->order('audit_timestamp', 'DESC')
                ->limit(0, 2)
                ->getResults();

            $A = null; // ultima riga
            $B = null; // penultima

            if ($lastTwo && count($lastTwo) >= 1) {
                $A = (object)$lastTwo->getRawData()[0];
            }
            if ($lastTwo && count($lastTwo) >= 2) {
                $B = (object)$lastTwo->getRawData()[1];
            }

            // ----- STEP 2: salva la nuova riga N -----
            if (!$auditModel->store($audit_record)) {
                die("Errore salvataggio audit: " . $auditModel->getLastError());
            }

            $newId = $auditModel->getLastInsertId();
            $N = $auditModel->where('audit_id = ?', [$newId])->getRow();

            // ----- STEP 3: Verifica se N è IDENTICA ad A -----
            if ($A) {

                $fieldsToCheck = array_keys((array)$audit_record);

                $changesFound = false;

                foreach ($fieldsToCheck as $field) {
                    $nVal = $N->$field ?? null;
                    $aVal = $A->$field ?? null;

                    if ($nVal != $aVal) {
                        $changesFound = true;
                        break;
                    }
                }

                // Se NON ci sono cambiamenti → cancella N (ma solo se stessa sessione)
                $sameUser = ($N->audit_user_id == $A->audit_user_id);
                $timeDiff = $N->audit_timestamp - $A->audit_timestamp;

                if (!$changesFound && $sameUser && $timeDiff < self::$sessionTimeWindow) {

                    $del = new AuditModel();
                    $del->delete($N->audit_id);

                    // Vai al prossimo record
                    continue;
                }
            }

            // ----- STEP 4: Consolidamento sessione A + B -----
            if ($A && $B) {

                $sameUser = ($N->audit_user_id == $A->audit_user_id &&
                            $N->audit_user_id == $B->audit_user_id);

                if ($sameUser) {

                    $timeDiff = $N->audit_timestamp - $B->audit_timestamp;

                    if ($timeDiff < self::$sessionTimeWindow) {

                        // Cancella A (modifica intermedia)
                        $del = new AuditModel();
                        $del->delete($A->audit_id);
                    }
                }
            }

            // ----- STEP 5: cleanup vecchi record -----
            $this->cleanupOldAuditRecords($recordId, $table_audit);
        }
    }


    /**
     * Hook chiamato prima di cancellare record
     * Permette di preparare o validare prima della cancellazione
     *
     * @param array $ids Array degli ID da cancellare
     * @return void
     */
    public function beforeDelete($ids) {
        // Questo hook può essere usato per validazioni o preparazioni
        // Per ora non facciamo nulla, ma è disponibile per usi futuri
    }

    /**
     * Hook chiamato dopo aver cancellato record
     * Salva i record cancellati nell'audit trail
     *
     * @param array $ids Array degli ID cancellati
     * @return void
     */
    public function afterDelete($ids) {
        // Configura l'hook per il modello audit
        Hooks::remove('AuditModel.configure');
        Hooks::set('AuditModel.configure', [$this, 'configureModelRule']);

        $parent_model = $this->model->get();
        $table_audit = $parent_model->getTable() . "_audit";
        $new_model = new AuditModel();
        $current_user_id = Get::make('Auth')->getUser()->id ?? 0;

        // Processa tutti gli ID cancellati
        foreach ($ids as $deleted_id) {
            // Per i delete, salva solo i campi audit (non abbiamo più i dati del record)
            $audit_record = [
                'audit_action' => 'delete',
                'audit_record_id' => $deleted_id,
                'audit_timestamp' => time(),
                'audit_user_id' => $current_user_id
            ];

            // Salva nell'audit
            if (!$new_model->store($audit_record)) {
                die('Errore salvataggio audit (delete): ' . $new_model->getLastError());
            }

            // Cleanup old audit records if limit is set
            $this->cleanupOldAuditRecords($deleted_id, $table_audit);
        }
    }

    /**
     * Cleanup old audit records for a specific record_id
     * Keeps only the most recent N EDIT records based on $maxAuditRecords setting
     * IMPORTANT: The initial INSERT record is ALWAYS preserved and NOT counted in the limit
     *
     * @param int $record_id The record_id to cleanup
     * @param string $table_audit The audit table name
     * @return void
     */
    protected function cleanupOldAuditRecords(int $record_id, string $table_audit): void
    {
        // Skip if unlimited audit history (0 = unlimited)
        if (self::$maxAuditRecords <= 0) {
            return;
        }

        // Configure hook for AuditModel
        Hooks::remove('AuditModel.configure');
        Hooks::set('AuditModel.configure', [$this, 'configureModelRule']);

        $auditModel = new AuditModel();

        // Count only EDIT records for this record_id (INSERT is never counted or deleted)
        $count = $auditModel
            ->where('audit_record_id = ?', [$record_id])
            ->where('audit_action = ?', ['edit'])
            ->getResults()
            ->count();

        // If we exceed the limit, delete the oldest EDIT records
        if ($count > self::$maxAuditRecords) {
            $toDelete = $count - self::$maxAuditRecords;

            // Get the oldest EDIT audit_ids to delete (excluding INSERT and DELETE)
            $oldRecords = $auditModel
                ->where('audit_record_id = ?', [$record_id])
                ->where('audit_action = ?', ['edit'])
                ->order('audit_timestamp', 'ASC')
                ->limit(0, $toDelete)
                ->getResults();

            if ($oldRecords && count($oldRecords) > 0) {
                $idsToDelete = [];
                foreach ($oldRecords as $record) {
                    $idsToDelete[] = $record->audit_id;
                }

                // Delete old EDIT records directly from database to avoid triggering hooks
                if (!empty($idsToDelete)) {
                    $db = Get::make('Database');
                    $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
                    $sql = "DELETE FROM `{$table_audit}` WHERE `audit_id` IN ({$placeholders})";
                    $db->query($sql, $idsToDelete);
                }
            }
        }
    }

    /**
     * Hook called to configure the audit model rules
     * Copies all field rules from the main model to create an audit table
     * with the same schema but with suffix '_audit' and audit-specific fields
     *
     * @param RuleBuilder $rule The rule builder for the audit model
     * @return void
     */
    public function configureModelRule($rule): void
    {
        
        $model = $this->model->get();
        $table = $model->getTable();
        $table_audit = $table."_audit";

        // Set audit table name
        $rule->table($table_audit);

        // Copy all rules from main model
        $model->copyRules($rule);

        // Remove primary keys from copied fields
        $rule->removePrimaryKeys();

        // Add audit-specific fields
        $rule->id('audit_id')                                    // New PK for audit table
            ->int('audit_record_id')->nullable(false)            // ID of the original record
            ->string('audit_action', 10)->nullable(false)        // 'insert', 'edit', 'delete'
            ->timestamp('audit_timestamp')->nullable(false)->default('CURRENT_TIMESTAMP')  // When action occurred
            ->int('audit_user_id')->nullable(true);              // User who performed the action
    }

}
