<?php
namespace Extensions\SoftDelete;

use App\Abstracts\AbstractGetDataBuilderExtension;
use App\{Get};

!defined('MILK_DIR') && die();

/**
 * Soft Delete GetDataBuilder Extension
 *
 * Adds soft delete functionality to GetDataBuilder:
 * - Automatically filters out soft deleted records
 * - Adds filter to show/hide deleted items
 * - Can add a column to show deletion status
 *
 * @package Extensions\SoftDelete
 */
class GetDataBuilder extends AbstractGetDataBuilderExtension
{
    /**
     * Field name for soft delete timestamp
     * @var string
     */
    protected $field_name = 'deleted_at';

    protected $deleted_by_field = 'deleted_by';

    /**
     * Automatically filter deleted records from queries
     * @var bool
     */
    protected $auto_filter = true;

    /**
     * Show deleted status column
     * @var bool
     */
    protected $show_column = false;

    /**
     * Add filter to toggle deleted items visibility
     * @var bool
     */
    protected $add_filter = true;

    /**
     * Hook called during builder configuration
     * Adds filter and optionally a column for soft delete status
     *
     * @param object $builder The builder instance
     * @return void
     */
    public function configure(object $builder): void
    {
        // Add filter to show/hide deleted items
        if ($this->add_filter) {
            $field_name = $this->field_name;
            //$builder->where($field_name . ' IS NULL');
            
            $builder->filter('show_deleted', function($query, $value) use ($field_name) {
                if ($value === 'active' ) {
                    // Show only deleted items
                    $query->where($field_name . ' IS NULL');
                } elseif ($value === 'deleted') {
                     $query->where($field_name . ' IS NOT NULL');
                }
            }, 'active');
            
        }
        $filters = $builder->getFilters();
        $soft_delete_status = (isset($filters['show_deleted']) && $filters['show_deleted'] === 'deleted');
        $label = $soft_delete_status ? 'Delete permanently' : 'Move to Trash';

        // Get current page for building links
        $page = $builder->getPage();

        // Replace 'delete' action with 'soft_delete' for active records
        $actions = $builder->getActions();
        $bulkActions = $builder->getBulkActions();

        if ($soft_delete_status) {
            // When viewing soft deleted records, show only restore and delete permanently
            $actions = [];

            // Add restore action
            $actions['restore'] = [
                'label' => 'Restore',
               // 'link' => '?page=' . $page . '&action=restore&id=%id%',
               //  'fetch' => 'post',
                 'action' => [$this, 'actionRestoreRow'],
            ];

            // Add delete permanently action
            $actions['delete'] = [
                'action' => [$builder, 'actionDeleteRow'],
                'confirm' => 'Permanently delete this record? This cannot be undone!',
                'class' => 'link-action-danger'
            ];

            $builder->setActions($actions);

            // Update bulk actions for deleted records
            $bulkActions = [];

            // Only delete permanently for bulk actions
            // Restore is available only as row action (single record at a time)
            $bulkActions['delete'] = [
                'label' => 'Delete permanently',
                'confirm' => 'Permanently delete selected records? This cannot be undone!',
                'action' => [$builder, 'actionDeleteRow'],
            ];

            $builder->setBulkActions($bulkActions);
        } else {
            // For active records, keep existing actions and modify delete
            // Replace delete action with soft delete in row actions
            if (!isset($actions['delete'])) {
                $actions['delete'] = [
                    'action' => [$builder, 'actionDeleteRow'],
                    'confirm' =>  'Move to trash?',
                    'class' => 'link-action-warning'
                ];
            }

            $actions['delete']['label'] = $label;
            $builder->setActions($actions);

            // Replace delete bulk action with soft delete
            if (!isset($bulkActions['delete'])) {
                $bulkActions['delete'] = [
                    'confirm' =>  'Move to trash?',
                    'action' => [$builder, 'actionDeleteRow'],
                ];
            }
            $bulkActions['delete']['label'] = $label;
            $builder->setBulkActions($bulkActions);
        }
        

        // Optionally add a column showing deletion status
        if ($this->show_column) {
            $field_name = $this->field_name;
            $builder->field($field_name)
                ->label('Status')
                ->fn(function($row) use ($field_name) {
                    if (isset($row[$field_name]) && $row[$field_name] !== null) {
                        return '<span class="badge bg-danger">Deleted</span>';
                    }
                    return '<span class="badge bg-success">Active</span>';
                });
        }
    }

    /**
     * Restore a soft deleted record
     *
     * @param object $record The record to restore
     * @param array $request The request parameters
     * @return void
     */
    public function actionRestoreRow($record, $request) {
        $record->{$this->field_name} = null;
        $record->{$this->deleted_by_field} = null;
        $record->save();
        return ['success' => true, 'msg' => 'Record restored successfully'];
    }

    /**
     * Hook called before data retrieval
     * Automatically filters deleted records if auto_filter is enabled
     *
     * @return void
     */
    /*
    public function beforeGetData(): void
    {
        if (!$this->auto_filter) {
            return;
        }

        // Check if show_deleted filter is active
        $request = $this->builder->getRequest();
        $filters_json = $request['filters'] ?? '';
        $show_deleted = false;
        $show_all = false;

        if ($filters_json != '') {
            $filters = json_decode($filters_json);
            if (is_array($filters)) {
                foreach ($filters as $filter_str) {
                    $parts = explode(':', $filter_str, 2);
                    if ($parts[0] === 'show_deleted') {
                        $value = $parts[1] ?? '';
                        if ($value === 'yes' || $value === '1') {
                            $show_deleted = true;
                        } elseif ($value === 'all') {
                            $show_all = true;
                        }
                        break;
                    }
                }
            }
        }

        // Only apply auto-filter if we're not showing deleted/all items
        if (!$show_deleted && !$show_all) {
            $db = Get::db();
            $query = $this->builder->getQuery();
            $query->where($db->qn($this->field_name) . ' IS NULL');
        }
    }
    */
}
