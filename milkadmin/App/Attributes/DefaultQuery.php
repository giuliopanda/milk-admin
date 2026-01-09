<?php
namespace App\Attributes;

use Attribute;

/**
 * DefaultQuery Attribute
 *
 * Marks a method as a default query scope that will be automatically applied
 * to all SELECT queries on this model.
 *
 * Example usage:
 * ```php
 * #[DefaultQuery]
 * protected function onlyActive($query) {
 *     return $query->where('status = ?', ['active']);
 * }
 * ```
 *
 * The method must:
 * - Be protected or public
 * - Accept one parameter: Query $query
 * - Return the modified Query object
 *
 * Default queries are applied automatically to all queries unless explicitly
 * disabled with withoutGlobalScope() or withoutGlobalScopes().
 */
#[Attribute(Attribute::TARGET_METHOD)]
class DefaultQuery
{
    /**
     * Constructor
     *
     * No parameters needed - the method name will be used as the scope name
     */
    public function __construct()
    {
        // No parameters needed
    }
}
