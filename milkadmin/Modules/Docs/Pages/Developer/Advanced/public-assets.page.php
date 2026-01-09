<?php
namespace Modules\Docs\Pages;
/**
 * @title Public Assets and asset_loader
 * @category Advanced
 * @order 6
 * @tags assets, asset_loader, public_html, override, production
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

<h1>Public Assets and asset_loader</h1>

<p class="lead">
    Assets normally loaded through <code>public_html/asset_loader.php</code> can be served directly by the web server when they exist inside <code>public_html</code>.
</p>

<hr>

<h2>How it works</h2>

<p>
    Normally, assets are resolved by <code>asset_loader.php</code>, which looks for files in the <code>milkadmin</code> and <code>milkadmin_local</code> folders.
    If the same asset is placed inside <code>public_html</code>, the web server serves it directly, bypassing the loader.
</p>

<div class="alert alert-info">
    <strong>Why use it:</strong> in production this reduces the PHP hop and is useful for quick overrides (for example project logos or images).
</div>

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
