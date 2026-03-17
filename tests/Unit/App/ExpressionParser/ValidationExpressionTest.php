<?php
/**
 * Run quickly from shell:
 * php vendor/bin/phpunit tests/Unit/App/ExpressionParser/ValidationExpressionTest.php
 */

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

namespace Tests\Unit\App\ExpressionParser {
    use App\Abstracts\AbstractModel;
    use App\MessagesHandler;
    use PHPUnit\Framework\TestCase;

    class ExprValidationStressModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_validation_stress')
                ->db('array')
                ->id('ID')
                ->int('START')->label('Start')->required()
                    ->validateExpr('[START] >= 0', 'Start must be >= 0')
                ->int('END')->label('End')
                    ->validateExpr('[END] >= [START]', 'End must be >= Start')
                ->int('SCORE')->label('Score')
                    ->validateExpr('([SCORE] >= 0) AND ([SCORE] <= 100)', 'Score out of range')
                ->string('CODE', 10)->label('Code')
                    ->validateExpr('[CODE] == "OK"', 'Code must be OK');
        }
    }

    class ExprValidationDateModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_validation_dates')
                ->db('array')
                ->id('ID')
                ->date('START_DATE')->label('Start Date')
                    ->validateExpr('[START_DATE] <= [END_DATE]', 'Start Date must be <= End Date')
                ->date('END_DATE')->label('End Date');
        }
    }

    class ExprValidationInvalidExprModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_validation_invalid_expr')
                ->db('array')
                ->id('ID')
                ->string('FIELD', 20)->label('Field')
                    ->validateExpr('[MISSING] > 0');
        }
    }

    class ExprValidationStringMessageModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_validation_string_message')
                ->db('array')
                ->id('ID')
                ->string('NOTE', 20)->label('Note')
                    ->validateExpr('"Dynamic error"');
        }
    }

    class ExprValidationRequireIfModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_validation_require_if')
                ->db('array')
                ->id('ID')
                ->string('TYPE', 20)->label('Type')
                ->string('NOTE', 20)->label('Note')
                    ->requireIf('[TYPE] == "special"');
        }
    }

    class ExprValidationIfModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_validation_if')
                ->db('array')
                ->id('ID')
                ->string('MODE', 10)->label('Mode')
                ->int('START')->label('Start')
                ->int('END')->label('End')
                    ->validateExpr('IF [MODE] == "strict" THEN [END] >= [START] ELSE [END] >= 0 ENDIF', 'IF validation failed');
        }
    }

    class ExprValidationTimeModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_validation_time')
                ->db('array')
                ->id('ID')
                ->time('START_TIME')->label('Start Time')
                ->time('END_TIME')->label('End Time')
                    ->validateExpr('[START_TIME] <= [END_TIME]', 'Start Time must be <= End Time');
        }
    }

    class ExprValidationDateTimeModel extends AbstractModel
    {
        protected function configure($rule): void
        {
            $rule->table('expr_validation_datetime')
                ->db('array')
                ->id('ID')
                ->datetime('START_AT')->label('Start At')
                ->datetime('END_AT')->label('End At')
                    ->validateExpr('[START_AT] <= [END_AT]', 'Start At must be <= End At');
        }
    }

    final class ValidationExpressionTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            MessagesHandler::reset();
        }

        private function assertFieldError(string $field, string $expected): void
        {
            $errors = MessagesHandler::getErrors();
            $this->assertArrayHasKey($field, $errors);
            $this->assertSame($expected, $errors[$field]);
        }

        public function testStressValidationsOnMultipleRecords(): void
        {
            $model = new ExprValidationStressModel();

            for ($i = 0; $i < 150; $i++) {
                $model->fill([
                    'START' => $i,
                    'END' => $i + 1,
                    'SCORE' => $i % 100,
                    'CODE' => 'OK'
                ]);
            }

            MessagesHandler::reset();
            $this->assertTrue($model->validate(true));
            $this->assertFalse(MessagesHandler::hasErrors());
        }

        public function testStressDetectsInvalidRecordAmongMany(): void
        {
            $model = new ExprValidationStressModel();

            for ($i = 0; $i < 50; $i++) {
                $model->fill([
                    'START' => $i,
                    'END' => $i + 2,
                    'SCORE' => 50,
                    'CODE' => 'OK'
                ]);
            }

            $model->fill([
                'START' => 10,
                'END' => 5,
                'SCORE' => 50,
                'CODE' => 'OK'
            ]);

            MessagesHandler::reset();
            $this->assertFalse($model->validate(true));
            $this->assertFieldError('END', 'End must be >= Start');
        }

        public function testDateExpressionValidation(): void
        {
            $model = new ExprValidationDateModel();
            $model->fill([
                'START_DATE' => '2025-01-01',
                'END_DATE' => '2025-01-10'
            ]);

            MessagesHandler::reset();
            $this->assertTrue($model->validate());
            $this->assertFalse(MessagesHandler::hasErrors());

            MessagesHandler::reset();
            $model = new ExprValidationDateModel();
            $model->fill([
                'START_DATE' => '2025-02-01',
                'END_DATE' => '2025-01-10'
            ]);
            $this->assertFalse($model->validate());
            $this->assertFieldError('START_DATE', 'Start Date must be <= End Date');
        }

        public function testInvalidExpressionDefaultsToGenericMessage(): void
        {
            $model = new ExprValidationInvalidExprModel();
            $model->fill([
                'FIELD' => 'test'
            ]);

            MessagesHandler::reset();
            $this->assertFalse($model->validate());
            $this->assertFieldError('FIELD', 'The field <b>Field</b> is invalid');
        }

        public function testExpressionReturningStringUsesItAsMessage(): void
        {
            $model = new ExprValidationStringMessageModel();
            $model->fill([
                'NOTE' => 'ok'
            ]);

            MessagesHandler::reset();
            $this->assertFalse($model->validate());
            $this->assertFieldError('NOTE', 'Dynamic error');
        }

        public function testRequireIfExpression(): void
        {
            $model = new ExprValidationRequireIfModel();
            $model->fill([
                'TYPE' => 'special'
            ]);

            MessagesHandler::reset();
            $this->assertFalse($model->validate());
            $this->assertFieldError('NOTE', 'the field <b>Note</b> is required');

            MessagesHandler::reset();
            $model = new ExprValidationRequireIfModel();
            $model->fill([
                'TYPE' => 'normal'
            ]);
            $this->assertTrue($model->validate());
            $this->assertFalse(MessagesHandler::hasErrors());
        }

        public function testIfExpressionValidation(): void
        {
            $model = new ExprValidationIfModel();
            $model->fill([
                'MODE' => 'strict',
                'START' => 10,
                'END' => 5
            ]);

            MessagesHandler::reset();
            $this->assertFalse($model->validate());
            $this->assertFieldError('END', 'IF validation failed');

            MessagesHandler::reset();
            $model = new ExprValidationIfModel();
            $model->fill([
                'MODE' => 'loose',
                'START' => 10,
                'END' => -1
            ]);
            $this->assertFalse($model->validate());
            $this->assertFieldError('END', 'IF validation failed');

            MessagesHandler::reset();
            $model = new ExprValidationIfModel();
            $model->fill([
                'MODE' => 'strict',
                'START' => 10,
                'END' => 15
            ]);
            $this->assertTrue($model->validate());
            $this->assertFalse(MessagesHandler::hasErrors());
        }

        public function testTimeExpressionValidation(): void
        {
            $model = new ExprValidationTimeModel();
            $model->fill([
                'START_TIME' => '09:00',
                'END_TIME' => '17:30'
            ]);

            MessagesHandler::reset();
            $this->assertTrue($model->validate());
            $this->assertFalse(MessagesHandler::hasErrors());

            MessagesHandler::reset();
            $model = new ExprValidationTimeModel();
            $model->fill([
                'START_TIME' => '18:00',
                'END_TIME' => '09:00'
            ]);
            $this->assertFalse($model->validate());
            $this->assertFieldError('END_TIME', 'Start Time must be <= End Time');
        }

        public function testDateTimeExpressionValidation(): void
        {
            $model = new ExprValidationDateTimeModel();
            $model->fill([
                'START_AT' => '2025-01-01 09:00:00',
                'END_AT' => '2025-01-01 10:00:00'
            ]);

            MessagesHandler::reset();
            $this->assertTrue($model->validate());
            $this->assertFalse(MessagesHandler::hasErrors());

            MessagesHandler::reset();
            $model = new ExprValidationDateTimeModel();
            $model->fill([
                'START_AT' => '2025-01-02 10:00:00',
                'END_AT' => '2025-01-02 09:00:00'
            ]);
            $this->assertFalse($model->validate());
            $this->assertFieldError('END_AT', 'Start At must be <= End At');
        }
    }
}
