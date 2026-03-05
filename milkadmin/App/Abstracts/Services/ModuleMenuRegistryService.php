<?php
namespace App\Abstracts\Services;

use App\Route;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Central registry for module menu configuration declared in configure().
 *
 * This service tracks:
 * - menu()/menuLinks() declarations per module
 * - selectMenu() declarations per module
 * - optional submenu metadata passed to selectMenu()
 */
class ModuleMenuRegistryService
{
    /**
     * @var array<string, array<int, array{
     *   module_page:string,
     *   module_title:string,
     *   name:string,
     *   icon:string,
     *   order:int,
     *   raw_url:string,
     *   query:string,
     *   url:string
     * }>>
     */
    private static array $configured_menus_by_module = [];

    /**
     * @var array<string, array{
     *   module_page:string,
     *   module_title:string,
     *   selected_menu:string,
     *   selected_menu_entry:array{
     *      module_page:string,
     *      module_title:string,
     *      name:string,
     *      icon:string,
     *      order:int,
     *      raw_url:string,
     *      query:string,
     *      url:string
     *   }|null
     * }>
     */
    private static array $selected_menus_by_module = [];

    /**
     * Internal method used by AbstractModule to keep registry synchronized.
     *
     * @param array<int, array<string,mixed>> $menuLinks
     * @param array<string,mixed>|null $selectedMenuEntry
     */
    public static function registerModuleConfiguration(
        string $modulePage,
        string $moduleTitle,
        array $menuLinks,
        ?string $selectedMenu,
        ?array $selectedMenuEntry = null
    ): void {
        $modulePage = trim($modulePage);
        if ($modulePage === '') {
            return;
        }

        $moduleTitle = trim($moduleTitle);
        if ($moduleTitle === '') {
            $moduleTitle = ucfirst($modulePage);
        }

        $normalizedMenus = self::normalizeConfiguredMenuLinks($menuLinks);
        $menuEntries = [];
        foreach ($normalizedMenus as $menu) {
            $menuEntries[] = self::createMenuEntry(
                $modulePage,
                $moduleTitle,
                (string) ($menu['name'] ?? ''),
                (string) ($menu['url'] ?? ''),
                (string) ($menu['icon'] ?? ''),
                (int) ($menu['order'] ?? 10)
            );
        }
        self::sortMenuEntries($menuEntries);
        self::$configured_menus_by_module[$modulePage] = $menuEntries;

        $selectedMenu = self::normalizeName($selectedMenu);
        if ($selectedMenu === null) {
            unset(self::$selected_menus_by_module[$modulePage]);
            return;
        }

        $entry = self::normalizeConfiguredMenuLink($selectedMenuEntry ?? []);
        $selectedEntry = null;
        if ($entry !== null) {
            $selectedEntry = self::createMenuEntry(
                $modulePage,
                $moduleTitle,
                (string) ($entry['name'] ?? ''),
                (string) ($entry['url'] ?? ''),
                (string) ($entry['icon'] ?? ''),
                (int) ($entry['order'] ?? 10)
            );
        }

        self::$selected_menus_by_module[$modulePage] = [
            'module_page' => $modulePage,
            'module_title' => $moduleTitle,
            'selected_menu' => $selectedMenu,
            'selected_menu_entry' => $selectedEntry,
        ];
    }

    /**
     * @return array<int, array{
     *   module_page:string,
     *   module_title:string,
     *   name:string,
     *   icon:string,
     *   order:int,
     *   raw_url:string,
     *   query:string,
     *   url:string
     * }>
     */
    public static function getConfiguredMenuLinksByModule(string $modulePage): array
    {
        return self::$configured_menus_by_module[$modulePage] ?? [];
    }

    public static function getConfiguredSelectedMenuByModule(string $modulePage): ?string
    {
        return self::$selected_menus_by_module[$modulePage]['selected_menu'] ?? null;
    }

    /**
     * @return array{
     *   module_page:string,
     *   module_title:string,
     *   name:string,
     *   icon:string,
     *   order:int,
     *   raw_url:string,
     *   query:string,
     *   url:string
     * }|null
     */
    public static function getConfiguredSelectedMenuEntryByModule(string $modulePage): ?array
    {
        $entry = self::$selected_menus_by_module[$modulePage]['selected_menu_entry'] ?? null;
        return is_array($entry) ? $entry : null;
    }

