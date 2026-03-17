<?php
/**
 * Run quickly from shell:
 * php vendor/bin/phpunit tests/Unit/Extensions/Projects/AutoEditRendererSuccessTargetTest.php
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

use App\Abstracts\AbstractModule;
use Extensions\Projects\Classes\Module\ActionContextRegistry;
use Extensions\Projects\Classes\Module\BreadcrumbManager;
use Extensions\Projects\Classes\Module\FkChainResolver;
use Extensions\Projects\Classes\Module\ShowIfEvaluator;
use Extensions\Projects\Classes\Renderers\AutoEditRenderer;
use PHPUnit\Framework\TestCase;

final class AutoEditRendererSuccessTargetTest extends TestCase
{
    public function testRootFormWithViewActionRedirectsToViewAfterSave(): void
    {
        $renderer = $this->makeRenderer();

        $result = $renderer->resolveAutomaticSuccessTargetForTest(
            [
                'is_root' => true,
                'view_action' => 'longitudinal-database-view',
            ],
            'longitudinal-database-list',
            [],
            0,
            true,
            'n',
            true,
            'id'
        );

        $this->assertSame('longitudinal-database-view', $result['action'] ?? null);
        $this->assertSame('%id%', $result['params']['id'] ?? null);
    }

    public function testRootFormFallsBackToListWhenPrimaryKeyIsMissing(): void
    {
        $renderer = $this->makeRenderer();

        $result = $renderer->resolveAutomaticSuccessTargetForTest(
            [
                'is_root' => true,
                'view_action' => 'longitudinal-database-view',
            ],
            'longitudinal-database-list',
            [],
            0,
            true,
            'n',
            true,
            ''
        );

        $this->assertSame('longitudinal-database-list', $result['action'] ?? null);
    }

    private function makeRenderer(): object
    {
        $registry = new ActionContextRegistry();
        $fkResolver = new FkChainResolver();
        $breadcrumbManager = new BreadcrumbManager($registry, $fkResolver);
        $showIfEvaluator = new ShowIfEvaluator();

        $module = new class extends AbstractModule {
            public function __construct()
            {
                // Keep test double lightweight: avoid full module bootstrap.
            }

            public function getPage(): string
            {
                return 'test-module';
            }

            public function getCommonData(): array
            {
                return [];
            }
        };

        return new class($module, $registry, $fkResolver, $breadcrumbManager, $showIfEvaluator) extends AutoEditRenderer {
            public function resolveAutomaticSuccessTargetForTest(
                array $context,
                string $listAction,
                array $chainParams,
                int $rootId,
                bool $isRoot,
                string $maxRecords,
                bool $hasChildren,
                string $primaryKey
            ): array {
                return $this->resolveAutomaticSuccessTarget(
                    $context,
                    $listAction,
                    $chainParams,
                    $rootId,
                    $isRoot,
                    $maxRecords,
                    $hasChildren,
                    $primaryKey
                );
            }
        };
    }
}
