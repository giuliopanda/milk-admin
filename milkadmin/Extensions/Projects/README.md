# Projects Extension

The `Projects` extension lets you define a **project** as a set of **forms/models** described by JSON schema files, connected through a **manifest tree** (nested forms).

It provides:

- Automatic `RequestAction` registration for each form (`*-list` / `*-edit`, optional `*-view`).
- Automatic parent-child linking via FK conventions (parent id in URL and saved in the child record).
- Automatic closure-style root linking via `root_id` on every child form (first-level and nested).
- Automatic `withCount()` columns on parent lists for **direct children only**, based on the manifest tree.
- Special behavior for single-record child forms (`max_records: 1`) to behave like `hasOne`.
- Optional JSON-driven root record view layout via `Project/view_layout.json`.
- Optional JSON-driven root list search filters via `Project/search_filters.json`.

Additional docs:

- `milkadmin/Extensions/Projects/JSON_OPTIONS.md`
- `milkadmin/Extensions/Projects/VIEW_LAYOUT.md`
- `milkadmin/Extensions/Projects/PERMISSIONS_HOOKS.md`

This README describes how to structure a module that uses it, how to write the manifest, and what conventions are enforced.

## Programmatic Manifest Access

If you need manifest data outside extension internals, use:

```php
use Extensions\Projects\Classes\ProjectJsonStore;

$manifest = ProjectJsonStore::getCurrentManifestData(); // or ProjectJsonStore::getCurrentManifestData('module-page')
if ($manifest === false) {
    // Current/requested module does not use Projects extension
    // or manifest is missing/unreadable/invalid.
}
```

`ProjectJsonStore::getCurrentManifestData(?string $modulePage = null): array|false`

- Returns manifest data (`array`) for the current request page (`$_REQUEST['page']`) or for the provided `$modulePage`.
- Returns `false` when:
  - no module instance is available for that page,
  - the module does not use `Projects` extension,
  - `manifest.json` is missing/unreadable/invalid.

## Additional Permissions (Short Note)

`Projects` exposes special permissions through hook `projects.get-special-permissions`.

Projects exposes permissions for every form in the manifest:

- root form keeps `project.<manifest_id>.main_table_*`
- child forms use `project.<manifest_id>.<form_name_snake>__*`
- standard columns are generated dynamically from each form config: `view`, `edit`, `soft delete`, `hard delete`

Notes:

- `<manifest_id>` comes from `manifest.json` field `id` (not module page).
- Permission keys are snake_case.
- Runtime checks keep backward compatibility with legacy `main_table_delete` roles.
- Extra shared permissions are declared in `manifest.json` with `additionalPermissions`, for example `"additionalPermissions": ["download"]`.
- Every `additionalPermissions` entry becomes one global toggle in the Additional permissions area of `UserRights`.
- The extension also sends lightweight UI metadata used by `UserRights`:
  - `block_title` (project name from manifest),
  - `block_name` (currently `Access Data`),
  - `row_name` (table `_name` from each schema JSON).

Runtime checks are delegated to:

- `Hooks::run("projects.check_special_permission", true, 'project.' . $permissionName)`

## Query Hook (Rows + Total)

Projects emits a hook with SQL payload for auto-list tables:

- Hook name: `projects.query.before-execute`
- Stage: emitted before list queries are executed (`beforeGetData`)
- Fired twice per table data load:
  - once for rows query (`is_total = false`)
  - once for total/count query (`is_total = true`)

The first hook argument is a payload array with:

- `is_total` (`bool`)
- `query_type` (`rows|total`)
- `stage` (`before_get_data`)
- `page` (`string`)
- `table_id` (`string`)
- `request` (`array`)
- `query` (`App\Database\Query`)
- `sql` (`string`, with placeholders)
- `params` (`array`)
- `manifest` (`array|null`) current project `manifest.json` when available
- `model` (`App\Abstracts\AbstractModel|null`)
- `model_columns` (`array<string>`)

Example:

```php
\App\Hooks::set('projects.query.before-execute', function (array $payload) {
    $type = $payload['is_total'] ? 'TOTAL' : 'ROWS';
    $sql = (string) ($payload['sql'] ?? '');
    $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];

    \App\Logs::set('SYSTEM', "Projects {$type} SQL: {$sql} | params=" . json_encode($params), 'INFO');

    return $payload;
});
```

## Post-Extract Visibility Hook

Projects also emits a hook after list rows are extracted, useful for correlated-table access checks:

