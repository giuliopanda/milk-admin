<?php

namespace Modules\Projects;

!defined('MILK_DIR') && die();

class ProjectViewLayoutService
{
    /**
     * @param array<string,mixed>|null $project
     * @return array{
     *   root_form:string,
     *   cards:array<int,array{
     *     id:string,
     *     type:string,
     *     title:string,
     *     icon:string,
     *     description:string,
     *     rows:array<int,array{
     *       table:string,
     *       display_as:string,
     *       title:string,
     *       icon:string,
     *       visible:bool
     *     }>
     *   }>,
     *   raw_layout_json:string,
     *   layout_abs_path:string,
     *   layout_rel_path:string,
     *   layout_exists:bool,
     *   load_source:string
     * }
     */
    public static function buildEditorData(?array $project): array
    {
        $result = [
            'root_form' => '',
            'cards' => [],
            'raw_layout_json' => "{}\n",
            'layout_abs_path' => '',
            'layout_rel_path' => '',
            'layout_exists' => false,
            'load_source' => 'none',
        ];

        if (!is_array($project)) {
            return $result;
        }

        $manifest = is_array($project['manifest_data'] ?? null) ? $project['manifest_data'] : [];
        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifest === [] && $manifestPath !== '' && is_file($manifestPath)) {
            $readManifest = ManifestService::readManifest($manifestPath);
            if (is_array($readManifest)) {
                $manifest = $readManifest;
            }
        }

        $projectDir = $manifestPath !== '' ? dirname($manifestPath) : '';
        if ($projectDir === '' || !is_dir($projectDir)) {
            return $result;
        }

        $layoutPath = $projectDir . '/view_layout.json';
        $result['layout_abs_path'] = $layoutPath;
        $localPrefix = rtrim((string) LOCAL_DIR, '/\\') . '/';
        $result['layout_rel_path'] = str_starts_with($layoutPath, $localPrefix)
            ? substr($layoutPath, strlen($localPrefix))
            : $layoutPath;
        $result['layout_exists'] = is_file($layoutPath);

        $allowedTables = self::collectManifestFirstLevelTables($manifest);
        if (!empty($allowedTables)) {
            $result['root_form'] = (string) $allowedTables[0];
        }

        $layoutData = null;
        if (is_file($layoutPath)) {
            $layoutData = self::readLayoutFile($layoutPath);
            if (is_array($layoutData)) {
                $result['load_source'] = 'view_layout.json';
            }
        }

        if (!is_array($layoutData)) {
            $layoutData = self::buildDefaultLayout($allowedTables);
            $result['load_source'] = 'generated-default';
            if (!is_file($layoutPath)) {
                $generatedJson = self::encodePrettyJson($layoutData);
                if (@file_put_contents($layoutPath, $generatedJson) !== false) {
                    $result['layout_exists'] = true;
                }
            }
        }

        $normalizedLayout = self::normalizeLayout($layoutData, $allowedTables);
        $result['cards'] = self::layoutToCards($normalizedLayout);
        $result['raw_layout_json'] = self::encodePrettyJson($normalizedLayout);

