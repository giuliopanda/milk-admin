<?php
namespace Local\Modules\{{MODULE_NAME}};

use App\Abstracts\AbstractModel;

!defined('MILK_DIR') && die();

class {{ROOT_FORM_NAME}}Model extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__{{ROOT_TABLE_NAME}}')
            ->db('db2')
            ->id('id')
            ->created_at('created_at')
                ->hideFromList()
                ->hideFromEdit()
            ->updated_at('updated_at')
                ->hideFromList()
                ->hideFromEdit()
            ->created_by('created_by')
                ->hideFromList()
                ->hideFromEdit()
            ->updated_by('updated_by')
                ->hideFromList()
                ->hideFromEdit()
            ->datetime('deleted_at')
                ->formType('hidden')
                ->hideFromList()
                ->hideFromEdit()
            ->int('deleted_by')
                ->formType('hidden')
                ->hideFromList()
                ->hideFromEdit()
            ->extensions(['Projects']);
    }
}
