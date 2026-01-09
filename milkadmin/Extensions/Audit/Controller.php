<?php
namespace Extensions\Audit;

use App\Abstracts\AbstractControllerExtension;
use App\Attributes\{RequestAction, AccessLevel};
use App\Response;
use App\{Route, Theme};

!defined('MILK_DIR') && die();

/**
 * Audit Controller Extension
 *
 * This extension adds audit-related functionality to controllers:
 * - Hello World example page
 * - Audit log viewing page
 * - Track user actions (via onHandleRoutes hook)
 *
 * @package Extensions\Audit
 */
class Controller extends AbstractControllerExtension
{
    /**
     * Hook called after controller initialization
     * Can be used to set up additional properties or configurations
     *
     * @return void
     */
    public function onInit(): void
    {
        // Extension initialized - you can set up properties here
    }

    /**
     * Hook called during controller's hookInit
     * Can be used to register additional hooks or routes
     *
     * @return void
     */
    public function onHookInit(): void
    {
        // Called during hookInit - can register additional hooks here
    }

    /**
     * Hook called before handling each route
     * Useful for logging, tracking, or pre-processing
     *
     * @return void
     */
    public function onHandleRoutes(): void
    {
        // Track user action (example - you could log this to database)
        $action = $_REQUEST['action'] ?? 'home';
        $page = $this->module->get()->getPage();

        // Example: Log to file or database
        // \App\Logs::set('AUDIT',  "User accessed: page={$page}, action={$action}");
    }

    /**
     * Audit trail page - shows complete audit log
     *
     * Accessible via: ?page=yourpage&action=audit
     * Access level: admin (administrators only)
     */
    #[RequestAction('audit')]
    #[AccessLevel('authorized:audit')]
    public function auditList()
    {
        // Get the parent model from the current module
        $module = $this->module->get();
        $model = $module->getModel();

        // Configure hook for AuditModel (same pattern as in Model.php)
        \App\Hooks::remove('AuditModel.configure');
        \App\Hooks::set('AuditModel.configure', function($rule) use ($model) {
          
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
       
        });

        // Create AuditModel instance
        $auditModel = new AuditModel();

        // Build the table
        $response = [
            'page' => $this->module->get()->getPage(),
            'title' => 'Audit Trail',
            'table_id' => 'idTableAudit'
        ];

        // Get all fields from audit model to apply text type and truncate
        $allFields = array_keys($auditModel->getRules());
        $auditSpecificFields = ['audit_id', 'audit_record_id', 'audit_action', 'audit_timestamp', 'audit_user_id'];

        $tableBuilder = \Builders\TableBuilder::create($auditModel, 'idTableAudit')
            // Riordina le colonne Audit
            ->reorderColumns([
                'audit_id',
                'audit_record_id',
                'audit_action',
                'audit_timestamp',
                'audit_user_id'
            ])

            // Configura audit_id
            ->field('audit_id')
                ->label('ID')
                ->colHeaderClass('bg-primary text-white')
                ->class('fw-bold bg-primary-subtle')

            // Configura audit_record_id (solo visualizzazione, senza link)
            ->field('audit_record_id')
                ->label('Record ID')
                ->colHeaderClass('bg-primary text-white')
                ->class('fw-bold bg-primary-subtle')

            // Configura audit_action
            ->field('audit_action')
                ->label('Action')
                ->colHeaderClass('bg-primary text-white')
                ->class('text-uppercase bg-primary-subtle')

            // Configura audit_timestamp
            ->field('audit_timestamp')
                ->label('Timestamp')
                ->colHeaderClass('bg-primary text-white')
                ->class('bg-primary-subtle')

            // Configura audit_user_id
            ->field('audit_user_id')
                ->label('User ID')
                ->colHeaderClass('bg-primary text-white')
                ->class('bg-primary-subtle');

        // Apply text type and truncate to all non-audit fields
        foreach ($allFields as $field) {
            if (!in_array($field, $auditSpecificFields)) {
                $tableBuilder->field($field)
                    ->type('text')
                    ->truncate(50);
            }
        }

        $response['html'] = $tableBuilder
            // Configura la colonna Action con i link
            ->setActions([
                'view' => [
                    'label' => 'View',
                    'link' => '?page=' . $this->module->get()->getPage() . '&action=audit-view&id=%audit_record_id%',
                    'class' => 'btn-sm btn-info'
                ]
            ])

            ->orderBy('audit_id', 'DESC')
            ->render();

        \App\Response::render(__DIR__ . '/Views/list_page.php', $response);
    }

