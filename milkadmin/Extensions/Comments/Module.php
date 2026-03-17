<?php
namespace Extensions\Comments;

use App\Abstracts\{AbstractModuleExtension, ModuleRuleBuilder};

!defined('MILK_DIR') && die();

/**
 * Comments Module Extension
 *
 * Registers the CommentsModel as an additional model for the module.
 * This allows the module to manage comments alongside its main entity.
 *
 * @package Extensions\Comments
 */
class Module extends AbstractModuleExtension
{
    /**
     * Hook called during module configuration
     * Registers the CommentsModel
     *
     * @param ModuleRuleBuilder $rule_builder The rule builder instance
     * @return void
     */
    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        // Register CommentsModel as an additional model
        // This allows the update command to create/update the comments table
        $rule_builder->addModels(['comment' => CommentsModel::class]);
    }
}
