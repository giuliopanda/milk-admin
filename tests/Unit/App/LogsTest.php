<?php

declare(strict_types=1);

use App\Logs;
use PHPUnit\Framework\TestCase;

final class LogsTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalLogs = [];
    private int $originalMinLevel = 5;
    private bool $originalEnableBacktrace = true;

    protected function setUp(): void
    {
        $this->originalLogs = $this->getPrivateStatic('logs');
        $this->originalMinLevel = $this->getPrivateStatic('min_level');
        $this->originalEnableBacktrace = $this->getPrivateStatic('enable_backtrace');

        $this->setPrivateStatic('logs', []);
        Logs::configure([
            'min_level' => Logs::DEBUG,
            'enable_backtrace' => false,
        ]);
    }

    protected function tearDown(): void
    {
        $this->setPrivateStatic('logs', $this->originalLogs);
        $this->setPrivateStatic('min_level', $this->originalMinLevel);
        $this->setPrivateStatic('enable_backtrace', $this->originalEnableBacktrace);
    }

    public function testSetStoresLogEntryInGroup(): void
    {
        Logs::set('SYSTEM', 'Application started', Logs::INFO);
        $logs = Logs::get('SYSTEM');

        $this->assertCount(1, $logs);
        $this->assertSame('INFO', $logs[0]['msgType']);
        $this->assertSame('Application started', $logs[0]['msg']);
        $this->assertSame([], $logs[0]['in']);
    }

    public function testConfigureMinLevelFiltersLessCriticalEntries(): void
    {
        Logs::configure(['min_level' => Logs::WARNING, 'enable_backtrace' => false]);

        Logs::debug('APP', 'debug');
        Logs::info('APP', 'info');
        Logs::success('APP', 'success');
        Logs::warning('APP', 'warning');
        Logs::error('APP', 'error');

        $appLogs = Logs::get('APP');
        $this->assertCount(2, $appLogs);
        $this->assertSame(['WARNING', 'ERROR'], array_column($appLogs, 'msgType'));
    }

    public function testGetByTypeAndGetAllErrors(): void
    {
        Logs::error('DB', 'connection failed');
        Logs::error('AUTH', 'invalid credentials');
        Logs::warning('AUTH', 'slow response');

        $errors = Logs::getByType(Logs::ERROR);

        $this->assertCount(2, $errors);
        $this->assertCount(2, Logs::getAllErrors());
    }

    public function testCountSupportsGroupAndTypeFilters(): void
    {
        Logs::info('A', 'a1');
        Logs::error('A', 'a2');
        Logs::error('B', 'b1');

        $this->assertSame(3, Logs::count());
        $this->assertSame(2, Logs::count('A'));
        $this->assertSame(1, Logs::count('A', Logs::ERROR));
        $this->assertSame(2, Logs::count(null, Logs::ERROR));
    }

    public function testClearRemovesOneGroupOrAllGroups(): void
    {
        Logs::info('A', 'a');
        Logs::info('B', 'b');

        Logs::clear('A');
        $this->assertSame([], Logs::get('A'));
        $this->assertCount(1, Logs::get('B'));

        Logs::clear();
        $this->assertSame([], Logs::getAll());
    }

    public function testToJsonExportsLogs(): void
    {
        Logs::warning('SECURITY', 'csrf mismatch');

        $all = json_decode(Logs::toJson(), true);
        $group = json_decode(Logs::toJson('SECURITY'), true);

        $this->assertIsArray($all);
        $this->assertIsArray($group);
        $this->assertSame('csrf mismatch', $group[0]['msg']);
    }

    public function testCleanStrAndLogStrRoundTrip(): void
    {
        $clean = Logs::cleanStr("  hello \"world\"\n ");
        $this->assertSame('"hello \"world\""', $clean);
        $this->assertSame('hello "world', Logs::logStr($clean));

        $this->assertSame('{"a":1}', Logs::cleanStr(['a' => 1]));
    }

    /**
     * @return mixed
     */
    private function getPrivateStatic(string $property)
    {
        $reflection = new ReflectionClass(Logs::class);
        $prop = $reflection->getProperty($property);

        return $prop->getValue();
    }

    private function setPrivateStatic(string $property, mixed $value): void
    {
        $reflection = new ReflectionClass(Logs::class);
        $prop = $reflection->getProperty($property);
        $prop->setValue(null, $value);
    }
}
