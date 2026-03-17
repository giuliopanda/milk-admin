<?php
namespace Extensions\Projects\Classes\Module;

!defined('MILK_DIR') && die();

/**
 * URL building utilities for Projects module actions.
 */
class UrlBuilder
{
    public static function action(string $modulePage, string $action, array $params = []): string
    {
        $query = array_merge(['page' => $modulePage, 'action' => $action], $params);
        return '?' . http_build_query($query);
    }

    /**
     * Build URL query while keeping placeholder values like "%id%" unencoded.
     *
     * Route::replaceUrlPlaceholders() expects raw "%" delimiters.
     */
    public static function actionPreservePlaceholders(string $modulePage, string $action, array $params = []): string
    {
        $pairs = [];
        $pairs[] = 'page=' . rawurlencode($modulePage);
        $pairs[] = 'action=' . rawurlencode($action);

        foreach ($params as $k => $v) {
            $key = rawurlencode((string) $k);
            $value = (string) $v;
            if (str_contains($value, '%')) {
                $pairs[] = $key . '=' . $value;
            } else {
                $pairs[] = $key . '=' . rawurlencode($value);
            }
        }

        return '?' . implode('&', $pairs);
    }

    public static function normalizeListId(string $listId): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', trim($listId));
    }

    /**
     * Request param key used to force reload of a specific list/table after edit success.
     */
    public static function reloadListIdParamKey(): string
    {
        return 'reload_list_id';
    }

    public static function getRequestedReloadListId(): string
    {
        return self::normalizeListId((string) ($_REQUEST[self::reloadListIdParamKey()] ?? ''));
    }

    /**
     * Build deterministic embedded child table id used in root view pages.
     */
    public static function buildViewChildTableId(string $parentFormName, string $childFormName, int $parentRecordId): string
    {
        $prefix = preg_replace('/[^a-zA-Z0-9]/', '', $parentFormName . $childFormName);
        return 'idTableView' . $prefix . max(0, $parentRecordId);
    }

    /**
     * Returns a finite max_records value (>0), or 0 for unlimited.
     */
    public static function getFiniteMaxRecords(string $maxRecords): int
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
}
