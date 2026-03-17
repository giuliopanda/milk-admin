<?php

declare(strict_types=1);

namespace App {
    final class RequestPhpInputStub
    {
        public static ?string $raw = null;
        public static int $calls = 0;
    }

    if (!function_exists(__NAMESPACE__ . '\file_get_contents')) {
        /**
         * Test seam for Request::json() reads from php://input.
         *
         * @param resource|null $context
         */
        function file_get_contents(
            string $filename,
            bool $use_include_path = false,
            $context = null,
            int $offset = 0,
            ?int $length = null
        ): string|false {
            if ($filename === 'php://input' && RequestPhpInputStub::$raw !== null) {
                RequestPhpInputStub::$calls++;
                return RequestPhpInputStub::$raw;
            }

            if ($length === null) {
                return \file_get_contents($filename, $use_include_path, $context, $offset);
            }

            return \file_get_contents($filename, $use_include_path, $context, $offset, $length);
        }
    }
}

namespace {
    use App\Request;
    use App\RequestPhpInputStub;
    use PHPUnit\Framework\TestCase;

    final class RequestTestProxy extends Request
    {
        public function callIsEmptyString(mixed $value): bool
        {
            return $this->isEmptyString($value);
        }

        /**
         * @param array<string, mixed> $data
         */
        public function callDataGet(array $data, string $key, mixed $default = null): mixed
        {
            return $this->dataGet($data, $key, $default);
        }

        /**
         * @param array<string, mixed> $data
         */
        public function callDataSet(array &$data, string $key, mixed $value): void
        {
            $this->dataSet($data, $key, $value);
        }

        /**
         * @param array<string, mixed> $data
         */
        public function callDataForget(array &$data, string $key): void
        {
            $this->dataForget($data, $key);
        }
    }

    final class RequestJsonAwareFake extends Request
    {
        /** @var array<string, mixed> */
        private array $payload = [];
        private bool $asJson = false;

        /**
         * @param array<string, mixed> $payload
         */
        public function withJsonPayload(array $payload, bool $asJson): self
        {
            $this->payload = $payload;
            $this->asJson = $asJson;
            return $this;
        }

        public function isJson(): bool
        {
            return $this->asJson;
        }

        /**
         * @return array<string, mixed>
         */
        public function json(): array
        {
            return $this->payload;
        }
    }

    final class RequestScalarInputFake extends Request
    {
        public function input(?string $key = null, mixed $default = null): mixed
        {
            if ($key === null) {
                return 'not-an-array';
            }

            return $default;
        }
    }

    final class RequestCaptureSubclass extends Request
    {
    }

    final class RequestTest extends TestCase
    {
        /** @var array<string, mixed> */
        private array $originalGet;
        /** @var array<string, mixed> */
        private array $originalPost;
        /** @var array<string, mixed> */
        private array $originalFiles;
        /** @var array<string, mixed> */
        private array $originalServer;
        /** @var array<string, mixed> */
        private array $originalCookie;

        protected function setUp(): void
        {
            $this->originalGet = $_GET;
            $this->originalPost = $_POST;
            $this->originalFiles = $_FILES;
            $this->originalServer = $_SERVER;
            $this->originalCookie = $_COOKIE;

            RequestPhpInputStub::$raw = null;
            RequestPhpInputStub::$calls = 0;
        }

        protected function tearDown(): void
        {
            $_GET = $this->originalGet;
            $_POST = $this->originalPost;
            $_FILES = $this->originalFiles;
            $_SERVER = $this->originalServer;
            $_COOKIE = $this->originalCookie;

            RequestPhpInputStub::$raw = null;
            RequestPhpInputStub::$calls = 0;
        }

        public function testConstructorUsesProvidedArrays(): void
        {
            $request = new Request(
                ['q' => 'value'],
                ['p' => 'value'],
                ['f' => ['name' => 'a.txt']],
                ['REQUEST_METHOD' => 'POST'],
                ['token' => 'abc']
            );

            $this->assertSame(['q' => 'value'], $request->query());
            $this->assertSame(['p' => 'value'], $request->post());
            $this->assertSame(['f' => ['name' => 'a.txt']], $request->file());
            $this->assertSame(['token' => 'abc'], $request->cookie());
            $this->assertSame('POST', $request->method());
        }

