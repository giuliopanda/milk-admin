<?php
namespace App\Exceptions;

!defined('MILK_DIR') && die();

/**
 * Base exception for File class errors
 *
 * Used for validation, access permission, and file operation errors.
 *
 * @package App\Exceptions
 */
class FileException extends \Exception
{
}
