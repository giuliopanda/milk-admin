<?php
namespace Extensions\Projects\Classes\Module;

use App\Abstracts\AbstractModule;
use App\Abstracts\Services\ModuleMenuRegistryService;
use Builders\LinksBuilder;

!defined('MILK_DIR') && die();

/**
 * Builds Projects sidebar HTML from selectedMenu() registry configuration.
 */
class SelectedMenuSidebarHelper
{
    /**
     * @param array<string,mixed> $response
     */
    public static function applyToResponse(array &$response, AbstractModule $module, ?string $selectedMenuOverride = null): void
    {
        $modulePage = trim((string) $module->getPage());
        if ($modulePage === '') {
            return;
        }

        $selectedMenu = self::resolveSelectedMenu($modulePage, $selectedMenuOverride);
        if ($selectedMenu === null) {
            return;
        }

        $selectedMenuData = ModuleMenuRegistryService::getSelectedMenu($selectedMenu);
        $submenus = is_array($selectedMenuData['submenus'] ?? null) ? $selectedMenuData['submenus'] : [];
        $mainEntry = self::buildMainMenuEntry($selectedMenu);
        if (empty($submenus) && $mainEntry === null) {
            return;
        }

        $sidebarLinks = LinksBuilder::create();
        $hasLinks = false;
        $seenModulePages = [];

        $entries = [];
        if (is_array($mainEntry)) {
            $entries[] = $mainEntry;
        }
        foreach ($submenus as $submenu) {
            $entries[] = $submenu;
        }

        foreach ($entries as $submenu) {
            if (!is_array($submenu)) {
                continue;
            }

            $submenuModulePage = trim((string) ($submenu['module_page'] ?? ''));
            if ($submenuModulePage !== '') {
                if (isset($seenModulePages[$submenuModulePage])) {
                    continue;
                }
                $seenModulePages[$submenuModulePage] = true;
            }

            if (!self::canAccessSubmenuModule($submenuModulePage, $modulePage)) {
                continue;
            }

            $label = trim((string) ($submenu['name'] ?? ''));
            if ($label === '') {
                continue;
            }

            $url = (string) ($submenu['url'] ?? ('?page=' . (string) ($submenu['module_page'] ?? '')));
            $icon = (string) ($submenu['icon'] ?? 'bi bi-circle');

            $sidebarLinks->add($label, $url)->icon($icon);

            if ((string) ($submenu['module_page'] ?? '') === $modulePage) {
                $sidebarLinks->active();
            }

            $hasLinks = true;
        }

        if (!$hasLinks) {
            return;
        }

        $sidebarHtml = $sidebarLinks->render('vertical');
        if ($selectedMenu !== '') {
            $header = htmlspecialchars($selectedMenu, ENT_QUOTES, 'UTF-8');
            $response['sidebar'] = '<div class="card"><div class="card-header">' . $header . '</div><div class="card-body">'
                . $sidebarHtml
                . '</div></div>';
        } else {
            $response['sidebar'] = $sidebarHtml;
        }
    }

    /**
     * @return array{module_page:string,name:string,icon:string,url:string,order:int}|null
     */
    private static function buildMainMenuEntry(string $selectedMenu): ?array
    {
        $selectedMenu = trim($selectedMenu);
        if ($selectedMenu === '') {
            return null;
        }

        $menuLinks = ModuleMenuRegistryService::getConfiguredMenuLinksByModule($selectedMenu);
        $first = is_array($menuLinks[0] ?? null) ? $menuLinks[0] : null;

        if ($first !== null) {
            return [
                'module_page' => $selectedMenu,
                'name' => (string) ($first['name'] ?? $selectedMenu),
                'icon' => (string) ($first['icon'] ?? 'bi bi-circle'),
                'url' => (string) ($first['url'] ?? ('?page=' . $selectedMenu)),
                'order' => (int) ($first['order'] ?? -10000),
            ];
        }

        return null;
    }

    private static function resolveSelectedMenu(string $modulePage, ?string $selectedMenuOverride = null): ?string
    {
        $selectedMenu = trim((string) $selectedMenuOverride);
        if ($selectedMenu !== '') {
            return $selectedMenu;
        }

        $configuredSelectedMenu = trim((string) ModuleMenuRegistryService::getConfiguredSelectedMenuByModule($modulePage));
        if ($configuredSelectedMenu !== '') {
            return $configuredSelectedMenu;
        }

        // No direct selectMenu: show sidebar only when other modules attach to this module page.
        $incoming = ModuleMenuRegistryService::getSelectedMenu($modulePage);
        $incomingSubmenus = is_array($incoming['submenus'] ?? null) ? $incoming['submenus'] : [];
        if (empty($incomingSubmenus)) {
            return null;
        }

        return $modulePage;
    }

    private static function canAccessSubmenuModule(string $submenuPage, string $currentModulePage): bool
    {
        if ($submenuPage === '' || $submenuPage === $currentModulePage) {
            return true;
        }

        $instance = AbstractModule::getInstance($submenuPage);
        if (!$instance) {
            return false;
        }

        return $instance->access();
    }
}
