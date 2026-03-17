<?php

namespace App;

!defined('MILK_DIR') && die();

/**
 * Logging System Class
 *
 * Provides a flexible in-memory logging system that organizes logs into groups,
 * supports different message severity levels, and optionally tracks the call stack
 * that generated each log entry.
 *
 * Features:
 * - Log grouping by category (SYSTEM, DATABASE, API, etc.)
 * - Severity levels with filtering support
 * - Optional backtrace tracking for debugging
 * - Hook integration for log interception
 * - Export capabilities (JSON)
 *
 * @example Basic usage
 * ```php
 * // Simple log entry
 * Logs::set('SYSTEM', 'Application started', Logs::INFO);
 *
 * // Error logging
 * Logs::set('DATABASE', 'Connection failed: timeout', Logs::ERROR);
 *
 * // Using shortcut methods
 * Logs::error('AUTH', 'Invalid credentials for user: john.doe');
 * Logs::debug('CACHE', 'Cache miss for key: user_123');
 * ```
 *
 * @example Configuration
 * ```php
 * // In production, disable DEBUG logs and backtrace for performance
 * Logs::configure([
 *     'min_level' => Logs::WARNING,
 *     'enable_backtrace' => false
 * ]);
 * ```
 *
 * @package App
 * @version 2.0.0
 */
class Logs
{
    public const INFO    = 'INFO';
    public const ERROR   = 'ERROR';
    public const WARNING = 'WARNING';
    public const DEBUG   = 'DEBUG';
    public const FATAL   = 'FATAL';
    public const SUCCESS = 'SUCCESS';

    /** @var array<string, int> Priority levels (lower = more critical) */
    private static array $levels = [
        self::FATAL   => 0,
        self::ERROR   => 1,
        self::WARNING => 2,
        self::SUCCESS => 3,
        self::INFO    => 4,
        self::DEBUG   => 5,
    ];

    /** @var array<string, array> Log storage organized by group */
    private static array $logs = [];

    /** @var int Minimum level to record (default: all) */
    private static int $min_level = 5;

    /** @var bool Enable backtrace collection */
    private static bool $enable_backtrace = true;

    /**
     * Configure logging options.
     *
     * @param array $options ['min_level' => Logs::WARNING, 'enable_backtrace' => false]
     */
    public static function configure(array $options): void
    {
        if (isset($options['min_level'], self::$levels[$options['min_level']])) {
            self::$min_level = self::$levels[$options['min_level']];
        }
        if (isset($options['enable_backtrace'])) {
            self::$enable_backtrace = (bool) $options['enable_backtrace'];
        }
    }

    /**
     * Add a log entry to a group.
     *
     * @param string $group    Log group (SYSTEM, DATABASE, API, etc.)
     * @param string $msg      Message to log
     * @param string $msg_type Severity level (use class constants)
     */
    public static function set(string $group, string $msg = '', string $msg_type = self::INFO): void
    {
        $level = self::$levels[$msg_type] ?? self::$levels[self::INFO];
        if ($level > self::$min_level) {
            return;
        }

        $in = self::$enable_backtrace ? self::getBacktrace() : [];

        if (!isset(self::$logs[$group])) {
            self::$logs[$group] = [];
        }

        $log_entry = [
            'msgType'   => $msg_type,
            'msg'       => $msg,
            'time'      => date('YmdHis'),
            'in'        => $in,
        ];

        self::$logs[$group][] = $log_entry;

        \App\Hooks::run('after_log_set', $group, $log_entry);
    }

    /**
     * Extract backtrace information.
     *
     * @return array<string> Array of "file:line" strings
     */
    private static function getBacktrace(): array
    {
        $debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $debug = array_slice($debug, 2);

        $in = [];
        foreach ($debug as $d) {
            if (isset($d['file'])) {
                $in[] = $d['file'] . (isset($d['line']) ? ':' . $d['line'] : '');
            }
        }
        return $in;
    }

    /**
     * Get all log entries for a group.
     *
     * @param string $group Group name
     * @return array Log entries
     */
    public static function get(string $group): array
    {
        return self::$logs[$group] ?? [];
    }

    /**
     * Get all logs from all groups.
     *
     * @return array All logs organized by group
     */
    public static function getAll(): array
    {
        return self::$logs;
    }

    /**
     * Get all ERROR level logs.
     *
     * @return array Error log entries
     */
    public static function getAllErrors(): array
    {
        return self::getByType(self::ERROR);
    }

    /**
     * Get all logs of a specific type.
     *
     * @param string $msg_type Message type to filter
     * @return array Matching log entries
     */
    public static function getByType(string $msg_type): array
    {
        $result = [];
        foreach (self::$logs as $group_logs) {
            foreach ($group_logs as $log) {
                if ($log['msgType'] === $msg_type) {
                    $result[] = $log;
                }
            }
        }
        return $result;
    }

    /**
     * Count log entries with optional filters.
     *
     * @param string|null $group    Filter by group
     * @param string|null $msg_type Filter by type
     * @return int Count of matching entries
     */
    public static function count(?string $group = null, ?string $msg_type = null): int
    {
        $count = 0;
        $groups = $group !== null ? [$group => self::$logs[$group] ?? []] : self::$logs;

        foreach ($groups as $group_logs) {
            foreach ($group_logs as $log) {
                if ($msg_type === null || $log['msgType'] === $msg_type) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Clear logs from memory.
     *
     * @param string|null $group Group to clear, or null for all
     */
    public static function clear(?string $group = null): void
    {
        if ($group === null) {
            self::$logs = [];
        } else {
            unset(self::$logs[$group]);
        }
    }

    /**
     * Export logs to JSON.
     *
     * @param string|null $group Group to export, or null for all
     * @return string JSON encoded logs
     */
    public static function toJson(?string $group = null): string
    {
        $data = $group !== null ? self::get($group) : self::$logs;
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sanitize string for log storage.
     *
     * @param mixed $string Value to sanitize
     * @return string Sanitized string
     */
    public static function cleanStr(mixed $string): string
    {
        if (is_array($string) || is_object($string)) {
            return json_encode($string, JSON_UNESCAPED_UNICODE);
        }
        $string = str_replace(['\\', "\n", "\r", '"', '  '], ['\\\\', '', '', '\"', ' '], (string) $string);
        $string = trim($string);
        if (str_contains($string, ' ')) {
            $string = '"' . $string . '"';
        }
        return $string;
    }

    /**
     * Reverse cleanStr() sanitization.
     *
     * @param string $string Sanitized string
     * @return string Original string
     */
    public static function logStr(string $string): string
    {
        return stripslashes(trim(trim($string), '"'));
    }

    // Shortcut methods
    public static function info(string $group, string $msg): void    {
         self::set($group, $msg, self::INFO); 
    }
    public static function error(string $group, string $msg): void   { 
        self::set($group, $msg, self::ERROR);
    }
    public static function warning(string $group, string $msg): void { 
        self::set($group, $msg, self::WARNING); 
    }
    public static function debug(string $group, string $msg): void   {
        self::set($group, $msg, self::DEBUG);
    }
    public static function fatal(string $group, string $msg): void   {
        self::set($group, $msg, self::FATAL);
    }
    public static function success(string $group, string $msg): void {
        self::set($group, $msg, self::SUCCESS); 
    }
}
