<?php
namespace Extensions\Projects\Classes\Module;

use App\Response;

!defined('MILK_DIR') && die();

/**
 * Helpers for display mode (page / offcanvas / modal) logic.
 */
class DisplayModeHelper
{
    public static function normalize(string $mode): string
    {
        $normalized = strtolower(trim($mode));
        if (in_array($normalized, ['page', 'offcanvas', 'modal'], true)) {
            return $normalized;
        }
        return 'page';
    }

    public static function getListMode(array $context): string
    {
        return self::normalize((string) ($context['list_display'] ?? 'page'));
    }

    public static function getEditMode(array $context): string
    {
        return self::normalize((string) ($context['edit_display'] ?? 'page'));
    }

    /**
     * Returns 'post' for non-page modes, null for regular page navigation.
     */
    public static function getFetchMethod(string $displayMode): ?string
    {
        return self::normalize($displayMode) === 'page' ? null : 'post';
    }

    /**
     * Build an HTML data-fetch attribute string for links.
     */
    public static function buildFetchAttribute(string $displayMode): string
    {
        $fetchMethod = self::getFetchMethod($displayMode);
        if ($fetchMethod === null) {
            return '';
        }
        return ' data-fetch="' . _r($fetchMethod) . '"';
    }

    /**
     * Apply fetch method to a button config array (by reference).
     */
    public static function applyToButton(array &$button, string $displayMode): void
    {
        $fetchMethod = self::getFetchMethod($displayMode);
        if ($fetchMethod !== null) {
            $button['fetch'] = $fetchMethod;
        }
    }

    /**
     * Render content inside an offcanvas or modal JSON response.
     */
    public static function respond(string $displayMode, string $title, string $body, ?string $size = null): void
    {
        $displayMode = self::normalize($displayMode);

        if ($displayMode === 'offcanvas') {
            $response = [
                'success' => true,
                'offcanvas_end' => [
                    'title' => $title,
                    'body' => $body,
                    'action' => 'show',
                ],
            ];
            if ($size !== null && $size !== '') {
                $response['offcanvas_end']['size'] = $size;
            }
            Response::json($response);
            return;
        }

        if ($displayMode === 'modal') {
            $response = [
                'success' => true,
                'modal' => [
                    'title' => $title,
                    'body' => $body,
                    'action' => 'show',
                ],
            ];
            if ($size !== null && $size !== '') {
                $response['modal']['size'] = $size;
            }
            Response::json($response);
            return;
        }

        Response::htmlJson(['success' => true, 'html' => $body]);
    }

    /**
     * Render a PHP view file to string.
     */
    public static function renderViewToString(string $viewPath, array $data): string
    {
        if (!is_file($viewPath)) {
            return '';
        }

        extract($data);
        ob_start();
        include $viewPath;
        return (string) ob_get_clean();
    }
}
