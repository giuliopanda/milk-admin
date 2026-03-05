<?php
namespace Extensions\Projects\Classes;

use App\Exceptions\FileException;
use App\File;

!defined('MILK_DIR') && die();

/**
 * Parser/validator for Projects manifest files (tree-based).
 *
 * New format (single-root):
 * {
 *   "version": "1.0",
 *   "name": "Project Title",
 *   "description": "Project description",
 *   "ref": "Root.json",
 *   "viewSingleRecord": true,
 *   "forms": [ { "ref": "Child.json", "max_records": 1 } ]
 * }
 *
 * Tree-root format is also accepted:
 * {
 *   "version": "1.0",
 *   "name": "Project Title",
 *   "description": "Project description",
 *   "forms": [ { "ref": "Root.json", "forms": [...] } ]
 * }
 */
class ProjectManifestParser
{
    public function parseFile(string $path): ProjectManifest
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Manifest file not found: {$path}");
        }

        try {
            $content = File::getContents($path);
        } catch (FileException $e) {
            throw new \RuntimeException("Manifest file is empty or unreadable: {$path}", 0, $e);
        }

        if (trim($content) === '') {
            throw new \RuntimeException("Manifest file is empty or unreadable: {$path}");
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Invalid manifest JSON at {$path}: {$e->getMessage()}");
        }

        if (!is_array($data)) {
            throw new \RuntimeException("Manifest root must be an object: {$path}");
        }

        return $this->parseArray($data, $path);
    }

    /**
     * Parse a decoded manifest array.
     *
     * @param array<string,mixed> $data
     */
    public function parseArray(array $data, string $sourceLabel = 'manifest'): ProjectManifest
    {
        if (!is_array($data)) {
            throw new \RuntimeException("Manifest root must be an object: {$sourceLabel}");
        }

        $description = trim((string) ($data['description'] ?? ''));
        $settings = $description !== '' ? ['description' => $description] : [];

        $version = trim((string) ($data['version'] ?? '1.0'));
        if ($version === '') {
            $version = '1.0';
        }

        $name = trim((string) ($data['name'] ?? ''));
        $usesTopLevelRoot = trim((string) ($data['ref'] ?? '')) !== '';

        if ($usesTopLevelRoot) {
            $rootEntry = $this->normalizeFormEntry($data, "Manifest root");
            if (array_key_exists('forms', $data)) {
                $rootEntry['forms'] = $this->normalizeFormsTree($data['forms']);
            }
            $formsTree = [$rootEntry];
        } else {
            $formsTree = $this->normalizeFormsTree($data['forms'] ?? []);
        }

        if (count($formsTree) !== 1) {
            throw new \RuntimeException(
                "Manifest must define exactly one root form. Found " . count($formsTree) . "."
            );
        }

        return new ProjectManifest(
            $version,
            $name,
            $settings,
            $formsTree
        );
    }

    /**
     * @param mixed $forms
 * @return array<int,array{
 *   ref:string,
 *   max_records?:int|string,
 *   showIf?:string,
 *   showIfMessage?:string,
 *   showSearch?:bool,
 *   softDelete?:bool,
 *   allowDeleteRecord?:bool,
 *   allowEdit?:bool,
 *   defaultOrderEnabled?:bool,
 *   defaultOrderField?:string,
 *   defaultOrderDirection?:string,
 *   viewAction?:bool,
 *   childCountColumn?:string,
 *   viewDisplay?:string,
 *   listDisplay?:string,
 *   editDisplay?:string,
 *   forms?:array
 * }>
     */
    protected function normalizeFormsTree(mixed $forms): array
    {
        if (!is_array($forms)) {
            throw new \RuntimeException("Manifest key 'forms' must be an array.");
        }

        $normalized = [];
        foreach ($forms as $index => $form) {
            if (!is_array($form)) {
                throw new \RuntimeException("Form entry at index {$index} must be an object.");
            }

            $entry = $this->normalizeFormEntry($form, "Form entry at index {$index}");

            if (array_key_exists('forms', $form)) {
                $entry['forms'] = $this->normalizeFormsTree($form['forms']);
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $form
     * @return array{
     *   ref:string,
     *   max_records?:int|string,
     *   showIf?:string,
     *   showIfMessage?:string,
     *   showSearch?:bool,
     *   softDelete?:bool,
     *   allowDeleteRecord?:bool,
     *   allowEdit?:bool,
     *   defaultOrderEnabled?:bool,
     *   defaultOrderField?:string,
     *   defaultOrderDirection?:string,
     *   viewAction?:bool,
     *   childCountColumn?:string,
     *   viewDisplay?:string,
     *   listDisplay?:string,
     *   editDisplay?:string
     * }
     */
    protected function normalizeFormEntry(array $form, string $contextLabel): array
    {
        $ref = trim((string) ($form['ref'] ?? ''));
        if ($ref === '') {
            throw new \RuntimeException("{$contextLabel} is missing a valid 'ref'.");
        }

        $entry = ['ref' => $ref];

        $maxRecords = ProjectJsonStore::resolveAliasedKey($form, ['max_records', 'maxRecords'], null);
        if ($maxRecords !== null && $maxRecords !== '') {
            $entry['max_records'] = $maxRecords;
        }

        $showIf = trim((string) ProjectJsonStore::resolveAliasedKey($form, ['showIf', 'show_if'], ''));
        if ($showIf !== '') {
            $entry['showIf'] = $showIf;
        }

        $showIfMessage = trim((string) ProjectJsonStore::resolveAliasedKey($form, ['showIfMessage', 'show_if_message'], ''));
        if ($showIfMessage !== '') {
            $entry['showIfMessage'] = $showIfMessage;
        }

        $showSearch = ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey($form, ['showSearch', 'show_search'], null)
        );
        if ($showSearch) {
            $entry['showSearch'] = true;
        }

        $softDelete = ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey($form, ['softDelete', 'soft_delete'], null)
        );
        if ($softDelete) {
            $entry['softDelete'] = true;
        }

        $hasAllowDeleteRecord = ProjectJsonStore::hasAliasedKey($form, ['allowDeleteRecord', 'allow_delete_record']);
        if ($hasAllowDeleteRecord) {
            $allowDeleteRecordRaw = ProjectJsonStore::resolveAliasedKey($form, ['allowDeleteRecord', 'allow_delete_record'], true);
            $entry['allowDeleteRecord'] = ProjectJsonStore::normalizeBool($allowDeleteRecordRaw);
        }

        $hasAllowEdit = ProjectJsonStore::hasAliasedKey($form, ['allowEdit', 'allow_edit']);
        if ($hasAllowEdit) {
            $allowEditRaw = ProjectJsonStore::resolveAliasedKey($form, ['allowEdit', 'allow_edit'], true);
            $entry['allowEdit'] = ProjectJsonStore::normalizeBool($allowEditRaw);
        }

        $hasDefaultOrderEnabled = ProjectJsonStore::hasAliasedKey($form, ['defaultOrderEnabled', 'default_order_enabled']);
        if ($hasDefaultOrderEnabled) {
            $defaultOrderEnabledRaw = ProjectJsonStore::resolveAliasedKey(
                $form,
                ['defaultOrderEnabled', 'default_order_enabled'],
                false
            );
            $entry['defaultOrderEnabled'] = ProjectJsonStore::normalizeBool($defaultOrderEnabledRaw);
        }

        $defaultOrderField = trim((string) ProjectJsonStore::resolveAliasedKey(
            $form,
            ['defaultOrderField', 'default_order_field'],
            ''
        ));
        if ($defaultOrderField !== '') {
            $entry['defaultOrderField'] = $defaultOrderField;
        }

        $hasDefaultOrderDirection = ProjectJsonStore::hasAliasedKey($form, ['defaultOrderDirection', 'default_order_direction']);
        if ($hasDefaultOrderDirection) {
            $entry['defaultOrderDirection'] = $this->normalizeOrderDirection((string) ProjectJsonStore::resolveAliasedKey(
                $form,
                ['defaultOrderDirection', 'default_order_direction'],
                'asc'
            ));
        }

        $viewAction = ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey(
                $form,
                ['viewSingleRecord', 'view_single_record', 'viewAction', 'view_action'],
                null
            )
        );
        if ($viewAction) {
            $entry['viewAction'] = true;
        }

        $childCountColumn = ProjectJsonStore::normalizeChildCountColumnMode(
            ProjectJsonStore::resolveAliasedKey(
                $form,
                ['childCountColumn', 'child_count_column'],
                ''
            )
        );
        if ($childCountColumn !== '') {
            $entry['childCountColumn'] = $childCountColumn;
        }

        $viewDisplay = ProjectJsonStore::normalizeDisplayMode(
            ProjectJsonStore::resolveAliasedKey($form, ['viewDisplay', 'view_display'], null),
            ''
        );
        if ($viewDisplay !== '') {
            $entry['viewDisplay'] = $viewDisplay;
        }

        $listDisplay = ProjectJsonStore::normalizeDisplayMode(
            ProjectJsonStore::resolveAliasedKey($form, ['listDisplay', 'list_display'], null),
            ''
        );
        if ($listDisplay !== '') {
            $entry['listDisplay'] = $listDisplay;
        }

        $editDisplay = ProjectJsonStore::normalizeDisplayMode(
            ProjectJsonStore::resolveAliasedKey($form, ['editDisplay', 'edit_display'], null),
            ''
        );
        if ($editDisplay !== '') {
            $entry['editDisplay'] = $editDisplay;
        }

        return $entry;
    }

    protected function normalizeOrderDirection(string $direction): string
    {
        $direction = strtolower(trim($direction));
        return $direction === 'desc' ? 'desc' : 'asc';
    }
}