    /**
     * @return array<string, array<int, array{
     *   module_page:string,
     *   module_title:string,
     *   name:string,
     *   icon:string,
     *   order:int,
     *   raw_url:string,
     *   query:string,
     *   url:string
     * }>>
     */
    public static function getAllConfiguredMenus(): array
    {
        return self::$configured_menus_by_module;
    }

    /**
     * @return array<string, array{
     *   module_page:string,
     *   module_title:string,
     *   selected_menu:string,
     *   selected_menu_entry:array{
     *      module_page:string,
     *      module_title:string,
     *      name:string,
     *      icon:string,
     *      order:int,
     *      raw_url:string,
     *      query:string,
     *      url:string
     *   }|null
     * }>
     */
    public static function getAllSelectedMenus(): array
    {
        return self::$selected_menus_by_module;
    }

    /**
     * Returns one selected menu group by name, with its owners and submenus.
     *
     * @return array{
     *   name:string,
     *   owners:array<int, array{module_page:string,module_title:string}>,
     *   selected_by:array<int, string>,
     *   submenus:array<int, array{
     *      module_page:string,
     *      module_title:string,
     *      name:string,
     *      icon:string,
     *      order:int,
     *      raw_url:string,
     *      query:string,
     *      url:string
     *   }>
     * }
     */
    public static function getSelectedMenu(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [
                'name' => '',
                'owners' => [],
                'selected_by' => [],
                'submenus' => [],
            ];
        }

        $ownersIndexed = [];
        foreach (self::$configured_menus_by_module as $modulePage => $entries) {
            foreach ($entries as $entry) {
                if ((string) ($entry['name'] ?? '') !== $name) {
                    continue;
                }
                $ownersIndexed[$modulePage] = [
                    'module_page' => (string) ($entry['module_page'] ?? $modulePage),
                    'module_title' => (string) ($entry['module_title'] ?? ucfirst($modulePage)),
                ];
                break;
            }
        }

        $selectedBy = [];
        $submenus = [];
        foreach (self::$selected_menus_by_module as $modulePage => $entry) {
            if ((string) ($entry['selected_menu'] ?? '') !== $name) {
                continue;
            }

            $selectedBy[] = $modulePage;
            $selectedMenuEntry = $entry['selected_menu_entry'] ?? null;
            if (is_array($selectedMenuEntry)) {
                $submenus[] = $selectedMenuEntry;
                continue;
            }

            $moduleTitle = (string) ($entry['module_title'] ?? ucfirst($modulePage));
            $fallback = self::createDefaultSubmenuEntry($modulePage, $moduleTitle);
            $submenus[] = $fallback;
        }

        self::sortMenuEntries($submenus);
        sort($selectedBy);

