<?php
namespace Extensions\Projects\Classes\Renderers;

use App\Get;
use App\Response;
use Builders\TitleBuilder;
use Extensions\Projects\Classes\Module\{
    ActionContextRegistry,
    FkChainResolver,
    ModelRecordHelper,
    RecordTreeDeleter,
    UrlBuilder
};

!defined('MILK_DIR') && die();

/**
 * Handles list page presentation concerns: title/button HTML rendering,
 * record deletion, and shared normalization utilities.
 *
 * Used by AutoListRenderer to separate delete handling and title building
 * from the main list response construction logic.
 */
class ListPresenterHelper
{
    // ------------------------------------------------------------------
    // Title rendering
    // ------------------------------------------------------------------

    /**
     * Build separated title/body payload for offcanvas/modal list rendering.
     *
     * Returns the full TitleBuilder HTML as title, and the list content
     * (description + table html) as body.
     *
     * @param array  $response  The list response array from ListResponseBuilder.
     * @param string $tableId   The table DOM ID for reload targeting.
     * @return array{0:string,1:string}  [titleHtml, bodyHtml]
     */
    public function buildFetchTitleAndBody(array $response, string $tableId): array
    {
        $titleBuilder = $this->buildTitleBuilderFromResponse($response, $tableId);

        $titleHtml = $titleBuilder->render();

        $bodyHtml = '';
        $description = trim((string) ($response['description'] ?? ''));
        if ($description !== '') {
            $bodyHtml .= '<p class="text-body-secondary mb-3">' . _r($description) . '</p>';
        }
        $bodyHtml .= (string) ($response['html'] ?? '');

        return [$titleHtml, $bodyHtml];
    }

    /**
     * Build a stable DOM ID for the title element based on the table ID.
     *
     * @param string $tableId  The table DOM ID.
     * @return string  The title element ID (e.g. 'idTableModelTitle'), or empty string.
     */
    public function buildTitleId(string $tableId): string
    {
        $normalizedTableId = UrlBuilder::normalizeListId($tableId);
        return $normalizedTableId !== '' ? ($normalizedTableId . 'Title') : '';
    }

    /**
     * Build the inner HTML of the title element (without the outer wrapper).
     *
     * Used for AJAX title updates when a table refreshes via JSON.
     *
     * @param array  $response  The list response array.
     * @param string $tableId   The table DOM ID.
     * @return string  Rendered inner HTML of the title.
     */
    public function buildTitleInnerHtml(array $response, string $tableId): string
    {
        return $this->buildTitleBuilderFromResponse($response, $tableId)->renderInner();
    }