- Hook name: `projects.data.after-extract-visibility`
- Stage: emitted in `afterGetData`
- Typical use: validate extracted rows visibility and stop rendering when data is not allowed.

Payload keys:

- `stage` (`after_get_data`)
- `page` (`string`)
- `table_id` (`string`)
- `request` (`array`)
- `query` (`App\Database\Query`)
- `manifest` (`array|null`)
- `model` (`App\Abstracts\AbstractModel|null`)
- `model_columns` (`array<string>`)
- `data` (`array`) table payload (`rows`, `info`, `page_info`)

## Record Pre-Save Hooks

Projects emits hooks before executing record save queries in edit forms (`FormBuilder::beforeSave`):

- `projects.record.create.before-save`: fired before saving a new record.
- `projects.record.update.before-save`: fired before saving an existing record.
- `projects.record.related.before-save`: fired before saving related-table records (child/non-root forms), for both create/update operations.

All hooks are emitted at stage `before_save_query` and receive a payload as first argument.

Main payload keys:

- `hook` (`string`)
- `stage` (`before_save_query`)
- `operation` (`create|update`)
- `is_create` (`bool`)
- `is_update` (`bool`)
- `is_related_table` (`bool`)
- `page` (`string`)
- `request` (`array`) mutable save input data
- `record_id` (`int`)
- `primary_key` (`string`)
- `model` (`App\Abstracts\AbstractModel|object|null`)
- `model_class` (`string`)
- `table` (`string`)
- `form_name` (`string`)
- `fk_field` (`string`)
- `root_field` (`string`)
- `root_id` (`int`)
- `max_records` (`string`)
- `manifest` (`array|null`) project `manifest.json`
- `context` (`array`) Projects action context

Example:

```php
\App\Hooks::set('projects.record.create.before-save', function (array $payload) {
    $request = is_array($payload['request'] ?? null) ? $payload['request'] : [];
    $request['updated_by_hook'] = 1;
    $payload['request'] = $request;

    return $payload;
});
```

## Quick Start

1. Enable the module extension:

```php
// <ModuleDir>/<YourModule>Module.php
$rule->extensions(['Projects']);
```

2. For every model used by the project, enable the model extension:

```php
// <ModuleDir>/<FormName>Model.php
$rule->table('your_table')->db('db')->id('id')->extensions(['Projects']);
```

3. Create a `Project/` folder inside your module:

```
milkadmin_local/Modules/YourModule/
  YourModuleModule.php
  RootFormModel.php
  ChildFormModel.php
  Project/
    manifest.json
    search_filters.json
    RootForm.json
    ChildForm.json
```

Model classes can stay in one of these locations:

- `<ModuleDir>/<FormName>Model.php` (searched first)
- `<ModuleDir>/Project/Models/<FormName>Model.php` (fallback)

4. Put a schema JSON next to the manifest, one file per model (name rules below).

5. Make sure the DB tables exist (the extension does not create tables for you).

## File/Name Conventions

### JSON Schema File Name

Schema file name must match the model name without the `Model` suffix:

- `VisitBaselineModel` -> `Project/VisitBaseline.json`
- `AdverseEventsModel` -> `Project/AdverseEvents.json`

### Form Name

The form name is derived from the schema `ref` filename (without extension):

- `"ref": "VisitBaseline.json"` -> form name `VisitBaseline`

Form names must be **unique** within the manifest tree (otherwise route conflicts happen).

### Action Names

Actions are derived from the form name (kebab-case):

- `VisitBaseline` -> `visit-baseline-list`, `visit-baseline-edit`
- `AdverseEvents` -> `adverse-events-list`, `adverse-events-edit`
- With `viewAction: true`: adds `visit-baseline-view`, `adverse-events-view`, ...

These actions are registered at runtime by the module extension.

## Manifest (Tree Format)

Manifest path:

`<ModuleDir>/Project/manifest.json`

Tree format (nested `forms`):

```json
{
  "_version": "1.0",
  "_name": "My Project",
  "settings": {
    "description": "Example project"
  },
  "forms": [
    {
      "ref": "RootA.json",
      "forms": [
        { "ref": "ChildA1.json", "max_records": 1 },
        { "ref": "ChildA2.json", "max_records": "n" }
      ]
    },
    {
      "ref": "RootB.json",
      "forms": [
        {
          "ref": "ChildB1.json",
          "max_records": "n",
          "showIf": "[AGE] < 60",
          "listDisplay": "offcanvas",
          "editDisplay": "modal",
          "forms": [
            { "ref": "GrandChildB1a.json", "max_records": 1 }
          ]
        }
      ]
    }
  ]
}
```

