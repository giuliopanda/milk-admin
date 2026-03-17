<?php
namespace TestApp;

use App\Abstracts\AbstractModel;

/**
 * Model di test per verificare la gestione dei timezone
 */
class TestTimezoneModel extends AbstractModel
{
    protected string $table = 'test_timezone_records';
    protected string $primary_key = 'id';

    protected function configure($rule): void
    {
        $rule->table($this->table)
            ->id('id')
            ->string('title', 255)->nullable()->label('Title')
            ->datetime('created_at')->label('Created At');
    }
}
