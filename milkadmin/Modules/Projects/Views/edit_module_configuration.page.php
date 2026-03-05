<?php
!defined('MILK_DIR') && die();

$page = (string) ($page ?? 'projects');
$project = is_array($project ?? null) ? $project : null;

$manifest = is_array($project) && is_array($project['manifest_data'] ?? null) ? $project['manifest_data'] : [];
$projectTitle = trim((string) ($manifest['name'] ?? (is_array($project) ? ($project['project_name'] ?? '') : '')));
$projectDescription = trim((string) ($manifest['description'] ?? (is_array($project) ? ($project['description'] ?? '') : '')));

$currentMenu = trim((string) ($manifest['menu'] ?? ''));
$currentMenuIcon = trim((string) ($manifest['menuIcon'] ?? ''));
$currentSelectMenu = trim((string) ($manifest['selectMenu'] ?? ($manifest['selectedMenu'] ?? ($manifest['select_menu'] ?? ''))));
$moduleMenuOptions = is_array($module_menu_options ?? null) ? $module_menu_options : [];
$menuName = $currentMenu !== '' ? $currentMenu : $projectTitle;

$moduleName = trim((string) ($project['module_name'] ?? ''));
$rightActions = [];
if ($moduleName !== '') {
    $projectHomeUrl = '?page=' . rawurlencode($page)
        . '&action=edit'
        . '&module=' . rawurlencode($moduleName);
    $rightActions[] = [
        'label' => 'Project Home',
        'url' => $projectHomeUrl,
        'class' => 'btn btn-sm btn-outline-secondary',
        'id' => '',
    ];
}
$projectEditContext = \Modules\Projects\ProjectEditContextService::buildBoxData($project, 'Edit Module Configuration', $page, [
    'right_actions' => $rightActions,
]);

$saveProjectSettingsUrl = '?page=' . rawurlencode($page)
    . '&action=save-project-settings'
    . '&module=' . rawurlencode($moduleName);

