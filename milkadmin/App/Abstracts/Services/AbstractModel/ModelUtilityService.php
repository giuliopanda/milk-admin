<?php
namespace App\Abstracts\Services\AbstractModel;

use App\Abstracts\AbstractModel;
use App\Get;

!defined('MILK_DIR') && die();

class ModelUtilityService
{
    public function copyRules(AbstractModel $model, object $destinationRule): void
    {
        $rules = $model->getRules();

        foreach ($rules as $fieldName => $fieldRule) {
            $type = $fieldRule['type'] ?? 'string';

            switch ($type) {
                case 'id':
                    $destinationRule->id($fieldName);
                    break;
                case 'string':
                    $destinationRule->string($fieldName, $fieldRule['length'] ?? 255);
                    break;
                case 'text':
                    $destinationRule->text($fieldName);
                    $dbType = strtolower(trim((string) ($fieldRule['db_type'] ?? '')));
                    if (in_array($dbType, ['tinytext', 'text', 'mediumtext', 'longtext'], true)) {
                        $destinationRule->property('db_type', $dbType);
                    }
                    break;
                case 'int':
                    $destinationRule->int($fieldName);
                    break;
                case 'float':
                case 'decimal':
                    $destinationRule->decimal($fieldName, $fieldRule['length'] ?? 10, $fieldRule['precision'] ?? 2);
                    break;
                case 'bool':
                case 'boolean':
                    $destinationRule->boolean($fieldName);
                    break;
                case 'date':
                    $destinationRule->date($fieldName);
                    break;
                case 'datetime':
                    $destinationRule->datetime($fieldName);
                    if (($fieldRule['timezone_conversion'] ?? true) === false) {
                        $destinationRule->noTimezoneConversion();
                    }
                    break;
                case 'time':
                    $destinationRule->time($fieldName);
                    break;
                case 'list':
                    $destinationRule->list($fieldName, $fieldRule['options'] ?? []);
                    break;
                case 'enum':
                    $destinationRule->enum($fieldName, $fieldRule['options'] ?? []);
                    break;
                case 'array':
                    $destinationRule->array($fieldName);
                    break;
                default:
                    $destinationRule->field($fieldName, $type);
                    break;
            }

            $this->copyRuleProperties($fieldName, $fieldRule, $destinationRule);
        }
    }

    public function registerVirtualTable(AbstractModel $model, string $tableName, ?string $autoIncrementColumn = null): array
    {
        $tableName = trim($tableName);
        if ($tableName === '' || preg_match('/\s/', $tableName)) {
            return [false, true, 'Invalid virtual table name'];
        }

        $db = Get::arrayDb();
        $resolvedName = $db->qn($tableName);
        $rows = $model->getSqlData('array', true);

        if (!is_array($rows)) {
            return [false, true, 'No data available for virtual table'];
        }

        $autoIncrementColumn ??= $model->getPrimaryKey() !== '' ? $model->getPrimaryKey() : null;

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
        return [true, false, ''];
    }

    public function afterSave(AbstractModel $model, mixed $saveResults): void
    {
        $successfulIds = [];

        if (is_array($saveResults)) {
            foreach ($saveResults as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $action = $row['action'] ?? null;
                $ok = (bool) ($row['result'] ?? false);
                $id = $row['id'] ?? null;

                if ($ok && in_array($action, ['insert', 'edit'], true) && $id !== null) {
                    $successfulIds[] = $id;
                }
            }
        }

        if ($successfulIds === []) {
            return;
        }

        $dirtyMap = $model->getAllDirtyMeta();
        if ($dirtyMap !== []) {
            $dirtyIndices = array_keys($dirtyMap);
            sort($dirtyIndices, SORT_NUMERIC);

            foreach ($dirtyIndices as $offset => $dirtyIndex) {
                $entityId = $successfulIds[$offset] ?? null;
                if ($entityId === null) {
                    continue;
                }

                $model->saveMeta($entityId, (int) $dirtyIndex);
            }
            return;
        }

        foreach ($successfulIds as $entityId) {
            $model->saveMeta($entityId);
        }
    }

    public function beforeDelete(): bool
    {
        return true;
    }

    public function afterDelete(AbstractModel $model, mixed $entityIds): void
    {
        $ids = is_array($entityIds) ? $entityIds : [$entityIds];

        foreach ($ids as $entityId) {
            if ($entityId === null || $entityId === '') {
                continue;
            }

            $model->deleteMeta($entityId);
        }
    }

    private function copyRuleProperties(string $fieldName, array $fieldRule, object $destinationRule): void
    {
        if (isset($fieldRule['nullable'])) {
            $destinationRule->nullable($fieldRule['nullable']);
        }

        if (isset($fieldRule['default'])) {
            $destinationRule->default($fieldRule['default']);
        }

        if (isset($fieldRule['label'])) {
            $destinationRule->label($fieldRule['label']);
        }

        if (($fieldRule['index'] ?? false) === true) {
            $destinationRule->index();
        }

        if (($fieldRule['unique'] ?? false) === true) {
            $destinationRule->unique();
        }

        if (isset($fieldRule['form-type'])) {
            $destinationRule->formType($fieldRule['form-type']);
        }

        if (isset($fieldRule['form-label'])) {
            $destinationRule->formLabel($fieldRule['form-label']);
        }

        if (isset($fieldRule['form-params'])) {
            $formParams = $fieldRule['form-params'];
            unset($formParams['required']);
            if ($formParams !== []) {
                $destinationRule->formParams($formParams);
            }
        }

        foreach ($fieldRule as $propertyKey => $propertyValue) {
            if (strpos($propertyKey, '_') !== 0) {
                continue;
            }

            $reflection = new \ReflectionClass($destinationRule);
            $rulesProperty = $reflection->getProperty('rules');
            $rulesArray = $rulesProperty->getValue($destinationRule);
            $rulesArray[$fieldName][$propertyKey] = $propertyValue;
            $rulesProperty->setValue($destinationRule, $rulesArray);
        }

        if (($fieldRule['list'] ?? true) === false) {
            $destinationRule->hideFromList();
        }

        if (($fieldRule['edit'] ?? true) === false) {
            $destinationRule->hideFromEdit();
        }

        if (($fieldRule['view'] ?? true) === false) {
            $destinationRule->hideFromView();
        }

        if (($fieldRule['sql'] ?? true) === false) {
            $destinationRule->excludeFromDatabase();
        }
    }
}
