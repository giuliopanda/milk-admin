<?php
namespace Extensions\SoftDelete;

use App\Abstracts\AbstractFormBuilderExtension;

!defined('MILK_DIR') && die();

/**
 * Soft Delete FormBuilder Extension
 *
 * Adds soft delete functionality to FormBuilder:
 * - Shows deleted_at field when editing deleted records
 * - Adds restore button for deleted records
 * - Optionally hides certain fields when record is deleted
 *
 * @package Extensions\SoftDelete
 */
class FormBuilder extends AbstractFormBuilderExtension
{
    /**
     * Whether to show restore button for deleted records
     * @var bool
     */
    protected bool $show_restore_button = true;

    /**
     * Label for the restore button
     * @var string
     */
    protected string $restore_button_label = 'Restore';

    /**
     * Whether to show deleted_at field when record is deleted
     * @var bool
     */
    protected bool $show_deleted_at = true;

    /**
     * Hook called during builder configuration
     * Can be used to add fields or modify form behavior
     *
     * @param object $builder The builder instance
     * @return void
     */
    public function configure(object $builder): void
    {
        // Get the model
        $model = $builder->getModel();
        $soft_del_ext = $model->getLoadedExtension('SoftDelete');
        if (!$soft_del_ext) {
            return;
        }
        $name = $soft_del_ext->field_name;

        if ($model->$name != null) {
            $by = $model->softDeletedUsers;
            if ($by) {
                $by = ' by <b>' . $by->username.'</b>';
            } else {
                $by = '';
            }
            $formatted_value = $model->getFormattedValue($name);
            $builder->addHtml('<div class="alert alert-warning">Record was deleted on <b>' . $formatted_value . '</b>' . $by . '</div>');
        }
        
        // Add restore action if record is deleted and show_restore_button is true
        if ($model->$name != null) {
            $builder->addActions([
                'restore' => [
                    'label' => $this->restore_button_label,
                    'type' => 'submit',
                    'class' => 'btn btn-warning',
                    'action' => function($form_builder, $request) {
                        $model = $form_builder->getModel();
                        $soft_del_ext = $model->getLoadedExtension('SoftDelete');
                    
                        $id = $request[$model->getPrimaryKey()] ?? 0;
                    
                        $record = $model->getById($id);
                        if (!$record->isEmpty()) {
                            $record->{$soft_del_ext->field_name} = null;
                            $record->{$soft_del_ext->deleted_by_field} = null;
                        
                            if($record->save()) { 
                                return [
                                    'success' => true,
                                    'message' => 'Record restored successfully'
                                ];
                            } else {
                                die("NNNNN");
                            }
                        } 

                        return [
                            'success' => false,
                            'message' => 'Failed to restore record'
                        ];
                    }
                ]
            ]);
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
        // You can modify fields here if needed
        // For example, hide certain fields when record is deleted

        return $fields;
    }

    /**
     * Hook called before data is saved
     * Can be used to validate or modify data before save
     *
     * @param array $request The request data to be saved
     * @return array Modified request data
     */
    public function beforeSave(array $request): array
    {
        // You can modify request data here if needed
        // For example, prevent saving certain fields when record is deleted

        return $request;
    }

    /**
     * Hook called after data is saved
     * Can be used to execute custom logic after save
     *
     * @param mixed $formBuilderOrRequest The FormBuilder instance or request data (for backwards compatibility)
     * @param array|null $request The saved request data (when FormBuilder is passed as first param)
     * @return void
     */
    public function afterSave($formBuilderOrRequest, ?array $request = null): void
    {
        // You can execute custom logic here after save
        // For example, log soft delete operations
    }
}
