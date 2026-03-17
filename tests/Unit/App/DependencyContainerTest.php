<?php

declare(strict_types=1);

use App\DependencyContainer;
use PHPUnit\Framework\TestCase;

final class DependencyContainerRegularService
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

final class DependencyContainerStaticService
{
    public static function ping(): string
    {
        return 'pong';
    }
}

final class DependencyContainerSingletonService
{
    private static ?self $instance = null;
    public int $counter = 0;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getCounter(): int
    {
        return $this->counter;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}

final class DependencyContainerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalServices = [];
    /** @var array<string, mixed> */
    private array $originalInstances = [];

    protected function setUp(): void
    {
        $this->originalServices = $this->getPrivateStaticArray('services');
        $this->originalInstances = $this->getPrivateStaticArray('instances');

        $this->setPrivateStaticArray('services', []);
        $this->setPrivateStaticArray('instances', []);
        DependencyContainerSingletonService::reset();
    }

    protected function tearDown(): void
    {
        $this->setPrivateStaticArray('services', $this->originalServices);
        $this->setPrivateStaticArray('instances', $this->originalInstances);
        DependencyContainerSingletonService::reset();
    }

    public function testGetReturnsNullForUnknownService(): void
    {
        $this->assertNull(DependencyContainer::get('not_registered'));
    }

    public function testBindAndGetRegularClassCreatesNewInstances(): void
    {
        DependencyContainer::bind('regular', DependencyContainerRegularService::class);

        $first = DependencyContainer::get('regular', ['alpha']);
        $second = DependencyContainer::get('regular', ['beta']);

        $this->assertInstanceOf(DependencyContainerRegularService::class, $first);
        $this->assertInstanceOf(DependencyContainerRegularService::class, $second);
        $this->assertSame('alpha', $first->name);
        $this->assertSame('beta', $second->name);
        $this->assertNotSame($first, $second);
    }

    public function testBindWithSingletonCachesRegularClassInstance(): void
    {
        DependencyContainer::bind('singleton_class', DependencyContainerRegularService::class, true);

        $first = DependencyContainer::get('singleton_class', ['first']);
        $second = DependencyContainer::get('singleton_class', ['second']);

        $this->assertSame($first, $second);
        $this->assertSame('first', $first->name);
    }

    public function testStaticClassRegistrationReturnsClassName(): void
    {
        DependencyContainer::bind('static_service', DependencyContainerStaticService::class);

        $resolved = DependencyContainer::get('static_service');

        $this->assertSame(DependencyContainerStaticService::class, $resolved);
        $this->assertSame('pong', $resolved::ping());
    }

    public function testSingletonPatternClassUsesGetInstance(): void
    {
        DependencyContainer::bind('singleton_service', DependencyContainerSingletonService::class);

        $first = DependencyContainer::get('singleton_service');
        $second = DependencyContainer::get('singleton_service');
        $first->counter = 42;

        $this->assertSame($first, $second);
        $this->assertSame(42, $second->counter);
    }

    public function testClosureFactoryReceivesArguments(): void
    {
        DependencyContainer::bind(
            'factory',
            static fn (string $name, int $count): array => [$name, $count]
        );

        $this->assertSame(['milk', 3], DependencyContainer::get('factory', ['milk', 3]));
    }

    public function testValueRegistrationReturnsRawValue(): void
    {
        DependencyContainer::bind('value_service', ['mode' => 'test']);

        $this->assertSame(['mode' => 'test'], DependencyContainer::get('value_service'));
    }

    public function testRegisterActsAsAliasForBind(): void
    {
        DependencyContainer::register('alias_service', DependencyContainerRegularService::class);

        $service = DependencyContainer::get('alias_service', ['aliased']);
        $this->assertInstanceOf(DependencyContainerRegularService::class, $service);
        $this->assertSame('aliased', $service->name);
    }

    public function testHasAndUnbindManageServiceLifecycle(): void
    {
        DependencyContainer::bind('to_remove', DependencyContainerRegularService::class);
        $this->assertTrue(DependencyContainer::has('to_remove'));

        DependencyContainer::unbind('to_remove');
        $this->assertFalse(DependencyContainer::has('to_remove'));
        $this->assertNull(DependencyContainer::get('to_remove'));
    }

    public function testFlushRemovesOnlyInstancesKeepingRegistrations(): void
    {
        DependencyContainer::bind('cached', DependencyContainerRegularService::class, true);
        $first = DependencyContainer::get('cached', ['first']);

        DependencyContainer::flush();
        $second = DependencyContainer::get('cached', ['second']);

        $this->assertTrue(DependencyContainer::has('cached'));
        $this->assertNotSame($first, $second);
        $this->assertSame('second', $second->name);
    }

    /**
     * @return array<string, mixed>
     */
    private function getPrivateStaticArray(string $name): array
    {
        $reflection = new ReflectionClass(DependencyContainer::class);
        $property = $reflection->getProperty($name);

        /** @var array<string, mixed> $value */
        $value = $property->getValue();
        return $value;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function setPrivateStaticArray(string $name, array $value): void
    {
        $reflection = new ReflectionClass(DependencyContainer::class);
        $property = $reflection->getProperty($name);
        $property->setValue(null, $value);
    }
}
