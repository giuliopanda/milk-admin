<?php
namespace Extensions\Projects\Classes\Module;

use App\Abstracts\AbstractModule;
use App\Get;
use App\Hooks;
use App\MessagesHandler;
use App\Response;
use App\Route;
use App\Token;
use Extensions\Projects\Classes\ProjectJsonStore;
use Extensions\Projects\Classes\ProjectNaming;

!defined('MILK_DIR') && die();

class ProjectDeleteService
{
    protected AbstractModule $module;
    protected ActionContextRegistry $registry;
    protected FkChainResolver $fkResolver;
    protected ProjectPermissionService $permissionService;

    public function __construct(
        AbstractModule $module,
        ActionContextRegistry $registry,
        FkChainResolver $fkResolver,
        ProjectPermissionService $permissionService
    ) {
        $this->module = $module;
        $this->registry = $registry;
        $this->fkResolver = $fkResolver;
        $this->permissionService = $permissionService;
    }

    public function renderAutoDeletePage(): void
    {
        $context = $this->registry->resolveForCurrentRequest();
        if ($context === null) {
            MessagesHandler::addError('No form context available for delete action.');
            Route::redirect(['page' => $this->module->getPage()]);
        }
        if (!$this->permissionService->canManageDeleteRecordsForContext($context)) {
            $msg = 'You are not allowed to delete or restore records.';
            MessagesHandler::addError($msg);
            if (Response::isJson()) {
                Response::json([
                    'success' => false,
                    'msg' => $msg,
                ]);
                return;
            }
            Route::redirect(['page' => $this->module->getPage()]);
        }

        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            MessagesHandler::addError('Model class not found for delete action.');
            Route::redirect(['page' => $this->module->getPage()]);
        }

        $model = new $modelClass();
        $primaryKey = (string) $model->getPrimaryKey();
        if ($primaryKey === '') {
            MessagesHandler::addError('Invalid primary key for delete action.');
            Route::redirect(['page' => $this->module->getPage()]);
        }

        $id = _absint($_REQUEST['id'] ?? 0);
        if ($id <= 0) {
            MessagesHandler::addError('Missing record id for delete action.');
            Route::redirect(['page' => $this->module->getPage()]);
        }

        if (!$this->isValidDeleteConfirmation($context, $id)) {
            $msg = MessagesHandler::errorsToString();
            if ($msg === '') {
                $msg = 'Delete confirmation required.';
            }
            if (Response::isJson()) {
                Response::json([
                    'success' => false,
                    'msg' => $msg,
                ]);
                return;
            }
            Route::redirect(['page' => $this->module->getPage()]);
        }

        $requestedRootId = ProjectJsonStore::normalizeBool($context['is_root'] ?? false)
            ? $id
            : $this->fkResolver->getRootIdFromRequest($context);

        $ok = $this->executeDeleteForContext($context, [$id], $requestedRootId);
        $redirect = $this->buildDeleteRedirectUrl($context);

        if (Response::isJson()) {
            $msg = $ok ? MessagesHandler::successToString() : MessagesHandler::errorsToString();
            if (trim($msg) === '') {
                $msg = $ok ? 'Item deleted successfully' : 'Unable to delete item';
            }

            $response = [
                'success' => $ok,
                'msg' => $msg,
            ];
            if ($ok) {
                $response['modal'] = ['action' => 'hide'];
                $response['redirect'] = Route::url($redirect);
            }
            Response::json($response);
            return;
        }

