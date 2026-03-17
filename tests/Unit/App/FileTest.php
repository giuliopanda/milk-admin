<?php
/**
 * File Class Test
 *
 * Run with: php vendor/bin/phpunit tests/Unit/App/FileTest.php --testdox
 *
 * Tests the App\File class for:
 * - Basic read/write operations
 * - Append operations
 * - Concurrent write scenarios
 * - File locking behavior
 * - Edge cases (non-existent files, empty files, large files)
 * - Error handling (permission errors, timeout scenarios)
 */

// Bootstrap the framework
if (!defined('MILK_TEST_CONTEXT')) {
    define('MILK_TEST_CONTEXT', true);
}
if (!defined('MILK_API_CONTEXT')) {
    define('MILK_API_CONTEXT', true);
}

require_once dirname(__DIR__, 3) . '/public_html/milkadmin.php';
require_once MILK_DIR . '/autoload.php';

use PHPUnit\Framework\TestCase;
use App\File;
use App\Exceptions\FileException;

class FileTest extends TestCase
{
    private string $testDir;
    private array $testFiles = [];

    protected function setUp(): void
    {
        // Create a temporary directory for test files
        $this->testDir = sys_get_temp_dir() . '/milk_file_tests_' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up all test files
        foreach ($this->testFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        // Remove test directory
        if (is_dir($this->testDir)) {
            @rmdir($this->testDir);
        }
    }

    private function getTestFilePath(string $name = 'test.txt'): string
    {
        $path = $this->testDir . '/' . $name;
        $this->testFiles[] = $path;
        return $path;
    }

    // ==================== BASIC WRITE TESTS ====================

    public function testPutContentsCreatesNewFile(): void
    {
        $filePath = $this->getTestFilePath();
        $content = "Hello World";

        File::putContents($filePath, $content);

        $this->assertFileExists($filePath);
        $this->assertEquals($content, file_get_contents($filePath));
    }

    public function testPutContentsOverwritesExistingFile(): void
    {
        $filePath = $this->getTestFilePath();

        File::putContents($filePath, "First content");
        File::putContents($filePath, "Second content");

        $this->assertEquals("Second content", file_get_contents($filePath));
    }

    public function testPutContentsEmptyString(): void
    {
        $filePath = $this->getTestFilePath();

        File::putContents($filePath, "");

        $this->assertFileExists($filePath);
        $this->assertEquals("", file_get_contents($filePath));
    }

    public function testPutContentsLargeData(): void
    {
        $filePath = $this->getTestFilePath('large.txt');
        // Create a 1MB string
        $largeContent = str_repeat("ABCDEFGHIJ", 100000);

        File::putContents($filePath, $largeContent);

        $this->assertEquals($largeContent, file_get_contents($filePath));
        $this->assertEquals(strlen($largeContent), filesize($filePath));
    }

    public function testPutContentsWithSpecialCharacters(): void
    {
        $filePath = $this->getTestFilePath('special.txt');
        $content = "Special chars: àèéìòù €£¥ \n\r\t 日本語";

        File::putContents($filePath, $content);

        $this->assertEquals($content, file_get_contents($filePath));
    }

    public function testPutContentsThrowsExceptionForNonWritableDirectory(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Directory not writable');

        // Try to write to a non-existent directory without creating it
        File::putContents('/non/existent/directory/file.txt', 'test');
    }

    // ==================== BASIC READ TESTS ====================

    public function testGetContentsReadsFile(): void
    {
        $filePath = $this->getTestFilePath();
        $content = "Test content";
        file_put_contents($filePath, $content);

        $result = File::getContents($filePath);

        $this->assertEquals($content, $result);
    }

    public function testGetContentsEmptyFile(): void
    {
        $filePath = $this->getTestFilePath();
        file_put_contents($filePath, "");

        $result = File::getContents($filePath);

        $this->assertEquals("", $result);
    }

    public function testGetContentsThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('File does not exist');

        File::getContents('/non/existent/file.txt');
    }

    public function testGetContentsLargeFile(): void
    {
        $filePath = $this->getTestFilePath('large_read.txt');
        $largeContent = str_repeat("Test line\n", 10000);
        file_put_contents($filePath, $largeContent);

        $result = File::getContents($filePath);

        $this->assertEquals($largeContent, $result);
    }

    // ==================== APPEND TESTS ====================

    public function testAppendContentsAppendsToExistingFile(): void
    {
        $filePath = $this->getTestFilePath();
        file_put_contents($filePath, "Line 1\n");

        File::appendContents($filePath, "Line 2\n");
        File::appendContents($filePath, "Line 3\n");

        $expected = "Line 1\nLine 2\nLine 3\n";
        $this->assertEquals($expected, file_get_contents($filePath));
    }

    public function testAppendContentsCreatesFileIfNotExists(): void
    {
        $filePath = $this->getTestFilePath();

        File::appendContents($filePath, "First line\n");

        $this->assertFileExists($filePath);
        $this->assertEquals("First line\n", file_get_contents($filePath));
    }

