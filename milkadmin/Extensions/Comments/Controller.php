<?php
namespace Extensions\Comments;

use App\Abstracts\AbstractControllerExtension;
use App\Attributes\{RequestAction, AccessLevel};
use App\Response;

!defined('MILK_DIR') && die();

/**
 * Comments Controller Extension
 *
 * Provides controller actions for managing comments:
 * - Display comments in offcanvas
 * - Update comments table
 * - Edit/create comments via modal
 *
 * @package Extensions\Comments
 */
class Controller extends AbstractControllerExtension
{
    /**
     * Display comments offcanvas for an entity
     * Accessible via: ?page={module}&action=comments&entity_id={id}
     */
    #[RequestAction('comments')]
    public function viewComments()
    {
        $entity = $this->getEntityRecord();

        Response::json([
            'offcanvas_end' => [
                'title' => 'Comments',
                'body' => Service::getCommentsOffcanvasHtml($entity),
                'size' => 'lg'
            ]
        ]);
    }

    /**
     * Update comments table (AJAX)
     * Accessible via: ?page={module}&action=update-comment-table&entity_id={id}
     */
    #[RequestAction('update-comment-table')]
    public function updateCommentTable()
    {
        $entity = $this->getEntityRecord();
        $tableBuilder = Service::getCommentsTable($entity);
        $response = $tableBuilder->getResponse();

        // Reload the main entity list table if it exists
        $response['list'] = [
            "id" => "idTable" . ucfirst($this->module->get()->getPage()),
            "action" => "reload"
        ];

        Response::json($response);
    }

    /**
     * Edit/create comment form (modal)
     * Accessible via: ?page={module}&action=comment-edit&entity_id={id}[&id={comment_id}]
     */
    #[RequestAction('comment-edit')]
    public function commentEdit()
    {
        $entity = $this->getEntityRecord();
        $formBuilder = Service::getCommentForm($entity);
        Response::json($formBuilder->getResponse());
    }

    /**
     * Get the entity record from POST/REQUEST data
     * Validates that entity_id is provided and entity exists
     *
     * @return object Entity record
     */
    private function getEntityRecord()
    {
        $module = $this->module->get();
        $model = $module->getModel();

        return Service::getEntityRecord($model);
    }
}
