(function () {
    'use strict';

    function safeParseTreeData() {
        var dataNode = document.getElementById('project-forms-tree-data');
        if (!dataNode) return null;

        try {
            var data = JSON.parse(dataNode.textContent || '{}');
            if (!data || typeof data !== 'object') return null;
            return data;
        } catch (e) {
            return null;
        }
    }

    function flattenTreeData(treeData) {
        var list = [];
        if (!treeData || typeof treeData !== 'object') return list;

        function walk(node) {
            if (!node || typeof node !== 'object') return;
            list.push(node);
            var children = Array.isArray(node.children) ? node.children : [];
            for (var i = 0; i < children.length; i++) {
                walk(children[i]);
            }
        }

        var main = treeData.main && typeof treeData.main === 'object' ? treeData.main : null;
        if (main) {
            walk(main);
        }

        var roots = Array.isArray(treeData.children) ? treeData.children : [];
        for (var j = 0; j < roots.length; j++) {
            walk(roots[j]);
        }

        return list;
    }

    function ensureDatasetFromTreeData() {
        var nodes = document.querySelectorAll('#project-build-forms-page .table-node');
        if (!nodes.length) return;

        var needFallback = false;
        for (var i = 0; i < nodes.length; i++) {
            if (String(nodes[i].dataset.integrityStatus || '').trim() === '') {
                needFallback = true;
                break;
            }
        }
        if (!needFallback) return;

        var treeData = safeParseTreeData();
        if (!treeData) return;

        var flat = flattenTreeData(treeData);
        if (!flat.length) return;

        var max = Math.min(nodes.length, flat.length);
        for (var k = 0; k < max; k++) {
            var node = nodes[k];
            var meta = flat[k] || {};

            node.dataset.integrityStatus = String(meta.integrity_status || '');
            node.dataset.integrityMessage = String(meta.integrity_message || '');
            node.dataset.integrityEditable = meta.integrity_is_editable === false ? '0' : '1';
            node.dataset.integrityDeleteAllowed = meta.integrity_delete_allowed === true ? '1' : '0';
        }
    }

    function toBool(value) {
        var v = String(value || '').toLowerCase();
        return v === '1' || v === 'true' || v === 'yes' || v === 'on';
    }

    function isNodeEditable(node) {
        return toBool(node.dataset.integrityEditable || '1');
    }

    function getNodeStatus(node) {
        return String(node.dataset.integrityStatus || '');
    }

    function getNodeMessage(node) {
        return String(node.dataset.integrityMessage || '').trim();
    }

    function canDeleteRow(node) {
        return toBool(node.dataset.integrityDeleteAllowed || '0');
    }

    function defaultBrokenMessage() {
        return "Contact your administrator: database integrity is broken.";
    }

    function findBtnGroup(node) {
        return node.querySelector(':scope > .table-node-header .btn-group');
    }

    function ensureMessage(node) {
        var msg = getNodeMessage(node);
        if (!msg) return;
        if (node.querySelector(':scope > .table-node-integrity-message')) return;

        var box = document.createElement('div');
        box.className = 'table-node-integrity-message';
        box.textContent = msg;

        var header = node.querySelector(':scope > .table-node-header');
        if (header && header.parentNode) {
            if (header.nextSibling) {
                header.parentNode.insertBefore(box, header.nextSibling);
            } else {
                header.parentNode.appendChild(box);
            }
        }
    }

    function ensureReadonlyState(node) {
        if (isNodeEditable(node)) return;

        node.classList.add('table-node-readonly');

        var handle = node.querySelector(':scope > .table-node-header .table-handle');
        if (handle) {
            handle.classList.add('table-handle-disabled');
            handle.setAttribute('title', 'Row is read-only');
        }

        var editBtn = node.querySelector(':scope > .table-node-header .edit-table');
        if (editBtn) {
            if (getNodeMessage(node)) {
                editBtn.title = getNodeMessage(node);
            }
        }
    }

    function ensureStatusStyles(node) {
        var status = getNodeStatus(node);
        if (status === 'missing_table') {
            node.classList.add('table-node-missing-table');
        } else if (status === 'locked_no_schema' || status === 'locked_existing_table') {
            node.classList.add('table-node-locked');
        } else if (status === 'missing_model') {
            node.classList.add('table-node-missing-model');
        }
    }

    function hasChildRows(node) {
        var sub = node.querySelector(':scope > .subtables-wrapper > .subtables-container');
        if (!sub) return false;
        return sub.querySelectorAll(':scope > .table-node').length > 0;
    }

    function deleteRow(node) {
        var message = getNodeMessage(node) || defaultBrokenMessage();

        if (!canDeleteRow(node) || hasChildRows(node)) {
            alert(message);
            return;
        }

        if (!window.confirm('Delete this row from the manifest?')) {
            return;
        }

        if (node.classList.contains('selected-table')) {
            node.classList.remove('selected-table');
        }

        if (node.parentNode) {
            node.parentNode.removeChild(node);
        }

        if (typeof window.projectsBuildFormsTriggerSave === 'function') {
            window.projectsBuildFormsTriggerSave();
        }
    }

    function ensureIntegrityButtons(node) {
        var status = getNodeStatus(node);
        var deleteAllowed = canDeleteRow(node);
        var needsIntegrityUi = deleteAllowed || status === 'missing_table' || status === 'missing_model';
        if (!needsIntegrityUi) return;

        var btnGroup = findBtnGroup(node);
        if (!btnGroup) return;

        var message = getNodeMessage(node) || defaultBrokenMessage();
        if (!deleteAllowed && /you can delete this row\./i.test(message)) {
            deleteAllowed = true;
        }

        if (deleteAllowed) {
            if (!btnGroup.querySelector('.delete-broken-row')) {
                var deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'btn btn-outline-danger delete-broken-row';
                deleteBtn.textContent = 'Delete row';
                deleteBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    deleteRow(node);
                });
                btnGroup.appendChild(deleteBtn);
            }
        } else {
            if (!btnGroup.querySelector('.integrity-help')) {
                var infoBtn = document.createElement('button');
                infoBtn.type = 'button';
                infoBtn.className = 'btn btn-outline-warning integrity-help';
                infoBtn.textContent = 'Info';
                infoBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    alert(message);
                });
                btnGroup.appendChild(infoBtn);
            }
        }
    }

    function applyNodeIntegrity(node) {
        if (!(node instanceof HTMLElement)) return;
        if (!node.classList.contains('table-node')) return;

        ensureStatusStyles(node);
        ensureReadonlyState(node);
        ensureMessage(node);
        ensureIntegrityButtons(node);
    }

    function applyAllRows() {
        var rows = document.querySelectorAll('#project-build-forms-page .table-node');
        for (var i = 0; i < rows.length; i++) {
            applyNodeIntegrity(rows[i]);
        }
    }

    function guardReadonlyNodeAdd(e) {
        var localAddBtn = e.target.closest('.add-subtable-local');
        if (localAddBtn) {
            var row = localAddBtn.closest('.table-node');
            if (row && !isNodeEditable(row)) {
                e.preventDefault();
                e.stopPropagation();
                alert(getNodeMessage(row) || 'This row is read-only.');
                return;
            }
        }

        var globalBtn = e.target.closest('#add-subtable-global');
        if (globalBtn) {
            var selected = document.querySelector('#project-build-forms-page .table-node.selected-table');
            if (selected && !isNodeEditable(selected)) {
                e.preventDefault();
                e.stopPropagation();
                alert(getNodeMessage(selected) || 'This row is read-only.');
            }
        }
    }

    function blockReadonlyDragStart(e) {
        var handle = e.target.closest('.table-handle-disabled');
        if (!handle) return;
        e.preventDefault();
        e.stopPropagation();
    }

    function setupObserver() {
        var root = document.getElementById('project-build-forms-page');
        if (!root) return;

        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var m = mutations[i];
                if (!m.addedNodes || m.addedNodes.length === 0) continue;

                for (var j = 0; j < m.addedNodes.length; j++) {
                    var added = m.addedNodes[j];
                    if (!(added instanceof HTMLElement)) continue;

                    if (added.classList.contains('table-node')) {
                        applyNodeIntegrity(added);
                    }

                    var nested = added.querySelectorAll ? added.querySelectorAll('.table-node') : [];
                    for (var k = 0; k < nested.length; k++) {
                        applyNodeIntegrity(nested[k]);
                    }
                }
            }
        });

        observer.observe(root, { childList: true, subtree: true });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var page = document.getElementById('project-build-forms-page');
        if (!page) return;

        ensureDatasetFromTreeData();
        applyAllRows();
        setupObserver();

        document.addEventListener('click', guardReadonlyNodeAdd, true);
        document.addEventListener('mousedown', blockReadonlyDragStart, true);
        document.addEventListener('touchstart', blockReadonlyDragStart, true);
    });
})();
