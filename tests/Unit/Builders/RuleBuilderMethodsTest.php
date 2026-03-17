<?php
/**
 * Run quickly from shell:
 * php vendor/bin/phpunit tests/Unit/Builders/RuleBuilderMethodsTest.php
 * Documentation: milkadmin/Modules/Docs/Pages/Framework/Core/rulebuilder.page.php
 */

declare(strict_types=1);

namespace {
    if (!defined('MILK_TEST_CONTEXT')) {
        define('MILK_TEST_CONTEXT', true);
    }
    if (!defined('MILK_API_CONTEXT')) {
        define('MILK_API_CONTEXT', true);
    }

    $projectRoot = dirname(__DIR__, 3);
    require_once $projectRoot . '/public_html/milkadmin.php';
    require_once MILK_DIR . '/autoload.php';
}

namespace Tests\Unit\Builders\RuleBuilder {
    use App\Abstracts\AbstractModel;
    use App\Abstracts\RuleBuilder;
    use App\Get;
    use PHPUnit\Framework\TestCase;

    class RBParentModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('test_rulebuilder_parent')
                ->id()
                ->string('name', 50);
        }
    }

    class RBChildModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('test_rulebuilder_child')
                ->id()
                ->int('parent_id')
                ->string('title', 50);
        }
    }

    class RBBelongsToModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('test_rulebuilder_belongs')
                ->id()
                ->int('parent_id')->belongsTo('parent', RBParentModel::class, 'id');
        }
    }

    class RBRenameModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('test_rulebuilder_rename')
                ->id()
                ->string('name', 150)
                ->renameField('full_name', 'name');
        }
    }

    class RuleBuilderMethodsTest extends TestCase
    {
        private $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Get::db();
    }

    public function testTableDbRenameGetters(): void
    {
        $rule = new RuleBuilder();
        $rule->table('test_table')->db('db')->renameField('old_name', 'new_name');

        $rename = $rule->getRenameFields();

        $this->assertSame('test_table', $rule->getTable());
        $this->assertSame('db', $rule->getDbType());
        $this->assertSame('new_name', $rename['old_name'] ?? null);
    }

    public function testRemovePrimaryKeysRemovesId(): void
    {
        $rule = new RuleBuilder();
        $rule->id();
        $rule->string('name', 50);
        $rule->removePrimaryKeys();
        $rules = $rule->getRules();

        $this->assertArrayNotHasKey('id', $rules);
        $this->assertNull($rule->getPrimaryKey());
    }

    public function testBasicFieldTypes(): void
    {
        $rule = new RuleBuilder();
        $rule->id('id')
            ->string('name', 100)->required()->unique()->index()->default('test')
            ->text('bio')->nullable()
            ->int('age')
            ->decimal('price', 10, 2)
            ->boolean('active')
            ->date('born_at')
            ->datetime('created_at')
            ->time('start_time')
            ->timestamp('updated_at')
            ->array('tags')
            ->email('email')
            ->tel('phone')
            ->url('website');

        $rules = $rule->getRules();

        $this->assertSame('id', $rules['id']['type'] ?? '');
        $this->assertSame(100, $rules['name']['length'] ?? 0);
        $this->assertSame(true, $rules['name']['unique'] ?? false);
        $this->assertSame(true, $rules['name']['index'] ?? false);
        $this->assertSame(true, $rules['name']['form-params']['required'] ?? false);
        $this->assertSame('text', $rules['bio']['type'] ?? '');
        $this->assertSame('array', $rules['tags']['type'] ?? '');
        $this->assertSame('string', $rules['email']['type'] ?? '');
        $this->assertSame('string', $rules['phone']['type'] ?? '');
        $this->assertSame('string', $rules['website']['type'] ?? '');
    }

    public function testListSelectEnumRadioCheckboxes(): void
    {
        $rule = new RuleBuilder();
        $rule->list('status', ['draft' => 'Draft', 'live' => 'Live'])
            ->select('category', ['a' => 'A', 'b' => 'B'])
            ->enum('mode', ['basic', 'advanced'])
            ->radio('level', ['1' => 'One', '2' => 'Two'])
            ->checkboxes('flags', ['a' => 'A', 'b' => 'B']);

        $rules = $rule->getRules();
        $this->assertSame('list', $rules['status']['type'] ?? '');
        $this->assertSame('list', $rules['category']['type'] ?? '');
        $this->assertSame('enum', $rules['mode']['type'] ?? '');
        $this->assertSame('radio', $rules['level']['type'] ?? '');
        $this->assertSame('array', $rules['flags']['type'] ?? '');
        $this->assertArrayHasKey('label', $rules['level']['form-params'] ?? []);
        $this->assertSame('Level', $rules['level']['form-params']['label'] ?? null);
    }

    public function testTitleCreatedAtNoTimezoneConversion(): void
    {
        $rule = new RuleBuilder();
        $rule->title('title', 80)
            ->created_at('created_at')
            ->noTimezoneConversion();
        $rules = $rule->getRules();
        $this->assertSame(true, $rules['title']['_is_title_field'] ?? false);
        $this->assertSame(true, $rules['created_at']['_auto_created_at'] ?? false);
        $this->assertSame(false, $rules['created_at']['timezone_conversion'] ?? true);
    }

    public function testFileFormParams(): void
    {
        $rule = new RuleBuilder();
        $rule->file('doc')
            ->multiple(true)
            ->maxFiles(3)
            ->accept('.pdf')
            ->maxSize(1024)
            ->uploadDir('uploads')
            ->image('photo');

        $rules = $rule->getRules();
        $this->assertSame('file', $rules['doc']['form-type'] ?? '');
        $this->assertSame('multiple', $rules['doc']['form-params']['multiple'] ?? '');
        $this->assertSame(3, $rules['doc']['form-params']['max-files'] ?? 0);
        $this->assertSame('.pdf', $rules['doc']['form-params']['accept'] ?? '');
        $this->assertSame(1024, $rules['doc']['form-params']['max-size'] ?? 0);
        $this->assertSame('uploads', $rules['doc']['form-params']['upload-dir'] ?? '');
        $this->assertSame('image', $rules['photo']['form-type'] ?? '');
        $this->assertSame('image/*', $rules['photo']['form-params']['accept'] ?? '');
    }

    public function testHasOneWhere(): void
    {
        $rule = new RuleBuilder();
        $rule->id('id')
            ->hasOne('child', RBChildModel::class, 'parent_id')
            ->where('active = ?', [1]);
        $rules = $rule->getRules();
        $where = $rules['id']['relationship']['where'] ?? [];

        $this->assertSame('active = ?', $where['condition'] ?? '');
        $this->assertSame(1, $where['params'][0] ?? null);
    }

    public function testHasMany(): void
    {
        $rule = new RuleBuilder();
        $rule->id('id')
            ->hasMany('children', RBChildModel::class, 'parent_id');
        $rules = $rule->getRules();
        $this->assertSame('hasMany', $rules['id']['relationship']['type'] ?? '');
    }

    public function testBelongsToWhere(): void
    {
        $rule = new RuleBuilder();
        $rule->int('parent_id')
            ->belongsTo('parent', RBParentModel::class, 'id')
            ->where('active = ?', [1]);
        $rules = $rule->getRules();
        $where = $rules['parent_id']['relationship']['where'] ?? [];

        $this->assertSame('active = ?', $where['condition'] ?? '');
        $this->assertSame(1, $where['params'][0] ?? null);
    }

    public function testWithCountWhereVirtualField(): void
    {
        $rule = new RuleBuilder();
        $rule->id('id')
            ->withCount('children_count', RBChildModel::class, 'parent_id')
            ->where('status = ?', ['active']);
        $rules = $rule->getRules();

        $this->assertArrayHasKey('children_count', $rules);
        $this->assertSame(true, $rules['children_count']['virtual'] ?? false);

        $where = $rules['id']['withCount'][0]['where'] ?? [];
        $this->assertSame('status = ?', $where['condition'] ?? '');
        $this->assertSame('active', $where['params'][0] ?? '');
    }

    public function testWhereWithoutRelationshipThrows(): void
    {
        $rule = new RuleBuilder();
        $this->expectException(\LogicException::class);
        $rule->where('id = ?', [1]);
    }

    public function testPropertiesCustomizeChangeType(): void
    {
        $rule = new RuleBuilder();
        $rule->string('code', 20)
            ->properties(['custom' => 'yes'])
            ->customize(function (array $data) {
                $data['customized'] = true;
                return $data;
            })
            ->changeType('code', 'text');
        $rules = $rule->getRules();

        $this->assertSame('yes', $rules['code']['custom'] ?? '');
        $this->assertSame(true, $rules['code']['customized'] ?? false);
        $this->assertSame('text', $rules['code']['type'] ?? '');
    }

    public function testSetRulesAndClear(): void
    {
        $rule = new RuleBuilder();
        $rule->setRules(['a' => ['type' => 'string']]);
        $this->assertArrayHasKey('a', $rule->getRules());
        $rule->clear();
        $this->assertCount(0, $rule->getRules());
    }

    public function testExtensions(): void
    {
        $rule = new RuleBuilder();
        $rule->extensions(['foo', 'bar']);
        $this->assertSame(['foo', 'bar'], $rule->getExtensions());
    }

    public function testModelBuildTableUsesRenameField(): void
    {
        $schema = Get::schema('test_rulebuilder_rename', $this->db);
        if ($schema->exists()) {
            $schema->drop();
        }
        $schema->id()->string('full_name', 150);
        $this->assertTrue($schema->create(), 'Failed to create baseline table');

        try {
            $model = new RBRenameModel();
            $this->assertTrue($model->buildTable(), 'buildTable failed for rename model');

            $columns = $this->getColumns('test_rulebuilder_rename');
            $this->assertContains('name', $columns);
            $this->assertNotContains('full_name', $columns);
        } finally {
            if ($schema->exists()) {
                $schema->drop();
            }
        }
    }

    private function getColumns(string $table): array
    {
        $type = $this->db->getType();
        if ($type === 'mysql') {
            $rows = $this->db->getResults('SHOW COLUMNS FROM ' . $this->db->qn($table));
            return array_map(fn($r) => $r->Field, $rows ?: []);
        }
        $rows = $this->db->getResults('PRAGMA table_info(' . $this->db->qn($table) . ')');
        return array_map(fn($r) => $r->name, $rows ?: []);
    }
}
}
