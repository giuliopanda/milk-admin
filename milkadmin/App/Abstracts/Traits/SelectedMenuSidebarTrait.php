<?php
namespace App\Abstracts\Traits;

use App\Abstracts\AbstractModule;
use App\Abstracts\Services\ModuleMenuRegistryService;
use Builders\LinksBuilder;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Shared helper to build sidebar links from selectedMenu() declarations.
 * Works both when used by AbstractModule and AbstractController.
 */
trait SelectedMenuSidebarTrait
{
    /**
     * Build sidebar links for the selected menu group and return the builder.
     *
     * @param string|null $menuName Optional selected menu group override
     * @return LinksBuilder|null
     */
    protected function buildSidebarFromSelectedMenu(?string $menuName = null): ?LinksBuilder
    {
        $module = $this->resolveSelectedMenuModuleContext();
        if (!$module) {
            return null;
        }

        $modulePage = trim((string) $module->getPage());
        $selectedMenu = trim((string) ($menuName ?? ''));
        $configuredSelectedMenu = trim((string) ModuleMenuRegistryService::getConfiguredSelectedMenuByModule($modulePage));

        if ($selectedMenu === '') {
            $selectedMenu = $configuredSelectedMenu !== '' ? $configuredSelectedMenu : $modulePage;
        }

        $selectedMenuData = ModuleMenuRegistryService::getSelectedMenu($selectedMenu);
        $submenus = is_array($selectedMenuData['submenus'] ?? null) ? $selectedMenuData['submenus'] : [];
        $mainEntry = $this->buildSelectedMenuMainEntry($selectedMenu);
        if (empty($submenus) && $mainEntry === null) {
            return null;
        }

        $sidebarLinks = LinksBuilder::create();
        $currentPage = (string) $module->getPage();
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
            $submenuPage = trim((string) ($submenu['module_page'] ?? ''));
            if ($submenuPage !== '') {
                if (isset($seenModulePages[$submenuPage])) {
                    continue;
                }
                $seenModulePages[$submenuPage] = true;
            }

            if (!$this->canAccessSelectedMenuModule($submenuPage, $currentPage)) {
                continue;
            }

            $label = trim((string) ($submenu['name'] ?? ''));
            if ($label === '') {
                continue;
            }

            $url = (string) ($submenu['url'] ?? ('?page=' . (string) ($submenu['module_page'] ?? '')));
            $icon = (string) ($submenu['icon'] ?? 'bi bi-circle');

            $sidebarLinks->add($label, $url)->icon($icon);

            if ((string) ($submenu['module_page'] ?? '') === $currentPage) {
                $sidebarLinks->active();
            }

            $hasLinks = true;
        }

        return $hasLinks ? $sidebarLinks : null;
    }

    /**
     * Resolve module context for selectedMenu lookup.
     */
    private function resolveSelectedMenuModuleContext(): ?AbstractModule
    {
        if ($this instanceof AbstractModule) {
            return $this;
        }

        if (property_exists($this, 'module') && $this->module instanceof AbstractModule) {
            return $this->module;
        }

        return null;
    }

    private function canAccessSelectedMenuModule(string $submenuPage, string $currentPage): bool
    {
        if ($submenuPage === '' || $submenuPage === $currentPage) {
            return true;
        }

        $instance = AbstractModule::getInstance($submenuPage);
        if (!$instance) {
            return false;
        }

        return $instance->access();
    }

    /**
     * @return array{module_page:string,name:string,icon:string,url:string,order:int}|null
     */
    private function buildSelectedMenuMainEntry(string $selectedMenu): ?array
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

        return [
            'module_page' => $selectedMenu,
            'name' => $selectedMenu,
            'icon' => 'bi bi-circle',
            'url' => '?page=' . $selectedMenu,
            'order' => -10000,
        ];
    }
}
