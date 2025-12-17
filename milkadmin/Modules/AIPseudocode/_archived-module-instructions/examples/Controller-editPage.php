<?php
namespace Modules\LinksData;
use App\Abstracts\AbstractController;
use App\Response;
use App\Attributes\RequestAction;
use Builders\FormBuilder;

class LinksDataController extends AbstractController
{
    #[RequestAction('edit')]
    public function linkEdit()
    {
        $response = $this->getCommonData();

        $response['form'] = FormBuilder::create($this->model, $this->page)
            ->getForm();

        Response::render(__DIR__ . '/Views/edit_page.php', $response);
    }
}
