<?php
namespace Modules\Docs\Pages;

use Builders\LinksBuilder;
use App\{Theme, Route, Response};

/**
 * @title LinksBuilder
 * @guide developer
 * @order 20
 * @tags LinksBuilder, fluent-interface, method-chaining, navigation, links, navbar, breadcrumb, sidebar, vertical, groups, search, icons, active-state, disabled-state, fetch, ajax, data-fetch, PHP-classes, simplified-API
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
    <h1>LinksBuilder Class Documentation</h1>

    <p>The LinksBuilder class provides a powerful fluent interface for creating navigation elements with support for multiple rendering styles, groups, search functionality, and advanced customization options.</p>

    <h2>System Overview</h2>
    <p>LinksBuilder simplifies navigation creation by providing:</p>
    <ul>
        <li><strong>Multiple Rendering Styles</strong>: navbar, breadcrumb, tabs, vertical, sidebar</li>
        <li><strong>Group Management</strong>: Organize links into logical groups</li>
        <li><strong>Search Integration</strong>: Built-in search functionality for sidebars</li>
        <li><strong>State Management</strong>: Active and disabled states with automatic detection</li>
        <li><strong>Custom Attributes</strong>: Full control over HTML attributes and styling</li>
        <li><strong>External Links</strong>: Support for external links in sidebars</li>
    </ul>

    <h2>Basic Usage</h2>

    <h3>Constructor and Factory Method</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$links = \Builders\LinksBuilder::create();</code></pre>

    <h3>Simple Navigation Creation</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$navbar = LinksBuilder::create()
    ->add('Home', '#')
        ->icon('bi bi-house')
        ->active()
    ->add('Posts', '#')
        ->icon('bi bi-file-earmark-text')
    ->add('Settings', '#')
        ->icon('bi bi-gear')
        ->disable()
    ->render('navbar');</code></pre>

    <h4>Example Output:</h4>
    <div class="border p-3 mb-4">
        <?php
        $exampleNavbar = LinksBuilder::create()
            ->add('Home', '#')
                ->icon('bi bi-house')
                ->active()
            ->add('Posts', '#')
                ->icon('bi bi-file-earmark-text')
            ->add('Settings', '#')
                ->icon('bi bi-gear')
                ->disable()
            ->render('navbar');
        echo $exampleNavbar;
        ?>
    </div>


    <p>Puoi applicare i menu alla barra degli header:</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">
// Apply to different header positions
Theme::set('header.top-left', $navbar);   // Left side
Theme::set('header.top-right', $navbar);  // Right side</code></pre>

    <h3>2. Breadcrumb Style</h3>
    <p>Creates semantic breadcrumb navigation with proper ARIA labels and Bootstrap styling.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$breadcrumb = LinksBuilder::create()
    ->add('Dashboard', '/')
        ->icon('bi bi-speedometer2')
    ->add('Posts', '?page=posts')
        ->icon('bi bi-file-earmark-text')
    ->add('My Posts', '?page=myposts')
        ->icon('bi bi-file-earmark-person')
        ->active()
    ->render('breadcrumb');

// Automatic features:
// - Last item is automatically marked as active
// - Proper aria-current="page" attribute
// - Bootstrap breadcrumb styling</code></pre>

    <h4>Example Output:</h4>
    <div class="border p-3 mb-4">
        <?php
        $breadcrumbExample = LinksBuilder::create()
            ->add('Dashboard', '/')
                ->icon('bi bi-speedometer2')
            ->add('Posts', '?page=posts')
                ->icon('bi bi-file-earmark-text')
            ->add('My Posts', '?page=myposts')
                ->icon('bi bi-file-earmark-person')
                ->active()
            ->render('breadcrumb');
        echo $breadcrumbExample;
        ?>
    </div>

    <h3>3. Tabs Style</h3>
    <p>Bootstrap tab navigation with active state management. Perfect for content sections with tab panels.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$tabs = LinksBuilder::create()
    ->add('General', '#general-tab')
        ->icon('bi bi-gear')
        ->active()
    ->add('Advanced', '#advanced-tab')
        ->icon('bi bi-sliders')
    ->add('Security', '#security-tab')
        ->icon('bi bi-shield-lock')
    ->add('Premium', '#premium-tab')
        ->icon('bi bi-star')
        ->disable()
    ->render('tabs');</code></pre>

    <h4>Example Output with Working Tabs:</h4>
    <div class="border p-3 mb-4">
        <!-- Tab Navigation -->
        <?php
        $tabsExample = LinksBuilder::create()
            ->add('General', '#general-tab')
                ->icon('bi bi-gear')
                ->active()
            ->add('Advanced', '#advanced-tab')
                ->icon('bi bi-sliders')
            ->add('Security', '#security-tab')
                ->icon('bi bi-shield-lock')
            ->add('Premium', '#premium-tab')
                ->icon('bi bi-star')
                ->disable()
            ->render('tabs');
        echo $tabsExample;
        ?>

        <!-- Tab Content -->
        <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="general-tab" role="tabpanel">
                <div class="p-3 bg-light">
                    <h5><i class="bi bi-gear"></i> General Settings</h5>
                    <p>Configure basic application settings here.</p>
                    <ul>
                        <li>Site name and description</li>
                        <li>Default language</li>
                        <li>Timezone settings</li>
                    </ul>
                </div>
            </div>
            <div class="tab-pane fade" id="advanced-tab" role="tabpanel">
                <div class="p-3 bg-light">
                    <h5><i class="bi bi-sliders"></i> Advanced Settings</h5>
                    <p>Configure advanced application features.</p>
                    <ul>
                        <li>Cache settings</li>
                        <li>Database optimization</li>
                        <li>API configurations</li>
                    </ul>
                </div>
            </div>
            <div class="tab-pane fade" id="security-tab" role="tabpanel">
                <div class="p-3 bg-light">
                    <h5><i class="bi bi-shield-lock"></i> Security Settings</h5>
                    <p>Manage security and authentication options.</p>
                    <ul>
                        <li>Password policies</li>
                        <li>Two-factor authentication</li>
                        <li>Access controls</li>
                    </ul>
                </div>
            </div>
            <div class="tab-pane fade" id="premium-tab" role="tabpanel">
                <div class="p-3 bg-light">
                    <h5><i class="bi bi-star"></i> Premium Features</h5>
                    <p>Premium features are disabled in this demo.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Enable Bootstrap tabs functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Get all tab links that aren't disabled
        const tabLinks = document.querySelectorAll('ul.nav-tabs a.nav-link:not(.disabled)');

        tabLinks.forEach(function(tabLink) {
            tabLink.addEventListener('click', function(e) {
                e.preventDefault();

                // Remove active class from all tabs and tab panes
                document.querySelectorAll('ul.nav-tabs .nav-link').forEach(function(link) {
                    link.classList.remove('active');
                });
                document.querySelectorAll('.tab-pane').forEach(function(pane) {
                    pane.classList.remove('show', 'active');
                });

                // Add active class to clicked tab
                this.classList.add('active');

                // Show corresponding tab pane
                const targetId = this.getAttribute('href').substring(1);
                const targetPane = document.getElementById(targetId);
                if (targetPane) {
                    targetPane.classList.add('show', 'active');
                }
            });
        });
    });
    </script>

    <h3>4. Pills Style</h3>
    <p>Modern pill-style navigation with rounded buttons and subtle hover effects.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$pills = LinksBuilder::create()
    ->add('Dashboard', '#dashboard')
        ->active()
    ->add('Utenti', '#users')
    ->add('Ordini', '#orders')
    ->add('Report', '#reports')
    ->add('Impostazioni', '#settings')
        ->disable()
    ->render('pills');</code></pre>

    <h4>Example Output:</h4>
    <div class="border p-3 mb-4">
        <?php
        $pillsExample = LinksBuilder::create()
            ->add('Dashboard', '#dashboard')
                ->active()
            ->add('Utenti', '#users')
            ->add('Ordini', '#orders')
            ->add('Report', '#reports')
            ->add('Impostazioni', '#settings')
                ->disable()
            ->render('pills');
        echo $pillsExample;
        ?>
    </div>

    <h3>5. Vertical Style (Simple Sidebar)</h3>
    <p>Vertical navigation without groups, perfect for simple sidebars.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$vertical = LinksBuilder::create()
    ->add('Dashboard', '?page=dashboard')
        ->icon('bi bi-speedometer2')
    ->add('Posts', '?page=posts')
        ->icon('bi bi-file-earmark-text')
    ->add('My Posts', '?page=myposts')
        ->icon('bi bi-file-earmark-person')
        ->active()
    ->add('Categories', '?page=categories')
        ->icon('bi bi-tags')
    ->add('Settings', '?page=settings')
        ->icon('bi bi-gear')
        ->disable()
    ->render('vertical');</code></pre>

    <h4>Example Output:</h4>
    <div class="border p-3 mb-4" style="max-width: 300px;">
        <?php
        $verticalExample = LinksBuilder::create()
            ->add('Dashboard', '#')
                ->icon('bi bi-speedometer2')
            ->add('Posts', '#')
                ->icon('bi bi-file-earmark-text')
            ->add('My Posts', '#')
                ->icon('bi bi-file-earmark-person')
                ->active()
            ->add('Categories', '#')
                ->icon('bi bi-tags')
            ->add('Settings', '#')
                ->icon('bi bi-gear')
                ->disable()
            ->render('vertical');
        echo $verticalExample;
        ?>
    </div>


    <h2>Group Management</h2>

    <h3>Sidebar with Groups and Advanced Features</h3>
    <p>Complete sidebar implementation with groups, search, and external links - as used in documentation.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Full-featured sidebar with groups
$sidebar = LinksBuilder::create()
    // Enable search functionality
    ->enableSearch('Search documentation...')
    ->setContainerClass('docs-sidebar border-end p-3')
    ->setContainerId('testContainer')

    // External links
    ->addExternalLinks([
        ['url' => 'https://milkadmin.org/docs', 'title' => 'API Documentation', 'target' => '_blank']
    ])

    // Custom attributes for search functionality
    ->setLiAttributes(['class' => 'nav-item doc-link'])
    ->setAAttributes(['class' => 'doc-link'])
    ->setActiveAttributes(['class' => 'doc-link-active'])

    // Getting Started group
    ->addGroup('getting-started', 'Getting Started')
    ->add('Introduction', '#')
        ->icon('bi bi-book')
        ->active()
    ->add('Installation', '#')
        ->icon('bi bi-download')

    // Development group
    ->addGroup('development', 'Development')
    ->addMany([
        ['title' => 'Modules', 'url' => '#', 'icon' => 'bi bi-code-slash'],
        ['title' => 'Models', 'url' => '#', 'icon' => 'bi bi-database'],
        ['title' => 'Views', 'url' => '#', 'icon' => 'bi bi-eye', 'disabled' => true]
    ])

    ->render('sidebar');

// Groups are visible in 'vertical' and 'sidebar' styles
// In other styles (navbar, breadcrumb, tabs), groups are ignored</code></pre>

    <h4>Example Output (Sidebar with Groups):</h4>
    <div class="border p-3 mb-4" style="max-width: 350px;">
        <?php
        $sidebarExample = LinksBuilder::create()
            ->enableSearch('Search documentation...')
            ->setContainerClass('docs-sidebar border-end p-3')
            ->setContainerId('testContainer')
            ->addExternalLinks([
                ['url' => 'https://milkadmin.org/docs', 'title' => 'API Documentation', 'target' => '_blank', 'class' => 'text-body-secondary']
            ])
            ->setLiAttributes(['class' => 'nav-item doc-link'])
            ->setAAttributes(['class' => 'doc-link'])
            ->setActiveAttributes(['class' => 'doc-link-active'])
            ->addGroup('getting-started', 'Getting Started')
            ->add('Introduction', '#')
                ->icon('bi bi-book')
                ->active()
            ->add('Installation', '#')
                ->icon('bi bi-download')
            ->addGroup('development', 'Development')
            ->addMany([
                ['title' => 'Modules', 'url' => '#', 'icon' => 'bi bi-code-slash'],
                ['title' => 'Models', 'url' => '#', 'icon' => 'bi bi-database'],
                ['title' => 'Views', 'url' => '#', 'icon' => 'bi bi-eye', 'disabled' => true]
            ])
            ->render('sidebar');
        echo $sidebarExample;
        ?>
    </div>

    <h4>Same Code with Navbar Style (Groups Hidden):</h4>
    <div class="border p-3 mb-4">
        <?php
        $groupsNavbarExample = LinksBuilder::create()
            ->addGroup('getting-started', 'Getting Started')
            ->add('Introduction', '#')
                ->icon('bi bi-book')
                ->active()
            ->add('Installation', '#')
                ->icon('bi bi-download')
            ->addGroup('development', 'Development')
            ->addMany([
                ['title' => 'Modules', 'url' => '#', 'icon' => 'bi bi-code-slash'],
                ['title' => 'Models', 'url' => '#', 'icon' => 'bi bi-database'],
                ['title' => 'Views', 'url' => '#', 'icon' => 'bi bi-eye', 'disabled' => true]
            ])
            ->render('navbar'); // Same code, different render style - groups are hidden
        echo $groupsNavbarExample;
        ?>
    </div>

    <h3>Groups Behavior by Style</h3>
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Style</th>
                <th>Groups Visible</th>
                <th>Group Titles</th>
                <th>Use Case</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>navbar</code></td>
                <td>No</td>
                <td>Hidden</td>
                <td>Horizontal navigation</td>
            </tr>
            <tr>
                <td><code>breadcrumb</code></td>
                <td>No</td>
                <td>Hidden</td>
                <td>Page hierarchy</td>
            </tr>
            <tr>
                <td><code>tabs</code></td>
                <td>No</td>
                <td>Hidden</td>
                <td>Content sections</td>
            </tr>
            <tr>
                <td><code>vertical</code></td>
                <td>Yes</td>
                <td>Simple headings</td>
                <td>Basic sidebar</td>
            </tr>
            <tr>
                <td><code>sidebar</code></td>
                <td>Yes</td>
                <td>Full featured</td>
                <td>Documentation, admin panels</td>
            </tr>
        </tbody>
    </table>

    <h2>Link Management Methods</h2>

    <h3>Adding Links</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$links = LinksBuilder::create()
    // Basic link
    ->add('Home', '/')

    // Link with icon
    ->add('Posts', '/posts')
        ->icon('bi bi-file-earmark-text')

    // Active link
    ->add('Current Page', '/current')
        ->icon('bi bi-circle-fill')
        ->active()

    // Disabled link
    ->add('Coming Soon', '#')
        ->icon('bi bi-clock')
        ->disable()

    // Link with custom parameters
    ->add('Custom Link', '/custom')
        ->setParam('data-toggle', 'tooltip')
        ->setParam('title', 'Custom tooltip');</code></pre>

    <h4>Example Output:</h4>
    <div class="border p-3 mb-4" style="max-width: 300px;">
        <?php
        $linksExample = LinksBuilder::create()
            ->add('Home', '/')
            ->add('Posts', '/posts')
                ->icon('bi bi-file-earmark-text')
            ->add('Current Page', '/current')
                ->icon('bi bi-circle-fill')
                ->active()
            ->add('Coming Soon', '#')
                ->icon('bi bi-clock')
                ->disable()
            ->add('Custom Link', '/custom')
            ->render('vertical');
        echo $linksExample;
        ?>
    </div>

    <h3>Bulk Adding with addMany()</h3>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$links = LinksBuilder::create()
    ->addMany([
        // Array format with keys
        ['title' => 'Home', 'url' => '/', 'icon' => 'bi bi-house', 'active' => true],
        ['title' => 'About', 'url' => '/about', 'icon' => 'bi bi-info-circle'],
        ['title' => 'Contact', 'url' => '/contact', 'icon' => 'bi bi-envelope', 'disabled' => true],

        // Alternative array format (positional)
        ['Services', '/services', ['icon' => 'bi bi-gear']]
    ]);</code></pre>

    <h4>Example Output:</h4>
    <div class="border p-3 mb-4">
        <?php
        $addManyExample = LinksBuilder::create()
            ->addMany([
                ['title' => 'Home', 'url' => '/', 'icon' => 'bi bi-house', 'active' => true],
                ['title' => 'About', 'url' => '/about', 'icon' => 'bi bi-info-circle'],
                ['title' => 'Contact', 'url' => '/contact', 'icon' => 'bi bi-envelope', 'disabled' => true],
                ['Services', '/services', ['icon' => 'bi bi-gear']]
            ])
            ->render('navbar');
        echo $addManyExample;
        ?>
    </div>

    <h2>Method Reference</h2>

    <h3>Basic Link Methods</h3>

    <h4><code>add(title, url)</code></h4>
    <p><strong>Parameters:</strong> <code>string $title</code>, <code>string $url</code> (default: '#')<br>
    <strong>Returns:</strong> <code>self</code> for method chaining<br>
    <strong>Usage:</strong> Adds a single link to the builder. Use '#' for anchors, '?page=...' for internal routes.</p>

    <h4><code>addMany(links)</code></h4>
    <p><strong>Parameters:</strong> <code>array $links</code><br>
    <strong>Returns:</strong> <code>self</code> for method chaining<br>
    <strong>Usage:</strong> Bulk add multiple links from array. Supports both associative and positional formats.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">// Multiple format support
->addMany([
    ['title' => 'Home', 'url' => '#', 'icon' => 'bi bi-house', 'active' => true],
    ['About Us', '#about', ['icon' => 'bi bi-info-circle']]
]);</code></pre>

    <h4>Example Output:</h4>
    <div class="border p-3 mb-4" style="max-width: 300px;">
        <?php
        $addManyExample = LinksBuilder::create()
            ->addMany([
                ['title' => 'Home', 'url' => '#', 'icon' => 'bi bi-house', 'active' => true],
                ['title' => 'About Us', 'url' => '#about', 'icon' => 'bi bi-info-circle'],
                ['Contact', '#contact', ['icon' => 'bi bi-envelope']]
            ])
            ->render('vertical');
        echo $addManyExample;
        ?>
    </div>

    <h3>Link State Methods</h3>

    <h4><code>icon(iconClass)</code></h4>
    <p><strong>Parameters:</strong> <code>string $icon</code><br>
    <strong>Usage:</strong> Adds an icon to the current link. Supports Bootstrap Icons classes.</p>

    <h4><code>active()</code></h4>
    <p><strong>Usage:</strong> Marks the current link as active. Overrides automatic detection.</p>

    <h4><code>disable()</code></h4>
    <p><strong>Usage:</strong> Disables the current link, making it non-clickable with appropriate styling.</p>

    <h4><code>fetch(method = 'post')</code></h4>
    <p><strong>Usage:</strong> Transforms the link into an asynchronous fetch call. Adds the <code>data-fetch</code> attribute that enables automatic AJAX handling via JavaScript.</p>
    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>method</code> (string): HTTP method - 'get' or 'post' (default: 'post')</li>
    </ul>
    <p><strong>Example:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">$links = LinksBuilder::create()
    ->add('Edit Item', '?page=items&action=edit&id=123')
        ->icon('bi bi-pencil')
        ->fetch('post')  // Converts to fetch POST call
    ->add('Load Data', '?page=items&action=load')
        ->fetch('get')   // Converts to fetch GET call
    ->render('pills');</code></pre>
    <p><strong>Generated HTML:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-html">&lt;a href="?page=items&action=edit&id=123" data-fetch="post"&gt;Edit Item&lt;/a&gt;
&lt;a href="?page=items&action=load" data-fetch="get"&gt;Load Data&lt;/a&gt;</code></pre>
    <p>The JavaScript will automatically intercept these links and execute fetch calls instead of navigation. See <a href="?page=docs&action=Framework/Theme/theme-javascript-fetch-link">data-fetch System Documentation</a> for more details.</p>
    <p><strong>Works with addMany:</strong></p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addMany([
    ['title' => 'Delete', 'url' => '?page=items&action=delete&id=5', 'fetch' => 'post'],
    ['title' => 'Refresh', 'url' => '?page=items&action=refresh', 'fetch' => 'get']
])</code></pre>

    <h4><code>setParam(name, value)</code></h4>
    <p><strong>Parameters:</strong> <code>string $name</code>, <code>mixed $value</code><br>
    <strong>Usage:</strong> Adds custom parameters for variable substitution in attributes.</p>

    <h3>Group Management Methods</h3>

    <h4><code>addGroup(name, title)</code></h4>
    <p><strong>Parameters:</strong> <code>string $name</code>, <code>string $title</code> (default: '')<br>
    <strong>Usage:</strong> Creates a new group. Subsequent links will be added to this group until a new group is created.</p>

    <h3>Search and Container Methods</h3>

    <h4><code>enableSearch(placeholder, inputId, resultId)</code></h4>
    <p><strong>Parameters:</strong> <code>string $placeholder</code>, <code>string $inputId</code>, <code>string $resultId</code><br>
    <strong>Usage:</strong> Enables search functionality in sidebar rendering. Only works with 'sidebar' style.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->enableSearch('Search items...', 'searchInput', 'resultCount')</code></pre>

    <h4><code>setContainerClass(class)</code></h4>
    <p><strong>Parameters:</strong> <code>string $class</code><br>
    <strong>Usage:</strong> Sets CSS class for sidebar container. Only affects 'sidebar' style.</p>

    <h4><code>setContainerId(id)</code></h4>
    <p><strong>Parameters:</strong> <code>string $id</code><br>
    <strong>Usage:</strong> Sets HTML ID for container. Must start with a letter. Auto-generated if not provided.</p>

    <h4><code>addExternalLinks(links)</code></h4>
    <p><strong>Parameters:</strong> <code>array $links</code><br>
    <strong>Usage:</strong> Adds external links displayed at bottom of sidebar. Only affects 'sidebar' style.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->addExternalLinks([
    ['url' => 'https://example.com', 'title' => 'External Link', 'target' => '_blank']
])</code></pre>

    <h4>Example Output:</h4>
    <div class="border p-3 mb-4" style="max-width: 300px;">
        <?php
        $externalExample = LinksBuilder::create()
            ->enableSearch('Search...', 'demoSearch', 'demoCount')
            ->setContainerClass('border p-3')
            ->addExternalLinks([
                ['url' => 'https://example.com', 'title' => 'External Documentation', 'target' => '_blank', 'class' => 'btn btn-sm btn-outline-primary']
            ])
            ->add('Internal Link', '#')
                ->icon('bi bi-house')
                ->active()
            ->render('sidebar');
        echo $externalExample;
        ?>
    </div>

    <h3>HTML Attribute Methods</h3>

    <h4><code>setLiAttributes(attributes)</code>, <code>setAAttributes(attributes)</code>, etc.</h4>
    <p><strong>Parameters:</strong> <code>array $attributes</code><br>
    <strong>Usage:</strong> Sets HTML attributes for specific elements. Supports variable substitution with %variable%.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->setLiAttributes(['class' => 'custom-item', 'data-title' => '%title%'])
->setAAttributes(['class' => 'custom-link', 'data-track' => 'true'])</code></pre>

    <h4><code>setActiveAttributes(attributes)</code>, <code>setDisabledAttributes(attributes)</code></h4>
    <p><strong>Usage:</strong> Sets attributes that override base attributes when links are active or disabled.</p>

    <h4>Example Output with Custom Attributes:</h4>
    <div class="border p-3 mb-4" style="max-width: 300px;">
        <?php
        $attributeExample = LinksBuilder::create()
            ->setLiAttributes(['class' => 'custom-item', 'data-custom' => 'true'])
            ->setAAttributes(['class' => 'custom-link'])
            ->setActiveAttributes(['class' => 'custom-link active-custom'])
            ->add('Custom Styled', '#')
                ->icon('bi bi-star')
                ->active()
            ->add('Normal Link', '#')
                ->icon('bi bi-circle')
            ->render('vertical');
        echo $attributeExample;
        ?>
    </div>

    <h3>Output Methods</h3>

    <h4><code>render(style)</code></h4>
    <p><strong>Parameters:</strong> <code>string $style</code> (default: 'navbar')<br>
    <strong>Returns:</strong> <code>string</code> HTML output<br>
    <strong>Usage:</strong> Generates HTML for specified style: 'navbar', 'breadcrumb', 'tabs', 'pills', 'vertical', 'sidebar'.</p>

    <h4><code>fill()</code></h4>
    <p><strong>Static method</strong><br>
    <strong>Returns:</strong> <code>LinksBuilder</code> instance<br>
    <strong>Usage:</strong> Factory method to create new builder instance.</p>

    <h3>Additional Configuration Methods</h3>

    <h4><code>setOptions(options)</code></h4>
    <p><strong>Parameters:</strong> <code>array $options</code><br>
    <strong>Usage:</strong> Sets multiple options at once for advanced configuration.</p>

    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">->setOptions([
    'show_search' => true,
    'search_placeholder' => 'Custom search...',
    'container_attributes' => ['role' => 'navigation']
])</code></pre>

    <h4><code>setNavAttributes(attributes)</code>, <code>setUlAttributes(attributes)</code></h4>
    <p><strong>Parameters:</strong> <code>array $attributes</code><br>
    <strong>Usage:</strong> Sets attributes for nav and ul wrapper elements respectively.</p>

    <h2>Automatic Features</h2>

    <h3>Active State Detection</h3>
    <p>LinksBuilder automatically detects active states by comparing URLs with current page using <code>Route::comparePageUrl()</code>. Manual <code>active()</code> calls override automatic detection.</p>

    <h3>URL Processing</h3>
    <ul>
        <li><strong>Query strings</strong> (?page=example) → processed with <code>Route::url()</code></li>
        <li><strong>Hash fragments</strong> (#section) → used as-is for anchors</li>
        <li><strong>Full URLs</strong> (http://example.com) → used as-is</li>
        <li><strong>Array URLs</strong> → processed with <code>Route::url()</code></li>
    </ul>

    <h3>JavaScript Integration</h3>
    <p>When search functionality is enabled, LinksBuilder automatically includes necessary JavaScript via the theme system using <code>Theme::set('javascript.linksBuilder', ...)</code>.</p>

    <h2>Method Reference Summary</h2>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Category</th>
                <th>Method</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td rowspan="4">Link Management</td>
                <td><code>add(title, url)</code></td>
                <td>Add a single link</td>
            </tr>
            <tr>
                <td><code>addMany(links)</code></td>
                <td>Add multiple links from array</td>
            </tr>
            <tr>
                <td><code>icon(iconClass)</code></td>
                <td>Add icon to current link</td>
            </tr>
            <tr>
                <td><code>setParam(name, value)</code></td>
                <td>Add custom parameter to current link</td>
            </tr>
            <tr>
                <td rowspan="4">State Management</td>
                <td><code>active()</code></td>
                <td>Mark current link as active</td>
            </tr>
            <tr>
                <td><code>disable()</code></td>
                <td>Disable current link</td>
            </tr>
            <tr>
                <td><code>fetch(method)</code></td>
                <td>Enable fetch mode (GET/POST)</td>
            </tr>
            <tr>
                <td><code>isActive(link)</code></td>
                <td>Automatic active state detection</td>
            </tr>
            <tr>
                <td rowspan="2">Group Management</td>
                <td><code>addGroup(name, title)</code></td>
                <td>Create a new group</td>
            </tr>
            <tr>
                <td><code>getGroupedLinks()</code></td>
                <td>Get links organized by groups</td>
            </tr>
            <tr>
                <td rowspan="4">Configuration</td>
                <td><code>enableSearch(placeholder, inputId, resultId)</code></td>
                <td>Enable search functionality</td>
            </tr>
            <tr>
                <td><code>setContainerClass(class)</code></td>
                <td>Set container CSS class</td>
            </tr>
            <tr>
                <td><code>setContainerId(id)</code></td>
                <td>Set container HTML ID</td>
            </tr>
            <tr>
                <td><code>addExternalLinks(links)</code></td>
                <td>Add external links to sidebar</td>
            </tr>
            <tr>
                <td rowspan="6">HTML Attributes</td>
                <td><code>setNavAttributes(attrs)</code></td>
                <td>Set nav element attributes</td>
            </tr>
            <tr>
                <td><code>setUlAttributes(attrs)</code></td>
                <td>Set ul element attributes</td>
            </tr>
            <tr>
                <td><code>setLiAttributes(attrs)</code></td>
                <td>Set li element attributes</td>
            </tr>
            <tr>
                <td><code>setAAttributes(attrs)</code></td>
                <td>Set anchor element attributes</td>
            </tr>
            <tr>
                <td><code>setActiveAttributes(attrs)</code></td>
                <td>Set attributes for active elements</td>
            </tr>
            <tr>
                <td><code>setDisabledAttributes(attrs)</code></td>
                <td>Set attributes for disabled elements</td>
            </tr>
            <tr>
                <td rowspan="2">Output</td>
                <td><code>render(style)</code></td>
                <td>Generate HTML with specified style</td>
            </tr>
            <tr>
                <td><code>__toString()</code></td>
                <td>Default rendering (navbar style)</td>
            </tr>
        </tbody>
    </table>

    <h2>Rendering Styles Comparison</h2>

    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Style</th>
                <th>HTML Structure</th>
                <th>Groups</th>
                <th>Search</th>
                <th>External Links</th>
                <th>Best Use Case</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>navbar</code></td>
                <td>ul.nav > li > a</td>
                <td>No</td>
                <td>No</td>
                <td>No</td>
                <td>Header navigation</td>
            </tr>
            <tr>
                <td><code>breadcrumb</code></td>
                <td>nav > ol.breadcrumb > li > a</td>
                <td>No</td>
                <td>No</td>
                <td>No</td>
                <td>Page hierarchy</td>
            </tr>
            <tr>
                <td><code>tabs</code></td>
                <td>ul.nav-tabs > li.nav-item > a.nav-link</td>
                <td>No</td>
                <td>No</td>
                <td>No</td>
                <td>Content sections</td>
            </tr>
            <tr>
                <td><code>pills</code></td>
                <td>ul.nav-pills > li.nav-item > a.nav-link</td>
                <td>No</td>
                <td>No</td>
                <td>No</td>
                <td>Modern button navigation</td>
            </tr>
            <tr>
                <td><code>vertical</code></td>
                <td>ul.flex-column > li.nav-item > a</td>
                <td>Simple</td>
                <td>No</td>
                <td>No</td>
                <td>Simple sidebar</td>
            </tr>
            <tr>
                <td><code>sidebar</code></td>
                <td>Complex structure with groups</td>
                <td>Full</td>
                <td>Yes</td>
                <td>Yes</td>
                <td>Admin panels, documentation</td>
            </tr>
        </tbody>
    </table>
</div>