    /**
     * Construct a fully configured TitleBuilder from a list response array.
     *
     * Applies heading size, CSS classes, buttons (link and click), search area,
     * and bottom content based on the response payload.
     *
     * @param array  $response  The list response array.
     * @param string $tableId   The table DOM ID.
     * @return TitleBuilder  Configured title builder ready for rendering.
     */
    protected function buildTitleBuilderFromResponse(array $response, string $tableId): TitleBuilder
    {
        $headingSize = $this->normalizeHeadingSize((string) ($response['title_heading_size'] ?? 'h2'));
        $titleBuilder = TitleBuilder::create((string) ($response['title'] ?? ''))
            ->headingSize($headingSize)
            ->includeMessages(false);

        // Apply optional CSS classes.
        $titleClass = trim((string) ($response['title_class'] ?? ''));
        if ($titleClass !== '') {
            $titleBuilder->titleClass($titleClass);
        }
        $titleContainerClass = trim((string) ($response['title_container_class'] ?? ''));
        if ($titleContainerClass !== '') {
            $titleBuilder->containerClass($titleContainerClass);
        }
        if ($this->normalizeBool($response['title_small_buttons'] ?? false)) {
            $titleBuilder->smallButtons();
        }

        // Set DOM ID for AJAX updates.
        $titleId = $this->buildTitleId((string) ($response['table_id'] ?? $tableId));
        if ($titleId !== '') {
            $titleBuilder->setId($titleId);
        }

        // Add action buttons (link buttons and click buttons).
        $titleBtns = is_array($response['title_btns'] ?? null) ? $response['title_btns'] : [];
        foreach ($titleBtns as $btn) {
            if (!is_array($btn)) {
                continue;
            }
            $label = (string) ($btn['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $click = (string) ($btn['click'] ?? '');
            if ($click !== '') {
                $titleBuilder->addClickButton(
                    $label,
                    $click,
                    (string) ($btn['color'] ?? 'primary'),
                    (string) ($btn['class'] ?? ''),
                    $this->normalizeBool($btn['small'] ?? false)
                );
                continue;
            }
            $link = (string) ($btn['link'] ?? '');
            if ($link === '') {
                continue;
            }
            $titleBuilder->addButton(
                $label,
                $link,
                (string) ($btn['color'] ?? 'primary'),
                (string) ($btn['class'] ?? ''),
                isset($btn['fetch']) ? (string) $btn['fetch'] : null,
                '',
                $this->normalizeBool($btn['small'] ?? false)
            );
        }

        // Add search area or custom right content.
        if (isset($response['search_html']) && is_string($response['search_html']) && $response['search_html'] !== '') {
            $titleBuilder->addRightContent($response['search_html']);
        } else {
            $showSearch = !isset($response['show_search']) || (bool) $response['show_search'];
            if ($showSearch) {
                $searchTableId = (string) ($response['table_id'] ?? $tableId);
                if ($searchTableId !== '') {
                    $titleBuilder->addSearch($searchTableId, 'Search...', 'Search');
                }
            }
        }

        // Add optional bottom content below the title.
        if (isset($response['bottom_content']) && is_string($response['bottom_content']) && $response['bottom_content'] !== '') {
            $titleBuilder->addBottomContent($response['bottom_content']);
        }

        return $titleBuilder;
    }

    // ------------------------------------------------------------------
    // Delete handling
    // ------------------------------------------------------------------

    /**
     * Execute the delete action for one or more records.
     *
     * Resolves target IDs from the request or records payload, validates
     * context and model, then delegates to RecordTreeDeleter.
     *
     * @param mixed                 $records    Records payload from TableBuilder.
     * @param mixed                 $request    Request data from TableBuilder.
     * @param ActionContextRegistry $registry   The action context registry.
     * @param FkChainResolver       $fkResolver FK chain resolver for root ID lookup.
     * @return bool  True if deletion succeeded.
     */
    public function deleteRows(
        mixed $records,
        mixed $request,
        ActionContextRegistry $registry,
        FkChainResolver $fkResolver
    ): bool {
        $request = is_array($request) ? $request : [];

        // Resolve current form context.
        $context = $registry->resolveForCurrentRequest();
        if ($context === null) {
            \App\MessagesHandler::addError('No form context available for delete action.');
            return false;
        }
        if (!$this->isDataMutationAllowedForContext($context)) {
            \App\MessagesHandler::addError($this->buildDataMutationBlockedMessage($context, 'delete'));
            return false;
        }
        if (!$this->canManageDeleteRecordsForContext($context)) {
            \App\MessagesHandler::addError('You are not allowed to delete or restore records.');
            return false;
        }
        if ($this->isDeleteDisabledByConfig($context)) {
            \App\MessagesHandler::addError('You cannot remove this record: deletion is disabled in the form configuration.');
            return false;
        }

        // Validate model class.
        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            \App\MessagesHandler::addError('Model class not found for delete action.');
            return false;
        }

        // Validate primary key is safe for SQL usage.
        $model = new $modelClass();
        $primaryKey = (string) $model->getPrimaryKey();
        if ($primaryKey === '' || !ModelRecordHelper::isSafeSqlIdentifier($primaryKey)) {
            \App\MessagesHandler::addError('Invalid primary key for delete action.');
            return false;
        }

        // Resolve which record IDs to delete.
        $ids = $this->resolveDeleteTargetIds($request, $records, $primaryKey);
        $requestedRootId = $fkResolver->getRootIdFromRequest($context);

        if ($this->normalizeBool($context['soft_delete'] ?? false)) {
            return $this->softDeleteMany($model, $ids);
        }

        // Delegate to RecordTreeDeleter for cascading delete.
        $deleter = new RecordTreeDeleter($registry);
        return $deleter->deleteMany($context, $ids, $requestedRootId);
    }

    /**
     * Restore soft-deleted records by clearing deleted_at/deleted_by fields.
     *
     * @param mixed                 $records
     * @param mixed                 $request
     * @param ActionContextRegistry $registry
     */
    public function restoreRows(
        mixed $records,
        mixed $request,
        ActionContextRegistry $registry
    ): bool {
        $request = is_array($request) ? $request : [];

        $context = $registry->resolveForCurrentRequest();
        if ($context === null) {
            \App\MessagesHandler::addError('No form context available for restore action.');
            return false;
        }
        if (!$this->isDataMutationAllowedForContext($context)) {
            \App\MessagesHandler::addError($this->buildDataMutationBlockedMessage($context, 'restore'));
            return false;
        }
        if (!$this->canManageDeleteRecordsForContext($context)) {
            \App\MessagesHandler::addError('You are not allowed to delete or restore records.');
            return false;
        }

        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            \App\MessagesHandler::addError('Model class not found for restore action.');
            return false;
        }

        $model = new $modelClass();
        $primaryKey = (string) $model->getPrimaryKey();
        if ($primaryKey === '' || !ModelRecordHelper::isSafeSqlIdentifier($primaryKey)) {
            \App\MessagesHandler::addError('Invalid primary key for restore action.');
            return false;
        }

        if (!method_exists($model, 'getRules') || !method_exists($model, 'getByIdForEdit')) {
            \App\MessagesHandler::addError('Restore is not available for this model.');
            return false;
        }

        $rules = $model->getRules();
        if (!is_array($rules) || !isset($rules['deleted_at']) || !isset($rules['deleted_by'])) {
            \App\MessagesHandler::addError("Restore requires 'deleted_at' and 'deleted_by' fields in model rules.");
            return false;
        }

        $ids = $this->resolveDeleteTargetIds($request, $records, $primaryKey);
        if (empty($ids)) {
            \App\MessagesHandler::addError('No items selected.');
            return false;
        }

        foreach ($ids as $id) {
            $id = _absint($id);
            if ($id <= 0) {
                continue;
            }

            $record = $model->getByIdForEdit($id);
            if (!is_object($record) || $record->isEmpty()) {
                \App\MessagesHandler::addError("Record #{$id} not found.");
                return false;
            }

            $isDeleted = $this->hasNonEmptyDateValue($record->deleted_at ?? null);
            if (!$isDeleted) {
                continue;
            }

            $record->deleted_at = null;
            $record->deleted_by = null;

            if (!method_exists($record, 'save') || !$record->save()) {
                $error = method_exists($record, 'getLastError')
                    ? trim((string) $record->getLastError())
                    : '';
                if ($error === '' && method_exists($model, 'getLastError')) {
                    $error = trim((string) $model->getLastError());
                }
                if ($error === '') {
                    $error = "Unable to restore record #{$id}.";
                }

                \App\MessagesHandler::addError($error);
                return false;
            }
        }

        \App\MessagesHandler::addSuccess('Item restored successfully');
        return true;
    }

