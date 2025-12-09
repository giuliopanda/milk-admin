<?php
namespace Extensions\Author;

use App\Abstracts\AbstractFormBuilderExtension;
use App\{Get, Permissions, Route};

!defined('MILK_DIR') && die();

/**
 * Author FormBuilder Extension
 *
 * Adds access control for users with "manage_own_only" permission:
 * - Prevents editing records not created by the current user
 * - Shows alert when viewing own records
 * - Redirects unauthorized access attempts
 *
 * @package Extensions\Author
 */
class FormBuilder extends AbstractFormBuilderExtension
{
    /**
     * Show alert when user is editing own record
     * @var bool
     */
    protected bool $show_author_info = true;

    /**
     * Hook called during form configuration
     * Checks access permissions and shows author information
     *
     * @param object $builder The builder instance
     * @return void
     */
    public function configure(object $builder): void
    {
        // Initialize Auth to ensure user permissions are loaded
        Get::make('Auth');

        // Administrators can edit all records and modify author field
        // The author field configuration is done in beforeRender()
        if (Permissions::check('_user.is_admin')) {
            $model = $builder->getModel();
            $builder->addField(
            'created_by', 'select',
                ['label' => 'Author',
                'options' => $this->getUsersList(),
                'row_value' => $model->created_by
                ]
            );
            return;
        }
       

        // Get the model
        $model = $builder->getModel();

        // Get current page
        $page = $builder->getPage();

        // Check if user has "manage_own_only" permission for this module
        if ($page && Permissions::check($page . '.manage_own_only')) {
            // Get current user
            $user = Get::make('Auth')->getUser();
            $current_user_id = $user->id ?? 0;

            if ($current_user_id <= 0) {
                return;
            }

            // Check if we're editing an existing record
            $primary_key = $model->getPrimaryKey();
            $record_id = $model->$primary_key ?? 0;

            if ($record_id > 0) {
                // Editing existing record - check if it belongs to current user
                $created_by = $model->created_by ?? 0;

                if ($created_by != $current_user_id) {
                    // Record doesn't belong to current user - deny access
                    $builder->addHtml(
                        '<div class="alert alert-danger">' .
                        '<strong>Access Denied:</strong> You can only edit your own records.' .
                        '</div>'
                    );

                    // Optionally redirect to deny page
                    $queryString = Route::getQueryString();
                    Route::redirect('?page=deny&redirect=' . Route::urlsafeB64Encode($queryString));
                    return;
                }

                // Record belongs to current user - optionally show info
                if ($this->show_author_info) {
                    $builder->addHtml(
                        '<div class="alert alert-info">' .
                        'You are editing your own record.' .
                        '</div>'
                    );
                }
            }
        }
    }

    /**
     * Hook called before form is rendered
     * Can be used to modify fields before rendering
     *
     * @param array $fields The form fields array
     * @return array Modified fields array
     */
    public function beforeRender(array $fields): array
    {
        // Initialize Auth
        Get::make('Auth');

        // For administrators, modify created_by field to be a select
        if (Permissions::check('_user.is_admin')) {
            // Hide the created_user_username field (readonly display field)
            if (isset($fields['created_user_username'])) {
                $fields['created_user_username']['edit'] = false;
            }

            // Show and configure created_by as a select
            
            /*
            Qui in teoria si può modificare un campo
            if (isset($fields['created_by'])) {
                // 
                $fields['created_by']['edit'] = true;
                $fields['created_by']['form-type'] = 'select2';
                $fields['created_by']['label'] = 'Author';

                // Get list of users for the select
                $users_list = $this->getUsersList();
                $fields['created_by']['options'] = $users_list;
            }
                */
        }

        return $fields;
    }

    /**
     * Get list of users for select field
     *
     * @return array Array of user options [id => username]
     */
    private function getUsersList(): array
    {
        $users = [ 0 => 'No user selected'];

        try {
            // Get UserModel instance
            $userModel = new \Modules\Auth\UserModel();

            // Get all active users
            $allUsers = $userModel->order('username')->getResults();
            
            // Build options array
            foreach ($allUsers as $user) {
                $users[$user->id] = $user->username ?? $user->email ?? "User #{$user->id}";
            }

        } catch (\Exception $e) {
            // Fallback: if we can't load users, return empty array
            // The field will still be editable but empty
        }

        return $users;
    }

    /**
     * Hook called before data is saved
     * Can be used to validate ownership before save
     *
     * @param array $request The request data to be saved
     * @return array Modified request data
     */
    public function beforeSave(array $request): array
    {
        // Initialize Auth
        Get::make('Auth');

        // Administrators can save all records - skip access control
        if (Permissions::check('_user.is_admin')) {
            return $request;
        }

        // Get the model
        $model = $this->builder->getModel();
        $page = $this->builder->getPage();

        // Check if user has "manage_own_only" permission
        if ($page && Permissions::check($page . '.manage_own_only')) {
            $user = Get::make('Auth')->getUser();
            $current_user_id = $user->id ?? 0;

            if ($current_user_id <= 0) {
                return $request;
            }
            unset ($request['created_by']);
            // Check if we're updating an existing record
            $primary_key = $model->getPrimaryKey();
            $record_id = $request[$primary_key] ?? 0;

            if ($record_id > 0) {
                // Load existing record to verify ownership
                $existing_record = $model->getById($record_id);

                if (!$existing_record->isEmpty()) {
                    $created_by = $existing_record->created_by ?? 0;

                    if ($created_by != $current_user_id) {
                        // Deny save - record doesn't belong to current user
                        // This should not normally happen if access is properly controlled
                        $queryString = Route::getQueryString();
                        Route::redirect('?page=deny&redirect=' . Route::urlsafeB64Encode($queryString));
                    }
                }
            } else {
                $request['created_by'] =  $current_user_id;
            }
        }

        return $request;
    }

    /**
     * Hook called after data is saved
     * Can be used to execute custom logic after save
     *
     * @param mixed $formBuilderOrRequest The FormBuilder instance or request data
     * @param array|null $request The saved request data
     * @return void
     */
    public function afterSave($formBuilderOrRequest, ?array $request = null): void
    {
        // Post-save logic can be added here if needed
    }
}
