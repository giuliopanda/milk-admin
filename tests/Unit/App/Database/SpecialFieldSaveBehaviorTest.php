<?php
declare(strict_types=1);

namespace {
    if (!defined('MILK_TEST_CONTEXT')) {
        define('MILK_TEST_CONTEXT', true);
    }
    if (!defined('MILK_API_CONTEXT')) {
        define('MILK_API_CONTEXT', true);
    }

    $projectRoot = dirname(__DIR__, 4);
    require_once $projectRoot . '/public_html/milkadmin.php';
    require_once MILK_DIR . '/autoload.php';
}

namespace Tests\Unit\App\Database {
    use App\Abstracts\AbstractModel;
    use PHPUnit\Framework\TestCase;

    /**
     * @property int|null $record_id
     * @property string|null $title
     * @property string|null $status
     * @property int|string|null $qty
     * @property float|int|string|null $price
     * @property float|int|string|null $total
     * @property string|null $server_token
     * @property int|string|null $optional_count
     * @property \DateTimeInterface|string|null $optional_date
     * @property \DateTimeInterface|null $created_at
     * @property \DateTimeInterface|null $updated_at
     */
    final class SpecialFieldSaveBehaviorModel extends AbstractModel
    {
        public const TABLE = 'test_special_field_save_behavior';

        protected function configure($rule): void
        {
            $rule->table(self::TABLE)
                ->id('record_id')
                ->string('title', 100)->label('Title')
                ->string('status', 20)->label('Status')->default('draft')
                ->int('qty')->label('Qty')->nullable()
                ->decimal('price', 10, 2)->label('Price')->nullable()
                ->decimal('total', 10, 2)->label('Total')
                    ->calcExpr('[qty] * [price]')
                ->string('server_token', 20)->label('Server Token')
                    ->saveValue('SERVER')
                ->int('optional_count')->label('Optional Count')->nullable()
                ->date('optional_date')->label('Optional Date')->nullable()
                ->created_at('created_at')->default('2001-02-03 04:05:06')
                ->updated_at('updated_at');
        }
    }

    final class SpecialFieldSaveBehaviorTest extends TestCase
    {
        private SpecialFieldSaveBehaviorModel $model;

        protected function setUp(): void
        {
            $this->model = new SpecialFieldSaveBehaviorModel();
            $this->createTable();
        }

        protected function tearDown(): void
        {
            try {
                $this->model->dropTable();
            } catch (\Throwable) {
                // Ignore cleanup errors.
            }
        }

        public function testCalculatedFieldAndSaveValueAreAppliedOnInsert(): void
        {
            $record = new SpecialFieldSaveBehaviorModel();
            $record->title = 'Insert with calc and save value';
            $record->qty = 3;
            $record->price = 12.50;
            $record->total = 999.99;
            $record->server_token = 'CLIENT';

            $this->assertTrue($record->save(), $record->last_error);

            $reloaded = $this->model->getById($record->record_id);

            $this->assertNotNull($reloaded);
            $this->assertEquals(37.5, $reloaded->total);
            $this->assertSame('SERVER', $reloaded->server_token);
        }

        public function testGetEmptyAppliesDefaultsWithoutDirtyingNewRecordAndSavePersistsDefaults(): void
        {
            $empty = $this->model->getEmpty();

            $this->assertNull($empty->getRecordAction());
            $this->assertSame('draft', $empty->status);
            $this->assertInstanceOf(\DateTimeInterface::class, $empty->created_at);
            $this->assertSame('2001-02-03 04:05:06', $empty->created_at->format('Y-m-d H:i:s'));

            $this->assertTrue($empty->save(), $empty->last_error);
            $this->assertNotNull($empty->record_id);

            $reloaded = $this->model->getById($empty->record_id, false);
            $this->assertNotNull($reloaded);
            $this->assertSame('draft', $reloaded->status);
            $this->assertSame('2001-02-03 04:05:06', $reloaded->created_at->format('Y-m-d H:i:s'));
            $this->assertSame('SERVER', $reloaded->server_token);
        }

        public function testStaleCalculatedFieldLoadedFromDatabaseBecomesEditAndPersistsCorrection(): void
        {
            $insertId = $this->model->getDb()->insert(SpecialFieldSaveBehaviorModel::TABLE, [
                'title' => 'Stale calculated row',
                'status' => 'draft',
                'qty' => 4,
                'price' => 7.50,
                'total' => 1,
                'server_token' => 'MANUAL',
            ]);

            $this->assertNotFalse($insertId);

            $loaded = $this->model->getById($insertId);

            $this->assertNotNull($loaded);
            $this->assertSame('edit', $loaded->getRecordAction());
            $this->assertEquals(30.0, $loaded->total);

            $this->assertTrue($loaded->save(), $loaded->last_error);

            $reloaded = $this->model->getById($insertId, false);
            $this->assertNotNull($reloaded);
            $this->assertEquals(30.0, $reloaded->total);
            $this->assertSame('SERVER', $reloaded->server_token);
        }

