<?php

declare(strict_types=1);

use App\Config;
use App\CSRFProtection;
use App\Logs;
use App\MessagesHandler;
use App\Token;
use PHPUnit\Framework\TestCase;

final class CSRFProtectionTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalServer = [];
    /** @var array<string, mixed> */
    private array $originalPost = [];
    /** @var array<string, mixed> */
    private array $originalGet = [];
    /** @var array<string, mixed> */
    private array $originalRequest = [];
    /** @var array<string, mixed> */
    private array $originalFiles = [];
    /** @var array<string, mixed> */
    private array $originalCookie = [];
    /** @var array<string> */
    private array $originalExemptRoutes = [];
    /** @var array<string, mixed> */
    private array $originalConfig = [];

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->originalPost = $_POST;
        $this->originalGet = $_GET;
        $this->originalRequest = $_REQUEST;
        $this->originalFiles = $_FILES;
        $this->originalCookie = $_COOKIE;
        $this->originalExemptRoutes = $this->getExemptRoutes();
        $this->originalConfig = Config::getAll();

        $_SERVER = [];
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
        $_FILES = [];
        $_COOKIE = [];

        $this->setExemptRoutes([]);
        Config::setAll([]);
        MessagesHandler::reset();
        $this->setMessagesSuccess([]);
        Logs::clear();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_POST = $this->originalPost;
        $_GET = $this->originalGet;
        $_REQUEST = $this->originalRequest;
        $_FILES = $this->originalFiles;
        $_COOKIE = $this->originalCookie;

        $this->setExemptRoutes($this->originalExemptRoutes);
        Config::setAll($this->originalConfig);
        MessagesHandler::reset();
        $this->setMessagesSuccess([]);
        Logs::clear();
    }

    public function testAddExemptRouteAvoidsDuplicates(): void
    {
        CSRFProtection::addExemptRoute('/api/*');
        CSRFProtection::addExemptRoute('/api/*');
        CSRFProtection::addExemptRoute('/health');

        $routes = CSRFProtection::getExemptRoutes();
        $this->assertSame(['/api/*', '/health'], array_values($routes));
    }

    public function testExtractTokenPrefersHeaderAndFallsBackToPost(): void
    {
        $tokenName = Token::getTokenName(session_id());
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'header-token';
        $_POST[$tokenName] = 'post-token';

        $this->assertSame('header-token', CSRFProtection::extractToken());

        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $this->assertSame('post-token', CSRFProtection::extractToken());

        $_POST = [];
        $this->assertNull(CSRFProtection::extractToken());
    }

    public function testIsExemptRouteHandlesExactAndWildcardPatterns(): void
    {
        $this->setExemptRoutes(['/admin/save', '/api/*']);

        $_SERVER['REQUEST_URI'] = '/admin/save';
        $this->assertTrue($this->invokePrivateStatic('isExemptRoute'));

        $_SERVER['REQUEST_URI'] = '/api/users/1';
        $this->assertTrue($this->invokePrivateStatic('isExemptRoute'));

        $_SERVER['REQUEST_URI'] = '/secure/page';
        $this->assertFalse($this->invokePrivateStatic('isExemptRoute'));
    }

    public function testIsAjaxRequestDetectsDifferentAjaxSignals(): void
    {
        $_SERVER = ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
        $this->assertTrue($this->invokePrivateStatic('isAjaxRequest'));

        $_SERVER = ['CONTENT_TYPE' => 'application/json; charset=utf-8'];
        $this->assertTrue($this->invokePrivateStatic('isAjaxRequest'));

        $_SERVER = ['HTTP_X_CSRF_TOKEN' => 'token'];
        $this->assertTrue($this->invokePrivateStatic('isAjaxRequest'));

        $_SERVER = ['HTTP_ACCEPT' => 'application/json'];
        $this->assertTrue($this->invokePrivateStatic('isAjaxRequest'));

        $_SERVER = ['HTTP_ACCEPT' => 'application/json,text/html'];
        $this->assertFalse($this->invokePrivateStatic('isAjaxRequest'));
    }

    public function testHandleFormSubmissionClearsPayloadAndAddsErrorAndLog(): void
    {
        Config::set('debug', true);

        $tmpFile = tempnam(sys_get_temp_dir(), 'csrf_');
        $this->assertNotFalse($tmpFile);
        file_put_contents((string) $tmpFile, 'x');

        $_GET = ['page' => 'list'];
        $_POST = ['name' => 'Alice'];
        $_FILES = [
            'upload' => [
                'name' => 'a.txt',
                'type' => 'text/plain',
                'tmp_name' => (string) $tmpFile,
                'error' => 0,
                'size' => 1,
            ],
        ];
        $_REQUEST = [
            'page' => 'users',
            'action' => 'edit',
            'page-output' => 'json',
            'data' => ['id' => 12],
            'name' => 'Alice',
        ];
        $_COOKIE[session_name()] = 'abc-session';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->invokePrivateStatic('handleFormSubmission', ['invalid_token', 'token-value']);

        $this->assertSame([], $_POST);
        $this->assertSame([], $_FILES);
        $this->assertFileDoesNotExist((string) $tmpFile);

        $this->assertTrue(MessagesHandler::hasErrors());
        $errorText = MessagesHandler::errorsToString();
        $this->assertStringContainsString('Security token expired during file upload', $errorText);
        $this->assertStringContainsString('csrf_reason=invalid_token', $errorText);

        $this->assertSame(1, Logs::count('SECURITY', Logs::WARNING));
        $this->assertSame(
            ['page' => 'users', 'action' => 'edit', 'page-output' => 'json', 'id' => 12],
            $_REQUEST
        );
    }

    public function testValidateReturnsEarlyWhenMethodIsNotPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = ['name' => 'Alice'];

        CSRFProtection::validate();

        $this->assertSame(['name' => 'Alice'], $_POST);
        $this->assertFalse(MessagesHandler::hasErrors());
        $this->assertSame(0, Logs::count('SECURITY'));
    }

    public function testValidateSkipsValidationForExemptRoutes(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/public/upload';
        $_POST = ['name' => 'Alice'];
        $_REQUEST = ['name' => 'Alice'];

        CSRFProtection::addExemptRoute('/public/*');
        CSRFProtection::validate();

        $this->assertSame(['name' => 'Alice'], $_POST);
        $this->assertFalse(MessagesHandler::hasErrors());
        $this->assertSame(0, Logs::count('SECURITY'));
    }

    /**
     * @param array<int, mixed> $args
     * @return mixed
     */
    private function invokePrivateStatic(string $method, array $args = [])
    {
        $reflection = new ReflectionClass(CSRFProtection::class);
        $m = $reflection->getMethod($method);

        return $m->invokeArgs(null, $args);
    }

    /**
     * @return array<string>
     */
    private function getExemptRoutes(): array
    {
        $reflection = new ReflectionClass(CSRFProtection::class);
        $prop = $reflection->getProperty('exempt_routes');

        /** @var array<string> $routes */
        $routes = $prop->getValue();
        return $routes;
    }

    /**
     * @param array<string> $routes
     */
    private function setExemptRoutes(array $routes): void
    {
        $reflection = new ReflectionClass(CSRFProtection::class);
        $prop = $reflection->getProperty('exempt_routes');
        $prop->setValue(null, $routes);
    }

    /**
     * @param array<int, string> $messages
     */
    private function setMessagesSuccess(array $messages): void
    {
        $reflection = new ReflectionClass(MessagesHandler::class);
        $prop = $reflection->getProperty('success_messages');
        $prop->setValue(null, $messages);
    }
}
