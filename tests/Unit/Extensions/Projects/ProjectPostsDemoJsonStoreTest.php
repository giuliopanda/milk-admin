<?php
/**
 * Run quickly from shell:
 * php vendor/bin/phpunit tests/Unit/Extensions/Projects/ProjectPostsDemoJsonStoreTest.php
 */

declare(strict_types=1);

if (!defined('MILK_TEST_CONTEXT')) {
    define('MILK_TEST_CONTEXT', true);
}
if (!defined('MILK_API_CONTEXT')) {
    define('MILK_API_CONTEXT', true);
}

require_once dirname(__DIR__, 4) . '/public_html/milkadmin.php';
require_once MILK_DIR . '/autoload.php';

use App\Abstracts\RuleBuilder;
use Extensions\Projects\Classes\ModelJsonParser;
use Extensions\Projects\Classes\ProjectJsonStore;
use PHPUnit\Framework\TestCase;

final class ProjectPostsDemoJsonStoreTest extends TestCase
{
    private string $projectDir;
    private string $tempRootDir;

    protected function setUp(): void
    {
        parent::setUp();

        ProjectJsonStore::resetAll();
        $this->tempRootDir = rtrim(sys_get_temp_dir(), '/\\') . '/milk-project-posts-demo-' . uniqid('', true);
        $this->projectDir = $this->tempRootDir . '/Project';
        $this->createDemoProjectFixture();
    }

    protected function tearDown(): void
    {
        ProjectJsonStore::resetAll();
        $this->removeDirectory($this->tempRootDir);
        parent::tearDown();
    }

    public function testManifestAndIndexFromDemoModuleAreReadable(): void
    {
        $this->assertDirectoryExists($this->projectDir);

        $store = ProjectJsonStore::for($this->projectDir);

        $this->assertSame('Project Posts Demo', $store->manifestName());

        $index = $store->manifestIndex();
        $this->assertNotNull($index);
        $this->assertSame(['ProjectPostsDemo'], $index->getRootFormNames());

        $node = $index->getNode('ProjectPostsDemo');
        $this->assertIsArray($node);
        $this->assertSame('page', $node['list_display'] ?? null);
        $this->assertSame('page', $node['edit_display'] ?? null);
        $this->assertTrue((bool) ($node['view_action'] ?? false));
    }

    public function testPostSchemaHasCoverImageAndCategoriesFields(): void
    {
        $store = ProjectJsonStore::for($this->projectDir);
        $schema = $store->schemaModel('ProjectPostsDemo');

        $this->assertNotEmpty($schema);

        $parser = new ModelJsonParser();
        $rule = $parser->parse($schema, new RuleBuilder());
        $rules = $rule->getRules();

        $this->assertSame('image', $rules['cover_image']['form-type'] ?? null);
        $this->assertSame('project-posts-demo', $rules['cover_image']['form-params']['upload-dir'] ?? null);
        $this->assertSame(1, $rules['cover_image']['form-params']['max-files'] ?? null);
        $this->assertSame('image/*', $rules['cover_image']['form-params']['accept'] ?? null);

        $this->assertSame('checkboxes', $rules['categories']['form-type'] ?? null);
        $this->assertSame('Tech', $rules['categories']['options']['tech'] ?? null);
        $this->assertSame('Eventi', $rules['categories']['options']['events'] ?? null);
    }

    private function createDemoProjectFixture(): void
    {
        if (!is_dir($this->projectDir) && !mkdir($concurrentDirectory = $this->projectDir, 0777, true) && !is_dir($concurrentDirectory)) {
            self::fail('Unable to create test project directory: ' . $this->projectDir);
        }

        $manifest = [
            'version' => '1.0',
            'name' => 'Project Posts Demo',
            'ref' => 'ProjectPostsDemo.json',
            'viewAction' => true,
            'listDisplay' => 'page',
            'editDisplay' => 'page',
        ];

        $schema = [
            '_version' => '1.0',
            '_name' => 'Project Posts Demo',
            'model' => [
                'fields' => [
                    ['name' => 'title', 'method' => 'title', 'required' => true],
                    ['name' => 'slug', 'method' => 'string', 'required' => true, 'unique' => true],
                    ['name' => 'content', 'method' => 'text', 'formType' => 'editor'],
                    [
                        'name' => 'cover_image',
                        'method' => 'image',
                        'label' => 'Cover image',
                        'uploadDir' => 'project-posts-demo',
                        'maxFiles' => 1,
                        'accept' => 'image/*',
                    ],
                    [
                        'name' => 'categories',
                        'method' => 'checkboxes',
                        'label' => 'Categories',
                        'options' => [
                            'tech' => 'Tech',
                            'news' => 'News',
                            'tutorial' => 'Tutorial',
                            'events' => 'Eventi',
                        ],
                    ],
                ],
            ],
        ];

        file_put_contents(
            $this->projectDir . '/manifest.json',
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        file_put_contents(
            $this->projectDir . '/ProjectPostsDemo.json',
            (string) json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function removeDirectory(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
                continue;
            }

            @unlink($itemPath);
        }

        @rmdir($path);
    }
}
