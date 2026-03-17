<?php
/**
 * Run quickly from shell:
 * php vendor/bin/phpunit tests/Unit/Extensions/Projects/ModelJsonParserIgnoreExistingFieldsTest.php
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
use PHPUnit\Framework\TestCase;

final class ModelJsonParserIgnoreExistingFieldsTest extends TestCase
{
    public function testSchemaFieldWithSameNameAsModelIsIgnored(): void
    {
        $rule = new RuleBuilder();
        $rule->id('id');
        $rule->string('name', 50)->label('Base Name');

        $schema = [
            'fields' => [
                ['name' => 'name', 'method' => 'string', 'label' => 'From JSON', 'required' => true],
                ['name' => 'email', 'method' => 'email', 'label' => 'Email'],
                ['name' => 'id', 'method' => 'id', 'replace' => true],
            ],
        ];

        $parser = new ModelJsonParser();

        $analysis = $parser->analyzeIgnoredFields($schema, $rule);
        $this->assertSame([], $analysis);

        $parser->parse($schema, $rule);

        $rules = $rule->getRules();
        $this->assertSame('From JSON', $rules['name']['label'] ?? null);
        $this->assertSame(true, $rules['name']['form-params']['required'] ?? false);
        $this->assertArrayHasKey('email', $rules);

        $ignored = $parser->getLastIgnoredFields();
        $this->assertSame([], $ignored);
    }
}