    /**
     * Permanently delete records and related children.
     *
     * @param mixed                 $records
     * @param mixed                 $request
     * @param ActionContextRegistry $registry
     * @param FkChainResolver       $fkResolver
     */
    public function hardDeleteRows(
        mixed $records,
        mixed $request,
        ActionContextRegistry $registry,
        FkChainResolver $fkResolver
    ): bool {
        $request = is_array($request) ? $request : [];

        $context = $registry->resolveForCurrentRequest();
        if ($context === null) {
            \App\MessagesHandler::addError('No form context available for delete action.');
            return false;
        }
        if (!$this->isDataMutationAllowedForContext($context)) {
            \App\MessagesHandler::addError($this->buildDataMutationBlockedMessage($context, 'hard delete'));
            return false;
        }
        if (!$this->canManageDeleteRecordsForContext($context)) {
            \App\MessagesHandler::addError('You are not allowed to delete or restore records.');
            return false;
        }
        if (!$this->isHardDeleteAllowedByConfig($context)) {
            \App\MessagesHandler::addError('You cannot permanently delete this record: hard delete is disabled in the form configuration.');
            return false;
        }
        if ($this->isDeleteDisabledByConfig($context)) {
            \App\MessagesHandler::addError('You cannot remove this record: deletion is disabled in the form configuration.');
            return false;
        }

        $modelClass = (string) ($context['model_class'] ?? '');
        if ($modelClass === '' || !class_exists($modelClass)) {
            \App\MessagesHandler::addError('Model class not found for delete action.');
            return false;
        }

        $model = new $modelClass();
        $primaryKey = (string) $model->getPrimaryKey();
        if ($primaryKey === '' || !ModelRecordHelper::isSafeSqlIdentifier($primaryKey)) {
            \App\MessagesHandler::addError('Invalid primary key for delete action.');
            return false;
        }

        $ids = $this->resolveDeleteTargetIds($request, $records, $primaryKey);
        if (empty($ids)) {
            \App\MessagesHandler::addError('No items selected.');
            return false;
        }

        $requestedRootId = $fkResolver->getRootIdFromRequest($context);
        $deleter = new RecordTreeDeleter($registry);
        return $deleter->deleteMany($context, $ids, $requestedRootId);
    }