    /**
     * Audit view page - shows complete history of a single record
     *
     * Accessible via: ?page=yourpage&action=audit-view&id=recordid
     * Access level: admin (administrators only)
     */
    #[RequestAction('audit-view')]
    #[AccessLevel('authorized:audit')]
    public function auditView()
    {
        // Load JavaScript for audit view
        Theme::set('javascript', Route::url().'/Extensions/Audit/Assets/audit.js');

        $record_id = $_REQUEST['id'] ?? 0;

        if (!$record_id) {
            \App\Response::themePage('default', '<div class="alert alert-danger">No record ID specified</div>');
            return;
        }

        // Get the parent model from the current module
        $module = $this->module->get();
        $model = $module->getModel();

        // Configure hook for AuditModel
        \App\Hooks::remove('AuditModel.configure');
        \App\Hooks::set('AuditModel.configure', function($rule) use ($model) {
            $table = $model->getTable();
            $table_audit = $table . "_audit";

            $rule->table($table_audit);
            $model->copyRules($rule);
            $rule->removePrimaryKeys();

            $rule->id('audit_id')
                ->int('audit_record_id')->nullable(false)
                ->string('audit_action', 10)->nullable(false)
                ->timestamp('audit_timestamp')->nullable(false)->default('CURRENT_TIMESTAMP')
                ->int('audit_user_id')->nullable(true);
        });

        // Create AuditModel instance
        $auditModel = new AuditModel();

        // Get current record (if exists)
        $primaryKey = $model->getPrimaryKey();
        $currentRecord = $model->where("$primaryKey = ?", [$record_id])->getRow();
        $currentRecord = $currentRecord->getFormattedData();
        if (isset($currentRecord[0])) {
            $currentRecord = $currentRecord[0];
        } else {
            $currentRecord = null;
        }
       
        // Get all audit records for this ID, ordered by timestamp DESC
        $auditRecords = $auditModel->where('audit_record_id = ?', [$record_id])
            ->order('audit_timestamp', 'DESC')
            ->getResults();
        $auditRecords = $auditRecords->getFormattedData();
        // Check if record was deleted (last action is delete)
        $isDeleted = false;
        $deleteInfo = null;
        if (count($auditRecords) > 0 && $auditRecords[0]->audit_action === 'delete') {
            $isDeleted = true;
            $deleteInfo = $auditRecords[0];
            // Remove delete record from list as it doesn't have field data
            array_shift($auditRecords);
        }

        // Get all fields from the model (excluding primary key)
        $fields = [];
        $primaryKey = $model->getPrimaryKey();

        foreach ($model->getRules() as $fieldName => $fieldRule) {
            if ($fieldName !== $primaryKey) {
                $fields[$fieldName] = [
                    'label' => $fieldRule['label'] ?: $fieldName
                ];
            }
        }

        $response = [
            'page' => $this->module->get()->getPage(),
            'title' => 'Audit History - Record #' . $record_id,
            'record_id' => $record_id,
            'currentRecord' => $currentRecord,
            'auditRecords' => $auditRecords,
            'fields' => $fields,
            'isDeleted' => $isDeleted,
            'deleteInfo' => $deleteInfo
        ];

        \App\Response::render(__DIR__ . '/Views/view_page.php', $response);
    }

