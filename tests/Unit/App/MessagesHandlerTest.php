<?php

declare(strict_types=1);

use App\MessagesHandler;
use PHPUnit\Framework\TestCase;

final class MessagesHandlerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalErrors = [];
    /** @var array<int, string> */
    private array $originalSuccess = [];
    /** @var array<int, string> */
    private array $originalInvalid = [];
    /** @var array<string, mixed> */
    private array $originalRequest = [];

    protected function setUp(): void
    {
        $this->originalErrors = $this->getPrivateStatic('error_messages');
        $this->originalSuccess = $this->getPrivateStatic('success_messages');
        $this->originalInvalid = $this->getPrivateStatic('invalid_fields');
        $this->originalRequest = $_REQUEST;

        $this->setPrivateStatic('error_messages', []);
        $this->setPrivateStatic('success_messages', []);
        $this->setPrivateStatic('invalid_fields', []);
        $_REQUEST = [];
    }

    protected function tearDown(): void
    {
        $this->setPrivateStatic('error_messages', $this->originalErrors);
        $this->setPrivateStatic('success_messages', $this->originalSuccess);
        $this->setPrivateStatic('invalid_fields', $this->originalInvalid);
        $_REQUEST = $this->originalRequest;
    }

    public function testAddErrorSupportsGeneralFieldAndMultipleFields(): void
    {
        MessagesHandler::addError('General error');
        MessagesHandler::addError('Email invalid', 'email');
        MessagesHandler::addError('Passwords mismatch', ['password', 'confirm_password']);

        $errors = MessagesHandler::getErrors();
        $this->assertCount(3, $errors);
        $this->assertSame('General error', $errors[0]);
        $this->assertSame('Email invalid', $errors['email']);
        $this->assertSame('Passwords mismatch', $errors['password|confirm_password']);
        $this->assertTrue(MessagesHandler::hasErrors());
    }

    public function testAddErrorIgnoresEmptyMessage(): void
    {
        MessagesHandler::addError('');
        $this->assertSame([], MessagesHandler::getErrors());
    }

    public function testAddFieldErrorMarksFieldOnlyOnce(): void
    {
        MessagesHandler::addFieldError('username');
        MessagesHandler::addFieldError('username');

        $this->assertSame('is-invalid js-focus-remove-is-invalid', MessagesHandler::getInvalidClass('username'));
    }

    public function testGetErrorsAndAlertCanExcludeFieldSpecificEntries(): void
    {
        MessagesHandler::addError('General error');
        MessagesHandler::addError('Email invalid', 'email');

        $general = MessagesHandler::getErrors(true);
        $this->assertCount(1, $general);
        $this->assertSame('General error', array_values($general)[0]);

        $alert = MessagesHandler::getErrorAlert(true);
        $this->assertStringContainsString('General error', $alert);
        $this->assertStringNotContainsString('Email invalid', $alert);
    }

    public function testSuccessMessagesApisAndAlert(): void
    {
        MessagesHandler::addSuccess('Saved');
        MessagesHandler::addSuccess('Sent');

        $this->assertTrue(MessagesHandler::hasSuccess());
        $this->assertSame(['Saved', 'Sent'], MessagesHandler::getSuccesses());
        $this->assertSame(['Saved', 'Sent'], MessagesHandler::getSuccessMessages());

        $alert = MessagesHandler::getSuccessAlert();
        $this->assertStringContainsString('Saved', $alert);
        $this->assertStringContainsString('Sent', $alert);
    }

    public function testErrorsToStringAndSuccessToStringApplyUniquenessAndSeparator(): void
    {
        MessagesHandler::addError('E1');
        MessagesHandler::addError('E1');
        MessagesHandler::addError('E2');

        MessagesHandler::addSuccess('S1');
        MessagesHandler::addSuccess('S1');
        MessagesHandler::addSuccess('S2');

        $this->assertSame("E1\nE2", MessagesHandler::errorsToString());
        $this->assertSame('E1<br>E2', MessagesHandler::errorsToString(true));
        $this->assertSame("S1\nS2", MessagesHandler::successToString());
        $this->assertSame('S1<br>S2', MessagesHandler::successToString(true));
    }

    public function testDisplayMessagesOutputsCombinedAlerts(): void
    {
        MessagesHandler::addError('Error text');
        MessagesHandler::addSuccess('Success text');

        ob_start();
        MessagesHandler::displayMessages();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('alert-danger', $html);
        $this->assertStringContainsString('alert-success', $html);
        $this->assertStringContainsString('Error text', $html);
        $this->assertStringContainsString('Success text', $html);
    }

    public function testResetClearsErrorsAndInvalidFields(): void
    {
        MessagesHandler::addError('Error', 'email');
        MessagesHandler::addFieldError('password');
        MessagesHandler::addSuccess('Success');

        MessagesHandler::reset();

        $this->assertFalse(MessagesHandler::hasErrors());
        $this->assertSame('', MessagesHandler::getInvalidClass('email'));
        $this->assertTrue(MessagesHandler::hasSuccess());
        $this->assertSame(['Success'], MessagesHandler::getSuccesses());
    }

    /**
     * @return mixed
     */
    private function getPrivateStatic(string $property)
    {
        $reflection = new ReflectionClass(MessagesHandler::class);
        $prop = $reflection->getProperty($property);

        return $prop->getValue();
    }

    private function setPrivateStatic(string $property, mixed $value): void
    {
        $reflection = new ReflectionClass(MessagesHandler::class);
        $prop = $reflection->getProperty($property);
        $prop->setValue(null, $value);
    }
}
