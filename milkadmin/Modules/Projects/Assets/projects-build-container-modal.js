(function () {
    'use strict';

    var VALID_WIDTHS = [2, 3, 4, 5, 6, 7, 8, 9, 10, 12];
    var MAX_COLS = 4;
    var TARGET_WIDTH = 12;
    var SORT_GROUP = 'projects-container-builder-group';

    var initialized = false;
    var modalInstance = null;
    var submitHandler = null;
    var mode = 'create';

    var availableFields = [];
    var columns = [];
    var uid = 0;
    var sortInstances = {};
    var poolSortable = null;
    var seedAttributes = { 'class': 'mb-3' };
    var seedPositionBefore = '';

    function el(id) {
        return document.getElementById(id);
    }

    function safeTrim(value) {
        return String(value || '').trim();
    }

    function toInt(value, fallback) {
        var parsed = parseInt(value, 10);
        if (!isFinite(parsed)) return fallback;
        return parsed;
    }

    function normalizeAvailableFields(rawFields) {
        var list = [];
        var seen = {};
        (Array.isArray(rawFields) ? rawFields : []).forEach(function (rawField) {
            var fieldName = safeTrim(rawField);
            if (!fieldName) return;
            var lower = fieldName.toLowerCase();
            if (seen[lower]) return;
            seen[lower] = true;
            list.push(fieldName);
        });
        return list;
    }

    function normalizeAttributes(rawAttributes) {
        var attributes = {};
        if (!rawAttributes || typeof rawAttributes !== 'object' || Array.isArray(rawAttributes)) {
            return attributes;
        }

        Object.keys(rawAttributes).forEach(function (key) {
            var attrName = safeTrim(key);
            if (!attrName) return;
            var attrValue = rawAttributes[key];
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

    function snapValidWidth(value) {
        var numeric = toInt(value, 6);
        if (numeric <= VALID_WIDTHS[0]) return VALID_WIDTHS[0];
        if (numeric >= VALID_WIDTHS[VALID_WIDTHS.length - 1]) return VALID_WIDTHS[VALID_WIDTHS.length - 1];
        if (VALID_WIDTHS.indexOf(numeric) >= 0) return numeric;

        var nearest = VALID_WIDTHS[0];
        for (var i = 1; i < VALID_WIDTHS.length; i++) {
            if (Math.abs(VALID_WIDTHS[i] - numeric) < Math.abs(nearest - numeric)) {
                nearest = VALID_WIDTHS[i];
            }
        }
        return nearest;
    }

    function nextColumnId() {
        uid += 1;
        return 'pcb_col_' + String(uid);
    }

    function createColumn(fields, width) {
        return {
            id: nextColumnId(),
            width: snapValidWidth(width),
            fields: Array.isArray(fields) ? fields.slice() : []
        };
    }

    function clearCanvasError() {
        var canvasError = el('canvasError');
        if (!canvasError) return;
        canvasError.style.display = 'none';
        canvasError.textContent = '';
    }

    function showCanvasError(message) {
        var canvasError = el('canvasError');
        if (!canvasError) return;
        canvasError.textContent = safeTrim(message);
        canvasError.style.display = '';
    }

    function setModeUi() {
        var titleEl = el('containerBuilderModalTitle');
        var submitLabelEl = el('containerBuilderSubmitLabel');

        if (mode === 'edit') {
            if (titleEl) {
                titleEl.innerHTML = '<i class="bi bi-layout-three-columns me-2 text-primary"></i>Edit Column Stack';
            }
            if (submitLabelEl) {
                submitLabelEl.textContent = 'Update container';
            }
            return;
        }

        if (titleEl) {
            titleEl.innerHTML = '<i class="bi bi-layout-three-columns me-2 text-primary"></i>Create Column Stack';
        }
        if (submitLabelEl) {
            submitLabelEl.textContent = 'Create container';
        }
    }

    function flattenSeedFieldNames(rawFields) {
        var flat = [];
        (Array.isArray(rawFields) ? rawFields : []).forEach(function (entry) {
            if (Array.isArray(entry)) {
                entry.forEach(function (groupEntry) {
                    if (Array.isArray(groupEntry)) return;
                    var name = safeTrim(groupEntry);
                    if (name) flat.push(name);
                });
                return;
            }
            var scalar = safeTrim(entry);
            if (scalar) flat.push(scalar);
        });
        return flat;
    }

    function parseSeedGroups(rawFields, availableMap) {
        var groups = [];
        var seen = {};

        (Array.isArray(rawFields) ? rawFields : []).forEach(function (entry) {
            if (Array.isArray(entry)) {
                var group = [];
                entry.forEach(function (groupEntry) {
                    if (Array.isArray(groupEntry)) return;
                    var rawName = safeTrim(groupEntry);
                    if (!rawName) return;
                    var canonical = availableMap[rawName.toLowerCase()] || rawName;
                    var lower = canonical.toLowerCase();
                    if (seen[lower]) return;
                    seen[lower] = true;
                    group.push(canonical);
                });
                if (group.length > 0) {
                    groups.push(group);
                }
                return;
            }

            var rawField = safeTrim(entry);
            if (!rawField) return;
            var fieldName = availableMap[rawField.toLowerCase()] || rawField;
            var fieldLower = fieldName.toLowerCase();
            if (seen[fieldLower]) return;
            seen[fieldLower] = true;
            groups.push([fieldName]);
        });

        return groups;
    }

    function limitGroups(groups, widths) {
        if (groups.length <= MAX_COLS) {
            return { groups: groups, widths: widths };
        }

        var limitedGroups = groups.slice(0, MAX_COLS);
        for (var i = MAX_COLS; i < groups.length; i++) {
            limitedGroups[MAX_COLS - 1] = limitedGroups[MAX_COLS - 1].concat(groups[i]);
        }

        var limitedWidths = Array.isArray(widths) ? widths.slice(0, MAX_COLS) : [];
        return { groups: limitedGroups, widths: limitedWidths };
    }

    function buildSeedWidths(seedCols, count) {
        var targetCount = Math.max(1, toInt(count, 1));
        var widths = [];

        if (Array.isArray(seedCols)) {
            seedCols.forEach(function (rawCol) {
                var col = toInt(rawCol, 0);
                if (col < 1) return;
                widths.push(snapValidWidth(Math.min(12, col)));
            });
        }

        if (widths.length < targetCount) {
            var fallbackWidth = 6;
            var colsHint = toInt(seedCols, 0);
            if (colsHint > 0) {
                fallbackWidth = Math.max(1, Math.floor(TARGET_WIDTH / colsHint));
            } else {
                fallbackWidth = Math.max(1, Math.floor(TARGET_WIDTH / targetCount));
            }
            fallbackWidth = snapValidWidth(fallbackWidth);

            while (widths.length < targetCount) {
                widths.push(fallbackWidth);
            }
        }

        return widths.slice(0, targetCount);
    }

    function getWidthTotal() {
        var total = 0;
        columns.forEach(function (col) {
            total += toInt(col.width, 0);
        });
        return total;
    }

    function renderBadge() {
        var widthTotal = el('widthTotal');
        if (!widthTotal) return;

        var total = getWidthTotal();
        widthTotal.textContent = String(total) + '/12';
        widthTotal.className = 'width-total';
        if (total === TARGET_WIDTH) {
            widthTotal.classList.add('ok');
        } else if (total > TARGET_WIDTH) {
            widthTotal.classList.add('over');
        } else {
            widthTotal.classList.add('under');
        }
    }

    function usedFieldMap() {
        var used = {};
        columns.forEach(function (col) {
            (Array.isArray(col.fields) ? col.fields : []).forEach(function (fieldName) {
                var key = safeTrim(fieldName).toLowerCase();
                if (key) used[key] = true;
            });
        });
        return used;
    }

    function renderPool() {
        var pool = el('fieldsPool');
        var emptyAlert = el('containerFieldsEmpty');
        if (!pool) return;

        var used = usedFieldMap();
        pool.innerHTML = '';

        availableFields.forEach(function (fieldName) {
            var key = fieldName.toLowerCase();
            if (used[key]) return;

            var chip = document.createElement('div');
            chip.className = 'pool-chip';
            chip.setAttribute('data-field', fieldName);
            chip.innerHTML = '<i class="bi bi-grip-vertical drag-handle"></i><span>' + fieldName + '</span>';
            pool.appendChild(chip);
        });

        if (emptyAlert) {
            if (pool.children.length === 0) {
                emptyAlert.classList.remove('d-none');
            } else {
                emptyAlert.classList.add('d-none');
            }
        }
    }

    function buildFieldSlot(fieldName, isLast) {
        var slot = document.createElement('div');
        slot.className = 'field-slot';
        if (isLast) {
            slot.classList.add('field-slot-last');
        }
        slot.setAttribute('data-field', fieldName);
        slot.innerHTML = ''
            + '<i class="bi bi-grip-vertical drag-handle"></i>'
            + '<span class="field-slot-name"></span>'
            + '<button type="button" class="field-slot-remove" title="Remove"><i class="bi bi-x-circle-fill"></i></button>';

        var nameNode = slot.querySelector('.field-slot-name');
        if (nameNode) nameNode.textContent = fieldName;

        var removeBtn = slot.querySelector('.field-slot-remove');
        if (removeBtn) {
            removeBtn.addEventListener('mousedown', function (event) {
                event.stopPropagation();
            });
            removeBtn.addEventListener('click', function () {
                syncStateFromDOM();
                columns.forEach(function (col) {
                    col.fields = (Array.isArray(col.fields) ? col.fields : []).filter(function (name) {
                        return safeTrim(name).toLowerCase() !== fieldName.toLowerCase();
                    });
                });
                render();
            });
        }

        return slot;
    }

    function buildColumnNode(col, index) {
        var colWidth = toInt(col.width, 6);
        var widthPct = (colWidth / TARGET_WIDTH * 100).toFixed(3);

        var colEl = document.createElement('div');
        colEl.className = 'layout-col col-idx-' + index;
        colEl.style.flex = '0 1 ' + widthPct + '%';

        var inner = document.createElement('div');
        inner.className = 'layout-col-inner';

        var header = document.createElement('div');
        header.className = 'layout-col-header';

        var widthIndex = VALID_WIDTHS.indexOf(colWidth);
        var canDec = widthIndex > 0;
        var canInc = widthIndex >= 0 && widthIndex < VALID_WIDTHS.length - 1;

        header.innerHTML = ''
            + '<div class="width-stepper">'
            + '  <button type="button" class="btn-dec"' + (canDec ? '' : ' disabled') + '><i class="bi bi-dash"></i></button>'
            + '  <span class="w-value">' + String(colWidth) + '</span>'
            + '  <button type="button" class="btn-inc"' + (canInc ? '' : ' disabled') + '><i class="bi bi-plus"></i></button>'
            + '  <span class="w-suffix">/12</span>'
            + '</div>'
            + '<button type="button" class="col-remove-btn" title="Remove column"' + (columns.length <= 1 ? ' disabled' : '') + '><i class="bi bi-x-lg"></i></button>';

        var decBtn = header.querySelector('.btn-dec');
        if (decBtn) {
            decBtn.addEventListener('click', function () {
                adjustWidth(col.id, -1);
            });
        }

        var incBtn = header.querySelector('.btn-inc');
        if (incBtn) {
            incBtn.addEventListener('click', function () {
                adjustWidth(col.id, 1);
            });
        }

        var removeBtn = header.querySelector('.col-remove-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                removeColumn(col.id);
            });
        }

        inner.appendChild(header);

        var zone = document.createElement('div');
        zone.className = 'col-drop-zone';
        zone.setAttribute('data-colid', col.id);

        var colFields = Array.isArray(col.fields) ? col.fields : [];
        for (var i = 0; i < colFields.length; i++) {
            zone.appendChild(buildFieldSlot(colFields[i], i === colFields.length - 1));
        }

        if (colFields.length === 0) {
            var hint = document.createElement('div');
            hint.className = 'col-empty-hint';
            hint.textContent = 'Drag here';
            zone.appendChild(hint);
        }

        inner.appendChild(zone);
        colEl.appendChild(inner);

        return colEl;
    }

    function renderCanvas() {
        var canvas = el('layoutCanvas');
        if (!canvas) return;

        canvas.innerHTML = '';
        columns.forEach(function (col, index) {
            canvas.appendChild(buildColumnNode(col, index));
        });
    }

    function destroySortables() {
        Object.keys(sortInstances).forEach(function (key) {
            var instance = sortInstances[key];
            if (instance && typeof instance.destroy === 'function') {
                try {
                    instance.destroy();
                } catch (e) {
                    // noop
                }
            }
        });
        sortInstances = {};

        if (poolSortable && typeof poolSortable.destroy === 'function') {
            try {
                poolSortable.destroy();
            } catch (e2) {
                // noop
            }
        }
        poolSortable = null;
    }

    function syncStateFromDOM() {
        var canvas = el('layoutCanvas');
        if (!canvas) return;

        columns.forEach(function (col) {
            var zone = canvas.querySelector('.col-drop-zone[data-colid="' + col.id + '"]');
            if (!zone) return;

            var zoneFields = [];
            Array.prototype.slice.call(zone.children).forEach(function (node) {
                if (!node || node.classList.contains('drag-placeholder')) return;
                var fieldName = safeTrim(node.getAttribute('data-field'));
                if (!fieldName) return;
                zoneFields.push(fieldName);
            });

            col.fields = zoneFields;
        });
    }

    function onSortableUpdate() {
        window.requestAnimationFrame(function () {
            syncStateFromDOM();
            render();
        });
    }

    function initSortables() {
        if (typeof ItoSortableList === 'undefined') return;

        var pool = el('fieldsPool');
        if (pool) {
            poolSortable = new ItoSortableList(pool, {
                group: SORT_GROUP,
                onUpdate: onSortableUpdate
            });
        }

        var canvas = el('layoutCanvas');
        if (!canvas) return;

        columns.forEach(function (col) {
            var zone = canvas.querySelector('.col-drop-zone[data-colid="' + col.id + '"]');
            if (!zone) return;
            sortInstances[col.id] = new ItoSortableList(zone, {
                group: SORT_GROUP,
                onUpdate: onSortableUpdate
            });
        });
    }

    function render() {
        destroySortables();
        renderPool();
        renderCanvas();
        renderBadge();

        var addColBtn = el('addColBtn');
        if (addColBtn) {
            addColBtn.disabled = columns.length >= MAX_COLS;
        }

        initSortables();
    }

    function rebalanceAll() {
        if (!columns.length) return;

        var count = columns.length;
        var base = Math.floor(TARGET_WIDTH / count);
        var remainder = TARGET_WIDTH - (base * count);

        columns.forEach(function (col, index) {
            var suggested = base + (index < remainder ? 1 : 0);
            col.width = snapValidWidth(suggested);
        });
    }

    function isValidExactWidth(value) {
        return VALID_WIDTHS.indexOf(toInt(value, 0)) >= 0;
    }

    function removeColumn(columnId) {
        if (columns.length <= 1) return;
        if (!window.confirm('Are you sure you want to remove this column?')) {
            return;
        }
        columns = columns.filter(function (col) {
            return col.id !== columnId;
        });
        rebalanceAll();
        render();
    }

    function adjustWidth(columnId, deltaDirection) {
        var index = -1;
        for (var i = 0; i < columns.length; i++) {
            if (columns[i].id === columnId) {
                index = i;
                break;
            }
        }
        if (index < 0) return;

        var column = columns[index];
        var currentIndex = VALID_WIDTHS.indexOf(snapValidWidth(column.width));
        if (currentIndex < 0) return;

        var targetIndex = currentIndex + (deltaDirection > 0 ? 1 : -1);
        if (targetIndex < 0 || targetIndex >= VALID_WIDTHS.length) return;

        var previousWidth = VALID_WIDTHS[currentIndex];
        var nextWidth = VALID_WIDTHS[targetIndex];

        var delta = nextWidth - previousWidth;
        var neighborIndex = index < columns.length - 1 ? index + 1 : index - 1;
        if (neighborIndex < 0) return;

        var neighbor = columns[neighborIndex];
        var neighborCurrent = snapValidWidth(neighbor.width);
        var neighborNext = neighborCurrent - delta;

        // Keep layout total locked to 12: if the paired column cannot absorb
        // the full delta with a valid width, cancel the resize.
        if (!isValidExactWidth(neighborNext)) {
            return;
        }

        column.width = nextWidth;
        neighbor.width = neighborNext;
        render();
    }

    function setDefaultColumns() {
        columns = [
            createColumn([], 6),
            createColumn([], 6)
        ];
    }

    function applySeed(seed, payloadAvailableFields) {
        var idInput = el('csId');
        var titleInput = el('csTitle');

        var idValue = safeTrim(seed && seed.id);
        var titleValue = safeTrim(seed && seed.title);

        if (idInput) idInput.value = idValue;
        if (titleInput) titleInput.value = titleValue;

        seedAttributes = normalizeAttributes(seed && seed.attributes);
        if (!safeTrim(seedAttributes['class'])) {
            seedAttributes['class'] = 'mb-3';
        }
        seedPositionBefore = safeTrim(seed && (seed.position_before || seed.positionBefore));

        availableFields = normalizeAvailableFields(payloadAvailableFields);

        var seedNames = flattenSeedFieldNames(seed && seed.fields);
        seedNames.forEach(function (name) {
            var key = name.toLowerCase();
            var exists = availableFields.some(function (item) {
                return item.toLowerCase() === key;
            });
            if (!exists) {
                availableFields.push(name);
            }
        });

        var availableMap = {};
        availableFields.forEach(function (name) {
            availableMap[name.toLowerCase()] = name;
        });

        var groups = parseSeedGroups(seed && seed.fields, availableMap);
        var widths = buildSeedWidths(seed && seed.cols, groups.length || 1);
        var limited = limitGroups(groups, widths);
        groups = limited.groups;
        widths = limited.widths;

        columns = [];
        if (groups.length === 0) {
            setDefaultColumns();
            return;
        }

        groups.forEach(function (group, index) {
            columns.push(createColumn(group, widths[index] || 6));
        });

        if (columns.length > MAX_COLS) {
            columns = columns.slice(0, MAX_COLS);
        }

        if (getWidthTotal() !== TARGET_WIDTH) {
            rebalanceAll();
        }
    }

    function buildSubmitAttributes() {
        var attributes = {};

        var cssClass = safeTrim(seedAttributes['class'] || 'mb-3');
        if (cssClass) {
            attributes['class'] = cssClass;
        }

        Object.keys(seedAttributes).forEach(function (key) {
            if (key === 'class') return;
            var value = seedAttributes[key];
            if (
                typeof value === 'string'
                || typeof value === 'number'
                || typeof value === 'boolean'
            ) {
                attributes[key] = value;
            }
        });

        return attributes;
    }

    function buildContainerConfig() {
        syncStateFromDOM();

        var activeColumns = [];
        columns.forEach(function (col) {
            var fields = Array.isArray(col.fields) ? col.fields.filter(function (name) {
                return safeTrim(name) !== '';
            }) : [];
            if (fields.length === 0) return;
            activeColumns.push({
                width: snapValidWidth(col.width),
                fields: fields
            });
        });

        var outputFields = [];
        var outputCols = [];
        activeColumns.forEach(function (col) {
            outputCols.push(col.width);
            if (col.fields.length === 1) {
                outputFields.push(col.fields[0]);
            } else {
                outputFields.push(col.fields.slice());
            }
        });

        var idInput = el('csId');
        var titleInput = el('csTitle');

        return {
            id: idInput ? safeTrim(idInput.value) : '',
            fields: outputFields,
            cols: outputCols,
            position_before: seedPositionBefore,
            title: titleInput ? safeTrim(titleInput.value) : '',
            attributes: buildSubmitAttributes()
        };
    }

    function validateBeforeSubmit(form) {
        clearCanvasError();

        if (!form || !form.checkValidity()) {
            if (form) form.classList.add('was-validated');
            return false;
        }

        syncStateFromDOM();

        if (!columns.length) {
            showCanvasError('Add at least one column.');
            return false;
        }

        var hasField = columns.some(function (col) {
            return Array.isArray(col.fields) && col.fields.length > 0;
        });
        if (!hasField) {
            showCanvasError('At least one column must contain one field.');
            return false;
        }

        if (getWidthTotal() !== TARGET_WIDTH) {
            showCanvasError('Columns total width must be exactly 12/12.');
            return false;
        }

        return true;
    }

    function resetState() {
        destroySortables();

        var form = el('containerBuilderForm');
        if (form) {
            form.reset();
            form.classList.remove('was-validated');
        }

        var pool = el('fieldsPool');
        if (pool) pool.innerHTML = '';

        var canvas = el('layoutCanvas');
        if (canvas) canvas.innerHTML = '';

        var fieldsEmpty = el('containerFieldsEmpty');
        if (fieldsEmpty) fieldsEmpty.classList.add('d-none');

        clearCanvasError();

        availableFields = [];
        columns = [];
        uid = 0;
        seedAttributes = { 'class': 'mb-3' };
        seedPositionBefore = '';
        submitHandler = null;
    }

    function ensureInitialized() {
        if (initialized) return true;

        var modal = el('containerBuilderModal');
        var form = el('containerBuilderForm');
        var addColBtn = el('addColBtn');
        if (!modal || !form || !addColBtn) {
            return false;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!validateBeforeSubmit(form)) {
                return;
            }

            var config = buildContainerConfig();
            if (typeof submitHandler === 'function') {
                submitHandler(config);
            }

            if (modalInstance && typeof modalInstance.hide === 'function') {
                modalInstance.hide();
            }
        });

        addColBtn.addEventListener('click', function () {
            if (columns.length >= MAX_COLS) return;
            syncStateFromDOM();
            columns.push(createColumn([], 6));
            rebalanceAll();
            render();
        });

        modal.addEventListener('hidden.bs.modal', function () {
            destroySortables();
        });

        modalInstance = (window.bootstrap && typeof window.bootstrap.Modal === 'function')
            ? new window.bootstrap.Modal(modal)
            : null;

        initialized = true;
        return true;
    }

    function openModal(openMode, payload) {
        if (!ensureInitialized()) return false;

        var data = (payload && typeof payload === 'object') ? payload : {};
        resetState();
        mode = openMode === 'edit' ? 'edit' : 'create';
        submitHandler = (typeof data.onSubmit === 'function') ? data.onSubmit : null;
        setModeUi();

        var seed = (data.seed && typeof data.seed === 'object') ? data.seed : {};
        var payloadAvailableFields = Array.isArray(data.availableFields) ? data.availableFields : [];

        applySeed(seed, payloadAvailableFields);
        render();

        if (modalInstance && typeof modalInstance.show === 'function') {
            modalInstance.show();
        }

        return true;
    }

    window.containerBuilderModal = {
        openCreate: function (payload) {
            return openModal('create', payload);
        },
        openEdit: function (payload) {
            return openModal('edit', payload);
        }
    };
})();
