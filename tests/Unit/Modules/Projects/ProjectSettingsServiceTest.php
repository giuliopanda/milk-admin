<?php
/**
 * Run quickly from shell:
 * php vendor/bin/phpunit tests/Unit/Modules/Projects/ProjectSettingsServiceTest.php
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

use Modules\Projects\ProjectSettingsService;
use PHPUnit\Framework\TestCase;

final class ProjectSettingsServiceTest extends TestCase
{
    private string $tmpManifestPath = '';

    protected function tearDown(): void
    {
        if ($this->tmpManifestPath !== '' && is_file($this->tmpManifestPath)) {
            @unlink($this->tmpManifestPath);
        }
        parent::tearDown();
    }

    public function testSaveProjectSettingsUpdatesManifestFields(): void
    {
        $this->tmpManifestPath = tempnam(sys_get_temp_dir(), 'manifest_');
        $this->assertNotFalse($this->tmpManifestPath);

        $manifest = [
            '_version' => '1.0',
            '_name' => 'Old Name',
            'settings' => ['description' => 'Old Description'],
            'db' => 'db2',
            'ref' => 'Root.json',
            'forms' => [],
        ];
        $this->assertNotFalse(file_put_contents(
            $this->tmpManifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        ));

        $project = [
            'module_name' => 'DemoModule',
            'manifest_abs_path' => $this->tmpManifestPath,
        ];

        $result = ProjectSettingsService::saveProjectSettings($project, [
            'project_title' => 'New Name',
            'project_description' => 'New Description',
            'view_single_record' => '1',
        ]);

        $this->assertTrue($result['success']);

        $savedRaw = file_get_contents($this->tmpManifestPath);
        $this->assertIsString($savedRaw);
        $saved = json_decode((string) $savedRaw, true);
        $this->assertIsArray($saved);
        $this->assertSame('New Name', $saved['name'] ?? null);
        $this->assertSame('New Description', $saved['description'] ?? null);
        $this->assertTrue((bool) ($saved['viewSingleRecord'] ?? false));
    }

    public function testSaveProjectSettingsRejectsEmptyTitle(): void
    {
        $this->tmpManifestPath = tempnam(sys_get_temp_dir(), 'manifest_');
        $this->assertNotFalse($this->tmpManifestPath);

        $manifest = [
            '_version' => '1.0',
            '_name' => 'Old Name',
            'settings' => ['description' => 'Old Description'],
            'db' => 'db2',
            'ref' => 'Root.json',
            'forms' => [],
        ];
        $this->assertNotFalse(file_put_contents(
            $this->tmpManifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        ));

        $result = ProjectSettingsService::saveProjectSettings([
            'module_name' => 'DemoModule',
            'manifest_abs_path' => $this->tmpManifestPath,
        ], [
            'project_title' => '   ',
            'project_description' => 'Anything',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Project title is required', $result['msg']);
    }
}
