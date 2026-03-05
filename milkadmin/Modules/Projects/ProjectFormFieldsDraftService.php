<?php

namespace Modules\Projects;

!defined('MILK_DIR') && die();

use Modules\Projects\DraftService\DraftContainerNormalizer;
use Modules\Projects\DraftService\DraftDatabaseChangeVerifier;
use Modules\Projects\DraftService\DraftFieldNormalizer;
use Modules\Projects\DraftService\DraftFieldReviewAnalyzer;
use Modules\Projects\DraftService\DraftFieldUtils;
use Modules\Projects\DraftService\DraftJsonFileHandler;
use Modules\Projects\DraftService\DraftModelResolver;
use Modules\Projects\DraftService\DraftSchemaBuilder;
use Modules\Projects\DraftService\DraftSessionStore;
use Modules\Projects\DraftService\DraftUrlBuilder;

/**
 * Facade that preserves the original public API.
 *
 * All logic has been refactored into dedicated service classes under
 * the Modules\Projects\DraftService namespace. This class delegates
 * every call so that existing consumers continue to work unchanged.
 */
class ProjectFormFieldsDraftService
{
    // ─── Public entry-points (unchanged signatures) ────────────────────

    public static function saveDraftFromRawInput(string|false $jsonData, string $projectsPage): array
    {
        $input = is_string($jsonData) ? json_decode($jsonData, true) : null;
        if (!is_array($input)) {
            return ['success' => false, 'msg' => 'Invalid JSON payload.'];
        }

        return self::saveDraft($input, $projectsPage);
    }

