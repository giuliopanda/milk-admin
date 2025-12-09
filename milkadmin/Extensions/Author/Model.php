<?php
namespace Extensions\Author;

use App\Abstracts\{AbstractModelExtension, RuleBuilder};
use App\{Get};
use App\Attributes\{ToDisplayValue, ToDatabaseValue};

!defined('MILK_DIR') && die();

/**
 * Author Model Extension
 *
 * Automatically adds created_by field to models and populates it with current user ID.
 * Provides formatted output showing username or email based on configuration.
 *
 * @package Extensions\Author
 */
class Model extends AbstractModelExtension
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
     * Hook called during model configuration
     * Adds the created_by field to the model
     *
     * @param RuleBuilder $rule_builder The rule builder instance
     * @return void
     */
    public function configure(RuleBuilder $rule_builder): void
    {
        
        // Add created_by field to the model
        $rule_builder
            ->int('created_by')
            ->belongsTo('created_user', \Modules\Auth\UserModel::class, 'id')
            ->nullable(true)
            ->label('Created By')
            ->hideFromEdit();

        // Add username field for display in lists and forms
        /*
        $rule_builder
            ->string('created_user_username', 255)
            ->label($this->show_email ? 'Created By (Email)' : 'Created By (Username)')
            ->excludeFromDatabase()
            ->property('readonly', true)
            ->property('database_field', false);
            */
    }

    /**
     * Automatically set created_by when inserting a new record
     * This method is called via attribute scanning
     *
     * @param obj $current_record Current record data
     * @return int User ID
     */
    #[ToDatabaseValue('created_by')]
    public function setCreatedBy($current_record)
    {
        $model = $this->model->get();
        $id = $model->getPrimaryKey();
        if ($current_record->$id > 0) {
            return $current_record->created_by;
        }
        $value = $current_record->created_by;
        // Only set on new records (when created_by is not already set)
        if (empty($value) || $value == 0) {
            $user = Get::make('Auth')->getUser();
            return $user->id ?? 0;
        }

        // Keep existing value for updates
        return $value;
    }

    /**
     * Format created_by for display
     * Shows username or email based on extension configuration
     *
     * @param obj $current_record Current record data
     * @return string Formatted author name
     */
    #[ToDisplayValue('created_by')]
    public function getFormattedCreatedBy($current_record)
    {
       return $current_record->created_user->username ?? '-';
    }


}
