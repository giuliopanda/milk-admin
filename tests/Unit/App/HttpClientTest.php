<?php

declare(strict_types=1);

use App\Exceptions\HttpClientException;
use App\HttpClient;
use PHPUnit\Framework\TestCase;

final class HttpClientTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalDefaults = [];

    protected function setUp(): void
    {
        $this->originalDefaults = HttpClient::getDefaultOptions();
        HttpClient::resetDefaultOptions();
    }

    protected function tearDown(): void
    {
        HttpClient::setDefaultOptions($this->originalDefaults);
    }

    public function testRequestRejectsInvalidUrl(): void
    {
        $this->expectException(HttpClientException::class);
        HttpClient::request('not-a-url');
    }

    public function testExecuteMultiValidatesInput(): void
    {
        $this->expectException(HttpClientException::class);
        HttpClient::executeMulti([]);
    }

    public function testDefaultOptionsCanBeSetReadAndReset(): void
    {
        HttpClient::setDefaultOption('timeout', 45);
        HttpClient::setDefaultOptions([
            'verify_ssl' => false,
            'headers' => ['Accept' => 'application/json'],
        ]);

        $current = HttpClient::getDefaultOptions();
        $this->assertSame(45, $current['timeout']);
        $this->assertFalse($current['verify_ssl']);
        $this->assertSame('application/json', $current['headers']['Accept']);

        HttpClient::resetDefaultOptions();
        $reset = HttpClient::getDefaultOptions();
        $this->assertSame(30, $reset['timeout']);
        $this->assertTrue($reset['verify_ssl']);
    }

    public function testPrivateHeaderAndJsonParsingHelpers(): void
    {
        $headersRaw = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nSet-Cookie: a=1\r\nSet-Cookie: b=2\r\n\r\n";
        $headers = $this->invokePrivateStatic('parseHeaders', [$headersRaw]);

        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame(['a=1', 'b=2'], $headers['Set-Cookie']);

        $decoded = $this->invokePrivateStatic('tryJsonDecode', ['{"ok":true}']);
        $invalid = $this->invokePrivateStatic('tryJsonDecode', ['not-json']);
        $this->assertSame(['ok' => true], $decoded);
        $this->assertNull($invalid);
    }

    public function testParseResponseBuildsStructuredPayload(): void
    {
        $raw = "HTTP/1.1 201 Created\r\nContent-Type: application/json\r\n\r\n{\"id\":12}";
        $info = [
            'header_size' => strpos($raw, "\r\n\r\n") + 4,
            'http_code' => 201,
        ];

        $parsed = $this->invokePrivateStatic('parseResponse', [$raw, $info]);

        $this->assertSame(201, $parsed['status_code']);
        $this->assertSame('application/json', $parsed['headers']['Content-Type']);
        $this->assertSame(['id' => 12], $parsed['body']);
        $this->assertSame($info, $parsed['info']);
    }

    /**
     * @param array<int, mixed> $args
     * @return mixed
     */
    private function invokePrivateStatic(string $method, array $args = [])
    {
        $reflection = new ReflectionClass(HttpClient::class);
        $m = $reflection->getMethod($method);

        return $m->invokeArgs(null, $args);
    }
}
