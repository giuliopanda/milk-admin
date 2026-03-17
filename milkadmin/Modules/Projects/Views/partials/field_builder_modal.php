<?php
!defined('MILK_DIR') && die();
?>
<div class="modal fade project-field-builder-modal" id="fieldBuilderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fieldBuilderModalTitle">
                    <i class="bi bi-plus-circle-fill"></i>
                    Create New Field
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form id="fieldBuilderForm" novalidate>
                    <div class="main-tabs">
                        <button type="button" class="main-tab active" data-maintab="base">
                            <i class="bi bi-sliders"></i> Basic
                        </button>
                        <button type="button" class="main-tab" data-maintab="advanced">
                            <i class="bi bi-gear-fill"></i> Advanced
                        </button>
                    </div>

                    <div class="main-tab-content active" data-maintab-content="base">
                        <div id="modelFieldEditNotice" class="alert alert-warning d-none" role="alert">
                            This field is defined in the PHP model: in the builder you can edit only part of its settings.
                        </div>
                        <div id="modelFieldIdentityInfo" class="alert alert-light border d-none" role="note">
                            <div><strong>Field Name:</strong> <code id="modelFieldReadonlyName"></code></div>
                            <div><strong>Field Type:</strong> <span id="modelFieldReadonlyType"></span></div>
                        </div>

                        <div class="form-section">
                            <div class="section-title">
                                <i class="bi bi-tag-fill"></i>
                                Field Identification
                            </div>

                            <div class="mb-3">
                                <label for="fieldLabel" class="form-label">
                                    Label <span class="required">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="fieldLabel"
                                       name="field_label"
                                       required>
                                <div class="form-text">Text displayed in the form</div>
                            </div>

                            <div class="mb-3" id="fieldNameInputGroup">
                                <label for="fieldName" class="form-label">
                                    Field Name <small>(optional - auto-generated)</small>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="fieldName"
                                       name="field_name"
                                       maxlength="32"
                                       pattern="^[a-z][a-z0-9_]*$">
                                <div class="invalid-feedback" id="fieldNameInvalidFeedback">
                                    Field name must start with a letter and contain only lowercase letters, numbers, and underscore.
                                </div>
                                <div class="form-text">If empty, generated from label</div>
                            </div>

                            <div id="customHtmlFieldInfo" class="alert alert-info d-none mb-0">
                                For <strong>Custom HTML</strong>, <strong>Field Name</strong> and <strong>Label</strong> are used only to render and manage the item in admin.
                            </div>
                        </div>

                        <div class="form-section" id="fieldTypeSection">
                            <div class="section-title">
                                <i class="bi bi-grid-3x3-gap-fill"></i>
                                Field Type
                            </div>

                            <div class="field-type-selector mb-3">
                                <div class="type-trigger" id="typeTrigger">
                                    <div class="type-current" id="typeCurrent">
                                        <i class="bi bi-fonts"></i>
                                        <div>
                                            <div class="type-current-title">Text</div>
                                            <small class="type-current-description">Simple text field</small>
                                        </div>
                                    </div>
                                    <i class="bi bi-chevron-down"></i>
                                </div>

                                <div class="type-grid-wrapper" id="typeGrid">
                                    <div class="field-type-grid" id="fieldTypeOptions"></div>
                                </div>
                            </div>

                            <input type="hidden" name="field_type" id="fieldType" value="string">
                            <input type="hidden" name="db_type" id="dbType" value="varchar">

                            <div id="typeParameters"></div>
                        </div>

                        <div class="form-section" id="optionsSection" style="display: none;">
                            <div class="section-title">
                                <i class="bi bi-list-check"></i>
                                Options Management
                            </div>

                            <div id="optionsManualGroup">
                                <div class="mb-3">
                                    <label class="form-label">Management Type</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="options_mode" id="optionsModeManual" value="manual" checked onchange="toggleOptionsMode()">
                                        <label class="btn btn-outline-primary btn-sm" for="optionsModeManual">
                                            <i class="bi bi-pencil-square"></i> Manual / Text
                                        </label>

                                        <input type="radio" class="btn-check" name="options_mode" id="optionsModeTable" value="table" onchange="toggleOptionsMode()">
                                        <label class="btn btn-outline-primary btn-sm" for="optionsModeTable">
                                            <i class="bi bi-table"></i> From Table
                                        </label>
                                    </div>
                                </div>

                                <div id="manualBulkPanel" class="projects-options-editor">
                                    <div class="options-tabs">
                                        <button type="button" class="options-tab active" data-tab="manual">
                                            <i class="bi bi-grip-vertical"></i> Manual
                                        </button>
                                        <button type="button" class="options-tab" data-tab="bulk">
                                            <i class="bi bi-text-paragraph"></i> Text
                                        </button>
                                    </div>

                                    <div class="options-panel active" data-panel="manual">
                                        <div class="options-sortable-list" id="optionsList"></div>
                                        <button type="button" class="btn-add-option">
                                            <i class="bi bi-plus-circle"></i> Add option
                                        </button>
                                    </div>

                                    <div class="options-panel" data-panel="bulk">
                                        <textarea class="form-control code-input options-bulk-input"
                                                  id="optionsBulk"
                                                  rows="6"></textarea>
                                        <div class="form-text">One option per line, format: value|label</div>
                                    </div>
                                </div>

                                <div id="tableSourcePanel" style="display: none;">
                                    <div class="table-source-group">
                                        <div class="table-source-col-wide">
                                            <label class="form-label" for="tableSourceManual">Model</label>
                                            <select class="form-select" id="tableSourceManual">
                                                <option value="">Select model</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label" for="tableValueFieldManual">Value Field</label>
                                            <select class="form-select" id="tableValueFieldManual" disabled>
                                                <option value="">Select model first</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label" for="tableLabelFieldManual">Label Field</label>
                                            <select class="form-select" id="tableLabelFieldManual" disabled>
                                                <option value="">Select model first</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <label class="form-label">WHERE Condition <small>(optional)</small></label>
                                        <input type="text" class="form-control code-input" id="tableWhereManual">
                                    </div>
                                    <div class="form-text mt-2">All options will be loaded from the selected model</div>
                                </div>
                            </div>

                            <div id="optionsTableGroup" style="display: none;">
                                <div class="table-source-group">
                                    <div class="table-source-col-wide">
                                        <label class="form-label" for="tableSource">Model</label>
                                        <select class="form-select" id="tableSource">
                                            <option value="">Select model</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label" for="tableValueField">Value Field</label>
                                        <select class="form-select" id="tableValueField" disabled>
                                            <option value="">Select model first</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label" for="tableLabelField">Label Field</label>
                                        <select class="form-select" id="tableLabelField" disabled>
                                            <option value="">Select model first</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label class="form-label">WHERE Condition <small>(optional)</small></label>
                                    <input type="text" class="form-control code-input" id="tableWhere">
                                </div>
                                <div class="form-text mt-2">Options will be loaded dynamically from the selected model</div>
                            </div>

                            <div id="relationSourceGroup" style="display: none;">
                                <div class="table-source-group">
                                    <div class="table-source-col-wide">
                                        <label class="form-label" for="relationTableSource">Model</label>
                                        <select class="form-select" id="relationTableSource">
                                            <option value="">Select model</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label" for="relationLabelColumn">Label column</label>
                                        <select class="form-select" id="relationLabelColumn" disabled>
                                            <option value="">Select model first</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label" for="relationAliasName">Relation name</label>
                                        <input type="text" class="form-control" id="relationAliasName">
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label class="form-label" for="relationWhere">Where condition</label>
                                    <input type="text" class="form-control code-input" id="relationWhere" placeholder="active = 1">
                                    <div class="form-text">SQL WHERE condition applied to the relation query (e.g. <code>active = 1</code>)</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="main-tab-content" data-maintab-content="advanced">
                        <div class="form-section" id="databaseTypeSection">
                            <div class="section-title section-title-with-action">
                                <span class="section-title-label">
                                    <i class="bi bi-database-fill"></i>
                                    Database Type
                                </span>
                                <div class="form-check form-switch db-exclude-toggle">
                                    <input class="form-check-input me-1" type="checkbox" id="excludeFromDbToggle" name="exclude_from_db">
                                    <label class="form-check-label small text-muted" for="excludeFromDbToggle">Exclude from DB</label>
                                </div>
                            </div>

                            <div class="db-type-selector" id="dbTypeSelectorWrapper">
                                <div class="db-type-trigger" id="dbTypeTrigger">
                                    <div class="db-type-display">
                                        <i class="bi bi-database-fill"></i>
                                        <strong id="dbTypeDisplay">VARCHAR(255)</strong>
                                    </div>
                                    <button type="button" class="btn-edit-db">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                </div>

                                <div class="db-type-editor-wrapper" id="dbTypeEditor">
                                    <div class="mb-3">
                                        <label class="form-label">Database Type</label>
                                        <select class="form-select" id="dbTypeSelect" onchange="updateDbEditorParams()"></select>
                                    </div>
                                    <div id="dbEditorParams"></div>
                                    <div class="d-flex gap-2 mt-3">
                                        <button type="button" class="btn btn-secondary btn-sm flex-fill" onclick="cancelDbTypeEdit()">Cancel</button>
                                        <button type="button" class="btn btn-primary btn-sm flex-fill" onclick="applyDbType()">
                                            <i class="bi bi-check-circle"></i> Apply
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="fieldPropertiesSection">
                            <div class="section-title">
                                <i class="bi bi-sliders"></i>
                                Field Properties
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input me-2" type="checkbox" id="fieldRequired" name="required">
                                        <label class="form-check-label" for="fieldRequired">Required field</label>
                                    </div>
                                </div>
                                <div class="col-md-4" id="fieldReadonlyGroup">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input me-2" type="checkbox" id="fieldReadonly" name="readonly">
                                        <label class="form-check-label" for="fieldReadonly">Read-only</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 mt-3">
                                <div class="col-md-6" id="defaultValueGroup">
                                    <label class="form-label">Default Value</label>
                                    <input type="text" class="form-control" name="default_value">
                                </div>
                                <div class="col-md-6" id="customAlignmentGroup">
                                    <label class="form-label">Custom Alignment</label>
                                    <select class="form-select" name="custom_alignment">
                                        <option value="horizontal">Horizontal</option>
                                        <option value="vertical_1">Vertical (1 column)</option>
                                        <option value="vertical_2">Vertical (2 columns)</option>
                                        <option value="vertical_3">Vertical (3 columns)</option>
                                        <option value="vertical_4">Vertical (4 columns)</option>
                                    </select>
                                    <div class="form-text">Field alignment on the page</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="validationSection">
                            <div class="section-title">
                                <i class="bi bi-shield-check"></i>
                                Validation
                            </div>

                            <div class="row g-2 mb-3" id="validationParams"></div>

                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label">Validation Expression</label>
                                    <textarea class="form-control code-input" name="validate_expr" rows="2"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Error Message</label>
                                    <input type="text" class="form-control" name="error_message">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-title">
                                <i class="bi bi-gear-fill"></i>
                                Behavior
                            </div>

                            <div id="advancedNonVisibilityGroup">
                                <div class="mb-3" id="helpTextGroup">
                                    <label class="form-label">Help Text</label>
                                    <input type="text" class="form-control" name="help_text">
                                </div>

                                <div class="mb-3" id="showIfGroup">
                                    <label class="form-label">Show If (Conditional)</label>
                                    <textarea class="form-control code-input" name="show_if_expr" rows="2"></textarea>
                                </div>

                                <div class="mb-3" id="calcExprGroup">
                                    <label class="form-label">Calculated Value</label>
                                    <textarea class="form-control code-input" name="calc_expr" rows="2"></textarea>
                                </div>
                            </div>

                            <div id="advancedBehavior">
                                <div class="row g-2 mt-2">
                                    <div class="col-12">
                                        <label class="form-label d-block mb-2">Field Visibility:</label>
                                        <div class="visibility-toggles">
                                            <div class="visibility-toggle">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input me-2" type="checkbox" id="showInList" name="show_in_list" checked>
                                                    <label class="form-check-label" for="showInList"><i class="bi bi-table"></i> List</label>
                                                </div>
                                            </div>
                                            <div class="visibility-toggle">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input me-2" type="checkbox" id="showInEdit" name="show_in_edit" checked>
                                                    <label class="form-check-label" for="showInEdit"><i class="bi bi-pencil"></i> Edit</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 mt-3" id="listOptionsBlock" style="display: none;">
                                        <div class="list-options-section">
                                            <div class="list-options-title"><i class="bi bi-table"></i> List Options</div>

                                            <!-- Link -->
                                            <div class="list-option-group">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input me-2" type="checkbox" id="listLinkEnabled" name="list_link_enabled">
                                                    <label class="form-check-label" for="listLinkEnabled"><i class="bi bi-link-45deg"></i> Add link</label>
                                                </div>
                                                <div id="listLinkOptions" class="mt-2" style="display: none;">
                                                    <div class="row g-2 align-items-end">
                                                        <div class="col">
                                                            <label class="form-label mb-1" for="listLinkUrl">Link URL</label>
                                                            <input type="text" class="form-control form-control-sm code-input" id="listLinkUrl" name="list_link_url" placeholder="e.g. ?page=module&action=view&id=%id%">
                                                        </div>
                                                        <div class="col-auto" style="min-width: 140px;">
                                                            <label class="form-label mb-1" for="listLinkTarget">Open link</label>
                                                            <select class="form-select form-select-sm" id="listLinkTarget" name="list_link_target">
                                                                <option value="same_window">Same window</option>
                                                                <option value="new_window">New window</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-text mt-1">Use <code>%field_name%</code> to insert row values, e.g. <code>?page=orders&id=%id%&type=%status%</code></div>
                                                </div>
                                            </div>

                                            <!-- Display Mode -->
                                            <div class="list-option-group">
                                                <label class="list-option-group-label"><i class="bi bi-eye"></i> Display</label>

                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input me-2" type="checkbox" id="listHtmlEnabled" name="list_html_enabled">
                                                    <label class="form-check-label" for="listHtmlEnabled">Render as HTML</label>
                                                </div>

                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input me-2" type="checkbox" id="listTruncateEnabled" name="list_truncate_enabled">
                                                        <label class="form-check-label" for="listTruncateEnabled">Truncate text</label>
                                                    </div>
                                                    <div id="listTruncateOptions" class="list-truncate-inline" style="display: none;">
                                                        <label class="form-label" for="listTruncateLength">max</label>
                                                        <input type="number" class="form-control form-control-sm" id="listTruncateLength" name="list_truncate_length" value="50" min="1">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Relation Extra Fields -->
                                            <div class="list-option-group d-none" id="listRelationFieldsGroup">
                                                <label class="list-option-group-label"><i class="bi bi-diagram-3"></i> Relation fields in list</label>
                                                <div class="row g-2 align-items-end">
                                                    <div class="col">
                                                        <label class="form-label mb-1" for="listRelationFieldSelect">Related field</label>
                                                        <select class="form-select form-select-sm" id="listRelationFieldSelect" disabled>
                                                            <option value="">Select relation field</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-auto">
                                                        <button type="button" class="btn btn-outline-primary btn-sm" id="listRelationFieldAddBtn">
                                                            <i class="bi bi-plus-circle"></i> Add field
                                                        </button>
                                                    </div>
                                                </div>
                                                <div id="listRelationFieldsSelected" class="relation-list-fields-selected mt-2"></div>
                                                <div class="form-text mt-1">Add extra columns from the related model to show in the list table.</div>
                                            </div>

                                            <!-- Change Values -->
                                            <div class="list-option-group">
                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input me-2" type="checkbox" id="listChangeValuesEnabled" name="list_change_values_enabled">
                                                    <label class="form-check-label" for="listChangeValuesEnabled"><i class="bi bi-arrow-left-right"></i> Change Values</label>
                                                </div>
                                                <div id="listChangeValuesOptions" style="display: none;">
                                                    <small class="text-muted d-block mb-2">Map stored values to display labels in the list.</small>
                                                    <div id="listChangeValuesEditor" class="projects-options-editor">
                                                        <div class="options-tabs">
                                                            <button type="button" class="options-tab active" data-tab="manual">
                                                                <i class="bi bi-grip-vertical"></i> Manual
                                                            </button>
                                                            <button type="button" class="options-tab" data-tab="bulk">
                                                                <i class="bi bi-text-paragraph"></i> Text
                                                            </button>
                                                        </div>
                                                        <div class="options-panel active" data-panel="manual">
                                                            <div class="options-sortable-list" id="listChangeValuesList"></div>
                                                            <button type="button" class="btn-add-option" id="listChangeValuesAddBtn">
                                                                <i class="bi bi-plus-circle"></i> Add mapping
                                                            </button>
                                                        </div>
                                                        <div class="options-panel" data-panel="bulk">
                                                            <textarea class="form-control code-input options-bulk-input"
                                                                      id="listChangeValuesBulk"
                                                                      rows="6"></textarea>
                                                            <div class="form-text">One mapping per line, format: value|display label</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="fieldBuilderForm" class="btn btn-primary" id="fieldBuilderSubmitBtn">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Create Field</span>
                </button>
            </div>
        </div>
    </div>
</div>
