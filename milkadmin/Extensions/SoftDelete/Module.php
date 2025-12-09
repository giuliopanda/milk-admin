<?php
namespace Extensions\SoftDelete;

use App\Abstracts\{AbstractModuleExtension, ModuleRuleBuilder};

/**
 * Soft Delete Module Extension
 *
 * Adds UI components for managing soft deleted records:
 * - Header link to view deleted items
 * - Permissions for viewing/restoring/deleting
 * - Badge showing count of deleted items
 *
 * @package Extensions\SoftDelete
 */
class Module extends AbstractModuleExtension
{
    /**
     * Hook called during module configuration
     * Adds header link to view deleted items and configures permissions
     *
     * @param ModuleRuleBuilder $rule_builder
     * @return void
     */
    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        // Add permissions for soft delete operations
        $rule_builder->addPermissions(['soft_deleted' => 'Soft Deleted']);
    }
}