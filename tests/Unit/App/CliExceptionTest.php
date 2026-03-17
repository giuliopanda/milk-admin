<?php
/**
 * PHPUnit Test for CLI Exception Handling
 *
 * This test suite validates the exception-based error handling in the CLI framework
 * Since CLI functions don't return values but use echo/print, we use output buffering
 * to capture and verify the output.
 * 
 *
 * php vendor/bin/phpunit tests/Unit/App/CliExceptionTest.php --testdox
 * Documentation: milkadmin/Modules/Docs/Pages/Framework/Core/cli.page.php
 *
 */

$projectRoot = dirname(__DIR__, 3);
if (!defined('MILK_DIR')) {
    require_once $projectRoot . '/vendor/autoload.php';
    define('MILK_DIR', realpath($projectRoot . '/milkadmin'));
    define('LOCAL_DIR', realpath($projectRoot . '/milkadmin_local'));
    require MILK_DIR . '/autoload.php';
}

use PHPUnit\Framework\TestCase;
use App\Cli;
use App\Exceptions\CliException;
use App\Exceptions\CliFunctionExecutionException;

class CliExceptionTest extends TestCase
{
    /**
     * Clear registered functions before each test to ensure isolation
     */
    protected function setUp(): void
    {
        // Reset the internal state by calling a non-existent function
        // and catching the exception (this ensures clean state)
        try {
            Cli::callFunction('__reset__');
        } catch (CliException $e) {
            // Expected, just to ensure clean slate
        }
    }

    /**
     * Test 1: Normal function registration and execution
     */
    public function testNormalFunctionRegistration()
    {
        // Register a simple function
        Cli::set('test_hello', function($name) {
            echo "Hello, " . ($name ?? 'World') . "!";
        });

        // Verify function is registered
        $registeredFunctions = Cli::getAllFn();
        $this->assertContains('test_hello', $registeredFunctions,
            "Function 'test_hello' should be in registered functions list");

        // Capture output and verify execution
        ob_start();
        Cli::callFunction('test_hello', 'MilkAdmin');
        $output = ob_get_clean();

        $this->assertEquals("Hello, MilkAdmin!", $output,
            "Function should output the correct greeting");
    }

    /**
     * Test 2: Try to register with invalid name (empty string)
     */
    public function testInvalidFunctionNameEmpty()
    {
        $this->expectException(CliException::class);
        $this->expectExceptionMessage("Function name must be a non-empty string");

        Cli::set('', function() {
            echo "This should not run";
        });
    }

    /**
     * Test 3: Try to register non-callable
     */
    public function testNonCallableFunction()
    {
        $this->expectException(CliException::class);
        $this->expectExceptionMessageMatches("/must be callable/");

        Cli::set('test_invalid', 'this_function_does_not_exist');
    }

    /**
     * Test 4: Try to register duplicate function
     */
    public function testDuplicateFunctionRegistration()
    {
        // First registration should work
        Cli::set('test_duplicate', function() {
            echo "First function";
        });

        // Second registration should throw exception
        $this->expectException(CliException::class);
        $this->expectExceptionMessage("Function 'test_duplicate' is already registered");

        Cli::set('test_duplicate', function() {
            echo "Second function";
        });
    }

    /**
     * Test 5: Try to call non-existent function
     */
    public function testCallNonExistentFunction()
    {
        $this->expectException(CliException::class);
        $this->expectExceptionMessage("Function 'non_existent_function' is not registered");

        Cli::callFunction('non_existent_function');
    }

    /**
     * Test 6: Function that throws an exception during execution
     */
    public function testFunctionExecutionError()
    {
        Cli::set('test_error', function() {
            throw new \Exception("Something went wrong inside the function!");
        });

        try {
            Cli::callFunction('test_error');
            $this->fail("Should have thrown CliFunctionExecutionException");
        } catch (CliFunctionExecutionException $e) {
            $this->assertStringContainsString("test_error", $e->getMessage(),
                "Exception message should contain function name");
            $this->assertStringContainsString("execution failed", $e->getMessage(),
                "Exception message should indicate execution failure");

            // Verify previous exception is preserved
            $this->assertInstanceOf(\Exception::class, $e->getPrevious(),
                "Previous exception should be preserved");
            $this->assertEquals("Something went wrong inside the function!",
                $e->getPrevious()->getMessage(),
                "Original exception message should be preserved");
        }
    }

