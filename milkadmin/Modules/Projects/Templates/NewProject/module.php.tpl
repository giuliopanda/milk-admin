<?php
namespace Local\Modules\{{MODULE_NAME}};

use App\Abstracts\AbstractModule;
use App\Attributes\RequestAction;
use App\Response;
use Modules\Projects\ProjectMenuService;

!defined('MILK_DIR') && die();

class {{MODULE_NAME}}Module extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('{{MODULE_PAGE}}')
            ->title('{{PROJECT_TITLE_PHP}}')
            ->menu('{{MENU_TITLE}}', '', 'bi bi-folder2-open', 200)
            ->access('authorized')
            ->extensions(['Projects'])
            ->version('1.0.0');

        ProjectMenuService::applyFromManifest($rule, __DIR__);
    }

    #[RequestAction('home')]
    public function home(): void
    {
        $projectsExtension = $this->getLoadedExtensions('Projects');
        if (is_object($projectsExtension) && method_exists($projectsExtension, 'getPrimaryFormLink')) {
            $link = $projectsExtension->getPrimaryFormLink();
            $action = $link['action'];
            \App\Route::redirect('?page=' . $this->page . '&action=' . _r((string) $action));
        } else {
            Response::themePage('default', '<div class="alert alert-danger mt-3">'
                . '<strong>Manifest errors</strong>'
                . '<ul class="mb-0">Please contact support.</ul>'
                . '</div>');
        }
    }
}
