(function () {
    var currentRow = null;
    var currentCard = null;
    var cardsSortable = null;
    var rowSortables = [];
    var rowSortGroupName = 'projects-view-layout-rows';
    var pendingLayoutEmitFrame = null;
    var sortableInitRetryTimer = null;
    var currentLayoutSnapshot = null;
    var isSavingLayout = false;

    var displayBadgeMap = {
        fields: { label: 'Fields', className: 'text-bg-primary' },
        icon: { label: 'Icon', className: 'text-bg-success' },
        table: { label: 'Table', className: 'text-bg-warning' }
    };

    function byId(id) {
        return document.getElementById(id);
    }

    function setFieldValue(id, value) {
        var el = byId(id);
        if (!el) return;
        el.value = value == null ? '' : String(value);
    }

    function closeModal(modalEl) {
        if (!modalEl) return;
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

    function safeJsonParse(rawValue, fallbackValue) {
        if (!rawValue) return fallbackValue;
        try {
            var parsed = JSON.parse(rawValue);
            return parsed && typeof parsed === 'object' ? parsed : fallbackValue;
        } catch (error) {
            return fallbackValue;
        }
    }

    function readJsonAttr(element, attrName, fallbackValue) {
        if (!element) return fallbackValue;
        return safeJsonParse(element.getAttribute(attrName), fallbackValue);
    }

    function writeJsonAttr(element, attrName, value) {
        if (!element) return;
        element.setAttribute(attrName, JSON.stringify(value || {}));
    }

    function normalizeCardId(rawValue) {
        return String(rawValue || '').trim().replace(/\s+/g, '-');
    }

    function normalizeDisplayAs(rawValue) {
        var value = String(rawValue || '').trim();
        if (value === 'fields' || value === 'icon' || value === 'table') {
            return value;
        }
        return 'icon';
    }

    function getCardConfig(card) {
        var raw = readJsonAttr(card, 'data-card-config', {});
        var id = normalizeCardId(raw.id || '');
        return {
            id: id,
            type: String(raw.type || 'group').trim() || 'group',
            title: String(raw.title || id || '').trim(),
            icon: String(raw.icon || '').trim(),
            description: String(raw.description || '').trim()
        };
    }

    function getRowConfig(row) {
        var raw = readJsonAttr(row, 'data-row-config', {});
        var table = String(raw.table || '').trim();
        return {
            table: table,
            display_as: normalizeDisplayAs(raw.display_as),
            title: String(raw.title || table).trim(),
            icon: String(raw.icon || '').trim(),
            visible: raw.visible !== false
        };
    }

    function createIconElement(iconClass) {
        if (!iconClass) return null;
        var iconEl = document.createElement('i');
        iconEl.className = iconClass;
        return iconEl;
    }

    function renderCard(card, config) {
        if (!card || !config) return;
        writeJsonAttr(card, 'data-card-config', config);

        var titleEl = card.querySelector('.projects-view-card-id');
        if (titleEl) {
            titleEl.textContent = '';
            var iconEl = createIconElement(config.icon);
            if (iconEl) {
                titleEl.appendChild(iconEl);
                titleEl.appendChild(document.createTextNode(' '));
            }
            titleEl.appendChild(document.createTextNode(config.title || config.id || 'Card'));
        }

        var codeEl = card.querySelector('.projects-view-card-title-wrap code');
        if (codeEl) {
            codeEl.textContent = config.id || '';
        }

        var textWrap = card.querySelector('.projects-view-card-title-wrap > div');
        var descriptionEl = card.querySelector('.projects-view-card-description');
        if (config.description) {
            if (!descriptionEl && textWrap) {
                descriptionEl = document.createElement('div');
                descriptionEl.className = 'projects-view-card-description';
                textWrap.appendChild(descriptionEl);
            }
            if (descriptionEl) {
                descriptionEl.textContent = config.description;
            }
        } else if (descriptionEl) {
            descriptionEl.remove();
        }
    }

    function renderRow(row, config) {
        if (!row || !config) return;
        writeJsonAttr(row, 'data-row-config', config);

        row.classList.add('projects-view-row');
        row.classList.remove('projects-view-row-display-fields', 'projects-view-row-display-icon', 'projects-view-row-display-table');
        row.classList.add('projects-view-row-display-' + config.display_as);
        row.classList.toggle('projects-view-row-hidden', !config.visible);

        var titleTextEl = row.querySelector('.projects-view-row-title-text');
        if (titleTextEl) {
            titleTextEl.textContent = '';
            var titleIconEl = createIconElement(config.icon);
            if (titleIconEl) {
                titleTextEl.appendChild(titleIconEl);
                titleTextEl.appendChild(document.createTextNode(' '));
            }
            titleTextEl.appendChild(document.createTextNode(config.title || config.table || ''));
        }

        var codeEl = row.querySelector('.projects-view-row-meta code');
        if (codeEl) {
            codeEl.textContent = config.table || '';
        }

        var visibilityEl = row.querySelector('.projects-view-row-visibility');
        if (visibilityEl) {
            visibilityEl.classList.remove('projects-view-row-visibility-visible', 'projects-view-row-visibility-hidden');
            visibilityEl.classList.add(config.visible ? 'projects-view-row-visibility-visible' : 'projects-view-row-visibility-hidden');

            var visibilityIconEl = visibilityEl.querySelector('i');
            if (visibilityIconEl) {
                visibilityIconEl.className = config.visible ? 'bi bi-eye-fill' : 'bi bi-eye-slash';
            }

            var visibilityTextEl = visibilityEl.querySelector('span');
            if (visibilityTextEl) {
                visibilityTextEl.textContent = config.visible ? 'Visible' : 'Hidden';
            }
        }

        var displayBadge = row.querySelector('.projects-view-row-meta .badge');
        if (displayBadge) {
            var badgeCfg = displayBadgeMap[config.display_as] || { label: config.display_as, className: 'text-bg-secondary' };
            displayBadge.className = 'badge ' + badgeCfg.className;
            displayBadge.textContent = badgeCfg.label;
        }
    }

    function buildCardElement(cardConfig) {
        var section = document.createElement('section');
        section.className = 'projects-view-card';
        section.innerHTML =
            '<div class="projects-view-card-header">' +
                '<div class="projects-view-card-title-wrap">' +
                    '<span class="projects-view-card-handle" title="Drag card">' +
                        '<i class="bi bi-grip-vertical"></i>' +
                    '</span>' +
                    '<div>' +
                        '<div class="d-flex align-items-center gap-2 flex-wrap">' +
                            '<span class="projects-view-card-id"></span>' +
                            '<code></code>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="projects-view-card-actions">' +
                    '<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#project-view-card-edit-modal">Edit Card</button>' +
                    '<button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#project-view-card-remove-modal">Remove</button>' +
                '</div>' +
            '</div>' +
            '<div class="projects-view-card-body">' +
                '<div class="projects-view-row-list" data-card-rows data-sortable-scope="view-layout-rows"></div>' +
                '<div class="projects-view-card-footer">' +
                    '<span class="text-muted small">Cards can be added/removed. Tables are fixed by manifest first level.</span>' +
                '</div>' +
            '</div>';

        renderCard(section, cardConfig);
        return section;
    }

    function cardIdExists(pageRoot, cardId, excludeCard) {
        if (!pageRoot || !cardId) return false;
        var cards = pageRoot.querySelectorAll('.projects-view-card');
        for (var i = 0; i < cards.length; i += 1) {
            var card = cards[i];
            if (excludeCard && card === excludeCard) {
                continue;
            }
            if (getCardConfig(card).id === cardId) {
                return true;
            }
        }
        return false;
    }

    function nextCardId(pageRoot) {
        var index = 1;
        while (cardIdExists(pageRoot, 'card-' + index, null)) {
            index += 1;
        }
        return 'card-' + index;
    }

    function getSortableCtor() {
        if (typeof window.ItoSortableList === 'function') {
            return window.ItoSortableList;
        }
        if (typeof ItoSortableList === 'function') {
            return ItoSortableList;
        }
        return null;
    }

    function toLayoutTable(rowConfig) {
        var table = {
            name: rowConfig.table || '',
            displayAs: rowConfig.display_as || 'icon',
            title: rowConfig.title || rowConfig.table || '',
            icon: rowConfig.icon || ''
        };
        if (rowConfig.visible === false) {
            table.visible = false;
        }
        return table;
    }

    function buildLayoutSnapshot(pageRoot) {
        var cards = [];
        if (!pageRoot) {
            return { version: '1.0', cards: cards };
        }

        var cardEls = pageRoot.querySelectorAll('[data-layout-board] > .projects-view-card');
        for (var i = 0; i < cardEls.length; i += 1) {
            var cardEl = cardEls[i];
            var cardConfig = getCardConfig(cardEl);
            var rowEls = cardEl.querySelectorAll('.projects-view-row-list .projects-view-row');
            var tables = [];
            for (var j = 0; j < rowEls.length; j += 1) {
                tables.push(toLayoutTable(getRowConfig(rowEls[j])));
            }

            var cardType = cardConfig.type;
            if (cardType === 'single-table' && tables.length !== 1) {
                cardType = 'group';
            }

            if (cardType === 'single-table' && tables.length === 1) {
                cards.push({
                    id: cardConfig.id,
                    type: 'single-table',
                    table: tables[0]
                });
            } else {
                var groupCard = {
                    id: cardConfig.id,
                    type: 'group',
                    title: cardConfig.title,
                    icon: cardConfig.icon,
                    tables: tables
                };
                if (cardConfig.description) {
                    groupCard.description = cardConfig.description;
                }
                cards.push(groupCard);
            }
        }

        return {
            version: '1.0',
            cards: cards
        };
    }

    function updateLayoutPreview(pageRoot, layout) {
        if (!pageRoot || !layout) return;
        var pre = pageRoot.querySelector('.projects-view-json-preview');
        if (!pre) return;
        pre.textContent = JSON.stringify(layout, null, 4);
    }

    // Placeholder hook for future persistence/update logic.
    function notifyLayoutUpdate(action, payload) {
        if (typeof window.projectsRecordViewLayoutUpdate === 'function') {
            try {
                window.projectsRecordViewLayoutUpdate(action, payload);
            } catch (error) {
                console.error('projectsRecordViewLayoutUpdate error:', error);
            }
        }
    }

    function emitLayoutUpdate(pageRoot, action, payload) {
        var layout = buildLayoutSnapshot(pageRoot);
        currentLayoutSnapshot = layout;
        updateLayoutPreview(pageRoot, layout);
        notifyLayoutUpdate(action, {
            payload: payload || {},
            layout: layout
        });
    }

    function saveLayout(pageRoot, saveButton) {
        if (!pageRoot || isSavingLayout) {
            return;
        }

        var moduleName = String(pageRoot.getAttribute('data-module') || '').trim();
        var saveUrl = String(pageRoot.getAttribute('data-save-url') || '').trim();
        if (!moduleName || !saveUrl) {
            if (window.toasts) {
                window.toasts.show('Save endpoint is not configured.', 'danger');
            }
            return;
        }

        var layout = currentLayoutSnapshot || buildLayoutSnapshot(pageRoot);
        isSavingLayout = true;

        var originalLabel = '';
        if (saveButton) {
            originalLabel = String(saveButton.textContent || 'Save Layout');
            saveButton.disabled = true;
            saveButton.textContent = 'Saving...';
        }

        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                module: moduleName,
                layout: layout
            })
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    if (window.toasts) {
                        window.toasts.show(data.msg || 'View layout saved.', 'success');
                    }
                    return;
                }
                var msg = data && data.msg ? data.msg : 'Save failed.';
                if (window.toasts) {
                    window.toasts.show(msg, 'danger');
                }
            })
            .catch(function (err) {
                console.error('Save layout error:', err);
                if (window.toasts) {
                    window.toasts.show('Save error: ' + (err && err.message ? err.message : 'Unknown error'), 'danger');
                }
            })
            .finally(function () {
                isSavingLayout = false;
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.textContent = originalLabel || 'Save Layout';
                }
            });
    }

    function scheduleLayoutUpdate(pageRoot, action, payload) {
        if (pendingLayoutEmitFrame !== null && typeof window.cancelAnimationFrame === 'function') {
            window.cancelAnimationFrame(pendingLayoutEmitFrame);
            pendingLayoutEmitFrame = null;
        }

        var cb = function () {
            pendingLayoutEmitFrame = null;
            emitLayoutUpdate(pageRoot, action, payload);
        };

        if (typeof window.requestAnimationFrame === 'function') {
            pendingLayoutEmitFrame = window.requestAnimationFrame(cb);
        } else {
            setTimeout(cb, 0);
        }
    }

    function findRowSortableIndex(container) {
        for (var i = 0; i < rowSortables.length; i += 1) {
            if (rowSortables[i].container === container) {
                return i;
            }
        }
        return -1;
    }

    function registerRowSortable(container, pageRoot, SortableCtor) {
        if (!container || typeof SortableCtor !== 'function') {
            return;
        }
        if (findRowSortableIndex(container) !== -1) {
            return;
        }

        var sortable = new SortableCtor(container, {
            handleSelector: '.projects-view-row-handle',
            group: rowSortGroupName,
            onUpdate: function (order, meta) {
                scheduleLayoutUpdate(pageRoot, 'reorder-rows', { meta: meta || null });
            }
        });

        rowSortables.push({
            container: container,
            sortable: sortable
        });
    }

    function unregisterRowSortable(container) {
        var idx = findRowSortableIndex(container);
        if (idx === -1) return;

        var entry = rowSortables[idx];
        if (entry.sortable && typeof entry.sortable.destroy === 'function') {
            entry.sortable.destroy();
        }

        rowSortables.splice(idx, 1);
    }

    function initSortables(pageRoot) {
        if (!pageRoot) {
            return false;
        }

        var SortableCtor = getSortableCtor();
        if (typeof SortableCtor !== 'function') {
            return false;
        }

        var layoutBoard = pageRoot.querySelector('[data-layout-board]');
        if (layoutBoard && !cardsSortable) {
            cardsSortable = new SortableCtor(layoutBoard, {
                handleSelector: '.projects-view-card-handle',
                onUpdate: function (order, meta) {
                    scheduleLayoutUpdate(pageRoot, 'reorder-cards', { meta: meta || null });
                }
            });
        }

        var rowLists = pageRoot.querySelectorAll('.projects-view-row-list[data-card-rows]');
        for (var i = 0; i < rowLists.length; i += 1) {
            registerRowSortable(rowLists[i], pageRoot, SortableCtor);
        }

        return true;
    }

    function hydrateFromConfig(pageRoot) {
        if (!pageRoot) return;

        var cards = pageRoot.querySelectorAll('.projects-view-card');
        for (var i = 0; i < cards.length; i += 1) {
            renderCard(cards[i], getCardConfig(cards[i]));
        }

        var rows = pageRoot.querySelectorAll('.projects-view-row');
        for (var j = 0; j < rows.length; j += 1) {
            renderRow(rows[j], getRowConfig(rows[j]));
        }
    }

    function initEditRecordViewPage() {
        var pageRoot = byId('project-edit-record-view-page');
        if (!pageRoot) return;
        if (pageRoot.dataset.sortableInit === '1') return;
        pageRoot.dataset.sortableInit = '1';

        hydrateFromConfig(pageRoot);
        if (!initSortables(pageRoot)) {
            sortableInitRetryTimer = setTimeout(function () {
                initSortables(pageRoot);
            }, 250);
        }
        currentLayoutSnapshot = buildLayoutSnapshot(pageRoot);
        updateLayoutPreview(pageRoot, currentLayoutSnapshot);

        var layoutBoard = pageRoot.querySelector('[data-layout-board]');
        var saveLayoutBtn = byId('project-view-save-layout-btn');
        if (saveLayoutBtn) {
            saveLayoutBtn.addEventListener('click', function () {
                saveLayout(pageRoot, saveLayoutBtn);
            });
        }

        var rowModalEl = byId('project-view-row-edit-modal');
        var rowModalForm = byId('project-view-row-edit-form');
        if (rowModalEl && rowModalForm) {
            rowModalEl.addEventListener('show.bs.modal', function (event) {
                var trigger = event && event.relatedTarget ? event.relatedTarget : null;
                if (!trigger) return;

                var row = trigger.closest('.projects-view-row');
                if (!row) return;
                currentRow = row;

                var rowConfig = getRowConfig(row);
                var card = row.closest('.projects-view-card');
                var cardConfig = getCardConfig(card);

                var modalTitle = byId('project-view-row-edit-modal-title');
                if (modalTitle) {
                    modalTitle.textContent = 'Edit row: ' + (rowConfig.title || rowConfig.table || 'Row');
                }

                setFieldValue('project-view-row-edit-table-name', rowConfig.table);
                setFieldValue('project-view-row-edit-card-id', cardConfig.id || '');
                setFieldValue('project-view-row-edit-row-index', '');
                setFieldValue('project-view-row-edit-display-as', rowConfig.display_as);

                var visibleEl = byId('project-view-row-edit-visible');
                if (visibleEl) {
                    visibleEl.checked = rowConfig.visible;
                }
            });

            rowModalEl.addEventListener('hidden.bs.modal', function () {
                currentRow = null;
            });

            rowModalForm.addEventListener('submit', function (event) {
                event.preventDefault();
                event.stopPropagation();

                if (currentRow) {
                    var rowConfig = getRowConfig(currentRow);
                    var displayField = byId('project-view-row-edit-display-as');
                    rowConfig.display_as = normalizeDisplayAs(displayField ? displayField.value : rowConfig.display_as);

                    var visibleField = byId('project-view-row-edit-visible');
                    rowConfig.visible = !!(visibleField && visibleField.checked);

                    renderRow(currentRow, rowConfig);

                    var card = currentRow.closest('.projects-view-card');
                    var cardConfig = getCardConfig(card);
                    emitLayoutUpdate(pageRoot, 'edit-row', {
                        card_id: cardConfig.id || '',
                        row: rowConfig
                    });
                }

                closeModal(rowModalEl);
            });
        }

        var addModalEl = byId('project-view-card-add-modal');
        var addForm = byId('project-view-card-add-form');
        if (addModalEl && addForm) {
            addModalEl.addEventListener('show.bs.modal', function () {
                setFieldValue('project-view-card-add-id', nextCardId(pageRoot));
                setFieldValue('project-view-card-add-title', '');
                setFieldValue('project-view-card-add-icon', '');
                setFieldValue('project-view-card-add-description', '');
            });

            addForm.addEventListener('submit', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (!layoutBoard) return;

                var cardId = normalizeCardId(byId('project-view-card-add-id') ? byId('project-view-card-add-id').value : '');
                var title = String(byId('project-view-card-add-title') ? byId('project-view-card-add-title').value : '').trim();
                var icon = String(byId('project-view-card-add-icon') ? byId('project-view-card-add-icon').value : '').trim();
                var description = String(byId('project-view-card-add-description') ? byId('project-view-card-add-description').value : '').trim();

                if (!cardId || !title) {
                    window.alert('Card ID and title are required.');
                    return;
                }
                if (cardIdExists(pageRoot, cardId, null)) {
                    window.alert('Card ID already exists.');
                    return;
                }

                var newCardConfig = {
                    id: cardId,
                    type: 'group',
                    title: title,
                    icon: icon,
                    description: description
                };
                var newCard = buildCardElement(newCardConfig);
                layoutBoard.appendChild(newCard);

                if (cardsSortable && typeof cardsSortable.makeDraggable === 'function') {
                    cardsSortable.makeDraggable(newCard);
                }

                var rowList = newCard.querySelector('.projects-view-row-list[data-card-rows]');
                if (rowList) {
                    var SortableCtor = getSortableCtor();
                    registerRowSortable(rowList, pageRoot, SortableCtor);
                }

                emitLayoutUpdate(pageRoot, 'add-card', { card: newCardConfig });
                closeModal(addModalEl);
            });
        }

        var editModalEl = byId('project-view-card-edit-modal');
        var editForm = byId('project-view-card-edit-form');
        if (editModalEl && editForm) {
            editModalEl.addEventListener('show.bs.modal', function (event) {
                var trigger = event && event.relatedTarget ? event.relatedTarget : null;
                if (!trigger) return;

                var card = trigger.closest('.projects-view-card');
                if (!card) return;
                currentCard = card;

                var config = getCardConfig(card);

                var modalTitle = byId('project-view-card-edit-title');
                if (modalTitle) {
                    modalTitle.textContent = 'Edit Card: ' + (config.title || config.id || 'Card');
                }

                setFieldValue('project-view-card-edit-original-id', config.id);
                setFieldValue('project-view-card-edit-id', config.id);
                setFieldValue('project-view-card-edit-name', config.title);
                setFieldValue('project-view-card-edit-icon', config.icon);
                setFieldValue('project-view-card-edit-description', config.description);
            });

            editModalEl.addEventListener('hidden.bs.modal', function () {
                currentCard = null;
            });

            editForm.addEventListener('submit', function (event) {
                event.preventDefault();
                event.stopPropagation();

                if (!currentCard) return;

                var originalId = String(byId('project-view-card-edit-original-id') ? byId('project-view-card-edit-original-id').value : '').trim();
                var newId = normalizeCardId(byId('project-view-card-edit-id') ? byId('project-view-card-edit-id').value : '');
                var newTitle = String(byId('project-view-card-edit-name') ? byId('project-view-card-edit-name').value : '').trim();
                var newIcon = String(byId('project-view-card-edit-icon') ? byId('project-view-card-edit-icon').value : '').trim();
                var newDescription = String(byId('project-view-card-edit-description') ? byId('project-view-card-edit-description').value : '').trim();

                if (!newId || !newTitle) {
                    window.alert('Card ID and title are required.');
                    return;
                }
                if (cardIdExists(pageRoot, newId, currentCard)) {
                    window.alert('Card ID already exists.');
                    return;
                }

                var currentConfig = getCardConfig(currentCard);
                currentConfig.id = newId;
                currentConfig.title = newTitle;
                currentConfig.icon = newIcon;
                currentConfig.description = newDescription;

                renderCard(currentCard, currentConfig);

                emitLayoutUpdate(pageRoot, 'edit-card', {
                    original_id: originalId,
                    card: currentConfig
                });

                closeModal(editModalEl);
            });
        }

        var removeModalEl = byId('project-view-card-remove-modal');
        var removeForm = byId('project-view-card-remove-form');
        if (removeModalEl && removeForm) {
            removeModalEl.addEventListener('show.bs.modal', function (event) {
                var trigger = event && event.relatedTarget ? event.relatedTarget : null;
                if (!trigger) return;

                var card = trigger.closest('.projects-view-card');
                if (!card) return;
                currentCard = card;

                var cardConfig = getCardConfig(card);
                setFieldValue('project-view-card-remove-id', cardConfig.id || '');

                var labelEl = byId('project-view-card-remove-label');
                if (labelEl) {
                    labelEl.textContent = cardConfig.id || '';
                }

                var statusEl = byId('project-view-card-remove-status');
                var submitBtn = byId('project-view-card-remove-submit');
                var rowCount = currentCard.querySelectorAll('.projects-view-row').length;
                var canRemove = rowCount === 0;

                if (statusEl) {
                    statusEl.classList.remove('alert-info', 'alert-warning');
                    statusEl.classList.add(canRemove ? 'alert-info' : 'alert-warning');
                    statusEl.textContent = canRemove
                        ? 'This card is empty and can be removed.'
                        : 'Cannot remove this card: it still contains tables. Move or remove all tables first.';
                }
                if (submitBtn) {
                    submitBtn.disabled = !canRemove;
                }
            });

            removeModalEl.addEventListener('hidden.bs.modal', function () {
                var submitBtn = byId('project-view-card-remove-submit');
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                currentCard = null;
            });

            removeForm.addEventListener('submit', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (!currentCard) return;

                var rowCount = currentCard.querySelectorAll('.projects-view-row').length;
                if (rowCount > 0) {
                    var statusEl = byId('project-view-card-remove-status');
                    var submitBtn = byId('project-view-card-remove-submit');
                    if (statusEl) {
                        statusEl.classList.remove('alert-info');
                        statusEl.classList.add('alert-warning');
                        statusEl.textContent = 'Cannot remove this card: it still contains tables. Move or remove all tables first.';
                    }
                    if (submitBtn) {
                        submitBtn.disabled = true;
                    }
                    return;
                }

                var rowList = currentCard.querySelector('.projects-view-row-list[data-card-rows]');
                if (rowList) {
                    unregisterRowSortable(rowList);
                }

                var cardConfig = getCardConfig(currentCard);
                currentCard.remove();
                currentCard = null;

                emitLayoutUpdate(pageRoot, 'remove-card', { id: cardConfig.id || '' });
                closeModal(removeModalEl);
            });
        }

        pageRoot.addEventListener('click', function (event) {
            var button = event.target && event.target.closest ? event.target.closest('[data-bs-target]') : null;
            if (!button) return;

            var target = button.getAttribute('data-bs-target');
            if (target === '#project-view-card-edit-modal' || target === '#project-view-card-remove-modal') {
                var card = button.closest('.projects-view-card');
                if (!card) {
                    event.preventDefault();
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEditRecordViewPage);
    } else {
        initEditRecordViewPage();
    }
})();
