<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MilkSelectRuntimeApiTest extends TestCase
{
    public function testMilkSelectRuntimeScript(): void
    {
        $nodeBinary = trim((string) shell_exec('command -v node'));
        if ($nodeBinary === '') {
            $this->markTestSkipped('Node.js not available in environment');
        }

        $projectRoot = dirname(__DIR__, 4);
        $scriptPath = $projectRoot . '/tests/Unit/Theme/Plugins/milkselect.runtime.test.js';

        $output = [];
        $exitCode = 1;
        $command = escapeshellarg($nodeBinary) . ' ' . escapeshellarg($scriptPath) . ' 2>&1';
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, "Node runtime test failed:\n" . implode("\n", $output));
    }
}
