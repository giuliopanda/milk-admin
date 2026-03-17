<?php
!defined('MILK_DIR') && die();

$page = (string) ($page ?? 'projects');
$project = is_array($project ?? null) ? $project : null;
$editAccess = is_array($edit_access ?? null) ? $edit_access : [
    'can_edit' => true,
    'message' => '',
    'inaccessible_paths' => [],
];
$moduleIsEditable = $project !== null && !empty($editAccess['can_edit']);
$inaccessiblePaths = is_array($editAccess['inaccessible_paths'] ?? null) ? $editAccess['inaccessible_paths'] : [];
$searchEditor = is_array($search_editor ?? null) ? $search_editor : [];
$moduleName = trim((string) ($project['module_name'] ?? ''));
$searchEditorCanEdit = $moduleIsEditable && !empty($searchEditor['can_edit']);
$searchEditorError = trim((string) ($searchEditor['error'] ?? ''));
$searchFiltersNotice = trim((string) ($searchEditor['search_filters_notice'] ?? ''));
$rootFormName = trim((string) ($searchEditor['root_form_name'] ?? ''));
$modelFqcn = trim((string) ($searchEditor['model_fqcn'] ?? ''));
$modelFilePath = trim((string) ($searchEditor['model_file_path'] ?? ''));
$availableFields = is_array($searchEditor['fields'] ?? null) ? $searchEditor['fields'] : [];
$availableDbFields = is_array($searchEditor['db_fields'] ?? null) ? $searchEditor['db_fields'] : [];
$initialConfig = is_array($searchEditor['initial_config'] ?? null)
    ? $searchEditor['initial_config']
    : ['search_mode' => 'submit', 'auto_buttons' => true, 'url_params' => [], 'filters' => []];
$queryFieldPickerOptions = [];
foreach ($availableFields as $availableField) {
    if (!is_array($availableField)) {
        continue;
    }
    $fieldName = trim((string) ($availableField['name'] ?? ''));
    if ($fieldName === '') {
        continue;
    }
    $fieldLabel = trim((string) ($availableField['label'] ?? ''));
    if ($fieldLabel === '') {
        $fieldLabel = \Modules\Projects\ManifestService::toTitle($fieldName);
    }
    if ($fieldLabel === '') {
        $fieldLabel = $fieldName;
    }
    $queryFieldPickerOptions[$fieldName] = $fieldLabel . ' (' . $fieldName . ')';
}
$urlParamFieldPickerOptions = [];
foreach ($availableDbFields as $dbField) {
    if (!is_array($dbField)) {
        continue;
    }
    $fieldName = trim((string) ($dbField['name'] ?? ''));
    if ($fieldName === '') {
        continue;
    }
    $fieldLabel = trim((string) ($dbField['label'] ?? ''));
    if ($fieldLabel === '') {
        $fieldLabel = \Modules\Projects\ManifestService::toTitle($fieldName);
        if ($fieldLabel === '') {
            $fieldLabel = $fieldName;
        }
        $fieldLabel .= ' (' . $fieldName . ')';
    }
    $urlParamFieldPickerOptions[$fieldName] = $fieldLabel;
}
$availableFieldsJson = json_encode($availableFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($availableFieldsJson) || $availableFieldsJson === '') {
    $availableFieldsJson = '[]';
}
$availableDbFieldsJson = json_encode($availableDbFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($availableDbFieldsJson) || $availableDbFieldsJson === '') {
    $availableDbFieldsJson = '[]';
}

$projectHomeUrl = '?page=' . rawurlencode($page)
    . '&action=edit'
    . '&module=' . rawurlencode($moduleName);
$saveSuccessRedirectUrl = '?page=' . rawurlencode($page)
    . '&action=save-filters-search-success'
    . '&module=' . rawurlencode($moduleName);
$saveFiltersSearchUrl = '?page=' . rawurlencode($page) . '&action=save-filters-search';

$initialConfigJson = json_encode($initialConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($initialConfigJson) || $initialConfigJson === '') {
    $initialConfigJson = '{"search_mode":"submit","auto_buttons":true,"url_params":[],"filters":[]}';
}

