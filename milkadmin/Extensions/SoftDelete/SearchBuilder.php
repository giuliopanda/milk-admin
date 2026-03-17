<?php
namespace Extensions\SoftDelete;

use App\Abstracts\AbstractSearchBuilderExtension;

!defined('MILK_DIR') && die();

/**
 * Soft Delete SearchBuilder Extension
 *
 * Adds soft delete filter to SearchBuilder:
 * - Automatically adds an action list filter to show/hide deleted items
 * - Filter options: 'active' (default) and 'deleted'
 *
 * @package Extensions\SoftDelete
 */
class SearchBuilder extends AbstractSearchBuilderExtension
{
    /**
     * Label for the filter
     * @var string
     */
    protected string $label = 'Status:';

    /**
     * Filter name/identifier
     * @var string
     */
    protected string $filter_name = 'show_deleted';

  
    /**
     * Hook called during builder configuration
     * Adds the soft delete filter as an action list
     *
     * @param object $builder The builder instance
     * @return void
     */
    public function configure(object $builder): void
    {
        $builder->actionList($this->filter_name)
            ->label($this->label)
            ->options(['active' => 'Active', 'deleted' => 'Deleted'])
            ->selected('active');
    }
}
