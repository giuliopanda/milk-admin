<?php
namespace TestApp;

use App\Abstracts\AbstractModel;

/**
 * Model di esempio per testare buildTable()
 *
 * Questo model dimostra come creare una tabella usando buildTable()
 * con campi id, titolo e data con gestione timezone
 */
class BuildTableExampleModel extends AbstractModel
{
    protected string $table = 'build_table_example';
    protected string $primary_key = 'id';

    protected function configure($rule): void
    {
        $rule->table($this->table)
            ->id('id')
            ->string('title', 255)->nullable()->label('Title')
            ->datetime('created_at')->label('Created At');
    }
}
