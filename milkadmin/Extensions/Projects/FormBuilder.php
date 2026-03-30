<?php
namespace Extensions\Projects;

use App\Abstracts\AbstractFormBuilderExtension;
use App\{Hooks, MessagesHandler, Token};
use Extensions\Projects\Classes\ProjectJsonStore;

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

    /**
     * AutoEdit context resolved from ActionContextRegistry.
     * @var array<string,mixed>
     */
    protected array $projectsContext = [];

    /**
     * Current project manifest.json data (if available).
     * @var array<string,mixed>|null
     */
    protected ?array $projectsManifest = null;

    /**
     * Module page that owns the current project form.
     */
    protected string $projectsModulePage = '';

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
        if ($fkField !== '') {
            $model = $this->builder->getModel();
            if (is_object($model) && method_exists($model, 'getPrimaryKey')) {
                $pk = (string) $model->getPrimaryKey();
                $id = _absint($request[$pk] ?? 0);
                $parentId = _absint($request[$fkField] ?? 0);
                $finiteMax = $this->getFiniteMaxRecords($this->maxRecords);

                // Only enforce on "new" records. Updates are always allowed.
                if (!($id > 0 || $parentId <= 0 || $finiteMax <= 0)) {
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
                        }
                    } else {
                        // For finite max>1, block creation when limit is reached.
                        try {
                            $existingCount = $this->countRecordsByParent($model, $fkField, $parentId);
                            if ($existingCount >= $finiteMax) {
                                MessagesHandler::addError("Maximum {$finiteMax} records reached for this parent context.");
                            }
                        } catch (\Throwable) {
                            // If anything goes wrong, do not block save here.
                        }
                    }
                }
            }
        }

        return $this->runProjectsPreSaveHooks($request);
    }

    /**
     * Run Projects pre-save hooks and allow payload handlers to modify request data.
     *
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    protected function runProjectsPreSaveHooks(array $request): array
    {
        $payload = $this->buildProjectsSavePayload($request);
        $isCreate = (bool) ($payload['is_create'] ?? false);
        $isRelated = (bool) ($payload['is_related_table'] ?? false);

        $mainHook = $isCreate
            ? 'projects.record.create.before-save'
            : 'projects.record.update.before-save';
        $payload = $this->runProjectsSaveHook($mainHook, $payload);

        if ($isRelated) {
            $payload = $this->runProjectsSaveHook('projects.record.related.before-save', $payload);
        }

        $updatedRequest = is_array($payload['request'] ?? null) ? $payload['request'] : $request;
        return $updatedRequest;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    protected function runProjectsSaveHook(string $hookName, array $payload): array
    {
        $payload['hook'] = $hookName;
        $payload['stage'] = 'before_save_query';

        $hookResult = Hooks::run(
            $hookName,
            $payload,
            $this->builder,
            $payload['request'] ?? [],
            $payload['context'] ?? [],
            $payload['manifest'] ?? null
        );

        return is_array($hookResult) ? $hookResult : $payload;
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    protected function buildProjectsSavePayload(array $request): array
    {
        $modulePage = $this->resolveProjectsModulePage();
        $model = $this->builder->getModel();
        $primaryKey = is_object($model) && method_exists($model, 'getPrimaryKey')
            ? (string) $model->getPrimaryKey()
            : 'id';
        $recordId = $primaryKey !== '' ? _absint($request[$primaryKey] ?? 0) : 0;
        $isCreate = $recordId <= 0;

        $context = $this->projectsContext;
        $isRelatedTable = $this->resolveProjectsIsRelatedTable($context);
        $manifest = $this->resolveProjectsManifest($modulePage);

        $tableName = is_object($model) && method_exists($model, 'getTable')
            ? (string) $model->getTable()
            : '';
        $formName = $this->resolveProjectsFormName($context, $model);

        return [
            'hook' => '',
            'stage' => 'before_save_query',
            'operation' => $isCreate ? 'create' : 'update',
            'is_create' => $isCreate,
            'is_update' => !$isCreate,
            'is_related_table' => $isRelatedTable,
            'page' => $modulePage,
            'request' => $request,
            'record_id' => $recordId,
            'primary_key' => $primaryKey,
            'model' => $model,
            'model_class' => is_object($model) ? get_class($model) : '',
            'table' => $tableName,
            'form_name' => $formName,
            'fk_field' => trim($this->fkField),
            'root_field' => trim($this->rootField),
            'root_id' => _absint($this->rootId),
            'max_records' => $this->maxRecords,
            'manifest' => $manifest,
            'context' => $context,
        ];
    }

    protected function resolveProjectsModulePage(): string
    {
        $modulePage = trim($this->projectsModulePage);
        if ($modulePage === '' && method_exists($this->builder, 'getPage')) {
            $modulePage = trim((string) $this->builder->getPage());
        }
        if ($modulePage === '') {
            $modulePage = trim((string) ($_REQUEST['page'] ?? ''));
        }

        return $modulePage;
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function resolveProjectsIsRelatedTable(array $context): bool
    {
        $isRootFromContext = array_key_exists('is_root', $context) ? ((bool) $context['is_root']) : null;
        if ($isRootFromContext === null) {
            return trim($this->fkField) !== '';
        }

        return !$isRootFromContext;
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function resolveProjectsManifest(string $modulePage): ?array
    {
        if (is_array($this->projectsManifest)) {
            return $this->projectsManifest;
        }

        $resolved = $modulePage !== ''
            ? ProjectJsonStore::getCurrentManifestData($modulePage)
            : ProjectJsonStore::getCurrentManifestData();

        return is_array($resolved) ? $resolved : null;
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function resolveProjectsFormName(array $context, mixed $model): string
    {
        $formName = trim((string) ($context['form_name'] ?? ''));
        if ($formName !== '' || !is_object($model)) {
            return $formName;
        }

        $modelClass = get_class($model);
        $short = strrpos($modelClass, '\\') !== false
            ? substr($modelClass, (int) strrpos($modelClass, '\\') + 1)
            : $modelClass;

        return preg_replace('/Model$/', '', (string) $short) ?? '';
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