        public function testConstructorFallsBackToSuperglobals(): void
        {
            $_GET = ['from_get' => 'yes'];
            $_POST = ['from_post' => 'yes'];
            $_FILES = ['upload' => ['name' => 'doc.txt']];
            $_SERVER = ['REQUEST_METHOD' => 'PATCH'];
            $_COOKIE = ['session' => 'cookie-value'];

            $request = new Request();

            $this->assertSame(['from_get' => 'yes'], $request->query());
            $this->assertSame(['from_post' => 'yes'], $request->post());
            $this->assertSame(['upload' => ['name' => 'doc.txt']], $request->file());
            $this->assertSame(['session' => 'cookie-value'], $request->cookie());
            $this->assertSame('PATCH', $request->method());
        }

        public function testCaptureReturnsLateStaticInstance(): void
        {
            $instance = RequestCaptureSubclass::capture();
            $this->assertInstanceOf(RequestCaptureSubclass::class, $instance);
        }

        public function testAllMergesQueryAndPostWithPostPriority(): void
        {
            $request = new Request(
                ['shared' => 'query', 'only_query' => 1],
                ['shared' => 'post', 'only_post' => 2]
            );

            $this->assertSame(
                ['shared' => 'post', 'only_query' => 1, 'only_post' => 2],
                $request->all()
            );
        }

        public function testInputMergesQueryPostAndJsonWhenJsonRequest(): void
        {
            $request = (new RequestJsonAwareFake(
                ['q' => 'query', 'shared' => 'query'],
                ['p' => 'post', 'shared' => 'post']
            ))->withJsonPayload(
                ['json' => 'value', 'shared' => 'json'],
                true
            );

            $this->assertSame(
                ['q' => 'query', 'shared' => 'json', 'p' => 'post', 'json' => 'value'],
                $request->input()
            );
        }

        public function testInputSupportsDotNotationAndDefault(): void
        {
            $request = new Request(
                ['user' => ['name' => 'Alice']],
                ['settings' => ['locale' => 'en_US']]
            );

            $this->assertSame('Alice', $request->input('user.name'));
            $this->assertSame('en_US', $request->input('settings.locale'));
            $this->assertSame('fallback', $request->input('user.email', 'fallback'));
        }

        public function testQueryPostFileAndCookieAccessors(): void
        {
            $request = new Request(
                ['filters' => ['status' => 'active']],
                ['meta' => ['page' => 2]],
                ['avatar' => ['name' => 'avatar.png']],
                [],
                ['prefs' => ['theme' => 'light']]
            );

            $this->assertSame('active', $request->query('filters.status'));
            $this->assertSame(2, $request->post('meta.page'));
            $this->assertSame('avatar.png', $request->file('avatar.name'));
            $this->assertSame('light', $request->cookie('prefs.theme'));
            $this->assertSame('default', $request->query('missing', 'default'));
        }

        public function testHasChecksPresenceWithNullAsMissing(): void
        {
            $request = new Request([], ['name' => 'Milk', 'empty' => '', 'null' => null]);

            $this->assertTrue($request->has('name'));
            $this->assertTrue($request->has(['name', 'empty']));
            $this->assertFalse($request->has('null'));
            $this->assertFalse($request->has(['name', 'missing']));
        }

        public function testFilledRejectsNullAndEmptyStrings(): void
        {
            $request = new Request([], ['name' => 'Milk', 'empty' => '   ', 'zero' => '0', 'null' => null]);

            $this->assertTrue($request->filled('name'));
            $this->assertTrue($request->filled('zero'));
            $this->assertFalse($request->filled('empty'));
            $this->assertFalse($request->filled('null'));
        }

        public function testMissingIsInverseOfHas(): void
        {
            $request = new Request([], ['present' => 'x']);

            $this->assertFalse($request->missing('present'));
            $this->assertTrue($request->missing('absent'));
        }

        public function testOnlyReturnsExistingKeysAndPreservesDotStructure(): void
        {
            $request = new Request([], [
                'id' => 12,
                'user' => ['name' => 'Alice', 'email' => 'alice@example.com'],
            ]);

            $this->assertSame(
                ['id' => 12, 'user' => ['name' => 'Alice']],
                $request->only(['id', 'user.name', 'missing'])
            );
        }

        public function testExceptRemovesTopLevelAndNestedKeys(): void
        {
            $request = new Request([], [
                'token' => 'abc',
                'user' => ['name' => 'Alice', 'password' => 'secret'],
                'active' => true,
            ]);

            $this->assertSame(
                ['user' => ['name' => 'Alice'], 'active' => true],
                $request->except(['token', 'user.password'])
            );
        }

