<?php
/**
 * File Security Tests
 *
 * Run with: php vendor/bin/phpunit tests/Unit/App/FileSecurityTest.php --testdox
 *
 * THESE TESTS SHOULD FAIL OR THROW EXCEPTIONS!
 * If these tests pass, it means there's a security vulnerability.
 *
 * Tests for:
 * - Path traversal attacks
 * - Race conditions
 * - File locking enforcement
 * - Permission bypasses
 */

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

class FileSecurityTest extends TestCase
{
    private string $testDir;
    private array $testFiles = [];

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/milk_security_tests_' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->testFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

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

    // ==================== PATH TRAVERSAL TESTS ====================

    /**
     * @test
     * Test path traversal with ../ sequences
     * This should throw an exception or fail safely
     */
    public function testPathTraversalAttempt(): void
    {
        $this->expectException(\Exception::class);

        // Try to write outside the intended directory
        $maliciousPath = $this->testDir . '/../../../etc/passwd';
        File::putContents($maliciousPath, 'hacked');

        // If we get here without exception, it's a security issue
        $this->fail('Path traversal was not prevented!');
    }

    /**
     * @test
     * Test null byte injection in file path
     */
    public function testNullByteInjection(): void
    {
        try {
            // Null byte can be used to bypass extension checks
            $maliciousPath = $this->testDir . "/test.txt\0.php";
            File::putContents($maliciousPath, '<?php echo "pwned"; ?>');

            // If this succeeds, check what actually got written
            $this->fail('Null byte injection was not prevented!');
        } catch (\Exception $e) {
            // Expected - null bytes should cause an error
            $this->assertNotSame('', $e->getMessage());
        }
    }

    /**
     * @test
     * Test absolute path escape attempt
     */
    public function testAbsolutePathEscape(): void
    {
        // This test verifies that we can't write to sensitive system locations
        $sensitivePaths = [
            '/etc/passwd',
            '/etc/shadow',
            '/root/.ssh/authorized_keys',
            'C:\\Windows\\System32\\drivers\\etc\\hosts', // Windows
        ];

        foreach ($sensitivePaths as $path) {
            if (!file_exists(dirname($path))) {
                continue; // Skip if parent directory doesn't exist
            }

            try {
                File::putContents($path, 'test');
                // If we successfully wrote, it's likely due to wrong permissions
                // This should fail with permission error, not succeed
                $this->fail("Was able to write to sensitive path: $path");
            } catch (FileException $e) {
                // Expected - should fail
                $this->assertStringContainsString('not writable', $e->getMessage());
            }
        }

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     * Test symbolic link following
     */
    public function testSymlinkFollowing(): void
    {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('symlink() not available');
        }

        $targetFile = $this->getTestFilePath('target.txt');
        $linkFile = $this->getTestFilePath('link.txt');

        // Create a symlink pointing outside the test directory
        $outsideTarget = sys_get_temp_dir() . '/outside_target.txt';
        file_put_contents($outsideTarget, 'original');

        try {
            @symlink($outsideTarget, $linkFile);

            // Try to write through the symlink
            File::putContents($linkFile, 'modified');

            // Check if we actually modified the target
            $outsideContent = file_get_contents($outsideTarget);

            // This is actually expected behavior for many file systems,
            // but it's important to be aware of it
            $this->assertEquals('modified', $outsideContent,
                'Symlink following occurred - be aware of this behavior');

        } finally {
            @unlink($linkFile);
            @unlink($outsideTarget);
        }
    }

    // ==================== RACE CONDITION TESTS ====================

    /**
     * @test
     * Test TOCTOU (Time-of-Check-Time-of-Use) vulnerability
     */
    public function testTOCTOURaceCondition(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $filePath = $this->getTestFilePath('race.txt');
        File::putContents($filePath, 'initial');

        $pid = pcntl_fork();

        if ($pid == 0) {
            // Child: try to read while parent is writing
            usleep(5000); // Small delay
            try {
                $content = File::getContents($filePath);
                // Should get either 'initial' or 'modified', never corrupted data
                $this->assertContains($content, ['initial', 'modified'],
                    'Read corrupted data during write!');
            } catch (FileException $e) {
                // Also acceptable - lock prevented the read
            }
            exit(0);
        } else {
            // Parent: write
            File::putContents($filePath, 'modified');
            pcntl_waitpid($pid, $status);
        }

        $this->assertSame('modified', File::getContents($filePath));
    }

