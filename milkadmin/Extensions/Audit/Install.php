<?php
namespace Extensions\Audit;

use App\Abstracts\AbstractInstallExtension;

!defined('MILK_DIR') && die();

/**
 * Audit Install Extension
 *
 * Example extension for Install classes.
 * Demonstrates lifecycle hooks for installation operations.
 *
 * @package Extensions\Audit
 * 
 * *Hook passivi "on"**: onInit, onSetup, onInstallGetHtmlModules, onInstallExecuteConfig, onInstallCheckData, onInstallDone
 * Metodi diretti: installExecute(), installUpdate(), shellUninstallModule()
 */
class Install extends AbstractInstallExtension
{
    /**
     * Hook called after Install initialization
     * Use this for early setup
     *
     * @return void
     */
    public function onInit(): void
    {
        // Extension initialized
        // At this point, module/model are not yet available
        // Example: Set up installation helpers or validators
    }

    /**
     * Hook called after setHandleInstall
     * Module, page, and model are now available
     *
     * @return void
     */
    public function onSetup(): void
    {
        // Install is now fully configured
        // Access module: $this->module
        // Access model: $this->module->getModel()
        // Access page: $this->module->getPage()

        // Example: Log installation setup
        // \App\Cli::info('Audit Install extension loaded for: ' . $this->module->getPage());
    }

    /**
     * Hook called after installGetHtmlModules
     * Can modify the installation form HTML
     *
     * @param string $html Current HTML
     * @param array $errors Current errors
     * @return string Modified HTML
     */
    public function onInstallGetHtmlModules(string $html, ?array $errors = []): string
    {
        // Example: Add custom installation form fields
        // $customField = '<div class="form-group">';
        // $customField .= '<label>Audit Settings</label>';
        // $customField .= '<input type="text" name="audit_config" class="form-control">';
        // $customField .= '</div>';
        // $html .= $customField;

        return $html;
    }

    /**
     * Hook called after installExecuteConfig
     * Can modify configuration data
     *
     * @param array $data Configuration data
     * @return array Modified data
     */
    public function onInstallExecuteConfig(array $data): array
    {
        // Example: Add audit-specific configuration
        // $data['audit_enabled'] = true;
        // $data['audit_log_path'] = MILK_DIR . '/logs/audit.log';

        return $data;
    }

    /**
     * Hook called after installExecute
     * Can perform additional installation tasks
     *
     * @param array $data Installation data
     * @return array Modified data
     */
    public function installExecute(array $data = []): array
    {
         $this->buildTable();
        // Example: Create audit log file or additional tables
        // $logPath = MILK_DIR . '/logs/audit.log';
        // if (!file_exists($logPath)) {
        //     file_put_contents($logPath, '');
        // }

        return $data;
    }

    /**
     * Hook called after installCheckData
     * Can add additional validation
     *
     * @param array $errors Current errors
     * @param array $data Form data
     * @return array Modified errors
     */
    public function onInstallCheckData(array $errors, array $data): array
    {
        // Example: Validate audit-specific fields
        // if (isset($data['audit_config']) && empty($data['audit_config'])) {
        //     $errors['audit_config'] = 'Audit configuration is required';
        // }

        return $errors;
    }

    /**
     * Hook called after installDone
     * Can modify completion message
     * Only on first install web?
     *
     * @param string $html Completion HTML
     * @return string Modified HTML
     */
    public function onInstallDone(string $html): string
    {
        // Example: Add custom completion message
        // $auditMessage = '<div class="alert alert-info">';
        // $auditMessage .= 'Audit logging has been enabled for this module.';
        // $auditMessage .= '</div>';
        // $html .= $auditMessage;

        return $html;
    }

    /**
     * Hook called after installUpdate
     * Can perform additional update tasks
     *
     * @param string $html Update HTML
     * @return string Modified HTML
     */
    public function installUpdate(string $html = ''): string
    {
        $this->buildTable();
        // Example: Log update action or migrate audit data
        // $updateMessage = '<div class="alert alert-success">';
        // $updateMessage .= 'Audit extension updated successfully.';
        // $updateMessage .= '</div>';
        // $html .= $updateMessage;

        return $html;
    }

    private function buildTable() {
        $model = $this->module->get()->getModel();
        $table = $model->getTable();
        $table_audit = $table."_audit"; 
        $schema = $model->getSchema();
        $schema->table = $table_audit;
        $schema->removePrimaryKeys();
        $schema->id('audit_id')                                    // PK della tabella audit
            ->int('audit_record_id', false)                          // ID del record originale
            ->string('audit_action', 10, false)   // 'create', 'update', 'delete';   
            ->timestamp('audit_timestamp', false, 'CURRENT_TIMESTAMP')  // ← TIMESTAMP
            ->int('audit_user_id', true);                

        // Crea la tabella
        if ($schema->exists()) {
            return $schema->modify();
        } else {
            return $schema->create();
        }

    }
}
