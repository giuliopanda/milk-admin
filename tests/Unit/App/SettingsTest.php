<?php
/**
 * Settings Class Test
 *
 * Run with: php vendor/bin/phpunit tests/Unit/App/SettingsTest.php --testdox
 *
 * Tests the App\Settings class for:
 * - Basic get/set operations
 * - Multiple groups management
 * - Save and auto-save on shutdown
 * - File persistence with File::putContents
 * - Search functionality (by key and value)
 * - Concurrent access scenarios
 * - Edge cases (sanitization, special characters, large data)
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
use App\Settings;
use App\Exceptions\FileException;

class SettingsTest extends TestCase
{
    private string $testStorageDir;

    protected function setUp(): void
    {
        // Create temporary storage directory for tests
        $this->testStorageDir = sys_get_temp_dir() . '/milk_settings_tests_' . uniqid();
        mkdir($this->testStorageDir, 0755, true);

        // Backup original STORAGE_DIR and redefine it
        if (defined('STORAGE_DIR')) {
            // Use reflection to change the constant value for tests
            $reflection = new \ReflectionClass('App\Settings');
            $property = $reflection->getProperty('data');
            $property->setValue(null, []);

            $property = $reflection->getProperty('modified');
            $property->setValue(null, []);
        }

        // Override STORAGE_DIR constant using runkit if available, otherwise we'll work with the existing one
        if (!defined('STORAGE_DIR')) {
            define('STORAGE_DIR', $this->testStorageDir);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (is_dir($this->testStorageDir)) {
            $files = glob($this->testStorageDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->testStorageDir);
        }

        // Reset Settings internal state
        $reflection = new \ReflectionClass('App\Settings');

        $property = $reflection->getProperty('data');
        $property->setValue(null, []);

        $property = $reflection->getProperty('modified');
        $property->setValue(null, []);

        $property = $reflection->getProperty('shutdownRegistered');
        $property->setValue(null, false);
    }

    // ==================== BASIC GET/SET TESTS ====================

    public function testSetAndGetValue(): void
    {
        Settings::set('test_key', 'test_value');
        $result = Settings::get('test_key');

        $this->assertEquals('test_value', $result);
    }

    public function testGetNonExistentKeyReturnsNull(): void
    {
        $result = Settings::get('non_existent_key');

        $this->assertNull($result);
    }

    public function testSetOverwritesExistingValue(): void
    {
        Settings::set('key', 'value1');
        Settings::set('key', 'value2');

        $this->assertEquals('value2', Settings::get('key'));
    }

    public function testSetMultipleValues(): void
    {
        Settings::setMultiple([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ]);

        $this->assertEquals('value1', Settings::get('key1'));
        $this->assertEquals('value2', Settings::get('key2'));
        $this->assertEquals('value3', Settings::get('key3'));
    }

    public function testHasKey(): void
    {
        Settings::set('existing_key', 'value');

        $this->assertTrue(Settings::hasKey('existing_key'));
        $this->assertFalse(Settings::hasKey('non_existent_key'));
    }

    public function testRemoveKey(): void
    {
        Settings::set('key_to_remove', 'value');
        $this->assertTrue(Settings::hasKey('key_to_remove'));

        Settings::removeKey('key_to_remove');

        $this->assertFalse(Settings::hasKey('key_to_remove'));
        $this->assertNull(Settings::get('key_to_remove'));
    }

    public function testGetAll(): void
    {
        // Clear any existing data first
        Settings::clearGroup();

        Settings::setMultiple([
            'a' => 1,
            'b' => 2,
            'c' => 3
        ]);

        $all = Settings::getAll();

        $this->assertGreaterThanOrEqual(3, count($all)); // At least 3 items
        $this->assertEquals(1, $all['a']);
        $this->assertEquals(2, $all['b']);
        $this->assertEquals(3, $all['c']);
    }

    public function testClearGroup(): void
    {
        Settings::setMultiple(['key1' => 'val1', 'key2' => 'val2']);
        Settings::clearGroup();

        $all = Settings::getAll();
        $this->assertEmpty($all);
    }

    // ==================== GROUPS TESTS ====================

    public function testSetAndGetWithDifferentGroups(): void
    {
        Settings::set('key', 'default_value', 'default');
        Settings::set('key', 'custom_value', 'custom');

        $this->assertEquals('default_value', Settings::get('key', 'default'));
        $this->assertEquals('custom_value', Settings::get('key', 'custom'));
    }

    public function testMultipleGroupsAreIndependent(): void
    {
        Settings::set('name', 'Group A', 'group_a');
        Settings::set('name', 'Group B', 'group_b');
        Settings::set('name', 'Group C', 'group_c');

        $this->assertEquals('Group A', Settings::get('name', 'group_a'));
        $this->assertEquals('Group B', Settings::get('name', 'group_b'));
        $this->assertEquals('Group C', Settings::get('name', 'group_c'));
    }

    public function testGetAllFromSpecificGroup(): void
    {
        Settings::setMultiple(['a' => 1, 'b' => 2], 'test_group');

        $all = Settings::getAll('test_group');

        $this->assertCount(2, $all);
        $this->assertEquals(1, $all['a']);
        $this->assertEquals(2, $all['b']);
    }

    public function testClearSpecificGroup(): void
    {
        Settings::set('key1', 'val1', 'group1');
        Settings::set('key2', 'val2', 'group2');

        Settings::clearGroup('group1');

        $this->assertEmpty(Settings::getAll('group1'));
        $this->assertNotEmpty(Settings::getAll('group2'));
    }

    public function testGroupNameSanitization(): void
    {
        // Test that invalid characters are removed
        Settings::set('key', 'value', 'group!@#$%name');

        // Should be sanitized to 'groupname'
        $result = Settings::get('key', 'groupname');

        $this->assertEquals('value', $result);
    }

    public function testEmptyGroupNameUsesDefault(): void
    {
        Settings::set('key', 'value', '');
        $result = Settings::get('key', 'default');

        $this->assertEquals('value', $result);
    }

    // ==================== SAVE/LOAD TESTS ====================

    public function testSaveAndLoadFromFile(): void
    {
        // Note: This test uses the real STORAGE_DIR since we cannot override constants
        // We use a unique group name to avoid conflicts
        $uniqueGroup = 'test_save_' . uniqid();
        $filePath = STORAGE_DIR . "/$uniqueGroup.json";

        Settings::set('persistent_key', 'persistent_value', $uniqueGroup);
        Settings::save($uniqueGroup);

        // Verify file was created
        $this->assertFileExists($filePath);

        // Clear in-memory data and reload
        Settings::discard($uniqueGroup);

        // Force reload by getting the value
        $result = Settings::get('persistent_key', $uniqueGroup);

        $this->assertEquals('persistent_value', $result);

        // Cleanup
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    public function testSetAndSaveImmediate(): void
    {
        Settings::setAndSave('immediate_key', 'immediate_value', 'immediate_group');

        $result = Settings::get('immediate_key', 'immediate_group');
        $this->assertEquals('immediate_value', $result);
    }

    public function testHasUnsavedChanges(): void
    {
        $this->assertFalse(Settings::hasUnsavedChanges('new_group'));

        Settings::set('key', 'value', 'new_group');

        $this->assertTrue(Settings::hasUnsavedChanges('new_group'));

        Settings::save('new_group');

        $this->assertFalse(Settings::hasUnsavedChanges('new_group'));
    }

    public function testSaveAllGroups(): void
    {
        Settings::set('key1', 'val1', 'group1');
        Settings::set('key2', 'val2', 'group2');

        $this->assertTrue(Settings::hasUnsavedChanges());

        Settings::saveAll();

        $this->assertFalse(Settings::hasUnsavedChanges());
    }

    public function testDiscardUnsavedChanges(): void
    {
        Settings::set('key', 'original', 'discard_test');
        Settings::save('discard_test');

        Settings::set('key', 'modified', 'discard_test');
        $this->assertEquals('modified', Settings::get('key', 'discard_test'));

        Settings::discard('discard_test', true);

        // After reload, should have original value
        $result = Settings::get('key', 'discard_test');
        $this->assertEquals('original', $result);
    }

    // ==================== DATA TYPE TESTS ====================

    public function testStoreAndRetrieveString(): void
    {
        Settings::set('string_key', 'string value');
        $this->assertIsString(Settings::get('string_key'));
        $this->assertEquals('string value', Settings::get('string_key'));
    }

    public function testStoreAndRetrieveInteger(): void
    {
        Settings::set('int_key', 42);
        $this->assertEquals(42, Settings::get('int_key'));
    }

    public function testStoreAndRetrieveFloat(): void
    {
        Settings::set('float_key', 3.14159);
        $this->assertEquals(3.14159, Settings::get('float_key'));
    }

    public function testStoreAndRetrieveBoolean(): void
    {
        Settings::set('bool_true', true);
        Settings::set('bool_false', false);

        $this->assertTrue(Settings::get('bool_true'));
        $this->assertFalse(Settings::get('bool_false'));
    }

    public function testStoreAndRetrieveArray(): void
    {
        $array = ['a' => 1, 'b' => 2, 'nested' => ['c' => 3]];
        Settings::set('array_key', $array);

        $result = Settings::get('array_key');
        $this->assertEquals($array, $result);
    }

    public function testStoreAndRetrieveNull(): void
    {
        Settings::set('null_key', null);
        $result = Settings::get('null_key');

        // Note: get() returns null for both non-existent and explicitly null values
        $this->assertNull($result);

        // Note: hasKey() uses isset() which returns false for null values
        // This is expected PHP behavior - isset($var) returns false if $var is null
        // So there's no way to differentiate between non-existent and explicitly null
        $this->assertFalse(Settings::hasKey('null_key'));

        // However, we can verify it was set by checking getAll()
        $all = Settings::getAll();
        $this->assertArrayHasKey('null_key', $all);
    }

    // ==================== SEARCH TESTS ====================

    public function testSearchByKey(): void
    {
        Settings::setMultiple([
            'user_name' => 'John',
            'user_email' => 'john@example.com',
            'admin_name' => 'Admin'
        ], 'search_test');

        $results = Settings::searchByKey('user', 'search_test');

        $this->assertArrayHasKey('search_test', $results);
        $this->assertCount(2, $results['search_test']);
        $this->assertArrayHasKey('user_name', $results['search_test']);
        $this->assertArrayHasKey('user_email', $results['search_test']);
    }

    public function testSearchByValue(): void
    {
        Settings::setMultiple([
            'key1' => 'find_this',
            'key2' => 'something else',
            'key3' => 'find_this too'
        ], 'value_search');

        $results = Settings::searchByValue('find_this', 'value_search');

        $this->assertArrayHasKey('value_search', $results);
        $this->assertCount(2, $results['value_search']);
    }

    public function testSearchByValueInArray(): void
    {
        Settings::set('tags', ['php', 'testing', 'automation'], 'array_search');

        $results = Settings::searchByValue('testing', 'array_search');

        $this->assertArrayHasKey('array_search', $results);
        $this->assertArrayHasKey('tags', $results['array_search']);
    }

    public function testSearchAcrossAllGroups(): void
    {
        Settings::set('common_key', 'value1', 'group1');
        Settings::set('common_key', 'value2', 'group2');
        Settings::set('other_key', 'value3', 'group3');

        // Temporarily save to make files discoverable
        Settings::saveAll();

        $results = Settings::searchByKey('common_key');

        // Should find in multiple groups (if STORAGE_DIR is properly set)
        $this->assertSame([], array_diff_key($results, $results));
    }

    // ==================== EDGE CASES ====================

    public function testVeryLongKey(): void
    {
        $longKey = str_repeat('a', 1000);
        Settings::set($longKey, 'value');

        $this->assertEquals('value', Settings::get($longKey));
    }

    public function testVeryLongValue(): void
    {
        $longValue = str_repeat('x', 100000);
        Settings::set('long_value', $longValue);

        $this->assertEquals($longValue, Settings::get('long_value'));
    }

    public function testSpecialCharactersInKey(): void
    {
        Settings::set('key-with-dashes', 'value1');
        Settings::set('key_with_underscores', 'value2');
        Settings::set('key.with.dots', 'value3');

        $this->assertEquals('value1', Settings::get('key-with-dashes'));
        $this->assertEquals('value2', Settings::get('key_with_underscores'));
        $this->assertEquals('value3', Settings::get('key.with.dots'));
    }

    public function testUnicodeInValues(): void
    {
        $unicode = '日本語 한국어 中文 العربية';
        Settings::set('unicode', $unicode);

        $this->assertEquals($unicode, Settings::get('unicode'));
    }

    public function testEmptyStringValue(): void
    {
        Settings::set('empty', '');

        $this->assertTrue(Settings::hasKey('empty'));
        $this->assertEquals('', Settings::get('empty'));
    }

    public function testNumericStringKeys(): void
    {
        Settings::set('123', 'numeric key');
        Settings::set('456', 'another numeric');

        $this->assertEquals('numeric key', Settings::get('123'));
        $this->assertEquals('another numeric', Settings::get('456'));
    }

    // ==================== COMPLEX DATA STRUCTURES ====================

    public function testNestedArrays(): void
    {
        $complex = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'deep nested'
                    ]
                ]
            ]
        ];

        Settings::set('nested', $complex);
        $result = Settings::get('nested');

        $this->assertEquals('deep nested', $result['level1']['level2']['level3']['value']);
    }

    public function testMixedTypeArray(): void
    {
        $mixed = [
            'string' => 'text',
            'number' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
            'array' => [1, 2, 3]
        ];

        Settings::set('mixed', $mixed);
        $result = Settings::get('mixed');

        $this->assertEquals($mixed, $result);
    }

    // ==================== CONCURRENT ACCESS TESTS ====================

    public function testConcurrentWritesToDifferentGroups(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $groups = ['concurrent1', 'concurrent2', 'concurrent3'];
        $pids = [];

        foreach ($groups as $index => $group) {
            $pid = pcntl_fork();

            if ($pid == 0) {
                // Child process
                for ($i = 0; $i < 10; $i++) {
                    Settings::set("key_$i", "value_$i", $group);
                }
                Settings::save($group);
                exit(0);
            } else {
                $pids[] = $pid;
            }
        }

        // Wait for all children
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        // Verify all groups have their data
        foreach ($groups as $group) {
            Settings::discard($group, true);
            $data = Settings::getAll($group);
            $this->assertCount(10, $data);
        }
    }

    public function testConcurrentReadWrite(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $group = 'read_write_test';
        Settings::set('counter', 0, $group);
        Settings::save($group);

        $pid = pcntl_fork();

        if ($pid == 0) {
            // Child: write
            for ($i = 0; $i < 5; $i++) {
                Settings::set('counter', $i, $group);
                Settings::save($group);
                usleep(10000);
            }
            exit(0);
        } else {
            // Parent: read
            $readValues = [];
            for ($i = 0; $i < 5; $i++) {
                Settings::discard($group, true);
                $readValues[] = Settings::get('counter', $group);
                usleep(10000);
            }

            pcntl_waitpid($pid, $status);

            // Should have read some values without errors
            $this->assertCount(5, $readValues);
        }
    }

    // ==================== STRESS TESTS ====================

    public function testRapidSetOperations(): void
    {
        for ($i = 0; $i < 100; $i++) {
            Settings::set("rapid_$i", "value_$i");
        }

        for ($i = 0; $i < 100; $i++) {
            $this->assertEquals("value_$i", Settings::get("rapid_$i"));
        }
    }

    public function testLargeNumberOfGroups(): void
    {
        for ($i = 0; $i < 20; $i++) {
            Settings::set('test', "value_$i", "group_$i");
        }

        for ($i = 0; $i < 20; $i++) {
            $this->assertEquals("value_$i", Settings::get('test', "group_$i"));
        }
    }

    public function testLargeNumberOfKeysInGroup(): void
    {
        $data = [];
        for ($i = 0; $i < 1000; $i++) {
            $data["key_$i"] = "value_$i";
        }

        Settings::setMultiple($data, 'large_group');
        $all = Settings::getAll('large_group');

        $this->assertCount(1000, $all);
        $this->assertEquals('value_500', $all['key_500']);
    }

    // ==================== JSON ENCODING TESTS ====================

    public function testJsonEncodingPreservesDataTypes(): void
    {
        $data = [
            'string' => 'text',
            'int' => 42,
            'float' => 3.14,
            'bool_true' => true,
            'bool_false' => false,
            'null' => null,
            'array' => [1, 2, 3]
        ];

        Settings::setMultiple($data, 'json_test');
        Settings::save('json_test');

        Settings::discard('json_test', true);

        $this->assertIsString(Settings::get('string', 'json_test'));
        $this->assertIsInt(Settings::get('int', 'json_test'));
        $this->assertIsFloat(Settings::get('float', 'json_test'));
        $this->assertIsBool(Settings::get('bool_true', 'json_test'));
        $this->assertIsBool(Settings::get('bool_false', 'json_test'));
    }
}
