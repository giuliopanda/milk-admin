<?php

declare(strict_types=1);

namespace App;

!defined('MILK_DIR') && die();

use DateTime;
use Exception;

/** @phpstan-consistent-constructor */
class Request
{
    /** @var array<string, mixed> */
    protected array $query;

    /** @var array<string, mixed> */
    protected array $request;

    /** @var array<string, mixed> */
    protected array $files;

    /** @var array<string, mixed> */
    protected array $server;

    /** @var array<string, mixed> */
    protected array $cookies;

    /** @var array<string, mixed>|null */
    protected ?array $json = null;

    /**
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>|null $request
     * @param array<string, mixed>|null $files
     * @param array<string, mixed>|null $server
     * @param array<string, mixed>|null $cookies
     */
    public function __construct(
        ?array $query = null,
        ?array $request = null,
        ?array $files = null,
        ?array $server = null,
        ?array $cookies = null
    ) {
        $this->query = $query ?? $_GET;
        $this->request = $request ?? $_POST;
        $this->files = $files ?? $_FILES;
        $this->server = $server ?? $_SERVER;
        $this->cookies = $cookies ?? $_COOKIE;
    }

    public static function capture(): static
    {
        return new static();
    }

    /**
     * Return all GET + POST data.
     * POST values override GET values if keys overlap.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $data = array_merge($this->query, $this->request);

        if ($this->isJson()) {
            $data = array_merge($data, $this->json());
        }

        if ($key === null) {
            return $data;
        }

        return $this->dataGet($data, $key, $default);
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->dataGet($this->query, $key, $default);
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->request;
        }

        return $this->dataGet($this->request, $key, $default);
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function file(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->files;
        }

        return $this->dataGet($this->files, $key, $default);
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->cookies;
        }

        return $this->dataGet($this->cookies, $key, $default);
    }

    public function has(string|array $keys): bool
    {
        foreach ((array) $keys as $key) {
            if ($this->input($key) === null) {
                return false;
            }
        }

        return true;
    }

    public function filled(string|array $keys): bool
    {
        foreach ((array) $keys as $key) {
            $value = $this->input($key);

            if ($value === null || $this->isEmptyString($value)) {
                return false;
            }
        }

        return true;
    }

    public function missing(string|array $keys): bool
    {
        return !$this->has($keys);
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $results = [];

        foreach ($keys as $key) {
            $value = $this->input($key);

            if ($value !== null) {
                $this->dataSet($results, $key, $value);
            }
        }

        return $results;
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        $data = $this->input();

        if (!is_array($data)) {
            return [];
        }

        foreach ($keys as $key) {
            $this->dataForget($data, $key);
        }

        return $data;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        if (is_array($value) || is_object($value)) {
            return $default;
        }

        return trim((string) $value);
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return $default;
        }

        return (int) $value;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key);

        if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
            return $default;
        }

        return (float) $value;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        $result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $result ?? $default;
    }

    /**
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->input($key);

        return is_array($value) ? $value : $default;
    }

    public function date(string $key, ?string $format = null, ?DateTime $default = null): ?DateTime
    {
        $value = $this->input($key);

        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        if ($format !== null) {
            $date = DateTime::createFromFormat($format, $value);

            return $date instanceof DateTime ? $date : $default;
        }

        try {
            return new DateTime($value);
        } catch (Exception) {
            return $default;
        }
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }

    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function header(string $key, mixed $default = null): mixed
    {
        $normalizedKey = strtoupper(str_replace('-', '_', $key));

        if ($normalizedKey === 'CONTENT_TYPE' || $normalizedKey === 'CONTENT_LENGTH') {
            return $this->server[$normalizedKey] ?? $default;
        }

        return $this->server['HTTP_' . $normalizedKey] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');

        if (!is_string($header) || $header === '') {
            return null;
        }

        if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    public function ip(): ?string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? null;

        return is_string($ip) ? $ip : null;
    }

    public function userAgent(): ?string
    {
        $userAgent = $this->server['HTTP_USER_AGENT'] ?? null;

        return is_string($userAgent) ? $userAgent : null;
    }

    public function isJson(): bool
    {
        $contentType = $this->server['CONTENT_TYPE'] ?? '';

        return is_string($contentType)
            && str_contains(strtolower($contentType), 'application/json');
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        $raw = file_get_contents('php://input');

        if (!is_string($raw) || trim($raw) === '') {
            $this->json = [];
            return $this->json;
        }

        $decoded = json_decode($raw, true);

        $this->json = is_array($decoded) ? $decoded : [];

        return $this->json;
    }

    public function sanitizeString(string $key, string $default = ''): string
    {
        $value = $this->string($key, $default);
        $sanitized = filter_var($value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);

        return is_string($sanitized) ? $sanitized : $default;
    }

    public function sanitizeEmail(string $key, string $default = ''): string
    {
        $value = $this->string($key, $default);
        $sanitized = filter_var($value, FILTER_SANITIZE_EMAIL);

        return is_string($sanitized) && $sanitized !== '' ? $sanitized : $default;
    }

    public function sanitizeUrl(string $key, string $default = ''): string
    {
        $value = $this->string($key, $default);
        $sanitized = filter_var($value, FILTER_SANITIZE_URL);

        return is_string($sanitized) && $sanitized !== '' ? $sanitized : $default;
    }

    protected function isEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) === '';
    }

    /**
     * @param array<string, mixed> $data
     * @param mixed $default
     * @return mixed
     */
    protected function dataGet(array $data, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        if (!str_contains($key, '.')) {
            return $default;
        }

        $current = $data;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $data
     * @param mixed $value
     */
    protected function dataSet(array &$data, string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $temp = &$data;

        foreach ($segments as $index => $segment) {
            $isLast = $index === array_key_last($segments);

            if ($isLast) {
                $temp[$segment] = $value;
                return;
            }

            if (!isset($temp[$segment]) || !is_array($temp[$segment])) {
                $temp[$segment] = [];
            }

            /** @var array<string, mixed> $next */
            $next = &$temp[$segment];
            $temp = &$next;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function dataForget(array &$data, string $key): void
    {
        $segments = explode('.', $key);
        $temp = &$data;

        while (count($segments) > 1) {
            $segment = array_shift($segments);

            if (!isset($temp[$segment]) || !is_array($temp[$segment])) {
                return;
            }

            /** @var array<string, mixed> $next */
            $next = &$temp[$segment];
            $temp = &$next;
        }

        $last = array_shift($segments);

        if ($last !== null) {
            unset($temp[$last]);
        }
    }
}