    /**
     * Soft-delete records by filling deleted_at/deleted_by fields.
     *
     * @param object $model
     * @param array<int,int> $ids
     */
    protected function softDeleteMany(object $model, array $ids): bool
    {
        if (empty($ids)) {
            \App\MessagesHandler::addError('No items selected.');
            return false;
        }

        if (!method_exists($model, 'getRules') || !method_exists($model, 'getByIdForEdit')) {
            \App\MessagesHandler::addError('Soft delete is not available for this model.');
            return false;
        }

        $rules = $model->getRules();
        if (!is_array($rules) || !isset($rules['deleted_at']) || !isset($rules['deleted_by'])) {
            \App\MessagesHandler::addError("Soft delete requires 'deleted_at' and 'deleted_by' fields in model rules.");
            return false;
        }

        $deletedBy = null;
        try {
            $auth = Get::make('Auth');
            if (is_object($auth) && method_exists($auth, 'getUser')) {
                $user = $auth->getUser();
                if (is_object($user)) {
                    $userId = _absint($user->id ?? 0);
                    $deletedBy = $userId > 0 ? $userId : null;
                }
            }
        } catch (\Throwable) {
            $deletedBy = null;
        }

        $deletedAt = date('Y-m-d H:i:s');
        foreach ($ids as $id) {
            $id = _absint($id);
            if ($id <= 0) {
                continue;
            }

            $record = $model->getByIdForEdit($id);
            if (!is_object($record) || $record->isEmpty()) {
                \App\MessagesHandler::addError("Record #{$id} not found.");
                return false;
            }

            $alreadyDeleted = $this->hasNonEmptyDateValue($record->deleted_at ?? null);
            if ($alreadyDeleted) {
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
                    $error = "Unable to soft delete record #{$id}.";
                }

                \App\MessagesHandler::addError($error);
                return false;
            }
        }