    /**
     * Restore a previous version from audit
     *
     * Accessible via: ?page=yourpage&action=audit-restore&id=recordid&audit_id=auditid
     * Access level: admin (administrators only)
     */
    #[RequestAction('audit-restore')]
    #[AccessLevel('admin')]
    public function auditRestore()
    {
        $record_id = $_REQUEST['id'] ?? 0;
        $audit_id = $_REQUEST['audit_id'] ?? 0;

        if (!$record_id || !$audit_id) {
            \App\Response::json([
                'success' => false,
                'toast' => [
                    'message' => 'Missing record ID or audit ID',
                    'type' => 'danger'
                ]
            ]);
        }

        // Get the parent model from the current module
        $module = $this->module->get();
        $model = $module->getModel();
        $primaryKey = $model->getPrimaryKey();

        // Configure hook for AuditModel
        \App\Hooks::remove('AuditModel.configure');
        \App\Hooks::set('AuditModel.configure', function($rule) use ($model) {
            $table = $model->getTable();
            $table_audit = $table . "_audit";

            $rule->table($table_audit);
            $model->copyRules($rule);
            $rule->removePrimaryKeys();

            $rule->id('audit_id')
                ->int('audit_record_id')->nullable(false)
                ->string('audit_action', 10)->nullable(false)
                ->timestamp('audit_timestamp')->nullable(false)->default('CURRENT_TIMESTAMP')
                ->int('audit_user_id')->nullable(true);
        });

        // Create AuditModel instance
        $auditModel = new AuditModel();

        // Get the audit record to restore (get raw data, not formatted)
        $auditRecordResult = $auditModel->where('audit_id = ?', [$audit_id])->getRow();

        if (!$auditRecordResult || count($auditRecordResult) == 0) {
            \App\Response::json([
                'success' => false,
                'toast' => [
                    'message' => 'Audit record not found',
                    'type' => 'danger'
                ]
            ]);
        }

        // Get raw data - extract from the model's records_array
        $auditData = $auditRecordResult->getRawData();
        
        if (empty($auditData)) {
            \App\Response::json([
                'success' => false,
                'toast' => [
                    'message' => 'No audit data found',
                    'type' => 'danger'
                ]
            ]);
        }
        $auditRecord = $auditData[0]; // First record from the array

        // Prepare data from audit record (exclude audit-specific and metadata fields)
        $restoreData = [];
        $excludeFields = [
            'audit_id', 'audit_record_id', 'audit_action', 'audit_timestamp', 'audit_user_id',
            '___action' // Exclude internal field
        ];

        foreach ($auditRecord as $field => $value) {
            if (!in_array($field, $excludeFields)) {
                $restoreData[$field] = $value;
            }
        }

        // Add the primary key for INSERT or UPDATE
        $restoreData[$primaryKey] = $record_id;

        // Temporarily disable audit tracking to avoid creating audit entry for restore
        \App\Hooks::remove('model.afterSave');

        try {
            // Use fill() to populate the model and save
            $model->fill($restoreData);
            $result = $model->save();

            if ($result) {
                // Success - reload the audit view to get updated content
                // Simulate the auditView request to get fresh HTML
                $currentRecord = $model->where("$primaryKey = ?", [$record_id])->getRow();
                $currentRecord = $currentRecord->getFormattedData();
                if (isset($currentRecord[0])) {
                    $currentRecord = $currentRecord[0];
                } else {
                    $currentRecord = null;
                }

                // Configure AuditModel for getting updated records
                \App\Hooks::remove('AuditModel.configure');
                \App\Hooks::set('AuditModel.configure', function($rule) use ($model) {
                    $table = $model->getTable();
                    $table_audit = $table . "_audit";

                    $rule->table($table_audit);
                    $model->copyRules($rule);
                    $rule->removePrimaryKeys();

                    $rule->id('audit_id')
                        ->int('audit_record_id')->nullable(false)
                        ->string('audit_action', 10)->nullable(false)
                        ->timestamp('audit_timestamp')->nullable(false)->default('CURRENT_TIMESTAMP')
                        ->int('audit_user_id')->nullable(true);
                });

                $auditModel = new AuditModel();
                $auditRecords = $auditModel->where('audit_record_id = ?', [$record_id])
                    ->order('audit_timestamp', 'DESC')
                    ->getResults();
                $auditRecords = $auditRecords->getFormattedData();

                // Check if record was deleted (last action is delete)
                $isDeleted = false;
                $deleteInfo = null;
                if (count($auditRecords) > 0 && $auditRecords[0]->audit_action === 'delete') {
                    $isDeleted = true;
                    $deleteInfo = $auditRecords[0];
                    // Remove delete record from list as it doesn't have field data
                    array_shift($auditRecords);
                }

                // Get fields
                $fields = [];
                $primaryKey = $model->getPrimaryKey();

                foreach ($model->getRules() as $fieldName => $fieldRule) {
                    if ($fieldName !== $primaryKey) {
                        $fields[$fieldName] = [
                            'label' => $fieldRule['label'] ?: $fieldName
                        ];
                    }
                }

                // Set missing variables for view
                $page = $this->module->get()->getPage();
                $title = 'Audit History - Record #' . $record_id;

                // Capture the view output
                ob_start();
                include __DIR__ . '/Views/view_page.php';
                $viewHtml = ob_get_clean();

                // Return JSON response with modal close, toast, and updated HTML
                \App\Response::json([
                    'success' => true,
                    'modal' => [
                        'action' => 'hide'
                    ],
                    'toast' => [
                        'message' => 'Record restored successfully',
                        'type' => 'success'
                    ],
                    'element' => [
                        'selector' => '.card',
                        'innerHTML' => $viewHtml
                    ]
                ]);
            } else {
                // Save failed
                \App\Response::json([
                    'success' => false,
                    'modal' => [
                        'action' => 'hide'
                    ],
                    'toast' => [
                        'message' => 'Failed to restore record: Save operation failed',
                        'type' => 'danger'
                    ]
                ]);
            }

        } catch (\Exception $e) {
            // Exception during restore
            \App\Response::json([
                'success' => false,
                'modal' => [
                    'action' => 'hide'
                ],
                'toast' => [
                    'message' => 'Failed to restore record: ' . $e->getMessage(),
                    'type' => 'danger'
                ]
            ]);
        }
    }
}
