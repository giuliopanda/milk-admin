(function () {
    function byId(id) {
        return document.getElementById(id);
    }

    function safeParseJson(rawValue, fallbackValue) {
        if (!rawValue) {
            return fallbackValue;
        }
        try {
            return JSON.parse(rawValue);
        } catch (error) {
            return fallbackValue;
        }
    }

    function safeParseJsonScript(id, fallbackValue) {
        var script = byId(id);
        if (!script) {
            return fallbackValue;
        }
        return safeParseJson(script.textContent || '', fallbackValue);
    }

    function isSafeIdentifier(value) {
        return /^[A-Za-z_][A-Za-z0-9_]*$/.test(String(value || '').trim());
    }

    function isSafeQueryFieldIdentifier(value) {
        var normalized = String(value || '').trim();
        if (!normalized) {
            return false;
        }
        if (isSafeIdentifier(normalized)) {
            return true;
        }
        return /^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/.test(normalized);
    }

    function isSafeUrlParamName(value) {
        return /^[A-Za-z][A-Za-z0-9_-]*$/.test(String(value || '').trim());
    }

    function normalizeBool(value, fallbackValue) {
        if (typeof value === 'boolean') {
            return value;
        }
        if (typeof value === 'number') {
            return value === 1;
        }

        var normalized = String(value || '').trim().toLowerCase();
        if (normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on') {
            return true;
        }
        if (normalized === '0' || normalized === 'false' || normalized === 'no' || normalized === 'off') {
            return false;
        }
        return fallbackValue !== false;
    }

    function normalizeFields(rawFields) {
        if (!Array.isArray(rawFields)) {
            return [];
        }

        var result = [];
        var seen = {};
        for (var i = 0; i < rawFields.length; i += 1) {
            var item = rawFields[i];
            if (!item || typeof item !== 'object') {
                continue;
            }

            var name = String(item.name || '').trim();
            if (!isSafeQueryFieldIdentifier(name)) {
                continue;
            }

            var key = name.toLowerCase();
            if (seen[key]) {
                continue;
            }
            seen[key] = true;

            var label = String(item.label || '').trim();
            if (!label) {
                label = name;
            }

            result.push({
                name: name,
                label: label,
                type: String(item.type || '').trim()
            });
        }
        return result;
    }

    function normalizeSanitizeType(value) {
        var type = String(value || '').trim().toLowerCase();
        if (type === 'int' || type === 'float' || type === 'bool' || type === 'string' || type === 'uuid' || type === 'slug') {
            return type;
        }
        return 'string';
    }

    function inferSanitizeTypeFromSqlType(fieldName, sqlType) {
        var field = String(fieldName || '').trim().toLowerCase();
        var typeRaw = String(sqlType || '').trim().toLowerCase();
        var baseMatch = typeRaw.match(/^[a-z]+/);
        var base = baseMatch ? baseMatch[0] : typeRaw;

        if (base === 'tinyint' && /^tinyint\s*\(\s*1\s*\)/.test(typeRaw)) {
            return 'bool';
        }
        if (base === 'int' || base === 'integer' || base === 'smallint' || base === 'mediumint' || base === 'bigint' || base === 'tinyint' || base === 'serial') {
            return 'int';
        }
        if (base === 'decimal' || base === 'numeric' || base === 'float' || base === 'double' || base === 'real') {
            return 'float';
        }
        if (base === 'bool' || base === 'boolean' || base === 'bit') {
            return 'bool';
        }
        if (base === 'uuid') {
            return 'uuid';
        }
        if (field.indexOf('uuid') !== -1 || /^char\s*\(\s*36\s*\)/.test(typeRaw)) {
            return 'uuid';
        }
        if (field.indexOf('slug') !== -1) {
            return 'slug';
        }
        return 'string';
    }

    function normalizeDbFields(rawFields) {
        if (!Array.isArray(rawFields)) {
            return [];
        }

        var result = [];
        var seen = {};
        for (var i = 0; i < rawFields.length; i += 1) {
            var item = rawFields[i];
            if (!item || typeof item !== 'object') {
                continue;
            }

            var name = String(item.name || '').trim();
            if (!name) {
                continue;
            }
            var key = name.toLowerCase();
            if (seen[key]) {
                continue;
            }
            seen[key] = true;

            var label = String(item.label || '').trim();
            if (!label) {
                label = name;
            }

            var type = String(item.type || '').trim();
            var sanitizeType = normalizeSanitizeType(item.sanitize_type);
            if (sanitizeType === 'string' && !String(item.sanitize_type || '').trim()) {
                sanitizeType = inferSanitizeTypeFromSqlType(name, type);
            }

            result.push({
                name: name,
                label: label,
                type: type,
                sanitize_type: sanitizeType
            });
        }

        return result;
    }

    function initUrlParamFieldAutoSanitize(dbFieldLookup) {
        var fieldInput = byId('project-search-url-param-field');
        var sanitizeTypeInput = byId('project-search-url-param-type');
        if (!fieldInput || !sanitizeTypeInput) {
            return null;
        }

        function applySanitizeTypeFromSelectedField() {
            var fieldName = String(fieldInput.value || '').trim().toLowerCase();
            var fieldMeta = fieldName ? (dbFieldLookup[fieldName] || null) : null;
            var sanitizeType = fieldMeta ? normalizeSanitizeType(fieldMeta.sanitize_type) : 'string';
            sanitizeTypeInput.value = sanitizeType;
        }

        function setFieldValue(fieldName) {
            var normalized = String(fieldName || '').trim();
            var lower = normalized.toLowerCase();
            var meta = lower ? (dbFieldLookup[lower] || null) : null;
            var milkSelect = tryInitMilkSelect('project-search-url-param-field');

            if (!normalized) {
                if (milkSelect && typeof milkSelect.clearSingleValue === 'function') {
                    milkSelect.clearSingleValue();
                }
                fieldInput.value = '';
                fieldInput.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }

            if (milkSelect && meta && meta.label && typeof milkSelect.selectOption === 'function') {
                if (typeof milkSelect.clearSingleValue === 'function') {
                    milkSelect.clearSingleValue();
                }
                milkSelect.selectOption(meta.label);
                return;
            }

            fieldInput.value = normalized;
            fieldInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        fieldInput.addEventListener('change', applySanitizeTypeFromSelectedField);
        fieldInput.addEventListener('input', applySanitizeTypeFromSelectedField);
        fieldInput.addEventListener('blur', applySanitizeTypeFromSelectedField);

        var modal = byId('project-search-url-param-modal');
        if (modal) {
            modal.addEventListener('shown.bs.modal', function () {
                applySanitizeTypeFromSelectedField();
            });
        }

        tryInitMilkSelect('project-search-url-param-field');
        applySanitizeTypeFromSelectedField();

        return {
            syncFromField: applySanitizeTypeFromSelectedField,
            setFieldValue: setFieldValue,
            getFieldValue: function () {
                return String(fieldInput.value || '').trim();
            }
        };
    }

    function tryInitMilkSelect(inputId) {
        var input = byId(inputId);
        if (!input) {
            return null;
        }
        if (!window.MilkSelect || typeof window.MilkSelect.initFromConfig !== 'function') {
            return null;
        }
        if (input.dataset && input.dataset.milkselectInitialized !== 'true') {
            window.MilkSelect.initFromConfig(inputId);
        }
        return input.milkSelectInstance || null;
    }

    function closeModal(modalEl) {
        if (!modalEl) {
            return;
        }
        if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
            var modalInstance = window.bootstrap.Modal.getInstance(modalEl);
            if (!modalInstance && typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
                modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalEl);
            }
            if (modalInstance) {
                modalInstance.hide();
                return;
            }
        }

        var closeBtn = modalEl.querySelector('[data-bs-dismiss="modal"]');
        if (closeBtn) {
            closeBtn.click();
        }
    }

    function notify(message, type) {
        var text = String(message || '').trim();
        if (!text) {
            return;
        }

        if (window.toasts && typeof window.toasts.show === 'function') {
            window.toasts.show(text, type || 'info');
            return;
        }

        window.alert(text);
    }

    function readSelectedFieldNames(hiddenInput) {
        if (!hiddenInput) {
            return [];
        }
        var parsed = safeParseJson(String(hiddenInput.value || '').trim(), []);
        if (!Array.isArray(parsed)) {
            return [];
        }

        var out = [];
        var seen = {};
        for (var i = 0; i < parsed.length; i += 1) {
            var name = String(parsed[i] || '').trim();
            if (!isSafeQueryFieldIdentifier(name)) {
                continue;
            }
            var key = name.toLowerCase();
            if (seen[key]) {
                continue;
            }
            seen[key] = true;
            out.push(name);
        }
        return out;
    }

    function writeSelectedFieldNames(hiddenInput, names) {
        if (!hiddenInput) {
            return;
        }
        hiddenInput.value = JSON.stringify(Array.isArray(names) ? names : []);
    }

    function initQueryFieldPicker(fields, fieldLookup) {
        var pickerInput = byId('project-search-field-query-field-picker');
        var addBtn = byId('project-search-field-query-field-add');
        var valuesInput = byId('project-search-field-query-fields-values');
        var list = byId('project-search-field-query-fields-list');
        var countEl = byId('project-search-field-query-fields-count');
        var emptyAlert = byId('project-search-field-query-fields-empty');
        var sourceEmptyAlert = byId('project-search-field-query-fields-source-empty');

        if (!pickerInput || !addBtn || !valuesInput || !list || !countEl || !emptyAlert || !sourceEmptyAlert) {
            return null;
        }

        function getLabel(name) {
            var field = fieldLookup[String(name || '').toLowerCase()] || null;
            if (field && field.label) {
                return field.label;
            }
            return String(name || '');
        }

        function renderSelectedRows() {
            var selectedNames = readSelectedFieldNames(valuesInput);
            list.innerHTML = '';

            for (var i = 0; i < selectedNames.length; i += 1) {
                var name = selectedNames[i];
                var row = document.createElement('div');
                row.className = 'list-group-item d-flex align-items-center justify-content-between gap-2 flex-wrap';

                var left = document.createElement('div');
                left.className = 'd-flex flex-column';

                var title = document.createElement('strong');
                title.textContent = getLabel(name);
                left.appendChild(title);

                var code = document.createElement('code');
                code.textContent = name;
                left.appendChild(code);

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-outline-danger btn-sm';
                removeBtn.setAttribute('data-remove-query-field', name);
                removeBtn.textContent = 'Remove';

                row.appendChild(left);
                row.appendChild(removeBtn);
                list.appendChild(row);
            }

            emptyAlert.classList.toggle('d-none', selectedNames.length > 0);
            countEl.textContent = String(selectedNames.length) + ' selected';
        }

        function getPickerValue() {
            return String(pickerInput.value || '').trim();
        }

        function clearPickerValue() {
            var instance = tryInitMilkSelect('project-search-field-query-field-picker');
            if (instance && typeof instance.clearSingleValue === 'function') {
                instance.clearSingleValue();
                return;
            }
            pickerInput.value = '';
        }

        function addFromPicker() {
            var pickedName = getPickerValue();
            if (!isSafeQueryFieldIdentifier(pickedName)) {
                return;
            }
            if (!fieldLookup[pickedName.toLowerCase()]) {
                return;
            }

            var selected = readSelectedFieldNames(valuesInput);
            var exists = false;
            for (var i = 0; i < selected.length; i += 1) {
                if (selected[i].toLowerCase() === pickedName.toLowerCase()) {
                    exists = true;
                    break;
                }
            }
            if (!exists) {
                selected.push(pickedName);
            }

            writeSelectedFieldNames(valuesInput, selected);
            renderSelectedRows();
            clearPickerValue();
        }

        function removeName(name) {
            var target = String(name || '').trim().toLowerCase();
            if (!target) {
                return;
            }

            var selected = readSelectedFieldNames(valuesInput);
            var out = [];
            for (var i = 0; i < selected.length; i += 1) {
                if (selected[i].toLowerCase() !== target) {
                    out.push(selected[i]);
                }
            }
            writeSelectedFieldNames(valuesInput, out);
            renderSelectedRows();
        }

        addBtn.addEventListener('click', function () {
            addFromPicker();
        });

        list.addEventListener('click', function (event) {
            var target = event.target;
            if (!target || !target.getAttribute) {
                return;
            }
            var removeNameValue = target.getAttribute('data-remove-query-field');
            if (!removeNameValue) {
                return;
            }
            removeName(removeNameValue);
        });

        var hasSourceFields = fields.length > 0;
        sourceEmptyAlert.classList.toggle('d-none', hasSourceFields);
        addBtn.disabled = !hasSourceFields;
        if (pickerInput.disabled !== true) {
            pickerInput.disabled = !hasSourceFields;
        }

        tryInitMilkSelect('project-search-field-query-field-picker');
        renderSelectedRows();

        return {
            getSelectedNames: function () {
                return readSelectedFieldNames(valuesInput);
            },
            setSelectedNames: function (names) {
                writeSelectedFieldNames(valuesInput, Array.isArray(names) ? names : []);
                renderSelectedRows();
            },
            clearPicker: function () {
                clearPickerValue();
            }
        };
    }

    function initUrlParamsEditor(dbFieldLookup, initialUrlParams, fieldSanitizer) {
        var list = byId('project-search-url-params-list');
        var empty = byId('project-search-url-params-empty');
        var addBtn = byId('project-search-add-url-param-btn');
        var modal = byId('project-search-url-param-modal');
        var form = byId('project-search-url-param-form');
        var modalTitle = modal ? modal.querySelector('.modal-title') : null;
        var submitBtn = byId('project-search-url-param-submit-btn');
        var editKeyInput = byId('project-search-url-param-edit-key');
        var errorBox = byId('project-search-url-param-form-error');
        var nameInput = byId('project-search-url-param-name');
        var operatorInput = byId('project-search-url-param-operator');
        var requiredInput = byId('project-search-url-param-required');
        var sanitizeTypeInput = byId('project-search-url-param-type');

        if (!list || !empty || !addBtn || !modal || !form || !modalTitle || !submitBtn || !editKeyInput || !errorBox || !nameInput || !operatorInput || !requiredInput || !sanitizeTypeInput) {
            return {
                getCurrentUrlParams: function () {
                    return [];
                },
                setUrlParams: function () {}
            };
        }

        function setFormError(message) {
            var msg = String(message || '').trim();
            if (!msg) {
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
                return;
            }
            errorBox.textContent = msg;
            errorBox.classList.remove('d-none');
        }

        function normalizeUrlParamOperator(value) {
            var allowed = {
                equals: true,
                like: true,
                starts_with: true,
                ends_with: true,
                greater_than: true,
                greater_or_equal: true,
                less_than: true,
                less_or_equal: true
            };
            var operator = String(value || '').trim().toLowerCase();
            return allowed[operator] ? operator : 'equals';
        }

        function getCurrentUrlParams() {
            var rows = list.querySelectorAll('.project-search-url-param-row');
            var out = [];
            for (var i = 0; i < rows.length; i += 1) {
                var parsed = safeParseJson(rows[i].getAttribute('data-url-param-config') || '{}', null);
                if (!parsed || typeof parsed !== 'object') {
                    continue;
                }
                out.push(parsed);
            }
            return out;
        }

        function updateEmptyState() {
            var count = list.querySelectorAll('.project-search-url-param-row').length;
            empty.classList.toggle('d-none', count > 0);
        }

        function getUrlParamKey(config) {
            return String(config && config.name ? config.name : '').trim().toLowerCase();
        }

        function findRowByKey(key) {
            if (!key) {
                return null;
            }
            return list.querySelector('.project-search-url-param-row[data-url-param-key="' + key.replace(/"/g, '\\"') + '"]');
        }

        function renderUrlParamRow(row, config, key) {
            row.className = 'list-group-item d-flex align-items-center justify-content-between gap-2 flex-wrap project-search-url-param-row';
            row.setAttribute('data-url-param-key', key);
            row.setAttribute('data-url-param-config', JSON.stringify(config || {}));
            row.innerHTML = '';

            var left = document.createElement('div');
            left.className = 'd-flex flex-column';

            var title = document.createElement('strong');
            title.textContent = String(config.name || '');
            left.appendChild(title);

            var meta = document.createElement('div');
            meta.className = 'small text-muted d-flex align-items-center gap-2 flex-wrap';

            if (config.field) {
                var fieldCode = document.createElement('code');
                fieldCode.textContent = String(config.field);
                meta.appendChild(fieldCode);
            }

            var opBadge = document.createElement('span');
            opBadge.className = 'badge text-bg-secondary';
            opBadge.textContent = String(config.operator || 'equals');
            meta.appendChild(opBadge);

            var typeBadge = document.createElement('span');
            typeBadge.className = 'badge text-bg-light';
            typeBadge.textContent = String(config.type || 'string');
            meta.appendChild(typeBadge);

            if (normalizeBool(config.required, false)) {
                var requiredBadge = document.createElement('span');
                requiredBadge.className = 'badge text-bg-warning';
                requiredBadge.textContent = 'required';
                meta.appendChild(requiredBadge);
            }

            left.appendChild(meta);

            var right = document.createElement('div');
            right.className = 'd-flex align-items-center gap-2 flex-wrap';

            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'btn btn-outline-secondary btn-sm';
            editBtn.setAttribute('data-url-param-action', 'edit');
            editBtn.textContent = 'Edit';
            right.appendChild(editBtn);

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-outline-danger btn-sm';
            removeBtn.setAttribute('data-url-param-action', 'remove');
            removeBtn.textContent = 'Remove';
            right.appendChild(removeBtn);

            row.appendChild(left);
            row.appendChild(right);
        }

        function upsertUrlParamRow(config, key, previousEditKey, skipRefresh) {
            if (!key) {
                return;
            }

            var rowByNewKey = findRowByKey(key);
            var rowByEditKey = previousEditKey ? findRowByKey(previousEditKey) : null;
            if (rowByEditKey && rowByNewKey && rowByEditKey !== rowByNewKey) {
                rowByEditKey.remove();
            }

            var targetRow = rowByNewKey || rowByEditKey;
            if (!targetRow) {
                targetRow = document.createElement('article');
                list.appendChild(targetRow);
            }

            renderUrlParamRow(targetRow, config, key);
            if (skipRefresh !== true) {
                updateEmptyState();
            }
        }

        function hydrateInitialUrlParams(rawUrlParams) {
            if (!Array.isArray(rawUrlParams)) {
                return;
            }
            for (var i = 0; i < rawUrlParams.length; i += 1) {
                var config = rawUrlParams[i];
                if (!config || typeof config !== 'object') {
                    continue;
                }
                var key = getUrlParamKey(config);
                if (!key) {
                    continue;
                }
                upsertUrlParamRow(config, key, '', true);
            }
        }

        function resetRowsFromUrlParams(rawUrlParams) {
            list.innerHTML = '';
            hydrateInitialUrlParams(rawUrlParams);
            updateEmptyState();
        }

        function fillFormFromConfig(config, key) {
            var rowConfig = config && typeof config === 'object' ? config : {};
            nameInput.value = String(rowConfig.name || '');
            if (fieldSanitizer && typeof fieldSanitizer.setFieldValue === 'function') {
                fieldSanitizer.setFieldValue(String(rowConfig.field || ''));
            }
            if (fieldSanitizer && typeof fieldSanitizer.syncFromField === 'function') {
                fieldSanitizer.syncFromField();
            } else {
                sanitizeTypeInput.value = normalizeSanitizeType(sanitizeTypeInput.value || 'string');
            }
            operatorInput.value = normalizeUrlParamOperator(rowConfig.operator || 'equals');
            requiredInput.checked = normalizeBool(rowConfig.required, false);
            editKeyInput.value = String(key || '');
        }

        function resetModalForAdd() {
            setFormError('');
            modalTitle.textContent = 'Add URL Param';
            submitBtn.textContent = 'Add';
            fillFormFromConfig({
                name: '',
                field: '',
                type: 'string',
                operator: 'equals',
                required: false
            }, '');
        }

        function openModalForEdit(row) {
            var key = String(row.getAttribute('data-url-param-key') || '');
            var config = safeParseJson(row.getAttribute('data-url-param-config') || '{}', {});
            setFormError('');
            modalTitle.textContent = 'Edit URL Param';
            submitBtn.textContent = 'Save';
            fillFormFromConfig(config, key);

            if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                window.bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        }

        function buildConfigFromForm() {
            var name = String(nameInput.value || '').trim();
            if (!isSafeUrlParamName(name)) {
                return { error: 'URL param name is invalid. Use letters, numbers, underscore, dash.' };
            }

            var field = '';
            if (fieldSanitizer && typeof fieldSanitizer.getFieldValue === 'function') {
                field = fieldSanitizer.getFieldValue();
            }
            field = String(field || '').trim();
            if (!isSafeIdentifier(field)) {
                return { error: 'Target field is required.' };
            }

            var fieldMeta = dbFieldLookup[field.toLowerCase()] || null;
            if (!fieldMeta) {
                return { error: 'Target field must be selected from DB fields.' };
            }

            if (fieldSanitizer && typeof fieldSanitizer.syncFromField === 'function') {
                fieldSanitizer.syncFromField();
            }
            var sanitizeType = normalizeSanitizeType(String(sanitizeTypeInput.value || ''));

            var config = {
                name: name,
                field: field,
                operator: normalizeUrlParamOperator(operatorInput.value),
                type: sanitizeType,
                required: normalizeBool(requiredInput.checked, false)
            };

            return {
                error: '',
                config: config,
                key: getUrlParamKey(config)
            };
        }

        addBtn.addEventListener('click', function () {
            resetModalForAdd();
        });

        list.addEventListener('click', function (event) {
            var target = event.target && event.target.closest ? event.target.closest('[data-url-param-action]') : null;
            if (!target) {
                return;
            }
            var action = String(target.getAttribute('data-url-param-action') || '');
            if (!action) {
                return;
            }

            var row = target.closest('.project-search-url-param-row');
            if (!row) {
                return;
            }

            if (action === 'remove') {
                row.remove();
                updateEmptyState();
                return;
            }
            if (action === 'edit') {
                openModalForEdit(row);
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            setFormError('');

            var built = buildConfigFromForm();
            if (built.error) {
                setFormError(built.error);
                return;
            }

            var previousEditKey = String(editKeyInput.value || '');
            upsertUrlParamRow(built.config, built.key, previousEditKey);
            resetModalForAdd();
            closeModal(modal);
        });

        hydrateInitialUrlParams(Array.isArray(initialUrlParams) ? initialUrlParams : []);
        updateEmptyState();

        return {
            getCurrentUrlParams: getCurrentUrlParams,
            setUrlParams: resetRowsFromUrlParams
        };
    }

    function initSearchFieldsEditor(fields, fieldLookup, queryPicker, urlParamsEditor, initialConfigRaw, pageMeta) {
        var list = byId('project-search-fields-list');
        var empty = byId('project-search-fields-empty');
        var addFieldBtn = byId('project-search-add-field-btn');
        var addNewlineBtn = byId('project-search-add-newline-btn');
        var saveBtn = byId('project-search-save-btn');
        var modal = byId('project-search-field-modal');
        var form = byId('project-search-field-form');
        var modalTitle = modal ? modal.querySelector('.modal-title') : null;
        var submitBtn = byId('project-search-field-submit-btn');
        var editKeyInput = byId('project-search-field-edit-key');
        var errorBox = byId('project-search-field-form-error');
        var typeInput = byId('project-search-field-type');
        var queryBlock = byId('project-search-field-query-block');
        var operatorBlock = byId('project-search-field-operator-block');
        var optionsBlock = byId('project-search-field-options-block');
        var optionsEditorRoot = byId('project-search-options-editor');
        if (!list || !empty || !addFieldBtn || !saveBtn || !modal || !form || !modalTitle || !submitBtn || !editKeyInput || !errorBox || !queryPicker || !typeInput || !optionsBlock || !optionsEditorRoot) {
            return;
        }

        var newlineCounter = 0;
        var clientFilterKeyCounter = 0;
        var sortable = null;

        function initSortable() {
            if (typeof ItoSortableList === 'undefined') {
                sortable = null;
                return;
            }
            sortable = new ItoSortableList(list, {
                handleSelector: '.project-search-filter-handle',
                onUpdate: function () {
                    // order is captured on save via getCurrentFilters()
                }
            });
        }

        function makeRowDraggable(row) {
            if (sortable && typeof sortable.makeDraggable === 'function') {
                sortable.makeDraggable(row);
            }
        }

        var meta = pageMeta && typeof pageMeta === 'object' ? pageMeta : {};
        var moduleName = String(meta.module || '').trim();
        var saveUrl = String(meta.saveUrl || '').trim();
        var projectHomeUrl = String(meta.projectHomeUrl || '').trim();
        var saveSuccessUrl = String(meta.saveSuccessUrl || '').trim();
        var isSaving = false;

        function normalizeSearchMode(value) {
            return 'submit';
        }

        function normalizeFilterTypeForEditor(value) {
            var type = String(value || '').trim().toLowerCase();
            if (!type) {
                return '';
            }
            if (type === 'search' || type === 'search_all' || type === 'search-all' || type === 'searchall') {
                return 'search_all';
            }
            if (type === 'action-list' || type === 'actionlist') {
                return 'action_list';
            }
            if (type === 'select' || type === 'action_list' || type === 'input' || type === 'newline') {
                return type;
            }
            return '';
        }

        function isSearchAllType(value) {
            return normalizeFilterTypeForEditor(value) === 'search_all';
        }

        function normalizeFilterConfigForEditor(input) {
            var raw = input && typeof input === 'object' ? input : null;
            if (!raw) {
                return null;
            }

            var type = normalizeFilterTypeForEditor(raw.type || '');
            if (!type) {
                return null;
            }
            if (type === 'newline') {
                return { type: 'newline' };
            }

            var name = String(raw.name || '').trim();
            if (!isSafeIdentifier(name)) {
                name = '';
            }

            var normalized = {
                type: type,
                layout: 'inline'
            };
            if (name) {
                normalized.name = name;
            }

            var label = String(raw.label || '').trim();
            if (label) {
                normalized.label = label;
            }

            var placeholder = String(raw.placeholder || '').trim();
            if (placeholder) {
                normalized.placeholder = placeholder;
            }

            if (type === 'input') {
                var inputType = String(raw.input_type || raw.inputType || 'text').trim();
                normalized.input_type = inputType || 'text';
            }

            if (isOptionsType(type)) {
                var optionsObject = optionsArrayToObject(normalizeOptionsFromAny(raw.options));
                if (Object.keys(optionsObject).length > 0) {
                    normalized.options = optionsObject;
                }
            }

            if (Object.prototype.hasOwnProperty.call(raw, 'default') || raw.has_default === true || raw.hasDefault === true) {
                normalized.has_default = true;
                normalized.default = raw.default != null ? raw.default : '';
            }

            if (type === 'search_all') {
                normalized.query = { operator: 'like', fields: [] };
                return normalized;
            }

            var queryRaw = raw.query && typeof raw.query === 'object' ? raw.query : {};
            var queryFieldsRaw = Array.isArray(queryRaw.fields) ? queryRaw.fields : (Array.isArray(raw.fields) ? raw.fields : []);
            var safeQueryFields = [];
            var seen = {};
            for (var i = 0; i < queryFieldsRaw.length; i += 1) {
                var queryField = String(queryFieldsRaw[i] || '').trim();
                if (!isSafeQueryFieldIdentifier(queryField)) {
                    continue;
                }
                var key = queryField.toLowerCase();
                if (seen[key]) {
                    continue;
                }
                seen[key] = true;
                safeQueryFields.push(queryField);
            }
            if (safeQueryFields.length === 0 && name) {
                safeQueryFields = [name];
            }

            normalized.query = {
                operator: normalizeOperator(queryRaw.operator || raw.operator || ''),
                fields: safeQueryFields
            };

            return normalized;
        }

        function normalizeInitialConfig(input) {
            var raw = input && typeof input === 'object' ? input : {};
            var filtersRaw = Array.isArray(raw.filters) ? raw.filters : [];
            var urlParamsRaw = Array.isArray(raw.url_params)
                ? raw.url_params
                : (Array.isArray(raw.urlParams) ? raw.urlParams : []);
            var filters = [];
            var urlParams = [];
            for (var i = 0; i < filtersRaw.length; i += 1) {
                if (filtersRaw[i] && typeof filtersRaw[i] === 'object') {
                    var normalizedFilter = normalizeFilterConfigForEditor(filtersRaw[i]);
                    if (normalizedFilter) {
                        filters.push(normalizedFilter);
                    }
                }
            }
            for (var j = 0; j < urlParamsRaw.length; j += 1) {
                if (urlParamsRaw[j] && typeof urlParamsRaw[j] === 'object') {
                    urlParams.push(urlParamsRaw[j]);
                }
            }
            return {
                search_mode: normalizeSearchMode(raw.search_mode),
                auto_buttons: normalizeBool(raw.auto_buttons, true),
                url_params: urlParams,
                filters: filters
            };
        }

        var initialConfig = normalizeInitialConfig(initialConfigRaw);
        var searchMeta = {
            search_mode: initialConfig.search_mode,
            auto_buttons: initialConfig.auto_buttons
        };

        var optionsTools = (window.projectsOptionsEditor && typeof window.projectsOptionsEditor === 'object')
            ? window.projectsOptionsEditor
            : null;
        var optionsEditor = null;
        if (optionsTools && typeof optionsTools.createManualBulkOptionsEditor === 'function') {
            optionsEditor = optionsTools.createManualBulkOptionsEditor({
                root: optionsEditorRoot,
                listSelector: '#project-search-options-list',
                bulkSelector: '#project-search-options-bulk',
                addButtonSelector: '#project-search-options-add',
                sortable: true
            });
        }

        function setFormError(message) {
            var msg = String(message || '').trim();
            if (!msg) {
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
                return;
            }
            errorBox.textContent = msg;
            errorBox.classList.remove('d-none');
        }

        function getInputValue(id) {
            var el = byId(id);
            return el ? String(el.value || '').trim() : '';
        }

        function setInputValue(id, value) {
            var el = byId(id);
            if (!el) {
                return;
            }
            el.value = value == null ? '' : String(value);
        }

        function normalizeOptionsFromAny(input) {
            if (optionsTools && typeof optionsTools.normalizeOptionsFromAny === 'function') {
                return optionsTools.normalizeOptionsFromAny(input);
            }
            if (Array.isArray(input)) {
                return input.map(function (item) {
                    return {
                        value: String(item && item.value ? item.value : ''),
                        label: String(item && item.label ? item.label : '')
                    };
                });
            }
            if (input && typeof input === 'object') {
                return Object.keys(input).map(function (key) {
                    return {
                        value: String(key),
                        label: String(input[key] || key)
                    };
                });
            }
            return [];
        }

        function optionsArrayToObject(options) {
            if (optionsTools && typeof optionsTools.optionsArrayToObject === 'function') {
                return optionsTools.optionsArrayToObject(options);
            }
            var out = {};
            if (!Array.isArray(options)) {
                return out;
            }
            options.forEach(function (item) {
                var value = String(item && item.value ? item.value : '').trim();
                if (!value) {
                    return;
                }
                var label = String(item && item.label ? item.label : '').trim();
                out[value] = label || value;
            });
            return out;
        }

        function isOptionsType(type) {
            var normalized = String(type || '').trim();
            return normalized === 'select' || normalized === 'action_list';
        }

        function updateTypeSpecificVisibility() {
            var currentType = normalizeFilterTypeForEditor(getInputValue('project-search-field-type'));
            var hideQueryBlocks = currentType === 'search_all';
            if (queryBlock) {
                queryBlock.classList.toggle('d-none', hideQueryBlocks);
            }
            if (operatorBlock) {
                operatorBlock.classList.toggle('d-none', hideQueryBlocks);
            }
            var shouldShow = isOptionsType(getInputValue('project-search-field-type'));
            optionsBlock.classList.toggle('d-none', !shouldShow);
        }

        function setEditorOptionsFromConfig(config) {
            if (!optionsEditor || typeof optionsEditor.setOptions !== 'function') {
                return;
            }
            var optionsFromConfig = config && typeof config === 'object' ? config.options : null;
            optionsEditor.setOptions(normalizeOptionsFromAny(optionsFromConfig));
            optionsEditor.setActiveTab('manual');
        }

        function getOptionsObjectFromEditor() {
            if (!optionsEditor) {
                return {};
            }
            if (typeof optionsEditor.getOptionsObject === 'function') {
                return optionsEditor.getOptionsObject();
            }
            if (typeof optionsEditor.getOptionsArray === 'function') {
                return optionsArrayToObject(optionsEditor.getOptionsArray());
            }
            return {};
        }

        function getCurrentFilters() {
            var rows = list.querySelectorAll('.project-search-filter-row');
            var filters = [];
            for (var i = 0; i < rows.length; i += 1) {
                var row = rows[i];
                var parsed = safeParseJson(row.getAttribute('data-filter-config') || '{}', null);
                if (!parsed || typeof parsed !== 'object') {
                    continue;
                }
                filters.push(parsed);
            }
            return filters;
        }

        function buildCurrentPayload() {
            return {
                search_mode: searchMeta.search_mode,
                auto_buttons: searchMeta.auto_buttons,
                url_params: urlParamsEditor && typeof urlParamsEditor.getCurrentUrlParams === 'function'
                    ? urlParamsEditor.getCurrentUrlParams()
                    : [],
                filters: getCurrentFilters()
            };
        }

        function countNonNewlineRows() {
            var rows = list.querySelectorAll('.project-search-filter-row');
            var count = 0;
            for (var i = 0; i < rows.length; i += 1) {
                if (!rows[i].hasAttribute('data-filter-newline')) {
                    count += 1;
                }
            }
            return count;
        }

        function updateEmptyState() {
            var count = list.querySelectorAll('.project-search-filter-row').length;
            empty.classList.toggle('d-none', count > 0);
            if (addNewlineBtn) {
                addNewlineBtn.classList.toggle('d-none', countNonNewlineRows() <= 2);
            }
        }

        function getFilterKey(config) {
            var type = String(config.type || '').trim();
            if (type === 'search_button' || type === 'clear_button') {
                return '__' + type;
            }
            if (type === 'newline') {
                newlineCounter += 1;
                return '__newline_' + newlineCounter;
            }
            var name = String(config.name || '').trim().toLowerCase();
            if (name) {
                return name;
            }

            var label = String(config.label || '').trim().toLowerCase();
            var slug = label.replace(/[^a-z0-9]+/g, '');
            clientFilterKeyCounter += 1;
            if (slug) {
                return '__tmp_' + slug + '_' + clientFilterKeyCounter;
            }
            return '__tmp_filter_' + clientFilterKeyCounter;
        }

        function findRowByKey(key) {
            if (!key) {
                return null;
            }
            return list.querySelector('.project-search-filter-row[data-filter-key="' + key.replace(/"/g, '\\"') + '"]');
        }

        function normalizeOperator(value) {
            var operator = String(value || '').trim();
            if (!operator) {
                return 'like';
            }
            return operator;
        }

        function buildConfigFromForm() {
            var type = normalizeFilterTypeForEditor(getInputValue('project-search-field-type'));
            if (!type) {
                return { error: 'Type is required.' };
            }

            var config = {
                type: type,
                layout: 'inline'
            };

            var label = getInputValue('project-search-field-label');
            if (!label) {
                return { error: 'Label is required.' };
            }
            config.label = label;

            if (isSearchAllType(type)) {
                config.query = {
                    operator: 'like',
                    fields: []
                };
            } else {
                var queryFields = queryPicker.getSelectedNames();
                var safeQueryFields = [];
                for (var i = 0; i < queryFields.length; i += 1) {
                    if (isSafeQueryFieldIdentifier(queryFields[i])) {
                        safeQueryFields.push(queryFields[i]);
                    }
                }
                if (safeQueryFields.length === 0) {
                    return { error: 'Select at least one query field for this type.' };
                }

                config.query = {
                    operator: normalizeOperator(getInputValue('project-search-field-operator')),
                    fields: safeQueryFields
                };
            }

            if (isOptionsType(type)) {
                var optionsObject = getOptionsObjectFromEditor();
                if (Object.keys(optionsObject).length > 0) {
                    config.options = optionsObject;
                }
            }

            return {
                error: '',
                config: config,
                key: getFilterKey(config)
            };
        }

        function createDragHandle() {
            var handle = document.createElement('span');
            handle.className = 'project-search-filter-handle bi bi-list';
            handle.setAttribute('title', 'Drag to reorder');
            handle.style.cursor = 'move';
            handle.style.color = '#6c757d';
            handle.style.fontSize = '1rem';
            handle.style.flexShrink = '0';
            return handle;
        }

        function renderFilterRow(row, config, key) {
            var isNewline = String(config.type || '').trim() === 'newline';

            row.className = 'list-group-item d-flex align-items-center gap-2 project-search-filter-row';
            row.setAttribute('data-filter-key', key);
            row.setAttribute('data-filter-config', JSON.stringify(config || {}));
            if (isNewline) {
                row.setAttribute('data-filter-newline', '1');
            } else {
                row.removeAttribute('data-filter-newline');
            }
            row.innerHTML = '';
            row.style.background = '';

            var handle = createDragHandle();
            row.appendChild(handle);

            if (isNewline) {
                row.style.background = '#f8f9fa';

                var center = document.createElement('div');
                center.className = 'd-flex align-items-center gap-2 flex-grow-1';

                var icon = document.createElement('i');
                icon.className = 'bi bi-arrow-return-left text-muted';
                center.appendChild(icon);

                var line = document.createElement('hr');
                line.className = 'flex-grow-1 m-0';
                center.appendChild(line);

                var badge = document.createElement('span');
                badge.className = 'badge text-bg-light text-muted';
                badge.textContent = 'New row';
                center.appendChild(badge);

                var line2 = document.createElement('hr');
                line2.className = 'flex-grow-1 m-0';
                center.appendChild(line2);

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-outline-danger btn-sm';
                removeBtn.setAttribute('data-filter-action', 'remove');
                removeBtn.textContent = 'Remove';

                row.appendChild(center);
                row.appendChild(removeBtn);
                return;
            }

            var left = document.createElement('div');
            left.className = 'd-flex flex-column flex-grow-1';

            var title = document.createElement('strong');
            var titleText = String(config.label || '').trim();
            if (!titleText) {
                titleText = String(config.type || 'filter');
            }
            title.textContent = titleText;
            left.appendChild(title);

            var meta = document.createElement('div');
            meta.className = 'small text-muted d-flex align-items-center gap-2 flex-wrap';

            var typeBadge = document.createElement('span');
            typeBadge.className = 'badge text-bg-secondary';
            var normalizedTypeLabel = normalizeFilterTypeForEditor(config.type || '') || String(config.type || '');
            typeBadge.textContent = normalizedTypeLabel === 'search_all' ? 'search all' : normalizedTypeLabel;
            meta.appendChild(typeBadge);

            if (config.name) {
                var nameCode = document.createElement('code');
                nameCode.textContent = String(config.name);
                meta.appendChild(nameCode);
            }

            var queryFields = [];
            if (config.query && Array.isArray(config.query.fields)) {
                queryFields = config.query.fields;
            }
            if (isSearchAllType(config.type || '')) {
                var queryAllText = document.createElement('span');
                queryAllText.textContent = 'Fields: all';
                meta.appendChild(queryAllText);
            } else if (queryFields.length > 0) {
                var queryText = document.createElement('span');
                queryText.textContent = 'Fields: ' + queryFields.join(', ');
                meta.appendChild(queryText);
            }

            left.appendChild(meta);

            var right = document.createElement('div');
            right.className = 'd-flex align-items-center gap-2 flex-wrap ms-auto';

            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'btn btn-outline-secondary btn-sm';
            editBtn.setAttribute('data-filter-action', 'edit');
            editBtn.textContent = 'Edit';
            right.appendChild(editBtn);

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-outline-danger btn-sm';
            removeBtn.setAttribute('data-filter-action', 'remove');
            removeBtn.textContent = 'Remove';
            right.appendChild(removeBtn);

            row.appendChild(left);
            row.appendChild(right);
        }

        function upsertFilterRow(config, key, previousEditKey, skipRefresh) {
            if (!key) {
                return;
            }

            var rowByNewKey = findRowByKey(key);
            var rowByEditKey = previousEditKey ? findRowByKey(previousEditKey) : null;

            if (rowByEditKey && rowByNewKey && rowByEditKey !== rowByNewKey) {
                rowByEditKey.remove();
            }

            var targetRow = rowByNewKey || rowByEditKey;
            var isNew = !targetRow;
            if (!targetRow) {
                targetRow = document.createElement('article');
                list.appendChild(targetRow);
            }

            renderFilterRow(targetRow, config, key);
            if (isNew) {
                makeRowDraggable(targetRow);
            }
            if (skipRefresh !== true) {
                updateEmptyState();
            }
        }

        function hydrateInitialFilters(rawFilters) {
            if (!Array.isArray(rawFilters)) {
                return;
            }
            for (var i = 0; i < rawFilters.length; i += 1) {
                var filterConfig = rawFilters[i];
                if (!filterConfig || typeof filterConfig !== 'object') {
                    continue;
                }
                var key = getFilterKey(filterConfig);
                if (!key) {
                    continue;
                }
                upsertFilterRow(filterConfig, key, '', true);
            }
        }

        function resetRowsFromFilters(rawFilters) {
            list.innerHTML = '';
            newlineCounter = 0;
            hydrateInitialFilters(rawFilters);
            initSortable();
            updateEmptyState();
        }

        function applyPayloadToState(rawPayload) {
            var normalized = normalizeInitialConfig(rawPayload);
            searchMeta.search_mode = normalized.search_mode;
            searchMeta.auto_buttons = normalized.auto_buttons;
            resetRowsFromFilters(normalized.filters);
            if (urlParamsEditor && typeof urlParamsEditor.setUrlParams === 'function') {
                urlParamsEditor.setUrlParams(normalized.url_params);
            }
        }

        function setSaveButtonBusy(isBusy) {
            isSaving = !!isBusy;
            saveBtn.disabled = isSaving;
            saveBtn.classList.toggle('disabled', isSaving);
            if (isSaving) {
                saveBtn.setAttribute('aria-disabled', 'true');
            } else {
                saveBtn.removeAttribute('aria-disabled');
            }
        }

        function fillFormFromConfig(config, key) {
            var rowConfig = normalizeFilterConfigForEditor(config && typeof config === 'object' ? config : {}) || {
                type: 'search_all',
                query: { operator: 'like', fields: [] },
                layout: 'inline'
            };
            setInputValue('project-search-field-type', rowConfig.type || 'search_all');
            setInputValue('project-search-field-label', rowConfig.label || rowConfig.name || '');
            setInputValue('project-search-field-operator', (rowConfig.query && rowConfig.query.operator) ? rowConfig.query.operator : 'like');
            setEditorOptionsFromConfig(rowConfig);
            updateTypeSpecificVisibility();

            var queryFields = [];
            if (rowConfig.query && Array.isArray(rowConfig.query.fields)) {
                queryFields = rowConfig.query.fields;
            }
            if (isSearchAllType(rowConfig.type || '')) {
                queryFields = [];
            }
            queryPicker.setSelectedNames(queryFields);
            queryPicker.clearPicker();

            editKeyInput.value = String(key || '');
        }

        function resetModalForAdd() {
            setFormError('');
            modalTitle.textContent = 'Add Search Field';
            submitBtn.textContent = 'Add';
            fillFormFromConfig({
                type: 'search_all',
                layout: 'inline',
                query: { operator: 'like', fields: [] }
            }, '');
        }

        function openModalForEdit(row) {
            var key = String(row.getAttribute('data-filter-key') || '');
            var config = safeParseJson(row.getAttribute('data-filter-config') || '{}', {});
            setFormError('');
            modalTitle.textContent = 'Edit Search Field';
            submitBtn.textContent = 'Save';
            fillFormFromConfig(config, key);

            if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                var instance = window.bootstrap.Modal.getOrCreateInstance(modal);
                instance.show();
                return;
            }
        }

        addFieldBtn.addEventListener('click', function () {
            resetModalForAdd();
        });

        if (addNewlineBtn) {
            addNewlineBtn.addEventListener('click', function () {
                var config = { type: 'newline' };
                var key = getFilterKey(config);
                upsertFilterRow(config, key, '');
            });
        }

        list.addEventListener('click', function (event) {
            var target = event.target;
            if (!target) {
                return;
            }

            var action = target.getAttribute('data-filter-action');
            if (!action) {
                return;
            }

            var row = target.closest('.project-search-filter-row');
            if (!row) {
                return;
            }

            if (action === 'remove') {
                row.remove();
                updateEmptyState();
                return;
            }

            if (action === 'edit') {
                openModalForEdit(row);
            }
        });

        typeInput.addEventListener('change', function () {
            if (isSearchAllType(typeInput.value || '')) {
                queryPicker.setSelectedNames([]);
                queryPicker.clearPicker();
                setInputValue('project-search-field-operator', 'like');
            }
            updateTypeSpecificVisibility();
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            setFormError('');

            var built = buildConfigFromForm();
            if (built.error) {
                setFormError(built.error);
                return;
            }

            var previousEditKey = String(editKeyInput.value || '');
            var keyToUse = previousEditKey || built.key;
            upsertFilterRow(built.config, keyToUse, previousEditKey);
            resetModalForAdd();
            closeModal(modal);
        });

        saveBtn.addEventListener('click', function (event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            if (isSaving) {
                return;
            }
            if (!moduleName || !saveUrl) {
                notify('Missing parameters for save.', 'danger');
                return;
            }

            var payload = buildCurrentPayload();
            setSaveButtonBusy(true);

            fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    module: moduleName,
                    config: payload
                })
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('HTTP ' + res.status);
                    }
                    return res.json();
                })
                .then(function (data) {
                    if (!data || data.success !== true) {
                        notify((data && data.msg) ? data.msg : 'Unable to save search filters.', 'danger');
                        return;
                    }

                    if (data.saved_config && typeof data.saved_config === 'object') {
                        applyPayloadToState(data.saved_config);
                    }

                    var msg = String(data.msg || 'Search filters saved.');
                    if (Array.isArray(data.warnings) && data.warnings.length > 0) {
                        msg += ' Warnings: ' + data.warnings.join(' | ');
                    }
                    notify(msg, 'success');
                    var redirectUrl = saveSuccessUrl || projectHomeUrl;
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    }
                })
                .catch(function (err) {
                    notify('Save error: ' + (err && err.message ? err.message : 'Unknown error'), 'danger');
                })
                .finally(function () {
                    setSaveButtonBusy(false);
                });
        });

        hydrateInitialFilters(initialConfig.filters);
        initSortable();
        updateEmptyState();
        updateTypeSpecificVisibility();
    }

    function initPage() {
        var page = byId('project-edit-filters-search-page');
        if (!page) {
            return;
        }

        if (String(page.getAttribute('data-can-edit') || '0') !== '1') {
            return;
        }

        var fields = normalizeFields(safeParseJsonScript('project-search-available-fields-data', []));
        var fieldLookup = {};
        for (var i = 0; i < fields.length; i += 1) {
            fieldLookup[fields[i].name.toLowerCase()] = fields[i];
        }
        var dbFields = normalizeDbFields(safeParseJsonScript('project-search-db-fields-data', []));
        var dbFieldLookup = {};
        for (var j = 0; j < dbFields.length; j += 1) {
            dbFieldLookup[dbFields[j].name.toLowerCase()] = dbFields[j];
        }
        var urlParamFieldSanitizer = initUrlParamFieldAutoSanitize(dbFieldLookup);

        var queryPicker = initQueryFieldPicker(fields, fieldLookup);
        if (!queryPicker) {
            return;
        }
        var initialConfig = safeParseJsonScript('project-search-initial-config-data', {
            search_mode: 'submit',
            auto_buttons: true,
            url_params: [],
            filters: []
        });
        var urlParamsEditor = initUrlParamsEditor(dbFieldLookup, initialConfig.url_params, urlParamFieldSanitizer);
        initSearchFieldsEditor(fields, fieldLookup, queryPicker, urlParamsEditor, initialConfig, {
            module: page.getAttribute('data-module') || '',
            saveUrl: page.getAttribute('data-save-url') || '',
            saveSuccessUrl: page.getAttribute('data-save-success-url') || '',
            projectHomeUrl: page.getAttribute('data-project-home-url') || ''
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initPage();
    });
})();
