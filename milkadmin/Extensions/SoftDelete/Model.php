<?php
namespace Extensions\SoftDelete;

use App\Abstracts\{AbstractModelExtension, RuleBuilder};
use App\{Get};

!defined('MILK_DIR') && die();

/**
 * Soft Delete Model Extension
 *
 * Implements soft delete functionality - marks records as deleted instead of removing them.
 * Features:
 * - Adds deleted_at timestamp field
 * - Automatically filters deleted records from normal queries
 * - Provides methods to restore and permanently delete records
 * - Tracks who deleted the record
 *
 * @package Extensions\SoftDelete
 */
class Model extends AbstractModelExtension
{
    /**
     * Field name for soft delete timestamp
     * @var string
     */
    public $field_name = 'deleted_at';

    /**
     * Field name for deleted by user
     * @var string
     */
    public $deleted_by_field = 'deleted_by';

    /**
     * Track who deleted the record
     * @var bool
     */
    protected $track_deleted_by = true;

    /**
     * Automatically filter deleted records from queries
     * @var bool
     */
    protected $auto_filter = true;

    /**
     * Add soft delete fields to the model
     *
     * @param RuleBuilder $rule_builder
     * @return void
     */
    public function configure(RuleBuilder $rule_builder): void
    {
        $rule_builder
            ->timestamp($this->field_name)
                ->hide()
                ->label('Deleted At')
            ->int($this->deleted_by_field)
                ->belongsTo('softDeletedUsers', \Modules\Auth\UserModel::class, 'id')
                ->hide()
                ->label('Deleted By');
    }

    /**
     * Intercept delete operation to soft/hard delete instead
     *
     * @param array $ids Array of IDs to delete
     * @return bool|null Return false to prevent actual deletion, null to continue
     */
    
    public function beforeDelete($return, $ids): bool {
        $model = $this->model->get();
 
        // Mark records as deleted instead of removing
        foreach ($ids as $id) {
            // Get the record without soft delete filter
            $record = $model->getById($id);
            if (!$record->isEmpty()) {
                if ($record->{$this->field_name} !== null) { 
                    // Real Delete Record 
                    $model->delete($id);
                } else {
                    $user = Get::make('Auth')->getUser();
                    $record->{$this->field_name} = time();
                    $record->{$this->deleted_by_field} = $user ? $user->id : null;

                    if (!$record->save()) {
                        throw new \Exception("Failed to soft delete record ID {$id}");
                    }
                }
            }
        }
        // Prevent actual deletion
        return false;
    }

}
