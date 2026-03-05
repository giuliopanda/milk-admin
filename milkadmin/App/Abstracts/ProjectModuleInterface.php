<?php
namespace App\Abstracts;

!defined('MILK_DIR') && die();

/**
 * Minimal contract used by Projects renderers/helpers for module integration.
 */
interface ProjectModuleInterface
{
    public function getPage();

    public function getModel();

    /**
     * @return array<string,mixed>
     */
    public function getCommonData(): array;

    public function registerRequestAction(string $action, string|array $handler, ?string $accessLevel = null): bool;
}

