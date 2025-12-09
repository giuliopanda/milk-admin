<?php
namespace Extensions\Author;

use App\Abstracts\{AbstractModuleExtension, ModuleRuleBuilder};

!defined('MILK_DIR') && die();

/**
 * Author Module Extension
 *
 * Adds created_by tracking to modules with configurable display options.
 * Can display either username or email of the creator.
 *
 * @package Extensions\Author
 */
class Module extends AbstractModuleExtension
{
    /**
     * Display username instead of email
     * @var bool
     */
    protected $show_username = true;

    /**
     * Display email instead of username
     * @var bool
     */
    protected $show_email = false;

    /**
     * Show author column in list views
     * @var bool
     */
    protected $show_in_list = true;

    /**
     * Hook called during module configuration
     *
     * @param ModuleRuleBuilder $rule_builder The rule builder instance
     * @return void
     */
    public function configure(ModuleRuleBuilder $rule_builder): void
    {
        // Add permission for managing only own records
        $rule_builder->addPermissions([
            'manage_own_only' => 'Manage Only Own Records'
        ]);
    }

    /**
     * Get the display field based on configuration
     *
     * @return string 'username' or 'email'
     */
    public function getDisplayField(): string
    {
        return $this->show_email ? 'email' : 'username';
    }

    /**
     * Check if should show in list view
     *
     * @return bool
     */
    public function shouldShowInList(): bool
    {
        return $this->show_in_list;
    }
}
