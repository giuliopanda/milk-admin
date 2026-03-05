<?php
namespace Modules\Docs\Pages;

/**
 * @title Projects Extension Quickstart
 * @order 40
 * @tags extensions, projects, manifest, json, schema, post, image, categories
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
    <h1>Projects Extension: First Practical Page</h1>
    <p class="text-muted">Revision: 2026/02/26</p>

    <p>This guide shows a first concrete setup of <code>Extensions/Projects</code> using a demo <strong>post</strong> module with:</p>
    <ul>
        <li>cover image field (<code>image</code>)</li>
        <li>multiple categories field (<code>checkboxes</code>)</li>
        <li>JSON files inside the <code>Project/</code> folder</li>
    </ul>

    <h2>Demo Module Used</h2>
    <p>Module path:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code>milkadmin_local/Modules/ProjectPostsDemo/
├── ProjectPostsDemoModule.php
├── ProjectPostsDemoModel.php
└── Project/
    ├── manifest.json
    ├── ProjectPostsDemo.json
    └── search_filters.json</code></pre>

    <h2 class="mt-4">1) Enable the Extension in the Module</h2>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">protected function configure($rule): void
{
    $rule->page('project-posts-demo')
        ->title('Project Posts Demo')
        ->menu('Project Posts Demo', '', 'bi bi-journal-richtext', 220)
        ->access('authorized')
        ->extensions(['Projects'])
        ->version('1.0.0');
}</code></pre>

    <h2 class="mt-4">2) Minimal Manifest</h2>
    <p>File: <code>Project/manifest.json</code></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
  "_version": "1.0",
  "_name": "Project Posts Demo",
  "menu": "Project Posts Demo",
  "menuIcon": "bi bi-journal-richtext",
  "ref": "ProjectPostsDemo.json",
  "viewAction": true,
  "max_records": "n",
  "forms": []
}</code></pre>

    <h2 class="mt-4">3) Post JSON Schema</h2>
    <p>File: <code>Project/ProjectPostsDemo.json</code></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-json">{
  "_version": "1.0",
  "_name": "Project Posts Demo",
  "model": {
    "fields": [
      { "name": "title", "method": "title", "required": true },
      { "name": "slug", "method": "string", "required": true, "unique": true },
      { "name": "content", "method": "text", "formType": "editor" },
      {
        "name": "cover_image",
        "method": "image",
        "label": "Cover image",
        "uploadDir": "project-posts-demo",
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
      }
    ]
  }
}</code></pre>

    <h2 class="mt-4">4) List Filters (Optional)</h2>
    <p>File: <code>Project/search_filters.json</code> to add text search and category filter in the root list.</p>

    <h2 class="mt-4">Related Automated Test</h2>
    <p>The demo module is also used in unit tests: <code>tests/Unit/Extensions/Projects/ProjectPostsDemoJsonStoreTest.php</code>.</p>

    <div class="alert alert-info mt-3 mb-0">
        This page is the first step: from here you can extend the demo with relations (<code>belongsTo</code>/<code>hasMany</code>),
        <code>view_layout.json</code>, and nested forms in <code>manifest.json</code>.
    </div>
</div>
