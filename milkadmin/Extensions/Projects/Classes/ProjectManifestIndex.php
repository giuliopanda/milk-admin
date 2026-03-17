<?php
namespace Extensions\Projects\Classes;

!defined('MILK_DIR') && die();

/**
 * Index/graph utilities for manifest forms tree.
 *
 * Builds:
 * - node map (by form name)
 * - parent links
 * - children lists
 * - ancestor chain
 */
class ProjectManifestIndex
{
    /**
     * @var array<string,array{
     *   form_name:string,
     *   ref:string,
     *   max_records:string,
     *   show_if:string,
     *   show_if_message:string,
     *   show_search:bool,
     *   soft_delete:bool,
     *   allow_delete_record:bool,
     *   allow_edit:bool,
     *   default_order_enabled:bool,
     *   default_order_field:string,
     *   default_order_direction:string,
     *   view_action:bool,
     *   child_count_column:string,
     *   view_display:string,
     *   list_display:string,
     *   edit_display:string,
     *   parent_form_name:?string,
     *   children:array<int,string>
     * }>
     */
    protected array $nodes = [];

    /**
     * @var array<int,string>
     */
    protected array $rootForms = [];

    public function __construct(ProjectManifest $manifest)
    {
        $this->build($manifest->getFormsTree());
    }

    /**
     * @return array<int,string>
     */
    public function getRootFormNames(): array
    {
        return $this->rootForms;
    }

    /**
     * @return array<string,array{
     *   form_name:string,
     *   ref:string,
     *   max_records:string,
     *   show_if:string,
     *   show_if_message:string,
     *   show_search:bool,
     *   soft_delete:bool,
     *   allow_delete_record:bool,
     *   allow_edit:bool,
     *   default_order_enabled:bool,
     *   default_order_field:string,
     *   default_order_direction:string,
     *   view_action:bool,
     *   child_count_column:string,
     *   view_display:string,
     *   list_display:string,
     *   edit_display:string,
     *   parent_form_name:?string,
     *   children:array<int,string>
     * }>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function getNode(string $formName): ?array
    {
        $formName = trim($formName);
        return $formName !== '' ? ($this->nodes[$formName] ?? null) : null;
    }

    public function getParentFormName(string $formName): ?string
    {
        $node = $this->getNode($formName);
        return is_array($node) ? ($node['parent_form_name'] ?? null) : null;
    }

    /**
     * @return array<int,string>
     */
    public function getChildrenFormNames(string $formName): array
    {
        $node = $this->getNode($formName);
        return is_array($node) ? ($node['children'] ?? []) : [];
    }

    /**
     * Returns ancestors from root to parent (does not include current node).
     *
     * @return array<int,string>
     */
    public function getAncestorFormNames(string $formName): array
    {
        $ancestors = [];
        $current = $this->getParentFormName($formName);
        while ($current !== null && $current !== '') {
            array_unshift($ancestors, $current);
            $current = $this->getParentFormName($current);
        }
        return $ancestors;
    }

    /**
     * Returns the FK param chain needed to identify a node context.
     *
     * Example:
     * Root A
     *  - B (fk: a_id)
     *    - C (fk: b_id)
     *
     * Chain for C: [a_id, b_id]
     *
     * @return array<int,string>
     */
    public function getFkChainFields(string $formName): array
    {
        $chain = [];
        $current = $formName;
        while (true) {
            $parent = $this->getParentFormName($current);
            if ($parent === null || $parent === '') {
                break;
            }
            array_unshift($chain, ProjectNaming::foreignKeyFieldForParentForm($parent));
            $current = $parent;
        }
        return $chain;
    }

    // ---------------------------------------------------------------------

    protected function build(array $formsTree): void
    {
        $this->nodes = [];
        $this->rootForms = [];

        foreach ($formsTree as $node) {
            $this->walkNode($node, null);
        }

        // Build roots list from nodes with no parent.
        foreach ($this->nodes as $formName => $node) {
            if (($node['parent_form_name'] ?? null) === null) {
                $this->rootForms[] = $formName;
            }
        }
    }

