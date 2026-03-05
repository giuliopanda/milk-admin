(function () {
    'use strict';

    var FIELD_TYPES = {
        string: {
            icon: 'bi-fonts',
            label: 'Text',
            description: 'Simple text field',
            dbType: 'varchar',
            dbTypeOptions: ['varchar', 'char'],
            parameters: []
        },
        text: {
            icon: 'bi-text-paragraph',
            label: 'Textarea',
            description: 'Long multiline text',
            dbType: 'text',
            dbTypeOptions: ['text', 'mediumtext', 'longtext', 'tinytext'],
            parameters: [
                { name: 'use_editor', label: 'Use Rich Text Editor', type: 'checkbox', def: false }
            ]
        },
        int: {
            icon: 'bi-123',
            label: 'Number',
            description: 'Integer or decimal number',
            dbType: 'int',
            dbTypeOptions: ['int', 'bigint', 'smallint', 'tinyint', 'decimal', 'float', 'double'],
            parameters: [
                { name: 'allow_negative', label: 'Allow negative values', type: 'checkbox', def: true },
                { name: 'decimals', label: 'Number of decimals', type: 'number', def: 0, min: 0, max: 10 }
            ],
            validation: ['min', 'max', 'step']
        },
        email: {
            icon: 'bi-envelope-fill',
            label: 'Email',
            description: 'Email address',
            dbType: 'varchar',
            dbTypeOptions: ['varchar'],
            parameters: []
        },
        tel: {
            icon: 'bi-telephone-fill',
            label: 'Phone',
            description: 'Phone number',
            dbType: 'varchar',
            dbTypeOptions: ['varchar', 'char'],
            parameters: []
        },
        url: {
            icon: 'bi-link-45deg',
            label: 'URL',
            description: 'Web address',
            dbType: 'varchar',
            dbTypeOptions: ['varchar', 'text'],
            parameters: []
        },
        date: {
            icon: 'bi-calendar-date',
            label: 'Date',
            description: 'Date without time',
            dbType: 'date',
            dbTypeOptions: ['date'],
            parameters: [],
            validation: ['min', 'max']
        },
        datetime: {
            icon: 'bi-calendar-event',
            label: 'Date & Time',
            description: 'Date with time',
            dbType: 'datetime',
            dbTypeOptions: ['datetime', 'timestamp'],
            parameters: [],
            validation: ['min', 'max']
        },
        time: {
            icon: 'bi-clock-fill',
            label: 'Time',
            description: 'Time only',
            dbType: 'time',
            dbTypeOptions: ['time'],
            parameters: []
        },
        boolean: {
            icon: 'bi-toggle-on',
            label: 'Checkbox',
            description: 'Yes/No',
            dbType: 'tinyint',
            dbTypeOptions: ['tinyint', 'char', 'varchar'],
            parameters: []
        },
        select: {
            icon: 'bi-list-ul',
            label: 'Select',
            description: 'Dropdown menu',
            dbType: 'varchar',
            dbTypeOptions: ['varchar', 'char', 'int', 'text', 'tinytext'],
            hasOptions: true,
            parameters: [
                { name: 'multiple', label: 'Multiple values', type: 'checkbox', def: false },
                { name: 'allow_empty', label: 'Allow empty value', type: 'checkbox', def: false }
            ]
        },
        relation: {
            icon: 'bi-diagram-3-fill',
            label: 'Relation',
            description: 'BelongsTo relation to another model',
            dbType: 'int',
            dbTypeOptions: ['int', 'bigint', 'varchar', 'char', 'tinytext', 'text'],
            hasRelationSource: true,
            parameters: [
                { name: 'multiple', label: 'Multiple values', type: 'checkbox', def: false }
            ]
        },
        radio: {
            icon: 'bi-ui-radios',
            label: 'Radio',
            description: 'Exclusive options',
            dbType: 'varchar',
            dbTypeOptions: ['varchar', 'char', 'int'],
            hasOptions: true,
            parameters: []
        },
        checkboxes: {
            icon: 'bi-ui-checks',
            label: 'Checkboxes',
            description: 'Multiple selection',
            dbType: 'text',
            dbTypeOptions: ['text', 'tinytext', 'mediumtext'],
            hasOptions: true,
            parameters: []
        },
        file: {
            icon: 'bi-file-earmark-arrow-up',
            label: 'File',
            description: 'File upload',
            dbType: 'text',
            dbTypeOptions: ['text', 'tinytext'],
            parameters: [
                { name: 'max_files', label: 'Max number of files', type: 'number', def: 1, min: 1, max: 999 }
            ]
        },
        image: {
            icon: 'bi-image-fill',
            label: 'Image',
            description: 'Image upload',
            dbType: 'tinytext',
            dbTypeOptions: ['tinytext', 'text'],
            parameters: [
                { name: 'max_files', label: 'Max number of files', type: 'number', def: 1, min: 1, max: 999 }
            ]
        },
        hidden: {
            icon: 'bi-eye-slash-fill',
            label: 'Hidden',
            description: 'Hidden input field',
            dbType: 'varchar',
            dbTypeOptions: ['varchar', 'char', 'text', 'int', 'bigint', 'smallint', 'tinyint', 'decimal', 'float', 'double'],
            parameters: []
        },
        html: {
            icon: 'bi-code-square',
            label: 'Custom HTML',
            description: 'Custom HTML content',
            dbType: 'text',
            dbTypeOptions: ['text', 'mediumtext', 'longtext'],
            parameters: [
                { name: 'html_content', label: 'HTML Content', type: 'textarea', def: '', rows: 4 }
            ]
        }
    };

    var DB_TYPE_PARAMS = {
        varchar: [
            { name: 'length', label: 'Length', type: 'number', def: 255, min: 1, max: 65535 }
        ],
        char: [
            { name: 'length', label: 'Length', type: 'number', def: 10, min: 1, max: 255 }
        ],
        decimal: [
            { name: 'digits', label: 'Total digits', type: 'number', def: 10, min: 1, max: 65 },
            { name: 'precision', label: 'Decimals', type: 'number', def: 2, min: 0, max: 30 }
        ],
        float: [
            { name: 'digits', label: 'Total digits', type: 'number', def: 10, min: 1, max: 53 },
            { name: 'precision', label: 'Decimals', type: 'number', def: 2, min: 0, max: 30 }
        ],
        double: [
            { name: 'digits', label: 'Total digits', type: 'number', def: 10, min: 1, max: 53 },
            { name: 'precision', label: 'Decimals', type: 'number', def: 2, min: 0, max: 30 }
        ],
        int: [
            { name: 'unsigned', label: 'Positive only (UNSIGNED)', type: 'checkbox', def: false }
        ],
        bigint: [
            { name: 'unsigned', label: 'Positive only (UNSIGNED)', type: 'checkbox', def: false }
        ],
        smallint: [
            { name: 'unsigned', label: 'Positive only (UNSIGNED)', type: 'checkbox', def: false }
        ],
        tinyint: [
            { name: 'unsigned', label: 'Positive only (UNSIGNED)', type: 'checkbox', def: false },
            { name: 'length', label: 'Display length', type: 'number', def: 1, min: 1, max: 4 }
        ]
    };
    var FIELD_NAME_MAX_LENGTH = 32;
    var FIELD_NAME_PATTERN = /^[a-z][a-z0-9_]*$/;
    var FIELD_NAME_DEFAULT_ERROR = 'Field name must start with a letter and contain only lowercase letters, numbers, and underscore.';
    var RESERVED_FIELD_NAME_RENAMES = {
        user: 'user_field'
    };
    var CALCULATED_VALUE_ALLOWED_TYPES = ['string', 'int', 'text', 'hidden'];
    var CUSTOM_ALIGNMENT_ALLOWED_TYPES = ['checkboxes', 'radio'];

    var TYPE_BEHAVIOR = {
        default: {
            showFieldProperties: true,
            showValidation: true,
            showHelpText: true,
            showShowIf: true
        },
        hidden: {
            showFieldProperties: false,
            showValidation: false,
            showHelpText: false,
            showShowIf: false
        }
    };

    var currentFieldType = 'string';
    var currentDbTypeParams = {};
    var optionsEditor = null;
    var initialized = false;
    var submitHandler = null;
    var mode = 'create';
    var currentModelDefinedField = false;
    var modalInstance = null;
    var relationTablesCache = null;
    var relationTablesFetchPromise = null;
    var relationColumnsCache = {};
    var relationColumnsRequestToken = 0;
    var relationListExtraFields = [];
    var optionsSourceColumnsRequestToken = {
        manual: 0,
        ajax: 0
    };

    function el(id) {
        return document.getElementById(id);
    }

    function normalizeBool(value) {
        if (typeof value === 'boolean') return value;
        if (typeof value === 'number') return value === 1;
        var normalized = String(value || '').trim().toLowerCase();
        return normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on';
    }

    function htmlEscape(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function normalizeOptionEntry(option) {
        if (!option || typeof option !== 'object') {
            return { value: '', label: '' };
        }
        return {
            value: String(option.value == null ? '' : option.value),
            label: String(option.label == null ? '' : option.label)
        };
    }

    function normalizeOptionsFromAny(input) {
        if (Array.isArray(input)) {
            return input.map(function (option, index) {
                if (option && typeof option === 'object') {
                    return normalizeOptionEntry(option);
                }
                return { value: String(index), label: String(option || '') };
            });
        }
        if (input && typeof input === 'object') {
            return Object.keys(input).map(function (valueKey) {
                return {
                    value: String(valueKey),
                    label: String(input[valueKey] || valueKey)
                };
            });
        }
        return [];
    }

    function optionsArrayToObject(options) {
        var result = {};
        if (!Array.isArray(options)) {
            return result;
        }
        options.forEach(function (option) {
            var entry = normalizeOptionEntry(option);
            var value = entry.value.trim();
            var label = entry.label.trim();
            if (value === '' && label === '') {
                return;
            }
            result[value] = label || value;
        });
        return result;
    }

    function getBuildFieldsPageRoot() {
        return document.getElementById('project-build-form-fields-page');
    }

    function getBuildFieldsPageDataAttribute(attributeName) {
        var root = getBuildFieldsPageRoot();
        if (!root) {
            return '';
        }
        return String(root.getAttribute(attributeName) || '').trim();
    }

    function normalizeStringArray(values) {
        if (!Array.isArray(values)) {
            return [];
        }
        var normalized = [];
        var seen = {};
        values.forEach(function (entry) {
            var value = String(entry || '').trim();
            if (!value) {
                return;
            }
            var lower = value.toLowerCase();
            if (seen[lower]) {
                return;
            }
            seen[lower] = true;
            normalized.push(value);
        });
        return normalized;
    }

    function normalizeSelectItems(values) {
        var items = [];
        if (Array.isArray(values)) {
            values.forEach(function (entry) {
                if (entry && typeof entry === 'object') {
                    var value = String(entry.value || entry.class || '').trim();
                    if (!value) return;
                    items.push({
                        value: value,
                        label: String(entry.label || entry.short_name || entry.shortName || value)
                    });
                    return;
                }
                var simple = String(entry || '').trim();
                if (!simple) return;
                items.push({ value: simple, label: simple });
            });
        } else if (values && typeof values === 'object') {
            Object.keys(values).forEach(function (key) {
                var value = String(key || '').trim();
                if (!value) return;
                items.push({
                    value: value,
                    label: String(values[key] || value)
                });
            });
        }

        var normalized = [];
        var seen = {};
        items.forEach(function (item) {
            var lower = item.value.toLowerCase();
            if (seen[lower]) return;
            seen[lower] = true;
            normalized.push(item);
        });
        return normalized;
    }

    function setSelectOptions(node, values, placeholder, selectedValue) {
        if (!node) {
            return;
        }
        var items = normalizeSelectItems(values);
        var selected = String(selectedValue || '').trim();
        var selectedLower = selected.toLowerCase();

        node.innerHTML = '';
        var placeholderNode = document.createElement('option');
        placeholderNode.value = '';
        placeholderNode.textContent = placeholder || 'Select';
        node.appendChild(placeholderNode);

        var hasSelectedInList = false;
        items.forEach(function (item) {
            var option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.label;
            if (selected && item.value.toLowerCase() === selectedLower) {
                option.selected = true;
                hasSelectedInList = true;
            }
            node.appendChild(option);
        });

        if (selected && !hasSelectedInList) {
            var missing = document.createElement('option');
            missing.value = selected;
            missing.textContent = selected + ' (not found)';
            missing.selected = true;
            node.appendChild(missing);
        }
    }

    function getRelationTablesUrl() {
        return getBuildFieldsPageDataAttribute('data-relation-models-url');
    }

    function getRelationColumnsUrl() {
        return getBuildFieldsPageDataAttribute('data-relation-model-fields-url');
    }

    function getProjectModulePage() {
        return getBuildFieldsPageDataAttribute('data-module-page');
    }

    function getColumnsForRelationTable(modelClass) {
        var key = String(modelClass || '').trim().toLowerCase();
        if (!key) {
            return [];
        }
        var meta = relationColumnsCache[key];
        if (!meta || typeof meta !== 'object') {
            return [];
        }
        return normalizeStringArray(meta.fields || []);
    }

    function getRelationModelMeta(modelClass) {
        var key = String(modelClass || '').trim().toLowerCase();
        if (!key) {
            return { fields: [], primary_field: 'id' };
        }
        var meta = relationColumnsCache[key];
        if (!meta || typeof meta !== 'object') {
            return { fields: [], primary_field: 'id' };
        }
        return {
            fields: normalizeStringArray(meta.fields || []),
            primary_field: String(meta.primary_field || '').trim()
        };
    }

    function chooseRelationValueField(modelMeta) {
        var meta = modelMeta && typeof modelMeta === 'object' ? modelMeta : { fields: [] };
        var list = normalizeStringArray(meta.fields || []);
        var primary = String(meta.primary_field || '').trim();
        if (primary && list.some(function (fieldName) { return fieldName.toLowerCase() === primary.toLowerCase(); })) {
            return primary;
        }
        for (var i = 0; i < list.length; i++) {
            if (list[i].toLowerCase() === 'id') {
                return list[i];
            }
        }
        if (list.length > 0) {
            return list[0];
        }
        return 'id';
    }

    function setRelationLabelColumns(columns, selectedColumn, placeholder) {
        var labelNode = el('relationLabelColumn');
        if (!labelNode) {
            return;
        }
        setSelectOptions(labelNode, columns, placeholder || 'Select label column', selectedColumn || '');
        labelNode.disabled = normalizeStringArray(columns).length === 0;
    }

    function buildRelationAliasFromModel(modelClass) {
        var cleanClass = String(modelClass || '').trim();
        if (!cleanClass) {
            return '';
        }
        var parts = cleanClass.split('\\');
        var shortName = parts.length > 0 ? parts[parts.length - 1] : cleanClass;
        shortName = shortName.replace(/Model$/i, '');
        shortName = shortName.replace(/[^A-Za-z0-9_]+/g, '_');
        shortName = shortName.replace(/([a-z0-9])([A-Z])/g, '$1_$2');
        shortName = shortName.replace(/_+/g, '_');
        shortName = shortName.replace(/^_+|_+$/g, '');
        return shortName.toLowerCase();
    }

    function normalizeRelationAliasValue(value) {
        var normalized = String(value || '').trim();
        if (!normalized) return '';
        normalized = normalized.replace(/[^A-Za-z0-9_]+/g, '_');
        normalized = normalized.replace(/([a-z0-9])([A-Z])/g, '$1_$2');
        normalized = normalized.replace(/_+/g, '_');
        normalized = normalized.replace(/^_+|_+$/g, '');
        normalized = normalized.toLowerCase();
        if (!normalized) return '';
        if (/^[0-9]/.test(normalized)) {
            normalized = 'rel_' + normalized;
        }
        return normalized;
    }

    function syncRelationAliasFromModelIfNeeded(modelClass) {
        var aliasNode = el('relationAliasName');
        if (!aliasNode) {
            return;
        }
        var currentValue = String(aliasNode.value || '').trim();
        var autoFlag = aliasNode.getAttribute('data-auto-generated') !== 'false';
        if (!currentValue || autoFlag) {
            aliasNode.value = buildRelationAliasFromModel(modelClass);
            aliasNode.setAttribute('data-auto-generated', 'true');
        }
    }

    function resetRelationSourceControls() {
        var modelNode = el('relationTableSource');
        if (modelNode) {
            setSelectOptions(modelNode, relationTablesCache || [], 'Select model', '');
            modelNode.disabled = false;
        }
        var aliasNode = el('relationAliasName');
        if (aliasNode) {
            aliasNode.value = '';
            aliasNode.setAttribute('data-auto-generated', 'true');
        }
        setRelationLabelColumns([], '', 'Select model first');
    }

    function fetchRelationTables() {
        if (Array.isArray(relationTablesCache)) {
            return Promise.resolve(relationTablesCache);
        }

        if (relationTablesFetchPromise) {
            return relationTablesFetchPromise;
        }

        var url = getRelationTablesUrl();
        if (!url) {
            return Promise.resolve([]);
        }

        relationTablesFetchPromise = fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (payload) {
                var models = normalizeSelectItems(payload && payload.models);
                relationTablesCache = models;
                return models;
            })
            .catch(function () {
                relationTablesCache = null;
                return [];
            })
            .finally(function () {
                relationTablesFetchPromise = null;
            });

        return relationTablesFetchPromise;
    }

    function fetchRelationColumns(modelClass) {
        var cleanModelClass = String(modelClass || '').trim();
        if (!cleanModelClass) {
            return Promise.resolve({ fields: [], primary_field: '' });
        }

        var cachedFields = getColumnsForRelationTable(cleanModelClass);
        if (cachedFields.length > 0) {
            var cachedMeta = getRelationModelMeta(cleanModelClass);
            return Promise.resolve({
                fields: cachedMeta.fields,
                primary_field: cachedMeta.primary_field
            });
        }

        var columnsUrl = getRelationColumnsUrl();
        if (!columnsUrl) {
            return Promise.resolve({ fields: [], primary_field: '' });
        }

        var separator = columnsUrl.indexOf('?') === -1 ? '?' : '&';
        var url = columnsUrl + separator + 'model=' + encodeURIComponent(cleanModelClass);

        return fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (payload) {
                var fields = normalizeStringArray(payload && payload.fields);
                var primaryField = String((payload && payload.primary_field) || '').trim();
                relationColumnsCache[cleanModelClass.toLowerCase()] = {
                    fields: fields,
                    primary_field: primaryField
                };
                return {
                    fields: fields,
                    primary_field: primaryField
                };
            });
    }

    function loadRelationTables(selectedModel) {
        var modelNode = el('relationTableSource');
        if (!modelNode) {
            return Promise.resolve([]);
        }

        setSelectOptions(modelNode, [], 'Loading models...', '');
        modelNode.disabled = true;

        return fetchRelationTables().then(function (models) {
            if (models.length > 0) {
                setSelectOptions(modelNode, models, 'Select model', selectedModel || '');
                modelNode.disabled = false;
            } else {
                setSelectOptions(modelNode, [], 'Unable to load models', '');
                modelNode.disabled = true;
            }
            return models;
        });
    }

    function loadRelationColumns(modelClass, selectedColumn) {
        var cleanModelClass = String(modelClass || '').trim();
        if (!cleanModelClass) {
            setRelationLabelColumns([], '', 'Select model first');
            return Promise.resolve([]);
        }

        var token = ++relationColumnsRequestToken;
        setRelationLabelColumns([], '', 'Loading fields...');

        return fetchRelationColumns(cleanModelClass)
            .then(function (meta) {
                var fields = normalizeStringArray(meta && meta.fields);
                if (token !== relationColumnsRequestToken) {
                    return fields;
                }
                setRelationLabelColumns(fields, selectedColumn || '', fields.length > 0 ? 'Select label column' : 'No fields found');
                return fields;
            })
            .catch(function () {
                if (token === relationColumnsRequestToken) {
                    setRelationLabelColumns([], '', 'Unable to load fields');
                }
                return [];
            });
    }

    function applyRelationSelection(modelClass, labelField, relationAlias) {
        var cleanModelClass = String(modelClass || '').trim();
        var cleanLabelField = String(labelField || '').trim();
        var cleanAlias = normalizeRelationAliasValue(relationAlias || '');
        return loadRelationTables(cleanModelClass).then(function () {
            var modelNode = el('relationTableSource');
            if (modelNode) {
                modelNode.value = cleanModelClass;
            }
            var aliasNode = el('relationAliasName');
            if (aliasNode) {
                aliasNode.value = cleanAlias;
                aliasNode.setAttribute('data-auto-generated', cleanAlias ? 'false' : 'true');
            }
            if (!cleanAlias) {
                syncRelationAliasFromModelIfNeeded(cleanModelClass);
            }
            return loadRelationColumns(cleanModelClass, cleanLabelField);
        });
    }

    function initRelationSourceHandlers() {
        var modelNode = el('relationTableSource');
        if (!modelNode) {
            return;
        }

        modelNode.addEventListener('change', function () {
            var modelClass = String(modelNode.value || '').trim();
            syncRelationAliasFromModelIfNeeded(modelClass);
            loadRelationColumns(modelClass, '').then(function () {
                refreshRelationListFieldOptions(false);
            });
        });

        var aliasNode = el('relationAliasName');
        if (aliasNode) {
            aliasNode.addEventListener('input', function () {
                aliasNode.setAttribute('data-auto-generated', 'false');
                aliasNode.setCustomValidity('');
                aliasNode.classList.remove('is-invalid');
            });
            aliasNode.addEventListener('blur', function () {
                aliasNode.value = normalizeRelationAliasValue(aliasNode.value || '');
                aliasNode.setCustomValidity('');
                aliasNode.classList.remove('is-invalid');
            });
        }

        modelNode.addEventListener('change', function () {
            modelNode.setCustomValidity('');
            modelNode.classList.remove('is-invalid');
        });
    }

    function validateRelationSourceRequired() {
        var modelNode = el('relationTableSource');
        var aliasNode = el('relationAliasName');
        if (!modelNode || !aliasNode) {
            return true;
        }

        // Only required for relation field type.
        if (currentFieldType !== 'relation') {
            modelNode.setCustomValidity('');
            aliasNode.setCustomValidity('');
            modelNode.classList.remove('is-invalid');
            aliasNode.classList.remove('is-invalid');
            return true;
        }

        var modelValue = String(modelNode.value || '').trim();
        var aliasValue = normalizeRelationAliasValue(aliasNode.value || '');
        aliasNode.value = aliasValue;

        var valid = true;
        if (modelValue === '') {
            modelNode.setCustomValidity('Select model first.');
            modelNode.classList.add('is-invalid');
            valid = false;
        } else {
            modelNode.setCustomValidity('');
            modelNode.classList.remove('is-invalid');
        }

        if (aliasValue === '') {
            aliasNode.setCustomValidity('Relation name is required.');
            aliasNode.classList.add('is-invalid');
            valid = false;
        } else {
            aliasNode.setCustomValidity('');
            aliasNode.classList.remove('is-invalid');
        }

        return valid;
    }

    function getOptionsSourceContext(modeKey) {
        var key = String(modeKey || '').trim().toLowerCase();
        if (key !== 'ajax') {
            key = 'manual';
        }
        return {
            key: key,
            modelNode: el(key === 'ajax' ? 'tableSource' : 'tableSourceManual'),
            valueNode: el(key === 'ajax' ? 'tableValueField' : 'tableValueFieldManual'),
            labelNode: el(key === 'ajax' ? 'tableLabelField' : 'tableLabelFieldManual')
        };
    }

    function setOptionsSourceColumns(context, columns, selectedValue, selectedLabel, placeholder) {
        if (!context || !context.valueNode || !context.labelNode) {
            return;
        }
        var list = normalizeStringArray(columns);
        var emptyPlaceholder = placeholder || 'Select model first';
        var fieldPlaceholder = list.length > 0 ? 'Select field' : emptyPlaceholder;
        setSelectOptions(context.valueNode, list, fieldPlaceholder, selectedValue || '');
        setSelectOptions(context.labelNode, list, fieldPlaceholder, selectedLabel || '');
        var disabled = list.length === 0;
        context.valueNode.disabled = disabled;
        context.labelNode.disabled = disabled;
    }

    function loadOptionsSourceModels(selectedManualModel, selectedAjaxModel) {
        var manualContext = getOptionsSourceContext('manual');
        var ajaxContext = getOptionsSourceContext('ajax');
        var contexts = [manualContext, ajaxContext].filter(function (entry) {
            return !!entry.modelNode;
        });
        if (contexts.length === 0) {
            return Promise.resolve([]);
        }

        contexts.forEach(function (context) {
            setSelectOptions(context.modelNode, [], 'Loading models...', '');
            context.modelNode.disabled = true;
        });

        return fetchRelationTables().then(function (models) {
            contexts.forEach(function (context) {
                var selected = context.key === 'ajax'
                    ? String(selectedAjaxModel || '').trim()
                    : String(selectedManualModel || '').trim();
                if (models.length > 0) {
                    setSelectOptions(context.modelNode, models, 'Select model', selected);
                    context.modelNode.disabled = false;
                } else {
                    setSelectOptions(context.modelNode, [], 'Unable to load models', '');
                    context.modelNode.disabled = true;
                }
            });
            return models;
        });
    }

    function loadOptionsSourceColumns(modeKey, modelClass, selectedValueField, selectedLabelField) {
        var context = getOptionsSourceContext(modeKey);
        if (!context.valueNode || !context.labelNode) {
            return Promise.resolve([]);
        }
        var cleanModelClass = String(modelClass || '').trim();
        if (!cleanModelClass) {
            setOptionsSourceColumns(context, [], '', '', 'Select model first');
            return Promise.resolve([]);
        }

        var tokenKey = context.key === 'ajax' ? 'ajax' : 'manual';
        var token = ++optionsSourceColumnsRequestToken[tokenKey];
        setOptionsSourceColumns(context, [], '', '', 'Loading fields...');

        return fetchRelationColumns(cleanModelClass)
            .then(function (meta) {
                var fields = normalizeStringArray(meta && meta.fields);
                if (token !== optionsSourceColumnsRequestToken[tokenKey]) {
                    return fields;
                }
                setOptionsSourceColumns(
                    context,
                    fields,
                    selectedValueField || '',
                    selectedLabelField || '',
                    fields.length > 0 ? 'Select field' : 'No fields found'
                );
                return fields;
            })
            .catch(function () {
                if (token === optionsSourceColumnsRequestToken[tokenKey]) {
                    setOptionsSourceColumns(context, [], '', '', 'Unable to load fields');
                }
                return [];
            });
    }

    function applyOptionsSourceSelection(modeKey, modelClass, valueField, labelField) {
        var cleanModelClass = String(modelClass || '').trim();
        var cleanValueField = String(valueField || '').trim();
        var cleanLabelField = String(labelField || '').trim();
        return loadOptionsSourceModels(
            modeKey === 'ajax' ? '' : cleanModelClass,
            modeKey === 'ajax' ? cleanModelClass : ''
        ).then(function () {
            var context = getOptionsSourceContext(modeKey);
            if (context.modelNode) {
                context.modelNode.value = cleanModelClass;
            }
            return loadOptionsSourceColumns(modeKey, cleanModelClass, cleanValueField, cleanLabelField);
        });
    }

    function resetOptionsSourceControls() {
        loadOptionsSourceModels('', '').then(function () {
            setOptionsSourceColumns(getOptionsSourceContext('manual'), [], '', '', 'Select model first');
            setOptionsSourceColumns(getOptionsSourceContext('ajax'), [], '', '', 'Select model first');
        });
    }

    function initOptionsSourceHandlers() {
        ['manual', 'ajax'].forEach(function (modeKey) {
            var context = getOptionsSourceContext(modeKey);
            if (!context.modelNode) {
                return;
            }
            context.modelNode.addEventListener('change', function () {
                var modelClass = String(context.modelNode.value || '').trim();
                loadOptionsSourceColumns(modeKey, modelClass, '', '');
            });
        });
    }

    function createManualBulkOptionsEditor(config) {
        var cfg = config && typeof config === 'object' ? config : {};
        var root = cfg.root || null;
        if (!root) {
            return null;
        }

        var tabs = root.querySelectorAll(cfg.tabsSelector || '.options-tab');
        var panels = root.querySelectorAll(cfg.panelsSelector || '.options-panel');
        var list = root.querySelector(cfg.listSelector || '.options-sortable-list');
        var bulkField = root.querySelector(cfg.bulkSelector || '.options-bulk-input, textarea');
        var addBtn = root.querySelector(cfg.addButtonSelector || '.btn-add-option');
        var fallback = [{ value: '', label: '' }];
        var options = fallback.slice();
        var sortable = null;

        function render() {
            if (!list) {
                return;
            }
            list.innerHTML = '';
            options.forEach(function (option, index) {
                var item = document.createElement('div');
                item.className = 'option-item add-field-sortable-option-item';
                item.setAttribute('data-index', String(index));
                item.innerHTML =
                    '<div class="option-handle"><i class="bi bi-grip-vertical"></i></div>' +
                    '<div class="option-inputs">' +
                    '<input type="text" class="form-control" placeholder="value" value="' + htmlEscape(option.value) + '" data-field="value">' +
                    '<input type="text" class="form-control" placeholder="Label" value="' + htmlEscape(option.label) + '" data-field="label">' +
                    '</div>' +
                    '<button type="button" class="btn-remove-option" data-remove-index="' + index + '"><i class="bi bi-trash"></i></button>';
                list.appendChild(item);
            });
        }

        function ensureSortable() {
            if (!list) {
                return;
            }
            if (sortable && typeof sortable.destroy === 'function') {
                sortable.destroy();
            }
            if (cfg.sortable === false || typeof ItoSortableList === 'undefined') {
                sortable = null;
                return;
            }
            sortable = new ItoSortableList(list, {
                handleSelector: '.option-handle',
                onUpdate: function () {
                    syncFromManual();
                }
            });
        }

        function syncFromManual() {
            if (!list) {
                return;
            }
            var items = list.querySelectorAll('.option-item');
            options = [];
            items.forEach(function (item) {
                var valueInput = item.querySelector('[data-field="value"]');
                var labelInput = item.querySelector('[data-field="label"]');
                options.push({
                    value: valueInput ? String(valueInput.value || '') : '',
                    label: labelInput ? String(labelInput.value || '') : ''
                });
            });
        }

        function exportToBulk() {
            syncFromManual();
            if (!bulkField) {
                return;
            }
            var bulkValue = options
                .filter(function (option) {
                    return String(option.value || '').trim() !== '' || String(option.label || '').trim() !== '';
                })
                .map(function (option) {
                    var value = String(option.value || '').trim();
                    var label = String(option.label || '').trim() || value;
                    return value + '|' + label;
                })
                .join('\n');
            bulkField.value = bulkValue;
        }

        function importFromBulk() {
            if (!bulkField) {
                return;
            }
            var lines = String(bulkField.value || '').split('\n');
            options = lines
                .filter(function (line) {
                    return String(line || '').trim() !== '';
                })
                .map(function (line) {
                    var parts = String(line || '').split('|');
                    var value = String(parts[0] || '').trim();
                    var label = String(parts[1] || '').trim() || value;
                    return {
                        value: value,
                        label: label
                    };
                });
            if (options.length === 0) {
                options = fallback.slice();
            }
            render();
            ensureSortable();
        }

        function getActiveTabName() {
            var activeTab = root.querySelector('.options-tab.active');
            return activeTab ? String(activeTab.getAttribute('data-tab') || 'manual') : 'manual';
        }

        function setActiveTab(tabName) {
            var target = String(tabName || '').trim().toLowerCase() === 'bulk' ? 'bulk' : 'manual';
            var current = getActiveTabName();
            if (current === 'manual' && target === 'bulk') {
                exportToBulk();
            } else if (current === 'bulk' && target === 'manual') {
                importFromBulk();
            }

            tabs.forEach(function (tab) {
                tab.classList.toggle('active', tab.getAttribute('data-tab') === target);
            });
            panels.forEach(function (panel) {
                panel.classList.toggle('active', panel.getAttribute('data-panel') === target);
            });
        }

        function syncFromActiveTab() {
            var active = getActiveTabName();
            if (active === 'bulk') {
                importFromBulk();
                setActiveTab('manual');
                return;
            }
            syncFromManual();
        }

        function addOption() {
            options.push({ value: '', label: '' });
            render();
            ensureSortable();
        }

        function removeOption(index) {
            var idx = parseInt(String(index || ''), 10);
            if (isNaN(idx) || options.length <= 1) {
                return;
            }
            options.splice(idx, 1);
            if (options.length === 0) {
                options = fallback.slice();
            }
            render();
            ensureSortable();
        }

        function setOptions(nextOptions) {
            var normalized = normalizeOptionsFromAny(nextOptions);
            options = normalized.length > 0 ? normalized : fallback.slice();
            render();
            ensureSortable();
            exportToBulk();
        }

        function getOptionsArray() {
            syncFromActiveTab();
            return options.map(function (option) {
                return normalizeOptionEntry(option);
            });
        }

        function getOptionsObject() {
            return optionsArrayToObject(getOptionsArray());
        }

        if (list) {
            list.addEventListener('input', function () {
                syncFromManual();
            });
            list.addEventListener('click', function (event) {
                var target = event.target;
                if (!target) {
                    return;
                }
                var removeBtn = target.closest('[data-remove-index]');
                if (!removeBtn) {
                    return;
                }
                removeOption(removeBtn.getAttribute('data-remove-index'));
            });
        }
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                addOption();
            });
        }
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                setActiveTab(tab.getAttribute('data-tab'));
            });
        });

        setOptions([]);
        setActiveTab('manual');

        return {
            setActiveTab: setActiveTab,
            syncFromActiveTab: syncFromActiveTab,
            addOption: addOption,
            removeOption: removeOption,
            setOptions: setOptions,
            getOptionsArray: getOptionsArray,
            getOptionsObject: getOptionsObject,
            exportToBulk: exportToBulk,
            importFromBulk: importFromBulk,
            destroy: function () {
                if (sortable && typeof sortable.destroy === 'function') {
                    sortable.destroy();
                }
                sortable = null;
            }
        };
    }

    function isCalculatedValueSupportedType(typeKey) {
        return CALCULATED_VALUE_ALLOWED_TYPES.indexOf(String(typeKey || '').trim()) !== -1;
    }

    function isCustomAlignmentSupportedType(typeKey) {
        return CUSTOM_ALIGNMENT_ALLOWED_TYPES.indexOf(String(typeKey || '').trim()) !== -1;
    }

    function normalizeCustomAlignmentValue(value) {
        var normalized = String(value || '').trim().toLowerCase();
        if (normalized === 'horizontal') {
            return 'horizontal';
        }
        if (normalized === 'vertical') {
            return 'vertical_1';
        }
        if (
            normalized === 'vertical_1'
            || normalized === 'vertical_2'
            || normalized === 'vertical_3'
            || normalized === 'vertical_4'
        ) {
            return normalized;
        }
        return '';
    }

    function humanizeName(value) {
        var normalized = String(value || '').trim().replace(/[_-]+/g, ' ');
        normalized = normalized.replace(/\s+/g, ' ');
        if (!normalized) return '';
        return normalized.replace(/\b\w/g, function (char) {
            return char.toUpperCase();
        });
    }

    function getFieldTypeLabel(typeKey) {
        var normalized = String(typeKey || '').trim();
        if (normalized && FIELD_TYPES[normalized]) {
            return FIELD_TYPES[normalized].label;
        }
        if (!normalized) {
            return FIELD_TYPES.string ? FIELD_TYPES.string.label : 'Text';
        }
        return humanizeName(normalized);
    }

    function generateFieldName(label) {
        return String(label || '')
            .toLowerCase()
            .replace(/[àáâãäå]/g, 'a')
            .replace(/[èéêë]/g, 'e')
            .replace(/[ìíîï]/g, 'i')
            .replace(/[òóôõö]/g, 'o')
            .replace(/[ùúûü]/g, 'u')
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function buildUniqueFieldNameCandidate(baseName, existingNamesMap, originalLower) {
        var base = String(baseName || '').trim().toLowerCase();
        if (!base) {
            base = 'field';
        }
        if (base.length > FIELD_NAME_MAX_LENGTH) {
            base = base.substring(0, FIELD_NAME_MAX_LENGTH);
        }

        var candidate = base;
        var suffix = 2;
        while (existingNamesMap[candidate] && candidate !== originalLower) {
            var suffixToken = '_' + suffix;
            var maxBaseLength = FIELD_NAME_MAX_LENGTH - suffixToken.length;
            var truncatedBase = base.length > maxBaseLength ? base.substring(0, Math.max(0, maxBaseLength)) : base;
            candidate = truncatedBase + suffixToken;
            suffix++;
        }
        return candidate;
    }

    function applyReservedFieldNameRename(candidate, existingNamesMap, originalLower) {
        var clean = String(candidate || '').trim().toLowerCase();
        if (!clean) {
            return {
                name: '',
                renamed: false
            };
        }

        var replacement = RESERVED_FIELD_NAME_RENAMES[clean];
        if (!replacement) {
            return {
                name: clean,
                renamed: false
            };
        }

        if (mode === 'edit' && originalLower === clean) {
            return {
                name: clean,
                renamed: false
            };
        }

        return {
            name: buildUniqueFieldNameCandidate(replacement, existingNamesMap, originalLower),
            renamed: true
        };
    }

    function getFieldNameFeedbackNode() {
        var feedback = el('fieldNameInvalidFeedback');
        if (feedback) return feedback;
        var nameNode = el('fieldName');
        if (!nameNode || !nameNode.parentNode) return null;
        return nameNode.parentNode.querySelector('.invalid-feedback');
    }

    function setFieldNameValidity(errorMessage) {
        var nameNode = el('fieldName');
        if (!nameNode) return;

        var feedbackNode = getFieldNameFeedbackNode();
        var message = String(errorMessage || '').trim();
        if (message !== '') {
            nameNode.classList.add('is-invalid');
            nameNode.setCustomValidity(message);
            if (feedbackNode) {
                feedbackNode.textContent = message;
            }
            return;
        }

        nameNode.classList.remove('is-invalid');
        nameNode.setCustomValidity('');
        if (feedbackNode && feedbackNode.textContent.trim() === '') {
            feedbackNode.textContent = FIELD_NAME_DEFAULT_ERROR;
        }
    }

    function getExistingFieldNamesMap() {
        var map = {};
        var rows = document.querySelectorAll('#project-form-fields-list .project-form-field-row');
        rows.forEach(function (row) {
            if (!row) return;
            var name = '';
            var raw = row.getAttribute('data-field-config') || '';
            if (raw !== '') {
                try {
                    var parsed = JSON.parse(raw);
                    if (parsed && typeof parsed === 'object') {
                        name = String(parsed.field_name || '').trim();
                    }
                } catch (err) {
                    name = '';
                }
            }
            if (name === '') {
                var nameNode = row.querySelector('.project-form-field-name');
                name = String(nameNode ? nameNode.textContent : '').trim();
            }
            if (name !== '') {
                map[name.toLowerCase()] = true;
            }
        });
        return map;
    }

    function validateFieldName() {
        var nameNode = el('fieldName');
        if (!nameNode) return true;

        var labelNode = el('fieldLabel');
        var rawName = String(nameNode.value || '');
        var trimmedName = rawName.trim();
        if (trimmedName !== rawName) {
            nameNode.value = trimmedName;
        }

        var label = String(labelNode ? labelNode.value : '').trim();
        var candidate = trimmedName !== '' ? trimmedName : generateFieldName(label);
        if (candidate === '') {
            setFieldNameValidity('');
            return true;
        }

        if (candidate.length > FIELD_NAME_MAX_LENGTH) {
            setFieldNameValidity('Field name cannot exceed ' + FIELD_NAME_MAX_LENGTH + ' characters.');
            return false;
        }
        if (!FIELD_NAME_PATTERN.test(candidate)) {
            setFieldNameValidity(FIELD_NAME_DEFAULT_ERROR);
            return false;
        }

        var existingNamesMap = getExistingFieldNamesMap();
        var originalLower = String(nameNode.getAttribute('data-original-field-name') || '').trim().toLowerCase();
        var reservedNameInfo = applyReservedFieldNameRename(candidate, existingNamesMap, originalLower);
        if (reservedNameInfo.name !== '' && reservedNameInfo.name !== candidate) {
            candidate = reservedNameInfo.name;
            nameNode.value = candidate;
            nameNode.setAttribute('data-auto-generated', 'true');
        }

        var candidateLower = candidate.toLowerCase();
        if (existingNamesMap[candidateLower] && candidateLower !== originalLower) {
            setFieldNameValidity('Field name "' + candidate + '" is already used in this form.');
            return false;
        }

        setFieldNameValidity('');
        return true;
    }

    function initMainTabs() {
        var tabs = document.querySelectorAll('#fieldBuilderModal .main-tab');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = this.getAttribute('data-maintab');
                document.querySelectorAll('#fieldBuilderModal .main-tab').forEach(function (item) {
                    item.classList.remove('active');
                });
                document.querySelectorAll('#fieldBuilderModal .main-tab-content').forEach(function (item) {
                    item.classList.remove('active');
                });
                this.classList.add('active');
                var content = document.querySelector('#fieldBuilderModal [data-maintab-content="' + target + '"]');
                if (content) {
                    content.classList.add('active');
                }
            });
        });
    }

    function initFieldTypeSelector() {
        var typeGrid = el('fieldTypeOptions');
        var trigger = el('typeTrigger');
        var gridWrapper = el('typeGrid');
        if (!typeGrid || !trigger || !gridWrapper) {
            return;
        }

        typeGrid.innerHTML = '';

        Object.keys(FIELD_TYPES).forEach(function (typeKey) {
            var type = FIELD_TYPES[typeKey];
            var option = document.createElement('div');
            option.className = 'field-type-option';
            option.setAttribute('data-type', typeKey);
            if (typeKey === 'string') {
                option.classList.add('selected');
            }
            option.innerHTML = '<i class="bi ' + type.icon + '"></i><span>' + htmlEscape(type.label) + '</span>';
            option.addEventListener('click', function () {
                selectFieldType(typeKey);
            });
            typeGrid.appendChild(option);
        });

        trigger.addEventListener('click', function (evt) {
            evt.stopPropagation();
            gridWrapper.classList.toggle('show');
            trigger.classList.toggle('active');
        });

        document.addEventListener('click', function (evt) {
            if (!gridWrapper.contains(evt.target) && !trigger.contains(evt.target)) {
                gridWrapper.classList.remove('show');
                trigger.classList.remove('active');
            }
        });
    }

    function selectFieldType(typeKey) {
        if (!FIELD_TYPES[typeKey]) {
            typeKey = 'string';
        }

        currentFieldType = typeKey;
        var type = FIELD_TYPES[typeKey];

        document.querySelectorAll('#fieldBuilderModal .field-type-option').forEach(function (option) {
            option.classList.toggle('selected', option.getAttribute('data-type') === typeKey);
        });

        var typeCurrent = el('typeCurrent');
        if (typeCurrent) {
            typeCurrent.innerHTML =
                '<i class="bi ' + type.icon + '"></i>' +
                '<div><div class="type-current-title">' + htmlEscape(type.label) + '</div>' +
                '<small class="type-current-description">' + htmlEscape(type.description) + '</small></div>';
        }

        var fieldTypeInput = el('fieldType');
        if (fieldTypeInput) {
            fieldTypeInput.value = typeKey;
        }

        currentDbTypeParams = {};
        var dbType = el('dbType');
        if (dbType) {
            dbType.value = type.dbType;
        }

        var gridWrapper = el('typeGrid');
        var trigger = el('typeTrigger');
        if (gridWrapper) gridWrapper.classList.remove('show');
        if (trigger) trigger.classList.remove('active');

        updateDBTypeDisplay();
        updateTypeParameters();
        updateValidationParams();

        var optionsSection = el('optionsSection');
        if (!optionsSection) return;

        if (type.hasOptions || type.hasTableSource || type.hasRelationSource) {
            optionsSection.style.display = 'block';
            var manualGroup = el('optionsManualGroup');
            var tableGroup = el('optionsTableGroup');
            var relationGroup = el('relationSourceGroup');
            if (type.hasRelationSource) {
                if (manualGroup) manualGroup.style.display = 'none';
                if (tableGroup) tableGroup.style.display = 'none';
                if (relationGroup) relationGroup.style.display = 'block';
                resetRelationSourceControls();
                loadRelationTables('').then(function () {
                    setRelationLabelColumns([], '', 'Select model first');
                });
            } else if (type.hasTableSource) {
                if (manualGroup) manualGroup.style.display = 'none';
                if (tableGroup) tableGroup.style.display = 'block';
                if (relationGroup) relationGroup.style.display = 'none';
                resetOptionsSourceControls();
            } else {
                if (manualGroup) manualGroup.style.display = 'block';
                if (tableGroup) tableGroup.style.display = 'none';
                if (relationGroup) relationGroup.style.display = 'none';
                initOptionsList();
                toggleOptionsMode();
            }
        } else {
            optionsSection.style.display = 'none';
            var relationGroupNode = el('relationSourceGroup');
            if (relationGroupNode) relationGroupNode.style.display = 'none';
        }

        updateTypeUi();
    }

    function getCurrentTypeBehavior() {
        var behavior = Object.assign({}, TYPE_BEHAVIOR.default);
        if (TYPE_BEHAVIOR[currentFieldType]) {
            behavior = Object.assign(behavior, TYPE_BEHAVIOR[currentFieldType]);
        }
        return behavior;
    }

    function setGroupVisibility(groupNode, isVisible) {
        if (!groupNode) return;
        groupNode.classList.toggle('d-none', !isVisible);
    }

    function updateReadonlyVisibility() {
        var readonlyNode = el('fieldReadonly');
        var readonlyGroupNode = el('fieldReadonlyGroup');
        if (!readonlyNode) {
            return;
        }

        var readonlyAllowed = currentFieldType !== 'hidden';
        if (!readonlyAllowed) {
            readonlyNode.checked = false;
        }
        readonlyNode.disabled = !readonlyAllowed;

        if (readonlyGroupNode) {
            readonlyGroupNode.classList.toggle('d-none', !readonlyAllowed);
        }
    }

    function updateTypeUi() {
        var isCustomHtml = currentFieldType === 'html';
        var behavior = getCurrentTypeBehavior();
        var hideDefaultValue = currentFieldType === 'checkboxes' || currentFieldType === 'radio';

        var infoNode = el('customHtmlFieldInfo');
        if (infoNode) {
            infoNode.classList.toggle('d-none', !isCustomHtml);
        }

        var advancedTabButton = document.querySelector('#fieldBuilderModal .main-tab[data-maintab="advanced"]');
        var advancedTabContent = document.querySelector('#fieldBuilderModal [data-maintab-content="advanced"]');
        if (advancedTabButton) {
            advancedTabButton.style.display = '';
        }
        if (advancedTabContent) {
            advancedTabContent.style.display = '';
        }

        var advancedBehaviorNode = el('advancedBehavior');
        if (advancedBehaviorNode) {
            advancedBehaviorNode.style.display = isCustomHtml ? 'none' : '';
        }

        setGroupVisibility(el('fieldPropertiesSection'), !isCustomHtml && behavior.showFieldProperties);
        setGroupVisibility(el('defaultValueGroup'), !hideDefaultValue);
        setGroupVisibility(el('validationSection'), !isCustomHtml && behavior.showValidation);
        setGroupVisibility(el('helpTextGroup'), !isCustomHtml && behavior.showHelpText);
        setGroupVisibility(el('showIfGroup'), isCustomHtml || behavior.showShowIf);

        if (currentFieldType === 'hidden') {
            var requiredNode = el('fieldRequired');
            if (requiredNode) requiredNode.checked = false;
        }
        updateReadonlyVisibility();
        syncRelationListOptionsVisibility();

        var excludeNode = document.querySelector('#fieldBuilderModal [name="exclude_from_db"]');
        if (excludeNode) {
            if (isCustomHtml) {
                excludeNode.checked = true;
                excludeNode.disabled = true;
            } else {
                excludeNode.disabled = false;
            }
        }

        updateDbTypeVisibilityByExcludeFlag();
        updateCalculatedValueAvailability(isCustomHtml);
        updateCustomAlignmentAvailability(isCustomHtml);
        updateModelFieldRestrictionUi();
    }

    function updateModelFieldRestrictionUi() {
        var isRestrictedEdit = mode === 'edit' && currentModelDefinedField;
        var isCustomHtml = currentFieldType === 'html';
        var noticeNode = el('modelFieldEditNotice');
        if (noticeNode) {
            noticeNode.classList.toggle('d-none', !isRestrictedEdit);
        }

        var identityNode = el('modelFieldIdentityInfo');
        if (identityNode) {
            identityNode.classList.toggle('d-none', !isRestrictedEdit);
        }
        if (isRestrictedEdit) {
            var readonlyNameNode = el('modelFieldReadonlyName');
            var readonlyTypeNode = el('modelFieldReadonlyType');
            var currentNameNode = el('fieldName');
            if (readonlyNameNode) {
                readonlyNameNode.textContent = String(currentNameNode ? (currentNameNode.value || '') : '').trim();
            }
            if (readonlyTypeNode) {
                readonlyTypeNode.textContent = getFieldTypeLabel(currentFieldType);
            }
        }

        var fieldNameInputGroupNode = el('fieldNameInputGroup');
        if (fieldNameInputGroupNode) {
            fieldNameInputGroupNode.classList.toggle('d-none', isRestrictedEdit);
        }

        var fieldTypeSectionNode = el('fieldTypeSection');
        if (fieldTypeSectionNode) {
            fieldTypeSectionNode.classList.toggle('d-none', isRestrictedEdit);
        }

        var databaseTypeSectionNode = el('databaseTypeSection');
        if (databaseTypeSectionNode) {
            databaseTypeSectionNode.classList.toggle('d-none', isRestrictedEdit || isCustomHtml);
        }

        var fieldPropertiesSectionNode = el('fieldPropertiesSection');
        if (fieldPropertiesSectionNode) {
            fieldPropertiesSectionNode.classList.toggle('d-none', isRestrictedEdit || isCustomHtml);
        }

        var validationSectionNode = el('validationSection');
        if (validationSectionNode) {
            validationSectionNode.classList.toggle('d-none', isRestrictedEdit || isCustomHtml);
        }

        var advancedNonVisibilityGroupNode = el('advancedNonVisibilityGroup');
        if (advancedNonVisibilityGroupNode) {
            advancedNonVisibilityGroupNode.classList.toggle('d-none', isRestrictedEdit);
        }

        var advancedBehaviorNode = el('advancedBehavior');
        if (advancedBehaviorNode && isRestrictedEdit) {
            advancedBehaviorNode.style.display = '';
        }

        if (isRestrictedEdit || isCustomHtml) {
            cancelDbTypeEdit();
        }
    }

    function updateCalculatedValueAvailability(isCustomHtml) {
        var calcExprGroupNode = el('calcExprGroup');
        var calcExprNode = document.querySelector('#fieldBuilderModal [name="calc_expr"]');
        if (!calcExprNode) return;

        var isSupportedType = !isCustomHtml && isCalculatedValueSupportedType(currentFieldType);
        if (calcExprGroupNode) {
            calcExprGroupNode.classList.toggle('d-none', !isSupportedType);
        }

        if (!isSupportedType) {
            calcExprNode.value = '';
        }

        calcExprNode.disabled = !isSupportedType;
        calcExprNode.setCustomValidity('');
    }

    function updateCustomAlignmentAvailability(isCustomHtml) {
        var customAlignmentGroupNode = el('customAlignmentGroup');
        var customAlignmentNode = document.querySelector('#fieldBuilderModal [name="custom_alignment"]');
        if (!customAlignmentNode) return;

        var isSupportedType = !isCustomHtml && isCustomAlignmentSupportedType(currentFieldType);
        if (customAlignmentGroupNode) {
            customAlignmentGroupNode.classList.toggle('d-none', !isSupportedType);
        }

        if (!isSupportedType) {
            customAlignmentNode.value = '';
        } else if (normalizeCustomAlignmentValue(customAlignmentNode.value) === '') {
            customAlignmentNode.value = 'vertical_1';
        }

        customAlignmentNode.disabled = !isSupportedType;
    }

    function updateDBTypeDisplay() {
        var dbTypeInput = el('dbType');
        var display = el('dbTypeDisplay');
        if (!dbTypeInput || !display) {
            return;
        }

        var dbType = dbTypeInput.value;
        var dbTypeStr = dbType.toUpperCase();

        if (currentDbTypeParams.length !== undefined) {
            if (dbType === 'varchar' || dbType === 'char') {
                dbTypeStr = dbType.toUpperCase() + '(' + (currentDbTypeParams.length || 255) + ')';
            }
        }

        if (currentDbTypeParams.digits !== undefined) {
            if (dbType === 'decimal' || dbType === 'float' || dbType === 'double') {
                dbTypeStr = dbType.toUpperCase() + '(' + (currentDbTypeParams.digits || 10) + ',' + (currentDbTypeParams.precision || 2) + ')';
            } else if (dbType === 'tinyint') {
                dbTypeStr = 'TINYINT(' + (currentDbTypeParams.length || 1) + ')';
            }
        }

        if (currentDbTypeParams.unsigned) {
            dbTypeStr += ' UNSIGNED';
        }

        display.textContent = dbTypeStr;
    }

    function updateTypeParameters() {
        var type = FIELD_TYPES[currentFieldType];
        var container = el('typeParameters');
        if (!container || !type) {
            return;
        }

        if (!type.parameters || type.parameters.length === 0) {
            container.innerHTML = '';
            return;
        }

        var html = '<div class="mt-3">';
        // Group consecutive checkboxes into a single flex row
        var checkboxGroup = [];
        type.parameters.forEach(function (param, idx) {
            var fieldId = 'param_' + param.name;
            if (param.type === 'checkbox') {
                checkboxGroup.push(
                    '<div class="form-check form-switch">' +
                    '<input class="form-check-input me-2" type="checkbox" name="' + fieldId + '" id="' + fieldId + '"' + (param.def ? ' checked' : '') + '>' +
                    '<label class="form-check-label" for="' + fieldId + '">' + htmlEscape(param.label) + '</label>' +
                    '</div>'
                );
                // Flush if next param is not a checkbox or this is the last param
                var nextParam = type.parameters[idx + 1];
                if (!nextParam || nextParam.type !== 'checkbox') {
                    if (checkboxGroup.length > 1) {
                        html += '<div class="d-flex gap-4 mb-2">' + checkboxGroup.join('') + '</div>';
                    } else {
                        html += '<div class="mb-2">' + checkboxGroup[0] + '</div>';
                    }
                    checkboxGroup = [];
                }
            } else if (param.type === 'textarea') {
                html += '<div class="mb-3">' +
                    '<label class="form-label" for="' + fieldId + '">' + htmlEscape(param.label) + '</label>' +
                    '<textarea class="form-control code-input" name="' + fieldId + '" id="' + fieldId + '" rows="' + (param.rows || 3) + '">' + htmlEscape(param.def || '') + '</textarea>' +
                    '</div>';
            } else if (param.type === 'number') {
                html += '<div class="mb-3">' +
                    '<label class="form-label" for="' + fieldId + '">' + htmlEscape(param.label) + '</label>' +
                    '<input type="number" class="form-control" name="' + fieldId + '" id="' + fieldId + '" value="' + htmlEscape(param.def || '') + '"' +
                    (param.min !== undefined ? ' min="' + param.min + '"' : '') +
                    (param.max !== undefined ? ' max="' + param.max + '"' : '') + '>' +
                    '</div>';
            }
        });
        html += '</div>';
        container.innerHTML = html;

        container.querySelectorAll('input, textarea, select').forEach(function (field) {
            field.addEventListener('change', handleParamChange);
            field.addEventListener('input', handleParamChange);
        });
    }

    function handleParamChange() {
        if (currentFieldType === 'int') {
            var decimalsNode = document.querySelector('#fieldBuilderModal [name="param_decimals"]');
            var allowNegativeNode = document.querySelector('#fieldBuilderModal [name="param_allow_negative"]');
            var decimals = parseInt(decimalsNode ? decimalsNode.value : '0', 10);
            var allowNegative = allowNegativeNode ? allowNegativeNode.checked : true;
            var dbTypeNode = el('dbType');
            if (!dbTypeNode) return;

            if (decimals > 0) {
                if (['decimal', 'float', 'double'].indexOf(dbTypeNode.value) === -1) {
                    dbTypeNode.value = 'decimal';
                    currentDbTypeParams = { digits: 10, precision: decimals };
                } else {
                    currentDbTypeParams.precision = decimals;
                }
            } else if (['decimal', 'float', 'double'].indexOf(dbTypeNode.value) !== -1) {
                dbTypeNode.value = 'int';
                currentDbTypeParams = {};
            }

            if (!allowNegative) currentDbTypeParams.unsigned = true;
            else delete currentDbTypeParams.unsigned;
            updateDBTypeDisplay();
        }

        // When Multiple values is toggled for select/relation, switch dbType to tinytext
        if (currentFieldType === 'select' || currentFieldType === 'relation') {
            var multipleNode = document.querySelector('#fieldBuilderModal [name="param_multiple"]');
            if (multipleNode && multipleNode.checked) {
                var dbTypeNode = el('dbType');
                if (dbTypeNode && ['tinytext', 'text', 'varchar'].indexOf(dbTypeNode.value) === -1) {
                    dbTypeNode.value = 'tinytext';
                    currentDbTypeParams = {};
                    updateDBTypeDisplay();
                }
            }
        }
    }

    function initDbTypeEditor() {
        var trigger = el('dbTypeTrigger');
        var editorWrapper = el('dbTypeEditor');
        if (!trigger || !editorWrapper) {
            return;
        }

        trigger.addEventListener('click', function (evt) {
            evt.stopPropagation();
            openDbTypeEditor();
        });

        document.addEventListener('click', function (evt) {
            if (!editorWrapper.contains(evt.target) && !trigger.contains(evt.target)) {
                editorWrapper.classList.remove('show');
                trigger.classList.remove('active');
            }
        });
    }

    function updateDbTypeVisibilityByExcludeFlag() {
        var wrapper = el('dbTypeSelectorWrapper');
        if (!wrapper) return;

        var excludeNode = document.querySelector('#fieldBuilderModal [name="exclude_from_db"]');
        var isExcludedFromDb = excludeNode ? excludeNode.checked : false;
        wrapper.classList.toggle('d-none', isExcludedFromDb);
        if (isExcludedFromDb) {
            cancelDbTypeEdit();
        }
    }

    function initExcludeFromDbHandlers() {
        var excludeNode = document.querySelector('#fieldBuilderModal [name="exclude_from_db"]');
        if (!excludeNode) return;
        excludeNode.addEventListener('change', function () {
            updateDbTypeVisibilityByExcludeFlag();
        });
    }

    function openDbTypeEditor() {
        var type = FIELD_TYPES[currentFieldType];
        var dbTypeSelect = el('dbTypeSelect');
        var dbTypeNode = el('dbType');
        var editorWrapper = el('dbTypeEditor');
        var trigger = el('dbTypeTrigger');
        if (!type || !dbTypeSelect || !dbTypeNode || !editorWrapper || !trigger) {
            return;
        }

        if (editorWrapper.classList.contains('show')) {
            editorWrapper.classList.remove('show');
            trigger.classList.remove('active');
            return;
        }

        dbTypeSelect.innerHTML = '';
        type.dbTypeOptions.forEach(function (dbTypeOpt) {
            var option = document.createElement('option');
            option.value = dbTypeOpt;
            option.textContent = dbTypeOpt.toUpperCase();
            if (dbTypeOpt === dbTypeNode.value) option.selected = true;
            dbTypeSelect.appendChild(option);
        });

        updateDbEditorParams();

        editorWrapper.classList.add('show');
        trigger.classList.add('active');
    }

    function cancelDbTypeEdit() {
        var editorWrapper = el('dbTypeEditor');
        var trigger = el('dbTypeTrigger');
        if (editorWrapper) editorWrapper.classList.remove('show');
        if (trigger) trigger.classList.remove('active');
    }

    function updateDbEditorParams() {
        var dbTypeSelect = el('dbTypeSelect');
        var container = el('dbEditorParams');
        if (!dbTypeSelect || !container) {
            return;
        }

        var dbType = dbTypeSelect.value;
        var params = DB_TYPE_PARAMS[dbType] || [];
        if (params.length === 0) {
            container.innerHTML = '<p class="text-muted mb-0">No parameters available for this type.</p>';
            return;
        }

        var html = '';
        params.forEach(function (param) {
            var currentValue = currentDbTypeParams[param.name] !== undefined ? currentDbTypeParams[param.name] : param.def;
            if (param.type === 'checkbox') {
                html += '<div class="form-check form-switch mb-3">' +
                    '<input class="form-check-input me-2" type="checkbox" id="dbparam_' + param.name + '"' + (currentValue ? ' checked' : '') + '>' +
                    '<label class="form-check-label" for="dbparam_' + param.name + '">' + htmlEscape(param.label) + '</label>' +
                    '</div>';
            } else {
                html += '<div class="mb-3">' +
                    '<label class="form-label">' + htmlEscape(param.label) + '</label>' +
                    '<input type="' + htmlEscape(param.type) + '" class="form-control" id="dbparam_' + param.name + '" value="' + htmlEscape(currentValue) + '"' +
                    (param.min !== undefined ? ' min="' + param.min + '"' : '') +
                    (param.max !== undefined ? ' max="' + param.max + '"' : '') + '>' +
                    '</div>';
            }
        });
        container.innerHTML = html;
    }

    function applyDbType() {
        var dbTypeSelect = el('dbTypeSelect');
        var dbTypeNode = el('dbType');
        if (!dbTypeSelect || !dbTypeNode) return;
        var dbType = dbTypeSelect.value;
        var params = DB_TYPE_PARAMS[dbType] || [];
        currentDbTypeParams = {};
        params.forEach(function (param) {
            var input = el('dbparam_' + param.name);
            if (!input) return;
            currentDbTypeParams[param.name] = param.type === 'checkbox' ? input.checked : input.value;
        });
        dbTypeNode.value = dbType;
        updateDBTypeDisplay();
        cancelDbTypeEdit();
    }

    function updateValidationParams() {
        var container = el('validationParams');
        if (!container) return;
        var isNumericType = currentFieldType === 'int';
        var showMin = isNumericType;
        var showMax = isNumericType;
        var showStep = isNumericType;
        var minMaxInputType = 'number';
        var minMaxStepAttr = ' step="any"';

        var html = '';
        if (showMin) {
            html += '<div class="col-md-4"><label class="form-label">Minimum value</label><input type="' + minMaxInputType + '" class="form-control form-control-sm" name="min"' + minMaxStepAttr + '></div>';
        }
        if (showMax) {
            html += '<div class="col-md-4"><label class="form-label">Maximum value</label><input type="' + minMaxInputType + '" class="form-control form-control-sm" name="max"' + minMaxStepAttr + '></div>';
        }
        if (showStep) {
            html += '<div class="col-md-4"><label class="form-label">Step</label><input type="number" class="form-control form-control-sm" name="step" step="any"></div>';
        }
        container.innerHTML = html;
    }

    function initOptionsEditor() {
        var root = el('manualBulkPanel');
        if (!root) {
            optionsEditor = null;
            return;
        }
        if (optionsEditor && typeof optionsEditor.destroy === 'function') {
            optionsEditor.destroy();
        }
        optionsEditor = createManualBulkOptionsEditor({
            root: root,
            listSelector: '#optionsList',
            bulkSelector: '#optionsBulk',
            addButtonSelector: '.btn-add-option',
            sortable: true
        });
    }

    function initOptionsTabs() {}

    function setActiveOptionsTab(tabName) {
        if (!optionsEditor || typeof optionsEditor.setActiveTab !== 'function') {
            return;
        }
        optionsEditor.setActiveTab(tabName);
    }

    function initOptionsList() {
        if (!optionsEditor || typeof optionsEditor.setOptions !== 'function') {
            return;
        }
        optionsEditor.setOptions([]);
    }

    function renderOptionsList() {}

    function addOption() {
        if (!optionsEditor || typeof optionsEditor.addOption !== 'function') {
            return;
        }
        optionsEditor.addOption();
    }

    function removeOption(index) {
        if (!optionsEditor || typeof optionsEditor.removeOption !== 'function') {
            return;
        }
        optionsEditor.removeOption(index);
    }

    function syncOptionsData() {}

    function exportToBulk() {
        if (!optionsEditor || typeof optionsEditor.exportToBulk !== 'function') {
            return;
        }
        optionsEditor.exportToBulk();
    }

    function importBulkOptions() {
        if (!optionsEditor || typeof optionsEditor.importFromBulk !== 'function') {
            return;
        }
        optionsEditor.importFromBulk();
    }

    function syncOptionsFromActiveTab() {
        if (!optionsEditor || typeof optionsEditor.syncFromActiveTab !== 'function') {
            return;
        }
        optionsEditor.syncFromActiveTab();
    }

    function toggleOptionsMode() {
        var modeNode = document.querySelector('#fieldBuilderModal input[name="options_mode"]:checked');
        var selectedMode = modeNode ? modeNode.value : 'manual';
        var manualBulkPanel = el('manualBulkPanel');
        var tablePanel = el('tableSourcePanel');
        var relationPanel = el('relationSourceGroup');
        if (!manualBulkPanel || !tablePanel) return;
        var isSelectType = currentFieldType === 'select';
        if (selectedMode === 'manual') {
            manualBulkPanel.style.display = 'block';
            tablePanel.style.display = 'none';
            if (isSelectType && relationPanel) {
                relationPanel.style.display = 'none';
            }
        } else {
            manualBulkPanel.style.display = 'none';
            if (isSelectType) {
                tablePanel.style.display = 'none';
                if (relationPanel) {
                    relationPanel.style.display = 'block';
                }
                var relationModelNode = el('relationTableSource');
                var selectedModel = String(relationModelNode ? relationModelNode.value : '').trim();
                loadRelationTables(selectedModel).then(function () {
                    if (!selectedModel) {
                        setRelationLabelColumns([], '', 'Select model first');
                    }
                });
            } else {
                tablePanel.style.display = 'block';
                if (relationPanel) {
                    relationPanel.style.display = 'none';
                }
            }
        }
    }

    function initAutoNameHandlers() {
        var label = el('fieldLabel');
        var name = el('fieldName');
        if (!label || !name) return;
        label.addEventListener('input', function () {
            if (!name.value || name.getAttribute('data-auto-generated') === 'true') {
                name.value = generateFieldName(label.value || '');
                name.setAttribute('data-auto-generated', 'true');
            }
            validateFieldName();
        });
        name.addEventListener('input', function () {
            name.setAttribute('data-auto-generated', 'false');
            validateFieldName();
        });
        name.addEventListener('blur', validateFieldName);
    }

    var listChangeValuesEditor = null;

    function normalizeRelationListExtraFields(values) {
        if (!Array.isArray(values)) {
            return [];
        }
        var normalized = [];
        var seen = {};
        values.forEach(function (entry) {
            var fieldName = '';
            var customLabel = '';

            if (entry && typeof entry === 'object' && !Array.isArray(entry)) {
                fieldName = String(entry.field || entry.name || '').trim();
                customLabel = String(entry.label || '').trim();
            } else {
                fieldName = String(entry || '').trim();
            }

            if (!fieldName) {
                return;
            }
            if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(fieldName)) {
                return;
            }
            var lower = fieldName.toLowerCase();
            if (seen[lower]) {
                return;
            }
            seen[lower] = true;
            normalized.push({
                field: fieldName,
                label: customLabel
            });
        });
        return normalized;
    }

    function setRelationListFieldSelectOptions(fields, selectedField, placeholder) {
        var selectNode = el('listRelationFieldSelect');
        if (!selectNode) {
            return;
        }
        var options = normalizeStringArray(fields || []);
        setSelectOptions(selectNode, options, placeholder || 'Select relation field', selectedField || '');
        selectNode.disabled = options.length === 0;
    }

    function renderRelationListExtraFields() {
        var container = el('listRelationFieldsSelected');
        if (!container) {
            return;
        }
        container.innerHTML = '';

        if (!Array.isArray(relationListExtraFields) || relationListExtraFields.length === 0) {
            var emptyNode = document.createElement('div');
            emptyNode.className = 'relation-list-fields-empty';
            emptyNode.textContent = 'No extra relation fields selected';
            container.appendChild(emptyNode);
            return;
        }

        relationListExtraFields.forEach(function (entry) {
            var rowNode = document.createElement('div');
            rowNode.className = 'relation-list-field-row';
            rowNode.setAttribute('data-relation-field-row', entry.field);

            var nameNode = document.createElement('div');
            nameNode.className = 'relation-list-field-name';
            nameNode.textContent = entry.field;
            rowNode.appendChild(nameNode);

            var labelInputNode = document.createElement('input');
            labelInputNode.type = 'text';
            labelInputNode.className = 'form-control form-control-sm relation-list-field-label';
            labelInputNode.placeholder = 'Label column (optional)';
            labelInputNode.value = String(entry.label || '');
            labelInputNode.setAttribute('data-relation-field-label-for', entry.field);
            rowNode.appendChild(labelInputNode);

            var removeNode = document.createElement('button');
            removeNode.type = 'button';
            removeNode.className = 'btn btn-outline-danger btn-sm relation-list-field-remove';
            removeNode.setAttribute('data-remove-relation-field', entry.field);
            removeNode.setAttribute('aria-label', 'Remove field ' + entry.field);
            removeNode.innerHTML = '<i class="bi bi-x-lg"></i>';
            rowNode.appendChild(removeNode);

            container.appendChild(rowNode);
        });
    }

    function refreshRelationListFieldOptions(keepCurrentSelection) {
        var selectNode = el('listRelationFieldSelect');
        if (!selectNode) {
            return Promise.resolve([]);
        }

        var relationModel = String(el('relationTableSource') ? el('relationTableSource').value : '').trim();
        // For non-relation field types, allow using the currently selected model source if available.
        if (!relationModel) {
            relationModel = String(el('tableSource') ? el('tableSource').value : '').trim();
        }
        if (!relationModel) {
            relationModel = String(el('tableSourceManual') ? el('tableSourceManual').value : '').trim();
        }
        if (!relationModel) {
            setRelationListFieldSelectOptions([], '', 'Select model first');
            return Promise.resolve([]);
        }

        var previousSelection = keepCurrentSelection ? String(selectNode.value || '').trim() : '';
        var selectedLabelField = String(el('relationLabelColumn') ? el('relationLabelColumn').value : '').trim();
        if (!selectedLabelField) {
            selectedLabelField = String(el('tableLabelField') ? el('tableLabelField').value : '').trim();
        }
        if (!selectedLabelField) {
            selectedLabelField = String(el('tableLabelFieldManual') ? el('tableLabelFieldManual').value : '').trim();
        }
        var selectedLabelFieldLower = selectedLabelField.toLowerCase();

        var applyFields = function (rawFields) {
            var allFields = normalizeStringArray(rawFields || []);
            var availableFields = allFields.filter(function (fieldName) {
                if (!fieldName) {
                    return false;
                }
                if (selectedLabelFieldLower && fieldName.toLowerCase() === selectedLabelFieldLower) {
                    return false;
                }
                return true;
            });

            var availableLookup = {};
            availableFields.forEach(function (fieldName) {
                availableLookup[fieldName.toLowerCase()] = true;
            });

            relationListExtraFields = normalizeRelationListExtraFields(relationListExtraFields).filter(function (entry) {
                return !!availableLookup[String(entry.field || '').toLowerCase()];
            });
            renderRelationListExtraFields();

            var selectedLookup = {};
            relationListExtraFields.forEach(function (entry) {
                selectedLookup[String(entry.field || '').toLowerCase()] = true;
            });
            var selectableFields = availableFields.filter(function (fieldName) {
                return !selectedLookup[fieldName.toLowerCase()];
            });

            var placeholder = selectableFields.length > 0
                ? 'Select relation field'
                : 'No more fields available';
            var selectedForSelect = '';
            if (previousSelection) {
                var previousLower = previousSelection.toLowerCase();
                for (var i = 0; i < selectableFields.length; i++) {
                    if (selectableFields[i].toLowerCase() === previousLower) {
                        selectedForSelect = selectableFields[i];
                        break;
                    }
                }
            }
            setRelationListFieldSelectOptions(selectableFields, selectedForSelect, placeholder);
            return availableFields;
        };

        var cachedFields = getColumnsForRelationTable(relationModel);
        if (cachedFields.length > 0) {
            applyFields(cachedFields);
            return Promise.resolve(cachedFields);
        }

        return loadRelationColumns(relationModel, selectedLabelField).then(function (fields) {
            applyFields(fields);
            return fields;
        });
    }

    function syncRelationListOptionsVisibility() {
        var relationGroupNode = el('listRelationFieldsGroup');
        if (!relationGroupNode) {
            return;
        }
        var showInListNode = el('showInList');
        var isRelationType = currentFieldType === 'relation';
        var visible = isRelationType && (!showInListNode || showInListNode.checked);
        relationGroupNode.classList.toggle('d-none', !visible);
        if (visible) {
            refreshRelationListFieldOptions(true);
        }
    }

    function enforceRequiredReadonlyExclusion(preferred) {
        var requiredNode = el('fieldRequired');
        var readonlyNode = el('fieldReadonly');
        if (!requiredNode || !readonlyNode) {
            return;
        }

        if (requiredNode.checked && readonlyNode.checked) {
            if (preferred === 'required') {
                readonlyNode.checked = false;
            } else {
                requiredNode.checked = false;
            }
        }
    }

    function initListOptionsHandlers() {
        var showInList = el('showInList');
        var optionsBlock = el('listOptionsBlock');
        var linkEnabled = el('listLinkEnabled');
        var linkOptions = el('listLinkOptions');
        var truncateEnabled = el('listTruncateEnabled');
        var truncateOptions = el('listTruncateOptions');
        var changeValuesEnabled = el('listChangeValuesEnabled');
        var changeValuesOptions = el('listChangeValuesOptions');
        var relationAddFieldBtn = el('listRelationFieldAddBtn');
        var relationFieldsSelect = el('listRelationFieldSelect');
        var relationSelectedContainer = el('listRelationFieldsSelected');

        if (!showInList || !optionsBlock) return;

        showInList.addEventListener('change', function () {
            optionsBlock.style.display = showInList.checked ? '' : 'none';
            if (!showInList.checked) {
                if (linkEnabled) { linkEnabled.checked = false; }
                if (linkOptions) linkOptions.style.display = 'none';
                if (truncateEnabled) { truncateEnabled.checked = false; }
                if (truncateOptions) truncateOptions.style.display = 'none';
                if (changeValuesEnabled) { changeValuesEnabled.checked = false; }
                if (changeValuesOptions) changeValuesOptions.style.display = 'none';
            }
            syncRelationListOptionsVisibility();
        });

        if (linkEnabled && linkOptions) {
            linkEnabled.addEventListener('change', function () {
                linkOptions.style.display = linkEnabled.checked ? '' : 'none';
            });
        }

        if (truncateEnabled && truncateOptions) {
            truncateEnabled.addEventListener('change', function () {
                truncateOptions.style.display = truncateEnabled.checked ? '' : 'none';
            });
        }

        if (changeValuesEnabled && changeValuesOptions) {
            changeValuesEnabled.addEventListener('change', function () {
                changeValuesOptions.style.display = changeValuesEnabled.checked ? '' : 'none';
            });
        }

        var editorRoot = el('listChangeValuesEditor');
        if (editorRoot) {
            listChangeValuesEditor = createManualBulkOptionsEditor({
                root: editorRoot,
                sortable: true
            });
        }

        if (relationAddFieldBtn && relationFieldsSelect) {
            relationAddFieldBtn.addEventListener('click', function () {
                var selectedField = String(relationFieldsSelect.value || '').trim();
                if (!selectedField) {
                    return;
                }
                relationListExtraFields.push({
                    field: selectedField,
                    label: ''
                });
                relationListExtraFields = normalizeRelationListExtraFields(relationListExtraFields);
                renderRelationListExtraFields();
                refreshRelationListFieldOptions(false);
            });
        }

        if (relationSelectedContainer) {
            relationSelectedContainer.addEventListener('click', function (evt) {
                var target = evt.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }
                var removeField = target.getAttribute('data-remove-relation-field')
                    || (target.closest('[data-remove-relation-field]') ? target.closest('[data-remove-relation-field]').getAttribute('data-remove-relation-field') : '');
                var cleanField = String(removeField || '').trim();
                if (!cleanField) {
                    return;
                }
                var lower = cleanField.toLowerCase();
                relationListExtraFields = normalizeRelationListExtraFields(relationListExtraFields).filter(function (entry) {
                    return String(entry.field || '').toLowerCase() !== lower;
                });
                renderRelationListExtraFields();
                refreshRelationListFieldOptions(true);
            });

            relationSelectedContainer.addEventListener('input', function (evt) {
                var target = evt.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }
                var inputField = target.getAttribute('data-relation-field-label-for');
                if (!inputField) {
                    return;
                }
                if (!('value' in target)) {
                    return;
                }
                var cleanField = String(inputField).trim().toLowerCase();
                relationListExtraFields = normalizeRelationListExtraFields(relationListExtraFields).map(function (entry) {
                    if (String(entry.field || '').toLowerCase() !== cleanField) {
                        return entry;
                    }
                    return {
                        field: entry.field,
                        label: String(target.value || '').trim()
                    };
                });
            });
        }

        var relationLabelNode = el('relationLabelColumn');
        if (relationLabelNode) {
            relationLabelNode.addEventListener('change', function () {
                refreshRelationListFieldOptions(true);
            });
        }

        renderRelationListExtraFields();
        syncRelationListOptionsVisibility();
    }

    function initFormHandlers() {
        var form = el('fieldBuilderForm');
        if (!form) return;

        var requiredNode = el('fieldRequired');
        var readonlyNode = el('fieldReadonly');
        if (requiredNode && readonlyNode) {
            requiredNode.addEventListener('change', function () {
                enforceRequiredReadonlyExclusion('required');
            });
            readonlyNode.addEventListener('change', function () {
                enforceRequiredReadonlyExclusion('readonly');
            });
        }

        form.addEventListener('submit', function (evt) {
            evt.preventDefault();
            validateFieldName();
            validateRelationSourceRequired();
            var calcExprNode = form.querySelector('[name="calc_expr"]');
            if (calcExprNode && !isCalculatedValueSupportedType(currentFieldType)) {
                calcExprNode.value = '';
            }
            if (!form.checkValidity()) {
                evt.stopPropagation();
                form.classList.add('was-validated');
                return;
            }

            var config = buildFieldConfig();
            if (typeof submitHandler === 'function') {
                submitHandler(config);
            }
            if (modalInstance && typeof modalInstance.hide === 'function') {
                modalInstance.hide();
            }
        });
    }

    function buildFieldConfig() {
        var form = el('fieldBuilderForm');
        var formData = new FormData(form);
        var label = String(formData.get('field_label') || '').trim();
        var name = String(formData.get('field_name') || '').trim();
        if (!name) name = generateFieldName(label);
        var nameNode = el('fieldName');
        var existingNamesMap = getExistingFieldNamesMap();
        var originalLower = String(nameNode ? nameNode.getAttribute('data-original-field-name') : '').trim().toLowerCase();
        var reservedNameInfo = applyReservedFieldNameRename(name, existingNamesMap, originalLower);
        if (reservedNameInfo.name !== '') {
            name = reservedNameInfo.name;
            if (nameNode) {
                nameNode.value = name;
                if (reservedNameInfo.renamed) {
                    nameNode.setAttribute('data-auto-generated', 'true');
                }
            }
        }

        var visibility = {
            list: formData.get('show_in_list') === 'on',
            edit: formData.get('show_in_edit') === 'on'
        };
        if (el('showInView')) {
            visibility.view = formData.get('show_in_view') === 'on';
        }

        var config = {
            field_name: name,
            field_label: label,
            type: String(formData.get('field_type') || 'string'),
            db_type: String(formData.get('db_type') || ''),
            db_type_params: currentDbTypeParams,
            form_type: String(formData.get('field_type') || 'string'),
            required: formData.get('required') === 'on',
            readonly: formData.get('readonly') === 'on',
            visibility: visibility
        };
        var isHiddenType = config.type === 'hidden';

        if (isHiddenType) {
            config.form_type = 'hidden';
            config.required = false;
            config.readonly = false;
        }
        if (config.readonly) {
            config.required = false;
        }
        if (config.required) {
            config.readonly = false;
        }

        if (config.type === 'int') {
            var decimals = parseInt(String(formData.get('param_decimals') || '0'), 10);
            config.number_decimals = decimals;
            config.number_allow_negative = formData.get('param_allow_negative') === 'on';
            config.form_type = 'number';
        }

        if (config.type === 'text' && formData.get('param_use_editor') === 'on') {
            config.form_type = 'editor';
        }

        if (config.type === 'html') {
            var htmlContent = formData.get('param_html_content');
            if (htmlContent) config.html_content = String(htmlContent);
            config.exclude_from_db = true;
        }

        if ((config.type === 'select' || config.type === 'relation') && formData.get('param_multiple') === 'on') {
            config.allow_multiple = true;
        }
        if (config.type === 'select' && formData.get('param_allow_empty') === 'on') {
            config.allow_empty = true;
        }
        if (config.type === 'file' || config.type === 'image') {
            var maxFiles = parseInt(String(formData.get('param_max_files') || '1'), 10);
            if (!isFinite(maxFiles) || maxFiles < 1) {
                maxFiles = 1;
            }
            config.max_files = maxFiles;
        }

        var validation = {};
        ['min', 'max', 'step', 'error_message', 'validate_expr'].forEach(function (key) {
            var val = formData.get(key);
            if (val !== null && String(val).trim() !== '') {
                validation[key] = String(val).trim();
            }
        });
        if (!isHiddenType && Object.keys(validation).length > 0) {
            config.validation = validation;
        }

        var typeInfo = FIELD_TYPES[config.type] || {};
        if (typeInfo.hasOptions) {
            var optionsMode = formData.get('options_mode');
            if (optionsMode === 'table') {
                if (config.type === 'select') {
                    var relationModelSelect = String(el('relationTableSource') ? el('relationTableSource').value : '').trim();
                    var relationLabelFieldSelect = String(el('relationLabelColumn') ? el('relationLabelColumn').value : '').trim();
                    var relationAliasSelect = normalizeRelationAliasValue(el('relationAliasName') ? el('relationAliasName').value : '');
                    var relationWhereSelect = String(el('relationWhere') ? el('relationWhere').value : '').trim();
                    if (el('relationAliasName')) {
                        el('relationAliasName').value = relationAliasSelect;
                    }
                    var relationMetaSelect = getRelationModelMeta(relationModelSelect);
                    var relationValueFieldSelect = chooseRelationValueField(relationMetaSelect);

                    if (relationModelSelect && relationLabelFieldSelect) {
                        config.options_source = {
                            mode: 'all',
                            model: relationModelSelect,
                            value_field: relationValueFieldSelect,
                            label_field: relationLabelFieldSelect
                        };
                        if (relationWhereSelect) config.options_source.where = relationWhereSelect;
                    }
                    if (relationModelSelect && relationAliasSelect) {
                        config.relation_model = relationModelSelect;
                        config.relation_alias = relationAliasSelect;
                        config.relation_value_field = relationValueFieldSelect;
                        if (relationLabelFieldSelect) {
                            config.relation_label_field = relationLabelFieldSelect;
                        }
                        if (relationWhereSelect) {
                            config.relation_where = relationWhereSelect;
                        }
                    }
                } else {
                    var modelSource = String(el('tableSourceManual') ? el('tableSourceManual').value : '').trim();
                    var valueField = String(el('tableValueFieldManual') ? el('tableValueFieldManual').value : '').trim();
                    var labelField = String(el('tableLabelFieldManual') ? el('tableLabelFieldManual').value : '').trim();
                    var whereClause = String(el('tableWhereManual') ? el('tableWhereManual').value : '').trim();
                    if (modelSource && valueField && labelField) {
                        config.options_source = {
                            mode: 'all',
                            model: modelSource,
                            value_field: valueField,
                            label_field: labelField
                        };
                        if (whereClause) config.options_source.where = whereClause;
                    }
                }
            } else {
                syncOptionsFromActiveTab();
                var options = optionsEditor && typeof optionsEditor.getOptionsObject === 'function'
                    ? optionsEditor.getOptionsObject()
                    : {};
                if (Object.keys(options).length > 0) {
                    config.options = options;
                }
            }
        }

        if (typeInfo.hasTableSource) {
            var modelSourceAjax = String(el('tableSource') ? el('tableSource').value : '').trim();
            var valueFieldAjax = String(el('tableValueField') ? el('tableValueField').value : '').trim();
            var labelFieldAjax = String(el('tableLabelField') ? el('tableLabelField').value : '').trim();
            var whereAjax = String(el('tableWhere') ? el('tableWhere').value : '').trim();
            if (modelSourceAjax && valueFieldAjax && labelFieldAjax) {
                config.options_source = {
                    mode: 'ajax',
                    model: modelSourceAjax,
                    value_field: valueFieldAjax,
                    label_field: labelFieldAjax
                };
                if (whereAjax) config.options_source.where = whereAjax;
            }
        }

        if (typeInfo.hasRelationSource) {
            var relationModel = String(el('relationTableSource') ? el('relationTableSource').value : '').trim();
            var relationLabelField = String(el('relationLabelColumn') ? el('relationLabelColumn').value : '').trim();
            var relationAlias = normalizeRelationAliasValue(el('relationAliasName') ? el('relationAliasName').value : '');
            if (el('relationAliasName')) {
                el('relationAliasName').value = relationAlias;
            }
            if (relationModel && relationAlias) {
                var relationModelMeta = getRelationModelMeta(relationModel);
                config.relation_model = relationModel;
                config.relation_alias = relationAlias;
                config.relation_value_field = chooseRelationValueField(relationModelMeta);
                config.relation_module_page = getProjectModulePage();
                if (relationLabelField) {
                    config.relation_label_field = relationLabelField;
                }
            }
            config.form_type = 'milkSelect';
            var relationWhereNode = el('relationWhere');
            if (relationWhereNode && String(relationWhereNode.value).trim() !== '') {
                config.relation_where = String(relationWhereNode.value).trim();
            }
        }

        var directFields = ['default_value', 'help_text', 'show_if_expr', 'calc_expr'];
        directFields.forEach(function (key) {
            var val = formData.get(key);
            if (key === 'show_if_expr') {
                if (!isHiddenType && val !== null) config.show_if = String(val).trim();
                return;
            }
            if (key === 'calc_expr') {
                var calcExpr = val !== null ? String(val).trim() : '';
                if (calcExpr !== '' && isCalculatedValueSupportedType(config.type)) {
                    config.calc_expr = calcExpr;
                }
                return;
            }
            if (isHiddenType && (key === 'default_value' || key === 'help_text')) {
                return;
            }
            if (val !== null && String(val).trim() !== '') {
                if (key === 'default_value') config.default = String(val).trim();
                else config[key] = String(val).trim();
            }
        });

        var customAlignment = normalizeCustomAlignmentValue(formData.get('custom_alignment'));
        if (!isHiddenType && isCustomAlignmentSupportedType(config.type) && customAlignment !== '') {
            config.custom_alignment = customAlignment;
        } else {
            delete config.custom_alignment;
        }

        if (isHiddenType) {
            delete config.default;
            delete config.custom_alignment;
            delete config.help_text;
            delete config.show_if;
            delete config.validation;
        }

        if (visibility.list) {
            var listOpts = {};

            if (formData.get('list_link_enabled') === 'on') {
                var linkUrl = String(formData.get('list_link_url') || '').trim();
                if (linkUrl) {
                    listOpts.link = {
                        url: linkUrl,
                        target: String(formData.get('list_link_target') || 'same_window')
                    };
                }
            }

            if (formData.get('list_html_enabled') === 'on') {
                listOpts.html = true;
            }

            if (formData.get('list_truncate_enabled') === 'on') {
                var truncLen = parseInt(String(formData.get('list_truncate_length') || '50'), 10);
                listOpts.truncate = truncLen > 0 ? truncLen : 50;
            }

            if (formData.get('list_change_values_enabled') === 'on' && listChangeValuesEditor) {
                var cvOptions = listChangeValuesEditor.getOptionsObject();
                if (Object.keys(cvOptions).length > 0) {
                    listOpts.change_values = cvOptions;
                }
            }

            var relationExtraFields = normalizeRelationListExtraFields(relationListExtraFields);
            if (relationExtraFields.length > 0) {
                listOpts.relation_fields = relationExtraFields.map(function (entry) {
                    var customLabel = String(entry.label || '').trim();
                    if (customLabel !== '') {
                        return {
                            field: entry.field,
                            label: customLabel
                        };
                    }
                    return entry.field;
                });
            }

            if (Object.keys(listOpts).length > 0) {
                config.list_options = listOpts;
            }
        }

        if (formData.get('exclude_from_db') === 'on') config.exclude_from_db = true;

        if (mode === 'edit' && currentModelDefinedField) {
            delete config.db_type;
            delete config.db_type_params;
            delete config.exclude_from_db;
        }

        return config;
    }

    function setModeUi() {
        var title = el('fieldBuilderModalTitle');
        var submitBtn = el('fieldBuilderSubmitBtn');
        var submitText = submitBtn ? submitBtn.querySelector('span') : null;
        if (!title || !submitBtn || !submitText) return;
        if (mode === 'edit') {
            title.innerHTML = '<i class="bi bi-pencil-square"></i> Edit Field';
            submitText.textContent = 'Update Field';
        } else {
            title.innerHTML = '<i class="bi bi-plus-circle-fill"></i> Create New Field';
            submitText.textContent = 'Create Field';
        }
    }

    function resetFormState() {
        var form = el('fieldBuilderForm');
        if (!form) return;
        form.reset();
        form.classList.remove('was-validated');

        var nameNode = el('fieldName');
        if (nameNode) {
            nameNode.setAttribute('data-original-field-name', '');
            nameNode.setAttribute('data-auto-generated', 'true');
        }
        setFieldNameValidity('');

        document.querySelectorAll('#fieldBuilderModal .main-tab').forEach(function (tab) {
            tab.classList.remove('active');
        });
        document.querySelectorAll('#fieldBuilderModal .main-tab-content').forEach(function (content) {
            content.classList.remove('active');
        });
        var firstTab = document.querySelector('#fieldBuilderModal .main-tab[data-maintab="base"]');
        var firstContent = document.querySelector('#fieldBuilderModal [data-maintab-content="base"]');
        if (firstTab) firstTab.classList.add('active');
        if (firstContent) firstContent.classList.add('active');

        currentDbTypeParams = {};
        selectFieldType('string');

        var showInList = el('showInList');
        var showInEdit = el('showInEdit');
        var showInView = el('showInView');
        if (showInList) showInList.checked = true;
        if (showInEdit) showInEdit.checked = true;
        if (showInView) showInView.checked = true;

        var listOptionsBlock = el('listOptionsBlock');
        if (listOptionsBlock) listOptionsBlock.style.display = '';

        var listLinkEnabled = el('listLinkEnabled');
        var listLinkOptions = el('listLinkOptions');
        if (listLinkEnabled) listLinkEnabled.checked = false;
        if (listLinkOptions) listLinkOptions.style.display = 'none';
        var listLinkUrl = el('listLinkUrl');
        var listLinkTarget = el('listLinkTarget');
        if (listLinkUrl) listLinkUrl.value = '';
        if (listLinkTarget) listLinkTarget.value = 'same_window';

        var listHtmlEnabled = el('listHtmlEnabled');
        if (listHtmlEnabled) listHtmlEnabled.checked = false;

        var listTruncateEnabled = el('listTruncateEnabled');
        var listTruncateOptions = el('listTruncateOptions');
        var listTruncateLength = el('listTruncateLength');
        if (listTruncateEnabled) listTruncateEnabled.checked = false;
        if (listTruncateOptions) listTruncateOptions.style.display = 'none';
        if (listTruncateLength) listTruncateLength.value = '50';

        var listChangeValuesEnabled = el('listChangeValuesEnabled');
        var listChangeValuesOptions = el('listChangeValuesOptions');
        if (listChangeValuesEnabled) listChangeValuesEnabled.checked = false;
        if (listChangeValuesOptions) listChangeValuesOptions.style.display = 'none';
        if (listChangeValuesEditor && typeof listChangeValuesEditor.setOptions === 'function') {
            listChangeValuesEditor.setOptions([]);
        }
        relationListExtraFields = [];
        renderRelationListExtraFields();
        setRelationListFieldSelectOptions([], '', 'Select model first');

        var manualMode = el('optionsModeManual');
        if (manualMode) manualMode.checked = true;
        toggleOptionsMode();
        setActiveOptionsTab('manual');
        if (optionsEditor && typeof optionsEditor.setOptions === 'function') {
            optionsEditor.setOptions([]);
        }
        resetOptionsSourceControls();
        resetRelationSourceControls();
        syncRelationListOptionsVisibility();

        currentModelDefinedField = false;
        updateModelFieldRestrictionUi();
    }

    function applyConfig(config) {
        if (!config || typeof config !== 'object') return;
        var normalized = config;

        var labelNode = el('fieldLabel');
        var nameNode = el('fieldName');
        var label = String(normalized.field_label || '').trim();
        var name = String(normalized.field_name || '').trim();
        if (!label && name) {
            label = humanizeName(name);
        }
        if (labelNode) labelNode.value = label;
        if (nameNode) {
            nameNode.value = name;
            nameNode.setAttribute('data-auto-generated', 'false');
            nameNode.setAttribute('data-original-field-name', name.toLowerCase());
        }
        validateFieldName();

        var normalizedType = String(normalized.type || '').trim();
        var normalizedOptionsSourceMode = '';
        if (normalized.options_source && typeof normalized.options_source === 'object') {
            normalizedOptionsSourceMode = String(normalized.options_source.mode || '').trim().toLowerCase();
        }
        var normalizedFormType = String(normalized.form_type || normalized.formType || '').trim().toLowerCase();
        if (
            normalizedType !== 'relation'
            && String(normalized.relation_model || '').trim() !== ''
            && (normalizedFormType === 'milkselect' || normalizedOptionsSourceMode === 'relation')
        ) {
            normalizedType = 'relation';
        }
        if (normalizedType && FIELD_TYPES[normalizedType]) {
            selectFieldType(normalizedType);
        }

        if (normalized.db_type) {
            var dbTypeNode = el('dbType');
            if (dbTypeNode) dbTypeNode.value = String(normalized.db_type);
        }

        if (normalized.db_type_params && typeof normalized.db_type_params === 'object') {
            currentDbTypeParams = Object.assign({}, normalized.db_type_params);
        }
        updateDBTypeDisplay();

        var requiredNode = el('fieldRequired');
        var readonlyNode = el('fieldReadonly');
        if (requiredNode) requiredNode.checked = normalizeBool(normalized.required);
        if (readonlyNode) readonlyNode.checked = normalizeBool(normalized.readonly);
        enforceRequiredReadonlyExclusion('readonly');

        var mapValues = {
            default_value: normalized.default,
            custom_alignment: normalizeCustomAlignmentValue(normalized.custom_alignment),
            validate_expr: normalized.validation && normalized.validation.validate_expr ? normalized.validation.validate_expr : '',
            error_message: normalized.validation && normalized.validation.error_message ? normalized.validation.error_message : '',
            help_text: normalized.help_text,
            show_if_expr: normalized.show_if,
            calc_expr: normalized.calc_expr
        };
        Object.keys(mapValues).forEach(function (key) {
            var node = document.querySelector('#fieldBuilderModal [name="' + key + '"]');
            if (node && mapValues[key] !== undefined) {
                node.value = String(mapValues[key] || '');
            }
        });

        ['min', 'max', 'step'].forEach(function (validationKey) {
            var validationNode = document.querySelector('#fieldBuilderModal [name="' + validationKey + '"]');
            if (validationNode && normalized.validation && normalized.validation[validationKey] !== undefined) {
                validationNode.value = String(normalized.validation[validationKey] || '');
            }
        });

        var visibility = normalized.visibility || {};
        var showInList = el('showInList');
        var showInEdit = el('showInEdit');
        var showInView = el('showInView');
        if (showInList) showInList.checked = visibility.list === undefined ? true : normalizeBool(visibility.list);
        if (showInEdit) showInEdit.checked = visibility.edit === undefined ? true : normalizeBool(visibility.edit);
        if (showInView) showInView.checked = visibility.view === undefined ? true : normalizeBool(visibility.view);

        var excludeNode = document.querySelector('#fieldBuilderModal [name="exclude_from_db"]');
        if (excludeNode) excludeNode.checked = normalizeBool(normalized.exclude_from_db);

        var listOptionsBlock = el('listOptionsBlock');
        var listLinkEnabled = el('listLinkEnabled');
        var listLinkOptions = el('listLinkOptions');
        var showInListChecked = showInList ? showInList.checked : false;
        if (listOptionsBlock) listOptionsBlock.style.display = showInListChecked ? '' : 'none';

        var lo = normalized.list_options && typeof normalized.list_options === 'object' ? normalized.list_options : {};

        if (lo.link && typeof lo.link === 'object') {
            if (listLinkEnabled) listLinkEnabled.checked = true;
            if (listLinkOptions) listLinkOptions.style.display = '';
            var linkUrlNode = el('listLinkUrl');
            var linkTargetNode = el('listLinkTarget');
            if (linkUrlNode) linkUrlNode.value = String(lo.link.url || '');
            if (linkTargetNode) linkTargetNode.value = String(lo.link.target || 'same_window');
        } else {
            if (listLinkEnabled) listLinkEnabled.checked = false;
            if (listLinkOptions) listLinkOptions.style.display = 'none';
        }

        var listHtmlEnabled = el('listHtmlEnabled');
        if (listHtmlEnabled) listHtmlEnabled.checked = normalizeBool(lo.html);

        var listTruncateEnabled = el('listTruncateEnabled');
        var listTruncateOptions = el('listTruncateOptions');
        var listTruncateLength = el('listTruncateLength');
        if (lo.truncate) {
            if (listTruncateEnabled) listTruncateEnabled.checked = true;
            if (listTruncateOptions) listTruncateOptions.style.display = '';
            if (listTruncateLength) listTruncateLength.value = String(parseInt(lo.truncate, 10) || 50);
        } else {
            if (listTruncateEnabled) listTruncateEnabled.checked = false;
            if (listTruncateOptions) listTruncateOptions.style.display = 'none';
            if (listTruncateLength) listTruncateLength.value = '50';
        }

        var listChangeValuesEnabled = el('listChangeValuesEnabled');
        var listChangeValuesOptions = el('listChangeValuesOptions');
        var cvData = lo.change_values || lo.changeValues;
        if (cvData && typeof cvData === 'object' && Object.keys(cvData).length > 0) {
            if (listChangeValuesEnabled) listChangeValuesEnabled.checked = true;
            if (listChangeValuesOptions) listChangeValuesOptions.style.display = '';
            if (listChangeValuesEditor && typeof listChangeValuesEditor.setOptions === 'function') {
                listChangeValuesEditor.setOptions(cvData);
            }
        } else {
            if (listChangeValuesEnabled) listChangeValuesEnabled.checked = false;
            if (listChangeValuesOptions) listChangeValuesOptions.style.display = 'none';
        }

        relationListExtraFields = normalizeRelationListExtraFields(lo.relation_fields || lo.relationFields || []);
        renderRelationListExtraFields();
        syncRelationListOptionsVisibility();

        if ((normalized.type === 'select' || normalized.type === 'relation') && normalizeBool(normalized.allow_multiple)) {
            var multipleNode = document.querySelector('#fieldBuilderModal [name="param_multiple"]');
            if (multipleNode) multipleNode.checked = true;
        }
        if (normalized.type === 'select' && normalizeBool(normalized.allow_empty)) {
            var allowEmptyNode = document.querySelector('#fieldBuilderModal [name="param_allow_empty"]');
            if (allowEmptyNode) allowEmptyNode.checked = true;
        }

        if (normalized.type === 'int') {
            var decimalsNode = document.querySelector('#fieldBuilderModal [name="param_decimals"]');
            var allowNegativeNode = document.querySelector('#fieldBuilderModal [name="param_allow_negative"]');
            if (decimalsNode && normalized.number_decimals !== undefined) {
                decimalsNode.value = String(normalized.number_decimals);
            }
            if (allowNegativeNode && normalized.number_allow_negative !== undefined) {
                allowNegativeNode.checked = normalizeBool(normalized.number_allow_negative);
            }
        }

        if (normalized.type === 'text' && normalized.form_type === 'editor') {
            var useEditorNode = document.querySelector('#fieldBuilderModal [name="param_use_editor"]');
            if (useEditorNode) useEditorNode.checked = true;
        }

        if (normalized.type === 'html') {
            var htmlNode = document.querySelector('#fieldBuilderModal [name="param_html_content"]');
            if (htmlNode && normalized.html_content !== undefined) {
                htmlNode.value = String(normalized.html_content || '');
            }
        }
        if (normalized.type === 'file' || normalized.type === 'image') {
            var maxFilesNode = document.querySelector('#fieldBuilderModal [name="param_max_files"]');
            if (maxFilesNode) {
                var normalizedMaxFiles = parseInt(String(
                    normalized.max_files !== undefined
                        ? normalized.max_files
                        : (normalized.maxFiles !== undefined ? normalized.maxFiles : 1)
                ), 10);
                maxFilesNode.value = String(
                    isFinite(normalizedMaxFiles) && normalizedMaxFiles > 0
                        ? normalizedMaxFiles
                        : 1
                );
            }
        }

        if (normalized.options && typeof normalized.options === 'object') {
            if (optionsEditor && typeof optionsEditor.setOptions === 'function') {
                optionsEditor.setOptions(normalized.options);
            }
        }

        if (normalized.options_source && typeof normalized.options_source === 'object') {
            if (normalized.options_source.mode === 'all') {
                var tableMode = el('optionsModeTable');
                if (tableMode) {
                    tableMode.checked = true;
                }
                toggleOptionsMode();
                if (currentFieldType === 'select') {
                    applyRelationSelection(
                        String(normalized.relation_model || normalized.options_source.model || normalized.options_source.table || ''),
                        String(normalized.relation_label_field || normalized.options_source.label_field || ''),
                        String(normalized.relation_alias || '')
                    );
                    if (el('relationWhere')) {
                        el('relationWhere').value = String(normalized.relation_where || normalized.options_source.where || '');
                    }
                } else {
                    applyOptionsSourceSelection(
                        'manual',
                        String(normalized.options_source.model || normalized.options_source.table || ''),
                        String(normalized.options_source.value_field || ''),
                        String(normalized.options_source.label_field || '')
                    );
                    if (el('tableWhereManual')) el('tableWhereManual').value = String(normalized.options_source.where || '');
                }
            }
            if (normalized.options_source.mode === 'ajax') {
                applyOptionsSourceSelection(
                    'ajax',
                    String(normalized.options_source.model || normalized.options_source.table || ''),
                    String(normalized.options_source.value_field || ''),
                    String(normalized.options_source.label_field || '')
                );
                if (el('tableWhere')) el('tableWhere').value = String(normalized.options_source.where || '');
            }
            if (normalized.options_source.mode === 'relation') {
                applyRelationSelection(
                    String(normalized.options_source.model || normalized.options_source.table || ''),
                    String(normalized.options_source.label_field || ''),
                    String(normalized.relation_alias || '')
                ).then(function () {
                    if (currentFieldType === 'relation') {
                        refreshRelationListFieldOptions(true);
                    }
                });
            }
        }

        if (String(normalized.relation_model || '').trim() !== '') {
            if (currentFieldType === 'select') {
                var selectTableMode = el('optionsModeTable');
                if (selectTableMode) {
                    selectTableMode.checked = true;
                }
                toggleOptionsMode();
            }
            applyRelationSelection(
                String(normalized.relation_model || ''),
                String(normalized.relation_label_field || ''),
                String(normalized.relation_alias || '')
            ).then(function () {
                if (currentFieldType === 'relation') {
                    refreshRelationListFieldOptions(true);
                }
            });
        }

        // Restore relation-specific options
        if (normalized.type === 'relation') {
            var relationWhereNode = el('relationWhere');
            if (relationWhereNode) relationWhereNode.value = String(normalized.relation_where || '');
        } else if (currentFieldType === 'select') {
            var relationWhereNodeSelect = el('relationWhere');
            if (relationWhereNodeSelect) relationWhereNodeSelect.value = String(normalized.relation_where || '');
        }

        updateTypeUi();
    }

    function ensureInitialized() {
        if (initialized) return true;
        var modal = el('fieldBuilderModal');
        if (!modal) return false;

        initMainTabs();
        initFieldTypeSelector();
        initOptionsEditor();
        initOptionsTabs();
        initAutoNameHandlers();
        initDbTypeEditor();
        initExcludeFromDbHandlers();
        initOptionsSourceHandlers();
        initRelationSourceHandlers();
        initListOptionsHandlers();
        initFormHandlers();

        modalInstance = (window.bootstrap && typeof window.bootstrap.Modal === 'function')
            ? new window.bootstrap.Modal(modal)
            : null;

        initialized = true;
        return true;
    }

    function openModal(openMode, config, onSubmit, options) {
        if (!ensureInitialized()) {
            return false;
        }
        var modalOptions = options && typeof options === 'object' ? options : {};
        mode = openMode === 'edit' ? 'edit' : 'create';
        currentModelDefinedField = mode === 'edit' && normalizeBool(modalOptions.model_defined || modalOptions.modelDefined);
        submitHandler = typeof onSubmit === 'function' ? onSubmit : null;
        resetFormState();
        currentModelDefinedField = mode === 'edit' && normalizeBool(modalOptions.model_defined || modalOptions.modelDefined);
        applyConfig(config || {});
        setModeUi();
        updateModelFieldRestrictionUi();
        if (modalInstance && typeof modalInstance.show === 'function') {
            modalInstance.show();
        }
        return true;
    }

    window.projectsOptionsEditor = {
        createManualBulkOptionsEditor: createManualBulkOptionsEditor,
        normalizeOptionsFromAny: normalizeOptionsFromAny,
        optionsArrayToObject: optionsArrayToObject
    };

    window.projectFieldBuilderModal = {
        openCreate: function (payload) {
            var data = payload && typeof payload === 'object' ? payload : {};
            var fieldConfig = (data.field && typeof data.field === 'object') ? data.field : {};
            return openModal('create', fieldConfig, data.onSubmit, { model_defined: false });
        },
        openEdit: function (payload) {
            var data = payload && typeof payload === 'object' ? payload : {};
            return openModal('edit', data.field || {}, data.onSubmit, {
                model_defined: normalizeBool(data.model_defined || data.modelDefined)
            });
        }
    };

    window.toggleOptionsMode = toggleOptionsMode;
    window.addOption = addOption;
    window.removeOption = removeOption;
    window.updateDbEditorParams = updateDbEditorParams;
    window.cancelDbTypeEdit = cancelDbTypeEdit;
    window.applyDbType = applyDbType;
})();
