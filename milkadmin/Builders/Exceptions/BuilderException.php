<?php
namespace Builders\Exceptions;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Builder Exception Class
 *
 * Exception thrown when builder operations fail due to improper usage,
 * particularly with the field-first pattern in TableBuilder/GetDataBuilder.
 *
 * @example
 * ```php
 * try {
 *     $table->label('My Label'); // Missing field() call
 * } catch (BuilderException $e) {
 *     echo "Builder error: " . $e->getMessage();
 * }
 * ```
 *
 * @package Builders\Exceptions
 */
class BuilderException extends \Exception
{
    /**
     * Create exception for missing field context
     *
     * @param string $method_name Name of the method that requires field context
     * @return static
     */
    public static function noCurrentField(string $method_name): self
    {
        return new self(
            "Method '{$method_name}()' requires field() to be called first. " .
            "Use: \$builder->field('column_name')->{$method_name}(...)"
        );
    }

    /**
     * Create exception for invalid field name
     *
     * @param string $field_name Invalid field name
     * @return static
     */
    public static function invalidField(string $field_name): self
    {
        return new self(
            "Invalid field name: '{$field_name}'. Field name cannot be empty."
        );
    }

    /**
     * Create exception for method chaining violation
     *
     * @param string $context Additional context about the violation
     * @return static
     */
    public static function chainingViolation(string $context = ''): self
    {
        $message = "Builder method chaining violation.";
        if ($context !== '') {
            $message .= " {$context}";
        }
        return new self($message);
    }
}
