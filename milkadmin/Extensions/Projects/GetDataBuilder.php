<?php
namespace Extensions\Projects;

use App\Abstracts\AbstractGetDataBuilderExtension;
use App\Hooks;
use Extensions\Projects\Classes\Hooks\ProjectsQueryHookContext;

!defined('MILK_DIR') && die();

/**
 * Projects GetDataBuilder extension.
 *
 * Emits hook payloads for:
 * - pre-execution list query inspection/mutation (rows + total)
 * - post-extraction row visibility checks
 */
class GetDataBuilder extends AbstractGetDataBuilderExtension
{
    public function beforeGetData(): void
    {
        $context = $this->resolveProjectsHookContext();
        if ($context === null) {
            return;
        }

        $this->emitQueryHook($context, false);
        $this->emitQueryHook($context, true);
    }

    public function afterGetData(array $data): array
    {
        $context = $this->resolveProjectsHookContext();
        if ($context === null) {
            return $data;
        }

        $payload = $context->toVisibilityPayload($data);

        $hookResult = Hooks::run(
            'projects.data.after-extract-visibility',
            $payload,
            $data,
            $context->page,
            $context->tableId
        );

        if (is_array($hookResult) && isset($hookResult['data']) && is_array($hookResult['data'])) {
            return $hookResult['data'];
        }

        return $data;
    }

    /**
     * @return ProjectsQueryHookContext|null
     */
    protected function resolveProjectsHookContext(): ?ProjectsQueryHookContext
    {
        return ProjectsQueryHookContext::fromBuilder($this->builder);
    }

    protected function emitQueryHook(ProjectsQueryHookContext $context, bool $isTotal): void
    {
        Hooks::run(
            'projects.query.before-execute',
            $context->toQueryPayload($isTotal),
            $context->query,
            $isTotal,
            $context->page,
            $context->tableId
        );
    }
}