### Optional module menu keys (manifest root)

At manifest root level you can also define:

- `menu`: custom sidebar label for the module
- `menuIcon`: custom sidebar icon class
- `selectMenu`: group name (or object config) to attach the module to a selected menu container

Example:

```json
{
  "_name": "Clinical Visits",
  "menu": "Clinical Visits",
  "menuIcon": "bi bi-clipboard2-pulse",
  "selectMenu": "Studies",
  "forms": [
    { "ref": "VisitBaseline.json" }
  ]
}
```

### `max_records`

`max_records` defines how many child records are allowed per parent record.

Supported values:

- `1` or `"1"`: single record per parent (hasOne-like)
- `2`, `3`, ... (or numeric strings): finite max records per parent
- `"n"`: multiple records per parent (hasMany-like)
- `"unlimited"`: treated like `"n"` (reserved for future)

If omitted, it defaults to `"n"`.

### `showIf`

Optional expression (ExpressionParser syntax) evaluated against the **immediate parent record**.

- If `showIf` is `false`, child navigation is blocked (no clickable child link in parent table).
- Direct URL access to child `*-list` / `*-edit` is also blocked.
- You can define `showIfMessage` to show a custom reason when blocked.

Example:

```json
{
  "ref": "VisitChecks.json",
  "max_records": 1,
  "showIf": "[AGE] < 60",
  "showIfMessage": "Visit checks are available only for patients under 60."
}
```

### `listDisplay` / `editDisplay`

Optional UI mode for each form action.

- `listDisplay`: controls how `<form>-list` opens
- `editDisplay`: controls how `<form>-edit` opens

Allowed values:

- `"page"` (default, normal navigation)
- `"offcanvas"` (fetch + offcanvas)
- `"modal"` (fetch + modal)

Snake case aliases are also accepted:

- `list_display`
- `edit_display`

If the action is opened without fetch (normal browser navigation), it still falls back to a standard page render.

Implementation notes:

- The extension automatically adds `data-fetch="post"` to generated links/buttons when target mode is `offcanvas` or `modal`.
- On fetch edit forms, the extension keeps track of the source table context and reloads the correct table after save.
- This also works for nested flows (for example: parent list in offcanvas -> child edit in modal).

### `viewAction` / `viewDisplay`

Optional view mode for each form:

- `viewAction`: when enabled, generates an additional `*-view` action.
- `viewDisplay`: controls how `<form>-view` opens (`page`, `offcanvas`, `modal`).

The generated view page shows:

- Main form data as `Label: Value` rows (uses model `getFormattedData()`).
- One table for each direct child form, filtered by current parent record id.

## Parent/Child Linking (FK Convention + `root_id`)

Every child form links to its **immediate parent** using an FK field:

`<parent_form_snake>_id`

Additionally, every child form stores the root record id in:

`root_id`

Examples:

- Parent form `ProjectsExtensionTest` -> child FK is `projects_extension_test_id`
- Parent form `VisitBaseline` -> child FK is `visit_baseline_id`
- Parent form `Staff` -> child FK is `staff_id`
- First-level child of `ProjectsExtensionTest`: `projects_extension_test_id = root_id`
- Nested child (`AdverseEvents` under `VisitBaseline`): `visit_baseline_id = <immediate parent>`, `root_id = <ProjectsExtensionTest id>`

The extension enforces this in two ways:

1. **Model rule injection**: each child model gets missing FK fields injected (`<parent>_id` and `root_id`, readonly, required).
2. **Form value enforcement**: the edit page sets FK values as real `data[...]` inputs so they are saved.

Important note:

- `FormBuilder->customData()` creates a top-level hidden input (e.g. `<input name="visit_baseline_id">`) and is **not saved** because FormBuilder saves only `$_REQUEST['data']`.
- Therefore, the extension sets FKs as real form field values using `FormBuilder->field($fk)->value($id)` (including `root_id`).

## Nested Navigation (FK Chain in Query String)

For nested forms, the system propagates the full parent id chain in the URL.

Example: `AdverseEvents` is a child of `VisitBaseline`, which is a child of `ProjectsExtensionTest`:

```
?page=projects-extension-test
&action=adverse-events-list
&projects_extension_test_id=1
&visit_baseline_id=5
```

