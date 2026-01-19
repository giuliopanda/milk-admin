<?php
namespace App\Abstracts\Traits;

use App\Get;

!defined('MILK_DIR') && die();

/**
 * Trait for registering model results as ArrayDb virtual tables.
 */
trait VirtualTableTrait
{
    /**
     * Register current results as a virtual table in ArrayDb.
     *
     * @param string $tableName Table name (can include #__ prefix token)
     * @param string|null $autoIncrementColumn Auto-increment column (defaults to primary key)
     * @return bool
     */
    public function registerVirtualTable(
        string $tableName,
        ?string $autoIncrementColumn = null
    ): bool {
        $this->error = false;
        $this->last_error = '';

        $tableName = trim($tableName);
        if ($tableName === '' || preg_match('/\s/', $tableName)) {
            $this->error = true;
            $this->last_error = 'Invalid virtual table name';
            return false;
        }

        $db = Get::arrayDb();
        $resolvedName = $db->qn($tableName);

        $rows = $this->getSqlData('array', true);
        if (!is_array($rows)) {
            $this->error = true;
            $this->last_error = 'No data available for virtual table';
            return false;
        }

        $autoIncrementColumn = $autoIncrementColumn ?? ($this->primary_key !== '' ? $this->primary_key : null);

        $normalized = [];
        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (!is_array($row)) {
                continue;
            }
            $clean = [];
            foreach ($row as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    continue;
                }
                $clean[(string) $key] = $value;
            }
            $normalized[] = $clean;
        }

        $db->addTable($resolvedName, $normalized, $autoIncrementColumn);

        return true;
    }
}
