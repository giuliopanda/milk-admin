<?php
namespace Modules\Posts;
use App\Abstracts\AbstractController;
use App\{Response, Route};
use App\Attributes\AccessLevel;
use App\Attributes\RequestAction;
use Builders\{ListBuilder, FormBuilder, TableBuilder};

class PostsController extends AbstractController
{ 
    #[RequestAction('home')]
    public function postsList() {
        $tableBuilder = TableBuilder::create($this->model, 'idTablePosts')
            ->field('content')->truncate(50)
            ->field('title')->link('?page=posts&action=edit&id=%id%')
            ->field('comments')->label('Comments')->fn(function($row) {
                return count($row->comments);
            })
            ->addAction('comment',  [
                'label' => 'Comments',
                'link' => '?page=posts&action=comments&post_id=%id%'
            ])
            ->setDefaultActions();
        $response = array_merge($this->getCommonData(), $tableBuilder->getResponse());
        $response['title_btns'] = [['label'=>'Add New', 'link'=>'?page=posts&action=edit']];
        Response::render(MILK_DIR . '/Theme/SharedViews/list_page.php', $response);
    }

    #[RequestAction('edit')]
    public function postEdit() {
        $response = $this->getCommonData();
        $response['form'] = FormBuilder::create($this->model, $this->page)
        ->getForm();
        $response['title'] = ($_REQUEST['id'] ?? 0) > 0 ? 'Edit Post' : 'Add Post';
        Response::render(MILK_DIR . '/Theme/SharedViews/edit_page.php', $response);
    }
    
    #[RequestAction('comments')]
    public function postComments() {
        $response = $this->getCommonData();
        $post_id = $this->requirePostId();
        $model = $this->getAdditionalModels('comment');
        $post = $this->model->getById($post_id);
        $tableBuilder = TableBuilder::create($model, 'idTableComments')
            ->where('post_id = ?', [$post_id])
            ->addAction('edit', ['label'=>'Edit', 'link'=>"?page=posts&action=comment-edit&id=%id%&post_id=".$post_id])
            ->customData('post_id', $post_id);
        // Handle comments listing/editing for a specific post
        $response = array_merge($response, $tableBuilder->getResponse());
        $response['title_btns'] = [['label'=>'Add New', 'link'=>'?page=posts&action=comment-edit&post_id='.$post_id], ['label'=>'Go Back', 'link'=>'?page=posts']];
        $response['title'] = 'Post Comments';
        $response['description'] = 'Comments for post ' . $post->title;
        Response::render(MILK_DIR . '/Theme/SharedViews/list_page.php', $response);
    }

    #[RequestAction('comment-edit')]
    public function commentEdit() {
        $post_id = $this->requirePostId();
        $model = $this->getAdditionalModels('comment');
        $response = $this->getCommonData();
        $response['form'] = FormBuilder::create($model, $this->page, '?page=posts&action=comments&post_id=' . $post_id)
            ->customData('post_id', $post_id)
            ->addStandardActions(true, '?page=posts&action=comments&post_id=' . $post_id)
            ->field('post_id')
            ->value($post_id)
            ->readonly()
            ->field('comment')->required()
            ->getForm();
        $response['title'] = ($_REQUEST['id'] ?? 0) > 0 ? 'Edit Comment' : 'Add Comment';
        Response::render(MILK_DIR . '/Theme/SharedViews/edit_page.php', $response);
    }

    private function requirePostId(): int {
        $post_id = ($_REQUEST['post_id'] ?? '0');
        if ($post_id == 0) {
            if (Response::isJson()) {
                Response::json(['success'=>false, 'msg' => 'No post ID provided']);
            } else {
                Route::redirectError('?page=posts', 'No post ID provided');
            }
        }
        return (int)$post_id;
    }
    
}
