<?php
namespace Extensions\Projects\Classes\Module;

use Extensions\Projects\Classes\ProjectNaming;

!defined('MILK_DIR') && die();

/**
 * Centralises reading and validation of the FK chain from the HTTP request.
 *
 * Instead of scattering $_REQUEST reads throughout the codebase,
 * every FK-related parameter is read through this class.
 */
class FkChainResolver
{
    /**
     * Extract the ordered FK field names from a context.
     *
     * @return array<int,string>
     */
    public function getChainFields(array $context): array
    {
        $chain = $context['fk_chain_fields'] ?? [];
        return is_array($chain) ? $chain : [];
    }

    /**
     * The first FK in the chain always points to the root form.
     */
    public function getRootFkField(array $context): string
    {
        $chain = $this->getChainFields($context);
        $rootFk = $chain[0] ?? '';
        return is_string($rootFk) ? $rootFk : '';
    }

    /**
     * Read the root id from the current request using the chain's first FK.
     */
    public function getRootIdFromRequest(array $context): int
    {
        $rootFk = $this->getRootFkField($context);
        if ($rootFk === '') {
            return 0;
        }
        return _absint($_REQUEST[$rootFk] ?? 0);
    }

    /**
     * Build param array from the current request for all FK fields in the chain.
     *
     * @return array<string,int>
     */
    public function getChainParams(array $context): array
    {
        $params = [];
        foreach ($this->getChainFields($context) as $fk) {
            $val = _absint($_REQUEST[$fk] ?? 0);
            if ($val > 0) {
                $params[(string) $fk] = $val;
            }
        }
        return $params;
    }

    /**
     * Chain params for the parent context (drops the last FK).
     *
     * @return array<string,int>
     */
    public function getChainParamsForParent(array $context): array
    {
        $params = $this->getChainParams($context);
        $chain = $this->getChainFields($context);
        $last = end($chain);
        if ($last !== false && isset($params[$last])) {
            unset($params[$last]);
        }
        return $params;
    }

    /**
     * Validate that every FK in the chain has a positive value in the request.
     *
     * Returns the name of the first missing FK, or null if all present.
     */
    public function findMissingChainField(array $context): ?string
    {
        foreach ($this->getChainFields($context) as $requiredFk) {
            $val = _absint($_REQUEST[$requiredFk] ?? 0);
            if ($val <= 0) {
                return $requiredFk;
            }
        }
        return null;
    }

    /**
     * Validate root_id consistency for ancestor records in the FK chain.
     *
     * The root form (index 0) is the root itself so it's skipped.
     * Validation starts from the second ancestor onward.
     */
    public function validateAncestorRootConsistency(array $context, int $expectedRootId): ?string
    {
        if ($expectedRootId <= 0) {
            return null;
        }

        $chain = $this->getChainFields($context);
        $ancestorFormNames = is_array($context['ancestor_form_names'] ?? null) ? $context['ancestor_form_names'] : [];
        $ancestorModelClasses = is_array($context['ancestor_model_classes'] ?? null) ? $context['ancestor_model_classes'] : [];
        $max = min(count($chain), count($ancestorFormNames));
        if ($max <= 1) {
            return null;
        }

        $rootField = ProjectNaming::rootIdField();

        for ($i = 1; $i < $max; $i++) {
            $fkField = (string) ($chain[$i] ?? '');
            if ($fkField === '') {
                continue;
            }

            $ancestorId = _absint($_REQUEST[$fkField] ?? 0);
            if ($ancestorId <= 0) {
                continue;
            }

            $ancestorFormName = (string) ($ancestorFormNames[$i] ?? '');
            if ($ancestorFormName === '') {
                continue;
            }

            $ancestorModelClass = (string) ($ancestorModelClasses[$ancestorFormName] ?? '');
            if ($ancestorModelClass === '' || !class_exists($ancestorModelClass)) {
                return "Unable to validate root chain: missing model for ancestor '{$ancestorFormName}'.";
            }

            try {
                $ancestorModel = new $ancestorModelClass();
                $ancestorRecord = $ancestorModel->getByIdForEdit($ancestorId);
            } catch (\Throwable) {
                return "Unable to validate root chain for ancestor '{$ancestorFormName}' record #{$ancestorId}.";
            }

            if (!is_object($ancestorRecord) && !is_array($ancestorRecord)) {
                return "Invalid URL chain: ancestor '{$ancestorFormName}' record #{$ancestorId} not found.";
            }

            $recordRootId = _absint(ModelRecordHelper::extractFieldValue($ancestorRecord, $rootField));

            if ($recordRootId <= 0) {
                return "Invalid URL chain: ancestor '{$ancestorFormName}' record #{$ancestorId} has empty '{$rootField}'.";
            }
            if ($recordRootId !== $expectedRootId) {
                return "Invalid URL chain: ancestor '{$ancestorFormName}' record #{$ancestorId} has '{$rootField}={$recordRootId}', expected {$expectedRootId}.";
            }
        }

        return null;
    }
}