        return $result;
    }

    public static function saveLayoutFromRawInput(string|false $jsonData, string $projectsPage): array
    {
        $input = is_string($jsonData) ? json_decode($jsonData, true) : null;
        if (!is_array($input)) {
            return ['success' => false, 'msg' => 'Invalid JSON payload.'];
        }

        $moduleName = trim((string) ($input['module'] ?? ''));
        if ($moduleName === '' || preg_match('/^[A-Za-z0-9_-]+$/', $moduleName) !== 1) {
            return ['success' => false, 'msg' => 'Invalid module name.'];
        }

        $layoutInput = is_array($input['layout'] ?? null) ? $input['layout'] : null;
        if (!is_array($layoutInput)) {
            return ['success' => false, 'msg' => 'Missing layout payload.'];
        }

        $project = ProjectCatalogService::findProjectByModuleName($moduleName, $projectsPage);
        if (!is_array($project)) {
            return ['success' => false, 'msg' => 'Project not found.'];
        }

        $manifest = is_array($project['manifest_data'] ?? null) ? $project['manifest_data'] : [];
        $allowedTables = self::collectManifestFirstLevelTables($manifest);

        $manifestPath = trim((string) ($project['manifest_abs_path'] ?? ''));
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return ['success' => false, 'msg' => 'Manifest file not found.'];
        }
        $projectDir = dirname($manifestPath);
        if ($projectDir === '' || !is_dir($projectDir)) {
            return ['success' => false, 'msg' => 'Project directory not found.'];
        }

        $layoutPath = $projectDir . '/view_layout.json';
        $normalizedLayout = self::normalizeLayout($layoutInput, $allowedTables);
        $json = self::encodePrettyJson($normalizedLayout);
        $written = @file_put_contents($layoutPath, $json);
        if ($written === false) {
            return ['success' => false, 'msg' => 'Unable to write view_layout.json.'];
        }

        return [
            'success' => true,
            'msg' => 'View layout saved.',
            'layout_path' => $layoutPath,
        ];
    }

    /**
     * @param array<string,mixed> $manifest
     * @return string[]
     */
    public static function collectManifestFirstLevelTables(array $manifest): array
    {
        $tables = [];
        $rootRef = trim((string) ($manifest['ref'] ?? ''));
        if ($rootRef !== '') {
            $rootName = trim((string) pathinfo($rootRef, PATHINFO_FILENAME));
            if ($rootName !== '') {
                $tables[$rootName] = true;
            }
        }

        $forms = is_array($manifest['forms'] ?? null) ? $manifest['forms'] : [];
        foreach ($forms as $formNode) {
            if (!is_array($formNode)) {
                continue;
            }
            $ref = trim((string) ($formNode['ref'] ?? ''));
            if ($ref === '') {
                continue;
            }
            $formName = trim((string) pathinfo($ref, PATHINFO_FILENAME));
            if ($formName !== '') {
                $tables[$formName] = true;
            }
        }

        return array_values(array_keys($tables));
    }

    /**
     * @param string[] $allowedTables
     */
    private static function buildDefaultLayout(array $allowedTables): array
    {
        $tables = [];
        foreach ($allowedTables as $index => $tableName) {
            $tableName = trim((string) $tableName);
            if ($tableName === '') {
                continue;
            }
            $title = ManifestService::toTitle($tableName);
            if ($title === '') {
                $title = $tableName;
            }
            $tables[] = [
                'name' => $tableName,
                'displayAs' => $index === 0 ? 'fields' : 'icon',
                'title' => $title,
                'icon' => '',
            ];
        }

        return [
            'version' => '1.0',
            'cards' => [
                [
                    'id' => 'all-tables',
                    'type' => 'group',
                    'title' => 'All Tables',
                    'icon' => 'bi bi-grid-3x3-gap',
                    'tables' => $tables,
                ],
            ],
        ];
    }

    private static function readLayoutFile(string $layoutPath): ?array
    {
        $raw = @file_get_contents($layoutPath);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string,mixed> $layout
     * @param string[] $allowedTables
     * @return array<string,mixed>
     */
    private static function normalizeLayout(array $layout, array $allowedTables = []): array
    {
        $allowedLookup = [];
        foreach ($allowedTables as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $allowedLookup[$name] = true;
            }
        }

        $normalized = [
            'version' => trim((string) ($layout['version'] ?? '1.0')) ?: '1.0',
            'cards' => [],
        ];
        $cards = is_array($layout['cards'] ?? null) ? $layout['cards'] : [];
        $cardCounter = 1;

        foreach ($cards as $card) {
            if (!is_array($card)) {
                continue;
            }

            $cardId = trim((string) ($card['id'] ?? ''));
            if ($cardId === '') {
                $cardId = 'card-' . $cardCounter;
            }
            $cardCounter += 1;

            $cardType = trim((string) ($card['type'] ?? 'group'));
            $isSingle = $cardType === 'single-table';

            if ($isSingle) {
                $table = self::normalizeTableNode(is_array($card['table'] ?? null) ? $card['table'] : []);
                if ($table === null) {
                    continue;
                }
                if (!empty($allowedLookup) && !isset($allowedLookup[(string) $table['name']])) {
                    continue;
                }
                $normalized['cards'][] = [
                    'id' => $cardId,
                    'type' => 'single-table',
                    'table' => $table,
                ];
                continue;
            }

            $tables = [];
            $tableNodes = is_array($card['tables'] ?? null) ? $card['tables'] : [];
            foreach ($tableNodes as $tableNode) {
                if (!is_array($tableNode)) {
                    continue;
                }
                $table = self::normalizeTableNode($tableNode);
                if ($table === null) {
                    continue;
                }
                if (!empty($allowedLookup) && !isset($allowedLookup[(string) $table['name']])) {
                    continue;
                }
                $tables[] = $table;
            }

            $groupCard = [
                'id' => $cardId,
                'type' => 'group',
                'title' => trim((string) ($card['title'] ?? $cardId)),
                'icon' => trim((string) ($card['icon'] ?? '')),
                'tables' => $tables,
            ];
            $description = trim((string) ($card['description'] ?? ''));
            if ($description !== '') {
                $groupCard['description'] = $description;
            }
            $normalized['cards'][] = $groupCard;
        }

        if (empty($normalized['cards'])) {
            return self::buildDefaultLayout($allowedTables);
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $table
     * @return array<string,mixed>|null
     */
    private static function normalizeTableNode(array $table): ?array
    {
        $name = trim((string) ($table['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $displayAs = trim((string) ($table['displayAs'] ?? 'icon'));
        if (!in_array($displayAs, ['fields', 'icon', 'table'], true)) {
            $displayAs = 'icon';
        }

        $normalized = [
            'name' => $name,
            'displayAs' => $displayAs,
            'title' => trim((string) ($table['title'] ?? $name)),
            'icon' => trim((string) ($table['icon'] ?? '')),
        ];

        if (array_key_exists('visible', $table) && !$table['visible']) {
            $normalized['visible'] = false;
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $layout
     * @return array<int,array{
     *   id:string,
     *   type:string,
     *   title:string,
     *   icon:string,
     *   description:string,
     *   rows:array<int,array{
     *     table:string,
     *     display_as:string,
     *     title:string,
     *     icon:string,
     *     visible:bool
     *   }>
     * }>
     */
    private static function layoutToCards(array $layout): array
    {
        $cards = [];
        $rawCards = is_array($layout['cards'] ?? null) ? $layout['cards'] : [];

        foreach ($rawCards as $card) {
            if (!is_array($card)) {
                continue;
            }

            $cardId = trim((string) ($card['id'] ?? ''));
            if ($cardId === '') {
                continue;
            }
            $cardType = trim((string) ($card['type'] ?? 'group'));
            $rows = [];

            if ($cardType === 'single-table') {
                $table = is_array($card['table'] ?? null) ? $card['table'] : [];
                $row = self::tableNodeToRow($table);
                if ($row !== null) {
                    $rows[] = $row;
                }
            } else {
                $tables = is_array($card['tables'] ?? null) ? $card['tables'] : [];
                foreach ($tables as $table) {
                    if (!is_array($table)) {
                        continue;
                    }
                    $row = self::tableNodeToRow($table);
                    if ($row !== null) {
                        $rows[] = $row;
                    }
                }
            }

            $title = trim((string) ($card['title'] ?? ''));
            if ($title === '' && !empty($rows)) {
                $title = (string) ($rows[0]['title'] ?? $cardId);
            }
            if ($title === '') {
                $title = $cardId;
            }

            $cards[] = [
                'id' => $cardId,
                'type' => $cardType === 'single-table' ? 'single-table' : 'group',
                'title' => $title,
                'icon' => trim((string) ($card['icon'] ?? (!empty($rows) ? (string) ($rows[0]['icon'] ?? '') : ''))),
                'description' => trim((string) ($card['description'] ?? '')),
                'rows' => $rows,
            ];
        }

        return $cards;
    }

    /**
     * @param array<string,mixed> $table
     * @return array<string,mixed>|null
     */
    private static function tableNodeToRow(array $table): ?array
    {
        $name = trim((string) ($table['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $displayAs = trim((string) ($table['displayAs'] ?? 'icon'));
        if (!in_array($displayAs, ['fields', 'icon', 'table'], true)) {
            $displayAs = 'icon';
        }

        return [
            'table' => $name,
            'display_as' => $displayAs,
            'title' => trim((string) ($table['title'] ?? $name)),
            'icon' => trim((string) ($table['icon'] ?? '')),
            'visible' => !array_key_exists('visible', $table) || (bool) $table['visible'],
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function encodePrettyJson(array $data): string
    {
        try {
            $json = json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\Throwable) {
            return "{}\n";
        }

        return (is_string($json) && $json !== '') ? $json . "\n" : "{}\n";
    }
}