        return [
            'name' => $name,
            'owners' => array_values($ownersIndexed),
            'selected_by' => $selectedBy,
            'submenus' => $submenus,
        ];
    }

    /**
     * @return array{
     *   menus: array<string, array<int, array{
     *      module_page:string,module_title:string,name:string,icon:string,order:int,raw_url:string,query:string,url:string
     *   }>>,
     *   selected_menus: array<string, array{
     *      module_page:string,module_title:string,selected_menu:string,selected_menu_entry:array{
     *          module_page:string,module_title:string,name:string,icon:string,order:int,raw_url:string,query:string,url:string
     *      }|null
     *   }>,
     *   usage: array<string, array{owners:array<int,string>,selected_by:array<int,string>}>
     * }
     */
    public static function getMenuConfigurationRegistry(): array
    {
        $usage = [];

        foreach (self::$configured_menus_by_module as $moduleEntries) {
            foreach ($moduleEntries as $entry) {
                $menuName = (string) ($entry['name'] ?? '');
                if ($menuName === '') {
                    continue;
                }
                if (!isset($usage[$menuName])) {
                    $usage[$menuName] = ['owners' => [], 'selected_by' => []];
                }
                $usage[$menuName]['owners'][] = (string) ($entry['module_page'] ?? '');
            }
        }

        foreach (self::$selected_menus_by_module as $modulePage => $entry) {
            $menuName = (string) ($entry['selected_menu'] ?? '');
            if ($menuName === '') {
                continue;
            }
            if (!isset($usage[$menuName])) {
                $usage[$menuName] = ['owners' => [], 'selected_by' => []];
            }
            $usage[$menuName]['selected_by'][] = (string) $modulePage;
        }

        foreach ($usage as $menuName => $data) {
            $owners = array_values(array_unique(array_filter($data['owners'], 'strlen')));
            $selectedBy = array_values(array_unique(array_filter($data['selected_by'], 'strlen')));
            sort($owners);
            sort($selectedBy);
            $usage[$menuName] = [
                'owners' => $owners,
                'selected_by' => $selectedBy,
            ];
        }
        ksort($usage);

        return [
            'menus' => self::getAllConfiguredMenus(),
            'selected_menus' => self::getAllSelectedMenus(),
            'usage' => $usage,
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $menuLinks
     * @return array<int, array{name:string,url:string,icon:string,order:int}>
     */
    private static function normalizeConfiguredMenuLinks(array $menuLinks): array
    {
        $result = [];
        foreach ($menuLinks as $menuLink) {
            if (!is_array($menuLink)) {
                continue;
            }
            $normalized = self::normalizeConfiguredMenuLink($menuLink);
            if ($normalized === null) {
                continue;
            }
            $result[] = $normalized;
        }
        return $result;
    }

    /**
     * @param array<string,mixed> $menuLink
     * @return array{name:string,url:string,icon:string,order:int}|null
     */
    private static function normalizeConfiguredMenuLink(array $menuLink): ?array
    {
        $name = self::normalizeName((string) ($menuLink['name'] ?? $menuLink['title'] ?? ''));
        if ($name === null) {
            return null;
        }

        $rawUrl = (string) ($menuLink['url'] ?? '');
        $icon = (string) ($menuLink['icon'] ?? '');
        $order = isset($menuLink['order']) && is_numeric($menuLink['order']) ? (int) $menuLink['order'] : 10;

        return [
            'name' => $name,
            'url' => $rawUrl,
            'icon' => $icon,
            'order' => $order,
        ];
    }

    /**
     * @return array{
     *   module_page:string,
     *   module_title:string,
     *   name:string,
     *   icon:string,
     *   order:int,
     *   raw_url:string,
     *   query:string,
     *   url:string
     * }
     */
    private static function createMenuEntry(
        string $modulePage,
        string $moduleTitle,
        string $name,
        string $rawUrl = '',
        string $icon = '',
        int $order = 10
    ): array {
        $query = self::buildConfiguredMenuQuery($modulePage, $rawUrl);
        return [
            'module_page' => $modulePage,
            'module_title' => $moduleTitle,
            'name' => $name,
            'icon' => $icon,
            'order' => $order,
            'raw_url' => $rawUrl,
            'query' => $query,
            'url' => Route::url('?' . $query),
        ];
    }

    /**
     * @return array{
     *   module_page:string,
     *   module_title:string,
     *   name:string,
     *   icon:string,
     *   order:int,
     *   raw_url:string,
     *   query:string,
     *   url:string
     * }
     */
    private static function createDefaultSubmenuEntry(string $modulePage, string $moduleTitle): array
    {
        $menuLinks = self::$configured_menus_by_module[$modulePage] ?? [];
        if (!empty($menuLinks)) {
            $first = $menuLinks[0];
            return [
                'module_page' => $modulePage,
                'module_title' => $moduleTitle,
                'name' => (string) ($first['name'] ?? $moduleTitle),
                'icon' => (string) ($first['icon'] ?? 'bi bi-circle'),
                'order' => (int) ($first['order'] ?? 9999),
                'raw_url' => (string) ($first['raw_url'] ?? ''),
                'query' => (string) ($first['query'] ?? self::buildConfiguredMenuQuery($modulePage, '')),
                'url' => (string) ($first['url'] ?? Route::url('?page=' . $modulePage)),
            ];
        }

        return self::createMenuEntry($modulePage, $moduleTitle, $moduleTitle, '', 'bi bi-circle', 9999);
    }

    private static function buildConfiguredMenuQuery(string $modulePage, string $rawUrl): string
    {
        $url = ltrim(trim($rawUrl), '?&/');
        if ($url === '') {
            return 'page=' . $modulePage;
        }
        return 'page=' . $modulePage . '&' . $url;
    }

    private static function normalizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        $name = trim($name);
        return $name === '' ? null : $name;
    }

    /**
     * @param array<int, array{
     *   module_page:string,
     *   module_title:string,
     *   name:string,
     *   icon:string,
     *   order:int,
     *   raw_url:string,
     *   query:string,
     *   url:string
     * }> $entries
     */
    private static function sortMenuEntries(array &$entries): void
    {
        usort($entries, function (array $a, array $b): int {
            $orderDiff = ((int) ($a['order'] ?? 10)) <=> ((int) ($b['order'] ?? 10));
            if ($orderDiff !== 0) {
                return $orderDiff;
            }
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });
    }
}
