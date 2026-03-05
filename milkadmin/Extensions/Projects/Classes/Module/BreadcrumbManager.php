<?php
namespace Extensions\Projects\Classes\Module;

use App\Theme;
use Builders\LinksBuilder;
use Extensions\Projects\Classes\ProjectNaming;

!defined('MILK_DIR') && die();

/**
 * Builds and applies header breadcrumbs for Projects extension pages.
 */
class BreadcrumbManager
{
    protected ActionContextRegistry $registry;
    protected FkChainResolver $fkResolver;

    public function __construct(ActionContextRegistry $registry, FkChainResolver $fkResolver)
    {
        $this->registry = $registry;
        $this->fkResolver = $fkResolver;
    }

    public function clear(): void
    {
        Theme::set('header.top-left', null);
    }

    public function apply(
        array $context,
        string $modulePage,
        string $actionType = 'list',
        int $recordId = 0,
        int $resolvedRootId = 0
    ): void {
        $rootFormName = $this->getRootFormName($context);
        if ($rootFormName === '') {
            $this->clear();
            return;
        }

        $rootListAction = ProjectNaming::toActionSlug($rootFormName) . '-list';
        $currentAction = trim((string) ($_REQUEST['action'] ?? ''));
        $isRootListDashboard = $actionType === 'list'
            && (bool) ($context['is_root'] ?? false)
            && $currentAction === $rootListAction;

        // On dashboard/root list page, header breadcrumb must be empty.
        if ($isRootListDashboard) {
            $this->clear();
            return;
        }

        $currentFormName = (string) ($context['form_name'] ?? '');
        $rootViewAction = $this->resolveRootViewAction($rootFormName);
        $hasRootView = ($rootViewAction !== '');
        $rootId = $this->resolveRootId($context, $recordId, $resolvedRootId);
        $currentChainParams = $this->fkResolver->getChainParams($context);

        $items = [[
            'label' => 'Dashboard',
            'url' => UrlBuilder::action($modulePage, $rootListAction),
        ]];

        if ($rootId > 0) {
            if ($hasRootView) {
                $items[] = [
                    'label' => 'Record ID: ' . $rootId,
                    'url' => UrlBuilder::action($modulePage, $rootViewAction, ['id' => $rootId]),
                    'disabled' => false,
                ];
            } else {
                $items[] = [
                    'label' => 'Record ID: ' . $rootId,
                    'url' => '#',
                    'disabled' => true,
                ];
            }
        }

        foreach ($this->buildFormTrail($context, $rootFormName, $hasRootView, $actionType) as $formName) {
            $listAction = ProjectNaming::toActionSlug($formName) . '-list';
            $targetContext = $this->registry->get($listAction);
            $params = is_array($targetContext)
                ? $this->buildChainParamsForTargetContext($targetContext, $currentChainParams, $rootId)
                : [];

            $items[] = [
                'label' => ProjectNaming::toTitle($formName),
                'url' => UrlBuilder::action($modulePage, $listAction, $params),
            ];
        }

        // Keep breadcrumb meaningful also for root edit pages without root view.
        if (count($items) === 1 && $currentFormName !== '') {
            $items[] = [
                'label' => ProjectNaming::toTitle($currentFormName),
                'url' => UrlBuilder::action($modulePage, $currentAction !== '' ? $currentAction : $rootListAction),
            ];
        }

        $links = LinksBuilder::create();
        foreach ($items as $item) {
            $links->add((string) $item['label'], (string) $item['url']);
            if ((bool) ($item['disabled'] ?? false)) {
                $links->disable();
            }
        }

        Theme::set('header.top-left', $links->render('breadcrumb'));
    }

    protected function getRootFormName(array $context): string
    {
        $ancestorFormNames = is_array($context['ancestor_form_names'] ?? null) ? $context['ancestor_form_names'] : [];
        if (!empty($ancestorFormNames)) {
            return (string) ($ancestorFormNames[0] ?? '');
        }
        return (bool) ($context['is_root'] ?? false) ? (string) ($context['form_name'] ?? '') : '';
    }

    protected function resolveRootViewAction(string $rootFormName): string
    {
        if ($rootFormName === '') {
            return '';
        }

        $rootListAction = ProjectNaming::toActionSlug($rootFormName) . '-list';
        $rootContext = $this->registry->get($rootListAction);
        if (!is_array($rootContext)) {
            return '';
        }

        return (string) ($rootContext['view_action'] ?? '');
    }

    /**
     * @return array<int,string>
     */
    protected function buildFormTrail(
        array $context,
        string $rootFormName,
        bool $hasRootView,
        string $actionType
    ): array {
        $trail = [];
        $ancestorFormNames = is_array($context['ancestor_form_names'] ?? null) ? $context['ancestor_form_names'] : [];
        $currentFormName = (string) ($context['form_name'] ?? '');
        $isRoot = (bool) ($context['is_root'] ?? false);

        foreach ($ancestorFormNames as $ancestorFormName) {
            $ancestorFormName = (string) $ancestorFormName;
            if ($ancestorFormName === '' || $ancestorFormName === $rootFormName) {
                continue;
            }
            $trail[] = $ancestorFormName;
        }

        if (!$isRoot && $currentFormName !== '' && $currentFormName !== $rootFormName) {
            $trail[] = $currentFormName;
        } elseif (!$hasRootView && $isRoot && $actionType !== 'list' && $currentFormName !== '') {
            $trail[] = $currentFormName;
        }

        return $trail;
    }

    protected function resolveRootId(array $context, int $recordId, int $resolvedRootId): int
    {
        if ($resolvedRootId > 0) {
            return $resolvedRootId;
        }

        if ((bool) ($context['is_root'] ?? false) && $recordId > 0) {
            return $recordId;
        }

        return $this->fkResolver->getRootIdFromRequest($context);
    }

    /**
     * @param array<string,int> $sourceChainParams
     * @return array<string,int>
     */
    protected function buildChainParamsForTargetContext(
        array $targetContext,
        array $sourceChainParams,
        int $rootId
    ): array {
        $params = [];
        $rootFkField = $this->fkResolver->getRootFkField($targetContext);

        foreach ($this->fkResolver->getChainFields($targetContext) as $fkField) {
            $value = _absint($sourceChainParams[$fkField] ?? 0);
            if ($value <= 0 && $rootId > 0 && $fkField === $rootFkField) {
                $value = $rootId;
            }
            if ($value > 0) {
                $params[(string) $fkField] = $value;
            }
        }

        return $params;
    }
}