    protected function walkNode(array $node, ?string $parentFormName): void
    {
        $ref = basename((string) ($node['ref'] ?? ''));
        $formName = (string) pathinfo($ref, PATHINFO_FILENAME);
        if ($formName === '') {
            return;
        }

        if (isset($this->nodes[$formName])) {
            // Form names must be unique in a module to avoid route conflicts.
            throw new \RuntimeException("Duplicate form name '{$formName}' in manifest tree.");
        }

        $maxRecords = ProjectJsonStore::normalizeMaxRecords(
            ProjectJsonStore::resolveAliasedKey($node, ['max_records', 'maxRecords'], null)
        );
        $showIf = trim((string) ProjectJsonStore::resolveAliasedKey($node, ['showIf', 'show_if'], ''));
        $showIfMessage = trim((string) ProjectJsonStore::resolveAliasedKey($node, ['showIfMessage', 'show_if_message'], ''));
        $showSearch = ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey($node, ['showSearch', 'show_search'], false)
        );
        $softDelete = ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey($node, ['softDelete', 'soft_delete'], false)
        );
        $allowDeleteRecord = !ProjectJsonStore::hasAliasedKey($node, ['allowDeleteRecord', 'allow_delete_record'])
            ? true
            : ProjectJsonStore::normalizeBool(
                ProjectJsonStore::resolveAliasedKey($node, ['allowDeleteRecord', 'allow_delete_record'], false)
            );
        $allowEdit = !ProjectJsonStore::hasAliasedKey($node, ['allowEdit', 'allow_edit'])
            ? true
            : ProjectJsonStore::normalizeBool(
                ProjectJsonStore::resolveAliasedKey($node, ['allowEdit', 'allow_edit'], false)
            );
        $defaultOrderEnabled = ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey($node, ['defaultOrderEnabled', 'default_order_enabled'], false)
        );
        $defaultOrderField = trim((string) ProjectJsonStore::resolveAliasedKey(
            $node,
            ['defaultOrderField', 'default_order_field'],
            ''
        ));
        $defaultOrderDirection = strtolower(trim((string) ProjectJsonStore::resolveAliasedKey(
            $node,
            ['defaultOrderDirection', 'default_order_direction'],
            'asc'
        )));
        if (!in_array($defaultOrderDirection, ['asc', 'desc'], true)) {
            $defaultOrderDirection = 'asc';
        }
        if ($defaultOrderField === '') {
            $defaultOrderEnabled = false;
        }
        $viewAction = ProjectJsonStore::normalizeBool(
            ProjectJsonStore::resolveAliasedKey(
                $node,
                ['viewSingleRecord', 'view_single_record', 'viewAction', 'view_action'],
                false
            )
        );
        $childCountColumn = ProjectJsonStore::normalizeChildCountColumnMode(
            ProjectJsonStore::resolveAliasedKey(
                $node,
                ['childCountColumn', 'child_count_column'],
                ''
            )
        );
        $viewDisplay = ProjectJsonStore::normalizeDisplayMode(
            ProjectJsonStore::resolveAliasedKey($node, ['viewDisplay', 'view_display'], null),
            'page'
        );
        $listDisplay = ProjectJsonStore::normalizeDisplayMode(
            ProjectJsonStore::resolveAliasedKey($node, ['listDisplay', 'list_display'], null),
            'page'
        );
        $editDisplay = ProjectJsonStore::normalizeDisplayMode(
            ProjectJsonStore::resolveAliasedKey($node, ['editDisplay', 'edit_display'], null),
            'page'
        );
        $childrenTree = is_array($node['forms'] ?? null) ? $node['forms'] : [];

        $this->nodes[$formName] = [
            'form_name' => $formName,
            'ref' => $ref,
            'max_records' => $maxRecords,
            'show_if' => $showIf,
            'show_if_message' => $showIfMessage,
            'show_search' => $showSearch,
            'soft_delete' => $softDelete,
            'allow_delete_record' => $allowDeleteRecord,
            'allow_edit' => $allowEdit,
            'default_order_enabled' => $defaultOrderEnabled,
            'default_order_field' => $defaultOrderField,
            'default_order_direction' => $defaultOrderDirection,
            'view_action' => $viewAction,
            'child_count_column' => $childCountColumn,
            'view_display' => $viewDisplay,
            'list_display' => $listDisplay,
            'edit_display' => $editDisplay,
            'parent_form_name' => $parentFormName,
            'children' => [],
        ];

        foreach ($childrenTree as $child) {
            if (!is_array($child)) {
                continue;
            }
            $childRef = basename((string) ($child['ref'] ?? ''));
            $childName = (string) pathinfo($childRef, PATHINFO_FILENAME);
            if ($childName !== '') {
                $this->nodes[$formName]['children'][] = $childName;
            }
            $this->walkNode($child, $formName);
        }
    }

}
