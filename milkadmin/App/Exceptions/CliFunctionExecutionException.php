<?php
namespace App\Exceptions;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Exception thrown when a CLI function execution fails
 *
 * @package     App\Exceptions
 */
class CliFunctionExecutionException extends CliException
{
}
