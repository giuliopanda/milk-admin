<?php
namespace Extensions\Projects;

use App\Abstracts\AbstractFormBuilderExtension;
use App\{MessagesHandler, Token};

!defined('MILK_DIR') && die();

/**
 * Projects FormBuilder Extension
 *
 * Enforces manifest constraints (e.g. single-record child forms).
 */
class FormBuilder extends AbstractFormBuilderExtension
{
    /**
     * Foreign key field (child -> immediate parent form).
     * Example: projects_extension_test_id
     */
    protected string $fkField = '';

    /**
     * Normalized max records value:
     * - "1" / "<N>" / "n" / "unlimited"
     */
    protected string $maxRecords = 'n';

    /**
     * Closure-root field stored on child records.
     */
    protected string $rootField = 'root_id';

    /**
     * Root record id for the current form context.
     */
    protected int $rootId = 0;

    public function beforeRender(array $fields): array
    {
        $modulePage = trim((string) $this->builder->getPage());
        if ($modulePage === '') {
            return $fields;
        }

        foreach ($fields as $fieldName => $field) {
            if (!is_array($field)) {
                continue;
            }

            $formType = strtolower(trim((string) ($field['form-type'] ?? $field['type'] ?? '')));
            if (!in_array($formType, ['file', 'image'], true)) {
                continue;
            }

            $formParams = is_array($field['form-params'] ?? null) ? $field['form-params'] : [];
            if (!array_key_exists('download-link', $formParams)) {
                $formParams['download-link'] = true;
                $fields[$fieldName]['form-params'] = $formParams;
            }
            if (!$this->isTruthy($formParams['download-link'] ?? false)) {
                continue;
            }

            $downloadAction = $this->normalizeDownloadAction((string) ($formParams['download-action'] ?? 'download-file'));
            $currentValue = $field['row_value'] ?? null;
            $fields[$fieldName]['row_value'] = $this->appendDownloadUrls($currentValue, $modulePage, $downloadAction);
        }

        return $fields;
    }

    public function beforeSave(array $request): array
    {
        $rootField = trim($this->rootField);
        if ($rootField !== '' && $this->rootId > 0) {
            // Server-side enforcement: keep root_id consistent with route context.
            $request[$rootField] = $this->rootId;
        }

        $fkField = trim($this->fkField);
        if ($fkField === '') {
            return $request;
        }

        $model = $this->builder->getModel();
        if (!is_object($model) || !method_exists($model, 'getPrimaryKey')) {
            return $request;
        }

        $pk = (string) $model->getPrimaryKey();
        $id = _absint($request[$pk] ?? 0);
        $parentId = _absint($request[$fkField] ?? 0);
        $finiteMax = $this->getFiniteMaxRecords($this->maxRecords);

        // Only enforce on "new" records. Updates are always allowed.
        if ($id > 0 || $parentId <= 0 || $finiteMax <= 0) {
            return $request;
        }

        // For max=1 keep hasOne behavior: convert insert into update when record exists.
        if ($finiteMax === 1) {
            try {
                $modelClass = get_class($model);
                $lookup = new $modelClass();
                $existing = $lookup->where($fkField . ' = ?', [$parentId])->getRow();

                $existingId = 0;
                if (is_object($existing)) {
                    $existingId = _absint($existing->$pk ?? 0);
                } elseif (is_array($existing)) {
                    $existingId = _absint($existing[$pk] ?? 0);
                }

                if ($existingId > 0) {
                    $request[$pk] = $existingId;
                }
            } catch (\Throwable) {
                // If anything goes wrong, do not block save here.
                return $request;
            }

            return $request;
        }

        // For finite max>1, block creation when limit is reached.
        try {
            $existingCount = $this->countRecordsByParent($model, $fkField, $parentId);
            if ($existingCount >= $finiteMax) {
                MessagesHandler::addError("Maximum {$finiteMax} records reached for this parent context.");
            }
        } catch (\Throwable) {
            // If anything goes wrong, do not block save here.
            return $request;
        }

        return $request;
    }

    protected function getFiniteMaxRecords(string $maxRecords): int
    {
        $v = strtolower(trim($maxRecords));
        if ($v === '' || $v === 'n' || $v === 'unlimited') {
            return 0;
        }
        if (!ctype_digit($v)) {
            return 0;
        }
        $n = (int) $v;
        return $n > 0 ? $n : 0;
    }

    protected function countRecordsByParent(object $model, string $fkField, int $parentId): int
    {
        $modelClass = get_class($model);
        $lookup = new $modelClass();
        $results = $lookup->where($fkField . ' = ?', [$parentId])->getResults();
        if (is_object($results) && method_exists($results, 'count')) {
            return (int) $results->count();
        }
        if (is_array($results)) {
            return count($results);
        }
        return 0;
    }

    protected function appendDownloadUrls(mixed $value, string $modulePage, string $downloadAction): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                return $value;
            }
        } elseif (is_object($value)) {
            $value = (array) $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $index => $item) {
            if (is_object($item)) {
                $item = (array) $item;
            }
            if (!is_array($item)) {
                continue;
            }

            $fileUrl = trim((string) ($item['url'] ?? ''));
            if ($fileUrl === '') {
                $value[$index] = $item;
                continue;
            }

            $normalizedFilename = Module::normalizeDownloadFilename($fileUrl);
            if ($normalizedFilename === '') {
                $value[$index] = $item;
                continue;
            }

            $tokenName = Module::buildDownloadTokenName($modulePage, $normalizedFilename);
            $tokenValue = Token::get($tokenName);
            $item['download_url'] = '?page=' . rawurlencode($modulePage)
                . '&action=' . rawurlencode($downloadAction)
                . '&filename=' . rawurlencode($normalizedFilename)
                . '&token=' . rawurlencode($tokenValue);

            $value[$index] = $item;
        }

        return $value;
    }

    protected function normalizeDownloadAction(string $action): string
    {
        $action = strtolower(trim($action));
        $action = preg_replace('/[^a-z0-9_-]/', '', $action);
        return $action !== '' ? $action : 'download-file';
    }

    protected function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