$rightActions = [];
$titleActions = [];
if ($searchEditorCanEdit) {
    $titleActions[] = [
        'label' => 'Save',
        'url' => '#',
        'class' => 'btn btn-success',
        'id' => 'project-search-save-btn',
    ];
}
if ($moduleName !== '') {
    $rightActions[] = [
        'label' => 'Project Home',
        'url' => $projectHomeUrl,
        'class' => 'btn btn-sm btn-outline-secondary',
        'id' => '',
    ];
}
$projectEditContext = \Modules\Projects\ProjectEditContextService::buildBoxData($project, 'Filters and Search', $page, [
    'title_actions' => $titleActions,
    'right_actions' => $rightActions,
]);
?>
<div id="project-edit-filters-search-page"
     class="container-fluid py-3 projects-module-shell"
     data-can-edit="<?php echo $searchEditorCanEdit ? '1' : '0'; ?>"
     data-module="<?php _p($moduleName); ?>"
     data-project-home-url="<?php _p($projectHomeUrl); ?>"
     data-save-success-url="<?php _p($saveSuccessRedirectUrl); ?>"
     data-save-url="<?php _p($saveFiltersSearchUrl); ?>">
    <?php $project_edit_context = $projectEditContext; ?>
    <?php include __DIR__ . '/partials/project_edit_context_box.php'; ?>

    <?php if ($project === null): ?>
        <div class="card projects-page-card">
            <div class="card-body">
                <div class="alert alert-warning mb-0">
                    Project not found or invalid <code>module</code> parameter.
                </div>
            </div>
        </div>
    <?php elseif (!$moduleIsEditable): ?>
        <div class="card projects-page-card">
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <?php _p((string) ($editAccess['message'] ?? 'This module is not editable because of project folder permissions.')); ?>
                    <div class="mt-2">Fix permissions and reload this page.</div>
                    <?php if (!empty($inaccessiblePaths)): ?>
                        <div class="mt-2">
                            <strong>Inaccessible project paths:</strong>
                            <ul class="mb-0 mt-1">
                                <?php foreach (array_slice($inaccessiblePaths, 0, 12) as $issue): ?>
                                    <?php
                                    $path = trim((string) ($issue['path'] ?? ''));
                                    $missingPermissions = trim((string) ($issue['missing_permissions'] ?? ''));
                                    if ($path === '') {
                                        continue;
                                    }
                                    ?>
                                    <li>
                                        <code><?php _p($path); ?></code>
                                        <?php if ($missingPermissions !== ''): ?>
                                            <span class="text-muted">(missing <?php _p($missingPermissions); ?> permission)</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card projects-page-card mb-3">
            <div class="card-body py-2">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <strong>Search config file:</strong>
                        <code>Project/search_filters.json</code>
                    </div>
                    <div class="small text-muted">
                        Root form:
                        <code><?php _p($rootFormName !== '' ? $rootFormName : '-'); ?></code>
                    </div>
                </div>
                <?php if ($modelFqcn !== ''): ?>
                    <div class="small text-muted mt-2">
                        Model:
                        <code><?php _p($modelFqcn); ?></code>
                    </div>
                <?php endif; ?>
                <?php if ($modelFilePath !== ''): ?>
                    <div class="small text-muted">
                        Source:
                        <code><?php _p($modelFilePath); ?></code>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($searchEditorError !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?php _p($searchEditorError); ?>
            </div>
        <?php endif; ?>
        <?php if ($searchFiltersNotice !== ''): ?>
            <div class="alert alert-warning" role="alert">
                <?php _p($searchFiltersNotice); ?>
            </div>
        <?php endif; ?>

        <?php if ($searchEditorCanEdit): ?>
            <script id="project-search-available-fields-data" type="application/json"><?php echo $availableFieldsJson; ?></script>
            <script id="project-search-db-fields-data" type="application/json"><?php echo $availableDbFieldsJson; ?></script>
            <script id="project-search-initial-config-data" type="application/json"><?php echo $initialConfigJson; ?></script>

            <div class="card projects-page-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <strong>Search Fields</strong>
                    <small class="text-muted">Drag rows to reorder</small>
                </div>
                <div class="card-body">
                    <div id="project-search-fields-list" class="list-group"></div>
                    <div id="project-search-fields-empty" class="alert alert-info mt-3 mb-0">
                        No search fields configured yet.
                    </div>

                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <button type="button"
                                class="btn btn-primary"
                                id="project-search-add-field-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#project-search-field-modal">
                            + Add search field
                        </button>
                        <button type="button"
                                class="btn btn-outline-secondary d-none"
                                id="project-search-add-newline-btn">
                            + Add new row
                        </button>
                    </div>
                </div>
            </div>

            <div class="card projects-page-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <strong>URL Params</strong>
                    <small class="text-muted">Allowed URL params with type-safe sanitization</small>
                </div>
                <div class="card-body">
                    <div class="alert alert-secondary py-2 mb-3">
                        <strong>Advanced system:</strong> URL Params lets you whitelist query-string parameters,
                        sanitize them by data type, and apply them automatically to list/table/form context.
                    </div>
                    <div id="project-search-url-params-list" class="list-group"></div>
                    <div id="project-search-url-params-empty" class="alert alert-info mt-3 mb-0">
                        No URL params configured yet.
                    </div>

                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <button type="button"
                                class="btn btn-primary"
                                id="project-search-add-url-param-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#project-search-url-param-modal">
                            + Add URL param
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="project-search-field-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <form class="modal-content" id="project-search-field-form">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Search Field</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="project-search-field-edit-key" value="">
                            <div id="project-search-field-form-error" class="alert alert-danger d-none" role="alert"></div>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="project-search-field-label" class="form-label">Label</label>
                                    <input type="text" class="form-control" id="project-search-field-label" placeholder="ex. Search">
                                    <div class="form-text">Required. Name is generated automatically on save.</div>
                                </div>

                                <div class="col-md-4">
                                    <label for="project-search-field-type" class="form-label">Type</label>
                                    <select id="project-search-field-type" class="form-select" required>
                                        <option value="search_all">search all</option>
                                        <option value="select">select</option>
                                        <option value="action_list">action_list</option>
                                        <option value="input">input</option>
                                    </select>
                                </div>

                                <div class="col-12" id="project-search-field-query-block">
                                    <label class="form-label mb-1" for="project-search-field-query-field-picker">
                                        Query fields
                                    </label>
                                    <?php if (!empty($queryFieldPickerOptions)): ?>
                                        <?php echo \App\Get::themePlugin('MilkSelect', [
                                            'id' => 'project-search-field-query-field-picker',
                                            'options' => $queryFieldPickerOptions,
                                            'type' => 'single',
                                            'placeholder' => 'Search and select field',
                                        ]); ?>
                                    <?php else: ?>
                                        <input type="text"
                                               id="project-search-field-query-field-picker"
                                               class="form-control"
                                               value=""
                                               placeholder="No searchable fields available"
                                               disabled>
                                    <?php endif; ?>

                                    <div class="d-flex align-items-center gap-2 flex-wrap mt-2 mb-2">
                                        <button type="button"
                                                id="project-search-field-query-field-add"
                                                class="btn btn-outline-secondary btn-sm">
                                            Add field
                                        </button>
                                        <span id="project-search-field-query-fields-count" class="small text-muted">
                                            0 selected
                                        </span>
                                    </div>

                                    <input type="hidden" id="project-search-field-query-fields-values" value="[]">

                                    <div id="project-search-field-query-fields-list" class="list-group"></div>

                                    <div id="project-search-field-query-fields-empty" class="alert alert-info mt-2 mb-0">
                                        No query fields selected.
                                    </div>

                                    <div id="project-search-field-query-fields-source-empty" class="alert alert-warning mt-2 d-none mb-0">
                                        No searchable fields available in root model or relations.
                                    </div>

                                    <div class="form-text">
                                        Select a field from MilkSelect and click <strong>Add field</strong>. Repeat for multiple fields.
                                    </div>
                                </div>

                                <div class="col-md-4" id="project-search-field-operator-block">
                                    <label for="project-search-field-operator" class="form-label">Operator</label>
                                    <select id="project-search-field-operator" class="form-select">
                                        <option value="like" selected>like</option>
                                        <option value="equals">equals</option>
                                        <option value="starts_with">starts_with</option>
                                        <option value="ends_with">ends_with</option>
                                        <option value="greater_than">greater_than</option>
                                        <option value="greater_or_equal">greater_or_equal</option>
                                        <option value="less_than">less_than</option>
                                        <option value="less_or_equal">less_or_equal</option>
                                        <option value="between">between</option>
                                    </select>
                                </div>

                                <div class="col-12 d-none" id="project-search-field-options-block">
                                    <label class="form-label">Options</label>
                                    <div id="project-search-options-editor" class="projects-options-editor">
                                        <div class="options-tabs">
                                            <button type="button" class="options-tab active" data-tab="manual">
                                                <i class="bi bi-grip-vertical"></i> Manual
                                            </button>
                                            <button type="button" class="options-tab" data-tab="bulk">
                                                <i class="bi bi-text-paragraph"></i> Text
                                            </button>
                                        </div>

                                        <div class="options-panel active" data-panel="manual">
                                            <div class="options-sortable-list" id="project-search-options-list"></div>
                                            <button type="button" class="btn-add-option" id="project-search-options-add">
                                                <i class="bi bi-plus-circle"></i> Add option
                                            </button>
                                        </div>

                                        <div class="options-panel" data-panel="bulk">
                                            <textarea class="form-control code-input options-bulk-input"
                                                      id="project-search-options-bulk"
                                                      rows="6"></textarea>
                                            <div class="form-text">One option per line, format: value|label</div>
                                        </div>
                                    </div>
                                    <div class="form-text">
                                        Used by <code>select</code> and <code>action_list</code>.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="project-search-field-submit-btn">Add</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="project-search-url-param-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <form class="modal-content" id="project-search-url-param-form">
                        <div class="modal-header">
                            <h5 class="modal-title">Add URL Param</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="project-search-url-param-edit-key" value="">
                            <div id="project-search-url-param-form-error" class="alert alert-danger d-none" role="alert"></div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="project-search-url-param-name" class="form-label">URL param name</label>
                                    <input type="text"
                                           id="project-search-url-param-name"
                                           class="form-control"
                                           placeholder="ex. user_id">
                                    <div class="form-text">Querystring key expected in URL (ex. <code>?user_id=2</code>).</div>
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label mb-1" for="project-search-url-param-field">
                                        Target field
                                    </label>
                                    <?php if (!empty($urlParamFieldPickerOptions)): ?>
                                        <?php echo \App\Get::themePlugin('MilkSelect', [
                                            'id' => 'project-search-url-param-field',
                                            'options' => $urlParamFieldPickerOptions,
                                            'type' => 'single',
                                            'placeholder' => 'Search and select DB column',
                                        ]); ?>
                                    <?php else: ?>
                                        <input type="text"
                                               id="project-search-url-param-field"
                                               class="form-control"
                                               value=""
                                               placeholder="No DB fields available"
                                               disabled>
                                    <?php endif; ?>
                                    <div class="form-text">DB column used in SQL WHERE and customData.</div>
                                </div>
                                <input type="hidden" id="project-search-url-param-type" value="string">

                                <div class="col-md-4">
                                    <label for="project-search-url-param-operator" class="form-label">Operator</label>
                                    <select id="project-search-url-param-operator" class="form-select">
                                        <option value="equals" selected>equals</option>
                                        <option value="like">like</option>
                                        <option value="starts_with">starts_with</option>
                                        <option value="ends_with">ends_with</option>
                                        <option value="greater_than">greater_than</option>
                                        <option value="greater_or_equal">greater_or_equal</option>
                                        <option value="less_than">less_than</option>
                                        <option value="less_or_equal">less_or_equal</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-check form-switch mt-4 pt-2">
                                        <input class="form-check-input me-2"
                                               type="checkbox"
                                               id="project-search-url-param-required">
                                        <label class="form-check-label" for="project-search-url-param-required">
                                            Required
                                        </label>
                                    </div>
                                    <div class="form-text">If invalid or missing, no rows will be returned.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="project-search-url-param-submit-btn">Add</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
