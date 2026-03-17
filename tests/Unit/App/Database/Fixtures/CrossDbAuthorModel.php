<?php
namespace Tests\Unit\App\Database\Fixtures;

use App\Abstracts\AbstractModel;

class CrossDbAuthorModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table('test_cross_db_authors')
            ->db('db2')
            ->id('author_id')
            ->string('username', 100)->label('Username')
            ->string('name', 100)->label('Nome');
    }
}
