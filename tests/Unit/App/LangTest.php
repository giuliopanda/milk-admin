<?php

declare(strict_types=1);

use App\Lang;
use PHPUnit\Framework\TestCase;

final class LangTest extends TestCase
{
    /** @var array<string, array<string, string>> */
    private array $originalStrings = [];

    protected function setUp(): void
    {
        $this->originalStrings = $this->getStrings();
        $this->setStrings([]);
    }

    protected function tearDown(): void
    {
        $this->setStrings($this->originalStrings);
    }

    public function testSetAndGetWithAreaFallback(): void
    {
        Lang::set('Hello', 'Ciao', 'it');
        Lang::set('Hello', 'Hello all', 'all');

        $this->assertSame('Ciao', Lang::get('Hello', 'it'));
        $this->assertSame('Hello all', Lang::get('Hello', 'missing-area'));
        $this->assertSame('Unknown', Lang::get('Unknown'));
    }

    public function testSetAndGetNormalizeInvalidAreaToAll(): void
    {
        Lang::set('Save', 'Salva', '');
        $this->assertSame('Salva', Lang::get('Save', ''));
        $this->assertSame('Salva', Lang::get('Save', ['invalid']));
    }

    public function testLoadPhpFileLoadsTranslationsFromLocalPath(): void
    {
        $langDir = LOCAL_DIR . '/temp/lang-tests';
        if (!is_dir($langDir)) {
            mkdir($langDir, 0755, true);
        }

        $file = $langDir . '/it.php';
        file_put_contents($file, "<?php\nreturn ['Hello' => 'Ciao', 'Bye' => 'Addio'];\n");

        $loaded = Lang::loadPhpFile($file, 'it');
        $this->assertTrue($loaded);
        $this->assertSame('Ciao', Lang::get('Hello', 'it'));
        $this->assertSame('Addio', Lang::get('Bye', 'it'));

        @unlink($file);
        @rmdir($langDir);
    }

    public function testLoadPhpFileReturnsFalseForMissingOrInvalidFile(): void
    {
        $this->assertFalse(Lang::loadPhpFile(LOCAL_DIR . '/temp/lang-tests/not-found.php', 'it'));

        $langDir = LOCAL_DIR . '/temp/lang-tests';
        if (!is_dir($langDir)) {
            mkdir($langDir, 0755, true);
        }
        $file = $langDir . '/invalid.php';
        file_put_contents($file, "<?php\nreturn 'not-an-array';\n");

        $this->assertFalse(Lang::loadPhpFile($file, 'it'));

        @unlink($file);
        @rmdir($langDir);
    }

    public function testGenerateJsProducesMinifiedAndPrettyOutput(): void
    {
        Lang::set('Hello', 'All', 'all');
        Lang::set('Hello', 'Page', 'dashboard');
        Lang::set('Save', 'Save', 'dashboard');

        $minified = Lang::generateJs('dashboard', true);
        $pretty = Lang::generateJs('dashboard', false);

        $this->assertStringStartsWith('window.TRANSLATIONS=', $minified);
        $this->assertStringContainsString('// Translation file', $pretty);
        $this->assertStringContainsString('window.TRANSLATIONS = ', $pretty);
        $this->assertStringContainsString('"Hello":"All"', str_replace(' ', '', $minified));
        $this->assertStringContainsString('"Save":"Save"', str_replace(' ', '', $minified));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getStrings(): array
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
    private function setStrings(array $strings): void
    {
        $reflection = new ReflectionClass(Lang::class);
        $property = $reflection->getProperty('strings');
        $property->setValue(null, $strings);
    }
}
