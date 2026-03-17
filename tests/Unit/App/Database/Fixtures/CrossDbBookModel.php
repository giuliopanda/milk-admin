<?php
namespace Tests\Unit\App\Database\Fixtures;

use App\Abstracts\AbstractModel;

class CrossDbBookModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('test_cross_db_books')
            ->db('db')
            ->id('book_id')
            ->int('created_by')->label('Creato da')->required()
                ->belongsTo('created_by', CrossDbAuthorModel::class, 'author_id')
            ->string('title', 200)->label('Titolo')->required();
    }
}
