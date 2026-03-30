<?php
namespace Modules\Docs\Pages;
/**
 * @title Asset Delivery Modes: Development Proxy vs Production Bundles
 * @category Advanced
 * @order 6
 * @tags assets, static-assets, css, javascript, js, stylesheets, scripts, images, fonts, media, downloads, asset_loader, asset-loader, resource-proxy, php-streaming, file-streaming, rewrite, rewrite-rule, htaccess, fallback, direct-serving, web-server, public_html, theme, theme-assets, assets-bundle, bundles, bundle-theme.css, bundle-theme.js, environment, development, production, deploy, deployment, performance, cache, caching, regenerate, rebuild, cleanup, sync, override, milkadmin_local, config.php
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

<h1>Asset Delivery Modes: Development Proxy vs Production Bundles</h1>

<p class="lead">
    MilkAdmin uses two asset delivery modes controlled by <code>$conf['environment']</code>: a development proxy flow and a production bundle flow.
</p>

<hr>

<h2>How request resolution works</h2>

<p>
    Asset URLs are requested under <code>public_html</code>. The web server first checks whether the file exists physically.
    If it exists, it is returned directly. If it does not exist, <code>public_html/.htaccess</code> rewrites the request to <code>asset_loader.php</code>.
    The loader then resolves and streams the file from <code>milkadmin</code> or <code>milkadmin_local</code>.
</p>

<p>
    This is why development can work even when assets are not pre-copied inside <code>public_html</code>.
</p>

<hr>

<h2>Environment modes</h2>

<p>
    Behavior is tied to <code>$conf['environment']</code> in <code>milkadmin_local/config.php</code>:
</p>
<ul>
    <li>
        <code>development</code>: CSS and JS are loaded as separate files (theme assets, plugin assets, extension assets). Missing files are served by <code>asset_loader.php</code>.
        This is effectively a <strong>resource proxy</strong> / <strong>PHP file streaming</strong> flow.
    </li>
    <li>
        <code>production</code>: CSS and JS are compiled into two bundle files (<code>bundle-theme.css</code> and <code>bundle-theme.js</code>) and written under
        <code>public_html/Theme/Assets</code>. Theme static assets are also synchronized under <code>public_html/Theme</code>, so most requests are served directly by the web server.
    </li>
</ul>

<div class="alert alert-info">
    <strong>Why this matters:</strong> production mode reduces PHP overhead for static assets and improves delivery by relying on direct web-server responses.
</div>

<hr>

<h2>How to rebuild production assets safely</h2>

<p>
    If a source CSS/JS file changes and you want to force a clean rebuild of production assets, use this workflow:
</p>
<ol>
    <li>Set <code>$conf['environment'] = 'development'</code> in <code>milkadmin_local/config.php</code>.</li>
    <li>Load any application page.</li>
    <li>
        In development mode, the system automatically cleans previously auto-generated production asset files under <code>public_html/Theme</code>
        (tracked managed files and bundle metadata).
    </li>
    <li>Set <code>$conf['environment'] = 'production'</code> again.</li>
    <li>Load a page again to trigger bundle and static asset regeneration.</li>
</ol>

<p>
    In short: switch to development to clear generated production assets, then switch back to production to regenerate them.
</p>

<hr>

<h2>Real-world example</h2>

<p>
    If your layout uses these URLs:
</p>

<pre><code>/Theme/Assets/logo-big.webp
/Theme/Assets/logo-white.webp</code></pre>

<p>
    you can create these files:
</p>

<pre><code>public_html/Theme/Assets/logo-big.webp
public_html/Theme/Assets/logo-white.webp</code></pre>

<p>
    This way the logos are served directly by the web server and override the assets loaded via <code>asset_loader.php</code>.
</p>

<div class="alert alert-warning">
    <strong>Note:</strong> You can also override CSS and JS the same way by placing them inside <code>public_html</code> with the same path.
</div>

</div>
