<?php
namespace Extensions\Projects\Classes\Renderers;

!defined('MILK_DIR') && die();

/**
 * Resolves list field/link behavior independently from a specific output facade.
 */
class LinkConfigurator
{
    /**
     * Resolve the final list column key for a model field.
     */
    public function resolveListFieldKey(string $fieldName, array $fieldRule): string
    {
        $fieldName = trim($fieldName);
        if ($fieldName === '') {
            return '';
        }

        $relationship = is_array($fieldRule['relationship'] ?? null) ? $fieldRule['relationship'] : [];
        if (($relationship['type'] ?? '') !== 'belongsTo') {
            return $fieldName;
        }

        $alias = trim((string) ($relationship['alias'] ?? ''));
        if ($alias === '') {
            return $fieldName;
        }

        $displayField = trim((string) ($fieldRule['api_display_field'] ?? ''));
        if ($displayField === '') {
            $displayField = trim((string) ($relationship['auto_display_field'] ?? ''));
        }

        if ($displayField === '') {
            $displayField = $this->detectRelationshipDisplayField($relationship);
        }

        if ($displayField === '') {
            return $fieldName;
        }

        return $alias . '.' . $displayField;
    }

    /**
     * Best-effort display field detection for belongsTo when not explicitly set.
     */
    public function detectRelationshipDisplayField(array $relationship): string
    {
        $relatedModelClass = trim((string) ($relationship['related_model'] ?? ''));
        if ($relatedModelClass === '' || !class_exists($relatedModelClass)) {
            return '';
        }

        try {
            $related = new $relatedModelClass();
        } catch (\Throwable) {
            return '';
        }

        if (!method_exists($related, 'getRules')) {
            return '';
        }

        $rules = $related->getRules();
        if (!is_array($rules) || empty($rules)) {
            return '';
        }

        foreach ($rules as $ruleField => $ruleDef) {
            if (!is_array($ruleDef)) {
                continue;
            }
            if (($ruleDef['title'] ?? false) || ($ruleDef['_is_title_field'] ?? false)) {
                return (string) $ruleField;
            }
        }

        foreach (['name', 'title', 'label', 'username', 'email'] as $candidate) {
            if (array_key_exists($candidate, $rules)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Determine which field should carry the clickable row link.
     *
     * In embedded view tables, prefers the model title field.
     */
    public function determineLinkField(ListContextParams $p, array $fields, array $modelRules): ?string
    {
        if ($p->isEmbeddedViewTable) {
            return $this->resolveLinkField($fields, $p->primaryKey, $p->childrenMetaByAlias, $modelRules);
        }

        $linkField = $fields[0] ?? null;
        foreach ($fields as $field) {
            if ($field !== $p->primaryKey) {
                if ($p->useSingleEntryRootView && isset($p->childrenMetaByAlias[$field])) {
                    continue;
                }
                $linkField = $field;
                break;
            }
        }
        return $linkField;
    }

    /**
     * Resolve the row link target action and URL.
     *
     * @return array{0:string,1:string}
     */
    public function resolveRowLinkTarget(ListContextParams $p, string $editUrl, string $viewUrl): array
    {
        if ($p->isEmbeddedViewTable && $p->editAction !== '') {
            return [$p->editAction, $editUrl];
        }
        if ($p->viewAction !== '') {
            return [$p->viewAction, $viewUrl];
        }
        return [$p->editAction, $editUrl];
    }

    /**
     * Resolve display mode for row link target.
     */
    public function resolveRowLinkDisplay(ListContextParams $p): string
    {
        if ($p->isEmbeddedViewTable && $p->editAction !== '') {
            return $p->editDisplay;
        }
        return $p->viewAction !== '' ? $p->viewDisplay : $p->editDisplay;
    }

    /**
     * Resolve which field should carry the clickable row link (embedded mode).
     */
    public function resolveLinkField(
        array $fields,
        string $primaryKey,
        array $childrenMetaByAlias,
        array $modelRules
    ): ?string {
        $titleField = $this->resolveModelTitleField($modelRules);
        if ($titleField !== null
            && in_array($titleField, $fields, true)
            && !isset($childrenMetaByAlias[$titleField])) {
            return $titleField;
        }

        foreach ($fields as $field) {
            if ($field !== $primaryKey && !isset($childrenMetaByAlias[$field])) {
                return $field;
            }
        }

        foreach ($fields as $field) {
            if (!isset($childrenMetaByAlias[$field])) {
                return $field;
            }
        }

        return $fields[0] ?? null;
    }

    /**
     * Resolve the model's title field from its rules.
     */
    public function resolveModelTitleField(array $modelRules): ?string
    {
        foreach ($modelRules as $field => $rule) {
            if (!is_string($field) || !is_array($rule)) {
                continue;
            }
            if (!empty($rule['_is_title_field']) || !empty($rule['title'])) {
                return $field;
            }
        }

        foreach ($modelRules as $field => $rule) {
            if (!is_string($field)) {
                continue;
            }
            if (strtolower($field) === 'title') {
                return $field;
            }
        }

        return null;
    }
}
