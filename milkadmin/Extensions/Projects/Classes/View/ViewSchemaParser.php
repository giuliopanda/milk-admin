<?php
namespace Extensions\Projects\Classes\View;

use App\Exceptions\FileException;
use App\File;

!defined('MILK_DIR') && die();

/**
 * Parses and validates a view_layout.json configuration file.
 *
 * The JSON defines how a root record's view page is structured:
 *   - Which cards to render
 *   - Whether each card is a single-table or a group
 *   - How each table is displayed (fields, icon, table)
 *   - Nested child tables (resolved automatically from manifest contexts)
 *
 * Usage:
 *   $parser = new ViewSchemaParser();
 *   $schema = $parser->parseFile('/path/to/view_layout.json');
 *   // or
 *   $schema = $parser->parseArray($decodedArray);
 */
class ViewSchemaParser
{
    /** @var string[] Collected warnings (non-fatal). */
    protected array $warnings = [];

    /**
     * Parse a view layout JSON file.
     *
     * @throws \RuntimeException If file is missing or JSON is invalid.
     * @return ViewSchema
     */
    public function parseFile(string $path): ViewSchema
    {
        if (!is_file($path)) {
            throw new \RuntimeException("View layout file not found: {$path}");
        }

        try {
            $json = File::getContents($path);
        } catch (FileException $e) {
            throw new \RuntimeException("View layout file is empty: {$path}", 0, $e);
        }

        if (trim($json) === '') {
            throw new \RuntimeException("View layout file is empty: {$path}");
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Invalid JSON in view layout: {$e->getMessage()}");
        }

        if (!is_array($data)) {
            throw new \RuntimeException("View layout must be a JSON object.");
        }

        return $this->parseArray($data);
    }

    /**
     * Parse from an already-decoded array (e.g. embedded in manifest).
     *
     * @throws \RuntimeException On structural validation errors.
     * @return ViewSchema
     */
    public function parseArray(array $data): ViewSchema
    {
        $this->warnings = [];

        $version = trim((string) ($data['version'] ?? '1.0'));
        $cards = $data['cards'] ?? null;

        if (!is_array($cards) || empty($cards)) {
            throw new \RuntimeException("View layout must contain a non-empty 'cards' array.");
        }

        $parsedCards = [];
        $seenIds = [];

        foreach ($cards as $index => $cardData) {
            if (!is_array($cardData)) {
                $this->warnings[] = "Card at index {$index} is not an object, skipping.";
                continue;
            }

            $card = $this->parseCard($cardData, $index, $seenIds);
            if ($card !== null) {
                $parsedCards[] = $card;
                $seenIds[$card->id] = true;
            }
        }

        if (empty($parsedCards)) {
            throw new \RuntimeException("View layout has no valid cards after parsing.");
        }

        return new ViewSchema($version, $parsedCards);
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    // ------------------------------------------------------------------
    // Card parsing
    // ------------------------------------------------------------------

    /**
     * @param array<string,bool> $seenIds
     */
    protected function parseCard(array $data, int $index, array $seenIds): ?ViewCardConfig
    {
        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            $id = 'card-' . ($index + 1);
            $this->warnings[] = "Card at index {$index} has no 'id', auto-assigned '{$id}'.";
        }

        if (isset($seenIds[$id])) {
            $this->warnings[] = "Duplicate card id '{$id}' at index {$index}, skipping.";
            return null;
        }

        $type = trim((string) ($data['type'] ?? ''));
        if ($type === '') {
            $this->warnings[] = "Card '{$id}' has no 'type', defaulting to 'single-table'.";
            $type = 'single-table';
        }

        if (!in_array($type, ['single-table', 'group'], true)) {
            $this->warnings[] = "Card '{$id}' has unknown type '{$type}', defaulting to 'single-table'.";
            $type = 'single-table';
        }

        $preHtml = (string) ($data['preHtml'] ?? '');
        $postHtml = (string) ($data['postHtml'] ?? '');

        if ($type === 'single-table') {
            return $this->parseSingleTableCard($id, $data, $preHtml, $postHtml);
        }

        return $this->parseGroupCard($id, $data, $preHtml, $postHtml);
    }

    protected function parseSingleTableCard(string $id, array $data, string $preHtml, string $postHtml): ?ViewCardConfig
    {
        $tableData = $data['table'] ?? null;
        if (!is_array($tableData)) {
            $this->warnings[] = "Single-table card '{$id}' has no 'table' property, skipping.";
            return null;
        }

        $table = $this->parseTableConfig($tableData, $id);
        if ($table === null) {
            return null;
        }

        return new ViewCardConfig(
            id: $id,
            type: 'single-table',
            table: $table,
            tables: [],
            title: $table->title,
            icon: $table->icon,
            preHtml: $preHtml,
            postHtml: $postHtml
        );
    }

    protected function parseGroupCard(string $id, array $data, string $preHtml, string $postHtml): ?ViewCardConfig
    {
        $title = trim((string) ($data['title'] ?? ''));
        $icon = trim((string) ($data['icon'] ?? ''));
        $tablesData = $data['tables'] ?? [];

        if (!is_array($tablesData) || empty($tablesData)) {
            $this->warnings[] = "Group card '{$id}' has no 'tables' array, skipping.";
            return null;
        }

        $tables = [];
        foreach ($tablesData as $tIdx => $tData) {
            if (!is_array($tData)) {
                continue;
            }
            $table = $this->parseTableConfig($tData, "{$id}[{$tIdx}]");
            if ($table !== null) {
                $tables[] = $table;
            }
        }

        if (empty($tables)) {
            $this->warnings[] = "Group card '{$id}' has no valid tables, skipping.";
            return null;
        }

        return new ViewCardConfig(
            id: $id,
            type: 'group',
            table: null,
            tables: $tables,
            title: $title,
            icon: $icon,
            preHtml: $preHtml,
            postHtml: $postHtml
        );
    }

    // ------------------------------------------------------------------
    // Table config parsing
    // ------------------------------------------------------------------

    protected function parseTableConfig(array $data, string $parentLabel): ?ViewTableConfig
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $this->warnings[] = "Table in '{$parentLabel}' has no 'name', skipping.";
            return null;
        }

        $displayAs = trim((string) ($data['displayAs'] ?? ''));
        if ($displayAs === '') {
            $this->warnings[] = "Table '{$name}' in '{$parentLabel}' has no 'displayAs', defaulting to 'icon'.";
            $displayAs = 'icon';
        }

        if (!in_array($displayAs, ['fields', 'icon', 'table'], true)) {
            $this->warnings[] = "Table '{$name}' in '{$parentLabel}' has unknown displayAs '{$displayAs}', defaulting to 'icon'.";
            $displayAs = 'icon';
        }

        $title = trim((string) ($data['title'] ?? ''));
        $icon = trim((string) ($data['icon'] ?? ''));
        $hideSideTitle = $this->normalizeBool($data['hideSideTitle'] ?? false);
        $preHtml = (string) ($data['preHtml'] ?? '');
        $postHtml = (string) ($data['postHtml'] ?? '');

        return new ViewTableConfig(
            name: $name,
            displayAs: $displayAs,
            title: $title,
            icon: $icon,
            hideSideTitle: $hideSideTitle,
            preHtml: $preHtml,
            postHtml: $postHtml
        );
    }

    protected function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        $v = strtolower(trim((string) $value));
        if ($v === '') {
            return false;
        }
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}
