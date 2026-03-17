<?php
namespace Extensions\Projects\Classes\Module;

!defined('MILK_DIR') && die();

/**
 * Registry for manifest-driven action contexts.
 *
 * Each form in the manifest produces two actions (list + edit).
 * This class stores and resolves the context array for the current request.
 */
class ActionContextRegistry
{
    /** @var array<string,array> action => context */
    protected array $contexts = [];

    /** @var array<int,array{label:string,action:string,fetch?:string|null}> */
    protected array $primaryFormLink = [];

    public function register(string $action, array $context): void
    {
        $this->contexts[$action] = $context;
    }

    public function setPrimaryFormLink(array $link): void
    {
        $this->primaryFormLink = $link;
    }

    /**
     * Resolve context for current request action.
     *
     * Returns null when no action matches — callers must handle this explicitly.
     * Unlike the old implementation, this does NOT silently fall back to the first context.
     */
    public function resolveForCurrentRequest(): ?array
    {
        $action = trim((string) ($_REQUEST['action'] ?? ''));
        if ($action !== '' && isset($this->contexts[$action])) {
            return $this->contexts[$action];
        }

        return null;
    }

    /**
     * Get context by explicit action name.
     */
    public function get(string $action): ?array
    {
        return $this->contexts[$action] ?? null;
    }

    public function has(string $action): bool
    {
        return isset($this->contexts[$action]);
    }

    /**
     * @return array<string,array>
     */
    public function getAll(): array
    {
        return $this->contexts;
    }

    /**
     * @return array<int,array{label:string,action:string,fetch?:string|null}>
     */
    public function getPrimaryFormLink(): array
    {
        return $this->primaryFormLink;
    }

    /**
     * Find all direct child contexts for a given parent form name.
     *
     * @return array<int,array>
     */
    public function getDirectChildContexts(string $parentFormName): array
    {
        if ($parentFormName === '') {
            return [];
        }

        $children = [];
        $seen = [];
        foreach ($this->contexts as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            if ((string) ($candidate['parent_form_name'] ?? '') !== $parentFormName) {
                continue;
            }
            $childFormName = (string) ($candidate['form_name'] ?? '');
            if ($childFormName === '' || isset($seen[$childFormName])) {
                continue;
            }
            $seen[$childFormName] = true;
            $children[] = $candidate;
        }

        return $children;
    }
}
