<?php
namespace App\Abstracts\Services\AbstractModule;

use App\{Route, Theme};
use App\Abstracts\ModuleRuleBuilder;
use Builders\LinksBuilder;

!defined('MILK_DIR') && die();

class ModuleAssetsService
{
    /**
     * Normalize and publish module sidebar links into Theme storage.
     * The incoming array is mutated to preserve the same by-reference behavior.
     */
    public static function appendSidebarLinks(string $page, array &$menuLinks): void
    {
        foreach ($menuLinks as &$link) {
            $link['name'] = $link['name'] ?? $page;
            $link['url'] = $link['url'] ?? '';
            $link['icon'] = $link['icon'] ?? '';
            $link['order'] = $link['order'] ?? 10;

            if (
                strpos($link['url'], '?') === 0
                || strpos($link['url'], '&') === 0
                || strpos($link['url'], '/') === 0
            ) {
                $link['url'] = substr($link['url'], 1);
            }

            if ($link['url'] == '') {
                $link['url'] = 'page=' . $page;
            } else {
                $link['url'] = '?page=' . $page . '&' . $link['url'];
            }

            Theme::set('sidebar.links', [
                'url' => Route::url($link['url']),
                'title' => $link['name'],
                'icon' => $link['icon'],
                'order' => $link['order'],
            ]);
        }
    }

    /**
     * Register JavaScript and CSS assets declared in ModuleRuleBuilder.
     *
     * @param callable|null $assetPathResolver Optional callback: fn(string $path, string $moduleFolder): string
     */
    public static function setStylesAndScripts(
        ModuleRuleBuilder $ruleBuilder,
        string $moduleFolder,
        ?callable $assetPathResolver = null
    ): void
    {
        $jsFiles = $ruleBuilder->getJs();
        $cssFiles = $ruleBuilder->getCss();
        $resolver = $assetPathResolver ?? [self::class, 'resolveAssetPath'];

        foreach ($jsFiles as $path) {
            Theme::set('javascript', $resolver($path, $moduleFolder));
        }

        foreach ($cssFiles as $path) {
            Theme::set('styles', $resolver($path, $moduleFolder));
        }
    }

    /**
     * Resolve an asset path to a public URL, supporting relative and absolute forms.
     */
    public static function resolveAssetPath(string $path, string $moduleFolder): string
    {
        if (str_starts_with($path, '/') || str_starts_with($path, './')) {
            $path = ltrim($path, './');
            $relativeModule = str_replace(MILK_DIR . '/', '', $moduleFolder);
            if ($moduleFolder === LOCAL_DIR || str_starts_with($moduleFolder, LOCAL_DIR . '/')) {
                $relativeModule = str_replace(LOCAL_DIR . '/', '', $relativeModule);
            }
            return Route::url() . '/' . $relativeModule . '/' . ltrim($path, '/');
        }

        if (str_starts_with($path, 'Modules/') || str_starts_with($path, 'modules/')) {
            return Route::url() . '/' . $path;
        }

        $relativeModule = str_replace(MILK_DIR . '/', '', $moduleFolder);
        $relativeModule = str_replace(LOCAL_DIR . '/', '', $relativeModule);

        return Route::url() . '/' . $relativeModule . '/' . $path;
    }

    /**
     * Push header title/description/links from module configuration into Theme.
     *
     * @param callable|null $headerLinksBuilder Optional callback: fn(array $links): void
     */
    public static function setHeader(ModuleRuleBuilder $ruleBuilder, ?callable $headerLinksBuilder = null): void
    {
        $headerTitle = $ruleBuilder->getHeaderTitle();
        if ($headerTitle !== null) {
            Theme::set('header.title', $headerTitle);
        }

        $headerDescription = $ruleBuilder->getHeaderDescription();
        if ($headerDescription !== null) {
            Theme::set('header.description', $headerDescription);
        }

        $headerLinks = $ruleBuilder->getHeaderLinks();
        if (!empty($headerLinks)) {
            if (is_callable($headerLinksBuilder)) {
                $headerLinksBuilder($headerLinks);
            } else {
                self::buildHeaderLinks($ruleBuilder, $headerLinks);
            }
        }
    }

    /**
     * Build and render header link markup via LinksBuilder.
     */
    public static function buildHeaderLinks(ModuleRuleBuilder $ruleBuilder, array $links): void
    {
        if (empty($links)) {
            return;
        }

        $style = $ruleBuilder->getHeaderLinksStyle();
        $position = $ruleBuilder->getHeaderLinksPosition();
        $builder = LinksBuilder::create();

        foreach ($links as $link) {
            $title = $link['title'] ?? '';
            $url = $link['url'] ?? '#';

            $builder->add($title, $url);

            if (isset($link['icon']) && $link['icon']) {
                $builder->icon($link['icon']);
            }
        }

        Theme::set('header.' . $position, $builder->render($style));
    }
}
