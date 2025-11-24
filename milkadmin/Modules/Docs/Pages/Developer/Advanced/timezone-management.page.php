<?php
namespace Modules\Docs\Pages;
/**
 * @title Timezone and Locale Management
 * @category Advanced
 * @order 5
 * @tags timezone, locale, date, time, utc, user timezone, language, i18n, internationalization
 */

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

<h1>Timezone and Locale Management</h1>
<p class="text-muted">Revision: 2025/11/21</p>
<p class="lead">
    Configure per-user timezone and locale settings to store dates in UTC while displaying them in each user's local timezone and language preferences.
</p>

<hr>

<h2>Overview</h2>

<p>
    MilkAdmin provides a sophisticated timezone and locale management system:
</p>

<ul>
    <li><strong>Timezone Management:</strong> Store dates in UTC and display them in each user's preferred timezone</li>
    <li><strong>Locale Management:</strong> Support multiple languages with automatic translation loading and regional conventions (including date formatting)</li>
</ul>

<hr>

<!-- ============================================ -->
<!-- TIMEZONE MANAGEMENT                           -->
<!-- ============================================ -->

<h2>Timezone Management</h2>

<h3>Configuration</h3>

<p>
    Configure timezone settings in your config file:
</p>

<pre><code class="language-php">// In milkadmin_local/config.php

// Default timezone used by the site
$conf['time_zone'] = 'Europe/Rome';

// Enable per-user timezone management
$conf['use_user_timezone'] = true;</code></pre>

<p class="text-muted">
    When <code>use_user_timezone</code> is enabled, dates from forms are parsed in the user's timezone and automatically converted to UTC before saving. Data is always stored in UTC regardless of timezone settings.
</p>

<h3>How It Works</h3>

<p>
    The system determines which timezone to use in the following priority order:
</p>

<ol>
    <li><strong>Explicitly Set Timezone</strong> - Using <code>Get::setUserTimezone()</code></li>
    <li><strong>Authenticated User's Timezone</strong> - From the user's profile (if logged in and <code>use_user_timezone</code> is enabled)</li>
    <li><strong>Default Site Timezone</strong> - From <code>$conf['time_zone']</code></li>
</ol>

<h3>Core Methods</h3>

<h4>Get::userTimezone()</h4>

<p>
    Returns the current user's timezone identifier.
</p>

<pre><code class="language-php">$timezone = Get::userTimezone();  // Returns 'Europe/Rome', 'America/New_York', etc.
</code></pre>

<h4>Get::setUserTimezone()</h4>

<p>
    Explicitly sets the timezone for the current request.
</p>

<pre><code class="language-php">Get::setUserTimezone('America/New_York');
</code></pre>

<h3>Working with Dates</h3>

<h4>Storing Dates (UTC)</h4>

<pre><code class="language-php">// Get current UTC time
$now = Get::dateTimeZone();
$model->created_at = $now->format('Y-m-d H:i:s');
$model->save();</code></pre>

<h4>Displaying Dates (User Timezone)</h4>

<pre><code class="language-php">// Format date in user's timezone
$userTimezone = Get::userTimezone();
$displayDate = Get::formatDate($model->created_at, 'datetime', $userTimezone);
echo "Created: " . $displayDate;</code></pre>

<h3>Best Practices</h3>

<ul>
    <li><strong>Always store dates in UTC</strong> in your database</li>
    <li>Use <code>Get::userTimezone()</code> when displaying dates to users</li>
    <li>Test with users in different timezones</li>
</ul>

<hr>

<!-- ============================================ -->
<!-- LOCALE MANAGEMENT                             -->
<!-- ============================================ -->

<h2>Locale Management</h2>

<h3>Configuration</h3>

<p>
    Configure the default locale and available languages:
</p>

<pre><code class="language-php">// In milkadmin_local/config.php

// Default locale (language + regional conventions, including date formatting)
$conf['locale'] = 'it_IT';

// Available languages for users
$conf['available_locales'] = [
    'it_IT' => 'Italian',
    'en_US' => 'English',
    'fr_FR' => 'French',
    'de_DE' => 'German',
    'es_ES' => 'Spanish'
];</code></pre>

<div class="alert alert-info">
    <strong>Note:</strong> When <code>$conf['available_locales']</code> is configured, users can select their preferred language in the user edit form. The locale also defines how dates and numbers are formatted.
</div>

<h3>How It Works</h3>

<p>
    The locale system automatically loads the correct language files based on:
</p>

<ol>
    <li><strong>User's Selected Locale</strong> - From the user's profile (if <code>available_locales</code> is configured)</li>
    <li><strong>Default Site Locale</strong> - From <code>$conf['locale']</code></li>
</ol>

<h3>Language Files Organization</h3>

<h4>Core Language Files</h4>

<p>
    Language files are PHP files named after the locale identifier (e.g., <code>it_IT.php</code>, <code>en_US.php</code>):
</p>

<ul>
    <li><code>milkadmin/Lang/</code> - Default language files</li>
    <li><code>milkadmin_local/Lang/</code> - Custom language files (override default ones)</li>
</ul>

<div class="alert alert-warning">
    <strong>Priority:</strong> Files in <code>milkadmin_local/Lang/</code> take precedence over those in <code>milkadmin/Lang/</code>
</div>

<h4>Module-Level Language Files</h4>

<p>
    Each module can have its own <code>Lang/</code> folder:
</p>

<pre><code>milkadmin/Modules/YourModule/
├── Lang/
│   ├── it_IT.php
│   ├── en_US.php
│   └── fr_FR.php
└── YourModule.php</code></pre>

<p class="text-muted">
    Module translations are automatically loaded and merged with core translations.
</p>

<h3>Working with Translations</h3>

<h4>Creating Language Files</h4>

<p>
    Language files return an associative array:
</p>

<pre><code class="language-php">// milkadmin_local/Lang/en_US.php
&lt;?php
return [
    'welcome_message' => 'Welcome to MilkAdmin',
    'save_button' => 'Save',
    'cancel_button' => 'Cancel',
];
</code></pre>

<pre><code class="language-php">// milkadmin_local/Lang/it_IT.php
&lt;?php
return [
    'welcome_message' => 'Benvenuto in MilkAdmin',
    'save_button' => 'Salva',
    'cancel_button' => 'Annulla',
];
</code></pre>

<h4>Using Translations in Your Code</h4>

<p>
    Use these helper functions to access translations:
</p>

<pre><code class="language-php">// Print translated text
_pt('welcome_message');  // Echoes: "Benvenuto in MilkAdmin" (if locale is it_IT)

// Return translated text
$message = _rt('save_button');  // Returns: "Salva"

// In HTML
&lt;button&gt;&lt;?php _pt('save_button'); ?&gt;&lt;/button&gt;
</code></pre>

<h4>Module-Specific Translations Example</h4>

<pre><code class="language-php">// milkadmin/Modules/Products/Lang/en_US.php
&lt;?php
return [
    'product_list' => 'Product List',
    'add_product' => 'Add New Product',
];
</code></pre>


</div>