        public function testExceptReturnsEmptyArrayWhenInputIsNotArray(): void
        {
            $request = new RequestScalarInputFake();
            $this->assertSame([], $request->except(['any']));
        }

        public function testStringReturnsTrimmedValueAndDefaultForArrayOrObject(): void
        {
            $request = new Request([], [
                'name' => '  Alice  ',
                'arr' => ['x' => 1],
                'obj' => (object) ['x' => 1],
            ]);

            $this->assertSame('Alice', $request->string('name'));
            $this->assertSame('fallback', $request->string('arr', 'fallback'));
            $this->assertSame('fallback', $request->string('obj', 'fallback'));
        }

        public function testIntegerReturnsParsedValueOrDefault(): void
        {
            $request = new Request([], ['id' => '42', 'bad' => '4.2']);

            $this->assertSame(42, $request->integer('id'));
            $this->assertSame(99, $request->integer('bad', 99));
            $this->assertSame(77, $request->integer('missing', 77));
        }

        public function testFloatReturnsParsedValueOrDefault(): void
        {
            $request = new Request([], ['ratio' => '3.14', 'bad' => 'x']);

            $this->assertSame(3.14, $request->float('ratio'));
            $this->assertSame(9.9, $request->float('bad', 9.9));
            $this->assertSame(1.5, $request->float('missing', 1.5));
        }

        public function testBooleanParsesValueAndFallsBackToDefault(): void
        {
            $request = new Request([], ['enabled' => 'true', 'disabled' => 'off', 'bad' => 'maybe']);

            $this->assertTrue($request->boolean('enabled'));
            $this->assertFalse($request->boolean('disabled', true));
            $this->assertTrue($request->boolean('missing', true));
            $this->assertTrue($request->boolean('bad', true));
        }

        public function testArrayReturnsArrayOrDefault(): void
        {
            $request = new Request([], ['items' => [1, 2, 3], 'name' => 'Alice']);

            $this->assertSame([1, 2, 3], $request->array('items'));
            $this->assertSame(['fallback'], $request->array('name', ['fallback']));
            $this->assertSame(['fallback'], $request->array('missing', ['fallback']));
        }

        public function testDateParsesWithAndWithoutFormatAndReturnsDefaultOnFailure(): void
        {
            $default = new DateTime('2020-01-01 00:00:00');
            $request = new Request([], [
                'plain' => '2026-03-11 10:30:00',
                'formatted' => '11/03/2026',
                'invalid' => 'not-a-date',
                'empty' => '   ',
            ]);

            $plain = $request->date('plain');
            $formatted = $request->date('formatted', 'd/m/Y');

            $this->assertInstanceOf(DateTime::class, $plain);
            $this->assertSame('2026-03-11 10:30:00', $plain->format('Y-m-d H:i:s'));
            $this->assertSame('2026-03-11', $formatted?->format('Y-m-d'));
            $this->assertSame($default, $request->date('invalid', 'Y-m-d', $default));
            $this->assertSame($default, $request->date('empty', null, $default));
        }

        public function testMethodAndHelpers(): void
        {
            $request = new Request([], [], [], ['REQUEST_METHOD' => 'post']);

            $this->assertSame('POST', $request->method());
            $this->assertTrue($request->isMethod('post'));
            $this->assertFalse($request->isGet());
            $this->assertTrue($request->isPost());
            $this->assertSame('GET', (new Request([], [], [], []))->method());
        }

        public function testHeaderReadsStandardAndContentHeaders(): void
        {
            $request = new Request([], [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer token-123',
                'CONTENT_TYPE' => 'application/json',
                'CONTENT_LENGTH' => '256',
            ]);

            $this->assertSame('Bearer token-123', $request->header('Authorization'));
            $this->assertSame('application/json', $request->header('Content-Type'));
            $this->assertSame('256', $request->header('Content-Length'));
            $this->assertSame('fallback', $request->header('X-Missing', 'fallback'));
        }

        public function testBearerTokenExtractsTokenOrReturnsNull(): void
        {
            $withToken = new Request([], [], [], ['HTTP_AUTHORIZATION' => 'Bearer   abc.def.ghi  ']);
            $this->assertSame('abc.def.ghi', $withToken->bearerToken());

            $withoutToken = new Request([], [], [], ['HTTP_AUTHORIZATION' => 'Basic 123']);
            $this->assertNull($withoutToken->bearerToken());

            $empty = new Request([], [], [], ['HTTP_AUTHORIZATION' => '']);
            $this->assertNull($empty->bearerToken());
        }

