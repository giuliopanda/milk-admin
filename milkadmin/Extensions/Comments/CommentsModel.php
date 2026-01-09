<?php
namespace Extensions\Comments;

use App\Abstracts\AbstractModel;
use App\{Hooks, Get};
use App\Attributes\{ToDisplayValue, ToDatabaseValue};

!defined('MILK_DIR') && die();

/**
 * Comments Model
 *
 * Generic model for managing comments on any entity.
 * Table name is dynamically configured based on parent model via hooks.
 *
 * @package Extensions\Comments
 */
class CommentsModel extends AbstractModel
{
    /**
     * Configure the comments model
     * Table name is set dynamically via CommentsModel.configure hook
     *
     * @param object $rule RuleBuilder instance
     * @return void
     */
    protected function configure($rule): void
    {
        // Run hook to allow dynamic configuration
        // The Comments Model extension will set this hook to configure the table
        $ris = Hooks::run('CommentsModel.getTableParams');
        $rule->table($ris['table_name'])
            ->db($ris['db'])
            ->id()
            ->int($ris['foreign_key'])
                ->formType('hidden')
                ->hideFromList()
                ->label($ris['entity_label'] . ' ID') 
            ->text($ris['comment_field'])
                ->label('Comment')
                ->required()
            ->int('created_by')
                ->belongsTo('created_user', \Modules\Auth\UserModel::class, 'id')
                ->nullable(true)
                ->label('Created')
                ->hideFromEdit()
            ->created_at('created_at')
                ->hide()
            ->int('updated_by')
                ->belongsTo('updated_user', \Modules\Auth\UserModel::class, 'id')
                ->nullable(true)
                ->label('Updated')
                ->hideFromEdit()
            ->datetime('updated_at')
                ->hide();
    }

     /**
     * Automatically set created_by when inserting a new comment
     *
     * @param object $current_record Current record data
     * @return int User ID
     */
    #[ToDatabaseValue('created_by')]
    public function setCreatedBy($current_record)
    {
   
        if ($current_record->id > 0) {
            $record = $this->getById($current_record->id); 
            if (isset($current_record->created_by)) {
                return $current_record->created_by;
            } else {
                return $record->created_by;
            }
        }
       
        $user = Get::make('Auth')->getUser();
        return $user->id ?? 0;

    }

    /**
     * Automatically set updated_by on every save
     *
     * @param object $current_record Current record data
     * @return int User ID
     */
    #[ToDatabaseValue('updated_by')]
    public function setUpdatedBy($current_record)
    {
        $user = Get::make('Auth')->getUser();
        return $user->id ?? 0;
    }

    /**
     * Format created_by for display
     *
     * @param object $current_record Current record data
     * @return string Formatted author name
     */
    #[ToDisplayValue('created_by')]
    public function getFormattedCreatedBy($current_record)
    {
        if (isset($current_record->created_at) && is_a($current_record->created_at, 'DateTime')) {
            $date = ' '.Get::formatDate($current_record->created_at, 'datetime', true);
        } else {
            $date = '';
        }
        return ($current_record->created_user->username ?? '-') . $date;
    }

    /**
     * Format updated_by for display
     *
     * @param object $current_record Current record data
     * @return string Formatted author name
     */
    #[ToDisplayValue('updated_by')]
    public function getFormattedUpdatedBy($current_record)
    {
        if (isset($current_record->created_at) && is_a($current_record->updated_at, 'DateTime')) {
            $date = ' '.Get::formatDate($current_record->updated_at, 'datetime', true);
        } else {
            $date = '';
        }
        return ($current_record->updated_user->username ?? '-') . $date;
    }


    #[ToDatabaseValue('updated_at')] 
    public function setUpdatedAt($current_record)
    {      
        return date('Y-m-d H:i:s');
    }
}
