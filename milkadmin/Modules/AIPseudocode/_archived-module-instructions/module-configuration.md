# Module Configuration

Configuration options for AbstractModule.

## configure() Method

All configuration via `$rule` builder in `configure()` method.

### Basic Configuration

```php
$rule->page(string $name)              // Module page name (URL: ?page=name)
     ->title(string $title)             // Module title
     ->access(string $level)            // Access level: public|registered|authorized|admin
     ->version(int $version)            // Version (YYMMDD format)
```

### Menu & Navigation

If there are no different indications, it automatically generates a menu

```php
$rule->menu(string $name, string $url, string $icon, int $order)
     // Add sidebar menu link. URL relative to page. Order: lower = higher

$rule->menuLinks(array $links)
     // Set all menu links at once
     // Format: [['name'=>'...', 'url'=>'...', 'icon'=>'...', 'order'=>10]]
```

### Header Configuration

```php
$rule->headerTitle(string $title)           // Page header title
     ->headerDescription(string $desc)       // Page header description
     ->addHeaderLink(string $title, string $url, string $icon)  // Header navigation link
     ->headerStyle(string $style)            // Style: pills|tabs|underline|default
     ->headerPosition(string $pos)           // Position: top-left|top-right
```

### Permissions

```php
$rule->permission(array $perms)
     // Define permissions for 'authorized' access
     // Format: ['key' => 'description']
     // Example: ['access' => 'Access Posts', 'edit' => 'Edit Posts']
```

### Assets

```php
$rule->setJs(string $path)    // Load JavaScript file (relative or absolute)
     ->setCss(string $path)   // Load CSS file (relative or absolute)
     // Relative paths start from module folder: /Assets/script.js
     // Absolute paths from Modules dir: Modules/Auth/Assets/auth.js
```

### Auto-loaded Components (by naming convention)

Components auto-load if files follow naming convention:
- `{ModuleName}Model.php` → Model
- `{ModuleName}Shell.php` → Shell (CLI commands)
- `{ModuleName}Router.php` → Router
- `{ModuleName}Hook.php` → Hook handler
- `{ModuleName}Api.php` → API handler
- `{ModuleName}Install.php` → Installation handler

No configuration needed - just create files with correct names.

### Multiple Tables

```php
$rule->addModels(array $models)
     // Add additional models for modules with multiple tables
     // Format: ['ModelName' => ModelClass::class]
     // Access via: $this->getAdditionalModels('ModelName')
```

### Module Flags

```php
$rule->disableCli()       // Disable automatic CLI commands (install/update/uninstall)
     ->isCoreModule()     // Mark as core module (cannot be removed/disabled)
```

## Lifecycle Methods

### bootstrap()
Called when module is loaded (pages, shell, APIs, hooks). Called once per request.
Use for: initializing models, loading translations, setting up resources.

```php
public function bootstrap()
{
    // Initialize module resources
}
```

### init()
Called ONLY when module page is accessed (?page=module-name).
Use for: loading page-specific assets.

```php
public function init()
{
    // Page-specific initialization
}
```

### Context-Specific Init

```php
hookInit()      // Called during 'init' hook phase
cliInit()       // Called when CLI commands run
apiInit()       // Called when API requests handled
jobsInit()      // Called during cron job initialization
jobsStart()     // Called when background jobs start
```

## Complete Example

```php
protected function configure($rule): void
{
    $rule->page('posts')
         ->title('Posts Management')
         ->access('authorized')
         ->permission(['access' => 'Access Posts', 'edit' => 'Edit Posts'])
         ->menu('All Posts', '', 'bi bi-list', 10)
         ->menu('Categories', 'action=categories', 'bi bi-tags', 20)
         ->headerTitle('Posts Dashboard')
         ->headerDescription('Manage your blog posts')
         ->addHeaderLink('New Post', '?page=posts&action=new', 'bi bi-plus')
         ->setJs('/Assets/posts.js')
         ->setCss('/Assets/posts.css')
         ->addModels(['Categories' => CategoriesModel::class])
         ->version(251205);
}

public function bootstrap()
{
    // Called on every request when module loaded
}

public function init()
{
    // Called only when ?page=posts accessed
}
```
