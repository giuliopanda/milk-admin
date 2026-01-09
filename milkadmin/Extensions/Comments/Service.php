<?php
namespace Extensions\Comments;

use Builders\{TableBuilder, FormBuilder, TitleBuilder, SearchBuilder};
use App\Response;
use App\Abstracts\AbstractModel;

!defined('MILK_DIR') && die();

/**
 * Comments Service
 *
 * Provides shared logic for building comments UI:
 * - Offcanvas with comments list
 * - Comments table with edit/delete actions
 * - Comment edit form
 *
 * @package Extensions\Comments
 */
class Service
{
    /**
     * Get entity record from POST/REQUEST data
     * Validates that entity_id is provided and entity exists
     *
     * @param AbstractModel $model Parent entity model
     * @return AbstractModel Entity record
     */
    public static function getEntityRecord(AbstractModel $model): AbstractModel
    {
        $entity_id = $_POST['data']['entity_id'] ?? $_REQUEST['entity_id'] ?? 0;

        if ($entity_id == 0) {
            Response::json(['success' => false, 'msg' => 'Entity ID not provided']);
        }

        // Get entity record
        $entity = $model->getById($entity_id);
        if ($entity->isEmpty()) {
            Response::json(['success' => false, 'msg' => 'Entity not found']);
        }

        return $entity;
    }

    /**
     * Build offcanvas HTML with comments list
     *
     * @param AbstractModel $entity Parent entity record
     * @return string HTML content for offcanvas
     */
    public static function getCommentsOffcanvasHtml(AbstractModel $entity): string
    {
        $primary_id = $entity->getPrimaryKey();
        $entity_id_value = $entity->$primary_id;

        // Get entity display name (try common fields)
        $entity_name = $entity->name ?? $entity->title ?? $entity->label ?? "Entity #{$entity_id_value}";

        $title = TitleBuilder::create($entity_name)
            ->addButton(
                'New Comment',
                '?page=' . self::getPageFromRequest() . '&action=comment-edit&entity_id=' . $entity_id_value,
                'primary',
                '',
                'post'
            );

        $search = SearchBuilder::create('idTableComments')
            ->search('search')
            ->layout('full-width')
            ->placeholder('Type to search...');

        $table = self::getCommentsTable($entity);

        return $title->render() . '<br>' . $search->render() . '<br>' . $table->render();
    }

    /**
     * Build comments table
     *
     * @param AbstractModel $entity Parent entity record
     * @return TableBuilder Table builder instance
     */
    public static function getCommentsTable(AbstractModel $entity): TableBuilder
    {
        $primary_id = $entity->getPrimaryKey();
        $entity_id_value = $entity->$primary_id;
        $page = self::getPageFromRequest();

        $commentsModel = new CommentsModel();

        return TableBuilder::create($commentsModel, 'idTableComments')
            ->activeFetch()
            ->setRequestAction('update-comment-table')
            ->where('entity_id = ?', [$entity_id_value])
            ->field('comment')->truncate(100)
            ->field('created_by')
            ->field('created_at')
            ->field('updated_by')
            ->field('updated_at')
            ->addAction('edit', [
                'label' => 'Edit',
                'link' => '?page=' . $page . '&action=comment-edit&entity_id=' . $entity_id_value . '&id=%id%',
            ])
            ->addAction('delete', [
                'label' => 'Delete',
                'action' => [self::class, 'deleteComment'],
                'class' => 'link-action-danger',
                'confirm' => 'Are you sure you want to delete this comment?',
                
            ])
            ->customData('entity_id', $entity_id_value);
    }

    /**
     * Build comment edit/create form
     *
     * @param AbstractModel $entity Parent entity record
     * @return FormBuilder Form builder instance
     */
    public static function getCommentForm(AbstractModel $entity): FormBuilder
    {
        $primary_id = $entity->getPrimaryKey();
        $entity_id_value = $entity->$primary_id;
        $page = self::getPageFromRequest();

        $commentsModel = new CommentsModel();

        return FormBuilder::create($commentsModel, $page)
            ->activeFetch()
            ->asModal()
            ->customData('entity_id', $entity_id_value)
            ->setTitle('New Comment', 'Edit Comment')
            ->dataListId('idTableComments')
            ->field('entity_id')->value($entity_id_value)->readonly()
            ->field('comment')->required()
            ->setActions([
                'save' => [
                    'label' => 'Save',
                    'class' => 'btn btn-primary',
                    'action' => FormBuilder::saveAction()
                ]
            ]);
    }

    /**
     * Delete comment action callback
     *
     * @param object $record Comment record to delete
     * @param array $request Request data
     * @return array Response with success/message
     */
    public static function deleteComment($record, $request): array
    {
        if ($record->delete($record->id)) {
            return ['success' => true, 'message' => 'Comment deleted successfully'];
        }
        return ['success' => false, 'message' => 'Delete failed'];
    }

    /**
     * Get page name from request
     *
     * @return string Page name
     */
    private static function getPageFromRequest(): string
    {
        return $_REQUEST['page'] ?? '';
    }
}
