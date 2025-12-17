<?php
namespace Modules\Db2tables;

use App\Abstracts\AbstractModule;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * DB2 Tables Explorer Module
 *
 * Provides database exploration and management capabilities
 * allowing users to view, edit, and manage database tables and views
 */
class Db2tablesModule extends AbstractModule
{
    /**
     * Configure the module using the fluent ModuleRuleBuilder interface
     *
     * @param object $rule ModuleRuleBuilder instance
     * @return void
     */
    protected function configure($rule): void
    {
        $rule->page('db2tables')
             ->title('DB2 Tables Explorer')
             ->access('admin')
             ->menu('Data', '', 'bi bi-table', 70)
             ->setJs('/Assets/db2tables.js')
             ->setJs('/Assets/datatab.js')
             ->setJs('/Assets/querytab.js')
             ->setJs('/Assets/suggestion.js')
             ->setJs('/Assets/table-structure.js')
             ->setJs('/Assets/home.js')
             ->setCss('/Assets/db2tables.css')
             ->version(251101);
    }

}
