<?php

declare(strict_types=1);

use App\Lang;
use PHPUnit\Framework\TestCase;

final class AppFunctionsTest extends TestCase
{
    /** @var array<string, array<string, string>> */
    private array $originalLangStrings = [];

    protected function setUp(): void
    {
        $this->originalLangStrings = $this->getLangStrings();
        $this->setLangStrings([]);
        $_REQUEST = [];
    }

    protected function tearDown(): void
    {
        $this->setLangStrings($this->originalLangStrings);
    }

    public function testSanitizeHelpersFromFunctionsPhp(): void
    {
        $this->assertSame('&lt;b&gt;x&lt;/b&gt;', _r('<b>x</b>'));
        $this->assertSame('<b>x</b>', _rh('<b>x</b>'));
        $this->assertSame('abc123', _raz('abc-123'));
        $this->assertSame(10, _absint(-10));
    }

    public function testTranslationHelpersFromFunctionsPhp(): void
    {
        Lang::set('Hello %s', 'Ciao %s', 'all');

        $this->assertSame('Ciao Mario', _rt('Hello %s', 'Mario'));

        ob_start();
        _pt('Hello %s', 'Luigi');
        $output = (string) ob_get_clean();
        $this->assertSame('Ciao Luigi', $output);
    }

    public function testGetValSupportsArrayAndObject(): void
    {
        $arrayData = ['name' => 'Alice'];
        $objectData = (object) ['name' => 'Bob'];

        $this->assertSame('Alice', getVal($arrayData, 'name'));
        $this->assertSame('Bob', getVal($objectData, 'name'));
        $this->assertNull(getVal($objectData, 'missing'));
    }

    public function testToMysqlArraySupportsArrayObjectAndModelLikeObject(): void
    {
        $arr = ['id' => 1];
        $obj = (object) ['id' => 2];
        $modelLike = new class {
            public function toMysqlArray(): array
            {
                return ['id' => 3];
            }
        };

        $this->assertSame(['id' => 1], toMysqlArray($arr, []));
        $this->assertSame(['id' => 2], toMysqlArray($obj, []));
        $this->assertSame(['id' => 3], toMysqlArray($modelLike, ['id' => 0]));
        $this->assertSame(['fallback' => true], toMysqlArray('invalid', ['fallback' => true]));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getLangStrings(): array
    {
        $reflection = new ReflectionClass(Lang::class);
        $property = $reflection->getProperty('strings');

        /** @var array<string, array<string, string>> $value */
        $value = $property->getValue();
        return $value;
    }

    /**
     * @param array<string, array<string, string>> $strings
     */
    private function setLangStrings(array $strings): void
    {
        $reflection = new ReflectionClass(Lang::class);
        $property = $reflection->getProperty('strings');
        $property->setValue(null, $strings);
    }
}
