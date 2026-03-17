<?php

declare(strict_types=1);

use App\Hooks;
use App\Theme;
use PHPUnit\Framework\TestCase;

final class ThemeTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalRegistry = [];
    /** @var array<string, mixed> */
    private array $originalHooks = [];

    protected function setUp(): void
    {
        $this->originalRegistry = Theme::$registry;
        $this->originalHooks = $this->getHooks();

        Theme::$registry = [];
        $this->setHooks([]);
    }

    protected function tearDown(): void
    {
        Theme::$registry = $this->originalRegistry;
        $this->setHooks($this->originalHooks);
    }

    public function testSetGetDeleteAndHas(): void
    {
        Theme::set('title', 'Home');
        Theme::set('title', 'Dashboard');

        $this->assertTrue(Theme::has('title'));
        $this->assertSame('Dashboard', Theme::get('title'));
        $this->assertSame(['Home', 'Dashboard'], Theme::getAll('title'));

        Theme::delete('title');
        $this->assertFalse(Theme::has('title'));
        $this->assertSame('fallback', Theme::get('title', 'fallback'));
    }

    public function testSetWithNullRemovesPath(): void
    {
        Theme::set('message', 'hello');
        Theme::set('message', null);

        $this->assertFalse(Theme::has('message'));
    }

    public function testMultiarrayOrderAndGeneratorIteration(): void
    {
        Theme::set('items', ['name' => 'b', 'order' => 2]);
        Theme::set('items', ['name' => 'a', 'order' => 1]);
        Theme::multiarrayOrder('items', 'order', 'asc');

        $ordered = Theme::getAll('items');
        $this->assertSame('a', $ordered[0]['name']);
        $this->assertSame('b', $ordered[1]['name']);

        $iterated = [];
        foreach (Theme::for('items') as $row) {
            $iterated[] = $row['name'];
        }
        $this->assertSame(['a', 'b'], $iterated);
    }

    public function testCheckSupportsScalarArrayAndObjectValidation(): void
    {
        $this->assertTrue(Theme::check('abc', 'string'));
        $this->assertTrue(Theme::check(10, 'int'));
        $this->assertTrue(Theme::check(10.5, 'float'));
        $this->assertTrue(Theme::check(['id' => 1], ['id']));
        $this->assertFalse(Theme::check(['name' => 'x'], ['id']));

        $type = (object) ['required_property' => 'id'];
        $value = (object) ['id' => 1];
        $this->assertTrue(Theme::check($value, $type));
    }

    public function testThemeSetAndGetHooksAreApplied(): void
    {
        Hooks::set('theme_set_headline', static fn ($value) => strtoupper((string) $value));
        Hooks::set('theme_get_headline', static fn ($value) => '>>' . $value . '<<');

        Theme::set('headline', 'hello');
        $this->assertSame('>>HELLO<<', Theme::get('headline'));
    }

    /**
     * @return array<string, mixed>
     */
    private function getHooks(): array
    {
        $reflection = new ReflectionClass(Hooks::class);
        $property = $reflection->getProperty('functions');

        /** @var array<string, mixed> $hooks */
        $hooks = $property->getValue();
        return $hooks;
    }

    /**
     * @param array<string, mixed> $hooks
     */
    private function setHooks(array $hooks): void
    {
        $reflection = new ReflectionClass(Hooks::class);
        $property = $reflection->getProperty('functions');
        $property->setValue(null, $hooks);
    }
}
