<?php
namespace Modules\Jobs;
use MilkCore\AbstractController;
use MilkCore\Get;
use MilkCore\Hooks;
use MilkCore\Cli;
use MilkCore\Theme;
use MilkCore\Route;

!defined('MILK_DIR') && die(); // Prevent direct access


class JobsController extends AbstractController {
    protected $page = 'jobs';
    protected $title = 'Manage Jobs';
    protected $access = 'authorized';
    protected $permission = ['manage' => 'Manage'];
    protected $menu_links = [
        ['url'=> '', 'name'=> 'Jobs List', 'icon'=> 'bi bi-clock', 'group'=> 'system', 'order'=> 80]
    ];

    public function bootstrap() {
        // Initialize the model
        $this->model = new JobsExecutionModel();
        // Set the model in the static classes
        JobsContract::set_model();     
        // It is no longer necessary to keep the model as an instance property
        // as it is now managed statically
    }

    /**
     * Initialize the jobs
     * 
     */
    public function jobs_init() {
        Get::dir_path(__DIR__ . '/assets/register-jobs.php');
    }
    
    /**
     * Initialize the module
     */
    public function init() {
        Theme::set('javascript', Route::url().'/modules/jobs/assets/jobs.js');
        Theme::set('header.breadcrumbs', 'Jobs <a class="link-action" href="'.Route::url('?page=docs&action=/modules/docs/pages/getting-started-cron.page').'">Help</a>');
        require_once __DIR__ . '/jobs.router.php';
        $this->router = new JobsRouter(); // Instantiate without arguments
        // start jobs-init
        Hooks::run('jobs-init'); 
        parent::init();
    }

    /**
     * This is when the cron is materially executed
     */
    public function jobs_start()
    {
        $jobs_contract = Get::make('jobs_contract');
        
        $now = new \DateTime();
        $model = new JobsExecutionModel();
        $pending_executions = $model->get_pending_executions();

        foreach ($pending_executions as $execution) {
            // check scheduled_at 
            if (is_null($execution->scheduled_at)) {
                continue;
            }
            if ($execution->scheduled_at <= $now) {
                if (!$jobs_contract::run($execution->jobs_name)) {
                    Cli::echo(date('Y-m-d H:i:s') . ' - Error executing job: ' . $execution->jobs_name. ' - ' . JobsContract::$last_error);   
                } else {
                    Cli::echo(date('Y-m-d H:i:s') . ' - Job executed successfully: ' . $execution->jobs_name);   
                }
            }
        }
    }

}

Hooks::set('modules_loaded', function() {
    Get::bind('jobs_contract', JobsContract::class);
    new JobsController();
});
