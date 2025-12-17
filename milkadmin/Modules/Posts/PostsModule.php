<?php
namespace Modules\Posts;
use App\Abstracts\AbstractModule;

/**
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @license     MIT
 */

class PostsModule extends AbstractModule
{
    protected function configure($rule): void
    {
        $rule->page('posts')
             ->title('Posts')
             ->menu('Posts', '', 'bi bi-file-earmark-post-fill', 10)
             ->access('authorized')
             ->permissions(['access' => 'Access'])
             ->addModels(['comment'=>CommentsModel::class])
             ->version(251201);
    }

}