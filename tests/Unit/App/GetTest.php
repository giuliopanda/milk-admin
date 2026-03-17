<?php

declare(strict_types=1);

use App\Config;
use App\Database\ArrayDb;
use App\DatabaseManager;
use App\Get;
use PHPUnit\Framework\TestCase;

final class GetTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalConfig = [];
    private mixed $originalMailClass = null;
    private mixed $originalTimezone = null;
    private mixed $originalLocale = null;

    protected function setUp(): void
    {
        $this->originalConfig = Config::getAll();
        $this->originalMailClass = $this->getPrivateStatic('mail_class');
        $this->originalTimezone = $this->getPrivateStatic('user_timezone');
        $this->originalLocale = $this->getPrivateStatic('user_locale');

        Config::setAll([]);
        DatabaseManager::reset();
        $this->setPrivateStatic('db', null);
        $this->setPrivateStatic('db2', null);
        $this->setPrivateStatic('array_db', null);
        $this->setPrivateStatic('mail_class', null);
        $this->setPrivateStatic('user_timezone', null);
        $this->setPrivateStatic('user_locale', null);
    }

    protected function tearDown(): void
    {
        Config::setAll($this->originalConfig);
        DatabaseManager::reset();
        $this->setPrivateStatic('db', null);
        $this->setPrivateStatic('db2', null);
        $this->setPrivateStatic('array_db', null);
        $this->setPrivateStatic('mail_class', $this->originalMailClass);
        $this->setPrivateStatic('user_timezone', $this->originalTimezone);
        $this->setPrivateStatic('user_locale', $this->originalLocale);
    }

    public function testArrayDbReturnsSingletonArrayAdapter(): void
    {
        $first = Get::arrayDb();
        $second = Get::arrayDb();

        $this->assertInstanceOf(ArrayDb::class, $first);
        $this->assertSame($first, $second);
    }

    public function testDbConnectionReturnsNullWhenConnectionIsMissing(): void
    {
        $this->assertNull(Get::dbConnection('missing_connection'));
    }

    public function testTempDirCreatesAndReturnsConfiguredDirectory(): void
    {
        $temp = LOCAL_DIR . '/temp/get-tests';
        Config::set('temp_dir', $temp);

        $resolved = Get::tempDir();

        $this->assertTrue(is_dir($resolved));
    }

    public function testUserTimezoneAndLocaleHelpers(): void
    {
        Config::set('use_user_timezone', false);
        $this->assertSame('UTC', Get::userTimezone());

        Config::set('use_user_timezone', true);
        Get::setUserTimezone('Europe/Rome');
        $this->assertSame('Europe/Rome', Get::userTimezone());

        Config::set('available_locales', false);
        Config::set('locale', 'it_IT');
        $this->assertSame('it_IT', Get::userLocale());

        Config::set('available_locales', true);
        Get::setUserLocale('en_US');
        $this->assertSame('en_US', Get::userLocale());
    }

    public function testCloseConnectionsResetsDbAndDb2(): void
    {
        Config::setAll([
            'db_type' => 'array',
            'db_type2' => 'array',
            'prefix' => '',
            'prefix2' => '',
        ]);

        $db = Get::db();
        $db2 = Get::db2();
        $this->assertInstanceOf(ArrayDb::class, $db);
        $this->assertInstanceOf(ArrayDb::class, $db2);

        Get::closeConnections();

        $this->assertNull($this->getPrivateStatic('db'));
        $this->assertNull($this->getPrivateStatic('db2'));
    }

    /**
     * @return mixed
     */
    private function getPrivateStatic(string $property)
    {
        $reflection = new ReflectionClass(Get::class);
        $prop = $reflection->getProperty($property);

        return $prop->getValue();
    }

    private function setPrivateStatic(string $property, mixed $value): void
    {
        $reflection = new ReflectionClass(Get::class);
        $prop = $reflection->getProperty($property);
        $prop->setValue(null, $value);
    }
}
