<?php

declare(strict_types=1);

use App\ExtensionLoader;
use PHPUnit\Framework\TestCase;

final class ExtensionLoaderInlineTarget
{
    public array $events = [];
}

final class ExtensionLoaderInlineExtension
{
    private object $target;
    /** @var array<string, mixed> */
    public array $params = [];

    public function __construct(object $target)
    {
        $this->target = $target;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function applyParameters(array $params): void
    {
        $this->params = $params;
    }

    public function onBoot(string $name): void
    {
        if (property_exists($this->target, 'events')) {
            $this->target->events[] = 'boot:' . $name;
        }
    }

    public function transform(mixed $value, string $suffix): mixed
    {
        return (string) $value . $suffix;
    }

    public function failHook(): void
    {
        throw new RuntimeException('hook failure');
    }
}

final class ExtensionLoaderTest extends TestCase
{
    protected function setUp(): void
    {
        ExtensionLoader::clearCache();
    }

    public function testLoadSupportsInlineClassAndParameters(): void
    {
        $target = new ExtensionLoaderInlineTarget();

        $extensions = ExtensionLoader::load(
            [ExtensionLoaderInlineExtension::class => ['mode' => 'fast']],
            'Model',
            $target
        );

        $this->assertArrayHasKey(ExtensionLoaderInlineExtension::class, $extensions);
        $instance = $extensions[ExtensionLoaderInlineExtension::class];
        $this->assertInstanceOf(ExtensionLoaderInlineExtension::class, $instance);
        $this->assertSame(['mode' => 'fast'], $instance->params);
    }

    public function testLoadSupportsOldNumericFormat(): void
    {
        $target = new ExtensionLoaderInlineTarget();
        $extensions = ExtensionLoader::load([ExtensionLoaderInlineExtension::class], 'Model', $target);

        $this->assertArrayHasKey(ExtensionLoaderInlineExtension::class, $extensions);
    }

    public function testCallHookAndCallReturnHook(): void
    {
        $target = new ExtensionLoaderInlineTarget();
        $extensions = ExtensionLoader::load([ExtensionLoaderInlineExtension::class], 'Model', $target);

        ExtensionLoader::callHook($extensions, 'onBoot', ['main']);
        $this->assertSame(['boot:main'], $target->events);

        $result = ExtensionLoader::callReturnHook($extensions, 'transform', ['start', '-done']);
        $this->assertSame('start-done', $result);
    }

    public function testCallHookRethrowsExtensionExceptions(): void
    {
        $target = new ExtensionLoaderInlineTarget();
        $extensions = ExtensionLoader::load([ExtensionLoaderInlineExtension::class], 'Model', $target);

        $this->expectException(RuntimeException::class);
        ExtensionLoader::callHook($extensions, 'failHook');
    }

    public function testRecursionGuardsAndCacheReset(): void
    {
        $this->assertTrue(ExtensionLoader::preventRecursion('extA'));
        $this->assertFalse(ExtensionLoader::preventRecursion('extA'));

        ExtensionLoader::freeRecursion('extA');
        ExtensionLoader::freeRecursion('extA');

        $this->assertTrue(ExtensionLoader::preventRecursion('extA'));

        $this->setLoadedFiles(['x' => 'Y']);
        ExtensionLoader::clearCache();
        $this->assertSame([], $this->getLoadedFiles());
    }

    /**
     * @return array<string, string>
     */
    private function getLoadedFiles(): array
    {
        $reflection = new ReflectionClass(ExtensionLoader::class);
        $property = $reflection->getProperty('loaded_files');

        /** @var array<string, string> $value */
        $value = $property->getValue();
        return $value;
    }

    /**
     * @param array<string, string> $value
     */
    private function setLoadedFiles(array $value): void
    {
        $reflection = new ReflectionClass(ExtensionLoader::class);
        $property = $reflection->getProperty('loaded_files');
        $property->setValue(null, $value);
    }
}
