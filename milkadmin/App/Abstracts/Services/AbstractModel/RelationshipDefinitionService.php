<?php
namespace App\Abstracts\Services\AbstractModel;

use App\Abstracts\RuleBuilder;

!defined('MILK_DIR') && die();

class RelationshipDefinitionService
{
    public function getRelationship(RuleBuilder $ruleBuilder, string $alias): ?array
    {
        foreach ($ruleBuilder->getRules() as $rule) {
            if (isset($rule['relationship']) && ($rule['relationship']['alias'] ?? null) === $alias) {
                return $rule['relationship'];
            }
        }

        return null;
    }

    public function hasMetaRelationship(RuleBuilder $ruleBuilder, string $alias): bool
    {
        $rules = $ruleBuilder->getRules();
        return isset($rules[$alias]['hasMeta']) && $rules[$alias]['hasMeta'] === true;
    }

    public function hasRelationship(RuleBuilder $ruleBuilder, string $alias): bool
    {
        return $this->hasMetaRelationship($ruleBuilder, $alias)
            || $this->getRelationship($ruleBuilder, $alias) !== null;
    }

    public function getMetaConfig(RuleBuilder $ruleBuilder, string $alias): ?array
    {
        $rules = $ruleBuilder->getRules();

        if (!isset($rules[$alias]['_meta_config'])) {
            return null;
        }

        $metaRef = $rules[$alias]['_meta_config'];
        $localKey = $metaRef['local_key'];
        $index = $metaRef['index'];

        return $rules[$localKey]['hasMeta'][$index] ?? null;
    }

    public function getRelationshipAliases(RuleBuilder $ruleBuilder): array
    {
        $aliases = [];

        foreach ($ruleBuilder->getRules() as $rule) {
            if (isset($rule['relationship']['alias'])) {
                $aliases[] = $rule['relationship']['alias'];
            }
        }

        return $aliases;
    }

    public function getWithCountScopes(RuleBuilder $ruleBuilder): array
    {
        $scopes = [];

        foreach ($ruleBuilder->getRules() as $rule) {
            if (!isset($rule['withCount']) || !is_array($rule['withCount'])) {
                continue;
            }

            foreach ($rule['withCount'] as $withCountConfig) {
                $alias = $withCountConfig['alias'] ?? null;
                if (!is_string($alias) || $alias === '') {
                    continue;
                }

                $scopes['withCount:' . $alias] = $withCountConfig;
            }
        }

        return $scopes;
    }
}
