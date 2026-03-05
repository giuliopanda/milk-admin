<?php
namespace Extensions\Projects\Classes\Module;

use App\ExpressionParser;
use App\Logs;
use Extensions\Projects\Classes\ProjectNaming;

!defined('MILK_DIR') && die();

/**
 * Evaluates manifest "showIf" expressions against parent records.
 */
class ShowIfEvaluator
{
    /**
     * Validate that the showIf condition for a child context is satisfied
     * by the immediate parent record.
     *
     * Returns null on success, or an error message string.
     */
    public function validate(array $context, int $parentId, int $expectedRootId = 0): ?string
    {
        $expression = trim((string) ($context['show_if'] ?? ''));
        if ($expression === '') {
            return null;
        }

        if ($parentId <= 0) {
            return "Missing parent id for showIf condition on '{$context['form_name']}'.";
        }

        $parentFormName = (string) ($context['parent_form_name'] ?? '');
        $parentModelClass = $this->resolveParentModelClass($context);
        if ($parentModelClass === '') {
            return "Unable to evaluate showIf: missing parent model for '{$parentFormName}'.";
        }

        try {
            $parentRecord = (new $parentModelClass())->getByIdForEdit($parentId);
        } catch (\Throwable) {
            return "Unable to evaluate showIf for '{$context['form_name']}' parent #{$parentId}.";
        }

        if (!is_object($parentRecord) || (method_exists($parentRecord, 'isEmpty') && $parentRecord->isEmpty())) {
            return "Unable to evaluate showIf: parent '{$parentFormName}' record #{$parentId} not found.";
        }

        if ($expectedRootId > 0) {
            $rootField = ProjectNaming::rootIdField();
            $parentRules = method_exists($parentRecord, 'getRules') ? $parentRecord->getRules() : [];
            if (is_array($parentRules) && isset($parentRules[$rootField])) {
                $parentRootId = _absint(ModelRecordHelper::extractFieldValue($parentRecord, $rootField));
                if ($parentRootId <= 0 || $parentRootId !== $expectedRootId) {
                    return "Invalid showIf context: parent '{$parentFormName}' record #{$parentId} has '{$rootField}={$parentRootId}', expected {$expectedRootId}.";
                }
            }
        }

        $showIfError = null;
        $isAllowed = $this->evaluate($expression, $parentRecord, $showIfError);
        if ($showIfError !== null) {
            return "Invalid showIf expression for '{$context['form_name']}': {$showIfError}";
        }
        if (!$isAllowed) {
            $customMessage = trim((string) ($context['show_if_message'] ?? ''));
            if ($customMessage !== '') {
                return $customMessage;
            }
            return "Access denied for '{$context['form_name']}': showIf condition is false for parent #{$parentId}.";
        }

        return null;
    }

    /**
     * Evaluate a showIf expression against a record (used also for inline table rendering).
     */
    public function evaluate(string $expression, mixed $record, ?string &$error = null): bool
    {
        $error = null;
        $expr = trim($expression);
        if ($expr === '') {
            return true;
        }

        $params = $this->buildParametersFromRecord($record);

        try {
            $parser = new ExpressionParser();
            $result = $parser
                ->resetAll()
                ->setParameters($params)
                ->execute($expr);

            return $parser->normalizeCheckboxValue($result);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Logs::set('SYSTEM', "Projects showIf error: {$error} | expr: {$expr}", 'ERROR');
            return false;
        }
    }

    /**
     * Build ExpressionParser params from a record (array/stdClass/Model).
     *
     * Adds lowercase and uppercase aliases for every key so expressions
     * are case-insensitive.
     *
     * @return array<string,mixed>
     */
    protected function buildParametersFromRecord(mixed $record): array
    {
        $rows = ModelRecordHelper::extractRawRows($record);
        $first = $rows[0] ?? null;
        $params = [];

        $data = [];
        if (is_array($first)) {
            $data = $first;
        } elseif (is_object($first)) {
            $data = get_object_vars($first);
        }

        foreach ($data as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $params[$key] = $value;
            $params[strtolower($key)] = $value;
            $params[strtoupper($key)] = $value;
        }

        return $params;
    }

    protected function resolveParentModelClass(array $context): string
    {
        $parentFormName = trim((string) ($context['parent_form_name'] ?? ''));
        if ($parentFormName === '') {
            return '';
        }

        $ancestorModelClasses = is_array($context['ancestor_model_classes'] ?? null)
            ? $context['ancestor_model_classes']
            : [];

        $class = (string) ($ancestorModelClasses[$parentFormName] ?? '');
        if ($class !== '' && class_exists($class)) {
            return $class;
        }

        return '';
    }
}
