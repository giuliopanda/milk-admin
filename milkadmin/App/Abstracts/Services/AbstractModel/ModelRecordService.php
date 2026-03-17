<?php
namespace App\Abstracts\Services\AbstractModel;

use App\Abstracts\AbstractModel;
use App\ExpressionParser;

!defined('MILK_DIR') && die();

class ModelRecordService
{
    public function setResults(AbstractModel $model, array $result): void
    {
        $runner = \Closure::bind(function (array $result): void {
            $this->records_objects = [];
            $this->new_record_indexes = [];
            $this->resetRecordStates();
            $this->meta_loaded = false;
            $counter = 0;

            if (count($result) > 0) {
                foreach ($result as $row) {
                    $data = $this->filterDataByRules($row);
                    $data['___action'] = null;
                    $this->records_objects[$counter] = $data;
                    $this->initializeRecordState($counter, $this->extractTrackedRecordData($data));
                    $this->markRecordHydrating($counter, true);
                    $this->current_index = $counter;
                    $this->applyCalculatedFieldsForCurrentRecord();
                    $this->markRecordHydrating($counter, false);
                    $counter++;
                }
            }

            $this->invalidateKeysCache();
            $this->current_index = 0;
        }, $model, AbstractModel::class);

        $runner($result);
    }

    public function setRow(AbstractModel $model, array|object|null $data): void
    {
        $runner = \Closure::bind(function (array|object|null $data): void {
            $this->records_objects = [];
            $this->new_record_indexes = [];
            $this->resetRecordStates();
            $this->dates_in_user_timezone = false;
            $this->meta_loaded = false;
            $data = $this->filterDataByRules($data);
            $data['___action'] = null;

            $this->records_objects[] = $data;
            $this->current_index = array_key_last($this->records_objects);
            $this->initializeRecordState($this->current_index, $this->extractTrackedRecordData($data));
            $this->markRecordHydrating($this->current_index, true);
            $this->applyCalculatedFieldsForCurrentRecord();
            $this->markRecordHydrating($this->current_index, false);
            $this->cleanEmptyRecords();
            $this->invalidateKeysCache();
        }, $model, AbstractModel::class);

        $runner($data);
    }

    public function fill(AbstractModel $model, array|object|null $data = null): AbstractModel
    {
        $runner = \Closure::bind(function (array|object|null $data = null): AbstractModel {
            $this->error = false;
            $this->last_error = '';

            if (is_array($data)) {
                $data = $this->extractRelationshipData($data);
            }

            $data = $this->filterData($data);

            if ($this->primary_key != null && ($this->primary_key != 0)) {
                if (isset($data[$this->primary_key]) && $data[$this->primary_key] !== 0 && $data[$this->primary_key] !== '') {
                    $this->dates_in_user_timezone = true;
                    if (is_array($this->records_objects)) {
                        foreach ($this->records_objects as $key => $value) {
                            if ($value[$this->primary_key] == $data[$this->primary_key]) {
                                $this->current_index = $key;
                                $this->markRecordAsNew($key, false);
                                $this->ensureRecordState($key);

                                foreach ($data as $key => $value) {
                                    if ($value instanceof AbstractModel) {
                                        continue;
                                    }
                                    $this->setValueWithConversion($key, $value);
                                }

                                $this->applyCalculatedFieldsForCurrentRecord();
                                return $this;
                            }
                        }
                    }

                    $this->dates_in_user_timezone = false;
                    if ($this->db === null) {
                        return $this;
                    }

                    $row = $this->db->getRow(
                        'SELECT * FROM ' . $this->db->qn($this->table) . ' WHERE ' . $this->db->qn($this->primary_key) . ' = ?',
                        [$data[$this->primary_key]]
                    );

                    if ($row != null) {
                        $this->current_index = $this->getNextCurrentIndex();
                        if (!is_array($this->records_objects)) {
                            $this->records_objects = [];
                        }

                        $this->dates_in_user_timezone = false;
                        $record = $this->filterDataByRules($row);
                        $record['___action'] = null;
                        $this->records_objects[$this->current_index] = $record;
                        $this->markRecordAsNew($this->current_index, false);
                        $this->initializeRecordState($this->current_index, $this->extractTrackedRecordData($record));
                        $this->dates_in_user_timezone = true;

                        foreach ($data as $key => $value) {
                            if ($value instanceof AbstractModel) {
                                continue;
                            }
                            $this->setValueWithConversion($key, $value);
                        }
                    } else {
                        $this->cleanEmptyRecords();
                        $this->current_index = $this->getNextCurrentIndex();
                        if (!is_array($this->records_objects)) {
                            $this->records_objects = [];
                        }

                        $this->dates_in_user_timezone = true;
                        $this->initializeRecordState($this->current_index);

                        foreach ($data as $key => $value) {
                            if ($value instanceof AbstractModel) {
                                continue;
                            }
                            $this->setValueWithConversion($key, $value);
                        }

                        $this->records_objects[$this->current_index]['___action'] = 'insert';
                        $this->markRecordAsNew($this->current_index, true);
                        $this->invalidateKeysCache();
                    }

                    $this->applyCalculatedFieldsForCurrentRecord();
                    return $this;
                }
            }

            $this->cleanEmptyRecords();
            $this->current_index = $this->getNextCurrentIndex();
            if (!is_array($this->records_objects)) {
                $this->records_objects = [];
            }

            $this->records_objects[$this->current_index]['___action'] = 'insert';
            $this->markRecordAsNew($this->current_index, true);
            $this->initializeRecordState($this->current_index);
            $this->dates_in_user_timezone = true;

            foreach ($data as $key => $value) {
                if ($value instanceof AbstractModel) {
                    continue;
                }
                $this->setValueWithConversion($key, $value);
            }

            $this->invalidateKeysCache();
            $this->applyCalculatedFieldsForCurrentRecord();

            return $this;
        }, $model, AbstractModel::class);

        return $runner($data);
    }

