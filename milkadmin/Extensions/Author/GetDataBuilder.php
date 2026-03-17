<?php
namespace Extensions\Author;

use App\Abstracts\AbstractGetDataBuilderExtension;
use App\{Get, Permissions};

!defined('MILK_DIR') && die();

/**
 * Author GetDataBuilder Extension
 *
 * Adds automatic filtering for users with "manage_own_only" permission:
 * - Filters records to show only those created by the current user
 * - Applies only when user has the "manage_own_only" permission
 *
 * @package Extensions\Author
 */
class GetDataBuilder extends AbstractGetDataBuilderExtension
{
    /**
     * Hook called during builder configuration
     * Sets up filtering for users with manage_own_only permission
     *
     * @param object $builder The builder instance
     * @return void
     */
    public function configure(object $builder): void
    {
        // Initialize Auth to ensure user permissions are loaded
        Get::make('Auth');

        // Administrators always see all records - skip filtering
        if (Permissions::check('_user.is_admin')) {
            return;
        }

        // Get current page from builder
        $page = $builder->getPage();

        // Check if user has "manage_own_only" permission for this module
        if ($page && Permissions::check($page . '.manage_own_only')) {
            // Get current user
            $user = Get::make('Auth')->getUser();
            $current_user_id = $user->id ?? 0;

            if ($current_user_id > 0) {
                // Get database instance for query building
                $db = Get::db();

                // Add filter to show only records created by current user
                $builder->where($db->qn('created_by') . ' = ?', [$current_user_id]);
            }
        }
    }

    /**
     * Hook called before data retrieval
     * Can be used for additional filtering logic if needed
     *
     * @return void
     */
    public function beforeGetData(): void
    {
        // Additional filtering logic can be added here if needed
    }

    /**
     * Hook called after data retrieval
     * Can be used to modify data array if needed
     *
     * @param array $data The data array
     * @return array Modified data array
     */
    public function afterGetData(array $data): array
    {
        // Data modification logic can be added here if needed
        return $data;
    }
}
