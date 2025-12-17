<?php
namespace Modules\Posts;
use App\Abstracts\AbstractController;
use App\Response;
use App\Attributes\RequestAction;
use Builders\{TableBuilder, SearchBuilder};

class PostsController extends AbstractController
{
    #[RequestAction('home')]
    public function postsList() {
        $response = $this->getCommonData();

        // Optional: SearchBuilder
        $search_html = SearchBuilder::create('idTablePosts')
            ->addSearch()->render();

        $response['search_html'] = $search_html;
        $response['link_action_edit'] = 'edit';
        $response['table_id'] = 'idTablePosts';

        // Generate table in separate method
        $response = array_merge($response, $this->getTableResponse());

        Response::render(__DIR__ . '/Views/list_page.php', $response);
    }

    private function getTableResponse() {
        return TableBuilder::create($this->model, 'idTablePosts')
            ->field('title')
                ->link('?page=posts&action=edit&id=%id%')
            ->field('content')
                ->truncate(50)
            ->setDefaultActions()
            ->getResponse();
    }
}
