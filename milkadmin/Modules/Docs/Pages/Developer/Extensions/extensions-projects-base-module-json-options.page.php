<?php
namespace Modules\Docs\Pages;
/**
 * @title Projects: Base Module + JSON Options
 * @order 41
 * @tags extensions, projects, manifest, json, schema, search_filters, view_layout, module, tutorial
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Projects Extension: Base Module with Project Folder</h1>
    <p class="text-muted">Revision: 2026/02/26</p>

    <p>This guide shows how to create a base module with <code>Extensions/Projects</code> using JSON files in <code>Project/</code>, without implementing <code>list()</code> and <code>edit()</code> methods in the Module.</p>

    <h2 class="mt-4">1) Minimal Module Structure</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>milkadmin_local/Modules/BlogPosts/
├── BlogPostsModule.php
├── BlogPostsModel.php
└── Project/
    ├── manifest.json
    ├── BlogPosts.json
    └── search_filters.json (optional)</code></pre>

    <h2 class="mt-4">2) Module Without list/edit</h2>
    <p>In the module, you enable <code>Projects</code> and let the extension automatically register actions <code>*-list</code>, <code>*-edit</code>, <code>*-view</code>, <code>*-delete-confirm</code> based on the manifest.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">namespace Local\Modules\BlogPosts;

use App\Abstracts\AbstractModule;
use App\Attributes\RequestAction;
use App\Route;

class BlogPostsModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('blog-posts')
            ->title('Blog Posts')
            ->menu('Blog Posts', '', 'bi bi-journal-text', 200)
            ->access('authorized')
            ->extensions(['Projects'])
            ->version('1.0.0');
    }

    #[RequestAction('home')]
    public function home(): void
    {
        $projects = $this->getLoadedExtensions('Projects');
        if (is_object($projects) && method_exists($projects, 'getPrimaryFormLink')) {
            $links = $projects->getPrimaryFormLink();
            $first = is_array($links[0] ?? null) ? $links[0] : [];
            $action = (string) ($first['action'] ?? '');
            if ($action !== '') {
                Route::redirect('?page=' . $this->getPage() . '&action=' . $action);
                return;
            }
        }

        Route::redirect('?page=' . $this->getPage());
    }
}</code></pre>

    <h2 class="mt-4">3) Esempio manifest.json</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
  "_version": "1.0",
  "_name": "Blog Posts",
  "menu": "Blog Posts",
  "menuIcon": "bi bi-journal-text",
  "forms": [
    {
      "ref": "BlogPosts.json",
      "viewAction": true,
      "max_records": "n"
    }
  ]
}</code></pre>

    <h2 class="mt-4">4) BlogPosts.json Schema Example (post + cover image + categories)</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
  "_version": "1.0",
  "_name": "Blog Posts",
  "model": {
    "fields": [
      { "name": "id", "method": "id" },
      { "name": "title", "method": "title", "required": true },
      { "name": "slug", "method": "string", "required": true, "unique": true },
      { "name": "excerpt", "method": "text", "dbType": "text" },
      { "name": "content", "method": "text", "formType": "editor" },
      {
        "name": "cover_image",
        "method": "image",
        "label": "Cover image",
        "uploadDir": "blog-posts",
        "maxFiles": 1,
        "accept": "image/*"
      },
      {
        "name": "categories",
        "method": "checkboxes",
        "label": "Categories",
        "options": {
          "tech": "Tech",
          "news": "News",
          "tutorial": "Tutorial",
          "events": "Events"
        }
      },
      { "name": "is_published", "method": "checkbox", "default": 0 },
      { "name": "published_at", "method": "datetime", "nullable": true }
    ]
  }
}</code></pre>

    <h2 class="mt-4">Complete JSON_OPTIONS.md Reference</h2>
    <p>This section documents all options supported by <code>milkadmin/Extensions/Projects/JSON_OPTIONS.md</code> in a compact format.</p>

    <h3 class="mt-4">Supported JSON File Types</h3>
    <ul>
        <li><code>Project/manifest.json</code>: project structure and nested form tree</li>
        <li><code>Project/&lt;FormName&gt;.json</code>: form/model schema</li>
        <li><code>Project/view_layout.json</code>: root <code>*-view</code> page layout (single record view)</li>
        <li><code>Project/search_filters.json</code>: root <code>*-list</code> search filters</li>
    </ul>

    <h3 class="mt-4"><code>Project/manifest.json</code>: root keys</h3>
    <ul>
        <li><code>_version</code>, <code>_name</code> (optional)</li>
        <li><code>settings</code> (object, optional)</li>
        <li><code>menu</code>, <code>menuIcon</code> (module menu override)</li>
        <li><code>selectMenu</code> string/object (alias: <code>selectedMenu</code>, <code>select_menu</code>)</li>
        <li><code>forms</code> (array, required)</li>
    </ul>

    <h3 class="mt-4"><code>forms[]</code> (Recursive Node)</h3>
    <ul>
        <li><code>ref</code> (string, required)</li>
        <li><code>max_records</code>: <code>1</code>, intero &gt;=2, <code>n</code>, <code>unlimited</code></li>
        <li><code>showIf</code>, <code>showIfMessage</code></li>
        <li><code>viewAction</code> (alias <code>view_action</code>)</li>
        <li><code>viewDisplay</code>, <code>listDisplay</code>, <code>editDisplay</code> (alias snake_case)</li>
        <li><code>forms</code> (children)</li>
        <li><code>existingTable</code> (alias <code>existing_table</code>)</li>
    </ul>

    <h3 class="mt-4">Legacy Keys Not Supported by the Current Parser</h3>
    <ul>
        <li><code>settings.default_form</code></li>
        <li><code>settings.default_form_ref</code></li>
        <li><code>default_form</code></li>
    </ul>

    <h3 class="mt-4"><code>Project/&lt;FormName&gt;.json</code>: Sections</h3>
    <ul>
        <li>Extension-used section: <code>model</code></li>
        <li>Recognized metadata: <code>_version</code>, <code>_name</code>, <code>_description</code>, <code>_author</code>, <code>_created</code>, <code>_updated</code>, and keys starting with <code>_</code></li>
    </ul>

    <h3 class="mt-4"><code>model</code>: Supported Keys</h3>
    <ul>
        <li><code>table</code>, <code>db</code></li>
        <li><code>extensions</code></li>
        <li><code>rename_fields</code></li>
        <li><code>removePrimaryKeys</code></li>
        <li><code>fields</code></li>
    </ul>

    <h3 class="mt-4"><code>model.fields[]</code>: Main Keys</h3>
    <ul>
        <li>Required: <code>name</code></li>
        <li><code>method</code> (default <code>string</code>)</li>
        <li><code>replace</code> (note: fields already defined in the PHP model are ignored by the JSON parser)</li>
        <li>Type parameters: <code>type</code>, <code>length</code>, <code>precision</code>, <code>dbType</code>, <code>options</code></li>
        <li>Standard config: <code>label</code>, <code>default</code>, <code>formType</code>, <code>formLabel</code>, <code>error</code>, <code>calcExpr</code>, <code>step</code>, <code>min</code>, <code>max</code>, <code>accept</code>, <code>uploadDir</code>, <code>saveValue</code>, <code>requireIf</code>, <code>maxFiles</code>, <code>maxSize</code>, <code>formParams</code>, <code>properties</code></li>
        <li>Boolean flags: <code>required</code>, <code>unique</code>, <code>index</code>, <code>hideFromList</code>, <code>hideFromEdit</code>, <code>hideFromView</code>, <code>hide</code>, <code>excludeFromDatabase</code>, <code>unsigned</code>, <code>noTimezoneConversion</code></li>
        <li>Special: <code>nullable</code>, <code>builderLocked</code> (alias <code>builder_locked</code>), <code>multiple</code>, <code>validateExpr</code>, <code>apiUrl</code>, <code>apiDisplayField</code>, <code>checkboxValues</code>, <code>property</code></li>
    </ul>

    <h3 class="mt-4">Supported <code>method</code> Values</h3>
    <p><code>id</code>, <code>primaryKey</code>, <code>string</code>, <code>title</code>, <code>text</code>, <code>int</code>, <code>decimal</code>, <code>datetime</code>, <code>date</code>, <code>time</code>, <code>timestamp</code>, <code>created_at</code>, <code>email</code>, <code>tel</code>, <code>url</code>, <code>file</code>, <code>image</code>, <code>boolean</code>, <code>checkbox</code>, <code>checkboxes</code>, <code>radio</code>, <code>list</code>, <code>select</code>, <code>enum</code>, <code>array</code>, <code>field</code>.</p>

    <h3 class="mt-4">Field Relationships</h3>
    <ul>
        <li><code>belongsTo</code>: <code>alias</code>, <code>related_model</code>, <code>related_key</code>, <code>where</code></li>
        <li><code>hasOne</code>/<code>hasMany</code>: <code>alias</code>, <code>related_model</code>, <code>foreign_key</code>, <code>onDelete</code>, <code>allowCascadeSave</code>, <code>where</code></li>
        <li><code>withCount</code>: object or array of objects</li>
        <li><code>hasMeta</code>: object or array with <code>meta_key_column</code>, <code>meta_value_column</code>, <code>meta_key_value</code>, etc.</li>
    </ul>

    <h3 class="mt-4"><code>Project/search_filters.json</code></h3>
    <ul>
        <li>Optional; applied only to the root list table</li>
        <li>Formats: single config or multi-form (<code>forms</code> + fallback <code>*</code>)</li>
        <li>Main keys: <code>search_mode</code>, <code>auto_buttons</code>, <code>wrapper_class</code>, <code>form_classes</code>, <code>container_classes</code>, <code>url_params</code>, <code>filters</code></li>
        <li><code>url_params</code> supports: <code>field</code>, <code>operator</code>, <code>type</code>, <code>required</code>, <code>max_length</code></li>
        <li>Behavior: URL params whitelist, type-based sanitization, propagation to links/actions, fail-closed if <code>required=true</code> and value is invalid</li>
        <li>Filter types: <code>search</code>, <code>select</code>, <code>action_list</code>, <code>input</code>, <code>search_button</code>, <code>clear_button</code></li>
        <li>Operators: <code>like</code>, <code>equals</code>, <code>starts_with</code>, <code>ends_with</code>, <code>greater_than</code>, <code>greater_or_equal</code>, <code>less_than</code>, <code>less_or_equal</code>, <code>between</code> (+ aliases <code>gt/gte/lt/lte/&gt;/&gt;=/&lt;/&lt;=</code>)</li>
    </ul>

    <h3 class="mt-4"><code>list_options</code> in Fields</h3>
    <ul>
        <li><code>link</code>: <code>url</code> with placeholders (<code>%id%</code>) and <code>target</code></li>
        <li><code>html</code>: render HTML raw</li>
        <li><code>truncate</code>: character limit</li>
        <li><code>change_values</code>: maps saved value -&gt; displayed label</li>
    </ul>

    <h3 class="mt-4">Runtime FK Conventions (Manifest Child Forms)</h3>
    <ul>
        <li>FK parent: <code>&lt;parent_form_snake&gt;_id</code></li>
        <li>FK root closure: <code>root_id</code></li>
        <li>In auto-generated child forms: required, hidden list, readonly, <code>builderLocked=true</code></li>
        <li>Technical child FK fields are always non-editable in the build editor</li>
    </ul>

    <h3 class="mt-4">Debug and Build Flow</h3>
    <ul>
        <li>Ignored fields analysis: <code>ModelJsonParser::analyzeIgnoredFields(...)</code>, <code>ModelSchemaSection::analyzeIgnoredFields(...)</code></li>
        <li><code>build-form-fields</code> uses a draft + review flow before final JSON save</li>
    </ul>

    <div class="alert alert-info mt-4 mb-0">
        For full <code>view_layout.json</code> details, see <code>milkadmin/Extensions/Projects/VIEW_LAYOUT.md</code>.
    </div>
</div>
