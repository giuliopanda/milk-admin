<?php
namespace Modules\Docs;

use Builders\LinksBuilder;
use App\Route;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Documentation Service
 *
 * Handles business logic for documentation:
 * - Scanning and organizing documentation files
 * - Filtering by guide
 * - Generating filtered sidebars
 */
class DocsService
{
    /**
     * Category order for sidebar display
     * Categories not in this list will be placed at the end in alphabetical order
     */
    private static $categoryOrder = [
        // Developer
        'Getting started',
        'Abstracts Class',
        'Model Class',
        'Builders',
        'Advanced',
        // Framework
        'Core',
        'Theme',
        'Dynamic Table',
        'Forms',
        // User
        'Modules',
        'System Administration'
    ];

    /**
     * Dynamically scan guide directories and build category mapping
     *
     * @return array Map of guides to their categories
     */
    private static function buildGuideCategoriesMap(): array
    {
        $docs_path = MILK_DIR . '/Modules/Docs/Pages';
        $guideCategories = [];

        if (!is_dir($docs_path)) {
            return [];
        }

        // Scan top-level directories (guides)
        $guides = scandir($docs_path);
        foreach ($guides as $guide) {
            if ($guide == '.' || $guide == '..') continue;

            $guide_path = $docs_path . '/' . $guide;
            if (is_dir($guide_path)) {
                $guide_key = strtolower($guide);
                $guideCategories[$guide_key] = [];

                // Scan subdirectories (categories)
                $categories = scandir($guide_path);
                foreach ($categories as $category) {
                    if ($category == '.' || $category == '..') continue;

                    $category_path = $guide_path . '/' . $category;
                    if (is_dir($category_path)) {
                        // Convert directory name to display name
                        // e.g., "GettingStarted" -> "Getting started"
                        $display_name = self::convertDirNameToDisplayName($category);
                        $guideCategories[$guide_key][] = $display_name;
                    }
                }
            }
        }

        return $guideCategories;
    }

    /**
     * Convert directory name to display name
     * Examples: "GettingStarted" -> "Getting started"
     *           "AbstractsClass" -> "Abstracts Class"
     *           "Administration" -> "System Administration"
     *
     * @param string $dirName Directory name
     * @return string Display name
     */
    public static function convertDirNameToDisplayName(string $dirName): string
    {
        // Special case mappings
        $specialMappings = [
            'Administration' => 'System Administration',
            'GettingStarted' => 'Getting started',
            'AbstractsClass' => 'Abstracts Class',
            'ModelClass' => 'Model Class',
            'DynamicTable' => 'Dynamic Table'
        ];

        if (isset($specialMappings[$dirName])) {
            return $specialMappings[$dirName];
        }

        // Split on capital letters
        $words = preg_split('/(?=[A-Z])/', $dirName, -1, PREG_SPLIT_NO_EMPTY);

        // Join with spaces - keep all words capitalized (Title Case)
        return implode(' ', $words);
    }

    /**
     * Get guide categories (cached for performance)
     *
     * @return array Map of guides to their categories
     */
    private static function getGuideCategories(): array
    {
        static $cache = null;

        if ($cache === null) {
            $cache = self::buildGuideCategoriesMap();
        }

        return $cache;
    }

    /**
     * Scan documentation files filtered by guide
     *
     * @param string $guide Guide name (developer|framework|user)
     * @return array Array of documents with metadata
     */
    public static function scanDocumentationFiles(string $guide = 'developer'): array
    {
        $docs_path = MILK_DIR . '/Modules/Docs/Pages';
        $documents = [];
        $guideCategories = self::getGuideCategories();
        $allowedCategories = $guideCategories[$guide] ?? $guideCategories['developer'] ?? [];

        // Recursive directory scanner
        $scanDir = function($dir, $base_path = '') use (&$scanDir, &$documents, $allowedCategories, $guide) {
            if (!is_dir($dir)) {
                return;
            }

            $files = scandir($dir);

            foreach ($files as $file) {
                if ($file == '.' || $file == '..') continue;

                $full_path = $dir . '/' . $file;
                $relative_path = $base_path ? $base_path . '/' . $file : $file;

                if (is_dir($full_path)) {
                    $scanDir($full_path, $relative_path);
                } elseif (is_file($full_path) && strpos($file, '.page.php') !== false) {
                    $metadata = new DocMetadata($full_path);

                    // Filter by allowed categories for this guide
                    if (in_array($metadata->category, $allowedCategories)) {
                        $documents[] = [
                            'path' => str_replace('.page.php', '', $relative_path),
                            'file' => $file,
                            'title' => $metadata->title,
                            'category' => $metadata->category,
                            'order' => $metadata->order,
                            'tags' => $metadata->tags,
                            'guide' => $metadata->guide ?? $guide,
                            'search_text' => strtolower($metadata->title . ' ' . implode(' ', $metadata->tags))
                        ];
                    }
                }
            }
        };

        $scanDir($docs_path);

        // Sort documents by category and order
        usort($documents, function($a, $b) {
            if ($a['category'] == $b['category']) {
                return $a['order'] - $b['order'];
            }
            return strcmp($a['category'], $b['category']);
        });

        return $documents;
    }