    public function applyCalculatedFieldsForAllRecords(AbstractModel $model): void
    {
        $runner = \Closure::bind(function (): void {
            if (!is_array($this->records_objects)) {
                return;
            }

            foreach ($this->records_objects as $index => $_record) {
                $this->current_index = $index;
                $this->applyCalculatedFieldsForCurrentRecord();
            }
        }, $model, AbstractModel::class);

        $runner();
    }

    public function applyCalculatedFieldsForCurrentRecord(AbstractModel $model): void
    {
        $runner = \Closure::bind(function (): void {
            if (!isset($this->records_objects[$this->current_index]) || !is_array($this->records_objects[$this->current_index])) {
                return;
            }

            $rules = $this->getRules();
            $record = $this->records_objects[$this->current_index];
            $parser = null;

            foreach ($rules as $field_name => $rule) {
                $expression = $rule['calc_expr'] ?? null;
                if (!is_string($expression) || trim($expression) === '') {
                    continue;
                }

                if ($parser === null) {
                    $parser = (new ExpressionParser())->useUntrustedMode();
                }

                try {
                    $parser->setParameters($this->getExpressionParameterNormalizerService()->normalize($this, $record));
                    $result = $parser->execute($expression);
                } catch (\Throwable $e) {
                    continue;
                }

                if (($rule['form-type'] ?? null) === 'checkbox') {
                    $result = $parser->normalizeCheckboxValue($result);
                }

                $this->applyCalculatedFieldValue($field_name, $result);
                $record = $this->records_objects[$this->current_index] ?? $record;
            }
        }, $model, AbstractModel::class);

        $runner();
    }

    public function cleanEmptyRecords(AbstractModel $model): void
    {
        $runner = \Closure::bind(function (): void {
            if ($this->records_objects == null) {
                return;
            }

            foreach ($this->records_objects as $key => $value) {
                $count_params = 0;
                foreach ($value as $k => $v) {
                    if ($k != '___action') {
                        if ($v instanceof AbstractModel) {
                            $v->cleanEmptyRecords();
                        }
                        $count_params++;
                    }
                }

                if ($count_params == 0) {
                    unset($this->new_record_indexes[$key]);
                    $this->removeRecordState((int) $key);
                    unset($this->records_objects[$key]);
                }
            }
        }, $model, AbstractModel::class);

        $runner();
    }

    public function getNextCurrentIndex(AbstractModel $model): int
    {
        $runner = \Closure::bind(function (): int {
            $index = $this->current_index;
            if (!is_array($this->records_objects) || count($this->records_objects) == 0) {
                $index = 0;
            } else {
                $index = array_key_last($this->records_objects);
                do {
                    $index++;
                } while (isset($this->records_objects[$index]));
            }

            return $index;
        }, $model, AbstractModel::class);

        return $runner();
    }

    public function markRecordAsNew(AbstractModel $model, int $index, bool $isNew = true): void
    {
        $runner = \Closure::bind(function (int $index, bool $isNew = true): void {
            if ($isNew) {
                $this->new_record_indexes[$index] = true;
                return;
            }

            unset($this->new_record_indexes[$index]);
        }, $model, AbstractModel::class);

        $runner($index, $isNew);
    }