        public function testIpAndUserAgentReturnStringValues(): void
        {
            $request = new Request([], [], [], [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_USER_AGENT' => 'PHPUnit Agent',
            ]);

            $this->assertSame('127.0.0.1', $request->ip());
            $this->assertSame('PHPUnit Agent', $request->userAgent());

            $invalid = new Request([], [], [], ['REMOTE_ADDR' => 123, 'HTTP_USER_AGENT' => 999]);
            $this->assertNull($invalid->ip());
            $this->assertNull($invalid->userAgent());
        }

        public function testIsJsonChecksContentTypeCaseInsensitive(): void
        {
            $jsonRequest = new Request([], [], [], ['CONTENT_TYPE' => 'Application/JSON; charset=utf-8']);
            $htmlRequest = new Request([], [], [], ['CONTENT_TYPE' => 'text/html']);

            $this->assertTrue($jsonRequest->isJson());
            $this->assertFalse($htmlRequest->isJson());
        }

        public function testJsonDecodesAndCachesPayload(): void
        {
            RequestPhpInputStub::$raw = '{"a":1,"nested":{"k":"v"}}';
            $request = new Request();

            $first = $request->json();
            $second = $request->json();

            $this->assertSame(['a' => 1, 'nested' => ['k' => 'v']], $first);
            $this->assertSame($first, $second);
            $this->assertSame(1, RequestPhpInputStub::$calls);
        }

        public function testJsonReturnsEmptyArrayForInvalidOrEmptyPayload(): void
        {
            RequestPhpInputStub::$raw = 'not-json';
            $invalid = new Request();
            $this->assertSame([], $invalid->json());

            RequestPhpInputStub::$raw = '   ';
            $empty = new Request();
            $this->assertSame([], $empty->json());
        }

        public function testSanitizeHelpers(): void
        {
            $request = new Request([], [
                'name' => "  Hello\x07  ",
                'email' => ' user<>@example.com ',
                'url' => ' https://example.com/path with spaces ',
            ]);

            $this->assertSame('Hello', $request->sanitizeString('name'));
            $this->assertSame('user@example.com', $request->sanitizeEmail('email'));
            $this->assertSame('https://example.com/pathwithspaces', $request->sanitizeUrl('url'));
            $this->assertSame('fallback', $request->sanitizeEmail('missing', 'fallback'));
            $this->assertSame('fallback', $request->sanitizeUrl('missing', 'fallback'));
        }

        public function testProtectedIsEmptyStringHelper(): void
        {
            $proxy = new RequestTestProxy([], []);

            $this->assertTrue($proxy->callIsEmptyString('   '));
            $this->assertFalse($proxy->callIsEmptyString('0'));
            $this->assertFalse($proxy->callIsEmptyString(0));
        }

        public function testProtectedDataGetSupportsDotNotationAndDefault(): void
        {
            $proxy = new RequestTestProxy([], []);
            $data = ['user' => ['name' => 'Alice'], 'status' => 'ok'];

            $this->assertSame('ok', $proxy->callDataGet($data, 'status'));
            $this->assertSame('Alice', $proxy->callDataGet($data, 'user.name'));
            $this->assertSame('fallback', $proxy->callDataGet($data, 'user.email', 'fallback'));
        }

        public function testProtectedDataSetCreatesNestedStructure(): void
        {
            $proxy = new RequestTestProxy([], []);
            $data = [];

            $proxy->callDataSet($data, 'user.profile.name', 'Alice');

            $this->assertSame(['user' => ['profile' => ['name' => 'Alice']]], $data);
        }

        public function testProtectedDataForgetRemovesNestedKeysAndSkipsMissingPaths(): void
        {
            $proxy = new RequestTestProxy([], []);
            $data = [
                'user' => ['name' => 'Alice', 'password' => 'secret'],
                'meta' => ['active' => true],
            ];

            $proxy->callDataForget($data, 'user.password');
            $proxy->callDataForget($data, 'user.not_existing');
            $proxy->callDataForget($data, 'missing.path');

            $this->assertSame(
                ['user' => ['name' => 'Alice'], 'meta' => ['active' => true]],
                $data
            );
        }
    }
}
