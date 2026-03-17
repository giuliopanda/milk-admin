<?php
/**
 * Run quickly from shell:
 * php vendor/bin/phpunit tests/Unit/Extensions/Projects/ViewSchemaParserTest.php
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

use Extensions\Projects\Classes\View\ViewSchemaParser;
use PHPUnit\Framework\TestCase;

final class ViewSchemaParserTest extends TestCase
{
    public function testParseArrayBuildsSchema(): void
    {
        $parser = new ViewSchemaParser();

        $schema = $parser->parseArray([
            'version' => '1.0',
            'cards' => [
                [
                    'id' => 'main',
                    'type' => 'single-table',
                    'table' => [
                        'name' => 'RootForm',
                        'displayAs' => 'fields',
                    ],
                ],
                [
                    'id' => 'children',
                    'type' => 'group',
                    'tables' => [
                        [
                            'name' => 'Visit1',
                            'displayAs' => 'table',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('1.0', $schema->version);
        $this->assertCount(2, $schema->cards);
        $this->assertSame('RootForm', $schema->getMainFormName());
        $this->assertSame([], $parser->getWarnings());
        $this->assertSame(['RootForm', 'Visit1'], $schema->getAllFormNames());
    }

    public function testParseArrayDefaultsMissingDisplayAsToIcon(): void
    {
        $parser = new ViewSchemaParser();

        $schema = $parser->parseArray([
            'cards' => [
                [
                    'id' => 'group-1',
                    'type' => 'group',
                    'tables' => [
                        [
                            'name' => 'Demographics',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $schema->cards);
        $this->assertSame('icon', $schema->cards[0]->tables[0]->displayAs);
        $this->assertNotEmpty($parser->getWarnings());
    }
}