    public function isNewRecordIndex(AbstractModel $model, int $index): bool
    {
        $runner = \Closure::bind(function (int $index): bool {
            return $this->new_record_indexes[$index] ?? false;
        }, $model, AbstractModel::class);

        return $runner($index);
    }

    public function withoutActionTracking(AbstractModel $model, callable $callback): mixed
    {
        $runner = \Closure::bind(function (callable $callback): mixed {
            $previous = $this->suspend_action_tracking;
            $this->suspend_action_tracking = true;

            try {
                return $callback();
            } finally {
                $this->suspend_action_tracking = $previous;
            }
        }, $model, AbstractModel::class);

        return $runner($callback);
    }

    public function initializePristineNewRecord(AbstractModel $model): void
    {
        $runner = \Closure::bind(function (): void {
            $this->cleanEmptyRecords();
            $this->current_index = $this->getNextCurrentIndex();

            if (!is_array($this->records_objects)) {
                $this->records_objects = [];
            }

            $this->records_objects[$this->current_index] = ['___action' => null];
            $this->markRecordAsNew($this->current_index, true);
            $this->initializeRecordState($this->current_index);
            $this->invalidateKeysCache();
        }, $model, AbstractModel::class);

        $runner();
    }

    public function applyDefaultValuesForCurrentRecord(AbstractModel $model): void
    {
        $runner = \Closure::bind(function (): void {
            if (!isset($this->records_objects[$this->current_index]) || !is_array($this->records_objects[$this->current_index])) {
                return;
            }

            $this->withoutActionTracking(function (): void {
                $this->markRecordHydrating($this->current_index, true);
                foreach ($this->getRules() as $field_name => $rule) {
                    if ($field_name === '___action' || $this->getRelationshipDefinitionService()->hasRelationship($this->getRuleBuilder(), $field_name)) {
                        continue;
                    }

                    if (array_key_exists($field_name, $this->records_objects[$this->current_index])) {
                        continue;
                    }

                    if (!array_key_exists('default', $rule) || $rule['default'] === null) {
                        continue;
                    }

                    $this->setValueWithConversion($field_name, $rule['default']);
                }

                $this->applyCalculatedFieldsForCurrentRecord();
                $this->markRecordHydrating($this->current_index, false);
            });
        }, $model, AbstractModel::class);

        $runner();
    }

    public function filterData(AbstractModel $model, array|object|null $data = null): array
    {
        $runner = \Closure::bind(function (array|object|null $data = null): array {
            if (is_object($data)) {
                $data = (array) $data;
            }

            if (!is_array($data)) {
                return [];
            }

            if (count($data) == 1) {
                $first_data = reset($data);
                if (is_array($first_data)) {
                    $data = $first_data;
                }
            }

            $new_data = [];
            $rules = $this->getRules();

            foreach ($rules as $key => $_rule) {
                if (array_key_exists($key, $data)) {
                    $new_data[$key] = $data[$key];
                }
            }

            foreach ($data as $key => $value) {
                if (isset($new_data[$key])) {
                    continue;
                }

                if ($this->getRelationshipDefinitionService()->hasRelationship($this->getRuleBuilder(), $key)) {
                    $new_data[$key] = $value;
                }
            }

            return $new_data;
        }, $model, AbstractModel::class);

        return $runner($data);
    }

    public function filterDataByRules(AbstractModel $model, array|object|null $data = null): array
    {
        $runner = \Closure::bind(function (array|object|null $data = null): array {
            if (is_object($data)) {
                $data = (array) $data;
            }

            if (!is_array($data)) {
                return [];
            }

            if (count($data) == 1) {
                $first_data = reset($data);
                if (is_array($first_data)) {
                    $data = $first_data;
                }
            }

            $new_data = [];
            $rules = $this->getRules();

            foreach ($rules as $key => $_rule) {
                if (array_key_exists($key, $data)) {
                    $new_data[$key] = $this->getValueWithConversion($key, $data[$key]);
                }
            }

            foreach ($data as $key => $value) {
                if (isset($new_data[$key])) {
                    continue;
                }

                if ($this->getRelationshipDefinitionService()->hasRelationship($this->getRuleBuilder(), $key)) {
                    $new_data[$key] = $value;
                }
            }

            return $new_data;
        }, $model, AbstractModel::class);

        return $runner($data);
    }
}
