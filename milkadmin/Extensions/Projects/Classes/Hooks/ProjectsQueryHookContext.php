<?php
namespace Extensions\Projects\Classes\Hooks;

use Extensions\Projects\Classes\ProjectJsonStore;

!defined('MILK_DIR') && die();

/**
 * Normalized runtime context for Projects list/query hooks.
 *
 * This keeps the internal contract stable even if the underlying builder
 * stores hook data in a loose array.
 */
final class ProjectsQueryHookContext
{
    /**
     * @param array<string,mixed> $request
     * @param array<int|string,mixed> $modelColumns
     * @param array<string,mixed> $context
     * @param array<string,mixed>|null $manifest
     */
    public function __construct(
        public readonly string $page,
        public readonly string $tableId,
        public readonly array $request,
        public readonly object $query,
        public readonly mixed $model,
        public readonly array $modelColumns,
        public readonly array $context,
        public readonly int $rootId,
        public readonly ?array $manifest
    ) {
    }

    public static function fromBuilder(object $builder): ?self
    {
        if (!method_exists($builder, 'getHookContext')) {
            return null;
        }

        $context = $builder->getHookContext();
        if (!is_array($context)) {
            return null;
        }

        return self::fromArray($context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function fromArray(array $context): ?self
    {
        $query = $context['query'] ?? null;
        if (!is_object($query) || !method_exists($query, 'get') || !method_exists($query, 'getTotal')) {
            return null;
        }

        $page = trim((string) ($context['page'] ?? ''));
        $manifest = $page !== ''
            ? ProjectJsonStore::getCurrentManifestData($page)
            : ProjectJsonStore::getCurrentManifestData();
        if (!is_array($manifest)) {
            $manifest = null;
        }

        return new self(
            $page,
            trim((string) ($context['table_id'] ?? '')),
            is_array($context['request'] ?? null) ? $context['request'] : [],
            $query,
            $context['model'] ?? null,
            is_array($context['model_columns'] ?? null) ? $context['model_columns'] : [],
            is_array($context['context'] ?? null) ? $context['context'] : [],
            _absint($context['root_id'] ?? 0),
            $manifest
        );
    }

    /**
     * @return array{0:string,1:array<int|string,mixed>}
     */
    public function querySnapshot(bool $isTotal): array
    {
        $snapshot = $isTotal
            ? $this->query->getTotal()
            : $this->query->get();

        $sql = (string) ($snapshot[0] ?? '');
        $params = is_array($snapshot[1] ?? null) ? $snapshot[1] : [];

        return [$sql, $params];
    }

    /**
     * @return array<string,mixed>
     */
    public function toQueryPayload(bool $isTotal): array
    {
        [$sql, $params] = $this->querySnapshot($isTotal);

        return [
            'hook' => 'projects.query.before-execute',
            'is_total' => $isTotal,
            'query_type' => $isTotal ? 'total' : 'rows',
            'stage' => 'before_get_data',
            'page' => $this->page,
            'table_id' => $this->tableId,
            'request' => $this->request,
            'query' => $this->query,
            'sql' => $sql,
            'params' => $params,
            'manifest' => $this->manifest,
            'model' => $this->model,
            'model_columns' => $this->modelColumns,
            'context' => $this->context,
            'root_id' => $this->rootId,
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function toVisibilityPayload(array $data): array
    {
        return [
            'hook' => 'projects.data.after-extract-visibility',
            'stage' => 'after_get_data',
            'page' => $this->page,
            'table_id' => $this->tableId,
            'request' => $this->request,
            'query' => $this->query,
            'manifest' => $this->manifest,
            'model' => $this->model,
            'model_columns' => $this->modelColumns,
            'context' => $this->context,
            'root_id' => $this->rootId,
            'data' => $data,
        ];
    }
}
