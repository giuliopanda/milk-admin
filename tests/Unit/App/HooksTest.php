<?php

declare(strict_types=1);

use App\Hooks;
use PHPUnit\Framework\TestCase;

final class HooksTest extends TestCase
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $originalFunctions = [];

    protected function setUp(): void
    {
        $this->originalFunctions = $this->getPrivateFunctions();
        $this->setPrivateFunctions([]);
    }

    protected function tearDown(): void
    {
        $this->setPrivateFunctions($this->originalFunctions);
    }

    public function testSetAndRunExecutesCallbacksInOrder(): void
    {
        Hooks::set('my_hook', static fn ($value, $suffix = null) => $value . 'A' . ($suffix ?? ''), 20);
        Hooks::set('my_hook', static fn ($value) => $value . 'B', 10);

        $result = Hooks::run('my_hook', 'start', '_x');

        $this->assertSame('startBA_x', $result);
    }

    public function testRunReturnsNullWhenNoArgumentsAndNoCallbacks(): void
    {
        $this->assertNull(Hooks::run('missing_hook'));
    }

    public function testRunPadsMissingArgumentsWithNull(): void
    {
        Hooks::set('pad_hook', static function ($value, $arg1, $arg2) {
            return [$value, $arg1, $arg2];
        });

        $result = Hooks::run('pad_hook', 'value', 'only-one');

        $this->assertSame(['value', 'only-one', null], $result);
    }

    public function testGetHookRegistrationsReturnsSortedMetadata(): void
    {
        Hooks::set('meta_hook', static fn ($value) => $value, 30);
        Hooks::set('meta_hook', static fn ($value) => $value, 5);

        $registrations = Hooks::getHookRegistrations('meta_hook');

        $this->assertCount(2, $registrations);
        $this->assertSame(5, $registrations[0]['order']);
        $this->assertSame(30, $registrations[1]['order']);
        $this->assertArrayHasKey('caller', $registrations[0]);
    }

    public function testRemoveClearsHookAndReportsStatus(): void
    {
        Hooks::set('to_remove', static fn ($value) => $value);

        $this->assertTrue(Hooks::remove('to_remove'));
        $this->assertFalse(Hooks::remove('to_remove'));
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function getPrivateFunctions(): array
    {
        $reflection = new ReflectionClass(Hooks::class);
        $property = $reflection->getProperty('functions');

        /** @var array<string, array<int, array<string, mixed>>> $functions */
        $functions = $property->getValue();
        return $functions;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $functions
     */
    private function setPrivateFunctions(array $functions): void
    {
        $reflection = new ReflectionClass(Hooks::class);
        $property = $reflection->getProperty('functions');
        $property->setValue(null, $functions);
    }
}
