<?php

declare(strict_types=1);

use App\MessagesHandler;
use App\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalRequest = [];
    /** @var array<string, mixed> */
    private array $originalServer = [];
    private bool $originalCaptureEnabled = false;
    private string $originalCapturedOutput = '';
    private string $originalCapturedType = 'html';
    /** @var array<string, mixed> */
    private array $originalErrorMessages = [];
    /** @var array<int, string> */
    private array $originalSuccessMessages = [];
    /** @var array<int, string> */
    private array $originalInvalidFields = [];

    protected function setUp(): void
    {
        $this->originalRequest = $_REQUEST;
        $this->originalServer = $_SERVER;
        $this->originalCaptureEnabled = $this->getPrivateStatic('capture_enabled');
        $this->originalCapturedOutput = $this->getPrivateStatic('captured_output');
        $this->originalCapturedType = $this->getPrivateStatic('captured_type');
        $this->originalErrorMessages = $this->getMessagesPrivate('error_messages');
        $this->originalSuccessMessages = $this->getMessagesPrivate('success_messages');
        $this->originalInvalidFields = $this->getMessagesPrivate('invalid_fields');

        $_REQUEST = [];
        $_SERVER = [];
        $this->setPrivateStatic('capture_enabled', false);
        $this->setPrivateStatic('captured_output', '');
        $this->setPrivateStatic('captured_type', 'html');
        $this->setMessagesPrivate('error_messages', []);
        $this->setMessagesPrivate('success_messages', []);
        $this->setMessagesPrivate('invalid_fields', []);
    }

    protected function tearDown(): void
    {
        $_REQUEST = $this->originalRequest;
        $_SERVER = $this->originalServer;
        $this->setPrivateStatic('capture_enabled', $this->originalCaptureEnabled);
        $this->setPrivateStatic('captured_output', $this->originalCapturedOutput);
        $this->setPrivateStatic('captured_type', $this->originalCapturedType);
        $this->setMessagesPrivate('error_messages', $this->originalErrorMessages);
        $this->setMessagesPrivate('success_messages', $this->originalSuccessMessages);
        $this->setMessagesPrivate('invalid_fields', $this->originalInvalidFields);
    }

    public function testCaptureModeStoresJsonOutput(): void
    {
        Response::beginCapture();
        Response::json(['success' => true, 'msg' => 'ok']);

        $this->assertSame('json', Response::getCapturedType());
        $payload = json_decode(Response::getCapturedOutput(), true);
        $this->assertSame(['success' => true, 'msg' => 'ok'], $payload);

        Response::endCapture();
    }

    public function testHtmlJsonBuildsDefaultsFromMessagesHandler(): void
    {
        MessagesHandler::addError('Validation failed');

        Response::beginCapture();
        Response::htmlJson([]);
        $payload = json_decode(Response::getCapturedOutput(), true);

        $this->assertFalse($payload['success']);
        $this->assertStringContainsString('Validation failed', $payload['msg']);
        $this->assertSame('', $payload['html']);

        Response::endCapture();
    }

    public function testCsvCaptureProducesCsvPayload(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        Response::beginCapture();
        Response::csv($rows, 'users');

        $this->assertSame('csv', Response::getCapturedType());
        $output = Response::getCapturedOutput();
        $this->assertStringContainsString("id,name", $output);
        $this->assertStringContainsString('1,Alice', $output);
        $this->assertStringContainsString('2,Bob', $output);

        Response::endCapture();
    }

    public function testIsJsonFromRequestOrAcceptHeader(): void
    {
        $_REQUEST['page-output'] = 'json';
        $this->assertTrue(Response::isJson());

        $_REQUEST = [];
        $_SERVER['HTTP_ACCEPT'] = 'application/json, text/plain';
        $this->assertTrue(Response::isJson());

        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $this->assertFalse(Response::isJson());
    }

    public function testRenderUsesJsonPathWhenJsonRequested(): void
    {
        $_REQUEST['page-output'] = 'json';

        Response::beginCapture();
        Response::render('<h1>Hello</h1>', ['html' => '<h1>Hello</h1>', 'success' => true, 'msg' => 'ok']);
        $payload = json_decode(Response::getCapturedOutput(), true);

        $this->assertSame('json', Response::getCapturedType());
        $this->assertSame('<h1>Hello</h1>', $payload['html']);
        $this->assertTrue($payload['success']);
        $this->assertSame('ok', $payload['msg']);

        Response::endCapture();
    }

    /**
     * @return mixed
     */
    private function getPrivateStatic(string $property)
    {
        $reflection = new ReflectionClass(Response::class);
        $prop = $reflection->getProperty($property);

        return $prop->getValue();
    }

    private function setPrivateStatic(string $property, mixed $value): void
    {
        $reflection = new ReflectionClass(Response::class);
        $prop = $reflection->getProperty($property);
        $prop->setValue(null, $value);
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