Why this matters:

- List reloads (fetch) and redirects keep the full context.
- "Back to parent list" can be generated without extra DB queries.
- `root_id` can be derived consistently at any nesting level.

Tradeoff:

- Deep nesting means more query parameters (one FK per level). This is intentional and predictable.

## What Gets Auto-Generated

### Request Actions

For every form in the manifest tree, the module extension registers:

- `<form>-list` -> list page (TableBuilder)
- `<form>-edit` -> edit page (FormBuilder)
- `<form>-view` -> optional detail page (enabled with `viewAction`)

### Home Links

Only **root forms** are listed in extension home links.

In your module `home` action you can render them via:

- `$projectsExtension->getHomeLinks()`

(see `milkadmin_local/Modules/ProjectsExtensionTest/ProjectsExtensionTestModule.php`).

### `withCount()` on Parent Lists (Direct Children Only)

If a form has direct children in the manifest, the model extension adds a `withCount()` virtual column for each direct child:

- Alias: `<child_form_snake>_count`
- Label: `<Child Form Title>` (the header does not include the word "Count")

The module extension renders those columns as clickable icons/links:

- For `max_records: 1`:
  - if the child has nested forms:
    - if count > 0: green check icon linking to `*-list`
    - if count = 0: plus icon linking to `*-list` (the list allows creating at most one row)
  - if the child has no nested forms:
    - if count > 0: green check icon linking to `*-edit` (existing record resolved server-side)
    - if count = 0: plus icon linking to `*-edit` without `id` (create)
- For `"n"`:
  - if count = 0: plus icon linking to `*-edit` (create)
  - if count > 0: badge with number linking to `*-list`

Per-form override in `manifest.json`:

- `childCountColumn: "hide"` -> always hide direct-child count columns in that form list.
- `childCountColumn: "show"` -> always show direct-child count columns in that form list.
- omitted -> keep default behavior (hidden on root when `viewAction/viewSingleRecord` is active, shown otherwise).

## Single-Record Child Forms (`max_records: 1`)

When a child form is configured with `max_records: 1`:

- If the form has no nested forms:
  - there is **no list** for that child (`*-list` redirects to `*-edit`).
- If the form has nested forms:
  - `*-list` is shown (single row max) so nested navigation stays accessible.
- `*-edit` without `id`:
  - if a record already exists for the parent, it redirects to the existing record edit.
- Save behavior:
  - without nested forms, flow goes back to the **parent list**;
  - with nested forms, flow goes back to the child **own list** (`*-list`).
- Save enforcement:
  - if the user tries to create a second record manually, a FormBuilder extension converts the insert into an update of the existing record (see `milkadmin/Extensions/Projects/FormBuilder.php`).

## JSON Schema (`Project/*.json`)

The extension currently applies the `model` section of the schema (fields).

Example:

```json
{
  "_version": "1.0",
  "_name": "Visit Baseline",
  "model": {
    "fields": [
      { "name": "visit_date", "method": "date", "label": "Visit Date", "required": true },
      { "name": "notes", "method": "text", "label": "Notes", "formType": "textarea" }
    ]
  }
}
```

Notes:

- You should keep `id()` and table/db config in the PHP model `configure()`.
- The JSON is used to add additional fields.
- If a JSON field has the same `name` as a field already defined in the PHP model RuleBuilder, it is **ignored** (no config/relationships are applied).

Debug tip:

- You can detect which fields will be ignored before applying a schema via `ModelSchemaSection::analyzeIgnoredFields()` (or `ModelJsonParser::analyzeIgnoredFields()`).

## Example Module (Reference)

The repository includes a working example:

- Module: `milkadmin_local/Modules/ProjectsExtensionTest/ProjectsExtensionTestModule.php`
- Manifest: `milkadmin_local/Modules/ProjectsExtensionTest/Project/manifest.json`
- Schemas: `milkadmin_local/Modules/ProjectsExtensionTest/Project/*.json`
- Models: `milkadmin_local/Modules/ProjectsExtensionTest/*Model.php`

## Limitations / Notes

- The extension does not create DB tables or columns. You must create them yourself.
- For child tables, ensure both columns exist: immediate parent FK (`<parent_form_snake>_id`) and `root_id`.
- Relationships beyond simple parent FK + withCount are not implemented here.
- Sorting/ordering of forms in the manifest is not used yet.
- Legacy flat manifest selectors (for example `default_form`) are not supported.