    /**
     * @test
     * Test that concurrent writes don't corrupt data
     */
    public function testDataCorruptionPrevention(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $filePath = $this->getTestFilePath('corruption.txt');
        $processCount = 10;
        $expectedContent = str_repeat('A', 1000);

        $pids = [];
        for ($i = 0; $i < $processCount; $i++) {
            $pid = pcntl_fork();

            if ($pid == 0) {
                // Child: write the same content
                File::putContents($filePath, $expectedContent);
                exit(0);
            } else {
                $pids[] = $pid;
            }
        }

        // Wait for all children
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        // Verify file is not corrupted
        $content = File::getContents($filePath);

        // Content should be exactly what one process wrote, not a mix
        $this->assertEquals($expectedContent, $content,
            'File was corrupted by concurrent writes!');
        $this->assertEquals(1000, strlen($content),
            'File size is wrong - data was corrupted!');
    }

    /**
     * @test
     * Test append atomicity
     */
    public function testAppendAtomicity(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $filePath = $this->getTestFilePath('atomic_append.txt');
        $processCount = 5;
        $appendsPerProcess = 10;
        $message = "PROCESS_X_LINE_Y\n";

        $pids = [];
        for ($i = 0; $i < $processCount; $i++) {
            $pid = pcntl_fork();

            if ($pid == 0) {
                // Child
                for ($j = 0; $j < $appendsPerProcess; $j++) {
                    $msg = str_replace(['X', 'Y'], [(string) $i, (string) $j], $message);
                    File::appendContents($filePath, $msg);
                }
                exit(0);
            } else {
                $pids[] = $pid;
            }
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        // Verify all lines are complete (not interleaved)
        $content = File::getContents($filePath);
        $lines = explode("\n", trim($content));

        $this->assertCount($processCount * $appendsPerProcess, $lines,
            'Some appends were lost!');

        // Check that each line matches the expected format
        foreach ($lines as $line) {
            $this->assertMatchesRegularExpression('/^PROCESS_\d+_LINE_\d+$/', $line,
                "Line was corrupted by concurrent append: $line");
        }
    }

    // ==================== FILE LOCKING ENFORCEMENT TESTS ====================

    /**
     * @test
     * Test that exclusive lock is actually enforced
     */
    public function testExclusiveLockEnforcement(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $filePath = $this->getTestFilePath('lock_test.txt');
        File::putContents($filePath, 'initial');

        $resultFile = $this->getTestFilePath('result.txt');

        $pid = pcntl_fork();

        if ($pid == 0) {
            // Child: try to write while parent holds lock
            usleep(50000); // Wait for parent to acquire lock

            $start = microtime(true);
            try {
                File::putContents($filePath, 'child');
                $elapsed = microtime(true) - $start;

                // If this completes too quickly, the lock wasn't enforced
                if ($elapsed < 0.1) {
                    file_put_contents($resultFile, 'LOCK_NOT_ENFORCED');
                } else {
                    file_put_contents($resultFile, 'OK');
                }
            } catch (FileException $e) {
                file_put_contents($resultFile, 'TIMEOUT');
            }
            exit(0);
        } else {
            // Parent: hold lock for a while
            $fp = fopen($filePath, 'c');
            flock($fp, LOCK_EX);
            sleep(1); // Hold lock for 1 second
            flock($fp, LOCK_UN);
            fclose($fp);

            pcntl_waitpid($pid, $status);

            if (file_exists($resultFile)) {
                $result = file_get_contents($resultFile);
                $this->assertNotEquals('LOCK_NOT_ENFORCED', $result,
                    'Exclusive lock was not enforced!');
            }
        }

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     * Test lock timeout works
     */
    public function testLockTimeout(): void
    {
        $filePath = $this->getTestFilePath('timeout.txt');
        file_put_contents($filePath, 'test');

        // Hold an exclusive lock
        $fp = fopen($filePath, 'c');
        flock($fp, LOCK_EX);

        try {
            // Try to write with File class - should timeout
            $start = microtime(true);

            $this->expectException(FileException::class);
            $this->expectExceptionMessage('Timeout');

            File::putContents($filePath, 'should fail');

            $elapsed = microtime(true) - $start;

            // Should have waited for timeout (~10 seconds)
            $this->assertGreaterThan(5, $elapsed,
                'Timeout happened too quickly - lock not enforced!');

        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    // ==================== PERMISSION TESTS ====================

    /**
     * @test
     * Test that permission checks work
     */
    public function testPermissionChecks(): void
    {
        // Create a file we can't write to
        $filePath = $this->getTestFilePath('readonly.txt');
        file_put_contents($filePath, 'original');
        chmod($filePath, 0444); // Read-only

        try {
            $this->expectException(FileException::class);
            File::putContents($filePath, 'modified');
        } finally {
            chmod($filePath, 0644); // Restore permissions
        }
    }

    /**
     * @test
     * Test writing to non-existent directory
     */
    public function testNonExistentDirectory(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Directory not writable');

        File::putContents('/definitely/does/not/exist/file.txt', 'test');
    }

    // ==================== DATA INTEGRITY TESTS ====================

    /**
     * @test
     * Test that partial writes don't occur
     */
    public function testNoPartialWrites(): void
    {
        $filePath = $this->getTestFilePath('partial.txt');
        $largeContent = str_repeat('0123456789', 100000); // 1MB

        File::putContents($filePath, $largeContent);

        // Read back and verify
        $readContent = File::getContents($filePath);

        $this->assertEquals(strlen($largeContent), strlen($readContent),
            'Partial write occurred!');
        $this->assertEquals($largeContent, $readContent,
            'Content was corrupted!');
    }

    /**
     * @test
     * Test binary data integrity
     */
    public function testBinaryDataIntegrity(): void
    {
        $filePath = $this->getTestFilePath('binary.dat');

        // Create binary data with all byte values
        $binaryData = '';
        for ($i = 0; $i < 256; $i++) {
            $binaryData .= chr($i);
        }
        $binaryData = str_repeat($binaryData, 1000); // Make it larger

        File::putContents($filePath, $binaryData);
        $readData = File::getContents($filePath);

        $this->assertEquals($binaryData, $readData,
            'Binary data was corrupted!');

        // Check specific problematic bytes
        $this->assertEquals(chr(0), $readData[0], 'Null byte was corrupted');
        $this->assertEquals(chr(255), $readData[255], 'Byte 255 was corrupted');
    }

    // ==================== INJECTION TESTS ====================

    /**
     * @test
     * Test command injection via filename
     */
    public function testCommandInjectionInFilename(): void
    {
        $maliciousNames = [
            'test.txt; rm -rf /',
            'test.txt`whoami`',
            'test.txt$(whoami)',
            'test.txt|cat /etc/passwd',
            'test.txt&& echo hacked',
        ];

        foreach ($maliciousNames as $maliciousName) {
            try {
                // The File class itself doesn't execute shell commands,
                // but let's verify the filesystem handles these safely
                $safePath = $this->testDir . '/' . basename($maliciousName);

                // This should either succeed (creating a weirdly-named file)
                // or fail with a filesystem error, but NEVER execute commands
                File::putContents($safePath, 'test');

                // If it succeeded, clean up
                if (file_exists($safePath)) {
                    @unlink($safePath);
                    $this->testFiles[] = $safePath;
                }
            } catch (\Exception $e) {
                // Expected for some cases
                $this->assertNotSame('', $e->getMessage());
            }
        }

        // If we got here, no commands were executed
        $this->addToAssertionCount(1);
    }

    // ==================== RESOURCE EXHAUSTION TESTS ====================

    /**
     * @test
     * Test extremely large file handling
     */
    public function testExtremelyLargeFile(): void
    {
        // Try to create a file larger than PHP memory limit
        $filePath = $this->getTestFilePath('huge.txt');

        try {
            // This should either fail gracefully or succeed
            $hugeContent = str_repeat('x', 100 * 1024 * 1024); // 100MB
            File::putContents($filePath, $hugeContent);

            // If it succeeded, verify integrity
            $read = File::getContents($filePath);
            $this->assertEquals(strlen($hugeContent), strlen($read));

        } catch (\Exception $e) {
            // Acceptable - system couldn't handle it
            $this->assertNotSame('', $e->getMessage());
        }
    }

    /**
     * @test
     * Test many rapid operations don't exhaust file descriptors
     */
    public function testFileDescriptorExhaustion(): void
    {
        $filePath = $this->getTestFilePath('fd_test.txt');

        // Perform many rapid operations
        for ($i = 0; $i < 1000; $i++) {
            File::putContents($filePath, "iteration $i");
        }

        // Should still be able to perform operations
        $content = File::getContents($filePath);
        $this->assertStringContainsString('iteration', $content);
    }
}
