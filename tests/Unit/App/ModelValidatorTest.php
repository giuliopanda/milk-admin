<?php

declare(strict_types=1);

use App\MessagesHandler;
use App\ModelValidator;
use PHPUnit\Framework\TestCase;

final class ModelValidatorTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalErrors = [];
    /** @var array<int, string> */
    private array $originalSuccess = [];
    /** @var array<int, string> */
    private array $originalInvalid = [];

    protected function setUp(): void
    {
        $this->originalErrors = $this->getMessagesPrivate('error_messages');
        $this->originalSuccess = $this->getMessagesPrivate('success_messages');
        $this->originalInvalid = $this->getMessagesPrivate('invalid_fields');

        $this->setMessagesPrivate('error_messages', []);
        $this->setMessagesPrivate('success_messages', []);
        $this->setMessagesPrivate('invalid_fields', []);
    }

    protected function tearDown(): void
    {
        $this->setMessagesPrivate('error_messages', $this->originalErrors);
        $this->setMessagesPrivate('success_messages', $this->originalSuccess);
        $this->setMessagesPrivate('invalid_fields', $this->originalInvalid);
    }

    public function testValidateReturnsTrueForValidRecord(): void
    {
        $validator = new ModelValidator([
            'name' => [
                'type' => 'string',
                'label' => 'Name',
                'form-params' => ['required' => true, 'minlength' => 3],
            ],
            'email' => [
                'type' => 'email',
                'label' => 'Email',
                'form-params' => ['required' => true],
            ],
            'age' => [
                'type' => 'int',
                'label' => 'Age',
                'primary' => false,
                'form-params' => ['min' => 18, 'max' => 65],
            ],
        ]);

        $valid = $validator->validate([
            'name' => 'Alice',
            'email' => 'alice@example.test',
            'age' => 30,
        ]);

        $this->assertTrue($valid);
        $this->assertFalse(MessagesHandler::hasErrors());
    }

    public function testValidateReturnsFalseAndCollectsErrorsForInvalidRecord(): void
    {
        $validator = new ModelValidator([
            'name' => [
                'type' => 'string',
                'label' => 'Name',
                'form-params' => ['required' => true, 'minlength' => 3],
            ],
            'email' => [
                'type' => 'email',
                'label' => 'Email',
                'form-params' => ['required' => true],
            ],
            'age' => [
                'type' => 'int',
                'label' => 'Age',
                'primary' => false,
                'form-params' => ['min' => 18],
            ],
        ]);

        $valid = $validator->validate([
            'name' => 'Al',
            'email' => 'not-an-email',
            'age' => 15,
        ]);

        $this->assertFalse($valid);
        $this->assertTrue(MessagesHandler::hasErrors());

        $errors = MessagesHandler::getErrors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('age', $errors);
    }

    public function testValidateExpressionCanRejectField(): void
    {
        $validator = new ModelValidator([
            'score' => [
                'type' => 'int',
                'label' => 'Score',
                'primary' => false,
                'validate_expr' => '[score] >= 10',
            ],
        ]);

        $this->assertFalse($validator->validate(['score' => 5]));
        $this->assertArrayHasKey('score', MessagesHandler::getErrors());
    }

    public function testValidateRecordsStopsAsFalseWhenAnyRecordFails(): void
    {
        $validator = new ModelValidator([
            'email' => ['type' => 'email', 'label' => 'Email'],
        ]);

        $valid = $validator->validateRecords([
            ['email' => 'ok@example.test'],
            ['email' => 'bad-email'],
        ]);

        $this->assertFalse($valid);
        $this->assertTrue(MessagesHandler::hasErrors());
    }

    /**
     * @return mixed
     */
    private function getMessagesPrivate(string $property)
    {
        $reflection = new ReflectionClass(MessagesHandler::class);
        $prop = $reflection->getProperty($property);

        return $prop->getValue();
    }

    private function setMessagesPrivate(string $property, mixed $value): void
    {
        $reflection = new ReflectionClass(MessagesHandler::class);
        $prop = $reflection->getProperty($property);
        $prop->setValue(null, $value);
    }
}
