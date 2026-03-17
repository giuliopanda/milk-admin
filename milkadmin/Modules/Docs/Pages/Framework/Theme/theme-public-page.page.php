<?php
namespace Modules\Docs\Pages;
use App\Get;
/**
* @title Public Page Template
* @order 50
* @tags public-template, public-page, frontend-template, custom-page, theme-customization, landing-page
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
<h1>Public Page Template</h1>
<p>The <code>public.page.php</code> template is a theme page without sidebar and menu, ideal for creating public pages, landing pages, or forms accessible without authentication.</p>

<div class="alert alert-info">
<strong>Main features:</strong>
<ul>
    <li>No sidebar or side menu</li>
    <li>Centered header with logo, title, and description</li>
    <li>Main content with maximum width of 800px</li>
    <li>Customizable footer</li>
    <li>Fully customizable background via CSS classes</li>
</ul>
</div>

<h2>How to use the public template</h2>

<p>To use the public template in your module, use <code>Response::themePage('public', $content)</code>:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('home')]
public function home() {
    // Customize the page appearance
    \App\Theme::set('public.header.title', 'Your Title');
    \App\Theme::set('public.header.description', 'An engaging description');

    // Create the content
    $content = '&lt;h2&gt;Welcome!&lt;/h2&gt;&lt;p&gt;Your content here&lt;/p&gt;';

    // Render with the public template
    Response::themePage('public', $content);
}</code></pre>

<h2>Customization variables</h2>

<p>You can customize the page appearance using <code>Theme::set()</code>. All variables are optional and have default values.</p>

<h3>Header</h3>
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Variable</th>
            <th>Description</th>
            <th>Default</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><code>public.header.title</code></td>
            <td>Main page title</td>
            <td>'Milk Admin'</td>
        </tr>
        <tr>
            <td><code>public.header.description</code></td>
            <td>Description below the title</td>
            <td>'' (empty)</td>
        </tr>
        <tr>
            <td><code>public.header.logo-path</code></td>
            <td>Custom logo path</td>
            <td>THEME_URL.'/Assets/logo-big.webp'</td>
        </tr>
        <tr>
            <td><code>public.header.title-color</code></td>
            <td>Title color (CSS)</td>
            <td>'#333'</td>
        </tr>
    </tbody>
</table>

<h3>Footer</h3>
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Variable</th>
            <th>Description</th>
            <th>Default</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><code>public.footer.text</code></td>
            <td>Footer text</td>
            <td>'© '.date('Y').' Milk Admin'</td>
        </tr>
        <tr>
            <td><code>public.footer.link</code></td>
            <td>Footer link URL</td>
            <td>'https://milkadmin.org'</td>
        </tr>
        <tr>
            <td><code>public.footer.link-text</code></td>
            <td>Link text (if different from footer.text)</td>
            <td>Uses footer.text value</td>
        </tr>
    </tbody>
</table>

<h3>Background</h3>
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Variable</th>
            <th>Description</th>
            <th>Default</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><code>public.theme.bg-class</code></td>
            <td>CSS class for background (see available classes below)</td>
            <td>'public-bg-light-gray'</td>
        </tr>
    </tbody>
</table>

<h2>Available Background Classes</h2>

<p>The following classes are defined in <code>theme.css</code> and can be used to customize the page background:</p>

<h3>Light Backgrounds (Professional)</h3>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-light-gray">
            <strong>public-bg-light-gray</strong><br>
            <small class="text-muted">Light gray (#f5f7fa)</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-soft-white">
            <strong>public-bg-soft-white</strong><br>
            <small class="text-muted">Soft white (#fafbfc)</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-warm-white">
            <strong>public-bg-warm-white</strong><br>
            <small class="text-muted">Warm white (#faf9f7)</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-cool-blue">
            <strong>public-bg-cool-blue</strong><br>
            <small class="text-muted">Cool blue (#f0f4f8)</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-mint">
            <strong>public-bg-mint</strong><br>
            <small class="text-muted">Mint green (#f0fdf4)</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-lavender">
            <strong>public-bg-lavender</strong><br>
            <small class="text-muted">Lavender (#faf5ff)</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-peach">
            <strong>public-bg-peach</strong><br>
            <small class="text-muted">Peach (#fff7ed)</small>
        </div>
    </div>
</div>

<h3>Gradient Backgrounds (Modern)</h3>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-gradient-purple text-white">
            <strong>public-bg-gradient-purple</strong><br>
            <small>Elegant purple/blue</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-gradient-ocean text-white">
            <strong>public-bg-gradient-ocean</strong><br>
            <small>Deep ocean</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-gradient-sunset text-white">
            <strong>public-bg-gradient-sunset</strong><br>
            <small>Warm sunset</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-gradient-forest text-white">
            <strong>public-bg-gradient-forest</strong><br>
            <small>Green forest</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-gradient-rose text-white">
            <strong>public-bg-gradient-rose</strong><br>
            <small>Intense rose</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-gradient-sky text-white">
            <strong>public-bg-gradient-sky</strong><br>
            <small>Blue sky</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-gradient-fire text-white">
            <strong>public-bg-gradient-fire</strong><br>
            <small>Blazing fire</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="p-3 border rounded public-bg-gradient-emerald text-white">
            <strong>public-bg-gradient-emerald</strong><br>
            <small>Bright emerald</small>
        </div>
    </div>
</div>

<h2>Complete Examples</h2>

<h3>Example 1: Custom Login Page</h3>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('home')]
public function login() {
    \App\Theme::set('public.header.title', 'Login to System');
    \App\Theme::set('public.header.description', 'Enter your credentials to continue');
    \App\Theme::set('public.theme.bg-class', 'public-bg-gradient-ocean');
    \App\Theme::set('public.footer.text', '© 2025 My Company');
    \App\Theme::set('public.footer.link', 'https://mycompany.com');

    $loginForm = \Builders\FormBuilder::create($this->model, $this->page)
        ->addField('username', 'string', ['label' => 'Username'])
        ->addField('password', 'password', ['label' => 'Password'])
        ->render();

    Response::themePage('public', $loginForm);
}</code></pre>

<h3>Example 2: Minimalist Landing Page</h3>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('home')]
public function landing() {
    \App\Theme::set('public.header.title', 'Welcome to MyApp');
    \App\Theme::set('public.header.description', 'The simple and powerful solution to manage your business');
    \App\Theme::set('public.theme.bg-class', 'public-bg-soft-white');
    \App\Theme::set('public.footer.text', 'MyApp - Made with ❤️ in Italy');
    \App\Theme::set('public.footer.link', '');  // No link

    $content = '
        &lt;div class="text-center"&gt;
            &lt;h2&gt;Main Features&lt;/h2&gt;
            &lt;p&gt;Discover what makes MyApp special&lt;/p&gt;
            &lt;a href="?page=register" class="btn btn-primary btn-lg"&gt;Get Started&lt;/a&gt;
        &lt;/div&gt;
    ';

    Response::themePage('public', $content);
}</code></pre>

<h3>Example 3: Contact Form</h3>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">#[RequestAction('home')]
public function contact() {
    \App\Theme::set('public.header.title', 'Contact Us');
    \App\Theme::set('public.header.description', 'We\'ll be happy to answer your questions');
    \App\Theme::set('public.theme.bg-class', 'public-bg-mint');
    \App\Theme::set('public.header.title-color', '#047857');  // Dark green

    $contactForm = \Builders\FormBuilder::create($this->model, $this->page)
        ->addField('name', 'string', ['label' => 'Name'])
        ->addField('email', 'email', ['label' => 'Email'])
        ->addField('message', 'text', ['label' => 'Message'])
        ->render();

    Response::themePage('public', $contactForm);
}</code></pre>

<h2>Advanced Customization</h2>

<h3>Creating a Custom Background Class</h3>
<p>If the predefined classes don't meet your needs, you can add custom classes in <code>theme.css</code>:</p>

<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-css">/* In theme.css */
.public-bg-custom-brand {
    background: linear-gradient(135deg, #your-color-1 0%, #your-color-2 100%);
}

/* Or a solid color */
.public-bg-custom-corporate {
    background: #f8f9fa;
}</code></pre>

<p>Then use it in your module:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">\App\Theme::set('public.theme.bg-class', 'public-bg-custom-brand');</code></pre>

<h3>Custom Logo</h3>
<p>To use a different logo from the default:</p>
<pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">\App\Theme::set('public.header.logo-path', THEME_URL.'/Assets/my-custom-logo.png');</code></pre>

<h2>Important Notes</h2>

<div class="alert alert-warning">
<strong>Security:</strong>
<ul>
    <li>Make sure to properly configure <code>->access('public')</code> in the module configuration if you want the page to be accessible without login</li>
    <li>Always sanitize user-generated content before rendering it</li>
    <li>Use <code>_r()</code> for outputting dynamic data in HTML</li>
</ul>
</div>

<div class="alert alert-info">
<strong>Responsive Design:</strong>
<p>The public template is fully responsive and automatically adapts to mobile, tablet, and desktop devices.</p>
</div>

<h2>See Also</h2>
<ul>
    <li><a href="?page=docs&action=Framework/Theme/theme">Theme Class</a> - For more details on Theme::set() and Theme::get()</li>
    <li><a href="?page=docs&action=Developer/Form/builders-form">Form Builder</a> - To create forms for use in public pages</li>
    <li><a href="?page=testFormValidation">Live Example</a> - See the public template in action</li>
</ul>

</div>