(function () {
    'use strict';

    var nodeDataMap = new WeakMap();

    function normalizeFormNameForCreation(str) {
        var value = String(str || '').trim();
        if (!value) return '';

        if (typeof value.normalize === 'function') {
            value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        value = value.replace(/[^A-Za-z0-9]+/g, ' ');
        value = value.replace(/\s+/g, ' ').trim();
        if (!value) return '';

        var tokens = value.split(' ');
        var normalized = '';
        for (var i = 0; i < tokens.length; i++) {
            var token = tokens[i];
            if (!token) continue;
            normalized += token.charAt(0).toUpperCase() + token.slice(1).toLowerCase();
        }

        if (!normalized) return '';
        if (/^[0-9]/.test(normalized)) normalized = 'Form' + normalized;

        return normalized;
    }

    function safeParseFormsTree() {
        var dataNode = document.getElementById('project-forms-tree-data');
        if (!dataNode) return { main: {}, children: [] };

        try {
            var data = JSON.parse(dataNode.textContent || '{}');
            if (!data || typeof data !== 'object') return { main: {}, children: [] };
            if (!Array.isArray(data.children)) data.children = [];
            return data;
        } catch (e) {
            return { main: {}, children: [] };
        }
    }

    function getDisplayLabel(data, fallback) {
        var d = data || {};
        var title = String(d.title || '').trim();
        if (title) return title;
        var formName = String(d.form_name || '').trim();
        if (formName) return formName;
        return fallback || 'Form';
    }

    function extractManifestProps(data) {
        var result = {};
        var skip = { title: 1, form_name: 1, children: 1 };

        for (var key in data) {
            if (!data.hasOwnProperty(key)) continue;
            if (skip[key]) continue;
            if (String(key).indexOf('integrity_') === 0) continue;
            result[key] = data[key];
        }

        return result;
    }

    function getSubtablesContainer(node) {
        return node.querySelector(':scope > .subtables-wrapper > .subtables-container');
    }

    function getSubtablesActions(node) {
        return node.querySelector(':scope > .subtables-wrapper > .subtables-actions');
    }

    function buildTreeFromContainer(container) {
        var nodes = [];
        var items = container.querySelectorAll(':scope > .table-node');

        for (var i = 0; i < items.length; i++) {
            var data = nodeDataMap.get(items[i]) || {};
            var obj = extractManifestProps(data);

            var sub = getSubtablesContainer(items[i]);
            if (sub) {
                var childNodes = buildTreeFromContainer(sub);
                if (childNodes.length > 0) {
                    obj.forms = childNodes;
                }
            }

            nodes.push(obj);
        }

        return nodes;
    }

    function buildFullTree(mainNode, rootTablesContainer) {
        var mainData = nodeDataMap.get(mainNode) || {};
        var root = extractManifestProps(mainData);

        var children = buildTreeFromContainer(rootTablesContainer);
        if (children.length > 0) {
            root.forms = children;
        }

        return root;
    }

    var _saving = false;
    var _pendingSave = false;
    var _mainNode = null;
    var _rootContainer = null;

    function showOverlay() {
        var el = document.getElementById('build-forms-loading-overlay');
        if (el) el.classList.remove('d-none');
    }

    function hideOverlay() {
        var el = document.getElementById('build-forms-loading-overlay');
        if (el) el.classList.add('d-none');
    }

    function triggerSave() {
        if (!_mainNode || !_rootContainer) return;

        if (_saving) {
            _pendingSave = true;
            return;
        }

        var pageRoot = document.getElementById('project-build-forms-page');
        if (!pageRoot) return;

        var moduleName = pageRoot.getAttribute('data-module') || '';
        var saveUrl = pageRoot.getAttribute('data-save-url') || '';
        if (!moduleName || !saveUrl) return;

        var root = buildFullTree(_mainNode, _rootContainer);

        _saving = true;
        showOverlay();

        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                module: moduleName,
                forms_tree: { root: root }
            })
        })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    if (window.toasts) window.toasts.show(data.msg || 'Saved.', 'success');
                } else {
                    var msg = (data && data.msg) ? data.msg : 'Save failed.';
                    if (window.toasts) window.toasts.show(msg, 'danger');
                }
            })
            .catch(function (err) {
                console.error('Save error:', err);
                if (window.toasts) window.toasts.show('Save error: ' + err.message, 'danger');
            })
            .finally(function () {
                _saving = false;
                hideOverlay();

                if (_pendingSave) {
                    _pendingSave = false;
                    triggerSave();
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var pageRoot = document.getElementById('project-build-forms-page');
        if (!pageRoot) return;
        var subtableDeleteEnabled = false;

        var projectMainWrapper = document.getElementById('project-main-table-wrapper');
        var rootTablesContainer = document.getElementById('root-tables-container');
        var addRootTableBtn = document.getElementById('add-root-table');
        var addSubtableGlobalBtn = document.getElementById('add-subtable-global');
        var createTableModalEl = document.getElementById('createTableModal');
        var createTableForm = document.getElementById('create-table-form');
        var createTableNameInput = document.getElementById('create-table-name');
        var createTableLocation = document.getElementById('create-table-location');
        var deleteTableModalEl = document.getElementById('deleteTableModal');
        var deleteTableNameEl = document.getElementById('delete-table-name');
        var confirmDeleteTableBtn = document.getElementById('confirm-delete-table');

        if (!projectMainWrapper) return;

        var formsTree = safeParseFormsTree();
        var sortableByContainer = new WeakMap();
        var deleteTableUrl = String(pageRoot.getAttribute('data-delete-table-url') || '').trim();
        var subtablesUiEnabled = !!(
            rootTablesContainer && addRootTableBtn && addSubtableGlobalBtn &&
            createTableModalEl && createTableForm && createTableNameInput &&
            createTableLocation && deleteTableModalEl && deleteTableNameEl &&
            confirmDeleteTableBtn &&
            typeof window.bootstrap !== 'undefined' &&
            typeof window.bootstrap.Modal !== 'undefined' &&
            typeof ItoSortableList !== 'undefined'
        );
        var createTableModal = subtablesUiEnabled ? new window.bootstrap.Modal(createTableModalEl) : null;
        var deleteTableModal = subtablesUiEnabled ? new window.bootstrap.Modal(deleteTableModalEl) : null;

        var mainNode = null;
        var selectedTableNode = null;
        var pendingParentNode = null;
        var pendingParentContainer = null;
        var pendingDeleteNode = null;
        var deleteInProgress = false;

        function buildFormFieldsUrl(ref) {
            var moduleName = pageRoot.getAttribute('data-module') || '';
            var query = new URLSearchParams(window.location.search || '');
            var currentPage = query.get('page') || 'projects';

            return '?page=' + encodeURIComponent(currentPage) +
                '&action=build-form-fields' +
                '&module=' + encodeURIComponent(moduleName) +
                '&ref=' + encodeURIComponent(String(ref || '').trim());
        }

        function buildFormConfigUrl(ref) {
            var moduleName = pageRoot.getAttribute('data-module') || '';
            var query = new URLSearchParams(window.location.search || '');
            var currentPage = query.get('page') || 'projects';

            return '?page=' + encodeURIComponent(currentPage) +
                '&action=build-form-config' +
                '&module=' + encodeURIComponent(moduleName) +
                '&ref=' + encodeURIComponent(String(ref || '').trim());
        }

        function buildMainFormConfigUrl() {
            var moduleName = pageRoot.getAttribute('data-module') || '';
            var query = new URLSearchParams(window.location.search || '');
            var currentPage = query.get('page') || 'projects';

            return '?page=' + encodeURIComponent(currentPage) +
                '&action=build-main-form-config' +
                '&module=' + encodeURIComponent(moduleName);
        }

        function initSortableContainer(container) {
            if (sortableByContainer.has(container)) return sortableByContainer.get(container);

            var sortable = new ItoSortableList(container, {
                handleSelector: '.table-handle',
                onUpdate: function () {
                    triggerSave();
                }
            });
            sortableByContainer.set(container, sortable);
            return sortable;
        }

        function appendNodeToContainer(container, node) {
            container.appendChild(node);
            var sortable = sortableByContainer.get(container);
            if (sortable && typeof sortable.makeDraggable === 'function') {
                sortable.makeDraggable(node);
            }
        }

        function setSelectedTable(node) {
            if (selectedTableNode === node) return;
            if (selectedTableNode) selectedTableNode.classList.remove('selected-table');
            selectedTableNode = node;
            if (selectedTableNode) selectedTableNode.classList.add('selected-table');
        }

        function getPathTitles(node) {
            var titles = [];
            var current = node;

            while (current) {
                var titleEl = current.querySelector(':scope > .table-node-header .table-title');
                if (titleEl) titles.unshift(titleEl.textContent.trim());

                var parent = current.parentElement;
                var parentTable = null;
                while (parent) {
                    if (parent.classList && parent.classList.contains('table-node')) {
                        parentTable = parent;
                        break;
                    }
                    parent = parent.parentElement;
                }
                current = parentTable;
            }

            return titles;
        }

        function openCreateTableModal(parentNode, parentContainer) {
            pendingParentNode = parentNode;
            pendingParentContainer = parentContainer;
            var path = getPathTitles(parentNode);
            createTableLocation.textContent = 'You are creating a form under: ' + path.join(' -> ');
            createTableNameInput.value = '';
            createTableModal.show();
            setTimeout(function () { createTableNameInput.focus(); }, 150);
        }

        function removeNodeFromUi(node) {
            if (!node || !node.parentNode) return;

            if (selectedTableNode && (selectedTableNode === node || node.contains(selectedTableNode))) {
                setSelectedTable(mainNode);
            }

            node.parentNode.removeChild(node);
        }

        function openDeleteTableModal(node) {
            if (!node) return;
            var data = nodeDataMap.get(node) || {};
            var ref = String(data.ref || '').trim();
            if (!ref) {
                alert('Invalid ref for this table.');
                return;
            }

            var labelEl = node.querySelector(':scope > .table-node-header .table-title');
            var label = labelEl ? String(labelEl.textContent || '').trim() : '';
            if (!label) {
                label = String(ref).replace(/\.json$/i, '');
            }

            pendingDeleteNode = node;
            deleteTableNameEl.textContent = label;
            deleteTableModal.show();
        }

        function setNodeIntegrityDataset(node, manifestData) {
            var data = manifestData || {};
            node.dataset.integrityStatus = String(data.integrity_status || '');
            node.dataset.integrityMessage = String(data.integrity_message || '');
            node.dataset.integrityEditable = data.integrity_is_editable === false ? '0' : '1';
            node.dataset.integrityDeleteAllowed = data.integrity_delete_allowed === true ? '1' : '0';
        }

        function attachTableNodeEvents(node) {
            var header = node.querySelector('.table-node-header');
            var editBtn = node.querySelector('.edit-table');
            var configBtn = node.querySelector('.config-table');
            var deleteBtn = node.querySelector('.delete-table');

            if (header) {
                header.addEventListener('click', function (e) {
                    if (e.target.closest('button')) return;
                    setSelectedTable(node);
                });
            }

            if (editBtn) {
                editBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var data = nodeDataMap.get(node) || {};
                    var ref = String(data.ref || '').trim();
                    if (!ref) {
                        alert('Invalid ref for this table.');
                        return;
                    }
                    window.location.href = buildFormFieldsUrl(ref);
                });
            }

            if (configBtn) {
                configBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if ((node.getAttribute('data-is-project-main') || '') === '1') {
                        window.location.href = buildMainFormConfigUrl();
                        return;
                    }
                    var data = nodeDataMap.get(node) || {};
                    var ref = String(data.ref || '').trim();
                    if (!ref) {
                        alert('Invalid ref for this table.');
                        return;
                    }
                    window.location.href = buildFormConfigUrl(ref);
                });
            }

            if (deleteBtn) {
                deleteBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    openDeleteTableModal(node);
                });
            }
        }

        function createTableNode(options) {
            var config = options || {};
            var label = config.label;
            var isProjectMain = !!config.isProjectMain;
            var manifestData = config.manifestData || {};

            var node = document.createElement('div');
            node.className = 'table-node mb-2';
            node.setAttribute('data-is-project-main', isProjectMain ? '1' : '0');

            var effectiveLabel = label || (isProjectMain ? 'Project Table (Main Record)' : 'New Form');

            if (isProjectMain) {
                node.innerHTML =
                    '<div class="table-node-header d-flex align-items-center justify-content-between">' +
                        '<div class="d-flex align-items-center">' +
                            '<span class="table-title fw-semibold">' + effectiveLabel + '</span>' +
                        '</div>' +
                        '<div class="btn-group btn-group-sm">' +
                            '<button type="button" class="btn btn-outline-secondary edit-table">Edit</button>' +
                            '<button type="button" class="btn btn-outline-secondary config-table">Config</button>' +
                        '</div>' +
                    '</div>';
            } else {
                var deleteButtonHtml = subtableDeleteEnabled
                    ? '<button type="button" class="btn btn-outline-danger delete-table">Delete Table</button>'
                    : '';
                node.innerHTML =
                    '<div class="table-node-header d-flex align-items-center justify-content-between">' +
                        '<div class="d-flex align-items-center">' +
                            '<span class="table-handle me-2" title="Drag to reorder">&#9776;</span>' +
                            '<span class="table-title fw-semibold">' + effectiveLabel + '</span>' +
                        '</div>' +
                        '<div class="btn-group btn-group-sm">' +
                            '<button type="button" class="btn btn-outline-secondary edit-table">Edit</button>' +
                            '<button type="button" class="btn btn-outline-secondary config-table">Config</button>' +
                            deleteButtonHtml +
                        '</div>' +
                    '</div>' +
                    '<div class="subtables-wrapper mt-2">' +
                        '<div class="subtables-container"></div>' +
                        '<div class="subtables-actions mt-1"></div>' +
                    '</div>';

                var sub = getSubtablesContainer(node);
                if (sub) initSortableContainer(sub);
            }

            nodeDataMap.set(node, manifestData);
            setNodeIntegrityDataset(node, manifestData);
            attachTableNodeEvents(node);

            return node;
        }

        function ensureLocalAddSubButton(node) {
            var subtablesContainer = getSubtablesContainer(node);
            var actions = getSubtablesActions(node);
            if (!subtablesContainer || !actions) return;

            if (!actions.querySelector('.add-subtable-local')) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-outline-primary add-subtable-local';
                btn.textContent = '+ Add Form to this Sublist';
                btn.addEventListener('click', function () {
                    openCreateTableModal(node, subtablesContainer);
                });
                actions.appendChild(btn);
            }
        }

        function appendManifestNode(container, manifestNodeData) {
            var children = Array.isArray(manifestNodeData.children) ? manifestNodeData.children : [];

            var storedData = {};
            for (var key in manifestNodeData) {
                if (manifestNodeData.hasOwnProperty(key) && key !== 'children') {
                    storedData[key] = manifestNodeData[key];
                }
            }

            var node = createTableNode({
                label: getDisplayLabel(manifestNodeData, null),
                isProjectMain: false,
                manifestData: storedData
            });

            appendNodeToContainer(container, node);

            if (children.length > 0) {
                var sub = getSubtablesContainer(node);
                if (sub) {
                    children.forEach(function (child) {
                        appendManifestNode(sub, child);
                    });
                }
            }
        }

        (function () {
            var mainData = formsTree.main || {};
            mainNode = createTableNode({
                label: getDisplayLabel(mainData, 'Project Table (Main Record)'),
                isProjectMain: true,
                manifestData: mainData
            });
            projectMainWrapper.appendChild(mainNode);
            setSelectedTable(mainNode);
        })();

        _mainNode = mainNode;
        _rootContainer = subtablesUiEnabled ? rootTablesContainer : null;

        // expose only what integrity extension needs
        window.projectsBuildFormsTriggerSave = triggerSave;

        if (!subtablesUiEnabled) {
            return;
        }

        initSortableContainer(rootTablesContainer);

        var rootChildren = Array.isArray(formsTree.children) ? formsTree.children : [];
        rootChildren.forEach(function (child) {
            appendManifestNode(rootTablesContainer, child);
        });

        _rootContainer = rootTablesContainer;

        addRootTableBtn.addEventListener('click', function () {
            if (!mainNode) return;
            openCreateTableModal(mainNode, rootTablesContainer);
        });

        addSubtableGlobalBtn.addEventListener('click', function () {
            if (!selectedTableNode) {
                alert('Please select a table first to add a form sublist to it.');
                return;
            }
            if (selectedTableNode === mainNode) {
                alert('For the main table, use the "Add Table" button below the main list.');
                return;
            }
            ensureLocalAddSubButton(selectedTableNode);
            selectedTableNode.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        createTableForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!pendingParentContainer || !pendingParentNode) {
                createTableModal.hide();
                return;
            }

            var rawName = createTableNameInput.value.trim();
            if (!rawName) return;

            var formName = normalizeFormNameForCreation(rawName);
            if (!formName) {
                alert('Use a valid form name with letters and numbers only.');
                return;
            }

            var ref = formName + '.json';
            var manifestData = {
                ref: ref,
                max_records: 'n',
                form_name: formName,
                title: formName
            };

            var node = createTableNode({
                label: formName,
                isProjectMain: false,
                manifestData: manifestData
            });

            appendNodeToContainer(pendingParentContainer, node);
            createTableModal.hide();
            setSelectedTable(node);

            triggerSave();
        });

        deleteTableModalEl.addEventListener('hidden.bs.modal', function () {
            pendingDeleteNode = null;
            deleteTableNameEl.textContent = '';
        });

        confirmDeleteTableBtn.addEventListener('click', function () {
            if (deleteInProgress) return;
            if (!pendingDeleteNode) {
                deleteTableModal.hide();
                return;
            }
            if (!deleteTableUrl) {
                alert('Delete endpoint is not configured.');
                return;
            }

            var moduleName = String(pageRoot.getAttribute('data-module') || '').trim();
            var data = nodeDataMap.get(pendingDeleteNode) || {};
            var ref = String(data.ref || '').trim();
            if (!moduleName || !ref) {
                alert('Invalid delete payload.');
                return;
            }

            deleteInProgress = true;
            confirmDeleteTableBtn.disabled = true;
            confirmDeleteTableBtn.textContent = 'Deleting...';

            fetch(deleteTableUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    module: moduleName,
                    ref: ref
                })
            })
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function (response) {
                    if (!response || !response.success) {
                        var errorMsg = (response && response.msg) ? response.msg : 'Delete failed.';
                        throw new Error(errorMsg);
                    }

                    var deletedNode = pendingDeleteNode;
                    pendingDeleteNode = null;
                    deleteTableModal.hide();
                    removeNodeFromUi(deletedNode);

                    if (window.toasts) {
                        window.toasts.show(response.msg || 'Table deleted.', 'success');
                    }

                    if (response.warnings && Array.isArray(response.warnings) && response.warnings.length > 0 && window.toasts) {
                        window.toasts.show(String(response.warnings[0]), 'warning');
                    }
                })
                .catch(function (err) {
                    console.error('Delete table error:', err);
                    if (window.toasts) {
                        window.toasts.show('Delete error: ' + err.message, 'danger');
                    } else {
                        alert('Delete error: ' + err.message);
                    }
                })
                .finally(function () {
                    deleteInProgress = false;
                    confirmDeleteTableBtn.disabled = false;
                    confirmDeleteTableBtn.textContent = 'Delete Table';
                });
        });
    });
})();