    /**
     * @param array<string,mixed> $editorPayload
     * @param array<string,mixed>|null $project
     * @return array<string,mixed>
     */
    public static function applyDraftToEditorPayload(array $editorPayload, ?array $project, string $draftToken): array
    {
        $draftToken = trim($draftToken);
        if ($draftToken === '' || !is_array($project)) {
            return $editorPayload;
        }

        $moduleName = trim((string) ($project['module_name'] ?? ''));
        $refBase = basename(trim((string) ($editorPayload['resolved_ref'] ?? '')));
        if ($moduleName === '' || $refBase === '') {
            return $editorPayload;
        }

        $entry = DraftSessionStore::get($draftToken);
        if (!is_array($entry)) {
            return $editorPayload;
        }

        if (strcasecmp((string) ($entry['module_name'] ?? ''), $moduleName) !== 0) {
            return $editorPayload;
        }
        if (strcasecmp((string) ($entry['ref'] ?? ''), $refBase) !== 0) {
            return $editorPayload;
        }

        $draftFields = is_array($entry['draft_fields'] ?? null) ? $entry['draft_fields'] : [];
        $draftContainers = is_array($entry['draft_containers'] ?? null) ? $entry['draft_containers'] : [];

        $fallbackFields = is_array($editorPayload['fields'] ?? null) ? $editorPayload['fields'] : [];
        $fallbackMap = [];
        foreach ($fallbackFields as $fallbackField) {
            if (!is_array($fallbackField)) {
                continue;
            }
            $name = trim((string) ($fallbackField['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $fallbackMap[strtolower($name)] = $fallbackField;
        }

        $appliedFields = [];
        foreach ($draftFields as $draftField) {
            if (!is_array($draftField)) {
                continue;
            }

            $name = trim((string) ($draftField['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $nameLower = strtolower($name);
            $fallback = is_array($fallbackMap[$nameLower] ?? null) ? $fallbackMap[$nameLower] : [];

            $config = is_array($draftField['config'] ?? null) ? $draftField['config'] : [];
            if (empty($config)) {
                $config = [
                    'field_name' => $name,
                    'field_label' => DraftFieldUtils::toTitle($name),
                    'type' => 'string',
                    '_draft_minimal' => true,
                ];
            }
            if (trim((string) ($config['field_name'] ?? '')) === '') {
                $config['field_name'] = $name;
            }

            $appliedFields[] = [
                'name' => $name,
                'builder_locked' => DraftFieldUtils::normalizeBool(
                    $draftField['builder_locked']
                    ?? ($draftField['builderLocked'] ?? ($fallback['builder_locked'] ?? ($fallback['builderLocked'] ?? false)))
                ),
                'can_delete' => array_key_exists('can_delete', $draftField)
                    ? DraftFieldUtils::normalizeBool($draftField['can_delete'])
                    : DraftFieldUtils::normalizeBool($fallback['can_delete'] ?? true),
                'config' => $config,
            ];
        }

        if (!empty($appliedFields)) {
            $editorPayload['fields'] = $appliedFields;
        }

        $activeFields = is_array($editorPayload['fields'] ?? null) ? $editorPayload['fields'] : [];
        $activeFieldMap = [];
        foreach ($activeFields as $activeField) {
            if (!is_array($activeField)) {
                continue;
            }
            $activeName = trim((string) ($activeField['name'] ?? ''));
            if ($activeName === '') {
                continue;
            }
            $activeFieldMap[strtolower($activeName)] = true;
        }

        $fallbackContainers = is_array($editorPayload['containers'] ?? null) ? $editorPayload['containers'] : [];
        $rawContainers = !empty($draftContainers) ? $draftContainers : $fallbackContainers;
        $containersResult = DraftContainerNormalizer::normalize($rawContainers, $activeFieldMap, false);
        if (is_array($containersResult['containers'] ?? null)) {
            $editorPayload['containers'] = $containersResult['containers'];
        }

        $editorPayload['draft_token'] = $draftToken;

        return $editorPayload;
    }

    /**
     * @param array<string,mixed>|null $project
     * @return array{
     *   can_review:bool,
     *   error:string,
     *   module_name:string,
     *   ref:string,
     *   form_name:string,
     *   draft_token:string,
     *   old_json_pretty:string,
     *   new_json_pretty:string,
     *   field_changes:array<int,array{
     *     name:string,
     *     status:string,
     *     status_label:string,
     *     before_lines:array<int,string>,
     *     after_lines:array<int,string>,
     *     changed_keys:array<int,string>,
     *     risk_level:string,
     *     risk_label:string,
     *     risk_note:string,
     *     db_check_level:string,
     *     db_check_label:string,
     *     db_check_note:string,
     *     db_check_lines:array<int,string>
     *   }>,
     *   field_changes_summary:array{
     *     total:int,
     *     unchanged:int,
     *     modified:int,
     *     added:int,
     *     removed:int,
     *     warnings:int,
     *     dangers:int
     *   },
     *   db_check_message:string,
     *   accept_url:string,
     *   back_to_edit_url:string
     * }
     */
    public static function buildReviewPageData(?array $project, string $requestedRef, string $draftToken, string $projectsPage): array
    {
        $payload = [
            'can_review' => false,
            'error' => '',
            'module_name' => '',
            'ref' => '',
            'form_name' => '',
            'draft_token' => '',
            'old_json_pretty' => '',
            'new_json_pretty' => '',
            'field_changes' => [],
            'field_changes_summary' => [
                'total' => 0,
                'unchanged' => 0,
                'modified' => 0,
                'added' => 0,
                'removed' => 0,
                'warnings' => 0,
                'dangers' => 0,
            ],
            'db_check_message' => '',
            'accept_url' => '',
            'back_to_edit_url' => '',
        ];

        if (!is_array($project)) {
            $payload['error'] = 'Project not found.';
            return $payload;
        }

        $moduleName = trim((string) ($project['module_name'] ?? ''));
        $refBase = basename(trim($requestedRef));
        $draftToken = trim($draftToken);
        if ($moduleName === '' || $refBase === '' || $draftToken === '') {
            $payload['error'] = 'Missing module/ref/draft token.';
            return $payload;
        }

        $entry = DraftSessionStore::get($draftToken);
        if (!is_array($entry)) {
            $payload['error'] = 'Draft not found. Save again from the editor.';
            return $payload;
        }

        if (strcasecmp((string) ($entry['module_name'] ?? ''), $moduleName) !== 0) {
            $payload['error'] = 'Draft does not match the selected project.';
            return $payload;
        }
        if (strcasecmp((string) ($entry['ref'] ?? ''), $refBase) !== 0) {
            $payload['error'] = 'Draft does not match the selected form.';
            return $payload;
        }

        $oldSchema = is_array($entry['old_schema'] ?? null) ? $entry['old_schema'] : null;
        $newSchema = is_array($entry['new_schema'] ?? null) ? $entry['new_schema'] : null;
        if (!is_array($oldSchema) || !is_array($newSchema)) {
            $payload['error'] = 'Invalid draft content.';
            return $payload;
        }

        $payload['can_review'] = true;
        $payload['module_name'] = $moduleName;
        $payload['ref'] = $refBase;
        $payload['form_name'] = trim((string) pathinfo($refBase, PATHINFO_FILENAME));
        $payload['draft_token'] = $draftToken;
        $payload['old_json_pretty'] = DraftJsonFileHandler::prettyJson($oldSchema);
        $payload['new_json_pretty'] = DraftJsonFileHandler::prettyJson($newSchema);
        $fieldReview = DraftFieldReviewAnalyzer::build($oldSchema, $newSchema);
        $payload['field_changes'] = is_array($fieldReview['rows'] ?? null) ? $fieldReview['rows'] : [];
        $payload['field_changes_summary'] = is_array($fieldReview['summary'] ?? null) ? $fieldReview['summary'] : $payload['field_changes_summary'];
        $dbCheck = DraftDatabaseChangeVerifier::analyze($project, $refBase, $oldSchema, $newSchema, $payload['field_changes']);
        $payload['db_check_message'] = trim((string) ($dbCheck['message'] ?? ''));
        $dbRows = is_array($dbCheck['rows'] ?? null) ? $dbCheck['rows'] : [];
        foreach ($payload['field_changes'] as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $dbRow = is_array($dbRows[strtolower($name)] ?? null) ? $dbRows[strtolower($name)] : [];

            $payload['field_changes'][$index]['db_check_level'] = trim((string) ($dbRow['level'] ?? 'unknown'));
            $payload['field_changes'][$index]['db_check_label'] = trim((string) ($dbRow['label'] ?? 'Not verified'));
            $payload['field_changes'][$index]['db_check_note'] = trim((string) ($dbRow['note'] ?? ''));
            $payload['field_changes'][$index]['db_check_lines'] = is_array($dbRow['lines'] ?? null) ? $dbRow['lines'] : [];
        }
        $payload['accept_url'] = DraftUrlBuilder::accept($projectsPage, $moduleName, $refBase, $draftToken);
        $payload['back_to_edit_url'] = DraftUrlBuilder::edit($projectsPage, $moduleName, $refBase, $draftToken);

        return $payload;
    }

    /**
     * @param array<string,mixed>|null $project
     * @return array{success:bool,msg:string,redirect_url:string}
     */
    public static function acceptDraft(?array $project, string $requestedRef, string $draftToken, string $projectsPage): array
    {
        if (!is_array($project)) {
            return [
                'success' => false,
                'msg' => 'Project not found.',
                'redirect_url' => DraftUrlBuilder::projectsHome($projectsPage),
            ];
        }

        $moduleName = trim((string) ($project['module_name'] ?? ''));
        $refBase = basename(trim($requestedRef));
        $draftToken = trim($draftToken);
        $editUrl = ($moduleName !== '' && $refBase !== '')
            ? DraftUrlBuilder::edit($projectsPage, $moduleName, $refBase, $draftToken)
            : ($moduleName !== ''
                ? DraftUrlBuilder::buildForms($projectsPage, $moduleName)
                : DraftUrlBuilder::projectsHome($projectsPage));
        if ($moduleName === '' || $refBase === '' || $draftToken === '') {
            return [
                'success' => false,
                'msg' => 'Missing module/ref/draft token.',
                'redirect_url' => $editUrl,
            ];
        }

        $structureAccess = ProjectSettingsService::evaluateStructureEditAccess($project);
        if (empty($structureAccess['can_edit_structure'])) {
            return [
                'success' => false,
                'msg' => (string) ($structureAccess['message'] ?? 'Structure editing is disabled for this project.'),
                'redirect_url' => $editUrl,
            ];
        }

        $entry = DraftSessionStore::get($draftToken);
        if (!is_array($entry)) {
            return [
                'success' => false,
                'msg' => 'Draft not found. Save again from the editor.',
                'redirect_url' => $editUrl,
            ];
        }

        if (strcasecmp((string) ($entry['module_name'] ?? ''), $moduleName) !== 0
            || strcasecmp((string) ($entry['ref'] ?? ''), $refBase) !== 0) {
            return [
                'success' => false,
                'msg' => 'Draft does not match project/form.',
                'redirect_url' => $editUrl,
            ];
        }

        $oldSchema = is_array($entry['old_schema'] ?? null) ? $entry['old_schema'] : null;
        $newSchema = is_array($entry['new_schema'] ?? null) ? $entry['new_schema'] : null;
        if (!is_array($oldSchema) || !is_array($newSchema)) {
            return [
                'success' => false,
                'msg' => 'Draft content is invalid.',
                'redirect_url' => DraftUrlBuilder::review($projectsPage, $moduleName, $refBase, $draftToken),
            ];
        }

        $schemaPath = DraftModelResolver::resolveSchemaPath($project, $refBase);
        if ($schemaPath === '') {
            return [
                'success' => false,
                'msg' => 'Schema file not found.',
                'redirect_url' => DraftUrlBuilder::review($projectsPage, $moduleName, $refBase, $draftToken),
            ];
        }

        if (!DraftJsonFileHandler::write($schemaPath, $newSchema)) {
            return [
                'success' => false,
                'msg' => 'Failed to write schema JSON file.',
                'redirect_url' => DraftUrlBuilder::review($projectsPage, $moduleName, $refBase, $draftToken),
            ];
        }

        $existingTableLocked = DraftModelResolver::isExistingTableLockedForRef($project, $refBase);
        if ($existingTableLocked) {
            DraftSessionStore::remove($draftToken);
            return [
                'success' => true,
                'msg' => 'Form JSON updated. Table schema update skipped because manifest marks this form as existingTable.',
                'redirect_url' => DraftUrlBuilder::edit($projectsPage, $moduleName, $refBase, ''),
            ];
        }

        $modelInfo = DraftModelResolver::resolveModelInfoForRef($project, $refBase);
        if (empty($modelInfo['success'])) {
            $rollbackMsg = self::rollbackAcceptedChanges($schemaPath, $oldSchema);
            return [
                'success' => false,
                'msg' => trim((string) ($modelInfo['error'] ?? 'Unable to resolve model for table update.')) . $rollbackMsg,
                'redirect_url' => DraftUrlBuilder::review($projectsPage, $moduleName, $refBase, $draftToken),
            ];
        }

        $tableUpdate = ProjectTableService::buildDatabaseTable(
            (string) ($modelInfo['model_file_path'] ?? ''),
            (string) ($modelInfo['model_fqcn'] ?? '')
        );
        if (empty($tableUpdate['success'])) {
            $tableError = trim((string) ($tableUpdate['error'] ?? 'unknown error'));
            if ($tableError === '') {
                $tableError = 'unknown error';
            }
            $rollbackMsg = self::rollbackAcceptedChanges($schemaPath, $oldSchema);
            return [
                'success' => false,
                'msg' => 'Failed to update table schema: ' . $tableError . $rollbackMsg,
                'redirect_url' => DraftUrlBuilder::review($projectsPage, $moduleName, $refBase, $draftToken),
            ];
        }

        DraftSessionStore::remove($draftToken);

        return [
            'success' => true,
            'msg' => 'Form saved',
            'redirect_url' => DraftUrlBuilder::edit($projectsPage, $moduleName, $refBase, ''),
        ];
    }

    // ─── Private orchestration ─────────────────────────────────────────

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,msg:string,redirect_url:string}
     */
    private static function saveDraft(array $input, string $projectsPage): array
    {
        $moduleName = trim((string) ($input['module'] ?? ''));
        if ($moduleName === '' || preg_match('/^[A-Za-z0-9_-]+$/', $moduleName) !== 1) {
            return ['success' => false, 'msg' => 'Invalid module name.', 'redirect_url' => ''];
        }

        $refBase = basename(trim((string) ($input['ref'] ?? '')));
        if ($refBase === '') {
            return ['success' => false, 'msg' => 'Invalid ref.', 'redirect_url' => ''];
        }

        $project = ProjectCatalogService::findProjectByModuleName($moduleName, $projectsPage);
        if (!is_array($project)) {
            return ['success' => false, 'msg' => 'Project not found.', 'redirect_url' => ''];
        }

        $structureAccess = ProjectSettingsService::evaluateStructureEditAccess($project);
        if (empty($structureAccess['can_edit_structure'])) {
            return [
                'success' => false,
                'msg' => (string) ($structureAccess['message'] ?? 'Structure editing is disabled for this project.'),
                'redirect_url' => '',
            ];
        }

        $schemaPath = DraftModelResolver::resolveSchemaPath($project, $refBase);
        if ($schemaPath === '') {
            return ['success' => false, 'msg' => 'Schema JSON file not found.', 'redirect_url' => ''];
        }

        $oldSchema = DraftJsonFileHandler::read($schemaPath);
        if (!is_array($oldSchema)) {
            return ['success' => false, 'msg' => 'Current schema JSON is invalid.', 'redirect_url' => ''];
        }

        $rawFields = is_array($input['fields'] ?? null) ? $input['fields'] : [];
        $draftFieldsResult = DraftFieldNormalizer::normalize($rawFields);
        if (!empty($draftFieldsResult['error'])) {
            return ['success' => false, 'msg' => (string) $draftFieldsResult['error'], 'redirect_url' => ''];
        }

        $draftFields = is_array($draftFieldsResult['fields'] ?? null) ? $draftFieldsResult['fields'] : [];
        $allowedFieldMap = [];
        foreach ($draftFields as $draftField) {
            if (!is_array($draftField)) {
                continue;
            }
            $name = trim((string) ($draftField['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $allowedFieldMap[strtolower($name)] = true;
        }

        $rawContainers = is_array($input['containers'] ?? null) ? $input['containers'] : [];
        $draftContainersResult = DraftContainerNormalizer::normalize($rawContainers, $allowedFieldMap, true);
        if (!empty($draftContainersResult['error'])) {
            return ['success' => false, 'msg' => (string) $draftContainersResult['error'], 'redirect_url' => ''];
        }
        $draftContainers = is_array($draftContainersResult['containers'] ?? null) ? $draftContainersResult['containers'] : [];

        $newSchema = DraftSchemaBuilder::build($oldSchema, $draftFields, $draftContainers);

        $draftToken = DraftSessionStore::generateToken();
        DraftSessionStore::put($draftToken, [
            'module_name' => $moduleName,
            'ref' => $refBase,
            'schema_path' => $schemaPath,
            'old_schema' => $oldSchema,
            'new_schema' => $newSchema,
            'draft_fields' => $draftFields,
            'draft_containers' => $draftContainers,
            'updated_at' => time(),
        ]);

        return [
            'success' => true,
            'msg' => 'Draft prepared. Review changes before applying.',
            'redirect_url' => DraftUrlBuilder::review($projectsPage, $moduleName, $refBase, $draftToken),
        ];
    }

    /**
     * @param array<string,mixed> $oldSchema
     */
    private static function rollbackAcceptedChanges(string $schemaPath, array $oldSchema): string
    {
        $warnings = [];

        if (!DraftJsonFileHandler::write($schemaPath, $oldSchema)) {
            $warnings[] = 'schema rollback failed';
        }

        if (empty($warnings)) {
            return '';
        }

        return ' Rollback warning: ' . implode(', ', $warnings) . '.';
    }
}