        Route::redirect($redirect);
    }

    public function renderAutoDeleteConfirmPage(): void
    {
        $context = $this->registry->resolveForCurrentRequest();
        if ($context === null) {
            Response::json(['success' => false, 'msg' => 'No form context available for delete action.']);
            return;
        }
        if (!$this->permissionService->canManageDeleteRecordsForContext($context)) {
            Response::json(['success' => false, 'msg' => 'You are not allowed to delete or restore records.']);
            return;
        }

        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            Response::json(['success' => false, 'msg' => 'Model class not found for delete action.']);
            return;
        }

        $id = _absint($_REQUEST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['success' => false, 'msg' => 'Missing record id for delete action.']);
            return;
        }

        $model = new $modelClass();
        $record = $model->getByIdForEdit($id);
        if (!is_object($record) || $record->isEmpty()) {
            Response::json(['success' => false, 'msg' => "Record #{$id} not found."]);
            return;
        }

        $hookPayload = $this->runRecordMutationHook(
            'projects.record.delete.before-action',
            $context,
            [$id],
            [
                'id' => $id,
                'stage' => 'before_confirm',
                'record' => $record,
            ]
        );
        if (($hookPayload['allowed'] ?? true) !== true) {
            $message = trim((string) ($hookPayload['message'] ?? 'You are not allowed to delete this record.'));
            Response::json(['success' => false, 'msg' => $message]);
            return;
        }

        $deleteAction = (string) ($context['delete_action'] ?? '');
        if ($deleteAction === '') {
            Response::json(['success' => false, 'msg' => 'Delete action is not enabled for this form.']);
            return;
        }

        $modulePage = $this->module->getPage();
        $chainParams = $this->fkResolver->getChainParams($context);
        $deleteParams = array_merge(['id' => $id], $chainParams);
        $deleteUrl = Route::url(UrlBuilder::action($modulePage, $deleteAction, $deleteParams));

        $tokenName = $this->buildDeleteConfirmationTokenName($context, $id);
        $tokenValue = Token::get($tokenName);
        $modalBody = $this->buildDeleteConfirmationModalBody($context, $id, $deleteUrl, $tokenName, $tokenValue);

        Response::json([
            'success' => true,
            'modal' => [
                'title' => 'Confirm Delete',
                'body' => $modalBody,
                'size' => 'sm',
                'action' => 'show',
            ],
        ]);
    }

    public function delete(mixed $records = null, array $request = []): bool
    {
        $context = $this->registry->resolveForCurrentRequest();
        if ($context === null) {
            MessagesHandler::addError('No form context available for delete action.');
            return false;
        }
        if (!$this->permissionService->isDataMutationAllowedForContext($context)) {
            MessagesHandler::addError($this->permissionService->buildDataMutationBlockedMessage($context, 'delete'));
            return false;
        }
        if (!$this->permissionService->canManageDeleteRecordsForContext($context)) {
            MessagesHandler::addError('You are not allowed to delete or restore records.');
            return false;
        }

        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            MessagesHandler::addError('Model class not found for delete action.');
            return false;
        }

        $model = new $modelClass();
        $primaryKey = (string) $model->getPrimaryKey();
        if ($primaryKey === '' || !ModelRecordHelper::isSafeSqlIdentifier($primaryKey)) {
            MessagesHandler::addError('Invalid primary key for delete action.');
            return false;
        }

        $ids = $this->resolveDeleteTargetIds($request, $records, $primaryKey);
        $requestedRootId = $this->fkResolver->getRootIdFromRequest($context);

        return $this->executeDeleteForContext($context, $ids, $requestedRootId);
    }

    /**
     * @param array<string,mixed> $context
     * @param array<int,int> $ids
     */
    protected function executeDeleteForContext(array $context, array $ids, int $requestedRootId): bool
    {
        if (!$this->permissionService->isDataMutationAllowedForContext($context)) {
            MessagesHandler::addError($this->permissionService->buildDataMutationBlockedMessage($context, 'delete'));
            return false;
        }
        if (!$this->permissionService->canManageDeleteRecordsForContext($context)) {
            MessagesHandler::addError('You are not allowed to delete or restore records.');
            return false;
        }
        if ($ids === []) {
            MessagesHandler::addError('No items selected.');
            return false;
        }
        $hookPayload = $this->runRecordMutationHook(
            'projects.record.delete.before-action',
            $context,
            $ids,
            [
                'root_id' => $requestedRootId,
                'stage' => 'before_action',
            ]
        );
        if (($hookPayload['allowed'] ?? true) !== true) {
            MessagesHandler::addError(trim((string) ($hookPayload['message'] ?? 'You are not allowed to delete this record.')));
            return false;
        }
        if ($this->permissionService->isDeleteDisabledByConfig($context)) {
            MessagesHandler::addError('You cannot remove this record: deletion is disabled in the form configuration.');
            return false;
        }

        $softDeleteEnabled = ProjectJsonStore::normalizeBool($context['soft_delete'] ?? false);
        if ($softDeleteEnabled) {
            if (!$this->permissionService->ensureConfiguredPermission(
                $context,
                'soft_delete',
                'You are not allowed to move records to trash.'
            )) {
                return false;
            }

            return $this->softDeleteMany($context, $ids);
        }

        if (!$this->permissionService->ensureConfiguredPermission(
            $context,
            'hard_delete',
            'You are not allowed to permanently delete records.'
        )) {
            return false;
        }

        $deleter = new RecordTreeDeleter($this->registry);
        return $deleter->deleteMany($context, $ids, $requestedRootId);
    }

    /**
     * @param array<string,mixed> $context
     * @param array<int,int> $ids
     */
    protected function softDeleteMany(array $context, array $ids): bool
    {
        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            MessagesHandler::addError('Model class not found for delete action.');
            return false;
        }

        $model = new $modelClass();
        if (!method_exists($model, 'getRules') || !method_exists($model, 'getByIdForEdit')) {
            MessagesHandler::addError('Soft delete is not available for this model.');
            return false;
        }

        $rules = $model->getRules();
        if (!is_array($rules) || !isset($rules['deleted_at']) || !isset($rules['deleted_by'])) {
            MessagesHandler::addError("Soft delete requires 'deleted_at' and 'deleted_by' fields in model rules.");
            return false;
        }

        $deletedBy = null;
        try {
            $auth = Get::make('Auth');
            if (is_object($auth) && method_exists($auth, 'getUser')) {
                $user = $auth->getUser();
                if (is_object($user)) {
                    $uid = _absint($user->id ?? 0);
                    $deletedBy = $uid > 0 ? $uid : null;
                }
            }
        } catch (\Throwable) {
            $deletedBy = null;
        }

        $deletedAt = date('Y-m-d H:i:s');
        foreach ($ids as $id) {
            $resolvedId = _absint($id);
            if ($resolvedId <= 0) {
                continue;
            }

            $record = $model->getByIdForEdit($resolvedId);
            if (!is_object($record) || $record->isEmpty()) {
                MessagesHandler::addError("Record #{$resolvedId} not found.");
                return false;
            }

            if ($this->permissionService->hasNonEmptyDateValue($record->deleted_at ?? null)) {
                continue;
            }

            $record->deleted_at = $deletedAt;
            $record->deleted_by = $deletedBy;

            if (!method_exists($record, 'save') || !$record->save()) {
                $error = method_exists($record, 'getLastError')
                    ? trim((string) $record->getLastError())
                    : '';
                if ($error === '' && method_exists($model, 'getLastError')) {
                    $error = trim((string) $model->getLastError());
                }
                if ($error === '') {
                    $error = "Unable to soft delete record #{$resolvedId}.";
                }

                MessagesHandler::addError($error);
                return false;
            }
        }

        MessagesHandler::addSuccess('Item moved to trash successfully');
        return true;
    }

    /**
     * @param array<string,mixed> $context
     * @param array<int,int> $ids
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    protected function runRecordMutationHook(string $hook, array $context, array $ids, array $request = []): array
    {
        $payload = [
            'hook' => $hook,
            'page' => $this->module->getPage(),
            'context' => $context,
            'request' => $request,
            'record_ids' => array_values(array_unique(array_map('_absint', $ids))),
            'root_id' => _absint($request['root_id'] ?? 0),
            'allowed' => true,
        ];

        $result = Hooks::run($hook, $payload);
        return is_array($result) ? $result : $payload;
    }

    /**
     * @return array<int,int>
     */
    protected function resolveDeleteTargetIds(array $request, mixed $records, string $primaryKey): array
    {
        $ids = [];
        $tableIds = $request['table_ids'] ?? [];

        if (is_string($tableIds)) {
            foreach (str_contains($tableIds, ',') ? explode(',', $tableIds) : [$tableIds] as $part) {
                $id = _absint($part);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } elseif (is_array($tableIds)) {
            foreach ($tableIds as $part) {
                $id = _absint($part);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        if ($ids !== []) {
            return array_values(array_unique($ids));
        }

        $requestCandidates = [$request];
        if (is_array($request['data'] ?? null)) {
            $requestCandidates[] = $request['data'];
        }

        foreach ($requestCandidates as $candidate) {
            foreach ([$primaryKey, 'id'] as $key) {
                if (!array_key_exists($key, $candidate)) {
                    continue;
                }
                $id = _absint($candidate[$key]);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        if ($ids !== []) {
            return array_values(array_unique($ids));
        }

        foreach (ModelRecordHelper::extractRawRows($records) as $row) {
            $id = _absint(ModelRecordHelper::extractFieldValue($row, $primaryKey));
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    protected function buildDeleteRedirectUrl(array $context): string
    {
        $modulePage = $this->module->getPage();
        $listAction = (string) ($context['list_action'] ?? '');
        $isRoot = ProjectJsonStore::normalizeBool($context['is_root'] ?? false);

        if ($listAction === '') {
            return '?page=' . rawurlencode($modulePage);
        }
        if ($isRoot) {
            return UrlBuilder::action($modulePage, $listAction);
        }

        $params = $this->fkResolver->getChainParamsForParent($context);
        return UrlBuilder::action($modulePage, $listAction, $params);
    }

    protected function isValidDeleteConfirmation(array $context, int $id): bool
    {
        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($requestMethod !== 'POST') {
            MessagesHandler::addError('Delete confirmation required.');
            return false;
        }

        $confirmed = in_array(
            strtolower(trim((string) ($_REQUEST['projects_delete_confirmation'] ?? ''))),
            ['1', 'true', 'yes', 'on'],
            true
        );
        if (!$confirmed) {
            MessagesHandler::addError('Delete confirmation required.');
            return false;
        }

        $postedId = _absint($_REQUEST['projects_delete_id'] ?? 0);
        if ($postedId <= 0 || $postedId !== $id) {
            MessagesHandler::addError('Invalid delete request id.');
            return false;
        }

        $tokenValue = (string) ($_REQUEST['projects_delete_token'] ?? '');
        if ($tokenValue === '') {
            MessagesHandler::addError('Missing delete confirmation token.');
            return false;
        }

        $expectedTokenName = $this->buildDeleteConfirmationTokenName($context, $id);
        if (!Token::checkValue($tokenValue, $expectedTokenName)) {
            $tokenError = Token::$last_error !== '' ? Token::$last_error : 'invalid_token';
            MessagesHandler::addError('Invalid or expired delete token (' . $tokenError . ').');
            return false;
        }

        return true;
    }

    protected function buildDeleteConfirmationTokenName(array $context, int $id): string
    {
        $formName = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower((string) ($context['form_name'] ?? 'record'))) ?? '';
        if ($formName === '') {
            $formName = 'record';
        }

        return 'projects_delete_' . $formName . '_' . max(0, $id);
    }

    protected function buildDeleteConfirmationModalBody(
        array $context,
        int $id,
        string $deleteUrl,
        string $tokenName,
        string $tokenValue
    ): string {
        $formTitle = trim((string) ($context['form_title'] ?? ''));
        if ($formTitle === '') {
            $formTitle = ProjectNaming::toTitle((string) ($context['form_name'] ?? 'Record'));
        }
        if ($formTitle === '') {
            $formTitle = 'Record';
        }
        $softDeleteEnabled = ProjectJsonStore::normalizeBool($context['soft_delete'] ?? false);

        ob_start();
        include dirname(__DIR__, 2) . '/Views/delete_confirmation_modal.php';
        return (string) ob_get_clean();
    }
}
