<?php

declare(strict_types=1);

use App\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalConfig = [];

    protected function setUp(): void
    {
        $this->originalConfig = Config::getAll();
        Config::setAll([]);
    }

    protected function tearDown(): void
    {
        Config::setAll($this->originalConfig);
    }

    public function testSetAndGetValue(): void
    {
        Config::set('site_name', 'MilkAdmin');

        $this->assertSame('MilkAdmin', Config::get('site_name'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $this->assertSame('fallback', Config::get('missing', 'fallback'));
    }

    public function testGetAllReturnsCurrentConfig(): void
    {
        Config::set('a', 1);
        Config::set('b', 2);

        $this->assertSame(['a' => 1, 'b' => 2], Config::getAll());
    }

    public function testAppendCreatesArrayAndAppendsScalarValues(): void
    {
        Config::append('items', 'first');
        Config::append('items', 'second');

        $this->assertSame(['first', 'second'], Config::get('items'));
    }

    public function testAppendMergesArrayValues(): void
    {
        Config::append('items', ['a' => 1, 'b' => 2]);
        Config::append('items', ['c' => 3]);

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], Config::get('items'));
    }

    public function testRemoveDeletesExistingKey(): void
    {
        Config::set('temp', 'value');
        Config::remove('temp');

        $this->assertNull(Config::get('temp'));
    }

    public function testSetAllNormalizesBaseUrlWithTrailingSlash(): void
    {
        Config::setAll([
            'base_url' => 'https://example.com/admin',
            'debug' => true,
        ]);

        $this->assertSame('https://example.com/admin/', Config::get('base_url'));
        $this->assertTrue(Config::get('debug'));
    }

    public function testSetAllKeepsBaseUrlWhenAlreadyNormalized(): void
    {
        Config::setAll(['base_url' => 'https://example.com/admin/']);

        $this->assertSame('https://example.com/admin/', Config::get('base_url'));
    }
}
