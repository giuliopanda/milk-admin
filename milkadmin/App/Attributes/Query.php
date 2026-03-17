<?php
namespace App\Attributes;

use Attribute;

/**
 * Query Attribute
 *
 * Marks a method as a named query scope that can be applied on-demand
 * to specific queries.
 *
 * Example usage:
 * ```php
 * #[Query('recent')]
 * protected function scopeRecent($query) {
 *     return $query->where('created_at > ?', [date('Y-m-d', strtotime('-30 days'))]);
 * }
 *
 * #[Query('ordered')]
 * protected function scopeOrdered($query) {
 *     return $query->order('name', 'ASC');
 * }
 * ```
 *
 * Usage:
 * ```php
 * // Apply named query to current query only
 * $model->withQuery('recent')->getAll();
 *
 * // Next query won't have 'recent' applied
 * $model->getAll();
 * ```
 *
 * The method must:
 * - Be protected or public
 * - Accept one parameter: Query $query
 * - Return the modified Query object
 *
 * Named queries are applied only when explicitly requested with withQuery()
 * and only affect the current query (temporary).
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Query
{
    /**
     * @var string The name of the query scope
     */
    public string $name;

    /**
     * Constructor
     *
     * @param string $name The unique name for this query scope
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
