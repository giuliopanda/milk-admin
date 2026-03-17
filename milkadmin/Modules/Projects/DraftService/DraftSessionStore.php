<?php

namespace Modules\Projects\DraftService;

!defined('MILK_DIR') && die();

class DraftSessionStore
{
    private const SESSION_KEY = 'projects_form_fields_drafts';

    /**
     * @param array<string,mixed> $entry
     */
    public static function put(string $token, array $entry): void
    {
        self::ensureSession();
        $allDrafts = self::getAll();
        $allDrafts[$token] = $entry;
        $_SESSION[self::SESSION_KEY] = $allDrafts;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get(string $token): ?array
    {
        if (!self::isValidToken($token)) {
            return null;
        }
        self::ensureSession();
        $allDrafts = self::getAll();
        $entry = $allDrafts[$token] ?? null;
        return is_array($entry) ? $entry : null;
    }

    public static function remove(string $token): void
    {
        if (!self::isValidToken($token)) {
            return;
        }

        self::ensureSession();
        $allDrafts = self::getAll();
        unset($allDrafts[$token]);
        $_SESSION[self::SESSION_KEY] = $allDrafts;
    }

    public static function generateToken(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return strtolower(str_replace('.', '', uniqid('draft', true)));
        }
    }

    public static function isValidToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        return preg_match('/^[a-z0-9]{16,80}$/', $token) === 1;
    }

    /**
     * @return array<string,mixed>
     */
    private static function getAll(): array
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            return [];
        }

        $value = $_SESSION[self::SESSION_KEY] ?? [];
        return is_array($value) ? $value : [];
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }
}
