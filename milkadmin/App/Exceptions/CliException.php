<?php
namespace App\Exceptions;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Base exception for CLI-related errors
 *
 * @package     App\Exceptions
 */
class CliException extends \Exception
{
}
