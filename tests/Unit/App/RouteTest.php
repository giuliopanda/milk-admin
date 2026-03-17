<?php

declare(strict_types=1);

use App\Config;
use App\Hooks;
use App\Route;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalConfig = [];
    /** @var array<string, mixed> */
    private array $originalServer = [];
    /** @var array<string, mixed> */
    private array $originalPost = [];
    /** @var array<string, mixed> */
    private array $originalCookie = [];
    /** @var array<string, mixed> */
    private array $originalSession = [];
    /** @var array<string, mixed> */
    private array $originalHooks = [];
    /** @var array<string, mixed> */
    private array $originalFunctions = [];
    /** @var array<string, mixed> */
    private array $originalPermissions = [];
    private mixed $originalCachedData = null;
    private mixed $originalSessions = null;

    protected function setUp(): void
    {
        $this->originalConfig = Config::getAll();
        $this->originalServer = $_SERVER;
        $this->originalPost = $_POST;
        $this->originalCookie = $_COOKIE;
        $this->originalSession = $_SESSION ?? [];
        $this->originalHooks = $this->getPrivateStatic(Hooks::class, 'functions');
        $this->originalFunctions = $this->getPrivateStatic(Route::class, 'functions');
        $this->originalPermissions = $this->getPrivateStatic(Route::class, 'permissions');
        $this->originalCachedData = $this->getPrivateStatic(Route::class, 'cached_data');
        $this->originalSessions = $this->getPrivateStatic(Route::class, 'sessions');

        Config::setAll([]);
        $_SERVER = [];
        $_POST = [];
        $_COOKIE = [];
        $_SESSION = [];
        $this->setPrivateStatic(Hooks::class, 'functions', []);
        $this->setPrivateStatic(Route::class, 'functions', []);
        $this->setPrivateStatic(Route::class, 'permissions', []);
        $this->setPrivateStatic(Route::class, 'cached_data', null);
        $this->setPrivateStatic(Route::class, 'sessions', null);
    }

    protected function tearDown(): void
    {
        Config::setAll($this->originalConfig);
        $_SERVER = $this->originalServer;
        $_POST = $this->originalPost;
        $_COOKIE = $this->originalCookie;
        $_SESSION = $this->originalSession;
        $this->setPrivateStatic(Hooks::class, 'functions', $this->originalHooks);
        $this->setPrivateStatic(Route::class, 'functions', $this->originalFunctions);
        $this->setPrivateStatic(Route::class, 'permissions', $this->originalPermissions);
        $this->setPrivateStatic(Route::class, 'cached_data', $this->originalCachedData);
        $this->setPrivateStatic(Route::class, 'sessions', $this->originalSessions);
    }

    public function testSetRunAndPermissionMetadata(): void
    {
        $called = false;
        Route::set('unit_route', static function () use (&$called): void {
            $called = true;
        });
        Route::set('admin_route', static function (): void {
        }, 'auth.manage');

        $this->assertTrue(Route::run('unit_route'));
        $this->assertTrue($called);
        $this->assertFalse(Route::run('missing_route'));

        $this->assertSame('auth.manage', Route::getRoutePermission('admin_route'));
        $this->assertTrue(Route::hasPermissionRequirement('admin_route'));

        $routes = Route::getRoutesWithPermissions();
        $this->assertArrayHasKey('admin_route', $routes);
        $this->assertSame('auth.manage', $routes['admin_route']['permission']);
    }

    public function testUrlAndCurrentUrlUseBaseUrlAndQuery(): void
    {
        Config::setAll(['base_url' => 'https://example.test/admin']);
        $_SERVER['QUERY_STRING'] = 'page=users&id=5';

        $this->assertSame(
            'https://example.test/admin/?page=users&id=5',
            Route::url(['page' => 'users', 'id' => 5])
        );
        $this->assertSame('https://example.test/admin/?q=abc', Route::url('?q=abc'));
        $this->assertSame('https://example.test/admin/?page=users&id=5', Route::currentUrl());
    }

    public function testCompareAndParseHelpers(): void
    {
        $_SERVER['QUERY_STRING'] = 'page=home&action=edit&id=3';

        $this->assertSame(
            ['page' => 'home', 'action' => 'edit', 'id' => '3'],
            Route::parseQueryString('?page=home&action=edit&id=3')
        );

        $this->assertTrue(Route::compareQueryUrl('page=home&id=3'));
        $this->assertFalse(Route::compareQueryUrl('page=home&id=9'));

        $this->assertTrue(Route::comparePageUrl('page=home'));
        $this->assertFalse(Route::comparePageUrl('page=users'));
        $this->assertTrue(Route::comparePageUrl('?page=home&action=edit', [], true));
        $this->assertFalse(Route::comparePageUrl('?page=home', [], true));
    }

    public function testRequestSchemeAndCurrentUrlDetection(): void
    {
        $_SERVER = [
            'HTTP_HOST' => 'example.test',
            'REQUEST_URI' => '/panel/index.php?page=home',
            'REQUEST_SCHEME' => 'http',
        ];

        $this->assertSame('http', Route::getRequestScheme());
        $this->assertSame('http://example.test/panel/index.php?page=home', Route::getCurrentUrl());

        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertSame('https', Route::getRequestScheme());

        Config::set('request_scheme', 'https');
        $this->assertSame('https', Route::getRequestScheme());
    }

    public function testReplaceUrlPlaceholdersAndBase64Helpers(): void
    {
        $this->assertSame(
            '?page=view&id=12',
            Route::replaceUrlPlaceholders('?page=view&id=%id%&kind=[kind]', ['id' => 12])
        );
        $this->assertSame(
            'https://example.test/path?id=99#frag',
            Route::replaceUrlPlaceholders('https://example.test/path?id=%id%&x=%x%#frag', ['id' => 99])
        );

        $encoded = Route::urlsafeB64Encode('hello+/=');
        $this->assertSame('hello+/=', Route::urlsafeB64Decode($encoded));
        $this->assertSame('', Route::urlsafeB64Encode(''));
    }

    public function testGetBearerTokenAndCredentialExtraction(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token-abc';
        $this->assertSame('token-abc', Route::getBearerToken());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('john:secret');
        $credentials = Route::extractCredentials();
        $this->assertSame(['username' => 'john', 'password' => 'secret'], $credentials);

        unset($_SERVER['HTTP_AUTHORIZATION']);
        $_SERVER['PHP_AUTH_USER'] = 'mary';
        $_SERVER['PHP_AUTH_PW'] = 'pwd';
        $credentials = Route::extractCredentials();
        $this->assertSame(['username' => 'mary', 'password' => 'pwd'], $credentials);

        unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        $_POST['user'] = 'api-user';
        $_POST['pass'] = 'api-pass';
        $credentials = Route::extractCredentials('user', 'pass');
        $this->assertSame(['username' => 'api-user', 'password' => 'api-pass'], $credentials);
    }

    public function testHeaderDataAndSessionDataFlow(): void
    {
        $_COOKIE['X-Redirect-payload'] = base64_encode(json_encode(['a' => 1]));
        $_COOKIE['X-Redirect-message'] = base64_encode('ok');

        set_error_handler(static fn () => true);
        $headerData = Route::getHeaderData();
        restore_error_handler();

        $this->assertSame(['a' => 1], $headerData['payload']);
        $this->assertSame('ok', $headerData['message']);

        $_COOKIE['X-Redirect-payload'] = base64_encode(json_encode(['a' => 999]));
        $cached = Route::getHeaderData();
        $this->assertSame(['a' => 1], $cached['payload']);

        $this->setPrivateStatic(Route::class, 'cached_data', []);
        $_SESSION['redirect_data'] = ['notice' => 'saved'];
        $_POST = ['name' => 'Alice'];

        $sessionData = Route::getSessionData(true);
        $this->assertSame(['notice' => 'saved', 'name' => 'Alice'], $sessionData);
        $this->assertArrayNotHasKey('redirect_data', $_SESSION);
    }

    /**
     * @return mixed
     */
    private function getPrivateStatic(string $class, string $property)
    {
        $reflection = new ReflectionClass($class);
        $prop = $reflection->getProperty($property);

        return $prop->getValue();
    }

    private function setPrivateStatic(string $class, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($class);
        $prop = $reflection->getProperty($property);
        $prop->setValue(null, $value);
    }
}