        public function testCreatedAtDefaultIsUsedOnInsertAndPreservedOnUpdateWhileUpdatedAtChanges(): void
        {
            $record = new SpecialFieldSaveBehaviorModel();
            $record->title = 'Audit fields';
            $record->qty = 1;
            $record->price = 10;

            $this->assertTrue($record->save(), $record->last_error);

            $inserted = $this->model->getById($record->record_id);
            $this->assertNotNull($inserted);
            $this->assertInstanceOf(\DateTimeInterface::class, $inserted->created_at);
            $this->assertInstanceOf(\DateTimeInterface::class, $inserted->updated_at);
            $this->assertSame('2001-02-03 04:05:06', $inserted->created_at->format('Y-m-d H:i:s'));

            $originalCreatedAt = $inserted->created_at->format('Y-m-d H:i:s');
            $originalUpdatedAt = $inserted->updated_at->format('Y-m-d H:i:s');

            usleep(1100000);

            $inserted->title = 'Audit fields updated';
            $inserted->created_at = new \DateTime('2099-01-01 00:00:00');

            $this->assertTrue($inserted->save(), $inserted->last_error);

            $updated = $this->model->getById($record->record_id, false);
            $this->assertNotNull($updated);
            $this->assertSame($originalCreatedAt, $updated->created_at->format('Y-m-d H:i:s'));
            $this->assertNotSame($originalUpdatedAt, $updated->updated_at->format('Y-m-d H:i:s'));
        }

        public function testNullableFieldsConvertEmptyStringsToNullOnSave(): void
        {
            $record = new SpecialFieldSaveBehaviorModel();
            $record->title = 'Nullable fields';
            $record->qty = 2;
            $record->price = 5;
            $record->optional_count = '';
            $record->optional_date = '';

            $this->assertTrue($record->save(), $record->last_error);

            $reloaded = $this->model->getById($record->record_id);
            $this->assertNotNull($reloaded);
            $this->assertNull($reloaded->optional_count);
            $this->assertNull($reloaded->optional_date);
        }

        public function testSchemaDefaultAndModelSaveBothPersistDefaultForUntouchedInsertFields(): void
        {
            $directInsertId = $this->model->getDb()->insert(SpecialFieldSaveBehaviorModel::TABLE, [
                'title' => 'Direct insert default',
                'qty' => 2,
                'price' => 5,
                'total' => 10,
                'server_token' => 'MANUAL',
            ]);

            $this->assertNotFalse($directInsertId);

            $directLoaded = $this->model->getById($directInsertId);
            $this->assertNotNull($directLoaded);
            $this->assertSame('draft', $directLoaded->status);

            $modelSaved = new SpecialFieldSaveBehaviorModel();
            $modelSaved->title = 'Model save default';
            $modelSaved->qty = 2;
            $modelSaved->price = 5;

            $this->assertTrue($modelSaved->save(), $modelSaved->last_error);

            $savedLoaded = $this->model->getById($modelSaved->record_id, false);
            $this->assertNotNull($savedLoaded);
            $this->assertSame('draft', $savedLoaded->status);
        }

        public function testExistingUntouchedDefaultFieldIsNotForcedDuringEdit(): void
        {
            $insertId = $this->model->getDb()->insert(SpecialFieldSaveBehaviorModel::TABLE, [
                'title' => 'Existing null default',
                'status' => null,
                'qty' => 2,
                'price' => 5,
                'total' => 10,
                'server_token' => 'MANUAL',
            ]);

            $this->assertNotFalse($insertId);

            $loaded = $this->model->getById($insertId);
            $this->assertNotNull($loaded);
            $this->assertNull($loaded->status);
            $this->assertNull($loaded->getRecordAction());

            $loaded->title = 'Existing null default updated';

            $this->assertSame('edit', $loaded->getRecordAction());
            $this->assertTrue($loaded->save(), $loaded->last_error);

            $reloaded = $this->model->getById($insertId, false);
            $this->assertNotNull($reloaded);
            $this->assertNull($reloaded->status);
            $this->assertSame('Existing null default updated', $reloaded->title);
        }

        public function testExplicitNullIsPersistedForDirtyNullableField(): void
        {
            $record = new SpecialFieldSaveBehaviorModel();
            $record->title = 'Nullable null';
            $record->qty = 2;
            $record->price = 5;

            $this->assertTrue($record->save(), $record->last_error);

            $loaded = $this->model->getById($record->record_id, false);
            $this->assertNotNull($loaded);

            $loaded->optional_count = 10;
            $this->assertTrue($loaded->save(), $loaded->last_error);

            $loaded = $this->model->getById($record->record_id, false);
            $this->assertNotNull($loaded);

            $loaded->optional_count = null;
            $this->assertSame('edit', $loaded->getRecordAction());
            $this->assertTrue($loaded->save(), $loaded->last_error);

            $reloaded = $this->model->getById($record->record_id, false);
            $this->assertNotNull($reloaded);
            $this->assertNull($reloaded->optional_count);
        }

        public function testUntouchedNullableFieldIsIgnoredOnUpdate(): void
        {
            $record = new SpecialFieldSaveBehaviorModel();
            $record->title = 'Untouched nullable';
            $record->qty = 2;
            $record->price = 5;
            $record->optional_count = 9;

            $this->assertTrue($record->save(), $record->last_error);

            $loaded = $this->model->getById($record->record_id, false);
            $this->assertNotNull($loaded);
            $this->assertSame(9, $loaded->optional_count);

            $loaded->title = 'Untouched nullable updated';
            $this->assertTrue($loaded->save(), $loaded->last_error);

            $reloaded = $this->model->getById($record->record_id, false);
            $this->assertNotNull($reloaded);
            $this->assertSame(9, $reloaded->optional_count);
            $this->assertSame('Untouched nullable updated', $reloaded->title);
        }

        private function createTable(): void
        {
            try {
                $this->model->dropTable();
            } catch (\Throwable) {
                // Ignore cleanup errors on a fresh database.
            }

            $this->assertTrue($this->model->buildTable(), $this->model->last_error);
        }
    }
}