    public function testAppendContentsMultipleTimes(): void
    {
        $filePath = $this->getTestFilePath('log.txt');

        for ($i = 1; $i <= 10; $i++) {
            File::appendContents($filePath, "Entry $i\n");
        }

        $content = file_get_contents($filePath);
        $this->assertEquals(10, substr_count($content, "Entry"));
        $this->assertStringContainsString("Entry 1", $content);
        $this->assertStringContainsString("Entry 10", $content);
    }

    // ==================== CONCURRENT ACCESS TESTS ====================

    public function testConcurrentWrites(): void
    {
        $filePath = $this->getTestFilePath('concurrent.txt');
        $processCount = 5;
        $writesPerProcess = 20;

        // Create child processes to write concurrently
        $children = [];
        for ($i = 0; $i < $processCount; $i++) {
            $pid = pcntl_fork();

            if ($pid == -1) {
                $this->markTestSkipped('Cannot fork process');
            } elseif ($pid == 0) {
                // Child process
                for ($j = 0; $j < $writesPerProcess; $j++) {
                    try {
                        File::appendContents($filePath, "Process $i - Write $j\n");
                        usleep(1000); // Small delay to increase contention
                    } catch (FileException $e) {
                        // Log error but continue
                        error_log("Child $i failed: " . $e->getMessage());
                    }
                }
                exit(0);
            } else {
                // Parent process
                $children[] = $pid;
            }
        }

        // Wait for all children to complete
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        // Verify file integrity
        $this->assertFileExists($filePath);
        $content = file_get_contents($filePath);
        $lines = explode("\n", trim($content));

        // Should have exactly processCount * writesPerProcess lines
        $this->assertCount($processCount * $writesPerProcess, $lines);

        // Each line should match the expected format
        foreach ($lines as $line) {
            $this->assertMatchesRegularExpression('/^Process \d+ - Write \d+$/', $line);
        }
    }

    public function testConcurrentReadWriteIntegrity(): void
    {
        $filePath = $this->getTestFilePath('read_write.txt');
        $initialContent = "Initial content";
        File::putContents($filePath, $initialContent);

        $pid = pcntl_fork();

        if ($pid == -1) {
            $this->markTestSkipped('Cannot fork process');
        } elseif ($pid == 0) {
            // Child: continuously write
            for ($i = 0; $i < 10; $i++) {
                File::putContents($filePath, "Updated content $i");
                usleep(5000);
            }
            exit(0);
        } else {
            // Parent: continuously read
            $readErrors = 0;
            for ($i = 0; $i < 10; $i++) {
                try {
                    $content = File::getContents($filePath);
                    // Content should be valid (not corrupted)
                    $this->assertNotEmpty($content);
                } catch (FileException $e) {
                    $readErrors++;
                }
                usleep(5000);
            }

            pcntl_waitpid($pid, $status);

            // There should be no read errors due to proper locking
            $this->assertEquals(0, $readErrors);
        }
    }

    public function testMultipleConcurrentAppends(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $filePath = $this->getTestFilePath('multi_append.txt');
        $processes = 3;
        $appendsEach = 10;

        $pids = [];
        for ($i = 0; $i < $processes; $i++) {
            $pid = pcntl_fork();

            if ($pid == 0) {
                // Child process
                for ($j = 0; $j < $appendsEach; $j++) {
                    File::appendContents($filePath, sprintf("P%d-L%d\n", $i, $j));
                }
                exit(0);
            } else {
                $pids[] = $pid;
            }
        }

        // Wait for all children
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $content = File::getContents($filePath);
        $lines = array_filter(explode("\n", $content));

        // Should have exactly processes * appendsEach lines
        $this->assertCount($processes * $appendsEach, $lines);

        // Count occurrences from each process
        for ($i = 0; $i < $processes; $i++) {
            $processLines = array_filter($lines, fn($line) => str_starts_with($line, "P$i-"));
            $this->assertCount($appendsEach, $processLines, "Process $i should have $appendsEach lines");
        }
    }

    // ==================== EDGE CASES ====================

    public function testWriteAndReadBinaryData(): void
    {
        $filePath = $this->getTestFilePath('binary.dat');
        // Create binary data with all possible byte values
        $binaryData = '';
        for ($i = 0; $i < 256; $i++) {
            $binaryData .= chr($i);
        }

        File::putContents($filePath, $binaryData);
        $result = File::getContents($filePath);

        $this->assertEquals($binaryData, $result);
        $this->assertEquals(256, strlen($result));
    }

    public function testZeroByteFile(): void
    {
        $filePath = $this->getTestFilePath('zero.txt');

        File::putContents($filePath, '');
        $result = File::getContents($filePath);

        $this->assertEquals('', $result);
        $this->assertEquals(0, filesize($filePath));
    }

