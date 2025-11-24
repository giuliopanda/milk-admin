<?php
namespace App\Exceptions;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Base exception for HttpClient-related errors
 *
 * Used for validation, configuration, and URL errors.
 *
 * @package App\Exceptions
 */
class HttpClientException extends \Exception
{
}
