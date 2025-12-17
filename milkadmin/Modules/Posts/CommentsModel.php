<?php
namespace Modules\Posts;
use App\Attributes\{Validate};
use App\Abstracts\AbstractModel;

class CommentsModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('#__comm')
            ->id()
            ->int('post_id')
            ->text('comment')->formType('editor');
    }
}