<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use App\Config;
use App\Database\ArrayDb;
use App\DatabaseManager;
use App\Get;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DatabaseManagerIntegrationTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConfig = Config::getAll();

        Config::setAll([]);
        DatabaseManager::reset();
    }

    protected function tearDown(): void
    {
        Config::setAll($this->originalConfig);
        DatabaseManager::reset();

        parent::tearDown();
    }

    public function testAutoDiscoveryHandlesPrimaryAndSecondaryConnections(): void
    {
        Config::setAll([
            'db_type' => 'array',
            'prefix' => 'main_',
            'db_type2' => 'array',
            'prefix2' => 'secondary_',
        ]);

        $primary = DatabaseManager::connection();
        $secondary = DatabaseManager::connection('db2');

        $this->assertInstanceOf(ArrayDb::class, $primary);
        $this->assertInstanceOf(ArrayDb::class, $secondary);
        $this->assertContains('db', DatabaseManager::getAvailableConnections());
        $this->assertContains('db2', DatabaseManager::getAvailableConnections());
        $this->assertSame('db', DatabaseManager::getDefaultConnection());
    }

    public function testRuntimeConnectionsCanBeRegisteredListedAndReused(): void
    {
        DatabaseManager::addConnection('analytics', ['type' => 'array', 'prefix' => 'a_']);
        DatabaseManager::addConnection('logging', ['type' => 'array', 'prefix' => 'l_']);
        DatabaseManager::addConnection('cache', ['type' => 'array', 'prefix' => 'c_']);

        $analyticsFirst = DatabaseManager::connection('analytics');
        $analyticsSecond = DatabaseManager::connection('analytics');
        $logging = DatabaseManager::connection('logging');

        $this->assertInstanceOf(ArrayDb::class, $analyticsFirst);
        $this->assertSame($analyticsFirst, $analyticsSecond);
        $this->assertNotSame($analyticsFirst, $logging);
        $this->assertContains('analytics', DatabaseManager::getAvailableConnections());
        $this->assertContains('logging', DatabaseManager::getAvailableConnections());
        $this->assertContains('cache', DatabaseManager::getAvailableConnections());
    }

    public function testDuplicateRegistrationAndReplacementFlow(): void
    {
        DatabaseManager::addConnection('test_runtime', ['type' => 'array', 'prefix' => 'old_']);

        $this->expectException(InvalidArgumentException::class);
        DatabaseManager::addConnection('test_runtime', ['type' => 'array', 'prefix' => 'new_']);
    }

    public function testReplaceUpdatesExistingConnectionConfiguration(): void
    {
        DatabaseManager::addConnection('test_runtime', ['type' => 'array', 'prefix' => 'old_']);
        DatabaseManager::addConnection('test_runtime', ['type' => 'array', 'prefix' => 'new_'], true);

        $config = DatabaseManager::getConnectionConfig('test_runtime');

        $this->assertSame('new_', $config['prefix']);
    }

    public function testBackwardCompatibilityHelpersStillUseRegisteredConnections(): void
    {
        Config::setAll([
            'db_type' => 'array',
            'prefix' => 'main_',
            'db_type2' => 'array',
            'prefix2' => 'secondary_',
        ]);

        DatabaseManager::addConnection('analytics', ['type' => 'array', 'prefix' => 'a_']);

        $this->assertInstanceOf(ArrayDb::class, Get::db());
        $this->assertInstanceOf(ArrayDb::class, Get::db2());
        $this->assertInstanceOf(ArrayDb::class, Get::dbConnection('analytics'));
    }

    public function testDisconnectKeepsConfigurationWhileRemoveDeletesIt(): void
    {
        DatabaseManager::addConnection('logging', ['type' => 'array', 'prefix' => 'l_']);
        DatabaseManager::connection('logging');

        DatabaseManager::disconnect('logging');
        $this->assertTrue(DatabaseManager::hasConnection('logging'));

        DatabaseManager::removeConnection('logging');
        $this->assertFalse(DatabaseManager::hasConnection('logging'));
    }
}
