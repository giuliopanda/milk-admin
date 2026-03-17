<?php

namespace Modules\Projects;

!defined('MILK_DIR') && die();

class ProjectMenuService
{
    /**
     * Read menu/menuIcon/selectMenu from manifest.json and apply to the rule builder.
     * Call this in the module's configure() after the default ->menu() call.
     *
     * @param \App\Abstracts\ModuleRuleBuilder $rule
     * @param string $moduleDir  The module's __DIR__
     */
    public static function applyFromManifest($rule, string $moduleDir): void
    {
        $manifestPath = ManifestService::findManifestPath($moduleDir);
        if ($manifestPath === null) {
            return;
        }

        $data = ManifestService::readManifest($manifestPath);
        if (!is_array($data)) {
            return;
        }

        $menu = trim((string) ($data['menu'] ?? ''));
        $menuIcon = trim((string) ($data['menuIcon'] ?? ''));
        $existingLinks = $rule->getMenuLinks();
        $existingLink = is_array($existingLinks) && !empty($existingLinks) ? $existingLinks[0] : [];
        $label = $menu !== '' ? $menu : (string) ($existingLink['name'] ?? '');
        if ($label === '') {
            $label = trim((string) ($data['name'] ?? ''));
        }
        $icon = $menuIcon !== '' ? $menuIcon : (string) ($existingLink['icon'] ?? '');
        $order = (int) ($existingLink['order'] ?? 9100);

        if (($menu !== '' || $menuIcon !== '') && $label !== '') {
            $rule->menuLinks([
                ['name' => $label, 'url' => '', 'icon' => $icon, 'order' => $order],
            ]);
        }

        $selectMenuConfig = self::resolveSelectMenuConfig($data, $label, $icon, $order);
        if ($selectMenuConfig !== null) {
            $rule->selectMenu(
                $selectMenuConfig['menu'],
                $selectMenuConfig['label'],
                $selectMenuConfig['url'],
                $selectMenuConfig['icon'],
                $selectMenuConfig['order']
            );
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array{menu:string,label:string,url:string,icon:string,order:int}|null
     */
    private static function resolveSelectMenuConfig(
        array $data,
        string $defaultLabel,
        string $defaultIcon,
        int $defaultOrder
    ): ?array {
        $raw = $data['selectMenu'] ?? ($data['selectedMenu'] ?? ($data['select_menu'] ?? null));
        if ($raw === null) {
            return null;
        }

        $menuName = '';
        $label = null;
        $url = '';
        $icon = null;
        $order = null;

        if (is_string($raw) || is_numeric($raw)) {
            $menuName = trim((string) $raw);
        } elseif (is_array($raw)) {
            $menuName = trim((string) ($raw['name'] ?? ($raw['menu'] ?? ($raw['group'] ?? ''))));

            if (array_key_exists('label', $raw) || array_key_exists('title', $raw)) {
                $label = trim((string) ($raw['label'] ?? ($raw['title'] ?? '')));
            }
            $url = trim((string) ($raw['url'] ?? ''));
            if (array_key_exists('icon', $raw)) {
                $icon = trim((string) ($raw['icon'] ?? ''));
            }
            if (array_key_exists('order', $raw) && is_numeric($raw['order'])) {
                $order = (int) $raw['order'];
            }
        }

        if ($menuName === '') {
            return null;
        }

        return [
            'menu' => $menuName,
            'label' => $label !== null ? $label : $defaultLabel,
            'url' => $url,
            'icon' => $icon !== null ? $icon : $defaultIcon,
            'order' => $order !== null ? $order : $defaultOrder,
        ];
    }
}