    /**
     * Generate sidebar HTML filtered by guide
     *
     * @param string $guide Guide name (developer|framework|user)
     * @return string HTML sidebar
     */
    public static function generateSidebar(string $guide = 'developer'): string
    {
        $documents = self::scanDocumentationFiles($guide);

        // Group documents by category
        $categories = [];
        foreach ($documents as $doc) {
            $categories[$doc['category']][] = $doc;
        }

        // Sort categories according to the defined order
        uksort($categories, function($a, $b) {
            $aPos = array_search($a, self::$categoryOrder);
            $bPos = array_search($b, self::$categoryOrder);

            if ($aPos !== false && $bPos !== false) {
                return $aPos - $bPos;
            }
            if ($aPos !== false) return -1;
            if ($bPos !== false) return 1;

            return strcmp($a, $b);
        });

        // Create sidebar using LinksBuilder
        $builder = LinksBuilder::create()
            ->enableSearch('Search documentation...', 'docsSearchInput', 'searchResultCount');
           

        // Add each category as a group
        foreach ($categories as $category => $docs) {
            if ($category === 'hidden') continue;

            $groupId = 'category-' . preg_replace('/[^a-zA-Z0-9]/', '-', strtolower($category));
            $builder->addGroup($groupId, $category);

            // Add all documents in the category
            foreach ($docs as $doc) {
                // IMPORTANT: include &guide= in all links
                $url = "?page=docs&action=" . $doc['path'];
                $searchData = htmlspecialchars(json_encode([
                    'title' => $doc['title'],
                    'tags' => $doc['tags'],
                    'search' => $doc['search_text']
                ]));

                $builder->add($doc['title'], $url)->setParam('search_data', $searchData);
            }
        }

        return $builder->render('sidebar');
    }

    /**
     * Get guide name from category
     *
     * @param string $category Category name
     * @return string Guide name
     */
    public static function getGuideFromCategory(string $category): string
    {
        $guideCategories = self::getGuideCategories();

        foreach ($guideCategories as $guide => $categories) {
            if (in_array($category, $categories)) {
                return $guide;
            }
        }
        return 'developer';
    }

    /**
     * Get all categories for a guide
     *
     * @param string $guide Guide name
     * @return array Categories
     */
    public static function getCategoriesForGuide(string $guide): array
    {
        $guideCategories = self::getGuideCategories();
        return $guideCategories[$guide] ?? [];
    }
}


/**
 * Document Metadata Parser
 *
 * Parses metadata from documentation page files
 */
class DocMetadata
{
    public $title = '';
    public $category = 'Uncategorized';
    public $order = 100;
    public $tags = [];
    public $guide = null;

    public function __construct($file_path)
    {
        $this->parseMetadata($file_path);
    }

    /**
     * Get first N lines of file for metadata parsing
     */
    private function getFirstLines($file_path, $num_lines = 20)
    {
        $lines = [];
        $handle = fopen($file_path, 'r');

        if ($handle) {
            for ($i = 0; $i < $num_lines && !feof($handle); $i++) {
                $line = fgets($handle);
                if ($line !== false) {
                    $lines[] = rtrim($line, "\r\n");
                }
            }
            fclose($handle);
        }

        return implode("\n", $lines);
    }

    /**
     * Parse metadata from file content
     */
    private function parseMetadata($file_path)
    {
        if (!file_exists($file_path)) return;

        $content = $this->getFirstLines($file_path);

        // Parse @title
        if (preg_match('/@title\s+(.+)$/m', $content, $m)) {
            $this->title = trim($m[1]);
        }

        // Parse @order
        if (preg_match('/@order\s+(\d+)\s*$/m', $content, $m)) {
            $this->order = (int)trim($m[1]);
        }

        // Parse @tags
        if (preg_match('/@tags\s+(.+)$/m', $content, $m)) {
            $this->tags = array_map('trim', explode(',', $m[1]));
        }

        // If no title, use filename
        if (empty($this->title)) {
            $this->title = ucfirst(str_replace(["-", ".page.php"], [" ", ""], basename($file_path)));
        }

        // Infer category and guide from directory structure
        // Path structure: .../Pages/Guide/Category/file.page.php
        $this->inferCategoryAndGuideFromPath($file_path);
    }

    /**
     * Infer category and guide from file path
     * Path structure: .../Pages/Guide/Category/file.page.php
     * Example: .../Pages/Framework/Core/api.page.php
     *   -> guide = "framework", category = "Core"
     *
     * @param string $file_path File path
     */
    private function inferCategoryAndGuideFromPath($file_path)
    {
        // Extract path relative to Pages directory
        if (preg_match('#/Pages/([^/]+)/([^/]+)/#', $file_path, $matches)) {
            $guideDir = $matches[1];      // e.g., "Developer", "Framework", "User"
            $categoryDir = $matches[2];   // e.g., "Core", "GettingStarted"

            // Convert directory names to display names
            $this->guide = strtolower($guideDir);
            $this->category = DocsService::convertDirNameToDisplayName($categoryDir);
        }
    }
}
