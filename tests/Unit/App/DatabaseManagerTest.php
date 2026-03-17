<?php

declare(strict_types=1);

use App\Config;
use App\Database\ArrayDb;
use App\DatabaseManager;
use PHPUnit\Framework\TestCase;

final class DatabaseManagerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalConfig = [];
    protected function setUp(): void
    {
        $this->originalConfig = Config::getAll();

        Config::setAll([]);
        DatabaseManager::reset();
    }

    protected function tearDown(): void
    {
        Config::setAll($this->originalConfig);
        DatabaseManager::reset();
    }

    public function testConnectionAutoDiscoveryFromConfigAndReuse(): void
    {
        Config::setAll([
            'db_type' => 'array',
            'prefix' => 't_',
        ]);

        $first = DatabaseManager::connection();
        $second = DatabaseManager::connection('db');

        $this->assertInstanceOf(ArrayDb::class, $first);
        $this->assertSame($first, $second);
        $this->assertTrue(DatabaseManager::hasConnection('db'));
        $this->assertSame('array', DatabaseManager::getConnectionConfig('db')['type']);
    }

    public function testAddConnectionAndAvailableConnections(): void
    {
        DatabaseManager::addConnection('analytics', [
            'type' => 'array',
            'data' => ['users' => [['id' => 1, 'name' => 'Alice']]],
        ]);

        $this->assertTrue(DatabaseManager::hasConnection('analytics'));
        $this->assertContains('analytics', DatabaseManager::getAvailableConnections());

        $conn = DatabaseManager::connection('analytics');
        $this->assertInstanceOf(ArrayDb::class, $conn);
    }

    public function testAddConnectionValidationAndReplaceFlow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DatabaseManager::addConnection('bad', ['prefix' => 'x']);
    }

    public function testAddConnectionDuplicateThrowsWithoutReplace(): void
    {
        DatabaseManager::addConnection('dup', ['type' => 'array']);

        $this->expectException(InvalidArgumentException::class);
        DatabaseManager::addConnection('dup', ['type' => 'array']);
    }

    public function testAddConnectionCanReplaceExistingConfiguration(): void
    {
        DatabaseManager::addConnection('dup', ['type' => 'array', 'prefix' => 'a_']);
        DatabaseManager::addConnection('dup', ['type' => 'array', 'prefix' => 'b_'], true);

        $config = DatabaseManager::getConnectionConfig('dup');
        $this->assertSame('b_', $config['prefix']);
    }

    public function testDefaultConnectionAndRemoveDisconnectHelpers(): void
    {
        DatabaseManager::addConnection('first', ['type' => 'array']);
        DatabaseManager::addConnection('second', ['type' => 'array']);

        DatabaseManager::setDefaultConnection('second');
        $this->assertSame('second', DatabaseManager::getDefaultConnection());

        DatabaseManager::connection('first');
        DatabaseManager::connection('second');
        DatabaseManager::disconnect('first');
        DatabaseManager::disconnectAll();

        DatabaseManager::removeConnection('second');
        $this->assertFalse(DatabaseManager::hasConnection('second'));
    }

    public function testSetDefaultConnectionThrowsForUnknownConnection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DatabaseManager::setDefaultConnection('unknown');
    }

    public function testConnectionReturnsNullWhenNotConfigured(): void
    {
        $this->assertNull(DatabaseManager::connection('missing'));
        $this->assertFalse(DatabaseManager::hasConnection('missing'));
    }
}
