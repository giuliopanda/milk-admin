<?php
namespace App\Exceptions;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Database Exception Class
 *
 * Exception thrown when database operations fail, particularly during connection.
 * This exception provides detailed error information about database failures.
 *
 * @example
 * ```php
 * try {
 *     $db = Get::db();
 * } catch (DatabaseException $e) {
 *     echo "Database error: " . $e->getMessage();
 *     // Log the error or handle it appropriately
 * }
 * ```
 *
 * @package App\Exceptions
 */
class DatabaseException extends \Exception
{
    /**
     * Database type (mysql or sqlite)
     *
     * @var string
     */
    protected string $dbType;

    /**
     * Connection parameters (sanitized)
     *
     * @var array
     */
    protected array $connectionParams;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param string $dbType Database type (mysql or sqlite)
     * @param array $connectionParams Connection parameters (passwords will be masked)
     * @param int $code Error code (default 0)
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        string $dbType = '',
        array $connectionParams = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->dbType = $dbType;

        // Sanitize connection params to hide passwords
        $this->connectionParams = $this->sanitizeParams($connectionParams);
    }

    /**
     * Get database type
     *
     * @return string Database type
     */
    public function getDbType(): string
    {
        return $this->dbType;
    }

    /**
     * Get sanitized connection parameters
     *
     * @return array Connection parameters with masked passwords
     */
    public function getConnectionParams(): array
    {
        return $this->connectionParams;
    }

    /**
     * Sanitize connection parameters to hide sensitive data
     *
     * @param array $params Connection parameters
     * @return array Sanitized parameters
     */
    protected function sanitizeParams(array $params): array
    {
        $sanitized = $params;

        // Mask password if present
        if (isset($sanitized['password']) && $sanitized['password'] !== '') {
            $sanitized['password'] = '***';
        }

        if (isset($sanitized['pass']) && $sanitized['pass'] !== '') {
            $sanitized['pass'] = '***';
        }

        return $sanitized;
    }

    /**
     * Get full error details as string
     *
     * @return string Formatted error details
     */
    public function getDetails(): string
    {
        $details = "Database Exception: " . $this->getMessage() . "\n";
        $details .= "Database Type: " . $this->dbType . "\n";

        if (!empty($this->connectionParams)) {
            $details .= "Connection Parameters:\n";
            foreach ($this->connectionParams as $key => $value) {
                $details .= "  - $key: " . (is_string($value) ? $value : json_encode($value)) . "\n";
            }
        }

        return $details;
    }
}
