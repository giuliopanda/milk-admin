<?php
namespace Modules\Posts;
use MilkCore\AbstractController;
use MilkCore\Lang;
use MilkCore\Theme;
use MilkCore\Route;
use MilkCore\Cli;
use MilkCore\Hooks;

!defined('MILK_DIR') && die(); // Prevents direct access

/**
 * This is the file that is loaded at the beginning of the module
 * The class automatically manages menus, shells through the shell_* functions
 * and hooks. So in general all events excluding those of the router.
 * 
 * 
 * @package     Modules
 * @subpackage  posts
 * @author      Giulio Pandolfelli <giuliopanda@gmail.com>
 * @copyright   2025 Giulio Pandolfelli
 * @license     MIT
 * @version     1.0.0
 */


class Posts extends AbstractController
{
    /**
     * The page to which this controller is assigned
     */
    protected $page = 'posts';
    /**
     * Title of the module
     */
    protected $title = 'Posts';
    /**
     * Access Level: public, registered, authorized, admin
     */
    protected $access = 'registered';
    /**
     * Menu link
     */
    protected $menu_links = [
        ['url'=> '', 'name'=> 'Posts', 'icon'=> 'bi bi-file-earmark-post-fill', 'order'=> 100]
    ];
   
    /**
     * The init method is called when you are inside the post pages i.e. when $_REQUEST['page'] = $this->page
     */
    public function init() {
        Theme::set('javascript', Route::url().'/modules/posts/assets/posts.js');
        Lang::load_ini_file(__DIR__.'/assets/translation.ini', 'posts');
        parent::init();
    }
    /**
     * This function is called only when the module is being used (so inside pages, but also inside the shell or APIs or other hooks).
     */
    public function bootstrap() {
        // There is no need to require files because there is a built-in automatic lazy loading system, which calls files only when they need to be used.
        // require_once __DIR__ . '/posts.model.php';
        // require_once __DIR__ . '/posts.router.php';
        // require_once __DIR__ . '/posts.object.php';
        $this->model = new PostsModel();
        $this->router = new PostsRouter();
    }

    /**
     * This is a test function for the shell
     * From the shell you can call it with the following command:
     * php cli.php posts:test
     */
    public function shell_test() {
        Cli::success("It's working!");
    }
}

/**
* The class must not be called directly, but inside the modules_loaded hook.
* This allows you to manage the order of loading the classes
*/
Hooks::set('modules_loaded', function() {
    new Posts();
});
