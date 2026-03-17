<?php
/**
 * Run quickly from shell:
 * php vendor/bin/phpunit tests/Unit/Modules/Projects/ProjectModelGeneratorServiceTest.php
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

use Modules\Projects\ProjectModelGeneratorService;
use PHPUnit\Framework\TestCase;

final class ProjectModelGeneratorServiceTest extends TestCase
{
    public function testBuildFromDescribeMapsPrimaryKeyAndFieldTypes(): void
    {
        $describe = [
            'keys' => ['ID_RECORD'],
            'struct' => [
                (object) [
                    'Field' => 'ID_RECORD',
                    'Type' => 'int(11)',
                    'Null' => 'NO',
                    'Key' => 'PRI',
                    'Default' => null,
                    'Extra' => 'auto_increment',
                ],
                (object) [
                    'Field' => 'TITLE',
                    'Type' => 'varchar(120)',
                    'Null' => 'NO',
                    'Key' => '',
                    'Default' => null,
                    'Extra' => '',
                ],
                (object) [
                    'Field' => 'ACTIVE',
                    'Type' => 'tinyint(1)',
                    'Null' => 'NO',
                    'Key' => '',
                    'Default' => '1',
                    'Extra' => '',
                ],
                (object) [
                    'Field' => 'TOTAL',
                    'Type' => 'decimal(12,4)',
                    'Null' => 'YES',
                    'Key' => '',
                    'Default' => '0.0000',
                    'Extra' => '',
                ],
                (object) [
                    'Field' => 'CREATED_AT',
                    'Type' => 'datetime',
                    'Null' => 'NO',
                    'Key' => '',
                    'Default' => 'CURRENT_TIMESTAMP',
                    'Extra' => '',
                ],
            ],
        ];

        $result = ProjectModelGeneratorService::buildFromDescribe(
            'TestModelGenerator',
            'TestModelGenerator',
            'test_model_generator_table',
            'db2',
            $describe
        );

        $this->assertTrue($result['success']);
        $this->assertSame('ID_RECORD', $result['primary_key']);

        $content = (string) $result['content'];
        $this->assertStringContainsString("->id('ID_RECORD')", $content);
        $this->assertStringContainsString("->string('TITLE', 120)", $content);
        $this->assertStringContainsString("->boolean('ACTIVE')", $content);
        $this->assertStringContainsString("->default(true)", $content);
        $this->assertStringContainsString("->decimal('TOTAL', 12, 4)", $content);
        $this->assertStringContainsString("->datetime('CREATED_AT')", $content);
        $this->assertStringContainsString("->default('CURRENT_TIMESTAMP')", $content);
        $this->assertStringContainsString("->extensions(['Projects']);", $content);
    }

    public function testBuildFromDescribeFallsBackToFirstColumnAsPrimaryKey(): void
    {
        $describe = [
            'keys' => [],
            'struct' => [
                (object) [
                    'Field' => 'code',
                    'Type' => 'varchar(20)',
                    'Null' => 'NO',
                    'Key' => '',
                    'Default' => null,
                    'Extra' => '',
                ],
                (object) [
                    'Field' => 'status',
                    'Type' => "enum('draft','live')",
                    'Null' => 'NO',
                    'Key' => '',
                    'Default' => 'draft',
                    'Extra' => '',
                ],
                (object) [
                    'Field' => 'payload',
                    'Type' => 'json',
                    'Null' => 'YES',
                    'Key' => '',
                    'Default' => null,
                    'Extra' => '',
                ],
            ],
        ];

        $result = ProjectModelGeneratorService::buildFromDescribe(
            'TestModelGenerator',
            'TestModelGenerator',
            'test_model_generator_table',
            'db2',
            $describe
        );

        $this->assertTrue($result['success']);
        $this->assertSame('code', $result['primary_key']);

        $content = (string) $result['content'];
        $this->assertStringContainsString("->id('code')", $content);
        $this->assertStringContainsString("->enum('status', ['draft', 'live'])", $content);
        $this->assertStringContainsString("->array('payload')", $content);
    }
}