    /**
     * Test 7: Function that throws CliException (should be re-thrown as-is)
     */
    public function testFunctionThrowsCliException()
    {
        Cli::set('test_cli_error', function() {
            throw new CliException("Configuration error from inside function");
        });

        $this->expectException(CliException::class);
        $this->expectExceptionMessage("Configuration error from inside function");

        // Should NOT be wrapped in CliFunctionExecutionException
        Cli::callFunction('test_cli_error');
    }

    /**
     * Test 8: Advanced usage with multiple parameters
     */
    public function testFunctionWithMultipleParameters()
    {
        Cli::set('test_params', function($action, $value, $flag) {
            if ($action === 'error') {
                throw new \RuntimeException("Runtime error occurred!");
            }
            echo "Action: $action, Value: $value, Flag: " . ($flag ?? 'null');
        });

        // Test successful execution
        ob_start();
        Cli::callFunction('test_params', 'normal', '42', 'true');
        $output = ob_get_clean();

        $this->assertStringContainsString("Action: normal", $output);
        $this->assertStringContainsString("Value: 42", $output);
        $this->assertStringContainsString("Flag: true", $output);

        // Test error case
        try {
            Cli::callFunction('test_params', 'error', '0', 'false');
            $this->fail("Should have thrown CliFunctionExecutionException");
        } catch (CliFunctionExecutionException $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            $this->assertEquals("Runtime error occurred!", $e->getPrevious()->getMessage());
        }
    }

    /**
     * Test 9: Verify output methods work correctly
     */
    public function testOutputMethods()
    {
        // Test error output
        ob_start();
        Cli::error("Test error message");
        $errorOutput = ob_get_clean();
        $this->assertStringContainsString("Error:", $errorOutput);
        $this->assertStringContainsString("Test error message", $errorOutput);

        // Test success output
        ob_start();
        Cli::success("Test success message");
        $successOutput = ob_get_clean();
        $this->assertStringContainsString("Success:", $successOutput);
        $this->assertStringContainsString("Test success message", $successOutput);

        // Test echo output
        ob_start();
        Cli::echo("Test echo message");
        $echoOutput = ob_get_clean();
        $this->assertStringContainsString("Test echo message", $echoOutput);
    }

    /**
     * Test 10: Verify function with null parameters
     */
    public function testFunctionWithNullParameters()
    {
        Cli::set('test_null', function($required, $optional = null) {
            echo "Required: $required, Optional: " . ($optional ?? 'NULL');
        });

        ob_start();
        Cli::callFunction('test_null', 'value1');
        $output = ob_get_clean();

        $this->assertStringContainsString("Required: value1", $output);
        $this->assertStringContainsString("Optional: NULL", $output);
    }

    /**
     * Test 11: Verify getAllFn returns correct function names
     */
    public function testGetAllFnReturnsFunctionList()
    {
        // Register multiple functions
        Cli::set('func1', function() {});
        Cli::set('func2', function() {});
        Cli::set('func3', function() {});

        $allFunctions = Cli::getAllFn();

        $this->assertContains('func1', $allFunctions);
        $this->assertContains('func2', $allFunctions);
        $this->assertContains('func3', $allFunctions);
        $this->assertGreaterThanOrEqual(3, count($allFunctions));
    }

    /**
     * Test 12: Verify isCli method
     */
    public function testIsCliMethod()
    {
        // In PHPUnit context, we should be running in CLI mode
        $this->assertTrue(Cli::isCli(),
            "isCli should return true when running from command line");
    }

    /**
     * Test 13: Test drawTable method doesn't crash
     */
    public function testDrawTableMethod()
    {
        $data = [
            ['id' => 1, 'name' => 'Test 1', 'status' => 'active'],
            ['id' => 2, 'name' => 'Test 2', 'status' => 'inactive'],
        ];

        ob_start();
        Cli::drawTable($data);
        $output = ob_get_clean();

        $this->assertNotEmpty($output, "drawTable should produce output");
        $this->assertStringContainsString('Test 1', $output);
        $this->assertStringContainsString('Test 2', $output);
    }

    /**
     * Test 14: Test drawTitle method doesn't crash
     */
    public function testDrawTitleMethod()
    {
        ob_start();
        Cli::drawTitle("Test Title");
        $output = ob_get_clean();

        $this->assertNotEmpty($output, "drawTitle should produce output");
        $this->assertStringContainsString('Test Title', $output);
    }

    /**
     * Test 15: Test drawSeparator method doesn't crash
     */
    public function testDrawSeparatorMethod()
    {
        ob_start();
        Cli::drawSeparator("Test Section");
        $output = ob_get_clean();

        $this->assertNotEmpty($output, "drawSeparator should produce output");
        $this->assertStringContainsString('Test Section', $output);
    }
}
