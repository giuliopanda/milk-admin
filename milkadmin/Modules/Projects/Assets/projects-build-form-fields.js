(function () {
    'use strict';

    var FIELD_LABEL_MAX_LENGTH = 50;
    var CONTAINER_ID_PATTERN = /^[A-Za-z][A-Za-z0-9_-]{0,63}$/;
    var existingTableLocked = false;
    var FIELD_TYPE_LABELS = {
        string: 'Text',
        text: 'Textarea',
        int: 'Number',
        email: 'Email',
        tel: 'Phone',
        url: 'URL',
        date: 'Date',
        datetime: 'Date & Time',
        time: 'Time',
        boolean: 'Checkbox',
        select: 'Select',
        relation: 'Relation',
        radio: 'Radio',
        checkboxes: 'Checkboxes',
        file: 'File',
        image: 'Image',
        hidden: 'Hidden',
        html: 'Custom HTML'
    };

    function normalizeBool(value) {
        if (typeof value === 'boolean') return value;
        if (typeof value === 'number') return value === 1;
        var normalized = String(value || '').trim().toLowerCase();
        return normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on';
    }

    function notify(message, type) {
        if (window.toasts && typeof window.toasts.show === 'function') {
            window.toasts.show(message, type || 'info');
            return;
        }
        alert(message);
    }

    function safeParseJsonData(nodeId) {
        var node = document.getElementById(nodeId);
        if (!node) return [];
        try {
            var data = JSON.parse(node.textContent || '[]');
            return Array.isArray(data) ? data : [];
        } catch (e) {
            return [];
        }
    }

    function safeParseFields() {
        return safeParseJsonData('project-form-fields-data');
    }

    function safeParseContainers() {
        return safeParseJsonData('project-form-containers-data');
    }

    function humanizeName(value) {
        var normalized = String(value || '').trim().replace(/[_-]+/g, ' ');
        normalized = normalized.replace(/\s+/g, ' ');
        if (!normalized) return '';
        return normalized.replace(/\b\w/g, function (char) {
            return char.toUpperCase();
        });
    }

    function truncateText(value, maxLength) {
        var text = String(value || '').trim();
        if (text.length <= maxLength) return text;
        return text.substring(0, Math.max(0, maxLength - 3)).trimEnd() + '...';
    }

    function getFieldTitle(config) {
        var label = String((config && (config.field_label || config.label)) || '').trim();
        if (!label) {
            label = humanizeName((config && config.field_name) || '');
        }
        return truncateText(label, FIELD_LABEL_MAX_LENGTH);
    }

    function getFieldTypeLabel(type) {
        var normalizedType = String(type || '').trim().toLowerCase();
        if (FIELD_TYPE_LABELS[normalizedType]) {
            return FIELD_TYPE_LABELS[normalizedType];
        }
        if (!normalizedType) return 'Text';
        return humanizeName(normalizedType);
    }

    function appendStatusIcon(container, iconClass, title, statusClass, active) {
        if (!container) return;
        var icon = document.createElement('i');
        icon.className = 'bi ' + iconClass + ' project-form-field-flag ' + statusClass + ' ' + (active ? 'is-active' : 'is-inactive');
        icon.setAttribute('title', title);
        icon.setAttribute('aria-label', title);
        container.appendChild(icon);
    }

    function updateRowStatusIcons(row, config) {
        var flagsNode = row ? row.querySelector('.project-form-field-flags') : null;
        if (!flagsNode) return;

        var showIfExpr = String(((config && (config.show_if || config.showIf)) || '')).trim();
        var calcExpr = String(((config && (config.calc_expr || config.calcExpr)) || '')).trim();
        var isRequired = normalizeBool(config && config.required);
        var visibility = (config && typeof config.visibility === 'object' && !Array.isArray(config.visibility)) ? config.visibility : {};
        var showInList = !Object.prototype.hasOwnProperty.call(visibility, 'list') || normalizeBool(visibility.list);
        var showInEdit = !Object.prototype.hasOwnProperty.call(visibility, 'edit') || normalizeBool(visibility.edit);

        flagsNode.innerHTML = '';
        appendStatusIcon(flagsNode, 'bi-asterisk', 'Required field', 'is-required', isRequired);
        appendStatusIcon(flagsNode, 'bi-card-list', 'Show in list', 'is-show-list', showInList);
        appendStatusIcon(flagsNode, 'bi-pencil-square', 'Show in edit', 'is-show-edit', showInEdit);
        appendStatusIcon(flagsNode, 'bi-calculator-fill', 'Calculated field', 'is-calculated', calcExpr !== '');
        appendStatusIcon(flagsNode, 'bi-eye', 'Has Show If condition', 'is-conditional', showIfExpr !== '');
        flagsNode.classList.remove('d-none');
    }

    function buildDefaultFieldConfig(fieldName) {
        var cleanName = String(fieldName || '').trim();
        return {
            field_name: cleanName,
            field_label: cleanName ? cleanName.replace(/[_-]+/g, ' ').replace(/\b\w/g, function (char) { return char.toUpperCase(); }) : '',
            type: 'string',
            _draft_minimal: true
        };
    }

    function normalizeFieldItem(item) {
        var field = {
            name: '',
            builder_locked: false,
            can_delete: true,
            config: null
        };

        if (item && typeof item === 'object') {
            field.name = String(item.name || '').trim();
            field.builder_locked = normalizeBool(item.builder_locked || item.builderLocked || false);
            if (Object.prototype.hasOwnProperty.call(item, 'can_delete')) {
                field.can_delete = normalizeBool(item.can_delete);
            } else if (Object.prototype.hasOwnProperty.call(item, 'canDelete')) {
                field.can_delete = normalizeBool(item.canDelete);
            }
            if (item.config && typeof item.config === 'object') {
                field.config = item.config;
            } else if (item.field_config && typeof item.field_config === 'object') {
                field.config = item.field_config;
            }
        } else {
            field.name = String(item || '').trim();
        }

        if (!field.config) {
            field.config = buildDefaultFieldConfig(field.name);
        }

        if (!field.config.field_name) {
            field.config.field_name = field.name;
        }

        return field;
    }

    function normalizeContainerCols(value, fallbackColumnCount) {
        var fallback = Math.max(1, Number(fallbackColumnCount) || 1);

        if (Array.isArray(value)) {
            var parsedCols = [];
            value.forEach(function (rawCol) {
                var col = parseInt(rawCol, 10);
                if (!isFinite(col) || col < 1) return;
                parsedCols.push(Math.min(12, col));
            });
            if (parsedCols.length > 0) {
                return parsedCols;
            }
        }

        var intCols = parseInt(value, 10);
        if (!isFinite(intCols) || intCols < 1) {
            intCols = fallback;
        }
        return Math.min(12, intCols);
    }

    function normalizeContainerFields(rawFields) {
        var normalized = [];
        var source = Array.isArray(rawFields) ? rawFields : [];
        var seen = {};

        source.forEach(function (entry) {
            if (Array.isArray(entry)) {
                var group = [];
                entry.forEach(function (groupEntry) {
                    if (Array.isArray(groupEntry)) return;
                    var name = String(groupEntry || '').trim();
                    if (!name) return;
                    var lower = name.toLowerCase();
                    if (seen[lower]) return;
                    seen[lower] = true;
                    group.push(name);
                });
                if (group.length === 1) {
                    normalized.push(group[0]);
                } else if (group.length > 1) {
                    normalized.push(group);
                }
                return;
            }

            var fieldName = String(entry || '').trim();
            if (!fieldName) return;
            var fieldLower = fieldName.toLowerCase();
            if (seen[fieldLower]) return;
            seen[fieldLower] = true;
            normalized.push(fieldName);
        });

        return normalized;
    }

    function getContainerFieldGroups(config) {
        var source = Array.isArray(config && config.fields) ? config.fields : [];
        var groups = [];

        source.forEach(function (entry) {
            if (Array.isArray(entry)) {
                var group = [];
                entry.forEach(function (groupEntry) {
                    var name = String(groupEntry || '').trim();
                    if (!name) return;
                    group.push(name);
                });
                if (group.length > 0) {
                    groups.push(group);
                }
                return;
            }

            var fieldName = String(entry || '').trim();
            if (!fieldName) return;
            groups.push([fieldName]);
        });

        return groups;
    }

    function flattenContainerFields(config) {
        var flat = [];
        getContainerFieldGroups(config).forEach(function (group) {
            group.forEach(function (fieldName) {
                flat.push(fieldName);
            });
        });
        return flat;
    }

    function normalizeContainerAttributes(value) {
        if (!value || typeof value !== 'object' || Array.isArray(value)) {
            return {};
        }

        var attributes = {};
        Object.keys(value).forEach(function (key) {
            var attrName = String(key || '').trim();
            if (!attrName) return;
            var attrValue = value[key];
            if (
                typeof attrValue === 'string'
                || typeof attrValue === 'number'
                || typeof attrValue === 'boolean'
            ) {
                attributes[attrName] = attrValue;
            }
        });

        return attributes;
    }

    function normalizeContainerItem(item, index) {
        var container = {
            id: '',
            fields: [],
            cols: 1,
            position_before: '',
            title: '',
            attributes: {}
        };

        if (!item || typeof item !== 'object') {
            return container;
        }

        var id = String(item.id || item.container_id || '').trim();
        if (id && CONTAINER_ID_PATTERN.test(id)) {
            container.id = id;
        } else if (id) {
            container.id = 'container_' + String(index + 1);
        }

        container.fields = normalizeContainerFields(item.fields);
        container.cols = normalizeContainerCols(item.cols, getContainerFieldGroups(container).length || 1);
        container.position_before = String(item.position_before || item.positionBefore || '').trim();
        container.title = String(item.title || '').trim();
        container.attributes = normalizeContainerAttributes(item.attributes);

        return container;
    }

    function readRowType(row) {
        return String((row && row.getAttribute('data-row-type')) || 'field').trim().toLowerCase();
    }

    function readRowConfig(row) {
        if (!row) return null;
        var raw = row.getAttribute('data-field-config') || '';
        if (!raw) return null;
        try {
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function writeRowConfig(row, config) {
        if (!row || !config || typeof config !== 'object') return;
        row.setAttribute('data-field-config', JSON.stringify(config));
    }

    function readContainerConfig(row) {
        if (!row) return null;
        var raw = row.getAttribute('data-container-config') || '';
        if (!raw) return null;
        try {
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? normalizeContainerItem(parsed, 0) : null;
        } catch (e) {
            return null;
        }
    }

    function writeContainerConfig(row, config) {
        if (!row || !config || typeof config !== 'object') return;
        row.setAttribute('data-container-config', JSON.stringify(config));
    }

    function updateRowVisualName(row, config) {
        if (!row || !config) return;
        var name = String(config.field_name || '').trim();
        var title = getFieldTitle(config);
        var typeLabel = getFieldTypeLabel(config.type || config.method);
        var titleEl = row.querySelector('.project-form-field-title');
        var nameEl = row.querySelector('.project-form-field-name');
        var typeEl = row.querySelector('.project-form-field-type');
        if (titleEl) titleEl.textContent = title || name;
        if (nameEl) nameEl.textContent = name;
        if (typeEl) typeEl.textContent = typeLabel;
        updateRowStatusIcons(row, config);
    }

    function containerColsToText(cols) {
        if (Array.isArray(cols)) {
            return cols.join(' / ');
        }
        var value = parseInt(cols, 10);
        if (!isFinite(value) || value < 1) value = 1;
        return String(value);
    }

    function updateContainerRowVisual(row, config) {
        if (!row || !config) return;

        var id = String(config.id || '').trim();
        var title = String(config.title || '').trim();
        var titleNode = row.querySelector('.project-form-container-title');
        var idNode = row.querySelector('.project-form-container-id');
        var colsNode = row.querySelector('.project-form-container-cols');

        if (titleNode) {
            titleNode.textContent = title !== '' ? title : ('Container ' + id);
        }
        if (idNode) {
            idNode.textContent = id;
        }
        if (colsNode) {
            colsNode.textContent = containerColsToText(config.cols);
        }
    }

    function findFieldRowByName(list, fieldName) {
        if (!list || !fieldName) return null;
        var nameLower = fieldName.toLowerCase();
        var rows = getFieldRows(list);
        for (var i = 0; i < rows.length; i++) {
            if (getFieldNameFromRow(rows[i]).toLowerCase() === nameLower) {
                return rows[i];
            }
        }
        return null;
    }

    function getExistingFieldNameSet(list) {
        var existing = {};
        getFieldRows(list).forEach(function (row) {
            var name = getFieldNameFromRow(row).toLowerCase();
            if (name) {
                existing[name] = true;
            }
        });
        return existing;
    }

    function stripFieldCopySuffix(name) {
        return String(name || '').trim().replace(/_copy\d*$/i, '');
    }

    function stripLabelCopySuffix(label) {
        return String(label || '').trim().replace(/\s+copy\d*$/i, '').trim();
    }

    function buildCloneFieldName(baseName, existingNameSet) {
        var cleanBase = stripFieldCopySuffix(baseName);
        if (!cleanBase) cleanBase = 'new_field';
        var existing = existingNameSet || {};

        var index = 1;
        while (index < 10000) {
            var suffix = index === 1 ? '_copy' : ('_copy' + index);
            var candidate = cleanBase + suffix;
            if (!existing[candidate.toLowerCase()]) {
                return { name: candidate, index: index, base: cleanBase };
            }
            index++;
        }

        return { name: cleanBase + '_copy' + Date.now(), index: index, base: cleanBase };
    }

    function getContainerColSize(config, columnIndex, columnsCount) {
        if (Array.isArray(config.cols) && columnIndex < config.cols.length) {
            var fromArray = parseInt(config.cols[columnIndex], 10);
            if (isFinite(fromArray) && fromArray >= 1) {
                return Math.min(12, fromArray);
            }
        }
        if (typeof config.cols === 'number' && config.cols >= 1) {
            var byCount = Math.floor(12 / Math.max(1, parseInt(config.cols, 10)));
            if (isFinite(byCount) && byCount >= 1) {
                return Math.min(12, byCount);
            }
        }
        var fallback = Math.floor(12 / Math.max(1, columnsCount || 1));
        if (!isFinite(fallback) || fallback < 1) fallback = 6;
        return Math.min(12, fallback);
    }

    function renderContainerFieldsGrid(containerRow, list, empty, sortable) {
        var config = readContainerConfig(containerRow);
        if (!config) return;

        var fieldsGrid = containerRow.querySelector('.project-form-container-fields-grid');
        if (!fieldsGrid) return;

        fieldsGrid.innerHTML = '';

        var columnGroups = getContainerFieldGroups(config);
        if (columnGroups.length === 0) {
            var emptyMsg = document.createElement('div');
            emptyMsg.className = 'text-muted py-1';
            emptyMsg.innerHTML = '<small><i class="bi bi-info-circle"></i> No fields in this container</small>';
            fieldsGrid.appendChild(emptyMsg);
            return;
        }

        var gridRow = document.createElement('div');
        gridRow.className = 'row g-2';

        for (var i = 0; i < columnGroups.length; i++) {
            var colSize = getContainerColSize(config, i, columnGroups.length);
            var col = document.createElement('div');
            col.className = 'col-' + colSize;

            var stack = document.createElement('div');
            stack.className = 'container-field-column-stack';

            var group = columnGroups[i];
            for (var g = 0; g < group.length; g++) {
                var fieldName = group[g];
                var fieldRow = findFieldRowByName(list, fieldName);
                var fieldConfig = fieldRow ? (readRowConfig(fieldRow) || {}) : {};
                var cellTitle = getFieldTitle(fieldConfig) || fieldName;
                var cellType = getFieldTypeLabel((fieldConfig && fieldConfig.type) || 'string');

                var cell = document.createElement('div');
                cell.className = 'container-field-cell d-flex align-items-start justify-content-between';
                if (g < group.length - 1) {
                    cell.classList.add('mb-2');
                }
                cell.setAttribute('data-field-name', fieldName);
                cell.setAttribute('data-field-config', JSON.stringify(fieldConfig));
                cell.setAttribute('data-builder-locked', fieldRow ? fieldRow.getAttribute('data-builder-locked') : '0');
                cell.setAttribute('data-can-delete', fieldRow ? fieldRow.getAttribute('data-can-delete') : '1');

                var cellContent = document.createElement('div');
                cellContent.className = 'container-field-cell-content';

                var cellTitleEl = document.createElement('div');
                cellTitleEl.className = 'container-field-cell-title';
                cellTitleEl.textContent = cellTitle;
                cellContent.appendChild(cellTitleEl);

                var cellMeta = document.createElement('div');
                cellMeta.className = 'container-field-cell-meta text-muted';
                cellMeta.innerHTML = '<code>' + fieldName + '</code> - ' + cellType;
                cellContent.appendChild(cellMeta);

                cell.appendChild(cellContent);

                var cellActions = document.createElement('div');
                cellActions.className = 'd-flex align-items-center gap-1 container-field-cell-actions';
                cellActions.innerHTML =
                    buildIconButton('btn-outline-secondary edit-container-field', 'bi-pencil-square', 'Edit', false)
                    + buildIconButton('btn-outline-danger delete-container-field', 'bi-trash', 'Delete', false);
                if (!existingTableLocked) {
                    cellActions.innerHTML += buildIconButton('btn-outline-primary clone-container-field', 'bi-copy', 'Clone', false);
                }
                cell.appendChild(cellActions);

                stack.appendChild(cell);
            }

            col.appendChild(stack);
            gridRow.appendChild(col);
        }

        fieldsGrid.appendChild(gridRow);
        wireContainerFieldCellActions(containerRow, list, empty, sortable);
    }

    function wireContainerFieldCellActions(containerRow, list, empty, sortable) {
        var cells = containerRow.querySelectorAll('.container-field-cell');

        for (var i = 0; i < cells.length; i++) {
            (function (cell) {
                var fieldName = cell.getAttribute('data-field-name');
                if (!fieldName) return;

                var editBtn = cell.querySelector('.edit-container-field');
                var deleteBtn = cell.querySelector('.delete-container-field');
                var cloneBtn = cell.querySelector('.clone-container-field');

                if (editBtn) {
                    editBtn.addEventListener('click', function () {
                        var currentConfig = readRowConfig(cell) || buildDefaultFieldConfig(fieldName);
                        var modelDefined = cell.getAttribute('data-can-delete') === '0';
                        openFieldModal({
                            mode: 'edit',
                            field: currentConfig,
                            model_defined: modelDefined,
                            onSubmit: function (updatedConfig) {
                                writeRowConfig(cell, updatedConfig);
                                var hiddenRow = findFieldRowByName(list, fieldName);
                                if (hiddenRow) {
                                    writeRowConfig(hiddenRow, updatedConfig);
                                    updateRowVisualName(hiddenRow, updatedConfig);
                                }
                                renderContainerFieldsGrid(containerRow, list, empty, sortable);
                            }
                        }, function () {
                            // no fallback for in-container fields
                        });
                    });
                }

                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function () {
                        var containerConfig = readContainerConfig(containerRow);
                        if (containerConfig) {
                            var fieldLower = fieldName.toLowerCase();
                            var nextFields = [];
                            var removed = false;

                            (Array.isArray(containerConfig.fields) ? containerConfig.fields : []).forEach(function (entry, columnIndex) {
                                if (Array.isArray(entry)) {
                                    var filtered = entry.filter(function (name) {
                                        return String(name || '').trim().toLowerCase() !== fieldLower;
                                    });
                                    if (filtered.length !== entry.length) {
                                        removed = true;
                                    }
                                    if (filtered.length > 1) {
                                        nextFields.push(filtered);
                                    } else if (filtered.length === 1) {
                                        nextFields.push(filtered[0]);
                                    } else if (Array.isArray(containerConfig.cols) && columnIndex < containerConfig.cols.length) {
                                        containerConfig.cols.splice(columnIndex, 1);
                                    }
                                    return;
                                }

                                var scalarName = String(entry || '').trim();
                                if (scalarName.toLowerCase() === fieldLower) {
                                    removed = true;
                                    if (Array.isArray(containerConfig.cols) && columnIndex < containerConfig.cols.length) {
                                        containerConfig.cols.splice(columnIndex, 1);
                                    }
                                    return;
                                }
                                nextFields.push(scalarName);
                            });

                            if (removed) {
                                containerConfig.fields = nextFields;
                            }
                            writeContainerConfig(containerRow, containerConfig);
                            updateContainerRowVisual(containerRow, containerConfig);
                        }

                        var fr = findFieldRowByName(list, fieldName);
                        if (fr) fr.remove();

                        if (containerConfig && flattenContainerFields(containerConfig).length === 0) {
                            containerRow.remove();
                        } else {
                            renderContainerFieldsGrid(containerRow, list, empty, sortable);
                        }

                        updateContainerizedFieldVisibility(list);
                        refreshEmptyState(list, empty);
                    });
                }

                if (cloneBtn) {
                    cloneBtn.addEventListener('click', function () {
                        var originalConfig = readRowConfig(cell) || buildDefaultFieldConfig(fieldName);
                        var clonedConfig = cloneFieldConfig(originalConfig, list);
                        var cloneRow = buildFieldRow({
                            name: clonedConfig.field_name,
                            builder_locked: false,
                            can_delete: true,
                            config: clonedConfig
                        });
                        wireRowActions(cloneRow, list, empty, sortable);
                        appendRow(list, cloneRow, sortable);
                        refreshEmptyState(list, empty);
                    });
                }
            })(cells[i]);
        }
    }

    function renderAllContainerGrids(list, empty, sortable) {
        getContainerRows(list).forEach(function (containerRow) {
            renderContainerFieldsGrid(containerRow, list, empty, sortable);
        });
    }

    function getFieldNameFromRow(row) {
        if (!row || readRowType(row) !== 'field') return '';
        var config = readRowConfig(row);
        var name = String((config && config.field_name) || '').trim();
        if (name !== '') return name;

        var nameNode = row.querySelector('.project-form-field-name');
        return nameNode ? String(nameNode.textContent || '').trim() : '';
    }

    function getFieldRows(list) {
        if (!list) return [];
        return Array.prototype.slice.call(list.querySelectorAll('.project-form-field-row[data-row-type="field"]'));
    }

    function getContainerRows(list) {
        if (!list) return [];
        return Array.prototype.slice.call(list.querySelectorAll('.project-form-field-row[data-row-type="container"]'));
    }

    function refreshEmptyState(list, empty) {
        if (!list || !empty) return;
        var visibleRows = list.querySelectorAll('.project-form-field-row:not(.d-none)');
        if (visibleRows.length) empty.classList.add('d-none');
        else empty.classList.remove('d-none');
    }

    function buildIconButton(buttonClass, iconClass, title, disabled) {
        return '<button type="button" class="btn btn-sm ' + buttonClass + ' project-form-icon-btn" title="' + title + '" aria-label="' + title + '"' + (disabled ? ' disabled aria-disabled="true"' : '') + '>'
            + '<i class="bi ' + iconClass + '"></i>'
            + '</button>';
    }

    function buildFieldRow(field) {
        field = field || {};
        var row = document.createElement('div');
        row.className = 'list-group-item d-flex align-items-start justify-content-between project-form-field-row project-form-row-padded';
        row.setAttribute('data-row-type', 'field');
        row.setAttribute('data-builder-locked', field.builder_locked ? '1' : '0');
        row.setAttribute('data-can-delete', field.can_delete ? '1' : '0');
        row.setAttribute('data-in-container', '0');
        writeRowConfig(row, field.config || buildDefaultFieldConfig(field.name));

        var left = document.createElement('div');
        left.className = 'd-flex align-items-start gap-2 flex-grow-1 me-2 project-form-field-main';

        var handle = document.createElement('span');
        if (field.builder_locked) {
            handle.className = 'text-muted bi bi-lock-fill';
            handle.setAttribute('title', 'Locked');
        } else {
            handle.className = 'project-form-field-handle bi bi-list';
            handle.setAttribute('title', 'Drag to reorder');
        }
        left.appendChild(handle);

        var content = document.createElement('div');
        content.className = 'project-form-field-content';

        var header = document.createElement('div');
        header.className = 'project-form-field-header';

        var flagsNode = document.createElement('div');
        flagsNode.className = 'project-form-field-flags d-none';
        header.appendChild(flagsNode);

        var titleNode = document.createElement('div');
        titleNode.className = 'project-form-field-title';
        header.appendChild(titleNode);

        content.appendChild(header);

        var meta = document.createElement('div');
        meta.className = 'project-form-field-meta';
        var nameNode = document.createElement('code');
        nameNode.className = 'project-form-field-name';
        var separator = document.createElement('span');
        separator.className = 'project-form-field-meta-separator';
        separator.textContent = ' - ';
        var typeNode = document.createElement('small');
        typeNode.className = 'project-form-field-type text-muted';
        meta.appendChild(nameNode);
        meta.appendChild(separator);
        meta.appendChild(typeNode);
        content.appendChild(meta);

        left.appendChild(content);
        row.appendChild(left);

        var right = document.createElement('div');
        right.className = 'd-flex align-items-center gap-1 project-form-field-actions';
        var isDisabled = !!field.builder_locked;
        var disabledAttr = isDisabled ? ' disabled aria-disabled="true"' : '';
        var html = '';
        html += '<button type="button" class="btn btn-sm btn-outline-secondary edit-field"' + disabledAttr + '><i class="bi bi-pencil-square"></i> Edit</button>';
        if (field.can_delete) {
            html += '<button type="button" class="btn btn-sm btn-outline-danger delete-field"' + disabledAttr + '><i class="bi bi-trash"></i> Delete</button>';
        }
        if (!existingTableLocked) {
            html += '<button type="button" class="btn btn-sm btn-outline-primary clone-field"' + disabledAttr + '><i class="bi bi-copy"></i> Clone</button>';
        }
        right.innerHTML = html;
        row.appendChild(right);

        updateRowVisualName(row, readRowConfig(row) || field.config || buildDefaultFieldConfig(field.name));
        return row;
    }

    function buildContainerRow(containerConfig) {
        var config = normalizeContainerItem(containerConfig || {}, 0);
        var row = document.createElement('div');
        row.className = 'list-group-item project-form-field-row project-form-container-row project-form-row-padded';
        row.setAttribute('data-row-type', 'container');
        row.setAttribute('data-builder-locked', '0');
        row.setAttribute('data-can-delete', '1');
        writeContainerConfig(row, config);

        var header = document.createElement('div');
        header.className = 'd-flex align-items-start justify-content-between project-form-container-header';

        var headerLeft = document.createElement('div');
        headerLeft.className = 'd-flex align-items-start gap-2 flex-grow-1 me-2';

        var handle = document.createElement('span');
        handle.className = 'project-form-field-handle bi bi-list';
        handle.setAttribute('title', 'Drag to reorder');
        headerLeft.appendChild(handle);

        var headerContent = document.createElement('div');
        headerContent.className = 'project-form-field-content';

        var titleNode = document.createElement('div');
        titleNode.className = 'project-form-container-title';
        headerContent.appendChild(titleNode);

        var meta = document.createElement('div');
        meta.className = 'project-form-container-meta text-muted';
        meta.innerHTML = 'ID: <code class="project-form-container-id"></code> - Cols: <span class="project-form-container-cols"></span>';
        headerContent.appendChild(meta);

        headerLeft.appendChild(headerContent);
        header.appendChild(headerLeft);

        var headerRight = document.createElement('div');
        headerRight.className = 'd-flex align-items-center gap-1 project-form-field-actions';
        headerRight.innerHTML =
            buildIconButton('btn-outline-secondary edit-container', 'bi-pencil-square', 'Edit container', false)
            + buildIconButton('btn-outline-danger delete-container', 'bi-trash', 'Delete container', false);
        header.appendChild(headerRight);

        row.appendChild(header);

        var fieldsGrid = document.createElement('div');
        fieldsGrid.className = 'project-form-container-fields-grid';
        row.appendChild(fieldsGrid);

        updateContainerRowVisual(row, config);
        return row;
    }

    function initSortableList(list) {
        if (typeof ItoSortableList === 'undefined') return null;
        return new ItoSortableList(list, {
            handleSelector: '.project-form-field-handle',
            onUpdate: function () {
                // UI reorder only.
            }
        });
    }

    function makeRowDraggable(sortable, row) {
        if (sortable && typeof sortable.makeDraggable === 'function') {
            sortable.makeDraggable(row);
        }
    }

    function appendRow(list, row, sortable) {
        list.appendChild(row);
        makeRowDraggable(sortable, row);
    }

    function appendContainerRowByPosition(list, row, config, sortable) {
        var positionBefore = String((config && config.position_before) || '').trim().toLowerCase();
        if (positionBefore === '') {
            appendRow(list, row, sortable);
            return;
        }

        var fieldRows = getFieldRows(list);
        for (var i = 0; i < fieldRows.length; i++) {
            var fieldName = getFieldNameFromRow(fieldRows[i]).toLowerCase();
            if (fieldName === positionBefore) {
                list.insertBefore(row, fieldRows[i]);
                makeRowDraggable(sortable, row);
                return;
            }
        }

        appendRow(list, row, sortable);
    }

    function insertRowAfter(list, targetRow, row, sortable) {
        if (targetRow && targetRow.nextSibling) {
            list.insertBefore(row, targetRow.nextSibling);
        } else {
            list.appendChild(row);
        }
        makeRowDraggable(sortable, row);
    }

    function isRowBuilderLocked(row) {
        return row && row.getAttribute('data-builder-locked') === '1';
    }

    function isRowDefinedInModel(row) {
        return row && row.getAttribute('data-can-delete') === '0';
    }

    function collectDraftFields(list) {
        var draftFields = [];

        getFieldRows(list).forEach(function (row) {
            var config = readRowConfig(row) || {};
            var name = String(config.field_name || '').trim();
            if (!name) {
                name = getFieldNameFromRow(row);
            }
            if (!name) {
                return;
            }

            config.field_name = name;
            if (!config.field_label) {
                config.field_label = name.replace(/[_-]+/g, ' ').replace(/\b\w/g, function (char) {
                    return char.toUpperCase();
                });
            }

            draftFields.push({
                name: name,
                builder_locked: isRowBuilderLocked(row),
                can_delete: row.getAttribute('data-can-delete') !== '0',
                config: config
            });
        });

        return draftFields;
    }

    function resolveContainerPositionBefore(row) {
        var next = row ? row.nextElementSibling : null;
        while (next) {
            if (next.classList.contains('project-form-field-row') && readRowType(next) === 'field' && next.getAttribute('data-in-container') !== '1') {
                var nextFieldName = getFieldNameFromRow(next);
                if (nextFieldName !== '') {
                    return nextFieldName;
                }
            }
            next = next.nextElementSibling;
        }
        return '';
    }

    function collectDraftContainers(list) {
        var draftContainers = [];

        getContainerRows(list).forEach(function (row) {
            var config = readContainerConfig(row);
            if (!config) return;
            var containerId = String(config.id || '').trim();
            if (!containerId) return;
            if (flattenContainerFields(config).length === 0) return;

            var normalized = normalizeContainerItem(config, draftContainers.length);
            normalized.position_before = resolveContainerPositionBefore(row);
            draftContainers.push(normalized);
        });

        return draftContainers;
    }

    function cloneFieldConfig(sourceConfig, list) {
        var source = (sourceConfig && typeof sourceConfig === 'object') ? sourceConfig : {};
        var clone = JSON.parse(JSON.stringify(source));
        var cloneNameInfo = buildCloneFieldName(clone.field_name, getExistingFieldNameSet(list));
        clone.field_name = cloneNameInfo.name;

        var labelBase = stripLabelCopySuffix(clone.field_label);
        if (!labelBase) {
            labelBase = humanizeName(cloneNameInfo.base);
        }

        if (labelBase) {
            clone.field_label = labelBase + ' Copy' + (cloneNameInfo.index > 1 ? String(cloneNameInfo.index) : '');
        } else {
            clone.field_label = humanizeName(clone.field_name);
        }
        return clone;
    }

    function getContainerIdSet(list, excludeRow) {
        var idSet = {};
        getContainerRows(list).forEach(function (row) {
            if (excludeRow && row === excludeRow) return;
            var config = readContainerConfig(row);
            var id = String((config && config.id) || '').trim().toLowerCase();
            if (id) {
                idSet[id] = true;
            }
        });
        return idSet;
    }

    function buildUniqueContainerId(list, baseId, excludeRow) {
        var cleanBase = String(baseId || '').trim();
        if (!cleanBase || !CONTAINER_ID_PATTERN.test(cleanBase)) {
            cleanBase = 'container';
        }
        var existing = getContainerIdSet(list, excludeRow);
        if (!existing[cleanBase.toLowerCase()]) {
            return cleanBase;
        }

        var index = 1;
        while (index < 10000) {
            var candidate = cleanBase + '_' + index;
            if (!existing[candidate.toLowerCase()]) {
                return candidate;
            }
            index++;
        }

        return cleanBase + '_' + Date.now();
    }

    function suggestContainerId(list) {
        return buildUniqueContainerId(list, 'container');
    }

    function getAvailableFieldNamesForContainer(list, currentRow) {
        var usedByOthers = {};
        getContainerRows(list).forEach(function (containerRow) {
            if (currentRow && containerRow === currentRow) return;
            var config = readContainerConfig(containerRow);
            if (!config) return;
            flattenContainerFields(config).forEach(function (fieldName) {
                var key = String(fieldName || '').trim().toLowerCase();
                if (key) usedByOthers[key] = true;
            });
        });

        var available = [];
        getFieldRows(list).forEach(function (fieldRow) {
            var fieldName = getFieldNameFromRow(fieldRow);
            var key = fieldName.toLowerCase();
            if (!fieldName || usedByOthers[key]) {
                return;
            }
            available.push(fieldName);
        });

        return available;
    }

    function openContainerModal(modalMode, seed, list, currentRow, onSubmit) {
        var modalApi = window.containerBuilderModal;
        if (!modalApi) {
            notify('Container builder modal not available.', 'danger');
            return;
        }

        var availableFields = getAvailableFieldNamesForContainer(list, currentRow);
        if (availableFields.length === 0 && (!seed.fields || seed.fields.length === 0)) {
            notify('No available fields to include in a container.', 'warning');
            return;
        }

        var normalizedSeed = normalizeContainerItem(seed || {}, 0);
        if (!normalizedSeed.id) {
            normalizedSeed.id = suggestContainerId(list);
        }

        var payload = {
            seed: normalizedSeed,
            availableFields: availableFields,
            onSubmit: function (config) {
                var normalizedConfig = normalizeContainerItem(config || {}, 0);
                normalizedConfig.id = buildUniqueContainerId(list, normalizedConfig.id, currentRow);
                if (typeof onSubmit === 'function') {
                    onSubmit(normalizedConfig);
                }
            }
        };

        if (modalMode === 'edit' && typeof modalApi.openEdit === 'function') {
            modalApi.openEdit(payload);
        } else if (typeof modalApi.openCreate === 'function') {
            modalApi.openCreate(payload);
        }
    }

    function openFieldModal(payload, fallback) {
        var modalApi = window.projectFieldBuilderModal;
        if (!modalApi) {
            fallback();
            return;
        }
        if (payload.mode === 'edit' && typeof modalApi.openEdit === 'function') {
            modalApi.openEdit(payload);
            return;
        }
        if (payload.mode === 'create' && typeof modalApi.openCreate === 'function') {
            modalApi.openCreate(payload);
            return;
        }
        fallback();
    }

    function updateContainerizedFieldVisibility(list) {
        var inContainer = {};

        getContainerRows(list).forEach(function (containerRow) {
            var config = readContainerConfig(containerRow);
            if (!config) return;
            flattenContainerFields(config).forEach(function (fieldName) {
                var key = String(fieldName || '').trim().toLowerCase();
                if (!key) return;
                if (!Object.prototype.hasOwnProperty.call(inContainer, key)) {
                    inContainer[key] = String(config.id || '');
                }
            });
        });

        getFieldRows(list).forEach(function (fieldRow) {
            var fieldName = getFieldNameFromRow(fieldRow);
            var key = fieldName.toLowerCase();
            if (key && Object.prototype.hasOwnProperty.call(inContainer, key)) {
                fieldRow.classList.add('d-none');
                fieldRow.setAttribute('data-in-container', '1');
                fieldRow.setAttribute('data-in-container-id', inContainer[key]);
            } else {
                fieldRow.classList.remove('d-none');
                fieldRow.setAttribute('data-in-container', '0');
                fieldRow.removeAttribute('data-in-container-id');
            }
        });
    }

    function wireRowActions(row, list, empty, sortable) {
        var editBtn = row.querySelector('.edit-field');
        var deleteBtn = row.querySelector('.delete-field');
        var cloneBtn = row.querySelector('.clone-field');
        var builderLocked = isRowBuilderLocked(row);

        if (!builderLocked && editBtn) {
            editBtn.addEventListener('click', function () {
                var currentConfig = readRowConfig(row) || buildDefaultFieldConfig('');
                openFieldModal({
                    mode: 'edit',
                    field: currentConfig,
                    model_defined: isRowDefinedInModel(row),
                    onSubmit: function (updatedConfig) {
                        writeRowConfig(row, updatedConfig);
                        updateRowVisualName(row, updatedConfig);
                    }
                }, function () {
                    var fallbackName = prompt('Field name', String(currentConfig.field_name || '').trim());
                    if (fallbackName === null) return;
                    fallbackName = String(fallbackName).trim();
                    if (!fallbackName) {
                        notify('Please provide a valid field name.', 'danger');
                        return;
                    }
                    currentConfig.field_name = fallbackName;
                    writeRowConfig(row, currentConfig);
                    updateRowVisualName(row, currentConfig);
                });
            });
        }

        if (!builderLocked && deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                row.remove();
                refreshEmptyState(list, empty);
            });
        }

        if (!builderLocked && cloneBtn) {
            cloneBtn.addEventListener('click', function () {
                var originalConfig = readRowConfig(row) || buildDefaultFieldConfig('');
                var clonedConfig = cloneFieldConfig(originalConfig, list);
                var cloneRow = buildFieldRow({
                    name: clonedConfig.field_name,
                    builder_locked: false,
                    can_delete: true,
                    config: clonedConfig
                });
                wireRowActions(cloneRow, list, empty, sortable);
                insertRowAfter(list, row, cloneRow, sortable);
                refreshEmptyState(list, empty);
            });
        }
    }

    function wireContainerRowActions(row, list, empty, sortable) {
        var editBtn = row.querySelector('.edit-container');
        var deleteBtn = row.querySelector('.delete-container');

        if (editBtn) {
            editBtn.addEventListener('click', function () {
                var currentConfig = readContainerConfig(row);
                if (!currentConfig) return;

                openContainerModal('edit', currentConfig, list, row, function (updated) {
                    writeContainerConfig(row, updated);
                    updateContainerRowVisual(row, updated);
                    updateContainerizedFieldVisibility(list);
                    renderContainerFieldsGrid(row, list, empty, sortable);
                    refreshEmptyState(list, empty);
                });
            });
        }

        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                row.remove();
                updateContainerizedFieldVisibility(list);
                refreshEmptyState(list, empty);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('project-build-form-fields-page');
        if (!root) return;
        if ((root.getAttribute('data-can-edit') || '') !== '1') return;
        existingTableLocked = normalizeBool(root.getAttribute('data-existing-table-locked') || '0');

        var moduleName = root.getAttribute('data-module') || '';
        var ref = root.getAttribute('data-ref') || '';
        var saveFieldsDraftUrl = root.getAttribute('data-save-fields-draft-url') || '';

        var list = document.getElementById('project-form-fields-list');
        var empty = document.getElementById('project-form-fields-empty');
        var addFieldBtn = document.getElementById('project-form-add-field');
        var addContainerBtn = document.getElementById('project-form-add-container');
        var savePreviewBtn = document.getElementById('project-form-fields-save-preview');
        if (!list || !empty || !addContainerBtn || !savePreviewBtn) return;

        if (existingTableLocked && addFieldBtn) {
            addFieldBtn.classList.add('d-none');
            addFieldBtn.setAttribute('disabled', 'disabled');
            addFieldBtn.setAttribute('aria-disabled', 'true');
        }

        var sortable = initSortableList(list);
        var fields = safeParseFields();
        var containers = safeParseContainers();

        var normalizedFields = [];
        for (var i = 0; i < fields.length; i++) {
            var normalized = normalizeFieldItem(fields[i]);
            if (!normalized.name) continue;
            if (normalized.builder_locked) continue;
            normalizedFields.push(normalized);
        }

        for (var j = 0; j < normalizedFields.length; j++) {
            var fieldRow = buildFieldRow(normalizedFields[j]);
            wireRowActions(fieldRow, list, empty, sortable);
            appendRow(list, fieldRow, sortable);
        }

        for (var c = 0; c < containers.length; c++) {
            var containerConfig = normalizeContainerItem(containers[c], c);
            if (!containerConfig.id || !containerConfig.fields.length) {
                continue;
            }
            var containerRow = buildContainerRow(containerConfig);
            wireContainerRowActions(containerRow, list, empty, sortable);
            appendContainerRowByPosition(list, containerRow, containerConfig, sortable);
        }

        updateContainerizedFieldVisibility(list);
        renderAllContainerGrids(list, empty, sortable);
        refreshEmptyState(list, empty);

        if (addFieldBtn && !existingTableLocked) {
            addFieldBtn.addEventListener('click', function () {
                openFieldModal({
                    mode: 'create',
                    field: {},
                    onSubmit: function (newConfig) {
                        var fieldName = String((newConfig && newConfig.field_name) || '').trim();
                        if (!fieldName) {
                            notify('Invalid field configuration.', 'danger');
                            return;
                        }
                        var row = buildFieldRow({
                            name: fieldName,
                            builder_locked: false,
                            can_delete: true,
                            config: newConfig
                        });
                        wireRowActions(row, list, empty, sortable);
                        appendRow(list, row, sortable);
                        refreshEmptyState(list, empty);
                    }
                }, function () {
                    var fallbackName = prompt('New field name');
                    if (fallbackName === null) return;
                    fallbackName = String(fallbackName).trim();
                    if (!fallbackName) {
                        notify('Please provide a valid field name.', 'danger');
                        return;
                    }
                    var row = buildFieldRow({
                        name: fallbackName,
                        builder_locked: false,
                        can_delete: true,
                        config: buildDefaultFieldConfig(fallbackName)
                    });
                    wireRowActions(row, list, empty, sortable);
                    appendRow(list, row, sortable);
                    refreshEmptyState(list, empty);
                });
            });
        }

        addContainerBtn.addEventListener('click', function () {
            var seed = {
                id: suggestContainerId(list),
                fields: [],
                cols: [],
                position_before: '',
                title: '',
                attributes: { 'class': 'mb-3' }
            };

            openContainerModal('create', seed, list, null, function (newConfig) {
                var row = buildContainerRow(newConfig);
                wireContainerRowActions(row, list, empty, sortable);
                appendRow(list, row, sortable);
                updateContainerizedFieldVisibility(list);
                renderContainerFieldsGrid(row, list, empty, sortable);
                refreshEmptyState(list, empty);
            });
        });

        savePreviewBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (savePreviewBtn.classList.contains('disabled')) {
                return;
            }
            if (!moduleName || !ref || !saveFieldsDraftUrl) {
                notify('Missing parameters for save.', 'danger');
                return;
            }

            var payload = {
                module: moduleName,
                ref: ref,
                fields: collectDraftFields(list),
                containers: collectDraftContainers(list)
            };

            savePreviewBtn.classList.add('disabled');
            savePreviewBtn.setAttribute('aria-disabled', 'true');

            fetch(saveFieldsDraftUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            })
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function (data) {
                    if (data && data.success && data.redirect_url) {
                        window.location.href = String(data.redirect_url);
                        return;
                    }
                    notify((data && data.msg) ? data.msg : 'Unable to prepare draft review.', 'danger');
                })
                .catch(function (err) {
                    notify('Save error: ' + err.message, 'danger');
                })
                .finally(function () {
                    savePreviewBtn.classList.remove('disabled');
                    savePreviewBtn.removeAttribute('aria-disabled');
                });
        });
    });
})();
