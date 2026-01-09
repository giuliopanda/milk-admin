<?php
namespace Extensions\Audit;

use App\Abstracts\{AbstractModuleExtension, ModuleRuleBuilder};
use App\{Theme, Route, Hooks, Permissions, Get};

!defined('MILK_DIR') && die();

/**
 * Audit Module Extension
 *
 * This extension adds audit-related functionality to modules:
 * - Adds header navigation links to audit pages
 * - Configures audit settings
 * - Tracks module initialization
 *x
 * @package Extensions\Audit
 */
class Module extends AbstractModuleExtension
{

    private $model = null;
    /**
     * Hook called during module configuration
     * Extends module configuration by adding header links to audit pages
     *
     * @param ModuleRuleBuilder $rule_builder The rule builder instance
     * @return void
     */
    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        // Avvio l'utente per averne i permessi
        Get::make('Auth');
        $page = $this->module->getPage();
     
        if (Permissions::check($page.".audit")) {
            $rule_builder
                ->addHeaderLink(
                    'Audit Trail',
                    "?page={$page}&action=audit",
                    'bi bi-database'
                )
                ->headerPosition('top-left');
   
        }
        $rule_builder->addPermissions(['audit' => 'Audit']);
        // You can also add additional configuration
        // $rule_builder->setJs('/Assets/audit.js');
        // $rule_builder->setCss('/Assets/audit.css');
    }

    /**
     * Hook called after module bootstrap
     * Initialize audit tracking system
     *
     * @return void
     */
    public function bootstrap(): void
    {
        // Initialize audit tracking
        // Example: Set up audit logger
        // Logs::set('AUDIT', 'Audit extension loaded for module: ' . $this->module->getPage());

        // You can access module properties
        $page = $this->module->getPage();
        $title = $this->module->getTitle();
        require_once __DIR__ . '/AuditModel.php';

        // Configure maximum audit records to keep per record_id
        // Set to 0 for unlimited audit history (default)
        // Example: Keep only last 10 audit records per record
        // \Extensions\Audit\Model::setMaxAuditRecords(10);

        // Example: Register global audit hook
        // \App\Hooks::set('model_save', [$this, 'trackModelSave']);
    }

    /**
     * Hook called after module init (page-specific)
     * Load page-specific audit functionality
     *
     * @return void
     */
    public function init(): void
    {
        // Load audit-specific JavaScript/CSS for this module page
        // Theme::set('javascript', Route::url() . '/Extensions/Audit/Assets/audit-page.js');
        // Theme::set('styles', Route::url() . '/Extensions/Audit/Assets/audit-page.css');

        // Example: Track page view
        $page = $this->module->getPage();
        $action = $_REQUEST['action'] ?? 'home';
        // Logs::set('AUDIT', "Page accessed: {$page}, action: {$action}");
    }

    /**
     * Example method: Track model save operations
     * This would be called by a hook registered in bootstrap()
     *
     * @param object $model The model being saved
     * @param array $data The data being saved
     * @return void
     */
    public function trackModelSave($model, $data): void
    {
        // Log the save operation
        // Logs::set('AUDIT', 'Model saved: ' . get_class($model));
    }

    
}
