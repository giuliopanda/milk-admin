<?php

namespace Modules\Projects\DraftService;

!defined('MILK_DIR') && die();

class DraftUrlBuilder
{
    public static function review(string $projectsPage, string $moduleName, string $refBase, string $draftToken): string
    {
        return '?page=' . rawurlencode($projectsPage)
            . '&action=review-form-fields-draft'
            . '&module=' . rawurlencode($moduleName)
            . '&ref=' . rawurlencode($refBase)
            . '&draft=' . rawurlencode($draftToken);
    }

    public static function accept(string $projectsPage, string $moduleName, string $refBase, string $draftToken): string
    {
        return '?page=' . rawurlencode($projectsPage)
            . '&action=accept-form-fields-draft'
            . '&module=' . rawurlencode($moduleName)
            . '&ref=' . rawurlencode($refBase)
            . '&draft=' . rawurlencode($draftToken);
    }

    public static function edit(string $projectsPage, string $moduleName, string $refBase, string $draftToken): string
    {
        $url = '?page=' . rawurlencode($projectsPage)
            . '&action=build-form-fields'
            . '&module=' . rawurlencode($moduleName)
            . '&ref=' . rawurlencode($refBase);

        if ($draftToken !== '') {
            $url .= '&draft=' . rawurlencode($draftToken);
        }

        return $url;
    }

    public static function buildForms(string $projectsPage, string $moduleName): string
    {
        return '?page=' . rawurlencode($projectsPage) . '&action=edit&module=' . rawurlencode($moduleName);
    }

    public static function projectsHome(string $projectsPage): string
    {
        return '?page=' . rawurlencode($projectsPage);
    }
}
