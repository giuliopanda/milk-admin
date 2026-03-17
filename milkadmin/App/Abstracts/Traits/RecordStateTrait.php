<?php
namespace App\Abstracts\Traits;

!defined('MILK_DIR') && die();

/**
 * Record state tracking for multi-record models.
 *
 * `records_objects` remains the source of truth for current values.
 * This trait only manages per-record metadata used by save semantics.
 */
trait RecordStateTrait
{
    protected function resetRecordStates(): void
    {
        $this->record_states = [];
    }

    /**
     * @param array<string, mixed> $originalData
     */
    protected function initializeRecordState(int $index, array $originalData = []): void
    {
        $this->record_states[$index] = [
            'originalData' => $originalData,
            'dirtyFields' => [],
            'staleFields' => [],
            'isHydrating' => false,
        ];
    }

    protected function ensureRecordState(int $index): void
    {
        if (isset($this->record_states[$index])) {
            return;
        }

        $record = $this->records_objects[$index] ?? [];
        if (!is_array($record)) {
            $record = [];
        }

        $this->initializeRecordState($index, $this->extractTrackedRecordData($record));
    }

    protected function hasRecordState(int $index): bool
    {
        return isset($this->record_states[$index]);
    }

    protected function removeRecordState(int $index): void
    {
        unset($this->record_states[$index]);
    }

    protected function markRecordHydrating(int $index, bool $isHydrating): void
    {
        $this->ensureRecordState($index);
        $this->record_states[$index]['isHydrating'] = $isHydrating;
    }

    protected function clearRecordTrackingState(int $index): void
    {
        $this->ensureRecordState($index);
        $this->record_states[$index]['dirtyFields'] = [];
        $this->record_states[$index]['staleFields'] = [];
        $this->record_states[$index]['isHydrating'] = false;
    }

    protected function refreshRecordStateAfterSuccessfulSave(int $index): void
    {
        $this->ensureRecordState($index);

        $record = $this->records_objects[$index] ?? [];
        if (!is_array($record)) {
            $record = [];
        }

        $this->record_states[$index]['originalData'] = $this->extractTrackedRecordData($record);
        $this->record_states[$index]['dirtyFields'] = [];
        $this->record_states[$index]['staleFields'] = [];
        $this->record_states[$index]['isHydrating'] = false;
        if (isset($this->records_objects[$index]) && is_array($this->records_objects[$index])) {
            $this->records_objects[$index]['___action'] = null;
        }
    }

    protected function trackAssignedFieldState(int $index, string $fieldName, mixed $value): void
    {
        if ($fieldName === '___action' || $this->getRelationshipDefinitionService()->hasRelationship($this->getRuleBuilder(), $fieldName) || !$this->isTrackedStateField($fieldName)) {
            return;
        }

        $this->ensureRecordState($index);

        if ($this->suspend_action_tracking) {
            return;
        }

        $original = $this->record_states[$index]['originalData'][$fieldName] ?? null;

        if ($this->record_states[$index]['isHydrating']) {
            if ($this->rawValuesAreEquivalent($original, $value)) {
                unset($this->record_states[$index]['staleFields'][$fieldName]);
            } else {
                $this->record_states[$index]['staleFields'][$fieldName] = true;
            }

            unset($this->record_states[$index]['dirtyFields'][$fieldName]);
            return;
        }

        if ($this->rawValuesAreEquivalent($original, $value)) {
            unset($this->record_states[$index]['dirtyFields'][$fieldName]);
            return;
        }

        $this->record_states[$index]['dirtyFields'][$fieldName] = true;
    }

    protected function trackCalculatedFieldState(int $index, string $fieldName, mixed $value): void
    {
        if ($fieldName === '___action' || $this->getRelationshipDefinitionService()->hasRelationship($this->getRuleBuilder(), $fieldName) || !$this->isTrackedStateField($fieldName)) {
            return;
        }

        $this->ensureRecordState($index);

        if ($this->suspend_action_tracking) {
            return;
        }

        $original = $this->record_states[$index]['originalData'][$fieldName] ?? null;

        if ($this->rawValuesAreEquivalent($original, $value)) {
            unset($this->record_states[$index]['staleFields'][$fieldName]);
        } else {
            $this->record_states[$index]['staleFields'][$fieldName] = true;
        }

        unset($this->record_states[$index]['dirtyFields'][$fieldName]);
    }

    protected function applyCalculatedFieldValue(string $fieldName, mixed $value): void
    {
        if (!isset($this->records_objects[$this->current_index]) || !is_array($this->records_objects[$this->current_index])) {
            return;
        }

        $this->ensureRecordState($this->current_index);

        $convertedValue = $this->getValueWithConversion($fieldName, $value);
        $currentRecord = $this->records_objects[$this->current_index];
        $hadValue = array_key_exists($fieldName, $currentRecord);
        $previousValue = $hadValue ? $currentRecord[$fieldName] : null;
        $valueChanged = !$hadValue || !$this->rawValuesAreEquivalent($previousValue, $convertedValue);

        if ($valueChanged) {
            $this->records_objects[$this->current_index][$fieldName] = $convertedValue;
        }

        $trackedValue = $valueChanged
            ? ($this->records_objects[$this->current_index][$fieldName] ?? $convertedValue)
            : $previousValue;

        $this->trackCalculatedFieldState($this->current_index, $fieldName, $trackedValue);

        if ($this->suspend_action_tracking) {
            return;
        }

        $this->syncRecordActionFromState($this->current_index);
    }

    /**
     * @return array<string, true>
     */
    protected function getRecordDirtyFields(int $index): array
    {
        $this->ensureRecordState($index);
        return $this->record_states[$index]['dirtyFields'];
    }

    /**
     * @return array<string, true>
     */
    protected function getRecordStaleFields(int $index): array
    {
        $this->ensureRecordState($index);
        return $this->record_states[$index]['staleFields'];
    }

    protected function recomputeRecordAction(int $index): ?string
    {
        $record = $this->records_objects[$index] ?? null;
        if (!is_array($record)) {
            return null;
        }

        if (!$this->hasRecordState($index)) {
            return $record['___action'] ?? null;
        }

        $dirtyFields = $this->record_states[$index]['dirtyFields'] ?? [];
        $staleFields = $this->record_states[$index]['staleFields'] ?? [];
        if ($dirtyFields === [] && $staleFields === []) {
            return null;
        }

        $isNewRecord = $this->isNewRecordIndex($index);
        if ($isNewRecord) {
            return 'insert';
        }

        return 'edit';
    }

    protected function syncRecordActionFromState(int $index): ?string
    {
        $action = $this->recomputeRecordAction($index);

        if (isset($this->records_objects[$index]) && is_array($this->records_objects[$index])) {
            $this->records_objects[$index]['___action'] = $action;
        }

        return $action;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractTrackedRecordData(array $record): array
    {
        $tracked = [];

        foreach ($record as $fieldName => $value) {
            $fieldName = (string) $fieldName;
            if (!$this->isTrackedStateField($fieldName)) {
                continue;
            }

            $tracked[$fieldName] = $value;
        }

        return $tracked;
    }

    protected function isTrackedStateField(string $fieldName): bool
    {
        if ($fieldName === '___action' || $this->getRelationshipDefinitionService()->hasRelationship($this->getRuleBuilder(), $fieldName)) {
            return false;
        }

        $rule = $this->getRule($fieldName);
        if (!is_array($rule)) {
            return false;
        }

        return ($rule['sql'] ?? true) !== false;
    }
}
