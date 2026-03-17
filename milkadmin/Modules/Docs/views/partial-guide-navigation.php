<?php
!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Partial View: Guide Navigation
 *
 * Dynamically generates navigation pills for documentation guides
 * based on first-level folders in Pages directory.
 * Automatically detects the active guide from the action parameter.
 */

// Get current action and extract guide from it
$currentAction = $_GET['action'] ?? '';
$activeGuide = '';

// Extract the first part of the action path (Developer/Framework/User)
if (!empty($currentAction)) {
    $actionParts = explode('/', $currentAction);
    $activeGuide = strtolower($actionParts[0] ?? '');
}

// If no action, try to get from 'guide' parameter
if (empty($activeGuide)) {
    $action = $_GET['action'] ?? '';
    $actionParts = explode('/', $action);
    $activeGuide = strtolower($actionParts[0] ?? 'developer');
}

// Define default guide configurations with icons
// This provides icons and labels for known guides
$defaultGuideConfigs = [
    'developer' => [
        'label' => 'Developer',
        'icon' => 'bi bi-code-square'
    ],
    'framework' => [
        'label' => 'Framework',
        'icon' => 'bi bi-gear-fill'
    ],
    'user' => [
        'label' => 'User Guide',
        'icon' => 'bi bi-book-half'
    ]
];

// Dynamically scan Pages directory for first-level folders
$pagesDir = MILK_DIR . '/Modules/Docs/Pages';
$guideFolders = [];
$guideConfigs = [];

if (is_dir($pagesDir)) {
    $items = scandir($pagesDir);
    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..' && is_dir($pagesDir . '/' . $item)) {
            $guideFolders[] = $item;

            // Build dynamic config for this guide
            $guideLower = strtolower($item);
            if (isset($defaultGuideConfigs[$guideLower])) {
                // Use predefined config
                $guideConfigs[$guideLower] = $defaultGuideConfigs[$guideLower];
            } else {
                // Auto-generate config for new guides
                $guideConfigs[$guideLower] = [
                    'label' => ucfirst($item),
                    'icon' => 'bi bi-folder'
                ];
            }
        }
    }
    sort($guideFolders);
}
?>
<ul class="nav nav-pills d-flex align-items-center">
<?php foreach ($guideFolders as $folder):
    $folderLower = strtolower($folder);
    $config = $guideConfigs[$folderLower] ?? [
        'label' => $folder,
        'icon' => 'bi bi-folder'
    ];

    $isActive = ($activeGuide === $folderLower) ? 'active' : '';

    // Build URL using action parameter with default page for each guide
    $defaultPages = [
        'developer' => 'Developer/GettingStarted/introduction',
        'framework' => 'Framework/Core/api',
        'user' => 'User/Administration/user-management-guide'
    ];
    $defaultAction = $defaultPages[$folderLower] ?? $defaultPages['developer'];
    $url = "?page=docs&action=" . urlencode($defaultAction);
?>
    <li class="nav-item">
        <a class="nav-link <?php _p($isActive) ?>" href="<?php _p($url) ?>">
            <i class="<?php _p($config['icon']) ?>"></i>
            <span class="d-none d-lg-inline ms-1"><?php _p($config['label']) ?></span>
        </a>
    </li>
<?php endforeach; ?>
</ul>