$selectMenuValueExists = false;
foreach ($moduleMenuOptions as $menuOption) {
    if (!is_array($menuOption)) {
        continue;
    }
    if (trim((string) ($menuOption['page'] ?? '')) === $currentSelectMenu) {
        $selectMenuValueExists = true;
        break;
    }
}
?>
<div class="container-fluid py-3 projects-module-shell">
    <?php $project_edit_context = $projectEditContext; ?>
    <?php include __DIR__ . '/partials/project_edit_context_box.php'; ?>

    <div class="card projects-page-card">
        <div class="card-body">
            <?php if ($project === null): ?>
                <div class="alert alert-warning mb-0">
                    Project not found or invalid <code>module</code> parameter.
                </div>
            <?php else: ?>
                <form method="post" action="<?php _p($saveProjectSettingsUrl); ?>">
                    <input type="hidden" name="return_action" value="edit-module-configuration">

                    <div class="row mb-3">
                        <div class="col-lg-8">
                            <label class="form-label" for="project-title">Project Title</label>
                            <input
                                id="project-title"
                                type="text"
                                name="project_title"
                                class="form-control"
                                required
                                value="<?php _p($projectTitle); ?>"
                            >
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-8">
                            <label class="form-label" for="project-description">Project Description</label>
                            <textarea
                                id="project-description"
                                name="project_description"
                                class="form-control"
                                rows="3"
                            ><?php _p($projectDescription); ?></textarea>
                        </div>

                    </div>
                    <hr class="my-4">
                    <h5 class="h6 text-uppercase text-muted mb-3">Menu Configuration</h5>

                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label" for="menu-name">Menu Name</label>
                            <input
                                id="menu-name"
                                type="text"
                                name="menu_name"
                                class="form-control"
                                value="<?php _p($menuName); ?>"
                            >
                            <div class="form-text">
                                The label shown in the sidebar. Defaults to the project name.
                                If you change it, a <code>menu</code> key will be saved in the manifest.
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label">Menu Icon</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="hidden" name="menu_icon" id="menu-icon-value" value="<?php _p($currentMenuIcon); ?>">
                                <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2" id="menu-icon-btn" data-bs-toggle="modal" data-bs-target="#iconPickerModal">
                                    <i id="menu-icon-preview" class="<?php _p($currentMenuIcon !== '' ? $currentMenuIcon : 'bi bi-question-square'); ?>" style="font-size:1.2rem;"></i>
                                    <span id="menu-icon-label"><?php _p($currentMenuIcon !== '' ? $currentMenuIcon : 'Select icon...'); ?></span>
                                </button>
                                <?php if ($currentMenuIcon !== ''): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="menu-icon-clear" title="Remove icon">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="form-text">
                                The icon shown next to the menu label in the sidebar.
                                A <code>menuIcon</code> key will be saved in the manifest.
                            </div>
                        </div>
                        <div class="col-lg-4"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label" for="select-menu">Select Menu Group</label>
                            <select id="select-menu" name="select_menu" class="form-select">
                                <option value="">-- None --</option>
                                <?php foreach ($moduleMenuOptions as $menuOption): ?>
                                    <?php
                                    if (!is_array($menuOption)) {
                                        continue;
                                    }
                                    $optionPage = trim((string) ($menuOption['page'] ?? ''));
                                    if ($optionPage === '') {
                                        continue;
                                    }
                                    $optionTitle = trim((string) ($menuOption['title'] ?? ''));
                                    if ($optionTitle === '') {
                                        $optionTitle = $optionPage;
                                    }
                                    ?>
                                    <option value="<?php _p($optionPage); ?>" <?php echo $currentSelectMenu === $optionPage ? 'selected' : ''; ?>>
                                        <?php _p($optionTitle); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($currentSelectMenu !== '' && !$selectMenuValueExists): ?>
                                    <option value="<?php _p($currentSelectMenu); ?>" selected>
                                        <?php _p($currentSelectMenu . ' (legacy)'); ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">
                                Optional. If set, the manifest saves <code>selectMenu</code> and this module is grouped with linked modules in the sidebar.
                            </div>
                        </div>
                         <div class="col-lg-8"></div>
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Configuration</button>
                        <a class="btn btn-outline-secondary" href="?page=<?php _p($page); ?>&action=edit&module=<?php _p(rawurlencode($moduleName)); ?>">
                            Back to Project Edit
                        </a>
                    </div>
                </form>

                <!-- Icon Picker Modal -->
                <div class="modal fade" id="iconPickerModal" tabindex="-1" aria-labelledby="iconPickerModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="iconPickerModalLabel">Select Icon</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <input type="text" class="form-control mb-3" id="icon-picker-search" placeholder="Search icons..." autocomplete="off">
                                <div id="icon-picker-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(52px, 1fr)); gap:4px;"></div>
                                <div id="icon-picker-empty" class="text-muted text-center py-3" style="display:none;">No icons found.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                (function() {
                    // --- Icon picker ---
                    var iconValue = document.getElementById('menu-icon-value');
                    var iconPreview = document.getElementById('menu-icon-preview');
                    var iconLabel = document.getElementById('menu-icon-label');
                    var iconBtn = document.getElementById('menu-icon-btn');
                    var iconClear = document.getElementById('menu-icon-clear');
                    var grid = document.getElementById('icon-picker-grid');
                    var searchInput = document.getElementById('icon-picker-search');
                    var emptyMsg = document.getElementById('icon-picker-empty');

                    var allIcons = [];
                    var cells = [];

                    // Extract icon names from loaded Bootstrap Icons stylesheet
                    function extractIcons() {
                        var names = [];
                        for (var s = 0; s < document.styleSheets.length; s++) {
                            var rules;
                            try { rules = document.styleSheets[s].cssRules; } catch(e) { continue; }
                            if (!rules) continue;
                            for (var r = 0; r < rules.length; r++) {
                                var sel = rules[r].selectorText || '';
                                var m = sel.match(/^\.bi-([\w-]+)::before$/);
                                if (m) names.push(m[1]);
                            }
                        }
                        return names;
                    }

                    function buildGrid() {
                        allIcons = extractIcons();
                        if (allIcons.length === 0) return;

                        var frag = document.createDocumentFragment();
                        cells = [];
                        for (var i = 0; i < allIcons.length; i++) {
                            var name = allIcons[i];
                            var cell = document.createElement('button');
                            cell.type = 'button';
                            cell.className = 'btn btn-outline-secondary p-0 d-flex align-items-center justify-content-center';
                            cell.style.cssText = 'width:48px;height:48px;font-size:1.3rem;';
                            cell.title = 'bi bi-' + name;
                            cell.dataset.icon = name;
                            cell.innerHTML = '<i class="bi bi-' + name + '"></i>';
                            frag.appendChild(cell);
                            cells.push({ el: cell, name: name });
                        }
                        grid.appendChild(frag);
                    }

                    // Lazy build: only build grid when modal opens
                    var modalEl = document.getElementById('iconPickerModal');
                    var gridBuilt = false;
                    if (modalEl) {
                        modalEl.addEventListener('shown.bs.modal', function() {
                            if (!gridBuilt) {
                                buildGrid();
                                gridBuilt = true;
                            }
                            searchInput.value = '';
                            filterIcons('');
                            searchInput.focus();
                        });
                    }

                    // Select icon
                    grid.addEventListener('click', function(e) {
                        var btn = e.target.closest('button[data-icon]');
                        if (!btn) return;
                        var cls = 'bi bi-' + btn.dataset.icon;
                        setIcon(cls);
                        var bsModal = bootstrap.Modal.getInstance(modalEl);
                        if (bsModal) bsModal.hide();
                    });

                    // Search/filter
                    searchInput.addEventListener('input', function() {
                        filterIcons(this.value.trim().toLowerCase());
                    });

                    function filterIcons(q) {
                        var visible = 0;
                        for (var i = 0; i < cells.length; i++) {
                            var show = q === '' || cells[i].name.indexOf(q) !== -1;
                            cells[i].el.style.display = show ? '' : 'none';
                            if (show) visible++;
                        }
                        emptyMsg.style.display = visible === 0 ? '' : 'none';
                    }

                    function setIcon(cls) {
                        iconValue.value = cls;
                        iconPreview.className = cls;
                        iconPreview.style.fontSize = '1.2rem';
                        iconLabel.textContent = cls;
                        ensureClearButton();
                    }

                    function ensureClearButton() {
                        if (!iconClear) {
                            iconClear = document.createElement('button');
                            iconClear.type = 'button';
                            iconClear.className = 'btn btn-outline-danger btn-sm';
                            iconClear.id = 'menu-icon-clear';
                            iconClear.title = 'Remove icon';
                            iconClear.innerHTML = '<i class="bi bi-x-lg"></i>';
                            iconBtn.parentNode.appendChild(iconClear);
                            iconClear.addEventListener('click', clearIcon);
                        }
                    }

                    function clearIcon() {
                        iconValue.value = '';
                        iconPreview.className = 'bi bi-question-square';
                        iconPreview.style.fontSize = '1.2rem';
                        iconLabel.textContent = 'Select icon...';
                        if (iconClear) {
                            iconClear.remove();
                            iconClear = null;
                        }
                    }

                    if (iconClear) {
                        iconClear.addEventListener('click', clearIcon);
                    }
                })();
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>