        \App\MessagesHandler::addSuccess('Item moved to trash successfully');
        return true;
    }

    /**
     * Resolve the list of record IDs to delete from request or records payload.
     *
     * Checks request['table_ids'] first (comma-separated string or array),
     * then falls back to extracting IDs from the records payload.
     *
     * @param array  $request    Request data array.
     * @param mixed  $records    Records payload from TableBuilder.
     * @param string $primaryKey Primary key field name.
     * @return array<int>  Unique positive integer IDs to delete.
     */
    protected function resolveDeleteTargetIds(array $request, mixed $records, string $primaryKey): array
    {
        $ids = [];
        $tableIds = $request['table_ids'] ?? [];

        // Parse table_ids from string (comma-separated) or array format.
        if (is_string($tableIds)) {
            $parts = str_contains($tableIds, ',') ? explode(',', $tableIds) : [$tableIds];
            foreach ($parts as $part) {
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

        if (!empty($ids)) {
            return array_values(array_unique($ids));
        }

        // Edit form actions usually pass payload under $_REQUEST['data'].
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
        if (!empty($ids)) {
            return array_values(array_unique($ids));
        }

        // Fallback: extract IDs from records payload.
        foreach (ModelRecordHelper::extractRawRows($records) as $row) {
            $id = _absint(ModelRecordHelper::extractFieldValue($row, $primaryKey));
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    // ------------------------------------------------------------------
    // Shared normalization utilities
    // ------------------------------------------------------------------

    /**
     * Normalize a heading size string to an allowed value.
     *
     * Accepts h2, h3, h4, h5. Defaults to 'h2' for invalid input.
     *
     * @param string $size  Raw heading size string.
     * @return string  Normalized heading size ('h2'–'h5').
     */
    public function normalizeHeadingSize(string $size): string
    {
        $normalized = strtolower(trim($size));
        if (in_array($normalized, ['h2', 'h3', 'h4', 'h5'], true)) {
            return $normalized;
        }
        return 'h2';
    }

    /**
     * Normalize a mixed value to boolean.
     *
     * Accepts bool, int (1 = true), and string ('1', 'true', 'yes', 'on').
     *
     * @param mixed $value  The value to normalize.
     * @return bool
     */
    public function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        return in_array(
            strtolower(trim((string) $value)),
            ['1', 'true', 'yes', 'on'],
            true
        );
    }

    protected function hasNonEmptyDateValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if ($value instanceof \DateTimeInterface) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value !== '';
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return trim((string) $value) !== '';
            }
            return true;
        }

        if (is_array($value)) {
            return !empty($value);
        }

        return trim((string) $value) !== '';
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function isDataMutationAllowedForContext(array $context): bool
    {
        return $this->normalizeBool(
            $context['project_allows_data_mutation'] ?? ($context['projectAllowsDataMutation'] ?? true)
        );
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function buildDataMutationBlockedMessage(array $context, string $action): string
    {
        $statusRaw = strtolower(trim((string) ($context['project_status'] ?? ($context['projectStatus'] ?? 'development'))));
        $status = match ($statusRaw) {
            'active', 'production', 'prod' => 'active',
            'suspended', 'suspend', 'paused', 'pause' => 'suspended',
            'closed', 'close' => 'closed',
            default => 'development',
        };

        $action = strtolower(trim($action));
        if ($action === '') {
            $action = 'modify records';
        }

        return 'Cannot ' . $action . ': project status is ' . ucfirst($status) . '.';
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function canManageDeleteRecordsForContext(array $context): bool
    {
        if ($this->isCurrentUserAdministrator()) {
            return true;
        }

        $softDeleteEnabled = $this->normalizeBool($context['soft_delete'] ?? ($context['softDelete'] ?? false));
        if ($softDeleteEnabled) {
            return true;
        }

        if (array_key_exists('allow_delete_record', $context)) {
            return $this->normalizeBool($context['allow_delete_record']);
        }

        if (array_key_exists('allowDeleteRecord', $context)) {
            return $this->normalizeBool($context['allowDeleteRecord']);
        }

        return true;
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function isDeleteDisabledByConfig(array $context): bool
    {
        $softDeleteEnabled = $this->normalizeBool($context['soft_delete'] ?? ($context['softDelete'] ?? false));

        $allowDeleteRecord = true;
        if (array_key_exists('allow_delete_record', $context) || array_key_exists('allowDeleteRecord', $context)) {
            $allowDeleteRecord = $this->normalizeBool(
                $context['allow_delete_record'] ?? ($context['allowDeleteRecord'] ?? false)
            );
        }

        return !$softDeleteEnabled && !$allowDeleteRecord;
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function isHardDeleteAllowedByConfig(array $context): bool
    {
        if ($this->isCurrentUserAdministrator()) {
            return true;
        }

        if (array_key_exists('allow_delete_record', $context)) {
            return $this->normalizeBool($context['allow_delete_record']);
        }

        if (array_key_exists('allowDeleteRecord', $context)) {
            return $this->normalizeBool($context['allowDeleteRecord']);
        }

        return true;
    }

    protected function isCurrentUserAdministrator(): bool
    {
        try {
            $auth = Get::make('Auth');
            if (is_object($auth) && method_exists($auth, 'getUser')) {
                $user = $auth->getUser();
                if (is_object($user) && property_exists($user, 'is_admin')) {
                    return $this->normalizeBool($user->is_admin);
                }
            }
        } catch (\Throwable) {
            // Fallback to permission check.
        }

        try {
            return \App\Permissions::check('_user.is_admin');
        } catch (\Throwable) {
            return false;
        }
    }
}