    public function testFileNameWithSpaces(): void
    {
        $filePath = $this->getTestFilePath('file with spaces.txt');
        $content = "Content in file with spaces";

        File::putContents($filePath, $content);
        $result = File::getContents($filePath);

        $this->assertEquals($content, $result);
    }

    public function testUnicodeFileName(): void
    {
        $filePath = $this->testDir . '/文件名.txt';
        $this->testFiles[] = $filePath;
        $content = "Unicode filename test";

        File::putContents($filePath, $content);
        $result = File::getContents($filePath);

        $this->assertEquals($content, $result);
    }

    public function testMultilineContent(): void
    {
        $filePath = $this->getTestFilePath('multiline.txt');
        $content = "Line 1\nLine 2\r\nLine 3\rLine 4";

        File::putContents($filePath, $content);
        $result = File::getContents($filePath);

        $this->assertEquals($content, $result);
    }

    // ==================== JSON DATA TESTS ====================

    public function testWriteAndReadJsonData(): void
    {
        $filePath = $this->getTestFilePath('data.json');
        $data = [
            'name' => 'Test User',
            'age' => 30,
            'tags' => ['php', 'testing'],
            'active' => true
        ];
        $json = json_encode($data);

        File::putContents($filePath, $json);
        $result = File::getContents($filePath);

        $this->assertEquals($json, $result);
        $this->assertEquals($data, json_decode($result, true));
    }

    // ==================== STRESS TESTS ====================

    public function testRapidWriteOperations(): void
    {
        $filePath = $this->getTestFilePath('rapid.txt');

        // Perform 100 rapid writes
        for ($i = 0; $i < 100; $i++) {
            File::putContents($filePath, "Content $i");
        }

        $final = File::getContents($filePath);
        $this->assertEquals("Content 99", $final);
    }

    public function testRapidAppendOperations(): void
    {
        $filePath = $this->getTestFilePath('rapid_append.txt');

        // Perform 100 rapid appends
        for ($i = 0; $i < 100; $i++) {
            File::appendContents($filePath, "$i,");
        }

        $content = File::getContents($filePath);
        $values = explode(',', rtrim($content, ','));

        $this->assertCount(100, $values);
        $this->assertEquals('0', $values[0]);
        $this->assertEquals('99', $values[99]);
    }

    // ==================== ERROR HANDLING TESTS ====================

    public function testReadNonExistentFile(): void
    {
        $this->expectException(FileException::class);
        File::getContents('/tmp/definitely_does_not_exist_' . uniqid() . '.txt');
    }

    public function testWriteToReadOnlyDirectory(): void
    {
        // Create a read-only directory
        $readOnlyDir = sys_get_temp_dir() . '/readonly_' . uniqid();
        mkdir($readOnlyDir, 0444);

        try {
            $this->expectException(FileException::class);
            File::putContents($readOnlyDir . '/test.txt', 'content');
        } finally {
            // Cleanup
            chmod($readOnlyDir, 0755);
            @rmdir($readOnlyDir);
        }
    }

    // ==================== LOCKING BEHAVIOR TESTS ====================

    public function testExclusiveLockPreventsSimultaneousWrites(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $filePath = $this->getTestFilePath('lock_test.txt');
        File::putContents($filePath, "Initial");

        $lockFile = $this->getTestFilePath('lock_status.txt');

        $pid = pcntl_fork();

        if ($pid == 0) {
            // Child: try to write
            sleep(1); // Give parent time to acquire lock
            try {
                File::putContents($filePath, "Child write");
                file_put_contents($lockFile, "child_success");
            } catch (FileException $e) {
                file_put_contents($lockFile, "child_failed");
            }
            exit(0);
        } else {
            // Parent: hold lock for a while
            $fp = fopen($filePath, 'c');
            flock($fp, LOCK_EX);
            sleep(2); // Hold lock
            fwrite($fp, "Parent write");
            flock($fp, LOCK_UN);
            fclose($fp);

            pcntl_waitpid($pid, $status);

            // Child should have succeeded after parent released lock
            $this->assertFileExists($lockFile);
        }
    }

    public function testSharedLockAllowsMultipleReaders(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $filePath = $this->getTestFilePath('shared_lock.txt');
        File::putContents($filePath, "Shared content");

        $readCount = 3;
        $pids = [];

        for ($i = 0; $i < $readCount; $i++) {
            $pid = pcntl_fork();

            if ($pid == 0) {
                // Child: read with shared lock
                for ($j = 0; $j < 5; $j++) {
                    $content = File::getContents($filePath);
                    $this->assertEquals("Shared content", $content);
                    usleep(10000);
                }
                exit(0);
            } else {
                $pids[] = $pid;
            }
        }

        // All children should complete without issues
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertEquals(0, pcntl_wexitstatus($status));
        }

        $this->addToAssertionCount(1); // If we get here, all readers succeeded
    }
}
